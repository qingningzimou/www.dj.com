<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/4/14
 * Time: 20:26
 */

namespace app\common\controller;
use sm\TxSm4;


class GmCrypt
{
    /**
     * 加密数据
     * @param string $time
     * @param string $user_id
     * @param string $sub
     * @return string
     */
    public function gmEncrypt($time,$user_id,$sub) {
        $secret_key = md5('Txzn-Edu-Deg-Key');
        $sm4 = new TxSm4();
        $header = array(
            'sub' => $sub,
            'time' => $time,
            'user_id' => $user_id,
        );
        return $sm4->encrypt($secret_key, json_encode($header));
    }

    /**
     * 解密数据
     * @param string $data
     * @return array
     */
    public function gmDecrypt($data) {
        $secret_key = md5('Txzn-Edu-Deg-Key');
        $sm4 = new TxSm4();
        return json_decode($sm4->decrypt($secret_key, $data),true);
    }

    /**
     * 生成TOKEN
     * @param string $user_id
     * @return string
     */
    public function setToken($user_id)
    {
        $token_expire = 7200;
        $sub = substr(md5(time()),0,8);
        $time = strtotime('+'.$token_expire.' second');
        $str = $this->gmEncrypt($time,$user_id,$sub);
        return $str;
    }
    /**
     * 检查TOKEN过期
     * @param string $user_id
     * @return string
     */
    public function checkExpire($data){
        $flag = false;
        $time = time();
        $tmp = $this->gmDecrypt($data);
        if (is_array($tmp)){
            if ($time >  $tmp['time']){
                $flag = true;
            }
        }
        return $flag;
    }
    /**
     * 检查TOKEN刷新
     * @param string $user_id
     * @return string
     */
    public function checkRefreshExpire($data){
        $flag = false;
        $time = time();
        $tmp = $this->gmDecrypt($data);
        if (is_array($tmp)){
            if ($tmp['time'] - $time < 3600){
                $flag = true;
            }
        }
        return $flag;
    }
}