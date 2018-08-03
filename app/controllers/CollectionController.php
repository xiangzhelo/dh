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
        $source_url = $this->request->get('source_url', 'string');
        $len = strpos($source_url, '?');
        if ($len > 0) {
            $source_url = substr($source_url, 0, $len);
        }
        if (strpos($source_url, 'http') === false) {
            $source_url = 'http:' . $source_url;
        }
        $data = CommonFun::hand($source_url);
        $status = 0;
        if (isset($data['产品id'])) {
            if ($data['匹配情况'] == '匹配成功') {
                $status = 1;
            }
            if (!empty($data['categories'])) {
                $len = count($data['categories']);
                $cateModel = \Categories::findFirst([
                            'conditions' => 'orign_category=:orign_category:',
                            'bind' => [
                                'orign_category' => trim(strtolower($data['categories'][$len - 1]))
                            ]
                ]);
                if ($cateModel != false && $cateModel->status == 200) {
                    $queueUrl = 'http://www.dh.com/lexicon/wordsMatch?source_product_id=' . $data['产品id'];
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
                if ($cateModel == false || $cateModel->status != 200) {
                    $needWorsModel = new \NeedWords();
                    $needWorsModel->source_product_id = $data['产品id'];
                    $needWorsModel->words = $data['categories'][$len - 1];
                    $needWorsModel->is_cate = 1;
                    $needWorsModel->status = 0;
                    $needWorsModel->createtime = date('Y-m-d H:i:s');
                    $needWorsModel->save();
                }
                if ($cateModel == false) {
                    $cateModel = new \Categories();
                    $cateModel->orign_category = $data['categories'][$len - 1];
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
                $this->echoJson(['status' => 'success', 'msg' => '采集成功', 'data' => ['product_data' => $data, 'item' => $model->toArray()]]);
            }
        }
        $this->echoJson(['status' => 'error', 'msg' => '采集失败,' . $data, 'data' => ['source_url' => $source_url]]);
    }

    public function dataAction() {
        $id = $this->request->get('id', 'int');
        $model = \Product::findFirst($id);
        $this->echoJson(json_decode($model->product_data, true));
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
        $url = 'https://www.aliexpress.com/wholesale?SearchText=boots&page=6';
        $this->multiHand($url, 1);
        exit();
    }

    public function multiHand($url, $page) {
        $curl = new \Lib\Vendor\MyCurl();
        $output = $curl->get($url);
        $dom = new \Lib\Vendor\HtmlDom();
        $html = $dom->load($output);
        $num = 0;
        foreach ($html->find('#hs-below-list-items li .img a') as $a) {
            $href = $a->href;
            $queueUrl = 'http://www.dh.com/collection/hand?source_url=' . urlencode($href);
            $queue = new \Queue();
            $queue->queue_url = $queueUrl;
            $queue->status = 0;
            $queue->createtime = date('Y-m-d H:i:s');
            $queue->contents = '产品采集';
            $queue->save();
            $num++;
        }
        $nUrl = $html->find('.page-next', 0)->href;
        if ($page > 1 && !empty($nUrl) && $nUrl != false) {
            $this->multiHand($nUrl, --$page);
        }
        $html->clear();
        echo 'success;处理条数:' . $num;
    }

    public function queueAction() {
        set_time_limit(0);
        $queues = \Queue::find([
                    'conditions' => 'status=200'
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
