<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\mobile\model\user\ApplyTmp;
use app\mobile\model\user\Family;
use think\facade\Db;
use think\facade\Log;
use think\response\Json;
use app\mobile\validate\Ancestor as validate;
use think\facade\Cache;
use comparison\Reckon;

class Ancestor extends MobileEducation
{
    /**
     * 三代同堂数据保存
     * @param string name              祖辈姓名
     * @param string idcard            祖辈身份证号
     * @param string idcard_pic        祖辈身份证图片
     * @param string relation          祖辈与孩子的关系
     * @param string hash              表单hash
     * @param int child_id          孩子自增ID
     * @param int family_id         自带监护人的自增ID
     * @param int singled         是否单亲
     * @param string other_name        另外一个监护人的姓名
     * @param string other_idcard      另外一个监护人的身份证
     * @param string other_relation    另外一个监护人与孩子的关系
     * @return Json
     */
    public function actSave(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'child_id',
                    'family_id',
                    'parent_name',
                    'idcard',
                    'relation',
                    'other_name',
                    'other_idcard',
                    'other_relation',
                    'singled',
                    'is_ancestor' => 1,
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'save');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['idcard'] = strtoupper(trim($data['idcard']));
                if (!check_Idcard($data['idcard'])){
                    throw new \Exception('祖辈身份证号不正确');
                }
                //  通过身份证号获取性别
                $sex = x_getSexByIdcard($data['idcard']);
                //  根据性别判断关系是否选择错误
                if (
                    (in_array($data['relation'], [1, 3, 5]) && $sex == 2) ||
                    (in_array($data['relation'], [2, 4, 6]) && $sex == 1)) {
                    throw new \Exception('录入的祖辈身份证号与学生关系不匹配');
                }
                $child_id = $data['child_id'];
                unset($data['child_id']);
                $child = Db::name("user_child")->where('id',$child_id)->find();
                if($data['idcard'] == $child['idcard']){
                    throw new \Exception('祖辈身份证号与学生身份号不能相同');
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
                $reckon = new Reckon();
                //  如果该监护人信息存在、则更新
                if ($tmpData['ancestors_family_id']) {
                    $ancestors_family_id = $tmpData['ancestors_family_id'];
                    $data['id'] = $ancestors_family_id;
                    $data['tmp_id'] = $tmp_id;
                    Db::name('UserFamily')
                        ->where('id',$ancestors_family_id)
                        ->update([
                            'tmp_id' => $tmp_id,
                            'parent_name' => $data['parent_name'],
                            'idcard' => $data['idcard'],
                            'relation' => $data['relation'],
                        ]);
                } else {
                    //  如果该监护人信息不存在，则新增
                    $data['tmp_id'] = $tmp_id;
                    $ancestors_family_id = Db::name('UserFamily')->insertGetId([
                        'tmp_id' => $tmp_id,
                        'parent_name' => $data['parent_name'],
                        'idcard' => $data['idcard'],
                        'relation' => $data['relation'],
                        'is_ancestor' => 1,
                    ]);
                    $data['id'] = $ancestors_family_id;
                    Db::name('UserApplyTmp')
                        ->where('id',$tmp_id)
                        ->update(['ancestors_family_id' => $ancestors_family_id]);
                }
                $real_generations_check = Cache::get('real_generations_check');
                if($real_generations_check){
                    $resReckon = $reckon->CheckAncestors($tmp_id,$ancestors_family_id,$data['idcard']);
                    if(!$resReckon['code']){
                        throw new \Exception($resReckon['msg']);
                    }
                }else{
                    Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_generations' => 0]);
                }
                if (!isset($this->result['singled']) || !in_array($this->result['singled'], [0, 1])) {
                    throw new \Exception('是否单亲家庭数据不正确');
                }
                $other_idcard ='';
                $other_family_id = 0;
                if(isset($data['other_relation']) && $data['other_relation']){
                    if (!in_array($data['other_relation'], [1, 2])) {
                        throw new \Exception('其它监护人关系数据不正确');
                    }
                    $data['other_idcard'] = strtoupper(trim($data['other_idcard']));
                    //  检测身份证号是否合法
                    if (!check_Idcard($data['other_idcard'])) {
                        switch ($data['other_relation']) {
                            case 1:
                                throw new \Exception('父亲身份证号不正确');
                                break;
                            case 2:
                                throw new \Exception('母亲身份证号不正确');
                                break;
                        }
                    }
                    if (empty($data['other_name']) || strlen($data['other_name']) > 24) {
                        switch ($data['other_relation']) {
                            case 1:
                                throw new \Exception('父亲姓名不正确');
                                break;
                            case 2:
                                throw new \Exception('母亲姓名不正确');
                                break;
                        }
                    }
                    if ($child['idcard'] == $data['other_idcard']) {
                        switch ($data['other_relation']) {
                            case 1:
                                throw new \Exception('父亲身份证号与学生身份证号不能相同');
                                break;
                            case 2:
                                throw new \Exception('母亲身份证号与学生身份证号不能相同');
                                break;
                        }
                    }
                    //  通过身份证号获取性别
                    $otherSex = x_getSexByIdcard($data['other_idcard']);
                    //  根据性别判断关系是否选择错误
                    if (
                        ($data['other_relation'] == 1 && $otherSex == 2) ||
                        ($data['other_relation'] == 2 && $otherSex == 1)) {
                        switch ($otherSex) {
                            case 1:
                                throw new \Exception('监护人(母亲)关系与输入的身份证号码不匹配');
                                break;
                            case 2:
                                throw new \Exception('监护人关系(父亲)与输入的身份证号码不匹配');
                                break;
                        }
                    }
                    //  新增其它监护人信息
                    $other_family_id = Db::name('UserFamily')->insertGetId([
                        'tmp_id' => $tmp_id,
                        'parent_name' => $data['other_name'],
                        'idcard' => $data['other_idcard'],
                        'relation' => $data['other_relation'],
                    ]);
                    Db::name('UserApplyTmp')
                        ->where('id',$tmp_id)
                        ->update(['other_family_id' => $other_family_id]);
                    $other_idcard = $data['other_idcard'];
                    $real_other_check = Cache::get('real_other_check');
                    if($real_other_check){
                        $resReckon = $reckon->CheckOther($tmp_id,$other_family_id,$other_idcard);
                        if(!$resReckon['code']){
                            throw new \Exception($resReckon['msg']);
                        }
                    }else{
                        Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_other' => 0]);
                    }

                }
                $familyData = Family::find($data['family_id']);
                if(empty($familyData)){
                    throw new \Exception('监护人数据不存在');
                }
                $ancestorData = [
                    'family_id' => $ancestors_family_id,
                    'child_id' => $child_id,
                    'singled' => $data['singled']
                ];
                if ($familyData['relation'] == 1) {
                    $father_id = $data['family_id'];
                    $mother_id = $other_family_id;
                } elseif($familyData['relation'] == 2) {
                    $mother_id = $data['family_id'];
                    $father_id = $other_family_id;
                }
                //  如果三代同堂关系存在，则编辑
                if ($tmpData['ancestor_id']) {
                    $ancestor_id = $tmpData['ancestor_id'];
                    $ancestorData['id'] = $ancestor_id;
                    Db::name('UserAncestor')
                        ->where('id',$ancestor_id)
                        ->update([
                            'tmp_id' => $tmp_id,
                            'child_id' => $child_id,
                            'family_id' => $ancestors_family_id,
                            'father_id' => $father_id,
                            'mother_id' => $mother_id,
                            'singled' => $this->result['singled'],
                        ]);
                } else {
                    //  如果三代同堂关系不存在，则新增
                    $ancestor_id = Db::name('UserAncestor')->insertGetId([
                        'tmp_id' => $tmp_id,
                        'child_id' => $child_id,
                        'family_id' => $ancestors_family_id,
                        'father_id' => $father_id,
                        'mother_id' => $mother_id,
                        'singled' => $this->result['singled'],
                    ]);
                    Db::name('UserApplyTmp')
                        ->where('id',$tmp_id)
                        ->update(['ancestor_id' => $ancestor_id]);
                }
                $real_ancestor_check = Cache::get('real_ancestor_check');
                if($real_ancestor_check){
                    $resReckon = $reckon->CheckAncestor($tmp_id,$ancestor_id,$child['idcard'],$familyData['idcard'],$data['idcard'],$other_idcard);
                    if(!$resReckon['code']){
                        throw new \Exception($resReckon['msg']);
                    }
                }else{
                    Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_ancestors' => 0]);
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