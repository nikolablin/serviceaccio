<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

class MediaUploadForm extends Model
{
    public $file = null;
    public string $title = '';
    private ?int $_category_id = null;
    public ?string $new_category = null;

    public function attributes(): array
    {
        return ['title', 'category_id', 'new_category'];
    }

    public function rules(): array
    {
        return [
            [['title'], 'required', 'message' => 'Укажите название (поле обязательно).'],
            [['file'], 'required', 'message' => 'Выберите файл для загрузки.'],

            [['title'], 'string', 'max' => 255],
            [['category_id'], 'integer'],

            [['new_category'], 'trim'],
            [['new_category'], 'string', 'max' => 150],

            [['file'], 'file',
                'maxSize' => 50 * 1024 * 1024,
                'tooBig' => 'Файл слишком большой (максимум 50 МБ).',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'title' => 'Название',
            'file' => 'Файл',
            'category_id' => 'Категория',
            'new_category' => 'Новая категория',
        ];
    }

    public function getCategory_id(): ?int
    {
       return $this->_category_id;
    }

    public function setCategory_id($value): void
    {
        $this->_category_id = ($value === '' || $value === null) ? null : (int)$value;
    }

    public static function makeStoredName(string $originalName): string
    {
        $ext  = pathinfo($originalName, PATHINFO_EXTENSION);
        $base = pathinfo($originalName, PATHINFO_FILENAME);

        $base = mb_strtolower($base);
        $base = preg_replace('~[^a-z0-9а-яё_-]+~ui', '-', $base);
        $base = trim($base, '-');

        $rand = bin2hex(random_bytes(4));
        return $base . '-' . $rand . ($ext ? '.' . mb_strtolower($ext) : '');
    }

    public function uploadAndCreate(): bool
    {
        if (!$this->validate()) return false;

        $storedName = self::makeStoredName($this->file->name);

        $dir = Yii::getAlias('@webroot/uploads/media');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->addError('file', 'Не удалось создать директорию для загрузки.');
            return false;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $storedName;
        if (!$this->file->saveAs($path)) {
            $this->addError('file', 'Не удалось сохранить файл.');
            return false;
        }

        $media = new MediaFile();
        $media->title = $this->title;
        $media->category_id = $this->category_id;
        $media->file_type = (string)$this->file->type;  // mime
        $media->original_name = $this->file->name;
        $media->stored_name = $storedName;

        if (!$media->save()) {
            foreach ($media->getErrors() as $attr => $errs) {
                $this->addError($attr, implode('; ', $errs));
            }
            return false;
        }

        return true;
    }

    public function setFile($value): void
    {
        $this->file = ($value instanceof UploadedFile) ? $value : null;
    }
}
