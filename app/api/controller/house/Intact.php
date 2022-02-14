<?php


namespace app\api\controller\house;

use app\common\controller\Education;
use app\common\model\Manage;
use think\facade\Cache;
use app\common\model\Schools;
use app\common\model\SysAddressIntact as model;
use app\common\validate\house\Address as validate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;
use think\facade\Lang;


class Intact extends Education
{
    /**
     * 完整地址列表
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                //区县角色隐藏发布机构
                $select_region = true;
                if ($this->userInfo['grade_id'] < $this->city_grade) {
                    $select_region = false;
                }
                $where = [];
                $school = Cache::get('school');
                $region = Cache::get('region');
                $manage = Cache::get('manage');
                //过滤不是负责人账号
                /*if(!empty($manage) && count($manage)){
                    $manage = array_values(filter_by_value($manage,'main_account',1));
                }*/
                if($select_region) {
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $where[] = ['region_id', '=', $this->request->param('region_id')];
                    }
                }
                if ($this->request->has('search') && $this->result['search'] != '') {
                    $where[] = ['address|simple_code', 'like', '%' . $this->request->param('search') . '%'];
                }
                if ($this->request->has('school_id') && $this->result['school_id'] > 0) {
                    $where[] = ['primary_school_id|middle_school_id', '=', $this->result['school_id']];
                }
                if ($this->request->has('is_select') && $this->result['is_select'] != '') {
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

                $region_ids = array_column(object_to_array($this->userInfo['relation_region']), 'region_id');
                $where[] = ['region_id', 'in', $region_ids];

                //禁用学校
                $schoolList = Db::name('SysSchool')->where('disabled',1)->where('deleted',0)->select()->toArray();

                $list = (new model())->alias('intact')
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
        }else{}
    }

    /**
     * 完整地址详情
     * @return \think\response\Json
     */
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id'] ], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = Db::table("deg_sys_address_intact")->where('id', $this->result['id'])->hidden(['deleted'])->find();
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
     * 学校指派
     * @param id 完整地址勾选ID
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
                    throw new \Exception('请勾选完整地址');
                }
                if ($data['region_id'] < 1 ) {
                    throw new \Exception('区县ID参数错误');
                }
                if ($data['school_id'] <= 0 ) {
                    throw new \Exception('学校ID参数错误');
                }

                $school = Schools::field(['id', 'region_id', 'school_type'])->find($data['school_id']);

                if ($school['region_id'] != $data['region_id'] ) {
                    throw new \Exception('学校区县ID和参数区县ID不相同');
                }
                /*$manage = (new Manage())->field('id')
                    ->where([['school_id', '=', $data['school_id']], ['main_account', '=', 1] ])->findOrEmpty();
                if(!$manage){
                    throw new \Exception('学校无负责人账号');
                }*/

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];

                $update = [];
                switch ($school['school_type']) {
                    case 1:
                        $update['primary_school_id'] = $school['id'];
                        //$update['primary_manager_id'] = $manage['id'];
                        break;
                    case 2:
                        $update['middle_school_id'] = $school['id'];
                        //$update['middle_manager_id'] = $manage['id'];
                        break;
                }

                $address_res = (new model())->editData($update, $where);
                if ($address_res['code'] == 0) {
                    throw new \Exception($address_res['msg']);
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
     * 本校认领地址导出
     * @throws \think\Exception
     */
    public function actExport()
    {
        //php 脚本执行时间设置 无限制
        set_time_limit(0);

        $where = [];
        if ($this->userInfo['grade_id'] < $this->city_grade) {
            if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 0) {
                $where[] = ['intact.region_id', '=', $this->userInfo['region_id']];
            }else{
                throw new \Exception('管理员区县ID设置错误');
            }
        }else{
            if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                $where[] = ['intact.region_id', '=', $this->request->param('region_id')];
            }
        }

        if ($this->request->has('search') && $this->result['search'] != '') {
            $where[] = ['intact.address', 'like', '%' . $this->request->param('search') . '%'];
        }
        if ($this->request->has('school_id') && $this->result['school_id'] > 0) {
            $where[] = ['intact.primary_school_id|intact.middle_school_id', '=', $this->result['school_id']];
        }
        if ($this->request->has('is_select')) {
            switch ($this->request->param('is_select')) {
                case 1:
                    $where[] = ['intact.primary_school_id', '>', 0];
                    break;
                case 2:
                    $where[] = ['intact.primary_school_id', '<=', 0];
                    break;
                case 3:
                    $where[] = ['intact.middle_school_id', '>', 0];
                    break;
                case 4:
                    $where[] = ['intact.middle_school_id', '<=', 0];
                    break;
            }
        }

        $region_ids = array_column(object_to_array($this->userInfo['relation_region']), 'region_id');
        $where[] = ['intact.region_id', 'in', $region_ids];

        //学校负责人账号去重
        /*$res_manage = $this->getSchoolMainAccountIds();
        if($res_manage['code'] == 0){
            throw new \Exception($res_manage['msg']);
        }
        $manage_ids = $res_manage['manage_ids'];*/

        $list = Db::name('SysAddressIntact')->alias('intact')
            ->join([
                'deg_sys_region' => 'region'
            ], 'region.id = intact.region_id')
            ->join([
                'deg_sys_school' => 'primary_school'
            ], 'primary_school.id = intact.primary_school_id', 'left')
            /*->join([
                'deg_manage' => 'primary_admin'
            ], 'primary_admin.school_id = intact.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ')', 'left')*/
            ->join([
                'deg_sys_school' => 'middle_school'
            ], 'middle_school.id = intact.middle_school_id', 'left')
            /*->join([
                'deg_manage' => 'middle_admin'
            ], 'middle_admin.school_id = intact.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ')', 'left')*/
            ->field([
                'intact.id',
                'region.region_name' => 'region_name',
                'intact.address',
                'primary_school.school_name' => 'primary_school_name',
                'intact.primary_school_id',
                //'primary_admin.real_name' => 'primary_admin_name',
                //'primary_admin.user_name' => 'primary_admin_mobile',
                'middle_school.school_name' => 'middle_school_name',
                'intact.middle_school_id',
                //'middle_admin.real_name' => 'middle_admin_name',
                //'middle_admin.user_name' => 'middle_admin_mobile'
            ])
            ->where($where)->select()->toArray();

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

        $headArr = ['编号', '区县', '完整地址', '小学', '小学负责人', '联系电话', '初中', '初中负责人', '联系电话'];

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
                $this->excelExport('完整地址_' . ($i + 1) . '_', $headArr, $data);
            }
        }else {
            $this->excelExport('完整地址', $headArr, $data);
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
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(70);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(50);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(50);
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

}