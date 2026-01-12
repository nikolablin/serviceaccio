<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\helpers\Url;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

class MediaFile extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%media_file}}';
    }

    public function rules(): array
    {
        return [
            [['file_type', 'title', 'original_name', 'stored_name'], 'required'],
            [['category_id'], 'integer'],
            [['created_at'], 'safe'],

            [['file_type'], 'string', 'max' => 40],
            [['title', 'original_name', 'stored_name'], 'string', 'max' => 255],

            [['stored_name'], 'unique'],
            [['category_id'], 'exist', 'skipOnError' => true,
                'targetClass' => MediaCategory::class,
                'targetAttribute' => ['category_id' => 'id'],
            ],
        ];
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false, // если нет updated_at
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function getCategory()
    {
        return $this->hasOne(MediaCategory::class, ['id' => 'category_id']);
    }

    public function beforeValidate()
    {
        if (parent::beforeValidate()) {
            if (empty($this->stored_name)) {
                $this->stored_name = self::makeStoredName($this->original_name);
            }
            return true;
        }
        return false;
    }

    public static function makeStoredName(string $originalName): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $base = pathinfo($originalName, PATHINFO_FILENAME);

        // простая нормализация (можно заменить на slugify)
        $base = mb_strtolower($base);
        $base = preg_replace('~[^a-z0-9а-яё_-]+~ui', '-', $base);
        $base = trim($base, '-');

        $rand = bin2hex(random_bytes(4)); // 8 символов
        return $base . '-' . $rand . ($ext ? '.' . mb_strtolower($ext) : '');
    }

    public function getUrl(bool $absolute = false): string
    {
        return Url::to(['/media/file', 'id' => $this->id], $absolute);
    }
}
