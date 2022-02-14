<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\model\WorkCityConfig;
use app\mobile\model\user\ChildRoll as model;
use app\mobile\model\user\ChildRollExtend as extend_model;
use app\mobile\model\user\ChildRollGuardian as guardian_model;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;
use app\mobile\validate\ChildRoll as validate;

class ChildRoll extends MobileEducation
{

    /**
     * 获取学生
     */
    public function getChild(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = Db::name('user_apply')
                    ->alias('u')
                    ->leftJoin('user_child c','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.signed",1)
                    ->where("u.resulted",1)
                    ->where("u.prepared",1)
                    ->where("u.voided",0)
                    ->where("u.result_school_id",">",0)
                    ->where("u.user_id",$this->userInfo['id'])
                    ->field(['c.real_name','c.idcard','u.child_id','u.school_type'])
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
     * 学籍信息填报时间是否开启
     */
    public function IsOpenRoll(): Json
    {
        if ($this->request->isPost()) {
            try {
                $config = (new WorkCityConfig())
                    ->where('item_key', 'XJXX')
                    ->where('deleted', '0')
                    ->find();
                if (!$config) {
                    throw new \Exception('系统错误');
                }
                $time = json_decode($config['item_value'], true);

                if (time() < strtotime($time['startTime']) || time() > strtotime($time['endTime'])) {
                    throw new \Exception('学籍信息填报功能尚未开启');
                }
                $res = [
                    'code' => 1,
                    'data' => $time
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
     * 获取资源
     */
    public function resArray(): Json
    {
        $data['code'] = 1;
        $data['data']['cart_type'] = [
            [
                'name' => '居民身份证',
                'id' => 1,
            ],
        ];
        $data['data']['politics_type'] = [
            [
                'name' => '群众',
                'id' => '群众',
            ],
            [
                'name' => '团员',
                'id' => '团员',
            ],
            [
                'name' => '党员',
                'id' => '党员',
            ]
        ];
        $data['data']['health_type'] = [
            [
                'name' => '健康',
                'id' => '健康',
            ],
            [
                'name' => '良好',
                'id' => '良好',
            ]
        ];
        $data['data']['entrance_type'] = [
            [
                'name' => '就近入学',
                'id' => '就近入学',
            ],
            [
                'name' => '其他',
                'id' => '其他',
            ],
        ];
        $data['data']['study_method'] = [
            [
                'name' => '走读',
                'id' => '走读',
            ],
            [
                'name' => '寄宿',
                'id' => '寄宿',
            ],
        ];
        $data['data']['grade_code'] = [
            [
                'name' => date("Y").'级',
                'id' => date("Y").'级',
            ],
        ];
        $data['data']['regular_class'] = [
            [
                'name' => '随班就读',
                'id' => '随班就读',
            ],
        ];
        $data['data']['relation_type'] = [
            [
                'name' => '父亲',
                'id' => '父亲',
            ],
            [
                'name' => '母亲',
                'id' => '母亲',
            ],
            [
                'name' => '爷爷',
                'id' => '爷爷',
            ],
            [
                'name' => '奶奶',
                'id' => '奶奶',
            ],
            [
                'name' => '外公',
                'id' => '外公',
            ],
            [
                'name' => '外婆',
                'id' => '外婆',
            ],
            [
                'name' => '其它',
                'id' => '其它',
            ],
        ];
        return parent::ajaxReturn($data);
    }

    /**
     * 获取学生学籍信息
     */
    public function getRoll(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only(['child_id']);
                $res = Db::name('sys_school_roll')
                    ->alias('r')
                    ->leftJoin('sys_school_roll_extend e','r.id=e.school_roll_id and e.deleted = 0')
                    ->where("r.deleted",0)
                    ->where("r.child_id",$data['child_id'])
                    ->field(['r.id','r.real_name','r.sex','r.birthday','r.birthplace','r.nativeplace','r.nation','r.nationality','r.card_type','r.id_card','r.overseas','r.politics','r.health','r.photo','r.student_code_auxiliary',
                        'r.student_number','r.grade_code','r.class_code','r.entrance_time','r.entrance_type','r.study_method','r.student_source','r.current_address','r.mailing_address','r.home_address','r.mobile','r.registed','r.postal_code','r.email','r.homepage'
                        ,'e.school_roll_id','e.name_pinyin','e.name_used','e.id_card_validity','e.residence','e.birthplace_attr','e.specialty','e.onlyed','e.preschooled','e.stayed','e.flowed','e.orphaned','e.martyred','e.regular_class'
                        ,'e.disability_type','e.purchased','e.supported','e.subsidy','e.distance','e.transportation','e.school_bused'])
                    ->find();
                //$child = Db::name('user_child')->where('id',$data['child_id'])->where('deleted',0)->field(['real_name','idcard'=>'id_card'])->find();
                if($res){
                    $famliy = Db::name('sys_school_roll_guardian')
                        ->where("deleted",0)
                        ->where("school_roll_id",$res['school_roll_id'])
                        ->select()
                        ->order('id', 'ASC')
                        ->toArray();
                    if($famliy){
                        foreach ($famliy as $k => $v){
                            if($k == 0){
                                $res['school_roll_id'] = $v['school_roll_id'];
                                $res['one_real_name'] = $v['real_name'];
                                $res['one_relation'] = $v['relation'];
                                $res['one_relation_remark'] = $v['relation_remark'];
                                $res['one_nation'] = $v['nation'];
                                $res['one_work_address'] = $v['work_address'];
                                $res['one_current_address'] = $v['current_address'];
                                $res['one_residence'] = $v['residence'];
                                $res['one_mobile'] = $v['mobile'];
                                $res['one_guardian'] = $v['guardian'];
                                $res['one_card_type'] = $v['card_type'];
                                $res['one_id_card'] = $v['id_card'];
                                $res['one_duties'] = $v['duties'];
                            }
                            if($k == 1){
                                $res['school_roll_id'] = $v['school_roll_id'];
                                $res['two_real_name'] = $v['real_name'];
                                $res['two_relation'] = $v['relation'];
                                $res['two_relation_remark'] = $v['relation_remark'];
                                $res['two_nation'] = $v['nation'];
                                $res['two_work_address'] = $v['work_address'];
                                $res['two_current_address'] = $v['current_address'];
                                $res['two_residence'] = $v['residence'];
                                $res['two_mobile'] = $v['mobile'];
                                $res['two_guardian'] = $v['guardian'];
                                $res['two_card_type'] = $v['card_type'];
                                $res['two_id_card'] = $v['id_card'];
                                $res['two_duties'] = $v['duties'];
                            }
                        }
                    }
                }
                $child = Db::name('user_apply')
                    ->alias('u')
                    ->leftJoin('user_child c','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.signed",1)
                    ->where("u.resulted",1)
                    ->where("u.prepared",1)
                    ->where("u.voided",0)
                    ->where("u.result_school_id",">",0)
                    ->where("u.user_id",$this->userInfo['id'])
                    ->where("u.child_id",$data['child_id'])
                    ->field(['c.real_name','c.idcard' => 'id_card','u.result_school_id'])
                    ->find();
                if($child){
                    $school = Cache::get('school');
                    $child['result_school_name'] = '-';//最终录取学校
                    $resultSchoolData = filter_value_one($school, 'id', $child['result_school_id']);
                    if (count($resultSchoolData) > 0){
                        $child['result_school_name'] = $resultSchoolData['school_name'];
                    }
                }

                $res['child'] = $child;
                $res = [
                    'code' => 1,
                    'data' => $res
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
     * 【学生个人基础信息】
     * @param string real_name      姓名
     * @param tinyint sex      性别1、男，2、女
     * @param string birthday      出生日期（8位数字）
     * @param string birthplace      出生地（身份证号前6位）
     * @param string nativeplace      籍贯
     * @param string nation      民族
     * @param string nationality      国籍/地区
     * @param tinyint card_type      身份证件类型，1、身份证，2护照，3其他
     * @param string id_card        身份证号
     * @param tinyint overseas        是否港澳台侨外
     * @param varchar politics        政治面貌（群众、党员）
     * @param varchar health        健康状况（健康、良好）
     * @return Json
     */
    public function actAddBasics(): Json
    {
        if ($this->request->isPost()) {

            Db::startTrans();
            try {
                $config = (new WorkCityConfig())
                    ->where('item_key', 'XJXX')
                    ->where('deleted', '0')
                    ->find();
                if (!$config) {
                    throw new \Exception('系统错误');
                }
                $time = json_decode($config['item_value'], true);

                if (time() < strtotime($time['startTime']) || time() > strtotime($time['endTime'])) {
                    throw new \Exception('学籍信息填报功能尚未开启');
                }
                $data = $this->request->only([
                    'child_id',
                    'real_name',
                    'sex',
                    'birthday',
                    'nativeplace',
                    'nation',
                    'nationality',
                    'card_type',
                    'id_card',
                    'overseas',
                    'politics',
                    'health',
                    'hash',
                ]);
                $data['id_card'] = strtoupper($data['id_card']);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addbasics');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $user_appy = Db::name('user_apply')
                    ->alias('u')
                    ->leftJoin('user_child c','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.signed",1)
                    ->where("u.resulted",1)
                    ->where("u.prepared",1)
                    ->where("u.voided",0)
                    ->where("c.id",$data['child_id'])
                    ->where("u.user_id",$this->userInfo['id'])
                    ->field(['c.real_name','c.idcard','u.child_id','u.region_id','u.result_school_id'])
                    ->find();

                if(!$user_appy){
                    throw new \Exception('学生未入学报到');
                }

                //身份证id_card
                if($data['card_type'] == 1){
                    $idcard = strtoupper(trim($data['id_card']));
                    if($idcard != $user_appy['idcard']){
                        throw new \Exception('证件号与当前学生申报身份证不一致');
                    }
                    if($data['real_name'] != $user_appy['real_name']){
                        throw new \Exception('姓名与当前学生不一致');
                    }
                    if(!preg_match('/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/',$idcard)){
                        throw new \Exception('身份证格式不正确');
                    }
                    //  通过身份证号获取性别
                    $sex = x_getSexByIdcard($data['id_card']);
                    //  通过身份证号获取生日
                    if (strlen($idcard) == 15) {
                        $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";
                        @preg_match($regx, $idcard, $arr_split);
                        $birthday = "19" . $arr_split[2] . $arr_split[3] .  $arr_split[4];
                    } else {
                        $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
                        @preg_match($regx, $idcard, $arr_split);
                        $birthday = $arr_split[2] . $arr_split[3]  . $arr_split[4];
                    }
                    $birthplace = $arr_split[1];
                    if($sex != $data['sex']){
                        throw new \Exception('性别和身份证不匹配');
                    }
                    if($birthday != $data['birthday']){
                        throw new \Exception('出生日期和身份证不匹配');
                    }
                    $data['birthplace'] = $birthplace;
                }

                $data['region_id'] = $user_appy['region_id'];
                $data['school_id'] = $user_appy['result_school_id'];

                //  判断学生个人基础信息是否已填写
                $isHas = model::where([
                    'child_id' => $data['child_id'],
                    'deleted' => 0
                ])->find();
                if($isHas && $isHas['registed'] == 1){
                    throw new \Exception('学籍确认提交后无法修改');
                }
                //  如果学生个人基础信息存在 更新
                if (!empty($isHas)) {
                    $data['id'] = $isHas['id'];
                    $res = (new model())->editData($data);
                    if ($res['code'] == 1) {
                        $res['data'] = array_merge($data, ['school_roll_id' => $data['id']]);
                    }
                } else {
                    //如果学生个人基础信息不存在 新增
                    $res = (new model())->addData($data, 1);
                    if ($res['code'] == 1) {
                        $res['data'] = array_merge($data, ['school_roll_id' => $res['insert_id']]);
                    }
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
     * 【学生个人辅助信息】
     * @param string name_pinyin    姓名拼音
     * @param string name_used    曾用名
     * @param string id_card_validity    身份证有效期
     * @param string residence    户口所在地★（区域代码）
     * @param string birthplace_attr    户口性质★（农业/非农业）
     * @param string specialty    特长
     * @return Json
     */
    public function actAddSide(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'school_roll_id',
                    'name_pinyin',
                    'name_used',
                    'id_card_validity',
                    'residence',
                    'birthplace_attr',
                    'specialty',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addside');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $roll = model::where([
                    'id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(!$roll){
                    throw new \Exception('学生个人基础信息未填写');
                }

                //身份证id_card
                if($roll['card_type'] == 1){
                    $idcard = strtoupper(trim($roll['id_card']));
                    $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
                    @preg_match($regx, $idcard, $arr_split);
                    $residence = $arr_split[1];

                    /*if($residence != $data['residence']){
                        throw new \Exception('户口所在地和身份证不匹配');
                    }*/
                }

                if($roll['registed'] == 1){
                    throw new \Exception('学籍确认提交后无法修改');
                }

                //  判断学生个人辅助信息是否已填写
                $isHas = extend_model::where([
                    'school_roll_id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();
                //  如果该学生个人辅助信息存在 更新
                if (!empty($isHas)) {
                    $data['id'] = $isHas['id'];
                    $res = (new extend_model())->editData($data);
                    if ($res['code'] == 1) {
                        $res['data'] = $data;
                    }
                } else {
                    //  如果学生个人辅助信息不存在 新增
                    $res = (new extend_model())->addData($data, 1);
                    if ($res['code'] == 1) {
                        $res['data'] = array_merge($data, ['id' => $res['insert_id']]);
                    }
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
     * 【学生学籍基本信息】
     * @param varchar student_code_auxiliary        学籍辅号
     * @param varchar student_number        班内学号
     * @param varchar grade_code        年级（2021级）
     * @param varchar class_code        班级号（202101）
     * @param varchar entrance_time        入学年月（202109）
     * @param varchar entrance_type        入学方式（就近入学）
     * @param varchar study_method        就读方式（走读、寄读）
     * @param varchar student_source        学生来源
     * @return Json
     */
    public function actAddRoll(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'school_roll_id',
                    'student_code_auxiliary',
                    'student_number',
                    'grade_code',
                    'class_code',
                    'entrance_time',
                    'entrance_type',
                    'study_method',
                    'student_source',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addroll');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $roll = model::where([
                    'id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(!$roll){
                    throw new \Exception('学生个人基础信息未填写');
                }

                if($roll['registed'] == 1){
                    throw new \Exception('学籍确认提交后无法修改');
                }

                //  跟新学生学籍基础信息存在
                $data['id'] = $roll['id'];
                $res = (new model())->editData($data);
                if ($res['code'] == 1) {
                    $res['data'] = $data;
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
     * 【学生个人联系信息】
     * @param varchar current_address        现住址（市区街道、小区、楼）
     * @param varchar mailing_address        通信地址
     * @param varchar home_address        家庭地址
     * @param varchar mobile        联系电话
     * @param varchar postal_code        邮政编码
     * @param varchar email        电子邮箱地址
     * @param varchar homepage        主页地址
     * @return Json
     */
    public function actAddContact(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'school_roll_id',
                    'current_address',
                    'mailing_address',
                    'home_address',
                    'mobile',
                    'postal_code',
                    'email',
                    'homepage',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addcontact');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $roll = model::where([
                    'id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(!$roll){
                    throw new \Exception('学生个人基础信息未填写');
                }

                if($roll['registed'] == 1){
                    throw new \Exception('学籍确认提交后无法修改');
                }

                //  跟新学生个人联系信息
                $data['id'] = $roll['id'];
                $res = (new model())->editData($data);
                if ($res['code'] == 1) {
                    $res['data'] = $data;
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
     * 【学生个人扩展信息】
     * @param tinyint onlyed   是否独生子女★
     * @param tinyint preschooled    是否受过学前教育★
     * @param tinyint stayed    是否留守儿童★
     * @param tinyint flowed    是否进城务工人员随迁子女★
     * @param tinyint orphaned    是否孤儿★
     * @param tinyint martyred    是否烈士或优抚子女★
     * @param varchar regular_class    随班就读
     * @param varchar disability_type    残疾类型
     * @param tinyint purchased    是否由政府购买学位
     * @param tinyint supported    是否需要申请资助★
     * @param tinyint subsidy    是否享受一补★
     * @return Json
     */
    public function actAddExtend(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'school_roll_id',
                    'onlyed',
                    'preschooled',
                    'stayed',
                    'flowed',
                    'orphaned',
                    'martyred',
                    'regular_class',
                    'disability_type',
                    'purchased',
                    'supported',
                    'subsidy',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addextent');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $roll = model::where([
                    'id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(!$roll){
                    throw new \Exception('学生个人基础信息未填写');
                }

                if($roll['registed'] == 1){
                    throw new \Exception('学籍确认提交后无法修改');
                }

                //  判断学生个人辅助信息是否已填写
                $extend_roll = extend_model::where([
                    'school_roll_id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(empty($extend_roll)){
                    throw new \Exception('学生个人辅助信息未填写');
                }

                //  如果学生信息不存在
                $data['id'] = $extend_roll['id'];
                $res = (new extend_model())->editData($data);
                if ($res['code'] == 1) {
                    $res['data'] = $data;
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
     * 【学生上下学交通方式】
     * @param tinyint distance    上下学距离（公里）
     * @param varchar transportation    上下学交通方式
     * @param tinyint school_bused    是否需要乘坐校车
     * @return Json
     */
    public function actAddTraffic(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'school_roll_id',
                    'distance',
                    'transportation',
                    'school_bused',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addtraffic');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $roll = model::where([
                    'id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(!$roll){
                    throw new \Exception('学生个人基础信息未填写');
                }

                if($roll['registed'] == 1){
                    throw new \Exception('学籍确认提交后无法修改');
                }

                //  判断学生个人辅助信息是否已填写
                $extend_roll = extend_model::where([
                    'school_roll_id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(empty($extend_roll)){
                    throw new \Exception('学生个人辅助信息未填写');
                }

                $data['id'] = $extend_roll['id'];
                $res = (new extend_model())->editData($data);
                if ($res['code'] == 1) {
                    $res['data'] = $data;
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
     * 【家庭成员或监护人一】
     * @param varchar one_real_name    家庭成员或监护人姓名
     * @param varchar one_relation    关系★（父亲）
     * @param varchar one_relation_remark    关系说明
     * @param varchar one_nation    民族
     * @param varchar one_work_address    工作单位
     * @param varchar one_current_address    现住址★
     * @param varchar one_residence    户口所在地★（区域代码）
     * @param varchar one_mobile    联系电话★
     * @param tinyint one_guardian    是否监护人★
     * @param tinyint one_card_type    身份证件类型，1、身份证，2护照，3其他
     * @param varchar one_id_card    身份证件号
     * @param varchar one_duties    职务
     * @return Json
     */
    public function actAddFamilyOne(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'school_roll_id',
                    'one_real_name',
                    'one_relation',
                    'one_relation_remark',
                    'one_nation',
                    'one_work_address',
                    'one_current_address',
                    'one_residence',
                    'one_mobile',
                    'one_guardian',
                    'one_card_type',
                    'one_id_card',
                    'one_duties',
                    'hash',
                ]);
                $data['one_id_card'] = strtoupper($data['one_id_card']);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addfamilyone');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                //身份证id_card
                if($data['one_card_type'] == 1 && $data['one_id_card']){
                    $idcard = strtoupper(trim($data['one_id_card']));
                    if(!preg_match('/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/',$idcard)){
                        throw new \Exception('身份证格式不正确');
                    }
                    $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
                    @preg_match($regx, $idcard, $arr_split);
                    $residence = $arr_split[1];

                    /*if($residence != $data['one_residence']){
                        throw new \Exception('户口所在地和身份证不匹配');
                    }*/
                }
                $roll = model::where([
                    'id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(!$roll){
                    throw new \Exception('学生个人基础信息未填写');
                }

                if($roll['registed'] == 1){
                    throw new \Exception('学籍确认提交后无法修改');
                }

                $isHas = guardian_model::where([
                    'school_roll_id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();
                $saveData = [];
                $saveData['school_roll_id'] = $data['school_roll_id'];
                $saveData['real_name'] = $data['one_real_name'];
                $saveData['relation'] = $data['one_relation'];
                $saveData['relation_remark'] = $data['one_relation_remark'];
                $saveData['nation'] = $data['one_nation'];
                $saveData['work_address'] = $data['one_work_address'];
                $saveData['current_address'] = $data['one_current_address'];
                $saveData['residence'] = $data['one_residence'];
                $saveData['mobile'] = $data['one_mobile'];
                $saveData['guardian'] = $data['one_guardian'];
                $saveData['card_type'] = $data['one_card_type'];
                $saveData['id_card'] = $data['one_id_card'];
                $saveData['duties'] = $data['one_duties'];
                //  如果已录入家庭成员信息，更新
                if (!empty($isHas)) {
                    $saveData['id'] = $isHas['id'];
                    $res = (new guardian_model())->editData($saveData);
                    if ($res['code'] == 1) {
                        $res['data'] = $data;
                    }
                } else {
                    //  如果家庭成员信息不存在 新增
                    $res = (new guardian_model())->addData($saveData, 1);
                    if ($res['code'] == 1) {
                        $res['data'] = array_merge($data, ['id' => $res['insert_id']]);
                    }
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
     * 【家庭成员或监护人二】
     * @param varchar two_real_name    家庭成员或监护人姓名
     * @param varchar two_relation    关系★（父亲）
     * @param varchar two_relation_remark    关系说明
     * @param varchar two_nation    民族
     * @param varchar two_work_address    工作单位
     * @param varchar two_current_address    现住址★
     * @param varchar two_residence    户口所在地★（区域代码）
     * @param varchar two_mobile    联系电话★
     * @param tinyint two_guardian    是否监护人★
     * @param tinyint two_card_type   身份证件类型，1、身份证，2护照，3其他
     * @param varchar two_id_card    身份证件号
     * @param varchar two_duties    职务
     * @return Json
     */
    public function actAddFamilyTwo(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'school_roll_id',
                    'two_real_name',
                    'two_relation',
                    'two_relation_remark',
                    'two_nation',
                    'two_work_address',
                    'two_current_address',
                    'two_residence',
                    'two_mobile',
                    'two_guardian',
                    'two_card_type',
                    'two_id_card',
                    'two_duties',
                    'hash',
                ]);
                $data['two_id_card'] = strtoupper($data['two_id_card']);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addfamilytwo');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                //身份证id_card
                if($data['two_card_type'] == 1 && $data['two_id_card']){
                    $idcard = strtoupper(trim($data['two_id_card']));
                    if(!preg_match('/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/',$idcard)){
                        throw new \Exception('身份证格式不正确');
                    }
                    $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
                    @preg_match($regx, $idcard, $arr_split);
                    $residence = $arr_split[1];

                    /*if($residence != $data['two_residence']){
                        throw new \Exception('户口所在地和身份证不匹配');
                    }*/
                }

                $roll = model::where([
                    'id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->find();

                if(!$roll){
                    throw new \Exception('学生个人基础信息未填写');
                }

                if($roll['registed'] == 1){
                    throw new \Exception('学籍确认提交后无法修改');
                }

                $hasFamily = guardian_model::where([
                    'school_roll_id' => $data['school_roll_id'],
                    'deleted' => 0
                ])->count();
                $saveData = [];
                $saveData['school_roll_id'] = $data['school_roll_id'];
                $saveData['real_name'] = $data['two_real_name'];
                $saveData['relation'] = $data['two_relation'];
                $saveData['relation_remark'] = $data['two_relation_remark'];
                $saveData['nation'] = $data['two_nation'];
                $saveData['work_address'] = $data['two_work_address'];
                $saveData['current_address'] = $data['two_current_address'];
                $saveData['residence'] = $data['two_residence'];
                $saveData['mobile'] = $data['two_mobile'];
                $saveData['guardian'] = $data['two_guardian'];
                $saveData['card_type'] = $data['two_card_type'];
                $saveData['id_card'] = $data['two_id_card'];
                $saveData['duties'] = $data['two_duties'];

                //  如果该学生个人辅助信息信息存在
                if ($hasFamily != 1) {
                    throw new \Exception('学生家庭成员或监护人录入异常');
                } else {
                    $res = (new guardian_model())->addData($saveData, 1);
                    if ($res['code'] == 1) {
                        $update_roll = (new model())->editData(['id'=>$data['school_roll_id'], 'registed'=>1]);
                        if($update_roll['code'] == 1){
                            $res['data'] = $data;
                        }
                    }
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
     * 初中学籍
    */
    public function getJuniorRoll(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only(['child_id']);
                $result = Db::name('user_apply')
                    ->alias('u')
                    ->leftJoin('user_child c','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.signed",1)
                    ->where("u.resulted",1)
                    ->where("u.prepared",1)
                    ->where("u.voided",0)
                    ->where("u.result_school_id",">",0)
                    ->where("u.user_id",$this->userInfo['id'])
                    ->where("u.child_id",$data['child_id'])
                    ->field(['u.school_type','c.real_name','c.id','c.idcard','u.result_school_id'])
                    ->find();

                if(!$result ){
                    throw new \Exception('学生无申请信息！');
                }
                if($result['school_type'] != 2){
                    throw new \Exception('填报的初中学校才能填写学籍');
                }

                $school = Cache::get('school');
                $resultSchoolData = filter_value_one($school, 'id', $result['result_school_id']);

                $data['result'] = Db::name("sys_school_roll")
                    ->where('child_id',$data['child_id'])
                    ->where('deleted',0)
                    ->field(['real_name','student_code','graduation_school','graduation_school_code'])
                    ->find();
                if(!$data['result']){
                    $sixth = Db::name("sixth_grade")->where('id_card',$result['idcard'])->where('deleted',0)->find();
                    $data['result']['real_name'] = $result['real_name'];
                    if($sixth){
                        $data['result']['student_code'] = $sixth['student_code'];
                        $graduation_school = Db::name('sys_school')
                            ->where('deleted',0)
                            ->where('disabled',0)
                            ->where('id',$sixth['graduation_school_id'])
                            ->field(['school_name','school_code'])
                            ->find();
                        $data['result']['graduation_school'] = $graduation_school['school_name'];
                        $data['result']['graduation_school_code'] = $graduation_school['school_code'];
                    }
                }

                $data['result']['result_school_name'] = '-';//最终录取学校
                if (count($resultSchoolData) > 0){
                    $data['result']['result_school_name'] = $resultSchoolData['school_name'];
                }

                $data['school'] = Db::name('sys_school')
                    ->where('deleted',0)
                    ->where('disabled',0)
                    ->where('school_type',1)
                    ->field(['id','school_name','school_code'])
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

    public function addJuniorRoll(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'child_id',
                    'student_code',
                    'real_name',
                    'graduation_school',
                    'graduation_school_code',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }


                $result = Db::name('user_apply')
                    ->alias('u')
                    ->leftJoin('user_child c','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.signed",1)
                    ->where("u.resulted",1)
                    ->where("u.prepared",1)
                    ->where("u.voided",0)
                    ->where("u.result_school_id",">",0)
                    ->where("u.user_id",$this->userInfo['id'])
                    ->where("u.child_id",$data['child_id'])
                    ->field(['u.region_id','c.idcard','u.result_school_id','c.sex'])
                    ->find();
                if(!$result){
                    throw new \Exception('没有查询到申报信息');
                }
                $roll = model::where([
                    'child_id' => $data['child_id'],
                    'deleted' => 0
                ])->find();

               if($roll){
                   throw new \Exception('学籍信息已存在不能修改');
               }

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'addJuniorRoll');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['region_id'] = $result['region_id'];
                $data['school_id'] = $result['result_school_id'];
                $data['registed'] = 1;

                $data['id_card'] = $result['idcard'];
                $data['sex'] = $result['sex'];
                $res = (new model())->addData($data);
                if ($res['code'] == 0) {
                    throw new \Exception($res['msg']);

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

}