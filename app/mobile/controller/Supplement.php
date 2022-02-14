<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\mobile\model\user\Child;
use app\mobile\model\user\Apply;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;
use app\mobile\validate\Supplement as validate;

class Supplement extends MobileEducation
{

    /**
     * 补充资料添加
     * @return Json
     *
     */
    public function AddInfo(): Json
    {
        if ($this->request->isPost()) {
            try {

                $child = Db::name('user_child')
                    ->alias('c')
                    ->leftJoin('user_apply u','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.user_id",$this->userInfo['id'])
                    ->field(['c.real_name','c.idcard','c.id'])
                    ->select()
                    ->toArray();


                if (!$child) {
                    throw new \Exception('请先填写入学申请绑定学生信息');
                }

                $res = [
                    'code' => 1,
                    'data' => $child,
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
     * 补充资料之前准备的数据
     * @param int child_id 学生id
     * @return Json
     *
    */
    public function addBeforData(): Json
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
                    ->where('child_id',$data['child_id'])
                    ->where('user_id',$data['user_id'])
                    ->where('resulted',0)
                    ->where('school_attr',1)
                    ->where('voided',0)
                    ->find();
                if(!$apply){
                    throw new \Exception('该学生还没有申报入学信息');
                }

                $supplement_before = (new \app\mobile\model\user\Supplement())
                    ->where('user_apply_id',$apply['id'])
                    ->where('status',1)
                    ->find();

                $supplement = (new \app\mobile\model\user\Supplement())
                    ->where('user_apply_id',$apply['id'])
                    ->where('status',0)
                    ->find();

                if(!$supplement && $supplement_before){
                    throw new \Exception('学生无资料需要补充');
                }

                if(!$supplement){
                    throw new \Exception('没有查找到相关申请资料');
                }
                $info['attachment'] = Db::name("user_supplement_attachment")
                    ->where('status',0)
                    ->where('supplement_id',$supplement['id'])
                    ->field(['cate_name','cate_id','id'])
                    ->select()->toArray();
                $info['cate_name'] = array_column($info['attachment'],'cate_name');

                $res = [
                    'code' => 1,
                    'data' => $info,
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
     * 添加补充资料
     * @param int child_id 学生id
     * @param string json_data 对应不同图片类型数据
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'child_id',
                    'json_data',
                    'hash',
                ]);

                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                //判断如果学生没有预录取并且开通了补充资料

                $supplement = (new \app\mobile\model\user\Supplement())
                    ->where("child_id",$data['child_id'])
                    ->find();

                $supplement_attachment = Db::name("user_supplement_attachment")
                                        ->where('supplement_id',$supplement['id'])
                                        ->where('status',0)
                                        ->select()
                                        ->toArray();

                if(!$supplement_attachment){
                    throw new \Exception('没有查找到相关申请资料或该学生已被预录取或没有开通补充资料功能');
                }

                $update_data['id'] = $supplement['id'];
                $update_data['status'] = 1;
                $insert = (new \app\mobile\model\user\Supplement())->editData($update_data);
                if ($insert['code'] == 0) {
                    throw new \Exception($insert['msg']);
                }
                $json_data = json_decode($data['json_data'],true);
                foreach($json_data as $key=>$value){
                    Db::name("user_supplement_attachment")
                        ->where('id',$value['id'])
                        ->update(['attachment'=>$value['attchment'],'status'=>1]);
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success'),
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