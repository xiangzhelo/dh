<?php

namespace Lib\Vendor;

require_once(APP_PATH . '/lib/BaiduAi/AipBCEUtil.php');
require_once(APP_PATH . '/lib/BaiduAi/AipBase.php');
require_once(APP_PATH . '/lib/BaiduAi/AipHttpClient.php');
require_once(APP_PATH . '/lib/BaiduAi/AipNlp.php');

class BaiduFun {

    public static function init($name) {
        set_time_limit(0);
        $app_id = '10668589'; //'10709476';
        $app_key = 'qpOiesSjNTzDKUCfmaAd1hCw';
        $secret_key = 'tyyLcYe3HRWevWzpZVcaQiyt7KiC3pf8';
        return new $name($app_id, $app_key, $secret_key);
    }

    public static function wordSimEmbedding($words1, $words2) {
        $client = self::init('AipNlp');
        $arr = $client->wordSimEmbedding($words1, $words2);
        return $arr;
//        exit();
    }

    public static function multiEmbedding() {
        $data = array();
        $data['word_1'] = $word1;
        $data['word_2'] = $word2;
        $data = array_merge($data, $options);
        $data = mb_convert_encoding(json_encode($data), 'GBK', 'UTF8');
        return $this->request('https://aip.baidubce.com/rpc/2.0/nlp/v2/word_emb_sim', $data);
    }

}
