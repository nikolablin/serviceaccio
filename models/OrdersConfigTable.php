<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use app\models\CpcProjectsDataTable;

class OrdersConfigTable extends ActiveRecord
{
  public static function tableName()
  {
      return '{{%orders_configs}}';
  }

  public function rules()
  {
      return [
          [['fiscal','project', 'payment_type', 'fiscal', 'status', 'organization', 'legal_account', 'channel', 'project_field', 'payment_status', 'delivery_service','cash_register','action_type'], 'required'],
          [['action_type'], 'integer'],
          [['project', 'payment_type', 'status', 'organization', 'legal_account', 'channel', 'project_field', 'payment_status', 'delivery_service'], 'string', 'max' => 255],
          [['project', 'action_type'], 'unique', 'targetAttribute' => ['project', 'action_type'],
              'message' => 'Конфиг для этого project + action_type уже существует.'
          ],
      ];
  }

  public function actionSaveConfig()
  {
      $post = Yii::$app->request->post();

      // ключевое поле — project
      $project = trim($post['project'] ?? '');

      if ($project === '') {
          throw new \yii\web\BadRequestHttpException('Не передан project');
      }

      // Ищем запись по project
      $model = CpcProjectsTable::findOne(['project' => $project]);

      if ($model === null) {
          $model = new CpcProjectsTable();
          $model->project = $project;
      }

      // Мапим поля из формы в поля таблицы
      $model->payment_type     = $post['payment-type']        ?? '';
      $model->fiscal           = $post['fiskal']              ?? '';
      $model->status           = $post['status']              ?? '';
      $model->organization     = $post['organization']        ?? '';
      $model->legal_account    = $post['legalaccountnumber']  ?? '';
      $model->channel          = $post['channel']             ?? '';
      // если в форме есть отдельное поле для project_field — сюда:
      $model->project_field    = $post['project_field']       ?? '';
      $model->payment_status   = $post['payment-status']      ?? '';
      $model->delivery_service = $post['delivery-service']    ?? '';
      $model->delivery_service = $post['delivery-service']    ?? '';
      $model->cash_register    = $post['cash-register']       ?? '';

      if (!$model->save()) {
          // на всякий случай — выведем ошибки, чтобы было что отладить
          Yii::error($model->errors, __METHOD__);
          throw new \yii\web\ServerErrorHttpException('Не удалось сохранить конфиг');
      }

      return $this->asJson(['success' => true]);
  }


}
