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
        if (($sw == 1 || $sw == 0) && $t > 1 && $queueNum > 0) {
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
        $queues = \Queue::find([
                    'conditions' => 'status=0',
                    'order' => 'id asc',
                    'limit' => $qnum
        ]);
        $mcurl = new \Lib\Vendor\Mcurl();
        $mcurl->maxThread = $qnum;
        $mcurl->maxTry = 0;
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
        \Log::createOne('URL:' . $queue->queue_url . ',返回:' . $res['content'], 'queue');
    }

}
