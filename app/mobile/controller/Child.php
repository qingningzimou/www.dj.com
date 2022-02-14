<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\middleware\ApplyMiddleware;
use app\common\model\Plan;
use app\common\model\RegionSetTime;
use app\common\model\SysDictionary;
use app\common\model\SysRegion;
use app\common\model\Schools;
use app\mobile\model\user\Child as model;
use app\mobile\model\user\ApplyTmp;
use app\mobile\model\user\Apply;
use app\mobile\model\user\User;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Db;
use think\File;
use think\response\Json;
use app\mobile\validate\Child as validate;
use comparison\Reckon;

class Child extends MobileEducation
{

    protected $middleware = [
        ApplyMiddleware::class
    ];
    /**
     * 获取孩子列表
     *
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $tmp_ids = (new ApplyTmp())->where([
                    'user_id' =>  $this->userInfo['id']
                ])->column('id');
                $list = Db::name('user_child')
                    ->Distinct(true)
                    ->whereIn('tmp_id', $tmp_ids)
                    ->where('deleted', 0)
                    ->field([
                        'id',
                        'real_name',
                        'idcard',
                        'idcard_pic',
                        'picurl',
                        'kindgarden_name'
                    ])
                    ->paginate(100);
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
     * 获取孩子詳情
     * @param int id
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only(['id']);
                $info = model::field([
                    'id',
                    'real_name',
                    'idcard',
                    'idcard_pic',
                    'picurl',
                    'kindgarden_name'
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
     * 获取页面所需要的资源
     */
    public function resArray(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data['relation'] = [
                    [
                        'value' => 1,
                        'label' => '父亲',
                    ],

                    [
                        'value' => 2,
                        'label' => '母亲',
                    ],

                    [
                        'value' => 3,
                        'label' => '爷爷',
                    ],

                    [
                        'value' => 4,
                        'label' => '奶奶',
                    ],

                    [
                        'value' => 5,
                        'label' => '外公',
                    ],

                    [
                        'value' => 6,
                        'label' => '外婆',
                    ],
                    [
                        'value' => 0,
                        'label' => '其他',
                    ],
                ];
                $data['house'] = [

                    [
                        'value' => 1,
                        'label' => '产权房',
                    ],

                    [
                        'value' => 2,
                        'label' => '租房',
                    ],

                    [
                        'value' => 3,
                        'label' => '自建房',
                    ],

                    [
                        'value' => 4,
                        'label' => '置换房',
                    ],

                    [
                        'value' => 5,
                        'label' => '公租房',
                    ],

                    [
                        'value' => 6,
                        'label' => '三代同堂',
                    ],
                ];

                $data['code'] = [
                    [
                        'value' => 1,
                        'label' => '房产证',
                    ],

                    [
                        'value' => 2,
                        'label' => '不动产证',
                    ],

                    [
                        'value' => 3,
                        'label' => '网签合同',
                    ],

                    [
                        'value' => 4,
                        'label' => '公租房',
                    ],
                ];

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
     * 新增孩子信息
     * @param string real_name      姓名
     * @param string idcard        身份证号
     * @param string idcard_pic    身份证照片
     * @param string picurl        户口簿照片
     * @param string hash          表单hash
     * @param string plan_id       招生计划自增ID
     * @param string region_id        区域id
     * @param int school_attr   机构性质（1：公办；2：民办）
     * @param int school_type   学校类型（1：小学；2：初中）
     * @param string kindergarten_name  毕业幼儿园
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'real_name',
                    'hash',
                    'idcard',
                    'picurl',
                    'plan_id',
                    'region_id',
                    'school_attr',
                    'school_type',
                    'apply_school_id',
                    'kindgarden_name',
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
                //身份证转大写
                $data['idcard'] = strtoupper(trim($data['idcard']));
                //  通过身份证号获取性别
                $data['sex'] = x_getSexByIdcard($data['idcard']);
                //  通过身份证号获取生日
                $data['birthday'] = getBirthdayByIdcard($data['idcard']);
                $age = birthday(date("Y-m-d",$data['birthday']));
                if(intval($age) <= 99 && intval($age) > 0){
                    $data['age'] = $age;
                }else{
                    $data['age'] = 0;
                }
                if($data['age'] >= 18 || $data['age'] == 0){
                    throw new \Exception('填报信息非适龄学生信息');
                }
                $idcard = $data['idcard'];
                if (!check_Idcard($data['idcard'])) {
                    throw new \Exception('身份证号码不正确');
                }
                if(!$this->checkImageUrl($data['picurl'])){
                    throw new \Exception('照片文件上传错误，请重新上传');
                }
                //判断年龄
                //获取招生年龄配置
                $region_time = (new RegionSetTime())
                    ->where('region_id',$data['region_id'])
                    ->where('grade_id',$data['school_type'])
                    ->find();
                if($region_time){
                    $birthday = $data['birthday'];
                    if(!($birthday >= $region_time['start_time'] && $birthday <= $region_time['end_time'])) {
                        $school_attr_text = $data['school_type'] == 1 ? '小学(一年级)' : '初中(七年级)';
                        $messsage = '您正在填报'.$school_attr_text.'，您填写的学生身份证信息非正常适龄入学年龄，请核实填报是否有误？';
                        $data['child_message'] = $messsage;
                    }
                }
                //  获取该用户录入了几个学生信息
                $tmp_ids = (new ApplyTmp())->where([
                    'user_id' =>  $this->result['user_id'],
                ])->column('id');
                $child_ids = (new model())
                    ->whereIn('tmp_id',$tmp_ids)
                    ->column('id');
                $hasChildId = array_filter($child_ids);
                $data['mobile'] = (new User())->where('id',$this->result['user_id'])->value('user_name');
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSPARENTBIND','SYSPARENTBINDNUM');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                //如果已经达到设定数量
                if(count($hasChildId) >= $getData['data'])
                {
                    $hasChildIdcard = model::whereIn( 'id',$hasChildId)->column('idcard');
                    if(!in_array($idcard,$hasChildIdcard)){
                        throw new \Exception('每个用户最多只能录入'.$getData['data'].'个学生信息');
                    }
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
                        ->where('user_id',$this->result['user_id'])
                        ->where('end_time','>', time())
                        ->whereRaw('FIND_IN_SET('.$data['school_attr'].',school_attr)')
                        ->where('status',0)
                        ->where('region_ids',$data['region_id'])
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
                        $child_ids = Db::name("change_region")
                            ->where("user_id", $this->result['user_id'])
                            ->where('status',0)
                            ->where('local_region_audit',1)
                            ->where('go_region_audit',1)
                            ->where('city_audit',1)
                            ->where('go_region_id',$data['region_id'])
                            ->column('child_id');
                        $idcards = Db::name('user_child')->whereIn('id',$child_ids)->where('deleted',0)->column('idcard');
                        if(in_array($data['idcard'],$idcards)){
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
                        $sixth = Db::name("sixth_grade")->where('id_card', $idcard)->where('deleted', 0)->find();
                        if (!$sixth) {
                            throw new \Exception('申报该校需具备襄阳市学籍');
                        }
                    }
                }
                //  判断该身份证是否已经存在
                $isHas = model::where([
                    'idcard' => $idcard
                ])->find();
                //  如果该学生信息存在
                if (!empty($isHas)) {
                    $child_id = $isHas['id'];
                    $hastmp_id = $isHas['tmp_id'];
                    //  检测当前学年，是否递交过申请
                    $isHasApply = Apply::where([
                        ['plan_id', 'in', $planList],
                        ['child_id', '=', $child_id],
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

                        }
                        //如果已提交申请
                        if($isHasApply){
                            //如果为民办落选
                            if($isHasApply['apply_status'] == 4){
                                $check_replenish = 1;
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
                    }
                    //查找已存在学生的临时表信息
                    $hasTmpData = (new ApplyTmp())->where([
                        'id' => $hastmp_id
                    ])->find();
                    //  如果该学生信息是自己操作的，则更新
                    if ($hasTmpData['user_id'] == $this->result['user_id']) {
                        //查找未提交信息
                        $tmpData = (new ApplyTmp())->where([
                            'child_id' => $child_id,
                            'completed' => 0
                        ])->find();
                        //如果没有未提交信息
                        if(empty($tmpData)){
                            $tmp_id = Db::name('UserApplyTmp')->insertGetId([
                                'user_id' => $this->result['user_id'],
                                'child_id' => $child_id
                            ]);
                            $data['tmp_id'] = $tmp_id;
                        }else{
                            $tmp_id = $tmpData['id'];
                            $data['tmp_id'] = $tmp_id;
                        }
                        $data['id'] = $child_id;
                        Db::name('UserChild')
                            ->where('id',$child_id)
                            ->update([
                                'region_id' => $data['region_id'],
                                'apply_school_id' => $data['apply_school_id'],
                                'real_name' => $data['real_name'],
                                'mobile' => $data['mobile'],
                                'idcard' => $data['idcard'],
                                'kindgarden_name' => $data['kindgarden_name'],
                                'sex' => $data['sex'],
                                'birthday' => $data['birthday'],
                                'picurl' => $data['picurl'],
                                'age' => $data['age'],
                            ]);
                    } else {
                        //  如果该学生信息不是自己操作的，则抛出，并附带操作人的手机号（隐藏中间4位）
                        $userMobile = User::where('id', $hasTmpData['user_id'])->value('user_name');
                        throw new \Exception(substr_replace($userMobile, '****', 3, 4) . '正在操作该学生信息');
                    }
                } else {
                    //如果开通了补录 并且申请了民办学校，检查是否还有学位
                    if($replenished && $data['school_attr'] == 2){
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
                    }
                    $child_id = Db::name('UserChild')->insertGetId([
                        'region_id' => $data['region_id'],
                        'apply_school_id' => $data['apply_school_id'],
                        'real_name' => $data['real_name'],
                        'mobile' => $data['mobile'],
                        'idcard' => $data['idcard'],
                        'kindgarden_name' => $data['kindgarden_name'],
                        'sex' => $data['sex'],
                        'birthday' => $data['birthday'],
                        'picurl' => $data['picurl'],
                        'age' => $data['age'],
                    ]);
                    $data['id'] = $child_id;
                    $tmp_id = Db::name('UserApplyTmp')->insertGetId([
                        'user_id' => $this->result['user_id'],
                        'child_id' => $child_id
                    ]);
                    Db::name('UserChild')->where('id',$child_id)->update(['tmp_id' => $tmp_id]);
                }
                $real_child_check = Cache::get('real_child_check');
                if($real_child_check){
                    $reckon = new Reckon();
                    $resReckon = $reckon->CheckChild($tmp_id,$child_id,$idcard);
                    if(!$resReckon['code']){
                        throw new \Exception($resReckon['msg']);
                    }
                }else{
                    Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_child' => 0]);
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
     * 编辑孩子信息
     * @param int id            自增ID
     * @param string real_name   姓名
     * @param string idcard        身份证号
     * @param string picurl        户口簿照片
     * @param int plan_id       招生计划自增ID
     * @param int region_id        区域id
     * @param int school_attr   机构性质（1：公办；2：民办）
     * @param int school_type   学校类型（1：小学；2：初中）
     * @return Json
     */
    public function actEdit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'real_name',
                    'picurl',
                    'plan_id',
                    'idcard',
                    'region_id',
                    'school_attr',
                    'school_type',
                    'apply_school_id',
                    'kindgarden_name',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                unset($data['hash']);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if(!$this->checkImageUrl($data['picurl'])){
                    throw new \Exception('照片文件上传错误，请重新上传');
                }
                //身份证转大写
                $data['idcard'] = strtoupper(trim($data['idcard']));
                //  通过身份证号获取生日
                $birthday = getBirthdayByIdcard($data['idcard']);
                $age = birthday(date("Y-m-d",$birthday));
                if(intval($age) <= 99 && intval($age) > 0 ){
                    $chkage = $age;
                }else{
                    $chkage = 0;
                }

                if($chkage >= 18 || $chkage == 0){
                    throw new \Exception('填报信息非适龄学生信息');
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
                        ->where('user_id',$this->result['user_id'])
                        ->where('end_time','>', time())
                        ->whereRaw('FIND_IN_SET('.$data['school_attr'].',school_attr)')
                        ->where('status',0)
                        ->where('region_ids',$data['region_id'])
                        ->where('deleted',0)
                        ->find();
                    if($apply_replenished){
//                        //高新区摇号落选自动开补录  民办不能补录  后台手动开的才能补录进来
//                        if( !($apply_replenished['region_ids'] == 4 && $apply_replenished['replenished'] == 1 && $data['school_attr'] == 2) ){
//                            $replenished = 1;
//                        }else{
//                            throw new \Exception('不符合补录条件');
//                        }
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
                        $child_ids = Db::name("change_region")
                            ->where("user_id", $this->result['user_id'])
                            ->where('status',0)
                            ->where('local_region_audit',1)
                            ->where('go_region_audit',1)
                            ->where('city_audit',1)
                            ->where('go_region_id',$data['region_id'])
                            ->column('child_id');
                        $idcards = Db::name('user_child')->whereIn('id',$child_ids)->where('deleted',0)->column('idcard');
                        if(in_array($data['idcard'],$idcards)){
                            $checkChanged = 1;
                        }
                    }else{
                        throw new \Exception('已过入学申请填报截止时间');
                    }
                }
                $child_id = $data['id'];
                //  检测当前学年，是否递交过申请
                $isHasApply = Apply::where([
                    ['plan_id', 'in', $planList],
                    ['child_id', '=', $child_id],
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
                    }
                    //如果已提交申请
                    if($isHasApply){
                        //如果为民办落选
                        if($isHasApply['apply_status'] == 4){
                            $check_replenish = 1;
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
                }
                //  验证已经存在的学生信息
                $childData = model::where([
                    'id' => $child_id
                ])->find();
                if(empty($childData)){
                    throw new \Exception('未找到提交的学生信息');
                }

                //判断年龄
                //获取招生年龄配置
                $region_time = (new RegionSetTime())
                    ->where('region_id',$data['region_id'])
                    ->where('grade_id',$data['school_type'])
                    ->find();
                if($region_time){
                    $birthday = $childData['birthday'];
                    if(!($birthday >= $region_time['start_time'] && $birthday <= $region_time['end_time'])) {
                        $school_attr_text = $data['school_type'] == 1 ? '小学(一年级)' : '初中(七年级)';
                        $messsage = '您正在填报'.$school_attr_text.'，您填写的学生身份证信息非正常适龄入学年龄，请核实填报是否有误？';
                        $data['child_message'] = $messsage;
                    }
                }

                $idcard = $childData['idcard'];
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
                        $sixth = Db::name("sixth_grade")->where('id_card', $idcard)->where('deleted', 0)->find();
                        if (!$sixth) {
                            throw new \Exception('申报该校需具备襄阳市学籍');
                        }
                    }
                }
                //查询学生的临时表信息
                $hasTmpData = (new ApplyTmp())->where([
                    'id' => $childData['tmp_id']
                ])->find();
                //  如果该学生信息是自己操作的，则更新
                if ($hasTmpData['user_id'] == $this->result['user_id']) {
                    //查找未提交信息
                    $tmpData = (new ApplyTmp())->where([
                        'child_id' => $child_id,
                        'completed' => 0
                    ])->find();
                    //如果没有未提交信息
                    if(empty($tmpData)){
                        $tmp_id = Db::name('UserApplyTmp')->insertGetId([
                            'user_id' => $this->result['user_id'],
                            'child_id' => $child_id
                        ]);
                        $data['tmp_id'] = $tmp_id;
                    }else{
                        $tmp_id = $tmpData['id'];
                        $data['tmp_id'] = $tmp_id;
                    }
                    Db::name('UserChild')
                        ->where('id',$child_id)
                        ->update([
                            'tmp_id' => $tmp_id,
                            'picurl' => $data['picurl'],
                            'region_id' => $data['region_id'],
                            'apply_school_id' => $data['apply_school_id'],
                            'kindgarden_name' => $data['kindgarden_name'],
                        ]);
                    $real_child_check = Cache::get('real_child_check');
                    if($real_child_check){
                        $reckon = new Reckon();
                        $resReckon = $reckon->CheckChild($tmp_id,$data['id'],$idcard);
                        if(!$resReckon['code']){
                            throw new \Exception($resReckon['msg']);
                        }
                    }else{
                        Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_child' => 0]);
                    }
                } else {
                    //  如果该学生信息不是自己操作的，则抛出，并附带操作人的手机号（隐藏中间4位）
                    $userMobile = User::where('id', $hasTmpData['user_id'])->value('user_name');
                    throw new \Exception(substr_replace($userMobile, '****', 3, 4) . '正在操作该学生信息');
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
     * 通过身份证正面照片识别身份证号码
     * @param string image 照片路劲
     * @return Json
     */
    public function getIdCard(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'image',
                ]);
                if(isset($data['image']) && !$data['image']){
                    throw new \Exception('请上传身份证照片');
                }
                $access_token = Cache::get('baidu_token');
                $url = 'https://aip.baidubce.com/rest/2.0/ocr/v1/idcard?access_token=' . $access_token;
                $img = file_get_contents($data['image']);
                $img = base64_encode($img);
                $bodys = array(
                    'id_card_side' => "front",
                    'image' => $img
                );
                $idcard_info = httpPost($url, $bodys,false);
                $idcard_info = json_decode($idcard_info,true);

                if(isset($idcard_info['words_result']['公民身份号码']['words'])){
                    $info['idcard'] = $idcard_info['words_result']['公民身份号码']['words'];
                    $info['real_name'] = $idcard_info['words_result']['姓名']['words'];
                    $res = [
                        'code' => 1,
                        'data' => $info
                    ];
                }else{
                    $res = [
                        'code' => 0,
                        'msg' => '未查找到数据，请重新上传身份证正面照片'
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