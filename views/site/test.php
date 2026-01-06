<?php
// use app\models\CashRegister;
//
// ini_set('display_errors', '1');
// error_reporting(E_ALL);

// CashRegister::getDepartmentData('UK00003857');

// 1) Данные для чека (упрощённо)
// $data = [
//     'operation_type' => 2, // продажа (как у тебя)
//     'items' => [
//         [
//             'code' => 'TEST-0001',
//             'name' => 'Тестовый товар',
//             'quantity' => 1,
//             'unit_price' => 1000,
//             'tax_rate' => 12,
//             'section_code' => '1488',
//             'measure_unit_code' => '796',
//         ],
//     ],
//     'payments' => [
//         ['type' => 0, 'sum_' => 1000], // наличные/карта зависит от ukassa типа (тут как в доке)
//     ],
//     // 'customer_iin_bin' => '910228451318', // опционально
//     'is_return_html' => false,
// ];
//
// // 2) Метаданные для БД (если нет order/demand — можно null/пусто)
// $meta = [
//     'order_id' => null,
//     'moysklad_order_id' => 'null9',
//     'moysklad_demand_id' => 'null9',
//     'receipt_type' => 'sale', // sale|return
//     // idempotency_key можно не задавать — сгенерится
// ];
//
// // 3) Создаём черновик в БД
// $receiptId = CashRegister::createReceiptDraft('UK00003842', $meta, $data);
//
// echo "DRAFT CREATED receipt_id={$receiptId}\n";
//
// // 4) DryRun: тормозим перед отправкой (ничего в ukassa не уйдёт)
// $res = CashRegister::sendReceiptById($receiptId, true);
//
// echo "\n=== DRY RUN RESULT ===\n";
// echo "URL: " . $res['url'] . "\n";
// echo "HEADERS:\n" . implode("\n", $res['headers']) . "\n";
// echo "\nPAYLOAD:\n";
// print('<pre>');
// print_r($res['payload']);
// print('</pre>');

// $payload = CashRegister::buildTestReceiptPayload('UK00006241',[
//     'operation_type' => 2,
//     'items' => [
//         [
//             'code' => '00000000067',
//             'name' => 'Хлеб/Нан',
//             'quantity' => 1,
//             'unit_price' => 555,
//             'tax_rate' => 12,
//             'section_code' => '1488',
//             'ntin' => '48743587',
//             'measure_unit_code' => '796',
//         ],
//     ],
//     'payments' => [
//         ['type' => 0, 'sum_' => 555],
//     ],
//     'customer_iin_bin' => '910228451318',
// ]);
//
// $receiptId = CashRegister::saveReceiptDraft([
//     'order_id' => 123,
//     'moysklad_order_id' => 'ms-order-id1',
//     'moysklad_demand_id' => 'ms-demand-id1',
//     'cash_register' => 'UK00006241',
//     'receipt_type' => 'sale',
//     'idempotency_key' => 'draft-' . uniqid('', true),
// ], $payload);
//
// echo "Receipt draft saved: id={$receiptId}\n";



// foreach (CashRegister::getCashRegisterList() as $code) {
//     try {
//         $t = CashRegister::getUkassaTokenByCashRegister($code);
//         echo $t.'<br/>';
//     } catch (\Throwable $e) {
//         echo $code . " FAIL: " . $e->getMessage() . "\n";
//     }
// }
