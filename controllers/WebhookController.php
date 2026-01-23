<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\filters\VerbFilter;
use app\models\Moysklad;
use app\services\MoyskladWebhookService;

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
                    'updatedemand' => ['get','post'],
                    'createsalesreturn' => ['post'],
                    'updatesalesreturn' => ['post'],
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
// $raw = '{"auditContext":{"meta":{"type":"audit","href":"https://api.moysklad.ru/api/remap/1.2/audit/55bec62c-f82f-11f0-0a80-0b3c0006165d"},"uid":"online@2336623","moment":"2026-01-23 10:44:23"},"events":[{"meta":{"type":"customerorder","href":"https://api.moysklad.ru/api/remap/1.2/entity/customerorder/4081cebb-f82b-11f0-0a80-0472000f6157"},"updatedFields":["state"],"action":"UPDATE","accountId":"021a4ccc-ee91-11ea-0a80-09f000002b18"}]}';

      // лог — быстро (но лучше без giant print_r)
      file_put_contents($logFile, $raw . PHP_EOL, FILE_APPEND);

      // ✅ СНАЧАЛА ответ МойСкладу (в 1500ms успеет всегда)
      $this->respondOkAndClose();

      // дальше — тяжёлая логика уже после закрытия соединения
      $payload = json_decode($raw);
      if (!$payload || empty($payload->events)) {
          return ''; // ответ уже отправлен
      }


      try {
          (new \app\services\MoyskladWebhookService())->handle($payload);
      } catch (\Throwable $e) {
          file_put_contents(
              $logFile,
              "ERROR: {$e->getMessage()}\n{$e->getTraceAsString()}\n",
              FILE_APPEND
          );
      }

      return ''; // ответ уже отправлен
    }




    public function actionTestMsV2()
    {
        $this->layout = false;
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $json = \Yii::$app->request->post('json');
        if (!$json) {
            // можно и в коде хранить дефолт
            // $json = '{"events":[{"meta":{"type":"demand","href":"https://api.moysklad.ru/api/remap/1.2/entity/demand/cc2ad48f-f82e-11f0-0a80-19e00006480e"},"action":"UPDATE","accountId":"021a4ccc-ee91-11ea-0a80-09f000002b18"}]}';
            $json = '{"events":[{"meta":{"type":"demand","href":"https://api.moysklad.ru/api/remap/1.2/entity/demand/7f6f5f31-f543-11f0-0a80-1474007f29b9"},"action":"UPDATE","accountId":"021a4ccc-ee91-11ea-0a80-09f000002b18"}]}';
        }

        $payload = json_decode($json);
        if (!$payload || empty($payload->events)) {
            return ['ok' => false, 'error' => 'bad json'];
        }

        // берём первый event
        $event = $payload->events[0];

        // вызов нужного handler-а (V2)
        $handler = new \app\services\handlers\DemandUpdateHandlerV2();
        $handler->handle($event);

        return ['ok' => true, 'handled' => true, 'type' => $event->meta->type ?? null, 'action' => $event->action ?? null];
    }



    /* Вебхуки Мойсклад */

    public function actionCreatecustomerorder() // Создание нового заказа / Создание отгрузки
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/createcustomerorder.txt'
      );
    }

    public function actionCreatesalesreturn()
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/createsalesreturn.txt'
      );
    }

    public function actionCreatedemand()
    {
      return 'ok';
      $data1 = file_get_contents('php://input');
      $data2 = $_POST;
      file_put_contents(__DIR__ . '/createdemand.txt',print_r($data1,true) . PHP_EOL,FILE_APPEND);
      file_put_contents(__DIR__ . '/createdemand.txt',print_r($data2,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
      return $this->render('createdemand');
    }

    public function actionUpdatecustomerorder() // Обновление заказа / Отгрузки / Позиций
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/updatecustomerorder.txt'
      );
    }

    public function actionUpdatesalesreturn()
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/updatesalesreturn.txt'
      );
    }

    public function actionUpdatedemand() // Обновление отгрузки → заказ / Позиций
    {
      return $this->handleWebhook(
          __DIR__ . '/../logs/ms_service/updatedemand.txt'
      );
    }

    public function actionDeletecustomerorder()
    {
      return 'ok';
      $data1 = file_get_contents('php://input');
      $data2 = $_POST;
      file_put_contents(__DIR__ . '/deletecustomerorder.txt',print_r($data1,true) . PHP_EOL,FILE_APPEND);
      file_put_contents(__DIR__ . '/deletecustomerorder.txt',print_r($data2,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
      return $this->render('deletecustomerorder');
    }

    public function actionDeletedemand()
    {
      return 'ok';
      $data1 = file_get_contents('php://input');
      $data2 = $_POST;
      file_put_contents(__DIR__ . '/deletedemand.txt',print_r($data1,true) . PHP_EOL,FILE_APPEND);
      file_put_contents(__DIR__ . '/deletedemand.txt',print_r($data2,true) . PHP_EOL . PHP_EOL,FILE_APPEND);
      return $this->render('deletedemand');
    }

    /* EOF Вебхуки Мойсклад */

}
