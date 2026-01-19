<?php
use Yii;

use app\models\CashRegister;
use app\models\Kaspi;
use app\models\Whatsapp;
use app\models\Moysklad;


ini_set('display_errors', '1');
error_reporting(E_ALL);

$moysklad = new Moysklad();

$orderHref = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/42906f91-f2e9-11f0-0a80-1145001f54f6';

$order = $moysklad->getHrefData(
    $orderHref . '?expand=project,state,positions,paymentType,attributes'
);

$demand = $order->demands[0];

$demand = $moysklad->getHrefData(
  $demand->meta->href . '?expand=project,state,positions,paymentType,attributes'
);

$returns = $demand->returns;

$batchSize   = 30;   // сколько удаляем за 1 batch
$maxTotal    = 300;  // общий лимит
$pause       = 3;    // пауза между batch
$payload     = [];
$c           = 0;

foreach ($returns as $return) {

    if ($c >= $maxTotal) {
        break;
    }

    $href = (string)($return->meta->href ?? '');
    if ($href === '') {
        continue;
    }

    $payload[] = [
        'meta' => [
            'href'      => $href,
            'type'      => 'salesreturn',
            'mediaType' => 'application/json',
        ],
    ];

    $c++;

    // когда набрали batch
    if (count($payload) === $batchSize) {

        $resp = $moysklad->batchDeleteEntity(
            'salesreturn/delete',
            $payload
        );

        // при необходимости — лог
        /*
        file_put_contents(__DIR__ . '/../logs/ms_service/delete_salesreturn_batch.txt',
            date('d.m.Y H:i:s') . PHP_EOL .
            "batch size=" . count($payload) . PHP_EOL .
            print_r($resp, true) . PHP_EOL .
            str_repeat('-', 60) . PHP_EOL,
            FILE_APPEND
        );
        */

        $payload = []; // очищаем batch
        sleep($pause);
    }
}

/**
 * если остался хвост (< batchSize)
 */
if (!empty($payload)) {
    $resp = \app\models\Moysklad::batchDeleteEntity(
        'salesreturn/delete',
        $payload
    );
}








// $c = 0;
// foreach ($returns as $return) {
//   $returnId = basename($return->meta->href);
//   $resp = $moysklad->deleteSalesreturn($returnId);
//   $c++;
//
//   if ($c % 30 === 0) {
//      sleep(3);
//  }
//
//  if($c >= 300) { break; }
// }


// $whatsapp = new Whatsapp();
//
// $messageInfo          = (object)array();
// $messageInfo->name    = 'Виктория';
// $messageInfo->link    = 'https://acciostore.kz';
// $messageInfo->orderid = '123123';

// $res = $whatsapp->sendWhatsappMessage($messageInfo,'+77772957038','set_kaspi_opinion_by_client_with_buttons_9','accio',true);
// $res2  = $whatsapp->checkWhatsappSendpulseMessage($res);

// $kaspi = new Kaspi();

// print('<pre>');
// print_r($kaspi->getKaspiOrder('787718713', 'accio', true));
// print('</pre>');

// CashRegister::getDepartmentData('UK00006241');

// 1) Данные для чека (упрощённо)
// $dataReceipt = [
//     // тип операции
//     // sell | sell_return
//     'operation' => 2,
//
//     // код кассы (как в конфиге, НЕ id)
//     'kassa' => 3868,
//
//     // позиции чека
//     'items' => [
//         [
//             'name'     => 'Товар',
//             'price'     => 4899,     // цена за единицу (в тиынах)
//             'quantity'  => 1,
//             'is_nds'    => true,       // есть НДС
//             'section'   => 3682,          // секция кассы
//             'ntin'      => '123456789012', // ИНТ/GTIN (можно '-')
//             'tax_rate'  => 12,         // ставка НДС
//             'total_amount' => 4899
//         ],
//     ],
//
//     // оплата
//     'payments' => [
//         [
//             'payment_type' => 1, // card | cash | mixed
//             'total'        => 4899, // сумма оплаты
//         ],
//     ],
//
//     // итог по чеку
//     'total_amount' => 4899,
//
//     // опционально
//     'is_return_html' => false,
// ];
//
// $metaReceipt = [
//     'order_id'           => 123,
//     'moysklad_order_id'  => 'f51412fa-ed48-11f0-0a80-0d9001c70105',
//     'moysklad_demand_id' => '7e231cdd-ed3e-11f0-0a80-01ba01c2b1f0',
//     'receipt_type'       => 'sale',
//     'idempotency_key'    => CashRegister::uuidV4(),
// ];
//
// $receiptId = CashRegister::createReceiptDraft(
//     'UK00003842',
//     $metaReceipt,
//     $dataReceipt
// );
//
// print('<pre>');
// print_r($receiptId);
// print('</pre>');
//
// $sent = CashRegister::sendReceiptById((int)$receiptId, false);
//
// print('<pre>');
// print_r($sent);
// print('</pre>');

//// ---------------------------------------


// foreach (CashRegister::getCashRegisterList() as $code) {
//     try {
//         $t = CashRegister::getUkassaTokenByCashRegister($code);
//         echo $t.'<br/>';
//     } catch (\Throwable $e) {
//         echo $code . " FAIL: " . $e->getMessage() . "\n";
//     }
// }
