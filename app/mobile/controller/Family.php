<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\middleware\ApplyMiddleware;
use app\mobile\model\user\Family as model;
use app\mobile\model\user\ApplyTmp;
use app\mobile\model\user\User;
use app\mobile\model\user\Child;
use think\facade\Db;
use think\facade\Cache;
use think\response\Json;
use app\mobile\validate\Family as validate;
use comparison\Reckon;

class Family extends MobileEducation
{

    protected $middleware = [
        ApplyMiddleware::class
    ];
    /**
     * 获取监护人列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $tmp_ids = (new ApplyTmp())->where([
                    'user_id' =>  $this->userInfo['id']
                ])->column('id');
                $list = Db::name('user_family')
                    ->Distinct(true)
                    ->whereIn('tmp_id', $tmp_ids)
                    ->field([
                        'id',
                        'parent_name',
                        'idcard',
                        'relation'
                    ])
                    ->paginate($this->pageSize);
                $res = [
                    'code' => 1,
                    'data' => $list
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
     * 獲取監護人詳情
     * @param int id    监护人自增ID
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only(['id']);
                $info = model::field([
                    'id',
                    'parent_name',
                    'idcard',
                    'relation'
                ])->find($data['id']);
                $res = [
                    'code' => 1,
                    'data' => $info
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
     * 新增監護人信息
     * @param string parent_name   姓名
     * @param string idcard        身份证号
     * @param string idcard_pic    身份证照片
     * @param string relation      关系（0：其它；1：父亲；2：母亲；3：爷爷；4：奶奶；5：外公；6：外婆；）
     * @param string hash          表单hash
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'child_id',
                    'parent_name',
                    'idcard',
                    'relation',
                    'hash'
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
                $child_id = $data['child_id'];
                unset($data['child_id']);
                //  通过身份证号获取性别
                $sex = x_getSexByIdcard(strtoupper(trim($data['idcard'])));
                $data['idcard'] = strtoupper(trim($data['idcard']));
                if (!check_Idcard($data['idcard'])) {
                    throw new \Exception('监护人身份证号不正确');
                }
                //  根据性别判断关系是否选择错误
                if (
                    (in_array($data['relation'], [1, 3, 5]) && $sex == 2)
                    ||
                    (in_array($data['relation'], [2, 4, 6]) && $sex == 1)){
                    throw new \Exception('监护人关系与输入的身份证号码不匹配');
                }
                //判断学生表里面身份证号是否重复
                $isHasChild = (new Child())
                    ->where("idcard",$data['idcard'])
                    ->where("deleted",0)
                    ->find();
                if($isHasChild){
                    throw new \Exception('监护人身份证号与学生身份证号不能重复，请重新填写');
                }
                $tmpData = (new ApplyTmp())->where([
                    'user_id' => $this->result['user_id'],
                    'child_id' => $child_id,
                    'completed' => 0
                ])->find();
                if(empty($tmpData)){
                    throw new \Exception('信息保存失败，请返回重新填写学生信息');
                }
                $tmp_id = $tmpData['id'];
                //  获取该用户录入的所有监护人信息
                $tmp_ids = (new ApplyTmp())->where([
                    'user_id' =>  $this->result['user_id'],
                ])->column('id');
                //  判断当前用户是否已经填报此身份证
                $isHas = model::where('idcard' , $data['idcard'])
                    ->whereIn('tmp_id',$tmp_ids)
                    ->find();
                //  如果该监护人信息存在、则更新
                if (!empty($isHas)) {
                    $family_id = $isHas['id'];
                    $data['id'] = $family_id;
                    $data['tmp_id'] = $tmp_id;
                    Db::name('UserFamily')
                        ->where('id',$family_id)
                        ->update([
                            'tmp_id' => $tmp_id,
                            'parent_name' => $data['parent_name'],
                            'idcard' => $data['idcard'],
                            'relation' => $data['relation'],
                        ]);
                    Db::name('UserApplyTmp')
                        ->where('id',$tmp_id)
                        ->update(['family_id' => $family_id]);
                } else {
                    //  如果该监护人信息不存在，则新增
                    $data['tmp_id'] = $tmp_id;
                    $family_id = Db::name('UserFamily')->insertGetId([
                        'tmp_id' => $tmp_id,
                        'parent_name' => $data['parent_name'],
                        'idcard' => $data['idcard'],
                        'relation' => $data['relation'],
                    ]);
                    $data['id'] = $family_id;
                    Db::name('UserApplyTmp')
                        ->where('id',$tmp_id)
                        ->update(['family_id' => $family_id]);
                }
                $real_family_check = Cache::get('real_family_check');
                if($real_family_check){
                    $reckon = new Reckon();
                    $resReckon = $reckon->CheckFamily($tmp_id,$family_id,$data['idcard']);
                    if(!$resReckon['code']){
                        throw new \Exception($resReckon['msg']);
                    }
                }else{
                    Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_family' => 0]);
                }
                $res = [
                    'code' => 1,
                    'data' => $data
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
     * 編輯監護人信息
     * @param int id               自增ID
     * @param string parent_name    姓名
     * @param string idcard        身份证号
     * @param string idcard_pic    身份证照片
     * @param int relation      关系（0：其它；1：父亲；2：母亲；3：爷爷；4：奶奶；5：外公；6：外婆；）
     * @return Json
     */
    public function actEdit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'child_id',
                    'parent_name',
                    'idcard',
                    'relation',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $child_id = $data['child_id'];
                unset($data['child_id']);
                $tmpData = (new ApplyTmp())->where([
                    'user_id' =>$this->userInfo['id'],
                    'child_id' => $child_id,
                    'completed' => 0
                ])->find();
                if(empty($tmpData)){
                    throw new \Exception('信息保存失败，请返回重新填写学生信息');
                }
                $tmp_id = $tmpData['id'];
                //  通过身份证号获取性别
                $sex = x_getSexByIdcard(strtoupper(trim($data['idcard'])));
                $data['idcard'] = strtoupper(trim($data['idcard']));
                //  根据性别判断关系是否选择错误
                if (
                    (in_array($data['relation'], [1, 3, 5]) && $sex == 2) ||
                    (in_array($data['relation'], [2, 4, 6]) && $sex == 1)) {
                    throw new \Exception('监护人关系与输入的身份证号码不匹配');
                }
                $data['tmp_id'] = $tmp_id;
                $family_id = $data['id'];
                //  验证已经存在的监护人信息
                $familyData = model::where([
                    'id' => $family_id
                ])->find();
                if(empty($familyData)){
                    throw new \Exception('未找到提交的监护人信息');
                }
                $idcard = $familyData['idcard'];
                //查询临时表信息
                $hasTmpData = (new ApplyTmp())->where([
                    'id' => $familyData['tmp_id']
                ])->find();
                //  如果该信息是自己操作的，则更新
                if ($hasTmpData['user_id'] == $this->result['user_id']) {
                    Db::name('UserFamily')
                        ->where('id',$family_id)
                        ->update([
                            'tmp_id' => $tmp_id,
                            'relation' => $data['relation'],
                        ]);
                    Db::name('UserApplyTmp')
                        ->where('id',$tmp_id)
                        ->update(['family_id' => $family_id]);
                }else{
                    throw new \Exception( '非法操作');
                }
                $real_family_check = Cache::get('real_family_check');
                if($real_family_check){
                    $reckon = new Reckon();
                    $resReckon = $reckon->CheckFamily($tmp_id,$family_id,$idcard);
                    if(!$resReckon['code']){
                        throw new \Exception($resReckon['msg']);
                    }
                }else{
                    Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_family' => 0]);
                }
                $res = [
                    'code' => 1,
                    'data' => $data
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