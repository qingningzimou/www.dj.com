<?php

namespace app\mobile\model\user;

use app\mobile\model\user\Basic;
use app\common\model\Plan;
use app\common\model\SysRegion;
use app\common\model\Schools;
use think\facade\Db;
use think\model\relation\HasOne;

class Apply extends Basic
{
    protected $name = 'user_apply';

    /**
     * 關聯註冊用戶
     * @return HasOne
     */
    public function relationUser(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'id');
    }

    /**
     * 关联民办，申请学校
     * @return HasOne
     */
    public function relationApplySchool():HasOne
    {
        return $this->hasOne(Schools::class,'id','apply_school_id')->field('id,name')->joinType('left');
    }

    /**
     * 關聯行政區域
     * @return HasOne
     */
    public function relationAdcode(): HasOne
    {
        return $this->hasOne(SysRegion::class, 'id', 'region_id');
    }

    /**
     * 關聯招生計劃
     * @return HasOne
     */
    public function relationPlan(): HasOne
    {
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    /**
     * 關聯孩子信息
     * @return HasOne
     */
    public function relationChild(): HasOne
    {
        return $this->hasOne(Child::class, 'id', 'child_id');
    }

    public function relationChild2():HasOne
    {
        return $this->hasOne(Child::class, 'id', 'child_id');
    }

    /**
     * 關聯監護人信息
     * @return HasOne
     */
    public function relationFamily(): HasOne
    {
        return $this->hasOne(Family::class, 'id', 'family_id');
    }

    /**
     * 關聯房產信息
     * @return HasOne
     */
    public function relationHouse(): HasOne
    {
        return $this->hasOne(House::class, 'id', 'house_id')->joinType('left');
    }

    /**
     * 關聯企業信息
     * @return HasOne
     */
    public function relationCompany(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id')->joinType('left');
    }

    /**
     * 關聯社保信息
     * @return HasOne
     */
    public function relationInsurance(): HasOne
    {
        return $this->hasOne(Insurance::class, 'id', 'insurance_id')->joinType('left');
    }

    /**
     * 關聯居住證信息
     * @return HasOne
     */
    public function relationResidence(): HasOne
    {
        return $this->hasOne(Residence::class, 'id', 'residence_id')->joinType('left');
    }

    /**
     * 關聯学位审查學校
     * @return HasOne
     */
    public function relationPrepareSchool(): HasOne
    {
        return $this->hasOne(Schools::class, 'id', 'prepare_school_id')->field('id,name')->joinType('left');
    }

    /**
     * 關聯自動派位學校
     * @return HasOne
     */
    public function relationReckonSchool(): HasOne
    {
        return $this->hasOne(Schools::class, 'id', 'reckon_school_id')->field('id,name')->joinType('left');
    }

    public function relationResultSchool(): HasOne
    {
        return $this->hasOne(Schools::class, 'id', 'result_school_id')->field('id,name')->joinType('left');
    }

    /**
     * 關聯民办面談學校
     * @return HasOne
     */
    public function relationAuditSchool(): HasOne
    {
        return $this->hasOne(Schools::class, 'id', 'audit_school_id')->field('id,name')->joinType('left');
    }

    /**
     * 关联学位申请详情
     * @return HasOne
     */
    public function relationUserApplyType(): HasOne
    {
        return $this->hasOne(ApplyType::class, 'user_apply_id', 'id')->joinType('left');
    }

    /**
     * 关联生源类型
     * @return HasOne
     */
    public function relationApplyType(): HasOne
    {
        return $this->hasOne(ApplyType::class, 'id', 'apply_type_id')->joinType('left');
    }

    /**
     * 关联自动计算的生源类型
     * @return HasOne
     */
    public function relationReckonApplyType(): HasOne
    {
        return $this->hasOne(ApplyType::class, 'id', 'reckon_apply_type_id')->joinType('left');
    }

    /**
     * 保存学位审查结果
     * @param array $data
     * @return array
     */
    public function xsavePrepare(array $data): array
    {
        $this->startTrans();
        try {
            //  定义学位申请更新数据
            $applyData = [
                'id' => $data['id'],
                'is_prepare' => $data['is_prepare']
            ];
            //  定义学位申请日志数据
            $applyLogData = [
                'user_apply_id' => $data['id'],
                'is_prepare' => $data['is_prepare'],
                'is_progress' => 1,
                'create_time' => time()
            ];
            //  获取学位申请详情
            $applyType = ApplyType::where('user_apply_id', $data['id'])->find();
            if (empty($applyType)) {
                throw new \Exception('该学位申请还未与大数据中心比对完，暂不能操作');
            }
            //  定义生源类型数据
            $applyTypeData = [
                'id' => $applyType['id'],
                'is_relation' => $data['is_relation'],
                'is_area' => $data['is_area'],
                'is_main_area' => $data['is_main_area'],
                'is_house' => $data['is_house'],
                'is_main_house' => $data['is_main_house'],
                'is_company' => $data['is_company'],
                'is_residence' => $data['is_residence'],
                'is_insurance' => $data['is_insurance'],
                'house_type' => $data['house_type'],
                'house_code_type' => $data['house_code_type']
            ];
            //  如果是区教育局管理员操作
            if (isset($data['education_admin_id'])) {
                $applyLogData['education_admin_id'] = $data['education_admin_id'];
            }
            //  如果是中心学校管理员操作
            if (isset($data['center_admin_id'])) {
                $applyLogData['center_admin_id'] = $data['center_admin_id'];
            }
            //  如果是学校管理员操作
            if (isset($data['school_admin_id'])) {
                $applyLogData['school_admin_id'] = $data['school_admin_id'];
            }
            //  获取申请详情
            $apply = $this->with([
                'relationAdcode',
                'relationChild'
            ])->find($data['id']);
            //  附加日志中的用户userid
            $applyLogData['userid'] = $apply['userid'];
            //  如果审核通过
            if ($data['is_prepare'] == 1) {
                if(time() > 1597449600)
                {
                    $applyLogData['is_show'] = 1;
                }
                $applyData['prepare_school_id'] = $data['prepare_school_id'];
                $applyLogData['apply_message'] = "经面审，（{$apply['relationChild']['name']}）符合（{$apply['relationAdcode']['name']}）就学条件，请等待入学通知。";
                //  如果原申请已经审核通过，则标记为调剂
                if ($apply['is_prepare'] == 1) {
                    $applyData['is_adjust'] = 1;
                }
                /*//  计算生源类型
                $applyTypeId = (new \app\mobile\model\manage\ApplyType())->reckon($data['education_id'], $applyTypeData);
                $applyData['apply_type_id'] = $applyTypeId;
                $applyLogData['apply_type_id'] = $applyTypeId;*/
            } elseif ($data['is_prepare'] == 2) {
                //  如果审核拒绝
                //  如果原申请已经审核通过，则不允许拒绝
                if ($apply['is_prepare'] == 1) {
                    throw new \Exception('该申请已经有学校审核通过，不允许拒绝');
                }
                if(empty($data['apply_message']))
                {
                    throw new \Exception('请填写拒绝理由');
                }
                $applyLogData['apply_message'] = '审核拒绝：' . $data['apply_message'];
            } else {
                throw new \Exception('非法操作');
            }
            $res = parent::editData($applyData);
            if ($res['code'] == 0) {
                throw new \Exception($res['msg']);
            }
            //  写入日志
            $resLog = (new ApplyLog())->addData($applyLogData);
            if ($resLog['code'] == 0) {
                throw new \Exception($resLog['msg']);
            }
            //  更新学位申请详情
            $resApplyType = (new ApplyType())->editData($applyTypeData);
            if ($resApplyType['code'] == 0) {
                throw new \Exception($resApplyType['msg']);
            }
            $this->commit();
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
            $this->rollback();
        }
        return $res;
    }

    /**
     * 保存预录取结果
     * @param array $data
     * @return array
     */
    public function xsaveResult(array $data): array
    {
        $this->startTrans();
        try {
            //  定义录取数量
            $resultTotal = 0;
            //  定义调剂数量
            $adjustTotal = 0;
            //  获取所有学位申请
            $applyList = $this->with([
                'relationAdcode',
                'relationChild'
            ])->whereIn('id', array_values($data['ids']))->select()->toArray();
            $schoolInfo = Schools::field('id,name')->find($data['school_id']);
            if(empty($applyList))
            {
                throw new \Exception('请选择后再提交');
            }
            //  查询该学校的学位数量
            $planApply = Apply::where([
                'plan_id' => $applyList[0]['plan_id'],
                'school_id' => $data['school_id'],
                'status' => 1
            ])->sum('spare_total');
            //  获取当前已使用学位数量
            $usePlanApply = Db::name('UserApply')->where([
                'voided' => 0,
                'is_delete' => 0,
                'result_school_id' => $data['school_id'],
                'plan_id' => $applyList[0]['plan_id']
            ])->count();
            if((count($applyList)+$usePlanApply) > $planApply)
            {
                throw new \Exception('学校学位数量不足，当前可用学位数量为' . intval($planApply-$usePlanApply));
            }
            foreach ($applyList as $apply) {
                if($apply['is_sign'] == 1)
                {
                    throw new \Exception("{$apply['relationChild']['name']}已经入学报到，不能指派。");
                }
                //  如果是樊城区，且未被录取，则发送消息
                if($apply['adcode'] == '420606' && $apply['is_result'] == 0 && $apply['school_type'] == 1 && $apply['school_attr'] == 1)
                {
                    (new ApplyLog())->addData([
                        'userid' => $apply['userid'],
                        'user_apply_id' => $apply['id'],
                        'apply_message_id' => 9,
                        'is_show' => 1,
                        'is_progress' => 1,
                        'create_time' => time(),
                        'apply_message' =>"（{$apply['relationChild']['name']}）因你未按照平台通知在规定的时间内到校面审，平台为（{$apply['relationChild']['name']}）派位在（{$schoolInfo['name']}）。请于2020年9月1日上午8点至11点凭平台注册二维码到（{$schoolInfo['name']}）入学报到。逾期未报到，派位结果作废。"
                    ]);
                }
                //  定义更新
                $updateData = [
                    'id' => $apply['id'],
                    'is_result' => 1,
                    'result_school_id' => $data['school_id']
                ];
                //  如果已经预录取学校了，则调剂
                if ($apply['result_school_id'] != 0) {
                    if($apply['result_school_id'] != $data['school_id']){
                        $updateData['is_adjust'] = 1;
                        $adjustTotal += 1;
                    }
                }else{
                    $resultTotal += 1;
                }
                if (isset($data['is_education'])) {
                    $updateData['is_education'] = $data['is_education'];
                }
                $resUpdate = parent::editData($updateData);
                if ($resUpdate['code'] == 0) {
                    throw new \Exception($resUpdate['msg']);
                }
                $message = "（child_name）已被（school_name）最终录取。";
                $message = str_replace('child_name', $apply['relationChild']['name'], $message);
                $message = str_replace('school_name',$schoolInfo['name'], $message);
                $logData = [
                    'userid' => $apply['userid'],
                    'user_apply_id' => $apply['id'],
                    'apply_message_id' => 0,
                    'is_show' => 0,
                    'create_time' => time(),
                    'apply_message' =>$message
                ];
                //  如果是教育局操作
                if(isset($data['education_admin_id']))
                {
                    $logData['education_admin_id'] = $data['education_admin_id'];
                }
                //  如果是中心学校操作
                if(isset($data['center_admin_id']))
                {
                    $logData['center_admin_id'] = $data['center_admin_id'];
                }
                //  如果是学校操作
                if(isset($data['school_admin_id']))
                {
                    $logData['school_admin_id'] = $data['school_admin_id'];
                }
                //  写入消息
                $resLog = (new ApplyLog())->addData($logData);
                if($resLog['code'] == 0)
                {
                    throw new \Exception($resLog['msg']);
                }
            }

            $res = [
                'code' => 1,
                'msg' => '新指派学位：' . $resultTotal . '，调剂生源：' . $adjustTotal
            ];
            $this->commit();
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
            $this->rollback();
        }
        return $res;
    }

    /**
     * 保存入学报到结果
     * @param array $data
     * @return array
     */
    public function xsaveSign(array $data): array
    {
        try {
            //  写入报到数据
            $res = parent::editData([
                'id' => $data['id'],
                'is_sign' => 1,
                'sign_time' => time()
            ]);
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }

    /**
     * 保存派遣单数据
     * @param array $data
     * @return array
     */
    public function xsaveDispatch(array $data): array
    {
        $this->startTrans();
        try {
            //  写入派遣单数据
            $res = (new Dispatch())->addData($data);
            if ($res['code'] == 0) {
                throw new \Exception($res['msg']);
            }
            //  更新学生入学申请
            $resUpdate = parent::editData([
                'id' => $data['user_apply_id'],
                'is_dispatch' => 1,
                'is_result' => 1,
                'is_education' => 1,
                'result_school_id' => $data['dispatch_school_id']
            ]);
            if ($resUpdate['code'] == 0) {
                throw new \Exception($resUpdate['msg']);
            }
            $apply = $this->with([
                'relationAdcode',
                'relationChild'
            ])->find($data['user_apply_id']);
            $schoolInfo = School::field('id,name')->find($data['dispatch_school_id']);
            $message = "（child_name）已被（school_name）最终录取。";
            $message = str_replace('child_name', $apply['relationChild']['name'], $message);
            $message = str_replace('school_name',$schoolInfo['name'], $message);
            $logData = [
                'userid' => $apply['userid'],
                'user_apply_id' => $apply['id'],
                'education_admin_id' => $data['education_admin_id'],
                'apply_message_id' => 0,
                'is_show' => 0,
                'create_time' => time(),
                'apply_message' =>$message
            ];
            //  写入消息
            $resLog = (new ApplyLog())->addData($logData);
            if($resLog['code'] == 0)
            {
                throw new \Exception($resLog['msg']);
            }
            //  查询该学校的学位数量
            $planApply = \app\mobile\model\plan\Apply::where([
                'plan_id' => $apply['plan_id'],
                'school_id' => $data['dispatch_school_id'],
                'status' => 1
            ])->sum('spare_total');
            //  获取当前已使用学位数量
            $usePlanApply = Db::name('UserApply')->where([
                'voided' => 0,
                'is_delete' => 0,
                'result_school_id' => $data['dispatch_school_id'],
                'plan_id' => $apply['plan_id']
            ])->count();
            if($usePlanApply > $planApply)
            {
                throw new \Exception('学校学位数量不足');
            }
            $this->commit();
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
            $this->rollback();
        }
        return $res;
    }

    /**
     * 民办面谈操作
     * @param array $data
     * @return array
     */
    public function auditData(array $data): array
    {
        $this->startTrans();
        try {
            //  定义学位申请更新数据
            $applyData = [
                'id' => $data['id']
            ];
            //  定义学位申请日志数据
            $applyLogData = [
                'user_apply_id' => $data['id'],
                'is_prepare' => $data['is_prepare'],
                'create_time' => time()
            ];
            //  获取学位申请详情
            $applyType = ApplyType::where('user_apply_id', $data['id'])->find();
            if (empty($applyType)) {
                throw new \Exception('该学位申请还未与大数据中心比对完，暂不能操作');
            }
            //  定义生源类型数据
            $applyTypeData = [
                'id' => $applyType['id'],
                'is_area' => $data['is_area'],
                'is_main_area' => $data['is_main_area'],
                'house_type' => $data['house_type'],
                'is_house' => $data['is_house'],
                'is_main_house' => $data['is_main_house'],
                'is_company' => $data['is_company'],
                'is_residence' => $data['is_residence'],
                'is_main_residence' => $data['is_main_residence'],
                'is_insurance' => $data['is_insurance']
            ];
            //  如果是区教育局管理员操作
            if (isset($data['education_admin_id'])) {
                $applyLogData['education_admin_id'] = $data['education_admin_id'];
            }
            //  如果是中心学校管理员操作
            if (isset($data['center_admin_id'])) {
                $applyLogData['center_admin_id'] = $data['center_admin_id'];
            }
            //  如果是学校管理员操作
            if (isset($data['school_admin_id'])) {
                $applyLogData['school_admin_id'] = $data['school_admin_id'];
            }
            //  获取申请详情
            $apply = $this->find($data['id']);
            //  附加日志中的用户userid
            $applyLogData['userid'] = $apply['userid'];
            //  如果审核：录取
            if ($data['is_audit'] == 1) {
                //  如果该申请已经被录取过，且录取学校与现在的学校不一致，则标记为调剂
                if ($apply['is_audit'] == 1) {
                    $applyData['is_adjust'] = 1;
                }
                //  计算生源类型
                $applyTypeId = (new ApplyType())->reckon($data['education_id'], $applyTypeData);
                $applyData['apply_type_id'] = $applyTypeId;
                $applyLogData['apply_type_id'] = $applyTypeId;
            } elseif ($data['is_audit'] == 2) {
                //  如果审核拒绝
                //  如果原申请已经审核通过，则不允许拒绝
                if ($apply['is_prepare'] == 1) {
                    throw new \Exception('该申请已经有学校审核通过，不允许拒绝');
                }
                $applyLogData['prepare_school_id'] = $data['prepare_school_id'];
            } else {
                throw new \Exception('非法操作');
            }
            $applyData = array_merge($applyData, [
                'is_prepare' => $data['is_prepare'],
                'prepare_school_id' => $data['prepare_school_id']
            ]);
            $res = parent::editData($applyData);
            if ($res['code'] == 0) {
                throw new \Exception($res['msg']);
            }
            //  写入日志
            $resLog = (new ApplyLog())->addData($applyLogData);
            if ($resLog['code'] == 0) {
                throw new \Exception($resLog['msg']);
            }
            //  更新学位申请详情
            $resApplyType = (new ApplyType())->editData($applyTypeData);
            if ($resApplyType['code'] == 0) {
                throw new \Exception($resApplyType['msg']);
            }
            $this->commit();
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
            $this->rollback();
        }
        return $res;
    }

    public function xsaveMiddle(array $data):array
    {
        $this->startTrans();
        try {
            //  定义学位申请更新数据
            $applyData = [
                'id' => $data['id'],
                'is_prepare' => 1,
                'prepare_school_id' => $data['result_school_id'],
                'is_result' => $data['is_result']
            ];
            //  定义学位申请日志数据
            $applyLogData = [
                'user_apply_id' => $data['id'],
                'create_time' => time(),
                'is_show' => 0
            ];
            //  获取学位申请详情
            $applyType = ApplyType::where('user_apply_id', $data['id'])->find();
            if (empty($applyType)) {
                throw new \Exception('该学位申请还未与大数据中心比对完，暂不能操作');
            }
            //  定义生源类型数据
            $applyTypeData = [
                'id' => $applyType['id'],
                'is_relation' => $data['is_relation'],
                'is_area' => $data['is_area'],
                'is_main_area' => $data['is_main_area'],
                'is_house' => $data['is_house'],
                'is_main_house' => $data['is_main_house'],
                'is_company' => $data['is_company'],
                'is_residence' => $data['is_residence'],
                'is_insurance' => $data['is_insurance'],
                'house_type' => $data['house_type'],
                'house_code_type' => $data['house_code_type']
            ];
            //  如果是区教育局管理员操作
            if (isset($data['education_admin_id'])) {
                $applyLogData['education_admin_id'] = $data['education_admin_id'];
            }
            //  如果是中心学校管理员操作
            if (isset($data['center_admin_id'])) {
                $applyLogData['center_admin_id'] = $data['center_admin_id'];
            }
            //  如果是学校管理员操作
            if (isset($data['school_admin_id'])) {
                $applyLogData['school_admin_id'] = $data['school_admin_id'];
            }
            //  获取申请详情
            $apply = $this->with([
                'relationAdcode',
                'relationChild'
            ])->find($data['id']);
            if($apply['is_result'] == 1)
            {
                if($apply['result_school_id'] == $data['result_school_id'])
                {
                    throw new \Exception('该学生已被本校录取，请至【入学报到】中查看');
                }else{
                    throw new \Exception('该学生已经有学校录取');
                }
            }
            $schoolInfo = Schools::field('id,name')->find($data['result_school_id']);
            //  附加日志中的用户userid
            $applyLogData['userid'] = $apply['userid'];
            //  如果审核通过
            if ($data['is_result'] == 1) {
                $applyData['result_school_id'] = $data['result_school_id'];
                $message = "（child_name）已被（school_name）最终录取。";
                $message = str_replace('child_name', $apply['relationChild']['name'], $message);
                $message = str_replace('school_name', $schoolInfo['name'], $message);
                $applyLogData['apply_message'] = $message;
                /*//  计算生源类型
                $applyTypeId = (new \app\mobile\model\manage\ApplyType())->reckon($data['education_id'], $applyTypeData);
                $applyData['apply_type_id'] = $applyTypeId;
                $applyLogData['apply_type_id'] = $applyTypeId;*/
            } else {
                throw new \Exception('非法操作');
            }
            $res = parent::editData($applyData);
            if ($res['code'] == 0) {
                throw new \Exception($res['msg']);
            }
            //  写入日志
            $resLog = (new ApplyLog())->addData($applyLogData);
            if ($resLog['code'] == 0) {
                throw new \Exception($resLog['msg']);
            }
            //  更新学位申请详情
            $resApplyType = (new ApplyType())->editData($applyTypeData);
            if ($resApplyType['code'] == 0) {
                throw new \Exception($resApplyType['msg']);
            }
            //  查询该学校的学位数量
            $planApply = Apply::where([
                'plan_id' => $apply['plan_id'],
                'school_id' => $data['result_school_id'],
                'status' => 1
            ])->sum('spare_total');
            //  获取当前已使用学位数量
            $usePlanApply = Db::name('UserApply')->where([
                'voided' => 0,
                'is_delete' => 0,
                'result_school_id' => $data['result_school_id'],
                'plan_id' => $apply['plan_id']
            ])->count();
            if($usePlanApply > $planApply)
            {
                throw new \Exception('学校学位数量不足');
            }
            if($res['code'] == 1)
            {
                $res['msg'] = "操作成功（剩余可使用学位数量为" . intval($planApply-$usePlanApply) ."）";
            }
            $this->commit();
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
            $this->rollback();
        }
        return $res;
    }
}