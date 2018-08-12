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
                            file_get_contents('http://www.dh.com/lexicon/update?id=' . $words->id . '&dest_words=' . $dest_words);
                        }
                    }
                }
            }
        }
    }

    public function draftAction() {
        set_time_limit(0);
        $this->needLogin();
        $id = $this->request->get('id', 'int');
        $model = \Product::findFirst($id);
        if ($model == false || empty($model->product_data)) {
            $this->echoJson(['status' => 'error', 'msg' => '该数据未抓取']);
        }
        if (in_array($model->dh_category_id, ['141001', '141003', '141004', '141006', '141007'])) {
            $this->playDraftOrSave($model, $id, '141001', 1);
        } else if (in_array($model->dh_category_id, ['137006', '137005', '137011008', '137011005', '137010', '137011004', '137011002', '137011003'])) {
            $this->playDraftOrSave($model, $id, '137005', 1);
        } else {
            $this->echoJson(['status' => 'success', 'msg' => '该分类未开放']);
        }
    }

    private function playDraftOrSave($model, $id, $cate = '141001', $isSave = '') {
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
        $productInfo = array_column($this->getCateAttrL($model->dh_category_id)['data']['attributeList'], null, 'lineAttrNameCn');
        $sourceData = json_decode($model->tran_product_data, true);
        $data = json_decode(file_get_contents(PUL_PATH . 'catepub_json/' . $cate . '.json'), true);
        $editContent = MyCurl::get($url, $this->cookie);
        $dom = new HtmlDom();
        $html = $dom->load($editContent);
        foreach ($html->find('form input') as $input) {
            $key = $input->name;
            if (isset($data[$key])) {
                $data[$key] = $input->value;
            }
        }
        $this->pubData($data, $sourceData, $html);
        $this->units($data, $sourceData, $html);
        $this->keyWords($data, $sourceData);
        $this->skuInfo($data, $sourceData, $productInfo);
        if (in_array($cate, ['141001'])) {
            $this->sizeTp($data, $sourceData, $html);
        }
        $html->clear();
        $this->saleTp($data);
        $this->imglist($data, $sourceData);
        $retStr = MyCurl::post($drafUrl, $data, $this->cookie, ['X-FORWARDED-FOR:' . CommonFun::Rand_IP(), 'CLIENT-IP:' . CommonFun::Rand_IP()]);
        $ret = json_decode($retStr, true);
        if (empty($isSave) && $ret['code'] == '1000') {
            $model = \Product::findFirst($id);
            $model->dh_product_id = $ret['data'];
            $model->updatetime = date('Y-m-d H:i:s');
            $model->status = empty($isSave) ? 2 : 200;
            $model->save();
            $this->echoJson(['status' => 'success', 'msg' => '保存成功', 'data' => ['dh_itemcode' => '', 'dh_product_id' => $model->dh_product_id, 'dh_category_id' => $model->dh_category_id]]);
        } else if ($ret['itemcode'] > 0) {
            $model = \Product::findFirst($id);
            $model->dh_itemcode = $ret['itemcode'];
            $model->updatetime = date('Y-m-d H:i:s');
            $model->status = 200;
            $model->save();
            $this->echoJson(['status' => 'success', 'msg' => '保存成功', 'data' => ['dh_itemcode' => $model->dh_itemcode, 'dh_category_id' => $model->dh_category_id]]);
        } else {
            $this->echoJson(['status' => 'error', 'msg' => '保存失败']);
        }
        $this->echoJson($data);
    }

    private function skuInfo(&$data, $sourceData, $productInfo) {
        $Color_Id = isset($productInfo['颜色']['attrId']) ? $productInfo['颜色']['attrId'] : '';
        $Size_Id = isset($productInfo['尺码']['attrId']) ? $productInfo['尺码']['attrId'] : '';
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
            if (!empty($colorsList)) {
                $colorsList = $this->color($sourceData['属性'], $colorsList);
            }
        }

        if ($Size_Id != '') {
            $sizesList = array_column($productInfo['尺码']['valueList'], null, 'lineAttrvalName');
            if (!empty($sizesList)) {
                $sizesList = $this->size($sourceData['属性'], $sizesList);
            }
        }
        $colorValueList = [];
        $sizeValueList = [];
        foreach ($sourceData['属性'] as $li) {
            if (($Color_Id == '' || isset($colorsList[$li['颜色']])) && ($Size_Id == '' || isset($sizesList[$li['尺码']]))) {
                $ids = [];
                $attrList = [];
                if ($Color_Id != '') {
                    $ids[] = $Color_Id . '_' . $colorsList[$li['颜色']]['attrValId'];
                    $attrList[] = [
                        'attrId' => (string) $Color_Id,
                        'attrVid' => (string) $colorsList[$li['颜色']]['attrValId'],
                        'type' => $productInfo['颜色']['type'],
                        'class' => '##'
                    ];
                    $data['c_' . $Color_Id . '_vname'] = $Color_Id . '_' . $colorsList[$li['颜色']]['attrValId'];
                }
                if ($Size_Id != '') {
                    $ids[] = $Size_Id . '_' . $sizesList[$li['尺码']]['attrValId'];
                    $attrList[] = [
                        'attrId' => (string) $Size_Id,
                        'attrVid' => (string) $sizesList[$li['尺码']]['attrValId'],
                        'type' => $productInfo['尺码']['type'],
                        'class' => '##'
                    ];
                    $data['c_' . $Size_Id . '_vname'] = $Size_Id . '_' . $sizesList[$li['尺码']]['attrValId'];
                }
                $skuInfo[0]['skuInfoList'][] = [
                    'class' => '#',
                    'status' => ($li['库存'] > 0 ? '1' : '0'),
                    'price' => (string) ($li['折扣价'] * 2),
                    'stock' => '0', //(string) $li['库存'],
                    'skuCode' => '',
                    'id' => implode('A', $ids),
                    'attrList' => $attrList
                ];
                if ($Color_Id != '') {
                    $this->colorValueList($data, $colorValueList, $colorsList, $li);
                }
                if ($Size_Id != '') {
                    $this->sizeValueList($sizeValueList, $sizesList, $li);
                }
            }
        }
        if (count($skuInfo[0]['skuInfoList']) == 0) {
            $this->echoJson(['status' => 'error', 'msg' => '价格表不可为空']);
        }
        $data['proSkuInfo'] = json_encode($skuInfo, JSON_UNESCAPED_UNICODE);
        $attrlist = [];
        if ($Color_Id != '') {
            $this->colorAttr($attrlist, $Color_Id, $productInfo, $colorValueList);
        }
        if ($Size_Id != '') {
            $this->sizeAttr($attrlist, $Size_Id, $productInfo, $sizeValueList);
        }
        $this->attr($data, $productInfo, $attrlist, $sourceData);
        $data['attrlist'] = json_encode(array_values($attrlist), JSON_UNESCAPED_UNICODE);
    }

    private function sizeValueList(&$sizeValueList, $sizesList, $li) {
        if (!isset($sizeValueList[$sizesList[$li['尺码']]['attrValId']])) {
            $sizeValueList[$sizesList[$li['尺码']]['attrValId']] = [
                'class' => 'com.dhgate.syi.model.ProductAttributeValueVO',
                'attrValId' => $sizesList[$li['尺码']]['attrValId'],
                'lineAttrvalName' => $sizesList[$li['尺码']]['lineAttrvalName'],
                'lineAttrvalNameCn' => $sizesList[$li['尺码']]['lineAttrvalNameCn'],
                'iscustomsized' => $sizesList[$li['尺码']]['iscustomsized'],
                'picUrl' => '', //$sizesList['US' . $li['尺码']]['picUrl'],
                'brandValId' => ''
            ];
        }
    }

    private function colorValueList($data, &$colorValueList, $colorsList, $li) {
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
            $template = 0; //'women shoe';
        } else {
            $template = 1; //'men shoe';
        }
        $data['sellerSzTemplateId'] = $getSzSellerTemplate[$template]['szId'];
        $data['sellerTemplateName'] = $template == 1 ? 'men show' : 'women shoe';
    }

    private function attr(&$data, &$productInfo, &$attrlist, $sourceData) {
        $num = 2;
        foreach ($productInfo as $key => $arr) {
            if (in_array($key, ['颜色', '尺码'])) {
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
            if ($arr['required'] != '0' && empty($attrlist[$num]['valueList'])) {
                $this->echoJson(['status' => 'error', 'msg' => $arr['lineAttrNameCn'] . $arr['lineAttrName'] . '该属性不可为空']);
            }
            if (count($attrlist[$num]['valueList']) == 0) {
                unset($attrlist[$num]);
                continue;
            }
            if (count($attrlist[$num]['valueList']) > 1) {
                if (isset($attrlist[$num]['valueList']['自定义'])) {
                    unset($attrlist[$num]['valueList']['自定义']);
                }
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
            $num++;
        }
    }

    private function imglist(&$data, $sourceData) {
        $imglistData = [];
        $waterMark = [];
        foreach ($sourceData['产品图片'] as $key => $img) {
            $imgData = $this->imgIntoDh($img, $data['imgtoken'], $data['supplierid']);
            if ($imgData['result'] != '1') {
                $this->echoJson(['status' => 'error', 'msg' => '图片上传失败']);
            }
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
                    'sequence' => (string) ($key - 1),
                    'filename' => ''
                ];
            }
        }
        $data['waterMark'] = json_encode($waterMark, JSON_UNESCAPED_UNICODE);
        $data['imglist'] = json_encode($imglistData, JSON_UNESCAPED_UNICODE);
    }

    private function saleTp(&$data) {
        $dom = new HtmlDom();
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
        $getTempTableContents = MyCurl::post('http://seller.dhgate.com/syi/getTempTable.do?isblank=true', $post_data, $this->cookie, null, 60);
        $html1 = $dom->load($getTempTableContents);
        $data['shippingScore'] = $html1->find('#shippingScore', 0)->value;
        $data['isPostAriMail'] = $html1->find('#isPostAriMail', 0)->value;
        $html1->clear();
    }

    private function sizeAttr(&$attrlist, $Size_Id, $productInfo, $sizeValueList) {
        $attrlist[] = [
            'class' => 'com.dhgate.syi.model.ProductAttributeVO',
            'attrId' => $Size_Id,
            'attrName' => $productInfo['尺码']['lineAttrName'],
            'isbrand' => $productInfo['尺码']['isbrand'],
            'valueList' => array_values($sizeValueList)
        ];
        unset($productInfo['尺码']);
    }

    private function colorAttr(&$attrlist, $Color_Id, $productInfo, $colorValueList) {
        $attrlist[] = [
            'class' => 'com.dhgate.syi.model.ProductAttributeVO',
            'attrId' => $Color_Id,
            'attrName' => $productInfo['颜色']['lineAttrName'],
            'isbrand' => $productInfo['颜色']['isbrand'],
            'valueList' => array_values($colorValueList),
        ];
        unset($productInfo['颜色']);
    }

    private function color($colorSizeArr, $colorsList) {
        $ysList = array_unique(array_column($colorSizeArr, '颜色'));
        $cList = [];
        foreach ($ysList as $k => $v) {
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

    private function size($colorSizeArr, $sizesList) {
        $cmList = array_unique(array_column($colorSizeArr, '尺码'));
        $sList = [];
        foreach ($cmList as $v) {
            if (isset($sizesList[$v]) || isset($sizesList['US' . $v])) {
                $sList[$v] = isset($sizesList[$v]) ? $sizesList[$v] : $sizesList['US' . $v];
                $ispipei = 1;
                unset($sizesList[$v]);
            } else {
                foreach ($sizesList as $key => $value) {
                    if (strpos($key, $v) !== false || strpos($v, $key) !== false) {
                        $sList[$v] = $value;
                        $ispipei = 1;
                        unset($sizesList[$key]);
                        break;
                    }
                }
                if ($ispipei == 0) {
                    foreach ($sizesList as $key => $value) {
                        if (!in_array($key, $cmList)) {
                            $value['lineAttrvalName'] = $v;
                            $sList[$v] = $value;
                            unset($sizesList[$key]);
                            break;
                        }
                    }
                }
            }
        }
        return $sList;
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
        $data['productname'] = mb_substr(htmlspecialchars_decode($sourceData['产品标题']), 0, 140, 'utf-8');
        $data['forEditOldCatePubid'] = $data['catepubid'];
        $data['brandid'] = '99';
        $data['brandName'] = '无品牌';
        $data['elm'] = @file_get_contents(PUL_PATH . $sourceData['产品介绍']);
        $data['productdesc'] = 'Color may be a little different due to monitor. Pictures are only samples for reference. Due to limitations in photography and the inevitable differences in monitor settings'; //$sourceData['描述']; //
        $data['inventoryStatus'] = '0'; //是否有备货  1、0
        $data['inventory'] = '100';            //有备货用限制最大购买
        $data['inventoryLocation'] = 'CN';
        $data['sizelen'] = '30.0'; //$sourceData['长'];
        $data['sizewidth'] = '20.0'; //$sourceData['宽'];
        $data['sizeheight'] = '10.0'; //$sourceData['高'];
        $data['productweight'] = $sourceData['重量'];
        $data['setdiscounttype'] = 1; //统一设置价格 ：2  分别设置：1
        $data['noSpecPrice'] = '11';         //?????'
        $data['packquantity'] = '1';  //???
        $data['specselfDef'] = '[]';
        if ($data['setdiscounttype'] == '1') {
            $data['discountRange'] = json_encode([['startqty' => '1', 'discount' => '0'], ['startqty' => '2', 'discount' => '3']]); //$sourceData['折扣']
        } else {
            $data['discountRange'] = json_encode([['startqty' => '1', 'discount' => $sourceData['特价']], ['startqty' => '2', 'discount' => $sourceData['特价']]]);
        }
        $data['sortby'] = '1'; //销售方式  1、件  2、箱
        $data['shippingmodelname'] = '标准运费模板';
        $data['shippingmodelid'] = $suppTemplates['标准运费模板']['shippingmodelid'];
        $data['isLeadingtime'] = 'on'; //是否要备货
        $data['productInventorylist'] = '[{"class":"com.dhgate.syi.model.ProductInventoryVO","productInventoryId":"","quantity":231,"hasBuyAttr":"0","hasSaleAttr":"0","commonSpec":"0","supplierid":""}]';
        if (isset($sourceData['适用']) && is_array($sourceData['适用']) && in_array('成人', $sourceData['适用'])) {
            $data['issample_adult'] = '2';
        }
        if (isset($sourceData['适用']) && is_array($sourceData['适用']) && in_array('非成人', $sourceData['适用'])) {
            $data['issample_adult'] = '3';
        }
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
            if (isset($img_data['result']) && $img_data['result'] == 1) {
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
