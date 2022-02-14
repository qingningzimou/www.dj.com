<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\model\Plan;
use app\common\model\Schools;
use app\common\model\WorkCityConfig;
use app\mobile\model\user\Child;
use app\mobile\model\user\Apply;
use app\mobile\model\user\Family;
use app\mobile\model\user\House;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;

class UserCenter extends MobileEducation
{

    /**
     * 获取学生
     */
    public function getChild(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if($this->request->has('school_attr') && $this->result['school_attr'] > 0) {
                    $where[] = ['u.school_attr','=',$this->result['school_attr']];
                }
                $data = Db::name('user_child')
                    ->alias('c')
                    ->leftJoin('user_apply u','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.voided",0)
                    ->where($where)
                    ->where("u.user_id",$this->userInfo['id'])
                    ->field(['c.real_name','c.idcard','c.id'])
                    ->select()
                    ->toArray();

                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 获取申报详情
     * @param int child_id 学生id
     * @return Json
     */
    public function getApplyDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'child_id',
                ]);
                if(!isset($data['child_id']) || intval($data['child_id']) <= 0){
                    throw new \Exception('系统错误');
                }
                $apply = (new Apply())
                    ->where('user_id',$data['user_id'])
                    ->where('child_id',$data['child_id'])
                    ->find();
                if(!$apply){
                    $res = [
                        'code' => 2,
                        'msg' => '您还没有填写申报数据',
                    ];
                    return parent::ajaxReturn($res);
                }
                $school_type = [1=>'小学',2=>'初中'];
                $apply_info = [];
                if($apply['school_attr'] == 1){
                    $region = Cache::get('region',[]);
                    $region = filter_value_one($region, 'id', $apply['region_id']);
                    $apply_info['header']['title'] = '公办学校';
                    $apply_info['header']['label'] = '就读区域';
                    $apply_info['header']['name'] = $region['region_name'];
                }

                if($apply['school_attr'] == 2){

                    $school = (new Schools())
                        ->where('id',$apply['apply_school_id'])
                        ->where('deleted',0)
                        ->where('disabled',0)
                        ->find();
                    if(!$school){
                        throw new \Exception('选择的学校不存在');
                    }
                    $apply_info['header']['title'] = '民办学校';
                    $apply_info['header']['label'] = '学校名称';
                    $apply_info['header']['name'] = $school['school_name'];
                }

                $child = (new Child())
                    ->where('id',$data['child_id'])
                    ->where('deleted',0)
                    ->find();
                if(!$child) {
                    throw new \Exception('学生信息不存在');
                }

                $family = (new Family())
                    ->where('id',$apply['family_id'])
                    ->where('deleted',0)
                    ->find();
                if(!$family) {
                    throw new \Exception('监护人信息不存在');
                }

                $house = (new House())
                    ->where('id',$apply['house_id'])
                    ->where('deleted',0)
                    ->find();
                if(!$house) {
                    throw new \Exception('房产信息不存在');
                }

                $plan = (new Plan())->where('id',$apply['plan_id'])->find();
                if(!$plan) {
                    throw new \Exception('招生计划信息不存在');
                }

                $dictionary = new FilterData();
                $houseType = $dictionary->resArray('dictionary', 'SYSFCLX');
                if(!$houseType['code']){
                    throw new \Exception($houseType['msg']);
                }

                $house_parent_name = $family['parent_name'];
                $apply_info['basic']['title'] = '基本信息';
                $apply_info['basic']['child_name'] = $child['real_name'];
                $apply_info['basic']['child_idcard'] = $child['idcard'];
                $region = Cache::get('region',[]);
                $region = filter_value_one($region, 'id', $child['region_id']);
                $apply_info['basic']['child_region_name'] = $region['region_name'];
                $apply_info['basic']['plan_name'] = $school_type[$plan['school_type']];
                $apply_info['basic']['family_name'] = $family['parent_name'];
                $apply_info['basic']['family_idcard'] = $family['idcard'];

                //三代同堂
                $ancestor = (new \app\mobile\model\user\Ancestor())
                    ->where('family_id',$apply['ancestor_id'])
                    ->where('deleted',0)
                    ->find();
                if($ancestor){
                    $father = (new Family())
                        ->where('id',$ancestor['father_id'])
                        ->find();
                    if($father){
                        $apply_info['same']['father_name'] = $father['parent_name'];
                        $apply_info['same']['father_idcard'] = $father['idcard'];
                    }
                    $mother = (new Family())
                        ->where('id',$ancestor['mother_id'])
                        ->find();
                    if($mother){
                        $apply_info['same']['mother_name'] = $mother['parent_name'];
                        $apply_info['same']['mother_idcard'] = $mother['idcard'];
                    }

                    $ancestors = (new Family())
                        ->where('id',$ancestor['family_id'])
                        ->find();
                    $apply_info['same']['ancestors_name'] = $ancestors['parent_name'];
                    $apply_info['same']['ancestors_idcard'] = $ancestors['idcard'];
                    $relation = [
                        1 => '父亲',
                        2 => '母亲',
                        3 => '爷爷',
                        4 => '奶奶',
                        5 => '外公',
                        6 => '外婆',
                    ];
                    if(array_key_exists($ancestors['relation'],$relation)) {
                        $apply_info['same']['relation_name'] = $relation[$ancestors['relation']];
                    }
                    $apply_info['same']['singled'] = $ancestor['singled'] == 1 ? '是' : '否';
                    $house_parent_name = $ancestors['parent_name'];
                }

                $apply_info['house']['has_house'] = $house['house_type'] != 2 ? '有房' : '无房';
                $apply_info['house']['parent_name'] = $house_parent_name;
                $apply_info['house']['address'] = $house['house_address'];

                $house_type_name = filter_value_one($houseType['data'], 'dictionary_value', $house['house_type']);
                $apply_info['house']['house_type_name'] = $house_type_name;

                $res = [
                    'code' => 1,
                    'data' => $apply_info
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 用户消息列表
     */
    public function getMessageList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = Db::name('user_message')
                    ->where("user_id",$this->userInfo['id'])
                    ->where("deleted",0)
                    ->field(['id','contents','create_time','read_time'])
                    ->order('create_time','DESC')
                    ->select()
                    ->toArray();
                if(count($data)){
                    foreach ($data as $k => $v){
                        if(!$v['read_time']){
                            Db::name("user_message")
                                ->where('id',$v['id'])
                                ->inc('read_total')
                                ->update(['end_time'=>time(),'read_time'=>time()]);
                        }else{
                            Db::name("user_message")
                                ->where('id',$v['id'])
                                ->inc('read_total')
                                ->update(['end_time'=>time()]);
                        }
                    }
                }
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * @return Json
     */
    public function getUserInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $res = [
                    'code' => 1,
                    'data' => $this->userInfo,
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    public function menuIsShow(): Json
    {
        if ($this->request->isPost()) {
            try {

                //变更区域是否显示
                $data = [];
                $data['open_change_region'] = 0;
                $config = (new WorkCityConfig())
                    ->where('item_key', 'BGQY')
                    ->where('deleted', '0')
                    ->find();
                if ($config) {
                    $time = json_decode($config['item_value'], true);

                    if (time() >= strtotime($time['startTime']) && time() <= strtotime($time['endTime'])) {
                        $data['open_change_region'] = 1;
                    }
                }

                //补充资料是否显示
                $data['open_supplement'] = 0;
                $apply = (new Apply())
                    ->where('user_id',$this->userInfo['id'])
                    ->where('resulted',0)
                    ->where('school_attr',1)
                    ->where('voided',0)
                    ->select()
                    ->toArray();

                //开启退费是否显示
                $data['open_refund'] = 0;
                if($apply){
                    foreach($apply as $key=>$value){
                        $supplement = (new \app\mobile\model\user\Supplement())
                            ->where('user_apply_id',$value['id'])
                            ->where('status',0)
                            ->find();
                        if($supplement){
                            $data['open_supplement'] = 1;
                        }
                    }
                }

                $refund_apply = (new Apply())
                    ->where('user_id',$this->userInfo['id'])
                    ->where('school_attr',2)
                    ->where('voided',0)
                    ->select()
                    ->toArray();

                if($refund_apply){
                    foreach($refund_apply as $key => $value){
                        //开启退费
                        if($value['open_refund']){
                            $data['open_refund'] = 1;
                        }
                    }
                }

                //缴费记录是否显示
                $data['open_money_record'] = 0;
                $open_money_record = Db::name('user_cost_pay')
                    ->where('user_id',$this->userInfo['id'])
                    ->where('status',1)
                    ->where('deleted',0)
                    ->count();
                //缴费记录
                if($open_money_record){
                    $data['open_money_record'] = 1;
                }

                $res = [
                    'code' => 1,
                    'data' => $data,
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 用户缴费信息列表
     */
    public function getPayList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = Db::name('user_cost_pay')
                    ->where('user_id',$this->userInfo['id'])
                    ->where('status',1)
                    ->where('deleted',0)
                    ->select()
                    ->toArray();
                foreach ($data as $k => $v){
                    $data[$k]['child_name'] = '';
                    $data[$k]['child_idcard'] = '';
                    $detailData = Db::name('user_apply_detail')
                        ->where('user_apply_id',$v['user_apply_id'])
                        ->where('deleted',0)
                        ->find();
                    if(!empty($detailData)){
                        $data[$k]['child_name'] = $detailData['child_name'];
                        $data[$k]['child_idcard'] = $detailData['child_idcard'];
                    }
                }
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 用户缴费信息详情
     */
    public function getPayInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->only([
                    'id',
                ]);
                $preg = '/^[0-9]+$/';
                if (preg_match($preg, $postData['id']) == 0){
                    throw new \Exception('缴费信息ID只能是数字');
                }
                $payData = Db::name('user_cost_pay')
                    ->where('id',$postData['id'])
                    ->where('user_id',$this->userInfo['id'])
                    ->where('status',1)
                    ->where('deleted',0)
                    ->find();
                if(empty($payData)){
                    throw new \Exception('缴费信息不存在');
                }
                $applyData = Db::name('user_apply')
                    ->alias('a')
                    ->leftJoin('sys_school s','a.result_school_id=s.id and s.deleted=0 and s.disabled=0')
                    ->where('a.id',$payData['user_apply_id'])
                    ->where('a.user_id', $this->userInfo['id'])
                    ->where('a.voided',0)
                    ->where('a.deleted',0)
                    ->field([
                        'a.child_id',
                        'a.school_attr',
                        'a.school_type',
                        's.school_name',
                    ])
                    ->findOrEmpty();
                if(empty($applyData)){
                    throw new \Exception('申报信息不存在');
                }
                $detailData = Db::name('user_apply_detail')
                    ->where('user_apply_id',$payData['user_apply_id'])
                    ->where('deleted',0)
                    ->find();
                if(empty($detailData)){
                    throw new \Exception('学生信息不存在');
                }
                $data['school_name'] = $applyData['school_name'];
                $data['school_type_text'] = $applyData['school_type'] == 1 ? '小学' : '初中';
                $data['school_attr_text'] = $applyData['school_attr'] == 1 ? '公办' : '民办';
                $data['school_grade'] = $applyData['school_attr'] == 1 ? '一年级' : '七年级';
                $data['pay_amount'] = $payData['pay_amount'];
                $data['pay_time'] = $payData['pay_time'];
                $data['order_code'] = $payData['order_code'];
                $data['child_name'] = $detailData['child_name'];
                $data['status'] = $payData['status'];
                $data['refund_status'] = $payData['refund_status'];
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 用户可退费学生列表
     */
    public function getRefundList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = (new Apply())
                    ->where('user_id',$this->userInfo['id'])
                    ->where('open_refund',1)
                    ->where('paid',1)
                    ->where('refund_status',0)  //申请退费  或者申请被拒绝
                    ->where('voided',0)
                    ->where('deleted',0)
                    ->field([
                        'id'
                    ])
                    ->select()
                    ->toArray();
                foreach ($data as $k => $v){
                    $data[$k]['child_name'] = '';
                    $data[$k]['child_idcard'] = '';
                    $data[$k]['pay_amount'] = '';
                    $detailData = Db::name('user_apply_detail')
                        ->where('user_apply_id',$v['id'])
                        ->where('deleted',0)
                        ->find();
                    if(!empty($detailData)){
                        $data[$k]['child_name'] = $detailData['child_name'];
                        $data[$k]['child_idcard'] = $detailData['child_idcard'];
                    }
                    $payData = Db::name('user_cost_pay')
                        ->where('user_apply_id',$v['id'])
                        ->where('status',1)
                        ->where('refund_status',0)
                        ->where('deleted',0)
                        ->find();
                    if(!empty($payData)){
                        $data[$k]['pay_amount'] = $payData['pay_amount'];
                    }
                }
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 学生退费申请
     */
    public function actRefundPay(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'reason',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'], $this->result['user_id']);
                if ($checkHash['code'] == 0) {
                    throw new \Exception($checkHash['msg']);
                }
                $preg = '/^[0-9]+$/';
                if (preg_match($preg, $data['id']) == 0){
                    throw new \Exception('申请ID只能是数字');
                }
                $data['reason'] = trim($data['reason']);
                if(mb_strlen($data['reason']) < 2 ){
                    throw new \Exception('请输入退费原因');
                }
                if(mb_strlen($data['reason']) > 255){
                    throw new \Exception('退费原因在255字符内');
                }
                $applyData = (new Apply())
                    ->where('id',$data['id'])
                    ->where('user_id',$this->result['user_id'])
                    ->where('open_refund',1)
                    ->where('paid',1)
                    ->where('refund_status',0)
                    ->where('voided',0)
                    ->where('deleted',0)
                    ->find();
                if(empty($applyData)){
                    throw new \Exception('退费申请已提交或不存在');
                }
                $payData = Db::name('user_cost_pay')
                    ->where('user_id',$this->userInfo['id'])
                    ->where('user_apply_id',$data['id'])
                    ->where('status',1)
                    ->where('refund_status',0)
                    ->where('deleted',0)
                    ->find();
                if(empty($payData)){
                    throw new \Exception('缴费信息不存在');
                }
                Db::name("user_apply")
                    ->where('id',$data['id'])
                    ->update([
                        'refund_status' => 1
                    ]);
                Db::name('user_cost_refund')->insert([
                    'user_id' => $this->result['user_id'],
                    'user_apply_id' => $data['id'],
                    'region_id' => $applyData['region_id'],
                    'school_id' => $applyData['result_school_id'],
                    'order_code' => $payData['order_code'],
                    'user_cost_pay_id' => $payData['id'],
                    'pay_time' => $payData['pay_time'],
                    'amount' => $payData['pay_amount'],
                    'reason' => $data['reason'],
                ]) ;
                $child_name = Db::name('user_child')->where('id',$applyData['child_id'])->where('deleted',0)->value('real_name');
                Db::name("UserMessage")->insertGetId([
                    'user_id' => $this->result['user_id'],
                    'contents' => '（'.$child_name.'）您的申请已成功提交，请关注平台信息等待审批结果',
                ]);
                $res = [
                    'code' => 1,
                    'data' => '退费申请已提交成功'
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }
}