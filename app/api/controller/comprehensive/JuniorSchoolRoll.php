<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\comprehensive;

use app\common\controller\Education;
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

class JuniorSchoolRoll extends Education
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

                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0){
                    throw new \Exception($role_school['msg']);
                }

                $school_ids = [];
                if($role_school['school_ids'] ){
                    $school_ids = Db::name("SysSchool")
                        ->where('id', 'in', $role_school['school_ids'])->where('school_type', 2)
                        ->where('deleted', 0)->where('disabled', 0)->column('id');
                }

                $where[] = ['roll.school_id', 'in', $school_ids];
                if( isset($role_school['middle_auth']) ){
                    $res_data['middle_auth'] = $role_school['middle_auth'];
                }
                if( isset($role_school['region_auth']) ){
                    $res_data['region_auth'] = $role_school['region_auth'];
                }
                if( isset($role_school['school_auth']) ){
                    $res_data['school_auth'] = $role_school['school_auth'];
                }

                $data = Db::name('SysSchoolRoll')->alias('roll')
                    ->field([
                        'roll.id',
                        'roll.region_id',
                        'roll.school_id',
                        'roll.real_name',
                        'roll.student_code',
                        'roll.graduation_school',
                        'roll.graduation_school_code',
                        'roll.id_card',
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
                    $data['data'][$k]['detail'] = true;
                    $houseSchoolData = filter_value_one($school, 'id', $v['school_id']);
                    if (count($houseSchoolData) > 0){
                        $data['data'][$k]['school_name'] = $houseSchoolData['school_name'];
                        $data['data'][$k]['school_code'] = $houseSchoolData['school_code'];
                        $data['data'][$k]['school_type'] = $houseSchoolData['school_type'];
                        if($houseSchoolData['school_type'] == 2){
                            $data['data'][$k]['detail'] = false;
                        }
                    }
                }

                $data['select_region'] = !$role_school['bounded'];
                $data['resources'] = $res_data;

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
     * 学籍页面资源
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
                foreach ((array)$result['school_list'] as $k => $v){
                    if($v['school_type'] == 2){
                        $data['school_list'][] = $v;
                    }
                }
                $data['region_list'] = $result['region_list'];

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
        }
    }

    /**
     * 获取指定学籍信息
     * @param id 学籍ID
     * @return Json
     */
    public function getInfo()
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

                $data = $this->request->only([
                    'student_code',
                    'student_name',
                    'graduation_school_code',
                    'graduation_school',
                    'id_card',
                    'hash'
                ]);
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

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['idcard', '=', strtoupper($this->result['id_card'])];
                $child = Db::name('UserChild')->where($where)->find();
                if(!$child)
                {
                    throw new \Exception('身份证号对应学生不存在！');
                }


                /*$where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['student_code', '=', $this->result['student_code']];
                $school_sixth = Db::name('SixthGrade')->where($where)->find();
                if(!$school_sixth)
                {
                    throw new \Exception('个人标识码不存在！');
                }*/
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['student_code', '=', $this->result['student_code']];
                $school_roll = Db::name('SysSchoolRoll')->where($where)->find();
                if($school_roll)
                {
                    throw new \Exception('个人标识码已注册学籍！');
                }
                
               /* $graduation_school = Db::name('SysSchool')->where('school_code', $this->result['graduation_school_code'])->find();
                if(!$graduation_school)
                {
                    throw new \Exception('毕业学校标识码不存在！');
                }*/

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
                if($user_apply['signed'] == 0) {
                    throw new \Exception('学生申请信息没有入学报到！');
                }

                if($user_apply['school_type'] != 2) {
                    throw new \Exception('学生申请信息学校不是初中类型！');
                }

                //获取角色所管理的学校ID
                $school_res = $this->getSchoolIdsByRole();
                if($school_res['code'] == 0){
                    throw new \Exception($school_res['msg']);
                }
                if(!in_array($user_apply['result_school_id'],$school_res['school_ids'])){
                    throw new \Exception('本区无权限管理该学生');
                }



                $roll = [];
                $roll['real_name'] = $this->result['student_name'];
                $roll['region_id'] = $user_apply['region_id'];
                $roll['child_id'] = $child['id'];
                $roll['school_id'] = $user_apply['result_school_id'];
                $roll['student_code'] = $this->result['student_code'];
                $roll['card_type'] = 1;
                $roll['id_card'] = strtoupper($child['idcard']);
                $roll['graduation_school'] = $this->result['graduation_school'];
                $roll['graduation_school_code'] = $this->result['graduation_school_code'];
                $roll['registed'] = 1;

                $result = (new model())->addData($roll);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //学籍统计
                $result = $this->getSixthGradeStatistics($user_apply['result_school_id']);
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
                $data = $this->request->only([
                    'id',
                    'student_code',
                    'student_name',
                    'graduation_school_code',
                    'graduation_school',
                    'id_card',
                    'hash'
                ]);
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

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['idcard', '=', strtoupper($this->result['id_card'])];
                $child = Db::name('UserChild')->where($where)->find();
                if(!$child)
                {
                    throw new \Exception('身份证号对应学生不存在！');
                }


               /* $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['student_code', '=', $this->result['student_code']];
                $school_sixth = Db::name('SixthGrade')->where($where)->find();
                if(!$school_sixth)
                {
                    throw new \Exception('个人标识码不存在！');
                }*/
               /* $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['student_code', '=', $this->result['student_code']];
                $school_roll = Db::name('SysSchoolRoll')->where($where)->find();
                if($school_roll)
                {
                    throw new \Exception('个人标识码已注册学籍！');
                }*/

                /*$graduation_school = Db::name('SysSchool')->where('school_code', $this->result['graduation_school_code'])->find();
                if(!$graduation_school)
                {
                    throw new \Exception('毕业学校标识码不存在！');
                }*/

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
                if($user_apply['signed'] == 0) {
                    throw new \Exception('学生申请信息没有入学报到！');
                }

                //获取角色所管理的学校ID
                $school_res = $this->getSchoolIdsByRole();
                if($school_res['code'] == 0){
                    throw new \Exception($school_res['msg']);
                }
                if(!in_array($user_apply['result_school_id'],$school_res['school_ids'])){
                    throw new \Exception('本区无权限管理该学生');
                }

                $roll = [];
                $roll['real_name'] = $this->result['student_name'];
                $roll['region_id'] = $user_apply['region_id'];
                $roll['child_id'] = $child['id'];
                $roll['school_id'] = $user_apply['result_school_id'];
                $roll['student_code'] = $this->result['student_code'];
                $roll['card_type'] = 1;
                $roll['id_card'] = strtoupper($child['idcard']);
                $roll['graduation_school'] = $this->result['graduation_school'];
                $roll['graduation_school_code'] = $this->result['graduation_school_code'];
                $roll['registed'] = 1;
                $roll['id'] = $data['id'];

                $result = (new model())->editData($roll);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //学籍统计
                $result = $this->getSixthGradeStatistics($user_apply['result_school_id']);
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

                //  如果数据不合法，则返回
                if (intval($data['id']) <= 0) {
                    throw new \Exception('学籍ID参数错误！');
                }

                $info = (new model())->where('id',$data['id'])->where('deleted',0)->find();
                if(!$info){
                    throw new \Exception('学籍信息不存在');
                }
                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //学籍统计
                  $result = $this->getSchoolRollStatistics($info['school_id']);
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
    public function import()
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
                    $check_res = $this->checkMiddleTemplate($objPHPExcel);
                    if ($check_res['code'] == 0) {
                        throw new \Exception($check_res['msg']);
                    }
                    //循环读取excel表格，整合成数组。model

                    $hasCodeData = Db::name('SysSchoolRoll')->where('student_code', '<>', '')
                        ->where('deleted',0)->column('school_id','student_code');

                    //循环读取excel表格，整合成数组。model
                    $hasIdCardData = Db::name('SysSchoolRoll')->where('id_card', '<>', '')
                        ->where('deleted',0)->column('school_id','id_card');
                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的学籍学生信息。
                    $data = [];
                    $repeat = [];
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

                    // 过滤上传数据中重复的身份证号、学籍号
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

//                        $where = [];
//                        $where[] = ['deleted', '=', 0];
//                        $where[] = ['student_code', '=', $item['student_code']];
//                        $school_sixth = Db::name('SixthGrade')->where($where)->find();
//                        if(!$school_sixth)
//                        {
//                            $error[] = '第' . $row . '行个人标识码为【' . $item['student_code'] . '】的信息不存在';
//                            continue;
//                        }

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
                            ['deleted','=',0], ['disabled', '=', 0] ,['school_type','=',2] ])->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行录取学校标识码为【' . $item['school_code'] . '】的学校不存在';
                            continue;
                        }
                        if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                            $error[] = '第' . $row . '行学校标识码为【' . $item['school_code'] . '】无权限管理';
                            continue;
                        }

                        /*if($school['school_type'] == 1){
                            $error[] = '第' . $row . '行录取学校标识码为【' . $item['school_code'] . '】的学校类型不正确';
                            continue;
                        }*/

                        $graduation_school = Db::name('SysSchool')->where([['school_code', '=', $item['graduation_school_code']],
                            ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
//                        if(!$graduation_school){
//                            $error[] = '第' . $row . '行毕业学校标识码为【' . $item['graduation_school_code'] . '】的学校不存在';
//                            continue;
//                        }
                        if($repeat_data_student_code[$item['student_code']] > 1) {
                            $error[] = '第' . $row . '行【个人标识码' . $item['student_code'] . '】重复';
                            continue;
                        }

                        if($repeat_data_card[$item['id_card']] > 1) {
                            $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】重复';
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

                        $user_apply_id = Db::name('UserApplyDetail')->where('deleted', 0)->where('child_idcard', $item['id_card'])->value('user_apply_id');
                        if(!$user_apply_id) {
                            $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】申请详细信息不存在';
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
                            'graduation_school' => isset($graduation_school['school_name']) ? $graduation_school['school_name'] : '' ,
                            'student_code' => $item['student_code'],
                            'registed' => 1,
                        ];

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
                    Cache::set('importError_junior_school_roll_'.$this->userInfo['manage_id'], $error);

                    $res = ['code' => 1, 'data' => $msg];

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

    /**
     * 导入错误信息
     * @return Json
     */
    public function getImportError(){
        if ($this->request->isPost()) {
            try {
                $errData = Cache::get('importError_junior_school_roll_'.$this->userInfo['manage_id']);
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
    public function export()
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

                if (isset($this->userInfo['relation_region']) ){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['roll.region_id', 'in', $region_ids];
                }

                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0){
                    throw new \Exception($role_school['msg']);
                }
                $school_ids = [];
                if($role_school['school_ids'] ){
                    $school_ids = Db::name("SysSchool")
                        ->where('id', 'in', $role_school['school_ids'])->where('school_type', 2)
                        ->where('deleted', 0)->where('disabled', 0)->column('id');
                }
                $where[] = ['roll.school_id', 'in', $school_ids];

                $list = Db::name('SysSchoolRoll')->alias('roll')
                    ->field('roll.*')
                    ->where($where)
                    ->order('roll.id desc')
                    ->select()->toArray();

                $school = Cache::get('school');
                $data = [];
                foreach ($list as $k => $v){
                    $data[$k]['id'] = $v['id'];
                    $data[$k]['student_code'] = $v['student_code'];
                    $data[$k]['real_name'] = $v['real_name'];
                    $data[$k]['id_card'] = $v['id_card'];
                    $data[$k]['graduation_school_code'] = $v['graduation_school_code'];
                    $data[$k]['school_code'] = '';
                    $schoolData = filter_value_one($school, 'id', $v['school_id']);
                    if (count($schoolData) > 0){
                        $data[$k]['school_code'] = $schoolData['school_code'];
                    }
                    $data[$k]['school_type_name'] = '初中';
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = ['编号', '个人标识码', '学生姓名', '身份证号', '毕业学校标识码', '录取学校标识码', '教育阶段'];

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
                $this->excelExport('初中学籍信息_', $headArr, $data);
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
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @author Mr.Lv   3063306168@qq.com
     */
    private function excelExport($fileName = '', $headArr = [], $data = [])
    {

        $fileName .= "_" . date("Y_m_d", time());
        $spreadsheet = new Spreadsheet();
        $objPHPExcel = $spreadsheet->getActiveSheet();
        $firstColumn = 'A';// 设置表头

        $i = 0;
        foreach ($headArr as $k => $v) {
            $key = floor($i / 26);
            $firstNum = '';
            if ($key > 0) {
                # 当$k等于1,第一个列标签还是A,所以需要减去1
                $firstNum = chr(ord($firstColumn) + $key - 1);
            }
            $secondKey = $i % 26;
            $secondNum = chr(ord($firstColumn) + $secondKey);
            $column = $firstNum . $secondNum;

            $objPHPExcel->setCellValue($column . '1', $v);
            $i++;
        }
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $spreadsheet->getActiveSheet()->getStyle('B')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(40);
        $spreadsheet->getActiveSheet()->getStyle('D')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(40);
        $spreadsheet->getActiveSheet()->getStyle('E')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(30);
        $spreadsheet->getActiveSheet()->getStyle('F')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(30);

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

                $str = $span . $column . "\n" . json_encode($value) . "\n" . "算你狠！！！";
                file_put_contents('test.log', $str, FILE_APPEND);
                if($keyName == 'id_card' || $keyName == 'student_code' || $keyName == 'school_code'
                    || $keyName == 'graduation_school_code'){
                    //$objPHPExcel->setCellValue($span . $column, $value);
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
                $school_ids = Db::name("SysSchool")
                    ->where('school_type', 2)
                    ->where('deleted', 0)->where('disabled', 0)->column('id');
                //批复学位
                $where = [];
                //$where[] = ['plan_id', '=', $plan_id];
                $where[] = ['deleted', '=', 0];
                $where[] = ['status', '=', 1];
                $where[] = ['school_id', 'in', $school_ids];
                $spare_total = Db::name('PlanApply')->where($where)->sum('spare_total');
                $data['spare_total'] = $spare_total;

                //学籍注册量
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['registed', '=', 1];
                $where[] = ['school_id', 'in', $school_ids];
                $school_roll_total = Db::name('SysSchoolRoll')->where($where)->count();
                $data['school_roll_total'] = $school_roll_total;

                //录取人数
                $where = [];
                $where[] = ['a.result_school_id', '>', 0];
                $where[] = ['a.deleted','=',0];
                $where[] = ['a.voided', '=', 0];//没有作废
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.result_school_id', 'in', $school_ids];
                $admission_total = Db::name('UserApply')->alias('a')->where($where)->count();
                $data['admission_total'] = $admission_total;

                //未注册人数
                $unregistered_total = $admission_total - $school_roll_total;
                $unregistered_total = $unregistered_total < 0 ? 0 : $unregistered_total;
                $data['unregistered_total'] = $unregistered_total;

            }else {
                //区县、教管会、学校
                $role_school = $this->getSchoolIdsByRole();//不含教学点
                if($role_school['code'] == 0) {
                    throw new \Exception($role_school['msg']);
                }
                //小学学校ID
                $school_ids = Db::name("SysSchool")
                    ->where('school_type', 2)->where('id', 'in', $role_school['school_ids'])
                    ->where('deleted', 0)->where('disabled', 0)->column('id');
                //批复学位
                $where = [];
                //$where[] = ['plan_id', '=', $plan_id];
                $where[] = ['deleted', '=', 0];
                $where[] = ['status', '=', 1];
                $where[] = ['school_id', 'in', $school_ids];
                $spare_total = Db::name('PlanApply')
                    ->where($where)
                    ->where('status', 1)->where('deleted', 0)->sum('spare_total');
                $data['spare_total'] = $spare_total;

                //学籍注册量
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['registed', '=', 1];
                $where[] = ['school_id', 'in', $school_ids ];
                $school_roll_total = Db::name('SysSchoolRoll')->where($where)->count();
                $data['school_roll_total'] = $school_roll_total;

                //录取人数
                $where = [];
                $where[] = ['a.result_school_id', 'in', $school_ids ];
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