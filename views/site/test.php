<?php
use app\models\CashRegister;

try {
    $result = CashRegister::loginUkassaUser(
      'ip.pastukhov90@ya.ru',
      '901128301025Dt+',
      'UK00003842'
    );

    print('<pre>');
    print_r($result);
    print('</pre>');
} catch (\Throwable $e) {
    echo $e->getMessage();
}

exit();

// try {
//   $cashboxes = CashRegister::getCashRegisterDataList();
//   print_r($cashboxes);
// } catch (\Throwable $e) {
//     echo $e->getMessage();
// }
//
// exit();
