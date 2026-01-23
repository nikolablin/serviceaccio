<?php
namespace app\models;

use yii\db\ActiveRecord;

class V2InvoicesOut extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%v2_invoicesout}}';
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (empty($this->created_at)) {
                $this->created_at = date('Y-m-d H:i:s');
            }
        }
        $this->updated_at = date('Y-m-d H:i:s');

        return parent::beforeSave($insert);
    }
}
