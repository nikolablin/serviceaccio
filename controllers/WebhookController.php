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

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    // вебхуки МойСклада приходят POST-ом
                    'index' => ['post'],
                    'createcustomerorder' => ['post'],
                    'updatecustomerorder' => ['post'],
                    'updatedemand' => ['post'],
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
        file_put_contents($logFile, $raw . PHP_EOL, FILE_APPEND);

        $payload = json_decode($raw);
        if (!$payload || empty($payload->events)) {
            return 'ok';
        }

        try {
            (new \app\services\MoyskladWebhookService())->handle($payload);
        } catch (\Throwable $e) {
            file_put_contents(
                $logFile,
                "ERROR: {$e->getMessage()}\n",
                FILE_APPEND
            );
        }

        return 'ok';
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
