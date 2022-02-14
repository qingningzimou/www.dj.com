<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/7/22
 * Time: 14:49
 */
namespace app\api\controller;
use app\common\controller\Education;
use think\facade\Db;
use think\facade\Log;
use think\facade\Lang;
use think\facade\Cache;
use dictionary\FilterData;
use app\mobile\model\user\Apply;
use app\mobile\model\user\Child;
use app\mobile\model\user\Family;
use app\mobile\model\user\House;
use app\mobile\model\user\Ancestor;
use app\mobile\model\user\Residence;
use app\mobile\model\user\Insurance;
use app\mobile\model\user\Company;
use app\mobile\model\user\ApplyTmp;
use app\mobile\model\user\SixthGrade;
use app\mobile\model\user\UserApplyStatus;
use app\common\model\RegionSetTime;
use app\mobile\model\user\UserApplyDetail;
use comparison\Reckon;
use appPush\AppMsg;
require_once (__DIR__.'/../../common/controller/AbcPay.php');

class TaskHandle extends Education
{
    /**
     * 执行批量处理程序
     * @return array
     */
    public function ExecuteHandle()
    {
        try {
            ignore_user_abort(true);
            set_time_limit(0);
            ob_end_clean();
            ob_implicit_flush();
            session_write_close();
            header('X-Accel-Buffering: no');
            header('Cache-Control: no-cache');
            header('Connection:keep-alive');
            echo str_pad('',1024);
            $code = $this->result['code'];
            switch ($code) {
                case "execute_assignment" :
                    $assignment = Db::name("assignment_setting_region")
                        ->alias('r')
                        ->join('assignment_setting s','r.assignment_id=s.id')
                        ->field([
                            's.*',
                            'r.region_id',
                            'r.single_double as r_single_double'
                        ])
                        ->where("r.deleted",0)
                        ->where("s.deleted",0)
                        ->whereIn('r.single_double',[1,2])
                        ->order('r.single_double DESC')
                        ->order('r.id ASC')
                        ->select()->toArray();
                    if(!$assignment){
                        throw new \Exception('系统错误');
                    }
                    Cache::set('execute_assignment_setting', $assignment);
                    $checkData =  Db::name('user_apply')
                        ->where("deleted",0)
                        ->where('voided',0)
                        ->where('single_double_check',0)
                        ->select()->toArray();
                    $method_name = 'actAssignment';
                    break;
                case "execute_unified" :
                    $checkData =  Db::name('user_apply_status')
                        ->where('auto_check_completed',0)
                        ->where('deleted',0)
                        ->column('id,user_apply_id');
                    $method_name = 'actUnified';
//                    $checkData =  Db::name('user_apply_detail')
//                        ->where('birthplace_status',4)
//                        ->where('deleted',0)
//                        ->column('id,user_apply_id');
//                    $method_name = 'actUnified';
                    break;
                case "execute_sendmsg" :
                    $checkData =  Db::name('UserMessageBatch')
                        ->where('sended',0)
                        ->where('deleted',0)
                        ->select()->toArray();
                    $method_name = 'actMsgSend';
                    break;
                case "execute_tmpsendmsg" :

                    die;
//                    $check_num = Db::name('UserMessageBatch')->where('sys_message_id', -1)->count();
//                    if($check_num){
//                        echo json_encode([
//                            'code' => 1,
//                            'data' => 0,
//                        ]);
//                        die;
//                        break;
//                    }

//                    $childData =  Db::name('UserChild')
//                        ->where('deleted',0)
//                        ->column('id,real_name,mobile');
//                    $applyData =  Db::name('UserApply')
//                        ->where('deleted',0)
//                        ->column('child_id');
//                    $childIds = array_column($childData,'id');
//                    $hasData = array_diff($childIds,$applyData);
//                    $chunkData = array_chunk($hasData, 10);
//                    foreach($chunkData as $new_list) {
//                        Db::startTrans();
//                        try{
//                            $max_id = Db::name('UserMessageBatch')->where('deleted', 0)->max('id');
//                            $temp_num = 100000000;
//                            $new_num = $max_id + $temp_num + 1;
//                            $sys_batch_code = 'TP'.substr($new_num,1,8);
//                            //批次ID
//                            $sys_batch_id = Db::name('UserMessageBatch')->insertGetId([
//                                'sys_batch_code' => $sys_batch_code,
//                                'sys_message_id' => -1,
//                            ]);
//                            $user_message = [];
//                            foreach ($new_list as $item){
//                                $child_data = filter_value_one($childData,'id',$item);
//                                if(!count($child_data)){
//                                    continue;
//                                }
//                                $user_id = Db::name('User')
//                                    ->where('deleted',0)
//                                    ->where('user_name',$child_data['mobile'])
//                                    ->value('id');
//                                if(empty($user_id)){
//                                    continue;
//                                }
//                                $content = '2021年秋季义务教育新生入学报名通道即将于今晚(8月7日23:59)关闭。如需报名请尽快提交入学申请。如已报名可略过该提示。';
//                                //用户消息内容
//                                $user_message[] = [
//                                    'user_id' => $user_id,
//                                    'mobile' => $child_data['mobile'],
//                                    'sys_message_id' => -1,
//                                    'sys_batch_code' => $sys_batch_code,
//                                    'sys_batch_id' => $sys_batch_id,
//                                    'title' => '报名关闭前提示',
//                                    'contents' => $content,
//                                ];
//                            }
//                            Db::name('UserMessage')->insertAll($user_message);
//                            Db::commit();
//                        }catch (\Exception $exception){
//                            Db::rollback();
//                        }
//                    }
                    $checkData = Db::name('UserMessage')
                        ->master(true)
                        ->where('sys_message_id',-1)
                        ->where('sended',0)
                        ->where('deleted',0)
                        ->select()->toArray();
                    $method_name = 'actTmpsend';
                    break;
                case "execute_admission" :
                    $degree_list = Db::name('PlanApply')
                        ->field(['school_id', 'SUM(spare_total)' => 'spare_total'])
                        ->where('school_id', '>', 0)
                        ->where('status', 1)
                        ->where('deleted', 0)->group('school_id')->select()->toArray();
                    $degree_school = [];
                    foreach ($degree_list as $item){
                        $degree_school[$item['school_id']] = $item['spare_total'];
                    }
                    $where = [];
                    $where[] = ['deleted','=',0];
                    $where[] = ['voided','=',0];
                    $where[] = ['prepared','=',0];
                    $where[] = ['resulted','=',0];
                    $where[] = ['school_attr','=',2];
                    $where[] = ['apply_school_id','>',0];
                    //民办申请数量情况
                    $apply_list = Db::name('UserApply')->field(['apply_school_id' => 'school_id', 'COUNT(*)' => 'total'])
                        ->where($where)->group('apply_school_id')->select()->toArray();

                    $school_ids = [];
                    foreach ($apply_list as $item){
                        $degree_count = isset($degree_school[$item['school_id']]) ? $degree_school[$item['school_id']] : 0;
                        //申请数量小于等于 学校批复的学位数量
                        if($item['total'] <= $degree_count){
                            $school_ids[] = $item['school_id'];
                        }
                    }
                    $where = [];
                    $where[] = ['a.deleted','=',0];
                    $where[] = ['a.voided','=',0];
                    $where[] = ['a.prepared','=',0];
                    $where[] = ['a.resulted','=',0];
                    $where[] = ['a.school_attr','=',2];
                    $where[] = ['a.apply_school_id','in',$school_ids];
                    $where[] = ['d.child_age_status','=',1];
                    $list = Db::name('UserApply')->alias('a')
                        ->field(['a.*'])
                        ->join([
                            'deg_user_apply_detail' => 'd'
                        ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                        ->where($where)->select()->toArray();
                    $data_num = 0;
                    foreach ($list as $item){
                        Db::startTrans();
                        try{
                            Db::name('UserApply')->where('id',$item['id'])->update([
                                'prepared' => 1,
                                'resulted' => 1,
                                'admission_type' => 7,//自动录取 录取方式
                                'result_school_id' => $item['apply_school_id'],
                                'apply_status' => 5,//民办录取
                            ]);
                            $data_num++;
                            Db::commit();
                        }catch (\Exception $exception){
                            Db::rollback();
                        }
                    }
                    Db::name('sys_school')->whereIn('id',$school_ids)->update(['auto_admission' => 1]);
                    echo json_encode([
                        'code' => 1,
                        'data' => $data_num,
                    ]);
                    die;
                    break;
                case "execute_onlinepay" :
                    $checkData = Db::name('user_cost_pay')
                        ->where('status',0)
                        ->where('expired',0)
                        ->where('deleted',0)
                        ->select()->toArray();
                    $method_name = 'actOnlinepay';
                    break;

                case "execute_refund" :
                    $checkData = Db::name('user_cost_refund')
                        ->where('region_status',1)
                        ->where('refund_status',0)
                        ->where('deleted',0)
                        ->select()->toArray();
                    $method_name = 'actRefund';
                    break;
                case "check_other" :

                    break;

                default:
                    throw new \Exception("未定义的处理程序");
            }
            Cache::set('execute_handle_data', $checkData);
            $data_num = count($checkData);
            echo json_encode([
                'code' => 1,
                'data' => $data_num,
            ]);
            if(!$data_num){
                die;
            }
            $base = 0;
            //线程数量
            $thread_count = 10;
            //每线程数据量
            $batch_count = 1000;
//            $sub_num =(int) ($data_num / ($thread_count*$batch_count));
            Cache::set('execute_completed', 0);
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 10000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 20000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 30000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 40000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 50000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 60000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 70000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 80000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 90000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 100000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 110000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 120000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 130000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 140000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 150000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 160000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 170000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 180000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
            $base = 190000;
            $this->asyncContrast($base,$thread_count,$batch_count,$method_name);
//            for($i = 0;$i<=$sub_num;$i++){
//                self::asyncContrast($base,$thread_count,$batch_count,$method_name);
//                $base += $thread_count*$batch_count;
//            }
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
            echo json_encode($res,256);
        }
    }

    /**
     * 处理情况反馈
     */
    public function showTask()
    {
        try {
            $execute_num = Cache::get('execute_completed');
            $res = [
                'code' => 1,
                'data' => $execute_num,
            ];
        }
        catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return json($res);
    }
    /**
     *
     * 发送消息
     */
    public function actMsgSend()
    {
        try{
            ignore_user_abort(true);
            set_time_limit(0);
            $minid = $this->result['minid'];
            $maxid = $this->result['maxid'];
            $checkData = Cache::get('execute_handle_data', []);
            $checkNum = 0;
            $appMsg = new AppMsg();
            //按传入的处理范围对数据进行处理
            foreach ($checkData as $k => $v) {
                if($k >= $minid-1 and $k <= $maxid -1){
                    if(isset($checkData[$k])){
                        $message_list = Db::name('UserMessage')
                            ->where('sys_batch_id',$v['id'])
                            ->where('deleted',0)
                            ->select()->toArray();
                        $title = '';
                        $push_object = [];
                        $msg_url = "/sga/sgah5/enrollStudent/#/pages/news/news";
                        foreach ($message_list as $key => $val){
//                            if(empty($title)){
//                                $title = $val['title'];
//                            }
                            $title = '招生平台消息通知';
                            $push_object[] = [
                                "phone" => $val['mobile'],
                                "content" => '点击至招生平台查看详情',
//                                "content" => $val['contents'],
                                "url" => $msg_url
                            ];
                        }
                        $result = $appMsg->send_msg(1, $v['sys_batch_code'], $title, $push_object);
                        if($result['code'] == 1){
                            Db::name('UserMessageBatch')->where('id',$v['id'])->update(['sended' => 1]);
                        }else{
                            Log::record($result['msg']);
                        }
                        $checkNum += 1;
                    }
                }
            }
            Cache::inc('execute_completed',$checkNum);
        }catch (\Exception $exception){
            Log::record($exception->getMessage() ?: Lang::get('system_error'));
        }
    }
    /**
     *
     * 更新退费记录
     */
    public function actRefund()
    {
        try{
            ignore_user_abort(true);
            set_time_limit(0);
            $payRefund = new \AbcPay();

            $minid = $this->result['minid'];
            $maxid = $this->result['maxid'];
            $checkData = Cache::get('execute_handle_data', []);
            $checkNum = 0;
            //按传入的处理范围对数据进行处理
            foreach ($checkData as $k => $v) {
                if($k >= $minid-1 and $k <= $maxid -1){
                    if(isset($checkData[$k])){
                        Db::startTrans();
                        try{
                            $merchant_num = Db::name('sys_school')->where('id',$v['school_id'])->value('merchant_num');
                            if(empty($merchant_num)){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            $resRefundStatus = $payRefund->actRefundStatus($v['refund_code'],$merchant_num);
                            if($resRefundStatus['code']){
                                $getData = $resRefundStatus['data'];
                                Db::name('user_cost_pay')
                                    ->where('id',$v['user_cost_pay_id'])
                                    ->update([
                                        'refund_status' => 1,
                                        'refund_code' => $getData['refund_code'],
                                        'refund_time' => $getData['refund_time'],
                                        'refund_amount' => $getData['refund_amount'],
                                    ]);
                                Db::name('user_cost_refund')
                                    ->where('id',$v['id'])
                                    ->update([
                                        'refund_status' => 1,
                                    ]);
                            }else{
                                $pay_time = $v['pay_time'];
                                $order_code = $v['order_code'];
                                $pay_amount = (string)floatval($v['amount']);
                                $resRefund= $payRefund->actRefund($pay_time, $order_code, $pay_amount,$merchant_num);
                                //如果请求成功
                                if($resRefund['code'] == 1){
                                    $getData = $resRefund['data'];
                                    //如果退费成功
                                    if($getData['refund_status']){
                                        Db::name('user_cost_pay')
                                            ->where('id',$v['user_cost_pay_id'])
                                            ->update([
                                                'refund_status' => 1,
                                                'refund_code' => $getData['refund_code'],
                                                'refund_time' => $getData['refund_time'],
                                                'refund_amount' => $getData['refund_amount'],
                                            ]);
                                    }
                                    Db::name('user_cost_refund')
                                        ->where('id',$v['id'])
                                        ->update([
                                            'refund_code' => $getData['refund_code'],
                                            'refund_status' => $getData['refund_status'],
                                        ]);
                                }

                            }
                            $checkNum += 1;
                            Db::commit();
                        }catch (\Exception $exception){
                            Db::rollback();
                        }
                    }
                }
            }
            Cache::inc('execute_completed',$checkNum);
        }catch (\Exception $exception){
            Log::record($exception->getMessage() ?: Lang::get('system_error'));
        }
    }
    /**
     *
     * 更新缴费记录
     */
    public function actOnlinepay()
    {
        try{
            ignore_user_abort(true);
            set_time_limit(0);
            $minid = $this->result['minid'];
            $maxid = $this->result['maxid'];
            $checkData = Cache::get('execute_handle_data', []);
            $checkNum = 0;
            $pay_url = Cache::get('pay_url');
            //按传入的处理范围对数据进行处理
            foreach ($checkData as $k => $v) {
                if($k >= $minid-1 and $k <= $maxid -1){
                    if(isset($checkData[$k])){
                        Db::startTrans();
                        try{
                            $query_object = [
                                'swiftNumber' => $v['cost_code']
                            ];
                            $query_object = json_encode($query_object);
                            //调取查询接口
                            $resSend = httpPost($pay_url, $query_object, true);
                            if (!$resSend) {
                                Db::commit();
                                continue;
                            }
                            $resSend = json_decode($resSend,true);
                            if ($resSend['respCode'] !== '00000000'){
                                if(strtotime($v['create_time']) < strtotime("-1 hour")){
                                    Db::name('user_cost_pay')
                                        ->where('id',$v['id'])
                                        ->update([
                                            'expired' => 1,
                                        ]);
                                }
                                Log::record($resSend['respMsg']);
                                Db::commit();
                                continue;
                            }
                            $getData = $resSend['respData'][0];
                            if($getData['payStatus'] == 1){
                                Db::name('user_cost_pay')
                                    ->where('id',$v['id'])
                                    ->update([
                                        'status' => 1,
                                        'epay_code' => $getData['epayCode'],
                                        'order_code' => $getData['orderNo'],
                                        'ixy_user' => $getData['userId'],
                                        'pay_time' => $getData['orderTime'],
                                        'pay_amount' => $getData['payamt'],
                                        'pay_name' => $getData['namePay'],
                                    ]);
                                Db::name("user_apply")
                                    ->where('id',$v['user_apply_id'])
                                    ->update([
                                        'paid' => 1,
                                        'apply_status' => 6
                                    ]);
                                $school_name = Db::name("sys_school")
                                    ->where('id',$v['school_id'])
                                    ->where('deleted',0)
                                    ->value('school_name');
                                $child_name = Db::name("user_apply_detail")
                                    ->where('user_apply_id',$v['user_apply_id'])
                                    ->where('deleted',0)
                                    ->value('child_name');
                                //审核日志
                                $log = [];
                                $log['user_apply_id'] = $v['user_apply_id'];
                                $log['admin_id'] = 0;
                                $log['school_id'] = $v['school_id'];
                                $log['education'] = 0;
                                $log['remark'] = '('.$child_name.')被'.$school_name.'录取，缴费成功'.$getData['payamt'].'元';
                                $log['status'] = 1;
                                //$log['create_time'] = $getData['orderTime'];
                                Db::name('UserApplyAuditLog')->save($log);
                            }
                            $checkNum += 1;
                            Db::commit();
                        }catch (\Exception $exception){
                            Db::rollback();
                        }
                    }
                }
            }
            Cache::inc('execute_completed',$checkNum);
        }catch (\Exception $exception){
            Log::record($exception->getMessage() ?: Lang::get('system_error'));
        }
    }
    /**
     *
     * 单双符自动标记
     */
    public function actAssignment()
    {
        try{
            ignore_user_abort(true);
            set_time_limit(0);
            $minid = $this->result['minid'];
            $maxid = $this->result['maxid'];
            $checkData = Cache::get('execute_handle_data', []);
            $assignment = Cache::get('execute_assignment_setting', []);
            $dictionary = new FilterData();
            //获取产权房编号
            $getData = $dictionary->findValue('dictionary', 'SYSFCLX', 'SYSFCLXCQF');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $property_code = $getData['data'];
            //获取三代同堂编号
            $getData = $dictionary->findValue('dictionary', 'SYSFCLX', 'SYSFCLXCDTT');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $generations_code = $getData['data'];
            //获取跨区派位区域
            $getData = $dictionary->findValue('dictionary', 'SYSKQCL', 'SYSKQPWQY');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $region_id = $getData['data'];
            //获取房产单符派位参数值
            $getData = $dictionary->findValue('dictionary', 'SYSPWSZ', 'SYSDFPW');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $chk_single = $getData['data'];
            //获取房产双符派位参数值
            $getData = $dictionary->findValue('dictionary', 'SYSPWSZ', 'SYSSFPW');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $chk_double = $getData['data'];
            $checkNum = 0;
            //按传入的处理范围对数据进行处理
            foreach ($checkData as $k => $v) {
                if($k >= $minid-1 and $k <= $maxid -1){
                    if(isset($checkData[$k])){
                        Db::startTrans();
                        try{
                            $detail = Db::name("user_apply_detail")->where("user_apply_id", $v['id'])->where('deleted',0)->find();
                            //如果没获取到申报详情，本次忽略
                            if (empty($detail)) {
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            //年龄不符 更新已比对状态，后续项目不再比对
                            if($detail['child_age_status'] != 1){
                                Db::name('user_apply')->where('id',$v['id'])->update(['single_double_check'=>1]);
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            $status = Db::name("user_apply_status")->where('deleted', 0)->where("user_apply_id", $v['id'])->find();
                            //如果没获取到申报状态，本次忽略
                            if (empty($status)) {
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            //如果监护人关系数据未比对，本次忽略
                            if($status['auto_check_relation'] == 0){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            //监护人关系不属实或无结果 更新已比对状态，后续项目不再比对
                            if($status['auto_check_relation'] != 1){
                                Db::name('user_apply')->where('id',$v['id'])->update(['single_double_check'=>1]);
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            //如果是三代同堂，并且关系数据未比对，本次忽略
                            if($v['ancestor_id'] && $status['auto_check_ancestor'] == 0){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            //如果是三代同堂，并且关系数据不属实或无结果 更新已比对状态，后续项目不再比对
                            if($v['ancestor_id'] && $status['auto_check_ancestor'] != 1){
                                Db::name('user_apply')->where('id',$v['id'])->update(['single_double_check'=>1]);
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            $single_double = 0;
                            $assignmentData = array_values(filter_by_value($assignment, 'region_id', $v['region_id']));
                            //根据各区设定的规则（十五类），进行双符匹配比对
                            foreach($assignmentData as $_key=>$_value){
                                if($_value['r_single_double'] == $chk_double){
                                    $flag = true;
                                    //房产类型
                                    if($_value['house_type'] != $v['house_type']) {
                                        $flag = false;
                                    }
                                    //如果是三代同堂并且当前类别不为三代同堂
                                    if($v['ancestor_id'] && $_value['house_type'] != $generations_code){
                                        $flag = false;
                                    }
                                    //如果是三代同堂并且当前类别是三代同堂，并且申报房产为产权房
                                    if($v['ancestor_id'] && $_value['house_type'] == $generations_code && $v['house_type'] == $property_code){
                                        $flag = true;
                                    }
                                    //如果要求主城区户籍
                                    if($_value['main_area_birthplace_status'] == 1){
                                        //如果主城区户籍尚未比对则跳过
                                        if($status['auto_check_birthplace_main_area'] == 0){
                                            continue;
                                        }
                                        if($status['auto_check_birthplace_main_area'] == 2 || $status['auto_check_birthplace_main_area'] == -1){
                                            $flag = false;
                                        }
                                        if($detail['chk_cross'] && $v['region_id'] == $region_id){
                                            $flag = true;
                                        }
                                    }
                                    //如果要求片区户籍
                                    if($_value['area_birthplace_status'] == 1){
                                        //如果片区户籍尚未比对则跳过
                                        if($status['auto_check_birthplace_area'] == 0){
                                            continue;
                                        }
                                        if($status['auto_check_birthplace_area'] == 2 || $status['auto_check_birthplace_area'] == -1){
                                            $flag = false;
                                        }
                                        if($detail['chk_cross'] && $v['region_id'] == $region_id){
                                            $flag = true;
                                        }
                                    }
                                    //如果要求片区主城区有房
                                    if($_value['main_area_house_status'] == 1){
                                        //如果片区主城区有房尚未比对则跳过
                                        if($status['auto_check_house_main_area'] == 0){
                                            continue;
                                        }
                                        if($status['auto_check_house_main_area'] == 2 || $status['auto_check_house_main_area'] == -1){
                                            $flag = false;
                                        }
                                    }
                                    //判断是否片区有房
                                    if($_value['area_house_status'] == 1){
                                        //如果片区有房尚未比对则跳过
                                        if($status['auto_check_house_area'] == 0){
                                            continue;
                                        }
                                        if($status['auto_check_house_area'] == 2 || $status['auto_check_house_area'] == -1){
                                            $flag = false;
                                        }
                                    }
                                    if($v['house_school_id'] == 0){
                                        $flag = false;
                                    }
                                    if($flag){
                                        $single_double = $chk_double;
                                        break;
                                    }
                                }
                            }
                            //如果没有匹配双符结果
                            if(!$single_double){
                                //如果有房产匹配学校
                                if($v['house_school_id']){
                                    $singled = Db::name('SysSchool')
                                        ->where('id',$v['house_school_id'])
                                        ->where('disabled',0)
                                        ->where('deleted',0)
                                        ->value('singled');
                                    //如果有房产匹配学校
                                    if(!empty($singled) && $singled){
                                        $single_double = $chk_single;
                                    }
                                }
                            }
//                            echo $v['region_id'].'-'.$single_double.'<br>';
////                            var_dump($single_double);
                            Db::name('UserApplyDetail')->where('id',$detail['id'])->update(['single_double' => $single_double]);
                            Db::name('user_apply')->where('id',$v['id'])->update(['single_double_check'=>1]);
                            $checkNum += 1;
                            Db::commit();
                        }catch (\Exception $exception){
                            Db::rollback();
                        }
                    }
                }
            }
            Cache::inc('execute_completed',$checkNum);
        }catch (\Exception $exception){
            Log::record($exception->getMessage() ?: Lang::get('system_error'));
            Db::rollback();
        }
    }

    /**
     *
     * 以学生为单位进行状态处理
     */
    public function actUnified()
    {
        try{
            ignore_user_abort(true);
            set_time_limit(0);
            $minid = $this->result['minid'];
            $maxid = $this->result['maxid'];
            $checkData = Cache::get('execute_handle_data', []);
            $dictionary = new FilterData();
            $reckon = new Reckon();
            $region = Cache::get('region',[]);
            $checkNum = 0;
            foreach ($checkData as $k => $v) {
                if($k >= $minid-1 and $k <= $maxid -1){
                    if(isset($checkData[$k])){
                        Db::startTrans();
                        try{
                            $applyStatusData = (new UserApplyStatus())->where([
                                'user_apply_id' => $v['user_apply_id'],
                            ])->find();
                            if(empty($applyStatusData)){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            $tmpData = (new ApplyTmp())->where([
                                'apply_id' => $v['user_apply_id'],
                            ])->find();
                            if(empty($tmpData)){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            $tmp_id = $tmpData['id'];
                            $applyData = (new Apply())->where([
                                'id' => $v['user_apply_id'],
                            ])->find();
                            if(empty($applyData)){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            //获取学生信息
                            $childData = Child::field([
                                'id',
                                'real_name',
                                'idcard',
                                'birthday',
                                'api_name',
                                'api_policestation',
                                'api_policestation_id',
                                'api_area',
                                'api_address',
                                'api_card',
                                'api_relation',
                                'api_region_id',
                                'is_main_area',
                            ])->find($tmpData['child_id']);
                            if(empty($childData)){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            //获取监护人信息
                            $familyData = Family::field([
                                'id',
                                'parent_name',
                                'idcard',
                                'relation',
                                'api_name',
                                'api_policestation',
                                'api_policestation_id',
                                'api_area',
                                'api_address',
                                'api_card',
                                'api_relation',
                                'api_region_id',
                                'is_area_main',
                            ])->find($tmpData['family_id']);
                            if(empty($familyData)){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            //获取房产信息
                            $houseData = House::field([
                                'id',
                                'family_id',
                                'house_type',
                                'code_type',
                                'cert_code',
                                'api_address',
                                'api_house_code',
                                'api_area_code',
                                'api_region_id',
                            ])->find($tmpData['house_id']);
                            if(empty($houseData)){
                                $checkNum += 1;
                                Db::commit();
                                continue;
                            }
                            $auto_check_child = 0;//学生信息系统比对状态
                            $auto_check_family = 0;//家长信息系统比对状态
                            $auto_check_relation = 0;//系统比对关系是否属实
                            $auto_check_birthplace_area = 0;//片区户籍信息系统比对状态
                            $auto_check_birthplace_main_area = 0;//主城区户籍信息系统比对状态
                            $auto_check_house_area = 0;//片区房产信息系统比对状态
                            $auto_check_house_main_area = 0;//片区主城区房产信息系统比对状态
                            $auto_check_company = 0;//工商信息系统比对状态
                            $auto_check_insurance = 0;//社保信息系统比对状态
                            $auto_check_residence = 0;//居住证信息系统比对状态
                            $auto_check_ancestor = 0;//三代同堂关系是否属实


                            $chk_cross = 0;//定义跨区处理标识（对应紫贞派出所和二十中）
                            $birthplace_status = 0;//定义户籍情况
                            $guardian_relation = 0;//定义监护人情况
                            $house_status = 0;//定义匹配学校情况
                            $insurance_status = 0;//定义社保情况
                            $business_license_status = 0;//定义工商情况
                            $residence_permit_status = 0;//定义居住证情况
                            $house_matching_school_id = 0;//定义房产匹配学校

                            //如果未比对学生信息
                            if($tmpData['check_child'] == 0){
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckChild($tmp_id,$tmpData['child_id'],$childData['idcard']);
                                    if($resReckon['code']){
                                        $childData = Child::field([
                                            'id',
                                            'real_name',
                                            'idcard',
                                            'birthday',
                                            'api_name',
                                            'api_policestation',
                                            'api_policestation_id',
                                            'api_area',
                                            'api_address',
                                            'api_card',
                                            'api_relation',
                                            'api_region_id',
                                            'is_main_area',
                                        ])->master(true)->find($tmpData['child_id']);
                                        $tmpData['check_child'] = $resReckon['data'];
                                    }else{
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                    Db::rollback();
                                }
                            }
                            //如果未比对监护人信息
                            if($tmpData['check_family'] == 0){
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckFamily($tmp_id,$tmpData['family_id'],$familyData['idcard']);
                                    if($resReckon['code']){
                                        $familyData = Family::field([
                                            'id',
                                            'parent_name',
                                            'idcard',
                                            'relation',
                                            'api_name',
                                            'api_policestation',
                                            'api_policestation_id',
                                            'api_area',
                                            'api_address',
                                            'api_card',
                                            'api_relation',
                                            'api_region_id',
                                            'is_area_main',
                                        ])->master(true)->find($tmpData['family_id']);
                                        $tmpData['check_family'] = $resReckon['data'];
                                    }else{
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                    Db::rollback();
                                }
                            }
                            //如果有祖辈信息
                            if($tmpData['ancestors_family_id']){
                                //获取祖辈信息
                                $ancestorsFamilyData = Family::field([
                                    'id',
                                    'parent_name',
                                    'idcard',
                                    'relation',
                                    'api_name',
                                    'api_policestation',
                                    'api_policestation_id',
                                    'api_area',
                                    'api_address',
                                    'api_card',
                                    'api_relation',
                                    'api_region_id',
                                    'is_area_main',
                                ])->find($tmpData['ancestors_family_id']);
                                if(empty($ancestorsFamilyData)){
                                    throw new \Exception('祖辈信息错误');
                                }
                                //如果未比对祖辈信息
                                if($tmpData['check_ancestors'] == 0){
                                    Db::startTrans();
                                    try{
                                        $resReckon = $reckon->CheckAncestors($tmp_id,$tmpData['ancestors_family_id'],$ancestorsFamilyData['idcard']);
                                        if($resReckon['code']){
                                            $ancestorsFamilyData = Family::field([
                                                'id',
                                                'parent_name',
                                                'idcard',
                                                'relation',
                                                'api_name',
                                                'api_policestation',
                                                'api_policestation_id',
                                                'api_area',
                                                'api_address',
                                                'api_card',
                                                'api_relation',
                                                'api_region_id',
                                                'is_area_main',
                                            ])->master(true)->find($tmpData['ancestors_family_id']);
                                            $tmpData['check_ancestors'] = $resReckon['data'];
                                        }else{
                                            throw new \Exception($resReckon['msg']);
                                        }
                                        Db::commit();
                                    }catch (\Exception $exception){
                                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                        Db::rollback();
                                    }
                                }
                            }
                            //如果未比对房产信息
                            if($tmpData['check_house'] == 0){
                                if($tmpData['ancestor_id'] && $tmpData['ancestors_family_id']){
                                    $chk_idcard = $ancestorsFamilyData['idcard'];
                                }else{
                                    $chk_idcard = $familyData['idcard'];
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckHouse($tmp_id,$tmpData['house_id'],$chk_idcard,$houseData['house_type'],$houseData['code_type'],$houseData['cert_code']);
                                    if($resReckon['code']){
                                        $houseData = House::field([
                                            'id',
                                            'family_id',
                                            'house_type',
                                            'code_type',
                                            'cert_code',
                                            'api_address',
                                            'api_house_code',
                                            'api_area_code',
                                            'api_region_id',
                                        ])->master(true)->find($tmpData['house_id']);
                                        $tmpData['check_house'] = $resReckon['data'];
                                    }else{
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                    Db::rollback();
                                }
                            }
                            //如果有其它监护人信息
                            if($tmpData['other_family_id']){
                                //获取其它监护人信息
                                $otherFamilyData = Family::field([
                                    'id',
                                    'parent_name',
                                    'idcard',
                                    'relation',
                                    'api_name',
                                    'api_policestation',
                                    'api_policestation_id',
                                    'api_area',
                                    'api_address',
                                    'api_card',
                                    'api_relation',
                                    'api_region_id',
                                    'is_area_main',
                                ])->find($tmpData['other_family_id']);
                                //如果未比对其它监护人信息
                                if($tmpData['check_other'] == 0 && !empty($otherFamilyData)){
                                    Db::startTrans();
                                    try{
                                        $resReckon = $reckon->CheckFamily($tmp_id,$tmpData['other_family_id'],$otherFamilyData['idcard']);
                                        if($resReckon['code']){
                                            $otherFamilyData = Family::field([
                                                'id',
                                                'parent_name',
                                                'idcard',
                                                'relation',
                                                'api_name',
                                                'api_policestation',
                                                'api_policestation_id',
                                                'api_area',
                                                'api_address',
                                                'api_card',
                                                'api_relation',
                                                'api_region_id',
                                                'is_area_main',
                                            ])->master(true)->find($tmpData['other_family_id']);
                                            $tmpData['check_other'] = $resReckon['data'];
                                        }else{
                                            throw new \Exception($resReckon['msg']);
                                        }
                                        Db::commit();
                                    }catch (\Exception $exception){
                                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                        Db::rollback();
                                    }
                                }
                            }

                            //如果有三代同堂关系信息
                            if($tmpData['ancestor_id']){
                                //获取三代同堂关系信息
                                $ancestorData = Ancestor::field([
                                    'id',
                                    'child_id',
                                    'family_id',
                                    'father_id',
                                    'mother_id',
                                    'singled',
                                    'api_child_relation',
                                    'api_parent_relation',
                                    'api_grandparent_relation',
                                    'api_father_idcard',
                                    'api_mother_idcard',
                                    'api_other_status',
                                    'api_marriage_status',
                                    'api_parent_status',
                                    'api_grandparent_status',
                                    'api_child_house',
                                    'api_father_house',
                                    'api_mother_house',
                                ])->find($tmpData['ancestor_id']);
                                if(empty($ancestorData)){
                                    throw new \Exception('三代同堂关系信息错误');
                                }
                                //如果未比对三代同堂关系信息
                                if($tmpData['check_generations'] == 0){
                                    if(!isset($ancestorsFamilyData) || empty($ancestorsFamilyData)){
                                        throw new \Exception('祖辈信息错误');
                                    }
                                    $other_idcard = '';
                                    if(isset($otherFamilyData) || !empty($otherFamilyData)){
                                        $other_idcard = $otherFamilyData['idcard'];
                                    }
                                    Db::startTrans();
                                    try{
                                        $resReckon = $reckon->CheckAncestor($tmp_id,$tmpData['ancestor_id'],$childData['idcard'],$familyData['idcard'],$ancestorsFamilyData['idcard'],$other_idcard);
                                        if($resReckon['code']){
                                            $ancestorData = Ancestor::field([
                                                'id',
                                                'child_id',
                                                'family_id',
                                                'father_id',
                                                'mother_id',
                                                'singled',
                                                'api_child_relation',
                                                'api_parent_relation',
                                                'api_grandparent_relation',
                                                'api_father_idcard',
                                                'api_mother_idcard',
                                                'api_other_status',
                                                'api_marriage_status',
                                                'api_parent_status',
                                                'api_grandparent_status',
                                                'api_child_house',
                                                'api_father_house',
                                                'api_mother_house',
                                            ])->master(true)->find($tmpData['ancestor_id']);
                                            $tmpData['check_generations'] = $resReckon['data'];
                                        }else{
                                            throw new \Exception($resReckon['msg']);
                                        }
                                        Db::commit();
                                    }catch (\Exception $exception){
                                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                        Db::rollback();
                                    }
                                }
                            }
                            //如果有工商信息
                            if($tmpData['company_id']){
                                //获取工商信息
                                $companyData = Company::field([
                                    'id',
                                    'family_id',
                                    'house_id',
                                    'org_code',
                                    'api_lawer_name',
                                    'api_company_name',
                                    'api_address',
                                    'api_organ',
                                    'api_establish',
                                    'api_company_status',
                                    'api_area',
                                ])->find($tmpData['company_id']);
                                //如果未比对工商信息
                                if($tmpData['check_company'] == 0 && !empty($companyData)){
                                    Db::startTrans();
                                    try{
                                        $resReckon = $reckon->CheckCompany($tmp_id,$tmpData['company_id'],$companyData['org_code']);
                                        if($resReckon['code']){
                                            $companyData = Company::field([
                                                'id',
                                                'family_id',
                                                'house_id',
                                                'org_code',
                                                'api_lawer_name',
                                                'api_company_name',
                                                'api_address',
                                                'api_organ',
                                                'api_establish',
                                                'api_company_status',
                                                'api_area',
                                            ])->master(true)->find($tmpData['company_id']);
                                            $tmpData['check_company'] = $resReckon['data'];
                                        }else{
                                            throw new \Exception($resReckon['msg']);
                                        }
                                        Db::commit();
                                    }catch (\Exception $exception){
                                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                        Db::rollback();
                                    }
                                }
                            }
                            //如果有居住证信息
                            if($tmpData['residence_id']){
                                //获取居住证信息
                                $residenceData = Residence::field([
                                    'id',
                                    'family_id',
                                    'house_id',
                                    'live_code',
                                    'api_address',
                                    'api_hold_address',
                                    'api_end_time',
                                    'api_code',
                                    'api_area_code',
                                ])->find($tmpData['residence_id']);
                                //如果未比对居住证信息
                                if($tmpData['check_residence'] == 0 && !empty($residenceData)){
                                    Db::startTrans();
                                    try{
                                        $resReckon = $reckon->CheckResidence($tmp_id,$tmpData['residence_id'],$residenceData['live_code']);
                                        if($resReckon['code']){
                                            $residenceData = Residence::field([
                                                'id',
                                                'family_id',
                                                'house_id',
                                                'live_code',
                                                'api_address',
                                                'api_hold_address',
                                                'api_end_time',
                                                'api_code',
                                                'api_area_code',
                                            ])->master(true)->find($tmpData['residence_id']);
                                            $tmpData['check_residence'] = $resReckon['data'];
                                        }else{
                                            throw new \Exception($resReckon['msg']);
                                        }
                                        Db::commit();
                                    }catch (\Exception $exception){
                                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                        Db::rollback();
                                    }
                                }
                            }
                            //如果有社保信息
                            if($tmpData['insurance_id']){
                                //获取社保信息
                                $insuranceData = Insurance::field([
                                    'id',
                                    'family_id',
                                    'house_id',
                                    'social_code',
                                    'api_code',
                                    'api_join_time',
                                    'api_end_time',
                                    'api_company_name',
                                    'api_adcode',
                                ])->find($tmpData['insurance_id']);
                                //如果未比对社保信息
                                if($tmpData['check_insurance'] == 0 && !empty($insuranceData)){
                                    Db::startTrans();
                                    try{
                                        $resReckon = $reckon->CheckInsurance($tmp_id,$tmpData['insurance_id'],$insuranceData['social_code']);
                                        if($resReckon['code']){
                                            $insuranceData = Insurance::field([
                                                'id',
                                                'family_id',
                                                'house_id',
                                                'social_code',
                                                'api_code',
                                                'api_join_time',
                                                'api_end_time',
                                                'api_company_name',
                                                'api_adcode',
                                            ])->master(true)->find($tmpData['insurance_id']);
                                            $tmpData['check_insurance'] = $resReckon['data'];
                                        }else{
                                            throw new \Exception($resReckon['msg']);
                                        }
                                        Db::commit();
                                    }catch (\Exception $exception){
                                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                        Db::rollback();
                                    }
                                }
                            }
                            //如果学生信息已比对
                            if($tmpData['check_child'] == 1){
                                //如果有比对后的区域ID
                                if($childData['api_region_id'] > 0){
                                    //如果对比申请的区县和公安局区县一样 则是本区县学生
                                    if($childData['api_region_id'] == $applyData['region_id']){
                                        //本区县非主城区
                                        $auto_check_child = 1;//学生信息本区县非主城区
                                        $auto_check_birthplace_area = 1;//是片区户籍
                                        $auto_check_birthplace_main_area = 2;//不是主城区户籍
                                        $birthplace_status = 2;//非主城区
                                        //派出所对应主城区
                                        if($childData['is_main_area'] == 1){
                                            //本区县主城区
                                            $auto_check_child = 2;//学生信息本区县主城区
                                            $auto_check_birthplace_main_area = 1;//是主城区户籍
                                            $birthplace_status = 1;//主城区
                                        }
                                        //不是本区域
                                    }else{
                                        $auto_check_child = -1;//学生信息不在本区
                                        $auto_check_birthplace_area = 2;//不是片区户籍
                                        $auto_check_birthplace_main_area = 2;//不是主城区户籍
                                        $birthplace_status = 3;//襄阳市非本区
                                    }
                                    //如果学生比对有派出所ID
                                    if($childData['api_policestation_id']){
                                        //获取跨区派出所
                                        $getData = $dictionary->findValue('dictionary', 'SYSKQCL', 'SYSKQPCS');
                                        if(!$getData['code']){
                                            throw new \Exception($getData['msg']);
                                        }
                                        $filter_police_id = $getData['data'];
                                        //匹配到跨区派出所并且申报区域未对应派出所区域
                                        if($childData['api_policestation_id'] == $filter_police_id && $applyData['region_id'] != $childData['api_region_id']){
                                            $getData = $dictionary->findValue('dictionary', 'SYSKQPCS','SYSKQXXYI');
                                            if(!$getData['code']){
                                                throw new \Exception($getData['msg']);
                                            }
                                            $primary_school_id =  $getData['data'];
                                            $getData = $dictionary->findValue('dictionary', 'SYSKQPCS','SYSKQXXER');
                                            if(!$getData['code']){
                                                throw new \Exception($getData['msg']);
                                            }
                                            $middle_school_id =  $getData['data'];
                                            $birthplaceData = Db::table("deg_sys_address_birthplace")->where('address',$childData['api_address'])->find();;
                                            if(!empty($birthplaceData)){
                                                //如果户籍地址有学校认领
                                                if($birthplaceData['primary_school_id'] == $primary_school_id || $birthplaceData['middle_school_id'] == $middle_school_id){
                                                    $chk_cross = 1;
                                                }
                                            }
                                        }
                                    }
                                }else{
                                    //无匹配派出所
                                    $auto_check_child = -2;//学生信息无结果
                                    $auto_check_birthplace_area = -1;//片区户籍无结果
                                    $auto_check_birthplace_main_area = -1;//主城区户籍无结果
                                    $birthplace_status = 5;
                                }
                            }elseif($tmpData['check_child'] == -1){
                                $auto_check_child = -2;//学生信息无结果
                                $auto_check_birthplace_area = -1;//片区户籍无结果
                                $auto_check_birthplace_main_area = -1;//主城区户籍无结果
                                $birthplace_status = 5;//未对比成功
                                //非襄阳市身份证
                                if(substr($childData['idcard'],0,4) != '4206'){
                                    $birthplace_status = 4;//非襄阳市
                                }
                            }
                            //处理六年级学籍信息
                            $sixthGrade = (new SixthGrade())->where('id_card', $childData['idcard'])->find();
                            if(!empty($sixthGrade)){
                                $student_status = 2;//学生学籍不在本区
                                if($applyData['region_id'] == $sixthGrade['region_id']){
                                    $student_status = 1;//学生学籍在本区
                                }
                            }else{
                                $student_status = 3;//学生学籍无结果
                            }
                            //获取招生年龄配置
                            $region_time = (new RegionSetTime())
                                ->where('region_id',$applyData['region_id'])
                                ->where('grade_id',$applyData['school_type'])
                                ->find();
                            $child_age = 0;
                            if($region_time){
                                //                        $birthday = strtotime($childInfo['birthday']);
                                $birthday = $childData['birthday'];
                                if($birthday >= $region_time['start_time'] && $birthday <= $region_time['end_time']){
                                    $child_age = 1;  //足龄
                                }else if($birthday < $region_time['start_time']){
                                    $child_age = 3; // 超龄
                                }else{
                                    $child_age = 2; //不足龄
                                }
                            }
                            //如果家长信息已比对
                            if($tmpData['check_family'] == 1){
                                //如果有比对后的区域ID
                                if($familyData['api_region_id'] > 0){
                                    //如果对比申请的区县和公安局区县一样 则是本区县户籍
                                    if($familyData['api_region_id'] == $applyData['region_id']){
                                        //本区县非主城区
                                        $auto_check_family = 1;//家长信息本区县非主城区
                                        //派出所对应主城区
                                        if($familyData['is_area_main'] == 1){
                                            //本区县主城区
                                            $auto_check_family = 2;//家长信息本区县主城区
                                        }
                                        //不是本区域
                                    }else{
                                        $auto_check_family = -1;//家长信息不在本区
                                    }
                                }else{
                                    //无匹配派出所
                                    $auto_check_family = -2;//家长信息无结果
                                }
                            }elseif($tmpData['check_family'] == -1){
                                $auto_check_family = -2;//家长信息无结果
                            }
                            //比对监护人关系
                            $resReckon = $reckon->CheckGuardian($childData['idcard'],$familyData['idcard']);
                            if($resReckon['code']){
                                if($resReckon['data']['status'] == 1){
                                    $auto_check_relation = 1;//属实
                                    $guardian_relation = 1;
                                }elseif($resReckon['data']['status'] == -1){
                                    $auto_check_relation = 2;//不属实
                                    $guardian_relation = 2;
                                }else{
                                    $auto_check_relation = -1;//无结果
                                    $guardian_relation = 3;
                                }
                            }
                            //如果房产信息已比对
                            if($tmpData['check_house'] == 1){
                                $regionData = filter_value_one($region, 'id', $applyData['region_id']);
                                if(count($regionData)){
                                    $region_code = $regionData['simple_code'];
                                }else{
                                    throw new \Exception('入学申报区域数据错误');
                                }
                                $school = Cache::get('school',[]);
                                $central = Cache::get('central',[]);
                                $police = Cache::get('police',[]);
                                //获取申报区域已勾选缩略对应详细房产信息
                                $addressData = Db::table("deg_sys_address_{$region_code}")->where('address',$houseData['api_address'])->find();;
                                if(!empty($addressData)){
                                    $auto_check_house_area = 1;//是片区有房
                                    $auto_check_house_main_area = 1;//是片区主城区有房
                                    $house_status = 2;//无匹配学校
                                    if($applyData['school_type'] == 1 && $addressData['primary_school_id']){
                                        //房产匹配学校-小学
                                        $house_status = 1;//有匹配学校
                                        $house_matching_school_id = $addressData['primary_school_id'];
                                        //获取学校信息
                                        $schoolData = filter_value_one($school, 'id', $addressData['primary_school_id']);
                                        //如果学校信息存在
                                        if(count($schoolData)){
                                            //如果学校受教管会管理
                                            if($schoolData['central_id']){
                                                //获取教管会信息
                                                $centralData = filter_value_one($central, 'id', $schoolData['central_id']);
                                                if(count($centralData)){
                                                    //获取派出所信息
                                                    $policeData = filter_value_one($police, 'id', $centralData['police_id']);
                                                    if(count($policeData)){
                                                        if(!$policeData['is_main_area']){
                                                            $auto_check_house_main_area = 2;//不是片区主城区有房
                                                        }
                                                    }else{
                                                        throw new \Exception('派出所数据错误');
                                                    }
                                                }else{
                                                    throw new \Exception('教管会数据错误');
                                                }
                                            }
                                        }else{
                                            throw new \Exception('学校数据错误');
                                        }
                                    }
                                    if($applyData['school_type'] == 2 && $addressData['middle_school_id']){
                                        //房产匹配学校-初中
                                        $house_status = 1;//有匹配学校
                                        $house_matching_school_id = $addressData['middle_school_id'];
                                        //获取学校信息
                                        $schoolData = filter_value_one($school, 'id', $addressData['middle_school_id']);
                                        //如果学校信息存在
                                        if(count($schoolData)){
                                            //如果学校受教管会管理
                                            if($schoolData['central_id']){
                                                //获取教管会信息
                                                $centralData = filter_value_one($central, 'id', $schoolData['central_id']);
                                                if(count($centralData)){
                                                    //获取派出所信息
                                                    $policeData = filter_value_one($police, 'id', $centralData['police_id']);
                                                    if(count($policeData)){
                                                        if(!$policeData['is_main_area']){
                                                            $auto_check_house_main_area = 2;//不是片区主城区有房
                                                        }
                                                    }else{
                                                        throw new \Exception('派出所数据错误');
                                                    }
                                                }else{
                                                    throw new \Exception('教管会数据错误');
                                                }
                                            }
                                        }else{
                                            throw new \Exception('学校数据错误');
                                        }
                                    }
                                }else{
                                    $auto_check_house_area = 2;//不是片区有房
                                    $auto_check_house_main_area = 2;//不是片区主城区有房
                                    $house_status = 2;//无匹配学校
                                }
                                if($auto_check_house_area == 0){
                                    //获取申报区域完整地址房产信息
                                    $addressData = Db::table("deg_sys_address_intact")->where('address',$houseData['api_address'])->find();;
                                    if(!empty($addressData)){
                                        $auto_check_house_area = 1;//是片区有房
                                        $auto_check_house_main_area = 1;//是片区主城区有房
                                        $house_status = 2;//无匹配学校
                                        if($applyData['school_type'] == 1 && $addressData['primary_school_id']){
                                            //房产匹配学校-小学
                                            $house_status = 1;//有匹配学校
                                            $house_matching_school_id = $addressData['primary_school_id'];
                                            //获取学校信息
                                            $schoolData = filter_value_one($school, 'id', $addressData['primary_school_id']);
                                            //如果学校信息存在
                                            if(count($schoolData)){
                                                //如果学校受教管会管理
                                                if($schoolData['central_id']){
                                                    //获取教管会信息
                                                    $centralData = filter_value_one($central, 'id', $schoolData['central_id']);
                                                    if(count($centralData)){
                                                        //获取派出所信息
                                                        $policeData = filter_value_one($police, 'id', $centralData['police_id']);
                                                        if(count($policeData)){
                                                            if(!$policeData['is_main_area']){
                                                                $auto_check_house_main_area = 2;//不是片区主城区有房
                                                            }
                                                        }else{
                                                            throw new \Exception('派出所数据错误');
                                                        }
                                                    }else{
                                                        throw new \Exception('教管会数据错误');
                                                    }
                                                }
                                            }else{
                                                throw new \Exception('学校数据错误');
                                            }
                                        }
                                        if($applyData['school_type'] == 2 && $addressData['middle_school_id']){
                                            //房产匹配学校-初中
                                            $house_status = 1;//有匹配学校
                                            $house_matching_school_id = $addressData['middle_school_id'];
                                            //获取学校信息
                                            $schoolData = filter_value_one($school, 'id', $addressData['middle_school_id']);
                                            //如果学校信息存在
                                            if(count($schoolData)){
                                                //如果学校受教管会管理
                                                if($schoolData['central_id']){
                                                    //获取教管会信息
                                                    $centralData = filter_value_one($central, 'id', $schoolData['central_id']);
                                                    if(count($centralData)){
                                                        //获取派出所信息
                                                        $policeData = filter_value_one($police, 'id', $centralData['police_id']);
                                                        if(count($policeData)){
                                                            if(!$policeData['is_main_area']){
                                                                $auto_check_house_main_area = 2;//不是片区主城区有房
                                                            }
                                                        }else{
                                                            throw new \Exception('派出所数据错误');
                                                        }
                                                    }else{
                                                        throw new \Exception('教管会数据错误');
                                                    }
                                                }
                                            }else{
                                                throw new \Exception('学校数据错误');
                                            }
                                        }
                                    }else{
                                        $auto_check_house_area = 2;//不是片区有房
                                        $auto_check_house_main_area = 2;//不是片区主城区有房
                                        $house_status = 2;//无匹配学校
                                    }
                                }
                                //如果学生比对有派出所ID
                                if($childData['api_policestation_id']){
                                    //获取跨区派出所
                                    $getData = $dictionary->findValue('dictionary', 'SYSKQCL', 'SYSKQPCS');
                                    if(!$getData['code']){
                                        throw new \Exception($getData['msg']);
                                    }
                                    $filter_police_id = $getData['data'];
                                    //匹配到跨区派出所并且申报区域未对应派出所区域
                                    if($childData['api_policestation_id'] == $filter_police_id && $applyData['region_id'] != $childData['api_region_id']){
                                        $getData = $dictionary->findValue('dictionary', 'SYSKQPCS','SYSKQXXYI');
                                        if(!$getData['code']){
                                            throw new \Exception($getData['msg']);
                                        }
                                        $primary_school_id =  $getData['data'];
                                        $getData = $dictionary->findValue('dictionary', 'SYSKQPCS','SYSKQXXER');
                                        if(!$getData['code']){
                                            throw new \Exception($getData['msg']);
                                        }
                                        $middle_school_id =  $getData['data'];
                                        //如果房产地址匹配到勾选户籍的房产
                                        $birthplaceData = Db::table("deg_sys_address_birthplace")->where('address',$houseData['api_address'])->find();;
                                        if(!empty($birthplaceData)){
                                            //如果房产有学校认领
                                            if($birthplaceData['primary_school_id'] == $primary_school_id || $birthplaceData['middle_school_id'] == $middle_school_id){
                                                $chk_cross = 1;
                                            }
                                        }
                                    }
                                }
                            }elseif($tmpData['check_house'] == -1){
                                $auto_check_house_area = -1;//片区有房无结果
                                $auto_check_house_main_area = -1;//片区主城区有房无结果
                                $house_status = 3;//房产信息无结果
                            }
                            //如果工商信息已比对
                            if($tmpData['check_company'] == 1){
                                $company_region_id = 0;
                                $companyRegion = filter_value_one($region, 'simple_code', $companyData['api_area']);
                                if(count($companyRegion)){
                                    $company_region_id = $companyRegion['id'];
                                }
                                if(($companyData['api_company_status'] == '存续（在营、开业、在册）') && ($familyData['parent_name'] == $companyData['api_lawer_name']) && $company_region_id == $applyData['region_id']){
                                    $auto_check_company = 1;//本区经商
                                    $business_license_status = 1;
                                }else{
                                    $auto_check_company = 2;//不是本区经商
                                }
                            }elseif($tmpData['check_company'] == -1){
                                $auto_check_company = -1;//工商信息无结果
                            }
                            //如果社保信息已比对
                            if($tmpData['check_insurance'] == 1){
                                $insurance_region_id = 0;
                                $insuranceRegion = filter_value_one($region, 'simple_code', $insuranceData['api_adcode']);
                                if(count($insuranceRegion)){
                                    $insurance_region_id = $insuranceRegion['id'];
                                }
                                if(!empty($insuranceData['api_end_time']) && strtotime('-1year') > strtotime($insuranceData['api_join_time'])  && $insurance_region_id == $applyData['region_id']){
                                    $auto_check_insurance = 1;//本区社保
                                    $insurance_status = 1;
                                }else{
                                    $auto_check_insurance = 2;//不是本区社保
                                }
                            }elseif($tmpData['check_insurance'] == -1){
                                $auto_check_insurance = -1;//社保无结果
                            }
                            //如果居住证已比对
                            if($tmpData['check_residence'] == 1){
                                $residence_region_id = 0;
                                $residenceRegion = filter_value_one($region, 'simple_code', $residenceData['api_area_code']);
                                if(count($residenceRegion)){
                                    $residence_region_id = $residenceRegion['id'];
                                }
                                if(date_to_unixtime($residenceData['api_end_time']) > time() && $residence_region_id == $applyData['region_id']){
                                    $auto_check_residence = 1;//本区居住证
                                    $residence_permit_status = 1;
                                }else{
                                    $auto_check_residence = 2;//不是本区居住证
                                }
                            }elseif($tmpData['check_residence'] == -1){
                                $auto_check_residence = -1;//社保无结果
                            }
                            //如果三代同堂信息已比对
                            if($tmpData['check_generations'] == 1){
                                $auto_check_ancestor = -1;//无结果
                                //如果学生和监护人以及祖辈户口同本
                                if($ancestorData['api_parent_status'] == 1 && $ancestorData['api_grandparent_status'] == 1){
                                    $auto_check_ancestor = 1;//属实
                                }
                                //如果单亲状态比对为结婚
                                if($ancestorData['singled'] && $ancestorData['api_marriage_status']){
                                    $auto_check_ancestor = 2;//不属实
                                }
                                //如果学生和监护人以及祖辈存在户口不同本
                                if($ancestorData['api_parent_status'] == -1 || $ancestorData['api_grandparent_status'] == -1){
                                    $auto_check_ancestor = 2;//不属实
                                }
                                //如果学生、父母名下有房
                                if($ancestorData['api_child_house'] == 1 || $ancestorData['api_father_house'] == 1 || $ancestorData['api_mother_house'] == 1){
                                    $auto_check_ancestor = 2;//不属实
                                }
                            }elseif($tmpData['check_generations'] == -1){
                                $auto_check_ancestor = -1;//无结果
                            }
                            //检查比对状态是否均已完成
                            $auto_check_completed = 1;
                            if($auto_check_child == 0 || $auto_check_family == 0 || $auto_check_relation == 0 ){
                                $auto_check_completed = 0;
                            }
                            if($auto_check_birthplace_area == 0 || $auto_check_birthplace_main_area == 0 || $auto_check_house_area == 0 || $auto_check_house_main_area == 0){
                                $auto_check_completed = 0;
                            }
                            if($auto_check_birthplace_area == 0 || $auto_check_birthplace_main_area == 0 || $auto_check_house_area == 0 || $auto_check_house_main_area == 0){
                                $auto_check_completed = 0;
                            }
                            if($applyStatusData['need_check_company'] && $auto_check_company == 0){
                                $auto_check_completed = 0;
                            }
                            if($applyStatusData['need_check_insurance'] && $auto_check_insurance == 0){
                                $auto_check_completed = 0;
                            }
                            if($applyStatusData['need_check_residence'] && $auto_check_residence == 0){
                                $auto_check_completed = 0;
                            }
                            if($applyStatusData['need_check_ancestor'] && $auto_check_ancestor == 0){
                                $auto_check_completed = 0;
                            }
                            $status_data['auto_check_child'] = $auto_check_child;
                            $status_data['auto_check_family'] = $auto_check_family;
                            $status_data['auto_check_relation'] = $auto_check_relation;
                            $status_data['auto_check_birthplace_area'] = $auto_check_birthplace_area;
                            $status_data['auto_check_birthplace_main_area'] = $auto_check_birthplace_main_area;
                            $status_data['auto_check_house_area'] = $auto_check_house_area ;
                            $status_data['auto_check_house_main_area'] = $auto_check_house_main_area ;
                            $status_data['auto_check_company'] = $auto_check_company;
                            $status_data['auto_check_insurance'] = $auto_check_insurance;
                            $status_data['auto_check_residence'] = $auto_check_residence;
                            $status_data['auto_check_ancestor'] = $auto_check_ancestor;
                            $status_data['auto_check_completed'] = $auto_check_completed;

                            $detail_data['chk_cross'] = $chk_cross;
                            $detail_data['child_age_status'] = $child_age;
                            $detail_data['birthplace_status'] = $birthplace_status;
                            $detail_data['guardian_relation'] = $guardian_relation;
                            $detail_data['house_status'] = $house_status;
                            $detail_data['student_status'] = $student_status;
                            $detail_data['insurance_status'] = $insurance_status;
                            $detail_data['business_license_status'] = $business_license_status;
                            $detail_data['residence_permit_status'] = $residence_permit_status;
                            $detail_data['house_matching_school_id'] = $house_matching_school_id;
                            //检查比对项目是否均已完成
                            $check_completed = 1;
                            if($tmpData['check_child'] == 0 || $tmpData['check_family'] == 0 || $tmpData['check_house'] == 0){
                                $check_completed = 0;
                            }
                            if($tmpData['other_family_id'] && $tmpData['check_other'] == 0){
                                $check_completed = 0;
                            }
                            if($tmpData['ancestors_family_id'] && $tmpData['check_ancestors'] == 0){
                                $check_completed = 0;
                            }
                            if($tmpData['company_id'] && $tmpData['check_company'] == 0){
                                $check_completed = 0;
                            }
                            if($tmpData['insurance_id'] && $tmpData['check_insurance'] == 0){
                                $check_completed = 0;
                            }
                            if($tmpData['residence_id'] && $tmpData['check_residence'] == 0){
                                $check_completed = 0;
                            }
                            if($tmpData['ancestor_id'] && $tmpData['check_generations'] == 0){
                                $check_completed = 0;
                            }
                            Db::startTrans();
                            try{
                                Db::name('UserApply')->where('id', $tmpData['apply_id'])->update(['house_school_id' => $house_matching_school_id]);
                                Db::name('UserApplyTmp')->where('id', $tmp_id)->update(['check_completed' => $check_completed]);
                                Db::name('UserApplyStatus')->where('user_apply_id', $tmpData['apply_id'])->update($status_data);
                                Db::name('UserApplyDetail')->where('user_apply_id',$tmpData['apply_id'])->update($detail_data);
                                Db::commit();
                            }catch (\Exception $exception){
                                Log::write($exception->getMessage() ?: Lang::get('system_error'));
                                Db::rollback();
                            }
                            $checkNum += 1;
                            Db::commit();
                        }catch (\Exception $exception){
                            Db::rollback();
                        }
                    }
                }
            }
            Cache::inc('execute_completed',$checkNum);
        }catch (\Exception $exception){
            Log::record($exception->getMessage() ?: Lang::get('system_error'));
            Db::rollback();
        }
    }

    /**
     * 多线程执行比对程序
     * @param $base
     * @param $count
     */
    private function asyncContrast($base,$thread_count,$batch_count,$method_name){
        try {
            $sys_url = Cache::get('special_use_url');
            for($i = 1;$i <= $thread_count;$i++){
                $num = ($i -1) * $batch_count;
                $num2 = $i * $batch_count;
                $tmp = 'ch'.$i;
                $$tmp = curl_init();
                curl_setopt($$tmp, CURLOPT_URL, $sys_url.'/api/TaskHandle/'.$method_name.'?minid='.($base + $num + 1).'&maxid='.($base + $num2));
                curl_setopt($$tmp, CURLOPT_RETURNTRANSFER, 1);
//                curl_setopt($$tmp, CURLOPT_TIMEOUT, 1);
            }
            $mh = curl_multi_init(); //1 创建批处理cURL句柄
            for($i = 1;$i <= $thread_count;$i++){
                $tmp = 'ch'.$i;
                curl_multi_add_handle($mh, $$tmp); //2 增加句柄
            }
            $active = null;
            do {
                while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM) ;
                if ($mrc != CURLM_OK) { break; }
                while ($done = curl_multi_info_read($mh)) {
                    $info = curl_getinfo($done['handle']);
                    $error = curl_error($done['handle']);
                    $result[] = curl_multi_getcontent($done['handle']);
                    // $responses[$map[(string) $done['handle']]] = compact('info', 'error', 'results');
                    curl_multi_remove_handle($mh, $done['handle']);
                    curl_close($done['handle']);
                }
                if ($active > 0) {
                    curl_multi_select($mh);
                }
            } while ($active);
            curl_multi_close($mh); //7 关闭全部句柄
        } catch (\Exception $exception) {
            Log::record($exception->getMessage() ?: Lang::get('system_error'));
        }
    }

}