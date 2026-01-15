<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'cronToken' => 'GNIWRNB82589NVWU',

    'wolt' => [
      'baseUrl'           => 'https://pos-integration-service.wolt.com/',
      'token'             => '23c529c7cfdca5a82bbfd3a846a72c1ed347c83c28e9ef8e53979f58661a8b29',
      'secret'            => '670e3e05e5c7bdb1cda7e542ae9c73cc32451bda73958971fbe3e6324215a8a3',

      'venue_id'          => '69671b2c02c47b76dea13d6b',
      'order_api_key'     => 'OtIDBSjHwF5H87xxscgKUqr_WJJgvzth5BhU-A9oXE0=',
      'menu_api_login'    => 'accio_test_menuapi',
      'menu_api_password' => 'ae0b45f571fd6ee2c490e48fde095fd7049da2950b14e3190168cb4d07074151',
      'timeout'           => 20,
      'almaty_venue_id'    => '6141dc39feba5992a4d2e1d5',
      'astana_venue_id'    => '617bcd10ab0bd1f03165036e',
      'test_baseUrl'       => 'https://pos-integration-service.development.dev.woltapi.com/',
      'test_order_api_key' => 'OtIDBSjHwF5H87xxscgKUqr_WJJgvzth5BhU-A9oXE0=',
      'test_almaty_venue_id'    => '69671b2c02c47b76dea13d6b',
      'test_astana_venue_id'    => '69671b2c02c47b76dea13d6b_astana',
    ],

    'ukassa' => [
      'baseUrl'    => 'https://ukassa.kz',
      'loginPath'  => '/api/auth/login/',
      'receiptPath'=> '/api/v2/operation/ticket/',
      // 'receiptPath'=> '/api/v1/ofd/receipt/',
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
      'sections' => [
          'UK00003842' => 3682,
          'UK00003857' => 3673,
          'UK00003827' => 3853,
          'UK00003854' => 3694,
          'UK00006240' => 6373,
          'UK00006241' => 6374,
      ],
      'operationTypeReturn' => 3,
      'operationTypeSell'   => 2
    ],

    'moysklad' => [
      'loopGuardTtl' => 10,
      'allowDemandStates' => [
        'd3e01366-75ca-11eb-0a80-02590037e535',
        '6d4d6565-79a4-11eb-0a80-07bf001ea079',
      ],
      'takeToJobOrderState' => '02482aa0-ee91-11ea-0a80-05f20007446d',
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
        'stateToDemand'           => '/db67917a-5717-11eb-0a80-079c002b43eb',

        'stateDemandCollected'    => 'eeed10b7-51a2-11ec-0a80-02ee0032e089',
        'stateDemandReturnNoCheck'=> '0ba2e09c-cda1-11eb-0a80-03110030c70c',
        'stateDemandDoReturn'     => '2a6c9db5-a7c4-11ed-0a80-10870015e950',

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
      'cashPaymentStatus' => '435a9570-c29d-11eb-0a80-077e0003e5b9', // Статус оплаты - Принять оплату в заказе
      'cashPaymentStatusPayed' => '302da776-c29d-11eb-0a80-093a0003ad4a',

      // money-in states:
      'paymentInStateWaiting' => '529980fb-346a-11eb-0a80-04cd00042953',
      'cashInStateWaiting'    => 'fc9b310f-2738-11eb-0a80-0902001c1e9c',

      // Платежные Статьи доходов
      'incomeIssues' => [
        'cashinIssueAttrId' => '8184c165-0bc0-11ec-0a80-0817001a8435', // Атрибут cashin Статья доходов
        'paymentIssueAttrId' => '9401deb0-0bc0-11ec-0a80-021c0019cf2b', // Атрибут paymentin Статья доходов
        'paymentTypeIssueAttrId' => 'f36cd71a-ace5-11ed-0a80-06ac001a56c2', // Атрибут Способ оплаты во входящем платеже
        'paymentTypeIssueCashAttrId' => '7e92f4ab-ace6-11ed-0a80-0570001a3e98', // Атрибут Способ оплаты в приходном ордере
        'orderNumIssueAttrId' => '886cd568-ea7f-11ed-0a80-10a80071443d', // Атрибут Номер заказа во входящем платеже

        'incomeDictId' => '1762788a-0bc0-11ec-0a80-00750019f2ed',

        'roznProdaji' => '392e4b46-0bc0-11ec-0a80-057d0019c290', // Продажи розничные
        'marketProdaji' => '925feba1-82ba-11ed-0a80-004000288b85', // Продажи маркетплейсы

        'marketplaceProjects' => [
          '5f351348-d269-11f0-0a80-15120016d622', // 🔴 Kaspi Accio
          '431a8172-d26a-11f0-0a80-0f110016cabd', // 🔴 Tutto Capsule Kaspi
          '98777142-d26a-11f0-0a80-1be40016550a', // 🔴 Ital Trade
          'a463b9da-d26c-11f0-0a80-1a6b0016a57a', // 🔵 Wolt
          '842c5548-c90c-11f0-0a80-1aee002c13e9', // 🟢 Halyk Market
          'a4481c66-d274-11f0-0a80-0f110017905c', // 🟣 Forte Market
        ],
        'roznProjects' => [
          '8fe86883-d275-11f0-0a80-15120017c4b6', // 🔥 Store
          '6b625db1-d270-11f0-0a80-1512001756b3' // 💎 Юридическое лицо
        ],
      ],

      'channelAttrId' => '45bdad04-68d6-11ee-0a80-095d000776da', // ☎️ Каналы связи
      'paymentTypeAttrId' => '19fb8dcf-94ac-11ed-0a80-0e930023e914',
      'demandPaymentTypeAttrId' => 'b4b6c6d6-836d-11ed-0a80-07fe00347b40',
      'demandPaymentStatusAttrId' => '64869eb0-c29d-11eb-0a80-08be00040769',
      'configFallbackByPaymentType' => false, // по желанию
      'kaspiProjects' => [
        'accio' => '5f351348-d269-11f0-0a80-15120016d622', // 🔴 Kaspi Accio
        'tutto' => '431a8172-d26a-11f0-0a80-0f110016cabd', // 🔴 Tutto Capsule Kaspi
        'ital'  => '98777142-d26a-11f0-0a80-1be40016550a', // 🔴 Ital Trade
      ],
      'woltProject' => 'a463b9da-d26c-11f0-0a80-1a6b0016a57a',
      'kaspiTokens' => [
        'accio' => 'dbU852Hq+JDbq5OiGDE+lZOkbpKgNX/qFfYfQTBYU60=',
        'tutto' => 'BiQdZihpwlTXKY2Ny6mCQiVnPHw8YwuuXExf6o1PB+8=',
        'ital'  => 'GBdEjOo4M4miI1ghi/yN/y9L6BZMrpE3UKRx4Vsc0lM='
      ]
    ],
];
