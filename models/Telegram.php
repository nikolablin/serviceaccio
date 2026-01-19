<?php
namespace app\models;

use Yii;
use yii\base\Model;

class Telegram extends Model {
  private static function getTelegramData()
  {
    $obj                = (object)array();
    $obj->token         = '5460987366:AAFpykLwfONqQ9k7IsiDNrEq8fvwfW4s-nc';
    $obj->ordersChat    = '-1001756938307';
    $obj->wholesaleChat = '-1001602976110';
    $obj->kaspiChat     = '-4069411652';
    $obj->cancelledChat = '-1002208197900';

    return $obj;
  }

  public function sendTelegramMessage($msg,$type)
  {
    $telegramData = self::getTelegramData();

    switch ($type) {
      case 'kaspi':
        $chatId = $telegramData->kaspiChat;
        break;
      case 'cancelled':
        $chatId = $telegramData->cancelledChat;
        break;
      default:
        $chatId = $telegramData->ordersChat;
        break;
    }

    file_put_contents(__DIR__ . '/../logs/telegram/sentMessage.txt', date('d.m.Y') . PHP_EOL . 'https://api.telegram.org/bot' . $telegramData->token . '/sendMessage?disable_web_page_preview=true&parse_mode=html&chat_id=' . $chatId . '&text='.urlencode($msg) . PHP_EOL . PHP_EOL,FILE_APPEND);

    $url = 'https://api.telegram.org/bot' . $telegramData->token . '/sendMessage?disable_web_page_preview=true&parse_mode=html&chat_id=' . $chatId . '&text='.urlencode($msg);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $page   = curl_exec($ch);
    curl_close($ch);
  }

  public function sendTelegramMessageToDefiniteChat($msg,$chatId)
  {
    $telegramData = self::getTelegramData();

    $url = 'https://api.telegram.org/bot' . $telegramData->token . '/sendMessage?disable_web_page_preview=true&parse_mode=html&chat_id=' . $chatId . '&text='.urlencode($msg);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $page   = curl_exec($ch);
    curl_close($ch);
  }
}
