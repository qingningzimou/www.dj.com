<?php


namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\PoliceStation;
use app\common\model\Schools;
use app\common\model\SysAddressBirthplace;
use app\common\model\SysAddressSimple as model;
use app\common\model\SysRegion;
use app\common\validate\house\Simple as validate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;


class Birthplace extends Education
{
    /**
     * 户籍地址列表
     * @return \think\response\Json
     */
    public function getList()
    {
        try {
            $police = Cache::get('police');
            $dictionary = new FilterData();
            $getData = $dictionary->findValue('dictionary', 'SYSKQCL', 'SYSKQPCS');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $filter_police = $getData['data'];
            $getData = $dictionary->resArray('dictionary', 'SYSKQPCS');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $filter_school = array_column($getData['data'],'dictionary_value');
            $where = [];
            if ($this->userInfo['region_id'] > 0 ) {
                $region_id = $this->userInfo['region_id'];
                $police_ids = [];
                if(in_array($this->userInfo['school_id'],$filter_school)){
                    $police_ids = [$filter_police];
                }
                foreach ((array)$police as $item){
                    if($item['region_id'] == $region_id){
                        $police_ids[] = $item['id'];
                    }
                }
                $where[] = ['b.police_id', 'in', $police_ids];
            }else{
                throw new \Exception('管理员所属区域为空');
            }
            if (isset($this->userInfo['school_id'])) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }

            //学校负责人账号去重
            /*$res_manage = $this->getSchoolMainAccountIds();
            if($res_manage['code'] == 0){
                throw new \Exception($res_manage['msg']);
            }
            $manage_ids = $res_manage['manage_ids'];*/

            $schoolInfo = Schools::field('id, school_type')->find($school_id);

            if ($this->request->has('police_id') && $this->result['police_id'] > 0) {
                $where[] = ['b.police_id', '=', $this->request->param('police_id')];
            }
            if ($this->request->has('search') && $this->result['search']) {
                $where[] = ['b.address', 'like', '%' . $this->request->param('search') . '%'];
            }
            $is_select = '';
            if ($this->request->has('is_select') && ($this->result['is_select'] || $this->result['is_select'] == '0')) {
                $is_select = $this->result['is_select'];
            }
            /*$list = Db::table("deg_sys_address_birthplace")->alias('b')
                ->join([
                    'deg_sys_police_station' => 'p'
                ], 'p.id = b.police_id')
                ->join([
                    'deg_sys_region' => 'r'
                ], 'r.id = p.region_id');*/

            switch ($schoolInfo['school_type']) {
                case 1:
                    //本校认领
                    if($is_select == 1){
                        $where[] = ['b.primary_school_id', '=', $schoolInfo['id']];
                    }
                    //已认领（全部）
                    if($is_select == 2){
                        $where[] = ['b.primary_school_id', '>', 0];
                    }
                    //未认领
                    if($is_select == 3){
                        $where[] = ['b.primary_school_id', '<=', 0];
                    }

                    /*$list = $list->join([
                        'deg_sys_school' => 'primary_school'
                    ], 'primary_school.id = b.primary_school_id', 'left')
                        ->join([
                            'deg_manage' => 'primary_admin'
                        ], 'primary_admin.school_id = b.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ') and primary_admin.deleted = 0 ', 'left')
                        ->field([
                            'b.id',
                            'b.address',
                            'r.region_name' => 'region_name',
                            'p.name' => 'police_name',
                            'primary_school.school_name' => 'school_name',
                            'primary_admin.real_name' => 'admin_name',
                            'primary_admin.user_name' => 'admin_mobile',
                        ]);*/

                    break;
                case 2:
                    if($is_select == 1){
                        $where[] = ['b.middle_school_id', '=', $schoolInfo['id']];
                    }
                    if($is_select == 2){
                        $where[] = ['b.middle_school_id', '>', 0];
                    }
                    if($is_select == 3){
                        $where[] = ['b.middle_school_id', '<=', 0];
                    }

                    /*$list = $list->join([
                        'deg_sys_school' => 'middle_school'
                    ], 'middle_school.id = b.middle_school_id', 'left')
                        ->join([
                            'deg_manage' => 'middle_admin'
                        ], 'middle_admin.school_id = b.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ') and middle_admin.deleted = 0 ', 'left')
                        ->field([
                            'b.id',
                            'b.address',
                            'r.region_name' => 'region_name',
                            'p.name' => 'police_name',
                            'middle_school.school_name' => 'school_name',
                            'middle_admin.real_name' => 'admin_name',
                            'middle_admin.user_name' => 'admin_mobile'
                        ]);*/

                    break;
            }
            /*$list = $list->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])
                ->order('b.id', 'ASC')->toArray();*/

            $list = Db::name("SysAddressBirthplace")->alias('b')
                ->field([
                    'id',
                    'address',
                    'police_id',
                    'primary_school_id',
                    'middle_school_id',
                ])->where($where)->master(true)
                ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            $school = Cache::get('school');
            $region = Cache::get('region');
            $manage = Cache::get('manage');
            //禁用学校
            $schoolList = Db::name('SysSchool')->where('disabled',1)->where('deleted',0)->select()->toArray();

            foreach($list['data'] as $key => &$val){
                $val['region_name'] = '';
                $val['police_name'] = '';

                if (!empty($val['police_id'])){
                    $policeData = filter_value_one($police, 'id', $val['police_id']);
                    if (count($policeData) > 0) {
                        $val['police_name'] = $policeData['name'];
                        $regionData = filter_value_one($region, 'id', $policeData['region_id']);
                        if (count($regionData) > 0) {
                            $val['region_name'] = $regionData['region_name'];
                        }
                    }
                }

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
     * 户籍地址详情
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
                $data = Db::table("deg_sys_address_birthplace")->where('id', $this->result['id'])->hidden(['deleted'])->find();
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
                        $exist = Db::name('SysAddressBirthplace')->where($where)
                            ->where([['primary_school_id', '>', 0], ['primary_school_id', '<>', $school_id] ])->findOrEmpty();
                        break;
                    case 2:
                        $exist = Db::name('SysAddressBirthplace')->where($where)
                            ->where([['middle_school_id', '>', 0], ['middle_school_id', '<>', $school_id] ])->findOrEmpty();
                        break;
                }
                if ($exist) {
                    $res = [
                        'code' => 10,
                        'id' => $ids,
                        'msg' => '选择的户籍地址已被别的学校认领，请与学校联系并进行确认！'
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
        }
    }

    /**
     * 确认认领
     * @param id 户籍地址勾选ID
     * @return mixed
     */
    public function actSave()
    {
        try {
            //开始事务
            Db::startTrans();

            if ( $this->userInfo['school_id'] > 0) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }
            $ids = $this->request->param('id');
            $id_array = explode(',', $ids);
            if(count($id_array) == 0){
                throw new \Exception('请勾选户籍地址');
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
                    if($restrictions) {
                        $where[] = ['primary_school_id', '<=', 0];
                    }
                    break;
                case 2:
                    $update['middle_school_id'] = $school['id'];
                    //$update['middle_manager_id'] = $this->userInfo['manage_id'];
                    //强制覆盖已认领地址
                    if($restrictions) {
                        $where[] = ['middle_school_id', '<=', 0];
                    }
                    break;
            }

            $result = (new SysAddressBirthplace())->editData($update, $where);
            if($result['code'] == 0){
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
     * 取消认领
     * @param id 户籍地址勾选ID
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
                throw new \Exception('请勾选户籍地址');
            }

            $where[] = ['id', 'in', $id_array];
            $where[] = ['deleted', '=', 0];
            $result = (new SysAddressBirthplace())->editData($update, $where);
            if($result['code'] == 0){
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
     * 获取区域下拉列表
     * @return \think\response\Json
     */
    public function getRegionList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['disabled','=', 0];
                $where[] = ['parent_id','>', 0];
                $where[] = ['id','<>', 13];

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
     * 获取派出所下拉列表
     * @return \think\response\Json
     */
    public function getPoliceStationList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted','=', 0];

                if ( $this->userInfo['region_id'] > 0 ) {
                    $where[] = ['region_id','=', $this->userInfo['region_id']];
                }else{
                    throw new \Exception('管理员所属区域为空');
                }

                $data = (new PoliceStation())->where($where)->select();
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
     * 导出学校认领地址
     * @throws \think\Exception
     */
    public function actExport()
    {
        try {
            //php 脚本执行时间设置 无限制
            set_time_limit(0);

            if (isset($this->userInfo['school_id'])) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }
            $school = Db::name('SysSchool')->field('id, school_type')->find($school_id);

            //搜索条件
            $where = [];
            if ($this->request->has('police_id') && $this->request->param('police_id') > 0) {
                $where[] = ['birthplace.police_id', '=', $this->request->param('police_id')];
            }

            if ($this->request->has('search') && $this->request->param('search') != '') {
                $where[] = ['birthplace.address', 'like', '%' . $this->request->param('search') . '%'];
            }

            //学校负责人账号去重
            /*$res_manage = $this->getSchoolMainAccountIds();
            if($res_manage['code'] == 0){
                throw new \Exception($res_manage['msg']);
            }
            $manage_ids = $res_manage['manage_ids'];*/

            $data = Db::table("deg_sys_address_birthplace")->alias('birthplace')
                ->join([
                    'deg_sys_police_station' => 'p'
                ], 'p.id = birthplace.police_id')
                ->join([
                    'deg_sys_region' => 'r'
                ], 'r.id = p.region_id');

            switch ($school['school_type']) {
                case 1:
                    $where[] = ['birthplace.primary_school_id', '=', $school['id']];
                    $data = $data->join([
                            'deg_sys_school' => 'primary_school'
                        ], 'primary_school.id = birthplace.primary_school_id', 'left')
                        /*->join([
                            'deg_manage' => 'primary_admin'
                        ], 'primary_admin.school_id = birthplace.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ') and primary_admin.deleted = 0 ', 'left')*/
                        ->field([
                            'birthplace.id',
                            'r.region_name' => 'region_name',
                            'birthplace.address',
                            'birthplace.primary_school_id',
                            'p.name' => 'police_name',
                            'primary_school.school_name' => 'school_name',
                            //'primary_admin.real_name' => 'admin_name',
                            //'primary_admin.user_name' => 'admin_mobile',
                        ]);

                    break;
                case 2:
                    $where[] = ['birthplace.middle_school_id', '=', $school['id']];
                    $data = $data->join([
                        'deg_sys_school' => 'middle_school'
                    ], 'middle_school.id = birthplace.middle_school_id', 'left')
                        /*->join([
                            'deg_manage' => 'middle_admin'
                        ], 'middle_admin.school_id = birthplace.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ') and middle_admin.deleted = 0 ', 'left')*/
                        ->field([
                            'birthplace.id',
                            'r.region_name' => 'region_name',
                            'birthplace.address',
                            'birthplace.middle_school_id',
                            'p.name' => 'police_name',
                            'middle_school.school_name' => 'school_name',
                            //'middle_admin.real_name' => 'admin_name',
                            //'middle_admin.user_name' => 'admin_mobile'
                        ]);

                    break;
            }
            $manage = Cache::get('manage');

            $data = $data->where($where)->select()->toArray();
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

            $headArr = ['编号', '区县', '户籍地址', '派出所', '学校', '学校负责人', '手机号码'];
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
                    $this->excelExport('本校认领户籍地址_' . ($i + 1) . '_', $headArr, $data);
                }
            }else {
                $this->excelExport('本校认领户籍地址_', $headArr, $data);
            }
            //$this->excelExport('户籍地址', $headArr, $data);
        } catch (\Exception $exception) {
            $res = $exception->getMessage() ?: Lang::get('system_error');
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
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(60);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(40);


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