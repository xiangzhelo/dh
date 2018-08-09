<?php

namespace Dh\Controllers;

use Lib\Vendor\CommonFun;
use Lib\Vendor\MyCurl;
use Lib\Vendor\HtmlDom;
use Lib\Vendor\ImgFun;

class ProductController extends ControllerBase {

    public $cookie;
    public $supplierid;
    public $username = 'lakeone';
    public $password = 'lk123456';

    public function needLogin() {
        $hasLogin = $this->hasLogin($this->username);
        if ($hasLogin == false) {
            $this->loginDh($this->username, $this->password);
        }
        $this->cookie = $this->getUserCookie($this->username);
        $this->supplierid = CommonFun::getCookieValueByKey($this->cookie, 'supplierid');
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
        $pages = \Product::getPage($page, $size, $status, $cateIds);
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
            echo file_get_contents($file);
            exit();
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
            return json_decode(file_get_contents($file), true);
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
                                'conditions' => 'key=:key: and value=:value:',
                                'bind' => [
                                    'key' => strtolower($v1['lineAttrName']),
                                    'value' => strtolower($v2['lineAttrvalName'])
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
                            file_get_contents('http://www.dh.com/lexicon/update?id=' . $words->id . '&dest_words=' . $dest_words);
                        }
                    }
                }
            }
        }
    }

    public function draftAction() {
        $this->needLogin();
        $id = $this->request->get('id', 'int');
        $model = \Product::findFirst($id);
        if ($model == false || empty($model->product_data)) {
            $this->echoJson(['status' => 'error', 'msg' => '该数据未抓取']);
        }
        if (!empty($model->dh_product_id)) {
            $url = 'http://seller.dhgate.com/syi/edit.do?prodDraftId=' . $model->dh_product_id . '&inp_catepubid=' . $model->dh_category_id . '&isdraftbox=1';
            $drafUrl = 'http://seller.dhgate.com/syi/ajaxSavedraftboxV2.do?isblank=true&prodDraftId=' . $model->dh_product_id;
        } else {
            $url = 'http://seller.dhgate.com/syi/edit.do?inp_catepubid=' . $model->dh_category_id;
            $drafUrl = 'http://seller.dhgate.com/syi/ajaxSavedraftboxV2.do?isblank=true&prodDraftId=';
        }
        $productInfo = array_column($this->getCateAttrL($model->dh_category_id)['data']['attributeList'], null, 'lineAttrNameCn');
        $sourceData = json_decode($model->tran_product_data, true);
        $suppTemplates = json_decode(MyCurl::get('http://seller.dhgate.com/syi/getSuppTemplates.do', $this->cookie), true);
        if (isset($suppTemplates['data']) && count($suppTemplates['data']) > 0) {
            $suppTemplates = array_column($suppTemplates['data'], null, 'modelname');
        } else {
            $suppTemplates = [];
        }
        $data = json_decode(file_get_contents(PUL_PATH . 'catepub_json/141001.json'), true);
        $editContent = MyCurl::get($url, $this->cookie);
        $dom = new HtmlDom();
        $html = $dom->load($editContent);
        foreach ($html->find('form input') as $input) {
            $key = $input->name;
            if (isset($data[$key])) {
                $data[$key] = $input->value;
            }
        }
        $data['productname'] = mb_substr(htmlspecialchars_decode($sourceData['产品标题']), 0, 140, 'utf-8');
        $data['forEditOldCatePubid'] = $data['catepubid'];
        $keywordNum = 1;
        $data['keyword1'] = false;
        $data['keyword2'] = false;
        $data['keyword3'] = false;
        foreach ($sourceData['关键词'] as $k => $v) {
            $v = trim($v);
            if (mb_strlen($v, 'utf-8') <= 40) {
                $data['keyword' . $keywordNum] = $v;
                $keywordNum++;
                if ($keywordNum > 2) {
                    break;
                }
            }
        }
        $data['brandid'] = '99';
        $data['brandName'] = '无品牌';
        $data['elm'] = @file_get_contents(PUL_PATH . $sourceData['产品介绍']);
        $data['productdesc'] = 'Color may be a little different due to monitor. Pictures are only samples for reference. Due to limitations in photography and the inevitable differences in monitor settings'; //$sourceData['描述']; //
        $data['inventoryStatus'] = '0'; //是否有备货  1、0
        $data['inventory'] = '100';            //有备货用限制最大购买
        $data['inventoryLocation'] = 'CN';
        $data['sizelen'] = 30; //$sourceData['长'];
        $data['sizewidth'] = 20; //$sourceData['宽'];
        $data['sizeheight'] = 10; //$sourceData['高'];
        $data['productweight'] = $sourceData['重量'];
        $data['setdiscounttype'] = 1; //统一设置价格 ：2  分别设置：1
        $data['noSpecPrice'] = '11';         //?????'
        $data['packquantity'] = '1';  //???
        $data['specselfDef'] = '[]';
        if ($data['setdiscounttype'] == '1') {
            $data['discountRange'] = json_encode([['startqty' => '1', 'discount' => '0'], ['startqty' => '2', 'discount' => '97']]); //$sourceData['折扣']
        } else {
            $data['discountRange'] = json_encode([['startqty' => '1', 'discount' => $sourceData['特价']], ['startqty' => '2', 'discount' => $sourceData['特价']]]);
        }
        $data['measureid'] = '00000000000000000000000000000017'; //鞋子是按双  pair
        $data['sortby'] = '1'; //销售方式  1、件  2、箱
        $data['shippingmodelname'] = '标准运费模板';
        $data['shippingmodelid'] = $suppTemplates['标准运费模板']['shippingmodelid'];
        $data['isLeadingtime'] = 'on'; //是否要备货
        $data['productInventorylist'] = '[{"class":"com.dhgate.syi.model.ProductInventoryVO","productInventoryId":"","quantity":231,"hasBuyAttr":"0","hasSaleAttr":"0","commonSpec":"0","supplierid":""}]';
        $Color_Id = $productInfo['颜色']['attrId'];
        $Size_Id = $productInfo['尺码']['attrId'];
        $colorsList = CommonFun::arrayColumns($productInfo['颜色']['valueList'], null, 'lineAttrvalName');
        $sizesList = array_column($productInfo['尺码']['valueList'], null, 'lineAttrvalName');
        $skuInfo = [
            [
                'class' => '###',
                'inventoryLocation' => $data['inventoryLocation'],
                'leadingTime' => 4, //备货期
                'skuInfoList' => []
            ]
        ];
        $colorValueList = [];
        $sizeValueList = [];
        if (isset($sourceData['适用']) && is_array($sourceData['适用']) && in_array('成人', $sourceData['适用'])) {
            $data['issample_adult'] = '2';
        }

        if (isset($sourceData['适用']) && is_array($sourceData['适用']) && in_array('非成人', $sourceData['适用'])) {
            $data['issample_adult'] = '3';
        }
        foreach ($sourceData['属性'] as $li) {
            if (isset($colorsList[$li['颜色']]) && isset($sizesList['US' . $li['尺码']])) {
                $skuInfo[0]['skuInfoList'][] = [
                    'class' => '#',
                    'status' => ($li['库存'] > 0 ? '1' : '0'),
                    'price' => $li['折扣价'],
                    'stock' => (string) $li['库存'],
                    'skuCode' => '',
                    'id' => $Color_Id . '_' . $colorsList[$li['颜色']]['attrValId'] . 'A' . $Size_Id . '_' . $sizesList['US' . $li['尺码']]['attrValId'],
                    'attrList' => [
                        [
                            'attrId' => (string) $Color_Id,
                            'attrVid' => (string) $colorsList[$li['颜色']]['attrValId'],
                            'type' => $productInfo['颜色']['type'],
                            'class' => '##'
                        ], [
                            'attrId' => (string) $Size_Id,
                            'attrVid' => (string) $sizesList['US' . $li['尺码']]['attrValId'],
                            'type' => $productInfo['尺码']['type'],
                            'class' => '##'
                        ]
                    ]
                ];
                if (!isset($colorValueList[$colorsList[$li['颜色']]['attrValId']])) {
                    if (!empty($li['图片'])) {
                        $imgData = $this->imgIntoDh($li['图片'], $data['imgtoken'], $data['supplierid']);
                    } else {
                        $imgData = ['l_imgurl' => ''];
                    }
                    $colorValueList[$colorsList[$li['颜色']]['attrValId']] = [
                        'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                        'attrValId' => $colorsList[$li['颜色']]['attrValId'],
                        'lineAttrvalName' => $colorsList[$li['颜色']]['lineAttrvalName'],
                        'lineAttrvalNameCn' => $colorsList[$li['颜色']]['lineAttrvalNameCn'],
                        'iscustomsized' => $colorsList[$li['颜色']]['iscustomsized'],
                        'picUrl' => $imgData['l_imgurl'],
                        'brandValId' => ''
                    ];
                }
                if (!isset($sizeValueList[$sizesList['US' . $li['尺码']]['attrValId']])) {
                    $sizeValueList[$sizesList['US' . $li['尺码']]['attrValId']] = [
                        'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                        'attrValId' => $sizesList['US' . $li['尺码']]['attrValId'],
                        'lineAttrvalName' => $sizesList['US' . $li['尺码']]['lineAttrvalName'],
                        'lineAttrvalNameCn' => $sizesList['US' . $li['尺码']]['lineAttrvalNameCn'],
                        'iscustomsized' => $sizesList['US' . $li['尺码']]['iscustomsized'],
                        'picUrl' => '', //$sizesList['US' . $li['尺码']]['picUrl'],
                        'brandValId' => ''
                    ];
                }
            }
        }
        $attrlist = [];
        $attrlist[] = [
            'class' => 'com.dhgate.syi.model.ProductAttributeVO',
            'attrId' => $Color_Id,
            'attrName' => $productInfo['颜色']['lineAttrName'],
            'isbrand' => $productInfo['颜色']['isbrand'],
            'valueList' => array_values($colorValueList),
        ];
        $attrlist[] = [
            'class' => 'com.dhgate.syi.model.ProductAttributeVO',
            'attrId' => $Size_Id,
            'attrName' => $productInfo['尺码']['lineAttrName'],
            'isbrand' => $productInfo['尺码']['isbrand'],
            'valueList' => array_values($sizeValueList)
        ];
        unset($productInfo['颜色']);
        unset($productInfo['尺码']);
        $num = 2;
        foreach ($productInfo as $key => $arr) {
            $isbreak = false;
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
                                            $attrlist[$num]['valueList'][] = [
                                                'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                                                'attrValId' => '0',
                                                'lineAttrvalName' => isset($vArr[1]) && !empty($vArr[1]) ? $vArr[1] : '',
                                                'lineAttrvalNameCn' => '',
                                                'iscustomsized' => '0',
                                                'picUrl' => '',
                                                'brandValId' => ''
                                            ];
                                            if ($arr['isother'] == '0') {
                                                $isbreak = true;
                                                break;
                                            }
                                        } else {
                                            foreach ($arr['valueList'] as $k2 => $v2) {
                                                if ($v1 == $v2['lineAttrvalNameCn']) {
                                                    $attrlist[$num]['valueList'][] = [
                                                        'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                                                        'attrValId' => $v2['attrValId'],
                                                        'lineAttrvalName' => $v2['lineAttrvalName'],
                                                        'lineAttrvalNameCn' => $v2['lineAttrvalNameCn'],
                                                        'iscustomsized' => $v2['iscustomsized'],
                                                        'picUrl' => $v2['picUrl'],
                                                        'brandValId' => ''
                                                    ];
                                                    unset($productInfo[$key]['valueList'][$k2]);
                                                    unset($arr['valueList'][$k2]);
                                                    if ($arr['isother'] == '0') {
                                                        $isbreak = true;
                                                        break;
                                                    }
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
                                                    $attrlist[$num]['valueList'][] = [
                                                        'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                                                        'attrValId' => '0',
                                                        'lineAttrvalName' => $keyvalue->value,
                                                        'lineAttrvalNameCn' => '',
                                                        'iscustomsized' => '0',
                                                        'picUrl' => '',
                                                        'brandValId' => ''
                                                    ];
                                                    if ($arr['isother'] == '0') {
                                                        $isbreak = true;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if ($isbreak == true) {
                                        break;
                                    }
                                }
                            } else {
                                if (is_array($arr['valueList']) && count($arr['valueList']) > 0 && is_string($v)) {
                                    if (strpos($v, '自定义') !== false) {
                                        $vArr = explode('|', $v);
                                        $attrlist[$num]['valueList'][] = [
                                            'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                                            'attrValId' => '0',
                                            'lineAttrvalName' => isset($vArr[1]) && !empty($vArr[1]) ? $vArr[1] : '',
                                            'lineAttrvalNameCn' => '',
                                            'iscustomsized' => '0',
                                            'picUrl' => '',
                                            'brandValId' => ''
                                        ];
                                        if ($arr['isother'] == '0') {
                                            $isbreak = true;
                                            break;
                                        }
                                    } else {
                                        foreach ($arr['valueList'] as $k1 => $v1) {
                                            if ($v == $v1['lineAttrvalNameCn']) {
                                                $attrlist[$num]['valueList'][] = [
                                                    'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                                                    'attrValId' => $v1['attrValId'],
                                                    'lineAttrvalName' => $v1['lineAttrvalName'],
                                                    'lineAttrvalNameCn' => $v1['lineAttrvalNameCn'],
                                                    'iscustomsized' => $v1['iscustomsized'],
                                                    'picUrl' => $v1['picUrl'],
                                                    'brandValId' => ''
                                                ];
                                                unset($productInfo[$key]['valueList'][$k1]);
                                                unset($arr['valueList'][$k1]);
                                                if ($arr['isother'] == '0') {
                                                    $isbreak = true;
                                                    break;
                                                }
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
                                                $attrlist[$num]['valueList'][] = [
                                                    'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                                                    'attrValId' => '0',
                                                    'lineAttrvalName' => $keyvalue->value,
                                                    'lineAttrvalNameCn' => '',
                                                    'iscustomsized' => '0',
                                                    'picUrl' => '',
                                                    'brandValId' => ''
                                                ];
                                                if ($arr['isother'] == '0') {
                                                    $isbreak = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ($isbreak == true) {
                        break;
                    }
                } else {
                    if ($arr['lineAttrName'] == $k) {
                        if (is_array($arr['valueList']) && count($arr['valueList']) > 0 && is_string($v)) {
                            foreach ($arr['valueList'] as $k1 => $v1) {
                                if (strtolower(trim($v1['lineAttrvalName'])) == strtolower(trim($v))) {
                                    $attrlist[$num]['valueList'][] = [
                                        'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                                        'attrValId' => $v1['attrValId'],
                                        'lineAttrvalName' => $v1['lineAttrvalName'],
                                        'lineAttrvalNameCn' => $v1['lineAttrvalNameCn'],
                                        'iscustomsized' => $v1['iscustomsized'],
                                        'picUrl' => $v1['picUrl'],
                                        'brandValId' => ''
                                    ];
                                    unset($productInfo[$key]['valueList'][$k1]);
                                    if ($arr['isother'] == '0') {
                                        $isbreak = true;
                                        break;
                                    }
                                }
                            }
                        }
                        if ($isbreak == true) {
                            break;
                        }
                    }
                }
            }
            if ($arr['required'] != '0' && empty($attrlist[$num]['valueList'])) {
                $this->echoJson(['status' => 'error', 'msg' => $arr['lineAttrNameCn'] . $arr['lineAttrName'] . '该属性不可为空']);
            }
            $num++;
        }

        $data['attrlist'] = json_encode($attrlist, JSON_UNESCAPED_UNICODE);
        $data['vaildday'] = 30; //产品有效期
        $data['proSkuInfo'] = json_encode($skuInfo, JSON_UNESCAPED_UNICODE);
        $data['selectSzTemplateType'] = '0';
        $szTemplateClassIdStr = $html->find('#szTemplateClassIdStr', 0)->value;
        $data['s_albums_winid'] = $html->find('#s_albums_winid option', 0)->value;
        $data['puw_albums_winid'] = $html->find('#puw_albums_winid option', 0)->value;
        $html->clear();
        $getSzSellerTemplate = json_decode(MyCurl::post('http://seller.dhgate.com/syi/getSzSellerTemplate.do', ['classIdStr' => $szTemplateClassIdStr], $this->cookie), true);
        if (isset($getSzSellerTemplate['data']) && count($getSzSellerTemplate['data']) > 0) {
            $getSzSellerTemplate = $getSzSellerTemplate['data']; //array_column($getSzSellerTemplate['data'], 'templateNameCn');
        } else {
            $getSzSellerTemplate = [];
        }
        if ((isset($sourceData['适用']) && is_array($sourceData['适用']) && in_array('women', $sourceData['适用'])) || in_array('女士', $sourceData)) {
            $template = 0; //'women shoe';
        } else {
            $template = 1; //'men shoe';
        }
        $data['sellerSzTemplateId'] = $getSzSellerTemplate[$template]['szId'];
        $data['sellerTemplateName'] = $template == 1 ? 'men show' : 'women shoe';
        $getSaleTemplateList = json_decode(MyCurl::get('http://seller.dhgate.com/syi/getSaleTemplateList.do', $this->cookie), true);
        if (isset($getSaleTemplateList['data']) && count($getSaleTemplateList['data']) > 0) {
            $getSaleTemplateList = array_column($getSaleTemplateList['data'], null, 'name');
        } else {
            $getSaleTemplateList = [];
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
        $getTempTableContents = MyCurl::post('http://seller.dhgate.com/syi/getTempTable.do?isblank=true', $post_data, $this->cookie);
        $html1 = $dom->load($getTempTableContents);
        $data['shippingScore'] = $html1->find('#shippingScore', 0)->value;
        $data['isPostAriMail'] = $html1->find('#isPostAriMail', 0)->value;
        $html1->clear();
        $imglistData = [];
        $waterMark = [];
        foreach ($sourceData['产品图片'] as $key => $img) {
            $imgData = $this->imgIntoDh($img, $data['imgtoken'], $data['supplierid']);
            $waterMark[] = [
                'url' => $imgData['l_imgurl'],
                'pos' => '5',
                'class' => 'com.dhgate.syi.model.PrintPostVO'
            ];
            if ($key == 0) {
                $data['inp_imgurl'] = $imgData['l_imgurl'];
                $data['inp_imgmd5'] = $imgData['l_imgmd5'];
            } else {
                $imglistData[] = [
                    'class' => 'com.dhgate.syi.model.TdProductAttachVO',
                    'fileurl' => $imgData['l_imgurl'],
                    'imgmd5' => $imgData['l_imgmd5'],
                    'sequence' => $key - 1,
                    'filename' => ''
                ];
            }
        }
        $data['waterMark'] = json_encode($waterMark, JSON_UNESCAPED_UNICODE);
        $data['imglist'] = json_encode($imglistData, JSON_UNESCAPED_UNICODE);
        $ret = MyCurl::post($drafUrl, $data, $this->cookie);
        $ret = json_decode($ret, true);
        if ($ret['code'] == '1000') {
            $model = \Product::findFirst($id);
            $model->dh_product_id = $ret['data'];
            $model->updatetime = date('Y-m-d H:i:s');
            $model->status = 2;
            $model->save();
            $this->echoJson(['status' => 'success', 'msg' => '保存成功', 'data' => ['dh_product_id' => $model->dh_product_id, 'dh_category_id' => $model->dh_category_id]]);
        } else {
            $this->echoJson(['status' => 'error', 'msg' => '保存失败']);
        }
        $this->echoJson($data);
    }

    public function imgIntoDh($url, $token, $supplierid) {
        $filename = ImgFun::downLoad($url);
        $img = \Imgs::findFirst([
                    'conditions' => 'filename=:filename:',
                    'bind' => [
                        'filename' => $filename
                    ],
                    'columns' => 'img_data'
        ]);
        if ($img != false) {
            $img_data = json_decode($img->img_data, true);
            if (isset($img_data['result'])) {
                return $img_data;
            }
        }
        $path = PUL_PATH . 'img/' . $filename;
        $data = ImgFun::upload($path, $token, $supplierid);
        if (!isset($data['result'])) {
            return [];
        }
        \Imgs::createOne($url, $filename, $path, json_encode($data, JSON_UNESCAPED_UNICODE));
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
        if (empty($ids)) {
            $this->echoJson(['status' => 'error', 'msg' => '添加产品数为空']);
        }
        $sNum = 0;
        $eNum = 0;
        foreach ($ids as $v) {
            $queue = new \Queue();
            $queue->queue_url = 'http://www.dh.com/product/draft?id=' . $v;
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
        $this->db->execute('update product set status=4 where id in (' . implode(',', $ids) . ')');
        $msg = '添加' . $sNum . '条队列成功,' . $eNum . '失败';
        $this->echoJson(['status' => 'success', 'msg' => $msg]);
    }

    public function getCate($catePubId, &$list) {
        $list[] = $catePubId;
        $json = json_decode(file_get_contents('http://www.dh.com/product/getCateAttr?catePubId=' . $catePubId), true);
        foreach ($json['data'] as $v) {
            if ($v['leaf'] == '0') {
                $this->getCate($v['catePubId'], $list);
            } else {
                $list[] = $v['catePubId'];
            }
        }
    }

}
