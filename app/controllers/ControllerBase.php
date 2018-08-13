<?php

namespace Dh\Controllers;

use Phalcon\Mvc\Controller;
use Lib\Vendor\CommonFun;

class ControllerBase extends Controller {

    public $username = 'lakeone';
    public $password = 'lk123456';

    public function initialize() {
        $users = [
            'lakeone' => 'lk123456',
            'ceshi'=>'123'
        ];
        if (isset($_COOKIE['current_user']) && isset($users[$_COOKIE['current_user']])) {
            $this->username = $_COOKIE['current_user'];
            $this->password = $users[$_COOKIE['current_user']];
        }

        if (isset($_GET['current_user']) && isset($users[$_GET['current_user']])) {
            $this->username = $_GET['current_user'];
            $this->password = $users[$_GET['current_user']];
        }
        $this->view->users = $users;
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
            if ($cookiesModel->cookies_time > (time() - 3600)) {
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
        $cookiesModel->cookies_time = time();
        $cookiesModel->cookies = $cookie;
        $cookiesModel->updatetime = date('Y-m-d H:i:s');
        $cookiesModel->save();
        $this->cookies_arr = $cookiesModel->toArray();
        return true;
    }

    protected function getUrl($url, &$cookie, $post_data = '') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
