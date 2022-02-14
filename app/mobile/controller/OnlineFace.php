<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\model\Schools;
use app\mobile\model\user\Apply;
use app\mobile\model\user\OnlineFace as model;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;

class OnlineFace extends MobileEducation
{

    /**
     * 选择学校
     * @param int child_id 学生id
     * @return Json
     */
    public function chooseSchool(): Json
    {

        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'child_id',
                ]);
                $where = [];
                if($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['school_name', 'like', '%' . $this->result['keyword'] . '%'];
                }

                if(!isset($data['child_id']) && !$data['child_id']) {
                    throw new \Exception('系统错误');
                }
                $apply = (new Apply())
                    ->where('child_id',$data['child_id'])
                    ->where('resulted',0)
                    ->where('school_attr',1)
                    ->where('voided',0)
                    ->find();
                if(!$apply){
                    throw new \Exception('没有查找到申报记录');
                }

                $school =  (new Schools())
                    ->where('region_id',$apply['region_id'])
                    ->where('school_type',$apply['school_type'])
                    ->where('school_attr',$apply['school_attr'])
                    ->where('deleted',0)
                    ->where("disabled",0)
                    ->where('displayed',1)
                    ->where('onlined',1)
                    ->where($where)
                    ->field(['id,school_name'])
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $res = [
                    'code' => 1,
                    'data' => $school,
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
     * @param int school_id 学校id
     * @param int child_id 学生id
     * @return Json
     */
    public function getSchoolDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'school_id',
                    'child_id',
                ]);
                if(!isset($data['school_id']) && !$data['school_id']) {
                    throw new \Exception('系统错误');
                }
                if(!isset($data['child_id']) && !$data['child_id']) {
                    throw new \Exception('系统错误');
                }

                $school =  (new Schools())
                    ->where('id',$data['school_id'])
                    ->where('deleted',0)
                    ->where("disabled",0)
                    ->where('displayed',1)
                    ->where('onlined',1)
                    ->field(['id,school_name,content,2 as cate'])
                    ->find();
                if(!$school){
                    throw new \Exception('学校信息不存在');
                }

                $school['refuse_count'] = (new Apply())
                    ->where('child_id',$data['child_id'])
                    ->where('resulted',0)
                    ->where('school_attr',1)
                    ->where('voided',0)
                    ->value('refuse_count');

                $info = Db::name("online_face")
                    ->where("child_id",$data['child_id'])
                    ->where("deleted",0)
                    ->order('id DESC')
                    ->find();
                $apply = Db::name("user_apply")
                    ->where('child_id',$data['child_id'])
                    ->where('user_id',$data['user_id'])
                    ->where('deleted',0)
                    ->field(['prepared'])
                    ->find();
                $message = '在“线上面审”期间。请根据本地招生划片方案和自身情况，选择最有可能就读的学校提交申请。如三次被学校拒收，将不能在填报。';

                //当前学校申请过，后台还没审批的状态
                $school['applyed'] = 0;
                $school['face_status'] = 0;
                if($info){
                    if($info['status'] == 1){
                        if($apply['prepared'] == 1){   //判断是否被预录取
                            $school['face_status'] = 1;
                            $message = '您的资料已通过审核，无法再次申请学校';
                        }
                    }else if($info['status'] == 0){
                        $message = '您的资料正在审核中，如更换学校提交申请，原申请将转移到本次申请的学校。同时，本次操作将视为被原学校拒绝。您确认更换吗？';
                    }else{
                        $message = '在“线上面审”期间。请根据本地招生划片方案和自身情况，选择最有可能就读的学校提交申请。如三次被学校拒收，将不能在填报。';
                    }
                    if($info['school_id'] == $data['school_id']){
                        $school['applyed'] = 1;
                    }
                }
                $school['message'] = $message;
                $res = [
                    'code' => 1,
                    'data' => $school,
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
     * 添加线上面审
     * @param int child_id 学生id
     * @param int school_id 学校id
     * @return Json
     */
    public function actSave(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'school_id',
                    'child_id',
                ]);

                $apply = (new Apply())
                    ->where('child_id',$data['child_id'])
                    ->where('school_attr',1)
                    ->where('voided',0)
                    ->find();
                if(!$apply){
                    throw new \Exception('没有查找到申报记录或者申报记录已作废');
                }

                if($apply['prepared'] == 1){
                    throw new \Exception('该学生已经被预录取，不能再次申请');
                }
                if($apply['refuse_count'] >= 3){
                    throw new \Exception('申请已到3次，不能再次申请');
                }
                $face_num = Db::name("online_face")
                    ->where("child_id",$data['child_id'])
                    ->where("deleted",0)
                    ->count();
                if($face_num >= 3){
                    throw new \Exception('申请已到3次，不能再次申请');
                }
                $info = Db::name("online_face")
                    ->where("child_id",$data['child_id'])
                    ->order('id DESC')
                    ->limit(1)
                    ->find();
                if($info){
                    if($info['status'] == 0){
                        //更新线上面审次数,线上面审时间，公办学校
                        Db::name('user_apply')
                            ->where('id', $apply['id'])
                            ->inc('refuse_count')
                            ->update();
                    }
                }

                $add_data['child_id'] = $data['child_id'];
                $add_data['school_id'] = $data['school_id'];
                $add_data['apply_id'] = $apply['id'];
                $result = (new model())->addData($add_data);
                if($result['code'] == 0){
                    $res = [
                        'code' => 0,
                        'msg' => $result['msg'],
                    ];
                }else{
                    $res = [
                        'code' => 1,
                        'msg' => Lang::get('提交申请成功'),
                    ];
                }

                $child_name = Db::name("user_child")->where('id',$data['child_id'])->where('deleted',0)->value('real_name');
                $school_name = Db::name("sys_school")->where('id',$data['school_id'])->where('deleted',0)->value('school_name');
                Db::name("UserMessage")->insertGetId([
                    'user_id' => $data['user_id'],
                    'user_apply_id' => $apply['id'],
                    'school_id' => $data['school_id'],
                    'title' => '线上审核申请提交成功',
                    'contents' => '（'.$child_name.'）您的资料已成功提交至（'.$school_name.'），请关注平台信息，等待学校审核结果',
                ]);
                Db::name('user_apply')
                    ->where('id', $apply['id'])
                    ->update(['public_school_id' => $data['school_id'],'fill_time' => date('Y-m-d H:i:s', time())]);
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
     * 线上面审校验
     * @param int child_id 学生id
     * @return Json
     */
    public function check(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'child_id',
                ]);
                if(!isset($data['child_id']) || intval($data['child_id']) <= 0){
                    throw new \Exception('系统错误');
                }

                $apply = (new Apply())
                    ->where('child_id',$data['child_id'])
                    ->where('resulted',0)
                    ->where('school_attr',1)
                    ->where('voided',0)
                    ->find();
                if(!$apply){
                    throw new \Exception('没有查找到公办申报记录或学生已被录取');
                }
                $detail = Db::name('user_apply_detail')
                    ->where('child_id',$apply['child_id'])
                    ->where('deleted',0)
                    ->find();
                if(!$detail){
                    throw new \Exception('没有查找到公办申报记录或学生已被录取');
                }

                if($apply['refuse_count'] >= 3){
                    throw new \Exception('申请已超过3次，不能再次申请');
                }

                if($apply['prepared'] == 1){
                    throw new \Exception('该学生已经被预录取，不能再次申请');
                }

                $result = Db::name('face_config')
                    ->where('deleted',0)
                    ->where('region_id',$apply['region_id'])
                    ->where('status',1)
                    ->order('id', 'ASC')
                    ->select()
                    ->toArray();
                $status = Db::name("user_apply_status")
                    ->where('user_apply_id',$apply['id'])
                    ->where('deleted',0)
                    ->find();
                $flag = false;
                if(!$result){
                    throw new \Exception('该区域线上审核未配置');
                }
                if($status){
                    foreach($result as $key=>$value){
                        switch ($value['type']){
                            case 1: //点对点开启
                                if(time() >= strtotime($value['start_time']) &&  time() <= strtotime($value['end_time']) && $apply['open_school'] == 1 ){
                                    $flag = true;
                                }
                                break;
                        }
                    }

                    if(!$flag){
                        throw new \Exception('学生不符合线上审核条件');
                    }
                }else{
                    throw new \Exception('系统错误');
                }

                $res = [
                    'code' => 1,
                    'msg' => '校验成功',
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

}