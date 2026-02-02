<?php
// use app\models\CashRegister;
// use app\models\Kaspi;
// use app\models\Whatsapp;
// use app\models\Moysklad;
// use app\models\MoyskladWebhook;
//
//
// ini_set('display_errors', '1');
// error_reporting(E_ALL);
//
// $moysklad = new Moysklad();
// $moysklad = new MoyskladWebhook();

// $moysklad->disableWebhook('fe487bd0-a40f-11ee-0a80-0411002fa50b');
// $moysklad->createWebhook('https://service.accio.kz/webhook/updatesalesreturn','UPDATE','salesreturn');

// $orderHref = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/110416e9-ee35-11f0-0a80-17c801ead99d';
//
// $order = $moysklad->getHrefData(
//     $orderHref . '?expand=project,state,positions,paymentType,attributes,finance'
// );
//
// foreach ($order->demands as $demand) {
//   $demand = $moysklad->getHrefData(
//     $demand->meta->href . '?expand=project,state,positions,paymentType,attributes,paymentin'
//   );
//   if(property_exists($demand,'payments') && !empty($demand->payments)){
//     $demandReady = $demand;
//     break;
//   }
// }
//
//
// $payments = $demandReady->payments;
//
// $batchSize   = 40;   // сколько удаляем за 1 batch
// $maxTotal    = 1000;  // общий лимит
// $pause       = 3;    // пауза между batch
// $payload     = [];
// $c           = 0;
//
// foreach ($payments as $payment) {
//     if ($c >= $maxTotal) {
//         break;
//     }
//
//
//     $href = (string)($payment->meta->href ?? '');
//     if ($href === '') {
//         continue;
//     }
//
//     if($href == 'https://api.moysklad.ru/api/remap/1.2/entity/paymentin/b8ed9762-eef6-11f0-0a80-039902073b4b'){
//       continue;
//     }
//
//     $payload[] = [
//         'meta' => [
//             'href'      => $href,
//             'type'      => 'paymentin',
//             'mediaType' => 'application/json',
//         ],
//     ];
//
//     $c++;
//
//     // когда набрали batch
//     if (count($payload) === $batchSize) {
//
//         $resp = $moysklad->batchDeleteEntity(
//             'paymentin/delete',
//             $payload
//         );
//
//         $payload = []; // очищаем batch
//         sleep($pause);
//     }
// }
//
// /**
//  * если остался хвост (< batchSize)
//  */
// if (!empty($payload)) {
//     $resp = $moysklad->batchDeleteEntity(
//         'salesreturn/delete',
//         $payload
//     );
// }








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
