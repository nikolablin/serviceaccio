<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'cronToken' => 'GNIWRNB82589NVWU',

    'ukassa' => [
      'baseUrl'    => 'https://ukassa.kz',
      'loginPath'  => '/api/auth/login/',
      'receiptPath'=> '/api/v1/ofd/receipt/',
      'hashline' => 'serviceaccio',           // любой стабильный идентификатор устройства
      'taxRate' => 16,
      'tokenCacheTtl' => 60 * 60 * 12,
      'accounts' => [
        'UK00003857' => [   'login' => 'fin@acciostore.kz',          'pwd' => 'Vm00855102@@$$'    ],
        'UK00003854' => [   'login' => '2336623@gmail.com',          'pwd' => 'AccioToo2023@@$$'  ],
        'UK00006240' => [   'login' => 'mazurviktoriia@gmail.com',   'pwd' => 'Ital2026@@$$'      ],
        'UK00006241' => [   'login' => 'acciokazakhstan@gmail.com',  'pwd' => 'Scelta2026@@$$'    ],
        'UK00003842' => [   'login' => 'ip.pastukhov90@ya.ru',       'pwd' => '901128301025Dt+'   ],
      ],
      'cashboxes' => [
          'UK00003842' => 3868,
          'UK00003857' => 3883,
          'UK00003827' => 3853,
          'UK00003854' => 3880,
          'UK00006240' => 6548,
          'UK00006241' => 6549,
      ],
    ],

    'moysklad' => [
      'loopGuardTtl' => 10,
      'allowDemandStates' => [
        'd3e01366-75ca-11eb-0a80-02590037e535',
        '6d4d6565-79a4-11eb-0a80-07bf001ea079',
      ],
      'deleteAllFromOrderState' => '02482aa0-ee91-11ea-0a80-05f20007446d',
      'orderStateInvoiceIssued' => '6d4d6565-79a4-11eb-0a80-07bf001ea079',
      'invoiceOutStateIssued' => '8cb3325d-0a72-11ed-0a80-0266001870b2',
      'stateMapOrderToDemand' => [
        'd3e01366-75ca-11eb-0a80-02590037e535' => 'db67917a-5717-11eb-0a80-079c002b43eb',
        '6d4d6565-79a4-11eb-0a80-07bf001ea079' => '2ecdeb7b-799b-11eb-0a80-00de001d8587',
      ],
      'stateMapDemandToOrder' => [
        'eeed10b7-51a2-11ec-0a80-02ee0032e089' => 'c4d8f685-a7c3-11ed-0a80-10870015dd4a',
        '732ffbde-0a19-11eb-0a80-055600083d2e' => '02482dd6-ee91-11ea-0a80-05f200074471',
        '24d4a11f-8af4-11eb-0a80-0122002915d0' => '02482dd6-ee91-11ea-0a80-05f200074471',
        '0ba2e09c-cda1-11eb-0a80-03110030c70c' => '02482e52-ee91-11ea-0a80-05f200074472',
        'aa7acdbc-a7c9-11ed-0a80-0c71001732ca' => '02482e52-ee91-11ea-0a80-05f200074472',
      ],
      'demandStatePassed' => '732ffbde-0a19-11eb-0a80-055600083d2e',
      'demandStateClosed' => '24d4a11f-8af4-11eb-0a80-0122002915d0',
      'demandUpdateHandler' => [
        'stateDemandCollected'    => 'eeed10b7-51a2-11ec-0a80-02ee0032e089',
        'stateDemandReturnNoCheck'=> '0ba2e09c-cda1-11eb-0a80-03110030c70c',

        'attrFiscalCheck'         => 'eb46b957-a4e7-11eb-0a80-014c00169cca',
        'attrFiscalCheckYes'      => 'c3c0ee4f-a4e7-11eb-0a80-075b00176e05',

        'stateOrderCollected'     => 'c4d8f685-a7c3-11ed-0a80-10870015dd4a',
        'stateOrderReturn'        => '02482e52-ee91-11ea-0a80-05f200074472',

        'stateInvoiceCanceled'    => '8cb333d7-0a72-11ed-0a80-0266001870b4',

        'statePaymentInCanceled'  => '529982b4-346a-11eb-0a80-04cd00042955',
        'stateCashInCanceled'     => '735c68bc-f6ce-11eb-0a80-0153001141cf',
      ],
      // order state:
      'orderStateCompleted' => '02482dd6-ee91-11ea-0a80-05f200074471',

      // cash payment type (custom entity id from твоего примера):
      'cashPaymentTypeId' => '1fd236d5-836d-11ed-0a80-0dbe0033eb31',

      // money-in states:
      'paymentInStateWaiting' => '529980fb-346a-11eb-0a80-04cd00042953',
      'cashInStateWaiting'    => 'fc9b310f-2738-11eb-0a80-0902001c1e9c',

      'channelAttrId' => '45bdad04-68d6-11ee-0a80-095d000776da', // ☎️ Каналы связи
      'paymentTypeAttrId' => '19fb8dcf-94ac-11ed-0a80-0e930023e914',
      'configFallbackByPaymentType' => false, // по желанию

    ],
];
