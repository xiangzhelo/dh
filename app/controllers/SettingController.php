<?php

namespace Dh\Controllers;

/**
 * Description of SettingController
 *
 * @author 94946
 */
class SettingController extends ControllerBase {

    public function indexAction() {
        $file = APP_PATH . 'config/setting.json';
        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file), true);
        } else {
            $json = [
                'switch' => '0',
                'time' => '3',
                'queueNum' => '3',
                'run' => '0'
            ];
        }
        $this->view->json = $json;
    }

    public function setUpdateAction() {
        $sw = $this->request->get('sw', 'int', '0');
        $t = $this->request->get('t', 'int', '3');
        $queueNum = $this->request->get('queueNum', 'int', '3');
        if (($sw == 1 || $sw == 0) && $t > 0 && $t < 60 && $queueNum > 0 && $queueNum < 8) {
            $file = APP_PATH . 'config/setting.json';
            if (file_exists($file)) {
                $json = json_decode(file_get_contents($file), true);
            } else {
                $json = [
                    'run' => '0'
                ];
            }
            if ($json['run'] == 1) {
                $log = \Log::findFirst(['order' => 'id desc']);
                if (time() - strtotime($log->createtime) > 60 && time() - strtotime($log->createtime) > $json['time'] * 2) {
                    $json['run'] = 0;
                }
            }
            $data = ['switch' => $sw, 'time' => $t, 'queueNum' => $queueNum, 'run' => $json['run']];
            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
            $this->echoJson(['status' => 'success', 'msg' => '配置成功', 'run' => $json['run']]);
        } else {
            $this->echoJson(['status' => 'error', 'msg' => '配置错误']);
        }
    }

    public function queueAction() {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ignore_user_abort();
        $file = APP_PATH . 'config/setting.json';
        $json = json_decode(file_get_contents($file), true);
        $json['run'] = 1;
        file_put_contents($file, json_encode($json, JSON_UNESCAPED_UNICODE));
        do {
            $json = json_decode(file_get_contents($file), true);
            if ($json['switch'] == 0) {
                $json['run'] = 0;
                file_put_contents($file, json_encode($json, JSON_UNESCAPED_UNICODE));
                die('process abort');
            }
            $this->queue($json['queueNum']);
            \Log::createOne('定时任务:' . time(), '定时任务');
            sleep($json['time']);
        } while (true);
    }

    public function queue($qnum) {
        try {
//            $queues = \Queue::findFirst([
//                        'conditions' => 'status=0 or status=400',
//                        'order' => 'status asc,id asc',
//            ]);
//
//            if ($queues instanceof \Queue) {
//                $output = @file_get_contents($queues->queue_url);
//                $this->queueCallBack1($output, ['id' => $queues->id]);
//            }

            $queues = \Queue::find([
                        'conditions' => 'status=0 or status=400',
                        'order' => 'status asc,id asc',
                        'limit' => $qnum
            ]);

            if (empty($queues)) {
                return;
            }
            $mcurl = new \Lib\Vendor\Mcurl();
            $mcurl->maxThread = $qnum;
            $mcurl->maxTry = 0;
            $num = 0;
            foreach ($queues as $item) {
                if ($item->status == 0) {
                    $item->status = 1;
                } else if ($item->status == 400) {
                    $item->status = 401;
                } else {
                    continue;
                }
                $item->save();
                $url = $item->queue_url;
                $mcurl->add(['url' => $url, 'args' => ['id' => $item->id]], [$this, 'queueCallBack'], [$this, 'queueFalse']);
                $num++;
            }
            if ($num > 0) {
                $mcurl->start();
            }
        } catch (Exception $e) {
            \Log::createOne('错误：' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function queueCallBack($res, $args) {
        if (json_decode($res['content'], true)['status'] == 'success') {
            $queue = \Queue::findFirst($args['id']);
            $queue->status = 200;
            $queue->save();
            \Log::createOne('URL:' . $queue->queue_url . ',返回:' . $res['content'], 'queue');
        } else {
            $queue = \Queue::findFirst($args['id']);
            if ($queue->status == 401) {
                $queue->status = 402;
            } else {
                $queue->status = 400;
            }
            $queue->save();
            \Log::createOne('URL:' . $queue->queue_url . ',返回:' . $res['content'], 'queue');
        }
    }

    public function queueCallBack1($content, $args) {
        if (json_decode($content, true)['status'] == 'success') {
            $queue = \Queue::findFirst($args['id']);
            $queue->status = 200;
            $queue->save();
            \Log::createOne('URL:' . $queue->queue_url . ',返回:' . $content, 'queue');
        } else {
            $queue = \Queue::findFirst($args['id']);
            if ($queue->status == 401) {
                $queue->status = 402;
            } else {
                $queue->status = 400;
            }
            $queue->save();
            \Log::createOne('URL:' . $queue->queue_url . ',返回:' . $content, 'queue');
        }
    }

    public function queueFalse($res, $args) {
        $queue = \Queue::findFirst($args['id']);
        if ($queue == false) {
            return;
        }
        if ($queue->status == 1) {
            $queue->status = 400;
        } elseif ($queue->status == 401) {
            $queue->status = 402;
        }
        $queue->save();
        \Log::createOne('URL:' . $queue->queue_url . ',返回:' . json_encode($res, JSON_UNESCAPED_UNICODE), 'queueFalse');
    }

    public function addQueueAction() {
        $queueUrl = $this->request->get('queue_url');
        $content = $this->request->get('content', 'string');
        if (empty($queueUrl)) {
            $this->echoJson(['status' => 'error', 'msg' => '链接不可为空']);
        }
        $sNum = 0;
        $eNum = 0;
        if (is_array($queueUrl)) {
            foreach ($queueUrl as $v) {
                $queue = new \Queue();
                $queue->queue_url = $v;
                $queue->status = 0;
                $queue->createtime = date('Y-m-d H:i:s');
                $queue->contents = $content;
                $ret = $queue->save();
                if ($ret == false) {
                    $eNum++;
                } else {
                    $sNum++;
                }
            }
            $msg = '添加' . $sNum . '条队列成功,' . $eNum . '失败';
        } else {
            $queue = new \Queue();
            $queue->queue_url = $queueUrl;
            $queue->status = 0;
            $queue->createtime = date('Y-m-d H:i:s');
            $queue->contents = $content;
            $ret = $queue->save();
            if ($ret == false) {
                $this->echoJson(['status' => 'error', 'msg' => '添加队列失败']);
            }
            $msg = '添加完成';
        }
        $this->echoJson(['status' => 'success', 'msg' => $msg]);
    }

}
