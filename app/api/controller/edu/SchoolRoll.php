<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\SysSchoolRollExtend;
use app\common\model\SysSchoolRollGuardian;
use Overtrue\Pinyin\Pinyin;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use app\common\model\SysSchoolRoll as model;
use app\common\validate\comprehensive\SysSchoolRoll as validate;
use think\facade\Db;
use think\facade\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SchoolRoll extends Education
{
    /**
     * 按分页获取学籍信息
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                $where = [];
                $where[] = ['roll.deleted','=',0];
                $where[] = ['roll.registed','=',1];

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['roll.id_card|roll.current_address|roll.real_name|roll.student_code','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['roll.region_id','=', $this->result['region_id']];
                }
                if($this->request->has('class_code') && $this->result['class_code'] !== '')
                {
                    $where[] = ['roll.class_code','=', $this->result['class_code']];
                }
                if (isset($this->userInfo['relation_region']) ){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['roll.region_id', 'in', $region_ids];
                }

                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                    $school_type = Db::name('SysSchool')->where('id', $school_id)->value('school_type');
                    $where[] = ['roll.school_id', '=', $school_id];
                } else {
                    throw new \Exception('学校管理员学校ID设置错误');
                }

                $data = Db::name('SysSchoolRoll')->alias('roll')
                    ->join([
                        'deg_sys_school_roll_extend' => 'extend'
                    ], 'roll.id = extend.school_roll_id', 'LEFT')
                    ->field([
                        'roll.id',
                        'roll.region_id',
                        'roll.school_id',
                        'roll.real_name',
                        'CASE roll.sex WHEN 1 THEN "男" WHEN 2 THEN "女" ELSE "未知" END' => 'sex',
                        //'CONCAT( IF(school.school_type = 1, "一年级", "七年级"), roll.class_id, "班")' => 'class_name',
                        'CONCAT(roll.grade_code, roll.class_code)' => 'class_name',
                        'roll.birthday' => 'birthday',
                        'CASE roll.card_type WHEN 1 THEN "身份证" WHEN 2 THEN "护照" ELSE "其他" END' => 'card_type',
                        'roll.id_card' => 'id_card',
                        'CONCAT(roll.nation, " ")' => 'nation',
                        'roll.nationality' => 'nationality',
                        'IF(roll.overseas = 1, "是", "否")' => 'overseas',
                        'extend.birthplace_attr' => 'birthplace_attr',
                        'extend.residence' => 'residence',
                        'roll.current_address',
                        'roll.student_code',
                        'roll.graduation_school' => 'graduation_school',
                        'roll.graduation_school_code',
                    ])
                    ->where($where)
                    ->order('roll.id desc')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');
                $school = Cache::get('school');
                foreach ($data['data'] as $k => $v){
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }
                    $houseSchoolData = filter_value_one($school, 'id', $v['school_id']);
                    if (count($houseSchoolData) > 0){
                        $data['data'][$k]['school_name'] = $houseSchoolData['school_name'];
                        $data['data'][$k]['school_type'] = $houseSchoolData['school_type'];
                        $data['data'][$k]['school_code'] = $houseSchoolData['school_code'];
                    }
                    $data['data'][$k]['detail'] = true;
                    if($houseSchoolData['school_type'] == 2){
                        $data['data'][$k]['detail'] = false;
                        $data['data'][$k]['school_type_name'] = '初中';
                    }
                }
                $data['resources'] = $res_data;
                $data['school_type'] = $school_type;

                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 业务办理页面资源
     * @return \think\response\Json
     */
    public function resView(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getAllSelectByRoleList();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data['school_list'] = $result['school_list'];
                $data['region_list'] = $result['region_list'];

                $data['class_code_list'] = Db::name('SysSchoolRoll')->where('registed', 1)
                    ->where('deleted', 0)->group('class_code')->column('class_code');

                $result = $this->getRecruit();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data['recruit'] = $result['data'];

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
        }else{}
    }

    /**
     * 获取指定学籍信息
     * @param id 学籍ID
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['basic'] = (new model())->where('id', $this->result['id'])->where('deleted', 0)->find();
                $data['extend'] = Db::name('SysSchoolRollExtend')->where('school_roll_id', $this->result['id'])
                    ->where('deleted', 0)->find();
                $data['guardian'] = Db::name('SysSchoolRollGuardian')->where('school_roll_id', $this->result['id'])
                    ->where('deleted', 0)->select()->toArray();
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
     * 新增
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                    $school = Db::name('SysSchool')->where('id', $school_id)->find();
                } else {
                    throw new \Exception('学校管理员学校ID设置错误！');
                }
                if($school['school_type'] == 1){
                    throw new \Exception('小学类型学校不能添加！');
                }
                $data = $this->request->only([
                    'student_code',
                    'id_card',
                    'student_name',
                    'graduation_school_code',
                    'graduation_school',
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
//                if($this->result['student_code'] == '')
//                {
//                    throw new \Exception('个人标识码（学籍号）不能为空！');
//                }
//                if($this->result['student_name'] == '')
//                {
//                    throw new \Exception('学生姓名不能为空！');
//                }
//                if($this->result['graduation_school_code'] == '')
//                {
//                    throw new \Exception('毕业学校标识码不能为空！');
//                }
//                if($this->result['graduation_school'] == '')
//                {
//                    throw new \Exception('毕业学校不能为空！');
//                }
//                $where = [];
//                $where[] = ['deleted', '=', 0];
//                $where[] = ['student_code', '=', $this->result['student_code']];
//                $school_sixth = Db::name('SixthGrade')->where($where)->find();
//                if(!$school_sixth)
//                {
//                    throw new \Exception('个人标识码不存在！');
//                }
//                $where = [];
//                $where[] = ['deleted', '=', 0];
//                $where[] = ['student_code', '=', $this->result['student_code']];
//                $school_roll = Db::name('SysSchoolRoll')->where($where)->find();
//                if($school_roll)
//                {
//                    throw new \Exception('个人标识码已注册学籍！');
//                }
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['idcard', '=', strtoupper($this->result['id_card'])];
                $child = Db::name('UserChild')->where($where)->find();
                if(!$child)
                {
                    throw new \Exception('身份证号对应学生不存在！');
                }
//                $graduation_school = Db::name('SysSchool')->where('school_code', $this->result['graduation_school_code'])->find();
//                if(!$graduation_school)
//                {
//                    throw new \Exception('毕业学校标识码不存在！');
//                }
                $where = [];
                $where[] = ['child_id', '=', $child['id']];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $user_apply = Db::name('UserApply')->where($where)->find();
                if(!$user_apply) {
                    throw new \Exception('学生申请信息不存在！');
                }
                if($user_apply['result_school_id'] == 0) {
                    throw new \Exception('学生申请信息没有最终录取！');
                }
                if($user_apply['result_school_id'] != $school['id']) {
                    throw new \Exception('学生申请信息最终录取学校不是本校！');
                }
                if($user_apply['signed'] == 0) {
                    throw new \Exception('学生申请信息没有入学报到！');
                }

                $roll = [];
                $roll['real_name'] = $this->result['student_name'];
                $roll['region_id'] = $school['region_id'];
                $roll['child_id'] = $child['id'];
                $roll['school_id'] = $school_id;
                $roll['student_code'] = $this->result['student_code'];
                $roll['card_type'] = 1;
                $roll['id_card'] = strtoupper($this->result['id_card']);
                $roll['graduation_school'] = $this->result['graduation_school'];
                $roll['graduation_school_code'] = $this->result['graduation_school_code'];
                $roll['registed'] = 1;

                $result = (new model())->addData($roll);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //学籍统计
                $result = $this->getSixthGradeStatistics($school_id);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
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
     * 编辑
     * @return Json
     */
    public function actEdit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                    $school = Db::name('SysSchool')->where('id', $school_id)->find();
                } else {
                    throw new \Exception('学校管理员学校ID设置错误！');
                }
                if($school['school_type'] == 1){
                    throw new \Exception('小学类型学校不能添加！');
                }
                $data = $this->request->only([
                    'id',
                    'student_code',
                    'id_card',
                    'student_name',
                    'graduation_school_code',
                    'graduation_school',
                    'hash'
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
//                if($this->result['id'] <= 0 )
//                {
//                    throw new \Exception('学籍ID参数错误！');
//                }
//                if($this->result['student_code'] == '')
//                {
//                    throw new \Exception('个人标识码（学籍号）不能为空！');
//                }
//                if($this->result['student_name'] == '')
//                {
//                    throw new \Exception('学生姓名不能为空！');
//                }
//                if($this->result['graduation_school_code'] == '')
//                {
//                    throw new \Exception('毕业学校标识码不能为空！');
//                }
//                $where = [];
//                $where[] = ['deleted', '=', 0];
//                $where[] = ['student_code', '=', $this->result['student_code']];
//                $school_sixth = Db::name('SixthGrade')->where($where)->find();
//                if(!$school_sixth)
//                {
//                    throw new \Exception('个人标识码不存在！');
//                }
//                $where = [];
//                $where[] = ['deleted', '=', 0];
//                $where[] = ['student_code', '=', $this->result['student_code']];
//                $where[] = ['id', '<>', $this->result['id']];
//                $school_roll = Db::name('SysSchoolRoll')->where($where)->find();
//                if($school_roll)
//                {
//                    throw new \Exception('个人标识码已注册学籍！');
//                }
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['idcard', '=', strtoupper($this->result['id_card'])];
                $child = Db::name('UserChild')->where($where)->find();
                if(!$child)
                {
                    throw new \Exception('身份证号对应学生不存在！');
                }
//                $graduation_school = Db::name('SysSchool')->where('school_code', $this->result['graduation_school_code'])->find();
//                if(!$graduation_school)
//                {
//                    throw new \Exception('毕业学校标识码不存在！');
//                }
//                if($graduation_school['school_name'] != $this->result['graduation_school'])
//                {
//                    throw new \Exception('毕业学校标识码和毕业学校不匹配！');
//                }
                $where = [];
                $where[] = ['child_id', '=', $child['id']];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $user_apply = Db::name('UserApply')->where($where)->find();
                if(!$user_apply) {
                    throw new \Exception('学生申请信息不存在！');
                }
                if($user_apply['result_school_id'] == 0) {
                    throw new \Exception('学生申请信息没有最终录取！');
                }
                if($user_apply['result_school_id'] != $school['id']) {
                    throw new \Exception('学生申请信息最终录取学校不是本校！');
                }
                if($user_apply['signed'] == 0) {
                    throw new \Exception('学生申请信息没有入学报到！');
                }

                $roll = [];
                $roll['id'] = $this->result['id'];
                $roll['real_name'] = $this->result['student_name'];
                $roll['region_id'] = $school['region_id'];
                $roll['child_id'] = $child['id'];
                $roll['school_id'] = $school_id;
                $roll['student_code'] = $this->result['student_code'];
                $roll['card_type'] = 1;
                $roll['id_card'] = strtoupper($this->result['id_card']);
                $roll['graduation_school'] = $this->result['graduation_school'];
                $roll['graduation_school_code'] = $this->result['graduation_school_code'];
                $roll['registed'] = 1;

                $result = (new model())->editData($roll);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
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
                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('学校管理员学校ID设置错误！');
                }
                //  如果数据不合法，则返回
                if ($data['id'] <= 0) {
                    throw new \Exception('学籍ID参数错误！');
                }

                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = (new SysSchoolRollExtend())->editData(['deleted' => 1], ['school_roll_id' => $data['id'] ]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = (new SysSchoolRollGuardian())->editData(['deleted' => 1], ['school_roll_id' => $data['id'] ]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //学籍统计
                $result = $this->getSchoolRollStatistics($school_id);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
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

    /**
     * 导入学籍信息
     */
    public function import(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                set_time_limit(0);
                $file_size = $_FILES['file']['size'];
                if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                    throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
                }
                //限制上传表格类型
                $fileExtendName = substr(strrchr($_FILES['file']["name"], '.'), 1);

                if ( $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                    $school = Db::name('SysSchool')->where('id', $school_id)->find();
                } else {
                    throw new \Exception('学校管理员学校ID设置错误！');
                }
                $res = [];
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    if ($fileExtendName == 'xls') {
                        $objReader = IOFactory::createReader('Xls');
                    } else {
                        $objReader = IOFactory::createReader('Xlsx');
                    }

                    $filename = $_FILES['file']['tmp_name'];
                    $objPHPExcel = $objReader->load($filename);
                    $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                    $highestRow = $sheet->getHighestRow();       // 取得总行数
                    //$highestColumn = $sheet->getHighestColumn();   // 取得总列数

                    //导入模板检测
                    if($school['school_type'] == 1) {
                        $result = $this->importPrimary($objPHPExcel, $highestRow);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }
                    }else{
                        $result = $this->importMiddle($objPHPExcel, $highestRow);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }
                    }

                    //定义$data，循环表格的时候，找出已存在的学籍学生信息。
                    /*$data = [];
                    $repeat = [];

                    //循环读取excel表格，整合成数组。model
                    $hasIdCardData = Db::name('SysSchoolRoll')->where('id_card', '<>', '')
                        ->where('deleted',0)->column('school_id','id_card');
                    $hasCodeData = Db::name('SysSchoolRoll')->where('student_code', '<>', '')
                        ->where('deleted',0)->column('school_id','identification');

                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    for ($j = 2; $j <= $highestRow; $j++) {
                        $identification         = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();//学籍接续标识
                        $school_code            = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();//学校标识码
                        $real_name              = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();//姓名
                        $sex                    = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();//性别
                        $birthday               = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();//出生日期
                        $card_type              = $objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue();//身份证件类型
                        $id_card                = $objPHPExcel->getActiveSheet()->getCell("G" . $j)->getValue();//身份证件号
                        $birthplace             = $objPHPExcel->getActiveSheet()->getCell("H" . $j)->getValue();//出生地行政区划代码
                        $nation                 = $objPHPExcel->getActiveSheet()->getCell("I" . $j)->getValue();//民族
                        $nationality            = $objPHPExcel->getActiveSheet()->getCell("J" . $j)->getValue();//国籍/地区
                        $overseas               = $objPHPExcel->getActiveSheet()->getCell("K" . $j)->getValue();//港澳台侨外
                        $birthplace_attr        = $objPHPExcel->getActiveSheet()->getCell("L" . $j)->getValue();//户口性质
                        $residence              = $objPHPExcel->getActiveSheet()->getCell("M" . $j)->getValue();//户口所在地行政区划
                        $current_address        = $objPHPExcel->getActiveSheet()->getCell("N" . $j)->getValue();//现住址
                        $onlyed                 = $objPHPExcel->getActiveSheet()->getCell("O" . $j)->getValue();//是否独生子女
                        $stayed                 = $objPHPExcel->getActiveSheet()->getCell("P" . $j)->getValue();//是否留守儿童
                        $flowed                 = $objPHPExcel->getActiveSheet()->getCell("Q" . $j)->getValue();//是否进城务工人员随迁子女
                        $orphaned               = $objPHPExcel->getActiveSheet()->getCell("R" . $j)->getValue();//是否孤儿
                        $nativeplace            = $objPHPExcel->getActiveSheet()->getCell("S" . $j)->getValue();//籍贯
                        $health                 = $objPHPExcel->getActiveSheet()->getCell("T" . $j)->getValue();//健康状况
                        $politics               = $objPHPExcel->getActiveSheet()->getCell("U" . $j)->getValue();//政治面貌
                        $grade_code             = $objPHPExcel->getActiveSheet()->getCell("V" . $j)->getValue();//年级
                        $class_code             = $objPHPExcel->getActiveSheet()->getCell("W" . $j)->getValue();//班级号
                        $entrance_time          = $objPHPExcel->getActiveSheet()->getCell("X" . $j)->getValue();//入学年月
                        $entrance_type          = $objPHPExcel->getActiveSheet()->getCell("Y" . $j)->getValue();//入学方式
                        $study_method           = $objPHPExcel->getActiveSheet()->getCell("Z" . $j)->getValue();//就读方式
                        $mailing_address        = $objPHPExcel->getActiveSheet()->getCell("AA" . $j)->getValue();//通信地址
                        $home_address           = $objPHPExcel->getActiveSheet()->getCell("AB" . $j)->getValue();//家庭地址
                        $mobile                 = $objPHPExcel->getActiveSheet()->getCell("AC" . $j)->getValue();//联系电话
                        $postal_code            = $objPHPExcel->getActiveSheet()->getCell("AD" . $j)->getValue();//邮政编码
                        $preschooled            = $objPHPExcel->getActiveSheet()->getCell("AE" . $j)->getValue();//是否受过学前教育
                        $disability_type        = $objPHPExcel->getActiveSheet()->getCell("AF" . $j)->getValue();//残疾人类型
                        $purchased              = $objPHPExcel->getActiveSheet()->getCell("AG" . $j)->getValue();//是否由政府购买学位
                        $supported              = $objPHPExcel->getActiveSheet()->getCell("AH" . $j)->getValue();//是否需要申请资助
                        $subsidy                = $objPHPExcel->getActiveSheet()->getCell("AI" . $j)->getValue();//是否享受一补
                        $martyred               = $objPHPExcel->getActiveSheet()->getCell("AJ" . $j)->getValue();//是否烈士或优抚子女
                        $distance               = $objPHPExcel->getActiveSheet()->getCell("AK" . $j)->getValue();//上下学距离
                        $transportation         = $objPHPExcel->getActiveSheet()->getCell("AL" . $j)->getValue();//上下学交通方式
                        $school_bused           = $objPHPExcel->getActiveSheet()->getCell("AM" . $j)->getValue();//是否乘坐校车
                        $name_used              = $objPHPExcel->getActiveSheet()->getCell("AN" . $j)->getValue();//曾用名
                        $id_card_validity       = $objPHPExcel->getActiveSheet()->getCell("AO" . $j)->getValue();//身份证件有效期
                        $specialty              = $objPHPExcel->getActiveSheet()->getCell("AP" . $j)->getValue();//特长
                        $student_code_auxiliary = $objPHPExcel->getActiveSheet()->getCell("AQ" . $j)->getValue();//学籍辅号
                        $student_number         = $objPHPExcel->getActiveSheet()->getCell("AR" . $j)->getValue();//班内学号
                        $student_source         = $objPHPExcel->getActiveSheet()->getCell("AS" . $j)->getValue();//学生来源
                        $email                  = $objPHPExcel->getActiveSheet()->getCell("AT" . $j)->getValue();//电子信箱
                        $homepage               = $objPHPExcel->getActiveSheet()->getCell("AU" . $j)->getValue();//主页地址

                        //成员1信息
                        $one_real_name          = $objPHPExcel->getActiveSheet()->getCell("AV" . $j)->getValue();//成员1姓名
                        $one_relation           = $objPHPExcel->getActiveSheet()->getCell("AW" . $j)->getValue();//成员1关系
                        $one_relation_remark    = $objPHPExcel->getActiveSheet()->getCell("AX" . $j)->getValue();//成员1关系说明
                        $one_current_address    = $objPHPExcel->getActiveSheet()->getCell("AY" . $j)->getValue();//成员1现住址
                        $one_residence          = $objPHPExcel->getActiveSheet()->getCell("AZ" . $j)->getValue();//成员1户口所在地行政区划
                        $one_mobile             = $objPHPExcel->getActiveSheet()->getCell("BA" . $j)->getValue();//成员1联系电话
                        $one_guardian           = $objPHPExcel->getActiveSheet()->getCell("BB" . $j)->getValue();//成员1是否监护人
                        $one_card_type          = $objPHPExcel->getActiveSheet()->getCell("BC" . $j)->getValue();//成员1身份证件类型
                        $one_id_card            = $objPHPExcel->getActiveSheet()->getCell("BD" . $j)->getValue();//成员1身份证件号
                        $one_nation             = $objPHPExcel->getActiveSheet()->getCell("BE" . $j)->getValue();//成员1民族
                        $one_work_address       = $objPHPExcel->getActiveSheet()->getCell("BF" . $j)->getValue();//成员1工作单位
                        $one_duties             = $objPHPExcel->getActiveSheet()->getCell("BG" . $j)->getValue();//成员1职务

                        //成员2信息
                        $two_real_name          = $objPHPExcel->getActiveSheet()->getCell("BH" . $j)->getValue();//成员2姓名
                        $two_relation           = $objPHPExcel->getActiveSheet()->getCell("BI" . $j)->getValue();//成员2关系
                        $two_relation_remark    = $objPHPExcel->getActiveSheet()->getCell("BJ" . $j)->getValue();//成员2关系说明
                        $two_current_address    = $objPHPExcel->getActiveSheet()->getCell("BK" . $j)->getValue();//成员2现住址
                        $two_residence          = $objPHPExcel->getActiveSheet()->getCell("BL" . $j)->getValue();//成员2户口所在地行政区划
                        $two_mobile             = $objPHPExcel->getActiveSheet()->getCell("BM" . $j)->getValue();//成员2联系电话
                        $two_guardian           = $objPHPExcel->getActiveSheet()->getCell("BN" . $j)->getValue();//成员2是否监护人
                        $two_card_type          = $objPHPExcel->getActiveSheet()->getCell("BO" . $j)->getValue();//成员2身份证件类型
                        $two_id_card            = $objPHPExcel->getActiveSheet()->getCell("BP" . $j)->getValue();//成员2身份证件号
                        $two_nation             = $objPHPExcel->getActiveSheet()->getCell("BQ" . $j)->getValue();//成员2民族
                        $two_work_address       = $objPHPExcel->getActiveSheet()->getCell("BR" . $j)->getValue();//成员2工作单位
                        $two_duties             = $objPHPExcel->getActiveSheet()->getCell("BS" . $j)->getValue();//成员2职务

                        $tmp[$j - 2] = [
                            'identification'            => trim($identification,"\0\t\n\x0B\r "),
                            'school_code'               => trim($school_code,"\0\t\n\x0B\r "),
                            'real_name'                 => trim($real_name,"\0\t\n\x0B\r "),
                            'sex'                       => trim($sex,"\0\t\n\x0B\r "),
                            'birthday'                  => trim($birthday,"\0\t\n\x0B\r "),
                            'card_type'                 => trim($card_type,"\0\t\n\x0B\r "),
                            'id_card'                   => strtoupper(ltrim($id_card,"\0\t\n\x0B\r ")),
                            'id_card_original'          => $id_card,
                            'birthplace'                => trim($birthplace,"\0\t\n\x0B\r "),
                            'nation'                    => trim($nation,"\0\t\n\x0B\r "),
                            'nationality'               => trim($nationality,"\0\t\n\x0B\r "),
                            'overseas'                  => trim($overseas,"\0\t\n\x0B\r "),
                            'birthplace_attr'           => trim($birthplace_attr,"\0\t\n\x0B\r "),
                            'residence'                 => trim($residence,"\0\t\n\x0B\r "),
                            'current_address'           => trim($current_address,"\0\t\n\x0B\r "),
                            'onlyed'                    => trim($onlyed,"\0\t\n\x0B\r "),
                            'stayed'                    => trim($stayed,"\0\t\n\x0B\r "),
                            'flowed'                    => trim($flowed,"\0\t\n\x0B\r "),
                            'orphaned'                  => trim($orphaned,"\0\t\n\x0B\r "),
                            'nativeplace'               => trim($nativeplace,"\0\t\n\x0B\r "),
                            'health'                    => trim($health,"\0\t\n\x0B\r "),
                            'politics'                  => trim($politics,"\0\t\n\x0B\r "),
                            'grade_code'                => trim($grade_code,"\0\t\n\x0B\r "),
                            'class_code'                => trim($class_code,"\0\t\n\x0B\r "),
                            'entrance_time'             => trim($entrance_time,"\0\t\n\x0B\r "),
                            'entrance_type'             => trim($entrance_type,"\0\t\n\x0B\r "),
                            'study_method'              => trim($study_method,"\0\t\n\x0B\r "),
                            'mailing_address'           => trim($mailing_address,"\0\t\n\x0B\r "),
                            'home_address'              => trim($home_address,"\0\t\n\x0B\r "),
                            'mobile'                    => trim($mobile,"\0\t\n\x0B\r "),
                            'postal_code'               => trim($postal_code,"\0\t\n\x0B\r "),
                            'preschooled'               => trim($preschooled,"\0\t\n\x0B\r "),
                            'disability_type'           => trim($disability_type,"\0\t\n\x0B\r "),
                            'purchased'                 => trim($purchased,"\0\t\n\x0B\r "),
                            'supported'                 => trim($supported,"\0\t\n\x0B\r "),
                            'subsidy'                   => trim($subsidy,"\0\t\n\x0B\r "),
                            'martyred'                  => trim($martyred,"\0\t\n\x0B\r "),
                            'distance'                  => trim($distance,"\0\t\n\x0B\r "),
                            'transportation'            => trim($transportation,"\0\t\n\x0B\r "),
                            'school_bused'              => trim($school_bused,"\0\t\n\x0B\r "),
                            'name_used'                 => trim($name_used,"\0\t\n\x0B\r "),
                            'id_card_validity'          => trim($id_card_validity,"\0\t\n\x0B\r "),
                            'specialty'                 => trim($specialty,"\0\t\n\x0B\r "),
                            'student_code_auxiliary'    => trim($student_code_auxiliary,"\0\t\n\x0B\r "),
                            'student_number'            => trim($student_number,"\0\t\n\x0B\r "),
                            'student_source'            => trim($student_source,"\0\t\n\x0B\r "),
                            'email'                     => trim($email,"\0\t\n\x0B\r "),
                            'homepage'                  => trim($homepage,"\0\t\n\x0B\r "),

                            'one_real_name'             => trim($one_real_name,"\0\t\n\x0B\r "),
                            'one_relation'              => trim($one_relation,"\0\t\n\x0B\r "),
                            'one_relation_remark'       => trim($one_relation_remark,"\0\t\n\x0B\r "),
                            'one_current_address'       => trim($one_current_address,"\0\t\n\x0B\r "),
                            'one_residence'             => trim($one_residence,"\0\t\n\x0B\r "),
                            'one_mobile'                => trim($one_mobile,"\0\t\n\x0B\r "),
                            'one_guardian'              => trim($one_guardian,"\0\t\n\x0B\r "),
                            'one_card_type'             => trim($one_card_type,"\0\t\n\x0B\r "),
                            'one_id_card'               => strtoupper(trim($one_id_card,"\0\t\n\x0B\r ")),
                            'one_id_card_original'      => $one_id_card,
                            'one_nation'                => trim($one_nation,"\0\t\n\x0B\r "),
                            'one_work_address'          => trim($one_work_address,"\0\t\n\x0B\r "),
                            'one_duties'                => trim($one_duties,"\0\t\n\x0B\r "),

                            'two_real_name'             => trim($two_real_name,"\0\t\n\x0B\r "),
                            'two_relation'              => trim($two_relation,"\0\t\n\x0B\r "),
                            'two_relation_remark'       => trim($two_relation_remark,"\0\t\n\x0B\r "),
                            'two_current_address'       => trim($two_current_address,"\0\t\n\x0B\r "),
                            'two_residence'             => trim($two_residence,"\0\t\n\x0B\r "),
                            'two_mobile'                => trim($two_mobile,"\0\t\n\x0B\r "),
                            'two_guardian'              => trim($two_guardian,"\0\t\n\x0B\r "),
                            'two_card_type'             => trim($two_card_type,"\0\t\n\x0B\r "),
                            'two_id_card'               => strtoupper(trim($two_id_card,"\0\t\n\x0B\r ")),
                            'two_id_card_original'      => $two_id_card,
                            'two_nation'                => trim($two_nation,"\0\t\n\x0B\r "),
                            'two_work_address'          => trim($two_work_address,"\0\t\n\x0B\r "),
                            'two_duties'                => trim($two_duties,"\0\t\n\x0B\r "),
                        ];
                        array_push($data, $tmp[$j - 2]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的身份证号、学籍号
                    $id_card_data = array_column($data,'id_card');
                    $id_card_data = array_filter($id_card_data);
                    $repeat_data_card = array_count_values($id_card_data);

                    $identification_data = array_column($data,'identification');
                    $identification_data = array_filter($identification_data);
                    $repeat_data_identification = array_count_values($identification_data);

                    $successNum = 0;
                    foreach ($data as $key=>$item) {
                        $row = $key + 2;
                        //非空判断
                        if(true) {
                            if ($item['identification'] == '') {
                                $error[] = '第' . $row . '行【学籍接续标识】为空';
                                continue;
                            }
                            if ($item['school_code'] == '') {
                                $error[] = '第' . $row . '行【学校标识码】为空';
                                continue;
                            }
                            if ($item['real_name'] == '') {
                                $error[] = '第' . $row . '行【姓名】为空';
                                continue;
                            }
                            if ($item['sex'] == '') {
                                $error[] = '第' . $row . '行【性别】为空';
                                continue;
                            }
                            if ($item['birthday'] == '') {
                                $error[] = '第' . $row . '行【出生日期】为空';
                                continue;
                            }
                            if ($item['card_type'] == '') {
                                $error[] = '第' . $row . '行【身份证件类型】为空';
                                continue;
                            }
                            if ($item['id_card'] == '') {
                                $error[] = '第' . $row . '行【身份证件号】为空';
                                continue;
                            }
                            if ($item['birthplace'] == '') {
                                $error[] = '第' . $row . '行【出生地行政区划代码】为空';
                                continue;
                            }
                            if ($item['nation'] == '') {
                                $error[] = '第' . $row . '行【民族】为空';
                                continue;
                            }
                            if ($item['nationality'] == '') {
                                $error[] = '第' . $row . '行【国籍/地区】为空';
                                continue;
                            }
                            if ($item['overseas'] == '') {
                                $error[] = '第' . $row . '行【港澳台侨外】为空';
                                continue;
                            }
                            if ($item['birthplace_attr'] == '') {
                                $error[] = '第' . $row . '行【户口性质】为空';
                                continue;
                            }
                            if ($item['residence'] == '') {
                                $error[] = '第' . $row . '行【户口所在地行政区划】为空';
                                continue;
                            }
                            if ($item['current_address'] == '') {
                                $error[] = '第' . $row . '行【现住址】为空';
                                continue;
                            }
                            if ($item['onlyed'] == '') {
                                $error[] = '第' . $row . '行【是否独生子女】为空';
                                continue;
                            }
                            if ($item['stayed'] == '') {
                                $error[] = '第' . $row . '行【是否留守儿童】为空';
                                continue;
                            }
                            if ($item['flowed'] == '') {
                                $error[] = '第' . $row . '行【是否进城务工人员随迁子女】为空';
                                continue;
                            }
                            if ($item['orphaned'] == '') {
                                $error[] = '第' . $row . '行【是否孤儿】为空';
                                continue;
                            }
                            if ($item['nativeplace'] == '') {
                                $error[] = '第' . $row . '行【籍贯】为空';
                                continue;
                            }
                            if ($item['health'] == '') {
                                $error[] = '第' . $row . '行【健康状况】为空';
                                continue;
                            }
                            if ($item['politics'] == '') {
                                $error[] = '第' . $row . '行【政治面貌】为空';
                                continue;
                            }
                            if ($item['grade_code'] == '') {
                                $error[] = '第' . $row . '行【年级】为空';
                                continue;
                            }
                            if ($item['class_code'] == '') {
                                $error[] = '第' . $row . '行【班级号】为空';
                                continue;
                            }
                            if ($item['entrance_time'] == '') {
                                $error[] = '第' . $row . '行【入学年月】为空';
                                continue;
                            }
                            if ($item['entrance_type'] == '') {
                                $error[] = '第' . $row . '行【入学方式】为空';
                                continue;
                            }
                            if ($item['study_method'] == '') {
                                $error[] = '第' . $row . '行【就读方式】为空';
                                continue;
                            }
                            if ($item['mailing_address'] == '') {
                                $error[] = '第' . $row . '行【通信地址】为空';
                                continue;
                            }
                            if ($item['home_address'] == '') {
                                $error[] = '第' . $row . '行【家庭地址】为空';
                                continue;
                            }
                            if ($item['mobile'] == '') {
                                $error[] = '第' . $row . '行【联系电话】为空';
                                continue;
                            }
                            if ($item['postal_code'] == '') {
                                $error[] = '第' . $row . '行【邮政编码】为空';
                                continue;
                            }
                            if ($item['preschooled'] == '') {
                                $error[] = '第' . $row . '行【是否受过学前教育】为空';
                                continue;
                            }
                            if ($item['supported'] == '') {
                                $error[] = '第' . $row . '行【是否需要申请资助】为空';
                                continue;
                            }
                            if ($item['subsidy'] == '') {
                                $error[] = '第' . $row . '行【是否享受一补】为空';
                                continue;
                            }
                            if ($item['martyred'] == '') {
                                $error[] = '第' . $row . '行【是否烈士或优抚子女】为空';
                                continue;
                            }
                            if ($item['martyred'] == '') {
                                $error[] = '第' . $row . '行【是否烈士或优抚子女】为空';
                                continue;
                            }

                            if ($item['one_real_name'] == '') {
                                $error[] = '第' . $row . '行【成员1姓名】为空';
                                continue;
                            }
                            if ($item['one_relation'] == '') {
                                $error[] = '第' . $row . '行【成员1关系】为空';
                                continue;
                            }
                            if ($item['one_current_address'] == '') {
                                $error[] = '第' . $row . '行【成员1现住址】为空';
                                continue;
                            }
                            if ($item['one_residence'] == '') {
                                $error[] = '第' . $row . '行【成员1户口所在地行政区划】为空';
                                continue;
                            }
                            if ($item['one_mobile'] == '') {
                                $error[] = '第' . $row . '行【成员1联系电话】为空';
                                continue;
                            }
                            if ($item['one_guardian'] == '') {
                                $error[] = '第' . $row . '行【成员1是否监护人】为空';
                                continue;
                            }
                            if ($item['one_card_type'] == '') {
                                $error[] = '第' . $row . '行【成员1身份证件类型】为空';
                                continue;
                            }
                            if ($item['one_id_card'] == '') {
                                $error[] = '第' . $row . '行【成员1身份证件号】为空';
                                continue;
                            }

                            if ($item['two_real_name'] == '') {
                                $error[] = '第' . $row . '行【成员2姓名】为空';
                                continue;
                            }
                            if ($item['two_relation'] == '') {
                                $error[] = '第' . $row . '行【成员2关系】为空';
                                continue;
                            }
                            if ($item['two_current_address'] == '') {
                                $error[] = '第' . $row . '行【成员2现住址】为空';
                                continue;
                            }
                            if ($item['two_residence'] == '') {
                                $error[] = '第' . $row . '行【成员2户口所在地行政区划】为空';
                                continue;
                            }
                            if ($item['two_mobile'] == '') {
                                $error[] = '第' . $row . '行【成员2联系电话】为空';
                                continue;
                            }
                            if ($item['two_guardian'] == '') {
                                $error[] = '第' . $row . '行【成员2是否监护人】为空';
                                continue;
                            }
                            if ($item['two_card_type'] == '') {
                                $error[] = '第' . $row . '行【成员2身份证件类型】为空';
                                continue;
                            }
                            if ($item['two_id_card'] == '') {
                                $error[] = '第' . $row . '行【成员2身份证件号】为空';
                                continue;
                            }
                        }

                        $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                        if (preg_match($preg,$item['real_name']) == 0){
                            $error[] = '第' . $row . '行【姓名】只能为汉字';
                            continue;
                        }
                        if (preg_match($preg,$item['one_real_name']) == 0){
                            $error[] = '第' . $row . '行【成员1姓名】只能为汉字';
                            continue;
                        }
                        if (preg_match($preg,$item['two_real_name']) == 0){
                            $error[] = '第' . $row . '行【成员2姓名】只能为汉字';
                            continue;
                        }

                        $birthday_card = substr($item['id_card'],6,8);
                        if ($item['birthday'] != $birthday_card) {
                            $error[] = '第' . $row . '行出生日期和身份证不相符';
                            continue;
                        }

                        $birthplace_card = substr($item['id_card'],0,6);
                        if ($item['birthplace'] != $birthplace_card) {
                            $error[] = '第' . $row . '行出生地行政区划代码和身份证不相符';
                            continue;
                        }
                        if ($item['residence'] != $birthplace_card) {
                            $error[] = '第' . $row . '行户口所在地行政区划和身份证不相符';
                            continue;
                        }

                        $school = Db::name('sys_school')->where([['school_code', '=', $item['school_code']],
                            ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行学校标识码为【' . $item['school_code'] . '】的学校不存在';
                            continue;
                        }
                        if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                            $error[] = '第' . $row . '行学校标识码为【' . $item['school_code'] . '】无权限管理';
                            continue;
                        }

                        $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                        if (preg_match($preg,$item['id_card']) == 0){
                            $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】格式不正确';
                            continue;
                        }
                        if($repeat_data_card[$item['id_card']] > 1) {
                            $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】重复';
                            continue;
                        }
                        if (preg_match($preg,$item['one_id_card']) == 0){
                            $error[] = '第' . $row . '行【成员1身份证件号' . $item['one_id_card_original'] . '】格式不正确';
                            continue;
                        }
                        if (preg_match($preg,$item['two_id_card']) == 0){
                            $error[] = '第' . $row . '行【成员2身份证件号' . $item['two_id_card_original'] . '】格式不正确';
                            continue;
                        }
                        if($repeat_data_identification[$item['identification']] > 1) {
                            $error[] = '第' . $row . '行【学籍接续标识' . $item['identification'] . '】重复';
                            continue;
                        }

                        if($item['sex'] == '男'){
                            $sex = 1;
                        }elseif ($item['sex'] == '女'){
                            $sex = 2;
                        }else{
                            $sex = 3;
                        }
                        if($item['card_type'] == '居民身份证'){
                            $card_type = 1;
                        }elseif ($item['card_type'] == '护照'){
                            $card_type = 2;
                        }else{
                            $card_type = 3;
                        }
                        if($item['one_card_type'] == '居民身份证'){
                            $one_card_type = 1;
                        }elseif ($item['one_card_type'] == '护照'){
                            $one_card_type = 2;
                        }else{
                            $one_card_type = 3;
                        }
                        if($item['two_card_type'] == '居民身份证'){
                            $two_card_type = 1;
                        }elseif ($item['card_type'] == '护照'){
                            $two_card_type = 2;
                        }else{
                            $two_card_type = 3;
                        }
                        $pinyin = new Pinyin();
                        $name_pinyin = $pinyin->abbr($item['real_name']);

                        $roll = [
                            'region_id' => $school['region_id'],
                            'school_id' => $school['id'],
                            'real_name' => $item['real_name'],
                            'sex' => $sex,
                            'birthday' => $item['birthday'],
                            'birthplace' => $item['birthplace'],
                            'nativeplace' => $item['nativeplace'],
                            'nation' => $item['nation'],
                            'nationality' => $item['nationality'],
                            'card_type' => $card_type,
                            'overseas' => $item['overseas'] == '是' ? 1 : 0,
                            'politics' => $item['politics'],
                            'health' => $item['health'],
                            'student_code_auxiliary' => $item['student_code_auxiliary'],
                            'student_number' => $item['student_number'],
                            'grade_code' => $item['grade_code'],
                            'class_code' => $item['class_code'],
                            'entrance_time' => $item['entrance_time'],
                            'entrance_type' => $item['entrance_type'],
                            'study_method' => $item['study_method'],
                            'student_source' => $item['student_source'],
                            'current_address' => $item['current_address'],
                            'mailing_address' => $item['mailing_address'],
                            'home_address' => $item['home_address'],
                            'mobile' => $item['mobile'],
                            'postal_code' => $item['postal_code'],
                            'email' => $item['email'],
                            'homepage' => $item['homepage'],
                            'registed' => 1,
                        ];

                        $extend = [
                            'name_pinyin' => $name_pinyin,
                            'name_used' => $item['name_used'],
                            'id_card_validity' => $item['id_card_validity'],
                            'residence' => $item['residence'],
                            'birthplace_attr' => $item['birthplace_attr'],
                            'specialty' => $item['specialty'],
                            'onlyed' => $item['specialty'] == '是' ? 1 : 0,
                            'preschooled' => $item['preschooled'] == '是' ? 1 : 0,
                            'stayed' => $item['stayed'] == '是' ? 1 : 0,
                            'flowed' => $item['flowed'] == '是' ? 1 : 0,
                            'orphaned' => $item['orphaned'] == '是' ? 1 : 0,
                            'martyred' => $item['martyred'] == '是' ? 1 : 0,
                            //'regular_class' => $item['regular_class'] == '是' ? 1 : 0,//随班就读
                            'disability_type' => $item['disability_type'],
                            'purchased' => $item['purchased'] == '是' ? 1 : 0,
                            'supported' => $item['supported'] == '是' ? 1 : 0,
                            'subsidy' => $item['subsidy'] == '是' ? 1 : 0,
                            'distance' => $item['distance'],
                            'transportation' => $item['transportation'],
                            'school_bused' => $item['school_bused'],
                        ];

                        $one_guardian = [
                            'real_name' => $item['one_real_name'],
                            'relation' => $item['one_relation'],
                            'relation_remark' => $item['one_relation_remark'],
                            'nation' => $item['one_nation'],
                            'work_address' => $item['one_work_address'],
                            'current_address' => $item['one_current_address'],
                            'residence' => $item['one_residence'],
                            'mobile' => $item['one_mobile'],
                            'guardian' => $item['one_guardian'] == '是' ? 1 : 0,
                            'card_type' => $one_card_type,
                            'id_card' => $item['one_id_card'],
                            'duties' => $item['one_duties'],
                        ];

                        $two_guardian = [
                            'real_name' => $item['two_real_name'],
                            'relation' => $item['two_relation'],
                            'relation_remark' => $item['two_relation_remark'],
                            'nation' => $item['two_nation'],
                            'work_address' => $item['two_work_address'],
                            'current_address' => $item['two_current_address'],
                            'residence' => $item['two_residence'],
                            'mobile' => $item['two_mobile'],
                            'guardian' => $item['two_guardian'] == '是' ? 1 : 0,
                            'card_type' => $two_card_type,
                            'id_card' => $item['two_id_card'],
                            'duties' => $item['two_duties'],
                        ];


                        //身份证件号已存在 更新覆盖数据
                        if( in_array($item['id_card'], array_keys($hasIdCardData)) ){
                            if(in_array($hasIdCardData[$item['id_card']], $school_res['school_ids'])){
                                $roll['identification'] = $item['identification'];
                                $model = new model();
                                $result = $model->editData($roll, ['id_card'=>$item['id_card']]);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $result = (new SysSchoolRollExtend())->editData($extend, ['school_roll_id' => $model->id ]);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $result = (new SysSchoolRollGuardian())->editData($one_guardian, ['school_roll_id' => $model->id, 'id_card' => $item['one_id_card'] ]);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $result = (new SysSchoolRollGuardian())->editData($two_guardian, ['school_roll_id' => $model->id, 'id_card' => $item['two_id_card'] ]);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                            }else{
                                $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】已在其他学校导入';
                            }
                            continue;
                        }

                        //学籍接续标识已存在 更新覆盖数据
                        if( in_array($item['identification'], array_keys($hasCodeData)) ){
                            if(in_array($hasCodeData[$item['identification']], $school_res['school_ids'])){
                                $roll['id_card'] = $item['id_card'];
                                $model = new model();
                                $result = $model->editData($roll, ['identification'=>$item['identification']]);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $result = (new SysSchoolRollExtend())->editData($extend, ['school_roll_id' => $model->id ]);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $result = (new SysSchoolRollGuardian())->editData($one_guardian, ['school_roll_id' => $model->id, 'id_card' => $item['one_id_card'] ]);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $result = (new SysSchoolRollGuardian())->editData($two_guardian, ['school_roll_id' => $model->id, 'id_card' => $item['two_id_card'] ]);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                            }else{
                                $error[] = '第' . $row . '行【学籍接续标识' . $item['identification'] . '】已在其他学校导入';
                            }
                            continue;
                        }

                        $roll['id_card'] = $item['id_card'];
                        $roll['identification'] = $item['identification'];
                        //学籍基本信息
                        $result = (new model())->addData($roll, 1);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        $school_roll_id = $result['insert_id'];

                        $extend['school_roll_id'] = $school_roll_id;
                        //学籍扩展信息
                        $result = (new SysSchoolRollExtend())->addData($extend);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        //学籍家庭成员1信息
                        $one_guardian['school_roll_id'] = $school_roll_id;
                        $result = (new SysSchoolRollGuardian())->addData($one_guardian);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        //学籍家庭成员2信息
                        $two_guardian['school_roll_id'] = $school_roll_id;
                        $result = (new SysSchoolRollGuardian())->addData($two_guardian);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }

                        //学籍统计
                        $result = $this->getSchoolRollStatistics($school['id']);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }

                        $successNum++;
                    }
                    $msg['success_num'] = $successNum;
                    $msg['repeat_num'] = count($repeat);

                    Cache::set('repeat', $repeat);
                    Cache::set('importError_school_roll_'.$this->userInfo['manage_id'], $error);*/

                    $res = $result;
                }
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

    private function importPrimary($objPHPExcel, $highestRow): array
    {
        try {
            //导入模板检测
            $check_res = $this->checkPrimaryTemplate($objPHPExcel);
            if ($check_res['code'] == 0) {
                throw new \Exception($check_res['msg']);
            }

            //定义$data，循环表格的时候，找出已存在的学籍学生信息。
            $data = [];
            $repeat = [];

            //循环读取excel表格，整合成数组。model
            $hasIdCardData = Db::name('SysSchoolRoll')->where('id_card', '<>', '')
                ->where('deleted',0)->column('school_id','id_card');
            $hasCodeData = Db::name('SysSchoolRoll')->where('student_code', '<>', '')
                ->where('deleted',0)->column('school_id','identification');

            //获取角色所管理的学校ID
            $school_res = $this->getSchoolIdsByRole();
            if($school_res['code'] == 0){
                throw new \Exception($school_res['msg']);
            }

            for ($j = 2; $j <= $highestRow; $j++) {
                $identification         = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();//学籍接续标识
                $school_code            = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();//学校标识码
                $real_name              = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();//姓名
                $sex                    = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();//性别
                $birthday               = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();//出生日期
                $card_type              = $objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue();//身份证件类型
                $id_card                = $objPHPExcel->getActiveSheet()->getCell("G" . $j)->getValue();//身份证件号
                $birthplace             = $objPHPExcel->getActiveSheet()->getCell("H" . $j)->getValue();//出生地行政区划代码
                $nation                 = $objPHPExcel->getActiveSheet()->getCell("I" . $j)->getValue();//民族
                $nationality            = $objPHPExcel->getActiveSheet()->getCell("J" . $j)->getValue();//国籍/地区
                $overseas               = $objPHPExcel->getActiveSheet()->getCell("K" . $j)->getValue();//港澳台侨外
                $birthplace_attr        = $objPHPExcel->getActiveSheet()->getCell("L" . $j)->getValue();//户口性质
                $residence              = $objPHPExcel->getActiveSheet()->getCell("M" . $j)->getValue();//户口所在地行政区划
                $current_address        = $objPHPExcel->getActiveSheet()->getCell("N" . $j)->getValue();//现住址
                $onlyed                 = $objPHPExcel->getActiveSheet()->getCell("O" . $j)->getValue();//是否独生子女
                $stayed                 = $objPHPExcel->getActiveSheet()->getCell("P" . $j)->getValue();//是否留守儿童
                $flowed                 = $objPHPExcel->getActiveSheet()->getCell("Q" . $j)->getValue();//是否进城务工人员随迁子女
                $orphaned               = $objPHPExcel->getActiveSheet()->getCell("R" . $j)->getValue();//是否孤儿
                $nativeplace            = $objPHPExcel->getActiveSheet()->getCell("S" . $j)->getValue();//籍贯
                $health                 = $objPHPExcel->getActiveSheet()->getCell("T" . $j)->getValue();//健康状况
                $politics               = $objPHPExcel->getActiveSheet()->getCell("U" . $j)->getValue();//政治面貌
                $grade_code             = $objPHPExcel->getActiveSheet()->getCell("V" . $j)->getValue();//年级
                $class_code             = $objPHPExcel->getActiveSheet()->getCell("W" . $j)->getValue();//班级号
                $entrance_time          = $objPHPExcel->getActiveSheet()->getCell("X" . $j)->getValue();//入学年月
                $entrance_type          = $objPHPExcel->getActiveSheet()->getCell("Y" . $j)->getValue();//入学方式
                $study_method           = $objPHPExcel->getActiveSheet()->getCell("Z" . $j)->getValue();//就读方式
                $mailing_address        = $objPHPExcel->getActiveSheet()->getCell("AA" . $j)->getValue();//通信地址
                $home_address           = $objPHPExcel->getActiveSheet()->getCell("AB" . $j)->getValue();//家庭地址
                $mobile                 = $objPHPExcel->getActiveSheet()->getCell("AC" . $j)->getValue();//联系电话
                $postal_code            = $objPHPExcel->getActiveSheet()->getCell("AD" . $j)->getValue();//邮政编码
                $preschooled            = $objPHPExcel->getActiveSheet()->getCell("AE" . $j)->getValue();//是否受过学前教育
                $disability_type        = $objPHPExcel->getActiveSheet()->getCell("AF" . $j)->getValue();//残疾人类型
                $purchased              = $objPHPExcel->getActiveSheet()->getCell("AG" . $j)->getValue();//是否由政府购买学位
                $supported              = $objPHPExcel->getActiveSheet()->getCell("AH" . $j)->getValue();//是否需要申请资助
                $subsidy                = $objPHPExcel->getActiveSheet()->getCell("AI" . $j)->getValue();//是否享受一补
                $martyred               = $objPHPExcel->getActiveSheet()->getCell("AJ" . $j)->getValue();//是否烈士或优抚子女
                $distance               = $objPHPExcel->getActiveSheet()->getCell("AK" . $j)->getValue();//上下学距离
                $transportation         = $objPHPExcel->getActiveSheet()->getCell("AL" . $j)->getValue();//上下学交通方式
                $school_bused           = $objPHPExcel->getActiveSheet()->getCell("AM" . $j)->getValue();//是否乘坐校车
                $name_used              = $objPHPExcel->getActiveSheet()->getCell("AN" . $j)->getValue();//曾用名
                $id_card_validity       = $objPHPExcel->getActiveSheet()->getCell("AO" . $j)->getValue();//身份证件有效期
                $specialty              = $objPHPExcel->getActiveSheet()->getCell("AP" . $j)->getValue();//特长
                $student_code_auxiliary = $objPHPExcel->getActiveSheet()->getCell("AQ" . $j)->getValue();//学籍辅号
                $student_number         = $objPHPExcel->getActiveSheet()->getCell("AR" . $j)->getValue();//班内学号
                $student_source         = $objPHPExcel->getActiveSheet()->getCell("AS" . $j)->getValue();//学生来源
                $email                  = $objPHPExcel->getActiveSheet()->getCell("AT" . $j)->getValue();//电子信箱
                $homepage               = $objPHPExcel->getActiveSheet()->getCell("AU" . $j)->getValue();//主页地址

                //成员1信息
                $one_real_name          = $objPHPExcel->getActiveSheet()->getCell("AV" . $j)->getValue();//成员1姓名
                $one_relation           = $objPHPExcel->getActiveSheet()->getCell("AW" . $j)->getValue();//成员1关系
                $one_relation_remark    = $objPHPExcel->getActiveSheet()->getCell("AX" . $j)->getValue();//成员1关系说明
                $one_current_address    = $objPHPExcel->getActiveSheet()->getCell("AY" . $j)->getValue();//成员1现住址
                $one_residence          = $objPHPExcel->getActiveSheet()->getCell("AZ" . $j)->getValue();//成员1户口所在地行政区划
                $one_mobile             = $objPHPExcel->getActiveSheet()->getCell("BA" . $j)->getValue();//成员1联系电话
                $one_guardian           = $objPHPExcel->getActiveSheet()->getCell("BB" . $j)->getValue();//成员1是否监护人
                $one_card_type          = $objPHPExcel->getActiveSheet()->getCell("BC" . $j)->getValue();//成员1身份证件类型
                $one_id_card            = $objPHPExcel->getActiveSheet()->getCell("BD" . $j)->getValue();//成员1身份证件号
                $one_nation             = $objPHPExcel->getActiveSheet()->getCell("BE" . $j)->getValue();//成员1民族
                $one_work_address       = $objPHPExcel->getActiveSheet()->getCell("BF" . $j)->getValue();//成员1工作单位
                $one_duties             = $objPHPExcel->getActiveSheet()->getCell("BG" . $j)->getValue();//成员1职务

                //成员2信息
                $two_real_name          = $objPHPExcel->getActiveSheet()->getCell("BH" . $j)->getValue();//成员2姓名
                $two_relation           = $objPHPExcel->getActiveSheet()->getCell("BI" . $j)->getValue();//成员2关系
                $two_relation_remark    = $objPHPExcel->getActiveSheet()->getCell("BJ" . $j)->getValue();//成员2关系说明
                $two_current_address    = $objPHPExcel->getActiveSheet()->getCell("BK" . $j)->getValue();//成员2现住址
                $two_residence          = $objPHPExcel->getActiveSheet()->getCell("BL" . $j)->getValue();//成员2户口所在地行政区划
                $two_mobile             = $objPHPExcel->getActiveSheet()->getCell("BM" . $j)->getValue();//成员2联系电话
                $two_guardian           = $objPHPExcel->getActiveSheet()->getCell("BN" . $j)->getValue();//成员2是否监护人
                $two_card_type          = $objPHPExcel->getActiveSheet()->getCell("BO" . $j)->getValue();//成员2身份证件类型
                $two_id_card            = $objPHPExcel->getActiveSheet()->getCell("BP" . $j)->getValue();//成员2身份证件号
                $two_nation             = $objPHPExcel->getActiveSheet()->getCell("BQ" . $j)->getValue();//成员2民族
                $two_work_address       = $objPHPExcel->getActiveSheet()->getCell("BR" . $j)->getValue();//成员2工作单位
                $two_duties             = $objPHPExcel->getActiveSheet()->getCell("BS" . $j)->getValue();//成员2职务

                $tmp[$j - 2] = [
                    'identification'            => trim($identification,"\0\t\n\x0B\r "),
                    'school_code'               => trim($school_code,"\0\t\n\x0B\r "),
                    'real_name'                 => trim($real_name,"\0\t\n\x0B\r "),
                    'sex'                       => trim($sex,"\0\t\n\x0B\r "),
                    'birthday'                  => trim($birthday,"\0\t\n\x0B\r "),
                    'card_type'                 => trim($card_type,"\0\t\n\x0B\r "),
                    'id_card'                   => strtoupper(trim($id_card,"\0\t\n\x0B\r ")),
                    'id_card_original'          => $id_card,
                    'birthplace'                => trim($birthplace,"\0\t\n\x0B\r "),
                    'nation'                    => trim($nation,"\0\t\n\x0B\r "),
                    'nationality'               => trim($nationality,"\0\t\n\x0B\r "),
                    'overseas'                  => trim($overseas,"\0\t\n\x0B\r "),
                    'birthplace_attr'           => trim($birthplace_attr,"\0\t\n\x0B\r "),
                    'residence'                 => trim($residence,"\0\t\n\x0B\r "),
                    'current_address'           => trim($current_address,"\0\t\n\x0B\r "),
                    'onlyed'                    => trim($onlyed,"\0\t\n\x0B\r "),
                    'stayed'                    => trim($stayed,"\0\t\n\x0B\r "),
                    'flowed'                    => trim($flowed,"\0\t\n\x0B\r "),
                    'orphaned'                  => trim($orphaned,"\0\t\n\x0B\r "),
                    'nativeplace'               => trim($nativeplace,"\0\t\n\x0B\r "),
                    'health'                    => trim($health,"\0\t\n\x0B\r "),
                    'politics'                  => trim($politics,"\0\t\n\x0B\r "),
                    'grade_code'                => trim($grade_code,"\0\t\n\x0B\r "),
                    'class_code'                => trim($class_code,"\0\t\n\x0B\r "),
                    'entrance_time'             => trim($entrance_time,"\0\t\n\x0B\r "),
                    'entrance_type'             => trim($entrance_type,"\0\t\n\x0B\r "),
                    'study_method'              => trim($study_method,"\0\t\n\x0B\r "),
                    'mailing_address'           => trim($mailing_address,"\0\t\n\x0B\r "),
                    'home_address'              => trim($home_address,"\0\t\n\x0B\r "),
                    'mobile'                    => trim($mobile,"\0\t\n\x0B\r "),
                    'postal_code'               => trim($postal_code,"\0\t\n\x0B\r "),
                    'preschooled'               => trim($preschooled,"\0\t\n\x0B\r "),
                    'disability_type'           => trim($disability_type,"\0\t\n\x0B\r "),
                    'purchased'                 => trim($purchased,"\0\t\n\x0B\r "),
                    'supported'                 => trim($supported,"\0\t\n\x0B\r "),
                    'subsidy'                   => trim($subsidy,"\0\t\n\x0B\r "),
                    'martyred'                  => trim($martyred,"\0\t\n\x0B\r "),
                    'distance'                  => trim($distance,"\0\t\n\x0B\r "),
                    'transportation'            => trim($transportation,"\0\t\n\x0B\r "),
                    'school_bused'              => trim($school_bused,"\0\t\n\x0B\r "),
                    'name_used'                 => trim($name_used,"\0\t\n\x0B\r "),
                    'id_card_validity'          => trim($id_card_validity,"\0\t\n\x0B\r "),
                    'specialty'                 => trim($specialty,"\0\t\n\x0B\r "),
                    'student_code_auxiliary'    => trim($student_code_auxiliary,"\0\t\n\x0B\r "),
                    'student_number'            => trim($student_number,"\0\t\n\x0B\r "),
                    'student_source'            => trim($student_source,"\0\t\n\x0B\r "),
                    'email'                     => trim($email,"\0\t\n\x0B\r "),
                    'homepage'                  => trim($homepage,"\0\t\n\x0B\r "),

                    'one_real_name'             => trim($one_real_name,"\0\t\n\x0B\r "),
                    'one_relation'              => trim($one_relation,"\0\t\n\x0B\r "),
                    'one_relation_remark'       => trim($one_relation_remark,"\0\t\n\x0B\r "),
                    'one_current_address'       => trim($one_current_address,"\0\t\n\x0B\r "),
                    'one_residence'             => trim($one_residence,"\0\t\n\x0B\r "),
                    'one_mobile'                => trim($one_mobile,"\0\t\n\x0B\r "),
                    'one_guardian'              => trim($one_guardian,"\0\t\n\x0B\r "),
                    'one_card_type'             => trim($one_card_type,"\0\t\n\x0B\r "),
                    'one_id_card'               => strtoupper(trim($one_id_card,"\0\t\n\x0B\r ")),
                    'one_id_card_original'      => $one_id_card,
                    'one_nation'                => trim($one_nation,"\0\t\n\x0B\r "),
                    'one_work_address'          => trim($one_work_address,"\0\t\n\x0B\r "),
                    'one_duties'                => trim($one_duties,"\0\t\n\x0B\r "),

                    'two_real_name'             => trim($two_real_name,"\0\t\n\x0B\r "),
                    'two_relation'              => trim($two_relation,"\0\t\n\x0B\r "),
                    'two_relation_remark'       => trim($two_relation_remark,"\0\t\n\x0B\r "),
                    'two_current_address'       => trim($two_current_address,"\0\t\n\x0B\r "),
                    'two_residence'             => trim($two_residence,"\0\t\n\x0B\r "),
                    'two_mobile'                => trim($two_mobile,"\0\t\n\x0B\r "),
                    'two_guardian'              => trim($two_guardian,"\0\t\n\x0B\r "),
                    'two_card_type'             => trim($two_card_type,"\0\t\n\x0B\r "),
                    'two_id_card'               => strtoupper(trim($two_id_card,"\0\t\n\x0B\r ")),
                    'two_id_card_original'      => $two_id_card,
                    'two_nation'                => trim($two_nation,"\0\t\n\x0B\r "),
                    'two_work_address'          => trim($two_work_address,"\0\t\n\x0B\r "),
                    'two_duties'                => trim($two_duties,"\0\t\n\x0B\r "),
                ];
                array_push($data, $tmp[$j - 2]);
            }

            $error = [];
            // 过滤上传数据中重复的身份证号、学籍号
            $id_card_data = array_column($data,'id_card');
            $id_card_data = array_filter($id_card_data);
            $repeat_data_card = array_count_values($id_card_data);

            $identification_data = array_column($data,'identification');
            $identification_data = array_filter($identification_data);
            $repeat_data_identification = array_count_values($identification_data);

            $successNum = 0;
            foreach ($data as $key=>$item) {
                $row = $key + 2;
                //非空判断
                if(true) {
//                    if ($item['identification'] == '') {
//                        $error[] = '第' . $row . '行【学籍接续标识】为空';
//                        continue;
//                    }
                    if ($item['school_code'] == '') {
                        $error[] = '第' . $row . '行【学校标识码】为空';
                        continue;
                    }
                    if ($item['real_name'] == '') {
                        $error[] = '第' . $row . '行【姓名】为空';
                        continue;
                    }
                    if ($item['sex'] == '') {
                        $error[] = '第' . $row . '行【性别】为空';
                        continue;
                    }
                    if ($item['birthday'] == '') {
                        $error[] = '第' . $row . '行【出生日期】为空';
                        continue;
                    }
                    if ($item['card_type'] == '') {
                        $error[] = '第' . $row . '行【身份证件类型】为空';
                        continue;
                    }
                    if ($item['id_card'] == '') {
                        $error[] = '第' . $row . '行【身份证件号】为空';
                        continue;
                    }
                    if ($item['birthplace'] == '') {
                        $error[] = '第' . $row . '行【出生地行政区划代码】为空';
                        continue;
                    }
                    if ($item['nation'] == '') {
                        $error[] = '第' . $row . '行【民族】为空';
                        continue;
                    }
                    if ($item['nationality'] == '') {
                        $error[] = '第' . $row . '行【国籍/地区】为空';
                        continue;
                    }
                    if ($item['overseas'] == '') {
                        $error[] = '第' . $row . '行【港澳台侨外】为空';
                        continue;
                    }
                    if ($item['birthplace_attr'] == '') {
                        $error[] = '第' . $row . '行【户口性质】为空';
                        continue;
                    }
                    if ($item['residence'] == '') {
                        $error[] = '第' . $row . '行【户口所在地行政区划】为空';
                        continue;
                    }
                    if ($item['current_address'] == '') {
                        $error[] = '第' . $row . '行【现住址】为空';
                        continue;
                    }
                    if ($item['onlyed'] == '') {
                        $error[] = '第' . $row . '行【是否独生子女】为空';
                        continue;
                    }
                    if ($item['stayed'] == '') {
                        $error[] = '第' . $row . '行【是否留守儿童】为空';
                        continue;
                    }
                    if ($item['flowed'] == '') {
                        $error[] = '第' . $row . '行【是否进城务工人员随迁子女】为空';
                        continue;
                    }
                    if ($item['orphaned'] == '') {
                        $error[] = '第' . $row . '行【是否孤儿】为空';
                        continue;
                    }
                    if ($item['nativeplace'] == '') {
                        $error[] = '第' . $row . '行【籍贯】为空';
                        continue;
                    }
                    if ($item['health'] == '') {
                        $error[] = '第' . $row . '行【健康状况】为空';
                        continue;
                    }
                    if ($item['politics'] == '') {
                        $error[] = '第' . $row . '行【政治面貌】为空';
                        continue;
                    }
                    if ($item['grade_code'] == '') {
                        $error[] = '第' . $row . '行【年级】为空';
                        continue;
                    }
                    if ($item['class_code'] == '') {
                        $error[] = '第' . $row . '行【班级号】为空';
                        continue;
                    }
                    if ($item['entrance_time'] == '') {
                        $error[] = '第' . $row . '行【入学年月】为空';
                        continue;
                    }
                    if ($item['entrance_type'] == '') {
                        $error[] = '第' . $row . '行【入学方式】为空';
                        continue;
                    }
                    if ($item['study_method'] == '') {
                        $error[] = '第' . $row . '行【就读方式】为空';
                        continue;
                    }
                    if ($item['mailing_address'] == '') {
                        $error[] = '第' . $row . '行【通信地址】为空';
                        continue;
                    }
                    if ($item['home_address'] == '') {
                        $error[] = '第' . $row . '行【家庭地址】为空';
                        continue;
                    }
                    if ($item['mobile'] == '') {
                        $error[] = '第' . $row . '行【联系电话】为空';
                        continue;
                    }
                    if ($item['postal_code'] == '') {
                        $error[] = '第' . $row . '行【邮政编码】为空';
                        continue;
                    }
                    if ($item['preschooled'] == '') {
                        $error[] = '第' . $row . '行【是否受过学前教育】为空';
                        continue;
                    }
                    if ($item['supported'] == '') {
                        $error[] = '第' . $row . '行【是否需要申请资助】为空';
                        continue;
                    }
                    if ($item['subsidy'] == '') {
                        $error[] = '第' . $row . '行【是否享受一补】为空';
                        continue;
                    }
                    if ($item['martyred'] == '') {
                        $error[] = '第' . $row . '行【是否烈士或优抚子女】为空';
                        continue;
                    }
                    if ($item['martyred'] == '') {
                        $error[] = '第' . $row . '行【是否烈士或优抚子女】为空';
                        continue;
                    }

                    if ($item['one_real_name'] == '') {
                        $error[] = '第' . $row . '行【成员1姓名】为空';
                        continue;
                    }
                    if ($item['one_relation'] == '') {
                        $error[] = '第' . $row . '行【成员1关系】为空';
                        continue;
                    }
                    if ($item['one_current_address'] == '') {
                        $error[] = '第' . $row . '行【成员1现住址】为空';
                        continue;
                    }
                    if ($item['one_residence'] == '') {
                        $error[] = '第' . $row . '行【成员1户口所在地行政区划】为空';
                        continue;
                    }
                    if ($item['one_mobile'] == '') {
                        $error[] = '第' . $row . '行【成员1联系电话】为空';
                        continue;
                    }
                    if ($item['one_guardian'] == '') {
                        $error[] = '第' . $row . '行【成员1是否监护人】为空';
                        continue;
                    }
                    if ($item['one_card_type'] == '') {
                        $error[] = '第' . $row . '行【成员1身份证件类型】为空';
                        continue;
                    }
                    if ($item['one_id_card'] == '') {
                        $error[] = '第' . $row . '行【成员1身份证件号】为空';
                        continue;
                    }

                    if ($item['two_real_name'] == '') {
                        $error[] = '第' . $row . '行【成员2姓名】为空';
                        continue;
                    }
                    if ($item['two_relation'] == '') {
                        $error[] = '第' . $row . '行【成员2关系】为空';
                        continue;
                    }
                    if ($item['two_current_address'] == '') {
                        $error[] = '第' . $row . '行【成员2现住址】为空';
                        continue;
                    }
                    if ($item['two_residence'] == '') {
                        $error[] = '第' . $row . '行【成员2户口所在地行政区划】为空';
                        continue;
                    }
                    if ($item['two_mobile'] == '') {
                        $error[] = '第' . $row . '行【成员2联系电话】为空';
                        continue;
                    }
                    if ($item['two_guardian'] == '') {
                        $error[] = '第' . $row . '行【成员2是否监护人】为空';
                        continue;
                    }
                    if ($item['two_card_type'] == '') {
                        $error[] = '第' . $row . '行【成员2身份证件类型】为空';
                        continue;
                    }
                    if ($item['two_id_card'] == '') {
                        $error[] = '第' . $row . '行【成员2身份证件号】为空';
                        continue;
                    }
                }

                $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                if (preg_match($preg,$item['real_name']) == 0){
                    $error[] = '第' . $row . '行【姓名】只能为汉字';
                    continue;
                }
                if (preg_match($preg,$item['one_real_name']) == 0){
                    $error[] = '第' . $row . '行【成员1姓名】只能为汉字';
                    continue;
                }
                if (preg_match($preg,$item['two_real_name']) == 0){
                    $error[] = '第' . $row . '行【成员2姓名】只能为汉字';
                    continue;
                }

                $birthday_card = substr($item['id_card'],6,8);
                if ($item['birthday'] != $birthday_card) {
                    $error[] = '第' . $row . '行出生日期和身份证不相符';
                    continue;
                }

                $birthplace_card = substr($item['id_card'],0,6);
                if ($item['birthplace'] != $birthplace_card) {
                    $error[] = '第' . $row . '行出生地行政区划代码和身份证不相符';
                    continue;
                }
//                if ($item['residence'] != $birthplace_card) {
//                    $error[] = '第' . $row . '行户口所在地行政区划和身份证不相符';
//                    continue;
//                }

                $school = Db::name('sys_school')->where([['school_code', '=', $item['school_code']],
                    ['deleted','=',0], ['disabled', '=', 0], ['school_type', '=', 1] ])->findOrEmpty();
                if(!$school){
                    $error[] = '第' . $row . '行学校标识码为【' . $item['school_code'] . '】的学校不存在';
                    continue;
                }
                if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                    $error[] = '第' . $row . '行学校标识码为【' . $item['school_code'] . '】无权限管理';
                    continue;
                }

                $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                if (preg_match($preg,$item['id_card']) == 0){
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】格式不正确';
                    continue;
                }
                if($repeat_data_card[$item['id_card']] > 1) {
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】重复';
                    continue;
                }
                if (preg_match($preg,$item['one_id_card']) == 0){
                    $error[] = '第' . $row . '行【成员1身份证件号' . $item['one_id_card_original'] . '】格式不正确';
                    continue;
                }
                if (preg_match($preg,$item['two_id_card']) == 0){
                    $error[] = '第' . $row . '行【成员2身份证件号' . $item['two_id_card_original'] . '】格式不正确';
                    continue;
                }
                if($item['identification'] && $repeat_data_identification[$item['identification']] > 1) {
                    $error[] = '第' . $row . '行【学籍接续标识' . $item['identification'] . '】重复';
                    continue;
                }

                $user_apply_id = Db::name('UserApplyDetail')->where('deleted', 0)->where('child_idcard', $item['id_card'])->value('user_apply_id');
                if(!$user_apply_id) {
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】申请详细信息不存在';
                    continue;
                }
                $where = [];
                $where[] = ['id', '=', $user_apply_id];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $user_apply = Db::name('UserApply')->where($where)->find();
                if(!$user_apply) {
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】申请信息不存在';
                    continue;
                }
                if($user_apply['result_school_id'] == 0) {
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】申请信息没有最终录取';
                    continue;
                }
                if($user_apply['result_school_id'] != $school['id']) {
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】最终录取学校与学校标识码不一致';
                    continue;
                }
                if($user_apply['signed'] == 0) {
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】申请信息没有入学报到';
                    continue;
                }

                if($item['sex'] == '男'){
                    $sex = 1;
                }elseif ($item['sex'] == '女'){
                    $sex = 2;
                }else{
                    $sex = 3;
                }
                if($item['card_type'] == '居民身份证'){
                    $card_type = 1;
                }elseif ($item['card_type'] == '护照'){
                    $card_type = 2;
                }else{
                    $card_type = 3;
                }
                if($item['one_card_type'] == '居民身份证'){
                    $one_card_type = 1;
                }elseif ($item['one_card_type'] == '护照'){
                    $one_card_type = 2;
                }else{
                    $one_card_type = 3;
                }
                if($item['two_card_type'] == '居民身份证'){
                    $two_card_type = 1;
                }elseif ($item['card_type'] == '护照'){
                    $two_card_type = 2;
                }else{
                    $two_card_type = 3;
                }
                $pinyin = new Pinyin();
                $name_pinyin = $pinyin->abbr($item['real_name']);

                $roll = [
                    'region_id' => $school['region_id'],
                    'school_id' => $school['id'],
                    'real_name' => $item['real_name'],
                    'sex' => $sex,
                    'birthday' => $item['birthday'],
                    'birthplace' => $item['birthplace'],
                    'nativeplace' => $item['nativeplace'],
                    'nation' => $item['nation'],
                    'nationality' => $item['nationality'],
                    'card_type' => $card_type,
                    'overseas' => $item['overseas'] == '是' ? 1 : 0,
                    'politics' => $item['politics'],
                    'health' => $item['health'],
                    'student_code_auxiliary' => $item['student_code_auxiliary'],
                    'student_number' => $item['student_number'],
                    'grade_code' => $item['grade_code'],
                    'class_code' => $item['class_code'],
                    'entrance_time' => $item['entrance_time'],
                    'entrance_type' => $item['entrance_type'],
                    'study_method' => $item['study_method'],
                    'student_source' => $item['student_source'],
                    'current_address' => $item['current_address'],
                    'mailing_address' => $item['mailing_address'],
                    'home_address' => $item['home_address'],
                    'mobile' => $item['mobile'],
                    'postal_code' => $item['postal_code'],
                    'email' => $item['email'],
                    'homepage' => $item['homepage'],
                    'registed' => 1,
                ];

                $extend = [
                    'name_pinyin' => $name_pinyin,
                    'name_used' => $item['name_used'],
                    'id_card_validity' => $item['id_card_validity'],
                    'residence' => $item['residence'],
                    'birthplace_attr' => $item['birthplace_attr'],
                    'specialty' => $item['specialty'],
                    'onlyed' => $item['onlyed'] == '是' ? 1 : 0,
                    'preschooled' => $item['preschooled'] == '是' ? 1 : 0,
                    'stayed' => $item['stayed'] == '是' ? 1 : 0,
                    'flowed' => $item['flowed'] == '是' ? 1 : 0,
                    'orphaned' => $item['orphaned'] == '是' ? 1 : 0,
                    'martyred' => $item['martyred'] == '是' ? 1 : 0,
                    //'regular_class' => $item['regular_class'] == '是' ? 1 : 0,//随班就读
                    'disability_type' => $item['disability_type'],
                    'purchased' => $item['purchased'] == '是' ? 1 : 0,
                    'supported' => $item['supported'] == '是' ? 1 : 0,
                    'subsidy' => $item['subsidy'] == '是' ? 1 : 0,
                    'distance' => $item['distance'],
                    'transportation' => $item['transportation'],
                    'school_bused' => $item['school_bused']== '是' ? 1 : 0,
                ];

                $one_guardian = [
                    'real_name' => $item['one_real_name'],
                    'relation' => $item['one_relation'],
                    'relation_remark' => $item['one_relation_remark'],
                    'nation' => $item['one_nation'],
                    'work_address' => $item['one_work_address'],
                    'current_address' => $item['one_current_address'],
                    'residence' => $item['one_residence'],
                    'mobile' => $item['one_mobile'],
                    'guardian' => $item['one_guardian'] == '是' ? 1 : 0,
                    'card_type' => $one_card_type,
                    'id_card' => $item['one_id_card'],
                    'duties' => $item['one_duties'],
                ];

                $two_guardian = [
                    'real_name' => $item['two_real_name'],
                    'relation' => $item['two_relation'],
                    'relation_remark' => $item['two_relation_remark'],
                    'nation' => $item['two_nation'],
                    'work_address' => $item['two_work_address'],
                    'current_address' => $item['two_current_address'],
                    'residence' => $item['two_residence'],
                    'mobile' => $item['two_mobile'],
                    'guardian' => $item['two_guardian'] == '是' ? 1 : 0,
                    'card_type' => $two_card_type,
                    'id_card' => $item['two_id_card'],
                    'duties' => $item['two_duties'],
                ];


                //身份证件号已存在 更新覆盖数据
                if( in_array($item['id_card'], array_keys($hasIdCardData)) ){
                    if(in_array($hasIdCardData[$item['id_card']], $school_res['school_ids'])){
                        $roll['identification'] = $item['identification'];
                        $model = new model();
                        $result = $model->editData($roll, ['id_card'=>$item['id_card']]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        $school_roll_id = Db::name('SysSchoolRoll')->where('id_card', $item['id_card'])->value('id');
                        $result = (new SysSchoolRollExtend())->editData($extend, ['school_roll_id' => $school_roll_id ]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        $result = (new SysSchoolRollGuardian())->editData($one_guardian, ['school_roll_id' => $school_roll_id, 'id_card' => $item['one_id_card'] ]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        $result = (new SysSchoolRollGuardian())->editData($two_guardian, ['school_roll_id' => $school_roll_id, 'id_card' => $item['two_id_card'] ]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                    }else{
                        $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】已在其他学校导入';
                    }
                    continue;
                }

                //学籍接续标识已存在 更新覆盖数据
                if($item['identification'] && in_array($item['identification'], array_keys($hasCodeData)) ){
                    if(in_array($hasCodeData[$item['identification']], $school_res['school_ids'])){
                        $roll['id_card'] = $item['id_card'];
                        $model = new model();
                        $result = $model->editData($roll, ['identification'=>$item['identification']]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        $school_roll_id = Db::name('SysSchoolRoll')->where('identification', $item['identification'])->value('id');
                        $result = (new SysSchoolRollExtend())->editData($extend, ['school_roll_id' => $school_roll_id ]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        $result = (new SysSchoolRollGuardian())->editData($one_guardian, ['school_roll_id' => $school_roll_id, 'id_card' => $item['one_id_card'] ]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        $result = (new SysSchoolRollGuardian())->editData($two_guardian, ['school_roll_id' => $school_roll_id, 'id_card' => $item['two_id_card'] ]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                    }else{
                        $error[] = '第' . $row . '行【学籍接续标识' . $item['identification'] . '】已在其他学校导入';
                    }
                    continue;
                }

                $roll['id_card'] = $item['id_card'];
                $roll['identification'] = $item['identification'] ?? '';
                //学籍基本信息
                $result = (new model())->addData($roll, 1);
                if($result['code'] == 0){
                    $error[] = $result['msg'];
                    continue;
                }
                $school_roll_id = $result['insert_id'];

                $extend['school_roll_id'] = $school_roll_id;
                //学籍扩展信息
                $result = (new SysSchoolRollExtend())->addData($extend);
                if($result['code'] == 0){
                    $error[] = $result['msg'];
                    continue;
                }
                //学籍家庭成员1信息
                $one_guardian['school_roll_id'] = $school_roll_id;
                $result = (new SysSchoolRollGuardian())->addData($one_guardian);
                if($result['code'] == 0){
                    $error[] = $result['msg'];
                    continue;
                }
                //学籍家庭成员2信息
                $two_guardian['school_roll_id'] = $school_roll_id;
                $result = (new SysSchoolRollGuardian())->addData($two_guardian);
                if($result['code'] == 0){
                    $error[] = $result['msg'];
                    continue;
                }

                //学籍统计
                $result = $this->getSchoolRollStatistics($school['id']);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                $successNum++;
            }
            $error[] = '成功导入' . $successNum . '条数据';

            $msg['success_num'] = $successNum;
            $msg['repeat_num'] = count($repeat);

            Cache::set('repeat', $repeat);
            Cache::set('importError_edu_school_roll_'.$this->userInfo['manage_id'], $error);

            return ['code' => 1, 'data' => $msg];

        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }

    }

    private function importMiddle($objPHPExcel, $highestRow): array
    {
        try {
            //导入模板检测
            $check_res = $this->checkMiddleTemplate($objPHPExcel);
            if ($check_res['code'] == 0) {
                throw new \Exception($check_res['msg']);
            }

            //定义$data，循环表格的时候，找出已存在的学籍学生信息。
            $data = [];
            $repeat = [];

            //循环读取excel表格，整合成数组。model
            $hasIdCardData = Db::name('SysSchoolRoll')->where('id_card', '<>', '')
                ->where('deleted',0)->column('school_id','id_card');
            $hasCodeData = Db::name('SysSchoolRoll')->where('student_code', '<>', '')
                ->where('deleted',0)->column('school_id','student_code');

            //获取角色所管理的学校ID
            $school_res = $this->getSchoolIdsByRole();
            if($school_res['code'] == 0){
                throw new \Exception($school_res['msg']);
            }

            for ($j = 2; $j <= $highestRow; $j++) {
                $student_code           = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();//个人标识码
                $real_name              = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();//学生姓名
                $id_card              = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();//学生身份证号
                $graduation_school_code = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();//毕业学校标识码
                $school_code            = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();//录取学校标识码
                $school_type            = $objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue();//教育阶段

                $tmp[$j - 2] = [
                    'student_code'              => trim($student_code,"\0\t\n\x0B\r "),
                    'real_name'                 => trim($real_name,"\0\t\n\x0B\r "),
                    'id_card'                   => strtoupper(trim($id_card,"\0\t\n\x0B\r ")),
                    'id_card_original'          => $id_card,
                    'graduation_school_code'    => trim($graduation_school_code,"\0\t\n\x0B\r "),
                    'school_code'               => trim($school_code,"\0\t\n\x0B\r "),
                    'school_type'               => trim($school_type,"\0\t\n\x0B\r "),
                ];
                array_push($data, $tmp[$j - 2]);
            }

            $error = [];
            // 过滤上传数据中重复的学籍号
            $id_card_data = array_column($data,'id_card');
            $id_card_data = array_filter($id_card_data);
            $repeat_data_card = array_count_values($id_card_data);

            $student_code_data = array_column($data,'student_code');
            $student_code_data = array_filter($student_code_data);
            $repeat_data_student_code = array_count_values($student_code_data);

            $successNum = 0;
            foreach ($data as $key=>$item) {
                $row = $key + 2;

                if ($item['student_code'] == '') {
                    $error[] = '第' . $row . '行【个人标识码】为空';
                    continue;
                }
                if ($item['real_name'] == '') {
                    $error[] = '第' . $row . '行【姓名】为空';
                    continue;
                }
                if ($item['id_card'] == '') {
                    $error[] = '第' . $row . '行【身份证号】为空';
                    continue;
                }
                if ($item['graduation_school_code'] == '') {
                    $error[] = '第' . $row . '行【毕业学校标识码】为空';
                    continue;
                }
                if ($item['school_code'] == '') {
                    $error[] = '第' . $row . '行【录取学校标识码】为空';
                    continue;
                }

                $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                if (preg_match($preg,$item['real_name']) == 0){
                    $error[] = '第' . $row . '行【姓名】只能为汉字';
                    continue;
                }

                $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                if (preg_match($preg,$item['id_card']) == 0){
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】格式不正确';
                    continue;
                }
                if($repeat_data_card[$item['id_card']] > 1) {
                    $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】重复';
                    continue;
                }

//                $where = [];
//                $where[] = ['deleted', '=', 0];
//                $where[] = ['student_code', '=', $item['student_code']];
//                $school_sixth = Db::name('SixthGrade')->where($where)->find();
//                if(!$school_sixth)
//                {
//                    $error[] = '第' . $row . '行个人标识码为【' . $item['student_code'] . '】的信息不存在';
//                    continue;
//                }

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['idcard', '=', $item['id_card']];
                $child = Db::name('UserChild')->where($where)->find();
                if(!$child)
                {
                    $error[] = '第' . $row . '行身份证号为【' . $item['id_card_original'] . '】对应学生不存在';
                    continue;
                }

                $school = Db::name('SysSchool')->where([['school_code', '=', $item['school_code']],
                    ['deleted','=',0], ['disabled', '=', 0], ['school_type', '=', 2] ])->findOrEmpty();
                if(!$school){
                    $error[] = '第' . $row . '行录取学校标识码为【' . $item['school_code'] . '】的学校不存在';
                    continue;
                }
                if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                    $error[] = '第' . $row . '行录取学校标识码为【' . $item['school_code'] . '】无权限管理';
                    continue;
                }

                $graduation_school = Db::name('SysSchool')->where([['school_code', '=', $item['graduation_school_code']],
                    ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
//                if(!$graduation_school){
//                    $error[] = '第' . $row . '行毕业学校标识码为【' . $item['graduation_school_code'] . '】的学校不存在';
//                    continue;
//                }
                if($repeat_data_student_code[$item['student_code']] > 1) {
                    $error[] = '第' . $row . '行【个人标识码' . $item['student_code'] . '】重复';
                    continue;
                }

                $where = [];
                $where[] = ['child_id', '=', $child['id']];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $user_apply = Db::name('UserApply')->where($where)->find();
                if(!$user_apply) {
                    $error[] = '第' . $row . '行【身份证号' . $item['id_card_original'] . '】申请信息不存在';
                    continue;
                }
                if($user_apply['result_school_id'] == 0) {
                    $error[] = '第' . $row . '行【身份证号' . $item['id_card_original'] . '】申请信息没有最终录取';
                    continue;
                }
                if($user_apply['result_school_id'] != $school['id']) {
                    $error[] = '第' . $row . '行【身份证号' . $item['id_card_original'] . '】最终录取学校与录取学校标识码不一致';
                    continue;
                }
                if($user_apply['signed'] == 0) {
                    $error[] = '第' . $row . '行【身份证号' . $item['id_card_original'] . '】申请信息没有入学报到';
                    continue;
                }

                $roll = [
                    'region_id' => $school['region_id'],
                    'school_id' => $school['id'],
                    'child_id' => $child['id'],
                    'real_name' => $item['real_name'],
                    'card_type' => 1,
                    'id_card' => $item['id_card'],
                    'graduation_school_code' => $item['graduation_school_code'],
                    'graduation_school' => isset($graduation_school['school_name']) ? $graduation_school['school_name'] : '',
                    'student_code' => $item['student_code'],
                    'registed' => 1,
                ];

                //学籍号已存在 更新覆盖数据
                if( in_array($item['student_code'], array_keys($hasCodeData)) ){
                    if(in_array($hasCodeData[$item['student_code']], $school_res['school_ids'])){
                        $model = new model();
                        $result = $model->editData($roll, ['student_code' => $item['student_code'] ]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                    }else{
                        $error[] = '第' . $row . '行【个人标识码' . $item['student_code'] . '】已在其他学校导入';
                    }
                    continue;
                }
                //身份证件号已存在 更新覆盖数据
                if( in_array($item['id_card'], array_keys($hasIdCardData)) ){
                    if(in_array($hasIdCardData[$item['id_card']], $school_res['school_ids'])){
                        $model = new model();
                        $result = $model->editData($roll, ['id_card'=>$item['id_card']]);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                    }else{
                        $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】已在其他学校导入';
                    }
                    continue;
                }

                //学籍基本信息
                $result = (new model())->addData($roll, 1);
                if($result['code'] == 0){
                    $error[] = $result['msg'];
                    continue;
                }
                $school_roll_id = $result['insert_id'];

                //学籍统计
                $result = $this->getSchoolRollStatistics($school['id']);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                $successNum++;
            }

            $error[] = '成功导入' . $successNum . '条数据';

            $msg['success_num'] = $successNum;
            $msg['repeat_num'] = count($repeat);

            Cache::set('repeat', $repeat);
            Cache::set('importError_edu_school_roll_'.$this->userInfo['manage_id'], $error);

            return ['code' => 1, 'data' => $msg];

        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }

    }

    /**
     * 导入错误信息
     * @return Json
     */
    public function getImportError(): Json
    {
        if ($this->request->isPost()) {
            try {
                $errData = Cache::get('importError_edu_school_roll_'.$this->userInfo['manage_id']);
                if(empty($errData)){
                    $errData = [];
                }
                $data['data'] = $errData;
                $data['total'] = count($errData);
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
     * 学籍信息导出
     * @return Json
     */
    public function export(): Json
    {
        if($this->request->isPost())
        {
            try {
                $where = [];
                $where[] = ['roll.deleted','=',0];
                $where[] = ['roll.registed','=',1];

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['roll.id_card|roll.current_address|roll.real_name|roll.student_code','like', '%' . $this->result['keyword'] . '%'];
                }

                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['roll.region_id','=', $this->result['region_id']];
                }
                if($this->request->has('school_id') && $this->result['school_id'] > 0)
                {
                    $where[] = ['roll.school_id','=', $this->result['school_id']];
                }
                if($this->request->has('class_code') && $this->result['class_code'] !== '')
                {
                    $where[] = ['roll.class_code','=', $this->result['class_code']];
                }
                if (isset($this->userInfo['relation_region']) ){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['roll.region_id', 'in', $region_ids];
                }

                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                    $schoolInfo = Db::name('SysSchool')->field(['school_type', 'school_code'])->where('id', $school_id)->find();
                    $where[] = ['roll.school_id', '=', $school_id];
                } else {
                    throw new \Exception('学校管理员学校ID设置错误');
                }

                $guardian_list = Db::name('SysSchoolRoll')->alias('roll')
                    ->join([
                        'deg_sys_school_roll_guardian' => 'guardian'
                    ], 'roll.id = guardian.school_roll_id', 'LEFT')
                    ->field(['guardian.*'])->where($where)->where('guardian.deleted', 0)->select()->toArray();

                $guardian_data = [];
                foreach ($guardian_list as $k => $v){
                    $guardian_data[$v['school_roll_id']][] = $v;
                }


                $list = Db::name('SysSchoolRoll')->alias('roll')
                    ->join([
                        'deg_sys_school_roll_extend' => 'extend'
                    ], 'roll.id = extend.school_roll_id', 'LEFT')
                    ->field([
                        'roll.id' => 'roll_id',
                        'roll.*',
                        'extend.*',
                        'CASE roll.sex WHEN 1 THEN "男" WHEN 2 THEN "女" ELSE "未知" END' => 'sex_name',
                        'CASE roll.card_type WHEN 1 THEN "身份证" WHEN 2 THEN "护照" ELSE "其他" END' => 'card_type_name',
                        'CASE roll.overseas WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'overseas_name',
                        'CASE extend.onlyed WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'onlyed_name',
                        'CASE extend.stayed WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'stayed_name',
                        'CASE extend.flowed WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'flowed_name',
                        'CASE extend.orphaned WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'orphaned_name',
                        'CASE extend.preschooled WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'preschooled_name',
                        'CASE extend.purchased WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'purchased_name',
                        'CASE extend.supported WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'supported_name',
                        'CASE extend.subsidy WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'subsidy_name',
                        'CASE extend.martyred WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'martyred_name',
                        'CASE extend.school_bused WHEN 1 THEN "是" WHEN 0 THEN "否" ELSE "-" END' => 'school_bused_name',
//                        'IF(roll.overseas = 1, "是", "否")' => 'overseas_name',
//                        'IF(extend.onlyed = 1, "是", "否")' => 'onlyed_name',
//                        'IF(extend.stayed = 1, "是", "否")' => 'stayed_name',
//                        'IF(extend.flowed = 1, "是", "否")' => 'flowed_name',
//                        'IF(extend.orphaned = 1, "是", "否")' => 'orphaned_name',
//                        'IF(extend.preschooled = 1, "是", "否")' => 'preschooled_name',
//                        'IF(extend.purchased = 1, "是", "否")' => 'purchased_name',
//                        'IF(extend.supported = 1, "是", "否")' => 'supported_name',
//                        'IF(extend.subsidy = 1, "是", "否")' => 'subsidy_name',
//                        'IF(extend.martyred = 1, "是", "否")' => 'martyred_name',
//                        'IF(extend.school_bused = 1, "是", "否")' => 'school_bused_name',
                    ])
                    ->where($where)->hidden(['extend.id', 'extend.school_roll_id'])
                    ->order('roll.id desc')
                    ->select()->toArray();

                $region = Cache::get('region');
                $school = Cache::get('school');
                $data = [];
                foreach ($list as $k => $v){
                    if($schoolInfo['school_type'] == 1) {
                        $data[$k]['id'] = $v['roll_id'];
                        $data[$k]['region_name'] = '';
                        $data[$k]['school_name'] = '';
                        $data[$k]['identification'] = $v['identification'];
                        $data[$k]['school_code'] = '';
                        $data[$k]['real_name'] = $v['real_name'];
                        $data[$k]['sex'] = $v['sex_name'];
                        $data[$k]['birthday'] = $v['birthday'];
                        $data[$k]['card_type'] = $v['card_type_name'];
                        $data[$k]['id_card'] = $v['id_card'];
                        $data[$k]['birthplace'] = $v['birthplace'];
                        $data[$k]['nation'] = $v['nation'];
                        $data[$k]['nationality'] = $v['nationality'];
                        $data[$k]['overseas'] = $v['overseas_name'];
                        $data[$k]['birthplace_attr'] = $v['birthplace_attr'];
                        $data[$k]['residence'] = $v['residence'];
                        $data[$k]['current_address'] = $v['current_address'];
                        $data[$k]['onlyed'] = $v['onlyed_name'];
                        $data[$k]['stayed'] = $v['stayed_name'];
                        $data[$k]['flowed'] = $v['flowed_name'];
                        $data[$k]['orphaned'] = $v['orphaned_name'];
                        $data[$k]['nativeplace'] = $v['nativeplace'];
                        $data[$k]['health'] = $v['health'];
                        $data[$k]['politics'] = $v['politics'];
                        $data[$k]['grade_code'] = $v['grade_code'];
                        $data[$k]['class_code'] = $v['class_code'];
                        $data[$k]['entrance_time'] = $v['entrance_time'];
                        $data[$k]['entrance_type'] = $v['entrance_type'];
                        $data[$k]['study_method'] = $v['study_method'];
                        $data[$k]['mailing_address'] = $v['mailing_address'];
                        $data[$k]['home_address'] = $v['home_address'];
                        $data[$k]['mobile'] = $v['mobile'];
                        $data[$k]['postal_code'] = $v['postal_code'];
                        $data[$k]['preschooled'] = $v['preschooled_name'];
                        $data[$k]['disability_type'] = $v['disability_type'];
                        $data[$k]['purchased'] = $v['purchased_name'];
                        $data[$k]['supported'] = $v['supported_name'];
                        $data[$k]['subsidy'] = $v['subsidy_name'];
                        $data[$k]['martyred'] = $v['martyred_name'];
                        $data[$k]['distance'] = $v['distance'];
                        $data[$k]['transportation'] = $v['transportation'];
                        $data[$k]['school_bused'] = $v['school_bused_name'];
                        $data[$k]['name_used'] = $v['name_used'];
                        $data[$k]['id_card_validity'] = $v['id_card_validity'];
                        $data[$k]['specialty'] = $v['specialty'];
                        $data[$k]['student_code_auxiliary'] = $v['student_code_auxiliary'];
                        $data[$k]['student_number'] = $v['student_number'];
                        $data[$k]['student_source'] = $v['student_source'];
                        $data[$k]['email'] = $v['email'];
                        $data[$k]['homepage'] = $v['homepage'];

                        $guardian = isset($guardian_data[$v['roll_id']]) ? $guardian_data[$v['roll_id']] : [];

                        //成员1信息
                        $data[$k]['one_real_name'] = isset($guardian[0]['real_name']) ? $guardian[0]['real_name'] : '';
                        $data[$k]['one_relation'] = isset($guardian[0]['relation']) ? $guardian[0]['relation'] : '';
                        $data[$k]['one_relation_remark'] = isset($guardian[0]['relation_remark']) ? $guardian[0]['relation_remark'] : '';
                        $data[$k]['one_current_address'] = isset($guardian[0]['current_address']) ? $guardian[0]['current_address'] : '';
                        $data[$k]['one_residence'] = isset($guardian[0]['residence']) ? $guardian[0]['residence'] : '';
                        $data[$k]['one_mobile'] = isset($guardian[0]['mobile']) ? $guardian[0]['mobile'] : '';
                        $data[$k]['one_guardian'] = (isset($guardian[0]['guardian']) && $guardian[0]['guardian'] == 1) ? '是' : '否';
                        $one_card_type = '';
                        if (isset($guardian[0]['card_type'])) {
                            if ($guardian[0]['card_type'] == 1) {
                                $one_card_type = '身份证';
                            } elseif ($guardian[0]['card_type'] == 2) {
                                $one_card_type = '护照';
                            } else {
                                $one_card_type = '其他';
                            }
                        }
                        $data[$k]['one_card_type'] = $one_card_type;
                        $data[$k]['one_id_card'] = isset($guardian[0]['id_card']) ? $guardian[0]['id_card'] : '';
                        $data[$k]['one_nation'] = isset($guardian[0]['nation']) ? $guardian[0]['nation'] : '';
                        $data[$k]['one_work_address'] = isset($guardian[0]['work_address']) ? $guardian[0]['work_address'] : '';
                        $data[$k]['one_duties'] = isset($guardian[0]['duties']) ? $guardian[0]['duties'] : '';

                        //成员2信息
                        $data[$k]['two_real_name'] = isset($guardian[1]['real_name']) ? $guardian[1]['real_name'] : '';
                        $data[$k]['two_relation'] = isset($guardian[1]['relation']) ? $guardian[1]['relation'] : '';
                        $data[$k]['two_relation_remark'] = isset($guardian[1]['relation_remark']) ? $guardian[1]['relation_remark'] : '';
                        $data[$k]['two_current_address'] = isset($guardian[1]['current_address']) ? $guardian[1]['current_address'] : '';
                        $data[$k]['two_residence'] = isset($guardian[1]['residence']) ? $guardian[1]['residence'] : '';
                        $data[$k]['two_mobile'] = isset($guardian[1]['mobile']) ? $guardian[1]['mobile'] : '';
                        $data[$k]['two_guardian'] = (isset($guardian[1]['guardian']) && $guardian[1]['guardian'] == 1) ? '是' : '否';
                        $two_card_type = '';
                        if (isset($guardian[1]['card_type'])) {
                            if ($guardian[1]['card_type'] == 1) {
                                $two_card_type = '身份证';
                            } elseif ($guardian[1]['card_type'] == 2) {
                                $two_card_type = '护照';
                            } else {
                                $two_card_type = '其他';
                            }
                        }
                        $data[$k]['two_card_type'] = $two_card_type;
                        $data[$k]['two_id_card'] = isset($guardian[1]['id_card']) ? $guardian[1]['id_card'] : '';
                        $data[$k]['two_nation'] = isset($guardian[1]['nation']) ? $guardian[1]['nation'] : '';
                        $data[$k]['two_work_address'] = isset($guardian[1]['work_address']) ? $guardian[1]['work_address'] : '';
                        $data[$k]['two_duties'] = isset($guardian[1]['duties']) ? $guardian[1]['duties'] : '';

                        $regionData = filter_value_one($region, 'id', $v['region_id']);
                        if (count($regionData) > 0) {
                            $data[$k]['region_name'] = $regionData['region_name'];
                        }
                        $houseSchoolData = filter_value_one($school, 'id', $v['school_id']);
                        if (count($houseSchoolData) > 0) {
                            $data[$k]['school_name'] = $houseSchoolData['school_name'];
                            $data[$k]['school_code'] = $houseSchoolData['school_code'];
                        }
                    }else{
                        $data[$k]['id'] = $v['roll_id'];
                        $data[$k]['student_code'] = $v['student_code'];
                        $data[$k]['real_name'] = $v['real_name'];
                        $data[$k]['id_card'] = $v['id_card'];
                        $data[$k]['graduation_school_code'] = $v['graduation_school_code'];
                        $data[$k]['school_code'] = $schoolInfo['school_code'];
                        $data[$k]['school_type'] = '初中';
                    }
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                if($schoolInfo['school_type'] == 1) {
                    $headArr = ['编号', '区县', '就读学校', '学籍接续标识', '学校标识码', '姓名', '性别', '出生日期', '证件类型', '身份证号',
                        '出生地行政区划代码', '民族', '国籍/地区', '港澳台侨外', '户口性质', '户口所在地行政区划', '现住址', '是否独生子女',
                        '是否留守儿童', '是否进城务工人员随迁子女', '是否孤儿', '籍贯', '健康状况', '政治面貌', '年级', '班级号', '入学年月',
                        '入学方式', '就读方式', '通信地址', '家庭地址', '联系电话', '邮政编码', '是否受过学前教育', '残疾人类型', '是否由政府购买学位',
                        '是否需要申请资助', '是否享受一补', '是否烈士或优抚子女', '上下学距离', '上下学交通方式', '是否乘坐校车', '曾用名',
                        '身份证件有效期', '特长', '学籍辅号', '班内学号', '学生来源', '电子信箱', '主页地址',
                        '成员1姓名', '成员1关系', '成员1关系说明', '成员1现住址', '成员1户口所在地行政区划', '成员1联系电话', '成员1是否监护人',
                        '成员1身份证件类型', '成员1身份证件号', '成员1民族', '成员1工作单位', '成员1职务',
                        '成员2姓名', '成员2关系', '成员2关系说明', '成员2现住址', '成员2户口所在地行政区划', '成员2联系电话', '成员2是否监护人',
                        '成员2身份证件类型', '成员2身份证件号', '成员2民族', '成员2工作单位', '成员2职务'];
                }else{
                    $headArr = ['编号', '个人标识码', '学生姓名', '身份证号', '毕业学校标识码', '录取学校标识码', '教育阶段'];
                }
//                if(count($data) > 10000){
//                    $total = count($data);
//                    $count_excel = ceil($total / 10000);
//                    for ($i = 0; $i < $count_excel; $i++){
//                        $offset = $i * 10000;
//                        $length = ($i + 1) * 10000;
//                        if($i == ($count_excel - 1)){
//                            $length = $total;
//                        }
//                        $data = array_slice($data, $offset, $length, true);
//                        $this->excelExport('学籍信息_' . ($i + 1) . '_', $headArr, $data);
//                    }
//                }else {
                        $this->excelExport('学籍信息_', $headArr, $data);
//                }

            } catch (\Exception $exception){
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?? Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * excel表格导出
     * @param string $fileName 文件名称
     * @param array $headArr 表头名称
     * @param array $data 要导出的数据
     * @author Mr.Lv   3063306168@qq.com
     */
    public function excelExport($fileName = '', $headArr = [], $data = []) {

        $fileName       .= "_" . date("Y_m_d", time());
        $spreadsheet    = new Spreadsheet();
        $objPHPExcel    = $spreadsheet->getActiveSheet();
        $firstColumn = 'A';// 设置表头

        foreach ($headArr as $k => $v) {
            $key = floor($k / 26);
            $firstNum = '';
            if ($key > 0) {
                # 当$k等于1,第一个列标签还是A,所以需要减去1
                $firstNum = chr(ord($firstColumn) + $key - 1);
            }
            $secondKey = $k % 26;
            $secondNum = chr(ord($firstColumn) + $secondKey);
            $column = $firstNum . $secondNum;

            $objPHPExcel->setCellValue($column . '1', $v);
        }
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(40);
        $spreadsheet->getActiveSheet()->getStyle('D')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('Z')->setWidth(20);

        $spreadsheet->getActiveSheet()->getStyle('J')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('AF')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('AT')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('AU')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('BD')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('BG')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('BP')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('BS')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);

        $column = 2;
        foreach ($data as $k => $rows) { // 行写入
            $i = 0;
            foreach ($rows as $keyName => $value) { // 列写入
                $key = floor($i / 26);
                $firstNum = '';
                if ($key > 0) {
                    # 当$k等于1,第一个列标签还是A,所以需要减去1
                    $firstNum = chr(ord($firstColumn) + $key - 1);
                }
                $secondKey = $i % 26;
                $secondNum = chr(ord($firstColumn) + $secondKey);
                $span = $firstNum . $secondNum;

                if($keyName == 'id_card' || $keyName == 'student_code' || $keyName == 'one_id_card' || $keyName == 'two_id_card'
                    || $keyName == 'mobile' || $keyName == 'one_mobile' || $keyName == 'two_mobile' || $keyName == 'student_number'
                    || $keyName == 'student_code_auxiliary'){
                    //$objPHPExcel->setCellValue($span . $column, $value . " ");
                    $spreadsheet->getActiveSheet()->setCellValueExplicit($span . $column, $value, DataType::TYPE_STRING);
                }else{
                    $objPHPExcel->setCellValue($span . $column, $value);
                }
                $i++;
            }
            $column++;

        }

        //$fileName = iconv("utf-8", "gbk//IGNORE", $fileName); // 重命名表（UTF8编码不需要这一步）
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        //删除清空：
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    }

    private function getRegionSchool()
    {
        $res = ['code' => 1];
        if ($this->userInfo['grade_id'] >= $this->city_grade) {
            if($this->request->has('school_id') && $this->result['school_id'] > 0)
            {
                $res['school_id'] = $this->result['school_id'];
            }else{
                $res['school_id'] = 0;
            }
            if($this->request->has('region_id') && $this->result['region_id'] > 0)
            {
                $res['region_id'] = $this->result['region_id'];
            }else{
                $res['region_id'] = 0;
            }
        }else{
            //学校权限 学校角色不需要选学校和区域
            if ($this->userInfo['grade_id'] == $this->school_grade) {
                $school_id = $this->userInfo['school_id'];
                if(!$school_id){
                    return ['code' => 0, 'msg' => '学校账号的学校ID错误' ];
                }
                $res['school_id'] = $school_id;
                $region_id = $this->userInfo['region_id'];
                if(!$region_id){
                    return ['code' => 0, 'msg' => '学校账号的区县ID错误' ];
                }
                $res['region_id'] = $region_id;
            }else{
                //区县、教管会角色不需要选择区县
                $region_id = $this->userInfo['region_id'];
                if(!$region_id){
                    return ['code' => 0, 'msg' => '区级、教管会账号的区县ID错误' ];
                }
                $res['region_id'] = $region_id;
                if($this->request->has('school_id') && $this->result['school_id'] > 0)
                {
                    $res['school_id'] = $this->result['school_id'];
                }else{
                    $res['school_id'] = 0;
                }
            }
        }
        return $res;
    }

    //小学模板
    private function checkPrimaryTemplate($objPHPExcel): array
    {
        $identification = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($identification != '学籍接续标识') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_code = $objPHPExcel->getActiveSheet()->getCell("B1")->getValue();
        if($school_code != '学校标识码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $real_name = $objPHPExcel->getActiveSheet()->getCell("C1")->getValue();
        if($real_name != '姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $sex = $objPHPExcel->getActiveSheet()->getCell("D1")->getValue();
        if($sex != '性别') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $birthday = $objPHPExcel->getActiveSheet()->getCell("E1")->getValue();
        if($birthday != '出生日期') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $id_card_type = $objPHPExcel->getActiveSheet()->getCell("F1")->getValue();
        if($id_card_type != '身份证件类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $id_card = $objPHPExcel->getActiveSheet()->getCell("G1")->getValue();
        if($id_card != '身份证件号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $birthplace = $objPHPExcel->getActiveSheet()->getCell("H1")->getValue();
        if($birthplace != '出生地行政区划代码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $nation = $objPHPExcel->getActiveSheet()->getCell("I1")->getValue();
        if($nation != '民族') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $nationality = $objPHPExcel->getActiveSheet()->getCell("J1")->getValue();
        if($nationality != '国籍/地区') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $overseas = $objPHPExcel->getActiveSheet()->getCell("K1")->getValue();
        if($overseas != '港澳台侨外') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $birthplace_attr = $objPHPExcel->getActiveSheet()->getCell("L1")->getValue();
        if($birthplace_attr != '户口性质') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $residence = $objPHPExcel->getActiveSheet()->getCell("M1")->getValue();
        if($residence != '户口所在地行政区划') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $current_address = $objPHPExcel->getActiveSheet()->getCell("N1")->getValue();
        if($current_address != '现住址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $onlyed = $objPHPExcel->getActiveSheet()->getCell("O1")->getValue();
        if($onlyed != '是否独生子女') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $stayed = $objPHPExcel->getActiveSheet()->getCell("P1")->getValue();
        if($stayed != '是否留守儿童') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $flowed = $objPHPExcel->getActiveSheet()->getCell("Q1")->getValue();
        if($flowed != '是否进城务工人员随迁子女') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $orphaned = $objPHPExcel->getActiveSheet()->getCell("R1")->getValue();
        if($orphaned != '是否孤儿') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $nativeplace = $objPHPExcel->getActiveSheet()->getCell("S1")->getValue();
        if($nativeplace != '籍贯') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $health = $objPHPExcel->getActiveSheet()->getCell("T1")->getValue();
        if($health != '健康状况') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $politics = $objPHPExcel->getActiveSheet()->getCell("U1")->getValue();
        if($politics != '政治面貌') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $grade_code = $objPHPExcel->getActiveSheet()->getCell("V1")->getValue();
        if($grade_code != '年级') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $class_code = $objPHPExcel->getActiveSheet()->getCell("W1")->getValue();
        if($class_code != '班级号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $entrance_time = $objPHPExcel->getActiveSheet()->getCell("X1")->getValue();
        if($entrance_time != '入学年月') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $entrance_type = $objPHPExcel->getActiveSheet()->getCell("Y1")->getValue();
        if($entrance_type != '入学方式') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $study_method = $objPHPExcel->getActiveSheet()->getCell("Z1")->getValue();
        if($study_method != '就读方式') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $mailing_address = $objPHPExcel->getActiveSheet()->getCell("AA1")->getValue();
        if($mailing_address != '通信地址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $home_address = $objPHPExcel->getActiveSheet()->getCell("AB1")->getValue();
        if($home_address != '家庭地址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $mobile = $objPHPExcel->getActiveSheet()->getCell("AC1")->getValue();
        if($mobile != '联系电话') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $postal_code = $objPHPExcel->getActiveSheet()->getCell("AD1")->getValue();
        if($postal_code != '邮政编码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $preschooled = $objPHPExcel->getActiveSheet()->getCell("AE1")->getValue();
        if($preschooled != '是否受过学前教育') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $disability_type = $objPHPExcel->getActiveSheet()->getCell("AF1")->getValue();
        if($disability_type != '残疾人类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $purchased = $objPHPExcel->getActiveSheet()->getCell("AG1")->getValue();
        if($purchased != '是否由政府购买学位') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $supported = $objPHPExcel->getActiveSheet()->getCell("AH1")->getValue();
        if($supported != '是否需要申请资助') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $subsidy = $objPHPExcel->getActiveSheet()->getCell("AI1")->getValue();
        if($subsidy != '是否享受一补') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $martyred = $objPHPExcel->getActiveSheet()->getCell("AJ1")->getValue();
        if($martyred != '是否烈士或优抚子女') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $distance = $objPHPExcel->getActiveSheet()->getCell("AK1")->getValue();
        if($distance != '上下学距离') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $transportation = $objPHPExcel->getActiveSheet()->getCell("AL1")->getValue();
        if($transportation != '上下学交通方式') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_bused = $objPHPExcel->getActiveSheet()->getCell("AM1")->getValue();
        if($school_bused != '是否乘坐校车') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $name_used = $objPHPExcel->getActiveSheet()->getCell("AN1")->getValue();
        if($name_used != '曾用名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $id_card_validity = $objPHPExcel->getActiveSheet()->getCell("AO1")->getValue();
        if($id_card_validity != '身份证件有效期') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $specialty = $objPHPExcel->getActiveSheet()->getCell("AP1")->getValue();
        if($specialty != '特长') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_code_auxiliary = $objPHPExcel->getActiveSheet()->getCell("AQ1")->getValue();
        if($student_code_auxiliary != '学籍辅号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_number = $objPHPExcel->getActiveSheet()->getCell("AR1")->getValue();
        if($student_number != '班内学号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_source = $objPHPExcel->getActiveSheet()->getCell("AS1")->getValue();
        if($student_source != '学生来源') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $email = $objPHPExcel->getActiveSheet()->getCell("AT1")->getValue();
        if($email != '电子信箱') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $homepage = $objPHPExcel->getActiveSheet()->getCell("AU1")->getValue();
        if($homepage != '主页地址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_real_name = $objPHPExcel->getActiveSheet()->getCell("AV1")->getValue();
        if($one_real_name != '成员1姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_relation = $objPHPExcel->getActiveSheet()->getCell("AW1")->getValue();
        if($one_relation != '成员1关系') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_relation_remark = $objPHPExcel->getActiveSheet()->getCell("AX1")->getValue();
        if($one_relation_remark != '成员1关系说明') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_current_address = $objPHPExcel->getActiveSheet()->getCell("AY1")->getValue();
        if($one_current_address != '成员1现住址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_residence = $objPHPExcel->getActiveSheet()->getCell("AZ1")->getValue();
        if($one_residence != '成员1户口所在地行政区划') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_mobile = $objPHPExcel->getActiveSheet()->getCell("BA1")->getValue();
        if($one_mobile != '成员1联系电话') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_guardian = $objPHPExcel->getActiveSheet()->getCell("BB1")->getValue();
        if($one_guardian != '成员1是否监护人') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_card_type = $objPHPExcel->getActiveSheet()->getCell("BC1")->getValue();
        if($one_card_type != '成员1身份证件类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_id_card = $objPHPExcel->getActiveSheet()->getCell("BD1")->getValue();
        if($one_id_card != '成员1身份证件号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_nation = $objPHPExcel->getActiveSheet()->getCell("BE1")->getValue();
        if($one_nation != '成员1民族') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_work_address = $objPHPExcel->getActiveSheet()->getCell("BF1")->getValue();
        if($one_work_address != '成员1工作单位') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $one_duties = $objPHPExcel->getActiveSheet()->getCell("BG1")->getValue();
        if($one_duties != '成员1职务') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_real_name = $objPHPExcel->getActiveSheet()->getCell("BH1")->getValue();
        if($two_real_name != '成员2姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_relation = $objPHPExcel->getActiveSheet()->getCell("BI1")->getValue();
        if($two_relation != '成员2关系') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_relation_remark = $objPHPExcel->getActiveSheet()->getCell("BJ1")->getValue();
        if($two_relation_remark != '成员2关系说明') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_current_address = $objPHPExcel->getActiveSheet()->getCell("BK1")->getValue();
        if($two_current_address != '成员2现住址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_residence = $objPHPExcel->getActiveSheet()->getCell("BL1")->getValue();
        if($two_residence != '成员2户口所在地行政区划') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_mobile = $objPHPExcel->getActiveSheet()->getCell("BM1")->getValue();
        if($two_mobile != '成员2联系电话') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_guardian = $objPHPExcel->getActiveSheet()->getCell("BN1")->getValue();
        if($two_guardian != '成员2是否监护人') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_card_type = $objPHPExcel->getActiveSheet()->getCell("BO1")->getValue();
        if($two_card_type != '成员2身份证件类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_id_card = $objPHPExcel->getActiveSheet()->getCell("BP1")->getValue();
        if($two_id_card != '成员2身份证件号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_nation = $objPHPExcel->getActiveSheet()->getCell("BQ1")->getValue();
        if($two_nation != '成员2民族') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_work_address = $objPHPExcel->getActiveSheet()->getCell("BR1")->getValue();
        if($two_work_address != '成员2工作单位') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $two_duties = $objPHPExcel->getActiveSheet()->getCell("BS1")->getValue();
        if($two_duties != '成员2职务') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

    //初中模板
    private function checkMiddleTemplate($objPHPExcel): array
    {
        $student_code = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($student_code != '个人标识码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $real_name = $objPHPExcel->getActiveSheet()->getCell("B1")->getValue();
        if($real_name != '学生姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $id_card = $objPHPExcel->getActiveSheet()->getCell("C1")->getValue();
        if($id_card != '身份证号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $graduation_school_code = $objPHPExcel->getActiveSheet()->getCell("D1")->getValue();
        if($graduation_school_code != '毕业学校标识码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_code = $objPHPExcel->getActiveSheet()->getCell("E1")->getValue();
        if($school_code != '录取学校标识码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_type = $objPHPExcel->getActiveSheet()->getCell("F1")->getValue();
        if($school_type != '教育阶段') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

    //招生情况
    private function getRecruit(): array
    {
        try{
            $data = [];

            //市级
            if ($this->userInfo['grade_id'] >= $this->city_grade) {
                //批复学位
                $where = [];
                //$where[] = ['plan_id', '=', $plan_id];
                $where[] = ['deleted', '=', 0];
                $where[] = ['status', '=', 1];
                $spare_total = Db::name('PlanApply')->where($where)->sum('spare_total');
                $data['spare_total'] = $spare_total;

                //学籍注册量
                $where = [];
                $where[] = ['deleted', '=', 0];
                $school_roll_total = Db::name('SysSchoolRoll')->where($where)->count();
                $data['school_roll_total'] = $school_roll_total;

                //录取人数
                $where = [];
                $where[] = ['a.result_school_id', '>', 0];
                $where[] = ['a.deleted','=',0];
                $where[] = ['a.voided', '=', 0];//没有作废
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $admission_total = Db::name('UserApply')->alias('a')->where($where)->count();
                $data['admission_total'] = $admission_total;

                //未注册人数
                $unregistered_total = $admission_total - $school_roll_total;
                $unregistered_total = $unregistered_total < 0 ? 0 : $unregistered_total;
                $data['unregistered_total'] = $unregistered_total;

            }else {
                //区县、教管会、学校
                $role_school = $this->getSchoolIdsByRole(true);//不含教学点
                if($role_school['code'] == 0) {
                    throw new \Exception($role_school['msg']);
                }
                //批复学位
                $spare_total = Db::name('PlanApply')
                    ->where('school_id', 'in', $role_school['school_ids'] )
                    ->where('status', 1)->where('deleted', 0)->sum('spare_total');
                $data['spare_total'] = $spare_total;

                //学籍注册量
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['registed', '=', 1];
                $where[] = ['school_id', 'in', $role_school['school_ids'] ];
                $school_roll_total = Db::name('SysSchoolRoll')->where($where)->count();
                $data['school_roll_total'] = $school_roll_total;

                //录取人数
                $where = [];
                $where[] = ['a.result_school_id', 'in', $role_school['school_ids'] ];
                $where[] = ['a.deleted','=',0];
                $where[] = ['a.voided', '=', 0];//没有作废
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $admission_total = Db::name('UserApply')->alias('a')->where($where)->count();
                $data['admission_total'] = $admission_total;

                //未注册人数
                $unregistered_total = $admission_total - $school_roll_total;
                $unregistered_total = $unregistered_total < 0 ? 0 : $unregistered_total;
                $data['unregistered_total'] = $unregistered_total;

            }
            return ['code' => 1, 'data' => $data];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }
    }

}