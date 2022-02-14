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
                if (isset($this->userInfo['relation_region']) ){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['s.region_id', 'in', $region_ids];
                }

                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                    $where[] = ['s.school_id', '=', $school_id];
                } else {
                    throw new \Exception('学校管理员学校ID设置错误');
                }

                $data = Db::name('UserApplySigned')->alias('s')
//                    ->join([
//                        'deg_sys_school_roll_extend' => 'extend'
//                    ], 'roll.id = extend.school_roll_id', 'LEFT')
                    ->field([
                        's.*',
//                        'roll.region_id',
//                        'roll.school_id',
//                        'roll.real_name',
//                        'CASE roll.sex WHEN 1 THEN "男" WHEN 2 THEN "女" ELSE "未知" END' => 'sex',
//                        //'CONCAT( IF(school.school_type = 1, "一年级", "七年级"), roll.class_id, "班")' => 'class_name',
//                        'CONCAT(roll.grade_code, roll.class_code)' => 'class_name',
//                        'roll.birthday' => 'birthday',
//                        'CASE roll.card_type WHEN 1 THEN "身份证" WHEN 2 THEN "护照" ELSE "其他" END' => 'card_type',
//                        'roll.id_card' => 'id_card',
//                        'CONCAT(roll.nation, " ")' => 'nation',
//                        'roll.nationality' => 'nationality',
//                        'IF(roll.overseas = 1, "是", "否")' => 'overseas',
//                        'extend.birthplace_attr' => 'birthplace_attr',
//                        'extend.residence' => 'residence',
//                        'roll.current_address',
//                        'roll.student_code',
//                        'roll.graduation_school' => 'graduation_school',
//                        'roll.graduation_school_code',
                    ])
                    ->where($where)
                    ->order('s.id desc')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');
                $school = Cache::get('school');
                foreach ($data['data'] as $k => $v){
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }
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
//                $result = $this->getAllSelectByRoleList();
//                if($result['code'] == 0){
//                    throw new \Exception($result['msg']);
//                }
//                $data['school_list'] = $result['school_list'];
//                $data['region_list'] = $result['region_list'];

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
                                $error[] = '第' . $row . '行【身份证件号' . $item['id_card_original'] . '】已在本校导入';
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

                        //学籍基本信息
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
                    Cache::set('importError_school_signed_'.$this->userInfo['manage_id'], $error);

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
                $errData = Cache::get('importError_school_signed_'.$this->userInfo['manage_id']);
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

    //招生情况
    private function getRecruit(): array
    {
        try{
            $data = [];

            if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                $school_id = $this->userInfo['school_id'];
            } else {
                throw new \Exception('学校管理员学校ID设置错误');
            }

            //入学报到人数
            $where = [];
            $where[] = ['a.result_school_id', '=', $school_id ];
            $where[] = ['a.deleted','=',0];
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
            $where[] = ['school_id', '=', $school_id];
            $replenish_total = Db::name('UserApplySigned')->where($where)->count();
            $data['replenish_total'] = $replenish_total;

            return ['code' => 1, 'data' => $data];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }
    }

}