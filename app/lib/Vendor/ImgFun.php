<?php

namespace Lib\Vendor;

class ImgFun {

    public static function downLoad($url) {
        $type = substr($url, strrpos($url, '.'));
        $filename = md5($url);
        $path = PUL_PATH . 'img/' . $filename . $type;
        if (file_exists($path)) {
            return $filename . $type;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (0 === strpos(strtolower($url), 'https')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //从证书中检查SSL加密算法是否存在
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        $file = curl_exec($ch);
        curl_close($ch);
        $resource = fopen($path, 'a');
        fwrite($resource, $file);
        fclose($resource);
        return $filename . $type;
    }

    public static function upload($file, $token, $supplierid, $funtionname = 'albu', $imagebannername = '') {
        $data = ['file' => '@' . $file];
        $url = 'http://upload.dhgate.com/uploadfile?functionname=' . $funtionname . '&supplierid=' . $supplierid . '&imagebannername=' . $imagebannername . '&token=' . $token;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if (0 === strpos(strtolower($url), 'https')) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); //对认证证书来源的检查
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); //从证书中检查SSL加密算法是否存在
        }
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $content = curl_exec($curl);
        curl_close($curl);
        return json_decode($content, true);
    }

}
