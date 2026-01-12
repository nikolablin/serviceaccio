<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use app\models\MediaFile;
use yii\filters\VerbFilter;

class MediaController extends Controller
{
     public function behaviors()
     {
         return [
             'verbs' => [
                 'class' => VerbFilter::class,
                 'actions' => [
                     'delete' => ['POST'],
                 ],
             ],
         ];
     }

   /**
    * Отдать файл по ID
    * URL: /media/file/<id>
    */
    public function actionFile(int $id)
    {
        $model = MediaFile::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        $path = Yii::getAlias('@webroot/uploads/media/' . $model->stored_name);
        if (!is_file($path)) {
            throw new NotFoundHttpException('Файл отсутствует на сервере.');
        }

        // mime берём из БД или определяем автоматически
        $mime = $model->file_type ?: mime_content_type($path);

        return Yii::$app->response->sendFile(
            $path,
            $model->original_name,
            [
                'mimeType' => $mime,
                'inline' => false, // true = открыть в браузере, false = скачать
            ]
        );
    }

    public function actionDelete(int $id)
    {
        $model = MediaFile::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        $path = Yii::getAlias('@webroot/uploads/media/' . $model->stored_name);

        // удаляем файл с диска
        if (is_file($path)) {
            @unlink($path);
        }

        // удаляем запись
        $model->delete();

        Yii::$app->session->setFlash('success', 'Файл удалён.');
        return $this->redirect(Yii::$app->request->referrer ?: ['/site/mediamanager']);
    }
}
