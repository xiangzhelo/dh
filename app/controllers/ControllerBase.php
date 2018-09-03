<?php

namespace Dh\Controllers;

use Phalcon\Mvc\Controller;
use Lib\Vendor\CommonFun;

class ControllerBase extends Controller {

    public $username = 'lakeone';
    public $password = 'lk123456';
    public $access_token;
    public $users = [
        'lakeone' => 'lk123456',
        'oldriver' => 'lk171001',
        'starone' => 'lk171001',
        'missyou2016' => 'lk171001',
        'kebe1' => 'lk171001',
        'ksld' => 'lk171001',
        'walon123' => 'lk171001'
    ];
    public $priceFormula = 'x*2.2';
    public $header = '';

    public function initialize() {
        $this->header = ['X-FORWARDED-FOR:' . CommonFun::Rand_IP(), 'CLIENT-IP:' . CommonFun::Rand_IP()];
        if ($_GET['_url'] != '/setting/setUpdate') {
            $file = APP_PATH . 'config/setting.json';
            if (!file_exists($file)) {
                file_get_contents(MY_DOMAIN . '/setting/setUpdate');
            }
            $json = json_decode(file_get_contents($file), true);
            $this->users = $json['users'];
            $this->priceFormula = $json['priceFormula'];
        }
        if (isset($_COOKIE['current_user']) && isset($this->users[$_COOKIE['current_user']])) {
            $this->username = $_COOKIE['current_user'];
            $this->password = $this->users[$_COOKIE['current_user']];
        }

        if (isset($_GET['current_user']) && isset($this->users[$_GET['current_user']])) {
            $this->username = $_GET['current_user'];
            $this->password = $this->users[$_GET['current_user']];
        }
        $this->view->users = $this->users;
        $this->view->current_user = $this->username;
    }

    protected function echoJson($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function hasLogin($username) {
        $cookiesModel = \Cookies::findFirst([
                    'conditions' => 'username=:username:',
                    'bind' => [
                        'username' => $username
                    ]
        ]);
        if ($cookiesModel instanceof \Cookies) {
            if ($cookiesModel->cookies_time > (time() - 3600) && !empty($cookiesModel->cookies)) {
                return true;
            }
        }
        return false;
    }

    protected function getUserCookie($username) {
        $cookiesModel = \Cookies::findFirst([
                    'conditions' => 'username=:username:',
                    'bind' => [
                        'username' => $username
                    ]
        ]);
        if ($cookiesModel instanceof \Cookies) {
            $this->access_token = $cookiesModel->access_token;
            return $cookiesModel->cookies;
        } else {
            return '';
        }
    }

    protected function loginDh($username, $password) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://secure.dhgate.com/passport/login?service=http%3A%2F%2Fseller.dhgate.com%2Fmerchant%2Flogin%2Fssologin.do%3FreturnUrl%3DaHR0cDovL3NlbGxlci5kaGdhdGUuY29tL21lcmNoYW50L2xvZ2luL2xvZ2luc2lnbi5kbw..#hp-trade-6'); //
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //从证书中检查SSL加密算法是否存在
        if (!empty($this->header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->header);
        }
        $content = curl_exec($ch);
        curl_close($ch);
        list($header, $body) = explode("\r\n\r\n", $content);
        $cookie = '';
        $arr = CommonFun::getCookieName($header);
        foreach ($arr as $v) {
            $cookie .= $v . '=' . CommonFun::getCookie($header, $v) . ';';
        }
        $post_data = 'tokenidx=' . CommonFun::getCookie($header, 'JSESSIONID') . '&username=' . $username . '&password=' . $password . '&code=&errorCode=passportusertype=1&visitaliflag=&service=http://seller.dhgate.com/merchant/login/ssologin.do';
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, 'https://secure.dhgate.com/passport/sigin');
        curl_setopt($ch1, CURLOPT_HEADER, 1);
        if (!empty($this->header)) {
            curl_setopt($ch1, CURLOPT_HTTPHEADER, $this->header);
        }
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch1, CURLOPT_POST, 1);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检查
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0); //从证书中检查SSL加密算法是否存在
        $content1 = curl_exec($ch1);
        curl_close($ch1);
        list($header1, $body) = explode("\r\n\r\n", $content1);
        $arr1 = CommonFun::getCookieName($header1);
        foreach ($arr1 as $v) {
            $cookie .= $v . '=' . CommonFun::getCookie($header, $v) . ';';
        }
        $url = CommonFun::getLocation($header1);
        $this->getUrl($url, $cookie);
        $cookiesModel = \Cookies::findFirst([
                    'conditions' => 'username=:username:',
                    'bind' => [
                        'username' => $username
                    ]
        ]);
        if (!$cookiesModel instanceof \Cookies) {
            $cookiesModel = new \Cookies();
            $cookiesModel->username = $username;
            $cookiesModel->createtime = date('Y-m-d H:i:s');
        }
        $tkUrl = 'https://secure.dhgate.com/dop/oauth2/access_token?grant_type=password&username=' . $username . '&password=' . $password . '&client_id=90IjdPyeufNuPJFUd40C&client_secret=4eNrNBz3q1yLOvi12bMcs0Yt9KI9CtpF&scope=basic';
        $curl = new \Lib\Vendor\Curl();
        $jsonStr = $curl->get($tkUrl, null, 30);
        $json = json_decode($jsonStr, true);
        $cookiesModel->cookies_time = time();
        $cookiesModel->cookies = $cookie;
        $cookiesModel->access_token = $json['access_token'];
        $cookiesModel->updatetime = date('Y-m-d H:i:s');
        $cookiesModel->save();
        $this->cookies_arr = $cookiesModel->toArray();
        $this->access_token = $cookiesModel->access_token;
        return true;
    }

    protected function getUrl($url, &$cookie, $post_data = '') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!empty($this->header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->header);
        }
        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if (0 === strpos(strtolower($url), 'https')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //从证书中检查SSL加密算法是否存在
        }
        if (!empty($post_data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        $content = curl_exec($ch);
        curl_close($ch);
        list($header, $body) = explode("\r\n\r\n", $content);
        $arr = CommonFun::getCookieName($header);
        foreach ($arr as $v) {
            $cookie .= $v . '=' . CommonFun::getCookie($header, $v) . ';';
        }
        return CommonFun::getLocation($header);
    }

}
