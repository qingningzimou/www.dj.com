<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\recruit;

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
use app\common\model\UserApplySigned as model;
use app\common\validate\comprehensive\SysSchoolRoll as validate;
use think\facade\Db;
use think\facade\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Signed extends Education
{
    /**
     * 按分页获取学籍信息
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['s.deleted','=',0];

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['s.child_idcard|s.child_name','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['s.region_id','=', $this->result['region_id']];
                }
                if($this->request->has('school_id') && $this->result['school_id'] > 0)
                {
                    $where[] = ['s.school_id','=', $this->result['school_id']];
                }
                if (isset($this->userInfo['relation_region']) ){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['s.region_id', 'in', $region_ids];
                }

                //获取角色所管理的学校ID
                $school_res = $this->getSchoolIdsByRole();
                if($school_res['code'] == 0){
                    throw new \Exception($school_res['msg']);
                }
                if($school_res['bounded'] ){
                    $where[] = ['s.school_id', 'in', $school_res['school_ids'] ];
                }

                $data = Db::name('UserApplySigned')->alias('s')
                    ->join([
                        'deg_user_apply' => 'a'
                    ], 'a.id = s.user_apply_id and a.deleted = 0 and a.voided = 0 ', 'LEFT')
                    ->field([
                        's.id',
                        's.child_name',
                        's.child_idcard' => 'id_card',
                        's.school_id',
                        's.region_id',
                        'IFNULL(a.region_id, 0)' => 'apply_region_id',
                        'IFNULL(a.school_type, 0)' => 'school_type',
                        'IFNULL(a.school_attr, 0)' => 'school_attr',
                        'IFNULL(a.result_school_id, 0)' => 'result_school_id',
                        'IFNULL(a.admission_type, 0)' => 'admission_type',
                        'IFNULL(a.apply_status, 0)' => 'apply_status',
                    ])
                    ->where($where)
                    ->order('s.id desc')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');
                $school = Cache::get('school');

                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = $result['data'];
                foreach ($data['data'] as $k => $v){
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0) {
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }
                    //入学报到学校信息
                    $schoolData = filter_value_one($school, 'id', $v['school_id']);
                    if (count($schoolData) > 0){
                        $data['data'][$k]['school_name'] = $schoolData['school_name'];
                        $data['data'][$k]['school_type'] = $schoolData['school_type'];
                        $data['data'][$k]['school_attr'] = $schoolData['school_attr'];
                    }
                    if($schoolData['school_type'] == 1){
                        $data['data'][$k]['school_type_name'] = '小学';
                    }
                    if($schoolData['school_type'] == 2){
                        $data['data'][$k]['school_type_name'] = '初中';
                    }
                    if($schoolData['school_attr'] == 1){
                        $data['data'][$k]['school_attr_name'] = '公办';
                    }
                    if($schoolData['school_attr'] == 2){
                        $data['data'][$k]['school_attr_name'] = '民办';
                    }
                    //申报区域
                    $data['data'][$k]['apply_region_name'] = '-';
                    if($v['region_id']) {
                        $applyRegionData = filter_value_one($region, 'id', $v['apply_region_id']);
                        if (count($applyRegionData) > 0) {
                            $data['data'][$k]['apply_region_name'] = $applyRegionData['region_name'];
                        }
                    }
                    //申报信息
                    $data['data'][$k]['apply_school_type_name'] = '-';
                    $data['data'][$k]['apply_school_attr_name'] = '-';
                    $data['data'][$k]['result_school_name'] = '-';
                    if($v['school_type'] == 1){
                        $data['data'][$k]['apply_school_type_name'] = '小学';
                    }
                    if($v['school_type'] == 2){
                        $data['data'][$k]['apply_school_type_name'] = '初中';
                    }
                    if($v['school_attr'] == 1){
                        $data['data'][$k]['apply_school_attr_name'] = '公办';
                    }
                    if($v['school_attr'] == 2){
                        $data['data'][$k]['apply_school_attr_name'] = '民办';
                    }
                    if($v['result_school_id']){
                        $resultSchoolData = filter_value_one($school, 'id', $v['result_school_id']);
                        if (count($resultSchoolData) > 0){
                            $data['data'][$k]['result_school_name'] = $resultSchoolData['school_name'];
                        }
                    }
                    $apply_status_name = isset($result['apply_status_list'][$v['apply_status']]) ? $result['apply_status_list'][$v['apply_status']] : '-';
                    $data['data'][$k]['apply_status_name'] = $apply_status_name;
                    $data['data'][$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'], $v['school_attr']);

                }

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

                //  如果数据不合法，则返回
                if ($data['id'] <= 0) {
                    throw new \Exception('入学报到ID参数错误！');
                }

                $result = (new model())->editData($data);
                if($result['code'] == 0){
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
//                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
//                    $school_id = $this->userInfo['school_id'];
//                    //$school = Db::name('SysSchool')->where('id', $school_id)->find();
//                } else {
//                    throw new \Exception('学校管理员学校ID设置错误！');
//                }
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
                    $check_res = $this->checkTemplate($objPHPExcel);
                    if ($check_res['code'] == 0) {
                        throw new \Exception($check_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的学籍学生信息。
                    $data = [];
                    $repeat = [];

                    //循环读取excel表格，整合成数组。model
                    $hasIdCardData = Db::name('UserApplySigned')->where('child_idcard', '<>', '')
                        ->where('deleted',0)->column('school_id','child_idcard');

                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    for ($j = 2; $j <= $highestRow; $j++) {
                        $real_name              = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();//姓名
                        $id_card                = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();//身份证件号
                        $school_name            = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();//录取学校

                        $tmp[$j - 2] = [
                            'real_name'                 => trim($real_name,"\0\t\n\x0B\r "),
                            'id_card'                   => strtoupper(ltrim($id_card,"\0\t\n\x0B\r ")),
                            'id_card_original'          => $id_card,
                            'school_name'               => trim($school_name,"\0\t\n\x0B\r "),
                        ];
                        array_push($data, $tmp[$j - 2]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的身份证号、学籍号
                    $id_card_data = array_column($data,'id_card');
                    $id_card_data = array_filter($id_card_data);
                    $repeat_data_card = array_count_values($id_card_data);

                    $successNum = 0;
                    $region = Cache::get('region');
                    foreach ($data as $key=>$item) {
                        $row = $key + 2;
                        //非空判断
                        if(true) {
                            if ($item['real_name'] == '') {
                                $error[] = '第' . $row . '行【姓名】为空';
                                continue;
                            }
                            if ($item['id_card'] == '') {
                                $error[] = '第' . $row . '行【身份证件号】为空';
                                continue;
                            }
                            if ($item['school_name'] == '') {
                                $error[] = '第' . $row . '行【录取学校】为空';
                                continue;
                            }
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

                        $school = Db::name('SysSchool')->where([['school_name', '=', $item['school_name']],
                            ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行录取学校为【' . $item['school_name'] . '】的学校不存在';
                            continue;
                        }
                        if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                            $error[] = '第' . $row . '行录取学校为【' . $item['school_name'] . '】无权限管理';
                            continue;
                        }

                        if( in_array($item['id_card'], array_keys($hasIdCardData)) ){
                            if($hasIdCardData[$item['id_card']] == $school['id'] ){
                                $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】已在录取学校导入';
                                continue;
                            }else {
                                $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】已在其他学校导入';
                                continue;
                            }
                        }

                        $where = [];
                        $where[] = ['a.deleted', '=', 0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['d.child_idcard', '=', $item['id_card'] ];
                        $user_apply = Db::name('UserApply')->alias('a')
                            ->field(['a.*'])
                            ->join([
                                'deg_user_apply_detail' => 'd'
                            ], 'd.child_id = a.child_id and d.deleted = 0', 'LEFT')
                            ->where($where)->find();
                        if($user_apply){
                            $region_name = "";
                            $regionData = filter_value_one($region, 'id', $user_apply['region_id']);
                            if (count($regionData) > 0){
                                $region_name = $regionData['region_name'];
                            }
                            if($user_apply['prepared'] == 1 && $user_apply['resulted'] == 1 ){
                                $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】已被' . $region_name . '录取，不能导入';
                                continue;
                            }
                        }

                        $signed = [
                            'region_id' => $school['region_id'],
                            'school_id' => $school['id'],
                            'child_name' => $item['real_name'],
                            'child_idcard' => $item['id_card'],
                            'user_apply_id' => isset($user_apply['id']) ? $user_apply['id'] : 0,
                        ];

                        //入学报到信息
                        $result = (new model())->addData($signed, 1);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }

                        $successNum++;
                    }
                    $error[] = '成功导入' . $successNum . '条数据';

                    $msg['success_num'] = $successNum;
                    $msg['repeat_num'] = count($repeat);

                    Cache::set('repeat', $repeat);
                    Cache::set('importError_education_signed_'.$this->userInfo['manage_id'], $error);

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
    public function getImportError(): Json
    {
        if ($this->request->isPost()) {
            try {
                $errData = Cache::get('importError_education_signed_'.$this->userInfo['manage_id']);
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
                $where[] = ['s.deleted','=',0];

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['s.child_idcard|s.child_name','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['s.region_id','=', $this->result['region_id']];
                }
                if (isset($this->userInfo['relation_region']) ){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['s.region_id', 'in', $region_ids];
                }

                //获取角色所管理的学校ID
                $school_res = $this->getSchoolIdsByRole();
                if($school_res['code'] == 0){
                    throw new \Exception($school_res['msg']);
                }
                if($school_res['bounded'] ){
                    $where[] = ['s.school_id', 'in', $school_res['school_ids'] ];
                }

                //填报监护人关系
                $relation_query = Db::name('UserFamily')->where('id', Db::raw('a.family_id'))
                    ->where('deleted', 0)->field(['relation'])->limit(1)->buildSql();
                //填报监护人姓名
                $parent_name = Db::name('UserFamily')->where('id', Db::raw('a.family_id'))
                    ->where('deleted', 0)->field(['parent_name'])->limit(1)->buildSql();
                //大数据对比派出所
                $child_query = Db::name('UserChild')->where('id', Db::raw('a.child_id'))
                    ->where('deleted', 0)->field(['api_policestation'])->limit(1)->buildSql();

                $data = Db::name('UserApplySigned')->alias('s')
                    ->join([
                        'deg_user_apply' => 'a'
                    ], 'a.id = s.user_apply_id and a.deleted = 0 and a.voided = 0 ', 'LEFT')
                    ->join([
                        'deg_user_house' => 'h'
                    ], 'a.house_id = h.id and h.deleted = 0 ', 'LEFT')
                    ->field([
                        's.id',
                        's.child_name',
                        's.child_idcard' => 'id_card',
                        's.school_id',
                        's.region_id',
                        'IFNULL(a.region_id, 0)' => 'apply_region_id',
                        'IFNULL(a.school_type, 0)' => 'school_type',
                        'IFNULL(a.school_attr, 0)' => 'school_attr',
                        'IFNULL(a.result_school_id, 0)' => 'result_school_id',
                        'IFNULL(a.admission_type, 0)' => 'admission_type',
                        'IFNULL(a.apply_status, 0)' => 'apply_status',
                        'h.house_address' => 'house_address',
                        'h.house_type' => 'house_type',
                        $child_query => 'student_api_policestation',
                        $relation_query => 'relation',
                        $parent_name => 'parent_name',
                    ])
                    ->where($where)
                    ->order('s.id desc')
                    ->select()->toArray();

                $region = Cache::get('region');
                $school = Cache::get('school');
                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = $result['data'];
                foreach ($data as $k => $v){
                    //录取学校区域
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0) {
                        $data[$k]['region_name'] = $regionData['region_name'];
                    }
                    //入学报到学校信息
                    $schoolData = filter_value_one($school, 'id', $v['school_id']);
                    if (count($schoolData) > 0){
                        $data[$k]['school_name'] = $schoolData['school_name'];
                        $data[$k]['school_type'] = $schoolData['school_type'];
                        $data[$k]['school_attr'] = $schoolData['school_attr'];
                    }
                    if($schoolData['school_type'] == 1){
                        $data[$k]['school_type_name'] = '小学';
                    }
                    if($schoolData['school_type'] == 2){
                        $data[$k]['school_type_name'] = '初中';
                    }
                    if($schoolData['school_attr'] == 1){
                        $data[$k]['school_attr_name'] = '公办';
                    }
                    if($schoolData['school_attr'] == 2){
                        $data[$k]['school_attr_name'] = '民办';
                    }
                    //申报区域
                    $data[$k]['apply_region_name'] = '-';
                    if($v['apply_region_id']) {
                        $regionData = filter_value_one($region, 'id', $v['apply_region_id']);
                        if (count($regionData) > 0) {
                            $data[$k]['apply_region_name'] = $regionData['region_name'];
                        }
                    }
                    //申报信息
                    $data[$k]['apply_school_type_name'] = '-';
                    $data[$k]['apply_school_attr_name'] = '-';
                    $data[$k]['result_school_name'] = '-';
                    if($v['school_type'] == 1){
                        $data[$k]['apply_school_type_name'] = '小学';
                    }
                    if($v['school_type'] == 2){
                        $data[$k]['apply_school_type_name'] = '初中';
                    }
                    if($v['school_attr'] == 1){
                        $data[$k]['apply_school_attr_name'] = '公办';
                    }
                    if($v['school_attr'] == 2){
                        $data[$k]['apply_school_attr_name'] = '民办';
                    }
                    if($v['result_school_id']){
                        $resultSchoolData = filter_value_one($school, 'id', $v['result_school_id']);
                        if (count($resultSchoolData) > 0){
                            $data[$k]['result_school_name'] = $resultSchoolData['school_name'];
                        }
                    }
                    $apply_status_name = isset($result['apply_status_list'][$v['apply_status']]) ? $result['apply_status_list'][$v['apply_status']] : '-';
                    $house_type_name = isset($result['house_type_list'][$v['house_type']]) ? $result['house_type_list'][$v['house_type']] : '-';
                    $fill_relation_name = isset($result['fill_relation_list'][$v['relation']]) ? $result['fill_relation_list'][$v['relation']] : '-';

                    $data[$k]['apply_status_name'] = $apply_status_name;
                    $data[$k]['house_type_name'] = $house_type_name;
                    $data[$k]['fill_relation_name'] = $fill_relation_name;

                    $data[$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'], $v['school_attr']);
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = [
                    'id' => '编号',
                    'child_name' => '姓名',
                    'id_card' => '身份证号',
                    'school_name' => '录取学校',
                    'region_name' => '录取学校区域',
                    'school_type_name' => '录取学校类型',
                    'school_attr_name' => '录取学校性质',
                    'apply_region_name' => '申请区域',
                    'apply_school_type_name' => '申请学校类型',
                    'apply_school_attr_name' => '申请学校性质',
                    'student_api_policestation' => '大数据对比派出所',
                    'parent_name' => '填报监护人姓名',
                    'fill_relation_name' => '填报监护人关系',
                    'house_type_name' => '房产类型',
                    'house_address' => '房产填报地址',
                    //'result_school_name' => '最终录取学校',
                    'admission_type_name' => '录取途径',
                    'apply_status_name' => '状态',
                ];

                $list = [];
                foreach($headArr as $key => $value){
                    foreach($data as $_key => $_value){
                        foreach($_value as $__key => $__value){
                            if($key == $__key){
                                $list[$_key][$__key] = $__value;
                            }
                        }
                    }
                }

                $data = $list;

                $this->excelExport('入学报到信息_', $headArr, $data);
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
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(60);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(30);

        $spreadsheet->getActiveSheet()->getStyle('C')->getNumberFormat()
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

                if($keyName == 'id_card' || $keyName == 'house_code' || $keyName == 'live_code' || $keyName == 'api_live_code' ){
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

    //模板检测
    private function checkTemplate($objPHPExcel): array
    {
        $real_name = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($real_name != '姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $id_card = $objPHPExcel->getActiveSheet()->getCell("B1")->getValue();
        if($id_card != '身份证号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_name = $objPHPExcel->getActiveSheet()->getCell("C1")->getValue();
        if($school_name != '录取学校') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

    //录取方式
    private function getAdmissionTypeName($type, $school_attr): string
    {
        $name = '-';
        if($school_attr) {
            switch ($type) {
                case 1:
                    if ($school_attr == 1) {
                        $name = '派位';
                    }
                    if ($school_attr == 2) {
                        $name = '摇号';
                    }
                    break;
                case 2:
                    $name = '线上审核';
                    break;
                case 3:
                    $name = '到校审核';
                    break;
                case 4:
                    $name = '派遣';
                    break;
                case 5:
                    $name = '调剂';
                    break;
                case 6:
                    $name = '政策生';
                    break;
                case 7:
                    $name = '自动录取';
                    break;
                case 8:
                    $name = '摸排';
                    break;
            }
        }
        return $name;
    }

    //招生情况
    private function getRecruit(): array
    {
        try{
            $data = [];

            //获取角色所管理的学校ID
            $school_res = $this->getSchoolIdsByRole();
            if($school_res['code'] == 0){
                throw new \Exception($school_res['msg']);
            }

            //入学报到人数
            $where = [];
            $where[] = ['a.result_school_id', 'in', $school_res['school_ids'] ];
            $where[] = ['a.deleted','=',0];
            $where[] = ['a.offlined','=',0];//线上录取
            $where[] = ['a.voided', '=', 0];//没有作废
            $where[] = ['a.prepared', '=', 1];//预录取
            $where[] = ['a.resulted', '=', 1];//录取
            $where[] = ['a.prepared', '=', 1];//预录取
            $where[] = ['a.signed', '=', 1];//入学报到
            $signed_total = Db::name('UserApply')->alias('a')->where($where)->count();
            $data['signed_total'] = $signed_total;

            //补充人数
            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['school_id', 'in', $school_res['school_ids'] ];
            $replenish_total = Db::name('UserApplySigned')->where($where)->count();
            $data['replenish_total'] = $replenish_total;

            return ['code' => 1, 'data' => $data];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }
    }

}