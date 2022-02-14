<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/7/16
 * Time: 22:10
 */

namespace aysncCurl;

class AsyncCURL
{
    /**
     * 是否需要返回HTTP头信息
     */
    public $curlopt_header = 0;
    public $header=[];
    /**
     * 单个CURL调用超时限制
     */
    public $curlopt_timeout = 1;
    private $param = array();
    /**
     * 构造函数（可直接传入请求参数）
     *
     * @param array 可选
     * @return void
     */
    public function __construct($param = False)
    {
        ini_set('max_execution_time',(string)(60*60*12));
        if ($param !== False)
        {
            $this->param = $this->init_param($param);
        }
    }
    /**
     * 设置请求参数
     *
     * @param array
     * @return void
     */
    public function set_param($param){
        $this->param = $this->init_param($param);
    }

    public function set_header($header){
        $this->header = $header;
    }
    /**
     * 发送请求
     *
     * @return array
     */
    public function send($post = false)
    {
        $curl = $ret = array();
        $handle = curl_multi_init();
        if(is_array($this->param) && count($this->param)){
            foreach ($this->param as $k => $v)
            {
                $param = $this->check_param($v,$post);
                if (!$param) $curl[$k] = False;
                else $curl[$k] = $this->add_handle($handle, $param);
            }
            $this->exec_handle($handle);
            foreach ($this->param as $k => $v)
            {
                if ($curl[$k])
                {
                    $ret[$k] = curl_multi_getcontent($curl[$k]);
                    curl_multi_remove_handle($handle, $curl[$k]);
                } else {
                    $ret[$k] = False;
                }
            }
            curl_multi_close($handle);
        }else{
            $param = $this->check_param($this->param, $post);
            $curl = $this->add_handle($handle, $param);
            $this->exec_handle($handle);
            if($curl){
                $ret = curl_multi_getcontent($curl);
//                $info = curl_getinfo($curl);
//                $error = curl_error($curl);
                curl_multi_remove_handle($handle, $curl);
            }else{
                $ret = False;
            }
            curl_multi_close($handle);
        }
        return $ret;
    }

    private function init_param($param)
    {
        $ret = False;
        if (isset($param['url']))
        {
            $ret = array($param);
        } else {
            $ret = isset($param[0]) ? $param : False;
        }
        return $ret;
    }
    private function check_param($param = array(),$post)
    {
        $ret = array();
        if (is_string($param))
        {
            $url = $param;
        } else {
            extract($param);
        }
        if (isset($url))
        {
            $url = trim($url);
            $url = (stripos($url, 'https://') === 0 || stripos($url, 'http://') === 0 )? $url : NULL;
        }
        if ($post)
        {
            $method = 'POST';
        } else {
            $method = 'GET';
            unset($data);
        }
        if (isset($url)) $ret['url'] = $url;
        if (isset($method)) $ret['method'] = $method;
        if (isset($data)) $ret['data'] = $data;
        $ret = isset($url) ? $ret : False;
        return $ret;
    }
    private function add_handle($handle, $param)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $param['url']);
        curl_setopt($curl, CURLOPT_HEADER, $this->curlopt_header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->curlopt_timeout);
        if(!empty($this->header)){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $this->header);
        }
        if ($param['method'] == 'POST')
        {
            curl_setopt($curl, CURLOPT_POST, 1);
            if(!isset($param['data'])){
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(['data']));
            }
        }
        if(isset($param['data'])){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $param['data']);
        }
        curl_multi_add_handle($handle, $curl);
        return $curl;
    }
    private function exec_handle($handle)
    {
        $flag = null;
        do {
            curl_multi_exec($handle, $flag);
        } while ($flag > 0);
    }
}