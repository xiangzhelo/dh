<?php

namespace Lib\Vendor;

use Lib\Vendor\HtmlDom;
use Lib\Vendor\Curl;
use Exception;

class CommonFun {

    public static function getCookie($cookie, $name) {
        preg_match("/set-cookie: " . $name . "=([^;]*);/i", $cookie, $arr);
        return isset($arr[1]) ? $arr[1] : '';
    }

    public static function getLocation($cookie) {
        preg_match("/Location: (.*).com/i", $cookie, $arr);
        return isset($arr[1]) ? ($arr[1] . '.com') : '';
    }

    public static function getCookieName($cookie) {
        preg_match_all("/set-cookie: ([^=]*)=/i", $cookie, $arr);
        return isset($arr[1]) ? $arr[1] : '';
    }

    public static function getJsValue($html, $key) {
        preg_match('/' . $key . '(\s{0,1})=(\s{0,1})"([^"]*)";/i', $html, $arr);
        if (isset($arr[3])) {
            return $arr[3];
        } else {
            return '';
        }
    }

    public static function getJsJson($html, $key) {
        preg_match('/' . $key . '(\s{0,1})=(\s{0,1})(.*);\s/i', $html, $arr);
        if (isset($arr[3])) {
            return json_decode($arr[3], true);
        } else {
            return [];
        }
    }

    public static function getCookieValueByKey($cookie, $key) {
        preg_match('/' . $key . '=([^;]);/i', $cookie, $arr);
        if (isset($arr[1])) {
            return $arr[1];
        } else {
            return '';
        }
    }

    public static function arrayColumns($list, $column, $key = '') {
        $data = [];
        foreach ($list as $arr) {
            if (empty($key)) {
                $data[] = $arr[$column];
            } else {
                if (!empty($column)) {
                    $data[strtolower(str_replace(' ', '', $arr[$key]))] = $arr[$column];
                } else {
                    $data[strtolower(str_replace(' ', '', $arr[$key]))] = $arr;
                }
            }
        }
        return $data;
    }

    public static function hand($url) {
        try {
            $curl = new Curl();
            $contents = $curl->get($url);
            if ($contents == false) {
                throw new Exception('错误');
            }
            $dom = new HtmlDom();
            $html = $dom->load($contents);
            $data = [
                '产品id' => $html->find('input[name=objectId]', 0)->value,
                '产品标题' => htmlspecialchars_decode($html->find('.product-name', 0)->plaintext),
                '原价' => self::getJsValue($contents, 'window.runParams.minPrice') . ' - ' . self::getJsValue($contents, 'window.runParams.maxPrice'), //$html->find('.product-price-main .p-del-price-detail .p-price', 0)->plaintext,
                '特价' => self::getJsValue($contents, 'window.runParams.actMinPrice') . ' - ' . self::getJsValue($contents, 'window.runParams.actMaxPrice'), //$html->find('.product-price-main .p-current-price .p-price', 0)->plaintext,
                '价格单位' => self::getJsValue($contents, 'window.runParams.baseCurrencyCode'), //$html->find('.p-symbol', 0)->outertext,//->getAttribute('itemprop'),
                '描述' => $html->find('meta[name=description]', 0)->content,
                '关键词' => explode(',', $html->find('meta[name=keywords]', 0)->content), //
                '折扣' => self::getJsValue($contents, 'window.runParams.discount')
            ];
            $data['categories'] = [];
            foreach ($html->find('.ui-breadcrumb a') as $a) {
                if (!empty($a->title)) {
                    $data['categories'][] = strtolower(urldecode($a->title));
                }
            }
            $attribute = [];
            $sizes = [];
            $colorList = [];
            foreach ($html->find('#j-product-info-sku #j-sku-list-1 a') as $a) {
                $id = $a->getAttribute('data-sku-id');
                $colorList[$id] = [
                    '颜色' => strtolower(str_replace(' ', '', $a->title)),
                    '图片' => $a->find('img', 0)->bigpic,
                    '颜色id' => $id,
                ];
            }
            foreach ($html->find('#j-product-info-sku #j-sku-list-2 a') as $a) {
                $id = $a->getAttribute('data-sku-id');
                $sizes[$id] = $a->find('span', 0)->plaintext;
            }
            $skuAttr = self::getJsJson($contents, 'skuAttrIds');
            $skuProducts = self::getJsJson($contents, 'skuProducts');
            if (count($skuProducts) > 0) {
                $skuProducts = array_column($skuProducts, null, 'skuPropIds');
            }
            $num = 0;
            foreach ($skuAttr[0] as $v0) {
                foreach ($skuAttr[1] as $v1) {
                    if (isset($skuProducts[$v0 . ',' . $v1])) {
                        $info = $skuProducts[$v0 . ',' . $v1];
                    } else {
                        $info = $skuProducts[$v0 . ',' . $v1 . ',201336100'];
                    }

                    $attribute[$num] = $colorList[$v0];
                    $attribute[$num]['尺码id'] = $v1;
                    $attribute[$num]['尺码'] = trim($sizes[$v1], ' ');
                    $attribute[$num]['可用量'] = $info['skuVal']['availQuantity'];
                    $attribute[$num]['库存'] = $info['skuVal']['inventory'];
                    $attribute[$num]['折扣价'] = $info['skuVal']['actSkuCalPrice'];
                    $attribute[$num]['原价'] = $info['skuVal']['skuCalPrice'];
                    $num++;
                }
            }
            $needMatchList = [];
            foreach ($html->find('.product-property-list li') as $li) {
                $key = strtolower(trim(trim($li->find('.propery-title', 0)->plaintext), ':'));
                $value = strtolower(trim(trim($li->find('.propery-des', 0)->plaintext), ':'));
                $needMatchList[$key] = $value;
                $data[$key] = $value;
            }
            foreach ($html->find('.product-packaging-list', 0)->find('li') as $li) {
                $key = strtolower($li->find('span', 0)->plaintext);
                if (strpos($key, 'unidad') !== false) {
                    $data['单位类型'] = strtolower($li->find('span', 1)->plaintext);
                }
                if (strpos($key, 'weight') !== false || strpos($key, 'peso') !== false) {
                    $data['重量'] = $li->find('span', 1)->rel;
                }
                if (strpos($key, 'dimensiones') !== false || strpos($key, 'size') !== false) {
                    $value = $li->find('span', 1)->rel;
                    $arr = explode('|', $value);
                    $data['长'] = isset($arr[0]) ? $arr[0] : 0;
                    $data['宽'] = isset($arr[1]) ? $arr[1] : 0;
                    $data['高'] = isset($arr[2]) ? $arr[2] : 0;
                }
            }
            $data['属性'] = $attribute;
            $data['产品介绍'] = 'desc/' . $data['产品id'] . '.html';
            @file_put_contents(PUL_PATH . 'desc/' . $data['产品id'] . '.html', $curl->get(self::getJsValue($contents, 'window.runParams.detailDesc')));
            $data['产品图片'] = [];
            foreach ($html->find('.image-thumb-list img') as $img) {
                $str = $img->src;
                $data['产品图片'][] = substr($str, 0, strrpos($str, '_50x50.jpg'));
            }
            $html->clear();
//            $words = array_values(array_merge($needMatchList, array_keys($needMatchList), $data['分类'], array_keys($data['分类'])));
//            $wordsList = \Words::find([
//                        'conditions' => 'orign_words in ({orign_words:array})',
//                        'bind' => [
//                            'orign_words' => $words
//                        ],
//                        'columns' => 'orign_words,dest_words,status'
//                    ])->toArray();
//            if (!empty($wordsList)) {
//                $wordsList = array_column($wordsList, null, 'orign_words');
//            }
            $insertSql = '';
            $data['匹配情况'] = '未匹配';
//            $needList = [];
//            foreach ($needMatchList as $key => $value) {
//                if (!is_numeric($value) && !in_array($value, $needList)) {
//                    if (isset($wordsList[$value])) {
//                        if ($wordsList[$value]['status'] == '200') {
//                            $data[$key] = $wordsList[$value]['dest_words'];
//                        } else {
//                            $data['匹配情况'] = '未匹配';
//                        }
//                    } else {
//                        $needList[] = $value;
//                        $data['匹配情况'] = '未匹配';
//                        $insertSql.='("' . $value . '","0","' . date('Y-m-d H:i:s') . '","0","' . $data['产品id'] . '"),';
//                    }
//                }
//                if (!is_numeric($key) && !in_array($key, $needList)) {
//                    if (isset($wordsList[$key])) {
//                        if ($wordsList[$key]['status'] == '200') {
//                            $data[$wordsList[$key]['dest_words']] = $data[$key];
//                            unset($data[$key]);
//                        } else {
//                            $data['匹配情况'] = '未匹配';
//                        }
//                    } else {
//                        $needList[] = $key;
//                        $data['匹配情况'] = '未匹配';
//                        $insertSql.='("' . $key . '","0","' . date('Y-m-d H:i:s') . '","0","' . $data['产品id'] . '"),';
//                    }
//                }
//            }
//            foreach ($data['分类'] as $key => $value) {
//                if (!is_numeric($value) && !in_array($value, $needList)) {
//                    if (isset($wordsList[$value])) {
//                        if ($wordsList[$value]['status'] == '200') {
//                            $data['分类'][$key] = $wordsList[$value]['dest_words'];
//                        } else {
//                            $data['匹配情况'] = '未匹配';
//                        }
//                    } else {
//                        $needList[] = $value;
//                        $data['匹配情况'] = '未匹配';
//                        $insertSql.='("' . $value . '","0","' . date('Y-m-d H:i:s') . '","1","' . $data['产品id'] . '"),';
//                    }
//                }
//                if (!is_numeric($key) && !in_array($key, $needList)) {
//                    if (isset($wordsList[$key])) {
//                        if ($wordsList[$key]['status'] == '200') {
//                            $data['分类'][$wordsList[$key]['dest_words']] = $data['分类'][$key];
//                            unset($data['分类'][$key]);
//                        } else {
//                            $data['匹配情况'] = '未匹配';
//                        }
//                    } else {
//                        $needList[] = $key;
//                        $data['匹配情况'] = '未匹配';
//                        $insertSql.='("' . $key . '","0","' . date('Y-m-d H:i:s') . '","1","' . $data['产品id'] . '"),';
//                    }
//                }
//            }
//            if (!empty($data['categories'])) {
//                $len = count($data['categories']);
//                $cateModel = \Categories::findFirst([
//                            'conditions' => 'orign_category=:orign_category:',
//                            'bind' => [
//                                'orign_category' => strtolower($data['categories'][$len - 1])
//                            ]
//                ]);
//                if ($cateModel->status == 200) {
//                    $queueUrl = 'http://www.dh.com/lexicon/wordsMatch?source_product_id=' . $data['产品id'];
//                    $qCount = \Queue::count([
//                                'conditions' => 'queue_url=:queue_url: and status=0',
//                                'bind' => [
//                                    'queue_url' => $queueUrl
//                                ]
//                    ]);
//                    if ($qCount == 0) {
//                        $queue = new \Queue();
//                        $queue->queue_url = $queueUrl;
//                        $queue->status = 0;
//                        $queue->createtime = date('Y-m-d H:i:s');
//                        $queue->content = '分类匹配成功';
//                        $queue->save();
//                    }
//                }
//                if ($cateModel == false || $cateModel->status != 200) {
//                    $needWorsModel = new \NeedWords();
//                    $needWorsModel->source_product_id = $data['产品id'];
//                    $needWorsModel->words = $data['categories'][$len - 1];
//                    $needWorsModel->is_cate = 1;
//                    $needWorsModel->status = 0;
//                    $needWorsModel->createtime = date('Y-m-d H:i:s');
//                    $needWorsModel->save();
//                }
//                if ($cateModel == false) {
//                    $cateModel = new \Categories();
//                    $cateModel->orign_category = $data['categories'][$len - 1];
//                    $cateModel->status = 0;
//                    $cateModel->source_product_id = $data['产品id'];
//                    $cateModel->createtime = date('Y-m-d H:i:s');
//                    $cateModel->save();
//                }
//            }
            if (!empty($insertSql)) {
                $insertSql = 'insert into words (`orign_words`,`status`,`createtime`,`is_cate`,`source_product_id`)values' . rtrim($insertSql, ',') . ';';
                \Words::insertSql($insertSql);
            }
            return $data;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}
