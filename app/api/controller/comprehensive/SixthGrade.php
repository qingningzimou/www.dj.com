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
use app\common\model\SixthGrade as model;
use app\common\validate\comprehensive\SixthGrade as validate;
use think\facade\Db;
use think\facade\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SixthGrade extends Education
{
    /**
     * 按分页获取六年级信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                $where = [];
                $where[] = ['grade.deleted','=',0];
                $where[] = ['school.deleted','=',0];
                $where[] = ['school.disabled','=',0];

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['id_card|student_name|student_code|grade.address|grade.police_name','like', '%' . $this->result['keyword'] . '%'];
                }

                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['grade.region_id','=', $this->result['region_id']];
                }
                if($this->request->has('school_id') && $this->result['school_id'] > 0)
                {
                    $where[] = ['grade.graduation_school_id','=', $this->result['school_id']];
                }
                if($this->request->has('school_attr') && $this->result['school_attr'])
                {
                    $where[] = ['school.school_attr','=', $this->result['school_attr']];
                }
                /*if($this->request->has('police_id') && $this->result['police_id'])
                {
                    $where[] = ['grade.police_id','=', $this->result['police_id']];
                }*/
                if($this->request->has('school_type') && $this->result['school_type'])
                {
                    $where[] = ['school.school_type','=', $this->result['school_type']];
                }
                if (isset($this->userInfo['relation_region']) ){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['school.region_id', 'in', $region_ids];
                }

                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0){
                    throw new \Exception($role_school['msg']);
                }
                if($role_school['bounded']  ){
                    $where[] = ['school.id', 'in', $role_school['school_ids']];
                }
                if( isset($role_school['middle_auth']) ){
                    $res_data['middle_auth'] = $role_school['middle_auth'];
                }
                if( isset($role_school['region_auth']) ){
                    $res_data['region_auth'] = $role_school['region_auth'];
                }
                if( isset($role_school['school_auth']) ){
                    $res_data['school_auth'] = $role_school['school_auth'];
                }

                $data = Db::name('sixth_grade')->alias('grade')
                    ->join([
                        'deg_sys_school' => 'school'
                    ], 'school.id = grade.graduation_school_id')
                    ->join([
                        'deg_sys_region' => 'region'
                    ], 'region.id = grade.region_id')
                    /*->join([
                        'deg_sys_police_station' => 'station'
                    ], 'station.id = grade.police_id')*/
                    ->field([
                        'grade.id',
                        'grade.police_name',
                        'region.region_name',
                        'school.school_name',
                        'grade.student_name',
                        //'station.name' => 'police_name',
                        'grade.id_card',
                        'grade.address',
                        'grade.student_code',
                    ])
                    ->where($where)
                    ->order('grade.id desc')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

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
     * 获取下拉列表信息 派出所、区县、学校
     * @return Json
     */
    public function getSelectList()
    {
        if ($this->request->isPost()) {
            $result = $this->getAllSelectByRoleList();

            $code = $result['code'];
            unset($result['plan_list']);
            unset($result['code']);
            unset($result['police_list']);//六年级信息 取所有派出所信息

            $result['police_list'] = Db::name('sys_police_station')
                ->field(['id', 'name',])->where('deleted', 0)
                ->order('id', 'desc')->select()->toArray();

            foreach ((array)$result['school_list'] as $key => $val){
                //移除初中学校
                if($val['school_type'] == 2){
                    unset($result['school_list'][$key]);
                }
            }

            $res = [
                'code' => $code,
                'data' => $result,
            ];
            return parent::ajaxReturn($res);
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    /**
     * 获取指定六年级信息
     * @param id 六年级ID
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
                $data = (new model())->where('id', $this->result['id'])
                    ->where('deleted',0)
                    ->hidden(['deleted'])->find();
                if(!$data){
                    throw new \Exception($checkData['学生信息不存在']);
                }
                $data['school_id'] = $data['graduation_school_id'];
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
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $_res = $this->getRegionSchool();
                if($_res['code'] == 0){
                    throw new \Exception($_res['msg']);
                }
                $data = $this->request->only([
                    'id_card',
                    'student_name',
                    'police_name',
                    'region_id',
                    'student_code',
                    'graduation_school_id' => $_res['school_id'],
                    'address',
                    'hash'
                ]);

                if(!$data['region_id']){
                    $data['region_id'] = $_res['region_id'];
                }

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

                unset($data['hash']);
                $data['id_card'] = strtoupper($data['id_card']);
                $result = (new model())->addData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //六年级统计
                $result = $this->getSixthGradeStatistics($data['graduation_school_id']);
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
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $_res = $this->getRegionSchool();
                if($_res['code'] == 0){
                    throw new \Exception($_res['msg']);
                }
                $data = $this->request->only([
                    'id',
                    'id_card',
                    'police_name',
                    'student_name',
                    'region_id' => $_res['region_id'],
                    'student_code',
                    'graduation_school_id' => $_res['school_id'],
                    'address',
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

                unset($data['hash']);
                $data['id_card'] = strtoupper($data['id_card']);
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
     * 删除
     * @return Json
     */
    public function actDelete()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'deleted' => 1
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'delete');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //六年级统计
                $school_id = Db::name('SixthGrade')->where('id', $data['id'])->value('graduation_school_id');
                $result = $this->getSixthGradeStatistics($school_id);
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
     * 导入六年级信息
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

                if (/*$fileExtendName != 'csv' &&*/ $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    /*if ($fileExtendName == 'csv') {
                        $objReader = IOFactory::createReader('Csv');
                    } else*/if ($fileExtendName == 'xls') {
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
                    if($check_res['code'] == 0){
                        throw new \Exception($check_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的六年级学生信息。
                    $data = [];
                    $repeat = [];

                    //循环读取excel表格，整合成数组。model
                    $hasIdCardData = (new model())->where('id_card', '<>', '')
                        ->where('deleted',0)->column('graduation_school_id','id_card');
                    $hasCodeData = (new model())->where('student_code', '<>', '')
                        ->where('deleted',0)->column('graduation_school_id','student_code');

                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    for ($j = 3; $j <= $highestRow; $j++) {
                        $student_name = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                        $student_code = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();
                        $school_name = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();
                        $police_name = $objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue();
                        $address = $objPHPExcel->getActiveSheet()->getCell("G" . $j)->getValue();

                        $id_card_type = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();
                        $id_card = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();

                        //if($student_name == '' && $student_code == '' && $school_name == '' && $police_name == '' && $address == '') continue;

                        $tmp[$j - 3] = [
                            'student_name' => trim($student_name,"\0\t\n\x0B\r "),
                            'id_card_type' => trim($id_card_type,"\0\t\n\x0B\r "),
                            'id_card' => strtoupper(trim($id_card,"\0\t\n\x0B\r ")),
                            'id_card_original' => $id_card,
                            'student_code' => trim($student_code,"\0\t\n\x0B\r "),
                            'school_name' => trim($school_name,"\0\t\n\x0B\r "),
                            'police_name' => trim($police_name,"\0\t\n\x0B\r "),
                            'address' => trim($address,"\0\t\n\x0B\r "),
                        ];
                        array_push($data, $tmp[$j - 3]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的身份证号、学籍号
                    $id_card_data = array_column($data,'id_card');
                    $id_card_data = array_filter($id_card_data);
                    $repeat_data_card = array_count_values($id_card_data);

                    $student_code_data = array_column($data,'student_code');
                    $student_code_data = array_filter($student_code_data);
                    $repeat_data_code = array_count_values($student_code_data);

                    $successNum = 0;
                    foreach ($data as $key=>$item) {
                        $row = $key + 3;
                        if($item['student_name'] == ''){
                            $error[] = '第' . $row . '行学生姓名为空';
                            continue;
                        }

                        $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                        if (preg_match($preg,$item['student_name']) == 0){
                            $error[] = '第' . $row . '行学生姓名只能为汉字';
                            continue;
                        }
                        if($item['student_code'] == ''){
                            $error[] = '第' . $row . '行学生学籍号为空';
                            continue;
                        }
                        if(strlen($item['student_code'] ) > 24){
                            $error[] = '第' . $row . '行学生学籍号超过24个字符';
                            continue;
                        }
                        if($item['school_name'] == ''){
                            $error[] = '第' . $row . '行毕业学校为空';
                            continue;
                        }
                        /*if($item['police_name'] == ''){
                            $error[] = '第' . $row . '行户口所在派出所不能为空';
                            continue;
                        }
                        if($item['address'] == ''){
                            $error[] = '第' . $row . '行住址为空';
                            continue;
                        }*/

                        /*$preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                        if (preg_match($preg,$item['police_name']) == 0){
                            $error[] = '第' . $row . '行派出所名称只能为汉字';
                            continue;
                        }*/

                        //$school_res['bounded'] ? $where = ['directly', '=', 0] : $where = [];
                        $school = Db::name('sys_school')
                            ->where([['school_name', '=', $item['school_name']],
                            ['deleted','=',0], ['disabled', '=', 0] ])
                            ->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】不存在';
                            continue;
                        }
                        if($school['school_type'] == 2){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】类型为初中';
                            continue;
                        }
                        if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】无权限管理';
                            continue;
                        }
                        if($item['id_card_type'] != '居民身份证' && $item['id_card_type'] != '护照/无身份证'){
                            $error[] = '第' . $row . '行身份证类型填写错误，请填写【居民身份证】或【护照/无身份证】';
                            continue;
                        }
                        if($item['id_card_type'] == '居民身份证') {
                            $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                            if (preg_match($preg, $item['id_card']) == 0) {
                                $error[] = '第' . $row . '行身份证格式为【' . $item['id_card_original'] . '】不正确';
                                continue;
                            }
                        }
                        if($repeat_data_card && $item['id_card'] && $repeat_data_card[$item['id_card']] > 1) {
                            $error[] = '第' . $row . '行身份证号为【' . $item['id_card_original'] . '】重复';
                            continue;
                        }

                        if( in_array($item['id_card'], array_keys($hasIdCardData)) ){
                            //$error[] = '第' . $row . '行身份证号为【' . $item['id_card'] . '】已存在';
                            if(in_array($hasIdCardData[$item['id_card']], $school_res['school_ids'])){
                                $editData = [
                                    'region_id' => $school['region_id'],
                                    'police_name' => $item['police_name'] ?? '',
                                    'student_name' => $item['student_name'],
                                    'id_card' => $item['id_card'],
                                    'student_code' => $item['student_code'],
                                    'graduation_school_id' => $school['id'],
                                    'address' => $item['address'] ?? '',
                                ];
                                (new model())->editData($editData,['id_card'=>$item['id_card']]);
                            }else{
                                $error[] = '第' . $row . '行身份证号为【' . $item['id_card_original'] . '】已在其他学校导入';
                            }
                            continue;
                        }

                        if($repeat_data_code[$item['student_code']] > 1) {
                            $error[] = '第' . $row . '行学籍号为【' . $item['student_code'] . '】重复';
                            continue;
                        }

                        if( in_array($item['student_code'], array_keys($hasCodeData)) ){
                            //$error[] = '第' . $row . '行学籍号为【' . $item['student_code'] . '】已存在';
                            if(in_array($hasCodeData[$item['student_code']], $school_res['school_ids'])){
                                $editData = [
                                    'region_id' => $school['region_id'],
                                    'police_name' => $item['police_name'] ?? '',
                                    'student_name' => $item['student_name'],
                                    'id_card' => $item['id_card'],
                                    'student_code' => $item['student_code'],
                                    'graduation_school_id' => $school['id'],
                                    'address' => $item['address'] ?? '',
                                ];
                                (new model())->editData($editData,['student_code'=>$item['student_code']]);
                            }else{
                                $error[] = '第' . $row . '行学籍号为【' . $item['student_code'] . '】已在其他学校导入';
                            }
                            continue;
                        }

                        $sixth_data = [
                            'region_id' => $school['region_id'],
                            'police_name' => $item['police_name'] ?? '',
                            'student_name' => $item['student_name'],
                            'id_card' => $item['id_card'],
                            'student_code' => $item['student_code'],
                            'graduation_school_id' => $school['id'],
                            'address' => $item['address'] ?? '',
                        ];
                        $result = (new model())->addData($sixth_data);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }

                        //六年级统计
                        $result = $this->getSixthGradeStatistics($school['id']);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }

                        $successNum++;
                    }
                    $error[] = '成功导入'.$successNum.'条数据';
                    $msg['success_num'] = $successNum;
                    $msg['repeat_num'] = count($repeat);

                    Cache::set('repeat', $repeat);
                    Cache::set('importError_sixthGrade_'.$this->userInfo['manage_id'], $error);

                    $res = [
                        'code' => 1,
                        'data' => $msg
                    ];
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
                $errData = Cache::get('importError_sixthGrade_'.$this->userInfo['manage_id']);
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
     * 六年级信息导出
     * @return Json
     */
    public function export()
    {
        if($this->request->isPost())
        {
            try {
                $where = [];
                $where[] = ['grade.deleted','=',0];
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['id_card|student_name|student_code|grade.address','like', '%' . $this->result['keyword'] . '%'];
                }

                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['grade.region_id','=', $this->result['region_id']];
                }
                if($this->request->has('school_id') && $this->result['school_id'] > 0)
                {
                    $where[] = ['grade.graduation_school_id','=', $this->result['school_id']];
                }
                if($this->request->has('school_attr') && $this->result['school_attr'])
                {
                    $where[] = ['school.school_attr','=', $this->result['school_attr']];
                }
                /*if($this->request->has('police_id') && $this->result['police_id'])
                {
                    $where[] = ['grade.police_id','=', $this->result['police_id']];
                }*/
                if($this->request->has('school_type') && $this->result['school_type'])
                {
                    $where[] = ['school.school_type','=', $this->result['school_type']];
                }
                if (isset($this->userInfo['relation_region']) ){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['school.region_id', 'in', $region_ids];
                }
                /*
                if($this->userInfo['grade_id'] < $this->city_grade){
                        //学校权限
                        if($this->userInfo['grade_id'] == $this->school_grade){
                            $school_id = $this->userInfo['school_id'];
                            if(!$school_id){
                                throw new \Exception('学校账号的学校ID错误');
                            }
                            $where[] = ['school.id','=', $school_id];
                        }else{
                            if ( $this->userInfo['department_id'] > 0 ) {
                                $department_id = $this->userInfo['department_id'];
                                $where[] = ['school.department_id','=', $department_id];
                                $where[] = ['school.directly', '=', 0];//不是市直学校
                            }else{
                                throw new \Exception('管理员所属部门机构为空');
                            }
                        }
                    }
                //公办、民办、小学、初中权限
                $school_where = $this->getSchoolWhere('school');
                $where[] = $school_where['school_attr'];
                $where[] = $school_where['school_type'];*/
                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0){
                    throw new \Exception($role_school['msg']);
                }
                if($role_school['bounded']  ){
                    $where[] = ['school.id', 'in', $role_school['school_ids']];
                }

                $data = Db::name('sixth_grade')->alias('grade')
                    ->join([
                        'deg_sys_school' => 'school'
                    ], 'school.id = grade.graduation_school_id')
                    ->join([
                        'deg_sys_region' => 'region'
                    ], 'region.id = school.region_id')
                    /*->join([
                        'deg_sys_police_station' => 'station'
                    ], 'station.id = grade.police_id')*/
                    ->field([
                        'grade.id',
                        'region.region_name',
                        'school.school_name',
                        'grade.student_name',
                        'grade.student_code',
                        //'station.name' => 'police_name',
                        'grade.police_name',
                        'grade.id_card',
                        'grade.address',
                    ])
                    ->where($where)
                    ->order('grade.id desc')
                    ->select()->toArray();

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = ['编号', '区县', '毕业学校', '学生姓名', '学籍号', '户籍所在派出所', '身份证号', '住址'];
                if(count($data) > 20000){
                    $total = count($data);
                    $count_excel = ceil($total / 20000);
                    for ($i = 0; $i < $count_excel; $i++){
                        $offset = $i * 20000;
                        $length = ($i + 1) * 20000;
                        if($i == ($count_excel - 1)){
                            $length = $total;
                        }
                        $data = array_slice($data, $offset, $length, true);
                        $this->excelExport('六年级信息_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('六年级信息_', $headArr, $data);
                }

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
        $key = ord("A"); // 设置表头

        foreach ($headArr as $v) {
            $colum = chr($key);
            $objPHPExcel->setCellValue($colum . '1', $v);
            //$spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
            $key += 1;
        }
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(70);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(30);

        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        $spreadsheet->getActiveSheet()->getStyle('G')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(50);

        $column = 2;
        foreach ($data as $key => $rows) { // 行写入
            $span = ord("A");
            foreach ($rows as $keyName => $value) { // 列写入
                if($keyName == 'id_card'){
                    //$objPHPExcel->setCellValue(chr($span) . $column, '\'' . $value);
                    $spreadsheet->getActiveSheet()->setCellValueExplicit(chr($span) . $column, $value, DataType::TYPE_STRING);
                }else{
                    $objPHPExcel->setCellValue(chr($span) . $column, $value);
                }
                $span++;
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

    private function checkTemplate($objPHPExcel): array
    {
        $title = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($title != '襄阳市小学六年级毕业生信息采集表（与国网学籍信息一致）') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        $student_name = $objPHPExcel->getActiveSheet()->getCell("A2")->getValue();
        if($student_name != '姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $id_card_type = $objPHPExcel->getActiveSheet()->getCell("B2")->getValue();
        if($id_card_type != '身份证类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $id_card = $objPHPExcel->getActiveSheet()->getCell("C2")->getValue();
        if($id_card != '身份证号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_code = $objPHPExcel->getActiveSheet()->getCell("D2")->getValue();
        if($student_code != '学籍号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_name = $objPHPExcel->getActiveSheet()->getCell("E2")->getValue();
        if($school_name != '毕业学校') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $police_name = $objPHPExcel->getActiveSheet()->getCell("F2")->getValue();
        if($police_name != '户口所在派出所') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $address = $objPHPExcel->getActiveSheet()->getCell("G2")->getValue();
        if($address != '住址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

}