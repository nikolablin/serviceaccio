<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'cronToken' => 'GNIWRNB82589NVWU',

    'halyk' => [
      'client_id' => 'HMM_910407300181',
      'client_secret' => 'aEUr_RaIENZi'
    ],

    'wolt' => [
      'baseUrl'           => 'https://pos-integration-service.wolt.com/',
      'token'             => 'f539fcc5c3bcaf861f52fa6b278728a347abf251a6d336f3f28c3d2445e7a1fb',
      'secret'            => 'f539fcc5c3bcaf861f52fa6b278728a347abf251a6d336f3f28c3d2445e7a1fb',

      'almaty_order_api_key'     => 'Fq3oGC4jYcvywOOIxvcc30Hfp2g0Hly7CjG3YvMWI4Q=',
      'astana_order_api_key'     => 'AT-yX16C1-Yvwf7AdYUSFLUEBjzyk7I7q_IjZ97AGV8=',

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

    'sendpulse' => [
      'user_id' => '7289be20b0b529f7fb296aad74832408',
      'secret'  => '257d30d3c90d55ae84ca661758347ea6',
      'bot_id'  => '626546739d06f4651210b358',
    ],

    'moyskladv2' => [

      'login'     => 'online@2336623',
      'password'  => 'Gj953928$',

      'products' => [
        'attributesFields' => [
          'ntin' => '594f2460-e4af-11f0-0a80-192e0037459c', // Атрибут NTIN продукта
        ]
      ],

      'invoicesout' => [
        'states' => [
          'invoiceissued' => '8cb3325d-0a72-11ed-0a80-0266001870b2',
        ]
      ],

      'factureOut' => [
        'states' => [
          'created' => '5dd3895d-0bbf-11ec-0a80-057d001991fa'
        ]
      ],

      'moneyin' => [
        'states' => [
          'paymentin' => [
            'invoiceissued'   => '6e980f8b-1a83-11f0-0a80-0dbb000314b7',
            'waitForIncoming' => '529980fb-346a-11eb-0a80-04cd00042953',
            'cancelled' => '529982b4-346a-11eb-0a80-04cd00042955',
          ],
          'cashin' => [
            'waitForIncoming' => 'fc9b310f-2738-11eb-0a80-0902001c1e9c',
            'cancelled' => '735c68bc-f6ce-11eb-0a80-0153001141cf',
          ],
        ],

        'attributesFields' => [
          'paymentin' => [
            'manager'       => '2ec56836-aa20-11ed-0a80-09620021e953',
            'incomeStream'  => '9401deb0-0bc0-11ec-0a80-021c0019cf2b',
            'paymentType'   => 'f36cd71a-ace5-11ed-0a80-06ac001a56c2',
            'orderNumber'   => '886cd568-ea7f-11ed-0a80-10a80071443d',
            'fiscalNeed'    => false
          ],
          'cashin' => [
            'manager'       => '442918d8-aa20-11ed-0a80-0fba00223f9c',
            'incomeStream'  => '8184c165-0bc0-11ec-0a80-0817001a8435',
            'paymentType'   => '7e92f4ab-ace6-11ed-0a80-0570001a3e98',
            'orderNumber'   => false,
            'fiscalNeed'    => '03352294-a4e9-11eb-0a80-00dd001686d3'
          ]
        ],

        'attributesFieldsDictionaries' => [
          'incomeStream'  => '1762788a-0bc0-11ec-0a80-00750019f2ed',
          'paymentType'   => 'd8662995-836c-11ed-0a80-04de0034157c',
          'fiscalNeed'    => 'b6fc53ef-a4e7-11eb-0a80-0dc70016db30'
        ],

        'attributesFieldsDictionariesValues' => [
          'cashin' => [
            'needFiscalYes' => 'c3c0ee4f-a4e7-11eb-0a80-075b00176e05',
            'needFiscalNo' => 'c919fb37-a4e7-11eb-0a80-00dd00166ffd'
          ],
        ],

        'attributesFieldsValues' => [
          'roznSales'   => '392e4b46-0bc0-11ec-0a80-057d0019c290',    // Значение Продажи розничные атрибута Статья доходов
          'marketSales' => '925feba1-82ba-11ed-0a80-004000288b85',    // Значение Продажи маркетплейсы атрибута Статья доходов
        ],
      ],

      'demands' => [
        'expand' => 'state,customerOrder,attributes,agent,organization,positions.assortment,owner,factureout',
        'states' => [
          'acceptBack'        => '2a6c9db5-a7c4-11ed-0a80-10870015e950',
          'assembled'         => 'eeed10b7-51a2-11ec-0a80-02ee0032e089',
          'backtostock'       => 'aa7acdbc-a7c9-11ed-0a80-0c71001732ca',
          'backwithoutbill'   => '0ba2e09c-cda1-11eb-0a80-03110030c70c',
          'closed'            => '24d4a11f-8af4-11eb-0a80-0122002915d0',
          'invoiceissued'     => '2ecdeb7b-799b-11eb-0a80-00de001d8587',
          'todemand'          => 'db67917a-5717-11eb-0a80-079c002b43eb',
          'transferred'       => '732ffbde-0a19-11eb-0a80-055600083d2e',
        ],

        'attributesFields' => [
          'billLink'          => '1ff6c2e8-1c3a-11ec-0a80-06650003408f',
          'returnBillLink'    => '2362f797-d068-11ec-0a80-0b8a00a44340',
          'numPlaces'         => 'f1d4a71a-c29a-11eb-0a80-001f0003a1be',
          'fiscal'            => 'eb46b957-a4e7-11eb-0a80-014c00169cca',
          'paymentType'       => 'b4b6c6d6-836d-11ed-0a80-07fe00347b40',
          'paymentStatus'     => '64869eb0-c29d-11eb-0a80-08be00040769',
          'marketPlaceNum'    => 'db30d9e9-a4e2-11eb-0a80-09b900160bbe',
          'waybillMark'       => '60dbcc74-a9e6-11ed-0a80-111e001b5386',
        ],

        'attributesFieldsValues' => [
          'fiscalYes' => 'c3c0ee4f-a4e7-11eb-0a80-075b00176e05',  // Значение Да в атрибуте Фискальный чек
          'cashYes'   => '1fd236d5-836d-11ed-0a80-0dbe0033eb31', // Значение Наличные в атрибуте Способ оплаты
          'payedYes'  => '302da776-c29d-11eb-0a80-093a0003ad4a', // Значение Оплачен в атрибуте Статус оплаты
        ],

        'upsertDemandAttributes' => [ // 'ID атрибута заказа' => 'ID атрибута отгрузки'
          '263aa028-2ba1-11ed-0a80-056b000879a8' => '1b0e12b6-3471-11eb-0a80-096400054956', // ✅ Город - Город
          '8a307d43-3b6a-11ee-0a80-06ae000fd467' => 'b2b883e2-3464-11eb-0a80-00f10003703a', // Служба доставки - Служба доставки
          '19fb8dcf-94ac-11ed-0a80-0e930023e914' => 'b4b6c6d6-836d-11ed-0a80-07fe00347b40', // Способ оплаты
          '17545020-4d14-11ed-0a80-0ef600207483' => 'b7665340-75d0-11eb-0a80-0259003872fe', // ✅ Предпочтительная дата доставки - Дата доставки
          'f313d67e-94ac-11ed-0a80-0e930023fd09' => '452d785a-75d1-11eb-0a80-05af00386c25', // ✅ Предпочтительное время доставки - Время доставки
          '4e4537e9-a0a2-11ed-0a80-1043003e432d' => 'eb46b957-a4e7-11eb-0a80-014c00169cca', // Нужен ли фискальный чек - ❗️Нужен ли фискальный чек?
          'a7f0812d-a0a3-11ed-0a80-114f003fc7f9' => 'db30d9e9-a4e2-11eb-0a80-09b900160bbe', // Номер заказа маркетплейса
          'f27758fb-b05d-11ed-0a80-09ae002b500b' => '64869eb0-c29d-11eb-0a80-08be00040769', // Статус оплаты
          '45bdad04-68d6-11ee-0a80-095d000776da' => 'b15cb6d6-295c-11ef-0a80-005b0036e745', // ☎️ Каналы связи
          'dd839a8b-47a1-11ed-0a80-01fb00205e82' => '892e27a6-99f5-11eb-0a80-0451000e89e5' // Проект
        ],
      ],

      'orders' => [
        'expand' => 'state,store,project,attributes,agent,organization,demands,positions.assortment,owner,invoicesOut',
        'states' => [
          'approvetodemand' => 'd3e01366-75ca-11eb-0a80-02590037e535',
          'assembled'       => 'c4d8f685-a7c3-11ed-0a80-10870015dd4a',
          'back'            => '02482e52-ee91-11ea-0a80-05f200074472',
          'canceled'        => '02482f22-ee91-11ea-0a80-05f200074473',
          'completed'       => '02482dd6-ee91-11ea-0a80-05f200074471',
          'injob'           => 'c58fb0d7-9c2a-11eb-0a80-06480005f781',
          'invoiceissued'   => '6d4d6565-79a4-11eb-0a80-07bf001ea079',
          'taketojob'       => '02482aa0-ee91-11ea-0a80-05f20007446d',

        ],
        'attributesFields' => [
          'paymentType'   => '19fb8dcf-94ac-11ed-0a80-0e930023e914',
          'channel'       => '45bdad04-68d6-11ee-0a80-095d000776da',
          'fiskal'        => '4e4537e9-a0a2-11ed-0a80-1043003e432d',
          'project'       => 'dd839a8b-47a1-11ed-0a80-01fb00205e82',
          'paymentStatus' => 'f27758fb-b05d-11ed-0a80-09ae002b500b',
          'delivery'      => '8a307d43-3b6a-11ee-0a80-06ae000fd467',
        ],
      ],

      'salesreturn' => [
        'states' => [
          'finish' => '88b390bc-87dc-11ec-0a80-0fbe0028a739',
        ],

        'attributesFields' => [
          'backReason' => '245c1e2c-857c-11ec-0a80-00ca00032d13'
        ],

        'attributesFieldsDictionaries' => [
          'backReason' => 'e374d372-857b-11ec-0a80-0fbe0003a99c'
        ],

        'attributesFieldsDictionariesValues' => [
          'backReason1' => 'f3aa06bf-857b-11ec-0a80-07b7000347ae' // Передумал покупать
        ]
      ],

      'kaspiProjects' => [
        'accio' => '5f351348-d269-11f0-0a80-15120016d622', // 🔴 Kaspi Accio
        'tutto' => '431a8172-d26a-11f0-0a80-0f110016cabd', // 🔴 Tutto Capsule Kaspi
        'ital'  => '98777142-d26a-11f0-0a80-1be40016550a', // 🔴 Ital Trade
      ],

      'woltProject' => 'a463b9da-d26c-11f0-0a80-1a6b0016a57a', // Wolt

      'vat' => [
        'vatOrganizations' => [ // Организации, где применяется НДС
          '3bd63649-f257-11ea-0a80-005d003d9ee4', // Итал Фудс
          'cdc20315-59ab-11ec-0a80-07d3000e5794', // Скельта
        ],
        'value' => '16',
      ],

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

      'staticReferenceValues' => [
        'channelIsWebsite' => '82257929-68d7-11ee-0a80-0f2f0008c9a9', // Значение Сайт из Справочника Каналы связи
      ],
    ],

    'moysklad' => [
      'loopGuardTtl' => 10,
      'allowDemandStates' => [
        'd3e01366-75ca-11eb-0a80-02590037e535',
        '6d4d6565-79a4-11eb-0a80-07bf001ea079',
      ],
      'autoorderAttrId' => '93cb86d9-f2df-11f0-0a80-1472000f820b',
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
      'salesReturnState1' => '88b38f1f-87dc-11ec-0a80-0fbe0028a736',
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
      'paymentInStateSchetVistavlen' => '6e980f8b-1a83-11f0-0a80-0dbb000314b7',

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
      'organizationProject' => '6b625db1-d270-11f0-0a80-1512001756b3', // Юридические лица
      'kaspiTokens' => [
        'accio' => 'dbU852Hq+JDbq5OiGDE+lZOkbpKgNX/qFfYfQTBYU60=',
        'tutto' => 'BiQdZihpwlTXKY2Ny6mCQiVnPHw8YwuuXExf6o1PB+8=',
        'ital'  => 'GBdEjOo4M4miI1ghi/yN/y9L6BZMrpE3UKRx4Vsc0lM='
      ]
    ],
];
