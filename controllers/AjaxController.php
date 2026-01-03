<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\helpers\Json;
use app\models\Moysklad;
use app\models\Reports;
use app\models\Accountment;
use app\models\Website;
use app\models\ReportMarketingTable;
use app\models\ReportIncomeBrandsOutlaysTable;
use app\models\CpcProjectsTable;
use app\models\CpcProjectsDataTable;
use app\models\OrdersConfigTable;
use yii\web\UploadedFile;

ini_set('max_execution_time', 3000);

class AjaxController extends Controller
{
    // Метод для обработки AJAX-запросов
    public function actionProcess()
    {
      Yii::$app->response->format = Response::FORMAT_JSON;

        // Проверка CSRF токена
        if (Yii::$app->request->isPost && Yii::$app->request->validateCsrfToken()) {
          $actAjax = true;
        }
        else {
          $actAjax = false;
        }

        if($actAjax){
          $postData = Yii::$app->request->post();

          switch($postData['action']){
            // Бухгалтерия
            case 'postBankAccounts': // Разнесение платежей из банков
              $model = new Accountment();

              $uploadedFile = UploadedFile::getInstanceByName('post-file');
              if (!$uploadedFile) {
                return ['success' => false, 'error' => '<div class="alert alert-danger p-2 mt-3"><small>Файл не получен</div>'];
              }

              if (!in_array($uploadedFile->extension, ['xls', 'xlsx'])) {
                return ['success' => false, 'error' => '<div class="alert alert-danger p-2 mt-3"><small>Допустимы только файлы .xls и .xlsx</div>'];
              }

              $newFileName  = time() . '.' . $uploadedFile->extension;
              $saveDir      = Yii::getAlias('@app/runtime/uploads/');
              $savePath     = $saveDir . $newFileName;

              if ($uploadedFile->saveAs($savePath)) {
                $postPaymentsList = $model->postAccountInformation($savePath,$postData['bank'],$postData['organization']);

                return ['success' => true, 'message' => '<div class="alert alert-success p-2 mt-3"><small>Внесены измененения в платежи в кодами - ' . implode(', ',$postPaymentsList) . '.<br/><br/>Так же добавлены исходящие платежи для Комиссий и Доставки.</small></div>'];
              }
              else{
                return ['success' => false, 'error' => '<div class="alert alert-danger p-2 mt-3"><small>Ошибка при сохранении файла</div>'];
              }

              break;

            // Отчеты
            case 'createSalesReport': // Отчет продажи
              $model = new Reports();
              parse_str($postData['formData'],$formData);
              $period = explode(' - ', $formData['period']);

              $periodFrom = new \DateTime($period[0]);
              $periodFrom->modify('-1 days');
              $periodTo   = new \DateTime($period[1]);

              $dateFrom = new \DateTime($periodFrom->format('Y-m-d') . ' 21:00:00');
              $dateTo = new \DateTime($periodTo->format('Y-m-d') . ' 20:59:59');

              $link = $model->createFullMSSalesReport($dateFrom,$dateTo);

              return '<div class="alert alert-info p-2 mt-3 text-center alert-download-file"><a class="text-info" href="https://service.accio.kz/tmpDocs/' . $link->file . '" target="_blank">Загрузить отчет по продажам</a></div>';
              break;
            case 'createBuyesReport': // Отчет закупки
              $model = new Reports();
              parse_str($postData['formData'],$formData);

              $category   = $formData['category'];
              $startDate  = new \DateTime($formData['start-date'] . ' ' . date('H:i:s'));
              $waitDays = (!empty($formData['wait-date'])) ? (int)$formData['wait-date'] : 0;
              $deliveryDays = (!empty($formData['delivery-date'])) ? (int)$formData['delivery-date'] : 0;
              $gholdDays = (!empty($formData['goods-hold-date'])) ? (int)$formData['goods-hold-date'] : 0;


              $link = $model->createBuyingReport($startDate,$category,$waitDays,$deliveryDays,$gholdDays);

              return '<div class="alert alert-info p-2 mt-3 text-center alert-download-file"><a class="text-info" href="https://service.accio.kz/tmpDocs/' . $link->file . '" target="_blank">Загрузить отчет по закупкам</a></div>';
              break;
            case 'createMovesReport': // Отчет перемещения
              $model = new Reports();
              parse_str($postData['formData'],$formData);

              $startDate  = new \DateTime($formData['start-date'] . ' ' . date('H:i:s'));

              $link = $model->createMSMovesReport($startDate);

              return '<div class="alert alert-info p-2 mt-3 text-center alert-download-file"><a class="text-info" href="https://service.accio.kz/tmpDocs/' . $link->file . '">Загрузить отчет по перемещениям</a></div>';
              break;
            case 'createComissionerReport': // Отчет комиссионера
              $model = new Reports();
              parse_str($postData['formData'],$formData);

              $date = new \DateTime($formData['start-date']);

              $link = $model->createComissionerReport($date);

              return '<div class="alert alert-info p-2 mt-3 text-center alert-download-file"><a class="text-info" href="https://service.accio.kz/tmpDocs/' . $link->file . '">Загрузить отчет комиссионера</a></div>';
              break;
            case 'getIncomeBrandYearData': // Получение сохраненных затрат года
              setlocale(LC_TIME, 'ru_RU.UTF-8');
              $modelTable = new ReportIncomeBrandsOutlaysTable();
              $year       = (int)$postData['actionData']['year'];
              $outlays    = $modelTable->getIncomeBrandsMonthOfYearOutlays($year);

              $response = '<hr/>
                           <div class="monthes-values">
                            <div>
                              <h3 class="mt-3 mb-1">Затраты с инвестициями</h3>';
              foreach (range(1,12) as $month) {
                $inValue = '';
                foreach ($outlays as $outlay) {
                  if($outlay['month'] == $month AND $outlay['type'] === 0){
                    $inValue = $outlay['value'];
                  }
                }
                $response .= '<div class="form-group month">
                                <label class="text-muted mb-2" for="ib_month_0_' . $month . '">' . strftime('%B', mktime(0, 0, 0, $month, 1)) . '</label>
                                <input type="text" class="digits-field" value="' . $inValue . '" id="ib_month_0_' . $month . '" name="ib_month_0_' . $month . '" />
                              </div>';
              }
              $response .= '</div>';

              $response .= '<div>
                              <h3 class="mt-5 mb-1">Затраты без инвестиций</h3>';
              foreach (range(1,12) as $month) {
                $inValue = '';
                foreach ($outlays as $outlay) {
                  if($outlay['month'] == $month AND $outlay['type'] === 1){
                    $inValue = $outlay['value'];
                  }
                }
                $response .= '<div class="form-group month">
                                <label class="text-muted mb-2" for="ib_month_1_' . $month . '">' . strftime('%B', mktime(0, 0, 0, $month, 1)) . '</label>
                                <input type="text" class="digits-field" value="' . $inValue . '" id="ib_month_1_' . $month . '" name="ib_month_1_' . $month . '" />
                              </div>';
              }
              $response .= '</div>';
              $response .= '</div>';
              $response .= '<div class="form-group submits">
                              <button type="button" class="btn btn-sm btn-dark me-4 reset">Сбросить</button>
                              <button type="submit" class="btn btn-sm btn-dark">Сохранить и создать отчет</button>
                            </div>';

              return $response;
              break;
            case 'createIncomeBrandsReport': // Отчет прибыльность по брендам
              $model = new Reports();
              $modelTable = new ReportIncomeBrandsOutlaysTable();
              parse_str($postData['formData'],$formData);

              $year  = (int)$formData['date-year'];

              // Сохраняем данные затрат в году/месяце
              $monthsValues = [];
              foreach ($formData as $key => $value) {
                  if (preg_match('/^ib_month_(\d+)_(\d{1,2})$/', $key, $matches)) {
                      $type = (int)$matches[1];
                      $month = (int)$matches[2];

                      if (trim($value) !== '') {
                          $monthsValues[] = [
                              'type' => $type,
                              'month' => $month,
                              'value' => (float)str_replace(',','.',$value),
                          ];
                      }
                  }
              }
              $modelTable->setYearMonthsValues($year,$monthsValues);

              // Создаем отчет
              $link = $model->createIncomeBrandsReport($year);

              if(!$link){
                return '<div class="alert alert-danger p-2 mt-4 text-center">Произошла какая-то ошибка.</div>';
              }
              else {
                return '<div class="alert alert-info p-2 mt-3 text-center alert-download-file"><a class="text-info" href="https://service.accio.kz/tmpDocs/' . $link->file . '" target="_blank">Загрузить отчет прибыльности по брендам</a></div>';
              }
              break;
            case 'removeMarketingMonthData': // Удаление данных месяц год для отчета
              $mreport = new ReportMarketingTable();
              $date = new \DateTime($postData['actionData']['date']);

              if($mreport->removeMonthYearData($date)){
                $deleted = true;
                $message = '<div class="alert alert-success p-2">Данные за период ' . $date->format('m.Y') . ' удалены.</div>';
              }
              else {
                $deleted = false;
                $message = '<div class="alert alert-warning p-2">Произошла ошибка удаления данных за период ' . $date->format('m.Y') . '. Свяжитесь с разработчиком.</div>';
              }

              return array('deleted' => $deleted, 'message' => $message);
              break;
            case 'getMarketingReportData': // Данные месяц год для отчета
              $model = new Reports();
              $mreport = new ReportMarketingTable();
              parse_str($postData['formData'],$formData);

              $date = new \DateTime($formData['add-data-date']);

              $data = $mreport->getMarketingData($date);

              $fields = $mreport->getMarketingDataArray();
              $periods = $mreport->separateFillingPeriodsForFields();
              $weeksCount = $mreport->getWeeksFromFirstWednesday($date);

              $groups = [];
              foreach ($fields as $name => $label) {
                $groupName = explode(":", $label)[0];
                $groups[$groupName][] = [
                                          'name' => $name,
                                          'label' => $label,
                                          'fieldName' => trim(explode(":", $label)[1]),
                                          'period' => $periods[$name] ?? 'monthly'
                                        ];
              }

              $html = '<form id="marketing-data-issue-form">';
              $html .= '<h2 class="d-flex">Данные за ' . $date->format('m.Y') . '<button type="button" class="remove-month-data btn btn-sm btn-danger ms-auto">Удалить данные за месяц</button></h2>';
              foreach ($groups as $groupName => $groupFields) {
                  $html .= "<h3 class='mb-3'>{$groupName}</h3>";
                  $html .= "<div class='form-group mb-5 d-flex flex-wrap justify-content-between'>";

                  // Генерация полей для каждой группы
                  foreach ($groupFields as $field) {
                      $fieldValue = isset($data[$field['name']]) ? $data[$field['name']] : '';

                      $html .= "<div class='form-group mb-3 col-5'>";
                      $html .= "<label class='text-muted mb-2' for='{$field['name']}'>{$field['fieldName']}</label>";

                      if ($field['period'] === 'weekly') {
                          $html .= "<div class='weekly-row'>";
                          $weekValues = explode(':::', $fieldValue);

                          for ($week = 1; $week <= count($weeksCount); $week++) {
                            $weekStart = new \DateTime($weeksCount[$week-1]['start']);
                            $weekEnd = new \DateTime($weeksCount[$week-1]['end']);

                              $fieldName = "{$field['name']}_week{$week}";
                              $weekValue = $weekValues[$week - 1] ?? '';

                              $html .= "<div>
                                          <label class='mini-label text-secondary'><small>{$weekStart->format('d.m')} - {$weekEnd->format('d.m')}</small></label>
                                          <input type='text' class='form-control digits-field' name='{$fieldName}' id='{$fieldName}' value='{$weekValue}'>
                                        </div>";
                          }
                          $html .= '</div>';
                      } else {
                          $fieldValue = isset($data[$field['name']]) ? $data[$field['name']] : '';
                          $html .= "<input type='text' class='form-control digits-field' name='{$field['name']}' id='{$field['name']}' value='{$fieldValue}'>";
                      }

                      $html .= "</div>";
                  }
                  $html .= "</div>";
              }

              $html .= "<input type='hidden' name='monthyear' value='{$date->format('Y-m-d')}'>";
              $html .= "<div class='submits'>
                          <button type='submit' class='btn btn-dark'>Сохранить данные</button>
                          <button type='button' class='btn btn-link cancel-editting'>Отмена</button>
                        </div>";
              $html .= '</form>';

              return $html;
              break;
            case 'getMarketingReportCpcData': // Данные месяц год для cpc отчета
              $mreport = new ReportMarketingTable();
              $cpcdatamodel = new CpcProjectsDataTable();

              parse_str($postData['formData'],$formData);

              $projectId = (int)$formData['cpc-project'];
              $date = new \DateTime($formData['cpc-add-data-date']);

              $data = $cpcdatamodel->getMarketingCpcData($projectId,$date);

              $fields = $cpcdatamodel->getMarketingCpcDataArray();
              $periods = $cpcdatamodel->separateFillingPeriodsForFields();
              $weeksCount = $mreport->getWeeksFromFirstWednesday($date);

              $groups = [];
              foreach ($fields as $name => $label) {
                $groupName = explode(":", $label)[0];
                $groups[$groupName][] = [
                                          'name' => $name,
                                          'label' => $label,
                                          'fieldName' => $label,
                                          'period' => $periods[$name] ?? 'monthly'
                                        ];
              }

              $html = '<form id="marketing-cpc-data-issue-form">';
              $html .= '<h2>Данные за ' . $date->format('m.Y') . '</h2><div class="d-flex flex-wrap justify-content-between">';
              foreach ($groups as $groupName => $groupFields) {
                  // $html .= "<div class='form-group mb-5 d-flex flex-wrap justify-content-between'>";

                  // Генерация полей для каждой группы
                  foreach ($groupFields as $field) {
                      $fieldValue = isset($data[$field['name']]) ? $data[$field['name']] : '';

                      $html .= "<div class='form-group mb-3 col-5'>";
                      $html .= "<label class='text-muted mb-2' for='{$field['name']}'><strong>{$field['fieldName']}</strong></label>";

                      if ($field['period'] === 'weekly') {
                          $html .= "<div class='weekly-row'>";
                          $weekValues = explode(':::', $fieldValue);

                          for ($week = 1; $week <= count($weeksCount); $week++) {
                            $weekStart = new \DateTime($weeksCount[$week-1]['start']);
                            $weekEnd = new \DateTime($weeksCount[$week-1]['end']);

                              $fieldName = "{$field['name']}_week{$week}";
                              $weekValue = $weekValues[$week - 1] ?? '';

                              $html .= "<div>
                                          <label class='mini-label text-secondary'><small>{$weekStart->format('d.m')} - {$weekEnd->format('d.m')}</small></label>
                                          <input type='text' class='form-control digits-field' name='{$fieldName}' id='{$fieldName}' value='{$weekValue}'>
                                        </div>";
                          }
                          $html .= '</div>';
                      } else {
                          $fieldValue = isset($data[$field['name']]) ? $data[$field['name']] : '';
                          $html .= "<input type='text' class='form-control digits-field' name='{$field['name']}' id='{$field['name']}' value='{$fieldValue}'>";
                      }

                      $html .= "</div>";
                  }
                  // $html .= "</div>";
              }

              $html .= '</div>';

              $html .= "<input type='hidden' name='monthyear' value='{$date->format('Y-m-d')}'>";
              $html .= "<div class='submits'>
                          <input type='hidden' name='cpc-project-id' value='" . $projectId . "' />
                          <button type='submit' class='btn btn-dark'>Сохранить данные</button>
                          <button type='button' class='btn btn-link cancel-editting'>Отмена</button>
                        </div>";
              $html .= '</form>';

              return $html;
              break;
            case 'setMarketingReportData': // Сохранение/Обновление данных
              $model = new Reports();
              $mreport = new ReportMarketingTable();
              parse_str($postData['formData'],$formData);
              $date = new \DateTime($formData['monthyear']);

              $periods = $mreport->separateFillingPeriodsForFields();

              $existingRec = $mreport->getMarketingData($date);

              $weeklyGrouped = [];

              if($existingRec){
                foreach ($formData as $fkey => $fd) {
                  if($fkey == 'monthyear'){ continue; }

                  $baseKey = preg_replace('/_week\d+$/', '', $fkey);

                  if (isset($periods[$baseKey]) && $periods[$baseKey] === 'weekly') {
                    $weeklyGrouped[$baseKey][str_replace($baseKey . '_', '', $fkey)] = $fd;
                    continue;
                  }

                  $fd = trim($fd);
                  $fd = preg_replace('/[,\.]{2,}/', '.', $fd);
                  $fd = str_replace(',', '.', $fd);
                  $existingRec->{$fkey} = $fd;
                }

                foreach ($weeklyGrouped as $baseKey => $values) {
                  ksort($values);
                  $implodedValues = implode(':::', $values);
                  $implodedValues = trim(preg_replace('/\s+/', '', $implodedValues));
                  $implodedValues = preg_replace('/,+/', ',', $implodedValues);
                  $implodedValues = preg_replace('/\.+/', '.', $implodedValues);
                  $implodedValues = str_replace(',', '.', $implodedValues);

                  $existingRec->{$baseKey} = $implodedValues;
                }

                $existingRec->update_date = date('Y-m-d H:i:s');
                $existingRec->save();
              }
              else {
                $rec = new ReportMarketingTable();

                foreach ($formData as $fkey => $fd) {
                  if($fkey == 'monthyear'){ continue; }

                  $baseKey = preg_replace('/_week\d+$/', '', $fkey);

                  if (isset($periods[$baseKey]) && $periods[$baseKey] === 'weekly') {
                    $weeklyGrouped[$baseKey][str_replace($baseKey . '_', '', $fkey)] = $fd;
                    continue;
                  }

                  $fd = trim($fd);
                  $fd = preg_replace('/[,\.]{2,}/', '.', $fd);
                  $fd = str_replace(',', '.', $fd);
                  $rec->{$fkey} = $fd;
                }

                foreach ($weeklyGrouped as $baseKey => $values) {
                  ksort($values);
                  $implodedValues = implode(':::', $values);
                  $implodedValues = trim(preg_replace('/\s+/', '', $implodedValues));
                  $implodedValues = preg_replace('/,+/', ',', $implodedValues);
                  $implodedValues = preg_replace('/\.+/', '.', $implodedValues);
                  $implodedValues = str_replace(',', '.', $implodedValues);
                  $rec->{$baseKey} = $implodedValues;
                }

                $rec->month = $date->format('m');
                $rec->year = $date->format('Y');
                $rec->create_date = date('Y-m-d H:i:s');
                $rec->save();

              }

              return '<div class="alert alert-success p-2"><small>Данные сохранены. Выберите месяц и год для заполнения данных.</small></div>';
              break;
            case 'setMarketingReportCpcData': // Сохранение/Обновление Cpc данных
              $model = new Reports();
              $cpcdatamodel = new CpcProjectsDataTable();
              parse_str($postData['formData'],$formData);

              $projectId = (int)$formData['cpc-project-id'];
              $date = new \DateTime($formData['monthyear']);

              $periods = $cpcdatamodel->separateFillingPeriodsForFields();

              $existingRec = $cpcdatamodel->getMarketingCpcData($projectId,$date);

              $weeklyGrouped = [];

              if($existingRec){
                foreach ($formData as $fkey => $fd) {
                  if($fkey == 'monthyear'){ continue; }
                  if($fkey == 'cpc-project-id'){ continue; }

                  $baseKey = preg_replace('/_week\d+$/', '', $fkey);

                  if (isset($periods[$baseKey]) && $periods[$baseKey] === 'weekly') {
                    $weeklyGrouped[$baseKey][str_replace($baseKey . '_', '', $fkey)] = $fd;
                    continue;
                  }

                  $fd = trim($fd);
                  $fd = preg_replace('/[,\.]{2,}/', '.', $fd);
                  $fd = str_replace(',', '.', $fd);
                  $existingRec->{$fkey} = $fd;
                }

                foreach ($weeklyGrouped as $baseKey => $values) {
                  ksort($values);
                  $implodedValues = implode(':::', $values);
                  $implodedValues = trim(preg_replace('/\s+/', '', $implodedValues));
                  $implodedValues = preg_replace('/,+/', ',', $implodedValues);
                  $implodedValues = preg_replace('/\.+/', '.', $implodedValues);
                  $implodedValues = str_replace(',', '.', $implodedValues);

                  $existingRec->{$baseKey} = $implodedValues;
                }

                $existingRec->update_date = date('Y-m-d H:i:s');
                $existingRec->save();
              }
              else {
                $rec = new CpcProjectsDataTable();

                foreach ($formData as $fkey => $fd) {
                  if($fkey == 'monthyear'){ continue; }
                  if($fkey == 'cpc-project-id'){ $fkey = 'cpc_id'; }

                  $baseKey = preg_replace('/_week\d+$/', '', $fkey);

                  if (isset($periods[$baseKey]) && $periods[$baseKey] === 'weekly') {
                    $weeklyGrouped[$baseKey][str_replace($baseKey . '_', '', $fkey)] = $fd;
                    continue;
                  }

                  $fd = trim($fd);
                  $fd = preg_replace('/[,\.]{2,}/', '.', $fd);
                  $fd = str_replace(',', '.', $fd);
                  $rec->{$fkey} = $fd;
                }

                foreach ($weeklyGrouped as $baseKey => $values) {
                  ksort($values);
                  $implodedValues = implode(':::', $values);
                  $implodedValues = trim(preg_replace('/\s+/', '', $implodedValues));
                  $implodedValues = preg_replace('/,+/', ',', $implodedValues);
                  $implodedValues = preg_replace('/\.+/', '.', $implodedValues);
                  $implodedValues = str_replace(',', '.', $implodedValues);
                  $rec->{$baseKey} = $implodedValues;
                }

                $rec->month = $date->format('m');
                $rec->year = $date->format('Y');
                $rec->create_date = date('Y-m-d H:i:s');
                $rec->save();

              }

              return '<div class="alert alert-success p-2"><small>Данные сохранены. Выберите месяц и год для заполнения данных.</small></div>';
              break;
            case 'createMarketingReport':
              $model = new Reports();
              parse_str($postData['formData'],$formData);

              $year  = (int)$formData['date-year'];

              $link = $model->createMarketingReport($year);

              if(!$link){
                return '<div class="alert alert-danger p-2 mt-4 text-center">В указанном году не заполнены данные ни за один месяц.</div>';
              }
              else {
                return '<div class="alert alert-info p-2 mt-3 text-center alert-download-file"><a class="text-info" href="https://service.accio.kz/tmpDocs/' . $link->file . '" target="_blank">Загрузить отчет по маркетингу</a></div>';
              }

              break;
            case 'addMarketingCPCProjects':
              $cpcmodel = new CpcProjectsTable();
              parse_str($postData['formData'],$formData);

              $project = (object)array(
                                  'title' => trim($formData['cpc-project-title']),
                                  'type' => (int)$formData['cpc-project-type'],
                                  'pid' => trim($formData['cpc-project-id'])
                                );

              $cpcmodel->title = $project->title;
              $cpcmodel->type = $project->type;
              $cpcmodel->pid = $project->pid;
              $cpcmodel->create_date = date('Y-m-d H:i:s');
              $cpcmodel->save();
              $project->id = $cpcmodel->id;


              $html = $cpcmodel->wrapCpcProject($project);

              return $html;
              break;
            case 'removeCpcProject':
              $model = new CpcProjectsTable();
              $projectId = (int)$postData['actionData']['projectId'];
              $message = '';

              if($model->removeCpcProject($projectId)){
                $deleted = true;
              }
              else {
                $deleted = false;
                $message = 'Произошла ошибка удаления проекта #' . $projectId . '. Свяжитесь с разработчиком.';
              }

              return array('deleted' => $deleted, 'message' => $message, 'projectId' => $projectId);
              break;
            case 'getMarketingReportAllPeriodsData':
              $mreport = new ReportMarketingTable();
              $allData = $mreport->find()->all();

              $grouppedData = [];

              foreach ($allData as $data) {
                $grouppedData[$data->year][] = $data;
              }

              $yearFillings = [];
              foreach ($grouppedData as $year => $datas) {
                foreach ($datas as $month) {
                  $validateResult = $mreport->validateEmptyArray($month);
                  $yearFillings[$year][$month->month] = $validateResult;
                }
                ksort($yearFillings[$year]);
              }

              return $yearFillings;
              break;
            case 'createRealizeReport':
              $model = new Reports();

              $contragent = $postData['contragent'];
              $year  = (int)$postData['date-year'];
              // $file = UploadedFile::getInstanceByName('sale-file');
              //
              // if (!$file) {
              //   return '<div class="alert alert-danger p-2 mt-4 text-center">Файл не получен.</div>';
              // }
              //
              // $ext = strtolower($file->getExtension());
              //
              // if ($ext !== 'xls' AND $ext !== 'xlsx') {
              //   return '<div class="alert alert-danger p-2 mt-4 text-center">Разрешён только .xls или .xlsx</div>';
              // }
              //
              // $fileTmpPath = \Yii::getAlias('@runtime') . '/sales_' . time() . '.' . $ext;
              // if (!$file->saveAs($fileTmpPath, false)) {
              //   return '<div class="alert alert-danger p-2 mt-4 text-center">Не удалось сохранить файл</div>';
              // }

              $link = $model->createRealizeReport($contragent,$year,false);

              if(!$link){
                return '<div class="alert alert-danger p-2 mt-4 text-center">Произошла какая-то ошибка.</div>';
              }
              else {
                return '<div class="alert alert-info p-2 mt-3 text-center alert-download-file"><a class="text-info" href="https://service.accio.kz/tmpDocs/' . $link->file . '" target="_blank">Загрузить отчет товаров на реализации</a></div>';
              }

              break;

            // Конфигурация заказов
            case 'updateLegalAccountList':
              $moysklad = new Moysklad();
              $organization = trim($postData['actionData']['val']);
              $accounts = $moysklad->getOrganizationAccounts($organization);

              $html = '<option value="">Выберите счет</option><option value="byhand">Устанавливается вручную</option>';
              foreach ($accounts->rows as $row) {
                $html .= '<option value="' . $row->id . '">' . $row->accountNumber . '</option>';
              }

              return $html;
              break;
              case 'submitOrderConfig':
                  parse_str($postData['formData'], $formData);

                  $project = trim($formData['project'] ?? '');
                  if ($project === '') {
                      return $this->asJson(['success' => false, 'message' => 'Не передан project']);
                  }

                  // единый маппинг
                  $map = [
                      'payment_type'     => 'payment-type',
                      'fiscal'           => 'fiskal',
                      'status'           => 'status',
                      'organization'     => 'organization',
                      'legal_account'    => 'legalaccountnumber',
                      'channel'          => 'channel',
                      'project_field'    => 'project-field',
                      'payment_status'   => 'payment-status',
                      'delivery_service' => 'delivery-service',
                      'cash_register'    => 'cash-register',
                  ];

                  // ✅ Ветка "сохранить всё" (юрик): configs[action_type][...]
                  if (!empty($formData['configs']) && is_array($formData['configs'])) {
                      $configs = $formData['configs'];

                      $tx = Yii::$app->db->beginTransaction();
                      try {
                          foreach ($configs as $actionType => $data) {
                              $actionType = (int)$actionType;

                              $model = OrdersConfigTable::findOne([
                                  'project' => $project,
                                  'action_type' => $actionType,
                              ]);

                              if ($model === null) {
                                  $model = new OrdersConfigTable();
                                  $model->project = $project;
                                  $model->action_type = $actionType;
                              }

                              foreach ($map as $attr => $key) {
                                  $model->$attr = $data[$key] ?? '';
                              }

                              if (!$model->save()) {
                                  throw new \RuntimeException(
                                      'Ошибка сохранения action_type=' . $actionType . ': ' .
                                      json_encode($model->errors, JSON_UNESCAPED_UNICODE)
                                  );
                              }
                          }

                          $tx->commit();
                          return $this->asJson(['success' => true, 'message' => 'Конфиги сохранены']);
                      } catch (\Throwable $e) {
                          $tx->rollBack();
                          Yii::error($e->getMessage(), __METHOD__);
                          return $this->asJson([
                              'success' => false,
                              'message' => 'Ошибка сохранения',
                              'errors'  => $e->getMessage(),
                          ]);
                      }
                  }

                  // ✅ Ветка "сохранить один" (остальные проекты / старый формат)
                  $actionType = (int)($formData['action_type'] ?? 0);

                  $model = OrdersConfigTable::findOne([
                      'project' => $project,
                      'action_type' => $actionType,
                  ]);

                  if ($model === null) {
                      $model = new OrdersConfigTable();
                      $model->project = $project;
                      $model->action_type = $actionType;
                  }

                  foreach ($map as $attr => $key) {
                      $model->$attr = $formData[$key] ?? '';
                  }

                  if ($model->save()) {
                      return $this->asJson(['success' => true, 'message' => 'Конфиг сохранён']);
                  }

                  Yii::error($model->errors, __METHOD__);
                  return $this->asJson([
                      'success' => false,
                      'message' => 'Ошибка сохранения',
                      'errors'  => $model->errors,
                  ]);
                  break;
          }

        }
        else {
          return '<div class="alert alert-danger p-2 mt-3 text-center">Произошла ошибка загрузки отчета. Перезагрузите страницу и попробуйте снова. Если ошибка будет появляться и дальше, свяжитесь с администратором.</div>';
        }
    }
}
