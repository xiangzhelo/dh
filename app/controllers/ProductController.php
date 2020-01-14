<?php

namespace Dh\Controllers;

use Lib\Vendor\CommonFun;
use Lib\Vendor\MyCurl;
use Lib\Vendor\HtmlDom;
use Lib\Vendor\ImgFun;

class ProductController extends ControllerBase {

    public $cookie;
    public $supplierid;

    public function needLogin($relogin = '0') {
        $hasLogin = $this->hasLogin($this->username);
        if ($hasLogin == false || $relogin == '1') {
            $this->loginDh($this->username, $this->password);
        }
        $this->cookie = $this->getUserCookie($this->username);
        $this->supplierid = CommonFun::getCookieValueByKey($this->cookie, 'supplierid');
    }

    public function getGroupAction() {
        $this->needLogin();
        preg_match_all('/([^;]+);/i', $this->cookie, $arr);
        foreach ($arr[1] as $v) {
            $v1 = explode('=', $v);
            setcookie($v1[0], $v1[1], time() + 3600 * 24, '/', '.dhgate.com');
        }
        $url = 'http://seller.dhgate.com/prodmanage/akey/getGroup.do?_=' . time();
        $curl = new MyCurl();
        $jsonStr = $curl->get($url, $this->cookie, $this->header, 30);
        $json = json_decode($jsonStr, true)['result'];
        $productgroupid = isset($_COOKIE['myproductgroupid']) ? $_COOKIE['myproductgroupid'] : '';
        $this->echoJson(['status' => 'success', 'msg' => '获取成功', 'data' => $json, 'productgroupid' => $productgroupid]);
    }

    public function setGroupAction() {
        $groupid = $this->request->get('groupid', 'string', '');
        setcookie('myproductgroupid', $groupid, time() + 3600 * 24 * 365, '/', '.dhgate.com');
        exit();
    }

    public function indexAction() {
        $page = $this->request->get('page', 'int', 1);
        $status = $this->request->get('status', 'string', '');
        $catePubId = $this->request->get('catePubId', 'string', '');
        $cateIds = [];
        if (!empty($catePubId)) {
            $this->getCate($catePubId, $cateIds);
        }
        $size = 100;
        $pages = \Product::getPage($page, $size, $status, $cateIds, $this->username);
        $this->view->pages = $pages;
        $this->view->page = $page;
        $this->view->status = $status;
    }

    public function getCateAttrAction() {
        $catePubId = $this->request->get('catePubId', 'string', '');
        if ($catePubId == '') {
            $file = PUL_PATH . 'cateArr/0.json';
        } else {
            $file = PUL_PATH . 'cateArr/' . $catePubId . '.json';
        }
        if (file_exists($file)) {
            echo file_get_contents($file);
            exit();
        }
        $this->needLogin();
        $contents = MyCurl::get('http://seller.dhgate.com/syi/categorybyid.do?catePubId=' . $catePubId . '&isblank=true&_=' . time(), $this->cookie);
        if ($contents != false) {
            file_put_contents($file, $contents);
        }
        echo $contents;
        exit();
    }

    public function getCateAttrLAction() {
        $catePubId = $this->request->get('catePubId', 'string', '');
        $file = PUL_PATH . 'cateArrL/' . $catePubId . '.json';
        if (file_exists($file)) {
            $jsonStr = file_get_contents($file);
            $json = json_decode($jsonStr, true);
            if (!empty($json['data'])) {
                echo $jsonStr;
                exit();
            }
        }
        $this->needLogin();
        $contents = MyCurl::get('http://seller.dhgate.com/syi/cateAttrL.do?catePubId=' . $catePubId . '&isblank=true&_=' . time(), $this->cookie);
        if ($contents != false) {
            file_put_contents($file, $contents);
        }
        echo $contents;
        exit();
    }

    public function getCateAttrL($dh_category_id) {
        $file = PUL_PATH . 'cateArrL/' . $dh_category_id . '.json';
        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file), true);
            if (!empty($json['data'])) {
                return $json;
            }
        }
        $contents = MyCurl::get('http://seller.dhgate.com/syi/cateAttrL.do?catePubId=' . $dh_category_id . '&isblank=true&_=' . time(), $this->cookie);
        return json_decode($contents, true);
    }

    public function getMatchAction() {
        set_time_limit(0);
        $this->needLogin();
        $this->getMatch(0);
        echo 'success';
        exit();
    }

    public function getMatch($cate_id) {
        $file = PUL_PATH . 'cateArr/' . $cate_id . '.json';
        if (file_exists($file)) {
            $jsonStr = file_get_contents($file);
        } else {
            $jsonStr = MyCurl::get('http://seller.dhgate.com/syi/categorybyid.do?catePubId=' . $cate_id . '&isblank=true&_=' . time(), $this->cookie);
            if ($jsonStr != false) {
                file_put_contents($file, $jsonStr);
            }
        }
        $json = json_decode($jsonStr, true);
        foreach ($json['data'] as $v) {
            if ($v['leaf'] == '0') {
                $this->getMatch($v['catePubId']);
            } else {
                $this->match($v['catePubId']);
            }
        }
    }

    public function match($cate_id) {
        $json = $this->getCateAttrL($cate_id);
        foreach ($json['data']['attributeList'] as $v1) {
            if (!empty($v1['valueList'])) {
                foreach ($v1['valueList'] as $v2) {
                    $keyvalue = \Keyvalue::findFirst([
                                'conditions' => 'key=:key: and keycn=:keycn: and value=:value: and valuecn=:valuecn:',
                                'bind' => [
                                    'key' => strtolower($v1['lineAttrName']),
                                    'keycn' => strtolower($v1['lineAttrNameCn']),
                                    'value' => strtolower($v2['lineAttrvalName']),
                                    'valuecn' => strtolower($v2['lineAttrvalNameCn'])
                                ]
                    ]);
                    if ($keyvalue == false) {
                        $keyvalue = new \Keyvalue();
                        $keyvalue->key = strtolower($v1['lineAttrName']);
                        $keyvalue->value = strtolower($v2['lineAttrvalName']);
                        $keyvalue->keycn = strtolower($v1['lineAttrNameCn']);
                        $keyvalue->valuecn = strtolower($v2['lineAttrvalNameCn']);
                        $keyvalue->createtime = date('Y-m-d H:i:s');
                        $keyvalue->save();
                    }
                    $str = strtolower($v1['lineAttrName']) . ':' . strtolower($v2['lineAttrvalName']);
                    $dest_words = $v1['lineAttrNameCn'] . ':' . $v2['lineAttrvalNameCn'];
                    $words = \Words::findFirst([
                                'conditions' => 'orign_words=:orign_words:',
                                'bind' => [
                                    'orign_words' => $str
                                ]
                    ]);
                    if ($words == false) {
                        $words = new \Words();
                        $words->orign_words = $str;
                        $words->dest_words = $dest_words;
                        $words->status = 200;
                        $words->createtime = date('Y-m-d H:i:s');
                        $words->save();
                    } else {
                        if ($words->status != 200) {
                            file_get_contents(MY_DOMAIN . '/lexicon/update?id=' . $words->id . '&dest_words=' . $dest_words);
                        }
                    }
                }
            }
        }
    }

    public function draftAction() {
        set_time_limit(0);
        $id = $this->request->get('id', 'int');
        $isSave = $this->request->get('isSave', 'int', 0);
        $productgroupid = $this->request->get('productgroupid', 'string', '');
        if (empty($productgroupid)) {
            $productgroupid = isset($_COOKIE['myproductgroupid']) ? $_COOKIE['myproductgroupid'] : '';
        }
        $model = \Product::findFirst($id);
        if (!empty($model->current_user)) {
            $this->username = $model->current_user;
        }
        $this->needLogin();
        if ($model == false || empty($model->product_data)) {
            $this->echoJson(['status' => 'error', 'msg' => '该数据未抓取']);
        }
        $cate014 = ['014028007', '014028004', '014028008002', '014028005', '014028003001', '014028006', '014028002', '014028009',
            '014028001001', '014028001002', '014028010', '014026018001', '014026016', '014026003', '014026004', '014026011', '014026014',
            '014026005001', '014026012', '014026013', '014026007', '014026006', '014026010', '014026002003', '014026002001', '014026002004',
            '014026020001', '014026020003', '014027001006', '014027001001', '014027002012', '014027002001'];    //衣服
        $cate006 = ['006003112', '006003115', '006003115', '006011116', '006011117', '006007176', '006007167']; //玩具礼物
        $cate142 = ['142006003', '142006005', '142006001', '142003011', '142003003003']; //母婴用品
        $cate135 = ['135005002', '135005006', '135010002', '135010003']; //手机和手机附件
        $cate008 = ['008178', '008002']; //消费类电子
        $cate140 = ['140002001', '140005']; //时尚配件
        $cate004 = ['004002002', '004002008', '004002011', '004006001', '004006008', '004007001', '004007008']; //珠宝
        $cate005 = ['005002', '005001']; //表
        $cate143 = ['143103113107', '143103113102']; //汽配  143106001车罩
        $cate024 = ['024029008', '024020005007', '024029001002', '024029001001', '024029005004', '024029003', '024029005003', '024026007001'
            , '024026007005', '024003003017', '024026008001', '014026010', '014028011', '024003019005', '024020004003', '024020005003'
            , '024020005002', '024020005001', '024020005007', '024034008001', '024034007002', '024033', '024023002005', '024023002004'
            , '024023001015', '024023001020', '024023001005', '024023001013', '024023001003', '024023001001', '024037']; //运动与户外产品
        if (in_array($model->dh_category_id, ['141001', '141003', '141004', '141006', '141007'])) {//鞋子
            $this->playDraftOrSave($model, $id, '141001', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, ['137006', '137005', '137011008', '137011005', '137010', '137011004', '137011002', '137011003'])) {
            $this->playDraftOrSave($model, $id, '137005', $isSave, $productgroupid); //包
        } else if (in_array($model->dh_category_id, $cate014)) {
            $this->playDraftOrSave($model, $id, '014028001001', $isSave, $productgroupid); //衣服
        } else if (in_array($model->dh_category_id, $cate006)) {
            $this->playDraftOrSave($model, $id, '006003112', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, $cate142)) {
            $this->playDraftOrSave($model, $id, '006003112', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, $cate135)) {
            $this->playDraftOrSave($model, $id, '006003112', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, $cate008)) {
            $this->playDraftOrSave($model, $id, '006003112', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, $cate140)) {
            $this->playDraftOrSave($model, $id, '006003112', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, $cate004)) {
            $this->playDraftOrSave($model, $id, '006003112', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, $cate005)) {
            $this->playDraftOrSave($model, $id, '006003112', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, $cate143)) {
            $this->playDraftOrSave($model, $id, '006003112', $isSave, $productgroupid);
        } else if (in_array($model->dh_category_id, $cate024)) {
            $this->playDraftOrSave($model, $id, '014028001001', $isSave, $productgroupid); //运动与户外产品
        } else {
            $this->echoJson(['status' => 'error', 'msg' => '该分类未开放']);
        }
    }

    private $specMaxLen = 1010;

    private function playDraftOrSave($model, $id, $cate = '141001', $isSave = '', $productgroupid = '') {
        if (!empty($model->dh_product_id)) {
            $url = 'http://seller.dhgate.com/syi/edit.do?prodDraftId=' . $model->dh_product_id . '&inp_catepubid=' . $model->dh_category_id . '&isdraftbox=1';
            if (empty($isSave)) {
                $drafUrl = 'http://seller.dhgate.com/syi/ajaxSavedraftboxV2.do?isblank=true&prodDraftId=' . $model->dh_product_id;
            } else {
                $drafUrl = 'http://seller.dhgate.com/syi/save.do';
            }
        } else {
            $url = 'http://seller.dhgate.com/syi/edit.do?inp_catepubid=' . $model->dh_category_id;
            if (empty($isSave)) {
                $drafUrl = 'http://seller.dhgate.com/syi/ajaxSavedraftboxV2.do?isblank=true&prodDraftId=';
            } else {
                $drafUrl = 'http://seller.dhgate.com/syi/save.do';
            }
        }
        if (!empty($model->dh_itemcode)) {
            $url = 'http://seller.dhgate.com/syi/edit.do?pid=' . $model->dh_itemcode;
        }
        $productInfo = array_column($this->getCateAttrL($model->dh_category_id)['data']['attributeList'], null, 'lineAttrNameCn');
        $sourceData = json_decode($model->tran_product_data, true);
        $data = json_decode(file_get_contents(PUL_PATH . 'catepub_json/' . $cate . '.json'), true);
        for ($i = 0; $i < 4; $i++) {
            $editContent = MyCurl::get($url, $this->cookie, $this->header);
            if (strpos($editContent, 'titleconbox') !== false) {
                break;
            } else {
                $this->needLogin(1);
            }
            if ($i == 3) {
                $this->echoJson(['status' => 'error', 'msg' => '请检查网络，如果该产品上传过可能是同款被禁']);
            }
        }
        $dom = new HtmlDom();
        $html = $dom->load($editContent);
        foreach ($html->find('form input') as $input) {
            $key = $input->name;
            if (isset($data[$key])) {
                $data[$key] = $input->value;
            }
        }
        $this->pubData($data, $sourceData, $html);
        if (!empty($data['prodDraftId']) || empty($isSave)) {
            $this->specMaxLen = 1050;
        }
        if (!empty($productgroupid)) {
            $data['productgroupid'] = $productgroupid;
        }
        $this->units($data, $sourceData, $html);
        $this->keyWords($data, $sourceData);
        if (isset($productInfo['镜框颜色'])) {
            $productInfo['颜色'] = $productInfo['镜框颜色'];
            unset($productInfo['镜框颜色']);
        }
        if (isset($productInfo['戒指尺寸'])) {
            $productInfo['尺码'] = $productInfo['戒指尺寸'];
            unset($productInfo['戒指尺寸']);
        }
        if (isset($productInfo['手套尺寸'])) {
            $productInfo['尺码'] = $productInfo['手套尺寸'];
            unset($productInfo['手套尺寸']);
        }
        if (in_array($model->dh_category_id, ['004002002', '004002008', '004002011', '004006001', '004006008', '004007001', '004007008', '143103113102', '143103113107', '024029008', '024020005007', '024020005007', '024034008001', '024034007002', '024023002005', '024023002004', '024023001015', '024023001020', '024023001005', '024023001013', '024023001003', '024023001001', '004006001', '004006008', '135005006'])) {
            $this->addProductInfo($productInfo, '颜色');
        }
        if (in_array($model->dh_category_id, ['024020004003'])) {
            $productInfo['color'] = $productInfo['颜色'];
            unset($productInfo['颜色']);
            $this->addProductInfo($productInfo, '颜色');
        }

        if (in_array($model->dh_category_id, ['140002001'])) {
            $productInfo['材质'] = $productInfo['风格'];
            unset($productInfo['风格']);
            $this->addProductInfo($productInfo, '尺寸');
        }
        if (in_array($model->dh_category_id, ['024033'])) {
            $this->addProductInfo($productInfo, '尺寸');
        }
        if (in_array($model->dh_category_id, ['014027002012'])) {
            $productInfo['尺码'] = $productInfo['袜子类型'];
            unset($productInfo['袜子类型']);
        }

        if (in_array($model->dh_category_id, ['024020005002', '024029005004', '135005002'])) {
            $this->addProductInfo($productInfo, '尺码');
        }

        if (in_array($model->dh_category_id, ['137010'])) {//137010
            $productInfo['尺码'] = $productInfo['颜色'];
            unset($productInfo['颜色']);
            $this->addProductInfo($productInfo, '颜色');
        }

        $this->skuInfo($data, $sourceData, $productInfo);
        if (in_array($cate, ['141001', '014028001001'])) {
            $this->sizeTp($data, $sourceData, $html);
        }
        if ($data['setdiscounttype'] == '1') {
            $data['noSpecPrice'] = '';
            $data['discountRange'] = json_encode([['startqty' => '1', 'discount' => '0'], ['startqty' => '2', 'discount' => '3']]); //$sourceData['折扣']
        } else {
            $pArr = explode('-', $sourceData['价格']);
            $price = trim($pArr[count($pArr) - 1], ' ');
            eval(str_replace('x', $price, '$price=' . $this->priceFormula . ';'));
            $data['noSpecPrice'] = number_format($price, 2, '.', '');
            $data['discountRange'] = json_encode([['startqty' => '1', 'discount' => number_format($price, 2, '.', '')], ['startqty' => '2', 'discount' => number_format($price * 0.97, 2, '.', '')]]);
        }
        $html->clear();
        $this->saleTp($data);
        $ret = $this->imglist($data, $sourceData);
        if ($ret == false) {
            if ($model->status == '4') {
                $model->need_attribute = '图片上传失败';
                $model->status = 402;
                $model->updatetime = date('Y-m-d H:i:s');
                $model->save();
            }
            $this->echoJson(['status' => 'error', 'msg' => '图片上传失败']);
        }
        if (!empty($data['prodDraftId']) || empty($isSave)) {
            $retStr = MyCurl::post($drafUrl, $data, $this->cookie, ['X-FORWARDED-FOR:' . CommonFun::Rand_IP(), 'CLIENT-IP:' . CommonFun::Rand_IP()]);
            if (empty($isSave)) {
                $ret = json_decode($retStr, true);
                if ($ret['code'] == '1000') {
                    $model = \Product::findFirst($id);
                    $model->dh_product_id = $ret['data'];
                    $model->updatetime = date('Y-m-d H:i:s');
                    $model->current_user = empty($model->current_user) ? $this->username : $model->current_user;
                    $model->status = empty($isSave) ? 2 : 200;
                    $model->save();
                    $this->echoJson(['status' => 'success', 'msg' => '保存成功', 'data' => ['dh_itemcode' => '', 'dh_product_id' => $model->dh_product_id, 'dh_category_id' => $model->dh_category_id]]);
                }
            } else {
                preg_match('/\{(.*)\}/', $retStr, $arr);
                if (isset($arr[0])) {
                    $ret = json_decode($arr[0], true);
                    if (isset($ret['itemcode']) && $ret['itemcode'] > 0) {
                        $model = \Product::findFirst($id);
                        $model->dh_itemcode = $ret['itemcode'];
                        $model->current_user = empty($model->current_user) ? $this->username : $model->current_user;
                        $model->updatetime = date('Y-m-d H:i:s');
                        $model->status = 200;
                        $model->save();
                        $this->echoJson(['status' => 'success', 'msg' => '保存成功', 'data' => ['dh_itemcode' => $model->dh_itemcode, 'dh_category_id' => $model->dh_category_id]]);
                    }
                }
            }
        } else {
            $retStr = $this->tranProduct($data, $id);
        }
        $error = '';
        $errorJson = json_decode($retStr, true);
        if (isset($errorJson['status']['subErrors'][0]['message'])) {
            $error = $errorJson['status']['subErrors'][0]['message'];
        } else {
            preg_match('/\{(.*)\}/', $retStr, $arr);
            if (isset($arr[0])) {
                $ret = json_decode($arr[0], true);
                if (isset($ret['errors'])) {
                    $errKey = array_values($ret['errors'])[0];
                    if (isset($this->errorsArr[$errKey]) && isset($this->errorSkus[$this->errorsArr[$errKey]])) {
                        $error = $this->errorSkus[$this->errorsArr[$errKey]]['name'] . '错误或者缺失';
                    }
                }
            }
        }
        if ($model->status == '4') {
            $model->need_attribute = $error;
            $model->updatetime = date('Y-m-d H:i:s');
            $model->status = 402;
            $model->save();
        }
        $this->echoJson(['status' => 'error', 'msg' => empty($error) ? '失败' : $error, 'ret' => $retStr]);
        $this->echoJson($data);
    }

    public function tranProduct($data, $id) {
        $arr = json_decode($data['attrlist'], true);
        $attrList = [];
        foreach ($arr as $v1) {
            $itemAttrValList = [];
            foreach ($v1['valueList'] as $v2) {
                if ($v1['attrName'] == 'BRAND') {
                    $v2['attrValId'] = 99;
                    $v2['lineAttrvalName'] = 'No Brand';
                    $v2['lineAttrvalNameCn'] = '无品牌';
                    $v1['isbrand'] = 0;
                }
                $itemAttrValList[] = [
                    'attrId' => (int) $v1['attrId'],
                    'attrName' => $v1['attrName'],
                    'attrValId' => (int) $v2['attrValId'],
                    'lineAttrvalName' => $v2['lineAttrvalName'],
                    'lineAttrvalNameCn' => empty($v2['lineAttrvalNameCn']) ? '其他' : $v2['lineAttrvalNameCn'],
                    'picUrl' => $v2['picUrl'],
                    'brandId' => isset($v2['brandValId']) ? $v2['brandValId'] : '',
                ];
            }
            if (isset($data['itemcode']) && !empty($data['itemcode'])) {
                $attrList[] = [
                    'attrId' => (int) $v1['attrId'],
                    'attrName' => null,
                    'attrNameCn' => null,
                    'isbrand' => (int) $v1['isbrand'],
                    'itemAttrValList' => $itemAttrValList
                ];
            } else {
                $attrList[] = [
                    'isbrand' => (int) $v1['isbrand'],
                    'itemAttrValList' => $itemAttrValList
                ];
            }
        }
        $imgList = [];
        $img_arr = json_decode($data['imglist'], true);
        foreach ($img_arr as $v) {
            $imgList[] = [
                'imgMd5' => $v['imgmd5'],
                'imgUrl' => $v['fileurl'],
                'type' => '1'
            ];
        }
        $skuList = [];
        $sku_arr = json_decode($data['proSkuInfo'], true);
        foreach ($sku_arr[0]['skuInfoList'] as $v1) {
            $itemSkuAttrvalList = [];
            foreach ($v1['attrList'] as $v2) {
                $itemSkuAttrvalList[] = [
                    'attrId' => (int) $v2['attrId'],
                    'attrValId' => (int) $v2['attrVid'],
                    'sizeSpecType' => $v2['attrId'] == '9999' ? 3 : ((int) $v2['type'])
                ];
            }
            $sku = [
                'inventory' => (int) $v1['stock'],
                'itemSkuAttrvalList' => $itemSkuAttrvalList,
                'itemSkuInvenList' => [],
                'retailPrice' => $v1['price'] * 1,
                'saleStatus' => (int) $v1['status'],
                'skuCode' => $v1['skuCode']
            ];
            if (isset($data['itemcode']) && !empty($data['itemcode'])) {
                $sku['skuId'] = 0;
                $sku['skuMD5'] = null;
                $sku['itemSkuAttrValueList'] = $sku['itemSkuAttrvalList'];
                $sku['itemSkuAttrvalList'] = null;
            }
            $skuList[] = $sku;
        }
        $disList = [];
        $dis_arr = json_decode($data['discountRange'], true);
        foreach ($dis_arr as $v) {
            $disList[] = [
                'discount' => $v['discount'] / 100,
                'startQty' => (int) $v['startqty']
            ];
        }
        $specList = [];
        $spec_arr = json_decode($data['specselfDef'], true);
        foreach ($spec_arr as $v) {
            $specList[] = [
                'attrValId' => (int) $v['attrValId'],
                'attrValName' => $v['attrvalName'],
                'picUrl' => $v['picUrl']//$this->getPicUrl($this->access_token, $this->username, $v['picUrl'])
            ];
        }
        $productData = [
            'method' => 'dh.item.add',
            'v' => '2.0',
            'access_token' => $this->access_token,
            'timestamp' => (string) (CommonFun::msectime()),
            'catePubId' => $data['catepubid'],
            'itemGroupId' => $data['productgroupid'],
            'shippingModelId' => $data['shippingmodelid'],
            'siteId' => 'EN',
            'vaildDay' => $data['vaildday'],
            'afterSaleTemplateId' => $data['saleTemplateId'],
            'sizeTemplateId' => isset($data['sellerSzTemplateId']) ? $data['sellerSzTemplateId'] : '',
            'itemBase' => json_encode([
                'htmlContent' => $data['elm'],
                'itemName' => $data['productname'],
                'keyWord1' => $data['keyword1'],
                'keyWord2' => $data['keyword2'],
                'keyWord3' => $data['keyword3'],
                'shortDesc' => $data['productdesc'],
                'videoUrl' => $data['videourl']
                    ], JSON_UNESCAPED_UNICODE),
            'itemPackage' => json_encode([
                'grossWeight' => $data['productweight'],
                'height' => $data['sizeheight'],
                'itemWeigthRange' => null, //$data['stepweight'],
                'length' => $data['sizelen'],
                'measureId' => $data['measureid'],
                'packingQuantity' => $data['packquantity'],
                'width' => $data['sizewidth']
                    ], JSON_UNESCAPED_UNICODE),
            'itemSaleSetting' => json_encode([
                'leadingTime' => $data['proLeadingtime'],
                'maxSaleQty' => $data['maxSaleQty'],
                'priceConfigType' => $data['setdiscounttype']
                    ], JSON_UNESCAPED_UNICODE),
            'itemAttrList' => json_encode($attrList, JSON_UNESCAPED_UNICODE),
            'itemImgList' => json_encode($imgList, JSON_UNESCAPED_UNICODE),
            'itemSkuList' => json_encode($skuList, JSON_UNESCAPED_UNICODE),
            'itemWholesaleRangeList' => json_encode($disList, JSON_UNESCAPED_UNICODE),
            'itemInventory' => $data['inventory'],
            'itemAttrGroupList' => $data['attrGroupDetail'],
            'itemSpecSelfDefList' => json_encode($specList, JSON_UNESCAPED_UNICODE)
        ];
        if (isset($data['itemcode']) && !empty($data['itemcode'])) {
            $productData['itemCode'] = $data['itemcode'];
            $productData['method'] = 'dh.item.update';
        }
//        $this->echoJson($productData);
        $curl = new MyCurl();
        $out = $curl->post('http://api.dhgate.com/dop/router', $productData, '', null, 300);
        $ret = json_decode($out, true);
        if (isset($ret['itemCode']) && $ret['itemCode'] > 0) {
            $model = \Product::findFirst($id);
            $model->dh_itemcode = $ret['itemCode'];
            $model->current_user = empty($model->current_user) ? $this->username : $model->current_user;
            $model->updatetime = date('Y-m-d H:i:s');
            $model->status = 200;
            $model->save();
            $this->echoJson(['status' => 'success', 'msg' => '保存成功', 'data' => ['dh_itemcode' => $model->dh_itemcode, 'dh_category_id' => $model->dh_category_id]]);
        }
        return $out;
//        $this->echoJson($productData);
    }

    public function getPicUrl($token, $imgBannerName, $imageBase64) {
        $curl = new MyCurl();
        $post_data = [
            'method' => 'dh.album.img.upload',
            'v' => '2.0',
            'access_token' => $token,
            'timestamp' => time() * 1000,
            'funType' => 'avim',
            'imgBannerName' => $imgBannerName,
            'imageBase64' => $imageBase64
        ];
        $jsonStr = $curl->post('http://api.dhgate.com/dop/router', $post_data, '', null, 300);
        $json = json_decode($jsonStr, true);
        return $json['productImg']['l_imgurl'];
    }

    private function addProductInfo(&$productInfo, $key) {
        $valueList = [];
        for ($i = 1000; $i < $this->specMaxLen; $i++) {
            $valueList[] = [
                'attrValId' => (string) $i,
                'picUrl' => '',
                'lineAttrvalName' => 'custom' . $i,
                'lineAttrvalNameCn' => 'custom' . $i,
                'iscustomsized' => '0'
            ];
        }
        $productInfo[$key] = [
            'buyAttr' => '1',
            'attrId' => '9999',
            'style' => '3',
            'is_add' => '1',
            'type' => '3',
            'lineAttrName' => $key,
            'valueList' => $valueList
        ];
    }

    private function skuInfo(&$data, &$sourceData, $productInfo) {
        $Color_Id = isset($productInfo['颜色']['attrId']) ? $productInfo['颜色']['attrId'] : '';
        $Size_Id = isset($productInfo['尺码']['attrId']) ? $productInfo['尺码']['attrId'] : '';
        $Height_Id = isset($productInfo['尺寸']['attrId']) && $productInfo['尺寸']['buyAttr'] == '1' ? $productInfo['尺寸']['attrId'] : '';
        $Material_Id = isset($productInfo['材质']['attrId']) && $productInfo['材质']['buyAttr'] == '1' ? $productInfo['材质']['attrId'] : '';
        $Type_Id = isset($productInfo['类型']['attrId']) && $productInfo['类型']['buyAttr'] == '1' ? $productInfo['类型']['attrId'] : '';
        $skuInfo = [
            [
                'class' => '###',
                'inventoryLocation' => $data['inventoryLocation'],
                'leadingTime' => 4, //备货期
                'skuInfoList' => []
            ]
        ];
        if ($Color_Id != '') {
            $colorsList = CommonFun::arrayColumns($productInfo['颜色']['valueList'], null, 'lineAttrvalName');
            $colorsList = array_filter($colorsList);
            if (!empty($colorsList)) {
                $colorsList = $this->color($sourceData['属性'], $colorsList);
            }
            if (isset($productInfo['颜色']['is_add']) && $productInfo['颜色']['is_add'] == '1' && count($colorsList) == 0) {
                $Color_Id = '';
            }
            if (isset($productInfo['颜色']['buyAttr']) && $productInfo['颜色']['buyAttr'] != '1' && count($colorsList) == 0) {
                $Color_Id = '';
            }
        }
        if ($Size_Id != '') {
            $sizesList = $this->skuList($productInfo, $sourceData['属性'], $Size_Id, '尺码');
        }
        if ($Height_Id != '') {
            $heightList = $this->skuList($productInfo, $sourceData['属性'], $Height_Id, '尺寸');
        }

        if ($Material_Id != '') {
            $materialList = $this->skuList($productInfo, $sourceData['属性'], $Material_Id, '材质');
        }
        if ($Type_Id != '') {
            $typeList = $this->skuList($productInfo, $sourceData['属性'], $Type_Id, '类型');
        }
        $specselfDef = [];
        $colorValueList = [];
        $sizeValueList = [];
        $heightValueList = [];
        $materialValueList = [];
        $typeValueList = [];
        $noReList = [];
        foreach ($sourceData['属性'] as $li) {
            if (!is_array($li)) {
                continue;
            }
            if (($Color_Id == '' || isset($colorsList[$li['颜色']])) && ($Size_Id == '' || isset($sizesList[$li['尺码']])) && ($Height_Id == '' || isset($heightList[$li['尺寸']])) && ($Material_Id == '' || isset($materialList[$li['材质']])) && ($Type_Id == '' || isset($typeList[$li['类型']]))) {
                $tkey = md5((!empty($Color_Id) ? $li['颜色'] : '') . '|' . (!empty($Size_Id) ? $li['尺码'] : '') . '|' . (!empty($Height_Id) ? $li['尺寸'] : '') . '|' . (!empty($Material_Id) ? $li['材质'] : '') . '|' . (!empty($Type_Id) ? $li['类型'] : ''));
                if (isset($noReList[$tkey])) {
                    continue;
                }
                $noReList[$tkey] = 1;
                $ids = [];
                $attrList = [];

                foreach ($productInfo as $k => $v) {
                    $id = $v['attrId'];
                    if ($id != '' && $v['buyAttr'] == '1') {
                        if ($k == '颜色') {
                            $list = $colorsList;
                        } elseif ($k == '尺码') {
                            $list = $sizesList;
                        } elseif ($k == '尺寸') {
                            $list = $heightList;
                        } elseif ($k == '材质') {
                            $list = $materialList;
                        } elseif ($k == '类型') {
                            $list = $typeList;
                        } else {
                            continue;
                        }
                        if (empty($li[$k])) {
                            continue;
                        }
                        $this->skuAttrList($data, $attrList, $ids, $id, $list, $li[$k], $v['type']);
                    }
                }
                eval(str_replace('x', $li['折扣价'], '$price=' . $this->priceFormula . ';'));
                $price = number_format($price, 2, '.', '');
                if (empty($price)) {
                    $this->echoJson(['status' => 'error', 'msg' => '价格公式错误']);
                }
                if (!empty($attrList)) {
                    $skuInfo[0]['skuInfoList'][] = [
                        'class' => '#',
                        'attrList' => $attrList,
                        'status' => ($li['库存'] > 0 ? '1' : '0'),
                        'price' => $price,
                        'stock' => '0', //(string) $li['库存'],
                        'skuCode' => '',
                        'id' => implode('A', $ids)
                    ];
                }
                if (!empty($li['图片'])) {
                    $sourceData['产品图片'][md5($li['图片'])] = $li['图片'];
                }
                if ($Color_Id != '') {
                    $this->skuValueList($data, $colorValueList, $colorsList, $li, $specselfDef, '颜色', $productInfo['颜色']);
                }
                if ($Size_Id != '') {
                    $this->skuValueList($data, $sizeValueList, $sizesList, $li, $specselfDef, '尺码', $productInfo['尺码']);
                }
                if ($Height_Id != '') {
                    $this->skuValueList($data, $heightValueList, $heightList, $li, $specselfDef, '尺寸', $productInfo['尺寸']);
                }
                if ($Material_Id != '') {
                    $this->skuValueList($data, $materialValueList, $materialList, $li, $specselfDef, '材质', $productInfo['材质']);
                }
                if ($Type_Id != '') {
                    $this->skuValueList($data, $typeValueList, $typeList, $li, $specselfDef, '类型', $productInfo['类型']);
                }
            }
        }
        $mustSkuInfo = 0;
        foreach ($productInfo as $v) {
            if ($v['buyAttr'] == '1' && (!isset($v['is_add']) || $v['is_add'] != '1')) {
                $mustSkuInfo = 1;
            }
        }
        $skuInfoCount = count($skuInfo[0]['skuInfoList']);
        if ($mustSkuInfo == 0 && $skuInfoCount == 0) {
            $data['setdiscounttype'] = '2';
            $skuInfo = [];
            $specselfDef = [];
        } else {
            if ($skuInfoCount == 0) {
                $this->echoJson(['status' => 'error', 'msg' => '价格表不可为空']);
            }
        }
        $data['proSkuInfo'] = json_encode($skuInfo, JSON_UNESCAPED_UNICODE);
        $attrlist = [];
        if ($Color_Id != '') {
            $this->skuAttr($attrlist, $Color_Id, $productInfo, $colorValueList, '颜色');
        }
        if ($Size_Id != '') {
            $this->skuAttr($attrlist, $Size_Id, $productInfo, $sizeValueList, '尺码');
        }
        if ($Height_Id != '') {
            $this->skuAttr($attrlist, $Height_Id, $productInfo, $heightList, '尺寸');
        }
        if ($Material_Id != '') {
            $this->skuAttr($attrlist, $Material_Id, $productInfo, $materialList, '材质');
        }
        if ($Type_Id != '') {
            $this->skuAttr($attrlist, $Type_Id, $productInfo, $typeList, '类型');
        }
        $this->attr($data, $productInfo, $attrlist, $sourceData);
        $data['specselfDef'] = json_encode(array_values($specselfDef), JSON_UNESCAPED_UNICODE);
        $data['attrlist'] = json_encode(array_values($attrlist), JSON_UNESCAPED_UNICODE);
    }

    private function skuAttrList(&$data, &$attrList, &$ids, $id, $list, $key, $type = '1') {
        $ids[] = $id . '_' . $list[$key]['attrValId'];
        $attrList[] = [
            'attrId' => (string) $id,
            'attrVid' => (string) $list[$key]['attrValId'],
            'type' => $type,
            'class' => '##'
        ];
        if ($id == '9999') {
            $data['c_' . $id . '_vname'] = $list[$key]['lineAttrvalName'];
            $data['text_defineattrval_url_9999_' . $list[$key]['attrValId']] = '';
        } else {
            $data['c_' . $id . '_vname'] = $id . '_' . $list[$key]['attrValId'];
        }
    }

    private function skuValueList($data, &$skuValueList, $skuList, $li, &$specselfDef, $key = '颜色', $info = []) {
        if (!isset($skuValueList[$skuList[$li[$key]]['attrValId']])) {
            if (!empty($li['图片']) && isset($info['style']) && $info['style'] == '3' && in_array($key, ['类型', '颜色'])) {
                $imgData = $this->imgIntoDh($li['图片'], $data['imgtoken'], $data['supplierid']);
            } else {
                $imgData = ['l_imgurl' => ''];
            }

            if (isset($info['is_add']) && $info['is_add'] == '1' && isset($skuList[$li[$key]]['lineAttrvalName'])) {
                $name = $skuList[$li[$key]]['lineAttrvalName'];
                $specselfDef[$name] = [
                    'attrvalName' => $name,
                    'attrId' => '9999',
                    'attrValId' => $skuList[$li[$key]]['attrValId'],
                    'picUrl' => $imgData['l_imgurl']
                ];
            } else {
                $skuValueList[$skuList[$li[$key]]['attrValId']] = [
                    'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                    'attrValId' => $skuList[$li[$key]]['attrValId'],
                    'lineAttrvalName' => $skuList[$li[$key]]['lineAttrvalName'],
                    'lineAttrvalNameCn' => $skuList[$li[$key]]['lineAttrvalNameCn'],
                    'iscustomsized' => $skuList[$li[$key]]['iscustomsized'],
                    'picUrl' => $imgData['l_imgurl'],
                    'brandValId' => ''
                ];
            }
        }
    }

    private function sizeTp(&$data, $sourceData, $html) {
        $szTemplateClassIdStr = $html->find('#szTemplateClassIdStr', 0)->value;
        $getSzSellerTemplate = json_decode(MyCurl::post('http://seller.dhgate.com/syi/getSzSellerTemplate.do', ['classIdStr' => $szTemplateClassIdStr], $this->cookie), true);
        if (isset($getSzSellerTemplate['data']) && count($getSzSellerTemplate['data']) > 0) {
            $getSzSellerTemplate = $getSzSellerTemplate['data']; //array_column($getSzSellerTemplate['data'], 'templateNameCn');
        } else {
            $getSzSellerTemplate = [];
        }
        if ((isset($sourceData['适用']) && is_array($sourceData['适用']) && in_array('women', $sourceData['适用'])) || in_array('女士', $sourceData)) {
            $template = 'women|女';
        } else {
            $template = 'men|男';
        }
        foreach ($getSzSellerTemplate as $v) {
            if (preg_match('/^' . $template . '/', $v['templateNameCn']) || preg_match('/^' . $template . ' /', $v['templateName'])) {
                $data['sellerSzTemplateId'] = $v['szId'];
                $data['sellerTemplateName'] = $v['templateNameCn'];
            }
        }
    }

    private function attr(&$data, &$productInfo, &$attrlist, $sourceData) {
        $num = 2;
        foreach ($productInfo as $key => $arr) {
            if (($arr['buyAttr'] == '1') || $arr['located'] == '1' || $arr['attrId'] == '782902') {//in_array($key, ['颜色', '尺码', '尺寸'])
                continue;
            }
            $attrlist[$num] = [
                'class' => 'com.dhgate.syi.model.ProductAttributeVO',
                'attrId' => $arr['attrId'],
                'attrName' => $arr['lineAttrName'],
                'isbrand' => $arr['isbrand'],
                'valueList' => [],
            ];
            foreach ($sourceData as $k => $v) {
                if (preg_match('/[\x7f-\xff]/', $k)) {
                    if ($k == $arr['lineAttrNameCn']) {
                        if (!empty($v)) {
                            if (is_array($v)) {
                                foreach ($v as $k1 => $v1) {
                                    if (is_array($arr['valueList']) && count($arr['valueList']) > 0) {
                                        if (strpos($v1, '自定义') !== false) {
                                            $vArr = explode('|', $v1);
                                            $attrlist[$num]['valueList']['自定义'] = $this->setValueList(0, isset($vArr[1]) && !empty($vArr[1]) ? $vArr[1] : '', '', '0', '');
                                        } else {
                                            foreach ($arr['valueList'] as $k2 => $v2) {
                                                if ($v1 == $v2['lineAttrvalNameCn']) {
                                                    $attrlist[$num]['valueList'][] = $this->setValueList($v2['attrValId'], $v2['lineAttrvalName'], $v2['lineAttrvalNameCn'], $v2['iscustomsized'], $v2['picUrl']);
                                                    unset($productInfo[$key]['valueList'][$k2]);
                                                    unset($arr['valueList'][$k2]);
                                                }
                                            }
                                            if (empty($attrlist[$num]['valueList'])) {
                                                $keyvalue = \Keyvalue::findFirst([
                                                            'conditions' => 'valuecn=:valuecn:',
                                                            'bind' => [
                                                                'valuecn' => $v1
                                                            ]
                                                ]);
                                                if ($keyvalue != false) {
                                                    $attrlist[$num]['valueList']['自定义'] = $this->setValueList(0, $keyvalue->value, '', '0', '');
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                if (is_array($arr['valueList']) && count($arr['valueList']) > 0 && is_string($v)) {
                                    if (strpos($v, '自定义') !== false) {
                                        $vArr = explode('|', $v);
                                        $attrlist[$num]['valueList']['自定义'] = $this->setValueList(0, isset($vArr[1]) && !empty($vArr[1]) ? $vArr[1] : '', '', '0', '');
                                    } else {
                                        foreach ($arr['valueList'] as $k1 => $v1) {
                                            if ($v == $v1['lineAttrvalNameCn']) {
                                                $attrlist[$num]['valueList'][] = $this->setValueList($v1['attrValId'], $v1['lineAttrvalName'], $v1['lineAttrvalNameCn'], $v1['iscustomsized'], $v1['picUrl']);
                                                unset($productInfo[$key]['valueList'][$k1]);
                                                unset($arr['valueList'][$k1]);
                                            }
                                        }
                                        if (empty($attrlist[$num]['valueList'])) {
                                            $keyvalue = \Keyvalue::findFirst([
                                                        'conditions' => 'valuecn=:valuecn:',
                                                        'bind' => [
                                                            'valuecn' => $v
                                                        ]
                                            ]);
                                            if ($keyvalue != false) {
                                                $attrlist[$num]['valueList']['自定义'] = $this->setValueList(0, $keyvalue->value, '', '0', '');
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    if ($arr['lineAttrName'] == $k) {
                        if (is_array($arr['valueList']) && count($arr['valueList']) > 0 && is_string($v)) {
                            foreach ($arr['valueList'] as $k1 => $v1) {
                                if (strtolower(trim($v1['lineAttrvalName'])) == strtolower(trim($v))) {
                                    $attrlist[$num]['valueList'][] = $this->setValueList($v1['attrValId'], $v1['lineAttrvalName'], $v1['lineAttrvalNameCn'], $v1['iscustomsized'], $v1['picUrl']);
                                    unset($productInfo[$key]['valueList'][$k1]);
                                    unset($arr['valueList'][$k1]);
                                }
                            }
                        }
                    }
                }
            }
            if ($key == '品牌') {
                $attrlist[$num]['valueList'][] = $this->setValueList(0, '', '', '0', '');
            }
            if ($key == '表面直径') {
                $attrlist[$num]['valueList'] = [$this->setValueList(null, str_replace('m', '', $sourceData['表面直径']), '', '0', '')];
            }
            if (count($attrlist[$num]['valueList']) > 1 || $arr['isother'] == '0') {
                if (isset($attrlist[$num]['valueList']['自定义'])) {
                    unset($attrlist[$num]['valueList']['自定义']);
                }
            }
            if (count($attrlist[$num]['valueList']) == 0 && $arr['required'] != '0' && $arr['isother'] == '0') {
                $attrlist[$num]['valueList'][] = $this->setValueList($arr['valueList'][0]['attrValId'], $arr['valueList'][0]['lineAttrvalName'], $arr['valueList'][0]['lineAttrvalNameCn'], $arr['valueList'][0]['iscustomsized'], $arr['valueList'][0]['picUrl']);
            }
            if (empty($attrlist[$num]['valueList']) && $arr['required'] != '0' && $arr['isother'] == '1') {
                $attrlist[$num]['valueList'][] = $this->setValueList(0, 'other', '', '0', '');
            }
            if ($arr['required'] != '0' && empty($attrlist[$num]['valueList'])) {
                $this->echoJson(['status' => 'error', 'msg' => $arr['lineAttrNameCn'] . $arr['lineAttrName'] . '该属性不可为空']);
            }
            if (count($attrlist[$num]['valueList']) == 0) {
                unset($attrlist[$num]);
                continue;
            }
            $attrlist[$num]['valueList'] = array_values($attrlist[$num]['valueList']);
            $l = count($attrlist[$num]['valueList']);
            if ($arr['type'] == '2' && $l > 0) {
                $attrlist[$num]['valueList'] = array_slice($attrlist[$num]['valueList'], 0, 1);
                $data['c_' . $arr['attrId'] . '_vname'] = (string) $attrlist[$num]['valueList'][0]['attrValId'];
            } else if ($arr['type'] == '1' && $l > 0) {
                $data['c_' . $arr['attrId'] . '_vname'] = $arr['attrId'] . '_' . $attrlist[$num]['valueList'][$l - 1]['attrValId'];
            } else {
                $data['c_' . $arr['attrId'] . '_vname'] = '';
            }

            if ($arr['lineAttrName'] == 'Compatible') {//Compatible
                $num++;
                $attrlist[$num] = [
                    'class' => 'com.dhgate.syi.model.ProductAttributeVO',
                    'attrId' => '1005500',
                    'attrName' => 'Compatiale Model',
                    'isbrand' => '0',
                    'valueList' => [$this->setValueList(0, 'other', '', '0', '')],
                ];
            }
            $num++;
        }
    }

    private function imglist(&$data, $sourceData) {
        $imglistData = [];
        $waterMark = [];
        $sourceData['产品图片'] = array_values($sourceData['产品图片']);
        $imgs = array_slice($sourceData['产品图片'], 0, 8);
        foreach ($imgs as $key => $img) {
            $imgData = $this->imgIntoDh($img, $data['imgtoken'], $data['supplierid']);
            if ($imgData['result'] != '1' && $imgData['result'] != 2) {
                return false;
            }
            if ($key == 0) {
                $data['inp_imgurl'] = $imgData['l_imgurl'];
                $data['inp_imgmd5'] = $imgData['l_imgmd5'];
            }
            $imglistData[] = [
                'class' => 'com.dhgate.syi.model.TdProductAttachVO',
                'fileurl' => $imgData['l_imgurl'],
                'imgmd5' => $imgData['l_imgmd5'],
                'sequence' => (string) ($key - 1),
                'filename' => ''
            ];
        }
        $data['waterMark'] = ''; //json_encode($waterMark, JSON_UNESCAPED_UNICODE);
        $data['imglist'] = json_encode($imglistData, JSON_UNESCAPED_UNICODE);
        return true;
    }

    private function saleTp(&$data) {
        $dom = new HtmlDom();
        for ($i = 0; $i < 3; $i++) {
            $getSaleTemplateList = json_decode(MyCurl::get('http://seller.dhgate.com/syi/getSaleTemplateList.do', $this->cookie), true);
            if (isset($getSaleTemplateList['data']) && count($getSaleTemplateList['data']) > 0) {
                $getSaleTemplateList = array_column($getSaleTemplateList['data'], null, 'name');
                break;
            }
        }
        $data['saleTemplateName'] = 'LK'; //售后模板选择
        $data['saleTemplateId'] = $getSaleTemplateList['LK']['templateId'];
        $post_data = [
            'width' => 0,
            'height' => 0,
            'length' => 0,
            'grossweight' => 0,
            'quantity' => 1,
            'country' => '',
            'shippingModelId' => $data['shippingmodelid'],
            'packageStatus' => 0,
            'status' => 0
        ];

        for ($i = 0; $i < 3; $i++) {
            $getTempTableContents = MyCurl::post('http://seller.dhgate.com/syi/getTempTable.do?isblank=true', $post_data, $this->cookie, null, 60);
            $html1 = $dom->load($getTempTableContents);
            if (!empty($html1->find('#shippingScore'))) {
                $data['shippingScore'] = $html1->find('#shippingScore', 0)->value;
                $data['isPostAriMail'] = $html1->find('#isPostAriMail', 0)->value;
                break;
            }
        }
        $html1->clear();
    }

    private function skuAttr(&$attrlist, $id, &$productInfo, $skuValueList, $key = '颜色') {
        if (isset($productInfo[$key]['is_add']) && $productInfo[$key]['is_add'] == '1') {
            return;
        }
        $attrlist[] = [
            'class' => 'com.dhgate.syi.model.ProductAttributeVO',
            'attrId' => $id,
            'attrName' => $productInfo[$key]['lineAttrName'],
            'isbrand' => $productInfo[$key]['isbrand'],
            'valueList' => array_values($skuValueList)
        ];
        unset($productInfo[$key]);
    }

    private function color($colorSizeArr, $colorsList) {
        $ysList = array_unique(array_column($colorSizeArr, '颜色'));
        $cList = [];
        foreach ($ysList as $k => $v) {
            if (strpos($v, '自定义') !== false) {
                $name = str_replace('自定义|', '', $v);
                $cList[$v] = [
                    'attrValCode' => '',
                    'attrValId' => '0',
                    'catePubAttrId' => '40335',
                    'catePubAttrvalId' => '286334',
                    'iscustomsized' => "0",
                    'lineAttrvalName' => $name,
                    'lineAttrvalNameCn' => "",
                    'picUrl' => '',
                    'sortval' => '217'
                ];
                continue;
            }
            if (empty($v)) {
                continue;
            }
            $ispipei = 0;
            if (isset($colorsList[$v])) {
                $cList[$v] = $colorsList[$v];
                $ispipei = 1;
                unset($colorsList[$v]);
            } else {
                foreach ($colorsList as $key => $value) {
                    if (strpos($key, $v) !== false || strpos($v, $key) !== false) {
                        $value['lineAttrvalName'] = isset($colorSizeArr[$k]['颜色orign']) ? $colorSizeArr[$k]['颜色orign'] : $v;
                        $cList[$v] = $value;
                        $ispipei = 1;
                        unset($colorsList[$key]);
                        break;
                    }
                }
                if ($ispipei == 0) {
                    foreach ($colorsList as $key => $value) {
                        if (!in_array($key, $ysList)) {
                            $value['lineAttrvalName'] = isset($colorSizeArr[$k]['颜色orign']) ? $colorSizeArr[$k]['颜色orign'] : $v;
                            $cList[$v] = $value;
                            $ispipei = 1;
                            unset($colorsList[$key]);
                            break;
                        }
                    }
                }
            }
        }
        return $cList;
    }

    private function skuList($productInfo, $colorSizeArr, &$id, $keyCn = '尺码') {
        if (!isset($productInfo[$keyCn]['valueList']) || empty($productInfo[$keyCn]['valueList'])) {
            return [];
        }
        $skuList = [];
        foreach ($productInfo[$keyCn]['valueList'] as $v) {
            $skuList[$v['lineAttrvalName']] = $v;
        }
        if (empty($skuList)) {
            return [];
        }
        $cmList = array_unique(array_column($colorSizeArr, $keyCn));
        $list = [];
        foreach ($cmList as $v) {
            if (strpos($v, '自定义') !== false) {
                $name = str_replace('自定义|', '', $v);
                $list[$v] = [
                    'attrValCode' => '',
                    'attrValId' => '0',
                    'catePubAttrId' => '',
                    'catePubAttrvalId' => '',
                    'iscustomsized' => "0",
                    'lineAttrvalName' => $name,
                    'lineAttrvalNameCn' => "",
                    'picUrl' => '',
                    'sortval' => ''
                ];
                continue;
            }
            if (empty($v)) {
                continue;
            }
            $ispipei = 0;
            if (isset($skuList[$v]) || isset($skuList['US' . $v])) {
                $list[$v] = isset($skuList[$v]) ? $skuList[$v] : $skuList['US' . $v];
                $ispipei = 1;
                unset($skuList[$v]);
            } else {
                foreach ($skuList as $key => $value) {
                    if (strpos($key, $v) !== false || strpos($v, $key) !== false) {
                        $list[$v] = $value;
                        $ispipei = 1;
                        unset($skuList[$key]);
                        break;
                    }
                }
                if ($ispipei == 0) {
                    foreach ($skuList as $key => $value) {
                        if (!in_array($key, $cmList)) {
                            $value['lineAttrvalName'] = $v;
                            $list[$v] = $value;
                            unset($skuList[$key]);
                            break;
                        }
                    }
                }
            }
        }
        $list = array_filter($list);
        if (isset($productInfo[$keyCn]['is_add']) && $productInfo[$keyCn]['is_add'] == '1' && count($list) == 0) {
            $id = '';
        }
        if (isset($productInfo[$keyCn]['buyAttr']) && $productInfo[$keyCn]['buyAttr'] != '1' && count($list) == 0) {
            $id = '';
        }
        return $list;
    }

    private function pubData(&$data, $sourceData, $html) {
        foreach ($html->find('form input') as $input) {
            $key = $input->name;
            if (isset($data[$key])) {
                $data[$key] = $input->value;
            }
        }
        $suppTemplates = json_decode(MyCurl::get('http://seller.dhgate.com/syi/getSuppTemplates.do', $this->cookie), true);
        if (isset($suppTemplates['data']) && count($suppTemplates['data']) > 0) {
            $suppTemplates = array_column($suppTemplates['data'], null, 'modelname');
        } else {
            $suppTemplates = [];
        }
        $getRelModelPage = json_decode(MyCurl::get('http://seller.dhgate.com/prodmanage/relModel/getRelModelPage.do?pageNum=1&pageSize=10', $this->cookie, $this->header), true);
        $modelHtml = '';
        if (isset($getRelModelPage['relModelRespList']) && count($getRelModelPage['relModelRespList']) > 0) {
            $num = 0;
            $modelHtml .= '<p>';
            foreach ($getRelModelPage['relModelRespList'] as $v) {
                $modelHtml .= '<img data-relateproduct="' . $v['relModelId'] . '" style="cursor:pointer;" class="j-relatedproduct" src="//css.dhresource.com/seller/product/relatedproducts/image/productmodel001.png" />';
                $num++;
                if ($num > 2) {
                    break;
                }
            }
            $modelHtml .= '</p>';
        }
        $data['productname'] = mb_substr(htmlspecialchars_decode($sourceData['产品标题']), 0, 140, 'utf-8');
        $data['forEditOldCatePubid'] = $data['catepubid'];
        $data['brandid'] = '99';
        $data['brandName'] = '无品牌';
        $data['elm'] = $modelHtml . @file_get_contents(PUL_PATH . $sourceData['产品介绍']);
        $data['productdesc'] = 'Color may be a little different due to monitor. Pictures are only samples for reference. Due to limitations in photography and the inevitable differences in monitor settings'; //$sourceData['描述']; //
        $data['inventoryStatus'] = '0'; //是否有备货  1、0
        $data['inventory'] = '100';            //有备货用限制最大购买
        $data['inventoryLocation'] = 'CN';
        $data['sizelen'] = '30.0'; //$sourceData['长'];
        $data['sizewidth'] = '20.0'; //$sourceData['宽'];
        $data['sizeheight'] = '10.0'; //$sourceData['高'];
        $data['productweight'] = isset($sourceData['重量']) ? (is_array($sourceData['重量']) ? $sourceData['重量'][0] : $sourceData['重量']) : 0.5;
        $data['setdiscounttype'] = '1'; //统一设置价格 ：2  分别设置：1
        $data['noSpecPrice'] = '200';         //?????'
        $data['packquantity'] = '1';  //???
        $data['specselfDef'] = '[]';
        $data['sortby'] = '1'; //销售方式  1、件  2、箱
        $data['shippingmodelname'] = '标准运费模板';
        $data['shippingmodelid'] = $suppTemplates['标准运费模板']['shippingmodelid'];
        $data['isLeadingtime'] = 'on'; //是否要备货
        $data['productInventorylist'] = '[{"class":"com.dhgate.syi.model.ProductInventoryVO","productInventoryId":"","quantity":231,"hasBuyAttr":"0","hasSaleAttr":"0","commonSpec":"0","supplierid":""}]';
//        if (isset($sourceData['适用']) && is_array($sourceData['适用']) && in_array('成人', $sourceData['适用'])) {
//            $data['issample_adult'] = '2';
//        }
//        if (isset($sourceData['适用']) && is_array($sourceData['适用']) && in_array('非成人', $sourceData['适用'])) {
//            $data['issample_adult'] = '3';
//        }
        $data['issample_adult'] = '3'; //默认非成人
        $data['vaildday'] = '30'; //产品有效期
        $data['selectSzTemplateType'] = '0';
        $data['s_albums_winid'] = $html->find('#s_albums_winid option', 0)->value;
        $data['puw_albums_winid'] = $html->find('#puw_albums_winid option', 0)->value;
        $data['maxSaleQty'] = '10000';
        $data['startqty'] = '2';
        $data['discount'] = '3';
        $data['prospeclist'] = '[]';
        $data['cmSzTableJson'] = '';
        $data['cmszAdviseTableJson'] = '';
    }

    private function group(&$data, $content) {
        $g_category = CommonFun::getJsJson($content, 'g_category');
        $groupdata = CommonFun::getJsJson($content, 'GROUP_LIST');
        $group = [];
        if (isset($groupdata['data']['result']) && count($groupdata['data']['result']) > 0) {
            $group = array_column($groupdata['data']['result'], null, 'groupName');
        }
        if (isset($group[$g_category['pubNameCn']])) {
            $data['productgroupid'] = $group[$g_category['pubNameCn']]['groupId'];
        } else {
            foreach ($group as $key => $value) {
                if (strpos($g_category['pubNameCn'], $key) !== false || strpos($key, $g_category['pubNameCn']) !== false) {
                    $data['productgroupid'] = $value['groupId'];
                }
            }
        }
    }

    private function units(&$data, $sourceData, $html) {
        $units = [];
        foreach ($html->find('select[name=measureid] option') as $op) {
            if (!empty($op->value)) {
                $text = $op->plaintext;
                preg_match('/[A-Za-z]+/', $text, $arr);
                $key = strtolower($arr[0]);
                $units[$key] = $op->value;
            }
        }
        $data['measureid'] = isset($sourceData['计量单位']) && isset($units[$sourceData['计量单位']]) ? $units[$sourceData['计量单位']] : '00000000000000000000000000000003'; //包
    }

    private function keyWords(&$data, $sourceData) {
        $keywordNum = 1;
        $data['keyword1'] = false;
        $data['keyword2'] = false;
        $data['keyword3'] = false;
        foreach ($sourceData['关键词'] as $k => $v) {
            $v = trim($v);
            if (mb_strlen($v, 'utf-8') <= 40) {
                $data['keyword' . $keywordNum] = $v;
                $keywordNum++;
                if ($keywordNum > 3) {
                    break;
                }
            }
        }
    }

    private function setValueList($attrValId, $lineAttrvalName, $lineAttrvalNameCn, $iscustomsized, $picUrl, $brandValId = '') {
        return [
            'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
            'attrValId' => $attrValId,
            'lineAttrvalName' => $lineAttrvalName,
            'lineAttrvalNameCn' => '', //$lineAttrvalNameCn,
            'iscustomsized' => $iscustomsized,
            'picUrl' => empty($picUrl) ? '' : $picUrl,
            'brandValId' => $brandValId
        ];
    }

    public function imgIntoDh($url, $token, $supplierid) {
        for ($i = 0; $i < 3; $i++) {
            $filename = ImgFun::downLoad($url, $this->header);
            $path = PUL_PATH . 'img/' . $filename;
            if (file_exists($path) && filesize($path) > 0) {
                break;
            }
        }
        $img = \Imgs::findFirst([
                    'conditions' => 'filename=:filename: and username=:username:',
                    'bind' => [
                        'filename' => $filename,
                        'username' => $this->username
                    ],
                    'columns' => 'img_data'
        ]);
        if ($img != false) {
            $img_data = json_decode($img->img_data, true);
            if (isset($img_data['result']) && ($img_data['result'] == 1 || $img_data['result'] == 2)) {
                return $img_data;
            }
        }
        for ($i = 0; $i < 3; $i++) {
            $data = ImgFun::upload($path, $token, $supplierid, $this->header);
            if (isset($data['result']) && $data['result'] > 0) {
                break;
            }
        }
        if (!isset($data['result'])) {
            return ['result' => 0];
        }
        \Imgs::createOne($url, $filename, $path, json_encode($data, JSON_UNESCAPED_UNICODE), $this->username);
        return $data;
    }

    public function categoriesExportJsonAction() {
        set_time_limit(0);
        $this->needLogin();
        $data = [];
        $this->getCategory('', $data);
        @file_put_contents(PUL_PATH . 'cate_words.json', json_encode($data, JSON_UNESCAPED_UNICODE));
        echo 'success';
        exit();
    }

    public function getCategory($id, &$data) {
        $curl = new MyCurl();
        $json = json_decode($curl->get('http://seller.dhgate.com/syi/categorybyid.do?isblank=true&catePubId=' . $id, $this->cookie), true);
        if (isset($json['data']) && !empty($json['data'])) {
            foreach ($json['data'] as $val) {
                $data[$val['catePubId']] = $val['pubNameCn'];
                $this->getCategory($val['catePubId'], $data);
            }
        }
    }

    public function t1Action() {
        $json = json_decode(file_get_contents(PUL_PATH . 'cate_words.json'), true);
        ksort($json, SORT_NUMERIC);
        $this->echoJson($json);
    }

    public function addProductAction() {
        $ids = $this->request->get('ids');
        $content = $this->request->get('content', 'string');
        $isSave = $this->request->get('isSave', 'int', 0);
        $productgroupid = isset($_COOKIE['myproductgroupid']) ? $_COOKIE['myproductgroupid'] : '';
        if (empty($ids)) {
            $this->echoJson(['status' => 'error', 'msg' => '添加产品数为空']);
        }
        $sNum = 0;
        $eNum = 0;
        foreach ($ids as $v) {
            $queue = new \Queue();
            $queue->queue_url = MY_DOMAIN . '/product/draft?id=' . $v . '&isSave=' . $isSave . '&current_user=' . $this->username . '&productgroupid=' . $productgroupid;
            $queue->status = 0;
            $queue->createtime = date('Y-m-d H:i:s');
            $queue->contents = $content;
            $ret = $queue->save();
            if ($ret == false) {
                $eNum++;
            } else {
                $this->db->execute('update product set status=4 where id=' . $v);
                $sNum++;
            }
        }
//        $this->db->execute('update product set status=4 where id in (' . implode(',', $ids) . ')');
        $msg = '添加' . $sNum . '条队列成功,' . $eNum . '失败';
        $this->echoJson(['status' => 'success', 'msg' => $msg]);
    }

    public function getCate($catePubId, &$list) {
        $list[] = $catePubId;
        $json = json_decode(file_get_contents(MY_DOMAIN . '/product/getCateAttr?catePubId=' . $catePubId), true);
        foreach ($json['data'] as $v) {
            if ($v['leaf'] == '0') {
                $this->getCate($v['catePubId'], $list);
            } else {
                $list[] = $v['catePubId'];
            }
        }
    }

    public function delProductsAction() {
        $ids = $this->request->get('ids');
        if (empty($ids)) {
            $this->echoJson(['status' => 'error', 'msg' => '删除产品数为空']);
        }
        $ret = $this->db->execute('delete from product where id in (' . implode(',', $ids) . ')');
        if ($ret == true) {
            $this->echoJson(['status' => 'success', 'msg' => '删除成功']);
        } else {
            $this->echoJson(['status' => 'error', 'msg' => '删除失败']);
        }
    }

    public $errorSkus = [
        'productattr' => [
            'name' => "产品属性",
        ],
        'productname' => [
            'name' => "产品标题",
        ],
        'keyword1' => [
            'name' => "英文产品关键词",
        ],
        'keyword2' => [
            'name' => "英文产品关键词",
        ],
        'keyword3' => [
            'name' => "英文产品关键词",
        ],
        'div_imgcontainer' => [
            'name' => "产品图片",
        ],
        'selectCategoryInput' => [
            'name' => "产品类目",
        ],
        'productdesc' => [
            'name' => "产品简短描述",
        ],
        'featureshtml' => [
            'name' => "产品详细描述",
        ],
        'measureid' => [
            'name' => "销售产品计量单位",
        ],
        'productweight' => [
            'name' => "产品包装后重量",
        ],
        'baseqt' => [
            'name' => "产品计重阶梯数量",
        ],
        'stepqt' => [
            'name' => "产品计重阶梯的增加数量",
        ],
        'stepweight' => [
            'name' => "产品计重阶梯的增加重量",
        ],
        'sizelen' => [
            'name' => "产品包装后长度",
        ],
        'sizewidth' => [
            'name' => "产品包装后宽度",
        ],
        'sizeheight' => [
            'name' => "产品包装后高度",
        ],
        'productprice' => [
            'name' => "产品价格",
        ],
        'productinventory' => [
            'name' => "产品备货信息",
        ],
        'shippingmodelid' => [
            'name' => "运费设置",
        ],
        'promisehtml' => [
            'name' => "服务承诺",
        ],
        'others' => [
            'name' => "其他问题",
        ],
        'googleshopping' => [
            'name' => "站内外推广图片",
        ],
        'validatecode' => [
            'name' => "验证码",
        ],
        'saleinfo' => [
            'name' => "产品销售信息",
        ],
        'saletemplate' => [
            'name' => "售后服务模板",
        ],
        'sellerSzTemplate' => [
            'name' => "尺码模板",
        ]
    ];
    public $errorsArr = [
        "error.empty.productproductname2" => "productname",
        "error.empty.productproductname3" => "productname",
        "error.empty.productproductname4" => "productname",
        "error.empty.productproductname5" => "productname",
        "error.empty.productproductname6" => "productname",
        "error.empty.productproductname7" => "productname",
        "error.empty.productproductname8" => "productname",
        "error.empty.productproductnamelen" => "productname",
        "error.empty.productproductnamechinese" => "productname",
        "error.productentry.productnameekeywords" => "productname",
        "error.productentry.productnameikeywords" => "productname",
        "error.productentry.productnamerepeat" => "productname",
        "error.prodbase.keywords.scripterror" => "productname",
        "error.product.isstate.del" => "productname",
        "error.empty.productpicurl7" => "div_imgcontainer",
        "errors.product.userindo_img" => "div_imgcontainer",
        "errors.product.userindo_img8" => "div_imgcontainer",
        "errors.product.userindo_img6" => "div_imgcontainer",
        "error.empty.picurl1" => "div_imgcontainer",
        "error.empty.productpromisehtmlempty" => "promisehtml",
        "error.empty.productpromisehtmlsize" => "promisehtml",
        "error.empty.productpromisehtmlchinese" => "promisehtml",
        "error.empty.productpromisehtmlupload" => "promisehtml",
        "error.empty.productfeatureshtml6" => "featureshtml",
        "error.empty.productfeatureshtmlupload" => "featureshtml",
        "error.empty.error.html5000" => "featureshtml",
        "error.empty.productpricetypechinese" => "productprice",
        "error.empty.productsupplierprice15" => "productprice",
        "error.empty.productsupplierprice10" => "productprice",
        "error.empty.productsupplierprice9" => "productprice",
        "error.empty.productsupplierprice8" => "productprice",
        "error.empty.productsupplierprice" => "productprice",
        "error.empty.productpostday" => "productprice",
        "error.empty.productprice" => "productprice",
        "error.empty.productsample" => "productprice",
        "error.empty.productsample2" => "productprice",
        "error.empty.productsample4" => "productprice",
        "error.empty.productsample5" => "productprice",
        "error.empty.productsample6" => "productprice",
        "error.empty.productsample7" => "productprice",
        "error.empty.productvaildday" => "productprice",
        "error.empty.productvaildday" => "productprice",
        "error.productentry.emptyattrname" => "productattr",
        "error.productentry.emptyattrval" => "productattr",
        "error.productentry.onlyhaveattrnoval" => "productattr",
        "error.productentry.existemptyattrval" => "productattr",
        "error.productentry.outlimit.proattrvalcount" => "productattr",
        "error.productentry.lengthoutlimit" => "productattr",
        "error.productentry.nonenglish" => "productattr",
        "error.productentry.brandkeyword" => "productattr",
        "error.productentry.illegalproductattr" => "productattr",
        "error.productentry.attrempty" => "productattr",
        "error.productentry.illegalbrand" => "productattr",
        "error.productentry.repeat.productattrval" => "productattr",
        "error.productentry.nonnumric" => "productattr",
        "error.productentry.attrvalidcorrect" => "productattr",
        "error.productentry.lengthoutlimit.selfdefined" => "productattr",
        "error.productentry.picurllengthoutlimit" => "productattr",
        "error.productentry.emptyotherval" => "productattr",
        "error.productentry.nonenglish.selfdefined" => "productattr",
        "error.match.sku.attr" => "productattr",
        "error.productentry.scriptattrname" => "productattr",
        "error.productentry.scripterror" => "productattr",
        "error.productentry.scriptattrval" => "productattr",
        "error.emptyValName.scriptSpecSelf" => "productattr",
        "error.productentry.scripterror.selfdefined" => "productattr",
        "error.productentry.attr.attrvalname.exists" => "productattr",
        "empty.Compatible.category" => "productattr",
        "empty.Compatible.category.detail" => "productattr",
        "empty.Compatible.category.detail.attrId" => "productattr",
        "empty.Compatible.category.detail.attrValId" => "productattr",
        "error.empty.category" => "selectCategoryInput",
        "error.category.none" => "selectCategoryInput",
        "error.empty.productcatalogid6" => "selectCategoryInput",
        "error.empty.productcatalogid7" => "selectCategoryInput",
        "label.productedit.mustinputpackageweiht" => "measureid",
        "label.productedit.mustimputpackagelength" => "measureid",
        "label.productedit.mustimputpackagewidth" => "measureid",
        "label.productedit.mustimputpackageheight" => "measureid",
        "error.empty.shipset1" => "shippingmodelid",
        "error.empty.shipset2" => "shippingmodelid",
        "error.empty.shipset3" => "shippingmodelid",
        "error.empty.shipset4" => "shippingmodelid",
        "error.empty.shipset5" => "shippingmodelid",
        "error.empty.shipset6" => "shippingmodelid",
        "error.empty.shipset11" => "shippingmodelid",
        "error.empty.shipset12" => "shippingmodelid",
        "error.empty.productproductdesc3" => "productdesc",
        "error.empty.productproductdesc4" => "productdesc",
        "error.empty.productproductdesc5" => "productdesc",
        "error.productentry.productdesckeywords" => "productdesc",
        "error.product.googleshopping.required" => "googleshopping",
        "error.googleshopping.update" => "googleshopping",
        "error.product.verifycode" => "validatecode",
        "error.product.InventoryLocation.sku.saleStatus" => "saleinfo",
        "error.product.setsample" => "saleinfo",
        "error.saletemplate.empty" => "saletemplate",
        "error.saletemplate.required" => "saletemplate",
        "error.inventory.category" => "saleinfo",
        "error.empty.sku.inventory" => "saleinfo",
        "error.productentry.syi.blackerror" => "others",
        "error.empty.cmtablejson.sztemplate" => "sellerSzTemplate",
        "error.create.sztemplate" => "sellerSzTemplate",
        "error.szRequired.szTemplate" => "sellerSzTemplate",
        "stop.status.class.szseller" => "sellerSzTemplate",
        "error.author.prodnum.supplier" => "selectCategoryInput",
        "error.author.category.supplier" => "selectCategoryInput"
    ];

}
