<?php
namespace app\models;

use Yii;
use yii\base\Model;
use app\models\OrdersDemands;

class Moysklad extends Model
{
  public function getMSLoginPassword()
  {
    return (object)array('login' => 'online@2336623', 'password' => 'Gj953928$');
  }

  public function getCustomCategories()
  {
    $accessdata = self::getMSLoginPassword();

    $response   = array();

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/productfolder',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $content  = json_decode( curl_exec($curl) );
    curl_close($curl);

    $excluding =  [
                    '10119948-b295-11ed-0a80-0bd4000cdf58',
                    '2e850b78-b295-11ed-0a80-08f7000c74e1',
                    '380bcff6-b295-11ed-0a80-0cc4000c73b3',
                    '4204ed9b-b295-11ed-0a80-045f000c7c8a',
                    '434bb54d-f400-11ea-0a80-0002000441b1',
                    '5e454570-73b5-11ed-0a80-026700286f67',
                    '830c746b-b295-11ed-0a80-0256000d4135',
                    'b7194743-98d8-11ee-0a80-0e9e0051e70b',
                    'b2d2f781-b2ae-11ed-0a80-0eb20016a4d0',
                    'bd1dc13a-98d8-11ee-0a80-039700524270',
                    'c6e77f74-abc6-11ee-0a80-138b00495471',
                    'c742902b-98d8-11ee-0a80-017900506a3f',
                    'e7386eb7-a0c1-11ee-0a80-0fee0026fa81',
                    'edc6cb68-abc6-11ee-0a80-100e004c4f76',
                    'fb7526e0-9ed2-11ee-0a80-051700027af4',
                    'f9084bdc-f059-11ea-0a80-05f2001b899c',
                    '5b238a3d-9ed3-11ee-0a80-0e1f0002b4ad',
                    '6cf62f6b-abc6-11ee-0a80-100e004c4342',
                    'e11cf86c-9ed2-11ee-0a80-01940002e543',
                    '6d5eb0c4-9ed2-11ee-0a80-10d50002c2fd',
                    'd15cd325-9ed2-11ee-0a80-026e00027a51',
                    '6f1eab0e-ee9c-11ea-0a80-005d0009039b',
                    'e95e2b2d-b345-11ed-0a80-0eb200248c0c',
                    '74518a46-b295-11ed-0a80-045f000c89a1',
                    '942f4207-98d8-11ee-0a80-107d0052548e',
                    'a79bacc7-98d8-11ee-0a80-146900510e66',
                    '9fe95edd-98d8-11ee-0a80-139800510e4f',
                    'aeef9e68-98d8-11ee-0a80-0cca005116e3'
                  ];
    $cats = [];
    foreach ($content->rows as $row) {
      if(in_array($row->id,$excluding)){ continue; }
      $cats[$row->id] = (($row->pathName != '') ? $row->pathName . '/' : '') . $row->name;
    }

    asort($cats);

    // $cats = [
    //           'e0b4cb97-7a81-11ec-0a80-01ce00043cad' => 'Аксессуары/Coffee to Go и Десерты',
    //           '7fecb3b0-f713-11ea-0a80-04cf002f953e' => 'Аксессуары/Посуда Nespresso',
    //           '4b224093-ee9c-11ea-0a80-02120008b5a8' => 'Аксессуары',
    //           '1b7ee889-3b0e-11ec-0a80-064d0035a678' => 'Аксессуары/Держатели Капсул',
    //           '020a6f71-3b0e-11ec-0a80-036f0005feb3' => 'Аксессуары/Капучинаторы',
    //           '8ec6deab-253b-11ed-0a80-0d5c000f4f86' => 'Аксессуары/Кофемолки',
    //           '3a739644-c404-11ed-0a80-0556004249fa' => 'Аксессуары/Посуда Vergnano',
    //           '3f36f6cf-3b0e-11ec-0a80-013400020941' => 'Аксессуары/Средства для очистки',
    //           '765f1d59-a76f-11ec-0a80-0ed700178415' => 'Кофе/Зерновой Кофе',
    //           '9483bf44-4670-11ee-0a80-0dbe001357df' => 'Кофе/Зерновой Кофе/Borbone',
    //           'f8d39fb0-d558-11ee-0a80-06b8005f601a' => 'Кофе/Зерновой Кофе/Bushido',
    //           'a554194d-223f-11ef-0a80-16f8000506d7' => 'Кофе/Зерновой Кофе/Caffe Moreno',
    //           'ffcd7565-d351-11ee-0a80-0129002f7ab4' => 'Кофе/Зерновой Кофе/Carte Noire',
    //           'db3de755-d558-11ee-0a80-0567005ff7b5' => 'Кофе/Зерновой Кофе/Egoiste',
    //           'ed20f1be-1a47-11ed-0a80-0c740011706a' => 'Кофе/Зерновой Кофе/Gimoka',
    //           'f50e40e8-4188-11ee-0a80-005d00068b5e' => 'Кофе/Зерновой Кофе/Illy',
    //           '9da1584e-d352-11ee-0a80-06b8002ea4ac' => 'Кофе/Зерновой Кофе/Kimbo',
    //           'cfd97a20-d6fd-11ee-0a80-15790012ade7' => 'Кофе/Зерновой Кофе/Lavazza',
    //           'c43a7ba3-1a47-11ed-0a80-0c74001166ef' => 'Кофе/Зерновой Кофе/Lollo',
    //           '286d8157-d559-11ee-0a80-0cba0060ac12' => 'Кофе/Зерновой Кофе/Movenpick',
    //           '144bc593-1a48-11ed-0a80-05c000104151' => 'Кофе/Зерновой Кофе/Vergnano',
    //           'a2164a91-4670-11ee-0a80-07c400139287' => 'Кофе/Капсульный кофе/Dolce Gusto/Borbone',
    //           'ae60ea3d-1a48-11ed-0a80-035100127f68' => 'Кофе/Капсульный кофе/Dolce Gusto/Foodness',
    //           '37cc9b6e-a76f-11ec-0a80-068d001d6dbb' => 'Кофе/Капсульный кофе/Dolce Gusto/Gimoka',
    //           '3ef37783-a76f-11ec-0a80-0ed70017756f' => 'Кофе/Капсульный кофе/Dolce Gusto/Kimbo',
    //           'c0967c1a-59df-11ee-0a80-02d90019a33a' => 'Кофе/Капсульный кофе/Dolce Gusto/Lavazza',
    //           '6f46f281-1a47-11ed-0a80-0dc000112a19' => 'Кофе/Капсульный кофе/Dolce Gusto/Lollo',
    //           'dfe2c307-a76d-11ec-0a80-04e300185548' => 'Кофе/Капсульный кофе/Dolce Gusto/Nescafe',
    //           '87b8d37c-1a47-11ed-0a80-0b95001036a8' => 'Кофе/Капсульный кофе/Dolce Gusto/Vergnano',
    //           'c215f4ab-253a-11ed-0a80-0e91000e5649' => 'Кофе/Капсульный кофе/Dolce Gusto/Starbucks',
    //           'ef7e0001-ee9b-11ea-0a80-005d0008f105' => 'Кофе/Капсульный кофе/Nespresso Original',
    //           'af62ebb2-4670-11ee-0a80-03500012ca1a' => 'Кофе/Капсульный кофе/Nespresso Original/Borbone',
    //           '5df2e0bd-a76f-11ec-0a80-0749001844e6' => 'Кофе/Капсульный кофе/Nespresso Original/Gimoka',
    //           '03385aab-37c6-11ec-0a80-0927001cb0d8' => 'Кофе/Капсульный кофе/Nespresso Original/Illy',
    //           '5976eb3b-624f-11ec-0a80-02aa000f72aa' => 'Кофе/Капсульный кофе/Nespresso Original/Jacobs',
    //           'dbf5c425-37c5-11ec-0a80-04f1001d8f15' => 'Кофе/Капсульный кофе/Nespresso Original/Kimbo',
    //           'f056c349-37c5-11ec-0a80-04f1001d9202' => 'Кофе/Капсульный кофе/Nespresso Original/Lavazza',
    //           '38f6b9f9-1a47-11ed-0a80-0dc0001122d6' => 'Кофе/Капсульный кофе/Nespresso Original/Lollo',
    //           'bc6ab979-5fed-11ec-0a80-04f400342b74' => 'Кофе/Капсульный кофе/Nespresso Original/L’OR',
    //           'b96b7998-7db9-11ec-0a80-02d0000820cd' => 'Кофе/Капсульный кофе/Nespresso Original/Movenpick',
    //           '064b675d-a76e-11ec-0a80-01c40018583a' => 'Кофе/Капсульный кофе/Nespresso Original/Nespresso',
    //           '6fcdd383-f584-11ee-0a80-139c00528e07' => 'Кофе/Капсульный кофе/Nespresso Original/RoccaCoffee',
    //           '46561774-253b-11ed-0a80-0b90000eb5aa' => 'Кофе/Капсульный кофе/Nespresso Original/Starbucks',
    //           '54dcfb45-1a47-11ed-0a80-0dc000112701' => 'Кофе/Капсульный кофе/Nespresso Original/Vergnano',
    //           '39d2041d-c24b-11ed-0a80-031200068c1e' => 'Кофе/Капсульный кофе/Lavazza Blue',
    //           'a6c6e296-0def-11eb-0a80-006b00052201' => 'Кофе/Капсульный кофе/Nespresso Professional',
    //           'fd858a8a-a76e-11ec-0a80-04e300186c39' => 'Кофе/Капсульный кофе/Nespresso Professional/Nespresso',
    //           '1e154849-a76f-11ec-0a80-0dca001d5b71' => 'Кофе/Капсульный кофе/Nespresso Professional/Gimoka',
    //           '14e0f867-ee9c-11ea-0a80-032700087aa2' => 'Кофе/Капсульный кофе/Nespresso Vertuo',
    //           'b89e0144-4670-11ee-0a80-08d60012a079' => 'Кофе/Молотый Кофе/Borbone',
    //           '04934eaa-d559-11ee-0a80-0cba0060a46d' => 'Кофе/Молотый Кофе/Bushido',
    //           'e41df00a-d351-11ee-0a80-00ad002f8ce6' => 'Кофе/Молотый Кофе/Carte Noire',
    //           'e7f456ad-d558-11ee-0a80-012900605b51' => 'Кофе/Молотый Кофе/Egoiste',
    //           'e25fa894-1a47-11ed-0a80-0b950010423f' => 'Кофе/Молотый Кофе/Gimoka',
    //           '965a69b2-4189-11ee-0a80-0349000748e6' => 'Кофе/Молотый Кофе/Illy',
    //           'b2483c76-d352-11ee-0a80-0129002f7ca6' => 'Кофе/Молотый Кофе/Kimbo',
    //           'cdd7cf16-1a47-11ed-0a80-05c00010375b' => 'Кофе/Молотый Кофе/Lollo',
    //           '398e5998-d559-11ee-0a80-06b8005f6e61' => 'Кофе/Молотый Кофе/Movenpick',
    //           '1ef15c9e-1a48-11ed-0a80-035100126caf' => 'Кофе/Молотый Кофе/Vergnano',
    //           '19383517-d559-11ee-0a80-02ac005e8ac9' => 'Растворимый кофе/Bushido',
    //           'd07f7dc7-d558-11ee-0a80-0d9e006021b8' => 'Растворимый кофе/Egoiste',
    //           '48f076c3-d559-11ee-0a80-06b8005f71de' => 'Растворимый кофе/Movenpick',
    //           'bf5e2aa6-4670-11ee-0a80-01cf0012df28' => 'Кофе/Чалды/Borbone',
    //           'e5347b48-1974-11ee-0a80-11d0002ebb92' => 'Кофе/Чалды/Gimoka',
    //           '30311436-005d-11ee-0a80-141f00086605' => 'Кофе/Чалды/Illy',
    //           '5b4cdd9c-1971-11ee-0a80-0fdd002cf44d' => 'Кофе/Чалды/Kimbo',
    //           '24da54e9-005d-11ee-0a80-07ad0008f95e' => 'Кофе/Чалды/LolloCaffe',
    //           'c80e567e-4fa8-11ee-0a80-119b00211170' => 'Кофе/Чалды/Vergnano',
    //           'ef5d9842-253a-11ed-0a80-0f43000e9ad8' => 'Кофемашины/Автоматические кофемашины',
    //           '2612e6e2-ee9c-11ea-0a80-013c0008aa48' => 'Кофемашины/Капсульные кофемашины/Nespresso Original',
    //           '0753ee79-6b90-11ec-0a80-05b700c50ffc' => 'Кофемашины/Капсульные кофемашины/Dolce Gusto',
    //           '68b50e11-f200-11ec-0a80-0e710006bfdc' => 'Кофемашины/Капсульные кофемашины/Nespresso Original/Кофемашины',
    //           '3c232293-ee9c-11ea-0a80-005d0008fd52' => 'Кофемашины/Капсульные кофемашины/Nespresso Vertuo',
    //           'ad61c795-0eec-11eb-0a80-04c3001d450f' => 'Кофемашины/Капсульные кофемашины/Nespresso Professional',
    //           'e4be079e-253a-11ed-0a80-068d000e93cb' => 'Кофемашины/Рожковые кофемашины',
    //           'e1841686-f79a-11ec-0a80-07ef000b788d' => 'Кофемашины/Капсульные кофемашины/Nespresso Vertuo/Кофемашины',
    //           'e50de198-2fba-11ee-0a80-0079003b54a7' => 'Чай/Чалды/Borbone',
    //           '786f478c-2533-11ed-0a80-0b90000d4a57' => 'Чай/Dolce Gusto/Foodness',
    //           'c17a6300-2533-11ed-0a80-0767000d993b' => 'Чай/Dolce Gusto/Lollo',
    //           'bbdeeabd-2516-11ed-0a80-0b9000078efa' => 'Чай/Nespresso Original/Lollo',
    //           'dd6a0748-243f-11ed-0a80-0d5600170f54' => 'Чай/Dolce Gusto',
    //           'cbb2ae63-4670-11ee-0a80-01cf0012e242' => 'Чай/Dolce Gusto/Borbone',
    //           'd5f6ecc7-4670-11ee-0a80-006100135505' => 'Чай/Nespresso Original/Borbone',
    //           '21024149-334d-11ed-0a80-0471000ca3b4' => 'Чай/Dolce Gusto/Gimoka',
    //           '29fbe2e5-3348-11ed-0a80-0471000b51ef' => 'Шоколадные напитки/Dolce Gusto/Gimoka',
    //           'e769050a-2534-11ed-0a80-02a7000d77b2' => 'Шоколадные напитки/Dolce Gusto/Lollo',
    //           'ddb4dcf8-4670-11ee-0a80-08d60012a6e3' => 'Шоколадные напитки/Dolce Gusto/Borbone',
    //           '22bc9f01-2518-11ed-0a80-04d1000779aa' => 'Шоколадные напитки/Nespresso Original/Lollo',
    //           '61dfdd46-2620-11ed-0a80-0d5c001d0920' => 'Шоколадные напитки/Dolce Gusto/Nescafe',
    //           'e83390fd-4670-11ee-0a80-0dbe001362a6' => 'Шоколадные напитки/Nespresso Original/Borbone',
    //         ];

    return $cats;
  }

  public function getHrefData($href)
  {
    $accessdata = self::getMSLoginPassword();

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $href,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        // 'Authorization: Bearer ' . $token,
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      )
    ));
    $content = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if(!empty(json_decode($content))){
      return json_decode($content);
    }

    return false;
  }

  public function getProductAttribute($attributes,$attrId)
  {
    foreach ($attributes as $attribute) {
      if($attribute->id == $attrId){
        return $attribute;
      }
    }

    return false;
  }

  public function getTurnoverByPeriod($from,$to,$categories)
  {
    $accessdata = self::getMSLoginPassword();

    $response   = array();
    $c = 1;

    $stores = [];
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/023870f6-ee91-11ea-0a80-05f20007444d';
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/805d5404-3797-11eb-0a80-01b1001ba27a';
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/55441d2d-f295-11ea-0a80-021200465d60';
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/7c5174ab-5200-11eb-0a80-03f90021dcc0';
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/1e1187c1-85e6-11ed-0a80-0dbe006f385b';

    foreach ($stores as $store) {
      $storeStr = '&filter=store=' . $store;
      $momentFrom = $from->format('Y-m-d') . '%2000:00:00';
      $momentTo   = $to->format('Y-m-d') . '%2023:59:59';
      $offset     = 0;
      $url        = "https://api.moysklad.ru/api/remap/1.2/report/turnover/all?offset=" . $offset . "&momentFrom=" . $momentFrom . "&momentTo=" . $momentTo . $storeStr;

      getsales:
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
          'Accept-Encoding: gzip',
          'Connection: Keep-Alive'
        ),
      ));
      $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      $content  = json_decode( curl_exec($curl) );
      curl_close($curl);

      $response = array_merge($response,$content->rows);
      if($content->meta->size == 1000){
        $offset = $offset+1000;
        $c++;
        if ($c % 20 === 0) { sleep(3); }
        goto getsales;
      }


    }

    return $response;
  }

  public function getMoySkladBuyReportProducts($date,$categories)
  {
    $response = [];
    $catSuffix = '';

    switch($categories[0]){
      case 'all':
        break;
      default:
        foreach ($categories as $cat) {
          $catSuffix .= ";productFolder=https://api.moysklad.ru/api/remap/1.2/entity/productfolder/" . $cat;
        }
    }

    $quantityMode = ';quantityMode=all';

    $stores = [];
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/023870f6-ee91-11ea-0a80-05f20007444d';
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/805d5404-3797-11eb-0a80-01b1001ba27a';
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/55441d2d-f295-11ea-0a80-021200465d60';
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/7c5174ab-5200-11eb-0a80-03f90021dcc0';
    $stores[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/1e1187c1-85e6-11ed-0a80-0dbe006f385b';

    $stores = ';store=' . implode(';store=',$stores);

    $accessdata = self::getMSLoginPassword();

    $url = 'https://api.moysklad.ru/api/remap/1.2/report/stock/all?filter=moment=' . $date->format('Y-m-d%20H:i:s') . $catSuffix . $quantityMode . $stores;

    loop:
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
      'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
      'Accept-Encoding: gzip'
    ),
    ));
    $server_output  = curl_exec ($curl);
    $httpcode       = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close ($curl);

    if($server_output){
      $response = array_merge($response,json_decode($server_output)->rows);

      if(count(json_decode($server_output)->rows) == 1000 AND property_exists(json_decode($server_output)->meta,'nextHref') AND !empty(json_decode($server_output)->meta->nextHref)){
        $url = json_decode($server_output)->meta->nextHref;
        goto loop;
      }
      else {
        return $response;
      }
    }

    return false;
  }

  public function getProfitByPeriod($from,$to,$categories,$stores = false)
  {
    $accessdata = self::getMSLoginPassword();

    $response     = [];
    $catSuffixArr = [];
    $catSuffix    = '';

    switch($categories[0]){
      case 'all':
        break;
      default:
        foreach ($categories as $cat) {
          $catSuffixArr[] = "productFolder=https://api.moysklad.ru/api/remap/1.2/entity/productfolder/" . $cat;
        }

        $catSuffix = '&filter=' . implode(';',$catSuffixArr);
    }

    $storesArr = [];
    if($stores){
      switch($stores){
        case 'almaty':
          $storesArr[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/023870f6-ee91-11ea-0a80-05f20007444d';
          $storesArr[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/1e1187c1-85e6-11ed-0a80-0dbe006f385b';
          break;
        case 'astana':
          $storesArr[] = 'https://api.moysklad.ru/api/remap/1.2/entity/store/805d5404-3797-11eb-0a80-01b1001ba27a';
          break;
      }
    }

    $momentFrom = $from->format('Y-m-d') . '%2000:00:00';
    $momentTo   = $to->format('Y-m-d') . '%2023:59:59';
    $offset     = 0;

    if(!empty($storesArr)){
      foreach ($storesArr as $store) {
        $storeStr = '&filter=store=' . $store;
        $url = "https://api.moysklad.ru/api/remap/1.2/report/profit/byproduct?offset=" . $offset . "&momentFrom=" . $momentFrom . "&momentTo=" . $momentTo . $catSuffix . $storeStr;

        getsalesStores:
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
            'Accept-Encoding: gzip',
            'Connection: Keep-Alive'
          ),
        ));
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $content  = json_decode( curl_exec($curl) );
        curl_close($curl);

        $response = array_merge($response,$content->rows);
        if($content->meta->size == 1000){
          $offset = $offset+1000;
          goto getsalesStores;
        }
      }

      return $response;
    }
    else {
      $url = "https://api.moysklad.ru/api/remap/1.2/report/profit/byproduct?offset=" . $offset . "&momentFrom=" . $momentFrom . "&momentTo=" . $momentTo . $catSuffix;

      getsales:
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
          'Accept-Encoding: gzip',
          'Connection: Keep-Alive'
        ),
      ));
      $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      $content  = json_decode( curl_exec($curl) );
      curl_close($curl);

      $response = array_merge($response,$content->rows);
      if($content->meta->size == 1000){
        $offset = $offset+1000;
        goto getsales;
      }

      return $response;
    }
  }

  public function getPeriodsProfitsWeeks($weeks)
  {
    $accessdata = self::getMSLoginPassword();

    foreach ($weeks as $week) {
      $week->profitsList = [];

      loop:
      $url = 'https://api.moysklad.ru/api/remap/1.2/report/profit/byproduct?momentFrom=' . $week->periodFrom . '&momentTo=' . $week->periodTo;
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
          'Accept-Encoding: gzip',
          'Connection: Keep-Alive'
        )
      ));

      $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      $content  = curl_exec($curl);

      if(!empty(json_decode($content))){
        $week->profitsList = array_merge($week->profitsList,json_decode($content)->rows);

        if(count(json_decode($content)->rows) == 1000 AND property_exists(json_decode($content)->meta,'nextHref') AND !empty(json_decode($content)->meta->nextHref)){
          $url = json_decode($content)->meta->nextHref;
          goto loop;
        }
        else {
          continue;
        }
      }
    }

    return $weeks;
  }

  public function getReference($refId)
  {
    $accessdata = self::getMSLoginPassword();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/' . $refId,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    if($response){
      return json_decode($response);
    }

    return false;
  }

  public function getStates($meta)
  {
    $accessdata = self::getMSLoginPassword();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/' . $meta . '/metadata',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    if($response){
      return json_decode($response);
    }

    return false;
  }

  public function buildStateMeta(string $entity, string $stateId): array
  {
      return [
          'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/{$entity}/metadata/states/{$stateId}",
          'type'      => 'state',
          'mediaType' => 'application/json',
      ];
  }

  private function buildAttributeMeta(string $entity, string $attrId): array
  {
      return [
          'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/{$entity}/metadata/attributes/{$attrId}",
          'type'      => 'attributemetadata',
          'mediaType' => 'application/json',
      ];
  }

  public function getOrganizations()
  {
    $accessdata = self::getMSLoginPassword();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    if($response){
      return json_decode($response);
    }

    return false;
  }

  public function getOrganizationAccounts($org)
  {
    $accessdata = self::getMSLoginPassword();

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . $org . '/accounts/',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    if($response){
      return json_decode($response);
    }

    return false;
  }

  public function getActualProjects()
  {
    return [
      // 'Kaspi - Времянка' => '698bbf4d-7346-11eb-0a80-083400146e88',
      // 'Kasta - Времянка' => '7b12e831-0817-11f0-0a80-165a0010ce66',
      // 'ItalFood - Времянка' => 'd4986e14-0931-11ef-0a80-0bd6000d967f',

      '🟢 Halyk Market' => '842c5548-c90c-11f0-0a80-1aee002c13e9',
      '🔴 Kaspi Accio' => '5f351348-d269-11f0-0a80-15120016d622',
      '🔴 Tutto Capsule Kaspi' => '431a8172-d26a-11f0-0a80-0f110016cabd',
      '🔴 Ital Trade' => '98777142-d26a-11f0-0a80-1be40016550a',
      '🔵 Wolt' => 'a463b9da-d26c-11f0-0a80-1a6b0016a57a',
      '🟣 Forte Market' => 'a4481c66-d274-11f0-0a80-0f110017905c',
      // '📍 Accio' => '341ee0eb-d269-11f0-0a80-0cf20015f0d3',
      '💎 Юридическое лицо' => '6b625db1-d270-11f0-0a80-1512001756b3',
      '🔥 Store' => '8fe86883-d275-11f0-0a80-15120017c4b6',
      '♥️ Accio Store' => 'c4bd7d52-d276-11f0-0a80-17910017cc0c'
    ];
  }

  public function getProjectByCode($code)
  {
    switch($code){
      case 'accio':
        return '698bbf4d-7346-11eb-0a80-083400146e88';
        break;
      case 'ItalFood':
        return '';
        break;
      case 'kasta':
        return '';
        break;
    }
  }

  public function getProjects($arr = false)
  {
    $accessdata = self::getMSLoginPassword();

    $filterStr = '';
    if($arr && !empty($arr)){
      $filter = array();
      foreach ($arr as $a) {
        $filter[] = 'filter=id=' . $a;
      }

      $filterStr = '?' . implode('&',$filter);
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/project/' . $filterStr,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    if($response){
      return json_decode($response);
    }

    return false;
  }

  public function getProductsRemains()
  {
    $accessdata = self::getMSLoginPassword();

    $url = "https://api.moysklad.ru/api/remap/1.2/report/stock/bystore/current?stockType=stock";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                          'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
                                          'Connection: Keep-Alive',
                                          'Accept-Encoding: gzip'
                                          ));
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_ENCODING,'');
    curl_setopt($ch, CURLOPT_MAXREDIRS,10);
    curl_setopt($ch, CURLOPT_TIMEOUT,0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content = curl_exec($ch);

    if(!empty(json_decode($content))){
      return $content;
    }

    return false;
  }

  public function getMoySkladCities()
  {
    $accessdata = self::getMSLoginPassword();

    $url = "https://api.moysklad.ru/api/remap/1.2/entity/customentity/08491328-345c-11eb-0a80-03ad0002ec7a";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                          'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
                                          'Connection: Keep-Alive',
                                          'Accept-Encoding: gzip'
                                          ));
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content = curl_exec($ch);

    if(!empty(json_decode($content))){
      return json_decode($content);
    }

    return false;
  }

  public function getReceivedComissionerReport()
  {
    $accessdata = self::getMSLoginPassword();

    $response = [];
    $limit = 100;

    $url = "https://api.moysklad.ru/api/remap/1.2/entity/commissionreportin";

    // $url = "https://api.moysklad.ru/api/remap/1.2/entity/demand?filter=state=https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/states/aa7acdbc-a7c9-11ed-0a80-0c71001732ca&filter=state=https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/states/732ffbde-0a19-11eb-0a80-055600083d2e&filter=state=https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/states/24d4a11f-8af4-11eb-0a80-0122002915d0&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/023870f6-ee91-11ea-0a80-05f20007444d&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/805d5404-3797-11eb-0a80-01b1001ba27a&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/1e1187c1-85e6-11ed-0a80-0dbe006f385b&filter=moment%3E=" . $from->format('Y-m-d%20H:i:s') . "&filter=moment%3C=" . $to->format('Y-m-d%20H:i:s') . "&expand=positions.assortment&limit=" . $limit;

    loop:
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      )
    ));

    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $content  = curl_exec($curl);

    if(!empty(json_decode($content))){
      $response = array_merge($response,json_decode($content)->rows);

      if(count(json_decode($content)->rows) == 100 AND property_exists(json_decode($content)->meta,'nextHref') AND !empty(json_decode($content)->meta->nextHref)){
        $url = json_decode($content)->meta->nextHref;
        goto loop;
      }
      else {
        return $response;
      }
    }

    return false;

  }

  public function getPeriodsDemands($from,$to,$conditional = false)
  {
    $accessdata = self::getMSLoginPassword();

    $response = [];
    $limit = 1000;
    $l = 1;

    if($conditional){
      $agentFilters = [];
      foreach ($conditional->agents as $agent) {
        if(!isset($agentFilters[$agent->agentId])){
          $agentFilters[$agent->agentId] = 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $agent->agentId;
        }
      }

      foreach ($conditional->states as $state) {
        if(!isset($statesFilters[$state])){
          $statesFilters[$state] = 'https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/states/' . $state;
        }
      }

      $periodData = '';
      if($conditional->includeperiods){
        $periodData = '&filter=moment%3E=' . $from->format('Y-m-d%20H:i:s') . '&filter=moment%3C=' . $to->format('Y-m-d%2023:59:59');
      }

      $url = "https://api.moysklad.ru/api/remap/1.2/entity/demand?filter=state=" . implode('&filter=state=',$statesFilters) . "&filter=agent=" . implode('&filter=agent=',$agentFilters) . $periodData . "&expand=positions.assortment&limit=" . $limit;
    }
    else {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/demand?filter=state=https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/states/aa7acdbc-a7c9-11ed-0a80-0c71001732ca&filter=state=https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/states/732ffbde-0a19-11eb-0a80-055600083d2e&filter=state=https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/states/24d4a11f-8af4-11eb-0a80-0122002915d0&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/023870f6-ee91-11ea-0a80-05f20007444d&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/805d5404-3797-11eb-0a80-01b1001ba27a&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/1e1187c1-85e6-11ed-0a80-0dbe006f385b&filter=moment%3E=" . $from->format('Y-m-d%20H:i:s') . "&filter=moment%3C=" . $to->format('Y-m-d%20H:i:s') . "&expand=positions.assortment&limit=" . $limit;
    }

    loop:
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      )
    ));

    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $content  = curl_exec($curl);

    if(!empty(json_decode($content))){
      $response = array_merge($response,json_decode($content)->rows);
      if(count(json_decode($content)->rows) == 100 AND property_exists(json_decode($content)->meta,'nextHref') AND !empty(json_decode($content)->meta->nextHref)){
        $url = json_decode($content)->meta->nextHref;
        $l++;
        if ($l % 30 === 0) { sleep(3); }
        goto loop;
      }
      else {
        return $response;
      }
    }

    return false;
  }

  private function getPaymentByMarketplaceOrderId($orderId)
  {
    $accessdata = self::getMSLoginPassword();

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/paymentin?filter=https://api.moysklad.ru/api/remap/1.2/entity/paymentin/metadata/attributes/886cd568-ea7f-11ed-0a80-10a80071443d=' . $orderId,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        // 'Authorization: Bearer ' . $token,
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      )
    ));
    $content = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if(!empty(json_decode($content))){
      return json_decode($content);
    }

    return false;
  }

  private function updatePayment($paymentId,$postPayment,$postDate,$state)
  {
    $accessdata = self::getMSLoginPassword();

    $data = array(
        "moment" => $postDate->format('Y-m-d H:i:s'),
        "applicable" => $postPayment,
        "incomingDate" => $postDate->format('Y-m-d H:i:s')
    );

    $paymentState = (object)array();
    $paymentState->meta = (object)array();
    $paymentState->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/paymentin/metadata/states/' . $state;
    $paymentState->meta->type = 'state';
    $paymentState->meta->mediaType = 'application/json';
    $data['state'] = $paymentState;

    $ch = curl_init('https://api.moysklad.ru/api/remap/1.2/entity/paymentin/' . $paymentId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive',
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (curl_errno($ch)) {
      file_put_contents(__DIR__ . '/../paymentUpdateError.txt','Ошибка запроса: ' . curl_error($ch) . PHP_EOL . print_r($data,true) . PHP_EOL . PHP_EOL, FILE_APPEND);
      return false;
    }

    return true;
  }

  private function createPayment($type,$organizationId,$accountId,$contragentId,$paymentTypeId,$issueOut = false, $issueOutNew = false,$paymentSum,$postDate)
  {
    $accessdata = self::getMSLoginPassword();

    $data = [
      "moment" => $postDate->format('Y-m-d H:i:s'),
      "created" => $postDate->format('Y-m-d H:i:s'),
      "applicable" => true,
      "sum" => $paymentSum
    ];
    $data['attributes'] = [];

    $paymentState = (object)array();
    $paymentState->meta = (object)array();
    $paymentState->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/paymentout/metadata/states/1ab07e7d-346a-11eb-0a80-04cd00042316'; // Статус - Оплачен
    $paymentState->meta->type = 'state';
    $paymentState->meta->mediaType = 'application/json';
    $data['state'] = $paymentState;

    $organization = (object)array();
    $organization->meta = (object)array();
    $organization->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . $organizationId;
    $organization->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata';
    $organization->meta->type = 'organization';
    $organization->meta->mediaType = 'application/json';
    $data['organization'] = $organization;

    $account = (object)array();
    $account->meta = (object)array();
    $account->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . $organizationId . '/accounts/' . $accountId;
    $account->meta->type = 'account';
    $account->meta->mediaType = 'application/json';
    $data['organizationAccount'] = $account;

    $agent = (object)array();
    $agent->meta = (object)array();
    $agent->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $contragentId;
    $agent->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata';
    $agent->meta->type = 'counterparty';
    $agent->meta->mediaType = 'application/json';
    $data['agent'] = $agent;

    $expenseItem = (object)array();
    $expenseItem->meta = (object)array();
    $expenseItem->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/expenseitem/' . $issueOut;
    // $expenseItem->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/entity/expenseitem/metadata';
    $expenseItem->meta->type = 'expenseitem';
    $expenseItem->meta->mediaType = 'application/json';
    $data['expenseItem'] = $expenseItem;

    $expenseItemNew = (object)array();
    $expenseItemNew->meta = (object)array();
    $expenseItemNew->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/paymentout/metadata/attributes/0a881597-d3e4-11ef-0a80-03bb0005cf5b';
    $expenseItemNew->meta->type = 'attributemetadata';
    $expenseItemNew->meta->mediaType = 'application/json';
    $expenseItemNew->value = (object)array();
    $expenseItemNew->value->meta = (object)array();
    $expenseItemNew->value->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/18db383a-d3e0-11ef-0a80-181f00052878/' . $issueOutNew;
    $expenseItemNew->value->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/18db383a-d3e0-11ef-0a80-181f00052878';
    $expenseItemNew->value->meta->type = 'customentity';
    $expenseItemNew->value->meta->mediaType = 'application/json';
    $data['attributes'][] = $expenseItemNew;

    $paymentType = (object)array();
    $paymentType->meta = (object)array();
    $paymentType->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/paymentout/metadata/attributes/e7b6111b-d3e5-11ef-0a80-03bb0006387b';
    $paymentType->meta->type = 'attributemetadata';
    $paymentType->meta->mediaType = 'application/json';
    $paymentType->value = (object)array();
    $paymentType->value->meta = (object)array();
    $paymentType->value->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/a614ce40-d3e5-11ef-0a80-06860006697b/00954746-d3e6-11ef-0a80-03bb00064723';
    $paymentType->value->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/a614ce40-d3e5-11ef-0a80-06860006697b';
    $paymentType->value->meta->type = 'customentity';
    $paymentType->value->meta->mediaType = 'application/json';
    $data['attributes'][] = $paymentType;

    $ch = curl_init('https://api.moysklad.ru/api/remap/1.2/entity/paymentout');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive',
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (curl_errno($ch)) {
      file_put_contents(__DIR__ . '/../paymentUpdateError.txt','Ошибка запроса: ' . curl_error($ch) . PHP_EOL . print_r($data,true) . PHP_EOL . PHP_EOL, FILE_APPEND);
      return false;
    }

    return true;
  }

  public function postPaymentInInformation($bank,$orderId,$paymentDate)
  {
    $payments = self::getPaymentByMarketplaceOrderId($orderId);
    $paymentDate = new \DateTime($paymentDate);
    $paymentDate->modify('+3 hours');

    if($payments){
      foreach ($payments->rows as $row) {
        self::updatePayment($row->id,true,$paymentDate,'5299823c-346a-11eb-0a80-04cd00042954'); // 5299823c-346a-11eb-0a80-04cd00042954 - статус Поступил
        break;
      }
    }

    return true;
  }

  public function postPaymentOutInformation($type,$bank,$organization,$paymentSum,$paymentDate)
  {
    switch($organization){
      case 'spectorg':
        $organizationId = '1e0488ad-0a26-11ec-0a80-05760004991d';
        $accountId      = '1e048dcd-0a26-11ec-0a80-05760004991e';
        break;
      case 'accio_retail_store':
        $organizationId = '640cb82e-82af-11ed-0a80-07fe00255908';
        $accountId      = '6956b8ab-82b0-11ed-0a80-06c70025a969';
        break;
      case 'ital_foods':
        $organizationId = '3bd63649-f257-11ea-0a80-005d003d9ee4';
        $accountId      = '3bd63c9b-f257-11ea-0a80-005d003d9ee5';
        break;
    }

    switch($type){
      case 'op_comission':
        $contragent     = '4c58fcbd-7f22-11eb-0a80-031e001dd4e9'; // Kaspi Магазин
        $issueOut       = 'b2162b97-82b8-11ed-0a80-04de0027a838'; // https://api.moysklad.ru/api/remap/1.2/entity/expenseitem/
        $issueOutNew    = '6c7a353f-d3e2-11ef-0a80-11f10005f097'; // Поле - 0a881597-d3e4-11ef-0a80-03bb0005cf5b, справочник - https://api.moysklad.ru/api/remap/1.2/entity/customentity/18db383a-d3e0-11ef-0a80-181f00052878
        $paymentType    = '00954746-d3e6-11ef-0a80-03bb00064723'; // Поле - e7b6111b-d3e5-11ef-0a80-03bb0006387b, справочник - https://api.moysklad.ru/api/remap/1.2/entity/customentity/a614ce40-d3e5-11ef-0a80-06860006697b
        break;
      case 'kaspi_pay_fee':
        $contragent     = '7be350d9-7737-11eb-0a80-03ee0002dda0'; // Kaspi Банк
        $issueOut       = 'b216082c-82b8-11ed-0a80-04de0027a837'; // https://api.moysklad.ru/api/remap/1.2/entity/expenseitem/
        $issueOutNew    = '5c32d01e-d3e2-11ef-0a80-0e850005af39'; // Поле - 0a881597-d3e4-11ef-0a80-03bb0005cf5b, справочник - https://api.moysklad.ru/api/remap/1.2/entity/customentity/18db383a-d3e0-11ef-0a80-181f00052878
        $paymentType    = '00954746-d3e6-11ef-0a80-03bb00064723'; // Поле - e7b6111b-d3e5-11ef-0a80-03bb0006387b, справочник - https://api.moysklad.ru/api/remap/1.2/entity/customentity/a614ce40-d3e5-11ef-0a80-06860006697b
        break;
      case 'kaspi_delivery_fee':
        $contragent     = '4c58fcbd-7f22-11eb-0a80-031e001dd4e9'; // Kaspi Магазин
        $issueOut       = '1606f944-f059-11ea-0a80-0650001c289b'; // https://api.moysklad.ru/api/remap/1.2/entity/expenseitem/
        $issueOutNew    = 'b85c6cbe-d3e0-11ef-0a80-0d1d00054f2c'; // Поле - 0a881597-d3e4-11ef-0a80-03bb0005cf5b, справочник - https://api.moysklad.ru/api/remap/1.2/entity/customentity/18db383a-d3e0-11ef-0a80-181f00052878
        $paymentType    = '00954746-d3e6-11ef-0a80-03bb00064723'; // Поле - e7b6111b-d3e5-11ef-0a80-03bb0006387b, справочник - https://api.moysklad.ru/api/remap/1.2/entity/customentity/a614ce40-d3e5-11ef-0a80-06860006697b
        break;
    }

    self::createPayment('out',$organizationId,$accountId,$contragent,$paymentType,$issueOut,$issueOutNew,$paymentSum,$paymentDate);

    return true;
  }

  public function getContragents($onlyrealize)
  {
    $accessdata = self::getMSLoginPassword();

    $ch = curl_init();

    $addon = '';
    if($onlyrealize){
      $addon = '?filter=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata/attributes/5c31a26c-b98c-11f0-0a80-17370030c144=true';
    }

    // Настройки запроса
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty' . $addon,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
            'Accept-Encoding: gzip',
        ],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (curl_errno($ch)) {
      file_put_contents(__DIR__ . '/../getConteragentsUpdateError.txt','Ошибка запроса: ' . curl_error($ch) . PHP_EOL . print_r($data,true) . PHP_EOL . PHP_EOL, FILE_APPEND);
      return false;
    }

    return $response;
  }

  public function searchContragentByPhone($phone)
  {
    $phone = str_replace(array('+7','-','(',')',' '),'',$phone);
    $accessdata = self::getMSLoginPassword();

    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty?search=' . $phone;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                          'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
                                          'Connection: Keep-Alive',
                                          'Accept-Encoding: gzip'
                                          ));
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content = curl_exec($ch);

    if(!empty(json_decode($content)->rows)){
      return json_decode($content)->rows[0];
    }
    return false;
  }

  public function createContragent($name,$phone,$address)
  {
    $accessdata = self::getMSLoginPassword();

    $data = (object)array();
    $data->name = $name;
    $data->email = '';
    $data->phone = $phone;
    $data->actualAddress  = $address;
    $data->attributes     = array();

    $data->companyType = 'individual';

    $attributeLegalAddress = (object)array();
    $attributeLegalAddress->meta = (object)array();
    $attributeLegalAddress->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata/attributes/71e9a1e1-00f1-11ed-0a80-0d790029c1ce';
    $attributeLegalAddress->meta->type = 'attributemetadata';
    $attributeLegalAddress->meta->mediaType = 'application/json';
    $attributeLegalAddress->value = true;
    array_push($data->attributes,$attributeLegalAddress);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"https://api.moysklad.ru/api/remap/1.2/entity/counterparty");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data));
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [
      'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
      'Content-Type: application/json',
      'Accept-Encoding: gzip'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $server_output = json_decode(curl_exec ($ch));
    curl_close ($ch);
    return $server_output;
  }

  public function getArrivals($contragent,$year)
  {
    $accessdata = self::getMSLoginPassword();

    $filters = [
            'agent=' . 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $contragent,
            'moment>=' . $year . '-01-01 00:00:00',
            'moment<=' . $year . '-12-31 23:59:59',
        ];

    $query = implode('&', array_map(fn($v) => 'filter=' . rawurlencode($v), $filters));

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/supply?' . $query,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));


    $response = curl_exec($curl);
    curl_close($curl);

    if (curl_errno($curl)) {
      file_put_contents(__DIR__ . '/../getArrivalsUpdateError.txt','Ошибка запроса: ' . curl_error($ch) . PHP_EOL . print_r($data,true) . PHP_EOL . PHP_EOL, FILE_APPEND);
      return false;
    }

    return $response;
  }

  public function getContragentLoss($contragent,$year)
  {
    $accessdata = self::getMSLoginPassword();
    $response = [];

    $curl = curl_init();

    $offset = 0;

    $filters = [
            'moment>=' . $year . '-01-01 00:00:00',
            'moment<=' . $year . '-12-31 23:59:59',
        ];

    $contragentStr = '&filter=https://api.moysklad.ru/api/remap/1.2/entity/loss/metadata/attributes/f53c3e32-c48c-11f0-0a80-1ab20013c501=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $contragent;

    $query = implode('&', array_map(fn($v) => 'filter=' . rawurlencode($v), $filters));

    next:
    $query = $query . $contragentStr . '&offset=' . $offset . '&limit=100&expand=positions.assortment';

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/loss?' . $query,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));

    $content = curl_exec($curl);
    $content = json_decode($content);

    curl_close($curl);

    $response = array_merge($response,$content->rows);
    if($content->meta->size == 100){
      $offset = $offset+100;
      $c++;
      if ($c % 20 === 0) { sleep(3); }
      goto next;
    }

    return $response;
  }

  public function getContragentPaymentouts($contragent,$year)
  {
    $accessdata = self::getMSLoginPassword();
    $response = [];

    $curl = curl_init();

    $offset = 0;

    $filters = [
            'moment>=' . $year . '-01-01 00:00:00',
            'moment<=' . $year . '-12-31 23:59:59',
        ];

    $contragentStr = '&filter=agent=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $contragent;

    $query = implode('&', array_map(fn($v) => 'filter=' . rawurlencode($v), $filters));
    $query = $query . $contragentStr . '&offset=' . $offset;

    next:
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/paymentout?' . $query,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive'
      ),
    ));

    $content = curl_exec($curl);
    $content = json_decode($content);

    curl_close($curl);

    $response = array_merge($response,$content->rows);
    if($content->meta->size == 1000){
      $offset = $offset+1000;
      $c++;
      if ($c % 20 === 0) { sleep(3); }
      goto next;
    }

    return $response;
  }

  public function updateOrderWithConfig($orderId,$config)
  {
    $accessdata = self::getMSLoginPassword();

    $data = [];
    $data['attributes'] = [];

    foreach ($config as $ckey => $c) {
      if (is_string($c) && $c === 'byhand') {
        continue;
      }

      if ($c === false || $c === null) {
        continue;
      }

      switch($ckey){
        case 'id':
        case 'project':
        case 'cash_register':
        case 'action_type':
          continue 2;
          break;

        // Атрибутивные поля
        case 'payment_type': // Тип платежа
          $fieldId    = '19fb8dcf-94ac-11ed-0a80-0e930023e914';
          $refId      = 'd8662995-836c-11ed-0a80-04de0034157c';
          $valueMetaHrefEl = 'customentity';
          $valueMetaMetaDataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/'.$refId;
          $fieldType  = 'attributemetadata';
          break;
        case 'fiscal': // фискальный чек
          $fieldId    = '4e4537e9-a0a2-11ed-0a80-1043003e432d';
          $refId = 'b6fc53ef-a4e7-11eb-0a80-0dc70016db30';
          $valueMetaHrefEl = 'customentity';
          $valueMetaMetaDataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/'.$refId;
          $fieldType = 'attributemetadata';
          break;
        case 'channel': // Канал связи
          $fieldId    = '45bdad04-68d6-11ee-0a80-095d000776da';
          $refId = '9c69b3d5-68d5-11ee-0a80-044c0009477e';
          $valueMetaHrefEl = 'customentity';
          $valueMetaMetaDataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/'.$refId;
          $fieldType = 'attributemetadata';
          break;
        case 'payment_status': // Статус оплаты
          $fieldId    = 'f27758fb-b05d-11ed-0a80-09ae002b500b';
          $refId = '1bbc6b51-c29d-11eb-0a80-01370004133f';
          $valueMetaHrefEl = 'customentity';
          $valueMetaMetaDataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/'.$refId;
          $fieldType = 'attributemetadata';
          break;
        case 'delivery_service': // Служба доставки
          $fieldId    = '8a307d43-3b6a-11ee-0a80-06ae000fd467';
          $refId = 'd220a555-345d-11eb-0a80-022e0002f1c7';
          $valueMetaHrefEl = 'customentity';
          $valueMetaMetaDataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/'.$refId;
          $fieldType = 'attributemetadata';
          break;
        case 'project_field': // Поле "Выбрать проект"
          $fieldId = 'dd839a8b-47a1-11ed-0a80-01fb00205e82';
          $refId = '';
          $valueMetaHrefEl = 'project';
          $valueMetaMetaDataHref = 'https://api.moysklad.ru/api/remap/1.2/entity/project/metadata';
          $fieldType = 'attributemetadata';
          break;

        // Нативные поля
        case 'status': // Статус
          $fieldId = 'customerorder/metadata/states';
          $fieldType = 'state';
          break;
        case 'organization': // Организация
          $fieldId = 'organization';
          $fieldType = 'organization';
          break;
        case 'legal_account': // Счет юридического лица
          $fieldId = 'organization/'.$config['organization'];
          $fieldType = 'account';
          break;
      }

      $obj                  = (object)array();
      $obj->meta            = (object)array();
      $obj->meta->mediaType = 'application/json';
      $obj->meta->type      = $fieldType;

      if($fieldType == 'attributemetadata'){
        $obj->meta->href                  = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/'.$fieldId;
        $obj->value                       = (object)array();
        $obj->value->meta                 = (object)array();
        $obj->value->meta->href           = 'https://api.moysklad.ru/api/remap/1.2/entity/'.$valueMetaHrefEl.'/' . (!empty($refId)?$refId.'/':'') .$c;
        $obj->value->meta->metadataHref   = $valueMetaMetaDataHref;
        $obj->value->meta->type           = $valueMetaHrefEl;
        $obj->value->meta->mediaType      = 'application/json';
        $data['attributes'][] = $obj;
      }
      else {
        if($fieldType == 'account'){
          $obj->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/'.$fieldId.'/accounts/'.$c;
          $data['organizationAccount'] = $obj;
        }
        else {
          $obj->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/'.$fieldId.'/'.$c;
          $data[$fieldType] = $obj;
        }
      }
    }

    $ch = curl_init('https://api.moysklad.ru/api/remap/1.2/entity/customerorder/' . $orderId . '?expand=agent,project,organization,store,state,positions,attributes');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
        'Accept-Encoding: gzip',
        'Connection: Keep-Alive',
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);

    curl_close($ch);
    if (curl_errno($ch)) {
      file_put_contents(__DIR__ . '/../logs/ms_service/createcustomerorder.txt','Ошибка запроса на обновление заказа: ' . curl_error($ch) . PHP_EOL . print_r($data,true) . PHP_EOL, FILE_APPEND);
      return false;
    }

    return json_decode($response);
  }

  public function mapAttributesDemandOrderFields()
  {
    return array(
      '1b0e12b6-3471-11eb-0a80-096400054956' => '263aa028-2ba1-11ed-0a80-056b000879a8', // Город
      'b2b883e2-3464-11eb-0a80-00f10003703a' => '8a307d43-3b6a-11ee-0a80-06ae000fd467', // Служба доставки
      'b4b6c6d6-836d-11ed-0a80-07fe00347b40' => '19fb8dcf-94ac-11ed-0a80-0e930023e914', // Способ оплаты
      'b7665340-75d0-11eb-0a80-0259003872fe' => '17545020-4d14-11ed-0a80-0ef600207483', // Дата доставки
      '452d785a-75d1-11eb-0a80-05af00386c25' => 'f313d67e-94ac-11ed-0a80-0e930023fd09', // Время доставки
      '892e27a6-99f5-11eb-0a80-0451000e89e5' => 'dd839a8b-47a1-11ed-0a80-01fb00205e82', // Проект
      'eb46b957-a4e7-11eb-0a80-014c00169cca' => '4e4537e9-a0a2-11ed-0a80-1043003e432d', // Фискальный чек
      'db30d9e9-a4e2-11eb-0a80-09b900160bbe' => 'a7f0812d-a0a3-11ed-0a80-114f003fc7f9', // Номер заказа маркетплейса
      '64869eb0-c29d-11eb-0a80-08be00040769' => 'f27758fb-b05d-11ed-0a80-09ae002b500b', // Статус оплаты
      'b15cb6d6-295c-11ef-0a80-005b0036e745' => '45bdad04-68d6-11ee-0a80-095d000776da', // Каналы связи
      '1b0e12b6-3471-11eb-0a80-096400054956' => '263aa028-2ba1-11ed-0a80-056b000879a8' // Город
    );
  }

  public function buildDemandPayloadFromOrder(object $msorder, $config, array $options = []): array
  {
      // -------------------------
      // 1. Позиции
      // -------------------------
      $rows = $msorder->positions->rows ?? [];
      $positions = [];

      foreach ($rows as $p) {
          if (empty($p->assortment->meta->href)) {
              continue;
          }

          $positions[] = [
              'quantity' => (float)($p->quantity ?? 0),
              'price' => (float)($p->price ?? 0),
              'discount' => (float)($p->discount ?? 0),
              'assortment' => [
                  'meta' => [
                      'href'      => $p->assortment->meta->href,
                      'type'      => $p->assortment->meta->type ?? 'product',
                      'mediaType' => 'application/json',
                  ]
              ]
          ];
      }

      if (!$positions) {
          throw new \RuntimeException('Не удалось собрать позиции для отгрузки');
      }

      // -------------------------
      // 2. Базовый payload
      // -------------------------
      $payload = [
          'organization' => [
              'meta' => [
                  'href'      => $msorder->organization->meta->href,
                  'type'      => 'organization',
                  'mediaType' => 'application/json',
              ]
          ],
          'agent' => [
              'meta' => [
                  'href'      => $msorder->agent->meta->href,
                  'type'      => 'counterparty',
                  'mediaType' => 'application/json',
              ]
          ],
          'customerOrder' => [
              'meta' => [
                  'href'      => $msorder->meta->href,
                  'type'      => 'customerorder',
                  'mediaType' => 'application/json',
              ]
          ],
          'positions' => $positions,
      ];

      if(property_exists($msorder,'store')){
        $payload['store'] = [
                              'meta' => [
                                'href'      => $msorder->store->meta->href,
                                'type'      => 'store',
                                'mediaType' => 'application/json',
                              ]
                            ];

      }

      // project (если есть)
      if (property_exists($msorder,'project') AND !empty($msorder->project->meta->href)) {
          $payload['project'] = [
              'meta' => [
                  'href'      => $msorder->project->meta->href,
                  'type'      => 'project',
                  'mediaType' => 'application/json',
              ]
          ];
      }

      // -------------------------
      // 3. shipmentAddressFull
      // -------------------------
      if (!empty($msorder->shipmentAddress)) {
          $payload['shipmentAddress'] = $msorder->shipmentAddress;
      }

      // -------------------------
      // 4. Кастомные поля (demand ← order)
      // -------------------------
      $map = $this->mapAttributesDemandOrderFields(); // demandAttrId => orderAttrId

      if ($map && !empty($msorder->attributes)) {
          // индексируем атрибуты заказа по ID
          $orderAttrs = [];
          foreach ($msorder->attributes as $attr) {
              if (empty($attr->meta->href)) {
                  continue;
              }
              $orderAttrs[basename($attr->meta->href)] = $attr;
          }

          $attrs = [];
          foreach ($map as $demandAttrId => $orderAttrId) {
              if (empty($orderAttrs[$orderAttrId])) {
                  continue;
              }

              $src = $orderAttrs[$orderAttrId];

              // если value отсутствует — пропускаем
              if (!property_exists($src, 'value')) {
                  continue;
              }

              $attrs[] = [
                  'meta' => [
                      'href'      => 'https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/attributes/' . $demandAttrId,
                      'type'      => 'attributemetadata',
                      'mediaType' => 'application/json',
                  ],
                  'value' => $src->value,
              ];
          }

          if ($attrs) {
              $payload['attributes'] = $attrs;
          }
      }


      // -------------------------
      // 5. Опции
      // -------------------------
      if (!empty($options['name'])) {
          $payload['name'] = (string)$options['name'];
      }

      if (!empty($options['moment'])) {
          $payload['moment'] = (string)$options['moment']; // YYYY-MM-DD HH:MM:SS
      }

      if (array_key_exists('applicable', $options)) {
          $payload['applicable'] = (bool)$options['applicable'];
      }


      return $payload;
  }

  private function normalizeDemandPositions(array $rows): array
  {
      $out = [];

      foreach ($rows as $row) {
          $meta = $row->assortment->meta ?? null;
          if (!$meta) continue;

          $out[] = [
              'href'  => (string)$meta->href,
              'type'  => (string)$meta->type,
              'qty'   => number_format((float)($row->quantity ?? 0), 3, '.', ''),
              'price' => isset($row->price) ? (int)$row->price : 0,
          ];
      }

      usort($out, static fn($a, $b) =>
          strcmp($a['href'] . '|' . $a['type'], $b['href'] . '|' . $b['type'])
      );

      return $out;
  }

  private function positionsEqual(array $a, array $b): bool
  {
      return sha1(json_encode($this->normalizeDemandPositions($a))) ===
             sha1(json_encode($this->normalizeDemandPositions($b)));
  }

  public function upsertDemandFromOrder($msorder, int $localOrderId, $config, array $options = [])
  {
      $accessdata = self::getMSLoginPassword();

      $configFiltered = $config;
      foreach ($configFiltered as $k => $v) {
        if (is_string($v) && $v === 'byhand') {
          unset($configFiltered[$k]);
        }
      }

      $payload = $this->buildDemandPayloadFromOrder($msorder, $config, $options);

      // 1) проверяем, есть ли demand уже
      $link = OrdersDemands::findOne(['moysklad_order_id' => (string)$msorder->id]);

      if ($link && !empty($link->moysklad_demand_id)) {
          // UPDATE demand

          // Проверка на теекущие позиции
          $currentDemand = $this->getHrefData( 'https://api.moysklad.ru/api/remap/1.2/entity/demand/' . $demandId . '?expand=positions' );
          $orderRows  = $msorder->positions->rows ?? [];
          $demandRows = $currentDemand->positions->rows ?? [];

          if (
              !empty($payload['positions']) &&
              $this->positionsEqual($orderRows, $demandRows)
          ) {
              // ❌ позиции совпадают — УБИРАЕМ их из payload
              unset($payload['positions']);

              file_put_contents(
                  __DIR__ . '/../logs/ms_service/updatedemand.txt',
                  "DEMAND {$demandId}: positions unchanged, skip sync\n",
                  FILE_APPEND
              );
          }

          $demandId = $link->moysklad_demand_id;

          $url = 'https://api.moysklad.ru/api/remap/1.2/entity/demand/' . $demandId;

          $ch = curl_init($url);
          curl_setopt_array($ch, [
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_CUSTOMREQUEST  => 'PUT',
              CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
              CURLOPT_ENCODING       => 'gzip',
              CURLOPT_HTTPHEADER     => [
                  'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
                  'Accept-Encoding: gzip',
                  'Content-Type: application/json',
              ],
          ]);

          $response = curl_exec($ch);

          $errNo = curl_errno($ch);
          $err   = $errNo ? curl_error($ch) : null;
          $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          curl_close($ch);

          if ($errNo || $code < 200 || $code >= 300) {
              file_put_contents(__DIR__ . '/../logs/ms_service/createcustomerorder.txt',
                  "DEMAND PUT error. HTTP={$code} ERR={$err}\nResp={$response}\nPayload=" . print_r($payload,true) . "\n\n",
                  FILE_APPEND
              );
              return false;
          }

          // обновим updated_at
          $link->updated_at = date('Y-m-d H:i:s');

          $respObj = json_decode($response);
          $stateHref = $respObj->state->meta->href ?? null;
          $link->moysklad_state_id = $stateHref ? basename($stateHref) : $link->moysklad_state_id;

          $link->save(false);

          return $respObj;
      }

      // 2) CREATE demand
      $url = 'https://api.moysklad.ru/api/remap/1.2/entity/demand';

      $stateToDemand = Yii::$app->params['moysklad']['demandUpdateHandler']['stateToDemand'];
      $payload['state'] = [
          'meta' => $this->buildStateMeta('demand', $stateToDemand)
      ];

      if ($stateToDemand !== '') {
          $payload['state'] = [
              'meta' => [
                  'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/states/{$stateToDemand}",
                  'type'      => 'state',
                  'mediaType' => 'application/json',
              ]
          ];
      }

      $ch = curl_init($url);
      curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST  => 'POST',
          CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
          CURLOPT_ENCODING       => 'gzip',
          CURLOPT_HTTPHEADER     => [
              'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
              'Accept-Encoding: gzip',
              'Content-Type: application/json',
          ],
      ]);

      $response = curl_exec($ch);
      $errNo = curl_errno($ch);
      $err   = $errNo ? curl_error($ch) : null;
      $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($errNo || $code < 200 || $code >= 300) {
          file_put_contents(__DIR__ . '/../logs/ms_service/createcustomerorder.txt',
              "DEMAND POST error. HTTP={$code} ERR={$err}\nResp={$response}\nPayload=" . print_r($payload,true) . "\n\n",
              FILE_APPEND
          );
          return false;
      }

      $created = json_decode($response);
      $demandId = $created->id ?? null;

      if (!$demandId) {
          file_put_contents(__DIR__ . '/../logs/ms_service/createcustomerorder.txt',
              "DEMAND POST ok, but no id in response\nResp={$response}\n\n",
              FILE_APPEND
          );
          return false;
      }

      // 3) сохраняем связь в БД
      if(!$link){
        $link = new OrdersDemands();
      }
      $link->order_id           = $localOrderId;
      $link->moysklad_order_id  = (string)$msorder->id;
      $link->moysklad_demand_id = (string)$demandId;
      $link->created_at         = date('Y-m-d H:i:s');
      $link->updated_at         = date('Y-m-d H:i:s');

      // ✅ текущий статус отгрузки
      $stateHref = $created->state->meta->href ?? null;
      $link->moysklad_state_id = $stateHref ? basename($stateHref) : null;

      $link->save(false);

      return $created;
  }

  public function checkOrderInMoySkladByMarketplaceCode($orderCode)
  {
    $accessdata = self::getMSLoginPassword();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"https://api.moysklad.ru/api/remap/1.2/entity/customerorder?filter=https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/a7f0812d-a0a3-11ed-0a80-114f003fc7f9=" . $orderCode);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                          'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
                                          'Connection: Keep-Alive',
                                          'Accept-Encoding: gzip'
                                          ));
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content = curl_exec($ch);

    if(!empty(json_decode($content)->rows)){
      return json_decode($content)->rows;
    }
    return false;
  }

  public function setFileToDemand($demandId,$fileUrl)
  {
    $accessdata = self::getMSLoginPassword();

    $ext      = pathinfo($fileUrl, PATHINFO_EXTENSION);
    if(!$ext OR empty($ext)){
      $ext = 'pdf';
    }
    $fileData = file_get_contents($fileUrl);
    $base64   = base64_encode($fileData);

    $data = array();
    $obj  = (object)array();
    $obj->filename = 'Накладная_' . date('Y-m-d_His') . '.' . $ext;
    $obj->content = $base64;
    $data[] = $obj;
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.moysklad.ru/api/remap/1.2/entity/demand/' . $demandId . '/files',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_HTTPHEADER => array(
        'Connection: Keep-Alive',
        'Accept-Encoding: gzip',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
      ),
    ));


    $response = curl_exec($curl);

    curl_close($curl);
  }

  public function markWaybillDelivery($demandId)
  {
    $accessdata = self::getMSLoginPassword();

    $data = (object)array();
    $data->attributes     = array();

    $attributeMarkWaybill = (object)array();
    $attributeMarkWaybill->meta = (object)array();
    $attributeMarkWaybill->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/attributes/60dbcc74-a9e6-11ed-0a80-111e001b5386';
    $attributeMarkWaybill->meta->type = 'attributemetadata';
    $attributeMarkWaybill->meta->mediaType = 'application/json';
    $attributeMarkWaybill->value = true;
    array_push($data->attributes,$attributeMarkWaybill);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"https://api.moysklad.ru/api/remap/1.2/entity/demand/" . $demandId);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);

    $headers = [
      'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
      'Content-Type: application/json',
      'Accept-Encoding: gzip'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $server_output = json_decode(curl_exec ($ch));
    curl_close ($ch);
  }

  public function productRemainsCheckByArray($sku,$quantity,$remains,$productId)
  {
    $response = (object)array();
    $response->almaty   = 0;
    $response->astana   = 0;
    $response->success  = 0;

    foreach ($remains as $key => $remain) {
      if($remain->assortmentId == $productId){
        switch($remain->storeId){
          case '023870f6-ee91-11ea-0a80-05f20007444d':
            $response->almaty += $remain->stock;
            break;
          case '1e1187c1-85e6-11ed-0a80-0dbe006f385b':
            $response->success += $remain->stock;
            break;
          case '805d5404-3797-11eb-0a80-01b1001ba27a':
            $response->astana += $remain->stock;
            break;
        }
      }
    }

    return $response;
  }

  public function getDeliveryTime($order)
  {
    $deliveryDateTimeMillisec = $order->attributes->plannedDeliveryDate;
    $seconds = $deliveryDateTimeMillisec / 1000;
    $deliveryDateTime = date("Y-m-d H:i:s", strtotime('@' . $seconds));
    $deliveryDateTime = new \DateTime($deliveryDateTime);

    $moySkladTiming = [
                        (object)array(
                          'timeFrom' => '10:00:00',
                          'timeTo' => '11:59:59',
                          'msid' => '04d3f3b5-75d1-11eb-0a80-04560038dfa7',

                        ),
                        (object)array(
                          'timeFrom' => '12:00:00',
                          'timeTo' => '14:59:59',
                          'msid' => '7bbf4d76-4d16-11ed-0a80-04e000206c67',

                        ),
                        (object)array(
                          'timeFrom' => '15:00:00',
                          'timeTo' => '17:59:59',
                          'msid' => '1e17e9ea-75d1-11eb-0a80-07ad003ab04e',
                        ),
                        (object)array(
                          'timeFrom' => '18:00:00',
                          'timeTo' => '20:59:59',
                          'msid' => '28fa59b6-75d1-11eb-0a80-06210039e247',
                        ),
                        (object)array(
                          'timeFrom' => '21:00:00',
                          'timeTo' => '23:59:59',
                          // 'timeTo' => '09:59:59',
                          'msid' => '579af8f2-458b-11ee-0a80-005d005b0931',
                        ),
                        (object)array(
                          'timeFrom' => '00:00:00',
                          'timeTo' => '09:59:59',
                          'msid' => '579af8f2-458b-11ee-0a80-005d005b0931',
                        )
                      ];
    foreach ($moySkladTiming as $mst) {
      $mstFromMillisec = strtotime($deliveryDateTime->format('Y-m-d') . ' ' . $mst->timeFrom) * 1000;
      $mstToMillisec = strtotime($deliveryDateTime->format('Y-m-d') . ' ' . $mst->timeTo) * 1000;

      if($deliveryDateTimeMillisec >= $mstFromMillisec AND $deliveryDateTimeMillisec <= $mstToMillisec){
        return $mst->msid;
      }
    }

    return false;
  }

  public function getCityId($kaspiCity,$moySkladCities)
  {
    foreach ($moySkladCities->rows as $msCity) {
      if(mb_strtolower($msCity->name) == mb_strtolower($kaspiCity)){
        return $msCity->id;
      }
    }

    return 'ce22d9f6-4941-11ed-0a80-00bd000e47e9';
  }

  public function createOrder($order,$area,$shopkey)
  {
    $data = (object)array();
    $data->name = $order->orderId . '_' . $area . '_' . $shopkey;

    if($order->comment):
      $data->description = $order->comment;
    endif;

    $data->project = (object)array();
    $data->project->meta = (object)array();
    $data->project->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/project/' . $order->project;
    $data->project->meta->type = 'project';
    $data->project->meta->mediaType = 'application/json';

    $data->organization = (object)array();
    $data->organization->meta = (object)array();
    $data->organization->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . $order->organization;
    $data->organization->meta->type = 'organization';
    $data->organization->meta->mediaType = 'application/json';

    $data->store = (object)array();
    $data->store->meta = (object)array();
    $data->store->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/store/' . $order->warehouse;
    $data->store->meta->type = 'store';
    $data->store->meta->mediaType = 'application/json';

    $data->agent = (object)array();
    $data->agent->meta = (object)array();
    $data->agent->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $order->contragent;
    $data->agent->meta->type = 'counterparty';
    $data->agent->meta->mediaType = 'application/json';

    $data->state = (object)array();
    $data->state->meta = (object)array();
    $data->state->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/states/' . $order->orderStatus; // EDITTING !!!
    $data->state->meta->type = 'state';
    $data->state->meta->mediaType = 'application/json';

    $data->positions = array();
    foreach($order->products AS $product){
      $pr = (object)array();
      $pr->quantity                     = (int)$product->quantity;
      $pr->price                        = (float)$product->price * 100;
      $pr->assortment                   = (object)array();
      $pr->assortment->meta             = (object)array();
      $pr->assortment->meta->href       = 'https://api.moysklad.ru/api/remap/1.2/entity/' . $product->type . '/' . $product->pid;
      $pr->assortment->meta->type       = $product->type;
      $pr->assortment->meta->mediaType  = 'application/json';
      array_push($data->positions,$pr);
    }

    $data->attributes = array();

    $attributePayment = (object)array();
    $attributePayment->meta = (object)array();
    $attributePayment->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/19fb8dcf-94ac-11ed-0a80-0e930023e914';
    $attributePayment->meta->type = 'attributemetadata';
    $attributePayment->meta->mediaType = 'application/json';
    $attributePayment->value = (object)array();
    $attributePayment->value->meta = (object)array();
    $attributePayment->value->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/d8662995-836c-11ed-0a80-04de0034157c/' . $order->paymentType;
    $attributePayment->value->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/d8662995-836c-11ed-0a80-04de0034157c';
    $attributePayment->value->meta->type = 'customentity';
    $attributePayment->value->meta->mediaType = 'application/json';
    array_push($data->attributes,$attributePayment);

    // Marketplace code
    $attributeMarketplaceCode = (object)array();
    $attributeMarketplaceCode->meta = (object)array();
    $attributeMarketplaceCode->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/a7f0812d-a0a3-11ed-0a80-114f003fc7f9';
    $attributeMarketplaceCode->meta->type = 'attributemetadata';
    $attributeMarketplaceCode->meta->mediaType = 'application/json';
    $attributeMarketplaceCode->value = $order->orderId;
    array_push($data->attributes,$attributeMarketplaceCode);

    // Kaspi delivery cost
    $attributeKaspiDeliveryCost = (object)array();
    $attributeKaspiDeliveryCost->meta = (object)array();
    $attributeKaspiDeliveryCost->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/f12cde65-be93-11ee-0a80-0bae0039f7d9';
    $attributeKaspiDeliveryCost->meta->type = 'attributemetadata';
    $attributeKaspiDeliveryCost->meta->mediaType = 'application/json';
    $attributeKaspiDeliveryCost->value = $order->deliveryCost;
    array_push($data->attributes,$attributeKaspiDeliveryCost);

    if($area == 'kaspi'){
      // External ID of Kaspi order
      $attributeExternalIdKaspi = (object)array();
      $attributeExternalIdKaspi->meta = (object)array();
      $attributeExternalIdKaspi->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/11dc767c-52d6-11ee-0a80-0f3d00080bcb';
      $attributeExternalIdKaspi->meta->type = 'attributemetadata';
      $attributeExternalIdKaspi->meta->mediaType = 'application/json';
      $attributeExternalIdKaspi->value = $order->orderExtId;
      array_push($data->attributes,$attributeExternalIdKaspi);
    }

    if($order->deliveryDate):
      $orderDeliveryDate = $order->deliveryDate . ' 14:59:59';
      $orderDeliveryTime = $order->deliveryTime;
    else:
      $orderDeliveryDate = date('Y-m-d') . ' 15:59:59';
      $orderDeliveryTime = '1e17e9ea-75d1-11eb-0a80-07ad003ab04e';
    endif;

    $attributeDeliveryDate = (object)array();
    $attributeDeliveryDate->meta = (object)array();
    $attributeDeliveryDate->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/17545020-4d14-11ed-0a80-0ef600207483';
    $attributeDeliveryDate->meta->type = 'attributemetadata';
    $attributeDeliveryDate->meta->mediaType = 'application/json';
    $attributeDeliveryDate->value = $orderDeliveryDate;
    array_push($data->attributes,$attributeDeliveryDate);

    $attributeDeliveryTime = (object)array();
    $attributeDeliveryTime->meta = (object)array();
    $attributeDeliveryTime->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/f313d67e-94ac-11ed-0a80-0e930023fd09';
    $attributeDeliveryTime->meta->type = 'attributemetadata';
    $attributeDeliveryTime->meta->mediaType = 'application/json';
    $attributeDeliveryTime->value = (object)array();
    $attributeDeliveryTime->value->meta = (object)array();
    $attributeDeliveryTime->value->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/d67a2f24-75d0-11eb-0a80-083400396032/' . $orderDeliveryTime;
    $attributeDeliveryTime->value->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/d67a2f24-75d0-11eb-0a80-083400396032';
    $attributeDeliveryTime->value->meta->type = 'customentity';
    $attributeDeliveryTime->value->meta->mediaType = 'application/json';
    array_push($data->attributes,$attributeDeliveryTime);

    $attributeDeliveryCity = (object)array();
    $attributeDeliveryCity->meta = (object)array();
    $attributeDeliveryCity->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/263aa028-2ba1-11ed-0a80-056b000879a8';
    $attributeDeliveryCity->meta->type = 'attributemetadata';
    $attributeDeliveryCity->meta->mediaType = 'application/json';
    $attributeDeliveryCity->value = (object)array();
    $attributeDeliveryCity->value->meta = (object)array();
    $attributeDeliveryCity->value->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/08491328-345c-11eb-0a80-03ad0002ec7a/' . $order->city;
    $attributeDeliveryCity->value->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/08491328-345c-11eb-0a80-03ad0002ec7a';
    $attributeDeliveryCity->value->meta->type = 'customentity';
    $attributeDeliveryCity->value->meta->mediaType = 'application/json';
    array_push($data->attributes,$attributeDeliveryCity);

    $attributeFiscalWaybill = (object)array();
    $attributeFiscalWaybill->meta = (object)array();
    $attributeFiscalWaybill->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/4e4537e9-a0a2-11ed-0a80-1043003e432d';
    $attributeFiscalWaybill->meta->type = 'attributemetadata';
    $attributeFiscalWaybill->meta->mediaType = 'application/json';
    $attributeFiscalWaybill->value = (object)array();
    $attributeFiscalWaybill->value->meta = (object)array();
    $attributeFiscalWaybill->value->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/b6fc53ef-a4e7-11eb-0a80-0dc70016db30/c3c0ee4f-a4e7-11eb-0a80-075b00176e05';
    $attributeFiscalWaybill->value->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/b6fc53ef-a4e7-11eb-0a80-0dc70016db30';
    $attributeFiscalWaybill->value->meta->type = 'customentity';
    $attributeFiscalWaybill->value->meta->mediaType = 'application/json';
    array_push($data->attributes,$attributeFiscalWaybill);

    $data->shipmentAddressFull = (object)array();
    $data->shipmentAddressFull->city = $order->cityStr;
    $data->shipmentAddressFull->street = $order->address;

    $attributeDeliveryService = (object)array();
    $attributeDeliveryService->meta = (object)array();
    $attributeDeliveryService->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/8a307d43-3b6a-11ee-0a80-06ae000fd467';
    $attributeDeliveryService->meta->type = 'attributemetadata';
    $attributeDeliveryService->meta->mediaType = 'application/json';
    $attributeDeliveryService->value = (object)array();
    $attributeDeliveryService->value->meta = (object)array();
    $attributeDeliveryService->value->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/d220a555-345d-11eb-0a80-022e0002f1c7/' . $order->deliveryType;
    $attributeDeliveryService->value->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/d220a555-345d-11eb-0a80-022e0002f1c7';
    $attributeDeliveryService->value->meta->type = 'customentity';
    $attributeDeliveryService->value->meta->mediaType = 'application/json';
    array_push($data->attributes,$attributeDeliveryService);

    $attributeProject = (object)array();
    $attributeProject->meta = (object)array();
    $attributeProject->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes/dd839a8b-47a1-11ed-0a80-01fb00205e82';
    $attributeProject->meta->type = 'attributemetadata';
    $attributeProject->meta->mediaType = 'application/json';
    $attributeProject->value = (object)array();
    $attributeProject->value->meta = (object)array();
    $attributeProject->value->meta->href = 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/project/' . $order->project;
    $attributeProject->value->meta->metadataHref = 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/project';
    $attributeProject->value->meta->type = 'project';
    $attributeProject->value->meta->mediaType = 'application/json';
    array_push($data->attributes,$attributeProject);

    $accessdata = self::getMSLoginPassword();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"https://api.moysklad.ru/api/remap/1.2/entity/customerorder");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);

    $headers = [
      'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
      'Content-Type: application/json',
      'Accept-Encoding: gzip'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $server_output = json_decode(curl_exec ($ch));
    curl_close ($ch);

    return $server_output;
  }

  private function requestJson(string $method, string $url, array $data = null)
  {
      $accessdata = self::getMSLoginPassword();

      $ch = curl_init($url);

      $opts = [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST  => strtoupper($method),
          CURLOPT_ENCODING       => 'gzip',
          CURLOPT_HTTPHEADER     => [
              'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
              'Accept-Encoding: gzip',
              'Connection: Keep-Alive',
          ],
      ];

      if ($data !== null) {
          $opts[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
      }

      curl_setopt_array($ch, $opts);

      $response = curl_exec($ch);
      $errNo    = curl_errno($ch);
      $err      = $errNo ? curl_error($ch) : null;
      $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      curl_close($ch);

      return [
          'ok'   => (!$errNo && $code >= 200 && $code < 300),
          'code' => $code,
          'err'  => $err,
          'raw'  => $response,
          'json' => $response ? json_decode($response) : null,
      ];
  }

  public function updateDemandState(string $demandId, array $stateMeta)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/demand/{$demandId}";

      $payload = [
          'state' => [
              'meta' => $stateMeta
          ]
      ];

      return $this->requestJson('PUT', $url, $payload);
  }

  public function updateOrderState(string $orderId, array $stateMeta)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$orderId}";

      $payload = [
          'state' => [
              'meta' => $stateMeta
          ]
      ];

      return $this->requestJson('PUT', $url, $payload);
  }

  public function updateOrderPositionsFromDemand(string $msOrderId, object $demand)
  {
      $accessdata = self::getMSLoginPassword();

      $rows = $demand->positions->rows ?? [];
      $positions = [];

      foreach ($rows as $row) {
          $meta = $row->assortment->meta ?? null;
          if (!$meta || empty($meta->href) || empty($meta->type)) {
              continue;
          }

          $pos = [
              'assortment' => [
                  'meta' => [
                      'href'      => $meta->href,
                      'type'      => $meta->type,
                      'mediaType' => $meta->mediaType ?? 'application/json',
                  ],
              ],
              'quantity' => (float)($row->quantity ?? 0),
          ];

          // В МС price — в копейках
          if (isset($row->price)) {
              $pos['price'] = (int)$row->price;
          }

          // опционально, если есть
          if (isset($row->vat)) {
              $pos['vat'] = $row->vat;
          }
          if (isset($row->discount)) {
              $pos['discount'] = $row->discount;
          }

          $positions[] = $pos;
      }

      $payload = ['positions' => $positions];

      $url = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/' . $msOrderId;

      $ch = curl_init($url);
      curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST  => 'PUT',
          CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          CURLOPT_ENCODING       => 'gzip',
          CURLOPT_HTTPHEADER     => [
              'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
              'Accept-Encoding: gzip',
              'Content-Type: application/json',
          ],
      ]);

      $resp = curl_exec($ch);
      $errNo = curl_errno($ch);
      $err   = $errNo ? curl_error($ch) : null;
      $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($errNo || $code < 200 || $code >= 300) {
          return ['ok' => false, 'code' => $code, 'raw' => $resp, 'err' => $err];
      }

      return ['ok' => true, 'code' => $code, 'data' => json_decode($resp)];
  }

  public function createInvoiceOutFromOrder(object $msOrder, $config = null)
  {
      $accessdata = self::getMSLoginPassword();

      // payload
      $payload = $this->buildInvoiceOutPayloadFromOrder($msOrder, $config);

      $url = 'https://api.moysklad.ru/api/remap/1.2/entity/invoiceout?expand=project,state,positions,paymentType,attributes';

      $ch = curl_init($url);
      curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST  => 'POST',
          CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
          CURLOPT_ENCODING       => 'gzip',
          CURLOPT_HTTPHEADER     => [
              'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
              'Accept-Encoding: gzip',
              'Content-Type: application/json',
          ],
      ]);

      $response = curl_exec($ch);
      $errNo = curl_errno($ch);
      $err   = $errNo ? curl_error($ch) : null;
      $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($errNo || $code < 200 || $code >= 300) {
          file_put_contents(__DIR__ . '/../logs/ms_service/createcustomerorder.txt',
              "INVOICEOUT POST error. HTTP={$code} ERR={$err}\nResp={$response}\nPayload=" . print_r($payload,true) . "\n\n",
              FILE_APPEND
          );
          return false;
      }

      return json_decode($response);
  }

  private function buildInvoiceOutPayloadFromOrder(object $msOrder, $config = null): array
  {
      // Агент и организация обычно обязательны для invoiceout
      $agentMeta = $msOrder->agent->meta ?? null;
      $orgMeta   = $msOrder->organization->meta ?? null;

      // Позиции invoiceout — можно просто скопировать из заказа
      $rows = [];
      foreach (($msOrder->positions->rows ?? []) as $p) {
          $ass = $p->assortment->meta ?? null;
          if (!$ass) continue;

          $rows[] = [
              'assortment' => ['meta' => $ass],
              'quantity'   => (float)($p->quantity ?? 0),
              'price'      => (int)($p->price ?? 0),
              // если нужно:
              // 'vat' => $p->vat ?? 0,
              // 'discount' => $p->discount ?? 0,
          ];
      }

      $payload = [
          'agent'        => ['meta' => $agentMeta],
          'organization' => ['meta' => $orgMeta],
          'customerOrder'=> ['meta' => $msOrder->meta], // связь со счетом через заказ
          'positions'    => ['rows' => $rows],
      ];

      // Если у тебя в конфиге есть статусы/счёт/какие-то реквизиты для invoiceout — добавь тут
      // Например, если хочешь проставить "Счет выставлен" как state в invoiceout (если есть отдельные статусы invoiceout):
      // if (!empty($config->invoiceout_state)) { $payload['state'] = $this->buildStateMeta('invoiceout', $config->invoiceout_state); }

      return $payload;
  }

  // public function hasInvoiceOutForOrder(string $msOrderId): bool
  // {
  //     $accessdata = self::getMSLoginPassword();
  //
  //     // фильтр по customerOrder
  //     $orderHref = "https://api.moysklad.ru/api/remap/1.2/entity/customerorder/{$msOrderId}";
  //
  //     // в filter спецсимволы, поэтому urlencode
  //     $url = 'https://api.moysklad.ru/api/remap/1.2/entity/invoiceout'
  //         . '?limit=1'
  //         . '&filter=' . rawurlencode('customerOrder=' . $orderHref);
  //
  //     $ch = curl_init($url);
  //     curl_setopt_array($ch, [
  //         CURLOPT_RETURNTRANSFER => true,
  //         CURLOPT_CUSTOMREQUEST  => 'GET',
  //         CURLOPT_ENCODING       => 'gzip',
  //         CURLOPT_HTTPHEADER     => [
  //             'Authorization: Basic ' . base64_encode($accessdata->login . ':' . $accessdata->password),
  //             'Accept-Encoding: gzip',
  //         ],
  //         CURLOPT_TIMEOUT        => (int)(Yii::$app->params['moysklad']['httpTimeout'] ?? 20),
  //     ]);
  //
  //     $response = curl_exec($ch);
  //     $errNo = curl_errno($ch);
  //     $err   = $errNo ? curl_error($ch) : null;
  //     $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  //     curl_close($ch);
  //
  //     if ($errNo || $code < 200 || $code >= 300) {
  //         file_put_contents(__DIR__ . '/../logs/ms_service/createcustomerorder.txt',
  //             "INVOICEOUT CHECK error. HTTP={$code} ERR={$err}\nResp={$response}\nURL={$url}\n\n",
  //             FILE_APPEND
  //         );
  //
  //         // если чек не удался — безопаснее НЕ создавать дубликат
  //         return true;
  //     }
  //
  //     $obj = json_decode($response);
  //     $rows = $obj->rows ?? [];
  //
  //     return !empty($rows);
  // }

  public function updateInvoiceOutState(string $invoiceOutId, array $stateMeta)
  {
      $access = self::getMSLoginPassword();
      $url = 'https://api.moysklad.ru/api/remap/1.2/entity/invoiceout/' . $invoiceOutId;

      $payload = ['state' => $stateMeta];

      $ch = curl_init($url);
      curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST  => 'PUT',
          CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
          CURLOPT_ENCODING       => 'gzip',
          CURLOPT_HTTPHEADER     => [
              'Authorization: Basic ' . base64_encode($access->login . ':' . $access->password),
              'Accept-Encoding: gzip',
              'Content-Type: application/json',
          ],
      ]);

      $resp = curl_exec($ch);
      $errNo = curl_errno($ch);
      $err   = $errNo ? curl_error($ch) : null;
      $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($errNo || $code < 200 || $code >= 300) {
          return ['ok' => false, 'code' => $code, 'raw' => $resp, 'err' => $err];
      }

      return ['ok' => true, 'data' => json_decode($resp)];
  }

  public function updatePaymentInApplicable(string $id, bool $applicable)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/paymentin/{$id}";
      return $this->requestJson('PUT', $url, ['applicable' => $applicable]);
  }

  public function updateCashInApplicable(string $id, bool $applicable)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/cashin/{$id}";
      return $this->requestJson('PUT', $url, ['applicable' => $applicable]);
  }

  public function updatePaymentInState(string $id, array $stateMeta)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/paymentin/{$id}";
      return $this->requestJson('PUT', $url, ['state' => ['meta' => $stateMeta]]);
  }

  public function updateCashInState(string $id, array $stateMeta)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/cashin/{$id}";
      return $this->requestJson('PUT', $url, ['state' => ['meta' => $stateMeta]]);
  }

  public function createPaymentInFromOrder(
      object $order,
      object $demand,
      string $orderNum,
      $paymentTypeMeta,
      string $incomeIssueAttrId,
      string $incomeIssueValueId
  ) {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/paymentin";

      $payload = [
          'organization' => ['meta' => $order->organization->meta],
          'agent'        => ['meta' => $order->agent->meta],
          'sum'          => (int)($demand->sum ?? 0),
          'operations'   => [
              [
                  'meta'      => $demand->meta,
                  'linkedSum' => (int)($demand->sum ?? 0),
              ]
          ],
          'customerOrder'=> ['meta' => $order->meta],
          'applicable'   => false,
      ];

      $attributes = [];

      if ($orderNum !== '' && $orderNum !== '-') {
          $attrId = YII::$app->params['moysklad']['incomeIssues']['orderNumIssueAttrId'];
          $attributes[] = [
              'meta'  => $this->buildAttributeMeta('paymentin', $attrId),
              'id'    => $attrId,
              'value' => $orderNum,
          ];
      }

      if ($paymentTypeMeta && isset($paymentTypeMeta->meta)) {
          $attrId = YII::$app->params['moysklad']['incomeIssues']['paymentTypeIssueAttrId'];
          $attributes[] = [
              'meta'  => $this->buildAttributeMeta('paymentin', $attrId),
              'id'    => $attrId,
              'value' => [
                  'meta' => $paymentTypeMeta->meta,
              ],
          ];
      }

      if ($incomeIssueAttrId !== '' && $incomeIssueValueId !== '') {
          $dictId = YII::$app->params['moysklad']['incomeIssues']['incomeDictId'];
          $attributes[] = [
              'meta'  => $this->buildAttributeMeta('paymentin', $incomeIssueAttrId),
              'id'    => $incomeIssueAttrId,
              'value' => [
                  'meta' => [
                      'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/customentity/{$dictId}/{$incomeIssueValueId}",
                      'type'      => 'customentity',
                      'mediaType' => 'application/json',
                  ],
              ],
          ];
      }

      if (!empty($attributes)) {
          $payload['attributes'] = $attributes;
      }

      file_put_contents(__DIR__ . '/../logs/ms_service/updatedemand.txt', print_r($payload,true). PHP_EOL, FILE_APPEND);

      return $this->requestJson('POST', $url, $payload);
  }

  // public function createPaymentInFromOrder(object $order, object $demand)
  // {
  //     $url = "https://api.moysklad.ru/api/remap/1.2/entity/paymentin";
  //
  //     $payload = [
  //         'organization' => ['meta' => $order->organization->meta],
  //         'agent'        => ['meta' => $order->agent->meta],
  //         'sum'          => (int)($demand->sum ?? 0),
  //         'operations'   => [
  //           [
  //             'meta'      => $demand->meta,         // <-- Привязка к отгрузке
  //             'linkedSum' => (int)($demand->sum ?? 0)
  //           ]
  //         ],
  //         'customerOrder'=> ['meta' => $order->meta],
  //         'applicable'   => false,
  //     ];
  //
  //     return $this->requestJson('POST', $url, $payload);
  // }

  public function createCashInFromOrder(
      object $order,
      object $demand,
      string $orderNum,
      $paymentTypeMeta,
      string $incomeIssueAttrId,
      string $incomeIssueValueId
  ) {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/cashin";

      $payload = [
          'organization' => ['meta' => $order->organization->meta],
          'agent'        => ['meta' => $order->agent->meta],
          'sum'          => (int)($order->sum ?? 0),
          'operations'   => [
              [
                  'meta'      => $demand->meta,
                  'linkedSum' => (int)($demand->sum ?? 0),
              ]
          ],
          'customerOrder'=> ['meta' => $order->meta],
          'applicable'   => false,
      ];

      $attributes = [];

      /** Способ оплаты (справочник) */
      if ($paymentTypeMeta && isset($paymentTypeMeta->meta)) {
          $attrId = YII::$app->params['moysklad']['incomeIssues']['paymentTypeIssueCashAttrId'];

          $attributes[] = [
              'meta'  => $this->buildAttributeMeta('cashin', $attrId),
              'id'    => $attrId,
              'value' => [
                  'meta' => $paymentTypeMeta->meta,
              ],
          ];
      }

      /** Статья доходов */
      if ($incomeIssueAttrId !== '' && $incomeIssueValueId !== '') {
          $dictId = YII::$app->params['moysklad']['incomeIssues']['incomeDictId'];
          $attributes[] = [
              'meta'  => $this->buildAttributeMeta('cashin', $incomeIssueAttrId),
              'id'    => $incomeIssueAttrId,
              'value' => [
                  'meta' => [
                      'href'      => "https://api.moysklad.ru/api/remap/1.2/entity/customentity/{$dictId}/{$incomeIssueValueId}",
                      'type'      => 'customentity',
                      'mediaType' => 'application/json',
                  ],
              ],
          ];
      }

      if (!empty($attributes)) {
          $payload['attributes'] = $attributes;
      }

      return $this->requestJson('POST', $url, $payload);
  }

  public function createCashInFromOrder(object $order, object $demand)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/cashin";

      $payload = [
          'organization' => ['meta' => $order->organization->meta],
          'agent'        => ['meta' => $order->agent->meta],
          'sum'          => (int)($order->sum ?? 0),
          'operations'   => [
            [
              'meta'      => $demand->meta,         // <-- Привязка к отгрузке
              'linkedSum' => (int)($demand->sum ?? 0)
            ]
          ],
          'customerOrder'=> ['meta' => $order->meta],
          'applicable'   => false,
      ];

      return $this->requestJson('POST', $url, $payload);
  }

  public function deleteDemand(string $id)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/demand/{$id}";
      return $this->requestJson('DELETE', $url);
  }

  public function deleteInvoiceOut(string $id)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/invoiceout/{$id}";
      return $this->requestJson('DELETE', $url);
  }

  public function deletePaymentIn(string $id)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/paymentin/{$id}";
      return $this->requestJson('DELETE', $url);
  }

  public function deleteCashIn(string $id)
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/cashin/{$id}";
      return $this->requestJson('DELETE', $url);
  }

  public function getAttributeValueId(object $entity, string $attrId): ?string
  {
      foreach (($entity->attributes ?? []) as $attr) {
          // у тебя resolver проверяет ($attr->id === $attrId)
          if (($attr->id ?? null) !== $attrId) continue;

          $href = $attr->value->meta->href ?? null;
          return $href ? basename($href) : null;
      }
      return null;
  }

  public function updateDemandApplicable(string $demandId, bool $applicable)
  {
     return $this->put("entity/demand/{$demandId}", ['applicable' => $applicable]);
  }

  public function updateOrderApplicable(string $orderId, bool $applicable)
  {
     return $this->put("entity/customerorder/{$orderId}", ['applicable' => $applicable]);
  }

  public function updateInvoiceOutApplicable(string $invoiceId, bool $applicable)
  {
     return $this->put("entity/invoiceout/{$invoiceId}", ['applicable' => $applicable]);
  }

  public function createSalesReturnFromDemand(object $order, object $demand): array
  {
      $url = "https://api.moysklad.ru/api/remap/1.2/entity/salesreturn";

      $positions = [];
      foreach (($demand->positions->rows ?? []) as $pos) {
          if (empty($pos->assortment->meta)) continue;

          $positions[] = [
              'assortment' => ['meta' => $pos->assortment->meta],
              'quantity'   => (float)($pos->quantity ?? 0),
              'price'      => (int)($pos->price ?? 0),
          ];
      }

      // ✅ Атрибут "Причина возврата"
      $attributes = [
          [
              'meta' => [
                  'href'      => 'https://api.moysklad.ru/api/remap/1.2/entity/salesreturn/metadata/attributes/245c1e2c-857c-11ec-0a80-00ca00032d13',
                  'type'      => 'attributemetadata',
                  'mediaType' => 'application/json',
              ],
              'value' => [
                  'meta' => [
                      'href'         => 'https://api.moysklad.ru/api/remap/1.2/entity/customentity/e374d372-857b-11ec-0a80-0fbe0003a99c/f3aa06bf-857b-11ec-0a80-07b7000347ae',
                      'metadataHref'=> 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/metadata/customEntities/e374d372-857b-11ec-0a80-0fbe0003a99c',
                      'type'         => 'customentity',
                      'mediaType'   => 'application/json',
                  ],
              ],
          ],
      ];

      $payload = [
          'organization' => ['meta' => $order->organization->meta],
          'agent'        => ['meta' => $order->agent->meta],
          'store'        => ['meta' => $order->store->meta],
          'demand'       => ['meta' => $demand->meta],
          'applicable'   => false,
          'positions'    => $positions,
          'attributes'   => $attributes,

      ];

      return $this->requestJson('POST', $url, $payload);
  }

  public function updateDemandAttributes(string $demandId, array $attributes): bool
  {
      $access = self::getMSLoginPassword();

      $url = 'https://api.moysklad.ru/api/remap/1.2/entity/demand/' . $demandId;

      $payload = [
          'attributes' => $attributes,
      ];

      $ch = curl_init($url);
      curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_CUSTOMREQUEST  => 'PUT',
          CURLOPT_ENCODING       => 'gzip',
          CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          CURLOPT_HTTPHEADER     => [
              'Authorization: Basic ' . base64_encode($access->login . ':' . $access->password),
              'Accept-Encoding: gzip',
              'Content-Type: application/json',
          ],
      ]);

      $resp = curl_exec($ch);
      $err  = curl_error($ch);
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($err || $code < 200 || $code >= 300) {
          file_put_contents(
              __DIR__ . '/../logs/ms_service/updatedemand.txt',
              "DEMAND ATTR UPDATE FAIL demand={$demandId} http={$code} err={$err} resp={$resp}\n",
              FILE_APPEND
          );
          return false;
      }

      return true;
  }

  public function buildDemandAttributePayload(string $attributeId, $value): array
  {
      return [
          [
              'meta' => [
                  'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/demand/metadata/attributes/' . $attributeId,
                  'type' => 'attributemetadata',
                  'mediaType' => 'application/json',
              ],
              'value' => $value,
          ],
      ];
  }

}
