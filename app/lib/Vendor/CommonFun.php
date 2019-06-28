<?php

namespace Lib\Vendor;

use Lib\Vendor\HtmlDom;
use Lib\Vendor\Curl;
use Exception;

class CommonFun {

    public static function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }

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
        preg_match('/' . $key . '=([^;]+);/i', $cookie, $arr);
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

    public static function Rand_IP() {
        $ip2id = round(rand(600000, 2550000) / 10000); //第一种方法，直接生成
        $ip3id = round(rand(600000, 2550000) / 10000);
        $ip4id = round(rand(600000, 2550000) / 10000);
        //下面是第二种方法，在以下数据中随机抽取
        $arr_1 = array("218", "218", "66", "66", "218", "218", "60", "60", "202", "204", "66", "66", "66", "59", "61", "60", "222", "221", "66", "59", "60", "60", "66", "218", "218", "62", "63", "64", "66", "66", "122", "211");
        $randarr = mt_rand(0, count($arr_1) - 1);
        $ip1id = $arr_1[$randarr];
        return $ip1id . "." . $ip2id . "." . $ip3id . "." . $ip4id;
    }

    public static function hand($url) {
        try {
            $curl = new Curl();
            $contents = $curl->get($url, ['X-FORWARDED-FOR:' . self::Rand_IP(), 'CLIENT-IP:' . self::Rand_IP()]);
            if ($contents == false) {
                throw new Exception('错误');
            }
            $dom = new HtmlDom();
            $html = $dom->load($contents);
            $data = [
                '产品id' => $html->find('input[name=objectId]', 0)->value,
                '产品标题' => htmlspecialchars_decode($html->find('.product-name', 0)->plaintext),
                '价格' => $html->find('#j-sku-price', 0)->plaintext,
                '原价' => self::getJsValue($contents, 'window.runParams.minPrice') . ' - ' . self::getJsValue($contents, 'window.runParams.maxPrice'), //$html->find('.product-price-main .p-del-price-detail .p-price', 0)->plaintext,
                '特价' => self::getJsValue($contents, 'window.runParams.actMinPrice') . ' - ' . self::getJsValue($contents, 'window.runParams.actMaxPrice'), //$html->find('.product-price-main .p-current-price .p-price', 0)->plaintext,
                '价格单位' => self::getJsValue($contents, 'window.runParams.baseCurrencyCode'), //$html->find('.p-symbol', 0)->outertext,//->getAttribute('itemprop'),
                '描述' => $html->find('meta[name=description]', 0)->content,
                '关键词' => explode(',', $html->find('meta[name=keywords]', 0)->content), //
                '折扣' => self::getJsValue($contents, 'window.runParams.discount'),
                '计量单位' => strtolower($html->find('#oddUnitName_id', 0)->value)
            ];
            $data['运费'] = self::getFreight($data['产品id']);
            $data['categories'] = [];
            if (!empty($html->find('.ui-breadcrumb a'))) {
                foreach ($html->find('.ui-breadcrumb a') as $a) {
                    if (!empty($a->title)) {
                        $data['categories'][] = strtolower(urldecode($a->title));
                    }
                }
            } else {
                if (!empty($html->find('.breadcrumb-layout b'))) {
                    foreach ($html->find('.breadcrumb-layout b') as $b) {
                        if (!empty($b->plaintext)) {
                            $data['categories'][] = strtolower(urldecode($b->plaintext));
                        }
                    }
                }
            }
            $attribute = [];
            $sizes = [];
            $height = [];
            $material = [];
            $length = [];
            $colorList = [];
            $skuAttr = [];
            $postion = ['size' => 99, 'from' => 99, 'color' => 99, 'height' => 99, 'material' => 99, 'length' => 99];
            $pos = 0;
            foreach ($html->find('#j-product-info-sku .p-property-item') as $item) {
                $type = strtolower($item->find('.p-item-title', 0)->plaintext);
                $ids = [];
                if (strpos($type, 'color') !== false || strpos($type, 'kleur') !== false) {
                    foreach ($item->find('a') as $a) {
                        $id = $a->getAttribute('data-sku-id');
                        $colorList[$id] = [
                            '颜色' => strtolower(str_replace(' ', '', empty($a->title) ? $a->find('span', 0)->plaintext : $a->title)),
                            '图片' => empty($a->find('img')) ? '' : $a->find('img', 0)->bigpic,
                            '颜色id' => $id,
                            '颜色orign' => empty($a->title) ? $a->find('span', 0)->plaintext : $a->title,
                        ];
                        $ids[] = $id;
                    }
                    $skuAttr[] = $ids;
                    $postion['color'] = $pos;
                    $pos++;
                } elseif (strpos($type, 'size') !== false || strpos($type, 'quantity') !== false) {
                    foreach ($item->find('a') as $a) {
                        $id = $a->getAttribute('data-sku-id');
                        $sizes[$id] = empty($a->find('span')) ? '' : $a->find('span', 0)->plaintext;
                        $ids[] = $id;
                    }
                    $skuAttr[] = $ids;
                    $postion['size'] = $pos;
                    $pos++;
                } elseif (strpos($type, 'height') !== false || strpos($type, 'capacity') !== false) {
                    foreach ($item->find('a') as $a) {
                        $id = $a->getAttribute('data-sku-id');
                        $height[$id] = empty($a->find('span')) ? '' : $a->find('span', 0)->plaintext;
                        $ids[] = $id;
                    }
                    $skuAttr[] = $ids;
                    $postion['height'] = $pos;
                    $pos++;
                } elseif (strpos($type, 'material') !== false) {
                    foreach ($item->find('a') as $a) {
                        $id = $a->getAttribute('data-sku-id');
                        $material[$id] = empty($a->find('span')) ? '' : $a->find('span', 0)->plaintext;
                        $ids[] = $id;
                    }
                    $skuAttr[] = $ids;
                    $postion['material'] = $pos;
                    $pos++;
                } elseif (strpos($type, 'ships from') !== false) {
                    $ids[] = 201336100;
                    $skuAttr[] = $ids;
                    $postion['from'] = $pos;
                    $pos++;
                } elseif (strpos($type, 'length') !== false || strpos($type, 'type') !== false) {
                    foreach ($item->find('a') as $a) {
                        $id = $a->getAttribute('data-sku-id');
                        $length[$id] = empty($a->find('span')) ? '' : $a->find('span', 0)->plaintext;
                        $ids[] = $id;
                    }
                    $skuAttr[] = $ids;
                    $postion['length'] = $pos;
                    $pos++;
                }
            }
            $skuProducts = self::getJsJson($contents, 'skuProducts');
            if (!empty($skuAttr)) {
                $skuAttr = self::crossArr([], $skuAttr);
            }
            if (count($skuProducts) > 0) {
                $skuProducts = array_column($skuProducts, null, 'skuPropIds');
            }
            $num = 0;
            foreach ($skuAttr as $v) {
                if (!isset($skuProducts[$v])) {
                    continue;
                }
                $info = $skuProducts[$v];
                $arr = explode(',', $v);
                $attribute[$num] = isset($arr[$postion['color']]) && isset($colorList[$arr[$postion['color']]]) ? $colorList[$arr[$postion['color']]] : '';
                $attribute[$num]['尺码id'] = isset($arr[$postion['size']]) ? $arr[$postion['size']] : '';
                $attribute[$num]['尺码'] = isset($arr[$postion['size']]) && isset($sizes[$arr[$postion['size']]]) ? trim($sizes[$arr[$postion['size']]], ' ') : '';
                $attribute[$num]['尺寸id'] = isset($arr[$postion['height']]) ? $arr[$postion['height']] : '';
                $attribute[$num]['尺寸'] = isset($arr[$postion['height']]) && isset($height[$arr[$postion['height']]]) ? trim($height[$arr[$postion['height']]], ' ') : '';
                $attribute[$num]['可用量'] = $info['skuVal']['availQuantity'];
                $attribute[$num]['库存'] = $info['skuVal']['inventory'];
                $attribute[$num]['折扣价'] = isset($info['skuVal']['actSkuCalPrice']) ? sprintf("%.2f", $info['skuVal']['actSkuCalPrice'] + $data['运费']) : sprintf("%.2f", $info['skuVal']['skuCalPrice'] + $data['运费']);
                $attribute[$num]['原价'] = $info['skuVal']['skuCalPrice'];
                $attribute[$num]['材质'] = isset($arr[$postion['material']]) && isset($material[$arr[$postion['material']]]) ? trim($material[$arr[$postion['material']]], ' ') : '';
                $attribute[$num]['长度'] = isset($arr[$postion['length']]) && isset($length[$arr[$postion['length']]]) ? trim($length[$arr[$postion['length']]], ' ') : '';
                $num++;
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
                $data['产品图片'][] = substr($str, 0, strrpos($str, '_50x50'));
            }
            $html->clear();
            $data['匹配情况'] = '未匹配';
            return $data;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public static function crossArr($arr1, $arr2, $s = ',') {
        $data = [];
        if (empty($arr1)) {
            $data = array_splice($arr2, 0, 1)[0];
        } else {
            $arr3 = array_splice($arr2, 0, 1)[0];
            foreach ($arr1 as $v1) {
                foreach ($arr3 as $v3) {
                    $data[] = $v1 . $s . $v3;
                }
            }
        }
        if (!empty($arr2)) {
            $data = self::crossArr($data, $arr2);
        }
        return $data;
    }

    public static function getFreight($productId) {
        $url = 'https://freight.aliexpress.com/ajaxFreightCalculateService.htm?productid=' . $productId . '&currencyCode=USD&transactionCurrencyCode=USD&sendGoodsCountry=&country=US&province=&city=&lang=en';
        $curl = new Curl();
        $jsonStr = $curl->get($url);
        $jsonStr = str_replace('(', '', $jsonStr);
        $jsonStr = str_replace(')', '', $jsonStr);
        $json = json_decode($jsonStr, true);
        if (isset($json['freight'][0]['localPrice'])) {
            return $json['freight'][0]['localPrice'];
        } else {
            return 0;
        }
    }

}
