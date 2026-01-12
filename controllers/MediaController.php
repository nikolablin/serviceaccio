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
            throw new \yii\web\NotFoundHttpException('Файл не найден.');
        }

        $path = Yii::getAlias('@webroot/uploads/media/' . $model->stored_name);

        $deletedFile = false;

        if (is_file($path)) {
            // Иногда на хостинге unlink() может "молча" не сработать — ловим причину
            $deletedFile = @unlink($path);

            if (!$deletedFile) {
                $err = error_get_last();
                Yii::error([
                    'msg' => 'Failed to delete media file',
                    'id' => $model->id,
                    'stored_name' => $model->stored_name,
                    'path' => $path,
                    'error' => $err,
                    'fileperms' => substr(sprintf('%o', @fileperms($path)), -4),
                    'owner' => function_exists('fileowner') ? @fileowner($path) : null,
                    'user' => function_exists('get_current_user') ? @get_current_user() : null,
                ], 'media');
            }
        } else {
            // файла физически нет — тоже логируем
            Yii::warning([
                'msg' => 'Media file missing on disk',
                'id' => $model->id,
                'stored_name' => $model->stored_name,
                'path' => $path,
            ], 'media');
        }

        // запись удаляем всегда (на твой выбор)
        $model->delete();

        Yii::$app->session->setFlash(
            'success',
            $deletedFile ? 'Файл удалён.' : 'Запись удалена, но файл не удалился (см. лог).'
        );

        return $this->redirect(Yii::$app->request->referrer ?: ['/site/mediamanager']);
    }
}
