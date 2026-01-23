<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\Moysklad;
use app\models\Reports;
use app\models\Accountment;
use app\models\User;
use app\models\UserGroups;
use app\models\UGroups;
use app\models\SignupForm;
use app\models\MarketingReportTable;
use app\models\CpcProjectsTable;
use app\models\OrdersConfig;
use app\models\MediaUploadForm;
use app\models\MediaCategory;
use app\models\MediaFile;
use yii\helpers\ArrayHelper;
use app\assets\AccountmentAsset;
use app\assets\OrdersConfigAsset;
use app\assets\ReportsAsset;
use yii\web\UploadedFile;
use yii\data\ActiveDataProvider;

class SiteController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    // Гостям
                    [
                        'actions' => ['login', 'signup', 'error', 'captcha'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    // Только авторизованным
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionReports()
    {
      $this->getView()->registerAssetBundle(\app\assets\ReportsAsset::class);

      $model = new Reports();
      $msmodel = new Moysklad();
      $cpcmodel = new CpcProjectsTable();

      $msCategories = $msmodel->getCustomCategories();
      $msRealizeContragents = $msmodel->getContragents(true);
      $msRealizeContragents = json_decode($msRealizeContragents);
      $msRealizeContragents = $msRealizeContragents->rows;

      return $this->render('reports', [ 'mscats' => $msCategories, 'realizeContragents' => $msRealizeContragents, 'cpcmodel' => $cpcmodel ]);
    }

    public function actionAccountment()
    {
      $this->getView()->registerAssetBundle(\app\assets\AccountmentAsset::class);

      $model = new Accountment();
      return $this->render('accountment', [ 'model' => $model ]);
    }

    public function actionUsers()
    {
      $model = new User();
      $users = $model->find()
          ->alias('u')
          ->joinWith([
              'userGroups ug' => function ($query) {
                  $query->joinWith('group g');
              }
          ])
          ->all();

      return $this->render('users', [ 'model' => $model, 'users' => $users ]);
    }

    public function actionSignup()
    {

      $model = new SignupForm();
      $groups = UGroups::find()->asArray()->all();
      $groups = ArrayHelper::map($groups, 'id', 'title');

      if ($model->load(Yii::$app->request->post()) && $model->register()) {

        $userId = Yii::$app->db->getLastInsertID();

        $userGroup = new UserGroups();
        $userGroup->user_id = $userId;
        $userGroup->group_id = Yii::$app->request->post()['group_id'];  // ID группы, которую нужно назначить пользователю

        // Сохраняем запись в таблицу
        if ($userGroup->save()) {
            Yii::$app->session->setFlash('success', 'Теперь вы с нами! Здравствуйте!');
        } else {
            Yii::$app->session->setFlash('error', 'Что-то пошло не так. Напишите нам.');
        }

        Yii::$app->session->setFlash('success', 'Теперь вы с нами! Здравствуйте!');
        return $this->redirect(['site/login']);
      }

      return $this->render('signup', ['model' => $model, 'groups' => $groups]);

    }

    public function actionChangePassword()
    {
        $user = Yii::$app->user->identity;

        $model = new \app\models\ChangePasswordForm($user);

        if ($model->load(Yii::$app->request->post()) && $model->changePassword()) {
            Yii::$app->session->setFlash('success', 'Пароль успешно изменен.');
            return $this->goHome();
        }

        return $this->render('change-password', [
            'model' => $model,
        ]);
    }

    public function actionOrdersconfig()
    {
      $this->getView()->registerAssetBundle(\app\assets\OrdersConfigAsset::class);

      $moysklad = new Moysklad();

      $actualProjects = $moysklad->getActualProjects();

      $references = (object)[];
      $references->paymentType      = $moysklad->getReference('d8662995-836c-11ed-0a80-04de0034157c');
      $references->paymentStatuses  = $moysklad->getReference('1bbc6b51-c29d-11eb-0a80-01370004133f');
      $references->deliveryServices = $moysklad->getReference('d220a555-345d-11eb-0a80-022e0002f1c7');
      $references->statuses         = $moysklad->getStates('customerorder');
      $references->organizations    = $moysklad->getOrganizations();
      $references->channels         = $moysklad->getReference('9c69b3d5-68d5-11ee-0a80-044c0009477e');
      $references->projects         = $moysklad->getProjects($actualProjects);
 
      return $this->render('ordersconfig',[ 'references' => $references ]);
    }

    public function actionMediamanager()
    {
      $this->getView()->registerAssetBundle(\app\assets\MediaAsset::class);

      $model = new \app\models\MediaUploadForm();

      $categories = MediaCategory::find()
          ->select(['name', 'id'])
          ->orderBy(['name' => SORT_ASC])
          ->indexBy('id')
          ->column();

      if (Yii::$app->request->isPost) {
          if ($model->load(Yii::$app->request->post())) {

              $model->file = UploadedFile::getInstance($model, 'file');

              // ✅ Создание категории "на лету"
              if (!empty($model->new_category)) {
                  $catName = trim($model->new_category);

                  // чтобы не плодить дубликаты
                  $cat = MediaCategory::find()->where(['name' => $catName])->one();

                  if (!$cat) {
                      $cat = new MediaCategory();
                      $cat->name = $catName;

                      if (!$cat->save()) {
                          $model->addError('new_category', implode('; ', $cat->getFirstErrors()));
                      }
                  }

                  if ($cat && !$cat->hasErrors()) {
                      $model->category_id = (int)$cat->id; // выбираем созданную
                  }
              }

              // ✅ если не было ошибок по категории — грузим файл и пишем в media_file
              if (!$model->hasErrors() && $model->uploadAndCreate()) {
                  Yii::$app->session->setFlash('success', 'Файл добавлен.');
                  return $this->refresh();
              }

              // обновим список категорий, чтобы новая появилась в dropdown после ошибки/успеха
              $categories = MediaCategory::find()
                  ->select(['name', 'id'])
                  ->orderBy(['name' => SORT_ASC])
                  ->indexBy('id')
                  ->column();
          }
      }

      $dataProvider = new ActiveDataProvider([
          'query' => MediaFile::find()->with('category'), // <-- БЕЗ orderBy()
          'pagination' => ['pageSize' => 25],
          'sort' => [
              'defaultOrder' => ['id' => SORT_DESC], // сортировка по умолчанию
              'attributes' => [
                  'id',
                  'title',
                  'file_type',
                  'created_at',
              ],
          ],
      ]);

      // dataProvider для списка файлов оставь как у тебя
      return $this->render('mediamanager', [
          'model' => $model,
          'categories' => $categories,
          'dataProvider' => $dataProvider, // если у тебя он есть
      ]);
    }

    public function actionTest()
    {
      return $this->render('test');
    }
}
