<?php

namespace Dh\Controllers;

use Lib\Vendor\CommonFun;

class CollectionController extends ControllerBase {

    public function index1Action() {
        $data = CommonFun::hand('https://es.aliexpress.com/item/2018-Autumn-Women-s-Boots-Pointed-Toe-Yarn-Elastic-Ankle-Boots-Thick-Heel-High-Heels-Shoes/32831836272.html?spm=a219c.11010108.06001.3.276f2963PUrRXe&gps-id=5347592&scm=1007.13562.105726.0&scm_id=1007.13562.105726.0&scm-url=1007.13562.105726.0&pvid=3c4db667-3db0-4fbf-847f-819dc05985ef');
        $this->echoJson($data);
    }

    public function indexAction() {
        
    }

    public function handAction() {
        ini_set('display_errors', 'On');
        error_reporting(E_ALL);
        $source_url = $this->request->get('source_url', 'string');
        $collection_new = $this->request->get('collection_new', 'string', '0');
        $len = strpos($source_url, '?');
        if ($len > 0) {
            $source_url = substr($source_url, 0, $len);
        }
        if (strpos($source_url, 'http') === false) {
            $source_url = 'http:' . $source_url;
        }
        if ($collection_new == '1') {
            $hasProduct = \Product::findFirst([
                        'conditions' => 'source_url = :source_url:',
                        'bind' => [
                            'source_url' => $source_url
                        ]
            ]);
            if ($hasProduct != false) {
                $this->echoJson(['status' => 'success', 'msg' => '已采集', 'data' => ['item' => ['id' => $hasProduct->id]]]);
            }
        }
        $data = CommonFun::hand($source_url);
        $status = 0;
        if (isset($data['产品id'])) {
            if ($data['匹配情况'] == '匹配成功') {
                $status = 1;
            }
            if (!empty($data['categories'])) {
                $len = count($data['categories']);
                $category = trim(strtolower(implode(' > ', $data['categories'])));
                $cateModel = \Categories::findFirst([
                            'conditions' => 'orign_category=:orign_category:',
                            'bind' => [
                                'orign_category' => $category
                            ]
                ]);
                if ($cateModel != false && $cateModel->status == 200) {
                    $queueUrl = MY_DOMAIN . '/lexicon/wordsMatch?source_product_id=' . $data['产品id'];
                    $qCount = \Queue::count([
                                'conditions' => 'queue_url=:queue_url: and status=0',
                                'bind' => [
                                    'queue_url' => $queueUrl
                                ]
                    ]);
                    if ($qCount == 0) {
                        $queue = new \Queue();
                        $queue->queue_url = $queueUrl;
                        $queue->status = 0;
                        $queue->createtime = date('Y-m-d H:i:s');
                        $queue->contents = '分类匹配成功,产品属性匹配';
                        $queue->save();
                    }
                }
                $needWords = \NeedWords::findFirst([
                            'conditions' => 'words=:words: and is_cate=1 and source_product_id=:source_product_id:',
                            'bind' => [
                                'words' => $category,
                                'source_product_id' => $data['产品id']
                            ]
                ]);
                if ($needWords == false) {
                    $needWorsModel = new \NeedWords();
                    $needWorsModel->source_product_id = $data['产品id'];
                    $needWorsModel->words = $category;
                    $needWorsModel->is_cate = 1;
                    $needWorsModel->status = 0;
                    $needWorsModel->createtime = date('Y-m-d H:i:s');
                    $needWorsModel->save();
                }
                if ($cateModel == false) {
                    $cateModel = new \Categories();
                    $cateModel->orign_category = $category;
                    $cateModel->status = 0;
                    $cateModel->source_product_id = $data['产品id'];
                    $cateModel->createtime = date('Y-m-d H:i:s');
                    $cateModel->save();
                }
                if ($cateModel != false && $cateModel->status == 400) {
                    $status = 400;
                }
            }
            $model = \Product::findFirst([
                        'conditions' => 'source_product_id=:source_product_id:',
                        'bind' => [
                            'source_product_id' => $data['产品id']
                        ]
            ]);
            if ($model == false) {
                $model = \Product::createOne($source_url, $data['产品id'], $data['产品标题'], $data['产品图片'][0], json_encode($data, JSON_UNESCAPED_UNICODE), '', $status);
            } else {
                $model->source_product_name = $data['产品标题'];
                $model->source_img = $data['产品图片'][0];
                $model->product_data = json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($model->status == 2 || $model->status == 3) {
                    $model->status = $status;
                }
                $ret = $model->save();
                if ($ret == false) {
                    $this->echoJson(['status' => 'error', 'msg' => '采集失败', 'data' => ['source_url' => $source_url]]);
                }
            }
            if ($model == true) {
                $this->echoJson(['status' => 'success', 'msg' => '采集成功', 'data' => ['item' => ['id' => $model->id]]]); //, 'data' => ['product_data' => $data, 'item' => $model->toArray()]
            }
        }
        $this->echoJson(['status' => 'error', 'msg' => '采集失败,' . $data, 'data' => ['source_url' => $source_url]]);
    }

    public function dataAction() {
        $id = $this->request->get('id', 'int');
        $model = \Product::findFirst($id);
        if (strpos(PUL_PATH, '94946') === false || isset($_GET['dev'])) {
            $data = json_decode(empty($model->tran_product_data) ? $model->product_data : $model->tran_product_data, true);
            echo "<div>匹配情况:<span style='color:red;'>{$data['匹配情况']}</span></div>";
            echo "<div style='margin-top:5px;'>[</div>";
            $this->dataJson($data);
            echo "<div style='margin-top:5px;'>]</div>";
            exit();
        }
        $this->echoJson(json_decode(empty($model->tran_product_data) ? $model->product_data : $model->tran_product_data, true));
    }

    private function dataJson($data, $depth = 1) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                echo "<div style='margin-left:" . ($depth * 30) . "px;margin-top:5px;'><span style='margin-right:5px;font-weight:600;display:inline-block;'>" . (is_int($key) ? '' : $key . ' ： ') . "</span>[</div>";
                $this->dataJson($value, $depth + 1);
                echo "<div style='margin-left:" . ($depth * 30) . "px;margin-top:5px;'><span style='margin-right:5px;font-weight:600;display:inline-block;'></span>],</div>";
            } else {
                echo "<div style='margin-left:" . ($depth * 30) . "px;margin-top:5px;'><span style='margin-right:5px;font-weight:600;display:inline-block;'>" . (is_int($key) ? '' : $key . ' ： ') . "</span>{$value},</div>";
            }
        }
    }

    public function t1Action() {
        $curl = new \Lib\Vendor\MyCurl();
        $url = 'https://passport.aliexpress.com/iv/mini/identity_verify.htm?' . str_replace('_url=/collection/t1&', '', $_SERVER['REDIRECT_QUERY_STRING']);
        $output = $curl->get($url, $_SERVER['HTTP_COOKIE']);
//        $output = file_get_contents(PUL_PATH . '3.html');
//        $output =preg_match('/html\(\'\s+G/i', $output, $arr);
        $output = preg_replace('/html\(\'\s+G/i', 'html(\'G', $output);
//        var_dump($arr);
        echo $output;
        exit();
    }

    public function multiHandAction() {
        set_time_limit(0);
        $url = $this->request->get('source_url', 'string', '');
        $page = $this->request->get('page', 'int', 10);
//        $url = 'https://www.aliexpress.com/wholesale?catId=0&SearchText=shoes&page=13';
        $msg = $this->multiHand($url, $page);
        $this->echoJson(['status' => 'success', 'msg' => $msg, 'data' => ['source_url' => $url]]);
        exit();
    }

    public function multiHand($url, $page, $p = 1) {
        $msg = '';
        $curl = new \Lib\Vendor\Curl();
        $output = $curl->get($url, ['X-FORWARDED-FOR:' . CommonFun::Rand_IP(), 'CLIENT-IP:' . CommonFun::Rand_IP()]);
        if ($output == false) {
            return '失败';
        }
        $dom = new \Lib\Vendor\HtmlDom();
        $html = $dom->load($output);
        $num = 0;
        if (!empty($html->find('#list-items li .img a,.list-items li .img a'))) {
            foreach ($html->find('#list-items li .img a,.list-items li .img a') as $a) {
                $href = $a->href;
                $queueUrl = MY_DOMAIN . '/collection/hand?source_url=' . urlencode($href) . '&collection_new=1';
                $queue = new \Queue();
                $queue->queue_url = $queueUrl;
                $queue->status = 0;
                $queue->createtime = date('Y-m-d H:i:s');
                $queue->contents = '产品采集';
                $queue->save();
                $num++;
            }
            $nUrl = 'https:' . str_replace('&amp;', '&', $html->find('.page-next', 0)->href);
        }
        $html->clear();
        $msg.= '第' . $p . '页获取' . $num . '商品； ';
        if ($page > 1 && !empty($nUrl) && $nUrl != false) {
            $msg.=$this->multiHand($nUrl, --$page, ++$p);
        }
        return $msg;
    }

    public function queueAction() {
        set_time_limit(0);
        $queues = \Queue::find([
                    'conditions' => 'queue_url like "' . MY_DOMAIN . '/lexicon/wordsMatch%" and status=200'
        ]);
        $mcurl = new \Lib\Vendor\Mcurl();
        $mcurl->maxThread = 3;
        $num = 0;
        foreach ($queues as $item) {
            $url = $item->queue_url;
            $mcurl->add(['url' => $url, 'args' => ['id' => $item->id]], [$this, 'queueCallBack']);
            $num++;
        }
        $mcurl->start();
    }

    public function queueCallBack($res, $args) {
        $queue = \Queue::findFirst($args['id']);
        $queue->status = 200;
        $queue->save();
        var_dump($res['content']);
        echo '<br /><br /><br /><br />';
    }

    public function t2Action() {
        $key = preg_replace('/(\s+)(\d+)$/', '', 'feature 1');
        var_dump($key);
        exit();
    }

}
