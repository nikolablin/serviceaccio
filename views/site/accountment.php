<?php
set_time_limit(0);
ini_set("memory_limit", "-1");
ini_set("max_execution_time", "-1");

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Tabs;

use app\models\CashRegister;

$this->title = 'Бухгалтерия';
$this->params['breadcrumbs'][] = $this->title;

// try {
//     $result = CashRegister::loginUkassaUser(
//       'ip.pastukhov90@ya.ru',
//       '901128301025Dt+',
//       'UK00003842'
//     );
//
//     print('<pre>');
//     print_r($result);
//     print('</pre>');
// } catch (\Throwable $e) {
//     echo $e->getMessage();
// }
//
// exit();

// try {
//   $cashboxes = CashRegister::getCashRegisterDataList();
//   print_r($cashboxes);
// } catch (\Throwable $e) {
//     echo $e->getMessage();
// }
//
// exit();

?>
<div class="site-accountment">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php

    $basic = '<section class="accountment"';

    $postAccountsHtml =  $basic . ' id="postAccounts">
                            <h2 class="kaspi">Kaspi-банк</h2>

                            <form name="post-accounts-kaspi">
                              <div class="mb-4 col-12">
                                <label class="form-label">Файл выписки банка</label>
                                <input type="file" name="post-file" accept=".xls,.xlsx" required>
                                <div class="hint">K - Номер заказа, N - Дата учета операции, V - Сумма к зачислению, O - Тип операции, W - Комиссия за операции, AC - Комиссия Kaspi Pay, AJ - Комиссия Kaspi Доставка</div>
                              </div>
                              <div class="mb-4 col-12 d-flex flex-wrap">
                                <label class="form-label w-100">Организация</label>
                                <div class="me-4 d-flex align-items-center"><label for="organization-spectorg"><input class="me-2" type="radio" id="organization-spectorg" name="organization" value="spectorg" required>ИП "Спекторг"</label></div>
                                <div class="me-4 d-flex align-items-center"><label for="organization-accio_retail_store"><input class="me-2" type="radio" id="organization-accio_retail_store" name="organization" value="accio_retail_store">Accio Retail Store</label></div>
                                <div class="d-flex align-items-center"><label for="organization-ital_foods"><input class="me-2" type="radio" id="organization-ital_foods" name="organization" value="ital_foods">Итал Фудз</label></div>
                              </div>
                              <div class="form-group">
                                <input type="hidden" name="bank" value="kaspi" />
                                <button type="submit" class="btn btn-sm btn-dark">Загрузить в МойСклад</button>
                              </div>
                            </form>

                          </section>';

    echo Tabs::widget([
        'items' => [
            [
                'label' => 'Разнесение банковских выписок',
                'content' => $postAccountsHtml,
            ],
        ],
    ]);
    ?>

</div>
