<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/3/20
 * Time: 14:47
 */

namespace app\api\controller;
use app\common\controller\Education;
use think\facade\Cache;
use think\response\Json;
use Overtrue\Pinyin\Pinyin;
use think\facade\Lang;
use think\facade\Db;
use think\facade\Log;
require_once (__DIR__.'/../../common/controller/AbcRefund.php');

class Aiwes extends Education
{

    public function show()
    {
        $pay = new \AbcRefund();
        $pay_time = '2021-08-06 09:03:02';
        $order_code = 'JF210806094358035470';
        $pay_amount = '0.10';
//        $res = $pay->actRefund($pay_time, $order_code, $pay_amount);

        $refund_code = 'JF210810175807619579_new';
        $res = $pay->actRefundStatus($refund_code);
        var_dump($res);


    }

    public function index()
    {
        try {
            set_time_limit(0);
            ob_end_clean();
            ob_implicit_flush();
            session_write_close();
            header('X-Accel-Buffering: no'); // 关键是加了这一行。
            header('Cache-Control: no-cache');
            header('Connection:keep-alive');
            echo str_pad('',1024);
            $addressListData =  Db::table('deg_sys_address_420602_copy')
                ->field([
                    'id',
                    'address',
                ])
                ->where('simple_id',0)
                ->select()->toArray();
            $pinyin = new Pinyin();
            foreach ($addressListData as $value) {
                Db::table('deg_sys_address_420602_copy')
                    ->where('id',$value['id'])
                    ->update([
                        'simple_code' => $pinyin->abbr($value['address'],PINYIN_KEEP_NUMBER),
                    ]);
                    echo $value['id'].'<br>';
//                    usleep(5000);
            }
            echo 'end';
//            var_dump($resData);

        } catch (\Exception $exception) {

            $msg = $exception->getMessage() ?: Lang::get('system_error');

            echo $msg;
        }
    }
    //处理数据
    public function actChkData()
    {
        try{

            $minid = $this->result['minid'];
            $maxid = $this->result['maxid'];

            Log::record($minid . '-'.$maxid);
//            function filter_like_values ($array, $index, $value ){
//                $newarray = [];
//                if(is_array($array) && count($array)>0)
//                {
//                    foreach(array_keys($array) as $key){
//                        $temp[$key] = $array[$key][$index];
//                        if (mb_strstr($value,$temp[$key]) !== false){
//                            $newarray[$key] = $array[$key];
//                            break;
//                        }
//                    }
//                }
//                return $newarray;
//            }
//            $simpleListData = Cache::get('simple_address');
//
//
////            $pinyin = new Pinyin();
//            $minid = $this->result['minid'];
//            $maxid = $this->result['maxid'];
//            $addressListData =  Db::table('deg_sys_address_420682_copy')
//                ->where('id','>=', $minid)
//                ->where('id','<=', $maxid)
//                ->field([
//                    'id',
//                    'address',
////                    'primary_school_id',
////                    'middle_school_id',
//                ])
//                ->select()->toArray();
//
//
//            $chunkData = array_chunk($addressListData, 1000);
//            $checkNum = 0;
//            foreach($chunkData as $new_list) {
//                foreach ($new_list as $value) {
//                    $simpleData = array_values(filter_like_values($simpleListData, 'address', $value['address']));
//                    if(count($simpleData)){
//                        Db::table('deg_sys_address_420682_copy')
//                            ->where('id',$value['id'])
//                            ->update([
//                                'simple_id' => $simpleData[0]['id'],
//                            ]);
//                        $checkNum += 1;
//                    }
////                    usleep(5000);
//                }
//                echo '.'.$checkNum;
//                Cache::set('chk-' . $minid . '-' . $maxid, $checkNum);
//
//            }


//            foreach($chunkData as $new_list) {
//                foreach ($new_list as $value) {
//                    $subnum = Db::table('deg_sys_address_420682_copy')->where('simple_id',$value['id'])->count();
//                    $primarynum = Db::table('deg_sys_address_420682_copy')->where('simple_id',$value['id'])->where('primary_school_id','>',0)->count();
//                    $middlenum = Db::table('deg_sys_address_420682_copy')->where('simple_id',$value['id'])->where('middle_school_id','>',0)->count();
//                    Db::table('deg_sys_address_simple_copy_copy')
//                        ->where('id',$value['id'])
//                        ->update([
//                            'sub_num' => $subnum,
//                            'primary_school_num' => $primarynum,
//                            'middle_school_num' => $middlenum,
//                        ]);
//
//                    $checkNum += 1;
////                    usleep(5000);
//                }
//                echo '.'.$checkNum;
//                Cache::set('chk-' . $minid . '-' . $maxid, $checkNum);
//
//            }

        }catch (\Exception $exception){
            Log::record($exception->getMessage() ?: Lang::get('system_error'));
        }

    }

    private function asyncContrast($base,$count){
        try {
            set_time_limit(0);
            $sys_url = Cache::get('sys_url');
            for($i = 1;$i <= $count;$i++){
                $num = ($i -1) * 100;
                $num2 = $i * 100;
                $tmp = 'ch'.$i;
                $$tmp = curl_init();

                curl_setopt($$tmp, CURLOPT_URL, $sys_url.'/api/Aiwes/actChkData?minid='.($base + $num + 1).'&maxid='.($base + $num2));
                curl_setopt($$tmp, CURLOPT_RETURNTRANSFER, 1);
//                curl_setopt($$tmp, CURLOPT_TIMEOUT, 1);
            }
            $mh = curl_multi_init(); //1 创建批处理cURL句柄
            for($i = 1;$i <= $count;$i++){
                $tmp = 'ch'.$i;
                curl_multi_add_handle($mh, $$tmp); //2 增加句柄
            }
            $active = null;
            do {
                while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM) ;
                if ($mrc != CURLM_OK) { break; }
                // a request was just completed -- find out which one
                while ($done = curl_multi_info_read($mh)) {
                    // get the info and content returned on the request
                    $info = curl_getinfo($done['handle']);
                    $error = curl_error($done['handle']);
                    $result[] = curl_multi_getcontent($done['handle']);
                    // $responses[$map[(string) $done['handle']]] = compact('info', 'error', 'results');
                    // remove the curl handle that just completed
                    curl_multi_remove_handle($mh, $done['handle']);
                    curl_close($done['handle']);
                }
                // Block for data in / output; error handling is done by curl_multi_exec
                if ($active > 0) {
                    curl_multi_select($mh);
                }
            } while ($active);
            curl_multi_close($mh); //7 关闭全部句柄
            Log::record(Lang::get('res_success'));
        } catch (\Exception $exception) {
            Log::record($exception->getMessage() ?: Lang::get('system_error'));
        }
    }


}