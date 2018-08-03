<?php

namespace Dh\Controllers;

class IndexController extends ControllerBase {

    public function indexAction() {
        
    }

    public function getCollectionAction() {
        $url = $this->request->get('url');
    }

    public function t4Action() {
//        header('Content-Type: text/html; charset=utf-8');
        header('Content-Type: application/json; charset=utf-8');
        $url = 'http://seller.dhgate.com/syi/cateAttrL.do?catePubId=141006&isblank=true';
//        $url = 'http://seller.dhgate.com/syi/categorybyid.do?isblank=true&catePubId=141';
        $cookie = file_get_contents(PUL_PATH . 'cookie.txt');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if (0 === strpos(strtolower($url), 'https')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //从证书中检查SSL加密算法是否存在
        }
        $content = curl_exec($ch);
        curl_close($ch);
//        var_dump($content);
        echo $content;
        exit();
    }

}
