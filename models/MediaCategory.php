<?php

namespace app\models;

use yii\db\ActiveRecord;

class MediaCategory extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%media_category}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'slug'], 'required'],
            [['name'], 'string', 'max' => 150],
            [['slug'], 'string', 'max' => 180],
            [['slug'], 'unique'],
            [['created_at'], 'safe'],
        ];
    }

    public function getFiles()
    {
        return $this->hasMany(MediaFile::class, ['category_id' => 'id']);
    }

    public function beforeValidate()
    {
        if (parent::beforeValidate()) {
            if (empty($this->slug) && !empty($this->name)) {
                $slug = mb_strtolower($this->name);
                $slug = preg_replace('~[^a-z0-9а-яё_-]+~ui', '-', $slug);
                $slug = trim($slug, '-');
                $this->slug = $slug;
            }
            return true;
        }
        return false;
    }
}
