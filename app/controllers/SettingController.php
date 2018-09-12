<?php

namespace Dh\Controllers;

use Lib\Vendor\CommonFun;

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
                'run' => '0',
                'priceFormula' => 'x*2.2',
                'users' => ["lakeone" => "lk123456", "oldriver" => "lk171001", "starone" => "lk171001", "missyou2016" => "lk171001", "kebe1" => "lk171001", "ksld" => "lk171001", "walon123" => "lk171001"]
            ];
        }
        if ($json['switch'] == 1) {
            $log = \Log::findFirst(['order' => 'id desc']);
            if (time() - strtotime($log->createtime) > 60 && time() - strtotime($log->createtime) > $json['time'] * 2) {
                $json['switch'] = 0;
            }
        }
        $this->view->json = $json;
    }

    public function setUpdateAction() {
        $sw = $this->request->get('sw', 'int', '0');
        $t = $this->request->get('t', 'int', '3');
        $queueNum = $this->request->get('queueNum', 'int', '3');
        $priceFormula = $this->request->get('priceFormula', 'string', 'x*2.2');
        eval(str_replace('x', 1, '$price=' . $priceFormula . ';'));
        $users = htmlspecialchars_decode($this->request->get('users', 'string', '{"lakeone": "lk123456","oldriver": "lk171001","starone": "lk171001","missyou2016": "lk171001","kebe1": "lk171001","ksld": "lk171001","walon123": "lk171001"}'));
        if (($sw == 1 || $sw == 0) && $t > 0 && $t < 60 && $queueNum > 0 && $queueNum < 11 && $price > 1) {
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
            $users = json_decode($users, true);
            $data = ['switch' => $sw, 'time' => $t, 'queueNum' => $queueNum, 'run' => $json['run'], 'priceFormula' => empty($priceFormula) ? $json['priceFormula'] : $priceFormula, 'users' => empty($users) ? $json['users'] : $users];
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
            $this->db->execute('update queue set status=400 where status=1 and start_time<"' . date('Y-m-d H:i:s', strtotime('-30 minutes')) . '"');
            $this->db->execute('update queue set status=402 where status=401 and start_time<"' . date('Y-m-d H:i:s', strtotime('-30 minutes')) . '"');
            $qCount = \Queue::count([
                        'conditions' => 'status in (0,1,400,401)'
            ]);
            if ($qCount == 0) {
                $this->db->execute('update product set status=1 where status=4');
            }
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
                        'limit' => $qnum * 10
            ]);

            if (empty($queues)) {
                return;
            }
            $mcurl = new \Lib\Vendor\Mcurl();
            $mcurl->maxThread = $qnum;
            $mcurl->maxTry = 0;
            $mcurl->opt[CURLOPT_TIMEOUT] = 300;
            $num = 0;
            foreach ($queues as $item) {
                $item->start_time = date('Y-m-d H:i:s');
                if ($item->status == 0) {
                    $item->status = 1;
                } else if ($item->status == 400 || $queue->status == 402) {
                    $item->status = 401;
                } else {
                    continue;
                }
                $ret = $item->save();
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
            if ($queue->status == 401 || $queue->status == 402) {
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

    public function setUserAction() {
        $user = $this->request->get('user', 'string', 'lakeone');
        $hasLogin = $this->hasLogin($user);
        if ($hasLogin == false && $this->users[$user]) {
            $this->loginDh($user, $this->users[$user]);
        }
        $this->cookie = $this->getUserCookie($user);
        $this->supplierid = CommonFun::getCookieValueByKey($this->cookie, 'supplierid');
        setcookie('current_user', $user, time() + 3600 * 24 * 365, '/', '.dhgate.com');
        preg_match_all('/([^;]+);/i', $this->cookie, $arr);
        foreach ($arr[1] as $v) {
            $v1 = explode('=', $v);
            setcookie($v1[0], $v1[1], time() + 3600 * 24, '/', '.dhgate.com');
        }
        $this->echoJson(['status' => 'success', 'msg' => '设置成功']);
    }

    public function getQueueCountAction() {
        $count = \Queue::count([
                    'conditions' => 'status in (0,1,400,401)'
        ]);
        $countList = \Queue::find([
                    'conditions' => 'status in (0,1,400,401)',
                    'group' => 'contents',
                    'columns' => 'contents,count(id) as num'
                ])->toArray();
        $this->echoJson(['status' => 'success', 'msg' => '队列数', 'data' => $count, 'list' => $countList]);
    }

    public function t1Action() {
        $curl = new \Lib\Vendor\MyCurl();
//        $url = 'https://game.weixin.qq.com/cgi-bin/gamewap/getusermobagameindex?offset=0&limit=10&openid=owanlsq4H4TQTI7xibhcRTt-u1BY&uin=&key=&pass_ticket=L%2FulwR7h5hVsZ3EoVT8nhldtkFy0AIvPrKi%2FVluzPWSHcneSULlfQk8GFyexoNVn&QB&';  //用户信息
        $url = 'https://game.weixin.qq.com/cgi-bin/gamewap/getusermobabattleinfolist?offset=0&limit=10&openid=owanlsq4H4TQTI7xibhcRTt-u1BY&zone_area_id=3080&uin=&key=&pass_ticket=L%2FulwR7h5hVsZ3EoVT8nhldtkFy0AIvPrKi%2FVluzPWSHcneSULlfQk8GFyexoNVn&QB&';  //用户比赛
        $cookies = 'key=9eeb6fa775f105e0770a0cb43fba759813d9bb72db7315c0d7accdf437a5b1704ac59748612c7650a086557b8c62a9f6c69bd86618db5ece790478e5e8e33dc78efbf48e69dc344acc4797a757a7fb0d; pass_ticket=L%2FulwR7h5hVsZ3EoVT8nhldtkFy0AIvPrKi%2FVluzPWSHcneSULlfQk8GFyexoNVn; uin=MTI5MDMzNTA2MA%3D%3D; pgv_info=pgvReferrer=https://qt.qq.com/v/hero/h5_player.html&ssid=s6564142384; pgv_pvid=5610868640; pgv_si=s6414125056; pgv_pvi=9364937728; sd_cookie_crttime=1529056056834; sd_userid=13581529056056834';
        $json = $curl->get($url, $cookies);
        $this->echoJson(json_decode($json, true));
    }

    public function t2Action() {
        phpinfo();
        exit();
        $this->echoJson($_POST);
    }

}
