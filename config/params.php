<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'cronToken' => 'GNIWRNB82589NVWU',

    'moysklad' => [
      'loopGuardTtl' => 10,
      'allowDemandStates' => [
        'd3e01366-75ca-11eb-0a80-02590037e535',
        '6d4d6565-79a4-11eb-0a80-07bf001ea079',
      ],
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

      'channelAttrId' => '45bdad04-68d6-11ee-0a80-095d000776da', // ☎️ Каналы связи
      'configFallbackByPaymentType' => false, // по желанию

    ],
];
