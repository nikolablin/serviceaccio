<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class ReportMarketingTable extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%report_marketing}}';
    }

    public function getMarketingData($date)
    {
      $month  = $date->format('m');
      $year   = $date->format('Y');

      $data = self::findOne(['year' => $year, 'month' => $month]);

      return $data;
    }

    public function getMarketingYearData($year)
    {
      $data = self::findAll(['year' => $year]);

      return $data;
    }

    public function getMarketingDataArray()
    {
      $data_columns = [
        'data_1_1' => 'Все каналы: К-ф',
        'data_1_2' => 'Все каналы: Курс $',
        'data_1_3' => 'Все каналы: Рентабельность, %',

        // Organic
        'data_2_10' => 'Organic:  Показы',
        'data_2_11' => 'Organic:  Клики / Переходы',
        'data_2_12' => 'Organic:  Средняя позиция сайта',
        'data_2_1' => 'Organic: Стоимость сопровождения, Тенге',
        'data_2_2' => 'Organic: Стоимость работ, Тенге',
        'data_2_3' => 'Organic: (Всего конверсий в шт) Добавление в корзину, шт',
        'data_2_4' => 'Organic: (Всего конверсий в шт) WhatsApp / переходы, шт',
        'data_2_5' => 'Organic: (Всего конверсий в шт) Binotel / звонки, шт',
        'data_2_6' => 'Organic: (Покупки в шт) Purchase / покупки на сайте, шт',
        'data_2_7' => 'Organic: (Покупки в шт) Binotel / покупки, шт',
        'data_2_8' => 'Organic: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
        'data_2_9' => 'Organic: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',

        // CPC
        // 'data_3_9' => 'CPC:  Показы',
        // 'data_3_10' => 'CPC:  Клики / Переходы',
        // 'data_3_11' => 'CPC:  Стоимость рекламы, $',
        'data_3_1' => 'CPC: Стоимость сопровождения, Тенге',
        // 'data_3_2' => 'CPC: (Всего конверсий в шт) Добавление в корзину, шт',
        // 'data_3_3' => 'CPC: (Всего конверсий в шт) WhatsApp / переходы, шт',
        // 'data_3_4' => 'CPC: (Всего конверсий в шт) Binotel / звонки, шт',
        // 'data_3_5' => 'CPC: (Покупки в шт) Purchase / покупки на сайте, шт',
        // 'data_3_6' => 'CPC: (Покупки в шт) Binotel / покупки, шт',
        // 'data_3_7' => 'CPC: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
        // 'data_3_8' => 'CPC: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',

        // Instagram / Facebook
        'data_4_1' => 'Instagram / Facebook: Показы',
        'data_4_2' => 'Instagram / Facebook: Клики / Переходы',
        'data_4_3' => 'Instagram / Facebook: Стоимость сопровождения',
        'data_4_4' => 'Instagram / Facebook: Стоимость рекламы',
        'data_4_5' => 'Instagram / Facebook: (Всего конверсий в шт) Добавление в корзину, шт',
        'data_4_6' => 'Instagram / Facebook: (Всего конверсий в шт) WhatsApp / переходы, шт',
        'data_4_7' => 'Instagram / Facebook: (Всего конверсий в шт) Binotel / звонки, шт',
        'data_4_8' => 'Instagram / Facebook: (Покупки в шт) Purchase / покупки на сайте, шт',
        'data_4_9' => 'Instagram / Facebook: (Покупки в шт) Binotel / покупки, шт',
        'data_4_10' => 'Instagram / Facebook: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
        'data_4_11' => 'Instagram / Facebook: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',

        // Email
        'data_5_1' => 'Email: Отправлено писем, шт',
        'data_5_2' => 'Email: Доставлено писем, шт',
        'data_5_3' => 'Email: Открыто писем, шт',
        'data_5_4' => 'Email: Клики / Переходы, шт',
        'data_5_5' => 'Email: Стоимость рассылки, Тенге',
        'data_5_6' => 'Email: Стомость работ, $',
        'data_5_7' => 'Email: (Всего конверсий в шт) Добавление в корзину, шт',
        'data_5_8' => 'Email: (Всего конверсий в шт) WhatsApp / переходы, шт',
        'data_5_9' => 'Email: (Всего конверсий в шт) Binotel / звонки, шт',
        'data_5_10' => 'Email: (Покупки в шт) Purchase / покупки на сайте, шт',
        'data_5_11' => 'Email: (Покупки в шт) Binotel / покупки, шт',
        'data_5_12' => 'Email: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
        'data_5_13' => 'Email: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',

        // Рассылка Bot
        'data_6_1' => 'Рассылка Bot: Отправлено',
        'data_6_2' => 'Рассылка Bot: Отклонено',
        'data_6_3' => 'Рассылка Bot: Доставлено',
        'data_6_4' => 'Рассылка Bot: Прочитано',
        'data_6_5' => 'Рассылка Bot: Стоимость рассылки',
        'data_6_6' => 'Рассылка Bot: Стомость работ, $',
        'data_6_7' => 'Рассылка Bot: (Всего конверсий в шт) Добавление в корзину, шт',
        'data_6_8' => 'Рассылка Bot: (Всего конверсий в шт) WhatsApp / переходы, шт',
        'data_6_9' => 'Рассылка Bot: (Всего конверсий в шт) Binotel / звонки, шт',
        'data_6_10' => 'Рассылка Bot: (Покупки в шт) Purchase / покупки на сайте, шт',
        'data_6_11' => 'Рассылка Bot: (Покупки в шт) Binotel / покупки, шт',
        'data_6_12' => 'Рассылка Bot: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
        'data_6_13' => 'Рассылка Bot: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',

        // Direct
        'data_7_1' => 'Direct: Сеансы',
        'data_7_2' => 'Direct: Сеансы с взаимодействием',
        'data_7_3' => 'Direct: (Всего конверсий в шт) Добавление в корзину, шт',
        'data_7_4' => 'Direct: (Всего конверсий в шт) WhatsApp / запросы, шт',
        'data_7_5' => 'Direct: (Всего конверсий в шт) Binotel / звонки, шт',
        'data_7_6' => 'Direct: (Покупки в шт) Purchase / покупки на сайте, шт',
        'data_7_7' => 'Direct: (Покупки в шт) Binotel / покупки, шт',
        'data_7_8' => 'Direct: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
        'data_7_9' => 'Direct: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',

        // Сумма продаж WhatsApp
        'data_8_4' => 'WhatsApp: Сумма продаж в Тенге',
        'data_8_1' => 'WhatsApp: Всего обращений WhatsApp, шт',
        'data_8_2' => 'WhatsApp: Всего конверсий чаты, шт',
        'data_8_3' => 'WhatsApp: Всего продаж WhatsApp, шт'
      ];

      return $data_columns;
    }

    public function separateFillingPeriodsForFields()
    {
      $data_columns = [
        'data_1_1' => 'monthly',
        'data_1_2' => 'monthly',
        'data_1_3' => 'weekly',

        // Organic
        'data_2_1' => 'monthly',
        'data_2_2' => 'monthly',
        'data_2_3' => 'weekly',
        'data_2_4' => 'weekly',
        'data_2_5' => 'weekly',
        'data_2_6' => 'weekly',
        'data_2_7' => 'weekly',
        'data_2_8' => 'weekly',
        'data_2_9' => 'weekly',
        'data_2_10' => 'weekly',
        'data_2_11' => 'weekly',
        'data_2_12' => 'weekly',

        // CPC
        'data_3_1' => 'monthly',
        'data_3_2' => 'weekly',
        'data_3_3' => 'weekly',
        'data_3_4' => 'weekly',
        'data_3_5' => 'weekly',
        'data_3_6' => 'weekly',
        'data_3_7' => 'weekly',
        'data_3_8' => 'weekly',
        'data_3_9' => 'weekly',
        'data_3_10' => 'weekly',
        'data_3_11' => 'weekly',

        // Instagram / Facebook
        'data_4_1' => 'weekly',
        'data_4_2' => 'weekly',
        'data_4_3' => 'monthly',
        'data_4_4' => 'weekly',
        'data_4_5' => 'weekly',
        'data_4_6' => 'weekly',
        'data_4_7' => 'weekly',
        'data_4_8' => 'weekly',
        'data_4_9' => 'weekly',
        'data_4_10' => 'weekly',
        'data_4_11' => 'weekly',

        // Email
        'data_5_1' => 'weekly',
        'data_5_2' => 'weekly',
        'data_5_3' => 'weekly',
        'data_5_4' => 'weekly',
        'data_5_5' => 'weekly',
        'data_5_6' => 'weekly',
        'data_5_7' => 'weekly',
        'data_5_8' => 'weekly',
        'data_5_9' => 'weekly',
        'data_5_10' => 'weekly',
        'data_5_11' => 'weekly',
        'data_5_12' => 'weekly',
        'data_5_13' => 'weekly',

        // Рассылка Bot
        'data_6_1' => 'weekly',
        'data_6_2' => 'weekly',
        'data_6_3' => 'weekly',
        'data_6_4' => 'weekly',
        'data_6_5' => 'weekly',
        'data_6_6' => 'monthly',
        'data_6_7' => 'weekly',
        'data_6_8' => 'weekly',
        'data_6_9' => 'weekly',
        'data_6_10' => 'weekly',
        'data_6_11' => 'weekly',
        'data_6_12' => 'weekly',
        'data_6_13' => 'weekly',

        // Direct
        'data_7_1' => 'weekly',
        'data_7_2' => 'weekly',
        'data_7_3' => 'weekly',
        'data_7_4' => 'weekly',
        'data_7_5' => 'weekly',
        'data_7_6' => 'weekly',
        'data_7_7' => 'weekly',
        'data_7_8' => 'weekly',
        'data_7_9' => 'weekly',

        // Сумма продаж WhatsApp
        'data_8_1' => 'weekly',
        'data_8_2' => 'weekly',
        'data_8_3' => 'weekly',
        'data_8_4' => 'weekly'
      ];

      return $data_columns;
    }

    public function getWeeksFromFirstWednesday($date)
    {
        $year = (int)$date->format('Y');
        $month = (int)$date->format('m');

        // Найти первую среду месяца
        $firstDay = new \DateTime("$year-$month-01");
        while ($firstDay->format('N') != 3) { // 3 — это среда
            $firstDay->modify('+1 day');
        }

        $weeks = [];
        $startOfWeek = clone $firstDay;

        while ((int)$startOfWeek->format('m') === $month) {
            $endOfWeek = clone $startOfWeek;
            $endOfWeek->modify('+6 days');

            $weeks[] = [
                'start' => $startOfWeek->format('Y-m-d'),
                'end' => $endOfWeek->format('Y-m-d'),
                'month' => $month
            ];

            $startOfWeek->modify('+7 days');
        }

        return $weeks;
    }

    public function validateEmptyArray($data)
    {
        $errors = [];

        foreach ($data as $key => $value) {
            // Игнорируем определённые ключи
            if (in_array($key, ['id', 'month', 'year', 'create_date', 'update_date','data_3_9','data_3_10','data_3_11','data_3_2','data_3_3','data_3_4','data_3_5','data_3_6','data_3_7','data_3_8'])) {
                continue;
            }

            // Пропускаем, если значение - это 0 или строка '0' (разрешаем нулевые значения)
            if ($value === 0 || $value === '0') {
                continue;
            }

            // Проверяем пустые значения
            if (empty($value) && $value !== '0' && $value !== 0) {
                $errors[] = $key;
                continue;
            }

            // Проверяем строки с разделителями `:::`
            if (is_string($value) && strpos($value, ':::') !== false) {
                $parts = explode(':::', $value);

                // Проверяем, чтобы не было пустых значений между `:::`
                foreach ($parts as $index => $part) {
                    if ($part === '') {
                        $errors[] = $key;
                        break; // Достаточно одного пустого значения, чтобы зафиксировать ошибку
                    }
                }
            }
        }

        return (object) [
            'emptyExist' => !empty($errors),
            'empty' => $errors
        ];
    }

    public function removeMonthYearData($date)
    {
      $data = $this->getMarketingData($date);

      if ($data) {
        $data->delete();

        return true;
      }

      return false;
    }
}
