<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use think\facade\Db;
use think\response\Json;
use think\facade\Cache;

class Pay extends MobileEducation
{

    /**
     * 支付前详情
     * @return Json
     */
    public function payBeforeInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->result['user_id'],
                    'child_id',
                ]);
                if(!isset($data['child_id']) || intval($data['child_id']) <= 0){
                    throw new \Exception('系统错误');
                }

                $apply = Db::name("user_apply")
                    ->alias('a')
                    ->leftJoin('sys_school s','a.result_school_id=s.id and s.deleted=0 and s.disabled=0')
                    ->where('a.user_id', $this->userInfo['id'])
                    ->where('a.resulted', 1)
                    ->where('a.school_attr', 2)
                    ->where('a.voided', 0)
                    ->where('a.prepared', 1)
                    ->where('a.child_id', $data['child_id'])
                    ->where('a.paid', 0)
                    ->where('a.deleted', 0)
                    ->where('s.onlinepay', 1)
                    ->where('s.fee_code', '<>','')
                    ->where('s.fee', '>',0)
                    ->field([
                        'a.paid',
                        'a.id as user_apply_id',
                        's.onlinepay',
                        's.fee_code',
                        'a.school_attr',
                        'a.school_type',
                        's.school_name',
                        's.fee',
                        's.id as school_id',
                    ])
                    ->findOrEmpty();
                if(!$apply){
                    throw new \Exception('不符合民办缴费条件');
                }

                $data['school_name'] = $apply['school_name'];
                $data['school_type_text'] = $apply['school_type'] == 1 ? '小学' : '初中';
                $data['school_attr_text'] = $apply['school_attr'] == 1 ? '公办' : '民办';
                $data['school_grade'] = $apply['school_attr'] == 1 ? '一年级' : '七年级';
                $data['school_fee'] = $apply['fee'];
                $data['user_apply_id'] = $apply['user_apply_id'];
                $data['school_id'] = $apply['school_id'];

                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 支付写入数据  返回爱襄阳支付url
     * @return Json
     * */
    public function pay(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->result['user_id'],
                    'user_apply_id',
                    'child_id',
                    'school_id',
                ]);
                if(!isset($data['child_id']) || intval($data['child_id']) <= 0){
                    throw new \Exception('系统错误');
                }

                if(!isset($data['user_apply_id']) || intval($data['user_apply_id']) <= 0){
                    throw new \Exception('系统错误');
                }

                if(!isset($data['school_id']) || intval($data['school_id']) <= 0){
                    throw new \Exception('系统错误');
                }
                $apply = Db::name("user_apply")
                    ->alias('a')
                    ->leftJoin('sys_school s','a.result_school_id=s.id and s.deleted=0 and s.disabled=0')
                    ->where('a.user_id', $this->userInfo['id'])
                    ->where('a.resulted', 1)
                    ->where('a.school_attr', 2)
                    ->where('a.voided', 0)
                    ->where('a.prepared', 1)
                    ->where('a.child_id', $data['child_id'])
                    ->where('a.result_school_id', $data['school_id'])
                    ->where('a.id', $data['user_apply_id'])
                    ->where('a.paid', 0)
                    ->where('a.deleted', 0)
                    ->where('s.onlinepay', 1)
                    ->where('s.fee_code', '<>','')
                    ->where('s.fee', '>',0)
                    ->field([
                        'a.paid',
                        'a.family_id',
                        'a.child_id',
                        'a.id' => 'user_apply_id',
                        's.onlinepay',
                        's.fee_code',
                        'a.school_attr',
                        'a.school_type',
                        's.school_name',
                        's.fee',
                        's.id' => 'school_id',
                        's.region_id' => 'school_region_id',
                    ])
                    ->findOrEmpty();
                if(!$apply){
                    throw new \Exception('不符合民办缴费条件');
                }
                $pay = Db::name('user_cost_pay')
                    ->master(true)
                    ->where('user_id',$data['user_id'])
                    ->where('user_apply_id',$data['user_apply_id'])
                    ->where('status',1)
                    ->find();
                if($pay){
                    throw new \Exception('该学生已缴费');
                }
                $max_id = Db::name('user_cost_pay')
                    ->master(true)
                    ->where('deleted', 0)
                    ->max('id');
                $temp_num = 100000000;
                $new_num = $max_id + $temp_num + 1;
                $cost_code = 'MB'.date('Ymd').substr($new_num,1,8);
                $insert_data['user_id'] = $data['user_id'];
                $insert_data['user_apply_id'] = $data['user_apply_id'];
                $insert_data['region_id'] = $apply['school_region_id'];
                $insert_data['school_id'] = $apply['school_id'];
                $insert_data['amount'] = $apply['fee'];
                $insert_data['cost_code'] = $cost_code;
                $insert_id = Db::name('user_cost_pay')->insertGetId($insert_data);
                if(!$insert_id){
                    throw new \Exception('支付异常');
                }
                $idcard = Db::name('user_family')->where('id', $apply['family_id'])->where('deleted', 0)->value('idcard');
                if(empty($idcard)){
                    throw new \Exception('支付用户信息错误');
                }
                $child_idcard = Db::name('user_child')->where('id', $apply['child_id'])->where('deleted', 0)->value('idcard');
                if(empty($child_idcard)){
                    throw new \Exception('学生信息错误');
                }
                $url_data['epayCode'] = $apply['fee_code'];
                $url_data['payInput'] = $apply['fee'];
                $url_data['input1'] = $child_idcard;
                $url_data['swiftNumber'] = $cost_code;
                $url_data['cardId'] = $idcard;
                $url_data['mode'] = 3;
                $url_param = http_build_query($url_data);
                $ixypay_url = Cache::get('ixypay_url');
                $url = $ixypay_url.$url_param;
                $res = [
                    'code' => 1,
                    'data' => $url
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 获取学生
     * @return Json
     */
    public function getChild(): Json
    {
        if ($this->request->isPost()) {
            try {
                $apply = Db::name("user_apply")
                    ->alias('a')
                    ->leftJoin('sys_school s','a.result_school_id=s.id and s.deleted=0 and s.disabled=0')
                    ->where('a.user_id', $this->userInfo['id'])
                    ->where('a.resulted', 1)
                    ->where('a.school_attr', 2)
                    ->where('a.voided', 0)
                    ->where('a.prepared', 1)
                    ->where('a.paid', 0)
                    ->where('s.onlinepay', 1)
                    ->where('s.fee_code', '<>','')
                    ->where('s.fee', '>',0)
                    ->field(['a.paid','s.onlinepay','s.fee_code','a.child_id'])
                    ->select()
                    ->toArray();
                $child = [];
                if($apply){
                    foreach($apply as $key => $value){
                        $child[] = Db::name('user_child')->where('id',$value['child_id'])->where('deleted',0)->find();
                    }
                }
                $res = [
                    'code' => 1,
                    'data' => $child
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

}