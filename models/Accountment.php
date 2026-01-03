<?php
namespace app\models;

use Yii;
use yii\base\Model;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Borders;
use app\models\Website;
use app\models\Moysklad;
use yii\db\Query;

class Accountment extends Model
{
  public function postAccountInformation($filePath,$bank,$organization)
  {
    try {
      $moysklad     = new Moysklad();
      $spreadsheet  = IOFactory::load($filePath);
      $sheet        = $spreadsheet->getActiveSheet();
      $rows         = $sheet->toArray(null, true, true, true);

      switch($bank){
        case 'kaspi':

          $data = [];
          $startReading = false;

          foreach ($rows as $index => $row) {
            if (!$startReading && ($row['A'] ?? '') === '#') {
              $startReading = true;
              continue;
            }

            if (!$startReading) {
              continue;
            }

            $data[] = [
                'order_id'            => $row['K'] ?? null,
                'order_date'          => $row['N'] ?? null,
                'net_amount'          => $row['V'] ?? null,
                'op_type'             => $row['O'],
                'op_comission'        => $row['W'] ?? null,
                'kaspi_pay_fee'       => $row['AC'] ?? null,
                'kaspi_delivery_fee'  => $row['AJ'] ?? null
                // 'order_id'            => $row['J'] ?? null,
                // 'order_date'          => $row['K'] ?? null,
                // 'net_amount'          => $row['U'] ?? null,
                // 'op_type'             => $row['N'],
                // 'op_comission'        => $row['V'] ?? null,
                // 'kaspi_pay_fee'       => $row['AB'] ?? null,
                // 'kaspi_delivery_fee'  => $row['AI'] ?? null

            ];
          }

          $grouppedData     = [];
          $responseOrderIds = [];

          // PaymentIn And group
          foreach ($data as $order) {
            if($order['order_id']){
              if($order['op_type'] == 'Покупка'){
                $moysklad->postPaymentInInformation($bank,$order['order_id'],$order['order_date']);

                $date = $order['order_date'];
                if (!isset($grouppedData[$date])) { $grouppedData[$date] = []; }
                $grouppedData[$date][]  = $order;
                $responseOrderIds[]     = mb_strtoupper($bank) . '::' . $order['order_id'];
              }
            }
          }

          // PaymentOut
          foreach ($grouppedData as $gdate => $group) {
            $opComissionSum       = 0;
            $kaspiPayFeeSum       = 0;
            $kaspiDeliveryFeeSum  = 0;
            $gdate = new \DateTime($gdate);
            $gdate->modify('+3 hours');

            foreach ($group as $gorder) {
              // Operation Fee
              if($gorder['op_comission'] AND !empty($gorder['op_comission']) AND $gorder['op_comission'] != 0){
                $opComission = str_replace([' ', ','], ['', ''], $gorder['op_comission']);
                $opComission = abs((float)$opComission);
                $opComissionSum += $opComission;
              }
              // Kaspi pay fee
              if($gorder['kaspi_pay_fee'] AND !empty($gorder['kaspi_pay_fee']) AND $gorder['kaspi_pay_fee'] != 0){
                $kaspiPayFee = str_replace([' ', ','], ['', ''], $gorder['kaspi_pay_fee']);
                $kaspiPayFee = abs((float)$kaspiPayFee);
                $kaspiPayFeeSum += $kaspiPayFee;
              }
              // Kaspi delivery fee
              if($gorder['kaspi_delivery_fee'] AND !empty($gorder['kaspi_delivery_fee']) AND $gorder['kaspi_delivery_fee'] != 0){
                $kaspiDeliveryFee = str_replace([' ', ','], ['', ''], $gorder['kaspi_delivery_fee']);
                $kaspiDeliveryFee = abs((float)$kaspiDeliveryFee);
                $kaspiDeliveryFeeSum += $kaspiDeliveryFee;
              }
            }

            if($opComissionSum > 0){
              $opComissionSum = $opComissionSum * 100;
              $moysklad->postPaymentOutInformation('op_comission',$bank,$organization,$opComissionSum,$gdate);
            }
            if($kaspiPayFeeSum > 0){
              $kaspiPayFeeSum = $kaspiPayFeeSum * 100;
              $moysklad->postPaymentOutInformation('kaspi_pay_fee',$bank,$organization,$kaspiPayFeeSum,$gdate);
            }
            if($kaspiDeliveryFeeSum > 0){
              $kaspiDeliveryFeeSum = $kaspiDeliveryFeeSum * 100;
              $moysklad->postPaymentOutInformation('kaspi_delivery_fee',$bank,$organization,$kaspiDeliveryFeeSum,$gdate);
            }
          }

          return $responseOrderIds;

          break;
      }

    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => 'Ошибка чтения файла: ' . $e->getMessage(),
        ];
    }
  }
}
