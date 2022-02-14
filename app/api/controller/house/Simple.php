<?php


namespace app\api\controller\house;

use app\common\controller\Education;
use app\common\model\Manage;
use think\facade\Cache;
use app\common\model\Schools;
use app\common\model\SysAddressSimple as model;
use app\common\model\SysRegion;
use app\common\validate\house\Simple as validate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;
use think\facade\Lang;


class Simple extends Education
{
    /**
     * 缩略地址列表
     * @return \think\response\Json
     */
    public function getList()
    {
        try {
            //区县角色隐藏发布机构
            $select_region = true;
            $grade = $this->city_grade;
            if ($this->userInfo['grade_id'] < $this->city_grade) {
                $select_region = false;
            }

            $where = [];
            $where[] = ['deleted', '=', 0];
            $school = Cache::get('school');
            $region = Cache::get('region');
            $manage = Cache::get('manage');
            //区级不用传区县ID
            if($select_region) {
                if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                    $where[] = ['region_id', '=', $this->request->param('region_id')];
                }
            }
            if ($this->request->has('search') && $this->result['search'] != '') {
                $where[] = ['address|simple_code', 'like', '%' . $this->request->param('search') . '%'];
            }
            if ($this->request->has('school_id') && $this->result['school_id'] > 0) {
                $where[] = ['primary_school_id|middle_school_id', '=', $this->result['school_id'] ];
            }
            if ($this->request->has('is_select') && $this->result['is_select'] !== '' ) {
                switch ($this->request->param('is_select')) {
                    case 1:
                        $where[] = ['primary_school_id', '>', 0];
                        break;
                    case 2:
                        $where[] = ['primary_school_id', '<=', 0];
                        break;
                    case 3:
                        $where[] = ['middle_school_id', '>', 0];
                        break;
                    case 4:
                        $where[] = ['middle_school_id', '<=', 0];
                        break;
                }
            }

            $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
            $where[] = ['simple.region_id', 'in', $region_ids];

            //禁用学校
            $schoolList = Db::name('SysSchool')->where('disabled',1)->where('deleted',0)->select()->toArray();

            $list = (new model())->alias('simple')
                ->field([
                    'id',
                    'address',
                    'region_id',
                    'primary_school_id',
                    'middle_school_id',
                ])
                ->where($where)->master(true)
                ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            foreach($list['data'] as $key=>&$value){
                $value['region_name'] = '';
                $value['primary_school_name'] = '';
                $value['primary_admin_name'] = '';
                $value['primary_admin_mobile'] = '';
                $value['middle_school_name'] = '';
                $value['middle_admin_name'] = '';
                $value['middle_admin_mobile'] = '';
                if (!empty($value['region_id'])){
                    $regionData = filter_value_one($region, 'id', $value['region_id']);
                    if (count($regionData) > 0) {
                        $value['region_name'] = $regionData['region_name'];
                    }
                }
                if (!empty($value['primary_school_id'])){
                    $schoolData = filter_value_one($school, 'id', $value['primary_school_id']);
                    if(!$schoolData){
                        $schoolData = filter_value_one($schoolList, 'id', $value['primary_school_id']);
                    }
                    if (count($schoolData) > 0) {
                        $value['primary_school_name'] = $schoolData['school_name'];
                        /*$manageData = filter_value_one($manage, 'school_id', $value['primary_school_id']);
                        if (count($manageData) > 0) {
                            $value['primary_admin_name'] = $manageData['real_name'];
                            $value['primary_admin_mobile'] = $manageData['user_name'];
                        }*/
                        $manageData = $this->getSchoolMainAccount($manage, $value['primary_school_id']);
                        $value['primary_admin_name'] = $manageData['admin_name'];
                        $value['primary_admin_mobile'] = $manageData['admin_mobile'];
                    }
                }
                if (!empty($value['middle_school_id'])){
                    $schoolData = filter_value_one($school, 'id', $value['middle_school_id']);
                    if(!$schoolData){
                        $schoolData = filter_value_one($schoolList, 'id', $value['middle_school_id']);
                    }
                    if (count($schoolData) > 0) {
                        $value['middle_school_name'] = $schoolData['school_name'];
                        /*$manageData = filter_value_one($manage, 'school_id', $value['middle_school_id']);
                        if (count($manageData) > 0) {
                            $value['middle_admin_name'] = $manageData['real_name'];
                            $value['middle_admin_mobile'] = $manageData['user_name'];
                        }*/
                        $manageData = $this->getSchoolMainAccount($manage, $value['middle_school_id']);
                        $value['middle_admin_name'] = $manageData['admin_name'];
                        $value['middle_admin_mobile'] = $manageData['admin_mobile'];
                    }
                }
            }
            $list['select_region'] = $select_region;

            //权限节点
            $res_data = $this->getResources($this->userInfo, $this->request->controller());
            $list['resources'] = $res_data;

            $res = [
                'code' => 1,
                'data' => $list
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ];
        }
        return parent::ajaxReturn($res);
    }

    /**
     * 缩略地址详情
     * @return \think\response\Json
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
                $info = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();
                if (!$info) {
                    throw new \Exception('找不到缩略地址信息');
                }
                $region = (new SysRegion())->field('id, simple_code')->find($info['region_id']);
                if(!$region){
                    throw new \Exception('未找到区县');
                }

                //学校负责人账号去重
                /*$res_manage = $this->getSchoolMainAccountIds();
                if($res_manage['code'] == 0){
                    throw new \Exception($res_manage['msg']);
                }
                $manage_ids = $res_manage['manage_ids'];*/

                $where[] = ['address.address', 'like', '%' . $this->request->param('search') . '%'];
                $list = Db::table("deg_sys_address_{$region['simple_code']}")
                    ->alias('address')
                    ->join([
                        'deg_sys_school' => 'primary_school'
                    ], 'primary_school.id = address.primary_school_id', 'left')
                    /*->join([
                        'deg_manage' => 'primary_admin'
                    ], 'primary_admin.school_id = address.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ')', 'left')*/
                    ->join([
                        'deg_sys_school' => 'middle_school'
                    ], 'middle_school.id = address.middle_school_id', 'left')
                    /*->join([
                        'deg_manage' => 'middle_admin'
                    ], 'middle_admin.school_id = address.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ')', 'left')*/
                    ->field([
                        'address.*',
                        'primary_school.school_name' => 'primary_school_name',
                        //'primary_admin.real_name' => 'primary_admin_name',
                        //'primary_admin.user_name' => 'primary_admin_mobile',
                        'middle_school.school_name' => 'middle_school_name',
                        //'middle_admin.real_name' => 'middle_admin_name',
                        //'middle_admin.user_name' => 'middle_admin_mobile'
                    ])
                    //->whereLike('address.address', "{$info['address']}%")
                    ->where('address.simple_id', $this->result['id'])
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $manage = Cache::get('manage');
                foreach($list['data'] as $key => &$value){
                    $value['primary_admin_name'] = '';
                    $value['primary_admin_mobile'] = '';
                    $value['middle_admin_name'] = '';
                    $value['middle_admin_mobile'] = '';

                    if (!empty($value['primary_school_id']) && $value['primary_school_name'] ){
                        $manageData = $this->getSchoolMainAccount($manage, $value['primary_school_id']);
                        $value['primary_admin_name'] = $manageData['admin_name'];
                        $value['primary_admin_mobile'] = $manageData['admin_mobile'];
                    }
                    if (!empty($value['middle_school_id']) && $value['middle_school_name'] ){
                        $manageData = $this->getSchoolMainAccount($manage, $value['middle_school_id']);
                        $value['middle_admin_name'] = $manageData['admin_name'];
                        $value['middle_admin_mobile'] = $manageData['admin_mobile'];
                    }
                }

                $res = [
                    'code' => 1,
                    'data' => $list
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
     * 指派学校
     * @param region_id 区域ID
     * @param school_id 学校ID
     * @param id 缩略/完整地址勾选ID
     * @return mixed
     */
    public function actSave()
    {
        if ($this->request->isPost()) {
            try {
                //开始事务
                Db::startTrans();

                $data = $this->request->only([
                    'id',
                    'region_id',
                    'school_id',
                ]);
                if ($this->userInfo['grade_id'] < $this->city_grade) {
                    //区级不用区县ID
                    if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1){
                        $data['region_id'] = $this->userInfo['region_id'];
                    }else{
                        throw new \Exception('管理员区县ID设置错误');
                    }
                }
                $ids = $data['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选缩略地址');
                }
                if ($data['region_id'] < 1 ) {
                    throw new \Exception('区县ID参数错误');
                }
                if ($data['school_id'] <= 0 ) {
                    throw new \Exception('学校ID参数错误');
                }

                $region = SysRegion::field('id, simple_code')->find($data['region_id']);
                $schoolInfo = Schools::find($data['school_id']);

                if ($schoolInfo['region_id'] != $data['region_id'] ) {
                    throw new \Exception('学校区县ID和参数区县ID不相同');
                }
                /*$manage = (new Manage())->field('id')
                    ->where([['school_id', '=', $data['school_id']], ['main_account', '=', 1] ])->findOrEmpty();
                if(!$manage){
                    throw new \Exception('学校无负责人账号');
                }*/

                $update = [];
                $_address_update = [];
                switch ($schoolInfo['school_type']) {
                    case 1:
                        $_address_update['primary_school_id'] = $schoolInfo['id'];

                        $update['primary_school_id'] = $schoolInfo['id'];
                        $update['primary_school_num'] = Db::raw('sub_num');
                        //$update['primary_manager_id'] = $manage['id'];
                        //$sql = "UPDATE deg_sys_address_simple SET primary_school_num = sub_num WHERE id in (" . $ids . ")";
                        break;
                    case 2:
                        $_address_update['middle_school_id'] = $schoolInfo['id'];

                        $update['middle_school_id'] = $schoolInfo['id'];
                        $update['middle_school_num'] = Db::raw('sub_num');
                        //$update['middle_manager_id'] = $manage['id'];
                        //$sql = "UPDATE deg_sys_address_simple SET middle_school_num = sub_num WHERE id in (" . $ids . ")";
                        break;
                }

                //Db::execute($sql);
                $simple_where = [];
                $simple_where[] = ['id', 'in', $id_array];
                $simple_where[] = ['deleted', '=', 0];
                $simple_res = (new model())->editData($update, $simple_where);
                if ($simple_res['code'] == 0) {
                    throw new \Exception($simple_res['msg']);
                }

                $address_model_name = $this->getModelNameByCode($region['simple_code']);
                if ($address_model_name == '') {
                    throw new \Exception('完整地址model名称获取失败');
                }
                $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
                foreach ((array)$id_array as $id) {
                    //$addressInfo = model::field('id,address')->find($id);
                    $address_where = [];
                    //$address_where[] = ['address', 'like', "{$addressInfo['address']}%"];
                    $address_where[] = ['simple_id', '=', $id];
                    $address_where[] = ['deleted', '=', 0];
                    $address_model = new $address_model_name();
                    $address_res = $address_model->editData($_address_update, $address_where);
                    if ($address_res['code'] == 0) {
                        throw new \Exception($address_res['msg']);
                    }
                    if ($address_res['code'] == 0) {
                        throw new \Exception($address_res['msg']);
                    }
                }

                //房产统计
                $result = $this->getAddressStatistics($data['school_id']);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => $exception->getCode(),
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }else{}
    }

    /**
     * 下拉列表【区县、学校】
     * @return \think\response\Json
     */
    public function getSelectList()
    {
        if ($this->request->isPost()) {
            $result = $this->getAllSelectByRoleList();

            $code = $result['code'];
            unset($result['police_list']);
            unset($result['plan_list']);
            unset($result['central_list']);
            unset($result['code']);

            foreach ((array)$result['school_list'] as $k => $v){
                //过滤民办
                if($v['school_attr'] == 2){
                    unset($result['school_list'][$k]);
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
     * 获取区域下拉列表
     * @return \think\response\Json
     */
    public function getRegionList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['disabled','=', 0];
                $where[] = ['deleted','=', 0];
                $where[] = ['parent_id','>', 0];

                $data = Db::name('sys_region')->where($where)->field(['id', 'region_name',])->select();
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
     * 学校下拉列表
     * @return \think\response\Json
     */
    public function getSchoolList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted','=', 0];
                if($this->request->has('region_id') && $this->result['region_id'])
                {
                    $where[] = ['region_id','=', $this->result['region_id']];
                }else{
                    //throw new \Exception('请选择区域');
                    //区级角色
                    $department_id = $this->userInfo['department_id'];

                    $department = Db::name('department')
                        ->where('id', $department_id)
                        ->where('disabled',0)
                        ->where('deleted',0)
                        ->find();
                    if (empty($department)) {
                        throw new \Exception('行政归属未找到');
                    }
                    $where[] = ['region_id','=', $department['region_id'] ];
                }
                if($this->request->has('school_type') && $this->result['school_type'] > 0 )
                {
                    $where[] = ['school_type','=', $this->result['school_type']];
                }
                $data = Db::name('sys_school')
                    ->field(['id', 'school_name',])
                    ->where($where)->order('id', 'desc')->select();

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
     * 缩略地址导出
     * @throws \think\Exception
     */
    public function actExport()
    {
        //php 脚本执行时间设置 无限制
        set_time_limit(0);

        $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
        //搜索条件
        $where = [];
        if ($this->userInfo['grade_id'] < $this->city_grade) {
            if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 0) {
                $where[] = ['simple.region_id', '=', $this->userInfo['region_id']];
            }else{
                throw new \Exception('管理员区县ID设置错误');
            }
        }else{
            if ($this->request->has('region_id') && $this->request->param('region_id') > 0) {
                $where[] = ['simple.region_id', '=', $this->request->param('region_id')];
            }else{
                //市级管理员默认取一个区域
                if(count($region_ids) > 1){
                    $where[] = ['simple.region_id', '=', $region_ids[1]];
                }
            }
        }
        if ($this->request->has('search') && $this->request->param('search') != '') {
            $where[] = ['simple.address', 'like', '%' . $this->request->param('search') . '%'];
        }
        if ($this->request->has('school_id') && $this->request->param('school_id') > 0) {
            $where[] = ['simple.primary_school_id|simple.middle_school_id', '=', $this->request->param('school_id')];
        }
        if ($this->request->has('is_select')) {
            switch ($this->result['is_select']) {
                case 1:
                    $where[] = ['simple.primary_school_id', '>', 0];
                    break;
                case 2:
                    $where[] = ['simple.primary_school_id', '<=', 0];
                    break;
                case 3:
                    $where[] = ['simple.middle_school_id', '>', 0];
                    break;
                case 4:
                    $where[] = ['simple.middle_school_id', '<=', 0];
                    break;
            }
        }
        $where[] = ['simple.region_id', 'in', $region_ids];

        //学校负责人账号去重
        /*$res_manage = $this->getSchoolMainAccountIds();
        if($res_manage['code'] == 0){
            throw new \Exception($res_manage['msg']);
        }
        $manage_ids = $res_manage['manage_ids'];*/

        $list = Db::name('sys_address_simple')->alias('simple')
            ->join([
                'deg_sys_region' => 'region'
            ], 'region.id = simple.region_id')
            ->join([
                'deg_sys_school' => 'primary_school'
            ], 'primary_school.id = simple.primary_school_id', 'left')
            /*->join([
                'deg_manage' => 'primary_admin'
            ], 'primary_admin.school_id = simple.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ')', 'left')*/
            ->join([
                'deg_sys_school' => 'middle_school'
            ], 'middle_school.id = simple.middle_school_id', 'left')
            /*->join([
                'deg_manage' => 'middle_admin'
            ], 'middle_admin.school_id = simple.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ')', 'left')*/
            ->field([
                'simple.id',
                'region.region_name' => 'region_name',
                'simple.address',
                'primary_school.school_name' => 'primary_school_name',
                'simple.primary_school_id',
                //'primary_admin.real_name' => 'primary_admin_name',
                //'primary_admin.user_name' => 'primary_admin_mobile',
                'middle_school.school_name' => 'middle_school_name',
                'simple.middle_school_id',
                //'middle_admin.real_name' => 'middle_admin_name',
                //'middle_admin.user_name' => 'middle_admin_mobile'
            ])
            ->where($where)
            ->order('simple.id', 'ASC')->select()->toArray();

        $data = [];
        $manage = Cache::get('manage');
        foreach($list as $key => $value){
            $data[$key]['id'] = $value['id'];
            $data[$key]['region_name'] = $value['region_name'];
            $data[$key]['address'] = $value['address'];
            $data[$key]['primary_school_name'] = $value['primary_school_name'];
            $data[$key]['primary_admin_name'] = '';
            $data[$key]['primary_admin_mobile'] = '';
            $data[$key]['middle_school_name'] = $value['middle_school_name'];
            $data[$key]['middle_admin_name'] = '';
            $data[$key]['middle_admin_mobile'] = '';

            if (!empty($value['primary_school_id']) && $value['primary_school_name'] ){
                $manageData = $this->getSchoolMainAccount($manage, $value['primary_school_id']);
                $data[$key]['primary_admin_name'] = $manageData['admin_name'];
                $data[$key]['primary_admin_mobile'] = $manageData['admin_mobile'];
            }
            if (!empty($value['middle_school_id']) && $value['middle_school_name'] ){
                $manageData = $this->getSchoolMainAccount($manage, $value['middle_school_id']);
                $data[$key]['middle_admin_name'] = $manageData['admin_name'];
                $data[$key]['middle_admin_mobile'] = $manageData['admin_mobile'];
            }

        }

        if(count($data) == 0){
            return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
        }

        $headArr = ['编号', '区县', '缩略地址', '小学', '小学负责人', '联系电话', '中学', '中学负责人', '联系电话'];
        if(count($data) > 10000){
            $total = count($data);
            $count_excel = ceil($total / 10000);
            for ($i = 0; $i < $count_excel; $i++){
                $offset = $i * 10000;
                $length = ($i + 1) * 10000;
                if($i == ($count_excel - 1)){
                    $length = $total;
                }
                $data = array_slice($data, $offset, $length, true);
                $this->excelExport('缩略地址_' . ($i + 1) . '_', $headArr, $data);
            }
        }else {
            $this->excelExport('缩略地址', $headArr, $data);
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
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(30);


        $column = 2;
        foreach ($data as $key => $rows) { // 行写入
            $span = ord("A");
            foreach ($rows as $keyName => $value) { // 列写入
                $objPHPExcel->setCellValue(chr($span) . $column, $value);
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

    /**
     * 根据行政代码获取model名称
     * @param $code
     * @return string
     */
    private function getModelNameByCode($code)
    {
        $model_name = '';
        switch ($code){
            case 420602:
                $model_name = "AddressXiangCheng"; break;
            case 420606:
                $model_name = "AddressFanCheng"; break;
            case 420685:
                $model_name = "AddressGaoXin"; break;
            case 420608:
                $model_name = "AddressDongJin"; break;
            case 420607:
                $model_name = "AddressXiangZhou"; break;
            case 420683:
                $model_name = "AddressZaoYang"; break;
            case 420684:
                $model_name = "AddressYiCheng"; break;
            case 420682:
                $model_name = "AddressLaoHeKou"; break;
            case 420624:
                $model_name = "AddressNanZhang"; break;
            case 420626:
                $model_name = "AddressBaoKang"; break;
            case 420625:
                $model_name = "AddressGuCheng"; break;
            case 420652:
                $model_name = "AddressYuLiangZhou"; break;
        }
        return $model_name;
    }
}