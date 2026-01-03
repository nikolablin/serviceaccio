<?php
set_time_limit(0);
ini_set("memory_limit", "-1");
ini_set("max_execution_time", "-1");

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Tabs;

$this->title = 'Отчеты';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-reports">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php
    $basic = '<section class="report"';

    $salesReportHtml =  $basic . ' id="salesReport">
                          <h2>Отчет по продажам</h2>

                          <form name="sales-report">
                            <div class="mb-3 col-6">
                              <label class="form-label">Период отчета</label>
                              <input type="text" name="period" class="period-calendar-field form-control" required="" placeholder="Выберите период">
                            </div>
                            <div class="form-group">
                              <button type="submit" class="btn btn-sm btn-dark">Сформировать отчет</button>
                            </div>
                          </form>

                        </section>';

    $buyerReportHtml =  $basic . ' id="buyerReport">
                          <h2>Отчет по закупкам</h2>
                          <form name="buyes-report">
                            <div class="mb-3 col-6">
                              <label class="form-label">Категории</label>
                              <select name="category[]" class="form-select" multiple="" style="height:200px;" required="">';

    $buyerReportHtml .= '<option value="" disabled selected>Выберите категорию</option>';
    $buyerReportHtml .= '<option value="all">Все</option>';
    foreach ($mscats as $catid => $catname) {
      $buyerReportHtml .= '<option value="' . $catid . '">' . $catname . '</option>';
    }

    $buyerReportHtml .=       '</select>
                            </div>
                            <div class="mb-3 col-2">
                              <label class="form-label">Срок ожидания, дней</label>
                              <input type="text" name="wait-date" class="digits-field form-control" required="" placeholder="">
                            </div>
                            <div class="mb-3 col-2">
                              <label class="form-label">Срок доставки, дней</label>
                              <input type="text" name="delivery-date" class="digits-field form-control" required="" placeholder="">
                            </div>
                            <div class="mb-3 col-2">
                              <label class="form-label">Необходимый товарный запас, дней</label>
                              <input type="text" name="goods-hold-date" class="digits-field form-control" required="" placeholder="">
                            </div>
                            <div class="mb-3 col-6">
                              <label class="form-label">Старт периода отчета</label>
                              <input type="text" name="start-date" class="calendar-field form-control" required="" placeholder="Выберите дату">
                            </div>
                            <div class="form-group">
                              <button type="submit" class="btn btn-sm btn-dark">Сформировать отчет</button>
                            </div>
                          </form>
                        </section>';
    $movesReportHtml =  $basic . ' id="movesReport">
                          <h2>Отчет о перемещениях</h2>
                          <form name="moves-report">
                            <div class="mb-3 col-6">
                              <label class="form-label">Старт периода отчета</label>
                              <input type="text" name="start-date" class="calendar-field form-control" required="" placeholder="Выберите дату">
                            </div>
                            <div class="form-group">
                              <button type="submit" class="btn btn-sm btn-dark">Сформировать отчет</button>
                            </div>
                          </form>
                        </section>';
    $comissionerReportHtml =  $basic . ' id="comissionerReport">
                                <h2>Отчет комиссионера</h2>
                                <form name="comissioner-report">
                                  <div class="mb-3 col-6">
                                    <label class="form-label">Старт периода отчета</label>
                                    <input type="text" name="start-date" class="calendar-field form-control" required="" placeholder="Выберите дату">
                                  </div>
                                  <div class="form-group">
                                    <button type="submit" class="btn btn-sm btn-dark">Сформировать отчет</button>
                                  </div>
                                </form>
                              </section>';

    $realizeReportHtml =  $basic . ' id="realizeReport">
                                <h2>Отчет товары на реализации</h2>
                                <form name="realize-report">
                                  <div class="mb-3 col-6">
                                    <label class="form-label">Контрагент</label>
                                    <select name="contragent" class="form-control" required>
                                      <option value="">Выберите контрагента</option>';

    foreach ($realizeContragents as $rcval) {
      $realizeReportHtml .= '<option value="' . $rcval->id . '">' . $rcval->name . '</option>';
    }

    $realizeReportHtml .=    '</select>
                                  </div>
                                  <div class="mb-3 col-6">
                                    <label class="form-label">Файл продаж</label>
                                    <input type="file" name="sale-file" class="form-control" accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                    <div class="mt-2"><small><a href="/uploads/realize_sales_example.xlsx">Пример файла реализованных товаров</a></small></div>
                                  </div>
                                  <div class="mb-3 col-6">
                                    <label class="form-label">Выберите год</label>
                                    <input type="text" name="date-year" class="year-calendar-field form-control" required="" placeholder="Выберите год">
                                  </div>
                                  <div class="form-group">
                                    <button type="submit" class="btn btn-sm btn-dark">Сформировать отчет</button>
                                  </div>
                                </form>
                              </section>';

    $marketingReportTab1Html = '<h2>Заполнение данных для отчета</h2>
                                <form name="marketing-report-data">
                                  <div class="mb-3 col-6">
                                    <label class="form-label">Выберите год и месяц заполнения<br/>
                                      <div class="statuses">
                                        <span class="ok">Заполнен полностью</span>
                                        <span class="notok">Требуется заполнить все поля</span>
                                      </div>
                                    </label>
                                    <input type="text" name="add-data-date" class="month-marketing-calendar-field form-control" required="" placeholder="Выберите дату">
                                  </div>
                                  <div class="form-group">
                                    <button type="submit" class="btn btn-sm btn-dark">Заполнить данные</button>
                                  </div>
                                </form>
                                <div class="mt-5 mb-3 data-form">
                                  <h3>Заполненные данные месяца и года</h3>
                                  Выберите месяц и год
                                </div>';

    $cpcProjectTypes = $cpcmodel->projectTypes();
    $cpcProjectsList = $cpcmodel->getProjectsList();
    $marketingReportTab3Html = '<h2>Заполнение кампаний CPC</h2>
                                <h3>Список кампаний</h3>
                                <div class="cpc-projects-list">';

    foreach ($cpcProjectsList as $cpcproject) {
      $marketingReportTab3Html .= $cpcmodel->wrapCpcProject($cpcproject);
    }

    $marketingReportTab3Html .= '</div>
                                <div class="alert alert-secondary">
                                  <h3>Добавить кампанию</h3>
                                  <form name="marketing-report-cpc-projects" class="pb-5">
                                    <div class="mb-3 col-2">
                                      <label class="form-label">Название кампании</label>
                                      <input type="text" name="cpc-project-title" class="form-control" required="" placeholder="">
                                    </div>
                                    <div class="mb-3 col-2">
                                      <label class="form-label">Тип кампании</label>
                                      <select name="cpc-project-type" class="form-control" required>
                                        <option value="">Выберите тип</option>';

      foreach ($cpcProjectTypes as $cpcprkey => $cpcprtitle) {
        $marketingReportTab3Html .= '<option value="' . $cpcprkey . '">' . $cpcprtitle . '</option>';
      }

      $marketingReportTab3Html .=    '</select>
                                    </div>
                                    <div class="mb-3 col-2">
                                      <label class="form-label">Идентификатор</label>
                                      <input type="text" name="cpc-project-id" class="form-control" required />
                                    </div>
                                    <div class="form-group col-12">
                                      <button type="submit" class="btn btn-sm btn-dark">Добавить новую кампанию</button>
                                    </div>
                                  </form>
                                </div>
                                <hr/>
                                <h3 class="mt-5">Ввод данных для кампании</h3>
                                <form name="marketing-report-cpc-data" class="pb-5">
                                  <div class="mb-3 col-2">
                                    <label class="form-label">Кампания</label>
                                    <select name="cpc-project" class="form-control" required>
                                      <option value="">Выберите кампанию</option>';

      foreach ($cpcProjectsList as $cpcproject) {
        $marketingReportTab3Html .= '<option value="' . $cpcproject->id . '">' . $cpcproject->title . '</option>';
      }
      $marketingReportTab3Html .= '</select>
                                  </div>
                                  <div class="mb-3 col-6">
                                    <label class="form-label">Выберите год и месяц заполнения</label>
                                    <input type="text" name="cpc-add-data-date" class="month-calendar-field form-control" required="" placeholder="Выберите дату">
                                  </div>
                                  <div class="form-group col-12">
                                    <button type="submit" class="btn btn-sm btn-dark">Заполнить данные</button>
                                  </div>
                                </form>
                                <div class="mt-5 mb-3 cpc-data-form">
                                  <h3>Заполненные данные месяца и года</h3>
                                  Выберите месяц и год
                                </div>';

    $marketingReportTab2Html = '<h2>Маркетинговый отчет</h2>
                                <form name="marketing-report" id="marketing-report">
                                  <div class="mb-3 col-6">
                                    <label class="form-label">Выберите год</label>
                                    <input type="text" name="date-year" class="year-calendar-field form-control" required="" placeholder="Выберите год">
                                  </div>
                                  <div class="form-group">
                                    <button type="submit" class="btn btn-sm btn-dark">Сформировать отчет</button>
                                  </div>
                                </form>';

    $incomeBrandsReportHtml = $basic . ' id="incomeBrandsReport">
                                <h2>Отчет Прибыльность по брендам</h2>
                                <form name="income-brands-report">
                                  <div class="mb-3 col-6">
                                    <label class="form-label">Выберите год</label>
                                    <input type="text" name="date-year" class="year-calendar-field form-control" required placeholder="Выберите год">
                                  </div>
                                  <div class="form-group">
                                    <button type="button" class="btn btn-sm btn-dark next">Далее &rarr;</button>
                                  </div>
                                  <div class="months-data"></div>
                                </form>
                              </section>';

    $marketingReportHtml = $basic . 'id="marketingReport">' . Tabs::widget([
                                          'items' => [
                                            [
                                                'label' => 'Ввод данных',
                                                'content' => $marketingReportTab1Html,
                                                'active' => true
                                            ],
                                            [
                                                'label' => 'Ввод данных CPC',
                                                'content' => $marketingReportTab3Html,
                                            ],
                                            [
                                                'label' => 'Формирование отчета',
                                                'content' => $marketingReportTab2Html,
                                            ]
                                          ]
                                        ])
                                        . '</section>';

    echo Tabs::widget([
        'items' => [
            [
                'label' => 'Продажи',
                'content' => $salesReportHtml,
                'active' => true
            ],
            [
                'label' => 'Закупки',
                'content' => $buyerReportHtml,
            ],
            [
                'label' => 'Перемещения',
                'content' => $movesReportHtml,
            ],
            [
                'label' => 'Комиссионеры',
                'content' => $comissionerReportHtml,
            ],
            [
                'label' => 'Маркетинговый отчет',
                'content' => $marketingReportHtml,
            ],
            [
                'label' => 'Прибыльность по брендам',
                'content' => $incomeBrandsReportHtml,
            ],
            [
                'label' => 'На реализацию',
                'content' => $realizeReportHtml,
            ],
        ],
    ]);
    ?>

</div>
