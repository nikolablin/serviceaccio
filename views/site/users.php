<?php
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
$this->title = 'Пользователи';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-reports">
  <h1><?= Html::encode($this->title) ?></h1>
  <div class="functions">
    <?php
    echo Html::a('Добавить пользователя', ['site/signup'], ['class' => 'btn btn-secondary']);
    ?>
  </div>
  <table class="mt-5 table users-table">
    <thead>
      <tr>
        <th scope="col">#</th>
        <th scope="col">Имя</th>
        <th scope="col">Логин</th>
        <th scope="col">Группа</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $u = 1;
      foreach ($users as $user) {
        ?>
        <tr>
          <td><?=$u;?></td>
          <td><?=$user->name;?></td>
          <td><?=$user->username;?></td>
          <td>
            <?php
            foreach ($user->userGroups as $group) {
              echo $group->group->title;
            }
            ?>
          </td>
        </tr>
        <?php
        $u++;
      }
      ?>
    </tbody>
  </table>
</div>
