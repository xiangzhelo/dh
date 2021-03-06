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

    public static function getRunParams($html) {
        preg_match('/window.runParams(\s{0,1})=([\{\s ]+)data:([\s ]+)(.*),([\s ]+)csrfToken/', $html, $arr);
        if (isset($arr[4])) {
            return json_decode($arr[4], true);
        } else {
            return [];
        }
    }

    public static function getMultiRunParams($html) {
        $data = [];
        $html = str_replace('window.runParams = {};', '', $html);
        preg_match('/window.runParams(\s{0,1})=([\{\s ]+)"abtest(.*)/', $html, $arr);
        if (isset($arr[0])) {
            preg_match('/window.runParams(\s{0,1})=([\s ]+)(.*);/', $arr[0], $arr1);
            if (isset($arr1[3])) {
                $data = json_decode($arr1[3], true);
            }
        }
        if (!$data) {
            preg_match('/window.runParams(\s{0,1})=([\s ]+)(.*);/', $html, $arr);
            if (isset($arr[3])) {
                $data = json_decode($arr[3], true);
            }
        }
        return $data;
    }

    public static function getLocationUrl($html) {
        preg_match('/Location:(\s{0,1})(.*)/', $html, $arr);
        if (isset($arr[2])) {
            return preg_replace('/\s/', '', $arr[2]);
        }
        return false;
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

    public static function esCookie() {
        if (strpos($_SERVER['PATH'], '\Users\94946')) {
            return shell_exec('python 2.py');
        } else {
            return shell_exec('C:\Users\Administrator\AppData\Local\Programs\Python\Python37\python.exe 1.py');
        }
    }

    private static function getProxyIp($proxy_ip, $fresh = false) {
        if ($fresh || empty($proxy_ip)) {
            $curl = new \Lib\Vendor\Curl();
            $jsonStr = $curl->get('http://api.wandoudl.com/api/ip?app_key=bf469f3d992360983d96acfe4a00a257&pack=0&num=1&xy=1&type=2&lb=\r\n&mr=1');
            $json = json_decode($jsonStr, true);
            setcookie('AUTH_PROXY_IP', $json['data'][0]['ip'] . ':' . $json['data'][0]['port'], strtotime($json['data'][0]['expire_time']));
            return $json['data'][0]['ip'] . ':' . $json['data'][0]['port'];
        } else {
            return $proxy_ip;
        }
    }

    private static function getContent($url, $proxy_ip) {
        $cookie = self::esCookie(); //@file_get_contents(PUL_PATH . 'ali_cookie.txt');
        $curl = new \Lib\Vendor\Curl();
        $username = "963205006@qq.com"; // 您的用户名邮箱963205006@qq.com 密码Lk123456
        $password = "Lk123456"; // 您的密码
        $basic = base64_encode($username . ":" . $password);
        $output = $curl->getHead($url, ["Proxy-Authorization: Basic " . $basic], 60, $cookie, $proxy_ip);
        if (strpos($output, 'Please slide to verify') !== false) {
            return ['status' => 'error', 'msg' => '请先打开aliexpress产品搜索，验证之后请刷新该页面同时1分钟后再开始采集'];
        }
        if (strpos($output, 'Location: https://login.aliexpress.com') !== false) {
            return ['status' => 'error', 'msg' => '请先登录aliexpress，登录之后请刷新该页面同时1分钟后再开始采集'];
        }
        if ($output == false) {
            return ['status' => 'error', 'msg' => '失败'];
        }
        return ['status' => 'success', 'msg' => '', 'data' => $output];
    }

    public static function hand($url) {
        try {
            $proxy_ip = '';
            if (isset($_COOKIE['AUTH_PROXY_IP'])) {
                $proxy_ip = $_COOKIE['AUTH_PROXY_IP'];
            }
            for ($i = 0; $i < 3; $i++) {
                $proxy_ip = self::getProxyIp($proxy_ip);
                $ret = self::getContent($url, $proxy_ip);
                if ($ret['status'] == 'error') {
                    $proxy_ip = self::getProxyIp($proxy_ip, true);
                    $ret = self::getContent($url, $proxy_ip);
                    if ($ret['status'] == 'error') {
                        throw new Exception($ret['msg']);
                    }
                }
                $contents = $ret['data'];
                if (strpos($contents, 'Location:') !== false) {
                    $url = self::getLocationUrl($contents);
                    if (!$url) {
                        throw new Exception('错误');
                    }
                } else {
                    break;
                }
            }
            $curl = new Curl();
//            $contents = $curl->get($url, ['X-FORWARDED-FOR:' . self::Rand_IP(), 'CLIENT-IP:' . self::Rand_IP()]);
            if ($contents == false) {
                throw new Exception('错误');
            }
            $webData = self::getRunParams($contents);
            $dom = new HtmlDom();
            $html = $dom->load($contents);
            $data = [
                '产品id' => $webData['actionModule']['productId'],
                '产品标题' => $webData['titleModule']['subject'],
                '价格' => isset($webData['priceModule']['maxActivityAmount']) ? $webData['priceModule']['maxActivityAmount']['value'] : $webData['priceModule']['maxAmount']['value'],
                '原价' => $webData['priceModule']['minAmount']['value'] . '-' . $webData['priceModule']['maxAmount']['value'], //$html->find('.product-price-main .p-del-price-detail .p-price', 0)->plaintext,
                '特价' => isset($webData['priceModule']['minActivityAmount']) ? $webData['priceModule']['minActivityAmount']['value'] . '-' . $webData['priceModule']['maxActivityAmount']['value'] : $webData['priceModule']['minAmount']['value'] . '-' . $webData['priceModule']['maxAmount']['value'], //$html->find('.product-price-main .p-current-price .p-price', 0)->plaintext,
                '价格单位' => $webData['priceModule']['minAmount']['currency'], //$html->find('.p-symbol', 0)->outertext,//->getAttribute('itemprop'),
                '描述' => $webData['pageModule']['description'],
                '关键词' => explode(',', $webData['pageModule']['keywords']), //
                '折扣' => isset($webData['priceModule']['discount']) ? $webData['priceModule']['discount'] : 100,
                '计量单位' => strtolower($webData['priceModule']['oddUnitName'])
            ];
            $data['运费'] = self::getFreight($data['产品id']);
            $data['categories'] = [];
            if (!empty($webData['crossLinkModule'])) {
                foreach ($webData['crossLinkModule']['breadCrumbPathList'] as $k => $v) {
                    if ($k > 1) {
                        $data['categories'][] = htmlentities(strtolower(trim($v['name'], ' ')));
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
            if (isset($webData['skuModule']['productSKUPropertyList']) && $webData['skuModule']['productSKUPropertyList']) {
                foreach ($webData['skuModule']['productSKUPropertyList'] as $item) {
                    $type = strtolower($item['skuPropertyName']);
                    $ids = [];
                    if (strpos($type, 'color') !== false || strpos($type, 'kleur') !== false) {
                        foreach ($item['skuPropertyValues'] as $a) {
                            $id = $a['propertyValueId'];
                            $colorList[$id] = [
                                '颜色' => strtolower(str_replace(' ', '', empty($a['propertyValueName']) ? $a['propertyValueDisplayName'] : $a['skuPropertyTips'])),
                                '图片' => isset($a['skuPropertyImagePath']) ? $a['skuPropertyImagePath'] : '',
                                '颜色id' => $id,
                                '颜色orign' => empty($a['propertyValueDisplayName']) ? $a['propertyValueDisplayName'] : $a['skuPropertyTips'],
                            ];
                            $ids[] = $id;
                        }
                        $skuAttr[] = $ids;
                        $postion['color'] = $pos;
                        $pos++;
                    } elseif (strpos($type, 'size') !== false || strpos($type, 'quantity') !== false) {
                        foreach ($item['skuPropertyValues'] as $a) {
                            $id = $a['propertyValueId'];
                            $sizes[$id] = $a['propertyValueDisplayName'];
                            $ids[] = $id;
                        }
                        $skuAttr[] = $ids;
                        $postion['size'] = $pos;
                        $pos++;
                    } elseif (strpos($type, 'height') !== false || strpos($type, 'capacity') !== false) {
                        foreach ($item['skuPropertyValues'] as $a) {
                            $id = $a['propertyValueId'];
                            $height[$id] = $a['propertyValueDisplayName'];
                            $ids[] = $id;
                        }
                        $skuAttr[] = $ids;
                        $postion['height'] = $pos;
                        $pos++;
                    } elseif (strpos($type, 'material') !== false) {
                        foreach ($item['skuPropertyValues'] as $a) {
                            $id = $a['propertyValueId'];
                            $material[$id] = $a['propertyValueDisplayName'];
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
                        foreach ($item['skuPropertyValues'] as $a) {
                            $id = $a['propertyValueId'];
                            $length[$id] = $a['propertyValueDisplayName'];
                            $ids[] = $id;
                        }
                        $skuAttr[] = $ids;
                        $postion['length'] = $pos;
                        $pos++;
                    }
                }
            }
            $skuProducts = $webData['skuModule']['skuPriceList'];
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
            foreach ($webData['specsModule']['props'] as $li) {
                $key = strtolower($li['attrName']);
                $value = strtolower($li['attrValue']);
                $needMatchList[$key] = $value;
                $data[$key] = $value;
            }
//            foreach ($html->find('.product-packaging-list', 0)->find('li') as $li) {
//                $key = strtolower($li->find('span', 0)->plaintext);
//                if (strpos($key, 'unidad') !== false) {
//                    $data['单位类型'] = strtolower($li->find('span', 1)->plaintext);
//                }
//                if (strpos($key, 'weight') !== false || strpos($key, 'peso') !== false) {
//                    $data['重量'] = $li->find('span', 1)->rel;
//                }
//                if (strpos($key, 'dimensiones') !== false || strpos($key, 'size') !== false) {
//                    $value = $li->find('span', 1)->rel;
//                    $arr = explode('|', $value);
//                    $data['长'] = isset($arr[0]) ? $arr[0] : 0;
//                    $data['宽'] = isset($arr[1]) ? $arr[1] : 0;
//                    $data['高'] = isset($arr[2]) ? $arr[2] : 0;
//                }
//            }
            $data['属性'] = $attribute;
            $data['产品介绍'] = 'desc/' . $data['产品id'] . '.html';
            @file_put_contents(PUL_PATH . 'desc/' . $data['产品id'] . '.html', $curl->get($webData['descriptionModule']['descriptionUrl']));
            $data['产品图片'] = $webData['imageModule']['imagePathList'];
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
