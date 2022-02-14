<?php


namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\Schools;
use app\common\model\SysAddressIntact as model;
use app\common\validate\house\Address as validate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Cache;
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
        try {
            $where = [];

            if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1) {
                $where[] = ['intact.region_id', '=', $this->userInfo['region_id']];
            }else{
                throw new \Exception('学校管理员所属区县设置错误');
            }
            if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('学校管理员学校ID设置错误');
            }
            if ($this->request->has('search') && $this->request->param('search') != '' ) {
                $where[] = ['intact.address', 'like', '%' . $this->request->param('search') . '%'];
            }

            $is_select = -1;
            if ($this->request->has('is_select') && $this->result['is_select'] != '') {
                $is_select = intval($this->result['is_select']);
            }

            $schoolInfo = Db::name('SysSchool')->field('id, school_type')->find($school_id);

            //$list = Db::name("sys_address_intact")->alias('intact');

            //学校负责人账号去重
            /*$res_manage = $this->getSchoolMainAccountIds();
            if($res_manage['code'] == 0){
                throw new \Exception($res_manage['msg']);
            }
            $manage_ids = $res_manage['manage_ids'];*/

            switch ($schoolInfo['school_type']) {
                case 1:
                    //本校认领
                    if($is_select == 1){
                        $where[] = ['intact.primary_school_id', '=', $schoolInfo['id']];
                    }
                    //已认领（全部）
                    if($is_select == 2){
                        $where[] = ['intact.primary_school_id', '>', 0];
                    }
                    //未认领
                    if($is_select == 3){
                        $where[] = ['intact.primary_school_id', '<=', 0];
                    }

                    /*$list = $list->join([
                        'deg_sys_school' => 'primary_school'
                    ], 'primary_school.id = intact.primary_school_id', 'left')
                        ->join([
                            'deg_manage' => 'primary_admin'
                        ], 'primary_admin.school_id = intact.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ') and primary_admin.deleted = 0 ', 'left')
                        ->field([
                            'intact.id',
                            'intact.address',
                            'primary_school.school_name' => 'school_name',
                            'primary_admin.real_name' => 'admin_name',
                            'primary_admin.user_name' => 'admin_mobile',
                        ]);*/

                    break;
                case 2:
                    if($is_select == 1){
                        $where[] = ['intact.middle_school_id', '=', $schoolInfo['id']];
                    }
                    if($is_select == 2){
                        $where[] = ['intact.middle_school_id', '>', 0];
                    }
                    if($is_select == 3){
                        $where[] = ['intact.middle_school_id', '<=', 0];
                    }

                    /*$list = $list->join([
                        'deg_sys_school' => 'middle_school'
                    ], 'middle_school.id = intact.middle_school_id', 'left')
                        ->join([
                            'deg_manage' => 'middle_admin'
                        ], 'middle_admin.school_id = intact.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ') and middle_admin.deleted = 0 ', 'left')
                        ->field([
                            'intact.id',
                            'intact.address',
                            'middle_school.school_name' => 'school_name',
                            'middle_admin.real_name' => 'admin_name',
                            'middle_admin.user_name' => 'admin_mobile'
                        ]);*/

                    break;
            }

            $list = Db::name("SysAddressIntact")->alias('intact')
                ->field([
                    'id',
                    'address',
                    'primary_school_id',
                    'middle_school_id',
                ])
                ->where($where)->master(true)
                ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();
            /*$list = $list->where($where)->order('intact.id', 'ASC')
                ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();*/

            $school = Cache::get('school');
            $manage = Cache::get('manage');
            //禁用学校
            $schoolList = Db::name('SysSchool')->where('disabled',1)->where('deleted',0)->select()->toArray();

            foreach($list['data'] as $key=>&$val){
                $val['school_name'] = '';
                $val['admin_name'] = '';
                $val['admin_mobile'] = '';

                switch ($schoolInfo['school_type']) {
                    case 1:
                        if($val['primary_school_id'] > 0){
                            $schoolData = filter_value_one($school, 'id', $val['primary_school_id']);
                            if(!$schoolData){
                                $schoolData = filter_value_one($schoolList, 'id', $val['primary_school_id']);
                            }
                            if (count($schoolData) > 0) {
                                $val['school_name'] = $schoolData['school_name'];
                                /*$manageData = filter_value_one($manage, 'school_id', $val['primary_school_id']);
                                if (count($manageData) > 0) {
                                    $val['admin_name'] = $manageData['real_name'];
                                    $val['admin_mobile'] = $manageData['user_name'];
                                }*/
                                $manageData = $this->getSchoolMainAccount($manage, $val['primary_school_id']);
                                $val['admin_name'] = $manageData['admin_name'];
                                $val['admin_mobile'] = $manageData['admin_mobile'];
                            }
                        }
                        break;

                    case 2:
                        if($val['middle_school_id'] > 0){
                            $schoolData = filter_value_one($school, 'id', $val['middle_school_id']);
                            if(!$schoolData){
                                $schoolData = filter_value_one($schoolList, 'id', $val['middle_school_id']);
                            }
                            if (count($schoolData) > 0) {
                                $val['school_name'] = $schoolData['school_name'];
                                /*$manageData = filter_value_one($manage, 'school_id', $val['middle_school_id']);
                                if (count($manageData) > 0) {
                                    $val['admin_name'] = $manageData['real_name'];
                                    $val['admin_mobile'] = $manageData['user_name'];
                                }*/
                                $manageData = $this->getSchoolMainAccount($manage, $val['middle_school_id']);
                                $val['admin_name'] = $manageData['admin_name'];
                                $val['admin_mobile'] = $manageData['admin_mobile'];
                            }
                        }
                        break;
                }
            }

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
     * 认领预先判断
     * @return \think\response\Json
     */
    public function actPrejudge()
    {
        if ($this->request->isPost()) {
            try {
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }

                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选缩略地址');
                }

                $school = Schools::field('id, school_type')->find($school_id);

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];

                $exist = [];
                switch ($school['school_type']) {
                    case 1:
                        $exist = Db::name('SysAddressIntact')->where($where)
                            ->where([['primary_school_id', '>', 0], ['primary_school_id', '<>', $school_id] ])->findOrEmpty();
                        break;
                    case 2:
                        $exist = Db::name('SysAddressIntact')->where($where)
                            ->where([['middle_school_id', '>', 0], ['middle_school_id', '<>', $school_id] ])->findOrEmpty();
                        break;
                }
                if ($exist) {
                    $res = [
                        'code' => 10,
                        'id' => $ids,
                        'msg' => '选择的房产地址已被别的学校认领，请与学校联系并进行确认！'
                    ];
                }else{
                    $res = [
                        'code' => 1,
                        'id' => $ids,
                        'msg' => '确认认领勾选地址？'
                    ];
                }

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
     * 学校确认认领
     * @param id 完整地址勾选ID
     * @return mixed
     */
    public function actSave()
    {
        if ($this->request->isPost()) {
            try {
                //开始事务
                Db::startTrans();

                if ($this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('学校管理员学校ID设置错误');
                }
                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选完整地址');
                }
                $restrictions = false;

                $school = Schools::field('id, school_type')->find($school_id);

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];

                $update = [];
                switch ($school['school_type']) {
                    case 1:
                        $update['primary_school_id'] = $school['id'];
                        //$update['primary_manager_id'] = $this->userInfo['manage_id'];
                        //强制覆盖已认领地址
                        if ($restrictions) {
                            $where[] = ['primary_school_id', '<=', 0];
                        }
                        break;
                    case 2:
                        $update['middle_school_id'] = $school['id'];
                        //$update['middle_manager_id'] = $this->userInfo['manage_id'];
                        //强制覆盖已认领地址
                        if ($restrictions) {
                            $where[] = ['middle_school_id', '<=', 0];
                        }
                        break;
                }

                $address_res = (new model())->editData($update, $where);
                if ($address_res['code'] == 0) {
                    throw new \Exception($address_res['msg']);
                }

                //房产统计
                $result = $this->getAddressStatistics($school_id);
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
     * 取消认领
     * @param id 完整地址勾选ID
     * @return \think\response\Json
     */
    public function actCancel()
    {
        try {
            //开始事务
            Db::startTrans();

            if (isset($this->userInfo['school_id'])) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }
            $school = Schools::field('id, school_type')->find($school_id);

            $update = [];
            $where = [];
            switch ($school['school_type']) {
                case 1:
                    $where[] = ['primary_school_id', '=', $school['id']];
                    $update['primary_school_id'] = 0;
                    //$update['primary_manager_id'] = 0;
                    break;
                case 2:
                    $where[] = ['middle_school_id', '=', $school['id']];
                    $update['middle_school_id'] = 0;
                    //$update['middle_manager_id'] = 0;
                    break;
            }
            $ids = $this->request->param('id');
            $id_array = explode(',', $ids);
            if(count($id_array) == 0){
                throw new \Exception('请勾选完整地址');
            }
            $where[] = ['id', 'in', $id_array];
            $where[] = ['deleted', '=', 0];

            $address_res = (new model())->editData($update, $where);
            if($address_res['code'] == 0){
                throw new \Exception($address_res['msg']);
            }

            //房产统计
            $result = $this->getAddressStatistics($school_id);
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
        if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1) {
            $where[] = ['intact.region_id', '=', $this->userInfo['region_id']];
        }else{
            throw new \Exception('学校管理员所属区县设置错误');
        }
        if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
            $school_id = $this->userInfo['school_id'];
        }else{
            throw new \Exception('学校管理员学校ID设置错误');
        }
        if ($this->request->has('search') && $this->request->param('search') != '' ) {
            $where[] = ['intact.address', 'like', '%' . $this->request->param('search') . '%'];
        }

        $school = Schools::field('id, school_type')->find($school_id);

        $list = Db::table("deg_sys_address_intact")->alias('intact')
            ->join(['deg_sys_region' => 'region'], 'region.id = intact.region_id');

        //学校负责人账号去重
        /*$res_manage = $this->getSchoolMainAccountIds();
        if($res_manage['code'] == 0){
            throw new \Exception($res_manage['msg']);
        }
        $manage_ids = $res_manage['manage_ids'];*/

        switch ($school['school_type']) {
            case 1:
                $where[] = ['intact.primary_school_id', '=', $school['id']];

                $list = $list->join([
                    'deg_sys_school' => 'primary_school'
                ], 'primary_school.id = intact.primary_school_id', 'left')
                    /*->join([
                        'deg_manage' => 'primary_admin'
                    ], 'primary_admin.school_id = intact.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ') and primary_admin.deleted = 0 ', 'left')*/
                    ->field([
                        'intact.id',
                        'region.region_name' => 'region_name',
                        'intact.address',
                        'intact.primary_school_id',
                        'primary_school.school_name' => 'school_name',
                        //'primary_admin.real_name' => 'admin_name',
                        //'primary_admin.user_name' => 'admin_mobile',
                    ]);

                break;
            case 2:
                $where[] = ['intact.middle_school_id', '=', $school['id']];

                $list = $list->join([
                    'deg_sys_school' => 'middle_school'
                ], 'middle_school.id = intact.middle_school_id', 'left')
                    /*->join([
                        'deg_manage' => 'middle_admin'
                    ], 'middle_admin.school_id = intact.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ') and middle_admin.deleted = 0 ', 'left')*/
                    ->field([
                        'intact.id',
                        'region.region_name' => 'region_name',
                        'intact.address',
                        'intact.middle_school_id',
                        'middle_school.school_name' => 'school_name',
                        //'middle_admin.real_name' => 'admin_name',
                        //'middle_admin.user_name' => 'admin_mobile'
                    ]);

                break;
        }
        $manage = Cache::get('manage');

        $data = $list->where($where)->order('intact.id', 'ASC')->select()->toArray();
        foreach ($data as $k => &$val){
            $val['admin_name'] = '';
            $val['admin_mobile'] = '';

            switch ($school['school_type']) {
                case 1:
                    if($val['primary_school_id'] > 0){
                        if ($val['school_name']) {
                            $manageData = $this->getSchoolMainAccount($manage, $val['primary_school_id']);
                            $val['admin_name'] = $manageData['admin_name'];
                            $val['admin_mobile'] = $manageData['admin_mobile'];
                        }
                    }
                    unset($data[$k]['primary_school_id']);
                    break;
                case 2:
                    if($val['middle_school_id'] > 0){
                        if ($val['school_name']) {
                            $manageData = $this->getSchoolMainAccount($manage, $val['middle_school_id']);
                            $val['admin_name'] = $manageData['admin_name'];
                            $val['admin_mobile'] = $manageData['admin_mobile'];
                        }
                    }
                    unset($data[$k]['middle_school_id']);
                    break;
            }
        }

        if(count($data) == 0){
            return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
        }

        $headArr = ['编号', '区县', '完整地址', '学校', '学校负责人', '联系电话'];
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
                $this->excelExport('本校认领完整地址_' . ($i + 1) . '_', $headArr, $data);
            }
        }else {
            $this->excelExport('本校认领完整地址_', $headArr, $data);
        }
        //$this->excelExport('本校认领完整地址', $headArr, $data);

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
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(40);

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