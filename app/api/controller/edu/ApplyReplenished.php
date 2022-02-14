<?php
namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\Plan;
use app\common\model\SysRegion;
use app\common\model\User;
use app\mobile\model\user\Apply;
use think\Cache;
use think\facade\Lang;
use think\response\Json;
use app\common\validate\comprehensive\ApplyReplenished as validate;
use think\facade\Db;

class ApplyReplenished extends Education
{
    /**
     * 查询用户
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where_apply = [];
                $where_school = [];
                $where_replenished[] = ['r.status','in',[0,1]];

                $where_user = [];


                $where_apply[]= ['r.region_ids','=',$this->userInfo['region_id']];
                $where_school[]= ['r.school_id','=',$this->userInfo['school_id']];

                if($this->request->has('status') && $this->result['status'] !== '')
                {
                    $where_replenished[] = ['r.status','=',$this->result['status']];
                }

                if($this->request->has('applyed') && $this->result['applyed'] !== "")
                {
                    if($this->result['applyed'] == 1){
                        $where_user[] = ['r.apply_status','=',1];
                    }else{
                        $where_user[] = ['r.apply_status','=',0];
                    }
                }
                if($this->request->has('mobile') && $this->result['mobile'])
                {
                    $preg = '/^1[3-9]\d{9}$/';
                    if (preg_match($preg,$this->result['mobile']) == 0){
                        throw new \Exception('手机号码格式不正确');
                    }
                    $where_user[] = ['u.user_name','=',$this->result['mobile']];
                    $where_apply = [];
                    $where_replenished = [];
                    $where_school = [];
                }

                $list['data'] = Db::name('user')
                    ->alias('u')
                    ->leftJoin('apply_replenished r','r.user_id=u.id and r.deleted = 0 and replenished = 0 ')
                    ->leftJoin('user_apply a','a.user_id=u.id and a.deleted=0')
                    ->where($where_apply)
                    ->where($where_school)
                    ->where($where_replenished)
                    ->where($where_user)
                    ->where('u.deleted',0)
                    ->field([
                        'u.id',
                        'u.user_name',
                        'r.id as apply_replenished_id',
                        'r.region_ids',
                        'r.start_time',
                        'r.end_time',
                        'r.status',
                        'r.school_attr_text',
                        'r.school_attr',
                        'r.region_names',
                        'r.apply_status',
                        Db::raw('if (r.end_time - unix_timestamp(now()) > 0 ,r.end_time - unix_timestamp(now()),0) as diff_time'),
                        'a.id as apply_id',
                        'a.child_id',
                    ])
                    ->group('u.user_name,r.region_ids')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                if($list['data']['data']){
                    foreach($list['data']['data'] as $key=>$value){
                       $apply = Db::name("user_apply")->where('user_id',$value['id'])->where('voided',1)->find();
                       if($apply){
                           $list['data']['data'][$key]['voided'] = 1;
                       }else{
                           $list['data']['data'][$key]['voided'] = 0;
                       }

                       if($value['diff_time'] > 0){
                           $list['data']['data'][$key]['diff_time'] = $this->changeTimeType($value['diff_time']);
                       }
                    }
                }


                $res = [
                    'code' => 1,
                    'data' => $list['data']
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
     *
     * @param string user_ids 用户id 逗号连接
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_ids',
                ]);
                if(!isset($data['user_ids']) || intval($data['user_ids']) <= 0) {
                    throw new \Exception('非法错误');
                }
                $regoin = [];
                if($this->userInfo['grade_id'] > $this->area_grade) {
                    $regoin = \think\facade\Cache::get('region');

                    foreach ($regoin as $k => $v){
                        if($v['parent_id'] == '0'){
                            unset($regoin[$k]);
                        }
                    }
                    $data['region'] = array_values($regoin);
                }

                $data['school_attr'] = [
                    '1' => '公办',
                    '2' => '民办',
                ];
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
     * 新增补录
     * @param string user_ids  用户id 逗号连接
     * @param string school_attrs  学校性质 逗号连接
     * @param string region_ids  区域id 逗号连接（区县角色region_ids为自己区县id）
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {

                $data = $this->request->only([
                    'user_ids',
                    'hash'
                ]);

                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'school_add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $region_ids = $this->userInfo['region_id'];

                $user_ids = explode(',',$data['user_ids']);
                $user_ids = array_unique($user_ids);

                $school_attr = Db::name('sys_school')->where('id',$this->userInfo['school_id'])->value('school_attr');
                $school_attr_tmp = [
                    1 => '公办',
                    2 => '民办',
                ];
                $school_attr_array = explode(',',$school_attr);
                $school_attr_array = array_unique($school_attr_array);

                //判断补录必须在招生结束之后才能添加补录
                $planInfo = Plan::where('plan_time', date('Y'))->where('deleted',0)->findOrEmpty();
                if(!$planInfo){
                    throw new \Exception('招生计划不存在');
                }

               foreach($planInfo as $key=>$value){
                    if(in_array(1,$school_attr_array)){
                        if($value['public_end_time'] > time()){
                            throw new \Exception('招生时间没结束，不能补录');
                        }
                    }

                   if(in_array(2,$school_attr_array)){
                       if($value['private_end_time'] > time()){
                           throw new \Exception('招生时间没结束，不能补录');
                       }
                   }
               }
                $school_attr_text = $school_attr_tmp[$school_attr];

                if(is_array($user_ids)){
                    foreach($user_ids as $key=>$value){
                        foreach(explode(',',$region_ids) as $_key => $_value){
                            $region_name = (new SysRegion())->where('id',$_value)->where('deleted',0)->value('region_name');
                            //删除对应区域的补录
                            (new \app\common\model\ApplyReplenished())->editData(['deleted'=>1],['user_id'=>$value,'region_ids'=>$_value]);
                            $add_data = [];
                            $add_data['user_id'] = $value;
                            $add_data['region_ids'] = $_value;
                            $add_data['region_names'] = $region_name;
                            $add_data['school_attr'] = $school_attr;
                            $add_data['school_attr_text'] = rtrim($school_attr_text,'/');
                            $add_data['start_time'] = time();
                            $add_data['end_time'] = time() + 72 * 3600;
                            $add_data['school_id'] = $this->userInfo['school_id'];
                            //添加新的补录
                            $add = (new \app\common\model\ApplyReplenished())->addData($add_data,1);
                            if($add['code'] == 0){
                                throw new \Exception($add['msg']);
                            }
                        }
                        Db::name("ApplyReplenished")
                            ->where('user_id',$value)
                            ->where('end_time','<',time())
                            ->where('deleted',0)
                            ->update(['deleted'=>1]);
                    }
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success'),
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

    /**
     * @param int status 状态 0禁用  1开启
     * @return Json
    */
    public function setStatus(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'user_id',
                    'status',
                    'school_attr',
                    'id'
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $result = (new \app\common\model\ApplyReplenished())
                            ->where('deleted',0)
                            ->where('id',$data['id'])
                            ->find();
                if($result){

                      $edit = (new \app\common\model\ApplyReplenished())->editData(['status'=>$data['status'],'id'=>$data['id']]);
                      if($edit['code'] == 0){
                          throw new \Exception($edit['msg']);
                      }
                }else{
                    throw new \Exception('数据不存在，不能修改');
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success'),
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * @param int user_id
     * @return Json
    */
    public function getVoideDetail(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'user_id',
                ]);

                if(!isset($data['user_id']) || intval($data['user_id']) <= 0){
                    throw new \Exception('系统错误');
                }

               $list['data'] = Db::name('user_apply')
                            ->alias('a')
                            ->leftJoin('user u','u.id=a.user_id and u.deleted=0')
                            ->leftJoin('user_child c','c.id=a.child_id and c.deleted=0')
                            ->leftJoin('user_family f','f.id=a.family_id and f.deleted=0')
                            ->leftJoin('user_house h','h.id=a.house_id and h.deleted=0')
                            ->where("a.user_id",$data['user_id'])
                            ->where('a.voided',1)
                            ->field([
                            'a.id as apply_id',
                            'u.user_name',
                            'c.real_name as child_name',
                            'c.idcard as child_idcard',
                            'c.api_policestation',
                            'c.api_policestation',
                            'c.id as child_id',
                             'f.id as family_id',
                             'h.id as house_id',
                            Db::raw('CASE a.school_attr WHEN 1 THEN "公办" WHEN 2 THEN "民办" ELSE "公办" END as school_attr_text'),
                            Db::raw('CASE a.school_type WHEN 1 THEN "小学" WHEN 2 THEN "初中" ELSE "小学" END as school_type_text'),
                            'f.parent_name',
                            'f.idcard as parent_idcard',
                            Db::raw('CASE f.relation WHEN 1 THEN "父亲" WHEN 2 THEN "母亲" ELSE "其他" END as relation_text'),
                            Db::raw('CASE h.house_type WHEN 1 THEN "产权房" WHEN 2 THEN "租房" WHEN 3 THEN "自建房" WHEN 4 THEN "置换房" WHEN 5 THEN "公租房" WHEN 6 THEN "三代同堂" ELSE "其他" END as house_type_name'),
                            Db::raw('CASE h.code_type WHEN 1 THEN "房产证" WHEN 2 THEN "不动产证" WHEN 3 THEN "网签合同" WHEN 4 THEN "公租房" ELSE "其他" END as code_type_text'),
                            'a.create_time',
                            ])
                            ->select()->toArray();

                $res = [
                    'code' => 1,
                    'data' => $list,
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * @param int child_id
     * @return Json
     */
    public function getChildDetail(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'child_id',
                    'family_id',
                    'house_id',
                    'apply_id'
                ]);

                if(!isset($data['apply_id']) || intval($data['apply_id']) <= 0){
                    throw new \Exception('系统错误');
                }

                $result = $this->getChildDetails($data['apply_id']);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }else{
                    $res = [
                        'code' => 1,
                        'data' => $result['data'],
                    ];
                }

                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }


    /**
     * 删除
     * @return Json
     */
    public function actDelete(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'deleted' => 1
                ]);

                $info = (new \app\common\model\ApplyReplenished())
                    ->where('id',$data['id'])
                    ->where('apply_status',0)
                    ->find();
                $is_deletd_all = 1;
                if($info){
                    $user_id = $info['user_id'];
                    $result = (new \app\common\model\ApplyReplenished())->editData($data);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    $info = (new \app\common\model\ApplyReplenished())
                        ->where('user_id',$user_id)
                        ->where('apply_status',0)
                        ->where('deleted',0)
                        ->find();
                    if($info){
                        $is_deletd_all = 0;
                    }
                }else{
                    throw new \Exception('数据不存在或者已补录不能删除');
                }
                 
                $res = [
                    'code' => 1,
                    'data' => $is_deletd_all,
                    'msg' => Lang::get('update_success'),
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

    private function changeTimeType($time){
    if ($time > 3600){
        $hours =intval($time/3600);
        $minutes = $time % 3600;
        $times = $hours.":".gmstrftime('%M:%S',$minutes);
    }else{
        $times = gmstrftime('%H:%M:%S',$time);
    }
        return $times;
    }

}