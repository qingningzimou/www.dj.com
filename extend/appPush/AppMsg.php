<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/7/10
 * Time: 18:51
 */

namespace appPush;

use think\facade\Cache;
use think\facade\Lang;

/**
 * i襄阳消息推送
 * Class AppMsg
 * @package appPush
 */
class AppMsg
{
    /**
     * 推送消息
     * @param $pushType     0：统一消息内容 1：定制消息内容 $pushObject 数据格式需相应调整
     * @param $batchId      批次号  查询通知结果的依据
     * @param $msgTitle     消息标题
     * @param $pushObject   推送手机/内容
     * @param $msgContent   消息内容
     * @param $msgUrl       跳转地址
     * @return array
     */
    public function send_msg($pushType, string $batchId, string $msgTitle,array $pushObject, $msgContent = '',$msgUrl = '')
    {
        try {
            if($pushType){
                $data = [
                    "platformType" => "招生平台",
                    "msgTitle" => $msgTitle,
                    "pushType" => $pushType,
                    "batchId" => $batchId,
                    "pushObject" => $pushObject,
//                   "pushObject" =>  [
//                        [
//                            "phone" => "",手机号
//                            "content" => "",消息内容，为空时，取msgContent的值
//                            "url" => ""//跳转url,为空时，取msgUrl的值
//                        ],
//                    ],
                    "segmentId" => "XX000001",
                    "msgContent" => $msgContent,
                    "msgUrl" => $msgUrl
                ];
            }else{
                $data = [
                    "platformType" => "招生平台",
                    "msgTitle" => $msgTitle,
                    "pushType" => $pushType,
                    "batchId" => $batchId,
                    "phoneList" => $pushObject,
//                   "phoneList" => ["13000000000","13000000001"],
                    "segmentId" => "XX000001",
                    "msgContent" => $msgContent,
                    "msgUrl" => $msgUrl
                ];
            }
            $sendData = json_encode($data);

            $push_url = Cache::get('push_url');
            $push_url  = $push_url.'/third/pushMsgBatch';
            $push_key = Cache::get('push_key');
            $push_token = Cache::get('push_token');
            $method = "AES-128-CBC";
            $encrypted = openssl_encrypt($sendData, $method, $push_key, true, $push_key);
            $encrypted = base64_encode($encrypted);
            $jsonData = "{\"data\":" . "\"" . $encrypted . "\"}";
            $resSend = $this->post_data($push_url, $jsonData, $push_token);
            if (!$resSend) {
                throw new \Exception('消息推送接口连接失败');
            }
            $resSend = json_decode($resSend,true);
            if ($resSend['respCode'] !== '00000000'){
                throw new \Exception($resSend['respMsg']);
            }
            $res = [
                'code' => 1,
                'msg' => '短信发送成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?? Lang::get('system_error')
            ];
        }
        return $res;
    }

    /**
     * 推送结果查询
     * @param $batchId
     * @return array
     */
    public function getPushResult($batchId)
    {
        try {
            $push_url = Cache::get('push_url');
            $push_url  = $push_url.'/pushMsgTask/queryPushResultByMsgBatchNumber';
            $push_url = $push_url.'?msgBatchNumber='.$batchId;
            $push_token = Cache::get('push_token');
            $resQuery = $this->get_data($push_url, $push_token);
            if (!$resQuery) {
                throw new \Exception('推送结果查询接口连接失败');
            }
            $resQuery = json_decode($resQuery,true);
            if ($resQuery['respCode'] !== '00000000'){
                throw new \Exception($resQuery['respMsg']);
            }
            $res = [
                'code' => 1,
                'data' => [
                    'title' => $resQuery['pushMsgTitle'],
                    'result' => $resQuery['respData'],
                    'status' => $resQuery['executeStatus'],
                ]
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?? Lang::get('system_error')
            ];
        }
        return $res;
    }

    /**
     * 推送受理查询
     * @param $batchId
     * @return array
     */
    public function getBatch($batchId){
        try {
            $push_url = Cache::get('push_url');
            $push_url  = $push_url.'/pushMsgTask/queryMsgStatus';
            $push_url = $push_url.'?msgBatchNumber='.$batchId;
            $push_token = Cache::get('push_token');
            $resQuery = $this->get_data($push_url, $push_token);
            if (!$resQuery) {
                throw new \Exception('推送结果查询接口连接失败');
            }
            $resQuery = json_decode($resQuery,true);
            if ($resQuery['respCode'] !== '00000000'){
                throw new \Exception($resQuery['respMsg']);
            }
            $res = [
                'code' => 1,
                'msg' => $resQuery['respMsg']
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?? Lang::get('system_error')
            ];
        }
        return $res;
    }


    /**
     * curl_get请求
     * @param $url
     * @return bool|string
     */
    private function get_data($url,$token)
    {
        $headers = [
            'Content-Type:text/xml; charset=utf-8',
        ];
        if ($token){
            array_push($headers, "token:$token");
        }
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $headers);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);

        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }
    /**
     * curl_post发送
     * @param $url
     * @param $param
     * @param null $token
     * @return bool|string
     */
    private function post_data($url, $param, $token)
    {
        $headers = [
            'Content-Type:application/json;charset=utf-8',
        ];
        if ($token){
            array_push($headers, "token:$token");
        }
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (is_string($param)) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
            $strPOST = join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $headers);

        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }

}