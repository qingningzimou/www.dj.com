<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\recruit;

use app\common\controller\Education;
use app\common\model\UserApplyDetail;
use app\common\model\UserApplyStatus;
use app\common\model\UserChild;
use app\common\model\UserFamily;
use app\common\model\UserHouse;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use app\common\model\UserApply as model;
use app\common\validate\recruit\Apply as validate;
use think\facade\Db;
use think\facade\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OfflineAdmission extends Education
{
    /**
     * 线下录取列表信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                $where = [];
                $where[] = ['a.deleted','=',0];
                $where[] = ['a.voided', '=', 0];//没有作废
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.offlined', '=', 1];//线下录取

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['c.idcard|c.real_name|c.mobile|s.school_name','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('school_type') && $this->result['school_type'] > 0)
                {
                    $where[] = ['a.school_type','=', $this->result['school_type']];
                }
                if($this->request->has('school_attr') && $this->result['school_attr'] > 0)
                {
                    $where[] = ['a.school_attr','=', $this->result['school_attr']];
                }
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $where[] = ['a.region_id', '=', $this->result['region_id'] ];
                    }
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['a.region_id', 'in', $region_ids];
                //权限学校
                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0){
                    throw new \Exception($role_school['msg']);
                }
                if($role_school['bounded']  ){
                    $where[] = ['a.result_school_id', 'in', $role_school['school_ids']];
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

                $data = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_sys_school' => 's'
                    ], 's.id = a.result_school_id')
                    ->join([
                        'deg_user_child' => 'c'
                    ], 'c.id = a.child_id')
                    ->join([
                        'deg_user_family' => 'f'
                    ], 'f.id = a.family_id')
                    ->join([
                        'deg_user_house' => 'h'
                    ], 'h.id = a.house_id', 'LEFT')

                    ->field([
                        'a.id',
                        'a.region_id',
                        'a.school_type',
                        'a.school_attr',
                        'c.real_name',
                        'c.kindgarden_name' => 'graduation_school_name',
                        'c.idcard' => 'student_id_card',
                        'c.mobile' => 'mobile',
                        'f.parent_name' => 'guardian_name',
                        'f.idcard' => 'guardian_id_card',
                        'h.house_type' => 'house_type',
                        'CASE h.house_type WHEN 1 THEN "产权房" WHEN 2 THEN "租房" WHEN 3 THEN "自建房" WHEN 4 THEN "置换房" WHEN 5 THEN "公租房" WHEN 6 THEN "三代同堂" ELSE "-" END' => 'house_type_name',
                        //'f.parent_name' => 'house_owner_name',
                        'IFNULL(h.house_address, "-")' => 'house_address',
                        'a.result_school_id',
                        's.school_name' => 'result_school_name',
                        'CASE a.signed WHEN 1 THEN "是" ELSE "否" END' => 'sign_name',
                        'CASE a.signed WHEN 1 THEN a.signe_time ELSE "-" END' => 'signe_time',
                    ])
                    ->where($where)
                    ->order('a.id desc')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');
                //$school = Cache::get('school');
                $dictionary = new FilterData();
                $typeData = $dictionary->resArray('dictionary','SYSXXLX');
                if(!$typeData['code']){
                    throw new \Exception($typeData['msg']);
                }
                $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
                if(!$attrData['code']){
                    throw new \Exception($attrData['msg']);
                }
                foreach ($data['data'] as $k => $v){
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }
                    /*$schoolData = filter_value_one($school, 'id', $v['result_school_id']);
                    if (count($schoolData) > 0){
                        $data['data'][$k]['result_school_name'] = $schoolData['school_name'];
                    }*/
                    $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $v['school_type']);
                    $data['data'][$k]['school_type_name'] = '';
                    if (count($schoolTypeData) > 0){
                        $data['data'][$k]['school_type_name'] = $schoolTypeData['dictionary_name'];
                    }
                    $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $v['school_attr']);
                    $data['data'][$k]['school_attr_name'] = '';
                    if (count($schoolAttrData) > 0){
                        $data['data'][$k]['school_attr_name'] = $schoolAttrData['dictionary_name'];
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
     * 获取下拉列表信息 派出所、区县、学校
     * @return Json
     */
    public function getSelectList(): Json
    {
        if ($this->request->isPost()) {
            $result = $this->getAllSelectByRoleList();
            if($result['code'] == 0){
                throw new \Exception($result['msg']);
            }
            foreach ($result['school_list'] as $item){
                if($item['onlined'] == 0){
                    $data['school_list'][] = $item;
                }
            }
            $data['region_list'] = $result['region_list'];

            $result = $this->getViewData();
            if($result['code'] == 0){
                throw new \Exception($result['msg']);
            }
            $result = $result['data'];
            foreach ($result['house_type_list'] as $k => $v){
                $data['house_type_list'][] = ['id' => $k, 'name' => $v];
            }

            $dictionary = new FilterData();
            $getData = $dictionary->resArray('dictionary', 'SYSXXLX');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            foreach ($getData['data'] as $value){
                if($this->userInfo['primary_school_status'] == 1 && $value['dictionary_value'] == 1) {
                    $data['school_type'][] = [
                        'id' => $value['dictionary_value'],
                        'type_name' => $value['dictionary_name']
                    ];
                }
                if($this->userInfo['junior_middle_school_status'] == 1 && $value['dictionary_value'] == 2) {
                    $data['school_type'][] = [
                        'id' => $value['dictionary_value'],
                        'type_name' => $value['dictionary_name']
                    ];
                }
            }
            $getData = $dictionary->resArray('dictionary', 'SYSXXXZ');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            foreach ($getData['data'] as $value){
                if($this->userInfo['public_school_status'] == 1 && $value['dictionary_value'] == 1) {
                    $data['school_attr'][] = [
                        'id' => $value['dictionary_value'],
                        'attr_name' => $value['dictionary_name']
                    ];
                }
                if($this->userInfo['civil_school_status'] == 1 && $value['dictionary_value'] == 2) {
                    $data['school_attr'][] = [
                        'id' => $value['dictionary_value'],
                        'attr_name' => $value['dictionary_name']
                    ];
                }
            }

            $res = [
                'code' => 1,
                'data' => $data,
            ];
            return parent::ajaxReturn($res);
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    /**
     * 线下录取详细信息
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
                $data = [];

                $apply = (new model())->where('id', $this->result['id'])->where('deleted', 0)->hidden(['deleted'])->find();
                if(!$apply){
                    throw new \Exception('无申请信息');
                }
                $child = (new UserChild())->where('id', $apply['child_id'])->where('deleted', 0)->find();
                if(!$child){
                    throw new \Exception('无学生信息');
                }
                $family = (new UserFamily())->where('id', $apply['family_id'])->where('deleted', 0)->find();
                if(!$family){
                    throw new \Exception('无监护人信息');
                }
                $house = (new UserHouse())->where('id', $apply['house_id'])->where('deleted', 0)->find();
                if(!$house){
                    throw new \Exception('无房产信息');
                }

                $data = [
                    'id' => $apply['id'],
                    'real_name' => $child['real_name'],
                    'graduation_school_name' => $child['kindgarden_name'],
                    'student_id_card' => $child['idcard'],
                    'mobile' => $child['mobile'],
                    'guardian_name' => $family['parent_name'],
                    'guardian_id_card' => $family['idcard'],
                    'house_type' => $house['house_type'],
                    'house_owner_name' => $family['parent_name'],
                    'house_address' => $house['house_address'],
                ];

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
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }
                $school = Db::name('SysSchool')->field('region_id, school_type, school_attr')->find($school_id);
                if(!$school){
                    throw new \Exception('管理员关联学校ID信息不存在');
                }

                $data = $this->request->only([
                    'real_name',
                    'student_id_card',
                    'guardian_name',
                    'guardian_id_card',
                    'mobile',
                    'house_type',
                    'house_owner_name',
                    'house_address',
                    'graduation_school_name',
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

                if($data['guardian_name'] != $data['house_owner_name'] ){
                    throw new \Exception('房产所有人和监护人不一致');
                }
                $id_card = strtoupper($data['student_id_card']);

                $exist = Db::name("UserChild")->where('idcard', $id_card)->where('deleted', 0)->count();
                if($exist > 0){
                    throw new \Exception('学生身份证号已存在！');
                }

                $child = [];
                $child['mobile'] = $data['mobile'];
                $child['real_name'] = $data['real_name'];
                $child['idcard'] = strtoupper($data['student_id_card']);
                $child['kindgarden_name'] = $data['graduation_school_name'];
                $result = (new UserChild())->addData($child, 1);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);

                }
                $child_id = $result['insert_id'];

                $family = [];
                $family['parent_name'] = $data['guardian_name'];
                $family['idcard'] = strtoupper($data['guardian_id_card']);
                $result = (new UserFamily())->addData($family, 1);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $family_id = $result['insert_id'];

                $house = [];
                $house['family_id'] = $family_id;
                $house['house_type'] = $data['house_type'];
                $house['house_address'] = $data['house_address'];
                $result = (new UserHouse())->addData($house, 1);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $house_id = $result['insert_id'];

                $apply = [
                    'enrol_year' => date('Y'),
                    'region_id' => $school['region_id'],
                    'school_attr' => $school['school_attr'],
                    'school_type' => $school['school_type'],
                    'child_id' => $child_id,
                    'family_id' => $family_id,
                    'house_id' => $house_id,
                    'prepared' => 1,
                    'resulted' => 1,
                    'offlined' => 1,
                    'public_school_id' => $school_id,
                    'result_school_id' => $school_id,
                ];

                $result = (new model())->addData($apply);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //中心学校录取统计
                $result = $this->getMiddleAdmissionStatistics($school_id);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                //线下录取统计
                $result = $this->getOfflineAdmissionStatistics($school_id);
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
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }
                $school = Db::name('SysSchool')->field('region_id, school_type, school_attr')->find($school_id);
                if(!$school){
                    throw new \Exception('管理员关联学校ID信息不存在');
                }

                $data = $this->request->only([
                    'id',
                    'real_name',
                    'student_id_card',
                    'guardian_name',
                    'guardian_id_card',
                    'mobile',
                    'house_type',
                    'house_owner_name',
                    'house_address',
                    'graduation_school_name',
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
                if($data['guardian_name'] != $data['house_owner_name'] ){
                    throw new \Exception('房产所有人和监护人不一致');
                }

                $apply = (new model())->where('id', $data['id'])->where('deleted', 0)->find();
                if (!$apply) {
                    throw new \Exception('无申请资料信息');
                }

                $child = [];
                $child['id'] = $apply['child_id'];
                $child['mobile'] = $data['mobile'];
                $child['real_name'] = $data['real_name'];
                $child['idcard'] = strtoupper($data['student_id_card']);
                $child['kindgarden_name'] = $data['graduation_school_name'];
                $result = (new UserChild())->editData($child);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $family = [];
                $family['id'] = $apply['family_id'];
                $family['parent_name'] = $data['guardian_name'];
                $family['idcard'] = strtoupper($data['guardian_id_card']);
                $result = (new UserFamily())->editData($family);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $house = [];
                $house['id'] = $apply['house_id'];
                $house['family_id'] = $apply['family_id'];
                $house['house_type'] = $data['house_type'];
                $house['house_address'] = $data['house_address'];
                $result = (new UserHouse())->editData($house);
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
     * 确认到校
     * @return Json
     */
    public function actConfirm(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                ]);
                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选录取的学生');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];

                $update['signed'] = 1;
                $update['signe_time'] = date('Y-m-d H:i:s', time());

                $result = (new model())->editData($update, $where);
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

                $model = new model();
                $apply = $model->find($data['id']);
                if (!$apply) {
                    throw new \Exception('无申请资料信息');
                }

                $result = $model->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                //删除学生信息
                $result = (new UserChild())->editData(['id' => $apply['child_id'], 'deleted' => 1]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                //删除监护人信息
                $result = (new UserFamily())->editData(['id' => $apply['family_id'], 'deleted' => 1]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                //删除房产信息
                $result = (new UserHouse())->editData(['id' => $apply['house_id'], 'deleted' => 1]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //中心学校录取统计
                $result = $this->getMiddleAdmissionStatistics($apply['result_school_id']);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                //线下录取统计
                $result = $this->getOfflineAdmissionStatistics($apply['result_school_id']);
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
     * 导入线下录取信息
     */
    public function import()
    {

        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                set_time_limit(0);
                ignore_user_abort(true);
                ini_set('max_execution_time', 0);
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
                    $check_res = $this->checkTemplate($objPHPExcel);
                    if($check_res['code'] == 0){
                        throw new \Exception($check_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的六年级学生信息。
                    $data = [];
                    $repeat = [];

                    //循环读取excel表格，整合成数组。model
                    $hasChildIdCardData = Db::name('UserChild')->where('idcard', '<>', '')
                        ->where('deleted',0)->column('id','idcard');
                    $hasFamilyIdCardData = Db::name('UserFamily')->where('idcard', '<>', '')
                        ->where('deleted',0)->column('id','idcard');

                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    for ($j = 3; $j <= $highestRow; $j++) {
                        $mobile = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                        $student_name = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();
                        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();
                        $guardian_name = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();
                        $guardian_id_card = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();
                        $house_type_name = $objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue();
                        $family_name = $objPHPExcel->getActiveSheet()->getCell("G" . $j)->getValue();
                        $house_address = $objPHPExcel->getActiveSheet()->getCell("H" . $j)->getValue();
                        $graduation_school_name = $objPHPExcel->getActiveSheet()->getCell("I" . $j)->getValue();
                        $result_school_name = $objPHPExcel->getActiveSheet()->getCell("J" . $j)->getValue();

                        //if($student_name == '' && $student_id_card == '' && $guardian_name == '' && $guardian_id_card == '' ) continue;

                        $tmp[$j - 3] = [
                            'mobile' => trim($mobile,' '),
                            'student_name' => trim($student_name,' '),
                            'student_id_card' => strtoupper(trim($student_id_card,' ')),
                            'student_id_card_original' => trim($student_id_card,' '),
                            'guardian_name' => trim($guardian_name,' '),
                            'guardian_id_card' => strtoupper(trim($guardian_id_card,' ')),
                            'guardian_id_card_original' => trim($guardian_id_card,' '),
                            'house_type_name' => trim($house_type_name,' '),
                            'family_name' => trim($family_name,' '),
                            'house_address' => strtoupper(trim($house_address,' ')),
                            'graduation_school_name' => trim($graduation_school_name,' '),
                            'result_school_name' => trim($result_school_name,' '),
                        ];
                        array_push($data, $tmp[$j - 3]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的身份证号、学籍号
                    $student_id_card_data = array_column($data,'student_id_card');
                    $student_id_card_data = array_filter($student_id_card_data);
                    $repeat_data_student = array_count_values($student_id_card_data);

                    $guardian_id_card_data = array_column($data,'guardian_id_card');
                    $guardian_id_card_data = array_filter($guardian_id_card_data);
                    $repeat_data_guardian = array_count_values($guardian_id_card_data);

                    $successNum = 0;
                    $apply_data = [];
                    $school_id_name = [];
                    $admission_school = [];
                    $offline_school_ids = [];
                    foreach ($data as $key=>$item) {
                        $row = $key + 3;
                        if($item['mobile'] == ''){
                            $error[] = '第' . $row . '行联系手机号码为空';
                            continue;
                        }
                        $preg = '/^1[3-9]\d{9}$/';
                        if (preg_match($preg, $item['mobile']) == 0){
                            $error[] = '第' . $row . '行联系手机号码格式错误';
                            continue;
                        }
                        if($item['student_name'] == ''){
                            $error[] = '第' . $row . '行学生姓名为空';
                            continue;
                        }
                        $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                        if (preg_match($preg, $item['student_name']) == 0){
                            $error[] = '第' . $row . '行学生姓名只能为汉字';
                            continue;
                        }
                        if($item['student_id_card'] == ''){
                            $error[] = '第' . $row . '行学生身份证号为空';
                            continue;
                        }
                        if($item['guardian_name'] == ''){
                            $error[] = '第' . $row . '行监护人姓名为空';
                            continue;
                        }
                        if (preg_match($preg, $item['guardian_name']) == 0){
                            $error[] = '第' . $row . '行监护人姓名只能为汉字';
                            continue;
                        }

                        if($item['guardian_id_card'] == ''){
                            $error[] = '第' . $row . '行监护人身份证号为空';
                            continue;
                        }
                        if($item['house_type_name'] == ''){
                            $error[] = '第' . $row . '行房产类型为空';
                            continue;
                        }
                        $house_type = $this->getHouseType($item['house_type_name']);
                        if($house_type == 0){
                            $error[] = '第' . $row . '行房产类型错误';
                            continue;
                        }
                        if($item['family_name'] == ''){
                            $error[] = '第' . $row . '行房产所有人不能为空';
                            continue;
                        }
//                        if($item['guardian_name'] !=$item['family_name'] ){
//                            $error[] = '第' . $row . '行房产所有人和监护人不一致';
//                            continue;
//                        }
                        if($item['house_address'] == ''){
                            $error[] = '第' . $row . '行房产地址不能为空';
                            continue;
                        }
                        if($item['graduation_school_name'] == ''){
                            $error[] = '第' . $row . '行毕业学校不能为空';
                            continue;
                        }
                        if($item['result_school_name'] == ''){
                            $error[] = '第' . $row . '行就读学校不能为空';
                            continue;
                        }

                        $school = Db::name('sys_school')->where([['school_name', '=', $item['result_school_name']],
                                ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行就读学校名称为【' . $item['result_school_name'] . '】不存在';
                            continue;
                        }
                        if($school['school_attr'] == 2){
                            $error[] = '第' . $row . '行就读学校名称为【' . $item['result_school_name'] . '】性质为【民办】';
                            continue;
                        }
                        if($school['onlined'] == 1){
                            $error[] = '第' . $row . '行就读学校名称为【' . $item['result_school_name'] . '】招生方式为【线上】';
                            continue;
                        }
                        if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                            $error[] = '第' . $row . '行就读学校名称为【' . $item['result_school_name'] . '】无权限管理';
                            continue;
                        }

                        $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                        if (preg_match($preg, $item['student_id_card']) == 0){
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】不正确';
                            continue;
                        }
                        if($repeat_data_student[$item['student_id_card']] > 1) {
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】重复';
                            continue;
                        }
                        if( in_array($item['student_id_card'], array_keys($hasChildIdCardData)) ){
                            $child_id = $hasChildIdCardData[$item['student_id_card']];
                            //$error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】已存在';
                            //continue;
                        }else{
                            $child = [];
                            $child['mobile'] = $item['mobile'];
                            $child['real_name'] = $item['student_name'];
                            $child['idcard'] = $item['student_id_card'];
                            $child['kindgarden_name'] = $item['graduation_school_name'];
                            $result = (new UserChild())->addData($child, 1);
                            if($result['code'] == 0){
                                $error[] = $result['msg'];
                                continue;
                            }
                            $child_id = $result['insert_id'];
                        }

                        if (preg_match($preg, $item['guardian_id_card']) == 0){
                            $error[] = '第' . $row . '行监护人身份证号为【' . $item['guardian_id_card_original'] . '】不正确';
                            continue;
                        }
                        /*if($repeat_data_guardian[$item['guardian_id_card']] > 1) {
                            $error[] = '第' . $row . '行监护人身份证号为【' . $item['guardian_id_card_original'] . '】重复';
                            continue;
                        }*/
                        if( in_array($item['guardian_id_card'], array_keys($hasFamilyIdCardData)) ){
                            $family_id = $hasFamilyIdCardData[$item['guardian_id_card']];
                            //$error[] = '第' . $row . '行监护人身份证号为【' . $item['guardian_id_card_original'] . '】已存在';
                            //continue;
                        }else{
                            $family = [];
                            $family['parent_name'] = $item['guardian_name'];
                            $family['idcard'] = $item['guardian_id_card'];
                            $result = (new UserFamily())->addData($family, 1);
                            if($result['code'] == 0){
                                $error[] = $result['msg'];
                                continue;
                            }
                            $family_id = $result['insert_id'];
                        }
                        //房产信息
                        $houseInfo = Db::name('UserHouse')->where('family_id', $family_id)->where('deleted', 0)->find();
                        if($houseInfo){
                            $house_id = $houseInfo['id'];
                        }else {
                            $house = [];
                            $house['family_id'] = $family_id;
                            $house['house_type'] = $house_type;
                            $house['house_address'] = $item['house_address'];
                            $result = (new UserHouse())->addData($house, 1);
                            if ($result['code'] == 0) {
                                $error[] = $result['msg'];
                                continue;
                            }
                            $house_id = $result['insert_id'];
                        }

                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['voided', '=', 0];//没有作废
                        $where[] = ['child_id', '=', $child_id];
                        $apply = Db::name('UserApply')->where($where)->find();
                        if($apply){
//                            if($apply['school_type'] != $school['school_type']){
//                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】填报学校类型与就读学校不一致';
//                                continue;
//                            }
//                            if($apply['school_attr'] != $school['school_attr']){
//                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】填报学校性质与就读学校不一致';
//                                continue;
//                            }
//                            if($apply['region_id'] != $school['region_id']){
//                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】已在其他区填报信息';
//                                continue;
//                            }
                            if($apply['region_id'] == $school['region_id'] && $apply['prepared'] == 1 && $apply['resulted'] == 1 ){
                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】已在本区录取';
                                continue;
                            }
                            if($apply['region_id'] == $school['region_id'] && $apply['prepared'] == 1 && $apply['resulted'] == 0){
                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】已在本区预录取';
                                continue;
                            }
                            $apply_data[] = [
                                'id' => $apply['id'],
                                'region_id' => $school['region_id'],
                                'school_attr' => $school['school_attr'],
                                'school_type' => $school['school_type'],
                                'child_id' => $child_id,
                                'family_id' => $family_id,
                                'house_id' => $house_id,
                                'prepared' => 1,
                                'resulted' => 1,
                                'signed' => 1,
                                'offlined' => 1,
                                'public_school_id' => $school['id'],
                                'result_school_id' => $school['id'],
                                'mobile' => $item['mobile'],
                                'student_name' => $item['student_name'],
                                'student_id_card' => $item['student_id_card'],
                                'house_type' => $house_type,
                                'apply_status' => 8,//公办入学报到
                            ];
                        }else {
                            $apply_data[] = [
                                'enrol_year' => date('Y'),
                                'region_id' => $school['region_id'],
                                'school_attr' => $school['school_attr'],
                                'school_type' => $school['school_type'],
                                'child_id' => $child_id,
                                'family_id' => $family_id,
                                'house_id' => $house_id,
                                'prepared' => 1,
                                'resulted' => 1,
                                'signed' => 1,
                                'offlined' => 1,
                                'public_school_id' => $school['id'],
                                'result_school_id' => $school['id'],
                                'mobile' => $item['mobile'],
                                'student_name' => $item['student_name'],
                                'student_id_card' => $item['student_id_card'],
                                'house_type' => $house_type,
                                'apply_status' => 8,//公办入学报到
                            ];
                        }

                        if(isset($admission_school[$school['id']])){
                            $admission_school[$school['id']] += 1;
                        }else{
                            $admission_school[$school['id']] = 1;
                        }
                        $school_id_name[$school['id']] = $school['school_name'];

                        if(!in_array($school['id'], $offline_school_ids)) {
                            $offline_school_ids[] = $school['id'];
                        }

                        /*//统计信息
                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['child_id', '=', $child_id];
                        $applyDetail = Db::name('UserApplyDetail')->where($where)->find();
                        if($applyDetail){
                            $detail = [];
                            $detail['id'] = $applyDetail['id'];
                            $detail['user_apply_id'] = $user_apply_id;
                            $detail['child_id'] = $child_id;
                            $detail['mobile'] = $item['mobile'];
                            $detail['child_name'] = $item['student_name'];
                            $detail['child_idcard'] = $item['student_id_card'];
                            $detail['school_attr'] = $school['school_attr'];
                            $detail['school_type'] = $school['school_type'];
                            $detail['house_type'] = $house_type;

                            $result = (new UserApplyDetail())->editData($detail);
                            if($result['code'] == 0){
                                $error[] = $result['msg'];
                                continue;
                            }
                        }else {
                            $detail = [];
                            $detail['user_apply_id'] = $user_apply_id;
                            $detail['child_id'] = $child_id;
                            $detail['mobile'] = $item['mobile'];
                            $detail['child_name'] = $item['student_name'];
                            $detail['child_idcard'] = $item['student_id_card'];
                            $detail['school_attr'] = $school['school_attr'];
                            $detail['school_type'] = $school['school_type'];
                            $detail['house_type'] = $house_type;
                            $result = (new UserApplyDetail())->addData($detail);
                            if ($result['code'] == 0) {
                                $error[] = $result['msg'];
                                continue;
                            }
                        }

                        //比对状态
                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['user_apply_id', '=', $user_apply_id];
                        $applyStatus = Db::name('UserApplyStatus')->where($where)->find();
                        if(!$applyStatus) {
                            $status = [];
                            $status['user_apply_id'] = $user_apply_id;
                            $result = (new UserApplyStatus())->addData($status);
                            if ($result['code'] == 0) {
                                $error[] = $result['msg'];
                                continue;
                            }
                        }

                        //中心学校录取统计
                        $result = $this->getMiddleAdmissionStatistics($school['id']);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }

                        //线下录取统计
                        $result = $this->getOfflineAdmissionStatistics($school['id']);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }*/

                        //$successNum++;
                    }

                    //公办线下录取数量
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['a.voided', '=', 0];//没有作废
                    $where[] = ['a.school_attr', '=', 1];
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.resulted', '=', 1];//录取
                    $where[] = ['a.offlined', '=', 1];//线下录取
                    $where[] = ['a.result_school_id', 'in', $offline_school_ids];//录取学校
                    $used = Db::name('UserApply')->alias('a')->group('result_school_id')
                        ->where($where)->column('COUNT(*)', 'result_school_id');

                    //公办审批学位数量
                    $degree = Db::name('PlanApply')
                        ->where('school_id', '>', 0)->where('status', 1)
                        ->where('deleted', 0)->where('school_id', 'in', $offline_school_ids)
                        ->group('school_id')->column('SUM(spare_total)', 'school_id');

                    foreach ($offline_school_ids as $k => $school_id){
                        $degree_count = isset($degree[$school_id]) ? $degree[$school_id] : 0;
                        $used_count = isset($used[$school_id]) ? $used[$school_id] : 0;
                        $admission_count = isset($admission_school[$school_id]) ? $admission_school[$school_id] : 0;

                        if( ($used_count + $admission_count) > $degree_count){
                            $error[] = '学校【' . $school_id_name[$school_id] . '】学位数量不足！';
                            unset($offline_school_ids[$k]);
                        }
                    }

                    foreach ($apply_data as $k => $item){
                        if(in_array($item['result_school_id'], $offline_school_ids)){
                            $child_id = $item['child_id'];
                            $mobile = $item['mobile'];
                            $student_name = $item['student_name'];
                            $student_id_card = $item['student_id_card'];
                            $house_type = $item['house_type'];

                            unset($item['mobile']);
                            unset($item['student_name']);
                            unset($item['student_id_card']);
                            unset($item['house_type']);

                            if(isset($item['id'])){
                                $result = (new model())->editData($item);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $user_apply_id = $item['id'];
                            }else{
                                $result = (new model())->addData($item, 1);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $user_apply_id = $result['insert_id'];
                            }


                            //统计信息
                            $where = [];
                            $where[] = ['deleted', '=', 0];
                            $where[] = ['child_id', '=', $child_id];
                            $applyDetail = Db::name('UserApplyDetail')->where($where)->find();
                            if($applyDetail){
                                $detail = [];
                                $detail['id'] = $applyDetail['id'];
                                $detail['user_apply_id'] = $user_apply_id;
                                $detail['child_id'] = $child_id;
                                $detail['mobile'] = $mobile;
                                $detail['child_name'] = $student_name;
                                $detail['child_idcard'] = $student_id_card;
                                $detail['school_attr'] = $item['school_attr'];
                                $detail['school_type'] = $item['school_type'];
                                $detail['house_type'] = $house_type;

                                $result = (new UserApplyDetail())->editData($detail);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                            }else {
                                $detail = [];
                                $detail['user_apply_id'] = $user_apply_id;
                                $detail['child_id'] = $child_id;
                                $detail['mobile'] = $mobile;
                                $detail['child_name'] = $student_name;
                                $detail['child_idcard'] = $student_id_card;
                                $detail['school_attr'] = $item['school_attr'];
                                $detail['school_type'] = $item['school_type'];
                                $detail['house_type'] = $house_type;
                                $result = (new UserApplyDetail())->addData($detail);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                            }

                            //比对状态
                            $where = [];
                            $where[] = ['deleted', '=', 0];
                            $where[] = ['user_apply_id', '=', $user_apply_id];
                            $applyStatus = Db::name('UserApplyStatus')->where($where)->find();
                            if(!$applyStatus) {
                                $status = [];
                                $status['user_apply_id'] = $user_apply_id;
                                $result = (new UserApplyStatus())->addData($status);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                            }

                            //中心学校录取统计
                            $result = $this->getMiddleAdmissionStatistics($item['result_school_id']);
                            if ($result['code'] == 0) {
                                throw new \Exception($result['msg']);
                            }

                            //线下录取统计
                            $result = $this->getOfflineAdmissionStatistics($item['result_school_id']);
                            if ($result['code'] == 0) {
                                throw new \Exception($result['msg']);
                            }

                            $successNum++;
                        }
                    }

                    $error[] = '成功导入' . $successNum . '条数据';

                    $msg['success_num'] = $successNum;
                    $msg['repeat_num'] = count($repeat);

                    Cache::set('repeat', $repeat);
                    Cache::set('importError_recruit_offlineAdmission_'.$this->userInfo['manage_id'], $error);

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
    public function getImportError(): Json
    {
        if ($this->request->isPost()) {
            try {
                $res = [
                    'code' => 1,
                    'data' => Cache::get('importError_recruit_offlineAdmission_'.$this->userInfo['manage_id']),
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
     * 线下录取信息导出
     * @return Json
     */
    public function export()
    {
        if($this->request->isPost())
        {
            try {
                $where = [];
                $where[] = ['a.deleted','=',0];
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.offlined', '=', 1];//线下录取

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['c.idcard|c.real_name|c.mobile','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('school_type') && $this->result['school_type'] > 0)
                {
                    $where[] = ['a.school_type','=', $this->result['school_type']];
                }
                if($this->request->has('school_attr') && $this->result['school_attr'] > 0)
                {
                    $where[] = ['a.school_attr','=', $this->result['school_attr']];
                }
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $where[] = ['a.region_id', '=', $this->result['region_id'] ];
                    }
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['a.region_id', 'in', $region_ids];
                //权限学校
                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0){
                    throw new \Exception($role_school['msg']);
                }
                if($role_school['bounded']  ){
                    $where[] = ['a.result_school_id', 'in', $role_school['school_ids']];
                }

                $data = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_child' => 'c'
                    ], 'c.id = a.child_id')
                    ->join([
                        'deg_user_family' => 'f'
                    ], 'f.id = a.family_id')
                    ->join([
                        'deg_user_house' => 'h'
                    ], 'h.id = a.house_id', 'LEFT')

                    ->field([
                        'a.id',
                        'a.region_id',
                        'c.real_name',
                        'c.idcard' => 'student_id_card',
                        'f.parent_name' => 'guardian_name',
                        'f.idcard' => 'guardian_id_card',
                        'c.mobile' => 'mobile',
                        'CASE h.house_type WHEN 1 THEN "产权房" WHEN 2 THEN "租房" WHEN 3 THEN "自建房" WHEN 4 THEN "置换房" WHEN 5 THEN "公租房" WHEN 6 THEN "三代同堂" ELSE "-" END' => 'house_type_name',
                        'f.api_name' => 'house_owner_name',
                        'IFNULL(h.house_address, "-")' => 'house_address',
                        'c.kindgarden_name' => 'graduation_school_name',
                        'a.signed' => 'signed',
                        'a.result_school_id' => 'result_school_id',
                    ])
                    ->where($where)
                    ->order('a.id desc')
                    ->select()->toArray();

                $school = Cache::get('school');
                $region = Cache::get('region');

                foreach ($data as $k => $v){
                    $data[$k]['house_owner_name'] = $v['guardian_name'];
                    $data[$k]['result_school_name'] = '-';
                    $data[$k]['signed_name'] = '否';
                    $resultSchoolData = filter_value_one($school, 'id', $v['result_school_id']);
                    if (count($resultSchoolData) > 0){
                        $data[$k]['result_school_name'] = $resultSchoolData['school_name'];
                    }
                    if ($v['signed'] > 0){
                        $data[$k]['signed_name'] = '是';
                    }
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data[$k]['region_name'] = $regionData['region_name'];
                    }
                    unset($data[$k]['signed']);
                    unset($data[$k]['region_id']);
                    unset($data[$k]['result_school_id']);
                }
                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = ['编号', '学生姓名', '学生身份证号', '监护人姓名', '监护人身份证号', '联系手机号', '房产类型', '房产所有人', '房产地址', '毕业学校', '就读学校', '入学报到状态', '申报区域'];
                if(count($data) > 100000){
                    $total = count($data);
                    $count_excel = ceil($total / 100000);
                    for ($i = 0; $i < $count_excel; $i++){
                        $offset = $i * 100000;
                        $length = ($i + 1) * 100000;
                        if($i == ($count_excel - 1)){
                            $length = $total;
                        }
                        $data = array_slice($data, $offset, $length, true);
                        $this->excelExport('线下录取明细_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('线下录取明细_', $headArr, $data);
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
    private function excelExport($fileName = '', $headArr = [], $data = []) {

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
        $spreadsheet->getActiveSheet()->getStyle('C')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(30);
        $spreadsheet->getActiveSheet()->getStyle('E')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(50);

        $column = 2;
        foreach ($data as $key => $rows) { // 行写入
            $span = ord("A");
            foreach ($rows as $keyName => $value) { // 列写入
                if($keyName == 'student_id_card' || $keyName == 'guardian_id_card' ){
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

    private function checkTemplate($objPHPExcel): array
    {
        $title = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($title != '录取生源信息采集') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        $mobile = $objPHPExcel->getActiveSheet()->getCell("A2")->getValue();
        if($mobile != '联系手机号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_name = $objPHPExcel->getActiveSheet()->getCell("B2")->getValue();
        if($student_name != '学生姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("C2")->getValue();
        if($student_id_card != '学生身份证') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $family_name = $objPHPExcel->getActiveSheet()->getCell("D2")->getValue();
        if($family_name != '监护人姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $family_id_card = $objPHPExcel->getActiveSheet()->getCell("E2")->getValue();
        if($family_id_card != '监护人身份证') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $house_type = $objPHPExcel->getActiveSheet()->getCell("F2")->getValue();
        if($house_type != '房产类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $house_owner = $objPHPExcel->getActiveSheet()->getCell("G2")->getValue();
        if($house_owner != '房产所有人') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $house_address = $objPHPExcel->getActiveSheet()->getCell("H2")->getValue();
        if($house_address != '房产地址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $graduation_school = $objPHPExcel->getActiveSheet()->getCell("I2")->getValue();
        if($graduation_school != '毕业学校（幼儿园/小学）') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $result_school = $objPHPExcel->getActiveSheet()->getCell("J2")->getValue();
        if($result_school != '就读学校') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

    private function getHouseType($name): int
    {
        $house_type = 0;
        if($name == '产权房'){
            $house_type = 1;
        }
        if($name == '租房'){
            $house_type = 2;
        }
        if($name == '自建房'){
            $house_type = 3;
        }
        if($name == '置换房'){
            $house_type = 4;
        }
        if($name == '公租房'){
            $house_type = 5;
        }
        if($name == '三代同堂'){
            $house_type = 6;
        }
        return $house_type;
    }

}