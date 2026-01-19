<?php
namespace app\models;

use Yii;
use yii\base\Model;
use Sendpulse\RestApi\ApiClient;
use Sendpulse\RestApi\Storage\FileStorage;
use Sendpulse\RestApi\ApiClientException;

class Whatsapp extends Model
{
    private static ?ApiClient $client = null;

    /**
     * Singleton SendPulse client
     */
    protected function spClient(): ApiClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $storageDir = Yii::getAlias('@runtime/sendpulse');

        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }

        self::$client = new ApiClient(
            Yii::$app->params['sendpulse']['user_id'],
            Yii::$app->params['sendpulse']['secret'],
            new FileStorage($storageDir)
        );

        return self::$client;
    }

    public function sendWhatsappMessage($data, $phone, $template, $shopid, $send)
    {
        if (!$send) {
            return ['success' => 1];
        }

        try {
            $res = $this->spClient()->post(
                'whatsapp/contacts/sendTemplateByPhone',
                [
                    "bot_id" => Yii::$app->params['sendpulse']['bot_id'],
                    "phone"  => $phone,
                    "template" => [
                        "name" => $template,
                        "language" => [
                            "policy" => "deterministic",
                            "code"   => "ru",
                        ],
                        "components" => [
                            [
                                "type" => "body",
                                "parameters" => [
                                    ["type" => "text", "text" => (string)($data->name ?? '')],
                                    ["type" => "text", "text" => (string)($data->link ?? '')],
                                ],
                            ],
                        ],
                    ],
                ]
            );

            file_put_contents(
                __DIR__ . '/../logs/kaspi/whatsappSendingMessage.txt',
                'SEND order=' . ($data->orderid ?? '-') . PHP_EOL .
                print_r($res, true) . PHP_EOL . PHP_EOL,
                FILE_APPEND
            );

            return $res;

        } catch (ApiClientException $e) {
            file_put_contents(
                __DIR__ . '/../logs/kaspi/whatsappSendingMessage.txt',
                'ERROR sendTemplate: ' . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );
            return false;
        }
    }

    public function checkWhatsappSendpulseMessage(array $sended, string $orderId = '')
    {
        try {
            $contactId = $sended['data']['contact_id'] ?? null;
            if (!$contactId) {
                return false;
            }

            $res = $this->spClient()->post(
                'whatsapp/contacts/setVariable',
                [
                    "contact_id" => $contactId,
                    "variables" => [
                        [
                            "variable_name"  => "Шаблон Kaspi",
                            "variable_value" => date('Y-m-d'),
                        ],
                    ],
                ]
            );

            file_put_contents(
                __DIR__ . '/../logs/kaspi/whatsappSendingMessage.txt',
                'SETVAR order=' . $orderId . PHP_EOL .
                print_r($res, true) . PHP_EOL . PHP_EOL,
                FILE_APPEND
            );

            return $res;

        } catch (ApiClientException $e) {
            file_put_contents(
                __DIR__ . '/../logs/kaspi/whatsappSendingMessage.txt',
                'ERROR setVariable: ' . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );
            return false;
        }
    }
}
