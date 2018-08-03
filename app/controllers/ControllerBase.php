<?php

namespace Dh\Controllers;

use Phalcon\Mvc\Controller;
use Lib\Vendor\CommonFun;

class ControllerBase extends Controller {

    public function initialize() {
        
    }

    protected function echoJson($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function hasLogin() {
        $url = 'http://seller.dhgate.com/mydhgate/menuv2.do?act=ajaxGetQuickMenuList';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $cookie = @file_get_contents(PUL_PATH . $username . '_cookie.txt');
        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if (0 === strpos(strtolower($url), 'https')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //从证书中检查SSL加密算法是否存在
        }
        $content = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($content, true);
        if (isset($json['queryMenuList'])) {
            return true;
        } else {
            return false;
        }
    }

    protected function getUserCookie($username) {
        $cookie = file_get_contents(PUL_PATH . $username . '_cookie.txt');
        return $cookie;
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
        @file_put_contents(PUL_PATH . $username . '_cookie.txt', $cookie);
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
