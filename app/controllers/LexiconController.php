<?php

namespace Dh\Controllers;

use Lib\Vendor\Mcurl;
use Lib\Vendor\BaiduFun;
use Lib\Vendor\CommonFun;

class LexiconController extends ControllerBase {

    public function indexAction() {
        $page = $this->request->get('page', 'int', 1);
        $status = $this->request->get('status', 'string', '');
        $like_words = $this->request->get('like_words', 'string', '');
        $important = $this->request->get('important', 'string', '');
        $size = 100;
        $pages = \Words::getPage($page, $size, $like_words, $status, $important);
        $this->view->pages = $pages;
        $this->view->page = $page;
        $this->view->status = $status;
        $this->view->important = $important;
        $this->view->like_words = $like_words;
    }

    public function categoriesIndexAction() {
        $page = $this->request->get('page', 'int', 1);
        $status = $this->request->get('status', 'string', '');
        $like_words = $this->request->get('like_words', 'string', '');
        $size = 100;
        $pages = \Categories::getPage($page, $size, $like_words, $status);
        $this->view->pages = $pages;
        $this->view->page = $page;
        $this->view->status = $status;
        $this->view->like_words = $like_words;
    }

    public function categoriesUpdateAction() {
        $id = $this->request->get('id', 'int');
        $catePubId = $this->request->get('catePubId', 'string', '');
        $text = $this->request->get('text', 'string', '');
        $infoJson = $this->request->get('infoJson', 'string', '');
        $model = \Categories::findFirst($id);
        if ($model == false) {
            $this->echoJson(['status' => 'error', 'msg' => '未找到该字库']);
        } else {
            $model->dest_category = $text;
            $model->dh_category_id = $catePubId;
            $model->info_json = $infoJson;
            $model->status = 200;
            $model->save();
            $needList = \NeedWords::find([
                        'conditions' => 'words=:words: and is_cate=1',
                        'bind' => [
                            'words' => $model->orign_category
                        ]
            ]);
            foreach ($needList as $item) {
                $item->status = 200;
                $item->save();
                $queueUrl = MY_DOMAIN . '/lexicon/wordsMatch?source_product_id=' . $item->source_product_id;
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
                    $queue->content = '分类匹配成功';
                    $queue->save();
                }
            }
            $this->echoJson(['status' => 'success', 'msg' => '保存成功']);
        }
    }

    public function giveUpWordsAction() {
        $id = $this->request->get('id', 'int');
        $model = \Words::findFirst($id);
        if ($model == false) {
            $this->echoJson(['status' => 'error', 'msg' => '未找到该字库']);
        } else {
            $model->dest_words = '放弃';
            $model->dh_category_id = '';
            $model->status = 400;
            $model->save();
            $needList = \NeedWords::find([
                        'conditions' => 'words=:words: and is_cate=0 and status=0',
                        'bind' => [
                            'words' => $model->orign_words
                        ]
            ]);
            foreach ($needList as $item) {
                $item->status = 400;
                $item->save();
            }
            $this->echoJson(['status' => 'success', 'msg' => '保存成功']);
        }
    }

    public function giveUpWordsKeyAction() {
        $id = $this->request->get('id', 'int');
        $model = \Words::findFirst($id);
        if ($model == false) {
            $this->echoJson(['status' => 'error', 'msg' => '未找到该字库']);
        } else {
            $arr = explode(':', $model->orign_words);
            if (isset($arr[0]) && !empty($arr[0])) {
                $words = \Words::find([
                            'conditions' => 'orign_words like :key: and is_cate=0 and status=0',
                            'bind' => [
                                'key' => $arr[0] . ':%'
                            ],
                            'columns' => 'id'
                        ])->toArray();
                if (empty($words)) {
                    $this->echoJson(['status' => 'error', 'msg' => '未找到该字库']);
                }
                $mcurl = new Mcurl();
                $mcurl->maxThread = 3;
                $mcurl->maxTry = 0;
                $num = 0;
                $ids = [];
                foreach ($words as $item) {
                    $url = MY_DOMAIN . '/lexicon/giveUpWords?id=' . $item['id'];
                    $mcurl->add(['url' => $url, 'args' => ['id' => $item['id']]]);
                    $num++;
                    $ids[] = $item['id'];
                }
                $mcurl->start();
                $this->echoJson(['status' => 'success', 'msg' => '处理成功', 'data' => ['ids' => $ids]]);
            }
        }
    }

    public function giveUpCateAction() {
        $id = $this->request->get('id', 'int');
        $model = \Categories::findFirst($id);
        if ($model == false) {
            $this->echoJson(['status' => 'error', 'msg' => '未找到该字库']);
        } else {
            $model->dest_category = '放弃';
            $model->dh_category_id = '';
            $model->info_json = '放弃';
            $model->status = 400;
            $model->save();
            $needList = \NeedWords::find([
                        'conditions' => 'words=:words: and is_cate=1 and status=0',
                        'bind' => [
                            'words' => $model->orign_category
                        ]
            ]);
            foreach ($needList as $item) {
                $item->status = 400;
                $item->save();
                $cate = \Categories::findFirst([
                            'conditions' => 'source_product_id=:source_product_id:',
                            'bind' => [
                                'source_product_id' => $item->source_product_id
                            ]
                ]);
                if ($cate == false) {
                    continue;
                }
                $cate->dest_category = '放弃';
                $cate->dh_category_id = '';
                $cate->info_json = '放弃';
                $cate->status = 400;
                $cate->save();
            }
            $this->echoJson(['status' => 'success', 'msg' => '保存成功']);
        }
    }

    public function wordsMatchAction() {
        set_time_limit(0);
        $source_product_id = $this->request->get('source_product_id', 'string');
        $product = \Product::findFirst([
                    'conditions' => 'source_product_id=:source_product_id:',
                    'bind' => [
                        'source_product_id' => $source_product_id
                    ]
        ]);
        $product_data = json_decode($product->product_data, true);
        if (empty($product_data['categories'])) {
            $this->echoJson(['status' => 'success', 'msg' => '分类错误,不存在分类']);
        }
        $mainCategory = trim(strtolower(implode(' > ', $product_data['categories'])));
        $categoryModel = \Categories::findFirst([
                    'conditions' => 'orign_category=:orign_category:',
                    'bind' => [
                        'orign_category' => $mainCategory
                    ]
        ]);
        if ($categoryModel == false || $categoryModel->status != 200) {
            $this->echoJson(['status' => 'success', 'msg' => '分类错误,分类未匹配']);
        }
        if (strpos($product_data['价格'], '-') !== false) {
            $priceArr = explode('-', $product_data['价格']);
            $product_data['价格'] = trim($priceArr[count($priceArr) - 1]);
        }
        $cate014 = ['014028007', '014028004', '014028008002', '014028005', '014028003001', '014028006', '014028002', '014028009',
            '014028001001', '014028001002', '014028010', '014026018001', '014026016', '014026003', '014026004', '014026011', '014026014',
            '014026005001', '014026012', '014026013', '014026007', '014026006', '014026010', '014026002003', '014026002001', '014026002004',
            '014026020001', '014026020003', '014027001006', '014027001001', '014027002012', '014027002001']; //服装
        $cate006 = ['006003112', '006003115', '006003115', '006011116', '006011117', '006007176', '006007167']; //玩具礼物
        $cate142 = ['142006003', '142006005', '142006001', '142003011', '142003003003']; //母婴用品
        $cate135 = ['135005002', '135010002', '135010003', '135005006']; //手机和手机附件
        $cate008 = ['008178']; //消费类电子  '008002', MP4变成了音箱所以取消
        $cate140 = [ '140005', '140002001']; //时尚配件
        $cate004 = ['004002002', '004002008', '004002011', '004006001', '004006008', '004007001', '004007008']; //珠宝
        $cate005 = ['005002', '005001']; //表
        $cate143 = ['143103113102', '143103113107', '143106001']; //汽配
        $cate024 = ['024029008', '024020005007', '024029001002', '024029001001', '024029005004', '024029003', '024029005003', '024026007001'
            , '024026007005', '024003003017', '024026008001', '014026010', '014028011', '024003019005', '024020004003', '024020005003'
            , '024020005002', '024020005001', '024020005007', '024034008001', '024034007002', '024033', '024023002005', '024023002004'
            , '024023001015', '024023001020', '024023001005', '024023001013', '024023001003', '024023001001', '024037']; //运动与户外产品
        if (in_array($categoryModel->dh_category_id, ['141001', '141003', '141004', '141006', '141007'])) {
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, ['137006', '137005', '137011008', '137011005', '137010', '137011004', '137011002', '137011003'])) {
            $this->cate137($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate014)) {
            $this->cate014($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate006)) {
            $this->cate006($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate142)) {
            $this->cate142($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate135)) {
            $this->cate135($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate008)) {
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate140)) {
            $this->cate140($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate004)) {
            $this->cate004($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate005)) {
            $this->cate005($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate143)) {
            $this->cate143($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else if (in_array($categoryModel->dh_category_id, $cate024)) {
            $this->cate024($categoryModel, $product_data);
            $this->matchProduct($product, $product_data, $categoryModel);
        } else {
            $product->dh_category_id = $categoryModel->dh_category_id;
            $product->status = 0;
            $product->need_attribute = '该分类未开放';
            $product->save();
            $this->echoJson(['status' => 'success', 'msg' => '该分类未开放']);
        }
    }

    private function cate014($categoryModel, &$product_data) {
//        if (count($product_data['属性']) > 0) {
//            foreach ($product_data['属性'] as $k => $v) {
//                if ($v['尺码'] == 'S') {
//                    $product_data['属性'][$k]['尺码'] = 'Free';
//                }
//            }
//        }
        foreach ($product_data['属性'] as $k => $v) {
            if ($v['尺码'] == 'XXXL') {
                $product_data['属性'][$k]['尺码'] = '3XL';
            }
        }
        //014027002012
        if (in_array($categoryModel->dh_category_id, ['014027002012'])) {
            foreach ($product_data['属性'] as $k => $v) {
                $product_data['属性'][$k]['尺码'] = 'other';
            }
        }
    }

    private function cate137($categoryModel, &$product_data) {
        if (in_array($categoryModel->dh_category_id, ['137010'])) {
            if (count($product_data['属性']) > 0) {
                foreach ($product_data['属性'] as $k => $v) {
                    $product_data['属性'][$k]['尺码'] = '自定义|others';
                }
            }
        }
    }

    private function cate004($categoryModel, &$product_data) {
        if (in_array($categoryModel->dh_category_id, ['004006001', '004006008'])) {
            if (count($product_data['属性']) > 0) {
                foreach ($product_data['属性'] as $k => $v) {
                    $product_data['属性'][$k]['颜色orign'] = trim((isset($v['颜色orign']) ? $v['颜色orign'] : '')
                            . (!empty($v['长度']) ? ' ' . $v['长度'] : ''));
                }
            }
        }
        if (in_array($categoryModel->dh_category_id, ['004007001'])) {
            if (count($product_data['属性']) > 0) {
                foreach ($product_data['属性'] as $k => $v) {
                    $product_data['属性'][$k]['尺码'] = empty($v['尺码']) ? $v['尺码'] : trim(
                                    (!empty($v['尺码']) ? ' ' . $v['尺码'] : '')
                                    . (!empty($v['尺寸']) ? ' ' . $v['尺寸'] : '')
                                    . (!empty($v['长度']) ? ' ' . $v['长度'] : '')
                                    . (!empty($v['材质']) ? ' ' . $v['材质'] : ''));
                    $product_data['属性'][$k]['尺码'] = empty($product_data['属性'][$k]['尺码']) ? '自定义|other' : $product_data['属性'][$k]['尺码'];
                }
            } else {
                $product_data['属性'][] = [
                    "颜色" => "自定义|pic",
                    "图片" => array_slice($product_data['产品图片'], -1, 1)[0],
                    "颜色id" => "自定义|pic",
                    "颜色orign" => "自定义|pic",
                    "尺码id" => "",
                    "尺码" => "自定义|other",
                    "尺寸id" => "",
                    "尺寸" => "",
                    "可用量" => "100",
                    "库存" => "100",
                    "折扣价" => $product_data['价格'],
                    "原价" => $product_data['价格'],
                    "材质" => '',
                    "长度" => ""
                ];
            }
        }
    }

    private function cate143($categoryModel, &$product_data) {
        if (in_array($categoryModel->dh_category_id, ['143103113102', '143103113107'])) {
            if (count($product_data['属性']) > 0) {
                foreach ($product_data['属性'] as $k => $v) {
                    $product_data['属性'][$k]['颜色orign'] = $v['颜色orign']
                            . (!empty($v['尺码']) ? ' ' . $v['尺码'] : '')
                            . (!empty($v['尺寸']) ? ' ' . $v['尺寸'] : '')
                            . (!empty($v['长度']) ? ' ' . $v['长度'] : '')
                            . (!empty($v['材质']) ? ' ' . $v['材质'] : '');
                }
            }
        }
    }

    private function cate140($categoryModel, &$product_data) {
        if (in_array($categoryModel->dh_category_id, ['140002001'])) {
            if (count($product_data['属性']) > 0) {
                foreach ($product_data['属性'] as $k => $v) {
                    if (!isset($v['颜色']) || empty($v['颜色'])) {
                        $product_data['属性'][$k]['颜色'] = '自定义|pic';
                        $product_data['属性'][$k]['图片'] = array_slice($product_data['产品图片'], -1, 1)[0];
                        $product_data['属性'][$k]['颜色id'] = '自定义|pic';
                        $product_data['属性'][$k]['颜色orign'] = '自定义|pic';
                    }
                    $product_data['属性'][$k]['材质'] = isset($product_data['style']) ? $product_data['style'] : 'other';
                    $product_data['属性'][$k]['尺寸'] = $v['长度'];
                }
            } else {
                $product_data['属性'][] = [
                    "颜色" => "自定义|pic",
                    "图片" => array_slice($product_data['产品图片'], -1, 1)[0],
                    "颜色id" => "自定义|pic",
                    "颜色orign" => "自定义|pic",
                    "尺码id" => "",
                    "尺码" => "",
                    "尺寸id" => "",
                    "尺寸" => "",
                    "可用量" => "100",
                    "库存" => "100",
                    "折扣价" => $product_data['价格'],
                    "原价" => $product_data['价格'],
                    "材质" => isset($product_data['style']) ? $product_data['style'] : 'other',
                    "长度" => ""
                ];
            }
        }
        if (in_array($categoryModel->dh_category_id, ['024020005001'])) {
            foreach ($product_data['属性'] as $k => $v) {
                $product_data['属性'][$k]['尺寸'] = empty($v['尺码']) ? 'other' : $v['尺码'];
            }
        }
        if (in_array($categoryModel->dh_category_id, ['024026007001', '024026007005'])) {
            foreach ($product_data['属性'] as $k => $v) {
                if (empty($v['尺寸'])) {
                    $product_data['属性'][$k]['尺寸'] = $v['尺码'];
                }
            }
        }
        if (in_array($categoryModel->dh_category_id, ['024020004003', '024034008001', '024023001003'])) {
            $product_data['color'] = [];
            foreach ($product_data['属性'] as $k => $v) {
                $product_data['color'][] = $v['颜色'];
            }
        }
    }

    private function cate024($categoryModel, &$product_data) {
        if (in_array($categoryModel->dh_category_id, ['024029008', '024029005004', '024034008001', '024034007002', '024023001020', '024023001005', '024023001013', '024023001003'])) {
            if (count($product_data['属性']) > 0) {
                foreach ($product_data['属性'] as $k => $v) {
                    if (!isset($v['颜色']) || empty($v['颜色'])) {
                        $product_data['属性'][$k]['颜色'] = 'other' . $k;
                        $product_data['属性'][$k]['图片'] = '';
                        $product_data['属性'][$k]['颜色id'] = 'other' . $k;
                    }
                    $product_data['属性'][$k]['颜色orign'] = trim((isset($v['颜色orign']) ? $v['颜色orign'] : '')
                            . (!empty($v['尺码']) ? ' ' . $v['尺码'] : '')
                            . (!empty($v['尺寸']) ? ' ' . $v['尺寸'] : '')
                            . (!empty($v['长度']) ? ' ' . $v['长度'] : '')
                            . (!empty($v['材质']) ? ' ' . $v['材质'] : ''), ' ');
                }
            } else {
                $product_data['属性'][] = [
                    "颜色" => "pic",
                    "图片" => array_slice($product_data['产品图片'], -1, 1)[0],
                    "颜色id" => "pic",
                    "颜色orign" => "pic",
                    "尺码id" => "",
                    "尺码" => "",
                    "尺寸id" => "",
                    "尺寸" => "",
                    "可用量" => "100",
                    "库存" => "100",
                    "折扣价" => $product_data['价格'],
                    "原价" => $product_data['价格'],
                    "材质" => "",
                    "长度" => ""
                ];
            }
        }
        if (in_array($categoryModel->dh_category_id, ['024020005001'])) {
            foreach ($product_data['属性'] as $k => $v) {
                if (empty($v['尺寸'])) {
                    $product_data['属性'][$k]['尺寸'] = 'other';
                }
            }
        }
        if (in_array($categoryModel->dh_category_id, ['024026007001', '024026007005'])) {
            foreach ($product_data['属性'] as $k => $v) {
                if (empty($v['尺寸'])) {
                    $product_data['属性'][$k]['尺寸'] = $v['尺码'];
                }
            }
        }
        if (in_array($categoryModel->dh_category_id, ['024020004003', '024034008001', '024023001003'])) {
            $product_data['color'] = [];
            foreach ($product_data['属性'] as $k => $v) {
                $product_data['color'][] = $v['颜色'];
            }
        }
    }

    private function cate005($categoryModel, &$product_data) {
        if (in_array($categoryModel->dh_category_id, ['005002'])) {
            if (count($product_data['属性']) == 0) {
                $product_data['属性'][] = [
                    "颜色" => "pic",
                    "图片" => array_slice($product_data['产品图片'], -1, 1)[0],
                    "颜色id" => "pic",
                    "颜色orign" => "pic",
                    "尺码id" => "",
                    "尺码" => "",
                    "尺寸id" => "",
                    "尺寸" => "",
                    "可用量" => "100",
                    "库存" => "100",
                    "折扣价" => $product_data['价格'],
                    "原价" => $product_data['价格'],
                    "材质" => "",
                    "长度" => ""
                ];
            } else {
                foreach ($product_data['属性'] as $k => $v) {
                    if (!isset($v['颜色']) || empty($v['颜色'])) {
                        $product_data['属性'][$k]['颜色'] = 'pic';
                        $product_data['属性'][$k]['图片'] = array_slice($product_data['产品图片'], -1, 1)[0];
                        $product_data['属性'][$k]['颜色id'] = 'pic';
                        $product_data['属性'][$k]['颜色orign'] = 'pic';
                    }
                }
            }
        }
        if (isset($product_data['dial diameter'])) {
            if (strpos($product_data['dial diameter'], 'inch') !== false) {
                $product_data['dial diameter'] = number_format(str_replace('inch', 'mm', $product_data['dial diameter']) * 25.4, 2) . 'mm';
            }
            $product_data['表面直径'] = $product_data['dial diameter'];
        } else {
            $product_data['表面直径'] = '0';
        }
    }

    private function cate006(&$categoryModel, $product_data) {
        if (in_array($categoryModel->dh_category_id, ['006003115'])) {
            if (!empty($product_data['属性'])) {
                foreach ($product_data['属性'] as $v) {
                    if (!empty($v['颜色'])) {
                        return;
                    }
                }
            }
        } else if (in_array($categoryModel->dh_category_id, ['006011126', '006011116', '006011117'])) {
            if (!empty($product_data['属性'])) {
                foreach ($product_data['属性'] as $v) {
                    if (!empty($v['颜色']) && !empty($v['尺寸'])) {
                        return;
                    }
                }
            }
        }
        $categoryModel->dh_category_id = '006003112';
    }

    private function cate135($categoryModel, &$product_data) {
        if (in_array($categoryModel->dh_category_id, ['135005006'])) {
            $type = (isset($product_data['type']) ? $product_data['type'] : '') . (isset($product_data['item type']) ? $product_data['item type'] : '');
            $title = strtolower($product_data['产品标题']);
            if (strpos($type, 'body') !== false) {
                $product_data['类型'] = 'full body';
            } elseif (strpos($type, 'front') !== false) {
                $product_data['类型'] = 'front';
            } elseif (strpos($type, 'mirror') !== false) {
                $product_data['类型'] = 'mirror';
            } elseif (strpos($type, 'glare') !== false) {
                $product_data['类型'] = 'anti-glare';
            } elseif (strpos($type, 'scratch') !== false) {
                $product_data['类型'] = 'anti-scratch';
            } elseif (strpos($title, 'body') !== false) {
                $product_data['类型'] = 'full body';
            } elseif (strpos($title, 'front') !== false) {
                $product_data['类型'] = 'front';
            } elseif (strpos($title, 'mirror') !== false) {
                $product_data['类型'] = 'mirror';
            } elseif (strpos($title, 'glare') !== false) {
                $product_data['类型'] = 'anti-glare';
            } elseif (strpos($title, 'scratch') !== false) {
                $product_data['类型'] = 'anti-scratch';
            }
            if (isset($product_data['类型'])) {
                foreach ($product_data['属性'] as $key => $value) {
                    $product_data['属性'][$key]['类型'] = $product_data['类型'];
                }
            }
        }
        if (in_array($categoryModel->dh_category_id, ['135005002', '135005006'])) {
            foreach ($product_data['属性'] as $key => $v) {
                $product_data['属性'][$key]['尺码'] = $v['材质'];
            }
        }
    }

    private function cate142($categoryModel, &$product_data) {
        if (in_array($categoryModel->dh_category_id, ['142003011'])) {
            if (!empty($product_data['属性'])) {
                foreach ($product_data['属性'] as &$v) {
                    $v['尺寸'] = $v['尺码'];
                    $v['尺寸id'] = $v['尺码id'];
                }
            }
        }
    }

    private function matchProduct($product, $product_data, $categoryModel) {
        $titleArr = explode(' ', $product_data['产品标题']);
        if (count($titleArr) > 0) {
            $titleCount = [];
            foreach ($titleArr as $k1 => $v) {
                $k = strtolower($v);
                if (isset($titleCount[$k])) {
                    $titleCount[$k] ++;
                } else {
                    $titleCount[$k] = 1;
                }
                if ($titleCount[$k] > 3) {
                    unset($titleArr[$k1]);
                }
            }
            $product_data['产品标题'] = implode(' ', $titleArr);
        }
        $tran_product_data = $this->mergeArr($product_data, $categoryModel->info_json);
        $tran_product_data['分类id'] = $categoryModel->dh_category_id;
        $tran_product_data['分类名称'] = $categoryModel->dest_category;
        $jsonStr = file_get_contents(MY_DOMAIN . '/product/getCateAttrL?catePubId=' . $categoryModel->dh_category_id);
        $needJson = json_decode($jsonStr, true);
        if (!empty($needJson['data']['attributeList'])) {
            $needJson = CommonFun::arrayColumns($needJson['data']['attributeList'], null, 'lineAttrNameCn');
        }
        foreach ($product_data as $key => $value) {
            if (preg_match('/[\x7f-\xff]/', $key)) {
                continue;
            }
            $this->addNeedWords($key, $value, $product->source_product_id, $tran_product_data, $tran_product_data['分类id']);
        }
        $needArr = [];
        $n = 0;
        $status = 1;
        $need_attribute = '';
        $ret = $this->colorSize($tran_product_data['属性'], $needJson);
        if ($ret == false) {
            $need_attribute.=' 产品规格|';
            $status = 0;
        }
        foreach ($needJson as $v) {
            if ($v['buyAttr'] == '1') {//in_array($v['lineAttrNameCn'], ['颜色', '尺码', '尺寸'])
                continue;
            }
            $needArr[$n] = [
                'need_key' => $v['lineAttrNameCn'],
                'required' => $v['required'],
                'is_exist' => '0'
            ];
            if (isset($tran_product_data[$v['lineAttrNameCn']])) {
                $needArr[$n]['is_exist'] = '1';
            }
            if ($needArr[$n]['is_exist'] != '1') {
                if (!empty($v['valueList'])) {
                    foreach ($v['valueList'] as $v1) {
                        if (in_array($v1['lineAttrvalNameCn'], $tran_product_data) || in_array(strtolower($v1['lineAttrvalName']), $tran_product_data)) {
                            if (isset($tran_product_data[$v['lineAttrNameCn']])) {
                                if (is_array($tran_product_data[$v['lineAttrNameCn']])) {
                                    $tran_product_data[$v['lineAttrNameCn']][] = $v1['lineAttrvalNameCn'];
                                } else {
                                    $tran_product_data[$v['lineAttrNameCn']] = [$tran_product_data[$v['lineAttrNameCn']], $v1['lineAttrvalNameCn']];
                                }
                            } else {
                                $tran_product_data[$v['lineAttrNameCn']] = $v1['lineAttrvalNameCn'];
                            }
                            $needArr[$n]['is_exist'] = '1';
                            if ($v['type'] != '1') {
                                break;
                            }
                        }
                    }
                }
            }
            if (($needArr[$n]['required'] == '1' || $v['isother'] == '1') && $needArr[$n]['is_exist'] != '1') {
                if ($v['isother'] == '0') {
                    $tran_product_data[$v['lineAttrNameCn']] = $v['valueList'][0]['lineAttrvalName'];
                } else {
                    $tran_product_data[$v['lineAttrNameCn']] = '自定义|other';
                }
            }
            $n++;
        }
        $tran_product_data['需要匹配'] = $needArr;
        if (count($product_data['产品图片']) < 3) {
            $status = '0';
            $need_attribute.='产品图片太少';
        }
        if ($status == '1') {
            $tran_product_data['匹配情况'] = '匹配成功';
        }
        $product->tran_product_data = json_encode($tran_product_data, JSON_UNESCAPED_UNICODE);
        $product->dh_category_id = $categoryModel->dh_category_id;
        $product->status = $status;
        $product->need_attribute = $need_attribute;
        $product->save();
        $this->echoJson(['status' => 'success', 'msg' => '成功']);
    }

    public function addNeedWords($key, $value, $source_product_id, &$tran_product_data, $catepubid) {
        $key = preg_replace('/(\s+)(\d+)$/', '', $key);
        $key = trim($key);
        $key = trim($key, '-');
        $key = preg_replace('/\d+$/', '', $key);
        if (in_array($key, ['brand name', 'model number', 'size', 'cn size', 'european size', 'eur size', 'euro size', 'size euro', 'shoes size'])) {
            return;
        }
        if (is_string($value)) {
            $str = $key . ':' . $value;
            $wordModel = \Words::findFirst([
                        'conditions' => 'orign_words = :orign_words:',
                        'bind' => [
                            'orign_words' => $str
                        ]
            ]);
            if ($wordModel == false) {
                if (preg_match('/^[\d|, |，|\.]+$/', $value)) {
                    return;
                }
                if ($key == 'gender') {
                    if (preg_match('/^men/', $value) || preg_match('/\smen/', $value) || preg_match('/^male/', $value) || preg_match('/\smale/', $value) || preg_match('/^man/', $value) || preg_match('/\sman/', $value)) {
                        $value = 'men';
                    }
                    if (strpos($value, 'women') !== false || strpos($value, 'woman') !== false || strpos($value, 'female') !== false) {
                        $value = 'women';
                    }
                }
                if (strpos($value, '(') === false && (strpos($value, ',') !== false || strpos($value, '，') !== false)) {
                    $value = str_replace('，', ',', $value);
                    $value = explode(',', $value);
                }
            }
        }
        $important = 3;
        $dest_words = '';
        if (is_string($value)) {
            $value = trim($value);
            if (empty($value)) {
                return;
            }
            if (empty($key) || preg_match('/^\d+$/', $key) || $key == 'type' || $key == 'style' || $key == 'function' || $key == 'feature' || $key == 'key word-' || $key == 'features') {
                $str = ':' . $value;
                $wordModels = \Words::find([
                            'conditions' => 'orign_words like :orign_words:',
                            'bind' => [
                                'orign_words' => '%' . $str
                            ],
                            'order' => 'status desc'
                ]);
                if (strlen($str) < 20) {
                    $important = 2;
                }
            } else {
                $str = $key . ':' . $value;
                $wordModels = \Words::find([
                            'conditions' => 'orign_words=:orign_words:',
                            'bind' => [
                                'orign_words' => $str
                            ]
                ]);
                if (strlen($str) < 30) {
                    $important = 2;
                }
            }
            $hasKeys = \Keyvalue::find([
                        'conditions' => 'key=:key:',
                        'bind' => [
                            'key' => $key
                        ],
                        'group' => 'keycn',
                        'columns' => 'keycn'
            ]);
            if (!empty($hasKeys)) {
                foreach ($hasKeys as $hasKey) {
                    $keyvalues = \Keyvalue::find([
                                'conditions' => 'key=:key: and keycn=:keycn: and value=:value:',
                                'bind' => [
                                    'key' => $key,
                                    'keycn' => $hasKey->keycn,
                                    'value' => $value
                                ]
                    ]);
                    if (!empty($keyvalues)) {
                        foreach ($keyvalues as $keyvalue) {
                            if ($keyvalue instanceof \Keyvalue) {
                                $dest_words .= ',' . $keyvalue->keycn . ':' . $keyvalue->valuecn;
                            }
                        }
                    } else {
                        $dest_words .= ',' . $hasKey->keycn . ':自定义|' . $value;
                    }
                }
            }
            if (empty($wordModels) && empty($dest_words)) {
                $wordModel = new \Words();
                $wordModel->orign_words = $str;
                $wordModel->dest_words = '';
                $wordModel->is_cate = 0;
                $wordModel->important = $important;
                $wordModel->status = 0;
                $wordModel->source_product_id = $source_product_id;
                $wordModel->createtime = date('Y-m-d H:i:s');
                $wordModel->catepubid = $catepubid;
                $wordModel->save();
            }
            if (!empty($wordModels)) {
                foreach ($wordModels as $wordModel) {
                    if ($wordModel instanceof \Words) {
                        $nCount = \NeedWords::count([
                                    'conditions' => 'words=:words:',
                                    'bind' => [
                                        'words' => $wordModel->orign_words
                                    ],
                                    'column' => 'DISTINCT source_product_id'
                        ]);
                        if ($nCount > 10) {
                            $important = 0;
                        } else if ($important == 2 && $nCount > 3) {
                            $important = 1;
                        }
                        if ($wordModel->status == 200) {
                            $dest_words .=',' . $wordModel->dest_words;
                        }
                        $wordModel->important = $important;
                        $wordModel->save();
                    }
                }
            }
            $needWords = \NeedWords::findFirst([
                        'conditions' => 'words=:words: and is_cate=0 and source_product_id=:source_product_id:',
                        'bind' => [
                            'words' => $str,
                            'source_product_id' => $source_product_id
                        ]
            ]);
            if ($needWords == false) {
                $needWorsModel = new \NeedWords();
                $needWorsModel->source_product_id = $source_product_id;
                $needWorsModel->words = $str;
                $needWorsModel->is_cate = 0;
                $needWorsModel->status = empty($dest_words) ? 0 : 200;
                $needWorsModel->createtime = date('Y-m-d H:i:s');
                $needWorsModel->save();
            }
            if ($dest_words != '') {
                $tran_product_data = $this->mergeArr($tran_product_data, $dest_words);
            }
        } else {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $this->addNeedWords($key, $v, $source_product_id, $tran_product_data, $catepubid);
                }
            }
        }
    }

    private function color($colorSizeArr) {
        foreach ($colorSizeArr as $key => $li) {
            if (empty($li['颜色'])) {
                unset($colorSizeArr[$key]);
            }
        }
        if (empty($colorSizeArr)) {
            return false;
        } else {
            return true;
        }
    }

    private function size($colorSizeArr) {
        foreach ($colorSizeArr as $key => $li) {
            if (empty($li['尺码'])) {
                unset($colorSizeArr[$key]);
            }
        }
        if (empty($colorSizeArr)) {
            return false;
        } else {
            return true;
        }
    }

    private function height($colorSizeArr) {
        foreach ($colorSizeArr as $key => $li) {
            if (empty($li['尺寸'])) {
                unset($colorSizeArr[$key]);
            }
        }
        if (empty($colorSizeArr)) {
            return false;
        } else {
            return true;
        }
    }

    private function material($colorSizeArr) {
        foreach ($colorSizeArr as $key => $li) {
            if (empty($li['材质'])) {
                unset($colorSizeArr[$key]);
            }
        }
        if (empty($colorSizeArr)) {
            return false;
        } else {
            return true;
        }
    }

    private function type($colorSizeArr) {
        foreach ($colorSizeArr as $key => $li) {
            if (empty($li['类型'])) {
                unset($colorSizeArr[$key]);
            }
        }
        if (empty($colorSizeArr)) {
            return false;
        } else {
            return true;
        }
    }

    public function colorSize($colorSizeArr, $needJson) {
        if (isset($needJson['颜色']) && $needJson['颜色']['buyAttr'] == '1') {
            $ret = $this->color($colorSizeArr);
            if ($ret == false) {
                return false;
            }
        }
        if (isset($needJson['尺码']) && $needJson['尺码']['buyAttr'] == '1') {
            $ret = $this->size($colorSizeArr);
            if ($ret == false) {
                return false;
            }
        }
        if (isset($needJson['尺寸']) && $needJson['尺寸']['buyAttr'] == '1') {
            $ret = $this->height($colorSizeArr);
            if ($ret == false) {
                return false;
            }
        }

        if (isset($needJson['材质']) && $needJson['材质']['buyAttr'] == '1') {
            $ret = $this->material($colorSizeArr);
            if ($ret == false) {
                echo 3;
                return false;
            }
        }

        if (isset($needJson['类型']) && $needJson['类型']['buyAttr'] == '1') {
            $ret = $this->type($colorSizeArr);
            if ($ret == false) {
                return false;
            }
        }
        return true;
    }

    public function mergeArr($arr, $jsonStr) {
        $arr1 = explode(',', trim($jsonStr, ','));
        if (!empty($arr1)) {
            foreach ($arr1 as $k => $v) {
                $arr2 = explode(':', $v);
                if (count($arr2) == 2) {
                    if (isset($arr[$arr2[0]]) && $arr[$arr2[0]] != $arr2[1]) {
                        if (!is_array($arr[$arr2[0]])) {
                            $arr[$arr2[0]] = [$arr[$arr2[0]]];
                        }
                        if (!in_array($arr2[1], $arr[$arr2[0]])) {
                            $arr[$arr2[0]][] = $arr2[1];
                        }
                    } else if (!isset($arr[$arr2[0]])) {
                        $arr[$arr2[0]] = $arr2[1];
                    }
                }
            }
        }
        return $arr;
    }

    public function updateAction() {
        $id = $this->request->get('id', 'int');
        $dest_words = $this->request->get('dest_words', 'string', '');
        $model = \Words::updateOne($id, $dest_words);
        if ($model != false) {
            $needList = \NeedWords::find([
                        'conditions' => 'words=:words: and is_cate=0',
                        'bind' => [
                            'words' => $model->orign_words
                        ]
            ]);
            foreach ($needList as $item) {
                $item->status = 200;
                $item->save();
                $queueUrl = MY_DOMAIN . '/lexicon/wordsMatch?source_product_id=' . $item->source_product_id;
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
            $this->echoJson(['status' => 'success', 'msg' => '修改成功']);
        } else {
            $this->echoJson(['status' => 'error', 'msg' => '修改失败']);
        }
    }

    public function createAction() {
        $orign_words = $this->request->get('orign_words', 'string', '');
        $dest_words = $this->request->get('dest_words', 'string', '');
        $model = \Words::createOne($orign_words, $dest_words);
        if ($model != false) {
            $this->echoJson(['status' => 'success', 'msg' => '创建成功', 'data' => $model->toArray()]);
        } else {
            $this->echoJson(['status' => 'error', 'msg' => '创建失败']);
        }
    }

    public $wordsList = [];

    public function autoTranAction() {
        set_time_limit(0);
        $this->wordsList = json_decode(file_get_contents(PUL_PATH . 'cate_words.json'), true);
        $wordsList = \Words::find([
                    'conditions' => 'status=0'
                ])->toArray();
        $curl = new Mcurl();
        $curl->maxThread = 5;
        foreach ($wordsList as $val) {
            $url = 'http://fanyi.baidu.com/sug?kw=' . $val['orign_words'];
            $curl->add(['url' => $url, 'args' => $val], [$this, 'match']);
        }
        $curl->start();
        exit();
    }

    public function match($res, $args) {
        $content = $res['content'];
        $arr = json_decode($content, true);
        if (isset($arr['errno']) && $arr['errno'] == 0) {
            if (isset($arr['data'][0]['k']) && strtolower($arr['data'][0]['k']) == strtolower($args['orign_words'])) {
                preg_match_all('/[\x7f-\xff]+/i', $arr['data'][0]['v'], $list);
                foreach ($list as $v) {
                    if (in_array($v, $this->wordsList)) {
                        $word = \Words::findFirst($args['id']);
                        $word->dest_words = $v;
                        $word->status = 200;
                        $word->save();
                    }
                }
            }
        }
    }

    public function selectWordAction() {
        $id = $this->request->get('id', 'int');
        $word = $this->request->get('word', 'string');
        $cate_id = $this->request->get('cate_id', 'string');
        $model = \Words::findFirst($id);
        $model->dest_words = $word;
        $model->catepubid = $cate_id;
        $model->status = 200;
        $model->save();
        $this->echoJson(['status' => 'success', 'msg' => '成功匹配']);
    }

    public function wordSimAction() {
        $words1 = $this->request->get('words1', 'string');
        $words2 = $this->request->get('words2', 'string');
        $data = BaiduFun::wordSimEmbedding($words1, $words2);
        $this->echoJson($data);
    }

    public function t1Action() {
        set_time_limit(100);
        $wordsList = \Words::find([
                    'limit' => 50
        ]);
        $file = PUL_PATH . 'cateArrL/141004.json';
        $json = json_decode(file_get_contents($file), true);
        $curl = new Mcurl();
        $curl->maxThread = 2;
        $num = 0;
        $max = 300;
        $wordsArr = array_column($json['data']['attributeList'], 'lineAttrNameCn');
        $wordsArr1 = array_column($json['data']['attributeList'], 'lineAttrName');
        foreach ($wordsList as $item) {
            $arr1 = explode(':', $item->orign_words);
            if (in_array($arr1[0], $wordsArr1)) {
                var_dump($arr1[0] . '|is true cate');
                echo '<br/>';
                continue;
            }
            $url = 'http://fanyi.baidu.com/sug?kw=' . $arr1[0];
            $ch = new \Lib\Vendor\Curl();
            $out = $ch->get($url);
            $d = json_decode($out, true);
            if (isset($d['data'][0]['v'])) {
                preg_match_all('/[\x7f-\xff]+/i', $d['data'][0]['v'], $list);
            } else {
                continue;
            }
            foreach ($list[0] as $v) {
                $arr2 = explode('，', $v);
                foreach ($arr2 as $v2) {
                    if (in_array($v2, $wordsArr)) {
                        var_dump($v2 . '|is true');
                        echo '<br/>';
                    } else {
                        foreach ($wordsArr as $v1) {
                            $curl->add(['url' => MY_DOMAIN . '/lexicon/wordSim?words1=' . $v2 . '&words2=' . $v1, 'args' => [$v2, $v1]], [$this, 't2']);
                            $num++;
                            if ($num > $max) {
                                break;
                            }
                        }
                    }
                }

                if ($num > $max) {
                    break;
                }
            }
            if ($num > $max) {
                break;
            }
        }
        $curl->start();
    }

    public function t2($res, $args) {
        var_dump($res['content'], $args);
        echo '<br/>';
        return;
    }

    public function t3Action() {
        $wordsList = \Words::find();
        $file = PUL_PATH . 'cateArrL/141004.json';
        $json = json_decode(file_get_contents($file), true);
        $curl = new Mcurl();
        $curl->maxThread = 3;
        $num = 0;
        $max = 10;
        foreach ($wordsList as $item) {
            $arr1 = explode(':', $item->orign_words);
            foreach ($json['data']['attributeList'] as $arr) {
                $curl->add(['url' => MY_DOMAIN . '/lexicon/wordSim?words1=' . $arr1[0] . '&words2=' . $arr['lineAttrNameCn']], [$this, 't2']);
                if (!empty($arr['valueList'])) {
                    foreach ($arr['valueList'] as $v) {
                        $str = $arr['lineAttrName'] . ':' . $v['lineAttrvalName'];
//                        BaiduFun::wordSimEmbedding($item->orgin_words, $str);

                        $curl->add(['url' => MY_DOMAIN . '/lexicon/wordSim?words1=' . $arr1[1] . '&words2=' . $v['lineAttrvalNameCn'], 'args' => [$arr1[1], $v['lineAttrvalNameCn']]], [$this, 't2']);
                        if ($num > $max) {
                            break;
                        }
                        $num++;
                    }
                }
                if ($num > $max) {
                    break;
                }
            }
            if ($num > $max) {
                break;
            }
        }
        $curl->start();
    }

    public function getAliAction() {
        set_time_limit(0);
        $this->getAli();
        echo 'success';
        exit();
    }

    public function getAli($catIds = 0) {
        $cookie = 'ali_apache_id=10.181.239.158.1530089215606.603925.0; cna=M/RVEwCFFWwCAXgplEjHFhd+; _ga=GA1.2.677428310.1530089231; RT="sl=0&ss=1532346992714&tt=0&obo=0&sh=&dm=aliexpress.com&si=0aef5063-b73e-4095-b5e4-aa75394ace6c&se=900&bcn=%2F%2F36fb619d.akstat.io%2F&r=https%3A%2F%2Fes.aliexpress.com%2Fitem%2FLALA-IKAI-Heels-Pointed-Toe-Women-Pumps-Ruffles-12-CM-Sexy-High-Heels-Buckle-Strap-Party%2F32887807552.html&ul=1532393684480&hd=1532393684554"; aep_history=keywords%5E%0Akeywords%09%0A%0Aproduct_selloffer%5E%0Aproduct_selloffer%0932795229294%0932832185077%0932887807552%0932788326030%0932845551897%0932776569755%0932851948064%0932846907021; aep_common_f=u5AiXQBl8srftiHgFSecZLdEg3l+JVidA691ahl0tL10tOrWeFPb6A==; UM_distinctid=164e180b20e8f-032efbd2826725-5e442e19-144000-164e180b20f2d9; l=AjU14eNeZwCmSL1IJwq6gOD7xbrvsunE; aep_usuc_t=ber_l=A0; ali_apache_tracktmp=W_signed=Y; intl_locale=zh_CN; _m_h5_tk=e9dc9ff6886d0082b7c2757dc090256e_1532958989122; _m_h5_tk_enc=a5c5569c8ef6dd27a3a63137a6188b96; xman_us_t=x_lid=cn1520479380atxs&sign=y&x_user=6pJGBWojaeD8ILx2ptbbO577hi8A3SO9gZdnv4cA3EM=&ctoken=aqwrl2e91r2i&need_popup=y&l_source=aliexpress; xman_f=IVf3EOtCdmcBqTohv6Vh3YGxXyWnBU7LHztuyCXAF6t4RTldZ4hMwtPywKEKX33ivAGn6hsm460JS3I5FDwx3b2HqwW0DE7YN7vVqzwsMthAiKxBBQDw2vxS/Yulp1ImB8K2PmMArxvM7ZREdtJThHi/pmd87LTAX2sfcTA+mjZVOhSIthJ5N/IqMgJf4VFmn1qHbbzTjoAEkQO9V4VaUdWavJChl0S/6pSBnqLHYKOm7k5HWHIYf5hrjNLeWPTYKOkdql13MPrYZrS91tg+LGzC4OP46YKtw11JQFSL3Z9PLQpvJitJvlyuRxY9khHUaiaqOEP1uEaaNkzFav8F45z0HgDcQ/MiH7/V2mR7O2T4cPoMsBXlKudWUi2muKEp3fDd9Rp20syK4qzaF0KPJ6rv2/eZTt6l; ali_apache_track=ms=|mt=2|mid=cn1520479380atxs; xman_us_f=zero_order=y&x_locale=zh_CN&x_l=1&last_popup_time=1532747477986&x_user=CN|Tom|Li|cnfm|230563257&no_popup_today=n; acs_usuc_t=x_csrf=i0mintr129wd&acs_rt=321c2cd436bf48ffb35e1ac74e84f871; aep_usuc_f=isfm=y&site=glo&c_tp=USD&x_alimid=230563257&iss=y&s_locale=zh_CN&region=US&b_locale=en_US; intl_common_forever=kop1OvcwKy5DHtjgA9IlnD1FUj/hMVJWx88J1/tp0ibxhj1fUTRYqw==; JSESSIONID=F9A16F5576DADF74BE56DD4346DD04F2; isg=BDEx7OUSzp-uxGKPWBixw4AvQL0Ltr31bAvI4RNGWPgXOlGMWm61YN87WY75rj3I; xman_t=G49n7tq5+UyugWS5slg2GKkqGPKWYQfUu8wEkGAKJF97tGIZeo48yxQKXpiKPLY+GJGjvWM+agyRrrWDdZ00swy3eRQ9S1L3ghN7spVQJkXNPPyOmdKQe59KQhfLKaCBXEhDC6nOioWFmd559qA/RPl/OXkg9VB3oBFahGtAxRED3Upmtga+yy/I5cL4SQ9SCF71oSlwXZtKpgyg6h6JeZ9RbWeocnAa1kY3t4pq0tgydNVmh2t9XgvgSprd2ypDXaYINw22gTaaPb9gJkNmEZaH3edDqK2hcRuh2sZxhUvwvwau1w3KFPaxPhrbOvAEm+43LqZdVuyO0UWJ4Qjl6hCNwJC3LAgZK0QJKxYP7U01Y3f+nzmh9w4f7ap1GXud1wo8pnTuUdDFLst33dKLGB8WkEkWxGnTmVRJOubqRp+0zS27VYrMtYW9tHR35jam2GyhqKK43PaaNP3n/7BskEXthvhk7Vfg8b/t5K7tkdDQTOMfxqUwOxtoCWBa3PI94rD2pr9ui5G/hlcTJEkc/OJEjC0k5+y/bokDZxTPV0T4IxwPx4vMyf6n8qNGKFQDsGJEmM2Cvgvfhs/kPQ/Yu/jzjgDqESWuvZbRwLaMTF++gsR34vorBhqIbK71qnn2g9QSnawBfgIEKOHZ4pgEQw==';
        $file = PUL_PATH . 'ali/' . $catIds . '.json';
        $has = false;
        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file), true);
            if ($json['success'] == true) {
                $has = true;
            }
        }
        $curl = new \Lib\Vendor\MyCurl();
        if ($has == false) {
            echo '错误';
            exit();
            $output = $curl->get('http://post.aliexpress.com/offer/category/ajaxGetProductCat.do?catIds=' . $catIds . '&locale=zh_CN', $cookie);
            @file_put_contents($file, $output);
            $json = json_decode($output, true);
        }
        if ($json['success'] == true && count($json['data']) > 0) {
            foreach ($json['data'] as $arr) {
                if (count($arr) > 0) {
                    foreach ($arr as $v) {
                        if ($v['isLeaf'] == false) {
                            $this->getAli($v['catId']);
                        } else {
                            $hasCate = \Categories::count([
                                        'conditions' => 'orign_category=:orign_category:',
                                        'bind' => [
                                            'orign_category' => $v['enName']
                                        ]
                            ]);
                            if ($hasCate == 0) {
                                $cateModel = new \Categories();
                                $cateModel->orign_category = trim(strtolower($v['enName']), ' ');
                                $cateModel->status = 0;
                                $cateModel->source_product_id = 0;
                                $cateModel->cn_name = $v['cnName'];
                                $cateModel->createtime = date('Y-m-d H:i:s');
                                $cateModel->save();
                            }
//                            $hasFile1 = false;
//                            $file1 = PUL_PATH . 'aliArrL/' . $v['catId'] . '.json';
//                            if (file_exists($file1)) {
//                                $str = file_get_contents($file1);
//                                if (!empty($str)) {
//                                    $hasFile1 = true;
//                                }
//                            }
//                            if ($hasFile1 == false) {
//                                $out = $curl->get('http://post.aliexpress.com/offer/post_ae_product.htm?catId=' . $v['catId'], $cookie);
////                                $data = \Lib\Vendor\CommonFun::getJsJson($out, 'window.pageConfig');
//                                @file_put_contents($file1, $out);
//                            }
                        }
                    }
                }
            }
        }
    }

    public function t2Action() {
        header('Content-Type: application/json; charset=utf-8');
        $file1 = PUL_PATH . 'aliArrL/200000392.json';
        $html = file_get_contents($file1);
        $html = preg_replace('/\s/', '', $html);
//        var_dump($html);exit();
        preg_match('/window.pageConfig=([^<]*);</i', $html, $arr);
//        $arr = \Lib\Vendor\CommonFun::getJsJson($html, 'window.pageConfig');
        echo $arr[1];
        exit();
    }

    public function t4Action() {
        set_time_limit(0);
        $list = \Words::find();
        foreach ($list as $item) {
            $important = 3;
            if (strpos($item->orign_words, ':') > 0) {
                if (strlen($item->orign_words) < 30) {
                    $important = 2;
                }
            } else {
                if (strlen($item->orign_words) < 30) {
                    $important = 2;
                }
            }
            $nCount = \NeedWords::count([
                        'conditions' => 'words=:words:',
                        'bind' => [
                            'words' => $item->orign_words
                        ],
                        'column' => 'DISTINCT source_product_id'
            ]);
            if ($nCount > 10) {
                $important = 0;
            } else if ($important == 2 && $nCount > 3) {
                $important = 1;
            }
            $item->important = $important;
            $item->save();
        }
        echo 'success';
        exit();
    }

    public function t5Action() {
        set_time_limit(0);
        $list = \Categories::find();
        foreach ($list as $item) {
            $nCount = \NeedWords::count([
                        'conditions' => 'words=:words:',
                        'bind' => [
                            'words' => $item->orign_category
                        ]
            ]);
            if ($nCount == 0) {
                $item->status = 400;
                $item->save();
            }
        }
        echo 'success';
        exit();
    }

    public function t6Action() {
        $list = \Product::find([
                    'conditions' => 'dh_category_id like "006003115%"'
        ]);
        foreach ($list as $item) {
//            $queueUrl = MY_DOMAIN . '/collection/hand?source_url=' . urlencode($item->source_url);
//            $queue = new \Queue();
//            $queue->queue_url = $queueUrl;
//            $queue->status = 0;
//            $queue->createtime = date('Y-m-d H:i:s');
//            $queue->contents = '采集';
//            $queue->save();
            $queueUrl = MY_DOMAIN . '/lexicon/wordsMatch?source_product_id=' . $item->source_product_id;
            $queue = new \Queue();
            $queue->queue_url = $queueUrl;
            $queue->status = 0;
            $queue->createtime = date('Y-m-d H:i:s');
            $queue->contents = '分类匹配成功,产品属性匹配';
            $queue->save();
        }
    }

    public function t7Action() {
        $list = \Product::find([
                    'conditions' => 'dh_category_id like "006%"'
        ]);
        foreach ($list as $item) {
            $queueUrl = MY_DOMAIN . '/collection/hand?source_url=' . urlencode($item->source_url);
            $queue = new \Queue();
            $queue->queue_url = $queueUrl;
            $queue->status = 0;
            $queue->createtime = date('Y-m-d H:i:s');
            $queue->contents = '采集';
            $queue->save();
        }
    }

    public function matchKeyvalueAction() {
        set_time_limit(0);
        $key = $this->request->get('key', 'string', '');
        $q = [
            'conditions' => 'status=0'
        ];
        if (!empty($key)) {
            $q['conditions'].=' and orign_words like "%' . $key . '%"';
        }
        $words = \Words::find($q);
        $num = 0;
        foreach ($words as $model) {
            $arr = explode(':', $model->orign_words);
            if (count($arr) != 2) {
                continue;
            }
            if (empty($arr[0])) {
                $count = \Keyvalue::count([
                            'conditions' => 'value=:value:',
                            'bind' => [
                                'value' => $arr[1]
                            ]
                ]);
                if ($count == 1) {
                    $keyvalue = \Keyvalue::findFirst([
                                'conditions' => 'value=:value:',
                                'bind' => [
                                    'value' => $arr[1]
                                ]
                    ]);
                    if ($keyvalue != false) {
                        $dest_words = $keyvalue->keycn . ':' . $keyvalue->valuecn;
                        file_get_contents(MY_DOMAIN . '/lexicon/update?id=' . $model->id . '&dest_words=' . $dest_words);
                        $num++;
                    }
                }
                continue;
            }
            $hasKey = \Keyvalue::findFirst([
                        'conditions' => 'key=:key:',
                        'bind' => [
                            'key' => $arr[0]
                        ]
            ]);
            if ($hasKey != false) {
                $keyvalue = \Keyvalue::findFirst([
                            'conditions' => 'key=:key: and value=:value:',
                            'bind' => [
                                'key' => $arr[0],
                                'value' => $arr[1]
                            ]
                ]);
                if ($keyvalue != false) {
                    $dest_words = $keyvalue->keycn . ':' . $keyvalue->valuecn;
                } else {
                    $dest_words = $hasKey->keycn . ':自定义|' . $arr[1];
                }
                file_get_contents(MY_DOMAIN . '/lexicon/update?id=' . $model->id . '&dest_words=' . $dest_words);
                $num++;
            }
        }
        echo 'success:' . $num;
        exit();
    }

    public function keyListAction() {
        $page = $this->request->get('page', 'int', 1);
        $size = 100;
        $all_num = $this->db->query('select count(*) as all_num from (select b.key from keyvalue b group by b.key,b.keycn) a')->fetch()['all_num'];
        $list = \Keyvalue::find([
                    'group' => 'key,keycn',
                    'order' => 'ctime desc',
                    'limit' => [
                        'number' => $size,
                        'offset' => ($page - 1) * $size
                    ],
                    'columns' => 'key,keycn,max(createtime) as ctime'
                ])->toArray();
        $pages = (object) [
                    'total_pages' => ceil($all_num / $size),
                    'last' => ceil($all_num / $size),
                    'current' => $page,
                    'all_num' => $all_num,
                    'items' => $list,
                    'before' => $page - 1,
                    'next' => $page + 1
        ];
        $this->view->pages = $pages;
        $this->view->page = $page;
    }

    public function addKeyAction() {
        $key = $this->request->get('key', 'string', '');
        $keycn = $this->request->get('keycn', 'string', '');
        $hasKey = \Keyvalue::count([
                    'conditions' => 'key=:key: and keycn=:keycn:',
                    'bind' => [
                        'key' => $key,
                        'keycn' => $keycn
                    ]
        ]);
        if ($hasKey > 0) {
            $this->echoJson(['status' => 'error', 'msg' => '已添加']);
        }
        $keyvalues = \Keyvalue::find([
                    'conditions' => 'keycn=:keycn:',
                    'bind' => [
                        'keycn' => $keycn
                    ],
                    'group' => 'value,valuecn',
                    'columns' => 'keycn,value,valuecn'
                ])->toArray();
        if (!empty($keyvalues)) {
            foreach ($keyvalues as $v) {
                $v['createtime'] = date('Y-m-d H:i:s');
                $v['key'] = strtolower($key);
                $model = new \Keyvalue();
                $model->save($v);
            }
        }
        file_get_contents(MY_DOMAIN . '/lexicon/matchKeyvalue?key=' . strtolower($key));
        $this->echoJson(['status' => 'success', 'msg' => '添加成功']);
    }

    public function delKeyAction() {
        $key = $this->request->get('key', 'string', '');
        $keycn = $this->request->get('keycn', 'string', '');
        $hasKeys = \Keyvalue::find([
                    'conditions' => 'key=:key: and keycn=:keycn:',
                    'bind' => [
                        'key' => $key,
                        'keycn' => $keycn
                    ]
        ]);
        if (empty($hasKeys)) {
            $this->echoJson(['status' => 'error', 'msg' => '未找到该Key']);
        }
        foreach ($hasKeys as $hasKey) {
            $hasKey->del();
        }
        $this->echoJson(['status' => 'success', 'msg' => '删除成功']);
    }

    public function addRefreshQueuesAction() {
        $ids = $this->request->get('ids');
        if (empty($ids)) {
            $this->echoJson(['status' => 'error', 'msg' => '刷新产品数为空']);
        }
        $list = \Product::find([
                    'conditions' => 'id in ({ids:array})',
                    'bind' => [
                        'ids' => $ids
                    ]
        ]);
        $eNum = 0;
        $sNum = 0;
        foreach ($list as $item) {
            $queueUrl = MY_DOMAIN . '/lexicon/wordsMatch?source_product_id=' . $item->source_product_id;
            $queue = new \Queue();
            $queue->queue_url = $queueUrl;
            $queue->status = 0;
            $queue->createtime = date('Y-m-d H:i:s');
            $queue->contents = '分类匹配成功,产品属性匹配';

            $ret = $queue->save();
            if ($ret == false) {
                $eNum++;
            } else {
                $sNum++;
            }
        }

        $msg = '添加' . $sNum . '条队列成功,' . $eNum . '失败';
        $this->echoJson(['status' => 'success', 'msg' => $msg]);
    }

}
