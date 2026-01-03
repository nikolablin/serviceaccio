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
use app\models\ReportMarketingTable;
use yii\db\Query;

class Reports extends Model
{
  private function getPeriodWeeks($periodFrom,$periodTo,$weekPeriodFrom,$weekPeriodTo)
  {
    $weekPeriodFrom   = new \DateTime($weekPeriodFrom);
    $startPeriod      = clone $weekPeriodFrom;
    $weekPeriodFrom   = $weekPeriodFrom->modify('+3 hours');
    $weekPeriodTo     = new \DateTime($weekPeriodTo);
    $weekPeriodTo     = $weekPeriodTo->modify('+3 hours');
    $countPeriodDays  = $weekPeriodFrom->diff($weekPeriodTo);
    $countPeriodDays  = $countPeriodDays->days;
    $countPeriodWeeks = (int)ceil($countPeriodDays / 7);

    $weeks = [];
    for($i = 1; $i <= $countPeriodWeeks; $i++){
      $obj        = (object)array();
      $obj->periodFrom  = $startPeriod->format('Y-m-d%20H:i:s');
      $obj->periodTo    = $startPeriod->modify('+7 days')->format('Y-m-d%2020:59:59');
      $obj->weekFrom    = $weekPeriodFrom->format('Y-m-d');
      $weekPeriodFrom->modify('+6 days');
      $obj->weekTo      = $weekPeriodFrom->format('Y-m-d');
      $weekPeriodFrom->modify('+1 days');

      $weeks[] = $obj;
    }
    return $weeks;
  }

  private function sortProfitsByWeekPeriod($profitsWeeks)
  {
    $response = [];

    foreach ($profitsWeeks as $prWeek) {
      $wobject            = (object)array();
      $wobject->weekFrom  = $prWeek->weekFrom;
      $wobject->weekTo    = $prWeek->weekTo;

      $wobject->brandsList = [];
      foreach ($prWeek->profitsList as $el) {
        if(!isset($wobject->brandsList[$el->productData->brand])){
          $wobject->brandsList[$el->productData->brand] = (object)array();
          $wobject->brandsList[$el->productData->brand]->quantity = 0;
          $wobject->brandsList[$el->productData->brand]->totalSum = 0;
        }
        $wobject->brandsList[$el->productData->brand]->quantity += ($el->sellQuantity - $el->returnQuantity);
        $wobject->brandsList[$el->productData->brand]->totalSum += round($el->sellSum - $el->returnSum,0);
      }

      $response[] = $wobject;
    }

    return $response;
  }

  private function sortProfitsByWeekPeriodCategories($profitsWeeks)
  {
    $response = [];

    foreach ($profitsWeeks as $prWeek) {
      $wobject            = (object)array();
      $wobject->weekFrom  = $prWeek->weekFrom;
      $wobject->weekTo    = $prWeek->weekTo;

      $wobject->list = [];

      foreach ($prWeek->profitsList as $el) {
        $productCategoryPath    = $el->productData->category_name;
        $productCategoryPathArr = explode('/',$productCategoryPath);
        $productQuantity        = $el->sellQuantity - $el->returnQuantity;
        $productSellSum         = round($el->sellSum - $el->returnSum,0);
        $productBrand           = $el->productData->brand;

        if(!isset($wobject->list[$productCategoryPath])){
          $wobject->list[$productCategoryPath] = [];
        }

        if(!isset($wobject->list[$productCategoryPath][$productBrand])){
          $wobject->list[$productCategoryPath][$productBrand] = (object)array();
          $wobject->list[$productCategoryPath][$productBrand]->quantity = 0;
          $wobject->list[$productCategoryPath][$productBrand]->totalSum = 0;
        }

        $wobject->list[$productCategoryPath][$productBrand]->quantity += ($el->sellQuantity - $el->returnQuantity);
        $wobject->list[$productCategoryPath][$productBrand]->totalSum += round($el->sellSum - $el->returnSum,0);
      }

      $response[] = $wobject;
    }

    return $response;
  }

  private function getCatTotalQuantitySum($weekdata,$cats)
  {
    $response = (object)array();
    $response->totalQuantity  = 0;
    $response->totalSum       = 0;
    foreach ($weekdata as $dkey => $data) {
      $dPathArr = explode('/',$dkey);

      if(isset($dPathArr[0]) AND !empty($dPathArr[0])){
        if($dPathArr[0] == $cats[0]){
          switch(mb_strtolower($cats[0])){
            case 'кофе':
              switch(mb_strtolower($cats[1])){
                case 'зерновой кофе':
                case 'молотый кофе':
                case 'чалды':
                  if(isset($cats[2])){
                    if($dPathArr[1] == $cats[1] AND $dPathArr[2] == $cats[2]){
                      foreach ($data as $d) {
                        $response->totalQuantity += $d->quantity;
                        $response->totalSum += $d->totalSum;
                      }
                    }
                  }
                  else {
                    if($dPathArr[1] == $cats[1]){
                      foreach ($data as $d) {
                        $response->totalQuantity += $d->quantity;
                        $response->totalSum += $d->totalSum;
                      }
                    }
                  }
                  break;
                case 'капсульный кофе':
                  if(isset($cats[3])){
                    if($dPathArr[1] == $cats[1] AND $dPathArr[2] == $cats[2] AND $dPathArr[3] == $cats[3]){
                      foreach ($data as $d) {
                        $response->totalQuantity += $d->quantity;
                        $response->totalSum += $d->totalSum;
                      }
                    }
                  }
                  else {
                    if($dPathArr[1] == $cats[1] AND $dPathArr[2] == $cats[2]){
                      foreach ($data as $d) {
                        $response->totalQuantity += $d->quantity;
                        $response->totalSum += $d->totalSum;
                      }
                    }
                  }
                  break;
              }
              break;
            case 'шоколадные напитки':
              if(isset($cats[1]) AND isset($cats[2])){
                if($dPathArr[1] == $cats[1] AND $dPathArr[2] == $cats[2]){
                  foreach ($data as $d) {
                    $response->totalQuantity += $d->quantity;
                    $response->totalSum += $d->totalSum;
                  }
                }
              }
              else {
                foreach ($data as $d) {
                  $response->totalQuantity += $d->quantity;
                  $response->totalSum += $d->totalSum;
                }
              }
              break;
            case 'аксессуары':
            case 'чай':
              foreach ($data as $d) {
                $response->totalQuantity += $d->quantity;
                $response->totalSum += $d->totalSum;
              }
              break;
            case 'кофемашины':
              switch(mb_strtolower($cats[1])){
                case 'автоматические кофемашины':
                case 'рожковые кофемашины':
                  if($dPathArr[1] == $cats[1]){
                    foreach ($data as $d) {
                      $response->totalQuantity += $d->quantity;
                      $response->totalSum += $d->totalSum;
                    }
                  }
                  break;
                default:
                  if($dPathArr[1] == $cats[1] AND $dPathArr[2] == $cats[2]){
                    foreach ($data as $d) {
                      $response->totalQuantity += $d->quantity;
                      $response->totalSum += $d->totalSum;
                    }
                  }
              }
              break;
          }
        }
      }
    }

    return $response;
  }

  private function calculateProfitSalesForProductByArr($profits,$productCode,$type)
  {
    $total = 0;
    foreach ($profits as $profit) {
      if($profit->assortment->code == $productCode){
        switch($type){
          case 'sales':
            $total += round( ($profit->sellSum - $profit->returnSum) / 100, 1 );
            break;
          case 'profit':
            $total += round( $profit->profit / 100, 1 );
            break;
          case 'rentability':
            $total += round( $profit->margin, 2 );
            break;
          case 'quantity':
            $total += $profit->sellQuantity - $profit->returnQuantity;
            break;
        }
        break;
      }
    }

    return $total;
  }

  private function calculateHalfAYearRemains($remains,$productCode)
  {
    $obj = (object)array();
    $obj->start = 0;
    $obj->end = 0;
    foreach ($remains as $remain) {
      if($remain->assortment->code == $productCode){
        $obj->start = $remain->onPeriodStart->quantity;
        $obj->end = $remain->onPeriodEnd->quantity;
        break;
      }
    }

    return $obj;
  }

  private function getProductFromCatalogueJson($catalogue,$productHref)
  {
    $response = false;
    foreach ($catalogue as $catproduct) {
      if($catproduct->meta->href == $productHref){
        $response = $catproduct;
      }
    }

    return $response;
  }

  private function getReportProductRemains($remains,$pid)
  {
    // 023870f6-ee91-11ea-0a80-05f20007444d - Almaty
    // 805d5404-3797-11eb-0a80-01b1001ba27a - Astana
    // 1e1187c1-85e6-11ed-0a80-0dbe006f385b - Success
    // 55441d2d-f295-11ea-0a80-021200465d60 - В пути Алматы
    // 7c5174ab-5200-11eb-0a80-03f90021dcc0 - В пути Астана

    $response = (object)array();
    $response->almaty   = 0;
    $response->success  = 0;
    $response->astana   = 0;
    $response->wayAlmaty   = 0;
    $response->wayAstana   = 0;
    foreach (json_decode($remains) as $remain) {
      if($remain->assortmentId == $pid){
        if($remain->storeId == '023870f6-ee91-11ea-0a80-05f20007444d'){
          $response->almaty = $remain->stock;
        }
        if($remain->storeId == '805d5404-3797-11eb-0a80-01b1001ba27a'){
          $response->astana = $remain->stock;
        }
        if($remain->storeId == '1e1187c1-85e6-11ed-0a80-0dbe006f385b'){
          $response->success = $remain->stock;
        }
        if($remain->storeId == '55441d2d-f295-11ea-0a80-021200465d60'){
          $response->wayAlmaty = $remain->stock;
        }
        if($remain->storeId == '7c5174ab-5200-11eb-0a80-03f90021dcc0'){
          $response->wayAstana = $remain->stock;
        }

      }
    }
    return $response;
  }

  private function collectAllComissionerSalesData($allComData,$moyskladModel)
  {
    $agentsList = [];
    foreach ($allComData as $comrep) {
      $comrep->positionsList = false;
      $comrep->positionsList = $moyskladModel->getHrefData($comrep->positions->meta->href)->rows;

      $agentData = (object)array();
      $agentData->reportCreated = new \DateTime($comrep->created);
      $agentData->agentId    = explode('/',$comrep->agent->meta->href);
      $agentData->agentId    = end($agentData->agentId);
      $agentData->positions = $comrep->positionsList;
      $agentsList[] = $agentData;
    }

    $grouppedAgentsReports = [];
    $grouppedAgentsReportsExist = [];
    $responseData = array();
    foreach ($agentsList as $ra) {
      if(!in_array($ra->agentId,$grouppedAgentsReportsExist)){
        $grouppedAgentsReports[$ra->agentId] = array();
        $grouppedAgentsReports[$ra->agentId][] = $ra;
        $grouppedAgentsReportsExist[] = $ra->agentId;
      }
      else {
        $grouppedAgentsReports[$ra->agentId][] = $ra;
      }
    }

    foreach ($grouppedAgentsReports as $garkey => $gar) {
      $responseData[$garkey] = array();

      foreach ($gar as $greport) {
        foreach ($greport->positions as $greportposition) {
          $assortmentId = explode('/',$greportposition->assortment->meta->href);
          $assortmentId = end($assortmentId);

          if(!isset($responseData[$garkey][$assortmentId])){
            $responseData[$garkey][$assortmentId] = (object)array();
            $responseData[$garkey][$assortmentId]->quantity = 0;
            $responseData[$garkey][$assortmentId]->price = 0;
          }

          $responseData[$garkey][$assortmentId]->quantity += $greportposition->quantity;
          $responseData[$garkey][$assortmentId]->price += ($greportposition->price / 100) * $greportposition->quantity;
        }
      }
    }

    return $responseData;
  }

  private function groupDemandsByAgentsAndPositions($demands,$months)
  {
    $demandsAgentsList = [];
    foreach ($demands as $demand) {
      $agentId = explode('/',$demand->agent->meta->href);
      $agentId = end($agentId);
      if(!isset($demandsAgentsList[$agentId])){
        $demandsAgentsList[$agentId] = (object)array();
        $demandsAgentsList[$agentId]->positions = array();
      }

      $demandCreatedDate = new \DateTime($demand->created);

      foreach ($demand->positions->rows as $dposition) {
        if(!isset($demandsAgentsList[$agentId]->positions[$dposition->assortment->id])){
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id] = array();
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['id'] = $dposition->assortment->id;
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities'] = array();
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities']['month1'] = 0;
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities']['month2'] = 0;
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities']['month3'] = 0;
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities']['month4'] = 0;
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities']['month5'] = 0;
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities']['month6'] = 0;
          $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities']['allFromBeforeCentury'] = 0;
        }

        foreach ($months as $monthid => $month) {
          if($demandCreatedDate >= $month->from AND $demandCreatedDate <= $month->to){
            $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities'][$monthid] += $dposition->quantity;
          }
        }

        $demandsAgentsList[$agentId]->positions[$dposition->assortment->id]['demandquantities']['allFromBeforeCentury'] += $dposition->quantity;
      }
    }

    return $demandsAgentsList;
  }

  private function calculateComissionaireSalesByMonth($grouppedComissionaires,$months,$grouppedDemandsByAgentsAndPositions)
  {
    $data = array();
    $response = array();

    $existInArray = [];

    foreach ($grouppedComissionaires as $gcsKey => $gcs) {
      if(!in_array($gcsKey,$existInArray)){
        $response[$gcsKey] = array();
        $response[$gcsKey]['month_1'] = array();
        $response[$gcsKey]['month_2'] = array();
        $response[$gcsKey]['month_3'] = array();
        $response[$gcsKey]['month_4'] = array();
        $response[$gcsKey]['month_5'] = array();
        $response[$gcsKey]['month_6'] = array();
        $response[$gcsKey]['grouppedProducts'] = array();
        $data[$gcsKey] = (object)array();
        $data[$gcsKey]->salesDate = array();
      }

      foreach ($gcs as $gcsreport) {
        $data[$gcsKey]->salesDate[$gcsreport->reportCreated->format('Y-m-d')] = $gcsreport->positions;
      }
    }


    // Sort reports by months
    $m = 1;
    foreach ($months as $month) {
      foreach ($data as $company => $d) {
        foreach ($d->salesDate as $salereportdate => $salereport) {
          $srdate = new \DateTime($salereportdate);

          if($srdate >= $month->from AND $srdate <= $month->to){
            $response[$company]['month_' .$m] = array_merge($response[$company]['month_' .$m],$salereport);
          }
        }
      }
      $m++;
    }

    // Group assortment by product - months
    foreach ($response as $company => $months) {
      $companyPositionsList = [];
      foreach ($months as $monthid => $positions) {
        if($monthid == 'grouppedProducts'){ continue; }
        if($monthid == 'otherProducts'){ continue; }

        foreach ($positions as $position) {
          $assortmentId = explode('/',$position->assortment->meta->href);
          $assortmentId = end($assortmentId);

          if(!isset($companyPositionsList[$assortmentId])){
            $companyPositionsList[$assortmentId] = [];
            $companyPositionsList[$assortmentId]['month_1'] = [];
            $companyPositionsList[$assortmentId]['month_2'] = [];
            $companyPositionsList[$assortmentId]['month_3'] = [];
            $companyPositionsList[$assortmentId]['month_4'] = [];
            $companyPositionsList[$assortmentId]['month_5'] = [];
            $companyPositionsList[$assortmentId]['month_6'] = [];
            $companyPositionsList['otherProducts'] = [];
          }

          $companyPositionsList[$assortmentId][$monthid][] = $position;
        }

        foreach ($grouppedDemandsByAgentsAndPositions as $agentId => $gdbap) {
          if($company == $agentId){
            foreach ($gdbap->positions as $poskey => $posvalue) {
              if(!isset($companyPositionsList[$poskey])){
                $companyPositionsList['otherProducts'][$poskey] = array();
                $companyPositionsList['otherProducts'][$poskey]['demandquantities'] = $posvalue['demandquantities'];
              }
              else {
                $companyPositionsList[$poskey]['demandquantities'] = $posvalue['demandquantities'];
              }
            }
          }
        }

        $response[$company]['grouppedProducts'] = $companyPositionsList;
      }
    }

    return $response;
  }

  private function calculateComissionaireSalesQuantityAndTotalSum($sales)
  {
    $response = (object)array();
    $response->quantity = 0;
    $response->total    = 0;

    foreach ($sales as $sale) {
      $response->quantity += $sale->quantity;
      $response->total += round($sale->price / 100,1);
    }

    return $response;
  }

  public function createFullMSSalesReport($periodFrom,$periodTo)
  {
    $websiteModel   = new Website();
    $moyskladModel  = new Moysklad();

    $weekPeriodFrom = $periodFrom->format('Y-m-d H:i:s');
    $weekPeriodTo   = $periodTo->format('Y-m-d H:i:s');
    $weeks          = self::getPeriodWeeks($periodFrom,$periodTo,$weekPeriodFrom,$weekPeriodTo);
    $productsTree   = $websiteModel->getProductsTree();
    $profitsWeeks   = $moyskladModel->getPeriodsProfitsWeeks($weeks);
    $brands         = $moyskladModel->getReference('ee0e0cdf-f6bb-11eb-0a80-06a7000f9f63');

    // Recombine Profits
    $addedProfitsWeeks = [];
    foreach ($profitsWeeks as $profitsWeek) {
      $addedWeeks = (object)array();
      $addedWeeks->profitsList = array();
      $addedWeeks->weekFrom = $profitsWeek->weekFrom;
      $addedWeeks->weekTo = $profitsWeek->weekTo;

      foreach ($profitsWeek->profitsList as $profitWeek) {
        if(mb_strpos($profitWeek->assortment->name,'Доставка') !== false){ continue; }
        if(!property_exists($profitWeek->assortment,'article')){ continue; }
        $profitObj                = (object)array();
        $profitObj->article       = $profitWeek->assortment->article;
        $profitObj->sellQuantity  = $profitWeek->sellQuantity;
        $profitObj->sellPrice     = $profitWeek->sellPrice / 100;
        $profitObj->sellCost      = $profitWeek->sellCost / 100;
        $profitObj->sellSum       = $profitWeek->sellSum / 100;
        $profitObj->sellCostSum   = $profitWeek->sellCostSum / 100;
        $profitObj->returnQuantity= $profitWeek->returnQuantity;
        $profitObj->returnSum     = $profitWeek->returnSum / 100;
        $productId              = parse_url($profitWeek->assortment->meta->href)['path'];
        $productId              = explode('/',$productId);
        $profitObj->productId   = end($productId);
        $profitObj->productData = $websiteModel->getProduct($profitObj->productId);
        if(!$profitObj->productData){ continue; }
        $addedWeeks->profitsList[] = $profitObj;
      }

      $addedProfitsWeeks[] = $addedWeeks;
    }

    $sortedProfits          = self::sortProfitsByWeekPeriod($addedProfitsWeeks);
    $sorterCategoryProfits  = self::sortProfitsByWeekPeriodCategories($addedProfitsWeeks);

    $reportLink = self::generateFullMSSaleReport($sortedProfits,$sorterCategoryProfits,$brands->rows,$productsTree);

    return $reportLink;


  }

  public function createComissionerReport($startDate)
  {
    $moyskladModel  = new Moysklad();

    $comissionerData = $moyskladModel->getReceivedComissionerReport();

    $actualReportsList  = [];

    $allSalesData = self::collectAllComissionerSalesData($comissionerData,$moyskladModel);

    foreach ($comissionerData as $comrep) {
      $createdDate = new \DateTime($comrep->created);

      if($createdDate <= $startDate){
        $comrep->positionsList = false;
        $comrep->positionsList = $moyskladModel->getHrefData($comrep->positions->meta->href)->rows;

        $actualReportsList[] = $comrep;
      }
    }

    $readyAgentsList = [];
    foreach ($actualReportsList as $creport) {
      $agentData = (object)array();
      $agentData->reportCreated = new \DateTime($creport->created);
      $agentData->agentId    = explode('/',$creport->agent->meta->href);
      $agentData->agentId    = end($agentData->agentId);
      $agentData->positions = $creport->positionsList;
      $readyAgentsList[] = $agentData;
    }

    $grouppedAgentsReports = [];
    $grouppedAgentsReportsExist = [];
    foreach ($readyAgentsList as $ra) {
      if(!in_array($ra->agentId,$grouppedAgentsReportsExist)){
        $grouppedAgentsReports[$ra->agentId] = array();
        $grouppedAgentsReports[$ra->agentId][] = $ra;
        $grouppedAgentsReportsExist[] = $ra->agentId;
      }
      else {
        $grouppedAgentsReports[$ra->agentId][] = $ra;
      }
    }

    // Quarters
    $quarter1DateFrom = clone $startDate;
    $quarter1DateFrom->setTime(0,0,0);
    $quarter1DateFrom = $quarter1DateFrom->modify('-180 days');
    $quarter1DateTo = clone $startDate;
    $quarter1DateTo = $quarter1DateTo->modify('-90 days');
    $quarter2DateFrom = clone $startDate;
    $quarter2DateFrom = $quarter2DateFrom->modify('-89 days');

    // Months
    $monthsObj = (object)array();
    $monthsObj->month1 = (object)array();
    $monthsObj->month2 = (object)array();
    $monthsObj->month3 = (object)array();
    $monthsObj->month4 = (object)array();
    $monthsObj->month5 = (object)array();
    $monthsObj->month6 = (object)array();

    $month1DateFrom = clone $quarter1DateFrom;
    $month1DateTo = clone $quarter1DateFrom;
    $month1DateFrom = $month1DateFrom->modify('first day of this month');
    $month1DateTo = $month1DateTo->modify('last day of this month');
    $month1DateTo->setTime(23, 59, 59);
    $monthsObj->month1->from = $month1DateFrom;
    $monthsObj->month1->to = $month1DateTo;

    $month2DateFrom = clone $month1DateTo;
    $month2DateFrom = $month2DateFrom->modify('+1 days');
    $month2DateTo = clone $month2DateFrom;
    $month2DateTo = $month2DateTo->modify('last day of this month');
    $month2DateTo->setTime(23, 59, 59);
    $monthsObj->month2->from = $month2DateFrom;
    $monthsObj->month2->to = $month2DateTo;

    $month3DateFrom = clone $month2DateTo;
    $month3DateFrom = $month3DateFrom->modify('+1 days');
    $month3DateTo = clone $month3DateFrom;
    $month3DateTo = $month3DateTo->modify('last day of this month');
    $month3DateTo->setTime(23, 59, 59);
    $monthsObj->month3->from = $month3DateFrom;
    $monthsObj->month3->to = $month3DateTo;

    $month4DateFrom = clone $month3DateTo;
    $month4DateFrom = $month4DateFrom->modify('+1 days');
    $month4DateTo = clone $month4DateFrom;
    $month4DateTo = $month4DateTo->modify('last day of this month');
    $month4DateTo->setTime(23, 59, 59);
    $monthsObj->month4->from = $month4DateFrom;
    $monthsObj->month4->to = $month4DateTo;

    $month5DateFrom = clone $month4DateTo;
    $month5DateFrom = $month5DateFrom->modify('+1 days');
    $month5DateTo = clone $month5DateFrom;
    $month5DateTo = $month5DateTo->modify('last day of this month');
    $month5DateTo->setTime(23, 59, 59);
    $monthsObj->month5->from = $month5DateFrom;
    $monthsObj->month5->to = $month5DateTo;

    $month6DateFrom = clone $month5DateTo;
    $month6DateFrom = $month6DateFrom->modify('+1 days');
    $month6DateTo = clone $month6DateFrom;
    $month6DateTo = $month6DateTo->modify('last day of this month');
    $month6DateTo->setTime(23, 59, 59);
    $monthsObj->month6->from = $month6DateFrom;
    $monthsObj->month6->to = $month6DateTo;

    $forDemandsConditions = (object)array();
    $forDemandsConditions->agents = $readyAgentsList;
    $forDemandsConditions->states = [];
    $forDemandsConditions->states[] = '732ffbde-0a19-11eb-0a80-055600083d2e'; // Статус Передан
    $forDemandsConditions->includeperiods = false;
    $demandListByPeriod = $moyskladModel->getPeriodsDemands($monthsObj->month1->from,$monthsObj->month6->to,$forDemandsConditions);

    $grouppedDemandsByAgentsAndPositions = self::groupDemandsByAgentsAndPositions($demandListByPeriod,$monthsObj);

    $calculatedProductsByMonths = self::calculateComissionaireSalesByMonth($grouppedAgentsReports,$monthsObj,$grouppedDemandsByAgentsAndPositions);

    // EOF ALL TIME REALIZING

    return self::generateComissionaireReport($calculatedProductsByMonths,$allSalesData);
  }

  public function createMarketingReport($year)
  {
    $mreport  = new ReportMarketingTable();
    $cpcmodel = new CpcProjectsTable();
    $cpcdata  = new CpcProjectsDataTable();

    $cpcProjects = $cpcmodel->getProjectsList();
    $data = $mreport->getMarketingYearData($year);

    $cpcProjectsData = [];
    $allMonths = range(1, date('m'));
    foreach ($cpcProjects as $cpcproject) {
      $cpcYearDatas = $cpcdata->getMarketingCpcYearData($cpcproject->id,$year);

      $existingMonths = [];
      foreach ($cpcYearDatas as $cpcYearD) {
        $existingMonths[] = $cpcYearD->month;
      }

      $missingMonths = array_diff($allMonths, $existingMonths);

      if (!empty($missingMonths)) {
        foreach ($missingMonths as $missMonth) {
          $missMonthYear = new \DateTime($year . '-' . $missMonth . '-01');
          $weeks = $mreport->getWeeksFromFirstWednesday($missMonthYear);
          $createRec = $cpcdata->setMarketingEmptyCpcProjectMonthYearRec($cpcproject->id,$missMonth,$year,count($weeks));
          $cpcYearDatas[] = $createRec;
        }
      }

      usort($cpcYearDatas, function ($a, $b) {
        return $a->month <=> $b->month;
      });

      $obj = (object)array(
                        'cpc_project_id' => $cpcproject->id,
                        'cpc_project_title' => $cpcproject->title,
                        'cpc_project_type' => $cpcproject->type,
                        'cpc_project_pid' => $cpcproject->pid,
                        'cpc_project_data' => $cpcYearDatas
                      );
      $cpcProjectsData[] = $obj;
    }

    if(count($data)){
      $reportLink = self::generateMarketingReport($data,$cpcProjectsData);
      return $reportLink;
    }
    else {
      return false;
    }
  }

  public function createIncomeBrandsReport($year)
  {
    $moyskladModel  = new Moysklad();
    $modelTable     = new ReportIncomeBrandsOutlaysTable();
    $assortment     = json_decode(file_get_contents(__DIR__ . '/../../../bot.accio.kz/html/models/catalogueFull.json'));
    $weeksByMonth   = [];
    $now            = new \DateTime();
    $start          = new \DateTime("$year-01-01");
    $outlays        = $modelTable->getIncomeBrandsMonthOfYearOutlays($year);
    $brands         = $moyskladModel->getReference('ee0e0cdf-f6bb-11eb-0a80-06a7000f9f63');

    while ($start->format('N') != 3) {
      $start->modify('+1 day');
    }

    $endOfYear = new \DateTime("$year-12-31");

    while ($start <= $endOfYear) {
        $weekStart = clone $start;
        $weekEnd = clone $start;
        $weekEnd->modify('+6 days');

        if ($weekEnd > $endOfYear) {
            $weekEnd = clone $endOfYear;
        }

        $monthKey = $weekStart->format('m');

        $week = new \stdClass();
        $week->start = $weekStart;
        $week->end = $weekEnd;
        $week->profits = null;

        $weeksByMonth[$monthKey][] = $week;

        $start->modify('+7 days');
    }

    foreach ($weeksByMonth as $monthnum => $weeks) {
        foreach ($weeks as $week) {
            if ($now >= $week->end) {
              $profits = $moyskladModel->getProfitByPeriod(
                                          $week->start,
                                          $week->end,
                                          ['all'],
                                          false
                                        );
              $groupedProfits = self::groupProfitProductsByBrand($profits,$assortment);

              $week->profits = $groupedProfits;
            }
        }
    }

    $reportLink = self::generateIncomeBrandsReport($weeksByMonth,$brands,$outlays);

    return $reportLink;
  }

  private function groupProfitProductsByBrand($profits,$assortment)
  {
    $BRAND_ATTR_ID = 'a51f0b60-f6be-11eb-0a80-081c000ff2c4';

    $assortmentByCode = [];
    foreach ($assortment as $item) {
      if (!empty($item->code)) {
        $assortmentByCode[$item->code] = $item;
      }
    }

    $groupedByBrand = [];

    foreach ($profits as $profitItem) {
      $code = $profitItem->assortment->code ?? null;
      if (!$code || !isset($assortmentByCode[$code])) {
        continue; // нет кода или товар не найден
      }

      $product = $assortmentByCode[$code];

      $brandName = 'Без бренда';
      if (!empty($product->attributes)) {
        foreach ($product->attributes as $attr) {
          if (
            isset($attr->id) &&
                  $attr->id === $BRAND_ATTR_ID &&
                  isset($attr->value->name)
              ) {
                  $brandName = $attr->value->name;
                  break;
              }
          }
      }

      $groupedByBrand[$brandName][] = $profitItem;
    }

    return $groupedByBrand;
  }

  private function columnFirstMarketingReport()
  {
    return [
      ["A2","Все каналы","gray"],
      ["A3","Показы","blue2"],
      ["A4","Всего","green"],
      ["A5","Organic","gray"],
      ["A6","CPC","gray"],
      ["A7","Instagram / Facebook","gray"],
      ["A8","Email","gray"],
      ["A9","Рассылка Bot","gray"],
      ["A10","Direct","gray"],

      ["A11","Клики","blue2"],
      ["A12","Всего","green"],
      ["A13","Organic","gray"],
      ["A14","CPC","gray"],
      ["A15","Instagram / Facebook","gray"],
      ["A16","Email","gray"],
      ["A17","Рассылка Bot","gray"],
      ["A18","Direct","gray"],

      ["A19","% отказов ","yellow"],
      ["A20","Всего","green"],
      ["A21","Organic","gray"],
      ["A22","CPC","gray"],
      ["A23","Instagram / Facebook","gray"],
      ["A24","Email","gray"],
      ["A25","Рассылка Bot","gray"],
      ["A26","Direct","gray"],

      ["A27","Общие затраты на рекламу","green2"],
      ["A28","Всего","green"],
      ["A29","Organic","gray"],
      ["A30","CPC","gray"],
      ["A31","Instagram / Facebook","gray"],
      ["A32","Email","gray"],
      ["A33","Рассылка Bot","gray"],

      ["A34","Стоимость сопровождения","green2"],
      ["A35","Всего","green"],
      ["A36","Organic","gray"],
      ["A37","CPC","gray"],
      ["A38","Instagram / Facebook","gray"],
      ["A39","Email","gray"],
      ["A40","Рассылка Bot","gray"],

      ["A41","Стоимость работ / рекламы","green2"],
      ["A42","Всего","green"],
      ["A43","Organic","gray"],
      ["A44","CPC","gray"],
      ["A45","Instagram / Facebook","gray"],
      ["A46","Email","gray"],
      ["A47","Рассылка Bot","gray"],

      ["A48","Стоимость лида MQL","green2"],
      ["A49","Всего","green"],
      ["A50","Organic","gray"],
      ["A51","CPC","gray"],
      ["A52","Instagram / Facebook","gray"],
      ["A53","Email","gray"],
      ["A54","Рассылка Bot","gray"],

      ["A55","Стоимость лида SQL","green2"],
      ["A56","Всего","green"],
      ["A57","Organic","gray"],
      ["A58","CPC","gray"],
      ["A59","Instagram / Facebook","gray"],
      ["A60","Email","gray"],
      ["A61","Рассылка Bot","gray"],

      ["A62","Прибыль ROI в $","green2"],
      ["A63","Всего","green"],
      ["A64","Organic","gray"],
      ["A65","CPC","gray"],
      ["A66","Instagram / Facebook","gray"],
      ["A67","Email","gray"],
      ["A68","Рассылка Bot","gray"],
      ["A69","Direct","gray"],

      ["A70","Прибыль в %","green2"],
      ["A71","Всего","green"],
      ["A72","Organic","gray"],
      ["A73","CPC","gray"],
      ["A74","Instagram / Facebook","gray"],
      ["A75","Email","gray"],
      ["A76","Рассылка Bot","gray"],
      ["A77","Direct","gray"],

      ["A78","ROMI c учетом работ","green2"],
      ["A79","Всего","green"],
      ["A80","Organic","gray"],
      ["A81","CPC","gray"],
      ["A82","Instagram / Facebook","gray"],
      ["A83","Email","gray"],
      ["A84","Рассылка Bot","gray"],

      ["A85","ROMI","green2"],
      ["A86","Всего","green"],
      ["A87","Organic","gray"],
      ["A88","CPC","gray"],
      ["A89","Instagram / Facebook","gray"],
      ["A90","Email","gray"],
      ["A91","Рассылка Bot","gray"],

      ["A92","Всего конверсий в шт","blue2"],
      ["A93","Всего","green"],
      ["A94","Organic","gray"],
      ["A95","CPC","gray"],
      ["A96","Instagram / Facebook","gray"],
      ["A97","Email","gray"],
      ["A98","Рассылка Bot","gray"],
      ["A99","Direct","gray"],

      ["A100","Добавлениев корзину в шт","blue2"],
      ["A101","Всего","green"],
      ["A102","Organic","gray"],
      ["A103","CPC","gray"],
      ["A104","Instagram / Facebook","gray"],
      ["A105","Email","gray"],
      ["A106","Рассылка Bot","gray"],
      ["A107","Direct","gray"],

      ["A108","WhatsApp конверсий в шт","blue2"],
      ["A109","Всего","green"],
      ["A110","Organic","gray"],
      ["A111","CPC","gray"],
      ["A112","Instagram / Facebook","gray"],
      ["A113","Email","gray"],
      ["A114","Рассылка Bot","gray"],
      ["A115","Direct","gray"],

      ["A116","Binotel конверсий в шт","blue2"],
      ["A117","Всего","green"],
      ["A118","Organic","gray"],
      ["A119","CPC","gray"],
      ["A120","Instagram / Facebook","gray"],
      ["A121","Email","gray"],
      ["A122","Рассылка Bot","gray"],
      ["A123","Direct","gray"],

      ["A124","Покупки в шт","blue2"],
      ["A125","Всего","green"],
      ["A126","Organic","gray"],
      ["A127","CPC","gray"],
      ["A128","Instagram / Facebook","gray"],
      ["A129","Email","gray"],
      ["A130","Рассылка Bot","gray"],
      ["A131","Direct","gray"],

      ["A132","Покупки Purchase,шт","blue2"],
      ["A133","Всего","green"],
      ["A134","Organic","gray"],
      ["A135","CPC","gray"],
      ["A136","Instagram / Facebook","gray"],
      ["A137","Email","gray"],
      ["A138","Рассылка Bot","gray"],
      ["A139","Direct","gray"],

      ["A140","Покупки WhatsApp,шт","blue2"],
      ["A141","Всего","green"],
      ["A142","Organic","gray"],
      ["A143","CPC","gray"],
      ["A144","Instagram / Facebook","gray"],
      ["A145","Email","gray"],
      ["A146","Рассылка Bot","gray"],
      ["A147","Direct","gray"],

      ["A148","Покупки Binotel,шт","blue2"],
      ["A149","Всего","green"],
      ["A150","Organic","gray"],
      ["A151","CPC","gray"],
      ["A152","Instagram / Facebook","gray"],
      ["A153","Email","gray"],
      ["A154","Рассылка Bot","gray"],
      ["A155","Direct","gray"],

      ["A156","Покупки в $","green2"],
      ["A157","Всего","green"],
      ["A158","Organic","gray"],
      ["A159","CPC","gray"],
      ["A160","Instagram / Facebook","gray"],
      ["A161","Email","gray"],
      ["A162","Рассылка Bot","gray"],
      ["A163","Direct","gray"],

      ["A164","Покупки в Purchase $","green2"],
      ["A165","Всего","green"],
      ["A166","Organic","gray"],
      ["A167","CPC","gray"],
      ["A168","Instagram / Facebook","gray"],
      ["A169","Email","gray"],
      ["A170","Рассылка Bot","gray"],
      ["A171","Direct","gray"],

      ["A172","Покупки в WhatsApp $","green2"],
      ["A173","Всего","green"],
      ["A174","Organic","gray"],
      ["A175","CPC","gray"],
      ["A176","Instagram / Facebook","gray"],
      ["A177","Email","gray"],
      ["A178","Рассылка Bot","gray"],
      ["A179","Direct","gray"],

      ["A180","Покупки в Binotel $","green2"],
      ["A181","Всего","green"],
      ["A182","Organic","gray"],
      ["A183","CPC","gray"],
      ["A184","Instagram / Facebook","gray"],
      ["A185","Email","gray"],
      ["A186","Рассылка Bot","gray"],
      ["A187","Direct","gray"],

      ["A188","Средний чек в $","green2"],
      ["A189","Всего","green"],
      ["A190","Organic","gray"],
      ["A191","CPC","gray"],
      ["A192","Instagram / Facebook","gray"],
      ["A193","Email","gray"],
      ["A194","Рассылка Bot","gray"],
      ["A195","Direct","gray"],

      ["A196","Средний чек Purchase в $","green2"],
      ["A197","Всего","green"],
      ["A198","Organic","gray"],
      ["A199","CPC","gray"],
      ["A200","Instagram / Facebook","gray"],
      ["A201","Email","gray"],
      ["A202","Рассылка Bot","gray"],
      ["A203","Direct","gray"],

      ["A204","Средний чек WhatsApp в $","green2"],
      ["A205","Всего","green"],
      ["A206","Organic","gray"],
      ["A207","CPC","gray"],
      ["A208","Instagram / Facebook","gray"],
      ["A209","Email","gray"],
      ["A210","Рассылка Bot","gray"],
      ["A211","Direct","gray"],

      ["A212","Средний чек Binotel в $","green2"],
      ["A213","Всего","green"],
      ["A214","Organic","gray"],
      ["A215","CPC","gray"],
      ["A216","Instagram / Facebook","gray"],
      ["A217","Email","gray"],
      ["A218","Рассылка Bot","gray"],
      ["A219","Direct","gray"],

      ["A220","Клик в добавление в корзину %, по каналу взаимодействия","yellow"],
      ["A221","Всего","green"],
      ["A222","Organic","gray"],
      ["A223","CPC","gray"],
      ["A224","Instagram / Facebook","gray"],
      ["A225","Email","gray"],
      ["A226","Рассылка Bot","gray"],
      ["A227","Direct","gray"],

      ["A228","Клик в переход % WhatsApp, по каналу взаимодействия","yellow"],
      ["A229","Всего","green"],
      ["A230","Organic","gray"],
      ["A231","CPC","gray"],
      ["A232","Instagram / Facebook","gray"],
      ["A233","Email","gray"],
      ["A234","Рассылка Bot","gray"],
      ["A235","Direct","gray"],

      ["A236","Клик в переход % Binotel, по каналу взаимодействия","yellow"],
      ["A237","Всего","green"],
      ["A238","Organic","gray"],
      ["A239","CPC","gray"],
      ["A240","Instagram / Facebook","gray"],
      ["A241","Email","gray"],
      ["A242","Рассылка Bot","gray"],
      ["A243","Direct","gray"],

      ["A244","Клик в заказ % Purchase, по каналу взаимодействия","yellow"],
      ["A245","Всего","green"],
      ["A246","Organic","gray"],
      ["A247","CPC","gray"],
      ["A248","Instagram / Facebook","gray"],
      ["A249","Email","gray"],
      ["A250","Рассылка Bot","gray"],
      ["A251","Direct","gray"],

      ["A252","Клик в заказ % WhatsApp, по каналу взаимодействия","yellow"],
      ["A253","Всего","green"],
      ["A254","Organic","gray"],
      ["A255","CPC","gray"],
      ["A256","Instagram / Facebook","gray"],
      ["A257","Email","gray"],
      ["A258","Рассылка Bot","gray"],
      ["A259","Direct","gray"],

      ["A260","Клик в заказ % Binotel, по каналу взаимодействия","yellow"],
      ["A261","Всего","green"],
      ["A262","Organic","gray"],
      ["A263","CPC","gray"],
      ["A264","Instagram / Facebook","gray"],
      ["A265","Email","gray"],
      ["A266","Рассылка Bot","gray"],
      ["A267","Direct","gray"],

      ["A268","Добавление в корзину в заказ %, по каналу","yellow"],
      ["A269","Всего","green"],
      ["A270","Organic","gray"],
      ["A271","CPC","gray"],
      ["A272","Instagram / Facebook","gray"],
      ["A273","Email","gray"],
      ["A274","Рассылка Bot","gray"],
      ["A275","Direct","gray"],

      ["A276","Organic","blue"],
      ["A277","Показы",""],
      ["A278","Клики / Переходы",""],
      ["A279","Средняя позиция сайта",""],
      ["A280","% отказов ",""],
      ["A281","Стоимость сопровождения",""],
      ["A282","Стоимость работ",""],
      ["A283","Стоимость лида MQL",""],
      ["A284","Стоимость лида SQL",""],
      ["A285","Прибыль ROI в $",""],
      ["A286","Прибыль в %",""],
      ["A287","ROMI c учетом работ",""],
      ["A288","ROMI",""],
      ["A289","Всего конверсий в шт","green"],
      ["A290","Добавление в корзину, шт",""],
      ["A291","WhatsApp / переходы, шт",""],
      ["A292","Binotel / звонки, шт",""],
      ["A293","Покупки в шт","green"],
      ["A294","Purchase / покупки на сайте, шт",""],
      ["A295","WhatsApp / покупки, шт",""],
      ["A296","Binotel / покупки, шт",""],
      ["A297","Покупки в $","green"],
      ["A298","Purchase / покупки на сайте, $",""],
      ["A299","WhatsApp / ≧ покупки, $",""],
      ["A300","Binotel / ≧ покупки, $",""],
      ["A301","Средний чек в $","green"],
      ["A302","Purchase / покупки на сайте, $",""],
      ["A303","WhatsApp / ≧ покупки, $",""],
      ["A304","Binotel / ≧ покупки, $",""],
      ["A305","Показа в Клик (CTR),%","green"],
      ["A306","Клик в заказы (все консерсии), %","green"],
      ["A307","Показ в заказ","green"],
      ["A308","Клик / Добавление в корзину","yellow"],
      ["A309","Добавление в корзину в заказ","yellow"],
      ["A310","Клик в заказ на сайте","yellow"],
      ["A311","Показ в заказ на сайте","yellow"],
      ["A312","Клик переход в WhatsApp","blue2"],
      ["A313","Переход WhatsApp в заказ","blue2"],
      ["A314","Клик заказ в WhatsApp","blue2"],
      ["A315","Показ в заказ в WhatsApp","blue2"],
      ["A316","Клик переход в Binotel","green2"],
      ["A317","Переход в Binotel в заказ","green2"],
      ["A318","Клик заказ в Binotel","green2"],
      ["A319","Показ в заказ Binotel","green2"],
      ["A320","CPC","blue"],
      ["A321","Показы",""],
      ["A322","Клики / Переходы",""],
      ["A323","Средняя стоимость клика",""],
      ["A324","% отказов ",""],
      ["A325","Стоимость сопровождения, $",""],
      ["A326","Стоимость рекламы",""],
      ["A327","Стоимость конверсии",""],
      ["A328","Стоимость лида MQL",""],
      ["A329","Стоимость лида SQL",""],
      ["A330","Прибыль ROI в $",""],
      ["A331","Прибыль в %",""],
      ["A332","ROMI c учетом работ",""],
      ["A333","ROMI",""],
      ["A334","Всего конверсий в шт","green"],
      ["A335","Добавление в корзину, шт",""],
      ["A336","WhatsApp / переходы, шт",""],
      ["A337","Binotel / звонки, шт",""],
      ["A338","Покупки в шт","green"],
      ["A339","Purchase / покупки на сайте, шт",""],
      ["A340","WhatsApp / покупки, шт",""],
      ["A341","Binotel / покупки, шт",""],
      ["A342","Покупки в $","green"],
      ["A343","Purchase / покупки на сайте, $",""],
      ["A344","WhatsApp / ≧ покупки, $",""],
      ["A345","Binotel / ≧ покупки, $",""],
      ["A346","Средний чек в $","green"],
      ["A347","Purchase / покупки на сайте, $",""],
      ["A348","WhatsApp / ≧ покупки, $",""],
      ["A349","Binotel / ≧ покупки, $",""],
      ["A350","Показа в Клик (CTR),%","green"],
      ["A351","Клик в заказы (все консерсии), %","green"],
      ["A352","Показ в заказ","green"],
      ["A353","Клик / Добавление в корзину","yellow"],
      ["A354","Добавление в корзину в заказ","yellow"],
      ["A355","Клик в заказ на сайте","yellow"],
      ["A356","Показ в заказ на сайте","yellow"],
      ["A357","Клик переход в WhatsApp","blue2"],
      ["A358","Переход WhatsApp в заказ","blue2"],
      ["A359","Клик заказ в WhatsApp","blue2"],
      ["A360","Показ в заказ в WhatsApp","blue2"],
      ["A361","Клик переход в Binotel","green2"],
      ["A362","Переход в Binotel в заказ","green2"],
      ["A363","Клик заказ в Binotel","green2"],
      ["A364","Показ в заказ Binotel","green2"],
      ["A365","Instagram / Facebook","blue"],
      ["A366","Показы",""],
      ["A367","Клики / Переходы",""],
      ["A368","Средняя стоимость клика",""],
      ["A369","% отказов ",""],
      ["A370","Стоимость сопровождения",""],
      ["A371","Стоимость рекламы",""],
      ["A372","Стоимость лида MQL",""],
      ["A373","Стоимость лида SQL",""],
      ["A374","Прибыль ROI в $",""],
      ["A375","Прибыль в %",""],
      ["A376","ROMI c учетом работ",""],
      ["A377","ROMI",""],
      ["A378","Всего конверсий в шт","green"],
      ["A379","Добавление в корзину, шт",""],
      ["A380","WhatsApp / переходы, шт",""],
      ["A381","Binotel / звонки, шт",""],
      ["A382","Покупки в шт","green"],
      ["A383","Purchase / покупки на сайте, шт",""],
      ["A384","WhatsApp / покупки, шт",""],
      ["A385","Binotel / покупки, шт",""],
      ["A386","Покупки в $","green"],
      ["A387","Purchase / покупки на сайте, $",""],
      ["A388","WhatsApp / ≧ покупки, $",""],
      ["A389","Binotel / ≧ покупки, $",""],
      ["A390","Средний чек в $","green"],
      ["A391","Purchase / покупки на сайте, $",""],
      ["A392","WhatsApp / ≧ покупки, $",""],
      ["A393","Binotel / ≧ покупки, $",""],
      ["A394","Показа в Клик (CTR),%","green"],
      ["A395","Клик в заказы (все консерсии), %","green"],
      ["A396","Показ в заказ","green"],
      ["A397","Клик / Добавление в корзину","yellow"],
      ["A398","Добавление в корзину в заказ","yellow"],
      ["A399","Клик в заказ на сайте","yellow"],
      ["A400","Показ в заказ на сайте","yellow"],
      ["A401","Клик переход в WhatsApp","blue2"],
      ["A402","Переход WhatsApp в заказ","blue2"],
      ["A403","Клик заказ в WhatsApp","blue2"],
      ["A404","Показ в заказ в WhatsApp","blue2"],
      ["A405","Клик переход в Binotel","green2"],
      ["A406","Переход в Binotel в заказ","green2"],
      ["A407","Клик заказ в Binotel","green2"],
      ["A408","Показ в заказ Binotel","green2"],
      ["A409","Email","blue"],
      ["A410","Отправлено писем, шт",""],
      ["A411","Доставлено писем, шт",""],
      ["A412","Открыто писем, шт",""],
      ["A413","Клики / Переходы, шт",""],
      ["A414","Средняя стоимость клика",""],
      ["A415","% отказов ",""],
      ["A416","Стоимость работ",""],
      ["A417","Стоимость рассылки",""],
      ["A418","Стоимость лида MQL",""],
      ["A419","Стоимость лида SQL",""],
      ["A420","Прибыль ROI в $",""],
      ["A421","Прибыль в %",""],
      ["A422","ROMI",""],
      ["A423","ROMI c учетом работ",""],
      ["A424","Всего конверсий в шт","green"],
      ["A425","Добавление в корзину, шт",""],
      ["A426","WhatsApp / переходы, шт",""],
      ["A427","Binotel / звонки, шт",""],
      ["A428","Покупки в шт","green"],
      ["A429","Purchase / покупки на сайте, шт",""],
      ["A430","WhatsApp / покупки, шт",""],
      ["A431","Binotel / покупки, шт",""],
      ["A432","Покупки в $","green"],
      ["A433","Purchase / покупки на сайте, $",""],
      ["A434","WhatsApp / ≧ покупки, $",""],
      ["A435","Binotel / ≧ покупки, $",""],
      ["A436","Средний чек в $","green"],
      ["A437","Purchase / покупки на сайте, $",""],
      ["A438","WhatsApp / ≧ покупки, $",""],
      ["A439","Binotel / ≧ покупки, $",""],
      ["A440","Отправлено в доставлено,%","green"],
      ["A441","Доставлено в открыто, %","green"],
      ["A442","Открыто в клик, %","green"],
      ["A443","Открыто в заказ","green"],
      ["A444","Клик / Добавление в корзину","yellow"],
      ["A445","Добавление в корзину в заказ","yellow"],
      ["A446","Клик в заказ на сайте","yellow"],
      ["A447","Доставлено в заказ на сайте","yellow"],
      ["A448","Клик переход в WhatsApp","blue2"],
      ["A449","Переход WhatsApp в заказ","blue2"],
      ["A450","Клик заказ в WhatsApp","blue2"],
      ["A451","Доставлено в заказ в WhatsApp","blue2"],
      ["A452","Клик переход в Binotel","green2"],
      ["A453","Переход в Binotel в заказ","green2"],
      ["A454","Клик заказ в Binotel","green2"],
      ["A455","Доставлено в заказ Binotel","green2"],
      ["A456","Рассылка Bot","blue"],
      ["A457","Отправлено",""],
      ["A458","Отклонено",""],
      ["A459","Доставлено",""],
      ["A460","Прочитано",""],
      ["A461","Средняя стоимость клика",""],
      ["A462","% отказов ",""],
      ["A463","Стоимость работ",""],
      ["A464","Стоимость рассылки",""],
      ["A465","Стоимость лида MQL",""],
      ["A466","Стоимость лида SQL",""],
      ["A467","Прибыль ROI в $",""],
      ["A468","Прибыль в %",""],
      ["A469","ROMI",""],
      ["A470","ROMI c учетом работ",""],
      ["A471","Всего конверсий в шт","green"],
      ["A472","Добавление в корзину, шт",""],
      ["A473","WhatsApp / запросы, шт",""],
      ["A474","Binotel / звонки, шт",""],
      ["A475","Покупки в шт","green"],
      ["A476","Purchase / покупки на сайте, шт",""],
      ["A477","WhatsApp / покупки, шт",""],
      ["A478","Binotel / покупки, шт",""],
      ["A479","Покупки в $","green"],
      ["A480","Purchase / покупки на сайте, $",""],
      ["A481","WhatsApp / ≧ покупки, $",""],
      ["A482","Binotel / ≧ покупки, $",""],
      ["A483","Средний чек в $","green"],
      ["A484","Purchase / покупки на сайте, $",""],
      ["A485","WhatsApp / ≧ покупки, $",""],
      ["A486","Binotel / ≧ покупки, $",""],
      ["A487","Отправлено в отклонено,%","green"],
      ["A488","Отправлено доставлено, %","green"],
      ["A489","Доставлено в прочитано (CTR), %","green"],
      ["A490","Прочитано в заказ, %","green"],
      ["A491","Доставлено в заказ, %","green"],
      ["A492","Отправлено в заказ, %","green"],
      ["A493","Прочитано / Добавление в корзину","yellow"],
      ["A494","Добавление в корзину в заказ","yellow"],
      ["A495","Доставлено в заказ на сайте","yellow"],
      ["A496","Прочитано в заказ на сайте","yellow"],
      ["A497","Отправлено в заказ на сайте","yellow"],
      ["A498","Отправлено в продажи","blue2"],
      ["A499","Доставлено в продажи","blue2"],
      ["A500","Прочитано в продажи","blue2"],
      ["A501","Отправлено переход в Binotel","green2"],
      ["A502","Доставлено переход в Binotel","green2"],
      ["A503","Прочитано переход в Binotel","green2"],
      ["A504","Прочитано в заказ Binotel","green2"],
      ["A505","Отправлено в заказ Binotel","green2"],
      ["A506","Direct","blue"],
      ["A507","Сеансы",""],
      ["A508","Сеансы с взаимодействием",""],
      ["A509","% отказов ",""],
      ["A510","Прибыль ROI в $",""],
      ["A511","Прибыль в %",""],
      ["A512","Всего конверсий в шт","green"],
      ["A513","Добавление в корзину, шт",""],
      ["A514","WhatsApp / переходы, шт",""],
      ["A515","Binotel / звонки, шт",""],
      ["A516","Покупки в шт","green"],
      ["A517","Purchase / покупки на сайте, шт",""],
      ["A518","WhatsApp / покупки, шт",""],
      ["A519","Binotel / покупки, шт",""],
      ["A520","Покупки в $","green"],
      ["A521","Purchase / покупки на сайте, $",""],
      ["A522","WhatsApp / ≧ покупки, $",""],
      ["A523","Binotel / ≧ покупки, $",""],
      ["A524","Средний чек в $","green"],
      ["A525","Purchase / покупки на сайте, $",""],
      ["A526","WhatsApp / ≧ покупки, $",""],
      ["A527","Binotel / ≧ покупки, $",""],
      ["A528","Сеансы в Взаимодействие (CTR),%","green"],
      ["A529","Взаимодействие в заказы (все конверсии), %","green"],
      ["A530","Сеансы в заказ","green"],
      ["A531","Взаимодействие / Добавление в корзину","yellow"],
      ["A532","Добавление в корзину в заказ","yellow"],
      ["A533","Взаимодействие в заказ на сайте","yellow"],
      ["A534","Сеанс в заказ на сайте","yellow"],
      ["A535","Взаимодействие переход в WhatsApp","blue2"],
      ["A536","Переход WhatsApp в заказ","blue2"],
      ["A537","Взаимодействие заказ в WhatsApp","blue2"],
      ["A538","Сеанс в заказ в WhatsApp","blue2"],
      ["A539","Взаимодействие переход в Binotel","green2"],
      ["A540","Переход в Binotel в заказ","green2"],
      ["A541","Взаимодействие заказ в Binotel","green2"],
      ["A542","Сеанс в заказ Binotel","green2"]
    ];
  }

  private function collectRowsStylesMarketingReport()
  {
    $styles = (object)array();

    $styles->green = [ // Зеленая строка
                          'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '92d050'],
                          ],
                          // 'alignment' => [
                          //   'horizontal' => Alignment::HORIZONTAL_CENTER, // Выравнивание по центру
                          //   'vertical' => Alignment::VERTICAL_CENTER,
                          // ],
                          'font' => [
                            'bold' => true,
                          ]
                        ];

    $styles->gray = [ // Серая строка
                          'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'e7e6e6'],
                          ],
                          // 'alignment' => [
                          //   'horizontal' => Alignment::HORIZONTAL_CENTER, // Выравнивание по центру
                          //   'vertical' => Alignment::VERTICAL_CENTER,
                          // ],
                          'font' => [
                            'bold' => true,
                          ]
                        ];

    $styles->blue = [ // Синяя строка
                          'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '00b0f0'],
                          ],
                          'font' => [
                            'bold' => true,
                          ]
                        ];

    $styles->yellow = [ // Желтая строка
                          'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'fef2cb'],
                          ],
                          'font' => [
                            'bold' => true,
                          ]
                        ];

    $styles->blue2 = [ // Голубая строка
                          'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'deeaf6'],
                          ],
                          'font' => [
                            'bold' => true,
                          ]
                        ];

    $styles->green2 = [ // Голубая строка
                          'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'e2efd9'],
                          ],
                          'font' => [
                            'bold' => true,
                          ]
                        ];

    return $styles;
  }

  private function countTotalColumnsMarketingReport($data)
  {
    $response = 0;
    $mreport = new ReportMarketingTable();

    foreach ($data as $d) {
      $ddate = new \DateTime($d->year . '-' . $d->month . '-01');
      $weeks = $mreport->getWeeksFromFirstWednesday($ddate);
      $response += count($weeks)+1;
    }

    return $response;
  }

  private function calculateAverageInArrayWithoutZeros(array $numbers): float
  {
    $filteredNumbers = array_filter($numbers, fn($num) => $num != 0);
    if (count($filteredNumbers) === 0) {
      return 0;
    }

    return array_sum($filteredNumbers) / count($filteredNumbers);
  }

  private function safeExplode($delimiter, $string)
  {
    return array_map(fn($val) => $val === '' ? 0 : $val, explode($delimiter, $string));
  }

  private function getMonthSortedDataMarketingReport($data)
  {
    $response = (object)array(
                          'basic' => (object)array(
                                              'data_1_1' => !empty($data->data_1_1) ? $data->data_1_1 : 0, // Все каналы: К-ф
                                              'data_1_2' => !empty($data->data_1_2) ? $data->data_1_2 : 0, // Все каналы: Курс $
                                              'data_1_3' => $this->safeExplode(':::', $data->data_1_3), // Все каналы: Рентабельность, %
                                             ),

                          'organic' => (object)array(
                                                'data_2_10' => $this->safeExplode(':::', $data->data_2_10), // Показы
                                                'data_2_11' => $this->safeExplode(':::', $data->data_2_11), // Клики
                                                'data_2_12' => $this->safeExplode(':::', $data->data_2_12), // Средняя позиция сайта
                                                'data_2_3' => $this->safeExplode(':::', $data->data_2_3), // Добавление в корзину, шт
                                                'data_2_4' => $this->safeExplode(':::', $data->data_2_4), // WhatsApp / переходы, шт
                                                'data_2_5' => $this->safeExplode(':::', $data->data_2_5), // Binotel / звонки, шт
                                                'data_2_6' => $this->safeExplode(':::', $data->data_2_6), // Purchase / покупки на сайте, шт
                                                'data_2_7' => $this->safeExplode(':::', $data->data_2_7), // Binotel / покупки, шт
                                                'data_2_8' => $this->safeExplode(':::', $data->data_2_8), // Purchase / покупки на сайте, Тенге
                                                'data_2_9' => $this->safeExplode(':::', $data->data_2_9), // Binotel / ≧ покупки, Тенге
                                                'data_2_1' => !empty($data->data_2_1) ? $data->data_2_1 : 0, // Стоимость сопровождения, Тенге
                                                'data_2_2' => !empty($data->data_2_2) ? $data->data_2_2 : 0, // Стоимость работ, Тенге
                                              ),

                          'cpc' => (object)array(
                                                'data_3_1' => !empty($data->data_3_1) ? $data->data_3_1 : 0 // Стоимость сопровождения, Тенге
                                              ),

                          'instagramfacebook' => (object)array(
                                                          'data_4_1' => $this->safeExplode(':::', $data->data_4_1), // 'Instagram / Facebook: Показы',
                                                          'data_4_2' => $this->safeExplode(':::', $data->data_4_2), // 'Instagram / Facebook: Клики / Переходы',
                                                          'data_4_3' => !empty($data->data_4_3) ? $data->data_4_3 : 0, // 'Instagram / Facebook: Стоимость сопровождения',
                                                          'data_4_4' => $this->safeExplode(':::', $data->data_4_4), // 'Instagram / Facebook: Стоимость рекламы',
                                                          'data_4_5' => $this->safeExplode(':::', $data->data_4_5), // 'Instagram / Facebook: (Всего конверсий в шт) Добавление в корзину, шт',
                                                          'data_4_6' => $this->safeExplode(':::', $data->data_4_6), // 'Instagram / Facebook: (Всего конверсий в шт) WhatsApp / переходы, шт',
                                                          'data_4_7' => $this->safeExplode(':::', $data->data_4_7), // 'Instagram / Facebook: (Всего конверсий в шт) Binotel / звонки, шт',
                                                          'data_4_8' => $this->safeExplode(':::', $data->data_4_8), // 'Instagram / Facebook: (Покупки в шт) Purchase / покупки на сайте, шт',
                                                          'data_4_9' => $this->safeExplode(':::', $data->data_4_9), // 'Instagram / Facebook: (Покупки в шт) Binotel / покупки, шт',
                                                          'data_4_10' => $this->safeExplode(':::', $data->data_4_10), // 'Instagram / Facebook: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
                                                          'data_4_11' => $this->safeExplode(':::', $data->data_4_11), // 'Instagram / Facebook: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',
                                              ),

                          'email' => (object)array(
                                              'data_5_1' => $this->safeExplode(':::', $data->data_5_1), // 'Email: Отправлено писем, шт',
                                              'data_5_2' => $this->safeExplode(':::', $data->data_5_2), // 'Email: Доставлено писем, шт',
                                              'data_5_3' => $this->safeExplode(':::', $data->data_5_3), // 'Email: Открыто писем, шт',
                                              'data_5_4' => $this->safeExplode(':::', $data->data_5_4), // 'Email: Клики / Переходы, шт',
                                              'data_5_5' => $this->safeExplode(':::', $data->data_5_5), // 'Email: Стоимость рассылки, Тенге',
                                              'data_5_6' => $this->safeExplode(':::', $data->data_5_6), // 'Email: Стомость работ, $',
                                              'data_5_7' => $this->safeExplode(':::', $data->data_5_7), // 'Email: (Всего конверсий в шт) Добавление в корзину, шт',
                                              'data_5_8' => $this->safeExplode(':::', $data->data_5_8), // 'Email: (Всего конверсий в шт) WhatsApp / переходы, шт',
                                              'data_5_9' => $this->safeExplode(':::', $data->data_5_9), // 'Email: (Всего конверсий в шт) Binotel / звонки, шт',
                                              'data_5_10' => $this->safeExplode(':::', $data->data_5_10), // 'Email: (Покупки в шт) Purchase / покупки на сайте, шт',
                                              'data_5_11' => $this->safeExplode(':::', $data->data_5_11), // 'Email: (Покупки в шт) Binotel / покупки, шт',
                                              'data_5_12' => $this->safeExplode(':::', $data->data_5_12), // 'Email: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
                                              'data_5_13' => $this->safeExplode(':::', $data->data_5_13), // 'Email: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',
                                            ),

                          'bot' => (object)array(
                                            'data_6_1' => $this->safeExplode(':::', $data->data_6_1), // 'Рассылка Bot: Отправлено',
                                            'data_6_2' => $this->safeExplode(':::', $data->data_6_2), //'Рассылка Bot: Отклонено',
                                            'data_6_3' => $this->safeExplode(':::', $data->data_6_3), //'Рассылка Bot: Доставлено',
                                            'data_6_4' => $this->safeExplode(':::', $data->data_6_4), //'Рассылка Bot: Прочитано',
                                            'data_6_5' => $this->safeExplode(':::', $data->data_6_5), //'Рассылка Bot: Стоимость рассылки',
                                            'data_6_6' => !empty($data->data_6_6) ? $data->data_6_6 : 0, //'Рассылка Bot: Стомость работ, $',
                                            'data_6_7' => $this->safeExplode(':::', $data->data_6_7), //'Рассылка Bot: (Всего конверсий в шт) Добавление в корзину, шт',
                                            'data_6_8' => $this->safeExplode(':::', $data->data_6_8), //'Рассылка Bot: (Всего конверсий в шт) WhatsApp / переходы, шт',
                                            'data_6_9' => $this->safeExplode(':::', $data->data_6_9), //'Рассылка Bot: (Всего конверсий в шт) Binotel / звонки, шт',
                                            'data_6_10' => $this->safeExplode(':::', $data->data_6_10), //'Рассылка Bot: (Покупки в шт) Purchase / покупки на сайте, шт',
                                            'data_6_11' => $this->safeExplode(':::', $data->data_6_11), //'Рассылка Bot: (Покупки в шт) Binotel / покупки, шт',
                                            'data_6_12' => $this->safeExplode(':::', $data->data_6_12), //'Рассылка Bot: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
                                            'data_6_13' => $this->safeExplode(':::', $data->data_6_13), //'Рассылка Bot: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',
                                          ),

                          'direct' => (object)array(
                                                'data_7_1' => $this->safeExplode(':::', $data->data_7_1), // 'Direct: Сеансы',
                                                'data_7_2' => $this->safeExplode(':::', $data->data_7_2), // 'Direct: Сеансы с взаимодействием',
                                                'data_7_3' => $this->safeExplode(':::', $data->data_7_3), // 'Direct: (Всего конверсий в шт) Добавление в корзину, шт',
                                                'data_7_4' => $this->safeExplode(':::', $data->data_7_4), // 'Direct: (Всего конверсий в шт) WhatsApp / запросы, шт',
                                                'data_7_5' => $this->safeExplode(':::', $data->data_7_5), // 'Direct: (Всего конверсий в шт) Binotel / звонки, шт',
                                                'data_7_6' => $this->safeExplode(':::', $data->data_7_6), // 'Direct: (Покупки в шт) Purchase / покупки на сайте, шт',
                                                'data_7_7' => $this->safeExplode(':::', $data->data_7_7), // 'Direct: (Покупки в шт) Binotel / покупки, шт',
                                                'data_7_8' => $this->safeExplode(':::', $data->data_7_8), // 'Direct: (Покупки в Тенге) Purchase / покупки на сайте, Тенге',
                                                'data_7_9' => $this->safeExplode(':::', $data->data_7_9), // 'Direct: (Покупки в Тенге) Binotel / ≧ покупки, Тенге',
                                              ),

                          'whatsappsales' => (object)array(
                                                      'data_8_4' => $this->safeExplode(':::', $data->data_8_4), // WhatsApp: Сумма продаж в Тенге
                                                      'data_8_1' => $this->safeExplode(':::', $data->data_8_1), // WhatsApp: Всего обращений WhatsApp, шт
                                                      'data_8_2' => $this->safeExplode(':::', $data->data_8_2), // WhatsApp: Всего конверсий чаты, шт
                                                      'data_8_3' => $this->safeExplode(':::', $data->data_8_3), // WhatsApp: Всего продаж WhatsApp, шт
                                                     )
                        );

    return $response;
  }

  // REPORTS XLS GENERATORS
  private function generateIncomeBrandsReport($data,$brands,$outlays)
  {
    $fileName       = 'Прибыльность_по_брендам_' . date('d.m.Y_H.i.s') . '.xlsx';
    $spreadsheet    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet          = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A4', 'Затраты с инвест');
    $sheet->setCellValue('A5', 'Затраты без инвест');
    $sheet->getStyle('A4')->getFont()->setBold(true);
    $sheet->getStyle('A5')->getFont()->setBold(true);

    // Форматирование
    $conditionalRed = new Conditional();
    $conditionalRed->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalRed->setOperatorType(Conditional::OPERATOR_BETWEEN);
    $conditionalRed->setStopIfTrue(true);
    $conditionalRed->addCondition('0.01');
    $conditionalRed->addCondition('0.6');
    $conditionalRed->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalRed->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
    $conditionalRed->getStyle()->getFont()->getColor()->setARGB('9c0006');

    $conditionalYellow = new Conditional();
    $conditionalYellow->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalYellow->setOperatorType(Conditional::OPERATOR_BETWEEN);
    $conditionalYellow->setStopIfTrue(true);
    $conditionalYellow->addCondition('0.601');
    $conditionalYellow->addCondition('0.999');
    $conditionalYellow->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalYellow->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c');
    $conditionalYellow->getStyle()->getFont()->getColor()->setARGB('9c5700');

    $conditionalGreen = new Conditional();
    $conditionalGreen->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalGreen->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
    $conditionalGreen->setStopIfTrue(true);
    $conditionalGreen->addCondition(1);
    $conditionalGreen->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalGreen->getStyle()->getFill()->getStartColor()->setARGB('c6efce'); // Зеленый
    $conditionalGreen->getStyle()->getFont()->getColor()->setARGB('006100');

    $conditionalRedPercent = new Conditional();
    $conditionalRedPercent->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalRedPercent->setOperatorType(Conditional::OPERATOR_BETWEEN);
    // $conditionalRedPercent->setStopIfTrue(true);
    $conditionalRedPercent->addCondition('0.0001');
    $conditionalRedPercent->addCondition('0.099');
    $conditionalRedPercent->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalRedPercent->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
    $conditionalRedPercent->getStyle()->getFont()->getColor()->setARGB('9c0006');

    $conditionalRedPercent2 = new Conditional();
    $conditionalRedPercent2->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalRedPercent2->setOperatorType(Conditional::OPERATOR_LESSTHAN);
    // $conditionalRedPercent2->setStopIfTrue(true);
    $conditionalRedPercent2->addCondition(0);
    $conditionalRedPercent2->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalRedPercent2->getStyle()->getFill()->getStartColor()->setARGB('FFFFFF');
    $conditionalRedPercent2->getStyle()->getFont()->getColor()->setARGB('FF0000');

    $conditionalYellowPercent = new Conditional();
    $conditionalYellowPercent->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalYellowPercent->setOperatorType(Conditional::OPERATOR_BETWEEN);
    // $conditionalYellowPercent->setStopIfTrue(true);
    $conditionalYellowPercent->addCondition('0.0991');
    $conditionalYellowPercent->addCondition('0.12');
    $conditionalYellowPercent->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalYellowPercent->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c');
    $conditionalYellowPercent->getStyle()->getFont()->getColor()->setARGB('9c5700');

    $conditionalGreenPercent = new Conditional();
    $conditionalGreenPercent->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalGreenPercent->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
    // $conditionalGreenPercent->setStopIfTrue(true);
    $conditionalGreenPercent->addCondition(0.12);
    $conditionalGreenPercent->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalGreenPercent->getStyle()->getFill()->getStartColor()->setARGB('c6efce'); // Зеленый
    $conditionalGreenPercent->getStyle()->getFont()->getColor()->setARGB('006100');

    // Заголовки недель/месяцев
    $column = 'B';
    $wcount = 1;
    $monthsColumns = [];
    foreach ($data as $monthnum => $weeks) {
      $startMonthColumn = $column;
      foreach ($weeks as $weekpair) {
        $sheet->getColumnDimension($column)->setOutlineLevel(1)->setVisible(false);
        $weekStart  = $weekpair->start;
        $weekEnd    = $weekpair->end;
        $sheet->setCellValue($column . '2', $weekStart->format('d.m.Y') . ' - ' . $weekEnd->format('d.m.Y'));
        $sheet->getStyle($column . '2')->getFont()->setBold(true);
        $sheet->getColumnDimension($column)->setWidth('11');
        $sheet->getStyle($column . '2')->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $index = Coordinate::columnIndexFromString($column);
        $column = Coordinate::stringFromColumnIndex($index + 1);
      }

      $monthOutlaysWithInvest = array_filter($outlays, function($item) use ($monthnum) {
          return (int)$item['month'] === (int)$monthnum && (int)$item['type'] === 0;
      });
      $monthOutlaysWithoutInvest = array_filter($outlays, function($item) use ($monthnum) {
          return (int)$item['month'] === (int)$monthnum && (int)$item['type'] === 1;
      });

      $monthOutlaysWithInvest = reset($monthOutlaysWithInvest);
      $monthOutlaysWithoutInvest = reset($monthOutlaysWithoutInvest);

      $sheet->setCellValue($startMonthColumn . '4', (($monthOutlaysWithInvest['value'] == 0 OR !$monthOutlaysWithInvest) ? 0 : $monthOutlaysWithInvest['value'] / 100) );
      $sheet->setCellValue($startMonthColumn . '5', (($monthOutlaysWithoutInvest['value'] == 0 OR !$monthOutlaysWithoutInvest) ? 0 : $monthOutlaysWithoutInvest['value'] / 100) );
      $sheet->mergeCells($startMonthColumn . '4:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - 1) . '4');
      $sheet->mergeCells($startMonthColumn . '5:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - 1) . '5');
      $sheet->getStyle($startMonthColumn . '4')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle($startMonthColumn . '4')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle($startMonthColumn . '4')->getFont()->setBold(true);
      $sheet->getStyle($startMonthColumn . '5')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
      $sheet->getStyle($startMonthColumn . '5')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle($startMonthColumn . '5')->getFont()->setBold(true);
      $index = Coordinate::columnIndexFromString($column);
      $sheet->getColumnDimension($column)->setCollapsed(true);
      $sheet->setCellValue($column . '2', self::getMonthsTitles($monthnum));
      $monthsColumns[] = $column;
      $sheet->getStyle($column . '2')->getFont()->setBold(true);
      $sheet->getStyle($column . '2')->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
      $column = Coordinate::stringFromColumnIndex($index + 1);
      $wcount++;
    }

    $sheet->setCellValue($column . '2', 'В среднем');
    $sheet->getStyle($column . '2')->getFont()->setBold(true);
    $sheet->getStyle($column . '2')->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getColumnDimension($column)->setWidth('13');
    $index = Coordinate::columnIndexFromString($column);
    $column = Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue($column . '2', 'Всего');
    $sheet->getStyle($column . '2')->getFont()->setBold(true);
    $sheet->getStyle($column . '2')->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getColumnDimension($column)->setWidth('13');
    $column = Coordinate::stringFromColumnIndex($index + 1);

    $stroke = 7;
    $viruchkaStrokesArr         = [];
    $sebesStrokesArr            = [];
    $incomeWithInvStrokesArr    = [];
    $incomeWithoutInvStrokesArr = [];
    foreach ($brands->rows as $brand) {
      if($brand->name == '' OR $brand->name == '-') { continue; }
      $column = 'A';
      $startStroke = $stroke;
      $sheet->setCellValue($column . $stroke, $brand->name);
      $sheet->getStyle($column . $stroke)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $stroke)->getFill()->getStartColor()->setRGB('fff2cc');
      $stroke++;
      $sheet->setCellValue($column . $stroke, 'Выручка от реализации');
      $sheet->getStyle($column . $stroke)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $stroke)->getFill()->getStartColor()->setRGB('92d050');
      $stroke++;
      $sheet->setCellValue($column . $stroke, 'Себестоимость общая');
      $stroke++;
      $sheet->setCellValue($column . $stroke, 'Рентабельность, % всего');
      $stroke++;
      $sheet->setCellValue($column . $stroke, 'Прибыль с инвест');
      $stroke++;
      $sheet->setCellValue($column . $stroke, 'Прибыль без инвест');
      $stroke++;
      $sheet->setCellValue($column . $stroke, '% с инвест');
      $stroke++;
      $sheet->setCellValue($column . $stroke, '% без инвест');
      $stroke++;

      // Заполнение данными
      $index = Coordinate::columnIndexFromString($column);
      $column = Coordinate::stringFromColumnIndex($index + 1);
      foreach ($data as $monthnum => $weeks) {
        $startMonthColumn = $column;
        foreach ($weeks as $wkey => $weekpair) {
          $viruchka   = 0;
          $sebesBasic = 0;
          if(isset($weekpair->profits[$brand->name])){
            $profits = $weekpair->profits[$brand->name];
            foreach ($profits as $profit) {
              $viruchka += $profit->sellSum-$profit->returnSum;
              $sebesBasic += $profit->sellCostSum-$profit->returnCostSum;
            }
          }

          $sheet->getStyle($column . $startStroke)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
          $sheet->getStyle($column . $startStroke)->getFill()->getStartColor()->setRGB('fff2cc');
          $sheet->setCellValue($column . $startStroke+1, (round($viruchka) / 100));
          if(!in_array($startStroke+1,$viruchkaStrokesArr)) : $viruchkaStrokesArr[] = $startStroke+1; endif;
          $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
          $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('0');
          $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('#,##0');
          $sheet->setCellValue($column . $startStroke+2, (round($sebesBasic) / 100));
          if(!in_array($startStroke+2,$sebesStrokesArr)) : $sebesStrokesArr[] = $startStroke+2; endif;
          $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
          $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode('0');
          $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode('#,##0');
          $sheet->setCellValue($column . $startStroke+3, '=IFERROR((' . $column . $startStroke+1 . '/' . $column . $startStroke+2 . ')-1,0)');
          $sheet->getStyle($column . $startStroke+3)->setConditionalStyles([$conditionalRed,$conditionalYellow,$conditionalGreen]);
          $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
          $sheet->setCellValue($column . $startStroke+4, '=ROUND(' . $column . $startStroke+1 . '-(' . $column . $startStroke+1 . '*' . '$'.$startMonthColumn.'$4' . '+' . $column . $startStroke+2 . '),0)');
          if(!in_array($startStroke+4,$incomeWithInvStrokesArr)) : $incomeWithInvStrokesArr[] = $startStroke+4; endif;
          $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
          $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('0');
          $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('#,##0');
          $sheet->setCellValue($column . $startStroke+5, '=' . $column . $startStroke+1 . '-(' . $column . $startStroke+1 . '*' . '$'.$startMonthColumn.'$5' . '+' . $column . $startStroke+2 . ')');
          if(!in_array($startStroke+5,$incomeWithoutInvStrokesArr)) : $incomeWithoutInvStrokesArr[] = $startStroke+5; endif;
          $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
          $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode('0');
          $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode('#,##0');
          $sheet->setCellValue($column . $startStroke+6, '=IFERROR(' . $column . $startStroke+4 . '/' . $column . $startStroke+1 . ',0)');
          $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
          $sheet->getStyle($column . $startStroke+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
          $sheet->setCellValue($column . $startStroke+7, '=IFERROR(' . $column . $startStroke+5 . '/' . $column . $startStroke+1 . ',0)');
          $sheet->getStyle($column . $startStroke+7)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
          $sheet->getStyle($column . $startStroke+7)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
          $index = Coordinate::columnIndexFromString($column);

          if($wkey == (count($weeks)-1)){
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getStyle($column . $startStroke)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($column . $startStroke)->getFill()->getStartColor()->setRGB('fff2cc');

            $sheet->setCellValue($column . $startStroke+1, '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - count($weeks)) . $startStroke+1 . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - 1) . $startStroke+1 . ')');
            $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle($column . $startStroke+1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($column . $startStroke+1)->getFill()->getStartColor()->setRGB('e2e2e2');
            $sheet->setCellValue($column . $startStroke+2, '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - count($weeks)) . $startStroke+2 . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - 1) . $startStroke+2 . ')');
            $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle($column . $startStroke+2)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($column . $startStroke+2)->getFill()->getStartColor()->setRGB('e2e2e2');
            $sheet->setCellValue($column . $startStroke+3, '=IFERROR((' . $column . $startStroke+1 . '/' . $column . $startStroke+2 . ')-1,0)');
            $sheet->getStyle($column . $startStroke+3)->setConditionalStyles([$conditionalRed,$conditionalYellow,$conditionalGreen]);
            $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle($column . $startStroke+3)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($column . $startStroke+3)->getFill()->getStartColor()->setRGB('e2e2e2');
            $sheet->setCellValue($column . $startStroke+4, '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - count($weeks)) . $startStroke+4 . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - 1) . $startStroke+4 . ')');
            $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle($column . $startStroke+4)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($column . $startStroke+4)->getFill()->getStartColor()->setRGB('e2e2e2');
            $sheet->setCellValue($column . $startStroke+5, '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - count($weeks)) . $startStroke+5 . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) - 1) . $startStroke+5 . ')');
            $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle($column . $startStroke+5)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($column . $startStroke+5)->getFill()->getStartColor()->setRGB('e2e2e2');
            $sheet->setCellValue($column . $startStroke+6, '=IFERROR(' . $column . $startStroke+4 . '/' . $column . $startStroke+1 . ',0)');
            $sheet->getStyle($column . $startStroke+6)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($column . $startStroke+6)->getFill()->getStartColor()->setRGB('e2e2e2');
            $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
            $sheet->getStyle($column . $startStroke+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->setCellValue($column . $startStroke+7, '=IFERROR(' . $column . $startStroke+5 . '/' . $column . $startStroke+1 . ',0)');
            $sheet->getStyle($column . $startStroke+7)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $sheet->getStyle($column . $startStroke+7)->getFill()->getStartColor()->setRGB('e2e2e2');
            $sheet->getStyle($column . $startStroke+7)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
            $sheet->getStyle($column . $startStroke+7)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

            $index = Coordinate::columnIndexFromString($column);
            $column = Coordinate::stringFromColumnIndex($index + 1);
          }
          else {
            $column = Coordinate::stringFromColumnIndex($index + 1);
          }
        }

      }

      // Среднее
      $averageIncome              = '=IFERROR((SUM(';
      $averageFirstPrice          = '=IFERROR((SUM(';
      $averageIncomeWithInvest    = '=IFERROR((SUM(';
      $averageIncomeWithNoInvest  = '=IFERROR((SUM(';
      foreach ($monthsColumns as $mc) {
        $averageIncome              .= 'IF((' . $mc . $startStroke+1 . '<>0)*(' . $mc . $startStroke+1 . '<>""),' . $mc . $startStroke+1 . ',0),';
        $averageFirstPrice          .= 'IF((' . $mc . $startStroke+2 . '<>0)*(' . $mc . $startStroke+2 . '<>""),' . $mc . $startStroke+2 . ',0),';
        $averageIncomeWithInvest    .= 'IF((' . $mc . $startStroke+4 . '<>0)*(' . $mc . $startStroke+4 . '<>""),' . $mc . $startStroke+4 . ',0),';
        $averageIncomeWithNoInvest  .= 'IF((' . $mc . $startStroke+5 . '<>0)*(' . $mc . $startStroke+5 . '<>""),' . $mc . $startStroke+5 . ',0),';
      }
      $averageIncome = rtrim($averageIncome,',');
      $averageIncome .= ')) / SUM(';
      $averageFirstPrice = rtrim($averageFirstPrice,',');
      $averageFirstPrice .= ')) / SUM(';
      $averageIncomeWithInvest = rtrim($averageIncomeWithInvest,',');
      $averageIncomeWithInvest .= ')) / SUM(';
      $averageIncomeWithNoInvest = rtrim($averageIncomeWithNoInvest,',');
      $averageIncomeWithNoInvest .= ')) / SUM(';

      foreach ($monthsColumns as $mc) {
        $averageIncome              .= '(' . $mc . $startStroke+1 . '<>0)*(' . $mc . $startStroke+1 . '<>"")+';
        $averageFirstPrice          .= '(' . $mc . $startStroke+2 . '<>0)*(' . $mc . $startStroke+2 . '<>"")+';
        $averageIncomeWithInvest    .= '(' . $mc . $startStroke+4 . '<>0)*(' . $mc . $startStroke+4 . '<>"")+';
        $averageIncomeWithNoInvest  .= '(' . $mc . $startStroke+5 . '<>0)*(' . $mc . $startStroke+5 . '<>"")+';
      }
      $averageIncome = rtrim($averageIncome,'+');
      $averageIncome .= '),0)';
      $averageFirstPrice = rtrim($averageFirstPrice,'+');
      $averageFirstPrice .= '),0)';
      $averageIncomeWithInvest = rtrim($averageIncomeWithInvest,'+');
      $averageIncomeWithInvest .= '),0)';
      $averageIncomeWithNoInvest = rtrim($averageIncomeWithNoInvest,'+');
      $averageIncomeWithNoInvest .= '),0)';

      $sheet->getStyle($column . $startStroke)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke)->getFill()->getStartColor()->setRGB('fff2cc');
      $sheet->setCellValue($column . $startStroke+1, $averageIncome);
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->getStyle($column . $startStroke+1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke+1)->getFill()->getStartColor()->setRGB('e2e2e2');
      $sheet->setCellValue($column . $startStroke+2, $averageFirstPrice);
      $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->getStyle($column . $startStroke+2)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke+2)->getFill()->getStartColor()->setRGB('e2e2e2');
      $sheet->setCellValue($column . $startStroke+3, '=IFERROR((' . $column . $startStroke+1 . '/' . $column . $startStroke+2 . ')-1,0)');
      $sheet->getStyle($column . $startStroke+3)->setConditionalStyles([$conditionalRed,$conditionalYellow,$conditionalGreen]);
      $sheet->setCellValue($column . $startStroke+4, $averageIncomeWithInvest);
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->getStyle($column . $startStroke+4)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke+4)->getFill()->getStartColor()->setRGB('e2e2e2');
      $sheet->setCellValue($column . $startStroke+5, $averageIncomeWithNoInvest);
      $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->getStyle($column . $startStroke+5)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke+5)->getFill()->getStartColor()->setRGB('e2e2e2');
      $sheet->setCellValue($column . $startStroke+6, '=IFERROR((' . $column . $startStroke+4 . '/' . $column . $startStroke+1 . '),0)');
      $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
      $sheet->setCellValue($column . $startStroke+7, '=IFERROR((' . $column . $startStroke+5 . '/' . $column . $startStroke+1 . '),0)');
      $sheet->getStyle($column . $startStroke+7)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
      $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle($column . $startStroke+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle($column . $startStroke+7)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

      // Всего
      $index = Coordinate::columnIndexFromString($column);
      $column = Coordinate::stringFromColumnIndex($index + 1);

      $row1    = $startStroke + 1;
      $row2    = $startStroke + 2;
      $row3    = $startStroke + 4;
      $row4    = $startStroke + 5;
      $cells1  = array_map(function($col) use ($row1) { return $col . $row1; }, $monthsColumns);
      $cells2  = array_map(function($col) use ($row2) { return $col . $row2; }, $monthsColumns);
      $cells3  = array_map(function($col) use ($row3) { return $col . $row3; }, $monthsColumns);
      $cells4  = array_map(function($col) use ($row4) { return $col . $row4; }, $monthsColumns);

      $allIncome = '=IFERROR(SUM(' . implode(',' , $cells1) . '),0)';
      $allFirstPrice = '=IFERROR(SUM(' . implode(',' , $cells2) . '),0)';
      $allIncomeWithInvest = '=IFERROR(SUM(' . implode(',' , $cells3) . '),0)';
      $allIncomeWithNoInvest = '=IFERROR(SUM(' . implode(',' , $cells4) . '),0)';

      $sheet->getStyle($column . $startStroke)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke)->getFill()->getStartColor()->setRGB('fff2cc');
      $sheet->setCellValue($column . $startStroke+1, $allIncome);
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->getStyle($column . $startStroke+1)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke+1)->getFill()->getStartColor()->setRGB('e2e2e2');
      $sheet->setCellValue($column . $startStroke+2, $allFirstPrice);
      $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->getStyle($column . $startStroke+2)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke+2)->getFill()->getStartColor()->setRGB('e2e2e2');
      $sheet->setCellValue($column . $startStroke+3, '=IFERROR((' . $column . $startStroke+1 . '/' . $column . $startStroke+2 . ')-1,0)');
      $sheet->getStyle($column . $startStroke+3)->setConditionalStyles([$conditionalRed,$conditionalYellow,$conditionalGreen]);
      $sheet->setCellValue($column . $startStroke+4, $allIncomeWithInvest);
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->getStyle($column . $startStroke+4)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke+4)->getFill()->getStartColor()->setRGB('e2e2e2');
      $sheet->setCellValue($column . $startStroke+5, $allIncomeWithNoInvest);
      $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->getStyle($column . $startStroke+5)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $sheet->getStyle($column . $startStroke+5)->getFill()->getStartColor()->setRGB('e2e2e2');
      $sheet->setCellValue($column . $startStroke+6, '=IFERROR((' . $column . $startStroke+4 . '/' . $column . $startStroke+1 . '),0)');
      $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
      $sheet->setCellValue($column . $startStroke+7, '=IFERROR((' . $column . $startStroke+5 . '/' . $column . $startStroke+1 . '),0)');
      $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
      $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle($column . $startStroke+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle($column . $startStroke+7)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    }

    // Всего Вообще всего
    $stroke++;
    $startStroke = $stroke+1;
    $column = 'A';
    $sheet->setCellValue($column . $stroke, 'Всего');
    $sheet->getStyle($column . $stroke)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet->getStyle($column . $stroke)->getFill()->getStartColor()->setRGB('fff2cc');
    $stroke++;
    $sheet->setCellValue($column . $stroke, 'Выручка от реализации');
    $sheet->getStyle($column . $stroke)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $sheet->getStyle($column . $stroke)->getFill()->getStartColor()->setRGB('92d050');
    $stroke++;
    $sheet->setCellValue($column . $stroke, 'Себестоимость общая');
    $stroke++;
    $sheet->setCellValue($column . $stroke, 'Рентабельность, % всего');
    $stroke++;
    $sheet->setCellValue($column . $stroke, 'Прибыль с инвест');
    $stroke++;
    $sheet->setCellValue($column . $stroke, 'Прибыль без инвест');
    $stroke++;
    $sheet->setCellValue($column . $stroke, '% с инвест');
    $stroke++;
    $sheet->setCellValue($column . $stroke, '% без инвест');

    $index = Coordinate::columnIndexFromString($column);
    $column = Coordinate::stringFromColumnIndex($index + 1);

    foreach ($data as $monthnum => $weeks) {
      foreach ($weeks as $wkey => $weekpair) {
        $sheet->setCellValue($column . ($startStroke-1), '');
        $sheet->getStyle($column . ($startStroke-1))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle($column . ($startStroke-1))->getFill()->getStartColor()->setRGB('fff2cc');

        $sheet->setCellValue($column . $startStroke, '=SUM(' . $column . implode(','.$column,$viruchkaStrokesArr) . ')');
        $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($column . $startStroke+1, '=SUM(' . $column . implode(','.$column,$sebesStrokesArr) . ')');
        $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($column . $startStroke+2, '=IFERROR((' . $column . $startStroke . '/' . $column . $startStroke+1 . ')-1,0)');
        $sheet->getStyle($column . $startStroke+2)->setConditionalStyles([$conditionalRed,$conditionalYellow,$conditionalGreen]);
        $sheet->setCellValue($column . $startStroke+3, '=SUM(' . $column . implode(','.$column,$incomeWithInvStrokesArr) . ')');
        $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($column . $startStroke+4, '=SUM(' . $column . implode(','.$column,$incomeWithoutInvStrokesArr) . ')');
        $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($column . $startStroke+5, '=IFERROR((' . $column . $startStroke+3 . '/' . $column . $startStroke . '),0)');
        $sheet->getStyle($column . $startStroke+5)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
        $sheet->setCellValue($column . $startStroke+6, '=IFERROR((' . $column . $startStroke+4 . '/' . $column . $startStroke . '),0)');
        $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
        $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $sheet->getStyle($column . $startStroke+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $index = Coordinate::columnIndexFromString($column);
        $column = Coordinate::stringFromColumnIndex($index + 1);
      }
      $sheet->setCellValue($column . $startStroke, '=SUM(' . $column . implode(','.$column,$viruchkaStrokesArr) . ')');
      $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->setCellValue($column . $startStroke+1, '=SUM(' . $column . implode(','.$column,$sebesStrokesArr) . ')');
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->setCellValue($column . $startStroke+2, '=IFERROR((' . $column . $startStroke . '/' . $column . $startStroke+1 . ')-1,0)');
      $sheet->getStyle($column . $startStroke+2)->setConditionalStyles([$conditionalRed,$conditionalYellow,$conditionalGreen]);
      $sheet->setCellValue($column . $startStroke+3, '=SUM(' . $column . implode(','.$column,$incomeWithInvStrokesArr) . ')');
      $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->setCellValue($column . $startStroke+4, '=SUM(' . $column . implode(','.$column,$incomeWithoutInvStrokesArr) . ')');
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('#,##0');
      $sheet->setCellValue($column . $startStroke+5, '=IFERROR((' . $column . $startStroke+3 . '/' . $column . $startStroke . '),0)');
      $sheet->getStyle($column . $startStroke+5)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
      $sheet->setCellValue($column . $startStroke+6, '=IFERROR((' . $column . $startStroke+4 . '/' . $column . $startStroke . '),0)');
      $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
      $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle($column . $startStroke+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $index = Coordinate::columnIndexFromString($column);
      $column = Coordinate::stringFromColumnIndex($index + 1);
    }

    // Всего Вообще всего Среднее
    $averageIncome              = '=IFERROR((SUM(';
    $averageFirstPrice          = '=IFERROR((SUM(';
    $averageIncomeWithInvest    = '=IFERROR((SUM(';
    $averageIncomeWithNoInvest  = '=IFERROR((SUM(';
    foreach ($monthsColumns as $mc) {
      $averageIncome              .= 'IF((' . $mc . $startStroke . '<>0)*(' . $mc . $startStroke . '<>""),' . $mc . $startStroke . ',0),';
      $averageFirstPrice          .= 'IF((' . $mc . $startStroke+1 . '<>0)*(' . $mc . $startStroke+1 . '<>""),' . $mc . $startStroke+1 . ',0),';
      $averageIncomeWithInvest    .= 'IF((' . $mc . $startStroke+3 . '<>0)*(' . $mc . $startStroke+3 . '<>""),' . $mc . $startStroke+3 . ',0),';
      $averageIncomeWithNoInvest  .= 'IF((' . $mc . $startStroke+4 . '<>0)*(' . $mc . $startStroke+4 . '<>""),' . $mc . $startStroke+4 . ',0),';
    }
    $averageIncome = rtrim($averageIncome,',');
    $averageIncome .= ')) / SUM(';
    $averageFirstPrice = rtrim($averageFirstPrice,',');
    $averageFirstPrice .= ')) / SUM(';
    $averageIncomeWithInvest = rtrim($averageIncomeWithInvest,',');
    $averageIncomeWithInvest .= ')) / SUM(';
    $averageIncomeWithNoInvest = rtrim($averageIncomeWithNoInvest,',');
    $averageIncomeWithNoInvest .= ')) / SUM(';

    foreach ($monthsColumns as $mc) {
      $averageIncome              .= '(' . $mc . $startStroke . '<>0)*(' . $mc . $startStroke . '<>"")+';
      $averageFirstPrice          .= '(' . $mc . $startStroke+1 . '<>0)*(' . $mc . $startStroke+1 . '<>"")+';
      $averageIncomeWithInvest    .= '(' . $mc . $startStroke+3 . '<>0)*(' . $mc . $startStroke+3 . '<>"")+';
      $averageIncomeWithNoInvest  .= '(' . $mc . $startStroke+4 . '<>0)*(' . $mc . $startStroke+4 . '<>"")+';
    }
    $averageIncome = rtrim($averageIncome,'+');
    $averageIncome .= '),0)';
    $averageFirstPrice = rtrim($averageFirstPrice,'+');
    $averageFirstPrice .= '),0)';
    $averageIncomeWithInvest = rtrim($averageIncomeWithInvest,'+');
    $averageIncomeWithInvest .= '),0)';
    $averageIncomeWithNoInvest = rtrim($averageIncomeWithNoInvest,'+');
    $averageIncomeWithNoInvest .= '),0)';

    $sheet->setCellValue($column . $startStroke, $averageIncome);
    $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($column . $startStroke+1, $averageFirstPrice);
    $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($column . $startStroke+2, '=IFERROR((' . $column . $startStroke . '/' . $column . $startStroke+1 . ')-1,0)');
    $sheet->getStyle($column . $startStroke+2)->setConditionalStyles([$conditionalRed,$conditionalYellow,$conditionalGreen]);
    $sheet->setCellValue($column . $startStroke+3, $averageIncomeWithInvest);
    $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($column . $startStroke+4, $averageIncomeWithNoInvest);
    $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($column . $startStroke+5, '=IFERROR((' . $column . $startStroke+3 . '/' . $column . $startStroke . '),0)');
    $sheet->getStyle($column . $startStroke+5)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
    $sheet->setCellValue($column . $startStroke+6, '=IFERROR((' . $column . $startStroke+4 . '/' . $column . $startStroke . '),0)');
    $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
    $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->getStyle($column . $startStroke+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $index = Coordinate::columnIndexFromString($column);
    $column = Coordinate::stringFromColumnIndex($index + 1);

    // Всего Вообще всего ВСЕГО
    $row1    = $startStroke;
    $row2    = $startStroke + 1;
    $row3    = $startStroke + 3;
    $row4    = $startStroke + 4;
    $cells1  = array_map(function($col) use ($row1) { return $col . $row1; }, $monthsColumns);
    $cells2  = array_map(function($col) use ($row2) { return $col . $row2; }, $monthsColumns);
    $cells3  = array_map(function($col) use ($row3) { return $col . $row3; }, $monthsColumns);
    $cells4  = array_map(function($col) use ($row4) { return $col . $row4; }, $monthsColumns);

    $allIncome = '=IFERROR(SUM(' . implode(',' , $cells1) . '),0)';
    $allFirstPrice = '=IFERROR(SUM(' . implode(',' , $cells2) . '),0)';
    $allIncomeWithInvest = '=IFERROR(SUM(' . implode(',' , $cells3) . '),0)';
    $allIncomeWithNoInvest = '=IFERROR(SUM(' . implode(',' , $cells4) . '),0)';

    $sheet->setCellValue($column . $startStroke, $allIncome);
    $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle($column . $startStroke)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($column . $startStroke+1, $allFirstPrice);
    $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle($column . $startStroke+1)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($column . $startStroke+2, '=IFERROR((' . $column . $startStroke . '/' . $column . $startStroke+1 . ')-1,0)');
    $sheet->getStyle($column . $startStroke+2)->setConditionalStyles([$conditionalRed,$conditionalYellow,$conditionalGreen]);
    $sheet->setCellValue($column . $startStroke+3, $allIncomeWithInvest);
    $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle($column . $startStroke+3)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($column . $startStroke+4, $allIncomeWithNoInvest);
    $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle($column . $startStroke+4)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($column . $startStroke+5, '=IFERROR((' . $column . $startStroke+3 . '/' . $column . $startStroke . '),0)');
    $sheet->getStyle($column . $startStroke+5)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
    $sheet->setCellValue($column . $startStroke+6, '=IFERROR((' . $column . $startStroke+4 . '/' . $column . $startStroke . '),0)');
    $sheet->getStyle($column . $startStroke+6)->setConditionalStyles([$conditionalRedPercent,$conditionalRedPercent2,$conditionalYellowPercent,$conditionalGreenPercent]);
    $sheet->getStyle($column . $startStroke+2)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->getStyle($column . $startStroke+5)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->getStyle($column . $startStroke+6)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);


    $sheet->getStyle('A7:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column)) . $stroke)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN, // или BORDER_MEDIUM, BORDER_DASHED и т.д.
                'color' => ['argb' => 'FF000000'], // черный цвет (ARGB)
            ],
        ],
    ]);

    $sheet->freezePane('B4');
    $sheet->getColumnDimension('A')->setWidth('28');

    $writer = new Xlsx($spreadsheet);
    $writer->save(__DIR__ . '/../web/tmpDocs/' . $fileName);
    $xlsData = ob_get_contents();
    return (object)array('file' => $fileName);
  }

  private function generateMarketingReport($data,$cpcdata)
  {
    setlocale(LC_TIME, 'ru_RU.UTF-8');

    usort($data, function ($a, $b) {
      return $a->month <=> $b->month;
    });

    $mreport        = new ReportMarketingTable();
    $fileName       = 'Маркетинг_' . date('d.m.Y_H.i.s') . '.xlsx';
    $spreadsheet    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet          = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Каналы');

    // Стили для строк
    $rowsStyles = self::collectRowsStylesMarketingReport();

    // Стиль для отрицательных значений
    $conditionalMinus = new Conditional();
    $conditionalMinus->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalMinus->setOperatorType(Conditional::OPERATOR_LESSTHAN);
    $conditionalMinus->setStopIfTrue(true);
    $conditionalMinus->addCondition(0);
    $conditionalMinus->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalMinus->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
    $conditionalMinus->getStyle()->getFont()->getColor()->setARGB('9c0006');

    // Первая колонка с заголовками
    $firstColumn = self::columnFirstMarketingReport();
    $totalColumns = self::countTotalColumnsMarketingReport($data);

    foreach ($firstColumn as [$cell, $value, $style]) {
      $sheet->setCellValue($cell, $value);
      if(!empty($style)){
        preg_match('/([A-Z]+)(\d+)/i', $cell, $matches);
        $column = $matches[1];
        $row = $matches[2];
        $endColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($column) + $totalColumns);
        $sheet->getStyle($column . $row . ':' . $endColumn . $row)->applyFromArray($rowsStyles->{$style});
      }
    }
    $sheet->getColumnDimension('A')->setWidth('60');

    $dataColumn = 'B';

    // Недели и месяцы (заголовки)
    $monthCounter = 1;
    foreach ($data as $monthdata) {
      $monthDate = new \DateTime($monthdata->year.'-'.$monthdata->month.'-01');
      $weeks = $mreport->getWeeksFromFirstWednesday($monthDate);
      $monthNum = false;

      $thisMonthSortedData = self::getMonthSortedDataMarketingReport($monthdata);

      foreach ($weeks as $wkey => $week) {
        $weekStart = new \DateTime($week['start']);
        $weekEnd = new \DateTime($week['end']);
        $monthNum = $week['month'];
        $sheet->setCellValue($dataColumn.'1', $weekStart->format('d.m.Y') . '-' . $weekEnd->format('d.m.Y'));

        // Organic
              $sheet->setCellValue($dataColumn.'277', $thisMonthSortedData->organic->data_2_10[$wkey]);
              $sheet->setCellValue($dataColumn.'278', $thisMonthSortedData->organic->data_2_11[$wkey]);
              $sheet->setCellValue($dataColumn.'279', $thisMonthSortedData->organic->data_2_12[$wkey]);
              $sheet->setCellValue($dataColumn.'290', $thisMonthSortedData->organic->data_2_3[$wkey]);
              $sheet->setCellValue($dataColumn.'291', $thisMonthSortedData->organic->data_2_4[$wkey]);
              $sheet->setCellValue($dataColumn.'292', $thisMonthSortedData->organic->data_2_5[$wkey]);
              $sheet->setCellValue($dataColumn.'294', $thisMonthSortedData->organic->data_2_6[$wkey]);
              $sheet->setCellValue($dataColumn.'295', '=IFERROR(ROUND(' . $dataColumn.'291' . ' * (' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . ' / ' . $thisMonthSortedData->whatsappsales->data_8_1[$wkey] . '),2), 0)');
              $sheet->setCellValue($dataColumn.'296', $thisMonthSortedData->organic->data_2_7[$wkey]);
              $sheet->setCellValue($dataColumn.'298', '=' . $thisMonthSortedData->organic->data_2_8[$wkey] . ' / ' . $thisMonthSortedData->basic->data_1_2);
              $sheet->setCellValue($dataColumn.'299', '=IFERROR(ROUND(' . $dataColumn.'295' . ' * ( (' . $thisMonthSortedData->whatsappsales->data_8_4[$wkey] / $thisMonthSortedData->basic->data_1_2 . ') / ' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . ' ),2), 0)');
              $sheet->setCellValue($dataColumn.'300', '=ROUND(' . $thisMonthSortedData->organic->data_2_9[$wkey] / $thisMonthSortedData->basic->data_1_2 . ',2)');
              $sheet->setCellValue($dataColumn.'289', '=SUM(' . $dataColumn.'290:' . $dataColumn.'292)');
              $sheet->setCellValue($dataColumn.'293', '=SUM(' . $dataColumn.'294:' . $dataColumn.'296)');
              $sheet->setCellValue($dataColumn.'297', '=SUM(' . $dataColumn.'298:' . $dataColumn.'300)');
              $sheet->setCellValue($dataColumn.'301', '=IFERROR(ROUND(' . $dataColumn . '297 / ' . $dataColumn . '293,2),0)');
              $sheet->setCellValue($dataColumn.'302', '=IFERROR(ROUND(' . $dataColumn . '298 / ' . $dataColumn . '294,2),0)');
              $sheet->setCellValue($dataColumn.'303', '=IFERROR(ROUND(' . $dataColumn . '299 / ' . $dataColumn . '295,2),0)');
              $sheet->setCellValue($dataColumn.'304', '=IFERROR(ROUND(' . $dataColumn . '300 / ' . $dataColumn . '296,2),0)');
              $sheet->setCellValue($dataColumn.'305', '=IFERROR(ROUND(' . $dataColumn . '278 / ' . $dataColumn . '277,4),0)');
              $sheet->setCellValue($dataColumn.'306', '=IFERROR(ROUND(' . $dataColumn . '293 / ' . $dataColumn . '278,4),0)');
              $sheet->setCellValue($dataColumn.'307', '=IFERROR(ROUND(' . $dataColumn . '293 / ' . $dataColumn . '277,4),0)');
              $sheet->setCellValue($dataColumn.'308', '=IFERROR(ROUND(' . $dataColumn . '290 / ' . $dataColumn . '278,4),0)');
              $sheet->setCellValue($dataColumn.'309', '=IFERROR(ROUND(' . $dataColumn . '294 / ' . $dataColumn . '290,4),0)');
              $sheet->setCellValue($dataColumn.'310', '=IFERROR(ROUND(' . $dataColumn . '294 / ' . $dataColumn . '278,4),0)');
              $sheet->setCellValue($dataColumn.'311', '=IFERROR(ROUND(' . $dataColumn . '294 / ' . $dataColumn . '277,4),0)');
              $sheet->setCellValue($dataColumn.'312', '=IFERROR(ROUND(' . $dataColumn . '291 / ' . $dataColumn . '278,4),0)');
              $sheet->setCellValue($dataColumn.'313', '=IFERROR(ROUND(' . $dataColumn . '295 / ' . $dataColumn . '291,4),0)');
              $sheet->setCellValue($dataColumn.'314', '=IFERROR(ROUND(' . $dataColumn . '295 / ' . $dataColumn . '278,4),0)');
              $sheet->setCellValue($dataColumn.'315', '=IFERROR(ROUND(' . $dataColumn . '295 / ' . $dataColumn . '277,4),0)');
              $sheet->setCellValue($dataColumn.'316', '=IFERROR(ROUND(' . $dataColumn . '292 / ' . $dataColumn . '278,4),0)');
              $sheet->setCellValue($dataColumn.'317', '=IFERROR(ROUND(' . $dataColumn . '296 / ' . $dataColumn . '292,4),0)');
              $sheet->setCellValue($dataColumn.'318', '=IFERROR(ROUND(' . $dataColumn . '296 / ' . $dataColumn . '278,4),0)');
              $sheet->setCellValue($dataColumn.'319', '=IFERROR(ROUND(' . $dataColumn . '296 / ' . $dataColumn . '277,4),0)');
              $sheet->setCellValue($dataColumn.'280', '=IFERROR(ROUND( (' . $dataColumn . '278 - ' . $dataColumn . '289) / ' . $dataColumn . '278,4),0)');
              $sheet->setCellValue($dataColumn.'281', '=IFERROR(ROUND(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + (count($weeks) - $wkey)) . '281 / ' . count($weeks) . ',2),0)');
              $sheet->setCellValue($dataColumn.'282', '=IFERROR(ROUND(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + (count($weeks) - $wkey)) . '282 / ' . count($weeks) . ',2),0)');
              $sheet->setCellValue($dataColumn.'283', '=IFERROR(ROUND( (' . $dataColumn.'281+' . $dataColumn.'282' . ') / ' . $dataColumn.'278' . ',2),0)');
              $sheet->setCellValue($dataColumn.'284', '=IFERROR(ROUND( (' . $dataColumn.'281+' . $dataColumn.'282' . ') / ' . $dataColumn.'293' . ',2),0)');
              $sheet->setCellValue($dataColumn.'285', '=ROUND(' . $dataColumn.'297 - ( ' . $dataColumn.'297 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) - ( ' . $dataColumn.'297 - ( ' . $dataColumn.'297 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) ) * ' . $thisMonthSortedData->basic->data_1_1 . ' - ' . $dataColumn.'282 - ' . $dataColumn.'281,2)');
              $sheet->setCellValue($dataColumn.'286', '=IFERROR(ROUND(' . $dataColumn.'285 / ' . $dataColumn.'297,4),0)');
              $sheet->setCellValue($dataColumn.'287', '=IFERROR(ROUND( ( ' . $dataColumn.'297 - ' . $dataColumn.'282 - ' . $dataColumn.'281 ) / ( ' . $dataColumn.'282 + ' . $dataColumn.'281 ), 4 ) ,0 )');
              $sheet->setCellValue($dataColumn.'288', '=IFERROR(ROUND( ( ' . $dataColumn.'297 - ' . $dataColumn.'281 ) / ' . $dataColumn.'281, 4 ),0 )');

        // CPC
        $cpcColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);
        $sheet->setCellValue($dataColumn.'321', '=CPC!' . $cpcColumn . '2');
        $sheet->setCellValue($dataColumn.'322', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) + 2 ) ) );
        $sheet->setCellValue($dataColumn.'323', '=IFERROR( ROUND( ' . $dataColumn . '326 / ' . $dataColumn . '322, 2), 0)' );
        $sheet->setCellValue($dataColumn.'324', '=IFERROR( ROUND( (' . $dataColumn . '322 - ' . $dataColumn . '334) / ' . $dataColumn . '322, 2), 0)' );

        $sheet->setCellValue($dataColumn.'325', '=IFERROR( ROUND(' . ($thisMonthSortedData->cpc->data_3_1 / $thisMonthSortedData->basic->data_1_2) / count($weeks)  . ', 2), 0)' );
        $sheet->setCellValue($dataColumn.'326', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 2 ) + 4 ) );
        $sheet->setCellValue($dataColumn.'327', '=IFERROR( ROUND( ' . $dataColumn . '326 / ' . $dataColumn . '334, 2), 0)' );
        $sheet->setCellValue($dataColumn.'328', '=IFERROR( ROUND( ( ' . $dataColumn . '325 + ' . $dataColumn . '326 ) / ' . $dataColumn . '322, 2), 0)' );
        $sheet->setCellValue($dataColumn.'329', '=IFERROR( ROUND( ( ' . $dataColumn . '325 + ' . $dataColumn . '326 ) / ' . $dataColumn . '338, 2), 0)' );
        $sheet->setCellValue($dataColumn.'330', '=' . $dataColumn . '342 - ( ' . $dataColumn . '342 / (1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) - ( ' . $dataColumn . '342 - ( ' . $dataColumn . '342 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) ) * ' . $thisMonthSortedData->basic->data_1_1 . ' - ' . $dataColumn . '326 - ' . $dataColumn . '325' );
        $sheet->setCellValue($dataColumn.'331', '=IFERROR( ROUND( ' . $dataColumn . '330 / ' . $dataColumn . '342, 4), 0)' );
        $sheet->setCellValue($dataColumn.'332', '=IFERROR( ROUND( ( ' . $dataColumn . '342 - ' . $dataColumn . '326 - ' . $dataColumn . '325 ) / ( ' . $dataColumn . '325 + ' . $dataColumn . '326), 4), 0)' );
        $sheet->setCellValue($dataColumn.'333', '=IFERROR( ROUND( ( ' . $dataColumn . '342 - ' . $dataColumn . '326 ) / ' . $dataColumn . '326, 4), 0)' );
        $sheet->setCellValue($dataColumn.'334', '=SUM(' . $dataColumn . '335:' . $dataColumn . '337)' );
        $sheet->setCellValue($dataColumn.'335', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 3 ) + 6 ) );
        $sheet->setCellValue($dataColumn.'336', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 4 ) + 8 ) );
        $sheet->setCellValue($dataColumn.'337', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 5 ) + 10 ) );
        $sheet->setCellValue($dataColumn.'338', '=SUM(' . $dataColumn . '339:' . $dataColumn . '341)' );
        $sheet->setCellValue($dataColumn.'339', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 7 ) + 14 ) );
        $sheet->setCellValue($dataColumn.'340', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 9 ) + 18 ) );
        $sheet->setCellValue($dataColumn.'341', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 8 ) + 16 ) );
        $sheet->setCellValue($dataColumn.'342', '=SUM(' . $dataColumn . '343:' . $dataColumn . '345)' );
        $sheet->setCellValue($dataColumn.'343', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 11 ) + 22 ) );
        $sheet->setCellValue($dataColumn.'344', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 13 ) + 26 ) );
        $sheet->setCellValue($dataColumn.'345', '=CPC!' . $cpcColumn . ( ( 2 + count($cpcdata) * 12 ) + 24 ) );
        $sheet->setCellValue($dataColumn.'346', '=IFERROR( ROUND( ' . $dataColumn . '342 / ' . $dataColumn . '338, 2), 0 )' );
        $sheet->setCellValue($dataColumn.'347', '=IFERROR( ROUND( ' . $dataColumn . '343 / ' . $dataColumn . '339, 2), 0 )' );
        $sheet->setCellValue($dataColumn.'348', '=IFERROR( ROUND( ' . $dataColumn . '344 / ' . $dataColumn . '340, 2), 0 )' );
        $sheet->setCellValue($dataColumn.'349', '=IFERROR( ROUND( ' . $dataColumn . '345 / ' . $dataColumn . '341, 2), 0 )' );
        $sheet->setCellValue($dataColumn.'350', '=IFERROR( ROUND( ' . $dataColumn . '322 / ' . $dataColumn . '321, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'351', '=IFERROR( ROUND( ' . $dataColumn . '338 / ' . $dataColumn . '322, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'352', '=IFERROR( ROUND( ' . $dataColumn . '338 / ' . $dataColumn . '321, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'353', '=IFERROR( ROUND( ' . $dataColumn . '335 / ' . $dataColumn . '322, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'354', '=IFERROR( ROUND( ' . $dataColumn . '339 / ' . $dataColumn . '335, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'355', '=IFERROR( ROUND( ' . $dataColumn . '339 / ' . $dataColumn . '322, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'356', '=IFERROR( ROUND( ' . $dataColumn . '339 / ' . $dataColumn . '321, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'357', '=IFERROR( ROUND( ' . $dataColumn . '336 / ' . $dataColumn . '322, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'358', '=IFERROR( ROUND( ' . $dataColumn . '340 / ' . $dataColumn . '336, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'359', '=IFERROR( ROUND( ' . $dataColumn . '340 / ' . $dataColumn . '322, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'360', '=IFERROR( ROUND( ' . $dataColumn . '340 / ' . $dataColumn . '321, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'361', '=IFERROR( ROUND( ' . $dataColumn . '337 / ' . $dataColumn . '322, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'362', '=IFERROR( ROUND( ' . $dataColumn . '341 / ' . $dataColumn . '337, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'363', '=IFERROR( ROUND( ' . $dataColumn . '341 / ' . $dataColumn . '322, 4), 0 )' );
        $sheet->setCellValue($dataColumn.'364', '=IFERROR( ROUND( ' . $dataColumn . '341 / ' . $dataColumn . '321, 4), 0 )' );


        // Instagram / Facebook
              $sheet->setCellValue($dataColumn.'366', $thisMonthSortedData->instagramfacebook->data_4_1[$wkey]);
              $sheet->setCellValue($dataColumn.'367', $thisMonthSortedData->instagramfacebook->data_4_2[$wkey]);
              $sheet->setCellValue($dataColumn.'368', '=IFERROR(ROUND(' . $dataColumn.'371 / ' . $dataColumn.'367,2), 0)');
              $sheet->setCellValue($dataColumn.'369', '=IFERROR(ROUND( ( ' . $dataColumn.'367' . ' - ' . $dataColumn.'378 ) / ' . $dataColumn.'367,4),0)');
              $sheet->setCellValue($dataColumn.'370', '=IFERROR(ROUND(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + (count($weeks) - $wkey)) . '370 / ' . count($weeks) . ',2),0)');
              $sheet->setCellValue($dataColumn.'371', $thisMonthSortedData->instagramfacebook->data_4_4[$wkey]);
              $sheet->setCellValue($dataColumn.'372', '=IFERROR(ROUND( ( ' . $dataColumn.'370 + ' . $dataColumn.'371 ) / ' . $dataColumn.'367,2),0)');
              $sheet->setCellValue($dataColumn.'373', '=IFERROR(ROUND( ( ' . $dataColumn.'370 + ' . $dataColumn.'371 ) / ' . $dataColumn.'382,2),0)');
              $sheet->setCellValue($dataColumn.'374', '=ROUND( ' . $dataColumn.'386 - (' . $dataColumn.'386 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) - ( ' . $dataColumn.'386 - ( ' . $dataColumn.'386 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) ) * ' . $thisMonthSortedData->basic->data_1_1 . ' - ' . $dataColumn.'370 - ' . $dataColumn.'371, 2)');
              $sheet->setCellValue($dataColumn.'375', '=IFERROR(ROUND( ' . $dataColumn.'374 / ' . $dataColumn.'386, 4 ) ,0)');
              $sheet->setCellValue($dataColumn.'376', '=IFERROR(ROUND( ( ' . $dataColumn.'386 - ' . $dataColumn.'371 - ' . $dataColumn.'370 ) / ( ' . $dataColumn.'370 + ' . $dataColumn.'371 ), 4),0)');
              $sheet->setCellValue($dataColumn.'377', '=IFERROR(ROUND( ( ' . $dataColumn.'386 - ' . $dataColumn.'371 ) / ' . $dataColumn.'370, 4 ), 0)');
              $sheet->setCellValue($dataColumn.'378', '=SUM( ' . $dataColumn.'379:' . $dataColumn.'381 )');
              $sheet->setCellValue($dataColumn.'379', $thisMonthSortedData->instagramfacebook->data_4_5[$wkey]);
              $sheet->setCellValue($dataColumn.'380', $thisMonthSortedData->instagramfacebook->data_4_6[$wkey]);
              $sheet->setCellValue($dataColumn.'381', $thisMonthSortedData->instagramfacebook->data_4_7[$wkey]);
              $sheet->setCellValue($dataColumn.'382', '=SUM( ' . $dataColumn.'383:' . $dataColumn.'385 )');
              $sheet->setCellValue($dataColumn.'383', $thisMonthSortedData->instagramfacebook->data_4_8[$wkey]);
              $sheet->setCellValue($dataColumn.'384', '=IFERROR(ROUND(' . $dataColumn.'380' . ' * (' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . ' / ' . $thisMonthSortedData->whatsappsales->data_8_1[$wkey] . '),2), 0)');
              $sheet->setCellValue($dataColumn.'385', $thisMonthSortedData->instagramfacebook->data_4_9[$wkey]);
              $sheet->setCellValue($dataColumn.'386', '=SUM( ' . $dataColumn.'387:' . $dataColumn.'389 )');
              $sheet->setCellValue($dataColumn.'387', '=ROUND(' . $thisMonthSortedData->instagramfacebook->data_4_10[$wkey] / $thisMonthSortedData->basic->data_1_2 . ', 2)');
              $sheet->setCellValue($dataColumn.'388', '=IFERROR(ROUND(' . $dataColumn.'384' . ' * ( (' . $thisMonthSortedData->whatsappsales->data_8_4[$wkey] / $thisMonthSortedData->basic->data_1_2 . ') / ' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . ' ),2), 0)');
              $sheet->setCellValue($dataColumn.'389', '=ROUND(' . $thisMonthSortedData->instagramfacebook->data_4_11[$wkey] / $thisMonthSortedData->basic->data_1_2 . ', 2)');

              $sheet->setCellValue($dataColumn.'390', '=IFERROR(ROUND(' . $dataColumn.'386 / ' . $dataColumn.'382, 2), 0)');

              $sheet->setCellValue($dataColumn.'391', '=IFERROR(ROUND(' . $dataColumn.'387 / ' . $dataColumn.'383, 2), 0)');
              $sheet->setCellValue($dataColumn.'392', '=IFERROR(ROUND(' . $dataColumn.'388 / ' . $dataColumn.'384, 2), 0)');
              $sheet->setCellValue($dataColumn.'393', '=IFERROR(ROUND(' . $dataColumn.'389 / ' . $dataColumn.'385, 2), 0)');
              $sheet->setCellValue($dataColumn.'394', '=IFERROR(ROUND(' . $dataColumn.'367 / ' . $dataColumn.'366, 4),0 )');
              $sheet->setCellValue($dataColumn.'395', '=IFERROR(ROUND(' . $dataColumn.'382 / ' . $dataColumn.'367, 4),0 )');
              $sheet->setCellValue($dataColumn.'396', '=IFERROR(ROUND(' . $dataColumn.'382 / ' . $dataColumn.'366, 4),0 )');
              $sheet->setCellValue($dataColumn.'397', '=IFERROR(ROUND(' . $dataColumn.'379 / ' . $dataColumn . '367, 4), 0 )');
              $sheet->setCellValue($dataColumn.'398', '=IFERROR(ROUND(' . $dataColumn.'383 / ' . $dataColumn . '379, 4), 0 )');
              $sheet->setCellValue($dataColumn.'399', '=IFERROR(ROUND(' . $dataColumn.'383 / ' . $dataColumn . '367, 4), 0 )');
              $sheet->setCellValue($dataColumn.'400', '=IFERROR(ROUND(' . $dataColumn.'383 / ' . $dataColumn . '366, 4), 0 )');
              $sheet->setCellValue($dataColumn.'401', '=IFERROR(ROUND(' . $dataColumn.'380 / ' . $dataColumn . '367, 4), 0 )');
              $sheet->setCellValue($dataColumn.'402', '=IFERROR(ROUND(' . $dataColumn.'384 / ' . $dataColumn . '380, 4), 0 )');
              $sheet->setCellValue($dataColumn.'403', '=IFERROR(ROUND(' . $dataColumn.'384 / ' . $dataColumn . '367, 4), 0 )');
              $sheet->setCellValue($dataColumn.'404', '=IFERROR(ROUND(' . $dataColumn.'384 / ' . $dataColumn . '366, 4), 0 )');
              $sheet->setCellValue($dataColumn.'405', '=IFERROR(ROUND(' . $dataColumn.'381 / ' . $dataColumn . '367, 4), 0 )');
              $sheet->setCellValue($dataColumn.'406', '=IFERROR(ROUND(' . $dataColumn.'385 / ' . $dataColumn . '381, 4), 0 )');
              $sheet->setCellValue($dataColumn.'407', '=IFERROR(ROUND(' . $dataColumn.'385 / ' . $dataColumn . '367, 4), 0 )');
              $sheet->setCellValue($dataColumn.'408', '=IFERROR(ROUND(' . $dataColumn.'385 / ' . $dataColumn . '366, 4), 0 )');

        // Email
              $sheet->setCellValue($dataColumn.'410', $thisMonthSortedData->email->data_5_1[$wkey]);
              $sheet->setCellValue($dataColumn.'411', $thisMonthSortedData->email->data_5_2[$wkey]);
              $sheet->setCellValue($dataColumn.'412', $thisMonthSortedData->email->data_5_3[$wkey]);
              $sheet->setCellValue($dataColumn.'413', $thisMonthSortedData->email->data_5_4[$wkey]);
              $sheet->setCellValue($dataColumn.'414', '=IFERROR(ROUND(' . $dataColumn.'417 / ' . $dataColumn.'413, 2), 0 )');
              $sheet->setCellValue($dataColumn.'415', '=IFERROR(ROUND(( ' . $dataColumn.'413 - ' . $dataColumn.'424 ) / ' . $dataColumn.'413, 4), 0 )');
              $sheet->setCellValue($dataColumn.'416', $thisMonthSortedData->email->data_5_6[$wkey]);
              $sheet->setCellValue($dataColumn.'417', '=ROUND(' . $thisMonthSortedData->email->data_5_5[$wkey] .  ' / ' . $thisMonthSortedData->basic->data_1_2 . ', 2)');
              $sheet->setCellValue($dataColumn.'418', '=IFERROR(ROUND( ' . $dataColumn.'417 / ' . $dataColumn.'413 , 2), 0 )');
              $sheet->setCellValue($dataColumn.'419', '=IFERROR(ROUND( ' . $dataColumn.'417 / ' . $dataColumn.'428 , 2), 0 )');
              $sheet->setCellValue($dataColumn.'420', '=ROUND(' . $dataColumn.'432 - ( ' . $dataColumn.'432 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) - ( ' . $dataColumn.'432 - ( ' . $dataColumn.'432 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) ) * ' . $thisMonthSortedData->basic->data_1_1 . ' - ' . $dataColumn.'417, 2)');
              $sheet->setCellValue($dataColumn.'421', '=IFERROR(ROUND( ' . $dataColumn.'420 / ' . $dataColumn.'432, 4), 0)');
              $sheet->setCellValue($dataColumn.'422', '=IFERROR(ROUND( ( ' . $dataColumn.'432 - ' . $dataColumn.'417 ) / ' . $dataColumn.'417, 4), 0)');
              $sheet->setCellValue($dataColumn.'423', '=IFERROR(ROUND( (' . $dataColumn.'432 - ' . $dataColumn.'417 - ' . $dataColumn.'416 ) / ( ' . $dataColumn.'417 + ' . $dataColumn.'416 ), 4), 0)');
              $sheet->setCellValue($dataColumn.'424', '=SUM(' . $dataColumn.'425:' . $dataColumn.'427)');
              $sheet->setCellValue($dataColumn.'425', $thisMonthSortedData->email->data_5_7[$wkey]);
              $sheet->setCellValue($dataColumn.'426', $thisMonthSortedData->email->data_5_8[$wkey]);
              $sheet->setCellValue($dataColumn.'427', $thisMonthSortedData->email->data_5_9[$wkey]);
              $sheet->setCellValue($dataColumn.'428', '=SUM(' . $dataColumn.'429:' . $dataColumn.'431)');
              $sheet->setCellValue($dataColumn.'429', $thisMonthSortedData->email->data_5_10[$wkey]);
              $sheet->setCellValue($dataColumn.'430', '=IFERROR(ROUND( ' . $dataColumn.'426 * (' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . ' / ' . $thisMonthSortedData->whatsappsales->data_8_1[$wkey] . '), 2) ,0)');
              $sheet->setCellValue($dataColumn.'431', $thisMonthSortedData->email->data_5_11[$wkey]);
              $sheet->setCellValue($dataColumn.'432', '=SUM(' . $dataColumn.'433:' . $dataColumn.'435)');
              $sheet->setCellValue($dataColumn.'433', '=ROUND(' . $thisMonthSortedData->email->data_5_12[$wkey] / $thisMonthSortedData->basic->data_1_2 . ', 2)');
              $sheet->setCellValue($dataColumn.'434', '=IFERROR(ROUND(' . $dataColumn.'430' . ' * ( (' . $thisMonthSortedData->whatsappsales->data_8_4[$wkey] / $thisMonthSortedData->basic->data_1_2 . ') / ' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . ' ),2), 0)');
              $sheet->setCellValue($dataColumn.'435', '=ROUND(' . $thisMonthSortedData->email->data_5_13[$wkey] / $thisMonthSortedData->basic->data_1_2 . ', 2)');
              $sheet->setCellValue($dataColumn.'436', '=IFERROR(ROUND(' . $dataColumn.'432 / ' . $dataColumn.'428, 2), 0)');
              $sheet->setCellValue($dataColumn.'437', '=IFERROR(ROUND(' . $dataColumn.'433 / ' . $dataColumn.'429, 2), 0)');
              $sheet->setCellValue($dataColumn.'438', '=IFERROR(ROUND(' . $dataColumn.'434 / ' . $dataColumn.'430, 2), 0)');
              $sheet->setCellValue($dataColumn.'439', '=IFERROR(ROUND(' . $dataColumn.'435 / ' . $dataColumn.'431, 2), 0)');
              $sheet->setCellValue($dataColumn.'440', '=IFERROR(ROUND(' . $dataColumn.'411 / ' . $dataColumn.'410, 4), 0)');
              $sheet->setCellValue($dataColumn.'441', '=IFERROR(ROUND(' . $dataColumn.'412 / ' . $dataColumn.'411, 4), 0)');
              $sheet->setCellValue($dataColumn.'442', '=IFERROR(ROUND(' . $dataColumn.'413 / ' . $dataColumn.'412, 4), 0)');
              $sheet->setCellValue($dataColumn.'443', '=IFERROR(ROUND(' . $dataColumn.'428 / ' . $dataColumn.'412, 4), 0)');
              $sheet->setCellValue($dataColumn.'444', '=IFERROR(ROUND(' . $dataColumn.'425 / ' . $dataColumn.'413, 4), 0)');
              $sheet->setCellValue($dataColumn.'445', '=IFERROR(ROUND(' . $dataColumn.'429 / ' . $dataColumn.'425, 4), 0)');
              $sheet->setCellValue($dataColumn.'446', '=IFERROR(ROUND(' . $dataColumn.'429 / ' . $dataColumn.'413, 4), 0)');
              $sheet->setCellValue($dataColumn.'447', '=IFERROR(ROUND(' . $dataColumn.'429 / ' . $dataColumn.'411, 4), 0)');
              $sheet->setCellValue($dataColumn.'448', '=IFERROR(ROUND(' . $dataColumn.'426 / ' . $dataColumn.'413, 4), 0)');
              $sheet->setCellValue($dataColumn.'449', '=IFERROR(ROUND(' . $dataColumn.'430 / ' . $dataColumn.'426, 4), 0)');
              $sheet->setCellValue($dataColumn.'450', '=IFERROR(ROUND(' . $dataColumn.'430 / ' . $dataColumn.'413, 4), 0)');
              $sheet->setCellValue($dataColumn.'451', '=IFERROR(ROUND(' . $dataColumn.'430 / ' . $dataColumn.'411, 4), 0)');
              $sheet->setCellValue($dataColumn.'452', '=IFERROR(ROUND(' . $dataColumn.'427 / ' . $dataColumn.'413, 4), 0)');
              $sheet->setCellValue($dataColumn.'453', '=IFERROR(ROUND(' . $dataColumn.'431 / ' . $dataColumn.'427, 4), 0)');
              $sheet->setCellValue($dataColumn.'454', '=IFERROR(ROUND(' . $dataColumn.'431 / ' . $dataColumn.'413, 4), 0)');
              $sheet->setCellValue($dataColumn.'455', '=IFERROR(ROUND(' . $dataColumn.'431 / ' . $dataColumn.'411, 4), 0)');

        // Рассылка Bot
              $sheet->setCellValue($dataColumn.'457', $thisMonthSortedData->bot->data_6_1[$wkey]);
              $sheet->setCellValue($dataColumn.'458', $thisMonthSortedData->bot->data_6_2[$wkey]);
              $sheet->setCellValue($dataColumn.'459', $thisMonthSortedData->bot->data_6_3[$wkey]);
              $sheet->setCellValue($dataColumn.'460', $thisMonthSortedData->bot->data_6_4[$wkey]);
              $sheet->setCellValue($dataColumn.'461', '=IFERROR(ROUND(' . $dataColumn.'464 / ' . $dataColumn.'460, 2), 0)');
              $sheet->setCellValue($dataColumn.'462', '=IFERROR(ROUND( (' . $dataColumn.'460 - ' . $dataColumn.'471 ) / ' . $dataColumn.'460, 4), 0)');
              $sheet->setCellValue($dataColumn.'463', '=ROUND(' . $thisMonthSortedData->bot->data_6_6 / count($weeks) . ', 2)');
              $sheet->setCellValue($dataColumn.'464', $thisMonthSortedData->bot->data_6_5[$wkey]);
              $sheet->setCellValue($dataColumn.'465', '=IFERROR(ROUND(' . $dataColumn.'464 / ' . $dataColumn.'460, 2), 0)');
              $sheet->setCellValue($dataColumn.'466', '=IFERROR(ROUND(' . $dataColumn.'464 / ' . $dataColumn.'475, 2), 0)');
              $sheet->setCellValue($dataColumn.'467', '=ROUND(' . $dataColumn.'479 - ( ' . $dataColumn.'479 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) - ( ' . $dataColumn.'479 - ( ' . $dataColumn.'479 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) ) * ' . $thisMonthSortedData->basic->data_1_1 . ' - ' . $dataColumn.'464, 2)');
              $sheet->setCellValue($dataColumn.'468', '=IFERROR(ROUND(' . $dataColumn.'467 / ' . $dataColumn.'479, 4), 0)');
              $sheet->setCellValue($dataColumn.'469', '=IFERROR(ROUND( (' . $dataColumn.'479 - ' . $dataColumn.'464 ) / ' . $dataColumn.'464, 4), 0)');
              $sheet->setCellValue($dataColumn.'470', '=IFERROR(ROUND( ( ' . $dataColumn.'479 - ' . $dataColumn.'464 - ' . $dataColumn.'463 ) / ( ' . $dataColumn.'464 + ' . $dataColumn.'463 ), 4), 0)');
              $sheet->setCellValue($dataColumn.'471','=SUM(' . $dataColumn.'472:' . $dataColumn.'474)');
              $sheet->setCellValue($dataColumn.'472', $thisMonthSortedData->bot->data_6_7[$wkey]);
              $sheet->setCellValue($dataColumn.'473', $thisMonthSortedData->bot->data_6_8[$wkey]);
              $sheet->setCellValue($dataColumn.'474', $thisMonthSortedData->bot->data_6_9[$wkey]);
              $sheet->setCellValue($dataColumn.'475','=SUM(' . $dataColumn.'476:' . $dataColumn.'478)');
              $sheet->setCellValue($dataColumn.'476', $thisMonthSortedData->bot->data_6_10[$wkey]);
              $sheet->setCellValue($dataColumn.'477', '=IFERROR(ROUND(' . $dataColumn.'473 * (' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . ' / ' . $thisMonthSortedData->whatsappsales->data_8_1[$wkey] . '), 2), 0)');
              $sheet->setCellValue($dataColumn.'478', $thisMonthSortedData->bot->data_6_11[$wkey]);
              $sheet->setCellValue($dataColumn.'479','=SUM(' . $dataColumn.'480:' . $dataColumn.'482)');
              $sheet->setCellValue($dataColumn.'480', $thisMonthSortedData->bot->data_6_12[$wkey] / $thisMonthSortedData->basic->data_1_2);
              $sheet->setCellValue($dataColumn.'481', '=IFERROR(ROUND(' . $dataColumn.'477 * ( (' . $thisMonthSortedData->whatsappsales->data_8_4[$wkey] / $thisMonthSortedData->basic->data_1_2 . ') / ' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . '), 2), 0)');
              $sheet->setCellValue($dataColumn.'482', $thisMonthSortedData->bot->data_6_13[$wkey] / $thisMonthSortedData->basic->data_1_2);
              $sheet->setCellValue($dataColumn.'483', '=IFERROR(ROUND(' . $dataColumn.'479 / ' . $dataColumn.'475, 2),0)');
              $sheet->setCellValue($dataColumn.'484', '=IFERROR(ROUND(' . $dataColumn.'480 / ' . $dataColumn.'476, 2), 0)');
              $sheet->setCellValue($dataColumn.'485', '=IFERROR(ROUND(' . $dataColumn.'481 / ' . $dataColumn.'477, 2), 0)');
              $sheet->setCellValue($dataColumn.'486', '=IFERROR(ROUND(' . $dataColumn.'482 / ' . $dataColumn.'478, 2), 0)');
              $sheet->setCellValue($dataColumn.'487', '=IFERROR(ROUND(' . $dataColumn.'458 / ' . $dataColumn.'457,4),0)');
              $sheet->setCellValue($dataColumn.'488', '=IFERROR(ROUND(' . $dataColumn.'459 / ' . $dataColumn.'457,4),0)');
              $sheet->setCellValue($dataColumn.'489', '=IFERROR(ROUND(' . $dataColumn.'460 / ' . $dataColumn.'459,4),0)');
              $sheet->setCellValue($dataColumn.'490', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'460,4),0)');
              $sheet->setCellValue($dataColumn.'491', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'459,4),0)');
              $sheet->setCellValue($dataColumn.'492', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'457,4),0)');
              $sheet->setCellValue($dataColumn.'493', '=IFERROR(ROUND(' . $dataColumn.'472 / ' . $dataColumn.'460,4),0)');
              $sheet->setCellValue($dataColumn.'494', '=IFERROR(ROUND(' . $dataColumn.'476 / ' . $dataColumn.'472,4),0)');
              $sheet->setCellValue($dataColumn.'495', '=IFERROR(ROUND(' . $dataColumn.'476 / ' . $dataColumn.'459,4),0)');
              $sheet->setCellValue($dataColumn.'496', '=IFERROR(ROUND(' . $dataColumn.'476 / ' . $dataColumn.'460,4),0)');
              $sheet->setCellValue($dataColumn.'497', '=IFERROR(ROUND(' . $dataColumn.'476 / ' . $dataColumn.'457,4),0)');
              $sheet->setCellValue($dataColumn.'498', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'457,4),0)');
              $sheet->setCellValue($dataColumn.'499', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'459,4),0)');
              $sheet->setCellValue($dataColumn.'500', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'460,4),0)');
              $sheet->setCellValue($dataColumn.'501', '=IFERROR(ROUND(' . $dataColumn.'474 / ' . $dataColumn.'457,4),0)');
              $sheet->setCellValue($dataColumn.'502', '=IFERROR(ROUND(' . $dataColumn.'474 / ' . $dataColumn.'459,4),0)');
              $sheet->setCellValue($dataColumn.'503', '=IFERROR(ROUND(' . $dataColumn.'474 / ' . $dataColumn.'460,4),0)');
              $sheet->setCellValue($dataColumn.'504', '=IFERROR(ROUND(' . $dataColumn.'478 / ' . $dataColumn.'460,4),0)');
              $sheet->setCellValue($dataColumn.'505', '=IFERROR(ROUND(' . $dataColumn.'478 / ' . $dataColumn.'457,4),0)');

        // Direct
              $sheet->setCellValue($dataColumn.'507', $thisMonthSortedData->direct->data_7_1[$wkey]);
              $sheet->setCellValue($dataColumn.'508', $thisMonthSortedData->direct->data_7_2[$wkey]);
              $sheet->setCellValue($dataColumn.'509', '=IFERROR(ROUND( ( ' . $dataColumn.'508 - ' . $dataColumn.'512 ) / ' . $dataColumn.'508, 4), 0)');
              $sheet->setCellValue($dataColumn.'510', '=ROUND(' . $dataColumn.'520 - ( ' . $dataColumn.'520 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) - ( ' . $dataColumn.'520 - ( ' . $dataColumn.'520 / ( 1 + ' . ($thisMonthSortedData->basic->data_1_3[$wkey] / 100) . ' ) ) ) * ' . $thisMonthSortedData->basic->data_1_1 . ', 2)');
              $sheet->setCellValue($dataColumn.'511', '=IFERROR(ROUND(' . $dataColumn.'510 / ' . $dataColumn.'520, 4),0)');
              $sheet->setCellValue($dataColumn.'512', '=SUM(' . $dataColumn.'513:' . $dataColumn.'515)');
              $sheet->setCellValue($dataColumn.'513', $thisMonthSortedData->direct->data_7_3[$wkey]);
              $sheet->setCellValue($dataColumn.'514', $thisMonthSortedData->direct->data_7_4[$wkey]);
              $sheet->setCellValue($dataColumn.'515', $thisMonthSortedData->direct->data_7_5[$wkey]);
              $sheet->setCellValue($dataColumn.'516', '=SUM(' . $dataColumn.'517:' . $dataColumn.'519)');
              $sheet->setCellValue($dataColumn.'517', $thisMonthSortedData->direct->data_7_6[$wkey]);
              $sheet->setCellValue($dataColumn.'518', '=IFERROR( ROUND(' . $dataColumn.'514 * (' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . ' / ' . $thisMonthSortedData->whatsappsales->data_8_1[$wkey] . '), 2 ), 0)');
              $sheet->setCellValue($dataColumn.'519', $thisMonthSortedData->direct->data_7_7[$wkey]);
              $sheet->setCellValue($dataColumn.'520', '=SUM(' . $dataColumn.'521:' . $dataColumn.'523)');
              $sheet->setCellValue($dataColumn.'521', '=ROUND(' . $thisMonthSortedData->direct->data_7_8[$wkey] / $thisMonthSortedData->basic->data_1_2 . ', 2)');
              $sheet->setCellValue($dataColumn.'522', '=IFERROR(ROUND(' . $dataColumn.'518 * ( (' . $thisMonthSortedData->whatsappsales->data_8_4[$wkey] / $thisMonthSortedData->basic->data_1_2 . ') / ' . $thisMonthSortedData->whatsappsales->data_8_3[$wkey] . '), 2), 0)');
              $sheet->setCellValue($dataColumn.'523', '=ROUND(' . $thisMonthSortedData->direct->data_7_9[$wkey] / $thisMonthSortedData->basic->data_1_2 . ', 2)');
              $sheet->setCellValue($dataColumn.'524', '=IFERROR(' . $dataColumn.'520 / ' . $dataColumn.'516, 0)');
              $sheet->setCellValue($dataColumn.'525', '=IFERROR(ROUND(' . $dataColumn.'521 / ' . $dataColumn.'517, 2), 0)');
              $sheet->setCellValue($dataColumn.'526', '=IFERROR(ROUND(' . $dataColumn.'522 / ' . $dataColumn.'518, 2), 0)');
              $sheet->setCellValue($dataColumn.'527', '=IFERROR(ROUND(' . $dataColumn.'523 / ' . $dataColumn.'519, 2), 0)');
              $sheet->setCellValue($dataColumn.'528', '=IFERROR(ROUND(' . $dataColumn.'508 / ' . $dataColumn.'507, 4), 0)');
              $sheet->setCellValue($dataColumn.'529', '=IFERROR(ROUND(' . $dataColumn.'516 / ' . $dataColumn.'508, 4), 0)');
              $sheet->setCellValue($dataColumn.'530', '=IFERROR(ROUND(' . $dataColumn.'516 / ' . $dataColumn.'507, 4), 0)');
              $sheet->setCellValue($dataColumn.'531', '=IFERROR(ROUND(' . $dataColumn.'513 / ' . $dataColumn.'508, 4), 0)');
              $sheet->setCellValue($dataColumn.'532', '=IFERROR(ROUND(' . $dataColumn.'517 / ' . $dataColumn.'513, 4), 0)');
              $sheet->setCellValue($dataColumn.'533', '=IFERROR(ROUND(' . $dataColumn.'517 / ' . $dataColumn.'508, 4), 0)');
              $sheet->setCellValue($dataColumn.'534', '=IFERROR(ROUND(' . $dataColumn.'517 / ' . $dataColumn.'507, 4), 0)');
              $sheet->setCellValue($dataColumn.'535', '=IFERROR(ROUND(' . $dataColumn.'514 / ' . $dataColumn.'508, 4), 0)');
              $sheet->setCellValue($dataColumn.'536', '=IFERROR(ROUND(' . $dataColumn.'518 / ' . $dataColumn.'514, 4), 0)');
              $sheet->setCellValue($dataColumn.'537', '=IFERROR(ROUND(' . $dataColumn.'518 / ' . $dataColumn.'508, 4), 0)');
              $sheet->setCellValue($dataColumn.'538', '=IFERROR(ROUND(' . $dataColumn.'518 / ' . $dataColumn.'507, 4), 0)');
              $sheet->setCellValue($dataColumn.'539', '=IFERROR(ROUND(' . $dataColumn.'515 / ' . $dataColumn.'508, 4), 0)');
              $sheet->setCellValue($dataColumn.'540', '=IFERROR(ROUND(' . $dataColumn.'519 / ' . $dataColumn.'515, 4), 0)');
              $sheet->setCellValue($dataColumn.'541', '=IFERROR(ROUND(' . $dataColumn.'519 / ' . $dataColumn.'508, 4), 0)');
              $sheet->setCellValue($dataColumn.'542', '=IFERROR(ROUND(' . $dataColumn.'519 / ' . $dataColumn.'507, 4), 0)');


        // // MAIN
              $sheet->setCellValue($dataColumn.'3', '='.$dataColumn.'4' );
              $sheet->setCellValue($dataColumn.'4', '=SUM(' . $dataColumn.'5:' . $dataColumn.'10)' );
              $sheet->setCellValue($dataColumn.'5', '=' . $dataColumn.'277');
              $sheet->setCellValue($dataColumn.'6', '=' . $dataColumn.'321');
              $sheet->setCellValue($dataColumn.'7', '=' . $dataColumn.'366');
              $sheet->setCellValue($dataColumn.'8', '=' . $dataColumn.'410');
              $sheet->setCellValue($dataColumn.'9', '=' . $dataColumn.'457');
              $sheet->setCellValue($dataColumn.'10', '=' . $dataColumn.'507');
              $sheet->setCellValue($dataColumn.'11', '=' . $dataColumn.'12' );
              $sheet->setCellValue($dataColumn.'12', '=SUM(' . $dataColumn.'13:' . $dataColumn.'18)' );
              $sheet->setCellValue($dataColumn.'13', '=' . $dataColumn.'278');
              $sheet->setCellValue($dataColumn.'14', '=' . $dataColumn.'322');
              $sheet->setCellValue($dataColumn.'15', '=' . $dataColumn.'367');
              $sheet->setCellValue($dataColumn.'16', '=' . $dataColumn.'413');
              $sheet->setCellValue($dataColumn.'17', '=' . $dataColumn.'460');
              $sheet->setCellValue($dataColumn.'18', '=' . $dataColumn.'508');
              $sheet->setCellValue($dataColumn.'19', '=' . $dataColumn.'20');
              $sheet->setCellValue($dataColumn.'20', '=IFERROR( ROUND( ( ' . $dataColumn.'12 - ' . $dataColumn.'93 ) / ' . $dataColumn.'12, 4), 0)');
              $sheet->setCellValue($dataColumn.'21', '=' . $dataColumn.'280');
              $sheet->setCellValue($dataColumn.'22', '=' . $dataColumn.'324');
              $sheet->setCellValue($dataColumn.'23', '=' . $dataColumn.'369');
              $sheet->setCellValue($dataColumn.'24', '=' . $dataColumn.'415');
              $sheet->setCellValue($dataColumn.'25', '=' . $dataColumn.'462');
              $sheet->setCellValue($dataColumn.'26', '=' . $dataColumn.'509');
              $sheet->setCellValue($dataColumn.'27', '=' . $dataColumn.'28');
              $sheet->setCellValue($dataColumn.'28', '=SUM(' . $dataColumn.'29:' . $dataColumn.'33)');
              $sheet->setCellValue($dataColumn.'29', '=' . $dataColumn.'281+' . $dataColumn.'282');
              $sheet->setCellValue($dataColumn.'30', '=' . $dataColumn.'325+' . $dataColumn.'326');
              $sheet->setCellValue($dataColumn.'31', '=' . $dataColumn.'370+' . $dataColumn.'371');
              $sheet->setCellValue($dataColumn.'32', '=' . $dataColumn.'417+' . $dataColumn.'416');
              $sheet->setCellValue($dataColumn.'33', '=' . $dataColumn.'464+' . $dataColumn.'463');
              $sheet->setCellValue($dataColumn.'34', '=' . $dataColumn.'35');
              $sheet->setCellValue($dataColumn.'35', '=SUM(' . $dataColumn.'36:' . $dataColumn.'40)');
              $sheet->setCellValue($dataColumn.'36', '=' . $dataColumn.'281');
              $sheet->setCellValue($dataColumn.'37', '=' . $dataColumn.'325');
              $sheet->setCellValue($dataColumn.'38', '=' . $dataColumn.'370');
              $sheet->setCellValue($dataColumn.'39', '=' . $dataColumn.'416');
              $sheet->setCellValue($dataColumn.'40', '=' . $dataColumn.'463');
              $sheet->setCellValue($dataColumn.'41', '=' . $dataColumn.'42');
              $sheet->setCellValue($dataColumn.'42', '=SUM(' . $dataColumn.'43:' . $dataColumn.'47)');
              $sheet->setCellValue($dataColumn.'43', '=' . $dataColumn.'282');
              $sheet->setCellValue($dataColumn.'44', '=' . $dataColumn.'326');
              $sheet->setCellValue($dataColumn.'45', '=' . $dataColumn.'371');
              $sheet->setCellValue($dataColumn.'46', '=' . $dataColumn.'417');
              $sheet->setCellValue($dataColumn.'47', '=' . $dataColumn.'464');
              $sheet->setCellValue($dataColumn.'48', '=' . $dataColumn.'49');
              $sheet->setCellValue($dataColumn.'49', '=IFERROR(ROUND( (' . $dataColumn.'35 + ' . $dataColumn.'42 ) / ' . $dataColumn.'12, 2), 0)');
              $sheet->setCellValue($dataColumn.'50', '=' . $dataColumn.'283');
              $sheet->setCellValue($dataColumn.'51', '=' . $dataColumn.'328');
              $sheet->setCellValue($dataColumn.'52', '=' . $dataColumn.'372');
              $sheet->setCellValue($dataColumn.'53', '=' . $dataColumn.'418');
              $sheet->setCellValue($dataColumn.'54', '=' . $dataColumn.'465');
              $sheet->setCellValue($dataColumn.'55', '=' . $dataColumn.'56');
              $sheet->setCellValue($dataColumn.'56', '=IFERROR(ROUND( (' . $dataColumn.'35 + ' . $dataColumn.'42 ) / ' . $dataColumn.'125, 2), 0)');
              $sheet->setCellValue($dataColumn.'57', '=' . $dataColumn.'284');
              $sheet->setCellValue($dataColumn.'58', '=' . $dataColumn.'329');
              $sheet->setCellValue($dataColumn.'59', '=' . $dataColumn.'373');
              $sheet->setCellValue($dataColumn.'60', '=' . $dataColumn.'419');
              $sheet->setCellValue($dataColumn.'61', '=' . $dataColumn.'466');
              $sheet->setCellValue($dataColumn.'62', '=' . $dataColumn.'63');
              $sheet->setCellValue($dataColumn.'63', '=SUM(' . $dataColumn.'64:' . $dataColumn.'69)');
              $sheet->setCellValue($dataColumn.'64', '=' . $dataColumn.'285');
              $sheet->setCellValue($dataColumn.'65', '=' . $dataColumn.'330');
              $sheet->setCellValue($dataColumn.'66', '=' . $dataColumn.'374');
              $sheet->setCellValue($dataColumn.'67', '=' . $dataColumn.'420');
              $sheet->setCellValue($dataColumn.'68', '=' . $dataColumn.'467');
              $sheet->setCellValue($dataColumn.'69', '=' . $dataColumn.'510');
              $sheet->setCellValue($dataColumn.'70', '=' . $dataColumn.'71');
              $sheet->setCellValue($dataColumn.'71', '=IFERROR(ROUND( ' . $dataColumn.'63 / ' . $dataColumn.'156, 4), 0)');
              $sheet->setCellValue($dataColumn.'72', '=' . $dataColumn.'286');
              $sheet->setCellValue($dataColumn.'73', '=' . $dataColumn.'331');
              $sheet->setCellValue($dataColumn.'74', '=' . $dataColumn.'375');
              $sheet->setCellValue($dataColumn.'75', '=' . $dataColumn.'421');
              $sheet->setCellValue($dataColumn.'76', '=' . $dataColumn.'468');
              $sheet->setCellValue($dataColumn.'77', '=' . $dataColumn.'511');
              $sheet->setCellValue($dataColumn.'78', '=' . $dataColumn.'79');
              $sheet->setCellValue($dataColumn.'79', '=IFERROR(ROUND( ( ' . $dataColumn.'158 + ' . $dataColumn.'159 + ' . $dataColumn.'160 + ' . $dataColumn.'161 + ' . $dataColumn.'162 - ' . $dataColumn.'28 ) / ' . $dataColumn.'28, 4), 0)');
              $sheet->setCellValue($dataColumn.'80', '=' . $dataColumn.'287');
              $sheet->setCellValue($dataColumn.'81', '=' . $dataColumn.'332');
              $sheet->setCellValue($dataColumn.'82', '=' . $dataColumn.'376');
              $sheet->setCellValue($dataColumn.'83', '=' . $dataColumn.'423');
              $sheet->setCellValue($dataColumn.'84', '=' . $dataColumn.'470');
              $sheet->setCellValue($dataColumn.'85', '=' . $dataColumn.'86');
              $sheet->setCellValue($dataColumn.'86', '=IFERROR(ROUND( ( ' . $dataColumn.'158 + ' . $dataColumn.'159 + ' . $dataColumn.'160 + ' . $dataColumn.'161 + ' . $dataColumn.'162 - ' . $dataColumn.'42) / ' . $dataColumn.'42, 4), 0)');
              $sheet->setCellValue($dataColumn.'87', '=' . $dataColumn.'288');
              $sheet->setCellValue($dataColumn.'88', '=' . $dataColumn.'333');
              $sheet->setCellValue($dataColumn.'89', '=' . $dataColumn.'377');
              $sheet->setCellValue($dataColumn.'90', '=' . $dataColumn.'422');
              $sheet->setCellValue($dataColumn.'91', '=' . $dataColumn.'469');
              $sheet->setCellValue($dataColumn.'92', '=' . $dataColumn.'93');
              $sheet->setCellValue($dataColumn.'93', '=SUM(' . $dataColumn.'94:' . $dataColumn.'99)');
              $sheet->setCellValue($dataColumn.'94', '=' . $dataColumn.'289');
              $sheet->setCellValue($dataColumn.'95', '=' . $dataColumn.'334');
              $sheet->setCellValue($dataColumn.'96', '=' . $dataColumn.'378');
              $sheet->setCellValue($dataColumn.'97', '=' . $dataColumn.'424');
              $sheet->setCellValue($dataColumn.'98', '=' . $dataColumn.'471');
              $sheet->setCellValue($dataColumn.'99', '=' . $dataColumn.'512');
              $sheet->setCellValue($dataColumn.'100', '=' . $dataColumn.'101');
              $sheet->setCellValue($dataColumn.'101', '=SUM(' . $dataColumn.'102:' . $dataColumn.'107)');
              $sheet->setCellValue($dataColumn.'102', '=' . $dataColumn.'290');
              $sheet->setCellValue($dataColumn.'103', '=' . $dataColumn.'335');
              $sheet->setCellValue($dataColumn.'104', '=' . $dataColumn.'379');
              $sheet->setCellValue($dataColumn.'105', '=' . $dataColumn.'425');
              $sheet->setCellValue($dataColumn.'106', '=' . $dataColumn.'472');
              $sheet->setCellValue($dataColumn.'107', '=' . $dataColumn.'513');
              $sheet->setCellValue($dataColumn.'108', '=' . $dataColumn.'109');
              $sheet->setCellValue($dataColumn.'109', '=SUM(' . $dataColumn.'110:' . $dataColumn.'115)');
              $sheet->setCellValue($dataColumn.'110', '=' . $dataColumn.'291');
              $sheet->setCellValue($dataColumn.'111', '=' . $dataColumn.'336');
              $sheet->setCellValue($dataColumn.'112', '=' . $dataColumn.'380');
              $sheet->setCellValue($dataColumn.'113', '=' . $dataColumn.'426');
              $sheet->setCellValue($dataColumn.'114', '=' . $dataColumn.'473');
              $sheet->setCellValue($dataColumn.'115', '=' . $dataColumn.'514');
              $sheet->setCellValue($dataColumn.'116', '=' . $dataColumn.'117');
              $sheet->setCellValue($dataColumn.'117', '=SUM(' . $dataColumn.'118:' . $dataColumn.'123)');
              $sheet->setCellValue($dataColumn.'118', '=' . $dataColumn.'292');
              $sheet->setCellValue($dataColumn.'119', '=' . $dataColumn.'337');
              $sheet->setCellValue($dataColumn.'120', '=' . $dataColumn.'381');
              $sheet->setCellValue($dataColumn.'121', '=' . $dataColumn.'427');
              $sheet->setCellValue($dataColumn.'122', '=' . $dataColumn.'474');
              $sheet->setCellValue($dataColumn.'123', '=' . $dataColumn.'515');
              $sheet->setCellValue($dataColumn.'124', '=' . $dataColumn.'125');
              $sheet->setCellValue($dataColumn.'125', '=SUM(' . $dataColumn.'126:' . $dataColumn.'131)');
              $sheet->setCellValue($dataColumn.'126', '=' . $dataColumn.'293');
              $sheet->setCellValue($dataColumn.'127', '=' . $dataColumn.'338');
              $sheet->setCellValue($dataColumn.'128', '=' . $dataColumn.'382');
              $sheet->setCellValue($dataColumn.'129', '=' . $dataColumn.'428');
              $sheet->setCellValue($dataColumn.'130', '=' . $dataColumn.'475');
              $sheet->setCellValue($dataColumn.'131', '=' . $dataColumn.'516');
              $sheet->setCellValue($dataColumn.'132', '=' . $dataColumn.'133');
              $sheet->setCellValue($dataColumn.'133', '=SUM(' . $dataColumn.'134:' . $dataColumn.'139)');
              $sheet->setCellValue($dataColumn.'134', '=' . $dataColumn.'294');
              $sheet->setCellValue($dataColumn.'135', '=' . $dataColumn.'339');
              $sheet->setCellValue($dataColumn.'136', '=' . $dataColumn.'383');
              $sheet->setCellValue($dataColumn.'137', '=' . $dataColumn.'429');
              $sheet->setCellValue($dataColumn.'138', '=' . $dataColumn.'476');
              $sheet->setCellValue($dataColumn.'139', '=' . $dataColumn.'517');
              $sheet->setCellValue($dataColumn.'140', '=' . $dataColumn.'141');
              $sheet->setCellValue($dataColumn.'141', '=SUM(' . $dataColumn.'142:' . $dataColumn.'147)');
              $sheet->setCellValue($dataColumn.'142', '=' . $dataColumn.'295');
              $sheet->setCellValue($dataColumn.'143', '=' . $dataColumn.'340');
              $sheet->setCellValue($dataColumn.'144', '=' . $dataColumn.'384');
              $sheet->setCellValue($dataColumn.'145', '=' . $dataColumn.'430');
              $sheet->setCellValue($dataColumn.'146', '=' . $dataColumn.'477');
              $sheet->setCellValue($dataColumn.'147', '=' . $dataColumn.'518');
              $sheet->setCellValue($dataColumn.'148', '=' . $dataColumn.'149');
              $sheet->setCellValue($dataColumn.'149', '=SUM(' . $dataColumn.'150:' . $dataColumn.'155)');
              $sheet->setCellValue($dataColumn.'150', '=' . $dataColumn.'296');
              $sheet->setCellValue($dataColumn.'151', '=' . $dataColumn.'341');
              $sheet->setCellValue($dataColumn.'152', '=' . $dataColumn.'385');
              $sheet->setCellValue($dataColumn.'153', '=' . $dataColumn.'431');
              $sheet->setCellValue($dataColumn.'154', '=' . $dataColumn.'478');
              $sheet->setCellValue($dataColumn.'155', '=' . $dataColumn.'519');
              $sheet->setCellValue($dataColumn.'156', '=' . $dataColumn.'157');
              $sheet->setCellValue($dataColumn.'157', '=SUM(' . $dataColumn.'158:' . $dataColumn.'163)');
              $sheet->setCellValue($dataColumn.'158', '=' . $dataColumn.'297');
              $sheet->setCellValue($dataColumn.'159', '=' . $dataColumn.'342');
              $sheet->setCellValue($dataColumn.'160', '=' . $dataColumn.'386');
              $sheet->setCellValue($dataColumn.'161', '=' . $dataColumn.'432');
              $sheet->setCellValue($dataColumn.'162', '=' . $dataColumn.'479');
              $sheet->setCellValue($dataColumn.'163', '=' . $dataColumn.'520');
              $sheet->setCellValue($dataColumn.'164', '=' . $dataColumn.'165');
              $sheet->setCellValue($dataColumn.'165', '=SUM(' . $dataColumn.'166:' . $dataColumn.'171)');
              $sheet->setCellValue($dataColumn.'166', '=' . $dataColumn.'298');
              $sheet->setCellValue($dataColumn.'167', '=' . $dataColumn.'343');
              $sheet->setCellValue($dataColumn.'168', '=' . $dataColumn.'387');
              $sheet->setCellValue($dataColumn.'169', '=' . $dataColumn.'433');
              $sheet->setCellValue($dataColumn.'170', '=' . $dataColumn.'480');
              $sheet->setCellValue($dataColumn.'171', '=' . $dataColumn.'521');
              $sheet->setCellValue($dataColumn.'172', '=' . $dataColumn.'173');
              $sheet->setCellValue($dataColumn.'173', '=SUM(' . $dataColumn.'174:' . $dataColumn.'179)');
              $sheet->setCellValue($dataColumn.'174', '=' . $dataColumn.'299');
              $sheet->setCellValue($dataColumn.'175', '=' . $dataColumn.'344');
              $sheet->setCellValue($dataColumn.'176', '=' . $dataColumn.'388');
              $sheet->setCellValue($dataColumn.'177', '=' . $dataColumn.'434');
              $sheet->setCellValue($dataColumn.'178', '=' . $dataColumn.'481');
              $sheet->setCellValue($dataColumn.'179', '=' . $dataColumn.'522');
              $sheet->setCellValue($dataColumn.'180', '=' . $dataColumn.'181');
              $sheet->setCellValue($dataColumn.'181', '=SUM(' . $dataColumn.'182:' . $dataColumn.'187)');
              $sheet->setCellValue($dataColumn.'182', '=' . $dataColumn.'300');
              $sheet->setCellValue($dataColumn.'183', '=' . $dataColumn.'345');
              $sheet->setCellValue($dataColumn.'184', '=' . $dataColumn.'389');
              $sheet->setCellValue($dataColumn.'185', '=' . $dataColumn.'435');
              $sheet->setCellValue($dataColumn.'186', '=' . $dataColumn.'482');
              $sheet->setCellValue($dataColumn.'187', '=' . $dataColumn.'523');
              $sheet->setCellValue($dataColumn.'188', '=' . $dataColumn.'189');
              $sheet->setCellValue($dataColumn.'189', '=IFERROR( ROUND(' . $dataColumn.'157 / ' . $dataColumn.'125, 2), 0)');
              $sheet->setCellValue($dataColumn.'190', '=' . $dataColumn.'301');
              $sheet->setCellValue($dataColumn.'191', '=' . $dataColumn.'346');
              $sheet->setCellValue($dataColumn.'192', '=' . $dataColumn.'390');
              $sheet->setCellValue($dataColumn.'193', '=' . $dataColumn.'436');
              $sheet->setCellValue($dataColumn.'194', '=' . $dataColumn.'483');
              $sheet->setCellValue($dataColumn.'195', '=' . $dataColumn.'524');
              $sheet->setCellValue($dataColumn.'196', '=' . $dataColumn.'197');
              $sheet->setCellValue($dataColumn.'197', '=IFERROR( ROUND(' . $dataColumn.'165 / ' . $dataColumn.'133, 2), 0)');
              $sheet->setCellValue($dataColumn.'198', '=IFERROR( ROUND(' . $dataColumn.'166 / ' . $dataColumn.'134, 2), 0)');
              $sheet->setCellValue($dataColumn.'199', '=IFERROR( ROUND(' . $dataColumn.'167 / ' . $dataColumn.'135, 2), 0)');
              $sheet->setCellValue($dataColumn.'200', '=IFERROR( ROUND(' . $dataColumn.'168 / ' . $dataColumn.'136, 2), 0)');
              $sheet->setCellValue($dataColumn.'201', '=IFERROR( ROUND(' . $dataColumn.'169 / ' . $dataColumn.'137, 2), 0)');
              $sheet->setCellValue($dataColumn.'202', '=IFERROR( ROUND(' . $dataColumn.'170 / ' . $dataColumn.'138, 2), 0)');
              $sheet->setCellValue($dataColumn.'203', '=IFERROR( ROUND(' . $dataColumn.'171 / ' . $dataColumn.'139, 2), 0)');
              $sheet->setCellValue($dataColumn.'204', '=' . $dataColumn.'205');
              $sheet->setCellValue($dataColumn.'205', '=IFERROR( ROUND(' . $dataColumn.'173 / ' . $dataColumn.'141, 2), 0)');
              $sheet->setCellValue($dataColumn.'206', '=IFERROR( ROUND(' . $dataColumn.'174 / ' . $dataColumn.'142, 2), 0)');
              $sheet->setCellValue($dataColumn.'207', '=IFERROR( ROUND(' . $dataColumn.'175 / ' . $dataColumn.'143, 2), 0)');
              $sheet->setCellValue($dataColumn.'208', '=IFERROR( ROUND(' . $dataColumn.'176 / ' . $dataColumn.'144, 2), 0)');
              $sheet->setCellValue($dataColumn.'209', '=IFERROR( ROUND(' . $dataColumn.'177 / ' . $dataColumn.'145, 2), 0)');
              $sheet->setCellValue($dataColumn.'210', '=IFERROR( ROUND(' . $dataColumn.'178 / ' . $dataColumn.'146, 2), 0)');
              $sheet->setCellValue($dataColumn.'211', '=IFERROR( ROUND(' . $dataColumn.'179 / ' . $dataColumn.'147, 2), 0)');
              $sheet->setCellValue($dataColumn.'212', '=' . $dataColumn.'213');
              $sheet->setCellValue($dataColumn.'213', '=IFERROR( ROUND(' . $dataColumn.'181 / ' . $dataColumn.'149, 2), 0)');
              $sheet->setCellValue($dataColumn.'214', '=IFERROR( ROUND(' . $dataColumn.'182 / ' . $dataColumn.'150, 2), 0)');
              $sheet->setCellValue($dataColumn.'215', '=IFERROR( ROUND(' . $dataColumn.'183 / ' . $dataColumn.'151, 2), 0)');
              $sheet->setCellValue($dataColumn.'216', '=IFERROR( ROUND(' . $dataColumn.'184 / ' . $dataColumn.'152, 2), 0)');
              $sheet->setCellValue($dataColumn.'217', '=IFERROR( ROUND(' . $dataColumn.'185 / ' . $dataColumn.'153, 2), 0)');
              $sheet->setCellValue($dataColumn.'218', '=IFERROR( ROUND(' . $dataColumn.'186 / ' . $dataColumn.'154, 2), 0)');
              $sheet->setCellValue($dataColumn.'219', '=IFERROR( ROUND(' . $dataColumn.'187 / ' . $dataColumn.'155, 2), 0)');

              $sheet->setCellValue($dataColumn.'220', '=' . $dataColumn.'221');
              $sheet->setCellValue($dataColumn.'221', '=IFERROR( ROUND(' . $dataColumn.'101 / ' . $dataColumn.'12, 4), 0)');
              $sheet->setCellValue($dataColumn.'222', '=IFERROR( ROUND(' . $dataColumn.'102 / ' . $dataColumn.'13, 4), 0)');
              $sheet->setCellValue($dataColumn.'223', '=IFERROR( ROUND(' . $dataColumn.'103 / ' . $dataColumn.'14, 4), 0)');
              $sheet->setCellValue($dataColumn.'224', '=IFERROR( ROUND(' . $dataColumn.'104 / ' . $dataColumn.'15, 4), 0)');
              $sheet->setCellValue($dataColumn.'225', '=IFERROR( ROUND(' . $dataColumn.'105 / ' . $dataColumn.'16, 4), 0)');
              $sheet->setCellValue($dataColumn.'226', '=IFERROR( ROUND(' . $dataColumn.'106 / ' . $dataColumn.'17, 4), 0)');
              $sheet->setCellValue($dataColumn.'227', '=IFERROR( ROUND(' . $dataColumn.'107 / ' . $dataColumn.'18, 4), 0)');

              $sheet->setCellValue($dataColumn.'228', '=' . $dataColumn.'229');
              $sheet->setCellValue($dataColumn.'229', '=IFERROR( ROUND(' . $dataColumn.'109 / ' . $dataColumn.'12, 4), 0)');
              $sheet->setCellValue($dataColumn.'230', '=IFERROR( ROUND(' . $dataColumn.'110 / ' . $dataColumn.'13, 4), 0)');
              $sheet->setCellValue($dataColumn.'231', '=IFERROR( ROUND(' . $dataColumn.'111 / ' . $dataColumn.'14, 4), 0)');
              $sheet->setCellValue($dataColumn.'232', '=IFERROR( ROUND(' . $dataColumn.'112 / ' . $dataColumn.'15, 4), 0)');
              $sheet->setCellValue($dataColumn.'233', '=IFERROR( ROUND(' . $dataColumn.'113 / ' . $dataColumn.'16, 4), 0)');
              $sheet->setCellValue($dataColumn.'234', '=IFERROR( ROUND(' . $dataColumn.'114 / ' . $dataColumn.'17, 4), 0)');
              $sheet->setCellValue($dataColumn.'235', '=IFERROR( ROUND(' . $dataColumn.'115 / ' . $dataColumn.'18, 4), 0)');

              $sheet->setCellValue($dataColumn.'236', '=' . $dataColumn.'237');
              $sheet->setCellValue($dataColumn.'237', '=IFERROR( ROUND(' . $dataColumn.'117 / ' . $dataColumn.'12, 4), 0)');
              $sheet->setCellValue($dataColumn.'238', '=IFERROR( ROUND(' . $dataColumn.'118 / ' . $dataColumn.'13, 4), 0)');
              $sheet->setCellValue($dataColumn.'239', '=IFERROR( ROUND(' . $dataColumn.'119 / ' . $dataColumn.'14, 4), 0)');
              $sheet->setCellValue($dataColumn.'240', '=IFERROR( ROUND(' . $dataColumn.'120 / ' . $dataColumn.'15, 4), 0)');
              $sheet->setCellValue($dataColumn.'241', '=IFERROR( ROUND(' . $dataColumn.'121 / ' . $dataColumn.'16, 4), 0)');
              $sheet->setCellValue($dataColumn.'242', '=IFERROR( ROUND(' . $dataColumn.'122 / ' . $dataColumn.'17, 4), 0)');
              $sheet->setCellValue($dataColumn.'243', '=IFERROR( ROUND(' . $dataColumn.'123 / ' . $dataColumn.'18, 4), 0)');

              $sheet->setCellValue($dataColumn.'244', '=' . $dataColumn.'245');
              $sheet->setCellValue($dataColumn.'245', '=IFERROR( ROUND(' . $dataColumn.'133 / ' . $dataColumn.'12, 4), 0)');
              $sheet->setCellValue($dataColumn.'246', '=IFERROR( ROUND(' . $dataColumn.'134 / ' . $dataColumn.'13, 4), 0)');
              $sheet->setCellValue($dataColumn.'247', '=IFERROR( ROUND(' . $dataColumn.'135 / ' . $dataColumn.'14, 4), 0)');
              $sheet->setCellValue($dataColumn.'248', '=IFERROR( ROUND(' . $dataColumn.'136 / ' . $dataColumn.'15, 4), 0)');
              $sheet->setCellValue($dataColumn.'249', '=IFERROR( ROUND(' . $dataColumn.'137 / ' . $dataColumn.'16, 4), 0)');
              $sheet->setCellValue($dataColumn.'250', '=IFERROR( ROUND(' . $dataColumn.'138 / ' . $dataColumn.'17, 4), 0)');
              $sheet->setCellValue($dataColumn.'251', '=IFERROR( ROUND(' . $dataColumn.'139 / ' . $dataColumn.'18, 4), 0)');

              $sheet->setCellValue($dataColumn.'252', '=' . $dataColumn.'253');
              $sheet->setCellValue($dataColumn.'253', '=IFERROR( ROUND(' . $dataColumn.'141 / ' . $dataColumn.'12, 4), 0)');
              $sheet->setCellValue($dataColumn.'254', '=IFERROR( ROUND(' . $dataColumn.'142 / ' . $dataColumn.'13, 4), 0)');
              $sheet->setCellValue($dataColumn.'255', '=IFERROR( ROUND(' . $dataColumn.'143 / ' . $dataColumn.'14, 4), 0)');
              $sheet->setCellValue($dataColumn.'256', '=IFERROR( ROUND(' . $dataColumn.'144 / ' . $dataColumn.'15, 4), 0)');
              $sheet->setCellValue($dataColumn.'257', '=IFERROR( ROUND(' . $dataColumn.'145 / ' . $dataColumn.'16, 4), 0)');
              $sheet->setCellValue($dataColumn.'258', '=IFERROR( ROUND(' . $dataColumn.'146 / ' . $dataColumn.'17, 4), 0)');
              $sheet->setCellValue($dataColumn.'259', '=IFERROR( ROUND(' . $dataColumn.'147 / ' . $dataColumn.'18, 4), 0)');

              $sheet->setCellValue($dataColumn.'260', '=' . $dataColumn.'261');
              $sheet->setCellValue($dataColumn.'261', '=IFERROR( ROUND(' . $dataColumn.'149 / ' . $dataColumn.'12, 4), 0)');
              $sheet->setCellValue($dataColumn.'262', '=IFERROR( ROUND(' . $dataColumn.'150 / ' . $dataColumn.'13, 4), 0)');
              $sheet->setCellValue($dataColumn.'263', '=IFERROR( ROUND(' . $dataColumn.'151 / ' . $dataColumn.'14, 4), 0)');
              $sheet->setCellValue($dataColumn.'264', '=IFERROR( ROUND(' . $dataColumn.'152 / ' . $dataColumn.'15, 4), 0)');
              $sheet->setCellValue($dataColumn.'265', '=IFERROR( ROUND(' . $dataColumn.'153 / ' . $dataColumn.'16, 4), 0)');
              $sheet->setCellValue($dataColumn.'266', '=IFERROR( ROUND(' . $dataColumn.'154 / ' . $dataColumn.'17, 4), 0)');
              $sheet->setCellValue($dataColumn.'267', '=IFERROR( ROUND(' . $dataColumn.'155 / ' . $dataColumn.'18, 4), 0)');

              $sheet->setCellValue($dataColumn.'268', '=' . $dataColumn.'269');
              $sheet->setCellValue($dataColumn.'269', '=ROUND(' . $dataColumn.'132 / ' . $dataColumn.'100, 4)');
              $sheet->setCellValue($dataColumn.'270', '=' . $dataColumn.'309');
              $sheet->setCellValue($dataColumn.'271', '=' . $dataColumn.'354');
              $sheet->setCellValue($dataColumn.'272', '=' . $dataColumn.'398');
              $sheet->setCellValue($dataColumn.'273', '=' . $dataColumn.'445');
              $sheet->setCellValue($dataColumn.'274', '=' . $dataColumn.'494');
              $sheet->setCellValue($dataColumn.'275', '=' . $dataColumn.'532');

        $sheet->getColumnDimension($dataColumn)->setWidth('12');
        $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);
      }
      $sheet->setCellValue($dataColumn.'1', strftime('%B', strtotime('2025-' . $monthNum . '-01')));
      $sheet->getStyle($dataColumn.'1')->getFont()->setBold(true);

      // Organic
      $sheet->setCellValue($dataColumn.'277', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '277:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '277)');
      $sheet->setCellValue($dataColumn.'278', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '278:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '278)');
      $sheet->setCellValue($dataColumn.'279', '=AVERAGE(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '279:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '279)');
      $sheet->setCellValue($dataColumn.'290', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '290:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '290)');
      $sheet->setCellValue($dataColumn.'291', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '291:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '291)');
      $sheet->setCellValue($dataColumn.'292', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '292:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '292)');
      $sheet->setCellValue($dataColumn.'294', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '294:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '294)');
      $sheet->setCellValue($dataColumn.'295', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '295:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '295)');
      $sheet->setCellValue($dataColumn.'296', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '296:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '296)');
      $sheet->setCellValue($dataColumn.'298', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '298:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '298)');
      $sheet->setCellValue($dataColumn.'299', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '299:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '299)');
      $sheet->setCellValue($dataColumn.'300', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '300:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '300)');
      $sheet->setCellValue($dataColumn.'302', '=IFERROR(ROUND(' . $dataColumn . '298 / ' . $dataColumn . '294,0),2)');
      $sheet->setCellValue($dataColumn.'303', '=IFERROR(ROUND(' . $dataColumn . '299 / ' . $dataColumn . '295,0),2)');
      $sheet->setCellValue($dataColumn.'304', '=IFERROR(ROUND(' . $dataColumn . '300 / ' . $dataColumn . '296,0),2)');
      $sheet->setCellValue($dataColumn.'289', '=SUM(' . $dataColumn.'290:' . $dataColumn.'292)');
      $sheet->setCellValue($dataColumn.'293', '=SUM(' . $dataColumn.'294:' . $dataColumn.'296)');
      $sheet->setCellValue($dataColumn.'297', '=SUM(' . $dataColumn.'298:' . $dataColumn.'300)');
      $sheet->setCellValue($dataColumn.'301', '=IFERROR(ROUND(' . $dataColumn . '297 / ' . $dataColumn . '293,4),0)');
      $sheet->setCellValue($dataColumn.'305', '=IFERROR(ROUND(' . $dataColumn . '278 / ' . $dataColumn . '277,4),0)');
      $sheet->setCellValue($dataColumn.'306', '=IFERROR(ROUND(' . $dataColumn . '293 / ' . $dataColumn . '278,4),0)');
      $sheet->setCellValue($dataColumn.'307', '=IFERROR(ROUND(' . $dataColumn . '293 / ' . $dataColumn . '277,4),0)');
      $sheet->setCellValue($dataColumn.'308', '=IFERROR(ROUND(' . $dataColumn . '290 / ' . $dataColumn . '278,4),0)');
      $sheet->setCellValue($dataColumn.'309', '=IFERROR(ROUND(' . $dataColumn . '294 / ' . $dataColumn . '290,4),0)');
      $sheet->setCellValue($dataColumn.'310', '=IFERROR(ROUND(' . $dataColumn . '294 / ' . $dataColumn . '278,4),0)');
      $sheet->setCellValue($dataColumn.'311', '=IFERROR(ROUND(' . $dataColumn . '294 / ' . $dataColumn . '277,4),0)');
      $sheet->setCellValue($dataColumn.'312', '=IFERROR(ROUND(' . $dataColumn . '291 / ' . $dataColumn . '278,4),0)');
      $sheet->setCellValue($dataColumn.'313', '=IFERROR(ROUND(' . $dataColumn . '295 / ' . $dataColumn . '291,4),0)');
      $sheet->setCellValue($dataColumn.'314', '=IFERROR(ROUND(' . $dataColumn . '295 / ' . $dataColumn . '278,4),0)');
      $sheet->setCellValue($dataColumn.'315', '=IFERROR(ROUND(' . $dataColumn . '295 / ' . $dataColumn . '277,4),0)');
      $sheet->setCellValue($dataColumn.'316', '=IFERROR(ROUND(' . $dataColumn . '292 / ' . $dataColumn . '278,4),0)');
      $sheet->setCellValue($dataColumn.'317', '=IFERROR(ROUND(' . $dataColumn . '296 / ' . $dataColumn . '292,4),0)');
      $sheet->setCellValue($dataColumn.'318', '=IFERROR(ROUND(' . $dataColumn . '296 / ' . $dataColumn . '278,4),0)');
      $sheet->setCellValue($dataColumn.'319', '=IFERROR(ROUND(' . $dataColumn . '296 / ' . $dataColumn . '277,4),0)');
      $sheet->setCellValue($dataColumn.'280', '=IFERROR(ROUND( (' . $dataColumn . '278 - ' . $dataColumn . '289) / ' . $dataColumn . '278,4),0)');
      $sheet->setCellValue($dataColumn.'281', '=ROUND(' . $thisMonthSortedData->organic->data_2_1 . '/' . $thisMonthSortedData->basic->data_1_2 . ',2)');
      $sheet->setCellValue($dataColumn.'282', '=ROUND(' . $thisMonthSortedData->organic->data_2_2 . '/' . $thisMonthSortedData->basic->data_1_2 . ',2)');
      $sheet->setCellValue($dataColumn.'283', '=IFERROR(ROUND( (' . $dataColumn.'281+' . $dataColumn.'282' . ') / ' . $dataColumn.'278' . ',2),0)');
      $sheet->setCellValue($dataColumn.'284', '=IFERROR(ROUND( (' . $dataColumn.'281+' . $dataColumn.'282' . ') / ' . $dataColumn.'293' . ',2),0)');
      $sheet->setCellValue($dataColumn.'285', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '285:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '285)');
      $sheet->getStyle($dataColumn.'285')->setConditionalStyles([$conditionalMinus]);
      $sheet->setCellValue($dataColumn.'286', '=IFERROR(ROUND(' . $dataColumn.'285 / ' . $dataColumn.'297,4),0)');
      $sheet->setCellValue($dataColumn.'287', '=IFERROR(ROUND( ( ' . $dataColumn.'297 - ' . $dataColumn.'282 - ' . $dataColumn.'281 ) / ( ' . $dataColumn.'282 + ' . $dataColumn.'281 ), 4 ) ,0 )');
      $sheet->setCellValue($dataColumn.'288', '=IFERROR(ROUND( ( ' . $dataColumn.'297 - ' . $dataColumn.'281 ) / ' . $dataColumn.'281, 4 ),0 )');

      // CPC
      $sheet->setCellValue($dataColumn.'321', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '321:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '321)');
      $sheet->setCellValue($dataColumn.'322', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '322:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '322)');
      $sheet->setCellValue($dataColumn.'323', '=IFERROR( ROUND( ' . $dataColumn . '326 / ' . $dataColumn . '322, 2), 0)' );
      $sheet->setCellValue($dataColumn.'324', '=IFERROR( ROUND( (' . $dataColumn . '322 - ' . $dataColumn . '334) / ' . $dataColumn . '322, 4), 0)' );
      $sheet->setCellValue($dataColumn.'325', '=IFERROR( ROUND(' . ($thisMonthSortedData->cpc->data_3_1 / $thisMonthSortedData->basic->data_1_2)  . ', 2), 0)' );
      $sheet->setCellValue($dataColumn.'326', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '326:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '326)');
      $sheet->setCellValue($dataColumn.'327', '=IFERROR( ROUND( ' . $dataColumn . '326 / ' . $dataColumn . '334, 2), 0)' );
      $sheet->setCellValue($dataColumn.'328', '=IFERROR( ROUND( ( ' . $dataColumn . '325 + ' . $dataColumn . '326 ) / ' . $dataColumn . '322, 2), 0)' );
      $sheet->setCellValue($dataColumn.'329', '=IFERROR( ROUND( ( ' . $dataColumn . '325 + ' . $dataColumn . '326 ) / ' . $dataColumn . '338, 2), 0)' );
      $sheet->setCellValue($dataColumn.'330', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '330:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '330)');
      $sheet->setCellValue($dataColumn.'331', '=IFERROR( ROUND( ' . $dataColumn . '330 / ' . $dataColumn . '342, 4), 0)' );
      $sheet->setCellValue($dataColumn.'332', '=IFERROR( ROUND( ( ' . $dataColumn . '342 - ' . $dataColumn . '326 - ' . $dataColumn . '325 ) / ( ' . $dataColumn . '325 + ' . $dataColumn . '326), 4), 0)' );
      $sheet->setCellValue($dataColumn.'333', '=IFERROR( ROUND( ( ' . $dataColumn . '342 - ' . $dataColumn . '326 ) / ' . $dataColumn . '326, 4), 0)' );
      $sheet->setCellValue($dataColumn.'334', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '334:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '334)=SUM(' . $dataColumn.'335:' . $dataColumn.'337), SUM(' . $dataColumn.'335:' . $dataColumn.'337),"ошибка")');
      $sheet->setCellValue($dataColumn.'335', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '335:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '335)');
      $sheet->setCellValue($dataColumn.'336', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '336:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '336)');
      $sheet->setCellValue($dataColumn.'337', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '337:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '337)');
      $sheet->setCellValue($dataColumn.'338', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '338:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '338)=SUM(' . $dataColumn.'339:' . $dataColumn.'341), SUM(' . $dataColumn.'339:' . $dataColumn.'341),"ошибка")');
      $sheet->setCellValue($dataColumn.'339', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '339:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '339)');
      $sheet->setCellValue($dataColumn.'340', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '340:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '340)');
      $sheet->setCellValue($dataColumn.'341', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '341:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '341)');
      $sheet->setCellValue($dataColumn.'342', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '342:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '342)=SUM(' . $dataColumn.'343:' . $dataColumn.'345), SUM(' . $dataColumn.'343:' . $dataColumn.'345),"ошибка")');
      $sheet->setCellValue($dataColumn.'343', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '343:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '343)');
      $sheet->setCellValue($dataColumn.'344', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '344:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '344)');
      $sheet->setCellValue($dataColumn.'345', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '345:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '345)');
      $sheet->setCellValue($dataColumn.'346', '=IFERROR( ROUND( ' . $dataColumn . '342 / ' . $dataColumn . '338, 2), 0 )' );
      $sheet->setCellValue($dataColumn.'347', '=IFERROR( ROUND( ' . $dataColumn . '343 / ' . $dataColumn . '339, 2), 0 )' );
      $sheet->setCellValue($dataColumn.'348', '=IFERROR( ROUND( ' . $dataColumn . '344 / ' . $dataColumn . '340, 2), 0 )' );
      $sheet->setCellValue($dataColumn.'349', '=IFERROR( ROUND( ' . $dataColumn . '345 / ' . $dataColumn . '341, 2), 0 )' );
      $sheet->setCellValue($dataColumn.'350', '=IFERROR( ROUND( ' . $dataColumn . '322 / ' . $dataColumn . '321, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'351', '=IFERROR( ROUND( ' . $dataColumn . '338 / ' . $dataColumn . '322, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'352', '=IFERROR( ROUND( ' . $dataColumn . '338 / ' . $dataColumn . '321, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'353', '=IFERROR( ROUND( ' . $dataColumn . '335 / ' . $dataColumn . '322, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'354', '=IFERROR( ROUND( ' . $dataColumn . '339 / ' . $dataColumn . '335, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'355', '=IFERROR( ROUND( ' . $dataColumn . '339 / ' . $dataColumn . '322, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'356', '=IFERROR( ROUND( ' . $dataColumn . '339 / ' . $dataColumn . '321, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'357', '=IFERROR( ROUND( ' . $dataColumn . '336 / ' . $dataColumn . '322, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'358', '=IFERROR( ROUND( ' . $dataColumn . '340 / ' . $dataColumn . '336, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'359', '=IFERROR( ROUND( ' . $dataColumn . '340 / ' . $dataColumn . '322, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'360', '=IFERROR( ROUND( ' . $dataColumn . '340 / ' . $dataColumn . '321, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'361', '=IFERROR( ROUND( ' . $dataColumn . '337 / ' . $dataColumn . '322, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'362', '=IFERROR( ROUND( ' . $dataColumn . '341 / ' . $dataColumn . '337, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'363', '=IFERROR( ROUND( ' . $dataColumn . '341 / ' . $dataColumn . '322, 4), 0 )' );
      $sheet->setCellValue($dataColumn.'364', '=IFERROR( ROUND( ' . $dataColumn . '341 / ' . $dataColumn . '321, 4), 0 )' );

      // Instagram / Facebook
      $sheet->setCellValue($dataColumn.'366', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '366:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '366)');
      $sheet->setCellValue($dataColumn.'367', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '367:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '367)');
      $sheet->setCellValue($dataColumn.'368', '=IFERROR(ROUND( ' . $dataColumn.'371 / ' . $dataColumn.'367, 2 ), 0)');
      $sheet->setCellValue($dataColumn.'369', '=IFERROR(ROUND( ( ' . $dataColumn.'367' . ' - ' . $dataColumn.'378 ) / ' . $dataColumn.'367, 4) ,0)');
      $sheet->setCellValue($dataColumn.'370', $thisMonthSortedData->instagramfacebook->data_4_3);
      $sheet->setCellValue($dataColumn.'371', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '371:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '371)');
      $sheet->setCellValue($dataColumn.'372', '=IFERROR(ROUND( ( ' . $dataColumn.'370 + ' . $dataColumn.'371 ) / ' . $dataColumn.'367,2),0)');
      $sheet->setCellValue($dataColumn.'373', '=IFERROR(ROUND( ( ' . $dataColumn.'370 + ' . $dataColumn.'371 ) / ' . $dataColumn.'382,2),0)');
      $sheet->setCellValue($dataColumn.'374', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '374:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '374)');
      $sheet->setCellValue($dataColumn.'375', '=IFERROR(ROUND( ' . $dataColumn.'374 / ' . $dataColumn.'386, 4 ) ,0)');
      $sheet->setCellValue($dataColumn.'376', '=IFERROR(ROUND( ( ' . $dataColumn.'386 - ' . $dataColumn.'371 - ' . $dataColumn.'370 ) / ( ' . $dataColumn.'370 + ' . $dataColumn.'371 ),4 ) ,0)');
      $sheet->setCellValue($dataColumn.'377', '=IFERROR(ROUND( ( ' . $dataColumn.'386 - ' . $dataColumn.'371 ) / ' . $dataColumn.'370, 4 ) ,0)');
      $sheet->setCellValue($dataColumn.'378', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '378:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '378)=SUM(' . $dataColumn.'379:' . $dataColumn.'381), SUM(' . $dataColumn.'379:' . $dataColumn.'381),"ошибка")');
      $sheet->setCellValue($dataColumn.'379', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '379:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '379)');
      $sheet->setCellValue($dataColumn.'380', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '380:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '380)');
      $sheet->setCellValue($dataColumn.'381', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '381:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '381)');
      $sheet->setCellValue($dataColumn.'382', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '382:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '382)=SUM(' . $dataColumn.'383:' . $dataColumn.'385), SUM(' . $dataColumn.'383:' . $dataColumn.'385),"ошибка")');
      $sheet->setCellValue($dataColumn.'383', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '383:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '383)');
      $sheet->setCellValue($dataColumn.'384', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '384:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '384)');
      $sheet->setCellValue($dataColumn.'385', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '385:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '385)');
      $sheet->setCellValue($dataColumn.'386', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '386:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '386)=SUM(' . $dataColumn.'387:' . $dataColumn.'389), SUM(' . $dataColumn.'387:' . $dataColumn.'389),"ошибка")');
      $sheet->setCellValue($dataColumn.'387', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '387:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '387)');
      $sheet->setCellValue($dataColumn.'388', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '388:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '388)');
      $sheet->setCellValue($dataColumn.'389', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '389:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '389)');
      $sheet->setCellValue($dataColumn.'390', '=IFERROR(ROUND(' . $dataColumn.'386 / ' . $dataColumn.'382, 2), 0)');
      $sheet->setCellValue($dataColumn.'391', '=IFERROR(ROUND(' . $dataColumn.'387 / ' . $dataColumn.'383, 2), 0)');
      $sheet->setCellValue($dataColumn.'392', '=IFERROR(ROUND(' . $dataColumn.'388 / ' . $dataColumn.'384, 2), 0)');
      $sheet->setCellValue($dataColumn.'393', '=IFERROR(ROUND(' . $dataColumn.'389 / ' . $dataColumn.'385, 2), 0)');
      $sheet->setCellValue($dataColumn.'394', '=IFERROR(ROUND(' . $dataColumn.'367 / ' . $dataColumn.'366, 4),0 )');
      $sheet->setCellValue($dataColumn.'395', '=IFERROR(ROUND(' . $dataColumn.'382 / ' . $dataColumn.'367, 4),0 )');
      $sheet->setCellValue($dataColumn.'396', '=IFERROR(ROUND(' . $dataColumn.'389 / ' . $dataColumn.'366, 4),0 )');
      $sheet->setCellValue($dataColumn.'397', '=IFERROR(ROUND(' . $dataColumn.'379 / ' . $dataColumn . '367, 4), 0 )');
      $sheet->setCellValue($dataColumn.'398', '=IFERROR(ROUND(' . $dataColumn.'383 / ' . $dataColumn . '379, 4), 0 )');
      $sheet->setCellValue($dataColumn.'399', '=IFERROR(ROUND(' . $dataColumn.'383 / ' . $dataColumn . '367, 4), 0 )');
      $sheet->setCellValue($dataColumn.'400', '=IFERROR(ROUND(' . $dataColumn.'383 / ' . $dataColumn . '366, 4), 0 )');
      $sheet->setCellValue($dataColumn.'401', '=IFERROR(ROUND(' . $dataColumn.'380 / ' . $dataColumn . '367, 4), 0 )');
      $sheet->setCellValue($dataColumn.'402', '=IFERROR(ROUND(' . $dataColumn.'384 / ' . $dataColumn . '380, 4), 0 )');
      $sheet->setCellValue($dataColumn.'403', '=IFERROR(ROUND(' . $dataColumn.'384 / ' . $dataColumn . '367, 4), 0 )');
      $sheet->setCellValue($dataColumn.'404', '=IFERROR(ROUND(' . $dataColumn.'384 / ' . $dataColumn . '366, 4), 0 )');
      $sheet->setCellValue($dataColumn.'405', '=IFERROR(ROUND(' . $dataColumn.'381 / ' . $dataColumn . '367, 4), 0 )');
      $sheet->setCellValue($dataColumn.'406', '=IFERROR(ROUND(' . $dataColumn.'385 / ' . $dataColumn . '381, 4), 0 )');
      $sheet->setCellValue($dataColumn.'407', '=IFERROR(ROUND(' . $dataColumn.'385 / ' . $dataColumn . '367, 4), 0 )');
      $sheet->setCellValue($dataColumn.'408', '=IFERROR(ROUND(' . $dataColumn.'385 / ' . $dataColumn . '366, 4), 0 )');

      // Email
      $sheet->setCellValue($dataColumn.'410', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '410:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '410)');
      $sheet->setCellValue($dataColumn.'411', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '411:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '411)');
      $sheet->setCellValue($dataColumn.'412', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '412:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '412)');
      $sheet->setCellValue($dataColumn.'413', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '413:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '413)');
      $sheet->setCellValue($dataColumn.'414', '=IFERROR(ROUND(' . $dataColumn.'417 / ' . $dataColumn.'413, 2), 0 )');
      $sheet->setCellValue($dataColumn.'415', '=IFERROR(ROUND(( ' . $dataColumn.'413 - ' . $dataColumn.'424 ) / ' . $dataColumn.'413, 4), 0 )');
      $sheet->setCellValue($dataColumn.'416', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '416:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '416)');
      $sheet->setCellValue($dataColumn.'417', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '417:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '417)');
      $sheet->setCellValue($dataColumn.'418', '=IFERROR(ROUND( ' . $dataColumn.'417 / ' . $dataColumn.'413 , 2), 0 )');
      $sheet->setCellValue($dataColumn.'419', '=IFERROR(ROUND( ' . $dataColumn.'417 / ' . $dataColumn.'428 , 2), 0 )');
      $sheet->setCellValue($dataColumn.'420', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '420:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '420)');
      $sheet->setCellValue($dataColumn.'421', '=IFERROR(ROUND( ' . $dataColumn.'420 / ' . $dataColumn.'432, 4), 0)');
      $sheet->setCellValue($dataColumn.'422', '=IFERROR(ROUND( ( ' . $dataColumn.'432 - ' . $dataColumn.'417 ) / ' . $dataColumn.'417, 4), 0)');
      $sheet->setCellValue($dataColumn.'423', '=IFERROR(ROUND( (' . $dataColumn.'432 - ' . $dataColumn.'417 - ' . $dataColumn.'416 ) / ( ' . $dataColumn.'417 + ' . $dataColumn.'416 ), 4), 0)');
      $sheet->setCellValue($dataColumn.'424', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '424:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '424)=SUM(' . $dataColumn.'425:' . $dataColumn.'427), SUM(' . $dataColumn.'425:' . $dataColumn.'427),"ошибка")');
      $sheet->setCellValue($dataColumn.'425', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '425:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '425)');
      $sheet->setCellValue($dataColumn.'426', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '426:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '426)');
      $sheet->setCellValue($dataColumn.'427', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '427:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '427)');
      $sheet->setCellValue($dataColumn.'428', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '428:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '428)=SUM(' . $dataColumn.'429:' . $dataColumn.'431), SUM(' . $dataColumn.'429:' . $dataColumn.'431),"ошибка")');
      $sheet->setCellValue($dataColumn.'429', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '429:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '429)');
      $sheet->setCellValue($dataColumn.'430', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '430:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '430)');
      $sheet->setCellValue($dataColumn.'431', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '431:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '431)');
      $sheet->setCellValue($dataColumn.'432', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '432:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '432)=SUM(' . $dataColumn.'433:' . $dataColumn.'435), SUM(' . $dataColumn.'433:' . $dataColumn.'435),"ошибка")');
      $sheet->setCellValue($dataColumn.'433', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '433:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '433)');
      $sheet->setCellValue($dataColumn.'434', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '434:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '434)');
      $sheet->setCellValue($dataColumn.'435', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '435:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '435)');
      $sheet->setCellValue($dataColumn.'436', '=IFERROR(ROUND(' . $dataColumn.'432 / ' . $dataColumn.'428, 2), 0)');
      $sheet->setCellValue($dataColumn.'437', '=IFERROR(ROUND(' . $dataColumn.'433 / ' . $dataColumn.'429, 2), 0)');
      $sheet->setCellValue($dataColumn.'438', '=IFERROR(ROUND(' . $dataColumn.'434 / ' . $dataColumn.'430, 2), 0)');
      $sheet->setCellValue($dataColumn.'439', '=IFERROR(ROUND(' . $dataColumn.'435 / ' . $dataColumn.'431, 2), 0)');
      $sheet->setCellValue($dataColumn.'440', '=IFERROR(ROUND(' . $dataColumn.'411 / ' . $dataColumn.'410, 4), 0)');
      $sheet->setCellValue($dataColumn.'441', '=IFERROR(ROUND(' . $dataColumn.'412 / ' . $dataColumn.'411, 4), 0)');
      $sheet->setCellValue($dataColumn.'442', '=IFERROR(ROUND(' . $dataColumn.'413 / ' . $dataColumn.'412, 4), 0)');
      $sheet->setCellValue($dataColumn.'443', '=IFERROR(ROUND(' . $dataColumn.'428 / ' . $dataColumn.'412, 4), 0)');
      $sheet->setCellValue($dataColumn.'444', '=IFERROR(ROUND(' . $dataColumn.'425 / ' . $dataColumn.'413, 4), 0)');
      $sheet->setCellValue($dataColumn.'445', '=IFERROR(ROUND(' . $dataColumn.'429 / ' . $dataColumn.'425, 4), 0)');
      $sheet->setCellValue($dataColumn.'446', '=IFERROR(ROUND(' . $dataColumn.'429 / ' . $dataColumn.'413, 4), 0)');
      $sheet->setCellValue($dataColumn.'447', '=IFERROR(ROUND(' . $dataColumn.'429 / ' . $dataColumn.'411, 4), 0)');
      $sheet->setCellValue($dataColumn.'448', '=IFERROR(ROUND(' . $dataColumn.'426 / ' . $dataColumn.'413, 4), 0)');
      $sheet->setCellValue($dataColumn.'449', '=IFERROR(ROUND(' . $dataColumn.'430 / ' . $dataColumn.'426, 4), 0)');
      $sheet->setCellValue($dataColumn.'450', '=IFERROR(ROUND(' . $dataColumn.'430 / ' . $dataColumn.'413, 4), 0)');
      $sheet->setCellValue($dataColumn.'451', '=IFERROR(ROUND(' . $dataColumn.'430 / ' . $dataColumn.'411, 4), 0)');
      $sheet->setCellValue($dataColumn.'452', '=IFERROR(ROUND(' . $dataColumn.'427 / ' . $dataColumn.'413, 4), 0)');
      $sheet->setCellValue($dataColumn.'453', '=IFERROR(ROUND(' . $dataColumn.'431 / ' . $dataColumn.'427, 4), 0)');
      $sheet->setCellValue($dataColumn.'454', '=IFERROR(ROUND(' . $dataColumn.'431 / ' . $dataColumn.'413, 4), 0)');
      $sheet->setCellValue($dataColumn.'455', '=IFERROR(ROUND(' . $dataColumn.'431 / ' . $dataColumn.'411, 4), 0)');


      // Рассылка Bot
      $sheet->setCellValue($dataColumn.'457', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '457:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '457)');
      $sheet->setCellValue($dataColumn.'458', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '458:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '458)');
      $sheet->setCellValue($dataColumn.'459', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '459:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '459)');
      $sheet->setCellValue($dataColumn.'460', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '460:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '460)');
      $sheet->setCellValue($dataColumn.'461', '=IFERROR(ROUND(' . $dataColumn.'464 / ' . $dataColumn.'460, 2), 0)');
      $sheet->setCellValue($dataColumn.'462', '=IFERROR(ROUND( (' . $dataColumn.'460 - ' . $dataColumn.'471 ) / ' . $dataColumn.'460, 4), 0)');
      $sheet->setCellValue($dataColumn.'463', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '463:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '463)');
      $sheet->setCellValue($dataColumn.'464', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '464:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '464)');
      $sheet->setCellValue($dataColumn.'465', '=IFERROR(ROUND(' . $dataColumn.'464 / ' . $dataColumn.'460, 2), 0)');
      $sheet->setCellValue($dataColumn.'466', '=IFERROR(ROUND(' . $dataColumn.'464 / ' . $dataColumn.'475, 2), 0)');
      $sheet->setCellValue($dataColumn.'467', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '467:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '467)');
      $sheet->setCellValue($dataColumn.'468', '=IFERROR(ROUND(' . $dataColumn.'467 / ' . $dataColumn.'479, 4), 0)');
      $sheet->setCellValue($dataColumn.'469', '=IFERROR(ROUND( (' . $dataColumn.'479 - ' . $dataColumn.'464 ) / ' . $dataColumn.'464, 4), 0)');
      $sheet->setCellValue($dataColumn.'470', '=IFERROR(ROUND( ( ' . $dataColumn.'479 - ' . $dataColumn.'464 - ' . $dataColumn.'463 ) / ( ' . $dataColumn.'464 + ' . $dataColumn.'463 ), 4), 0)');
      $sheet->setCellValue($dataColumn.'471', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '471:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '471)=SUM(' . $dataColumn.'472:' . $dataColumn.'474), SUM(' . $dataColumn.'472:' . $dataColumn.'474),"ошибка")');
      $sheet->setCellValue($dataColumn.'472', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '472:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '472)');
      $sheet->setCellValue($dataColumn.'473', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '473:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '473)');
      $sheet->setCellValue($dataColumn.'474', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '474:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '474)');
      $sheet->setCellValue($dataColumn.'475', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '475:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '475)=SUM(' . $dataColumn.'476:' . $dataColumn.'478), SUM(' . $dataColumn.'476:' . $dataColumn.'478),"ошибка")');
      $sheet->setCellValue($dataColumn.'476', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '476:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '476)');
      $sheet->setCellValue($dataColumn.'477', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '477:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '477)');
      $sheet->setCellValue($dataColumn.'478', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '478:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '478)');
      $sheet->setCellValue($dataColumn.'479', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '479:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '479)=SUM(' . $dataColumn.'480:' . $dataColumn.'482), SUM(' . $dataColumn.'480:' . $dataColumn.'482),"ошибка")');
      $sheet->setCellValue($dataColumn.'480', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '480:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '480)');
      $sheet->setCellValue($dataColumn.'481', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '481:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '481)');
      $sheet->setCellValue($dataColumn.'482', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '482:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '482)');
      $sheet->setCellValue($dataColumn.'483', '=IFERROR(ROUND(' . $dataColumn.'479 / ' . $dataColumn.'475, 2),0)');
      $sheet->setCellValue($dataColumn.'484', '=IFERROR(ROUND(' . $dataColumn.'480 / ' . $dataColumn.'476, 2), 0)');
      $sheet->setCellValue($dataColumn.'485', '=IFERROR(ROUND(' . $dataColumn.'481 / ' . $dataColumn.'477, 2), 0)');
      $sheet->setCellValue($dataColumn.'486', '=IFERROR(ROUND(' . $dataColumn.'482 / ' . $dataColumn.'478, 2), 0)');
      $sheet->setCellValue($dataColumn.'487', '=IFERROR(ROUND(' . $dataColumn.'458 / ' . $dataColumn.'457,4),0)');
      $sheet->setCellValue($dataColumn.'488', '=IFERROR(ROUND(' . $dataColumn.'459 / ' . $dataColumn.'457,4),0)');
      $sheet->setCellValue($dataColumn.'489', '=IFERROR(ROUND(' . $dataColumn.'460 / ' . $dataColumn.'459,4),0)');
      $sheet->setCellValue($dataColumn.'490', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'460,4),0)');
      $sheet->setCellValue($dataColumn.'491', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'459,4),0)');
      $sheet->setCellValue($dataColumn.'492', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'457,4),0)');
      $sheet->setCellValue($dataColumn.'493', '=IFERROR(ROUND(' . $dataColumn.'472 / ' . $dataColumn.'460,4),0)');
      $sheet->setCellValue($dataColumn.'494', '=IFERROR(ROUND(' . $dataColumn.'476 / ' . $dataColumn.'472,4),0)');
      $sheet->setCellValue($dataColumn.'495', '=IFERROR(ROUND(' . $dataColumn.'476 / ' . $dataColumn.'459,4),0)');
      $sheet->setCellValue($dataColumn.'496', '=IFERROR(ROUND(' . $dataColumn.'476 / ' . $dataColumn.'460,4),0)');
      $sheet->setCellValue($dataColumn.'497', '=IFERROR(ROUND(' . $dataColumn.'476 / ' . $dataColumn.'457,4),0)');
      $sheet->setCellValue($dataColumn.'498', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'457,4),0)');
      $sheet->setCellValue($dataColumn.'499', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'459,4),0)');
      $sheet->setCellValue($dataColumn.'500', '=IFERROR(ROUND(' . $dataColumn.'475 / ' . $dataColumn.'460,4),0)');
      $sheet->setCellValue($dataColumn.'501', '=IFERROR(ROUND(' . $dataColumn.'474 / ' . $dataColumn.'457,4),0)');
      $sheet->setCellValue($dataColumn.'502', '=IFERROR(ROUND(' . $dataColumn.'474 / ' . $dataColumn.'459,4),0)');
      $sheet->setCellValue($dataColumn.'503', '=IFERROR(ROUND(' . $dataColumn.'474 / ' . $dataColumn.'460,4),0)');
      $sheet->setCellValue($dataColumn.'504', '=IFERROR(ROUND(' . $dataColumn.'478 / ' . $dataColumn.'460,4),0)');
      $sheet->setCellValue($dataColumn.'505', '=IFERROR(ROUND(' . $dataColumn.'478 / ' . $dataColumn.'457,4),0)');


      // Direct
      $sheet->setCellValue($dataColumn.'507', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '507:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '507)');
      $sheet->setCellValue($dataColumn.'508', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '508:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '508)');
      $sheet->setCellValue($dataColumn.'509', '=IFERROR(ROUND( ( ' . $dataColumn.'508 - ' . $dataColumn.'512 ) / ' . $dataColumn.'508, 4), 0)');
      $sheet->setCellValue($dataColumn.'510', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '510:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '510)');
      $sheet->setCellValue($dataColumn.'511', '=IFERROR(ROUND(' . $dataColumn.'510 / ' . $dataColumn.'520, 4),0)');
      $sheet->setCellValue($dataColumn.'512', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '512:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '512)=SUM(' . $dataColumn.'513:' . $dataColumn.'515), SUM(' . $dataColumn.'513:' . $dataColumn.'515),"ошибка")');
      $sheet->setCellValue($dataColumn.'513', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '513:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '513)');
      $sheet->setCellValue($dataColumn.'514', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '514:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '514)');
      $sheet->setCellValue($dataColumn.'515', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '515:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '515)');
      $sheet->setCellValue($dataColumn.'516', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '516:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '516)=SUM(' . $dataColumn.'517:' . $dataColumn.'519), SUM(' . $dataColumn.'517:' . $dataColumn.'519),"ошибка")');
      $sheet->setCellValue($dataColumn.'517', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '517:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '517)');
      $sheet->setCellValue($dataColumn.'518', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '518:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '518)');
      $sheet->setCellValue($dataColumn.'519', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '519:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '519)');
      $sheet->setCellValue($dataColumn.'520', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '520:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '520)=SUM(' . $dataColumn.'521:' . $dataColumn.'523), SUM(' . $dataColumn.'521:' . $dataColumn.'523),"ошибка")');
      $sheet->setCellValue($dataColumn.'521', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '521:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '521)');
      $sheet->setCellValue($dataColumn.'522', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '522:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '522)');
      $sheet->setCellValue($dataColumn.'523', '=SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '523:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '523)');
      $sheet->setCellValue($dataColumn.'524', '=IFERROR(' . $dataColumn.'520 / ' . $dataColumn.'516, 0)');
      $sheet->setCellValue($dataColumn.'525', '=IFERROR(ROUND(' . $dataColumn.'521 / ' . $dataColumn.'517, 2), 0)');
      $sheet->setCellValue($dataColumn.'526', '=IFERROR(ROUND(' . $dataColumn.'522 / ' . $dataColumn.'518, 2), 0)');
      $sheet->setCellValue($dataColumn.'527', '=IFERROR(ROUND(' . $dataColumn.'523 / ' . $dataColumn.'519, 2), 0)');
      $sheet->setCellValue($dataColumn.'528', '=IFERROR(ROUND(' . $dataColumn.'508 / ' . $dataColumn.'507, 4), 0)');
      $sheet->setCellValue($dataColumn.'529', '=IFERROR(ROUND(' . $dataColumn.'516 / ' . $dataColumn.'508, 4), 0)');
      $sheet->setCellValue($dataColumn.'530', '=IFERROR(ROUND(' . $dataColumn.'516 / ' . $dataColumn.'507, 4), 0)');
      $sheet->setCellValue($dataColumn.'531', '=IFERROR(ROUND(' . $dataColumn.'513 / ' . $dataColumn.'508, 4), 0)');
      $sheet->setCellValue($dataColumn.'532', '=IFERROR(ROUND(' . $dataColumn.'517 / ' . $dataColumn.'513, 4), 0)');
      $sheet->setCellValue($dataColumn.'533', '=IFERROR(ROUND(' . $dataColumn.'517 / ' . $dataColumn.'508, 4), 0)');
      $sheet->setCellValue($dataColumn.'534', '=IFERROR(ROUND(' . $dataColumn.'517 / ' . $dataColumn.'507, 4), 0)');
      $sheet->setCellValue($dataColumn.'535', '=IFERROR(ROUND(' . $dataColumn.'514 / ' . $dataColumn.'508, 4), 0)');
      $sheet->setCellValue($dataColumn.'536', '=IFERROR(ROUND(' . $dataColumn.'518 / ' . $dataColumn.'514, 4), 0)');
      $sheet->setCellValue($dataColumn.'537', '=IFERROR(ROUND(' . $dataColumn.'518 / ' . $dataColumn.'508, 4), 0)');
      $sheet->setCellValue($dataColumn.'538', '=IFERROR(ROUND(' . $dataColumn.'518 / ' . $dataColumn.'507, 4), 0)');
      $sheet->setCellValue($dataColumn.'539', '=IFERROR(ROUND(' . $dataColumn.'515 / ' . $dataColumn.'508, 4), 0)');
      $sheet->setCellValue($dataColumn.'540', '=IFERROR(ROUND(' . $dataColumn.'519 / ' . $dataColumn.'515, 4), 0)');
      $sheet->setCellValue($dataColumn.'541', '=IFERROR(ROUND(' . $dataColumn.'519 / ' . $dataColumn.'508, 4), 0)');
      $sheet->setCellValue($dataColumn.'542', '=IFERROR(ROUND(' . $dataColumn.'519 / ' . $dataColumn.'507, 4), 0)');


      // MAIN
      $sheet->setCellValue($dataColumn.'3', '=' . $dataColumn.'4' );
      $sheet->setCellValue($dataColumn.'4', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '4:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '4)=SUM(' . $dataColumn.'5:' . $dataColumn.'10),SUM(' . $dataColumn.'5:' . $dataColumn.'10),"ошибка")' );
      $sheet->setCellValue($dataColumn.'5', '=' . $dataColumn.'277');
      $sheet->setCellValue($dataColumn.'6', '=' . $dataColumn.'321');
      $sheet->setCellValue($dataColumn.'7', '=' . $dataColumn.'366');
      $sheet->setCellValue($dataColumn.'8', '=' . $dataColumn.'410');
      $sheet->setCellValue($dataColumn.'9', '=' . $dataColumn.'457');
      $sheet->setCellValue($dataColumn.'10', '=' . $dataColumn.'507');
      $sheet->setCellValue($dataColumn.'11', '=' . $dataColumn.'12' );
      $sheet->setCellValue($dataColumn.'12', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '12:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '12)=SUM(' . $dataColumn.'13:' . $dataColumn.'18),SUM(' . $dataColumn.'13:' . $dataColumn.'18),"ошибка")' );
      $sheet->setCellValue($dataColumn.'13', '=' . $dataColumn.'278');
      $sheet->setCellValue($dataColumn.'14', '=' . $dataColumn.'322');
      $sheet->setCellValue($dataColumn.'15', '=' . $dataColumn.'367');
      $sheet->setCellValue($dataColumn.'16', '=' . $dataColumn.'413');
      $sheet->setCellValue($dataColumn.'17', '=' . $dataColumn.'460');
      $sheet->setCellValue($dataColumn.'18', '=' . $dataColumn.'508');
      $sheet->setCellValue($dataColumn.'19', '=' . $dataColumn.'20');
      $sheet->setCellValue($dataColumn.'20', '=IFERROR( ROUND( ( ' . $dataColumn.'12 - ' . $dataColumn.'93 ) / ' . $dataColumn.'12, 4), 0)');
      $sheet->setCellValue($dataColumn.'21', '=' . $dataColumn.'280');
      $sheet->setCellValue($dataColumn.'22', '=' . $dataColumn.'324');
      $sheet->setCellValue($dataColumn.'23', '=' . $dataColumn.'369');
      $sheet->setCellValue($dataColumn.'24', '=' . $dataColumn.'415');
      $sheet->setCellValue($dataColumn.'25', '=' . $dataColumn.'462');
      $sheet->setCellValue($dataColumn.'26', '=' . $dataColumn.'509');
      $sheet->setCellValue($dataColumn.'27', '=' . $dataColumn.'28');
      $sheet->setCellValue($dataColumn.'28', '=SUM(' . $dataColumn.'29:' . $dataColumn.'33)');
      // $sheet->setCellValue($dataColumn.'28', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '28:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '28)=SUM(' . $dataColumn.'29:' . $dataColumn.'33),SUM(' . $dataColumn.'29:' . $dataColumn.'33),"ошибка")' );
      $sheet->setCellValue($dataColumn.'29', '=' . $dataColumn.'281+' . $dataColumn.'282');
      $sheet->setCellValue($dataColumn.'30', '=' . $dataColumn.'325+' . $dataColumn.'326');
      $sheet->setCellValue($dataColumn.'31', '=' . $dataColumn.'370+' . $dataColumn.'371');
      $sheet->setCellValue($dataColumn.'32', '=' . $dataColumn.'417+' . $dataColumn.'416');
      $sheet->setCellValue($dataColumn.'33', '=' . $dataColumn.'464+' . $dataColumn.'463');
      $sheet->setCellValue($dataColumn.'34', '=' . $dataColumn.'35');
      $sheet->setCellValue($dataColumn.'35', '=SUM(' . $dataColumn.'36:' . $dataColumn.'40)');
      // $sheet->setCellValue($dataColumn.'35', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '35:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '35)=SUM(' . $dataColumn.'36:' . $dataColumn.'40),SUM(' . $dataColumn.'36:' . $dataColumn.'40),"ошибка")' );
      $sheet->setCellValue($dataColumn.'36', '=' . $dataColumn.'281');
      $sheet->setCellValue($dataColumn.'37', '=' . $dataColumn.'325');
      $sheet->setCellValue($dataColumn.'38', '=' . $dataColumn.'370');
      $sheet->setCellValue($dataColumn.'39', '=' . $dataColumn.'416');
      $sheet->setCellValue($dataColumn.'40', '=' . $dataColumn.'463');
      $sheet->setCellValue($dataColumn.'41', '=' . $dataColumn.'42');
      $sheet->setCellValue($dataColumn.'42', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '42:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '42)=SUM(' . $dataColumn.'43:' . $dataColumn.'47),SUM(' . $dataColumn.'43:' . $dataColumn.'47),"ошибка")' );
      $sheet->setCellValue($dataColumn.'43', '=' . $dataColumn.'282');
      $sheet->setCellValue($dataColumn.'44', '=' . $dataColumn.'326');
      $sheet->setCellValue($dataColumn.'45', '=' . $dataColumn.'371');
      $sheet->setCellValue($dataColumn.'46', '=' . $dataColumn.'417');
      $sheet->setCellValue($dataColumn.'47', '=' . $dataColumn.'464');
      $sheet->setCellValue($dataColumn.'48', '=' . $dataColumn.'49');
      $sheet->setCellValue($dataColumn.'49', '=IFERROR(ROUND( (' . $dataColumn.'35 + ' . $dataColumn.'42 ) / ' . $dataColumn.'12, 2), 0)');
      $sheet->setCellValue($dataColumn.'50', '=' . $dataColumn.'283');
      $sheet->setCellValue($dataColumn.'51', '=' . $dataColumn.'328');
      $sheet->setCellValue($dataColumn.'52', '=' . $dataColumn.'372');
      $sheet->setCellValue($dataColumn.'53', '=' . $dataColumn.'418');
      $sheet->setCellValue($dataColumn.'54', '=' . $dataColumn.'465');
      $sheet->setCellValue($dataColumn.'55', '=' . $dataColumn.'56');
      $sheet->setCellValue($dataColumn.'56', '=IFERROR(ROUND( (' . $dataColumn.'35 + ' . $dataColumn.'42 ) / ' . $dataColumn.'125, 2), 0)');
      $sheet->setCellValue($dataColumn.'57', '=' . $dataColumn.'284');
      $sheet->setCellValue($dataColumn.'58', '=' . $dataColumn.'329');
      $sheet->setCellValue($dataColumn.'59', '=' . $dataColumn.'373');
      $sheet->setCellValue($dataColumn.'60', '=' . $dataColumn.'419');
      $sheet->setCellValue($dataColumn.'61', '=' . $dataColumn.'466');
      $sheet->setCellValue($dataColumn.'62', '=' . $dataColumn.'63');
      $sheet->setCellValue($dataColumn.'63', '=SUM(' . $dataColumn.'64:' . $dataColumn.'69)');
      $sheet->setCellValue($dataColumn.'64', '=' . $dataColumn.'285');
      $sheet->setCellValue($dataColumn.'65', '=' . $dataColumn.'330');
      $sheet->setCellValue($dataColumn.'66', '=' . $dataColumn.'374');
      $sheet->setCellValue($dataColumn.'67', '=' . $dataColumn.'420');
      $sheet->setCellValue($dataColumn.'68', '=' . $dataColumn.'467');
      $sheet->setCellValue($dataColumn.'69', '=' . $dataColumn.'510');
      $sheet->setCellValue($dataColumn.'70', '=' . $dataColumn.'71');
      $sheet->setCellValue($dataColumn.'71', '=IFERROR(ROUND( ' . $dataColumn.'63 / ' . $dataColumn.'156, 4), 0)');
      $sheet->setCellValue($dataColumn.'72', '=' . $dataColumn.'286');
      $sheet->setCellValue($dataColumn.'73', '=' . $dataColumn.'331');
      $sheet->setCellValue($dataColumn.'74', '=' . $dataColumn.'375');
      $sheet->setCellValue($dataColumn.'75', '=' . $dataColumn.'421');
      $sheet->setCellValue($dataColumn.'76', '=' . $dataColumn.'468');
      $sheet->setCellValue($dataColumn.'77', '=' . $dataColumn.'511');
      $sheet->setCellValue($dataColumn.'78', '=' . $dataColumn.'79');
      $sheet->setCellValue($dataColumn.'79', '=IFERROR(ROUND( ( ' . $dataColumn.'158 + ' . $dataColumn.'159 + ' . $dataColumn.'160 + ' . $dataColumn.'161 + ' . $dataColumn.'162 - ' . $dataColumn.'28 ) / ' . $dataColumn.'28, 4), 0)');
      $sheet->setCellValue($dataColumn.'80', '=' . $dataColumn.'287');
      $sheet->setCellValue($dataColumn.'81', '=' . $dataColumn.'332');
      $sheet->setCellValue($dataColumn.'82', '=' . $dataColumn.'376');
      $sheet->setCellValue($dataColumn.'83', '=' . $dataColumn.'423');
      $sheet->setCellValue($dataColumn.'84', '=' . $dataColumn.'470');
      $sheet->setCellValue($dataColumn.'85', '=' . $dataColumn.'86');
      $sheet->setCellValue($dataColumn.'86', '=IFERROR(ROUND( ( ' . $dataColumn.'158 + ' . $dataColumn.'159 + ' . $dataColumn.'160 + ' . $dataColumn.'161 + ' . $dataColumn.'162 - ' . $dataColumn.'42) / ' . $dataColumn.'42, 4), 0)');
      $sheet->setCellValue($dataColumn.'87', '=' . $dataColumn.'288');
      $sheet->setCellValue($dataColumn.'88', '=' . $dataColumn.'333');
      $sheet->setCellValue($dataColumn.'89', '=' . $dataColumn.'377');
      $sheet->setCellValue($dataColumn.'90', '=' . $dataColumn.'422');
      $sheet->setCellValue($dataColumn.'91', '=' . $dataColumn.'469');
      $sheet->setCellValue($dataColumn.'92', '=' . $dataColumn.'93');
      $sheet->setCellValue($dataColumn.'93', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '93:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '93)=SUM(' . $dataColumn.'94:' . $dataColumn.'99),SUM(' . $dataColumn.'94:' . $dataColumn.'99),"ошибка")' );
      $sheet->setCellValue($dataColumn.'94', '=' . $dataColumn.'289');
      $sheet->setCellValue($dataColumn.'95', '=' . $dataColumn.'334');
      $sheet->setCellValue($dataColumn.'96', '=' . $dataColumn.'378');
      $sheet->setCellValue($dataColumn.'97', '=' . $dataColumn.'424');
      $sheet->setCellValue($dataColumn.'98', '=' . $dataColumn.'471');
      $sheet->setCellValue($dataColumn.'99', '=' . $dataColumn.'512');
      $sheet->setCellValue($dataColumn.'100', '=' . $dataColumn.'101');
      $sheet->setCellValue($dataColumn.'101', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '101:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '101)=SUM(' . $dataColumn.'102:' . $dataColumn.'107),SUM(' . $dataColumn.'102:' . $dataColumn.'107),"ошибка")' );
      $sheet->setCellValue($dataColumn.'102', '=' . $dataColumn.'290');
      $sheet->setCellValue($dataColumn.'103', '=' . $dataColumn.'335');
      $sheet->setCellValue($dataColumn.'104', '=' . $dataColumn.'379');
      $sheet->setCellValue($dataColumn.'105', '=' . $dataColumn.'425');
      $sheet->setCellValue($dataColumn.'106', '=' . $dataColumn.'472');
      $sheet->setCellValue($dataColumn.'107', '=' . $dataColumn.'513');
      $sheet->setCellValue($dataColumn.'108', '=' . $dataColumn.'109');
      $sheet->setCellValue($dataColumn.'109', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '109:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '109)=SUM(' . $dataColumn.'110:' . $dataColumn.'115),SUM(' . $dataColumn.'110:' . $dataColumn.'115),"ошибка")' );
      $sheet->setCellValue($dataColumn.'110', '=' . $dataColumn.'291');
      $sheet->setCellValue($dataColumn.'111', '=' . $dataColumn.'336');
      $sheet->setCellValue($dataColumn.'112', '=' . $dataColumn.'380');
      $sheet->setCellValue($dataColumn.'113', '=' . $dataColumn.'426');
      $sheet->setCellValue($dataColumn.'114', '=' . $dataColumn.'473');
      $sheet->setCellValue($dataColumn.'115', '=' . $dataColumn.'514');
      $sheet->setCellValue($dataColumn.'116', '=' . $dataColumn.'117');
      $sheet->setCellValue($dataColumn.'117', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '117:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '117)=SUM(' . $dataColumn.'118:' . $dataColumn.'123),SUM(' . $dataColumn.'118:' . $dataColumn.'123),"ошибка")' );
      $sheet->setCellValue($dataColumn.'118', '=' . $dataColumn.'292');
      $sheet->setCellValue($dataColumn.'119', '=' . $dataColumn.'337');
      $sheet->setCellValue($dataColumn.'120', '=' . $dataColumn.'381');
      $sheet->setCellValue($dataColumn.'121', '=' . $dataColumn.'427');
      $sheet->setCellValue($dataColumn.'122', '=' . $dataColumn.'474');
      $sheet->setCellValue($dataColumn.'123', '=' . $dataColumn.'515');
      $sheet->setCellValue($dataColumn.'124', '=' . $dataColumn.'125');
      $sheet->setCellValue($dataColumn.'125', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '125:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '125)=SUM(' . $dataColumn.'126:' . $dataColumn.'131),SUM(' . $dataColumn.'126:' . $dataColumn.'131),"ошибка")' );
      $sheet->setCellValue($dataColumn.'126', '=' . $dataColumn.'293');
      $sheet->setCellValue($dataColumn.'127', '=' . $dataColumn.'338');
      $sheet->setCellValue($dataColumn.'128', '=' . $dataColumn.'382');
      $sheet->setCellValue($dataColumn.'129', '=' . $dataColumn.'428');
      $sheet->setCellValue($dataColumn.'130', '=' . $dataColumn.'475');
      $sheet->setCellValue($dataColumn.'131', '=' . $dataColumn.'516');
      $sheet->setCellValue($dataColumn.'132', '=' . $dataColumn.'133');
      $sheet->setCellValue($dataColumn.'133', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '133:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '133)=SUM(' . $dataColumn.'134:' . $dataColumn.'139),SUM(' . $dataColumn.'134:' . $dataColumn.'139),"ошибка")' );
      $sheet->setCellValue($dataColumn.'134', '=' . $dataColumn.'294');
      $sheet->setCellValue($dataColumn.'135', '=' . $dataColumn.'339');
      $sheet->setCellValue($dataColumn.'136', '=' . $dataColumn.'383');
      $sheet->setCellValue($dataColumn.'137', '=' . $dataColumn.'429');
      $sheet->setCellValue($dataColumn.'138', '=' . $dataColumn.'476');
      $sheet->setCellValue($dataColumn.'139', '=' . $dataColumn.'517');
      $sheet->setCellValue($dataColumn.'140', '=' . $dataColumn.'141');
      $sheet->setCellValue($dataColumn.'141', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '141:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '141)=SUM(' . $dataColumn.'142:' . $dataColumn.'147),SUM(' . $dataColumn.'142:' . $dataColumn.'147),"ошибка")' );
      $sheet->setCellValue($dataColumn.'142', '=' . $dataColumn.'295');
      $sheet->setCellValue($dataColumn.'143', '=' . $dataColumn.'340');
      $sheet->setCellValue($dataColumn.'144', '=' . $dataColumn.'384');
      $sheet->setCellValue($dataColumn.'145', '=' . $dataColumn.'430');
      $sheet->setCellValue($dataColumn.'146', '=' . $dataColumn.'477');
      $sheet->setCellValue($dataColumn.'147', '=' . $dataColumn.'518');
      $sheet->setCellValue($dataColumn.'148', '=' . $dataColumn.'149');
      $sheet->setCellValue($dataColumn.'149', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '149:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '149)=SUM(' . $dataColumn.'150:' . $dataColumn.'155),SUM(' . $dataColumn.'150:' . $dataColumn.'155),"ошибка")' );
      $sheet->setCellValue($dataColumn.'150', '=' . $dataColumn.'296');
      $sheet->setCellValue($dataColumn.'151', '=' . $dataColumn.'341');
      $sheet->setCellValue($dataColumn.'152', '=' . $dataColumn.'385');
      $sheet->setCellValue($dataColumn.'153', '=' . $dataColumn.'431');
      $sheet->setCellValue($dataColumn.'154', '=' . $dataColumn.'478');
      $sheet->setCellValue($dataColumn.'155', '=' . $dataColumn.'519');
      $sheet->setCellValue($dataColumn.'156', '=' . $dataColumn.'157');
      $sheet->setCellValue($dataColumn.'157', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '157:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '157)=SUM(' . $dataColumn.'158:' . $dataColumn.'163),SUM(' . $dataColumn.'158:' . $dataColumn.'163),"ошибка")' );
      $sheet->setCellValue($dataColumn.'158', '=' . $dataColumn.'297');
      $sheet->setCellValue($dataColumn.'159', '=' . $dataColumn.'342');
      $sheet->setCellValue($dataColumn.'160', '=' . $dataColumn.'386');
      $sheet->setCellValue($dataColumn.'161', '=' . $dataColumn.'432');
      $sheet->setCellValue($dataColumn.'162', '=' . $dataColumn.'479');
      $sheet->setCellValue($dataColumn.'163', '=' . $dataColumn.'520');
      $sheet->setCellValue($dataColumn.'164', '=' . $dataColumn.'165');
      $sheet->setCellValue($dataColumn.'165', '=SUM(' . $dataColumn.'166:' . $dataColumn.'171)');
      // $sheet->setCellValue($dataColumn.'165', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '165:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '165)=SUM(' . $dataColumn.'166:' . $dataColumn.'171),SUM(' . $dataColumn.'166:' . $dataColumn.'171),"ошибка")' );
      $sheet->setCellValue($dataColumn.'166', '=' . $dataColumn.'298');
      $sheet->setCellValue($dataColumn.'167', '=' . $dataColumn.'343');
      $sheet->setCellValue($dataColumn.'168', '=' . $dataColumn.'387');
      $sheet->setCellValue($dataColumn.'169', '=' . $dataColumn.'433');
      $sheet->setCellValue($dataColumn.'170', '=' . $dataColumn.'480');
      $sheet->setCellValue($dataColumn.'171', '=' . $dataColumn.'521');
      $sheet->setCellValue($dataColumn.'172', '=' . $dataColumn.'173');
      $sheet->setCellValue($dataColumn.'173', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '173:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '173)=SUM(' . $dataColumn.'174:' . $dataColumn.'179),SUM(' . $dataColumn.'174:' . $dataColumn.'179),"ошибка")' );
      $sheet->setCellValue($dataColumn.'174', '=' . $dataColumn.'299');
      $sheet->setCellValue($dataColumn.'175', '=' . $dataColumn.'344');
      $sheet->setCellValue($dataColumn.'176', '=' . $dataColumn.'388');
      $sheet->setCellValue($dataColumn.'177', '=' . $dataColumn.'434');
      $sheet->setCellValue($dataColumn.'178', '=' . $dataColumn.'481');
      $sheet->setCellValue($dataColumn.'179', '=' . $dataColumn.'522');
      $sheet->setCellValue($dataColumn.'180', '=' . $dataColumn.'181');
      $sheet->setCellValue($dataColumn.'181', '=IF( SUM(' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($weeks)) . '181:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . '181)=SUM(' . $dataColumn.'182:' . $dataColumn.'187),SUM(' . $dataColumn.'182:' . $dataColumn.'187),"ошибка")' );
      $sheet->setCellValue($dataColumn.'182', '=' . $dataColumn.'300');
      $sheet->setCellValue($dataColumn.'183', '=' . $dataColumn.'345');
      $sheet->setCellValue($dataColumn.'184', '=' . $dataColumn.'389');
      $sheet->setCellValue($dataColumn.'185', '=' . $dataColumn.'435');
      $sheet->setCellValue($dataColumn.'186', '=' . $dataColumn.'482');
      $sheet->setCellValue($dataColumn.'187', '=' . $dataColumn.'523');
      $sheet->setCellValue($dataColumn.'188', '=' . $dataColumn.'189');
      $sheet->setCellValue($dataColumn.'189', '=IFERROR( ROUND(' . $dataColumn.'157 / ' . $dataColumn.'125, 2), 0)');
      $sheet->setCellValue($dataColumn.'190', '=' . $dataColumn.'301');
      $sheet->setCellValue($dataColumn.'191', '=' . $dataColumn.'346');
      $sheet->setCellValue($dataColumn.'192', '=' . $dataColumn.'390');
      $sheet->setCellValue($dataColumn.'193', '=' . $dataColumn.'436');
      $sheet->setCellValue($dataColumn.'194', '=' . $dataColumn.'483');
      $sheet->setCellValue($dataColumn.'195', '=' . $dataColumn.'524');
      $sheet->setCellValue($dataColumn.'196', '=' . $dataColumn.'197');
      $sheet->setCellValue($dataColumn.'197', '=IFERROR( ROUND(' . $dataColumn.'165 / ' . $dataColumn.'133, 2), 0)');
      $sheet->setCellValue($dataColumn.'198', '=IFERROR( ROUND(' . $dataColumn.'166 / ' . $dataColumn.'134, 2), 0)');
      $sheet->setCellValue($dataColumn.'199', '=IFERROR( ROUND(' . $dataColumn.'167 / ' . $dataColumn.'135, 2), 0)');
      $sheet->setCellValue($dataColumn.'200', '=IFERROR( ROUND(' . $dataColumn.'168 / ' . $dataColumn.'136, 2), 0)');
      $sheet->setCellValue($dataColumn.'201', '=IFERROR( ROUND(' . $dataColumn.'169 / ' . $dataColumn.'137, 2), 0)');
      $sheet->setCellValue($dataColumn.'202', '=IFERROR( ROUND(' . $dataColumn.'170 / ' . $dataColumn.'138, 2), 0)');
      $sheet->setCellValue($dataColumn.'203', '=IFERROR( ROUND(' . $dataColumn.'171 / ' . $dataColumn.'139, 2), 0)');
      $sheet->setCellValue($dataColumn.'204', '=' . $dataColumn.'205');
      $sheet->setCellValue($dataColumn.'205', '=IFERROR( ROUND(' . $dataColumn.'173 / ' . $dataColumn.'141, 2), 0)');
      $sheet->setCellValue($dataColumn.'206', '=IFERROR( ROUND(' . $dataColumn.'174 / ' . $dataColumn.'142, 2), 0)');
      $sheet->setCellValue($dataColumn.'207', '=IFERROR( ROUND(' . $dataColumn.'175 / ' . $dataColumn.'143, 2), 0)');
      $sheet->setCellValue($dataColumn.'208', '=IFERROR( ROUND(' . $dataColumn.'176 / ' . $dataColumn.'144, 2), 0)');
      $sheet->setCellValue($dataColumn.'209', '=IFERROR( ROUND(' . $dataColumn.'177 / ' . $dataColumn.'145, 2), 0)');
      $sheet->setCellValue($dataColumn.'210', '=IFERROR( ROUND(' . $dataColumn.'178 / ' . $dataColumn.'146, 2), 0)');
      $sheet->setCellValue($dataColumn.'211', '=IFERROR( ROUND(' . $dataColumn.'179 / ' . $dataColumn.'147, 2), 0)');
      $sheet->setCellValue($dataColumn.'212', '=' . $dataColumn.'213');
      $sheet->setCellValue($dataColumn.'213', '=IFERROR( ROUND(' . $dataColumn.'181 / ' . $dataColumn.'149, 2), 0)');
      $sheet->setCellValue($dataColumn.'214', '=IFERROR( ROUND(' . $dataColumn.'182 / ' . $dataColumn.'150, 2), 0)');
      $sheet->setCellValue($dataColumn.'215', '=IFERROR( ROUND(' . $dataColumn.'183 / ' . $dataColumn.'151, 2), 0)');
      $sheet->setCellValue($dataColumn.'216', '=IFERROR( ROUND(' . $dataColumn.'184 / ' . $dataColumn.'152, 2), 0)');
      $sheet->setCellValue($dataColumn.'217', '=IFERROR( ROUND(' . $dataColumn.'185 / ' . $dataColumn.'153, 2), 0)');
      $sheet->setCellValue($dataColumn.'218', '=IFERROR( ROUND(' . $dataColumn.'186 / ' . $dataColumn.'154, 2), 0)');
      $sheet->setCellValue($dataColumn.'219', '=IFERROR( ROUND(' . $dataColumn.'187 / ' . $dataColumn.'155, 2), 0)');
      $sheet->setCellValue($dataColumn.'220', '=' . $dataColumn.'221');
      $sheet->setCellValue($dataColumn.'221', '=IFERROR( ROUND(' . $dataColumn.'101 / ' . $dataColumn.'12, 4), 0)');
      $sheet->setCellValue($dataColumn.'222', '=IFERROR( ROUND(' . $dataColumn.'102 / ' . $dataColumn.'13, 4), 0)');
      $sheet->setCellValue($dataColumn.'223', '=IFERROR( ROUND(' . $dataColumn.'103 / ' . $dataColumn.'14, 4), 0)');
      $sheet->setCellValue($dataColumn.'224', '=IFERROR( ROUND(' . $dataColumn.'104 / ' . $dataColumn.'15, 4), 0)');
      $sheet->setCellValue($dataColumn.'225', '=IFERROR( ROUND(' . $dataColumn.'105 / ' . $dataColumn.'16, 4), 0)');
      $sheet->setCellValue($dataColumn.'226', '=IFERROR( ROUND(' . $dataColumn.'106 / ' . $dataColumn.'17, 4), 0)');
      $sheet->setCellValue($dataColumn.'227', '=IFERROR( ROUND(' . $dataColumn.'107 / ' . $dataColumn.'18, 4), 0)');
      $sheet->setCellValue($dataColumn.'228', '=' . $dataColumn.'229');
      $sheet->setCellValue($dataColumn.'229', '=IFERROR( ROUND(' . $dataColumn.'109 / ' . $dataColumn.'12, 4), 0)');
      $sheet->setCellValue($dataColumn.'230', '=IFERROR( ROUND(' . $dataColumn.'110 / ' . $dataColumn.'13, 4), 0)');
      $sheet->setCellValue($dataColumn.'231', '=IFERROR( ROUND(' . $dataColumn.'111 / ' . $dataColumn.'14, 4), 0)');
      $sheet->setCellValue($dataColumn.'232', '=IFERROR( ROUND(' . $dataColumn.'112 / ' . $dataColumn.'15, 4), 0)');
      $sheet->setCellValue($dataColumn.'233', '=IFERROR( ROUND(' . $dataColumn.'113 / ' . $dataColumn.'16, 4), 0)');
      $sheet->setCellValue($dataColumn.'234', '=IFERROR( ROUND(' . $dataColumn.'114 / ' . $dataColumn.'17, 4), 0)');
      $sheet->setCellValue($dataColumn.'235', '=IFERROR( ROUND(' . $dataColumn.'115 / ' . $dataColumn.'18, 4), 0)');
      $sheet->setCellValue($dataColumn.'236', '=' . $dataColumn.'237');
      $sheet->setCellValue($dataColumn.'237', '=IFERROR( ROUND(' . $dataColumn.'117 / ' . $dataColumn.'12, 4), 0)');
      $sheet->setCellValue($dataColumn.'238', '=IFERROR( ROUND(' . $dataColumn.'118 / ' . $dataColumn.'13, 4), 0)');
      $sheet->setCellValue($dataColumn.'239', '=IFERROR( ROUND(' . $dataColumn.'119 / ' . $dataColumn.'14, 4), 0)');
      $sheet->setCellValue($dataColumn.'240', '=IFERROR( ROUND(' . $dataColumn.'120 / ' . $dataColumn.'15, 4), 0)');
      $sheet->setCellValue($dataColumn.'241', '=IFERROR( ROUND(' . $dataColumn.'121 / ' . $dataColumn.'16, 4), 0)');
      $sheet->setCellValue($dataColumn.'242', '=IFERROR( ROUND(' . $dataColumn.'122 / ' . $dataColumn.'17, 4), 0)');
      $sheet->setCellValue($dataColumn.'243', '=IFERROR( ROUND(' . $dataColumn.'123 / ' . $dataColumn.'18, 4), 0)');
      $sheet->setCellValue($dataColumn.'244', '=' . $dataColumn.'245');
      $sheet->setCellValue($dataColumn.'245', '=IFERROR( ROUND(' . $dataColumn.'133 / ' . $dataColumn.'12, 4), 0)');
      $sheet->setCellValue($dataColumn.'246', '=IFERROR( ROUND(' . $dataColumn.'134 / ' . $dataColumn.'13, 4), 0)');
      $sheet->setCellValue($dataColumn.'247', '=IFERROR( ROUND(' . $dataColumn.'135 / ' . $dataColumn.'14, 4), 0)');
      $sheet->setCellValue($dataColumn.'248', '=IFERROR( ROUND(' . $dataColumn.'136 / ' . $dataColumn.'15, 4), 0)');
      $sheet->setCellValue($dataColumn.'249', '=IFERROR( ROUND(' . $dataColumn.'137 / ' . $dataColumn.'16, 4), 0)');
      $sheet->setCellValue($dataColumn.'250', '=IFERROR( ROUND(' . $dataColumn.'138 / ' . $dataColumn.'17, 4), 0)');
      $sheet->setCellValue($dataColumn.'251', '=IFERROR( ROUND(' . $dataColumn.'139 / ' . $dataColumn.'18, 4), 0)');
      $sheet->setCellValue($dataColumn.'252', '=' . $dataColumn.'253');
      $sheet->setCellValue($dataColumn.'253', '=IFERROR( ROUND(' . $dataColumn.'141 / ' . $dataColumn.'12, 4), 0)');
      $sheet->setCellValue($dataColumn.'254', '=IFERROR( ROUND(' . $dataColumn.'142 / ' . $dataColumn.'13, 4), 0)');
      $sheet->setCellValue($dataColumn.'255', '=IFERROR( ROUND(' . $dataColumn.'143 / ' . $dataColumn.'14, 4), 0)');
      $sheet->setCellValue($dataColumn.'256', '=IFERROR( ROUND(' . $dataColumn.'144 / ' . $dataColumn.'15, 4), 0)');
      $sheet->setCellValue($dataColumn.'257', '=IFERROR( ROUND(' . $dataColumn.'145 / ' . $dataColumn.'16, 4), 0)');
      $sheet->setCellValue($dataColumn.'258', '=IFERROR( ROUND(' . $dataColumn.'146 / ' . $dataColumn.'17, 4), 0)');
      $sheet->setCellValue($dataColumn.'259', '=IFERROR( ROUND(' . $dataColumn.'147 / ' . $dataColumn.'18, 4), 0)');
      $sheet->setCellValue($dataColumn.'260', '=' . $dataColumn.'261');
      $sheet->setCellValue($dataColumn.'261', '=IFERROR( ROUND(' . $dataColumn.'149 / ' . $dataColumn.'12, 4), 0)');
      $sheet->setCellValue($dataColumn.'262', '=IFERROR( ROUND(' . $dataColumn.'150 / ' . $dataColumn.'13, 4), 0)');
      $sheet->setCellValue($dataColumn.'263', '=IFERROR( ROUND(' . $dataColumn.'151 / ' . $dataColumn.'14, 4), 0)');
      $sheet->setCellValue($dataColumn.'264', '=IFERROR( ROUND(' . $dataColumn.'152 / ' . $dataColumn.'15, 4), 0)');
      $sheet->setCellValue($dataColumn.'265', '=IFERROR( ROUND(' . $dataColumn.'153 / ' . $dataColumn.'16, 4), 0)');
      $sheet->setCellValue($dataColumn.'266', '=IFERROR( ROUND(' . $dataColumn.'154 / ' . $dataColumn.'17, 4), 0)');
      $sheet->setCellValue($dataColumn.'267', '=IFERROR( ROUND(' . $dataColumn.'155 / ' . $dataColumn.'18, 4), 0)');
      $sheet->setCellValue($dataColumn.'268', '=' . $dataColumn.'269');
      $sheet->setCellValue($dataColumn.'269', '=ROUND(' . $dataColumn.'132 / ' . $dataColumn.'100, 4)');
      $sheet->setCellValue($dataColumn.'270', '=' . $dataColumn.'309');
      $sheet->setCellValue($dataColumn.'271', '=' . $dataColumn.'354');
      $sheet->setCellValue($dataColumn.'272', '=' . $dataColumn.'398');
      $sheet->setCellValue($dataColumn.'273', '=' . $dataColumn.'445');
      $sheet->setCellValue($dataColumn.'274', '=' . $dataColumn.'494');
      $sheet->setCellValue($dataColumn.'275', '=' . $dataColumn.'532');

      $sheet->getStyle($dataColumn.'2:' . $dataColumn.'542')->getFont()->setBold(true);

      // Границы всему
      $highestColumn = $sheet->getHighestColumn();
      $highestRow = $sheet->getHighestRow();
      $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray(
        [
          'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'], // Чёрный цвет границ
            ],
          ]
        ]
      );

      // Формат ячеек
      $sheet->getStyle('B305:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '319')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B280:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '280')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B286:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '288')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B369:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '369')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B375:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '377')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B394:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '408')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B415:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '415')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B421:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '423')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B440:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '455')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B462:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '462')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B468:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '470')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B487:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '505')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B509:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '509')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B511:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '511')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B528:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '542')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B19:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '26')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B70:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '91')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B223:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '275')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B324:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '324')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B331:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '333')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B350:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '364')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->getStyle('B220:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '227')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

      // $sheet->getStyle('B277:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '278')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      // $sheet->getStyle('B321:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '323')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      // $sheet->getStyle('B366:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '368')->getNumberFormat()->setFormatCode('"$"#,##0.00');

      $sheet->getStyle('B27:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '69')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B156:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '219')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B281:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '285')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B297:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '304')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B325:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '330')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B342:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '349')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B370:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '374')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B386:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '393')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B414:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '414')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B416:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '420')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B432:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '439')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B461:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '461')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B463:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '467')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B479:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '486')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B510:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '510')->getNumberFormat()->setFormatCode('"$"#,##0.00');
      $sheet->getStyle('B520:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '527')->getNumberFormat()->setFormatCode('"$"#,##0.00');


      // Закрепляем первую строку
      $sheet->freezePane('A2');

      // Сворачиваем строки
      for ($row = 4; $row <= 10; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 12; $row <= 18; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 20; $row <= 26; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 28; $row <= 33; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 35; $row <= 40; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 42; $row <= 47; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 49; $row <= 54; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 56; $row <= 61; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 63; $row <= 69; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 71; $row <= 77; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 79; $row <= 84; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 86; $row <= 91; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 93; $row <= 99; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 101; $row <= 107; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 109; $row <= 115; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 117; $row <= 123; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 125; $row <= 131; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 133; $row <= 139; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 141; $row <= 147; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 149; $row <= 155; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 157; $row <= 163; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 165; $row <= 171; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 173; $row <= 179; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 181; $row <= 187; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 189; $row <= 195; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 197; $row <= 203; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 205; $row <= 211; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 213; $row <= 219; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 221; $row <= 227; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 229; $row <= 235; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 237; $row <= 243; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 245; $row <= 251; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 253; $row <= 259; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 261; $row <= 267; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 269; $row <= 275; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 277; $row <= 319; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 321; $row <= 364; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 366; $row <= 408; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 410; $row <= 455; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 457; $row <= 505; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
      for ($row = 507; $row <= 542; $row++) {
        $sheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }

      $sheet->getColumnDimension($dataColumn)->setWidth('12');
      $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

      // Сворачиваем колонки
      $currentToCollapseIndex = Coordinate::columnIndexFromString($dataColumn);
      $startToCollapseIndex   = $currentToCollapseIndex - (count($weeks) + 1);
      $endToCollapseIndex     = $currentToCollapseIndex - 2;

      $monthColumnsToCollapse = [];
      for ($i = $startToCollapseIndex; $i <= $endToCollapseIndex; $i++) {
        $monthColumnsToCollapse[] = Coordinate::stringFromColumnIndex($i);
      }

      foreach ($monthColumnsToCollapse as $col) {
        $sheet->getColumnDimension($col)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }

      $monthCounter++;
    }

    $sheet->getStyle('B2:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString('B') + $totalColumns) . '550')->setConditionalStyles([$conditionalMinus]);

    $sheet->getStyle('A1:' . $dataColumn . '1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A:' . $dataColumn)->getAlignment()->setWrapText(true);

    // Добавляем лист CPC
    $cpcSheet = $spreadsheet->createSheet();
    $cpcSheet->setTitle('CPC');

    self::generateMarketingReportCpc($cpcSheet,$data,$cpcdata,$rowsStyles,$mreport);

    $writer = new Xlsx($spreadsheet);
    $writer->save(__DIR__ . '/../web/tmpDocs/' . $fileName);
    $xlsData = ob_get_contents();
    ob_end_clean();
    return (object)array('file' => $fileName);
  }

  private function getMonthsTitles($monthNum)
  {
    $months = [
        '01' => 'Январь',
        '02' => 'Февраль',
        '03' => 'Март',
        '04' => 'Апрель',
        '05' => 'Май',
        '06' => 'Июнь',
        '07' => 'Июль',
        '08' => 'Август',
        '09' => 'Сентябрь',
        '10' => 'Октябрь',
        '11' => 'Ноябрь',
        '12' => 'Декабрь',
    ];

    return $months[$monthNum];
  }

  private function columnFirstCpcMarketingAllReportsBasics()
  {
    return [
      0 => ['Показы','cpc_data_1_1','blue2','peace'],
      1 => ['Клики','cpc_data_1_2','blue2','peace'],
      2 => ['Стоимость рекламы, $','cpc_data_1_3','blue2',''],
      3 => ['Добавление в корзину, шт','cpc_data_1_4','blue2','peace'],
      4 => ['WhatsApp / переходы, шт','cpc_data_1_5','blue2','peace'],
      5 => ['Binotel / звонки, шт','cpc_data_1_6','blue2','peace'],
      6 => ['Покупки, шт',false,'green2','pokupki_shtuki'],
      7 => ['Purchase / покупки на сайте, шт','cpc_data_1_7','green2','peace'],
      8 => ['Binotel / покупки, шт','cpc_data_1_8','green2','peace'],
      9 => ['WhatsApp / покупки, шт',false,'green2','pokupki_whatsapp_peace'],
      10 => ['Покупки, $',false,'green2','pokupki_dollars'],
      11 => ['Purchase / покупки на сайте, $','cpc_data_1_10','green2','dollars'],
      12 => ['Binotel / ≧ покупки, $','cpc_data_1_11','green2','dollars'],
      13 => ['WhatsApp / ≧ покупки, $',false,'green2', 'pokupki_whatsapp_dollars'],
      14 => ['% отказов',false,'gray', 'percent_otkazi'],
      15 => ['Стоимость конверсии',false,'gray', 'cost_konversiya'],
      16 => ['Стоимость лида MQL (заявка или стоимость клика)',false,'gray', 'cost_lead_mql'],
      17 => ['Стоимость лида SQL (покупка)',false,'gray', 'cost_lead_sql'],
      18 => ['CTR',false,'gray', 'ctr'],
      19 => ['ROMI',false,'gray', 'romi'],
      20 => ['Клик в SQL',false,'gray', 'click_sql'],
      21 => ['SQL в MQL',false,'gray', 'click_mql']
    ];
  }

  private function columnFirstCpcMarketingOneReportBasics()
  {
    return [
      0 => ['Показы',                             'cpc_data_1_1',     ''],
      1 => ['Клики',                              'cpc_data_1_2',     ''],
      2 => ['Стоимость рекламы, $',               'cpc_data_1_3',     ''],
      3 => ['Всего конверсий в шт',               false,              'green', 'conversion_piece'],
      4 => ['Добавление в корзину, шт',           'cpc_data_1_4',     ''],
      5 => ['WhatsApp / переходы, шт',            'cpc_data_1_5',     ''],
      6 => ['Binotel / звонки, шт',               'cpc_data_1_6',     ''],
      7 => ['Покупки, шт',                        false,              'green', 'purchase_piece'],
      8 => ['Purchase / покупки на сайте, шт',    'cpc_data_1_7',     ''],
      9 => ['Binotel / покупки, шт',              'cpc_data_1_8',     ''],
      10 => ['WhatsApp / покупки, шт',            false,              '', 'pokupki_whatsapp_peace'],
      11 => ['Покупки, Тенге',                    false,              'green', 'purchase_tenge'],
      12 => ['Purchase / покупки на сайте, Тенге','cpc_data_1_10',    ''],
      13 => ['Binotel / ≧ покупки, Тенге',        'cpc_data_1_11',    ''],
      14 => ['Покупки, $',                        false,              'green', 'purchase_dollar'],
      15 => ['Purchase / покупки на сайте, $',    'cpc_data_1_10',    '', 'dollar'],
      16 => ['Binotel / ≧ покупки, $',            'cpc_data_1_11',    '', 'dollar'],
      17 => ['WhatsApp / ≧ покупки, $',           false,              '', 'whatsapp_dollar'],
      18 => ['% отказов',                         false,              '', 'percent_otkazi'],
      19 => ['Стоимость конверсии',               false,              '', 'conversion_cost'],
      20 => ['Стоимость лида MQL (заявка или стоимость клика)',false, '', 'cost_lead_mql'],
      21 => ['Стоимость лида SQL (покупка)',      false,              '', 'cost_lead_sql'],
      22 => ['CTR',                               false,              '', 'ctr'],
      23 => ['ROMI',                              false,              '', 'romi'],
      24 => ['Клик в SQL',                        false,              '', 'click_sql'],
      25 => ['SQL в MQL',                         false,              '', 'click_mql']
    ];
  }

  private function getCpcProjectsTypesStyles()
  {
    $styles = array();

    $styles[1] = [ // Поиск +
                    'fill' => [
                      'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                      'startColor' => ['rgb' => 'b7e1cd'],
                    ],
                  ];

    $styles[2] = [ // Умная кампания
                    'fill' => [
                      'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                      'startColor' => ['rgb' => 'cfe2f3'],
                    ],
                  ];

    $styles[3] = [ // Торговая кампания +
                    'fill' => [
                      'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                      'startColor' => ['rgb' => 'fce8b2'],
                    ],
                  ];

    $styles[4] = [ // Баннеры +
                    'fill' => [
                      'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                      'startColor' => ['rgb' => 'b4a7d6'],
                    ],
                  ];

    $styles[5] = [ // Максимальная эффективность +
                    'fill' => [
                      'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                      'startColor' => ['rgb' => 'f4c7c3'],
                    ],
                  ];


    return $styles;
  }

  private function generateMarketingReportCpc($cpcSheet,$basicdata,$cpcdata,$rowsStyles,$mreport)
  {
    // Стили для проектов
    $cpcRowsStyles = self::getCpcProjectsTypesStyles();

    // Общие строки Cpc
    $firstColumn = self::columnFirstCpcMarketingAllReportsBasics();
    $firstColumnCpcProject = self::columnFirstCpcMarketingOneReportBasics();

    // Получить нужные недели и месяцы
    $weeksMonthsList = [];
    foreach ($cpcdata as $cpcProject) {
      foreach ($cpcProject->cpc_project_data as $cpcProjectData) {
        $monthDate = new \DateTime($cpcProjectData->year.'-'.$cpcProjectData->month.'-01');
        $weeks = $mreport->getWeeksFromFirstWednesday($monthDate);

        $weeksMonthsList = array_merge($weeksMonthsList,$weeks);
      }
    }
    $weeksMonthsList = array_map('unserialize', array_unique(array_map('serialize', $weeksMonthsList)));
    $weeksMonthsList = array_reduce($weeksMonthsList, function($result, $item) { $result[$item['month']][] = $item; return $result; }, []);
    ksort($weeksMonthsList);

    // Недели и месяцы
    $dataColumn = 'C';

    foreach ($weeksMonthsList as $monthId => $month) {

      foreach ($month as $week) {
        $weekStart = new \DateTime($week['start']);
        $weekEnd = new \DateTime($week['end']);

        $monthNum = $week['month'];
        $cpcSheet->setCellValue($dataColumn.'1', $weekStart->format('d.m.Y') . '-' . $weekEnd->format('d.m.Y'));
        $cpcSheet->getColumnDimension($dataColumn)->setWidth('12');
        $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);
      }
      $cpcSheet->setCellValue($dataColumn.'1', strftime('%B', strtotime('2025-' . $monthId . '-01')));
      $cpcSheet->getStyle($dataColumn.'1')->getFont()->setBold(true);
      $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

      // Сворачиваем колонки
      $currentToCollapseIndex = Coordinate::columnIndexFromString($dataColumn);
      $startToCollapseIndex   = $currentToCollapseIndex - (count($month) + 1);
      $endToCollapseIndex     = $currentToCollapseIndex - 2;

      $monthColumnsToCollapse = [];
      for ($i = $startToCollapseIndex; $i <= $endToCollapseIndex; $i++) {
        $monthColumnsToCollapse[] = Coordinate::stringFromColumnIndex($i);
      }


      foreach ($monthColumnsToCollapse as $col) {
        $cpcSheet->getColumnDimension($col)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }
    }

    $dataRow = 2;

    foreach ($firstColumn as $column) {

      $dataColumn = 'A';
      $cpcSheet->setCellValue($dataColumn.$dataRow, $column[0]);
      if(!empty($column[2])) { $cpcSheet->getStyle($dataColumn.$dataRow)->applyFromArray($rowsStyles->{$column[2]}); };
      $cpcSheet->mergeCells($dataColumn.$dataRow . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1).$dataRow);
      $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 2);

      $blockRowStart = $dataRow;

      for ($row = $dataRow+1; $row <= $dataRow+count($cpcdata); $row++) {
        $cpcSheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }

      foreach ($weeksMonthsList as $monthId => $month){
        $monthBasicData = current(array_filter($basicdata, fn($bd) => $bd->month == $monthId));

        foreach ($month as $wwkey => $week) {

          $monthFillingDefault = true;
          switch($column[3]){
            case 'pokupki_whatsapp_dollars':
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=SUM(' . $dataColumn . ($dataRow+1) . ':' . $dataColumn . ($dataRow+count($cpcdata)) . ')');
              $monthFillingDefault = false;
              break;
            case 'percent_otkazi':
              $row1 = $dataRow - ( count($cpcdata) * 11 + 22 );
              $row2 = $dataRow - ( count($cpcdata) * 10 + 20 );
              $row3 = $dataRow - ( count($cpcdata) * 9 + 18 );
              $row4 = $dataRow - ( count($cpcdata) * 13 + 26 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( (SUM(' . $dataColumn.$row1 . ',' . $dataColumn.$row2 . ', ' . $dataColumn.$row3 . ')) / ' . $dataColumn.$row4 . ', 4) , 0)');
              $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
              $monthFillingDefault = false;
              break;
            case 'cost_konversiya':
              $row1 = $dataRow - ( count($cpcdata) * 13 + 26 );
              $row2 = $dataRow - ( count($cpcdata) * 12 + 24 );
              $row3 = $dataRow - ( count($cpcdata) * 11 + 22 );
              $row4 = $dataRow - ( count($cpcdata) * 10 + 20 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ' . $dataColumn.$row1 . ' / (SUM(' . $dataColumn.$row2 . ',' . $dataColumn.$row3 . ', ' . $dataColumn.$row4 . ')), 2) , 0)');
              $monthFillingDefault = false;
              break;
            case 'cost_lead_mql':
              $row1 = $dataRow - ( count($cpcdata) * 15 + 30 );
              $row2 = $dataRow - ( count($cpcdata) * 14 + 28 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ' . $dataColumn.$row2 . ' / ' . $dataColumn.$row1 . ', 2) , 0)');
              $monthFillingDefault = false;
              break;
            case 'cost_lead_sql':
              $row1 = $dataRow - ( count($cpcdata) * 15 + 30 );
              $row2 = $dataRow - ( count($cpcdata) * 10 + 20 );
              $row3 = $dataRow - ( count($cpcdata) * 9 + 18 );
              $row4 = $dataRow - ( count($cpcdata) * 8 + 16 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ' . $dataColumn.$row1 . ' / SUM(' . $dataColumn.$row2 . ',' . $dataColumn.$row3 . ',' . $dataColumn.$row4 . '), 2) , 0)');
              $monthFillingDefault = false;
              break;
            case 'ctr':
              $row1 = $dataRow - ( count($cpcdata) * 17 + 34 );
              $row2 = $dataRow - ( count($cpcdata) * 18 + 36 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ' . $dataColumn.$row1 . ' / ' . $dataColumn.$row2 . ', 4) , 0)');
              $monthFillingDefault = false;
              break;
            case 'romi':
              $row1 = $dataRow - ( count($cpcdata) * 8 + 16 );
              $row2 = $dataRow - ( count($cpcdata) * 7 + 14 );
              $row3 = $dataRow - ( count($cpcdata) * 6 + 12 );
              $row4 = $dataRow - ( count($cpcdata) * 17 + 34 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ( SUM(' . $dataColumn.$row1 . ',' . $dataColumn.$row2 . ', ' . $dataColumn.$row3 . ' ) - ' . $dataColumn.$row4 . ' ) / ' . $dataColumn.$row4 . ', 4) , 0)');
              $monthFillingDefault = false;
              break;
            case 'click_sql':
              $row1 = $dataRow - ( count($cpcdata) * 13 + 26 );
              $row2 = $dataRow - ( count($cpcdata) * 12 + 24 );
              $row3 = $dataRow - ( count($cpcdata) * 11 + 22 );
              $row4 = $dataRow - ( count($cpcdata) * 19 + 38 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( SUM(' . $dataColumn.$row1 . ',' . $dataColumn.$row2 . ', ' . $dataColumn.$row3 . ' ) / ' . $dataColumn.$row4 . ', 4) , 0)');
              $monthFillingDefault = false;
              break;
            case 'click_mql':
              $row1 = $dataRow - ( count($cpcdata) * 14 + 28 );
              $row2 = $dataRow - ( count($cpcdata) * 13 + 26 );
              $row3 = $dataRow - ( count($cpcdata) * 12 + 24 );
              $row4 = $dataRow - ( count($cpcdata) * 18 + 36 );
              $row5 = $dataRow - ( count($cpcdata) * 17 + 34 );
              $row6 = $dataRow - ( count($cpcdata) * 16 + 32 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( SUM(' . $dataColumn.$row1 . ',' . $dataColumn.$row2 . ', ' . $dataColumn.$row3 . ' ) / SUM( ' . $dataColumn.$row4 . ',' . $dataColumn.$row5 . ', ' . $dataColumn.$row6 . '), 4) , 0)');
              $monthFillingDefault = false;
              break;
            default:
              $cpcSheet->setCellValue($dataColumn.$dataRow, '=SUM(' . $dataColumn.$dataRow+1 . ':' . $dataColumn.$dataRow+count($cpcdata) . ')');
          }

          switch($column[3]){
            case 'pokupki_whatsapp_dollars':
            case 'purchase_dollar':
            case 'pokupki_dollars':
            case 'cost_konversiya':
            case 'cost_lead_mql':
            case 'cost_lead_sql':
            case 'dollars':
              $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
              break;
            case 'percent_otkazi':
              $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
              break;
          }

          if(!empty($column[2])) { $cpcSheet->getStyle($dataColumn.$dataRow)->applyFromArray($rowsStyles->{$column[2]}); };
          $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

        }

        if($monthFillingDefault){
          $monthFirst = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - count($month));
          $monthEnd = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1);
          $cpcSheet->setCellValue($dataColumn.$dataRow, '=IF( SUM( ' . $monthFirst.$dataRow . ':' . $monthEnd.$dataRow . ' ) = SUM( ' . $dataColumn.$dataRow+1 . ':' . $dataColumn.$dataRow+count($cpcdata) . ' ), SUM( ' . $dataColumn.$dataRow+1 . ':' . $dataColumn.$dataRow+count($cpcdata) . ' ), "ошибка" )');
        }
        else {
          switch($column[3]){
            case 'pokupki_whatsapp_dollars':
              $whatsAppPurchaseCountRow = $dataRow - ( count($cpcdata) * 4 + 8);
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=SUM(' . $dataColumn . ($dataRow+1) . ':' . $dataColumn . ($dataRow+count($cpcdata)) . ')');
              // $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( (' . ( (float)$this->safeExplode(':::', $monthBasicData->data_8_4)[$wwkey] / (float)$monthBasicData->data_1_2 ) . ' / ' . (float)$this->safeExplode(':::', $monthBasicData->data_8_3)[$wwkey] . ') * ' . $dataColumn.$whatsAppPurchaseCountRow . ', 2) , 0)');
              break;
            case 'percent_otkazi':
              $row1 = $dataRow - ( count($cpcdata) * 11 + 22 );
              $row2 = $dataRow - ( count($cpcdata) * 10 + 20 );
              $row3 = $dataRow - ( count($cpcdata) * 9 + 18 );
              $row4 = $dataRow - ( count($cpcdata) * 13 + 26 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( (SUM(' . $dataColumn.$row1 . ',' . $dataColumn.$row2 . ', ' . $dataColumn.$row3 . ')) / ' . $dataColumn.$row4 . ', 4) , 0)');
              $monthFillingDefault = false;
              break;
            case 'cost_konversiya':
              $row1 = $dataRow - ( count($cpcdata) * 13 + 26 );
              $row2 = $dataRow - ( count($cpcdata) * 12 + 24 );
              $row3 = $dataRow - ( count($cpcdata) * 11 + 22 );
              $row4 = $dataRow - ( count($cpcdata) * 10 + 20 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ' . $dataColumn.$row1 . ' / (SUM(' . $dataColumn.$row2 . ',' . $dataColumn.$row3 . ', ' . $dataColumn.$row4 . ')), 2) , 0)');
              $monthFillingDefault = false;
              break;
            case 'cost_lead_mql':
              $row1 = $dataRow - ( count($cpcdata) * 15 + 30 );
              $row2 = $dataRow - ( count($cpcdata) * 14 + 28 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ' . $dataColumn.$row2 . ' / ' . $dataColumn.$row1 . ', 2) , 0)');
              $monthFillingDefault = false;
              break;
            case 'cost_lead_sql':
              $row1 = $dataRow - ( count($cpcdata) * 15 + 30 );
              $row2 = $dataRow - ( count($cpcdata) * 10 + 20 );
              $row3 = $dataRow - ( count($cpcdata) * 9 + 18 );
              $row4 = $dataRow - ( count($cpcdata) * 8 + 16 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ' . $dataColumn.$row1 . ' / SUM(' . $dataColumn.$row2 . ',' . $dataColumn.$row3 . ',' . $dataColumn.$row4 . '), 2) , 0)');
              $monthFillingDefault = false;
              break;
            case 'ctr':
              $row1 = $dataRow - ( count($cpcdata) * 17 + 34 );
              $row2 = $dataRow - ( count($cpcdata) * 18 + 36 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ' . $dataColumn.$row1 . ' / ' . $dataColumn.$row2 . ', 4) , 0)');
              $monthFillingDefault = false;
              break;
            case 'romi':
              $row1 = $dataRow - ( count($cpcdata) * 8 + 16 );
              $row2 = $dataRow - ( count($cpcdata) * 7 + 14 );
              $row3 = $dataRow - ( count($cpcdata) * 6 + 12 );
              $row4 = $dataRow - ( count($cpcdata) * 17 + 34 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( ( SUM(' . $dataColumn.$row1 . ',' . $dataColumn.$row2 . ', ' . $dataColumn.$row3 . ' ) - ' . $dataColumn.$row4 . ' ) / ' . $dataColumn.$row4 . ', 4) , 0)');
              $monthFillingDefault = false;
              break;
            case 'click_sql':
              $row1 = $dataRow - ( count($cpcdata) * 13 + 26 );
              $row2 = $dataRow - ( count($cpcdata) * 12 + 24 );
              $row3 = $dataRow - ( count($cpcdata) * 11 + 22 );
              $row4 = $dataRow - ( count($cpcdata) * 19 + 38 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( SUM(' . $dataColumn.$row1 . ',' . $dataColumn.$row2 . ', ' . $dataColumn.$row3 . ' ) / ' . $dataColumn.$row4 . ', 4) , 0)');
              $monthFillingDefault = false;
              break;
            case 'click_mql':
              $row1 = $dataRow - ( count($cpcdata) * 14 + 28 );
              $row2 = $dataRow - ( count($cpcdata) * 13 + 26 );
              $row3 = $dataRow - ( count($cpcdata) * 12 + 24 );
              $row4 = $dataRow - ( count($cpcdata) * 18 + 36 );
              $row5 = $dataRow - ( count($cpcdata) * 17 + 34 );
              $row6 = $dataRow - ( count($cpcdata) * 16 + 32 );
              $cpcSheet->setCellValue($dataColumn.$dataRow,'=IFERROR( ROUND( SUM(' . $dataColumn.$row1 . ',' . $dataColumn.$row2 . ', ' . $dataColumn.$row3 . ' ) / SUM( ' . $dataColumn.$row4 . ',' . $dataColumn.$row5 . ', ' . $dataColumn.$row6 . '), 4) , 0)');
              $monthFillingDefault = false;
              break;
          }

          switch($column[3]){
            case 'pokupki_whatsapp_dollars':
            case 'purchase_dollar':
            case 'pokupki_dollars':
            case 'cost_konversiya':
            case 'cost_lead_mql':
            case 'cost_lead_sql':
            case 'dollars':
              $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
              break;
            case 'percent_otkazi':
              $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
              break;
          }
        }
        if(!empty($column[2])) { $cpcSheet->getStyle($dataColumn.$dataRow)->applyFromArray($rowsStyles->{$column[2]}); };
        $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

      }

      // Перечисляем данные строки проектов
      $cpcDataRow = $dataRow+1;
      $countProjects = count($cpcdata);

      foreach ($cpcdata as $cpckey => $cpcproject) {
        $cpcDataColumn = 'A';
        $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, $cpcproject->cpc_project_title);
        $cpcSheet->getStyle($cpcDataColumn.$cpcDataRow)->applyFromArray($cpcRowsStyles[$cpcproject->cpc_project_type]);
        $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
        $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, $cpcproject->cpc_project_pid);
        $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);

        // Каждая неделя проекта
        foreach ($cpcproject->cpc_project_data as $cpcprojectdata) {
          $projectsWeeks = $weeksMonthsList[$cpcprojectdata->month];
          $monthBasicData = current(array_filter($basicdata, fn($bd) => $bd->month == $cpcprojectdata->month));

          if($column[1]){
            $weekData = $this->safeExplode(':::',$cpcprojectdata->{$column[1]});

            $monthFirst = $cpcDataColumn;
            $monthEnd = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + count($projectsWeeks) - 1);
            foreach ($projectsWeeks as $wkey => $week) {
              switch($column[3]){
                case 'dollars':
                  // file_put_contents(__DIR__ . '/test.txt',print_r($wkey,true) . PHP_EOL,FILE_APPEND);
                  // file_put_contents(__DIR__ . '/test.txt',print_r($week,true) . PHP_EOL,FILE_APPEND);
                  // file_put_contents(__DIR__ . '/test.txt',print_r($weekData,true) . PHP_EOL,FILE_APPEND);
                  // file_put_contents(__DIR__ . '/test.txt',print_r($monthBasicData->data_1_2,true) . PHP_EOL . PHP_EOL . '-----------' . PHP_EOL . PHP_EOL,FILE_APPEND);
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=ROUND(' . $weekData[$wkey] . ' / ' . $monthBasicData->data_1_2 . ', 2)');
                  $cpcSheet->getStyle($cpcDataColumn.$cpcDataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                  break;
                default:
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, $weekData[$wkey]);
              }
              $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
            }

            $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=SUM(' . $monthFirst.$cpcDataRow . ':' . $monthEnd.$cpcDataRow . ')');

            switch($column[3]){
              case 'dollars':
                $cpcSheet->getStyle($cpcDataColumn.$cpcDataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                break;
            }
            $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
          }
          else {
            // Подстановка из других ячеек
            switch($column[3]){
              case 'pokupki_whatsapp_peace':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 13) + 24 + 2 + (27 * $cpckey) + 12));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 13) + 24 + 2 + (27 * $cpckey) + 12));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'pokupki_shtuki':
              case 'pokupki_dollars':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=SUM(' . $cpcDataColumn.($cpcDataRow+$countProjects+2) . ',' . $cpcDataColumn.($cpcDataRow+($countProjects * 2)+4) . ',' . $cpcDataColumn.($cpcDataRow+($countProjects * 3)+6) . ')');
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=SUM(' . $cpcDataColumn.($cpcDataRow+$countProjects+2) . ',' . $cpcDataColumn.($cpcDataRow+($countProjects * 2)+4) . ',' . $cpcDataColumn.($cpcDataRow+($countProjects * 3)+6) . ')');
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'pokupki_whatsapp_dollars':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 9) + 16 + 2 + (27 * $cpckey) + 19 ));
                  $cpcSheet->getStyle($cpcDataColumn.$cpcDataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 9) + 16 + 2 + (27 * $cpckey) + 19 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'percent_otkazi':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 8) + 14 + 2 + (27 * $cpckey) + 20 ));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 8) + 14 + 2 + (27 * $cpckey) + 20 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                $cpcSheet->getStyle('C' . $cpcDataRow . ':' . $cpcDataColumn . $cpcDataRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                break;
              case 'cost_konversiya':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 7) + 12 + 2 + (27 * $cpckey) + 21 ));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 7) + 12 + 2 + (27 * $cpckey) + 21 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'cost_lead_mql':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 6) + 10 + 2 + (27 * $cpckey) + 22 ));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 6) + 10 + 2 + (27 * $cpckey) + 22 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'cost_lead_sql':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 5) + 8 + 2 + (27 * $cpckey) + 23 ));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 5) + 8 + 2 + (27 * $cpckey) + 23 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'ctr':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 4) + 6 + 2 + (27 * $cpckey) + 24 ));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 4) + 6 + 2 + (27 * $cpckey) + 24 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'romi':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 3) + 4 + 2 + (27 * $cpckey) + 25 ));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 3) + 4 + 2 + (27 * $cpckey) + 25 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'click_sql':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 2) + 2 + 2 + (27 * $cpckey) + 26 ));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 2) + 2 + 2 + (27 * $cpckey) + 26 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
              case 'click_mql':
                foreach ($projectsWeeks as $wkey => $week) {
                  $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 1) + 2 + (27 * $cpckey) + 27 ));
                  $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                }
                $cpcSheet->setCellValue($cpcDataColumn.$cpcDataRow, '=' . $cpcDataColumn . ( $cpcDataRow + ($countProjects * 1) + 2 + (27 * $cpckey) + 27 ));
                $cpcDataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($cpcDataColumn) + 1);
                break;
            }

          }
        }
        $cpcDataRow++;
      }

      $blockRowEnd = $cpcDataRow-1;

      switch($column[3]){
        case 'percent_otkazi':
        case 'ctr':
        case 'romi':
        case 'click_sql':
        case 'click_mql':
          $cpcSheet->getStyle('A' . $blockRowStart . ':' . $dataColumn . $blockRowEnd)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
          break;
        case 'pokupki_dollars':
        case 'purchase_dollar':
        case 'cost_lead_mql':
        case 'cost_lead_sql':
        case 'pokupki_whatsapp_dollars':
          $cpcSheet->getStyle('A' . $blockRowStart . ':' . $dataColumn . $blockRowEnd)->getNumberFormat()->setFormatCode('"$"#,##0.00');
          break;
      }

      $cpcSheet->mergeCells('A'.$cpcDataRow.':'.Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn)-1).$cpcDataRow);
      $dataRow = $cpcDataRow+1;
    }

    $cpcSheet->getStyle('A'.$dataRow.':'.Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn)-1).$dataRow)->applyFromArray($rowsStyles->green);

    // Отдельно каждый CPC
    $dataRow = $dataRow+2;
    foreach ($cpcdata as $cpcProject) {
      $collapseRow = $dataRow+1;
      $dataColumn = 'A';
      $cpcSheet->setCellValue($dataColumn.$dataRow, $cpcProject->cpc_project_title);
      $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);
      $cpcSheet->setCellValue($dataColumn.$dataRow, $cpcProject->cpc_project_pid);
      $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

      foreach ($weeksMonthsList as $monthId => $month) {

        foreach ($month as $week) {
          $weekStart = new \DateTime($week['start']);
          $weekEnd = new \DateTime($week['end']);

          $cpcSheet->setCellValue($dataColumn.$dataRow, '=' . $dataColumn.$dataRow+1);
          $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);
        }

        $cpcSheet->setCellValue($dataColumn.$dataRow, '=' . $dataColumn.$dataRow+1);
        $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

      }

      $cpcSheet->getStyle('A'.$dataRow.':'.Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn)-1).$dataRow)->applyFromArray($cpcRowsStyles[$cpcProject->cpc_project_type]);
      $dataRow++;

      // Данные cpc-проекта
      foreach ($firstColumnCpcProject as $prcolumn) {
        $dataColumn = 'A';
        $cpcSheet->setCellValue($dataColumn.$dataRow, $prcolumn[0]);
        $finishColumn = array_sum(array_map('count', $weeksMonthsList)) + count($weeksMonthsList) + 1;
        if(!empty($prcolumn[2])) { $cpcSheet->getStyle($dataColumn.$dataRow . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn)+$finishColumn) . $dataRow )->applyFromArray($rowsStyles->{$prcolumn[2]}); };
        $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 2);

        foreach ($cpcProject->cpc_project_data as $cpcprojectdata) {
          foreach ($weeksMonthsList as $monthId => $month) {
            $monthBasicData = current(array_filter($basicdata, fn($bd) => $bd->month == $monthId));

            if($cpcprojectdata->month == $monthId){

              $weeksStartCell = $dataColumn;
              $dollarCell = false;

              foreach ($month as $wwkey => $week){
                if($monthId == $week['month']){
                  if($prcolumn[1]){
                    $classicMonthData = true;
                    $projectdataArr = $this->safeExplode(':::', $cpcprojectdata->{$prcolumn[1]});

                    if(isset($prcolumn[3])){
                      switch($prcolumn[3]){
                        case 'dollar':
                          $cpcSheet->setCellValue($dataColumn.$dataRow, '=ROUND(' . ($projectdataArr[$wwkey] / $monthBasicData->data_1_2) . ', 2)');
                          $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                          $dollarCell = true;
                          break;
                      }
                    }
                    else {
                      $cpcSheet->setCellValue($dataColumn.$dataRow, $projectdataArr[$wwkey]);
                    }
                    $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

                  }
                  else {
                    $gotoClassic = true;
                    $classicMonthData = false;

                    switch($prcolumn[3]){
                      case 'conversion_piece':
                      case 'purchase_piece':
                        $downCells = 3;
                        break;
                      case 'purchase_dollar':
                        $dollarCell = true;
                        $downCells = 3;
                        break;
                      case 'purchase_tenge':
                        $downCells = 2;
                        break;
                      case 'pokupki_whatsapp_peace':
                        $gotoClassic = false;
                        $denominator = $this->safeExplode(':::', $monthBasicData->data_8_3)[$wwkey] ?? 0;
                        $classicMonthData = true;
                        if($denominator == 0){
                          $notClassicFormula = '0';
                        }
                        else {
                          $notClassicFormula = '=IFERROR( ROUND( ' . $dataColumn.$dataRow-5 . ' * ' . ( $this->safeExplode(':::', $monthBasicData->data_8_3)[$wwkey] / $this->safeExplode(':::', $monthBasicData->data_8_1)[$wwkey] ) . ' , 2), 0)';
                        }
                        break;
                      case 'whatsapp_dollar':
                        $gotoClassic = false;
                        $classicMonthData = true;
                        $dollarCell = true;
                        $denominator = $this->safeExplode(':::', $monthBasicData->data_8_3)[$wwkey] ?? 0;
                        if($denominator == 0){
                          $notClassicFormula = '0';
                        }
                        else {
                          $notClassicFormula = '=IFERROR( ROUND( ' . ( ($this->safeExplode(':::', $monthBasicData->data_8_4)[$wwkey] / $monthBasicData->data_1_2) / $this->safeExplode(':::', $monthBasicData->data_8_3)[$wwkey] )  . ' * ' . $dataColumn.$dataRow-7 . ', 2) , 0)';
                        }
                        break;
                      case 'percent_otkazi':
                        $gotoClassic = false;
                        $notClassicFormula = '=IFERROR( ROUND( (' . $dataColumn.$dataRow-17 . ' - ' . $dataColumn.$dataRow-15 . ') / ' . $dataColumn.$dataRow-17 . ' , 4), 0)';
                        break;
                      case 'conversion_cost':
                        $gotoClassic = false;
                        $notClassicFormula = '=IFERROR( ROUND( ' . $dataColumn.$dataRow-17 . ' / ' . $dataColumn.$dataRow-16 . ' , 2), 0)';
                        $dollarCell = true;
                        break;
                      case 'cost_lead_mql':
                        $gotoClassic = false;
                        $notClassicFormula = '=IFERROR( ROUND( ' . $dataColumn.$dataRow-18 . ' / ' . $dataColumn.$dataRow-19 . ' , 2), 0)';
                        $dollarCell = true;
                        break;
                      case 'cost_lead_sql':
                        $gotoClassic = false;
                        $notClassicFormula = '=IFERROR( ROUND( ' . $dataColumn.$dataRow-19 . ' / ' . $dataColumn.$dataRow-14 . ' , 2), 0)';
                        $dollarCell = true;
                        break;
                      case 'ctr':
                        $gotoClassic = false;
                        $notClassicFormula = '=IFERROR( ROUND( ' . $dataColumn.$dataRow-21 . ' / ' . $dataColumn.$dataRow-22 . ' , 4), 0)';
                        break;
                      case 'romi':
                        $gotoClassic = false;
                        $notClassicFormula = '=IFERROR( ROUND( (' . $dataColumn.$dataRow-9 . ' - ' . $dataColumn.$dataRow-21 . ') / ' . $dataColumn.$dataRow-21 . ' , 4), 0)';
                        break;
                      case 'click_sql':
                        $gotoClassic = false;
                        $notClassicFormula = '=IFERROR( ROUND( ' . $dataColumn.$dataRow-21 . ' / ' . $dataColumn.$dataRow-23 . ' , 4), 0)';
                        break;
                      case 'click_mql':
                        $gotoClassic = false;
                        $notClassicFormula = '=IFERROR( ROUND( ' . $dataColumn.$dataRow-18 . ' / ' . $dataColumn.$dataRow-22 . ' , 4), 0)';
                        break;
                      default:
                    }

                    if($gotoClassic){
                      $cpcSheet->setCellValue($dataColumn.$dataRow, '=SUM(' . $dataColumn.$dataRow+1 . ':' . $dataColumn.($dataRow+$downCells) . ')');
                      if($dollarCell){
                        $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                      }
                    }
                    else {
                      $cpcSheet->setCellValue($dataColumn.$dataRow, $notClassicFormula);
                      if($dollarCell){
                        $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                      }
                    }
                    $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

                  }

                }
              }

              if($classicMonthData){
                $cpcSheet->setCellValue($dataColumn.$dataRow, '=SUM(' . $weeksStartCell . $dataRow . ':' . (Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1)).$dataRow . ')');
                if($dollarCell){
                  $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                }
              }
              else {
                switch($prcolumn[3]){
                  case 'percent_otkazi':
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IFERROR( ROUND( (' . $dataColumn.$dataRow-17 . ' - ' . $dataColumn.$dataRow-15 . ') / ' . $dataColumn.$dataRow-17 . ' , 4), 0)');
                    $cpcSheet->getStyle('C' . $dataRow . ':' . $dataColumn . $dataRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                    break;
                  case 'conversion_cost':
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IFERROR( ROUND( ' . $dataColumn.$dataRow-17 . ' / ' . $dataColumn.$dataRow-16 . ' , 2), 0)');
                    $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                    $dollarCell = true;
                    break;
                  case 'cost_lead_mql':
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IFERROR( ROUND( ' . $dataColumn.$dataRow-18 . ' / ' . $dataColumn.$dataRow-19 . ' , 2), 0)');
                    $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                    $dollarCell = true;
                    break;
                  case 'cost_lead_sql':
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IFERROR( ROUND( ' . $dataColumn.$dataRow-19 . ' / ' . $dataColumn.$dataRow-14 . ' , 2), 0)');
                    $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                    $dollarCell = true;
                    break;
                  case 'ctr':
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IFERROR( ROUND( ' . $dataColumn.$dataRow-21 . ' / ' . $dataColumn.$dataRow-22 . ' , 4), 0)');
                    $cpcSheet->getStyle('C' . $dataRow . ':' . $dataColumn . $dataRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                    break;
                  case 'romi':
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IFERROR( ROUND( (' . $dataColumn.$dataRow-9 . ' - ' . $dataColumn.$dataRow-21 . ') / ' . $dataColumn.$dataRow-21 . ' , 4), 0)');
                    $cpcSheet->getStyle('C' . $dataRow . ':' . $dataColumn . $dataRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                    break;
                  case 'click_sql':
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IFERROR( ROUND( ' . $dataColumn.$dataRow-21 . ' / ' . $dataColumn.$dataRow-23 . ' , 4), 0)');
                    $cpcSheet->getStyle('C' . $dataRow . ':' . $dataColumn . $dataRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                    break;
                  case 'click_mql':
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IFERROR( ROUND( ' . $dataColumn.$dataRow-18 . ' / ' . $dataColumn.$dataRow-22 . ' , 4), 0)');
                    $cpcSheet->getStyle('C' . $dataRow . ':' . $dataColumn . $dataRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                    break;
                  default:
                    $cpcSheet->setCellValue($dataColumn.$dataRow, '=IF( SUM(' . $weeksStartCell . $dataRow . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) - 1) . $dataRow . ')=SUM(' . $dataColumn . $dataRow+1 . ':' . $dataColumn.$dataRow+$downCells . '), SUM(' . $dataColumn . $dataRow+1 . ':' . $dataColumn.$dataRow+$downCells . '),"ошибка")');
                }

                if($dollarCell){
                  $cpcSheet->getStyle($dataColumn.$dataRow)->getNumberFormat()->setFormatCode('"$"#,##0.00');
                }
              }
              $dataColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn) + 1);

            }

          }

        }

        $dataRow++;
      }

      $cpcSheet->mergeCells('A'.$dataRow.':'.Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($dataColumn)-1).$dataRow);

      for ($row = $collapseRow; $row <= $dataRow-1; $row++) {
        $cpcSheet->getRowDimension($row)->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
      }

      $dataRow++;

    }

    // Границы всему
    $highestColumn = $cpcSheet->getHighestColumn();
    $highestRow = $cpcSheet->getHighestRow();
    $cpcSheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray(
      [
        'borders' => [
          'allBorders' => [
              'borderStyle' => Border::BORDER_THIN,
              'color' => ['rgb' => '000000'], // Чёрный цвет границ
          ],
        ]
      ]
    );

    $cpcSheet->getColumnDimension('A')->setWidth('60');
    $cpcSheet->getStyle('A1:' . $dataColumn . '1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $cpcSheet->getStyle('A:' . $dataColumn)->getAlignment()->setWrapText(true);
  }

  private function generateFullMSSaleReport($profits,$categoryProfits,$brands,$catBranches)
  {
    $fileName       = 'ПродажиПоБрендам_' . date('d.m.Y_H.i.s') . '.xlsx';
    $spreadsheet    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet          = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('B1', 'Показатели');

    foreach ($brands as $brandkey => $brand) {
      if(empty($brand->name) OR trim($brand->name) == '-'){ unset($brands[$brandkey]); continue; }
    }
    $brands = array_values($brands);

    // Продажи по количеству брендов // Operation 1
    $r = 2;
    $loops = 0;
    for($bc = 0; $bc <= count($brands); $bc++){
      $w = 'C';
      foreach ($profits as $dkey => $dweek) {
        // Weeks
        if($bc == 0){
          $from = new \DateTime($dweek->weekFrom);
          $to   = new \DateTime($dweek->weekTo);
          $sheet->setCellValue($w . '1', $from->format('d.m.Y') . ' - ' . $to->format('d.m.Y'));
          ++$w;

          if(array_key_last($profits)){
            $sheet->setCellValue($w . '1', 'Всего');
          }
        }
        // Data
        else {
          $brandKey = ($bc-1);
          if($dkey == 0):
            $sheet->setCellValue('B' . $r, $brands[$brandKey]->name);
          endif;

          $sheet->setCellValue($w . $r, 0);
          if(isset($dweek->brandsList[$brands[$brandKey]->name])){
            $sheet->setCellValue($w . $r, $dweek->brandsList[$brands[$brandKey]->name]->quantity);
          }

          ++$w;
          $loops++;

          if(array_key_last($profits)){
            $sumrange = 'C' . $r . ':' . chr(ord($w)-1) . $r;
            $sheet->setCellValue($w . $r, '=SUM(' . $sumrange . ')');
          }
        }
      }
      $r++;
    }

    // Суммарная продажа по брендам по кoличеству
    $w = 'C';
    $previousEndCellRow = $r-1;
    $summarySellsInCountRow = 2;
    for($n = 0; $n <= count($profits); $n++){
      $sheet->setCellValue($w . '2','=SUM(' . $w . '3:' . $w . $previousEndCellRow . ')');
      $sheet->getStyle($w . '2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ebf1de');
      ++$w;
    }

    $sheet->mergeCells('A3:A' . (count($brands)+2));
    $sheet->setCellValue('A3','Кол-во продаж по брендам, шт');
    $sheet->getStyle('A3')->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . (count($brands)+2) . ':' . $w . (count($brands)+2))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('fce4d6');

    // Продажи по количеству брендов в процентах
    $operation1ColumnTitleEndCellRow = $r-1;
    $columnTitleStartCellRow = $r;
    for($bc = 1; $bc <= count($brands); $bc++){
      $w = 'C';
      foreach ($profits as $dkey => $dweek) {
        // Data
        $brandKey = ($bc-1);
        if($dkey == 0):
          $sheet->setCellValue('B' . $r, $brands[$brandKey]->name);
        endif;

        $sheet->setCellValue($w . $r, 0);
        if(isset($dweek->brandsList[$brands[$brandKey]->name])){
          $sheet->setCellValue($w . $r, '=IF(' . $w . ($r-count($brands)) . '=0,"0",ROUND(' . $w . ($r-count($brands)) . '/SUM($' . $w . '$3:$' . $w . '$' . $operation1ColumnTitleEndCellRow . '),5))');
          $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
        }

        ++$w;

        if(array_key_last($profits)){
          $sheet->setCellValue($w . $r, '=IF(' . $w . ($r-count($brands)) . '=0,"0",ROUND(' . $w . ($r-count($brands)) . '/SUM($' . $w . '$3:$' . $w . '$' . $operation1ColumnTitleEndCellRow . '),5))');
          $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
        }
      }
      $r++;
    }

    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, '%, шт');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('fce4d6');

    // Темп прироста в количестве продаж
    $operation1ColumnTitleEndCellRow = $r-1;
    $columnTitleStartCellRow = $r;
    for($bc = 1; $bc <= count($brands); $bc++){
      $w = 'C';
      foreach ($profits as $dkey => $dweek) {
        // Data
        $brandKey = ($bc-1);
        if($dkey == 0):
          $sheet->setCellValue('B' . $r, $brands[$brandKey]->name);
        endif;

        ++$w;
      }
      $r++;
    }

    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Темп прироста');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('fce4d6');

    // Суммарная продажа по брендам по сумме
    $w = 'C';
    $summarySellsInMoneyRow = $r;
    for($n = 0; $n <= count($profits); $n++){
      $sheet->setCellValue($w . $r,'=SUM(' . $w . $r . ':' . $w . ($r+count($brands)) . ')');
      $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ebf1de');
      ++$w;
    }
    $r++;

    // Сумма продаж по брендам
    $columnTitleStartCellRow = $r;
    $operation1ColumnTitleEndCellRow = $r-1;
    for($bc = 1; $bc <= count($brands); $bc++){
      $w = 'C';
      foreach ($profits as $dkey => $dweek) {
        // Data
        $brandKey = ($bc-1);
        if($dkey == 0):
          $sheet->setCellValue('B' . $r, $brands[$brandKey]->name);
        endif;

        $sheet->setCellValue($w . $r, 0);
        if(isset($dweek->brandsList[$brands[$brandKey]->name])){
          $sheet->setCellValue($w . $r, $dweek->brandsList[$brands[$brandKey]->name]->totalSum);
        }

        ++$w;

        if(array_key_last($profits)){
          $sumrange = 'C' . $r . ':' . chr(ord($w)-1) . $r;
          $sheet->setCellValue($w . $r, '=SUM(' . $sumrange . ')');
        }
      }
      $r++;
    }

    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' . $columnTitleStartCellRow,'Сумма продаж по брендам');
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ffe699');

    // Продажи по сумме брендов в процентах
    $operation1ColumnTitleEndCellRow = $r-1;
    $columnTitleStartCellRow = $r;
    $columnTitlePreviousStartCellRow = ($r-count($brands));
    for($bc = 1; $bc <= count($brands); $bc++){
      $w = 'C';
      foreach ($profits as $dkey => $dweek) {
        // Data
        $brandKey = ($bc-1);
        if($dkey == 0):
          $sheet->setCellValue('B' . $r, $brands[$brandKey]->name);
        endif;

        $sheet->setCellValue($w . $r, 0);
        if(isset($dweek->brandsList[$brands[$brandKey]->name])){
          $sheet->setCellValue($w . $r, '=IF(' . $w . ($r-count($brands)) . '=0,"0",ROUND(' . $w . ($r-count($brands)) . '/SUM($' . $w . '$' . $columnTitlePreviousStartCellRow . ':$' . $w . '$' . $operation1ColumnTitleEndCellRow . '),5))');
          $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
        }

        ++$w;

        if(array_key_last($profits)){
          $sheet->setCellValue($w . $r, '=IF(' . $w . ($r-count($brands)) . '=0,"0",ROUND(' . $w . ($r-count($brands)) . '/SUM($' . $w . '$' . $columnTitlePreviousStartCellRow . ':$' . $w . '$' . $operation1ColumnTitleEndCellRow . '),5))');
          $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
        }
      }
      $r++;
    }

    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, '%');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ffe699');

    // Темп прироста суммы продаж
    $operation1ColumnTitleEndCellRow = $r-1;
    $columnTitleStartCellRow = $r;
    $columnTitlePreviousStartCellRow = ($r-count($brands));
    for($bc = 1; $bc <= count($brands); $bc++){
      $w = 'C';
      foreach ($profits as $dkey => $dweek) {
        // Data
        $brandKey = ($bc-1);
        if($dkey == 0):
          $sheet->setCellValue('B' . $r, $brands[$brandKey]->name);
        endif;
        ++$w;
      }
      $r++;
    }

    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Темп прироста');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ffe699');

    // Общий средний чек в неделю
    $w = 'C';
    for($n = 0; $n <= count($profits); $n++){
      $sheet->setCellValue($w . $r, '=IF(' . $w . $summarySellsInMoneyRow . '=0,"0",ROUND(' . $w . $summarySellsInMoneyRow . '/ ' . $w . $summarySellsInCountRow . ',0))');
      $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ebf1de');
      ++$w;
    }
    $r++;

    // Средний чек
    $columnTitleStartCellRow = $r;
    $summaProdajPoBrendamStartCellRow = $r - (count($brands)) * 3 - 1;
    $kolichestvoProdajPoBrendamStartCellRow = 3;
    for($bc = 1; $bc <= count($brands); $bc++){
      $w = 'C';
      foreach ($profits as $dkey => $dweek) {
        // Data
        $brandKey = ($bc-1);
        if($dkey == 0):
          $sheet->setCellValue('B' . $r, $brands[$brandKey]->name);
        endif;

        $sheet->setCellValue($w . $r, 0);
        if(isset($dweek->brandsList[$brands[$brandKey]->name])){
          $sheet->setCellValue($w . $r, '=IF(' . $w . $summaProdajPoBrendamStartCellRow . '=0,"0",ROUND(' . $w . $summaProdajPoBrendamStartCellRow . '/' . $w . $kolichestvoProdajPoBrendamStartCellRow . ',0))');
        }

        ++$w;

        if(array_key_last($profits)){
          $sheet->setCellValue($w . $r, '=IF(' . $w . $summaProdajPoBrendamStartCellRow . '=0,"0",ROUND(' . $w . $summaProdajPoBrendamStartCellRow . '/' . $w . $kolichestvoProdajPoBrendamStartCellRow . ',0))');
        }
      }
      $r++;
      $summaProdajPoBrendamStartCellRow++;
      $kolichestvoProdajPoBrendamStartCellRow++;
    }
    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Средний чек, тенге');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('bf8f00');

    //---------------------- Отчет по категориям ------------------- //
    $r++;

    // ****** Количество продаж по категориям, В ШТУКАХ ****** //
    $sheet->getStyle('A' . $r . ':' . $w . $r)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $columnTitleStartCellRow = $r;
    $parentCategoriesRows = [];
    $countCategoryQuantityStartRow = $r;

    foreach ($catBranches as $brkey => $branch) {
      $sheet->setCellValue('B' . $r, $branch);
      if(is_int($brkey)){
        $sheet->getStyle('B' . $r . ':' . 'Z' . $r)->getFont()->setBold(true);
        $parentCategoriesRows[] = $r;
        $sheet->getStyle('B' . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
      }

      $w = 'C';

      $m = 1;
      foreach ($categoryProfits as $wckey => $weekCatProfits) {
        foreach ($weekCatProfits->list as $catPath => $catData) {
          $catPathArr = explode('/',$catPath);

          if(is_int($brkey)){
            $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
          }

          if($brkey == 0 AND mb_strtolower($catPathArr[0]) == 'аксессуары'){
            $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0]))->totalQuantity);
          }
          if($brkey == 15 AND mb_strtolower($catPathArr[0]) == 'чай'){
            $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0]))->totalQuantity);
          }
          if(mb_strtolower($catPathArr[0]) == 'шоколадные напитки'){
            if($brkey === 16){
              $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0]))->totalQuantity);
            }
            else {
              if($brkey === '16_1' AND mb_strtolower($catPathArr[1]) == 'dolce gusto' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
              if($brkey === '16_2' AND mb_strtolower($catPathArr[1]) == 'dolce gusto' AND mb_strtolower($catPathArr[2]) == 'gimoka'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
              if($brkey === '16_3' AND mb_strtolower($catPathArr[1]) == 'dolce gusto' AND mb_strtolower($catPathArr[2]) == 'lollo'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
              if($brkey === '16_4' AND mb_strtolower($catPathArr[1]) == 'dolce gusto' AND mb_strtolower($catPathArr[2]) == 'nescafe'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
              if($brkey === '16_5' AND mb_strtolower($catPathArr[1]) == 'nespresso original' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
              if($brkey === '16_6' AND mb_strtolower($catPathArr[1]) == 'nespresso original' AND mb_strtolower($catPathArr[2]) == 'lollo'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
            }
          }
          if(mb_strtolower($catPathArr[0]) == 'кофе'){
            if(mb_strtolower($catPathArr[1]) == 'зерновой кофе'){
              if($catPathArr[2] == 'Lollo'){
                file_put_contents(__DIR__ . '/test123.txt',$weekCatProfits->weekFrom . PHP_EOL . PHP_EOL, FILE_APPEND);
                file_put_contents(__DIR__ . '/test123.txt',print_r($weekCatProfits->list,true) . PHP_EOL . '------------------' . PHP_EOL, FILE_APPEND);
              }
              if($brkey === 1){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalQuantity);
              }
              else {
                if($brkey === '1_1' AND mb_strtolower($catPathArr[2]) == 'gimoka'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }

                if($brkey === '1_2' AND mb_strtolower($catPathArr[2]) == 'lollo'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }

                if($brkey === '1_3' AND mb_strtolower($catPathArr[2]) == 'vergnano'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }

                if($brkey === '1_4' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
              }
            }

            if(mb_strtolower($catPathArr[1]) == 'молотый кофе'){
              if($brkey === 2){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalQuantity);
              }
              else {
                if($brkey === '2_1' AND mb_strtolower($catPathArr[2]) == 'gimoka'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }

                if($brkey === '2_2' AND mb_strtolower($catPathArr[2]) == 'lollo'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }

                if($brkey === '2_3' AND mb_strtolower($catPathArr[2]) == 'vergnano'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }

                if($brkey === '2_4' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
              }
            }

            if(mb_strtolower($catPathArr[1]) == 'чалды'){
              if($brkey === 8){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalQuantity);
              }
              else {
                if($brkey === '8_1' AND mb_strtolower($catPathArr[2]) == 'illy'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
                if($brkey === '8_2' AND mb_strtolower($catPathArr[2]) == 'lollocaffe'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
                if($brkey === '8_3' AND mb_strtolower($catPathArr[2]) == 'gimoka'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
                if($brkey === '8_4' AND mb_strtolower($catPathArr[2]) == 'kimbo'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
                if($brkey === '8_5' AND mb_strtolower($catPathArr[2]) == 'vergnano'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
                if($brkey === '8_6' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
              }
            }

            if(mb_strtolower($catPathArr[1]) == 'капсульный кофе'){
              if(mb_strtolower($catPathArr[2]) == 'dolce gusto'){
                if($brkey === 3){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
                else {
                  if($brkey === '3_1' AND mb_strtolower($catPathArr[3]) == 'gimoka'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '3_2' AND mb_strtolower($catPathArr[3]) == 'kimbo'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '3_3' AND mb_strtolower($catPathArr[3]) == 'lollo'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '3_4' AND mb_strtolower($catPathArr[3]) == 'starbucks'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '3_5' AND mb_strtolower($catPathArr[3]) == 'vergnano'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '3_6' AND mb_strtolower($catPathArr[3]) == 'borbone'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '3_7' AND mb_strtolower($catPathArr[3]) == 'lavazza'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '3_8' AND mb_strtolower($catPathArr[3]) == 'nescafe'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                }
              }
              if(mb_strtolower($catPathArr[2]) == 'nespresso original'){
                if($brkey === 4){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
                else {
                  if($brkey === '4_1' AND mb_strtolower($catPathArr[3]) == 'gimoka'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_2' AND mb_strtolower($catPathArr[3]) == 'illy'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_3' AND mb_strtolower($catPathArr[3]) == 'jacobs'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_4' AND mb_strtolower($catPathArr[3]) == 'kimbo'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_5' AND mb_strtolower($catPathArr[3]) == 'lavazza'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_6' AND mb_strtolower($catPathArr[3]) == 'lollo'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_7' AND mb_strtolower($catPathArr[3]) == 'l’or'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_8' AND mb_strtolower($catPathArr[3]) == 'nespresso'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_9' AND mb_strtolower($catPathArr[3]) == 'starbucks'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_10' AND mb_strtolower($catPathArr[3]) == 'vergnano'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '4_11' AND mb_strtolower($catPathArr[3]) == 'borbone'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                }
              }
              if($brkey === 5 AND mb_strtolower($catPathArr[2]) == 'nespresso vertuo'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
              if(mb_strtolower($catPathArr[2]) == 'nespresso professional'){
                if($brkey === 6){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
                }
                else {
                  if($brkey === '6_1' AND mb_strtolower($catPathArr[3]) == 'nespresso'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                  if($brkey === '6_2' AND mb_strtolower($catPathArr[3]) == 'gimoka'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalQuantity);
                  }
                }
              }
              if($brkey === 7 AND mb_strtolower($catPathArr[2]) == 'lavazza blue'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
            }
          }
          if(mb_strtolower($catPathArr[0]) == 'кофемашины'){
            if($brkey == 9 AND mb_strtolower($catPathArr[1]) == 'рожковые кофемашины'){
              $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalQuantity);
            }

            if($brkey == 10 AND mb_strtolower($catPathArr[1]) == 'автоматические кофемашины'){
              $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalQuantity);
            }

            if(mb_strtolower($catPathArr[1]) == 'капсульные кофемашины'){
              if($brkey == 11 AND mb_strtolower($catPathArr[2]) == 'dolce gusto'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }

              if($brkey == 12 AND mb_strtolower($catPathArr[2]) == 'nespresso original'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }

              if($brkey == 13 AND mb_strtolower($catPathArr[2]) == 'nespresso vertuo'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }

              if($brkey == 14 AND mb_strtolower($catPathArr[2]) == 'nespresso professional'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalQuantity);
              }
            }

          }
        }

        $currentCell = $sheet->getCell($w . $r);
        if(empty(trim($currentCell->getValue())) OR !$currentCell->getValue()) {
          $sheet->setCellValue($w . $r,0);
        }
        ++$w;

        if(array_key_last($categoryProfits) AND count($categoryProfits) == $m){
          $sumrange = 'C' . $r . ':' . chr(ord($w)-1) . $r;
          $sheet->setCellValue($w . $r, '=SUM(' . $sumrange . ')');
          if(is_int($brkey)){
            $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
          }
        }
        $m++;
      }
      $r++;
      continue;
    }

    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Кол-во продаж по категориям, шт');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d9e1f2');

    // ****** Количество продаж по категориям, В ШТУКАХ Проценты ****** //
    $previousStartCellRow     = $columnTitleStartCellRow;
    $columnTitleStartCellRow  = $r;
    $lastParentCellRow        = $columnTitleStartCellRow;
    foreach ($catBranches as $brkey => $branch) {
      $sheet->setCellValue('B' . $r, $branch);
      if(is_int($brkey)){
        $sheet->getStyle('B' . $r . ':' . 'Z' . $r)->getFont()->setBold(true);
        $sheet->getStyle('B' . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
      }
      $w = 'C';
      foreach ($categoryProfits as $wckey => $weekCatProfits) {
        if(is_int($brkey)){
          $sheet->setCellValue($w . $r, '=IF(' . $w . $previousStartCellRow . '=0,"0",' . $w . $previousStartCellRow . '/(' . $w . implode('+' . $w,$parentCategoriesRows) . '))');
          $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
          $lastParentCellRow = $previousStartCellRow;
          $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
        }
        else {
          $sheet->setCellValue($w . $r, '=IF(' . $w . $previousStartCellRow . '=0,"0",' . $w . $previousStartCellRow . '/' . $w . $lastParentCellRow . ')');
          $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
        }
        ++$w;

        if(array_key_last($categoryProfits)){
          if(is_int($brkey)){
            $sheet->setCellValue($w . $r, '=IF(' . $w . $previousStartCellRow . '=0,"0",' . $w . $previousStartCellRow . '/(' . $w . implode('+' . $w,$parentCategoriesRows) . '))');
            $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
          }
          else {
            $sheet->setCellValue($w . $r, '=IF(' . $w . $previousStartCellRow . '=0,"0",' . $w . $previousStartCellRow . '/' . $w . $lastParentCellRow . ')');
            $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
          }
        }
      }
      $previousStartCellRow++;
      $r++;
    }
    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Кол-во продаж по категориям, %');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d9e1f2');

    // ****** Количество продаж по категориям Проценты В ШТУКАХ, Темп прироста ****** //
    $columnTitleStartCellRow  = $r;
    foreach ($catBranches as $brkey => $branch) {
      $sheet->setCellValue('B' . $r, $branch);
      if(is_int($brkey)){
        $sheet->getStyle('B' . $r . ':' . 'Z' . $r)->getFont()->setBold(true);
      }
      $w = 'C';
      foreach ($categoryProfits as $wckey => $weekCatProfits) {
        ++$w;
      }
      $r++;
    }
    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Темп прироста, %');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d9e1f2');

    // ****** Сумма продаж по категориям, В ТЕНГЕ ****** //
    $sheet->getStyle('A' . $r . ':' . $w . $r)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $columnTitleStartCellRow = $r;
    $parentCategoriesRows = [];
    $sumCategoryQuantityStartRow = $r;
    foreach ($catBranches as $brkey => $branch) {
      $sheet->setCellValue('B' . $r, $branch);
      if(is_int($brkey)){
        $sheet->getStyle('B' . $r . ':' . 'Z' . $r)->getFont()->setBold(true);
        $sheet->getStyle('B' . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
        $parentCategoriesRows[] = $r;
      }

      $w = 'C';

      $m = 1;
      foreach ($categoryProfits as $wckey => $weekCatProfits) {
        foreach ($weekCatProfits->list as $catPath => $catData) {
          $catPathArr = explode('/',$catPath);

          if(is_int($brkey)){
            $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
          }

          if($brkey == 0 AND mb_strtolower($catPathArr[0]) == 'аксессуары'){
            $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0]))->totalSum);
          }
          if($brkey == 15 AND mb_strtolower($catPathArr[0]) == 'чай'){
            $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0]))->totalSum);
          }
          if(mb_strtolower($catPathArr[0]) == 'шоколадные напитки'){
            if($brkey === 16){
              $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0]))->totalSum);
            }
            else {
              if($brkey === '16_1' AND mb_strtolower($catPathArr[1]) == 'dolce gusto' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
              if($brkey === '16_2' AND mb_strtolower($catPathArr[1]) == 'dolce gusto' AND mb_strtolower($catPathArr[2]) == 'gimoka'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
              if($brkey === '16_3' AND mb_strtolower($catPathArr[1]) == 'dolce gusto' AND mb_strtolower($catPathArr[2]) == 'lollo'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
              if($brkey === '16_4' AND mb_strtolower($catPathArr[1]) == 'dolce gusto' AND mb_strtolower($catPathArr[2]) == 'nescafe'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
              if($brkey === '16_5' AND mb_strtolower($catPathArr[1]) == 'nespresso original' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
              if($brkey === '16_6' AND mb_strtolower($catPathArr[1]) == 'nespresso original' AND mb_strtolower($catPathArr[2]) == 'lollo'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
            }
          }

          if(mb_strtolower($catPathArr[0]) == 'кофе'){
            if(mb_strtolower($catPathArr[1]) == 'зерновой кофе'){
              if($brkey === 1){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalSum);
              }
              else {
                if($brkey === '1_1' AND mb_strtolower($catPathArr[2]) == 'gimoka'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }

                if($brkey === '1_2' AND mb_strtolower($catPathArr[2]) == 'lollo'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }

                if($brkey === '1_3' AND mb_strtolower($catPathArr[2]) == 'vergnano'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }

                if($brkey === '1_4' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
              }
            }

            if(mb_strtolower($catPathArr[1]) == 'молотый кофе'){
              if($brkey === 2){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalSum);
              }
              else {
                if($brkey === '2_1' AND mb_strtolower($catPathArr[2]) == 'gimoka'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }

                if($brkey === '2_2' AND mb_strtolower($catPathArr[2]) == 'lollo'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }

                if($brkey === '2_3' AND mb_strtolower($catPathArr[2]) == 'vergnano'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }

                if($brkey === '2_4' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
              }
            }

            if(mb_strtolower($catPathArr[1]) == 'чалды'){
              if($brkey === 8){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalSum);
              }
              else {
                if($brkey === '8_1' AND mb_strtolower($catPathArr[2]) == 'illy'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
                if($brkey === '8_2' AND mb_strtolower($catPathArr[2]) == 'lollocaffe'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
                if($brkey === '8_3' AND mb_strtolower($catPathArr[2]) == 'gimoka'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
                if($brkey === '8_4' AND mb_strtolower($catPathArr[2]) == 'kimbo'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
                if($brkey === '8_5' AND mb_strtolower($catPathArr[2]) == 'vergnano'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
                if($brkey === '8_6' AND mb_strtolower($catPathArr[2]) == 'borbone'){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
              }
            }

            if(mb_strtolower($catPathArr[1]) == 'капсульный кофе'){
              if(mb_strtolower($catPathArr[2]) == 'dolce gusto'){
                if($brkey === 3){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
                else {
                  if($brkey === '3_1' AND mb_strtolower($catPathArr[3]) == 'gimoka'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '3_2' AND mb_strtolower($catPathArr[3]) == 'kimbo'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '3_3' AND mb_strtolower($catPathArr[3]) == 'lollo'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '3_4' AND mb_strtolower($catPathArr[3]) == 'starbucks'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '3_5' AND mb_strtolower($catPathArr[3]) == 'vergnano'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '3_6' AND mb_strtolower($catPathArr[3]) == 'borbone'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '3_7' AND mb_strtolower($catPathArr[3]) == 'lavazza'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '3_8' AND mb_strtolower($catPathArr[3]) == 'nescafe'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                }
              }
              if(mb_strtolower($catPathArr[2]) == 'nespresso original'){
                if($brkey === 4){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
                else {
                  if($brkey === '4_1' AND mb_strtolower($catPathArr[3]) == 'gimoka'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_2' AND mb_strtolower($catPathArr[3]) == 'illy'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_3' AND mb_strtolower($catPathArr[3]) == 'jacobs'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_4' AND mb_strtolower($catPathArr[3]) == 'kimbo'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_5' AND mb_strtolower($catPathArr[3]) == 'lavazza'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_6' AND mb_strtolower($catPathArr[3]) == 'lollo'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_7' AND mb_strtolower($catPathArr[3]) == 'l’or'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_8' AND mb_strtolower($catPathArr[3]) == 'nespresso'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_9' AND mb_strtolower($catPathArr[3]) == 'starbucks'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_10' AND mb_strtolower($catPathArr[3]) == 'vergnano'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '4_11' AND mb_strtolower($catPathArr[3]) == 'borbone'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                }
              }
              if($brkey === 5 AND mb_strtolower($catPathArr[2]) == 'nespresso vertuo'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
              if(mb_strtolower($catPathArr[2]) == 'nespresso professional'){
                if($brkey === 6){
                  $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
                }
                else {
                  if($brkey === '6_1' AND mb_strtolower($catPathArr[3]) == 'nespresso'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                  if($brkey === '6_2' AND mb_strtolower($catPathArr[3]) == 'gimoka'){
                    $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2],$catPathArr[3]))->totalSum);
                  }
                }
              }
              if($brkey === 7 AND mb_strtolower($catPathArr[2]) == 'lavazza blue'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
            }
          }
          if(mb_strtolower($catPathArr[0]) == 'кофемашины'){
            if($brkey == 9 AND mb_strtolower($catPathArr[1]) == 'рожковые кофемашины'){
              $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalSum);
            }

            if($brkey == 10 AND mb_strtolower($catPathArr[1]) == 'автоматические кофемашины'){
              $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1]))->totalSum);
            }

            if(mb_strtolower($catPathArr[1]) == 'капсульные кофемашины'){
              if($brkey == 11 AND mb_strtolower($catPathArr[2]) == 'dolce gusto'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }

              if($brkey == 12 AND mb_strtolower($catPathArr[2]) == 'nespresso original'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }

              if($brkey == 13 AND mb_strtolower($catPathArr[2]) == 'nespresso vertuo'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }

              if($brkey == 14 AND mb_strtolower($catPathArr[2]) == 'nespresso professional'){
                $sheet->setCellValue($w . $r, self::getCatTotalQuantitySum($weekCatProfits->list,array($catPathArr[0],$catPathArr[1],$catPathArr[2]))->totalSum);
              }
            }

          }
        }

        $currentCell = $sheet->getCell($w . $r);
        if(empty(trim($currentCell->getValue()))){
          $sheet->setCellValue($w . $r,0);
        }
        ++$w;

        if(array_key_last($categoryProfits) AND count($categoryProfits) == $m){
          $sumrange = 'C' . $r . ':' . chr(ord($w)-1) . $r;
          $sheet->setCellValue($w . $r, '=SUM(' . $sumrange . ')');
          if(is_int($brkey)){
            $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
          }
        }
        $m++;
      }
      $r++;
      continue;
    }
    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Сумма продаж по категориям, тенге');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('c6e0b4');

    // ****** Сумма продаж по категориям, В ТЕНГЕ Проценты ****** //
    $previousStartCellRow     = $columnTitleStartCellRow;
    $columnTitleStartCellRow  = $r;
    $lastParentCellRow        = $columnTitleStartCellRow;
    foreach ($catBranches as $brkey => $branch) {
      $sheet->setCellValue('B' . $r, $branch);
      if(is_int($brkey)){
        $sheet->getStyle('B' . $r . ':' . 'Z' . $r)->getFont()->setBold(true);
        $sheet->getStyle('B' . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
      }
      $w = 'C';
      foreach ($categoryProfits as $wckey => $weekCatProfits) {
        if(is_int($brkey)){
          $sheet->setCellValue($w . $r, '=IF(' . $w . $previousStartCellRow . '=0,"0",' . $w . $previousStartCellRow . '/(' . $w . implode('+' . $w,$parentCategoriesRows) . '))');
          $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
          $lastParentCellRow = $previousStartCellRow;
          $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
        }
        else {
          $sheet->setCellValue($w . $r, '=IF(' . $w . $previousStartCellRow . '=0,"0",' . $w . $previousStartCellRow . '/' . $w . $lastParentCellRow . ')');
          $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
        }
        ++$w;

        if(array_key_last($categoryProfits)){
          if(is_int($brkey)){
            $sheet->setCellValue($w . $r, '=IF(' . $w . $previousStartCellRow . '=0,"0",' . $w . $previousStartCellRow . '/(' . $w . implode('+' . $w,$parentCategoriesRows) . '))');
            $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
            $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
          }
          else {
            $sheet->setCellValue($w . $r, '=IF(' . $w . $previousStartCellRow . '=0,"0",' . $w . $previousStartCellRow . '/' . $w . $lastParentCellRow . ')');
            $sheet->getStyle($w . $r)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
          }
        }
      }
      $previousStartCellRow++;
      $r++;
    }
    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Кол-во продаж по категориям, %');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('c6e0b4');

    // ****** Сумма продаж по категориям Проценты В ТЕНГЕ, Темп прироста ****** //
    $columnTitleStartCellRow  = $r;
    foreach ($catBranches as $brkey => $branch) {
      $sheet->setCellValue('B' . $r, $branch);
      if(is_int($brkey)){
        $sheet->getStyle('B' . $r . ':' . 'Z' . $r)->getFont()->setBold(true);
      }
      $w = 'C';
      foreach ($categoryProfits as $wckey => $weekCatProfits) {
        ++$w;
      }
      $r++;
    }
    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Темп прироста, %');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('c6e0b4');

    // ****** Средний чек по категориям, В ТЕНГЕ ****** //

    $columnTitleStartCellRow  = $r;
    foreach ($catBranches as $brkey => $branch) {
      $sheet->setCellValue('B' . $r, $branch);
      if(is_int($brkey)){
        $sheet->getStyle('B' . $r . ':' . 'Z' . $r)->getFont()->setBold(true);
        $sheet->getStyle('B' . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
      }
      $w = 'C';
      foreach ($categoryProfits as $wckey => $weekCatProfits) {

        if(is_int($brkey)){
          $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
        }

        $sheet->setCellValue($w . $r, '=IF(' . $w . $countCategoryQuantityStartRow . '=0,"0", ROUND(' . $w . $sumCategoryQuantityStartRow . '/' . $w . $countCategoryQuantityStartRow . ',0))');
        ++$w;

        if(array_key_last($categoryProfits)){
          $sheet->setCellValue($w . $r, '=IF(' . $w . $countCategoryQuantityStartRow . '=0,"0", ROUND(' . $w . $sumCategoryQuantityStartRow . '/' . $w . $countCategoryQuantityStartRow . ',0))');
          if(is_int($brkey)){
            $sheet->getStyle($w . $r)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('f2f2f2');
          }
        }
      }
      $countCategoryQuantityStartRow++;
      $sumCategoryQuantityStartRow++;
      $r++;
    }
    $sheet->mergeCells('A' . $columnTitleStartCellRow . ':A' . ($r-1));
    $sheet->setCellValue('A' .  $columnTitleStartCellRow, 'Средний чек, тенге');
    $sheet->getStyle('A' .  $columnTitleStartCellRow)->getAlignment()->setTextRotation(90)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A' . $columnTitleStartCellRow . ':' . $w . ($r-1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
    $sheet->getStyle('A' . $columnTitleStartCellRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('92d050');

    $allSheetStyle    = $sheet->getStyle('A1:' . $w . $r);
    $allSheetBorders  = $allSheetStyle->getBorders();
    $allSheetBorders->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    $sheet->freezePane($w . '2');
    $sheet->getStyle('A1:' . $w . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('d5d5d5');
    $sheet->getStyle('A1:' . $w . '1')->getFont()->setBold(true);
    foreach ($sheet->getColumnIterator() as $column) { if($column->getColumnIndex() == 'A'){ continue; } $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true); }
    $sheet->getStyle('C:' . $w)->getAlignment()->setHorizontal('right');
    $writer = new Xlsx($spreadsheet);
    $writer->save(__DIR__ . '/../web/tmpDocs/' . $fileName);
    $xlsData = ob_get_contents();
    // ob_end_clean();
    return (object)array('file' => $fileName);
  }

  public function createBuyingReport($startDate,$categories,$waitDays,$deliveryDays,$gholdDays)
  {
    $moyskladModel  = new Moysklad();

    $fileName       = 'Закупки_' . date('d.m.Y_H.i.s') . '.xlsx';
    $spreadsheet    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet          = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('B1', 'Показатели');

    // Quarters
    $quarter1DateFrom = clone $startDate;
    $quarter1DateFrom->setTime(0,0,0);
    $quarter1DateFrom = $quarter1DateFrom->modify('-180 days');
    $quarter1DateTo = clone $startDate;
    $quarter1DateTo = $quarter1DateTo->modify('-90 days');
    $quarter2DateFrom = clone $startDate;
    $quarter2DateFrom = $quarter2DateFrom->modify('-89 days');

    // Months
    $month1DateFrom = clone $quarter1DateFrom;
    $month1DateTo = clone $quarter1DateFrom;
    $month1DateTo = $month1DateTo->modify('+ 30 days');

    $month2DateFrom = clone $month1DateTo;
    $month2DateFrom = $month2DateFrom->modify('+1 days');
    $month2DateTo = clone $month2DateFrom;
    $month2DateTo = $month2DateTo->modify('+ 29 days');

    $month3DateFrom = clone $month2DateTo;
    $month3DateFrom = $month3DateFrom->modify('+1 days');
    $month3DateTo = clone $month3DateFrom;
    $month3DateTo = $month3DateTo->modify('+ 29 days');

    $month4DateFrom = clone $month3DateTo;
    $month4DateFrom = $month4DateFrom->modify('+1 days');
    $month4DateTo = clone $month4DateFrom;
    $month4DateTo = $month4DateTo->modify('+ 29 days');

    $month5DateFrom = clone $month4DateTo;
    $month5DateFrom = $month5DateFrom->modify('+1 days');
    $month5DateTo = clone $month5DateFrom;
    $month5DateTo = $month5DateTo->modify('+ 29 days');

    $month6DateFrom = clone $month5DateTo;
    $month6DateFrom = $month6DateFrom->modify('+1 days');
    $month6DateTo = clone $month6DateFrom;
    $month6DateTo = $month6DateTo->modify('+ 29 days');

    $turnovers              = $moyskladModel->getTurnoverByPeriod($quarter1DateFrom,$startDate,$categories);
    $moySkladProducts       = $moyskladModel->getMoySkladBuyReportProducts($startDate,$categories); // ABC fields
    $moySkladProfitQuarter1 = $moyskladModel->getProfitByPeriod($quarter1DateFrom,$quarter1DateTo,$categories);
    $moySkladProfitQuarter2 = $moyskladModel->getProfitByPeriod($quarter2DateFrom,$startDate,$categories);
    $moySkladProfitMonth1   = $moyskladModel->getProfitByPeriod($month1DateFrom,$month1DateTo,$categories);
    $moySkladProfitMonth2   = $moyskladModel->getProfitByPeriod($month2DateFrom,$month2DateTo,$categories);
    $moySkladProfitMonth3   = $moyskladModel->getProfitByPeriod($month3DateFrom,$month3DateTo,$categories);
    $moySkladProfitMonth4   = $moyskladModel->getProfitByPeriod($month4DateFrom,$month4DateTo,$categories);
    $moySkladProfitMonth5   = $moyskladModel->getProfitByPeriod($month5DateFrom,$month5DateTo,$categories);
    $moySkladProfitMonth6   = $moyskladModel->getProfitByPeriod($month6DateFrom,$month6DateTo,$categories);

    // Headers
    $sheet->setCellValue('A2', 'Код');
    $sheet->setCellValue('B2', 'Артикул');
    $sheet->setCellValue('C2', 'Наименование');
    $sheet->setCellValue('D2', 'Бренд');
    $sheet->setCellValue('E2', 'Поставщик');
    $sheet->setCellValue('F2', 'Мастер-пак');
    $sheet->setCellValue('G2', 'Сумма продаж 3 квартал');
    $sheet->setCellValue('H2', 'Сумма продаж 4 квартал');
    $sheet->setCellValue('I2', 'Сумма продаж 2 полугодие');
    $sheet->setCellValue('J2', 'Прибыль 3 квартал');
    $sheet->setCellValue('K2', 'Прибыль 4 квартал');
    $sheet->setCellValue('L2', 'Прибыль 2 полугодие');
    $sheet->setCellValue('M2', 'Рентабельность 3 квартал');
    $sheet->setCellValue('N2', 'Рентабельность 4 квартал');
    $sheet->setCellValue('O2', 'Рентабельность 2 полугодие');
    $sheet->setCellValue('P2', 'Продано');
    $sheet->setCellValue('Q2', $month1DateTo->format('d.m.Y'));
    $sheet->setCellValue('R2', $month2DateTo->format('d.m.Y'));
    $sheet->setCellValue('S2', $month3DateTo->format('d.m.Y'));
    $sheet->setCellValue('T2', $month4DateTo->format('d.m.Y'));
    $sheet->setCellValue('U2', $month5DateTo->format('d.m.Y'));
    $sheet->setCellValue('V2', $month6DateTo->format('d.m.Y'));
    $sheet->setCellValue('W2', 'Ожидание');
    $sheet->setCellValue('X2', 'Остаток');
    $sheet->setCellValue('Y2', 'Остаток Начало периода');
    $sheet->setCellValue('Z2', 'Остаток Конец периода');
    $sheet->setCellValue('AA2', 'Оборачиваемость в днях');
    $sheet->setCellValue('AB1', 'Срок ожидания, дней');
    $sheet->setCellValue('AB2', 'Оборачиваемость, раз');
    $sheet->setCellValue('AC2', '');
    $sheet->setCellValue('AD2', '');
    $sheet->setCellValue('AE1', $waitDays);
    $sheet->setCellValue('AE2', 'К-во');
    $sheet->setCellValue('AF2', '');
    $sheet->setCellValue('AG2', '');
    $sheet->setCellValue('AH1', 'Срок доставки, дней');
    $sheet->setCellValue('AH2', 'Сумма продаж');
    $sheet->setCellValue('AI2', '');
    $sheet->setCellValue('AJ2', '');
    $sheet->setCellValue('AK1', $deliveryDays);
    $sheet->setCellValue('AK2', 'Прибыль');
    $sheet->setCellValue('AL1', 'Необходимый товарный запас, дн');
    $sheet->setCellValue('AL2', 'Рентабельность');
    $sheet->setCellValue('AM1', $gholdDays);
    $sheet->setCellValue('AM2', 'Оборачиваемость в днях');
    $sheet->setCellValue('AN2', 'ABC');
    $sheet->setCellValue('AO2', 'Текущий запас в днях');
    $sheet->setCellValue('AP2', 'Запас товара в ожидании');
    $sheet->setCellValue('AQ2', 'Заказать штук');
    $sheet->setCellValue('AR2', 'Запас в днях с учетом заказа');
    $sheet->setCellValue('AS2', 'Количество мастер-паков');
    $sheet->setCellValue('AT2', 'ВЭД Объем');
    $sheet->setCellValue('AU2', 'Кол-во палет');
    $sheet->setCellValue('AV2', 'Супермаркет');
    $sheet->setCellValue('AW2', 'Закупочная цена');
    $sheet->setCellValue('AX2', 'Валюта закупочной цены');
    $sheet->setCellValue('AY2', 'Сумма заказа');

    $sheet->setCellValue('C3', 'Итого:');

    $i = 0;
    $l = 4;

    foreach ($moySkladProducts as $row) {
      if(!property_exists($row,'article')){ continue; }
      $productData = $moyskladModel->getHrefData($row->meta->href,false);

      switch($productData->meta->type){
        case 'product':
        case 'bundle':
          break;
        default:
          continue 2;
      }

      $brand = $moyskladModel->getProductAttribute($productData->attributes,'a51f0b60-f6be-11eb-0a80-081c000ff2c4');
      if($brand){ $brand = $brand->value->name; } else { $brand = 'Не определен'; }

      $provider = $moyskladModel->getProductAttribute($productData->attributes,'5e7712e4-c642-11ee-0a80-0b6400041569');
      if($provider){ $provider = $provider->value; } else { $provider = 'Не определен'; }

      $masterpack = $moyskladModel->getProductAttribute($productData->attributes,'ada19664-8dba-11ed-0a80-06c701018819');
      if($masterpack){ $masterpack = $masterpack->value; } else { $masterpack = 'Не определен'; }

      $vadVolume = $moyskladModel->getProductAttribute($productData->attributes,'4f418c2f-21a5-11ef-0a80-0871005beb58');
      if($vadVolume){ $vadVolume = $vadVolume->value; } else { $vadVolume = 'Не указан'; }

      $supermarket = $moyskladModel->getProductAttribute($productData->attributes,'4f418f88-21a5-11ef-0a80-0871005beb59');
      if($supermarket AND $supermarket->value == true){ $supermarket = 'Да'; } else { $supermarket = 'Нет'; }

      $buyPrice = $productData->buyPrice;
      if($buyPrice) {
        $currency = $moyskladModel->getHrefData($productData->buyPrice->currency->meta->href,false);
        $currency = $currency->name;
        $buyPrice = $productData->buyPrice->value / 100;
        $buyCurrency = $currency;
      } else {
        $buyPrice = 'Не определена';
        $buyCurrency = '';
        $currency = '';
      }

      $quarter1SalesSum     = self::calculateProfitSalesForProductByArr($moySkladProfitQuarter1,$row->code,'sales');
      $quarter2SalesSum     = self::calculateProfitSalesForProductByArr($moySkladProfitQuarter2,$row->code,'sales');
      $quarter1ProfitSum    = self::calculateProfitSalesForProductByArr($moySkladProfitQuarter1,$row->code,'profit');
      $quarter2ProfitSum    = self::calculateProfitSalesForProductByArr($moySkladProfitQuarter2,$row->code,'profit');
      $quarter1Rentability  = self::calculateProfitSalesForProductByArr($moySkladProfitQuarter1,$row->code,'rentability');
      $quarter2Rentability  = self::calculateProfitSalesForProductByArr($moySkladProfitQuarter2,$row->code,'rentability');
      $month1Quantity       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth1,$row->code,'quantity');
      $month2Quantity       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth2,$row->code,'quantity');
      $month3Quantity       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth3,$row->code,'quantity');
      $month4Quantity       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth4,$row->code,'quantity');
      $month5Quantity       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth5,$row->code,'quantity');
      $month6Quantity       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth6,$row->code,'quantity');
      $halfAYearRemains     = self::calculateHalfAYearRemains($turnovers,$row->code);

      $sheet->setCellValue('A' . $l, $row->code)->getStyle('A' . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
      $sheet->setCellValue('B' . $l, $row->article)->getStyle('A' . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
      $sheet->setCellValue('C' . $l, $row->name);
      $sheet->setCellValue('D' . $l, $brand);
      $sheet->setCellValue('E' . $l, $provider);
      $sheet->setCellValue('F' . $l, $masterpack);
      $sheet->setCellValue('G' . $l, $quarter1SalesSum);
      $sheet->setCellValue('H' . $l, $quarter2SalesSum);
      $sheet->setCellValue('I' . $l, '=SUM(G' . $l . ',H' . $l . ')');
      $sheet->setCellValue('J' . $l, $quarter1ProfitSum);
      $sheet->setCellValue('K' . $l, $quarter2ProfitSum);
      $sheet->setCellValue('L' . $l, '=SUM(J' . $l . ',K' . $l . ')');
      $sheet->setCellValue('M' . $l, $quarter1Rentability)->getStyle('M' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->setCellValue('N' . $l, $quarter2Rentability)->getStyle('N' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->setCellValue('O' . $l, '=IF(N' . $l . ' = 0, M' . $l . ', IF(M' . $l . ' = 0,N' . $l . ', AVERAGE(M' . $l . ':N' . $l . ')))')->getStyle('O' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      // $sheet->setCellValue('O' . $l, '=AVERAGE(' . $quarter1Rentability . ',' . $quarter2Rentability . ')')->getStyle('O' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      $sheet->setCellValue('P' . $l, '=SUM(Q' . $l . ':V' . $l . ')');

      $sheet->setCellValue('Q' . $l, $month1Quantity);
      $sheet->setCellValue('R' . $l, $month2Quantity);
      $sheet->setCellValue('S' . $l, $month3Quantity);
      $sheet->setCellValue('T' . $l, $month4Quantity);
      $sheet->setCellValue('U' . $l, $month5Quantity);
      $sheet->setCellValue('V' . $l, $month6Quantity);

      $sheet->setCellValue('W' . $l, $row->inTransit);
      $sheet->setCellValue('X' . $l, $row->stock);

      $sheet->setCellValue('Y' . $l, $halfAYearRemains->start);
      $sheet->setCellValue('Z' . $l, $halfAYearRemains->end);
      $sheet->setCellValue('AA' . $l, '=((((Y' . $l . '+Z' . $l . ')/2)*180)/P' . $l . ')');
      // $sheet->setCellValue('AA' . $l, '=ROUND((((Y' . $l . '+Z' . $l . ')/2)*180)/P' . $l . ', 2)');
      $sheet->setCellValue('AB' . $l, '=(P' . $l . '/((Y' . $l . '+Z' . $l . ')/2))');
      // $sheet->setCellValue('AB' . $l, '=ROUND(P' . $l . '/((Y' . $l . '+Z' . $l . ')/2), 2)');

      $sheet->setCellValue('AC' . $l, '=(P' . $l . '/$P$3)')->getStyle('AC' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      // $sheet->setCellValue('AC' . $l, '=ROUND((P' . $l . '/$P$3), 2)')->getStyle('AC' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      if($l == 4):
        $sheet->setCellValue('AD' . $l, '=AC' . $l)->getStyle('AD' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      else:
        $sheet->setCellValue('AD' . $l, '=AD' . $l-1 .' + AC' . $l)->getStyle('AD' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      endif;
      $sheet->setCellValue('AE' . $l, '=IF(AD' . $l . ' <= 0.8, "A", IF(AD' . $l . ' <= 0.95, "B", IF(AD' . $l . ' >= 0.95, "C")))');


      $sheet->setCellValue('AF' . $l, '=I' . $l . '/$I$3')->getStyle('AF' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      if($l == 4):
        $sheet->setCellValue('AG' . $l, '=AF' . $l)->getStyle('AG' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      else:
        $sheet->setCellValue('AG' . $l, '=AG' . $l-1 .' + AF' . $l)->getStyle('AG' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      endif;
      $sheet->setCellValue('AH' . $l, '=IF(AG' . $l . ' <= 0.8, "A", IF(AG' . $l . ' <= 0.95, "B", IF(AG' . $l . ' >= 0.95, "C")))');


      $sheet->setCellValue('AI' . $l, '=L' . $l . '/$L$3')->getStyle('AI' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      if($l == 4):
        $sheet->setCellValue('AJ' . $l, '=AI' . $l)->getStyle('AJ' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      else:
        $sheet->setCellValue('AJ' . $l, '=AJ' . $l-1 .' + AI' . $l)->getStyle('AJ' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
      endif;
      $sheet->setCellValue('AK' . $l, '=IF(AJ' . $l . ' <= 0.8, "A", IF(AJ' . $l . ' <= 0.95, "B", IF(AJ' . $l . ' >= 0.95, "C")))');

      $sheet->setCellValue('AL' . $l, '=IF(O' . $l . ' >= 1, "A", IF(O' . $l . ' <= 0.8, "C", IF(O' . $l . ' < 1, "B")))');

      $sheet->setCellValue('AM' . $l, '=IF(AA' . $l . ' = 0, "X", IF(AA' . $l . ' > 120, "C", IF(AA' . $l . ' <= 90, "A", IF(AA' . $l . ' > 90, "B"))))');
      // $sheet->setCellValue('AM' . $l, '=IF(AA' . $l . ' > 120, "C", IF(AA' . $l . ' <= 90, "A", IF(AA' . $l . ' > 90, "B")))');

      $sheet->setCellValue('AN' . $l, '=CONCATENATE(AE' . $l . ',AH' . $l . ',AK' . $l . ',AL' . $l . ',AM' . $l . ')')->getStyle('AN' . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

      $sheet->setCellValue('AO' . $l, '=IFERROR(ROUND(IF(IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,X'.$l.'/SUM(Q'.$l.':S'.$l.')*90,IF(((X'.$l.'/(V'.$l.'+U'.$l.'+T'.$l.'))*90) <= 0, 1,(X'.$l.'/(V'.$l.'+U'.$l.'+T'.$l.'))*90))=0,1,IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,X'.$l.'/SUM(Q'.$l.':S'.$l.')*90,IF(((X'.$l.'/(V'.$l.'+U'.$l.'+T'.$l.'))*90) <= 0, 1,(X'.$l.'/(V'.$l.'+U'.$l.'+T'.$l.'))*90))),0),1)');

      $sheet->setCellValue('AP' . $l, '=IFERROR(ROUND(IF(IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,W'.$l.'/SUM(Q'.$l.':S'.$l.')*90,IF(((W'.$l.'/(V'.$l.'+U'.$l.'+T'.$l.'))*90) <= 0, 1,(W'.$l.'/(V'.$l.'+U'.$l.'+T'.$l.'))*90))=0,1,IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,W'.$l.'/SUM(Q'.$l.':S'.$l.')*90,IF(((W'.$l.'/(V'.$l.'+U'.$l.'+T'.$l.'))*90) <= 0, 1,(W'.$l.'/(V'.$l.'+U'.$l.'+T'.$l.'))*90))),0),1)');

      $sheet->setCellValue('AQ' . $l, '=IFERROR(IF(ROUND(IF(AP'.$l.'>=$AK$1,IF(AO'.$l.'>=$AK$1,IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,(Q'.$l.'+R'.$l.'+S'.$l.')*1.1/90*$AM$1,(T'.$l.'+U'.$l.'+V'.$l.')*1.05/90*$AM$1)-(X'.$l.'/AO'.$l.')*(AO'.$l.'-$AK$1),IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,(Q'.$l.'+R'.$l.'+S'.$l.')*1.1/90*$AM$1,(T'.$l.'+U'.$l.'+V'.$l.')*1.05/90*$AM$1))-(W'.$l.'/AP'.$l.')*(AP'.$l.'-($AK$1-$AE$1)),IF(AO'.$l.'>=$AK$1,IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,(Q'.$l.'+R'.$l.'+S'.$l.')*1.1/90*$AM$1,(T'.$l.'+U'.$l.'+V'.$l.')*1.05/90*$AM$1)-(X'.$l.'/AO'.$l.')*(AO'.$l.'-$AK$1),IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,(Q'.$l.'+R'.$l.'+S'.$l.')*1.1/90*$AM$1,(T'.$l.'+U'.$l.'+V'.$l.')*1.05/90*$AM$1)))/F'.$l.',0)<0,0,ROUND(IF(AP'.$l.'>=$AK$1,IF(AO'.$l.'>=$AK$1,IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,(Q'.$l.'+R'.$l.'+S'.$l.')*1.1/90*$AM$1,(T'.$l.'+U'.$l.'+V'.$l.')*1.05/90*$AM$1)-(X'.$l.'/AO'.$l.')*(AO'.$l.'-$AK$1),IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,(Q'.$l.'+R'.$l.'+S'.$l.')*1.1/90*$AM$1,(T'.$l.'+U'.$l.'+V'.$l.')*1.05/90*$AM$1))-(W'.$l.'/AP'.$l.')*(AP'.$l.'-($AK$1-$AE$1)),IF(AO'.$l.'>=$AK$1,IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,(Q'.$l.'+R'.$l.'+S'.$l.')*1.1/90*$AM$1,(T'.$l.'+U'.$l.'+V'.$l.')*1.05/90*$AM$1)-(X'.$l.'/AO'.$l.')*(AO'.$l.'-$AK$1),IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,(Q'.$l.'+R'.$l.'+S'.$l.')*1.1/90*$AM$1,(T'.$l.'+U'.$l.'+V'.$l.')*1.05/90*$AM$1)))/F'.$l.',0))*F'.$l.',1)');

      $sheet->setCellValue('AR' . $l, '=IFERROR(ROUND(IF(AP'.$l.'>=$AK$1,IF(AO'.$l.'>=$AK$1,IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,AQ'.$l.'/SUM(Q'.$l.':S'.$l.')*90,AQ'.$l.'/SUM(T'.$l.':V'.$l.')*90)+(AO'.$l.'-$AK$1),IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,AQ'.$l.'/SUM(Q'.$l.':S'.$l.')*90,AQ'.$l.'/SUM(T'.$l.':V'.$l.')*90))+AP'.$l.'-($AK$1-$AE$1),IF(AO'.$l.'>=$AK$1,IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,AQ'.$l.'/SUM(Q'.$l.':S'.$l.')*90,AQ'.$l.'/SUM(T'.$l.':V'.$l.')*90)+(AO'.$l.'-$AK$1),IF((S'.$l.'+R'.$l.'+Q'.$l.')/(V'.$l.'+U'.$l.'+T'.$l.')>1.5,AQ'.$l.'/SUM(Q'.$l.':S'.$l.')*90,AQ'.$l.'/SUM(T'.$l.':V'.$l.')*90))),0),"Проверка позиции")')->getStyle('A' . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

      $sheet->setCellValue('AS' . $l, '=(AQ' . $l . ' / F' . $l . ')');
      $sheet->setCellValue('AT' . $l, $vadVolume);
      $sheet->setCellValue('AU' . $l, '=(AT' . $l . ' * AQ' . $l . ')');
      $sheet->setCellValue('AV' . $l, $supermarket);
      $sheet->setCellValue('AW' . $l, $buyPrice);
      $sheet->setCellValue('AX' . $l, $buyCurrency);
      $sheet->setCellValue('AY' . $l, '=(AW' . $l . ' * AQ' . $l . ')');

      // Format colors
      $conditional1 = new Conditional();
      $conditional1->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
      $conditional1->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
      $conditional1->setStopIfTrue(true);
      $conditional1->setText('A');
      $conditional1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $conditional1->getStyle()->getFill()->getStartColor()->setARGB('c6efce'); // Зеленый
      $conditional1->getStyle()->getFont()->getColor()->setARGB('006100');

      $conditional2 = new Conditional();
      $conditional2->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
      $conditional2->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
      $conditional2->setStopIfTrue(true);
      $conditional2->setText('B');
      $conditional2->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $conditional2->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c'); // Желтый
      $conditional2->getStyle()->getFont()->getColor()->setARGB('9c5700');

      $conditional3 = new Conditional();
      $conditional3->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
      $conditional3->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
      $conditional3->setStopIfTrue(true);
      $conditional3->setText('C');
      $conditional3->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $conditional3->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce'); // Красный
      $conditional3->getStyle()->getFont()->getColor()->setARGB('9c0006');

      $conditional4 = new Conditional();
      $conditional4->setConditionType(Conditional::CONDITION_CELLIS);
      $conditional4->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
      $conditional4->setStopIfTrue(true);
      $conditional4->addCondition(1);
      $conditional4->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $conditional4->getStyle()->getFill()->getStartColor()->setARGB('c6efce'); // Зеленый
      $conditional4->getStyle()->getFont()->getColor()->setARGB('006100');

      $cond67Range = ['AO','AP'];
      foreach ($cond67Range as $condR) {
        $conditional6 = new Conditional();
        $conditional6->setConditionType(Conditional::CONDITION_EXPRESSION);
        $conditional6->setOperatorType(Conditional::OPERATOR_NONE);
        $conditional6->addCondition('AND('.$condR.$l.'>=2, '.$condR.$l.'<=30)');
        $conditional6->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $conditional6->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c'); // Жёлтый
        $conditional6->getStyle()->getFont()->getColor()->setARGB('9c5700');

        $conditional7 = new Conditional();
        $conditional7->setConditionType(Conditional::CONDITION_EXPRESSION);
        $conditional7->setOperatorType(Conditional::OPERATOR_NONE);
        $conditional7->addCondition('AND('.$condR.$l.'>=31, '.$condR.$l.'<=46)');
        $conditional7->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $conditional7->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce'); // Красный
        $conditional7->getStyle()->getFont()->getColor()->setARGB('9c0006');

        $sheet->getStyle($condR . $l)->setConditionalStyles([$conditional6, $conditional7]);
      }

      $conditional8 = new Conditional();
      $conditional8->setConditionType(Conditional::CONDITION_EXPRESSION);
      $conditional8->setOperatorType(Conditional::OPERATOR_NONE);
      $conditional8->addCondition('AND(AR'.$l.'>=100, AR'.$l.'<=1000)');
      $conditional8->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $conditional8->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce'); // Красный
      $conditional8->getStyle()->getFont()->getColor()->setARGB('9c0006');

      $conditional9 = new Conditional();
      $conditional9->setConditionType(Conditional::CONDITION_CELLIS);
      $conditional9->setOperatorType(Conditional::OPERATOR_LESSTHAN);
      $conditional9->setStopIfTrue(true);
      $conditional9->addCondition(80);
      $conditional9->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $conditional9->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c'); // Жёлтый
      $conditional9->getStyle()->getFont()->getColor()->setARGB('9c5700');

      $conditional10 = new Conditional();
      $conditional10->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
      $conditional10->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
      $conditional10->setStopIfTrue(true);
      $conditional10->setText('Проверка позиции');
      $conditional10->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $conditional10->getStyle()->getFill()->getStartColor()->setARGB('c6efce'); // Зеленый
      $conditional10->getStyle()->getFont()->getColor()->setARGB('006100');

      $sheet->getStyle('AE' . $l)->setConditionalStyles([$conditional1, $conditional2, $conditional3]);
      $sheet->getStyle('AH' . $l)->setConditionalStyles([$conditional1, $conditional2, $conditional3]);
      $sheet->getStyle('AK' . $l)->setConditionalStyles([$conditional1, $conditional2, $conditional3]);
      $sheet->getStyle('AL' . $l)->setConditionalStyles([$conditional1, $conditional2, $conditional3]);
      $sheet->getStyle('AM' . $l)->setConditionalStyles([$conditional1, $conditional2, $conditional3]);
      $sheet->getStyle('AN' . $l)->setConditionalStyles([$conditional3, $conditional2, $conditional1]);
      $sheet->getStyle('AQ' . $l)->setConditionalStyles([$conditional4]);
      $sheet->getStyle('AR' . $l)->setConditionalStyles([$conditional8, $conditional9,$conditional10]);

      $i++;
      $l++;
    }

    $sheet->setCellValue('P3', '=SUM(P4:P' . $l-1 . ')');
    $sheet->setCellValue('I3', '=SUM(I4:I' . $l-1 . ')');
    $sheet->setCellValue('L3', '=SUM(L4:L' . $l-1 . ')');
    $sheet->setCellValue('G3', '=SUM(G4:G' . $l-1 . ')');
    $sheet->setCellValue('H3', '=SUM(H4:H' . $l-1 . ')');
    $sheet->setCellValue('J3', '=SUM(J4:J' . $l-1 . ')');
    $sheet->setCellValue('K3', '=SUM(K4:K' . $l-1 . ')');
    $sheet->setCellValue('M3', '=AVERAGE(M4:M' . $l-1 . ')')->getStyle('M3')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->setCellValue('N3', '=AVERAGE(N4:N' . $l-1 . ')')->getStyle('N3')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->setCellValue('O3', '=AVERAGE(O4:O' . $l-1 . ')')->getStyle('O3')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    $sheet->setCellValue('P3', '=SUM(P4:P' . $l-1 . ')');
    $sheet->setCellValue('Q3', '=SUM(Q4:Q' . $l-1 . ')');
    $sheet->setCellValue('R3', '=SUM(R4:R' . $l-1 . ')');
    $sheet->setCellValue('S3', '=SUM(S4:S' . $l-1 . ')');
    $sheet->setCellValue('T3', '=SUM(T4:T' . $l-1 . ')');
    $sheet->setCellValue('U3', '=SUM(U4:U' . $l-1 . ')');
    $sheet->setCellValue('V3', '=SUM(V4:V' . $l-1 . ')');
    $sheet->setCellValue('W3', '=SUM(W4:W' . $l-1 . ')');
    $sheet->setCellValue('X3', '=SUM(X4:X' . $l-1 . ')');
    $sheet->setCellValue('AU3', '=SUM(AU4:AU' . $l-1 . ')');
    $sheet->setCellValue('AY3', '=SUM(AY4:AY' . $l-1 . ')');

    // Styling
    $sheet->freezePane('F3');

    $titlesStyle = [
        'font' => [
            'bold' => true,
            'size' => 12
        ],
        'alignment' => [
          'wrapText' => true, // Установим перенос текста
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
        ],
    ];
    $bordersStyle = [
      'borders' => [
          'allBorders' => [
              'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
          ],
      ],
    ];
    $sheet->getStyle('2')->applyFromArray($titlesStyle);
    $sheet->getStyle('C3')->applyFromArray($titlesStyle);
    $sheet->getStyle('A3:AQ3')->applyFromArray($titlesStyle);

    $darkGrayFill = [
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'argb' => 'c0c0c0'
            ],
        ],
    ];
    $lightGreenFill = [
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'argb' => 'e2efda'
            ],
        ],
    ];
    $lightYellowFill = [
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'argb' => 'fff2cc'
            ],
        ],
    ];
    $lightGrayFill = [
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'argb' => 'd9e1f2'
            ],
        ],
    ];


    $sheet->getStyle('A2:E2')->applyFromArray($darkGrayFill);
    $sheet->getStyle('AB2:AP2')->applyFromArray($darkGrayFill);
    $sheet->getStyle('F2:I2')->applyFromArray($lightGreenFill);
    $sheet->getStyle('J2:L' . $l-1)->applyFromArray($lightGreenFill);
    $sheet->getStyle('M2:O' . $l-1)->applyFromArray($lightYellowFill);
    $sheet->getStyle('P2:P' . $l-1)->applyFromArray($lightGrayFill);
    $sheet->getStyle('P2:P' . $l-1)->applyFromArray($lightGrayFill);
    $sheet->getStyle('A2:AY' . $l-1)->applyFromArray($bordersStyle);

    $sheet->getColumnDimension('G')->setOutlineLevel(1);
    $sheet->getColumnDimension('H')->setOutlineLevel(1);
    $sheet->getColumnDimension('G')->setVisible(false);
    $sheet->getColumnDimension('H')->setVisible(false);

    $sheet->getColumnDimension('J')->setOutlineLevel(1);
    $sheet->getColumnDimension('K')->setOutlineLevel(1);
    $sheet->getColumnDimension('J')->setVisible(false);
    $sheet->getColumnDimension('K')->setVisible(false);

    $sheet->getColumnDimension('M')->setOutlineLevel(1);
    $sheet->getColumnDimension('N')->setOutlineLevel(1);
    $sheet->getColumnDimension('M')->setVisible(false);
    $sheet->getColumnDimension('N')->setVisible(false);

    $sheet->getColumnDimension('Q')->setOutlineLevel(1);
    $sheet->getColumnDimension('R')->setOutlineLevel(1);
    $sheet->getColumnDimension('S')->setOutlineLevel(1);
    $sheet->getColumnDimension('T')->setOutlineLevel(1);
    $sheet->getColumnDimension('U')->setOutlineLevel(1);
    $sheet->getColumnDimension('V')->setOutlineLevel(1);
    $sheet->getColumnDimension('W')->setOutlineLevel(1);
    $sheet->getColumnDimension('Q')->setVisible(false);
    $sheet->getColumnDimension('R')->setVisible(false);
    $sheet->getColumnDimension('S')->setVisible(false);
    $sheet->getColumnDimension('T')->setVisible(false);
    $sheet->getColumnDimension('U')->setVisible(false);
    $sheet->getColumnDimension('V')->setVisible(false);
    $sheet->getColumnDimension('W')->setVisible(false);

    $sheet->getColumnDimension('Y')->setOutlineLevel(1);
    $sheet->getColumnDimension('Z')->setOutlineLevel(1);
    $sheet->getColumnDimension('AA')->setOutlineLevel(1);
    $sheet->getColumnDimension('Y')->setVisible(false);
    $sheet->getColumnDimension('Z')->setVisible(false);
    $sheet->getColumnDimension('AA')->setVisible(false);

    $sheet->getColumnDimension('AC')->setOutlineLevel(1);
    $sheet->getColumnDimension('AD')->setOutlineLevel(1);
    $sheet->getColumnDimension('AC')->setVisible(false);
    $sheet->getColumnDimension('AD')->setVisible(false);

    $sheet->getColumnDimension('AF')->setOutlineLevel(1);
    $sheet->getColumnDimension('AG')->setOutlineLevel(1);
    $sheet->getColumnDimension('AF')->setVisible(false);
    $sheet->getColumnDimension('AG')->setVisible(false);

    $sheet->getColumnDimension('AI')->setOutlineLevel(1);
    $sheet->getColumnDimension('AJ')->setOutlineLevel(1);
    $sheet->getColumnDimension('AI')->setVisible(false);
    $sheet->getColumnDimension('AJ')->setVisible(false);

    $sheet->getColumnDimension('C')->setWidth('35');
    $sheet->getColumnDimension('D')->setWidth('15');
    $sheet->getColumnDimension('E')->setWidth('15');
    $sheet->getColumnDimension('F')->setWidth('8');
    $sheet->getColumnDimension('G')->setWidth('11');
    $sheet->getColumnDimension('H')->setWidth('11');
    $sheet->getColumnDimension('I')->setWidth('11');
    $sheet->getColumnDimension('J')->setWidth('11');
    $sheet->getColumnDimension('K')->setWidth('11');
    $sheet->getColumnDimension('L')->setWidth('11');
    $sheet->getColumnDimension('M')->setWidth('11');
    $sheet->getColumnDimension('N')->setWidth('11');
    $sheet->getColumnDimension('O')->setWidth('11');
    $sheet->getColumnDimension('P')->setWidth('11');
    $sheet->getColumnDimension('Q')->setWidth('8');
    $sheet->getColumnDimension('R')->setWidth('8');
    $sheet->getColumnDimension('S')->setWidth('8');
    $sheet->getColumnDimension('T')->setWidth('8');
    $sheet->getColumnDimension('U')->setWidth('8');
    $sheet->getColumnDimension('V')->setWidth('8');
    $sheet->getColumnDimension('W')->setWidth('8');
    $sheet->getColumnDimension('X')->setWidth('8');
    $sheet->getColumnDimension('Y')->setWidth('10');
    $sheet->getColumnDimension('Z')->setWidth('10');
    $sheet->getColumnDimension('AA')->setWidth('10');
    $sheet->getColumnDimension('AB')->setWidth('10');
    $sheet->getColumnDimension('AC')->setWidth('9');
    $sheet->getColumnDimension('AD')->setWidth('9');
    $sheet->getColumnDimension('AE')->setWidth('9');
    $sheet->getColumnDimension('AF')->setWidth('9');
    $sheet->getColumnDimension('AG')->setWidth('9');
    $sheet->getColumnDimension('AH')->setWidth('9');
    $sheet->getColumnDimension('AI')->setWidth('9');
    $sheet->getColumnDimension('AJ')->setWidth('9');
    $sheet->getColumnDimension('AK')->setWidth('9');
    $sheet->getColumnDimension('AL')->setWidth('9');
    $sheet->getColumnDimension('AM')->setWidth('9');
    $sheet->getColumnDimension('AN')->setWidth('9');
    $sheet->getColumnDimension('AO')->setWidth('9');
    $sheet->getColumnDimension('AP')->setWidth('10');
    $sheet->getColumnDimension('AQ')->setWidth('9');
    $sheet->getColumnDimension('AR')->setWidth('17');
    $sheet->getColumnDimension('AS')->setWidth('10');
    $sheet->getColumnDimension('AT')->setWidth('13');
    $sheet->getColumnDimension('AU')->setWidth('10');
    $sheet->getColumnDimension('AV')->setWidth('10');
    $sheet->getColumnDimension('AW')->setWidth('10');
    $sheet->getColumnDimension('AX')->setWidth('10');

    $writer = new Xlsx($spreadsheet);
    $writer->save(__DIR__ . '/../web/tmpDocs/' . $fileName);
    $xlsData = ob_get_contents();
    ob_end_clean();
    return (object)array('file' => $fileName);
  }

  public function createMSMovesReport($startDate)
  {
    $moyskladModel = new Moysklad();

    $assortiment        = json_decode(file_get_contents(__DIR__ . '/../../../bot.accio.kz/html/models/catalogueFull.json'));

    $fileName       = 'Перемещения_' . date('d.m.Y_H.i.s') . '.xlsx';
    $spreadsheet    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet          = $spreadsheet->getActiveSheet();

    $moySkladProducts       = $moyskladModel->getMoySkladBuyReportProducts($startDate,array('all')); // ABC fields
    $productsRemains        = $moyskladModel->getProductsRemains();

    // Quarters
    $quarter1DateFrom = clone $startDate;
    $quarter1DateFrom->setTime(0,0,0);
    $quarter1DateFrom = $quarter1DateFrom->modify('-180 days');
    $quarter1DateTo = clone $startDate;
    $quarter1DateTo = $quarter1DateTo->modify('-90 days');
    $quarter2DateFrom = clone $startDate;
    $quarter2DateFrom = $quarter2DateFrom->modify('-89 days');

    // Months
    $month1DateFrom = clone $quarter1DateFrom;
    $month1DateTo = clone $quarter1DateFrom;
    $month1DateTo = $month1DateTo->modify('+ 30 days');

    $month2DateFrom = clone $month1DateTo;
    $month2DateFrom = $month2DateFrom->modify('+1 days');
    $month2DateTo = clone $month2DateFrom;
    $month2DateTo = $month2DateTo->modify('+ 29 days');

    $month3DateFrom = clone $month2DateTo;
    $month3DateFrom = $month3DateFrom->modify('+1 days');
    $month3DateTo = clone $month3DateFrom;
    $month3DateTo = $month3DateTo->modify('+ 29 days');

    $month4DateFrom = clone $month3DateTo;
    $month4DateFrom = $month4DateFrom->modify('+1 days');
    $month4DateTo = clone $month4DateFrom;
    $month4DateTo = $month4DateTo->modify('+ 29 days');

    $month5DateFrom = clone $month4DateTo;
    $month5DateFrom = $month5DateFrom->modify('+1 days');
    $month5DateTo = clone $month5DateFrom;
    $month5DateTo = $month5DateTo->modify('+ 29 days');

    $month6DateFrom = clone $month5DateTo;
    $month6DateFrom = $month6DateFrom->modify('+1 days');
    $month6DateTo = clone $month6DateFrom;
    $month6DateTo = $month6DateTo->modify('+ 29 days');

    $moySkladProfitMonth1Almaty   = $moyskladModel->getProfitByPeriod($month1DateFrom,$month1DateTo,array('all'),'almaty');
    $moySkladProfitMonth1Astana   = $moyskladModel->getProfitByPeriod($month1DateFrom,$month1DateTo,array('all'),'astana');
    $moySkladProfitMonth2Almaty   = $moyskladModel->getProfitByPeriod($month2DateFrom,$month2DateTo,array('all'),'almaty');
    $moySkladProfitMonth2Astana   = $moyskladModel->getProfitByPeriod($month2DateFrom,$month2DateTo,array('all'),'astana');
    $moySkladProfitMonth3Almaty   = $moyskladModel->getProfitByPeriod($month3DateFrom,$month3DateTo,array('all'),'almaty');
    $moySkladProfitMonth3Astana   = $moyskladModel->getProfitByPeriod($month3DateFrom,$month3DateTo,array('all'),'astana');
    $moySkladProfitMonth4Almaty   = $moyskladModel->getProfitByPeriod($month4DateFrom,$month4DateTo,array('all'),'almaty');
    $moySkladProfitMonth4Astana   = $moyskladModel->getProfitByPeriod($month4DateFrom,$month4DateTo,array('all'),'astana');
    $moySkladProfitMonth5Almaty   = $moyskladModel->getProfitByPeriod($month5DateFrom,$month5DateTo,array('all'),'almaty');
    $moySkladProfitMonth5Astana   = $moyskladModel->getProfitByPeriod($month5DateFrom,$month5DateTo,array('all'),'astana');
    $moySkladProfitMonth6Almaty   = $moyskladModel->getProfitByPeriod($month6DateFrom,$month6DateTo,array('all'),'almaty');
    $moySkladProfitMonth6Astana   = $moyskladModel->getProfitByPeriod($month6DateFrom,$month6DateTo,array('all'),'astana');

    $conditionalW1 = new Conditional();
    $conditionalW1->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalW1->setOperatorType(Conditional::OPERATOR_BETWEEN);
    $conditionalW1->setStopIfTrue(true);
    $conditionalW1->addCondition('7');
    $conditionalW1->addCondition('14');
    $conditionalW1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalW1->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c');
    $conditionalW1->getStyle()->getFont()->getColor()->setARGB('9c5700');

    $conditionalW2 = new Conditional();
    $conditionalW2->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalW2->setOperatorType(Conditional::OPERATOR_LESSTHAN);
    $conditionalW2->setStopIfTrue(true);
    $conditionalW2->addCondition(7);
    $conditionalW2->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalW2->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
    $conditionalW2->getStyle()->getFont()->getColor()->setARGB('9c0006');

    $conditionalX1 = new Conditional();
    $conditionalX1->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalX1->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
    $conditionalX1->setStopIfTrue(true);
    $conditionalX1->addCondition(0);
    $conditionalX1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalX1->getStyle()->getFill()->getStartColor()->setARGB('c6efce');
    $conditionalX1->getStyle()->getFont()->getColor()->setARGB('2c612e');

    $conditionalAG1 = new Conditional();
    $conditionalAG1->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
    $conditionalAG1->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
    $conditionalAG1->setStopIfTrue(true);
    $conditionalAG1->setText('Проверка');
    $conditionalAG1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalAG1->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
    $conditionalAG1->getStyle()->getFont()->getColor()->setARGB('9c0006');

    $conditionalAH1 = new Conditional();
    $conditionalAH1->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
    $conditionalAH1->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
    $conditionalAH1->setStopIfTrue(true);
    $conditionalAH1->setText('Мало');
    $conditionalAH1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalAH1->getStyle()->getFill()->getStartColor()->setARGB('c6efce');
    $conditionalAH1->getStyle()->getFont()->getColor()->setARGB('2c612e');

    $conditionalY1 = new Conditional();
    $conditionalY1->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalY1->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
    $conditionalY1->setStopIfTrue(true);
    $conditionalY1->addCondition(100);
    $conditionalY1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalY1->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
    $conditionalY1->getStyle()->getFont()->getColor()->setARGB('9c0006');

    $conditionalY2 = new Conditional();
    $conditionalY2->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalY2->setOperatorType(Conditional::OPERATOR_LESSTHAN);
    $conditionalY2->setStopIfTrue(true);
    $conditionalY2->addCondition(80);
    $conditionalY2->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalY2->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c');
    $conditionalY2->getStyle()->getFont()->getColor()->setARGB('9c5700');

    $sheet->setCellValue('F1', 'Алматы');
    $sheet->mergeCells('F1:S1');
    $sheet->setCellValue('T1', 'Астана');
    $sheet->mergeCells('T1:AC1');

    $sheet->setCellValue('F2', $month1DateTo->format('d.m.Y'));
    $sheet->setCellValue('G2', $month2DateTo->format('d.m.Y'));
    $sheet->setCellValue('H2', $month3DateTo->format('d.m.Y'));
    $sheet->setCellValue('I2', $month4DateTo->format('d.m.Y'));
    $sheet->setCellValue('J2', $month5DateTo->format('d.m.Y'));
    $sheet->setCellValue('K2', $month6DateTo->format('d.m.Y'));

    $sheet->setCellValue('T2', $month1DateTo->format('d.m.Y'));
    $sheet->setCellValue('U2', $month2DateTo->format('d.m.Y'));
    $sheet->setCellValue('V2', $month3DateTo->format('d.m.Y'));
    $sheet->setCellValue('W2', $month4DateTo->format('d.m.Y'));
    $sheet->setCellValue('X2', $month5DateTo->format('d.m.Y'));
    $sheet->setCellValue('Y2', $month6DateTo->format('d.m.Y'));

    $sheet->setCellValue('A2', 'Код');
    $sheet->setCellValue('B2', 'Артикул');
    $sheet->setCellValue('C2', 'Наименование');
    $sheet->setCellValue('D2', 'Бренд');
    $sheet->setCellValue('E2', 'Мастер-пак');

    $sheet->setCellValue('N2', 'Категория по количеству');
    $sheet->setCellValue('O2', 'Товарный запас достаточный');
    $sheet->setCellValue('P2', 'Ожидание в Алматы');
    $sheet->setCellValue('Q2', 'Остаток в Алматы');
    $sheet->setCellValue('R2', 'Продажи за 6 мес в Алматы');
    $sheet->setCellValue('S2', 'Min товарный запас Алматы');

    $sheet->setCellValue('Z2', 'Продажи за 6 мес в Астане');
    $sheet->setCellValue('AA2', 'Ожидание в Астане');

    $sheet->setCellValue('AB2', 'Остаток в Астане');
    $sheet->setCellValue('AC2', 'Текущий запас в днях в Астане');
    $sheet->setCellValue('AD2', 'Переместить данные продаж Астана');
    $sheet->setCellValue('AE2', 'Рекомендации к отправке');
    $sheet->setCellValue('AF2', 'К перемещению');
    $sheet->setCellValue('AG2', 'Проверка продаж Астана / Алматы');
    $sheet->setCellValue('AH2', 'Запас в днях с учетом перемещения');
    $sheet->setCellValue('AI2', 'Количество мастер-паков');
    $sheet->setCellValue('AJ2', 'ВЭД Объем');
    $sheet->setCellValue('AK2', 'Кол-во палет, шт');

    $l = 4;
    foreach ($moySkladProducts as $product) {
      if(!property_exists($product,'article')){ continue; }
      $productData = self::getProductFromCatalogueJson($assortiment, str_replace('?expand=supplier','',$product->meta->href));
      if(!$productData){ continue; }
      if(!property_exists($productData,'attributes')){ continue; }

      $productRemains = self::getReportProductRemains($productsRemains,$productData->id);

      switch($productData->meta->type){
        case 'product':
        case 'bundle':
          break;
        default:
          continue 2;
      }

      $brand = $moyskladModel->getProductAttribute($productData->attributes,'a51f0b60-f6be-11eb-0a80-081c000ff2c4');
      if($brand){ $brand = $brand->value->name; } else { $brand = 'Не определен'; }

      $provider = $moyskladModel->getProductAttribute($productData->attributes,'5e7712e4-c642-11ee-0a80-0b6400041569');
      if($provider){ $provider = $provider->value; } else { $provider = 'Не определен'; }

      $masterpack = $moyskladModel->getProductAttribute($productData->attributes,'ada19664-8dba-11ed-0a80-06c701018819');
      if($masterpack){ $masterpack = $masterpack->value; } else { $masterpack = 'Не определен'; }

      $vadVolume = $moyskladModel->getProductAttribute($productData->attributes,'4f418c2f-21a5-11ef-0a80-0871005beb58');
      if($vadVolume){ $vadVolume = str_replace(',','.',$vadVolume->value); } else { $vadVolume = 'Не указан'; }

      $month1QuantityAlmaty       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth1Almaty,$product->code,'quantity');
      $month1QuantityAstana       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth1Astana,$product->code,'quantity');
      $month2QuantityAlmaty       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth2Almaty,$product->code,'quantity');
      $month2QuantityAstana       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth2Astana,$product->code,'quantity');
      $month3QuantityAlmaty       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth3Almaty,$product->code,'quantity');
      $month3QuantityAstana       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth3Astana,$product->code,'quantity');
      $month4QuantityAlmaty       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth4Almaty,$product->code,'quantity');
      $month4QuantityAstana       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth4Astana,$product->code,'quantity');
      $month5QuantityAlmaty       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth5Almaty,$product->code,'quantity');
      $month5QuantityAstana       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth5Astana,$product->code,'quantity');
      $month6QuantityAlmaty       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth6Almaty,$product->code,'quantity');
      $month6QuantityAstana       = self::calculateProfitSalesForProductByArr($moySkladProfitMonth6Astana,$product->code,'quantity');

      $sheet->setCellValue('A' . $l, $product->code)->getStyle('A' . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
      $sheet->setCellValue('B' . $l, $product->article)->getStyle('A' . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
      $sheet->setCellValue('C' . $l, $product->name);
      $sheet->setCellValue('D' . $l, $brand);
      $sheet->setCellValue('E' . $l, $masterpack);

      $sheet->setCellValue('F' . $l, $month1QuantityAlmaty);
      $sheet->setCellValue('G' . $l, $month2QuantityAlmaty);
      $sheet->setCellValue('H' . $l, $month3QuantityAlmaty);
      $sheet->setCellValue('I' . $l, $month4QuantityAlmaty);
      $sheet->setCellValue('J' . $l, $month5QuantityAlmaty);
      $sheet->setCellValue('K' . $l, $month6QuantityAlmaty);

      $sheet->setCellValue('L' . $l, '=ROUND(R' . $l . '/$R$3,4)');
      $sheet->setCellValue('M' . $l, '=ROUND(M' . ($l-1) . '+L' . $l . ',4)');
      $sheet->setCellValue('N' . $l, '=IF(M' . $l . ' <= 0.8, "A", IF(M' . $l . ' <= 0.95, "B", "C"))');
      $sheet->setCellValue('O' . $l, '=IF(S' . $l . ' > Q' . $l . ',"⛔️","✅")');

      $sheet->setCellValue('P' . $l, ($product->inTransit));
      $sheet->setCellValue('Q' . $l, ($productRemains->almaty+$productRemains->success));
      $sheet->setCellValue('R' . $l, '=SUM(F' . $l . ':K' . $l . ')');
      $sheet->setCellValue('S' . $l, '=IFERROR(ROUNDUP(IF(M' . $l . '=0,IF((F' . $l . '+G' . $l . '+H' . $l . ')<(I' . $l . '+J' . $l . '+K' . $l . '),((I' . $l . '+J' . $l . '+K' . $l . ')*1.15)/2,(SUM(F' . $l . ':K' . $l . ')/6)*1.5)*1.2,IF((F' . $l . '+G' . $l . '+H' . $l . ')<(I' . $l . '+J' . $l . '+K' . $l . '),((I' . $l . '+J' . $l . '+K' . $l . ')*1.15)/2,(SUM(F' . $l . ':K' . $l . ')/6)*1.5)),0),0.1)');

      $sheet->setCellValue('T' . $l, $month1QuantityAstana);
      $sheet->setCellValue('U' . $l, $month2QuantityAstana);
      $sheet->setCellValue('V' . $l, $month3QuantityAstana);
      $sheet->setCellValue('W' . $l, $month4QuantityAstana);
      $sheet->setCellValue('X' . $l, $month5QuantityAstana);
      $sheet->setCellValue('Y' . $l, $month6QuantityAstana);

      $sheet->setCellValue('Z' . $l, '=SUM(T' . $l . ':Y' . $l . ')');
      $sheet->setCellValue('AA' . $l, ($productRemains->wayAstana));
      $sheet->setCellValue('AB' . $l, $productRemains->astana);
      $sheet->setCellValue('AC' . $l, '=IF((Y' . $l . '+X' . $l . '+W' . $l . ') <= 0,1, IF((((AA' . $l . '+AA' . $l . ')/(Y' . $l . '+X' . $l . '+W' . $l . '))*90) <= 0, 1, ROUND( ((AA' . $l . '+AA' . $l . ')/(Y' . $l . '+X' . $l . '+W' . $l . '))*90, 2) ))');
      $sheet->setCellValue('AD' . $l, '=IFERROR(ROUNDUP( IF(Q' . $l . '/S' . $l . '<0.5,0,(IF(Q' . $l . '<=0,0,IF(P' . $l . '>0,IF((Q' . $l . '-S' . $l . ')<0,IF((AB' . $l . '+AC' . $l . ')<=0,IF((U' . $l . '+V' . $l . '+W' . $l . ')<(X' . $l . '+Y' . $l . '+AA' . $l . '),IF(((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))*1.15<=0,0,((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))/3),IF((SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))*1.15<=0,0,((SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))*1.15))/3),IF((U' . $l . '+V' . $l . '+W' . $l . ')<(X' . $l . '+Y' . $l . '+AA' . $l . '),IF((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . ')<0,0,((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))/3),IF(SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . ')<0,0,(SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))/3)))*0.5,IF((AB' . $l . '+AC' . $l . ')<=0,IF((U' . $l . '+V' . $l . '+W' . $l . ')<(X' . $l . '+Y' . $l . '+AA' . $l . '),IF(((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))*1.15<=0,0,((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))/3),IF((SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))*1.15<=0,0,((SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))*1.15))/3),IF((U' . $l . '+V' . $l . '+W' . $l . ')<(X' . $l . '+Y' . $l . '+AA' . $l . '),IF((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . ')<0,0,((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))/3),IF(SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . ')<0,0,(SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))/3)))),IF((Q' . $l . '-S' . $l . ')<0,IF((AB' . $l . '+AC' . $l . ')<=0,IF((U' . $l . '+V' . $l . '+W' . $l . ')<(X' . $l . '+Y' . $l . '+AA' . $l . '),IF(((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))*1.15<=0,0,((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))/3),IF((SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))*1.15<=0,0,((SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))*1.15))/3),IF((U' . $l . '+V' . $l . '+W' . $l . ')<(X' . $l . '+Y' . $l . '+AA' . $l . '),IF((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . ')<0,0,((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))/3),IF(SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . ')<0,0,(SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))/3)))*0.3,IF((AB' . $l . '+AC' . $l . ')<=0,IF((U' . $l . '+V' . $l . '+W' . $l . ')<(X' . $l . '+Y' . $l . '+AA' . $l . '),IF(((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))*1.15<=0,0,((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))/3),IF((SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))*1.15<=0,0,((SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))*1.15))/3),IF((U' . $l . '+V' . $l . '+W' . $l . ')<(X' . $l . '+Y' . $l . '+AA' . $l . '),IF((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . ')<0,0,((X' . $l . '+Y' . $l . '+AA' . $l . ')-(AB' . $l . '+AC' . $l . '))/3),IF(SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . ')<0,0,(SUM(U' . $l . ':AA' . $l . ')/180*90-(AB' . $l . '+AC' . $l . '))/3)))))))),0),0.001)');
      $sheet->setCellValue('AE' . $l, '=IF(ROUND(SUM(I' . $l . ':K' . $l . ')/3*0.4,0)-AB' . $l . '<=0,0,ROUND(SUM(I' . $l . ':K' . $l . ')/3*0.4,0)-AB' . $l . ')');
      $sheet->setCellValue('AF' . $l, '=ROUND(IF(S' . $l . '>Q' . $l . ',IF(S' . $l . '*0.75>Q' . $l . ',IF(S' . $l . '*0.5>Q' . $l . ',IF(S' . $l . '*0.3>Q' . $l . ',0,AE' . $l . '*0.3),AE' . $l . '*0.4),AE' . $l . '*0.8),AE' . $l . '),0)');
      $sheet->setCellValue('AG' . $l, '=IFERROR(IF(AF' . $l . '=0,"",IF((1-AF' . $l . '/AD' . $l . ')>0.4,"Проверка",IF((AF' . $l . '/AD' . $l . ')>1.4,"Проверка",""))),"Проверка")');
      $sheet->setCellValue('AH' . $l, '=IF(( Y' . $l . '+X' . $l . '+W' . $l . ') <= 0, "Мало продаж", ROUND(  (((AB' . $l . '+AA' . $l . ')/(Y' . $l . '+X' . $l . '+W' . $l . '))*90)+((AF' . $l . '/(Y' . $l . '+X' . $l . '+W' . $l . ')) * 90), 2))');
      $sheet->setCellValue('AI' . $l, '=IFERROR((AF' . $l . '/E' . $l . '),0)');
      $sheet->setCellValue('AJ' . $l, $vadVolume);
      $sheet->setCellValue('AK' . $l, '=IFERROR(ROUND((AD' . $l . '*AJ' . $l . '),2),0)');

      $sheet->getStyle('AC' . $l)->setConditionalStyles([$conditionalW1, $conditionalW2]);
      $sheet->getStyle('AD' . $l)->setConditionalStyles([$conditionalX1]);
      $sheet->getStyle('AE' . $l)->setConditionalStyles([$conditionalX1]);
      $sheet->getStyle('AF' . $l)->setConditionalStyles([$conditionalX1]);
      $sheet->getStyle('AG' . $l)->setConditionalStyles([$conditionalAG1]);
      $sheet->getStyle('AH' . $l)->setConditionalStyles([$conditionalAH1,$conditionalY2,$conditionalY1]);

      $l++;
    }

    // Суммирование
    foreach (range('F','K') as $sumCell) {
      $sheet->setCellValue($sumCell . '3', '=SUM(' . $sumCell . '4:' . $sumCell . $l . ')');
    }
    foreach (range('P','Y') as $sumCell) {
      $sheet->setCellValue($sumCell . '3', '=SUM(' . $sumCell . '4:' . $sumCell . $l . ')');
    }
    $start = Coordinate::columnIndexFromString('Z');   // 24
    $end   = Coordinate::columnIndexFromString('AF');  // 159
    for ($i = $start; $i <= $end; $i++) {
      $sumCell = Coordinate::stringFromColumnIndex($i);
      $sheet->setCellValue($sumCell . '3', '=SUM(' . $sumCell . '4:' . $sumCell . $l . ')');
    }
    $sheet->setCellValue('AK3', '=SUM(AK4:AK' . $l . ')');

    $sheet->getColumnDimension('A')->setWidth('10');
    $sheet->getColumnDimension('B')->setWidth('15');
    $sheet->getColumnDimension('C')->setWidth('27');
    $sheet->getColumnDimension('D')->setWidth('15');
    $sheet->getColumnDimension('E')->setWidth('14');
    $sheet->getColumnDimension('X')->setWidth('10');
    $sheet->getColumnDimension('Y')->setWidth('13');
    $sheet->getColumnDimension('Z')->setWidth('13');
    $sheet->getColumnDimension('AA')->setWidth('10');
    $sheet->getColumnDimension('AB')->setWidth('12');
    $sheet->getColumnDimension('AC')->setWidth('12');
    $sheet->getColumnDimension('AD')->setWidth('10');
    $sheet->getColumnDimension('AE')->setWidth('10');
    $sheet->getColumnDimension('AF')->setWidth('14');
    $sheet->getColumnDimension('AG')->setWidth('14');
    $sheet->getColumnDimension('AH')->setWidth('14');
    $sheet->getColumnDimension('AI')->setWidth('12');
    $sheet->getColumnDimension('AJ')->setWidth('12');
    $sheet->getColumnDimension('AK')->setWidth('12');

    foreach(range('F','W') as $columnID) {
      $sheet->getColumnDimension($columnID)->setWidth('10');
    }

    $sheet->getStyle('A2:AK' . $l)->getAlignment()->setWrapText(true)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('L4:L' . $l)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

    $grayStyle =  [
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'c0c0c0']
                    ],
                  ];

    $lightGreenStyle =  [
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'e2efda']
                    ],
                  ];

    $greenStyle =  [
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'c6efce']
                    ],
                  ];

    $purpleStyle =  [
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'ccc0da']
                    ],
                  ];

    $borderArray = [
                  'borders' => [
                      'allBorders' => [
                          'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                          'color' => ['argb' => 'FF000000'],
                      ],
                  ],
              ];

    $sheet->getStyle('A1:AK' . ($l-1))->applyFromArray($borderArray);
    $sheet->getStyle('A2:D2')->applyFromArray($grayStyle);
    $sheet->getStyle('E2')->applyFromArray($lightGreenStyle);
    $sheet->getStyle('P2:R2')->applyFromArray($lightGreenStyle);
    $sheet->getStyle('S2')->applyFromArray($purpleStyle);
    $sheet->getStyle('Z2')->applyFromArray($purpleStyle);
    $sheet->getStyle('AC2')->applyFromArray($purpleStyle);

    $sheet->getColumnDimension('F')->setOutlineLevel(1);
    $sheet->getColumnDimension('G')->setOutlineLevel(1);
    $sheet->getColumnDimension('H')->setOutlineLevel(1);
    $sheet->getColumnDimension('I')->setOutlineLevel(1);
    $sheet->getColumnDimension('J')->setOutlineLevel(1);
    $sheet->getColumnDimension('K')->setOutlineLevel(1);
    $sheet->getColumnDimension('L')->setOutlineLevel(1);
    $sheet->getColumnDimension('M')->setOutlineLevel(1);

    $sheet->getColumnDimension('F')->setVisible(false);
    $sheet->getColumnDimension('G')->setVisible(false);
    $sheet->getColumnDimension('H')->setVisible(false);
    $sheet->getColumnDimension('I')->setVisible(false);
    $sheet->getColumnDimension('J')->setVisible(false);
    $sheet->getColumnDimension('K')->setVisible(false);
    $sheet->getColumnDimension('L')->setVisible(false);
    $sheet->getColumnDimension('M')->setVisible(false);
    // $sheet->getColumnDimension('M')->setVisible(false);

    $sheet->getColumnDimension('T')->setOutlineLevel(1);
    $sheet->getColumnDimension('U')->setOutlineLevel(1);
    $sheet->getColumnDimension('V')->setOutlineLevel(1);
    $sheet->getColumnDimension('W')->setOutlineLevel(1);
    $sheet->getColumnDimension('X')->setOutlineLevel(1);
    $sheet->getColumnDimension('Y')->setOutlineLevel(1);

    $sheet->getColumnDimension('T')->setVisible(false);
    $sheet->getColumnDimension('U')->setVisible(false);
    $sheet->getColumnDimension('V')->setVisible(false);
    $sheet->getColumnDimension('W')->setVisible(false);
    $sheet->getColumnDimension('X')->setVisible(false);
    $sheet->getColumnDimension('Y')->setVisible(false);

    $sheet->getStyle('A1:AK3')->getFont()->setBold(true);
    $sheet->getStyle('A2:A2')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A1:AK3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C4:D' . $l)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('A4:AK' . $l)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->freezePane('A4');
    $sheet->freezePane('D3');

    $writer = new Xlsx($spreadsheet);
    $writer->save(__DIR__ . '/../web/tmpDocs/' . $fileName);
    $xlsData = ob_get_contents();
    ob_end_clean();
    return (object)array('file' => $fileName);
  }

  public function generateComissionaireReport($list,$allSalesData)
  {
    $moyskladModel  = new Moysklad();

    $fileName       = 'ОтчетКомиссионера_' . date('d.m.Y_H.i.s') . '.xlsx';
    $spreadsheet    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet          = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'Наименование');
    $sheet->mergeCells('A1:A2');
    $sheet->setCellValue('B1', 'Код');
    $sheet->mergeCells('B1:B2');
    $sheet->setCellValue('C1', 'Артикул');
    $sheet->mergeCells('C1:C2');
    $sheet->setCellValue('D1', 'Передано всего, шт.');
    $sheet->mergeCells('D1:D2');
    $sheet->setCellValue('E1', 'Передано за отчетный период, шт.');
    $sheet->mergeCells('E1:E2');
    $sheet->setCellValue('F1', 'Остаток на начало периода');
    $sheet->mergeCells('F1:F2');
    $sheet->setCellValue('G1', 'Реализовано, шт.');
    $sheet->mergeCells('G1:M1');
    $sheet->setCellValue('N1', 'Сумма реализации');
    $sheet->mergeCells('N1:T1');
    $sheet->setCellValue('U1', 'Возвраты продавцу');
    $sheet->mergeCells('U1:V1');
    $sheet->setCellValue('W1', 'Остаток у комиссионера');
    $sheet->mergeCells('W1:AF1');

    $sheet->setCellValue('G2', '1 месяц с 1 по 31 число');
    $sheet->setCellValue('H2', '2 месяц с 1 по 31 число');
    $sheet->setCellValue('I2', '3 месяц с 1 по 31 число');
    $sheet->setCellValue('J2', '4 месяц с 1 по 31 число');
    $sheet->setCellValue('K2', '5 месяц с 1 по 31 число');
    $sheet->setCellValue('L2', '6 месяц с 1 по 31 число');
    $sheet->setCellValue('M2', 'Всего за период, шт.');

    $sheet->setCellValue('N2', '1 месяц с 1 по 31 число');
    $sheet->setCellValue('O2', '2 месяц с 1 по 31 число');
    $sheet->setCellValue('P2', '3 месяц с 1 по 31 число');
    $sheet->setCellValue('Q2', '4 месяц с 1 по 31 число');
    $sheet->setCellValue('R2', '5 месяц с 1 по 31 число');
    $sheet->setCellValue('S2', '6 месяц с 1 по 31 число');
    $sheet->setCellValue('T2', 'Сумма за период');

    $sheet->setCellValue('U2', 'Кол-во');
    $sheet->setCellValue('V2', 'Сумма');

    $sheet->setCellValue('W2', 'Кол-во');
    $sheet->setCellValue('X2', 'Оборачиваемость в днях');
    $sheet->setCellValue('Y2', 'Что делать с товаром');
    $sheet->setCellValue('Z2', 'Необходимый товарный запас на 60 дней');
    $sheet->setCellValue('AA2', 'Поставить комиссионеру');
    $sheet->setCellValue('AB2', 'Вернуть на склад');
    $sheet->setCellValue('AC2', 'Ср. срок продажи в днях');
    $sheet->setCellValue('AD2', 'Себестоимсоть SKU');
    $sheet->setCellValue('AE2', 'Сумма себестоимости');
    $sheet->setCellValue('AF2', 'План. сумма продаж');

    // FORMAT "Y" CELL COLORS
    $conditionalY1 = new Conditional();
    $conditionalY1->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
    $conditionalY1->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
    $conditionalY1->setStopIfTrue(true);
    $conditionalY1->setText('Возврат на склад или Согласовать акцию');
    $conditionalY1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalY1->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
    $conditionalY1->getStyle()->getFont()->getColor()->setARGB('9c0006');

    $conditionalY2 = new Conditional();
    $conditionalY2->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
    $conditionalY2->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
    $conditionalY2->setStopIfTrue(true);
    $conditionalY2->setText('Проверить товарную выкладку. Товар без движения > 90 дней. Согласовать акцию');
    $conditionalY2->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalY2->getStyle()->getFill()->getStartColor()->setARGB('fff2cc');
    $conditionalY2->getStyle()->getFont()->getColor()->setARGB('383100');

    // FORMAT "X" CELL COLORS
    $conditionalX1 = new Conditional();
    $conditionalX1->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
    $conditionalX1->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
    $conditionalX1->setStopIfTrue(true);
    $conditionalX1->setText('Товар без движения');
    $conditionalX1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalX1->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c');
    $conditionalX1->getStyle()->getFont()->getColor()->setARGB('9c5700');

    // FORMAT "Z" CELL COLORS
    $conditionalZ1 = new Conditional();
    $conditionalZ1->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalZ1->setOperatorType(Conditional::OPERATOR_LESSTHANOREQUAL);
    $conditionalZ1->setStopIfTrue(true);
    $conditionalZ1->addCondition(0);
    $conditionalZ1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalZ1->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
    $conditionalZ1->getStyle()->getFont()->getColor()->setARGB('9c0006');

    // FORMAT "AA" CELL COLORS
    $conditionalAA1 = new Conditional();
    $conditionalAA1->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalAA1->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
    $conditionalAA1->setStopIfTrue(true);
    $conditionalAA1->addCondition(0);
    $conditionalAA1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalAA1->getStyle()->getFill()->getStartColor()->setARGB('c6efce');
    $conditionalAA1->getStyle()->getFont()->getColor()->setARGB('2c612e');

    // FORMAT "AB" CELL COLORS
    $conditionalAB1 = new Conditional();
    $conditionalAB1->setConditionType(Conditional::CONDITION_CELLIS);
    $conditionalAB1->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
    $conditionalAB1->setStopIfTrue(true);
    $conditionalAB1->addCondition(0);
    $conditionalAB1->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalAB1->getStyle()->getFill()->getStartColor()->setARGB('ffeb9c');
    $conditionalAB1->getStyle()->getFont()->getColor()->setARGB('9c5700');

    $conditionalAB2 = new Conditional();
    $conditionalAB2->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
    $conditionalAB2->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
    $conditionalAB2->setStopIfTrue(true);
    $conditionalAB2->setText(' ');
    $conditionalAB2->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
    $conditionalAB2->getStyle()->getFill()->getStartColor()->setARGB('ffffff');
    $conditionalAB2->getStyle()->getFont()->getColor()->setARGB('000000');

    $l = 3;
    foreach ($list as $agentId => $coms) {
      $agentData = $moyskladModel->getHrefData('https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $agentId);
      $sheet->setCellValue('A' . $l, $agentData->name);
      $sheet->getStyle('A' . $l . ':AF' . $l)->getFont()->setBold(true);
      $sheet->getStyle('A' . $l . ':AF' . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('92d050');
      $l++;

      foreach ($coms['grouppedProducts'] as $positionId => $positionMonths) {
        if($positionId == 'otherProducts'){ continue; }
        $posProduct = $moyskladModel->getHrefData('https://api.moysklad.ru/api/remap/1.2/entity/product/' . $positionId);

        $sheet->setCellValue('A' . $l, $posProduct->name);
        $sheet->setCellValue('B' . $l, $posProduct->code);
        $sheet->setCellValue('C' . $l, $posProduct->article . ' ');

        $fullPeriodReportQuantity = 0;
        foreach($positionMonths['demandquantities'] AS $dmonth => $dquant){
          if($dmonth == 'allFromBeforeCentury'){ continue; }
          $fullPeriodReportQuantity += $dquant;
        }
        $sheet->setCellValue('D' . $l, $positionMonths['demandquantities']['allFromBeforeCentury']);
        $sheet->setCellValue('E' . $l, $fullPeriodReportQuantity);

        foreach ($positionMonths as $monthid => $sales) {
          if($monthid == 'demandquantities'){ continue; }
          $salesData = self::calculateComissionaireSalesQuantityAndTotalSum($sales);

          $quantityLetter = false;
          $sumLetter = false;
          switch($monthid){
            case 'month_1':
              $quantityLetter = 'G';
              $sumLetter = 'N';
              break;
            case 'month_2':
              $quantityLetter = 'H';
              $sumLetter = 'O';
              break;
            case 'month_3':
              $quantityLetter = 'I';
              $sumLetter = 'P';
              break;
            case 'month_4':
              $quantityLetter = 'J';
              $sumLetter = 'Q';
              break;
            case 'month_5':
              $quantityLetter = 'K';
              $sumLetter = 'R';
              break;
            case 'month_6':
              $quantityLetter = 'L';
              $sumLetter = 'S';
              break;
          }
          $sheet->setCellValue($quantityLetter . $l, $salesData->quantity);
          $sheet->setCellValue($sumLetter . $l, $salesData->total);
        }
        $sheet->setCellValue('M' . $l, '=SUM(G' . $l . ':L' . $l . ')');
        $sheet->setCellValue('T' . $l, '=SUM(N' . $l . ':S' . $l . ')');

        $sheet->setCellValue('W' . $l, '=' . $allSalesData[$agentId][$positionId]->quantity . ' - M' . $l);

        $sheet->setCellValue('X' . $l, '=IF(M' . $l . '=0,"Товар без движения",(((F' . $l . '+W' . $l . ')/2)*180)/M' . $l . ')');
        $sheet->getStyle('X' . $l)->setConditionalStyles([$conditionalX1]);

        $sheet->setCellValue('Y' . $l, '=IF(M' . $l . '=0,"Возврат на склад или Согласовать акцию",IF((J' . $l . '+K' . $l . '+L' . $l . ')=0,"Проверить товарную выкладку. Товар без движения > 90 дней. Согласовать акцию"," "))');
        $sheet->getStyle('Y' . $l)->setConditionalStyles([$conditionalY1, $conditionalY2]);

        $sheet->setCellValue('Z' . $l, '=IF(ROUND(IF((L' . $l . '+K' . $l . '+J' . $l . ')>(I' . $l . '+H' . $l . '+G' . $l . '),IF((I' . $l . '+H' . $l . '+G' . $l . ')=0,((L' . $l . '+K' . $l . '+J' . $l . ')/90*60),((L' . $l . '+K' . $l . '+J' . $l . ')/90*60)*(L' . $l . '+K' . $l . '+J' . $l . ')/(I' . $l . '+H' . $l . '+G' . $l . ')),(L' . $l . '+K' . $l . '+J' . $l . ')/90*60),0)=1,2,ROUND(IF((L' . $l . '+K' . $l . '+J' . $l . ')>(I' . $l . '+H' . $l . '+G' . $l . '),IF((I' . $l . '+H' . $l . '+G' . $l . ')=0,((L' . $l . '+K' . $l . '+J' . $l . ')/90*60),((L' . $l . '+K' . $l . '+J' . $l . ')/90*60)*(L' . $l . '+K' . $l . '+J' . $l . ')/(I' . $l . '+H' . $l . '+G' . $l . ')),(L' . $l . '+K' . $l . '+J' . $l . ')/90*60),0))');
        $sheet->getStyle('Z' . $l)->setConditionalStyles([$conditionalZ1]);

        $sheet->setCellValue('AA' . $l, '=IF(Z' . $l . '<W' . $l . ',0,Z' . $l . '-W' . $l . ')');
        $sheet->getStyle('AA' . $l)->setConditionalStyles([$conditionalAA1]);

        $sheet->setCellValue('AB' . $l, '=IF(W' . $l . '-Z' . $l . '<=0," ",W' . $l . '-Z' . $l . ')');
        $sheet->getStyle('AB' . $l)->setConditionalStyles([$conditionalAB1,$conditionalAB2]);

        $sheet->setCellValue('AD' . $l, '=IF(W' . $l . '=0," ",AE' . $l . '/W' . $l . ')');

        $l++;
      }

      foreach ($coms['grouppedProducts']['otherProducts'] AS $otherProductId => $otherProduct) {
        $posProduct = $moyskladModel->getHrefData('https://api.moysklad.ru/api/remap/1.2/entity/product/' . $otherProductId);

        $sheet->setCellValue('A' . $l, $posProduct->name);
        $sheet->setCellValue('B' . $l, $posProduct->code);
        $sheet->setCellValue('C' . $l, $posProduct->article . ' ');

        $fullPeriodReportQuantity = 0;
        foreach($otherProduct['demandquantities'] AS $dmonth => $dquant){
          if($dmonth == 'allFromBeforeCentury'){ continue; }
          $fullPeriodReportQuantity += $dquant;
        }
        $sheet->setCellValue('D' . $l, $otherProduct['demandquantities']['allFromBeforeCentury']);
        $sheet->setCellValue('E' . $l, $fullPeriodReportQuantity);

        $sheet->getStyle('A' . $l . ':AF' . $l)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('e4e4e4');
        $l++;
      }
    }

    foreach(range('A','F') as $columnID) {
      $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    $sheet->getColumnDimension('G')->setWidth('15');
    $sheet->getColumnDimension('H')->setWidth('15');
    $sheet->getColumnDimension('I')->setWidth('15');
    $sheet->getColumnDimension('J')->setWidth('15');
    $sheet->getColumnDimension('K')->setWidth('15');
    $sheet->getColumnDimension('L')->setWidth('15');
    $sheet->getColumnDimension('M')->setWidth('15');
    $sheet->getColumnDimension('N')->setWidth('15');
    $sheet->getColumnDimension('O')->setWidth('15');
    $sheet->getColumnDimension('P')->setWidth('15');
    $sheet->getColumnDimension('Q')->setWidth('15');
    $sheet->getColumnDimension('R')->setWidth('15');
    $sheet->getColumnDimension('S')->setWidth('15');
    $sheet->getColumnDimension('T')->setWidth('15');
    $sheet->getColumnDimension('X')->setWidth('20');
    $sheet->getColumnDimension('Y')->setWidth('38');
    $sheet->getColumnDimension('Z')->setWidth('25');
    $sheet->getColumnDimension('AA')->setWidth('25');
    $sheet->getColumnDimension('AB')->setWidth('25');
    $sheet->getColumnDimension('AC')->setWidth('25');
    $sheet->getColumnDimension('AD')->setWidth('25');
    $sheet->getColumnDimension('AE')->setWidth('35');
    $sheet->getColumnDimension('AE')->setWidth('35');
    $sheet->getColumnDimension('AF')->setWidth('35');

    $titleStyle1 = [
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                      'wrapText' => true
                    ],
                    'font' => [
                      'bold' => true
                    ]
                  ];

    $titleStyle2 =  [
                    'alignment' => [
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'font' => [
                      'bold' => true
                    ]
                  ];

    $blueStyle =  [
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => '00b0f0']
                    ],
                  ];

    $grayStyle =  [
                    'borders' => [
                      'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'b2b2b2'],
                      ],
                    ],
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'ddebf7']
                    ],
                  ];

    $lightblueStyle =  [
                    'borders' => [
                      'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'b2b2b2'],
                      ],
                    ],
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                      'wrapText' => true
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'bdd7ee']
                    ],
                  ];

    $lightgreenStyle =  [
                    'borders' => [
                      'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'b2b2b2'],
                      ],
                    ],
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                      'wrapText' => true
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'c6e0b4']
                    ],
                  ];

    $lightredStyle =  [
                    'borders' => [
                      'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'b2b2b2'],
                      ],
                    ],
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                      'wrapText' => true
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'fce4d6']
                    ],
                  ];

    $lightyellowStyle =  [
                    'borders' => [
                      'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'b2b2b2'],
                      ],
                    ],
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                      'wrapText' => true
                    ],
                    'font' => [
                      'bold' => true
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['argb' => 'fff2cc']
                    ],
                  ];

    $dataStyle =  [
                    'borders' => [
                      'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'b2b2b2'],
                      ],
                    ],
                    'alignment' => [
                      'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                      'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                      'wrapText' => true
                    ]
                  ];

    $sheet->getStyle('A1:C1')->applyFromArray($blueStyle);
    $sheet->getStyle('D1:F1')->applyFromArray($grayStyle);
    $sheet->getStyle('G1:M2')->applyFromArray($lightblueStyle);
    $sheet->getStyle('N1:T2')->applyFromArray($lightgreenStyle);
    $sheet->getStyle('U1:V2')->applyFromArray($lightredStyle);
    $sheet->getStyle('W1:AF2')->applyFromArray($lightyellowStyle);
    $sheet->getStyle('A4:AF' . $l)->applyFromArray($dataStyle);

    $sheet->freezePane('A3');

    $writer = new Xlsx($spreadsheet);
    $writer->save(__DIR__ . '/../web/tmpDocs/' . $fileName);
    $xlsData = ob_get_contents();
    ob_end_clean();
    return (object)array('file' => $fileName);
  }

  public function getRealizeSalesFiledata($file)
  {
    $spreadsheet  = IOFactory::load($file);
    $sheet        = $spreadsheet->getActiveSheet();
    $data         = $sheet->toArray(null, true, true, true);

    $c = 0;
    $response = [];
    foreach ($data as $row) {
      if($c > 0){
        $response[] = $row;
      }
      $c++;
    }

    return $response;
  }

  public function createRealizeReport($contragent,$year,$file)
  {
    $moyskladModel  = new Moysklad();

    $fileName       = 'ОтчетРеализация_' . date('d.m.Y_H.i.s') . '.xlsx';
    $spreadsheet    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet          = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'Код');
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->setCellValue('B1', 'Штрих-код');
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->setCellValue('C1', 'Наименование');
    $sheet->getColumnDimension('C')->setWidth(50);

    $lossData = $moyskladModel->getContragentLoss($contragent,$year); // Списания
    $payoutData = $moyskladModel->getContragentPaymentouts($contragent,$year); // Суммы выплат
    $salesData = [];
    // $salesData = self::getRealizeSalesFiledata($file);

    $arrivals = $moyskladModel->getArrivals($contragent,$year);
    $from = new \DateTime($year.'-01-01 00:00:00');
    $to = new \DateTime($year.'-12-31 23:59:59');

    $profits = $moyskladModel->getProfitByPeriod($from,$to,['all'],false);
    $turnovers  = $moyskladModel->getTurnoverByPeriod($from,$to,false);
    if($arrivals){

      $arrivals = json_decode($arrivals)->rows;

      $a                      = 1;
      $arrColumn              = 'D';
      $allArrivalsProducts    = [];
      $arrivalQuantityColumns = [];
      $arrivalPriceColumns    = [];
      $priceCols              = [];

      foreach ($arrivals as $arrival) {
        $arrivalQuantityColumns[$arrival->id] = $arrColumn;
        $products = $moyskladModel->getHrefData($arrival->positions->meta->href . '?expand=assortment');
        $arrival->products = $products->rows;
        $allArrivalsProducts = array_merge($allArrivalsProducts,$products->rows);
        $sheet->setCellValue($arrColumn . '2', 'Приемка ' . $a . ' Штук');
        $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension($arrColumn)->setWidth(11);
        $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);
        $arrivalPriceColumns[$arrival->id] = $arrColumn;
        $sheet->setCellValue($arrColumn . '2', 'Приемка ' . $a . ' Тенге');
        $sheet->getColumnDimension($arrColumn)->setWidth(14);
        $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
        $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);
        $a++;
        if ($a % 30 === 0) { sleep(3); }
      }

      $paymentOutSUm = 0;
      foreach ($payoutData as $poutrow) {
        $paymentOutSUm += ($poutrow->sum / 100);
      }
      $sheet->mergeCells('A1:B1');
      $sheet->setCellValue('A1', 'Сумма выплат контрагенту');
      $sheet->setCellValue('C1', $paymentOutSUm);
      $sheet->getStyle('C1')->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]');

      $sheet->setCellValue($arrColumn . '2', 'Всего принято Штук');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(14);
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Всего принято Тенге');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(14);
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Всего продано Штук');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(15);
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Списания Штук');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(12);
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Остаток Штук');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(10);
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Остаток товара на реализации Тенге');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(15);
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Сумма к оплате Тенге');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(14);
      $sheet->getStyle($arrColumn . '2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ebf1de');
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Цена продажи Тенге');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(14);
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Списания Тенге');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(12);
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $sheet->setCellValue($arrColumn . '2', 'Оплатить поставщику');
      $sheet->getStyle($arrColumn . '2')->getAlignment()->setWrapText(true);
      $sheet->getColumnDimension($arrColumn)->setWidth(14);
      $sheet->getStyle($arrColumn . '2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('ebf1de');
      $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

      $row                  = 4;
      $addedProductCodes    = [];

      $conditionalMinus = new Conditional();
      $conditionalMinus->setConditionType(Conditional::CONDITION_CELLIS);
      $conditionalMinus->setOperatorType(Conditional::OPERATOR_LESSTHAN);
      $conditionalMinus->setStopIfTrue(true);
      $conditionalMinus->addCondition(0);
      $conditionalMinus->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
      $conditionalMinus->getStyle()->getFill()->getStartColor()->setARGB('ffc7ce');
      $conditionalMinus->getStyle()->getFont()->getColor()->setARGB('9c0006');

      foreach ($allArrivalsProducts as $product) {
        // Добавляем новую строку товара
        if(!isset($addedProductCodes[$product->assortment->code])){
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString(end($arrivalPriceColumns)) + 1);
          $addedProductCodes[$product->assortment->code] = $row;

          $sheet->setCellValue('A'.$row, $product->assortment->code);
          if(property_exists($product->assortment,'barcodes') AND !empty($product->assortment->barcodes)){

            $ean = '';
            foreach ($product->assortment->barcodes as $b) {
                if (!empty($b->ean13)) {
                    $ean = $b->ean13;
                    break;
                }
            }

            $sheet->setCellValue('B'.$row, $ean);
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('0');
          }
          if($product->assortment->name){
            $sheet->setCellValue('C'.$row, $product->assortment->name);
          }

          $sheet->setCellValue($arrColumn.$row, '=SUM(' . implode($row.',',$arrivalQuantityColumns) . $row . ')'); // Кол-во всего
          $vsegoPrinyatoQtyCell = $arrColumn.$row;
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

          $terms = [];
          foreach ($arrivalQuantityColumns as $id => $qCol) {
            if (!empty($arrivalPriceColumns[$id])) {
              $pCol     = $arrivalPriceColumns[$id];
              $terms[]  = "({$qCol}{$row}*{$pCol}{$row})";
            }
          }

          $formula = $terms ? '=' . implode('+', $terms) : '=0';
          $sheet->setCellValue($arrColumn . $row, $formula);
          $priceCols[] = $arrColumn;
          $sheet->getStyle($arrColumn.$row)->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]'); // Сумма всего
          $vsegoPrinyatoSumCell = $arrColumn.$row;
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

          $outcome = 0;
          foreach ($profits as $profit) {
            if($profit->assortment->code == $product->assortment->code){
              $outcome = $profit->sellQuantity - $profit->returnQuantity;
            }
          }

          $sheet->setCellValue($arrColumn.$row, $outcome); // Всего продано
          $vsegoProdanoCell = $arrColumn.$row;
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

          // Списания Расчет
          $productLossQty = 0;
          $productLossSum = 0;
          foreach ($lossData as $loss) {
            foreach($loss->positions->rows as $lossproduct){
              if($lossproduct->assortment->code == $product->assortment->code){
                $productLossQty += $lossproduct->quantity;
                $productLossSum += ($lossproduct->price / 100);
              }
            }
          }

          // Списания Штук
          $sheet->setCellValue($arrColumn.$row, $productLossQty);
          $spisaniyaShtukColumn = $arrColumn;
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

          // Остаток Штук
          $sheet->setCellValue($arrColumn.$row, '=' . $vsegoPrinyatoQtyCell . '-' .  $vsegoProdanoCell);
          $sheet->getStyle($arrColumn.$row)->setConditionalStyles([$conditionalMinus]);
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

          // Остаток товара на реализации Тенге
          $sheet->setCellValue($arrColumn.$row, '=' . $vsegoPrinyatoSumCell . '- 0'); ///////////////////
          $priceCols[] = $arrColumn;
          $sheet->getStyle($arrColumn.$row)->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]');
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

          // Сумма к оплате Тенге
          $summPaymentformula = '';
          $aqc = 0;
          $usedQtyExpr = [];
          foreach ($arrivalQuantityColumns as $arrivalQC) {
            $arrivalQCSum = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrivalQC) + 1);
            switch($aqc){
              case 0:
                $summPaymentformula .= 'IF($' . $arrivalQC . $row . ' = 0, 0, MIN($' . $arrivalQC . $row . ',$' . $spisaniyaShtukColumn . $row . ' + $' . $vsegoProdanoCell . ') * $' . $arrivalQCSum . $row . ')';
                break;
              default:
                $expStr = '$' . implode($row . ' - $',$usedQtyExpr) . $row;
                $summPaymentformula .= ' + IF($' . $arrivalQC . $row . ' = 0, 0, MAX(0, MIN($' . $arrivalQC . $row . ', $' . $spisaniyaShtukColumn . $row . ' + $' . $vsegoProdanoCell . ' - ' . $expStr . ')) * $' . $arrivalQCSum . $row . ')';
            }
            $usedQtyExpr[] = $arrivalQC;
            $aqc++;
          }

          $sheet->setCellValue($arrColumn.$row, '=' . $summPaymentformula);
          $totalPaymentSumColumn = $arrColumn;
          $priceCols[] = $arrColumn;
          $sheet->getStyle($arrColumn.$row)->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]');
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

          // Цена продажи
          $pricesFiltered = array_filter(
              $product->assortment->salePrices,
              fn($p) => $p->priceType->id === '02393000-ee91-11ea-0a80-05f200074453'
          );
          $price = reset($pricesFiltered);
          $priceVal = 0;
          if($price){ $priceVal = ($price->value / 100); }
          $sheet->setCellValue($arrColumn.$row, $priceVal);
          $priceCols[] = $arrColumn;
          $sheet->getStyle($arrColumn.$row)->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]');
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);

          // Списания Тенге
          $sheet->setCellValue($arrColumn.$row, $productLossSum);
          $priceCols[] = $arrColumn;
          $sheet->getStyle($arrColumn.$row)->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]');
          $arrColumn = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($arrColumn) + 1);
          $row++;
        }
      }

      $sheet->setCellValue($arrColumn.'3', '=' . $totalPaymentSumColumn . '3 - C1');
      $sheet->getStyle($arrColumn.'3')->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]');
      $priceCols[] = $arrColumn;

      // --- Второй проход: расставляем по приходам без суммирования ---
      foreach ($addedProductCodes as $code => $r) {

        $codeKey = trim((string)$code); // нормализуем для сравнения

        foreach ($arrivals as $arrival) {
            $qtyColLetter   = $arrivalQuantityColumns[$arrival->id];
            $priceColLetter = $arrivalPriceColumns[$arrival->id];

            $qty   = 0;
            $price = 0.0;

            // ищем товар в этом приходе
            foreach (($arrival->products ?? []) as $p) {
                $pcode = isset($p->assortment->code) ? trim((string)$p->assortment->code) : '';
                if ($pcode === $codeKey) {
                    $qty   = (int)($p->quantity ?? 0);
                    $price = (float)(($p->price ?? 0) / 100); // тенге за единицу
                    break;
                }
            }

            // ставим значения в колонки этого прихода
            $qtyCell   = $qtyColLetter   . $r;
            $priceCell = $priceColLetter . $r;

            $sheet->setCellValue($qtyCell, $qty);
            $sheet->setCellValue($priceCell, $price);
            $priceCols[] = $priceColLetter;
            $sheet->getStyle($priceCell)->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]');
        }
      }
      // --- конец второго прохода ---

      $lastDataRow      = $row - 1;
      $firstSumColIndex = Coordinate::columnIndexFromString('D');
      $lastSumColIndex  = Coordinate::columnIndexFromString($arrColumn) - 1;
      for ($colIndex = $firstSumColIndex; $colIndex <= $lastSumColIndex; $colIndex++) {
          $colLetter = Coordinate::stringFromColumnIndex($colIndex);
          // сумма по колонке с 3-й строки до последней
          $sheet->setCellValue($colLetter . '3',"=SUM({$colLetter}4:{$colLetter}{$lastDataRow})");
          $sheet->getStyle($colLetter . '3')->getFont()->setBold(true);
          if(in_array($colLetter,$priceCols)){ $sheet->getStyle($colLetter . '3')->getNumberFormat()->setFormatCode('#,##0.00 [$₸-kk-KZ]'); }
      }

      $sheet->getStyle('A1:' . $arrColumn . '2')->getFont()->setBold(true);
      $sheet->freezePane('D2');

      $writer = new Xlsx($spreadsheet);
      $writer->save(__DIR__ . '/../web/tmpDocs/' . $fileName);
      $xlsData = ob_get_contents();
      ob_end_clean();
      return (object)array('file' => $fileName);
    }
  }
}
