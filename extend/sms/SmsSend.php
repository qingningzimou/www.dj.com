<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/20
 * Time: 21:35
 */

namespace sms;
use think\facade\Lang;
use think\facade\Cache;

class SmsSend
{
    public function send_sms($mobile, $message, $timeout = 3)
    {
        try {
            $url = Cache::get('sms_url');
            $key = Cache::get('sms_key');
            $url = 'https://sms-api.luosimao.com/v1/send.json';
            $key = '82a6e4e00d876a9c5a1580b42545f0b5';
            $data = array(
                'mobile' => $mobile,
                'message' => $message
            );
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_USERAGENT, 'OpsHttp/1.0');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, 'api:' . $key);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POSTFIELDS, self::toFields($data));
            $res = curl_exec($curl);
            if (!$res) {
                $errno = curl_errno($curl);
                if ($errno != 0) {
                    $error = $errno . ' ' . curl_error($curl);
                    throw new \Exception($error);
                }
            }
            curl_close($curl);
            if (empty($res)){
                throw new \Exception('发送失败：短信服务器异常');
            }
            $getdata = json_decode($res, true);
            if ($getdata['error'] != 0) {
                throw new \Exception('发送失败：短信服务不可用');
            }
            $res = [
                'code' => 1,
                'msg' => '短信发送成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return $res;
    }
    private function toFields($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $field = '';
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    $field .= $k . '[]=' . urlencode($vv) . '&';
                }
            } else {
                if (substr($v, 0, 1) == '@') {
                    return $data;
                }
                $field .= $k . '=' . urlencode($v) . '&';
            }
        }

        return trim($field, '&');
    }
}