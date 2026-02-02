<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\filters\VerbFilter;
use app\models\Moysklad;
use app\services\MoyskladWebhookService;
use app\services\handlers\CustomerOrderCreateHandlerV2;
use app\services\handlers\CustomerOrderUpdateHandlerV2;
use app\services\handlers\DemandUpdateHandlerV2;
use app\services\handlers\SalesreturnUpdateHandlerV2;

class WebhookController extends Controller
{
    // Для вебхуков обычно отключают CSRF

    public $enableCsrfValidation = false;

    private function respondOkAndClose(): void
    {
        // 204 = “успешно, тела нет” — идеально для вебхуков
        $resp = Yii::$app->response;
        $resp->statusCode = 204;
        $resp->format = \yii\web\Response::FORMAT_RAW;
        $resp->content = '';

        // отправляем ответ клиенту СРАЗУ
        $resp->send();

        // важно: закрыть сессию, если вдруг открыта
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // не убиваем выполнение скрипта, даже если клиент отключился
        ignore_user_abort(true);

        // сбросить буферы (на всякий)
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        // ключевое: закрыть соединение в PHP-FPM
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    public function behaviors()
    {
        return [];
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    // вебхуки МойСклада приходят POST-ом
                    'index' => ['post'],
                    'createcustomerorder' => ['post'],
                    'updatecustomerorder' => ['post'],
                    'updatedemand'        => ['get','post'],
                    'updatesalesreturn'   => ['post'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $request = Yii::$app->request;

        // // 1) Проверяем секретный токен в GET-параметре
        // $token = $request->get('token');
        // if ($token !== self::SECRET_TOKEN) {
        //     Yii::warning('Webhook: wrong token from IP ' . $request->userIP, __METHOD__);
        //     throw new ForbiddenHttpException('Forbidden');
        // }

        // // 2) Доп. защита: проверяем User-Agent (его МойСклад ставит в вебхуках)
        // $ua = $request->userAgent;
        // if (stripos($ua, 'MoySklad webhook touch agent 2.0') === false) {
        //     Yii::warning('Webhook: wrong UA ' . $ua . ' from IP ' . $request->userIP, __METHOD__);
        //     throw new ForbiddenHttpException('Forbidden');
        // }

        return true;
    }

    public function actionIndex()
    {
        $rawBody = Yii::$app->request->getRawBody();
        $data = json_decode($rawBody, true);

        // тут сохраняешь/обрабатываешь вебхук
        Yii::info('MoySklad webhook: ' . $rawBody, __METHOD__);

        // МойСклад ждёт 200/204
        return 'ok';
    }

    private function handleWebhook(string $logFile): string
    {
        $raw = file_get_contents('php://input');
        file_put_contents($logFile, $raw . PHP_EOL, FILE_APPEND);

        // ✅ ответ сразу
        $this->respondOkAndClose();

        $payload = json_decode($raw);
        if (!$payload) {
            file_put_contents($logFile, "WARN: bad json\n", FILE_APPEND);
            return '';
        }

        // ✅ вот тут “тестовая логика”, но для реальных вебхуков
        $this->handleMsV2Payload($payload, $logFile);

        return '';
    }

    private function handleMsV2Payload(object $payload, string $logFile): void
    {
        if (empty($payload->events) || !is_array($payload->events)) {
            file_put_contents($logFile, "WARN: empty events\n", FILE_APPEND);
            return;
        }

        foreach ($payload->events as $event) {
            $type   = $event->meta->type ?? null;
            $action = $event->action ?? null;

            try {
                // ВАЖНО: тут роутинг на нужный handler (пример)
                switch ($type) {
                    case 'customerorder':
                        $action = strtoupper((string)($event->action ?? ''));
                        if ($action === 'CREATE') {
                            (new CustomerOrderCreateHandlerV2())->handle($event);
                        }
                        elseif ($action === 'UPDATE') {
                            (new CustomerOrderUpdateHandlerV2())->handle($event);
                        }
                        break;
                    case 'demand':
                        (new DemandUpdateHandlerV2())->handle($event);
                        break;
                    case 'salesreturn':
                        (new SalesreturnUpdateHandlerV2())->handle($event);
                        break;
                    default:
                        file_put_contents($logFile, "SKIP: unknown type={$type}\n", FILE_APPEND);
                        break;
                }
            } catch (\Throwable $e) {
                file_put_contents(
                    $logFile,
                    "ERROR: type={$type} action={$action} msg={$e->getMessage()}\n{$e->getTraceAsString()}\n",
                    FILE_APPEND
                );
            }
        }
    }


    /* Вебхуки Мойсклад */

    public function actionCreatecustomerorder() // Создание нового заказа / Создание отгрузки
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/createcustomerorder.txt'
      );
    }

    public function actionUpdatecustomerorder() // Обновление заказа / Отгрузки / Позиций
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/updatecustomerorder.txt'
      );
    }

    public function actionUpdatedemand() // Обновление отгрузки → заказ / Позиций
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/updatedemand.txt'
      );
    }

    public function actionUpdatesalesreturn() // Обновление возврата
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/salesreturn.txt'
      );
    }

    /* EOF Вебхуки Мойсклад */


    /* Тестовые экшены */

    // public function actionTestMsV2()
    // {
    //     $this->layout = false;
    //     \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    //
    //     $json = \Yii::$app->request->post('json');
    //     if (!$json) {
    //         // можно и в коде хранить дефолт
    //         // $json = '{"events":[{"meta":{"type":"customerorder","href":"https://api.moysklad.ru/api/remap/1.2/entity/customerorder/ceae17f1-f560-11f0-0a80-045f000094d7"},"action":"UPDATE","accountId":"021a4ccc-ee91-11ea-0a80-09f000002b18"}]}';
    //         // $json = '{"events":[{"meta":{"type":"demand","href":"https://api.moysklad.ru/api/remap/1.2/entity/demand/7f6f5f31-f543-11f0-0a80-1474007f29b9"},"action":"UPDATE","accountId":"021a4ccc-ee91-11ea-0a80-09f000002b18"}]}';
    //     }
    //
    //     $payload = json_decode($json);
    //     if (!$payload || empty($payload->events)) {
    //         return ['ok' => false, 'error' => 'bad json'];
    //     }
    //
    //     // берём первый event
    //     $event = $payload->events[0];
    //
    //     // вызов нужного handler-а (V2)
    //     $handler = new \app\services\handlers\DemandUpdateHandlerV2();
    //     $handler->handle($event);
    //
    //     return ['ok' => true, 'handled' => true, 'type' => $event->meta->type ?? null, 'action' => $event->action ?? null];
    // }

    /* EOF Тестовые экшены */
}
