<?php
namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\Query;

class Whatsapp extends Model
{
  public function sendWhatsappMessage($data,$phone,$template,$shopid,$send)
  {
    if(!$send){ return array('success' => 1); }

    $apiClient = new ApiClient(SENDPULSE_API_USER_ID, SENDPULSE_API_SECRET, new FileStorage());

    try {
        $sendTemplateByPhoneResult = $apiClient->post('whatsapp/contacts/sendTemplateByPhone', [
            "bot_id" => "626546739d06f4651210b358",
            "phone" => $phone,
            "template" => [
                "name" => $template,
                "language" => [
                    "policy" => "deterministic",
                    "code" => "ru"
                ],
                "components" => [
                  (object)array(
                    "type" => "body",
                    "parameters" => [
                      (object)array(
                        "type" => "text",
                        "text" => $data->name
                      ),
                      (object)array(
                        "type" => "text",
                        "text" => $data->link
                      )
                    ]
                  )
                ]
            ]
        ]);
        file_put_contents(__DIR__ . '/../logs/kaspi/whatsappSendingMessage.txt','order: ' . $data->orderid . PHP_EOL . print_r($sendTemplateByPhoneResult,true) . PHP_EOL . PHP_EOL, FILE_APPEND);
      return $sendTemplateByPhoneResult;
    } catch (ApiClientException $e) {
      return false;
    }
  }

  public function checkWhatsappSendpulseMessage($sended)
  {
    $apiClient = new ApiClient(SENDPULSE_API_USER_ID, SENDPULSE_API_SECRET, new FileStorage());

    try {
        $setSendingDate = $apiClient->post('whatsapp/contacts/setVariable', [
            "contact_id" => $sended['data']['contact_id'],
            "variables" => array(
              (object)array(
                "variable_name" => "Шаблон Kaspi",
                "variable_value" => date('Y-m-d')
              )
            )
          ]);

        file_put_contents(__DIR__ . '/../logs/kaspi/whatsappSendingMessage.txt','order: ' . $data->orderid . PHP_EOL . print_r($sendTemplateByPhoneResult,true) . PHP_EOL . PHP_EOL, FILE_APPEND);
      return $setSendingDate;
    } catch (ApiClientException $e) {
      return false;
    }
  }

}
