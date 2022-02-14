<?php
namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\middleware\ApplyMiddleware;
use app\common\model\Plan;
use app\common\model\RegionSetTime;
use app\common\model\Schools;
use app\common\model\SysRegion;
use app\mobile\model\user\Apply as model;
use app\mobile\model\user\ApplyTmp;
use app\mobile\model\user\ApplyMessage;
use app\mobile\model\user\Child;
use app\mobile\model\user\Family;
use app\mobile\model\user\House;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;
use app\mobile\validate\Apply as validate;
use aysncCurl\AsyncCURL;

class Apply extends MobileEducation
{
    protected $middleware = [
        ApplyMiddleware::class
    ];
    /**
     * 获取入学申请列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $list = Db::name('UserApply')
                    ->alias('apply')
                    ->join('Plan plan', 'plan.id = apply.plan_id')
                    ->join('Child child', 'child.id = apply.child_id')
                    ->join('Adcode adcode', 'adcode.adcode = apply.adcode')
                    ->join('School school','school.id = apply.apply_school_id','left')
                    ->field([
                        'apply.id',
                        'apply.plan_id',
                        'plan.name' => 'plan_name',
                        'plan.plan_time',
                        'apply.apply_time',
                        'apply.child_id',
                        'child.name',
                        'child.idcard',
                        'adcode.name' => 'adcode_name',
                        'apply.adcode',
                        'apply.school_type',
                        'apply.school_attr',
                        'apply.is_audit',
                        'apply.voided',
                        'is_prepare',
                        'apply.apply_school_id',
                        'school.name' => 'apply_school_name'
                    ])
                    ->where([
                        'apply.user_id' => $this->userInfo['id']
                    ])
                    ->select();

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
     * 獲取入學申請詳情
     * @param int apply_id
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'apply_id'
                ]);
                $info = Db::name('UserApply')
                    ->alias('apply')
                    ->join('Plan plan','plan.id = apply.plan_id')
                    ->join('SysRegion region', 'region.id = apply.region_id')
                    ->join('School school', 'school.id = apply.apply_school_id', 'left')
                    ->join('Child child', 'child.id = apply.child_id')
                    ->join('Family family', 'family.id = apply.family_id')
                    ->join('House house', 'house.id = apply.house_id', 'left')
                    ->join('Insurance insurance', 'insurance.id = apply.insurance_id', 'left')
                    ->join('Residence residence', 'residence.id = apply.residence_id', 'left')
                    ->join('Company company', 'company.id = apply.company_id', 'left')
                    ->where([
                        'apply.id' => $data['apply_id']
                    ])
                    ->field([
                        'apply.id',
                        'apply.plan_id',
                        'plan.name' => 'plan_name',
                        'apply.school_attr',
                        'apply.adcode',
                        'region.name' => 'region_name',
                        'apply.child_id',
                        'child.name' => 'child_name',
                        'child.idcard' => 'child_idcard',
                        'apply.family_id',
                        'family.relation' => 'family_relation',
                        'family.parent_name' => 'family_name',
                        'family.idcard' => 'family_idcard',
                        'apply.house_id',
                        'house.house_type',
                        'house.code_type' => 'house_code_type',
                        'house.address' => 'house_address',
                        'house.code' => 'house_code',
                        'apply.insurance_id',
                        'insurance.code' => 'insurance_code',
                        'apply.residence_id',
                        'residence.code' => 'residence_code',
                        'apply.company_id',
                        'company.code' => 'company_code',
                        'apply.apply_school_id',
                        'school.name' => 'apply_school_name'
                    ])
                    ->find();
                if (in_array($info['family_relation'], [3, 4, 5, 6])) {
                    $info['ancestor'] = Db::name('Ancestor')
                        ->alias('ancestor')
                        ->join('Family father', 'father.id = ancestor.father_id', 'left')
                        ->join('Family mother', 'mother.id = ancestor.mother_id', 'left')
                        ->where([
                            'ancestor.family_id' => $info['family_id'],
                            'ancestor.child_id' => $info['child_id'],
                            'ancestor.is_delete' => 0
                        ])
                        ->field([
                            'ancestor.father_id',
                            'father.parent_name' => 'father_name',
                            'father.idcard' => 'father_idcard',
                            'ancestor.mother_id',
                            'mother.parent_name' => 'mother_name',
                            'mother.idcard' => 'mother_idcard'
                        ])
                        ->find();
                }
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
     * 保存入學申請
     * @param int region_id                行政区域
     * @param int plan_id               招生计划自增ID
     * @param int school_type           学校类型（1：小学；2：初中）
     * @param int school_attr           机构性质（1：公办；2：民办）
     * @param int child_id              孩子信息自增ID
     * @param int family_id             监护人信息自增ID
     * @param int house_id              房产信息自增ID
     * @param int company_id            企业信息自增ID
     * @param int insurance_id          社保信息自增ID
     * @param int residence_id          居住证信息自增ID
     * @param string hash                  表单hash
     * @param string apply_time            提交的时间
     * @param int apply_school_id       民办申请的学校自增ID
     * @return Json
     */
    public function actSave(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'user_id' => $this->result['user_id'],
                    'region_id',
                    'plan_id',
                    'school_type',
                    'school_attr',
                    'child_id',
                    'family_id',
                    'house_id',
                    'company_id',
                    'insurance_id',
                    'residence_id',
                    'house_type',
                    'ancestor_id',
                    'apply_school_id',
                    'apply_time' => time(),
                    'apply_message_id' => 1,
                    'enrol_year' => date("Y"),
                    'hash',
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
                $tmpData = (new ApplyTmp())->where([
                    'user_id' => $data['user_id'],
                    'child_id' => $data['child_id'],
                    'completed' => 0
                ])->find();
                if(empty($tmpData)){
                    throw new \Exception('信息保存失败，请返回重新填写学生信息');
                }
                //  获取当年的招生计划
                $planList = Plan::where('plan_time', date('Y'))->column('id');
                //获取小学或者初中的报名截止时间
                $planData = Plan::where('plan_time', date('Y'))
                    ->where('school_type',$data['school_type'])
                    ->field([
                        'public_end_time',
                        'private_end_time'
                    ])
                    ->find();
                //当前时间是否已经超过截止时间
                $check_end_time = 0;
                if($data['school_attr'] == 1 && time() > $planData['public_end_time']){
                    $check_end_time = 1;
                }
                if($data['school_attr'] == 2 && time() > $planData['private_end_time']){
                    $check_end_time = 1;
                }
                //如果已经超过截止时间，判断是否具备在当前申报区域补录的条件
                $replenished = 0;
                $checkChanged = 0;
                if($check_end_time){
                    //判断是否开通了补录
                    $apply_replenished = Db::name('apply_replenished')
                        ->where('user_id',$data['user_id'])
                        ->whereRaw('FIND_IN_SET('.$data['school_attr'].',school_attr)')
                        ->where('end_time','>', time())
                        ->where('region_ids',$data['region_id'])
                        ->where('status',0)
                        ->where('deleted',0)
                        ->find();
                    if($apply_replenished){
                        $replenished = 1;
                        //高新区摇号落选自动开补录  民办不能补录  后台手动开的才能补录进来
                        if($apply_replenished['region_ids'] == 4 && $apply_replenished['replenished'] == 1 && $data['school_attr'] == 2){
                            throw new \Exception('不符合补录条件');
                        }

                        //判断民办学校开通的补录只能选择对应学校申报
                        $school_attr = explode(',',$apply_replenished['school_attr']);
                        if(in_array(2,$school_attr) && $apply_replenished['school_id'] > 0){
                            if($apply_replenished['school_id'] != $data['apply_school_id']){
                                throw new \Exception('不符合补录条件');
                            }
                        }
                    }
                    if($replenished){
                        //判断是否变更为当前申报区域
                        $change_child_ids = Db::name("change_region")
                            ->where("user_id", $data['user_id'])
                            ->where('status',0)
                            ->where('local_region_audit',1)
                            ->where('go_region_audit',1)
                            ->where('city_audit',1)
                            ->where('go_region_id',$data['region_id'])
                            ->column('child_id');
                        $change_child_ids = array_filter($change_child_ids);
                        if(in_array($data['child_id'],$change_child_ids)){
                            $checkChanged = 1;
                        }
                    }else{
                        throw new \Exception('已过入学申请填报截止时间');
                    }
                }
                //  如果是民办申请，则判断是否对本地学籍有要求
                if($data['school_attr'] == 2) {
                    $school = (new Schools())
                        ->where('id', $data['apply_school_id'])
                        ->where('disabled', 0)
                        ->where('deleted', 0)
                        ->find();
                    if (!$school) {
                        throw new \Exception('民办学校不存在');
                    }
                    //民办学校禁止非本地学籍填报
                    if ($school['localed'] == 1) {
                        $child_idcard = Db::name('user_child')->where('id',$data['child_id'])->value('idcard');
                        $sixth = Db::name("sixth_grade")->where('id_card', $child_idcard)->where('deleted', 0)->find();
                        if (!$sixth) {
                            throw new \Exception('申报该校需具备襄阳市学籍');
                        }
                    }
                    $data['region_id'] = $school['region_id'];
                }
                //如果选择公办学校  民办学校id为0
                if($data['school_attr'] == 1){
                    $data['apply_school_id'] = 0;
                }
                //  检测当前学年，是否递交过申请
                $isHasApply = model::where([
                    ['plan_id', 'in', $planList],
                    ['child_id', '=', $data['child_id']],
                    ['voided', '=', 0],
                    ['deleted', '=', 0],
                ])->field([
                    'id',
                    'region_id',
                    'school_attr',
                    'plan_id',
                    'audited',
                    'apply_status',
                    'prepared',
                ])->find();
                //  如果当前学年已经递交过申请
                if(!empty($isHasApply))
                {
                    if($isHasApply['prepared'] == 1){
                        throw new \Exception('该学生已经被预录取，不能再次提交申请');
                    }
                }
                //如果开通了补录,检查是否具备补录条件
                $check_replenish = 0;
                $primary_lost_status = 0;
                $check_replenish_status = 0;
                if($replenished){
                    //如果申请民办学校，检查是否还有学位
                    if($data['school_attr'] == 2)
                    {
                        //民办审批学位数量
                        $degree_count = Db::name('PlanApply')
                            ->where('status', 1)
                            ->where('deleted', 0)
                            ->where('school_id',  $data['apply_school_id'])
                            ->SUM('spare_total');
                        //民办已录取数量
                        $used_count = Db::name('UserApply')
                            ->where('deleted',0)
                            ->where('voided',0)
                            ->where('school_attr',2)
                            ->where('prepared',1)
                            ->where('resulted',1)
                            ->where('offlined',0)
                            ->where('admission_type','>',0)
                            ->where('result_school_id',$data['apply_school_id'])
                            ->count();
                        if($used_count >= $degree_count){
                            throw new \Exception('选择的民办学校学位已满');
                        }
                        $schoolData = Db::name('SysSchool')
                            ->where('id',$data['apply_school_id'])
                            ->where('deleted',0)
                            ->find();
                        if(empty($schoolData)){
                            throw new \Exception('未找到申报的学校');
                        }
                    }
                    //如果有未作废申请
                    if($isHasApply){
                        //如果为民办落选
                        if($isHasApply['apply_status'] == 4){
                            $check_replenish = 1;
                            $primary_lost_status = 1;
                        }
                        //如果为变更区域
                        if($checkChanged){
                            $check_replenish = 1;
                        }
                        if(!$check_replenish){
                            $false_region_id = Db::name('user_apply_detail')
                                ->where('user_apply_id', $isHasApply['id'])
                                ->where('deleted', 0)
                                ->value('false_region_id');
                            if(!empty($false_region_id)){
                                $check_replenish = 1;
                            }
                        }
                    }else{
                        $check_replenish = 1;
                    }
                    if(!$check_replenish){
                        throw new \Exception('提交的学生申请不符合补录条件');
                    }
                    //  获取该用户提交了几个学生信息
                    $child_ids = (new model())->where([
                        'user_id' =>  $data['user_id'],
                    ])->column('child_id');
                    $hasChildId = array_filter($child_ids);
                    $dictionary = new FilterData();
                    $getData = $dictionary->findValue('dictionary', 'SYSPARENTBIND','SYSPARENTBINDNUM');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    //如果补录填报学生数量已经达到设定数量
                    if(count($hasChildId) + 1 >= $getData['data'])
                    {
                        $check_replenish_status = 1;
                    }
                    $checkAllNum = model::where([
                        ['plan_id', 'in', $planList],
                        ['child_id', 'in', $hasChildId],
                        ['voided', '=', 0],
                        ['apply_status', '=', 4],
                        ['deleted', '=', 0],
                    ])->count();
                    //如果是最后一个民办落选需要补录
                    if($checkAllNum == 1)
                    {
                        $check_replenish_status = 1;
                    }
                    //如果是最后一个学生需要变更区域
                    if(count($change_child_ids) == 1)
                    {
                        $check_replenish_status = 1;
                    }
                }
                //不是补录的
                // 此处限制只能襄州区-变更取消

                //  如果当前学年已经递交过申请
                if(!empty($isHasApply))
                {
                    //  先删除之前的申请  再新增一条信息
                    if($replenished){
                        $data['replenished'] = 1;
                        Db::name("UserApply")->where('id',$isHasApply['id'])->update(['deleted'=>1,'voided'=>1]);
                    }else{
                        Db::name("UserApply")->where('id',$isHasApply['id'])->update(['deleted'=>1]);
                    }
                    Db::name("UserApplyStatus")->where('user_apply_id',$isHasApply['id'])->update(['deleted'=>1]);
                    Db::name("UserApplyDetail")->where('user_apply_id',$isHasApply['id'])->update(['deleted'=>1]);
                    $apply_id = Db::name("UserApply")->insertGetId([
                        'enrol_year' => $data['enrol_year'],
                        'plan_id' => $data['plan_id'],
                        'region_id' => $data['region_id'],
                        'school_attr' => $data['school_attr'],
                        'school_type' => $data['school_type'],
                        'user_id' => $data['user_id'],
                        'child_id' => $data['child_id'],
                        'family_id' => $data['family_id'],
                        'house_id' => $data['house_id'],
                        'ancestor_id' => $data['ancestor_id'],
                        'house_type' => $data['house_type'],
                        'apply_school_id' => $data['apply_school_id'],
                        'apply_message_id' => 1,
                        'primary_lost_status' => $primary_lost_status,
                    ]);
                }else{
                    //  检测是否有已作废申请
                    $isVoidApply = model::where([
                        ['plan_id', 'in', $planList],
                        ['child_id', '=', $data['child_id']],
                        ['voided', '=', 1],
                        ['deleted', '=', 0],
                    ])->field([
                        'id',
                    ])->find();
                    //  如果当前学年有已作废申请
                    if(!empty($isVoidApply))
                    {
                        //  先删除之前的申请  再新增一条信息
                        if($replenished){
                            $data['replenished'] = 1;
                        }
                        Db::name("UserApply")->where('id',$isVoidApply['id'])->update(['deleted'=>1]);
                        Db::name("UserApplyStatus")->where('user_apply_id',$isVoidApply['id'])->update(['deleted'=>1]);
                        Db::name("UserApplyDetail")->where('user_apply_id',$isVoidApply['id'])->update(['deleted'=>1]);
                    }
                    //  写入新的申请数据
                    $apply_id = Db::name("UserApply")->insertGetId([
                        'enrol_year' => $data['enrol_year'],
                        'plan_id' => $data['plan_id'],
                        'region_id' => $data['region_id'],
                        'school_attr' => $data['school_attr'],
                        'school_type' => $data['school_type'],
                        'user_id' => $data['user_id'],
                        'child_id' => $data['child_id'],
                        'family_id' => $data['family_id'],
                        'house_id' => $data['house_id'],
                        'ancestor_id' => $data['ancestor_id'],
                        'house_type' => $data['house_type'],
                        'apply_school_id' => $data['apply_school_id'],
                        'apply_message_id' => 1,
                        'primary_lost_status' => $primary_lost_status,
                    ]);
                }
                if($data['ancestor_id']){
                    Db::name('UserApplyTmp')
                        ->where('id',$tmpData['id'])
                        ->update([
                            'apply_id' => $apply_id,
                            'completed' => 1
                        ]);
                }else{
                    if($tmpData['ancestor_id']){
                        Db::name('UserAncestor')
                            ->where('id',$tmpData['ancestor_id'])
                            ->update([
                                'deleted' => 1
                            ]);
                    }
                    if($tmpData['other_family_id']){
                        Db::name('UserFamily')
                            ->where('id',$tmpData['other_family_id'])
                            ->update([
                                'deleted' => 1
                            ]);
                    }
                    if($tmpData['ancestors_family_id']){
                        Db::name('UserFamily')
                            ->where('id',$tmpData['ancestors_family_id'])
                            ->update([
                                'deleted' => 1
                            ]);
                    }
                    Db::name('UserApplyTmp')
                        ->where('id',$tmpData['id'])
                        ->update([
                            'apply_id' => $apply_id,
                            'ancestor_id' => 0,
                            'check_generations' => 0,
                            'other_family_id' => 0,
                            'check_other' => 0,
                            'ancestors_family_id' => 0,
                            'check_ancestors' => 0,
                            'completed' => 1
                        ]);
                }
                //  如果填报成功，则发送消息
                if($apply_id)
                {
                    $childInfo = Child::find($data['child_id']);
                    $message = ApplyMessage::where('id',1)->value('message');
                    $message = str_replace('child_name',$childInfo['real_name'],$message);
                    Db::name("UserApplyLog")->insertGetId([
                        'user_id' => $data['user_id'],
                        'user_apply_id' => $apply_id,
                        'apply_message_id' => 1,
                        'apply_message' => $message,
                        'is_show' => 1,
                        'is_progress' => 1,
                    ]);
                    Db::name("UserMessage")->insertGetId([
                        'user_id' => $data['user_id'],
                        'user_apply_id' => $apply_id,
                        'contents' => $message,
                        'title' => '申请填报成功',
                    ]);
                    //判断年龄
                    //获取招生年龄配置
                    $region_time = (new RegionSetTime())
                        ->where('region_id',$data['region_id'])
                        ->where('grade_id',$data['school_type'])
                        ->find();
                    $child_age = 0;
                    if($region_time){
                        //                        $birthday = strtotime($childInfo['birthday']);
                        $birthday = $childInfo['birthday'];
                        if($birthday >= $region_time['start_time'] && $birthday <= $region_time['end_time']){
                            $child_age = 1;  //足龄
                        }else if($birthday < $region_time['start_time']){
                            $child_age = 3; // 超龄
                        }else{
                            $child_age = 2; //不足龄
                        }
                    }
                    //如果补录更新补录表
                    if($replenished == 1) {
                        Db::name('apply_replenished')
                            ->where('user_id', $data['user_id'])
                            ->where('region_ids', $data['region_id'])
                            ->whereRaw('FIND_IN_SET('.$data['school_attr'].',school_attr)')
                            ->where('deleted',0)
                            ->update([
                                'apply_status' => 1,
                                'apply_id' => $apply_id,
                            ]);
                        if($data['school_attr'] == 2 && $schoolData['auto_admission'] && $child_age == 1)
                        {
                            //高新区不自动开补录
                            if($data['region_id'] != 4){
                                Db::name('UserApply')
                                    ->where('id',$apply_id)
                                    ->update([
                                        'prepared' => 1,
                                        'resulted' => 1,
                                        'admission_type' => 7,//自动录取 录取方式
                                        'result_school_id' => $data['apply_school_id'],
                                        'apply_status' => 5,//民办录取
                                    ]);
                            }
                        }
                        if($check_replenish_status){
                            Db::name('apply_replenished')
                                ->where('user_id', $data['user_id'])
                                ->where('deleted',0)
                                ->update([
                                    'status' => $check_replenish_status,
                                    'end_time' => 0,
                                    'start_time' => 0,
                                ]);
                        }
                    }
                    //如果是变更区域 更新
                    if($checkChanged == 1) {
                        Db::name('change_region')
                            ->where("child_id", $data['child_id'])
                            ->where('local_region_audit',1)
                            ->where('go_region_audit',1)
                            ->where('city_audit',1)
                            ->update(['status'=>1]);
                    }
                    $three_syndromes_status = 0;
                    if($data['company_id'] || $data['insurance_id'] || $data['residence_id']){
                        $three_syndromes_status = 1;
                    }
                    //添加汇总表
                    Db::name("UserApplyDetail")->insertGetId([
                        'user_apply_id' => $apply_id,
                        'child_id' => $data['child_id'],
                        'child_name' => $childInfo['real_name'],
                        'child_idcard' =>  $childInfo['idcard'],
                        'school_attr' => $data['school_attr'],
                        'school_type' => $data['school_type'],
                        'child_policstation' => $childInfo['api_policestation'],
                        'child_age' => $childInfo['age'],
                        'child_age_status' => $child_age,
                        'three_syndromes_status' => $three_syndromes_status,
                        'mobile' => $this->userInfo['user_name'],
                        'house_type' => $data['house_type'],
                    ]);
                    //增加比对结果表
                    if($data['company_id']){
                        $status_data['need_check_company'] = 1;
                    }
                    if($data['insurance_id']){
                        $status_data['need_check_insurance'] = 1;
                    }
                    if($data['residence_id']){
                        $status_data['need_check_residence'] = 1;
                    }
                    if($data['ancestor_id']){
                        $status_data['need_check_ancestor'] = 1;
                    }
                    $status_data['user_apply_id'] = $apply_id;
                    Db::name("UserApplyStatus")->insertGetId($status_data);
                }
                Db::commit();
                $real_apply_check = Cache::get('real_apply_check');
                if($real_apply_check){
                    $headers = [
                        'Content-Type:application/json; charset=utf-8',
                        'token:'.$this->userInfo['token']
                    ];
                    $sys_url = Cache::get('sys_url');
                    $param = $sys_url.'/mobile/Comparison/actConduct?id='.$tmpData['id'];
                    $acCurl = new AsyncCURL();
                    $acCurl->set_header($headers);
                    $acCurl->set_param($param);
                    $acCurl->send(true);
                }
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
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
     * 获取学校资源
     * @param int agree
     * @param int school_attr
     * @return Json
     */
    public function getSchool(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'school_attr',
                    'school_type'
                ]);
                //  验证表单hash
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'getSchool');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if($data['school_attr'] != 2){
                    throw new \Exception('民办才能选学校');
                }
                $where = [];
                if($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['school_name', 'like', '%' . $this->result['keyword'] . '%'];
                }
                $planData = Plan::where('plan_time', date('Y'))
                    ->where('school_type',$data['school_type'])
                    ->field([
                        'public_end_time',
                        'private_end_time'
                    ])
                    ->find();
                //当前时间是否已经超过截止时间
                $check_end_time = 0;
                if($data['school_attr'] == 2 && time() > $planData['private_end_time']){
                    $check_end_time = 1;
                }
                if($check_end_time == 1){
                    $region_ids = $replenished_public = Db::name("apply_replenished")
                        ->where("user_id",$this->userInfo['id'])
                        ->whereRaw('FIND_IN_SET(2,school_attr)')
                        ->where('status',0)
                        ->where('deleted',0)
                        ->column('region_ids');
                    if(count($region_ids)){
                        $where[] = ['region_id', 'in', $region_ids];
                    }
                }
                $school =  (new Schools())
                    ->where("deleted",0)
                    ->where('disabled',0)
                    ->where('school_type',$data['school_type'])
                    ->where('school_attr',$data['school_attr'])
                    ->where('displayed',1)
                    ->where($where)
                    ->order('sort_order','DESC')
                    ->field([
                        'school_name',
                        'id',
                        'region_id',
                        'address',
                        'applied',
                        'yaohao_end_status',
                    ])->paginate(['list_rows'=> $this->pageSize,'var_page'=>'curr'])->toArray();

                foreach((array)$school['data'] as $key=>$value){
                    $school['data'][$key]['apply_plan'] = Db::name("plan_apply")
                        ->where('school_id', $value['id'])
                        ->where('status', 1)
                        ->group('school_id')
                        ->sum('spare_total');
                    $school['data'][$key]['apply_num'] = '限'.($school['data'][$key]['apply_plan'] - Db::name("user_apply")
                                ->where('school_attr', 2)
                                ->where('voided', 0)
                                ->where("deleted", 0)
                                ->where("result_school_id", $value['id'])
                                ->count());
                    //民办审批学位数量
                    $degree_count = Db::name('PlanApply')
                        ->where('status', 1)
                        ->where('deleted', 0)
                        ->where('school_id',  $value['id'])
                        ->SUM('spare_total');
                    //民办已录取数量
                    $used_count = Db::name('UserApply')
                        ->where('deleted',0)
                        ->where('voided',0)
                        ->where('school_attr',2)
                        ->where('prepared',1)
                        ->where('resulted',1)
                        ->where('offlined',0)
                        ->where('admission_type','>',0)
                        ->where('result_school_id',$value['id'])
                        ->count();
                    if($used_count >= $degree_count){
                        $school['data'][$key]['yaohao_end_status'] = 1;
                    }
                }

                $res = [
                    'code' => 1,
                    'data' => $school,
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
     * 获取区县资源
     * @param int agree
     * @param int school_attr
     * @return Json
     */
    public function getRegion(): Json
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->only([
                    'school_attr',
                ]);
                if(!isset($postData['school_attr'])){
                    throw new \Exception('请返回填报窗口填报');
                }
                if($postData['school_attr'] != 1){
                    throw new \Exception('公办才能选区域');
                }

                $replenished_public = Db::name("apply_replenished")
                    ->where("user_id",$this->userInfo['id'])
                    ->whereRaw('FIND_IN_SET(1,school_attr)')
                    ->where('status',0)
                    ->where('deleted',0)
                    ->select()
                    ->toArray();
                $region_id = [];
                if($replenished_public){
                    $region_id = array_column($replenished_public,'region_ids');
                }
                $data = Cache::get('region');
                foreach ($data as $k => $v){
                    if($v['parent_id'] == '0'){
                        unset($data[$k]);
                    }
                    //是补录的取补录表开通区域
                    if($region_id) {
                        if (!in_array($v['id'], $region_id)) {
                            unset($data[$k]);
                        }
                    }
                    //不是补录的
                    // 此处限制襄州区
                }
                $data = array_values($data);
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
     * 获取学校性质，类型，区县资源
     * @return Json
     */
    public function index(): Json
    {

        if ($this->request->isPost()) {

            try {
                $school_info =
                    [
                        [
                            'school_type' => 1,
                            'public' =>
                                [
                                    'school_attr' => 1,
                                ],
                            'private' =>
                                [
                                    'school_attr' => 2,
                                ],
                        ],


                        [
                            'school_type' => 2,
                            'public' =>
                                [
                                    'school_attr' => 1,
                                ],
                            'private' =>
                                [
                                    'school_attr' => 2,
                                ],
                        ],
                    ];

                foreach ($school_info as $key=>$value){
                    foreach($value as $_key=>&$_value){
                        if($_key == 'public' || $_key == 'private'){
                            $plan = Plan::where('plan_time', date('Y'))
                                ->where('school_type',$value['school_type'])
                                ->field(['plan_name','id','public_start_time','public_end_time','private_start_time','private_end_time','school_type'])->find();
                            if(!$plan){
                                throw new \Exception('招生计划不存在');
                            }
                            $replenished_public = Db::name("apply_replenished")
                                ->where("user_id",$this->userInfo['id'])
                                ->whereRaw('FIND_IN_SET(1,school_attr)')
                                ->where('status',0)
                                ->where('deleted',0)
                                ->find();
                            $school_info[$key]['public']['public_is_open'] = 1;
                            if (time() < $plan['public_start_time']  || time() > $plan['public_end_time']) {
                                if($replenished_public){
                                    //判断补录时间
                                    if(time() < $replenished_public['start_time'] || time() > $replenished_public['end_time']) {
                                        $school_info[$key]['public']['public_is_open'] = -1;
                                    }
                                }else{
                                    $school_info[$key]['public']['public_is_open'] = -1;
                                }
                            }
                            $replenished_private = Db::name("apply_replenished")
                                ->where("user_id",$this->userInfo['id'])
                                ->whereRaw('FIND_IN_SET(2,school_attr)')
                                ->where('status',0)
                                ->where('deleted',0)
                                ->find();
                            $school_info[$key]['private']['private_is_open'] = 1;
                            if (time() < $plan['private_start_time']  || time() > $plan['private_end_time']) {

                                if($replenished_private){
                                    //判断补录时间
                                    if(time() < $replenished_private['start_time'] || time() > $replenished_private['end_time']) {
                                        $school_info[$key]['private']['private_is_open'] = -1;
                                    }
                                }else{
                                    $school_info[$key]['private']['private_is_open'] = -1;
                                }
                            }

                            $school_info[$key]['plan_id'] = $plan['id'];
                            $school_info[$key]['plan_name'] = $plan['plan_name'];

                            $school_info[$key]['public_start_time'] = date("Y.m.d H:i",$plan['public_start_time']);
                            $school_info[$key]['public_end_time'] = date("Y.m.d H:i",$plan['public_end_time']);
                        }
                    }
                }
                $data['school_info'] = $school_info;
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
     * 獲取招生計劃詳情
     * @param int id    招生计划自增ID
     * @return Json
     */
    public function getPlanDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only(['id']);
                if(!isset($data['id'])){
                    throw new \Exception('请返回填报窗口填报');
                }
                $info = (new plan())::find($data['id']);
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
     * 申请信息预览
     * @return Json
     */
    public function BeforeInsertApplyInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->result['user_id'],
                    'region_id',
                    'plan_id',
                    'school_attr',
                    'child_id',
                    'family_id',
                    'house_id',
                    'ancestor_family_id',
                    'apply_school_id',
                ]);

                $school_type = [1=>'小学',2=>'初中'];
                //  验证表单hash
                /*$checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }*/

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'showInfo');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $apply_info = [];
                if($data['school_attr'] == 1){
                    if(!$data['region_id']){
                        throw new \Exception('请选择区域');
                    }
                    $region = Cache::get('region',[]);
                    $region = filter_value_one($region, 'id', $data['region_id']);
                    if(!$region){
                        throw new \Exception('选择的区域不存在');
                    }
                    $apply_info['header']['title'] = '公办学校';
                    $apply_info['header']['label'] = '就读区域';
                    $apply_info['header']['name'] = $region['region_name'];
                }

                if($data['school_attr'] == 2){
                    if(!$data['apply_school_id']) {
                        throw new \Exception('请选择学校');
                    }
                    $school = (new Schools())
                        ->where('id',$data['apply_school_id'])
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
                    ->where('id',$data['family_id'])
                    ->where('deleted',0)
                    ->find();
                if(!$family) {
                    throw new \Exception('监护人信息不存在');
                }

                $house = (new House())
                    ->where('id',$data['house_id'])
                    ->where('deleted',0)
                    ->find();
                if(!$house) {
                    throw new \Exception('房产信息不存在');
                }

                $plan = (new Plan())->where('id',$data['plan_id'])->find();
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

//                $region = filter_value_one($region, 'id', $child['api_region_id']);
                $region = filter_value_one($region, 'id', $child['region_id']);
                $apply_info['basic']['child_region_name'] = $region['region_name'];
                $apply_info['basic']['plan_name'] = $school_type[$plan['school_type']];
                $apply_info['basic']['family_name'] = $family['parent_name'];
                $apply_info['basic']['family_idcard'] = $family['idcard'];


                //三代同堂
                $ancestor = (new \app\mobile\model\user\Ancestor())
                    ->where('family_id',$data['ancestor_family_id'])
                    ->where('deleted',0)
                    ->where('child_id',$data['child_id'])
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
                        ->where('id',$data['ancestor_family_id'])
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
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 学生入学申请汇总
     * @param int $apply_id
     * @return Json
     */
    private function ApplyDetail(int $apply_id): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = Db::name("user_apply")->find($apply_id);
                if(!$data){
                    throw new \Exception('申请信息不存在');
                }

                $child = Db::name('user_child')->find($data['child_id']);
                if(!$child){
                    throw new \Exception('学生信息不存在');
                }

                $family = Db::name('user_family')->find($data['family_id']);
                if(!$family){
                    throw new \Exception('监护人信息不存在');
                }

                $house = Db::name('user_house')->find($data['house_id']);
                if(!$house){
                    throw new \Exception('房产信息不存在');
                }
                $status_data['auto_check_child'] = $child['is_area_main'];
                $status_data['auto_check_family'] = $family['is_area_main'];

                $res = [
                    'code' => 1,
                    'data' => $status_data
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
     * 提交申请前检查
     * @return Json
     */
    public function check(): Json
    {

        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'region_id',
                    'plan_id',
                    'school_type',
                    'school_attr',
                    'child_id',
                    'enrol_year' => date("Y"),
                ]);
                $planList = Plan::where('plan_time', date('Y'))->column('id');
                //  检测当前学年，是否递交过申请
                $isHasApply = model::where([
                    ['plan_id', 'in', $planList],
                    ['child_id', '=', $data['child_id']],
                    ['voided', '=', 0]
                ])->field([
                    'id',
                    'region_id',
                    'school_attr',
                    'plan_id',
                    'audited',
                    'apply_school_id',
                ])->find();
                if(!empty($isHasApply)) {
                    //获取小学或者初中的报名截止时间
                    $planData = Plan::where('plan_time', date('Y'))
                        ->where('school_type',$data['school_type'])
                        ->field([
                            'public_end_time',
                            'private_end_time'
                        ])
                        ->find();
                    //当前时间是否已经超过截止时间
                    $check_end_time = 0;
                    if($data['school_attr'] == 1 && time() > $planData['public_end_time']){
                        $check_end_time = 1;
                    }
                    if($data['school_attr'] == 2 && time() > $planData['private_end_time']){
                        $check_end_time = 1;
                    }
                    $replenished = 0;
                    if($check_end_time) {
                        //判断是否开通了补录
                        $apply_replenished = Db::name('apply_replenished')
                            ->where('user_id', $data['user_id'])
                            ->whereRaw('FIND_IN_SET(' . $data['school_attr'] . ',school_attr)')
                            ->where('end_time', '>', time())
                            ->where('region_ids', $data['region_id'])
                            ->where('status', 0)
                            ->where('deleted', 0)
                            ->find();
                        if ($apply_replenished) {
                            $replenished = 1;
                        }
                    }
                    if (!$replenished) {
                        $res = [
                            'code' => 2,
                            'msg' => '您已提交过入学申请，若变更申请，原申请信息将被移除，确定要变更申请吗?',
                        ];
                    }else{
                        $res = [
                            'code' => 1,
                        ];
                    }
                }else{
                    $res = [
                        'code' => 1,
                    ];
                }

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