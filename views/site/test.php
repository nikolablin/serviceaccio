<?php
use app\models\CashRegister;

ini_set('display_errors', '1');
error_reporting(E_ALL);

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
