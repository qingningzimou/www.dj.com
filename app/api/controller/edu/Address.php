<?php


namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\Schools;
use app\common\model\SysAddressSimple;
use app\common\model\SysRegion;
use app\common\validate\house\Address as validate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;
use think\facade\Lang;


class Address extends Education
{
    /**
     * 完整地址列表
     * @return \think\response\Json
     */
    public function getList()
    {
        try {
            $where = [];
            if (isset($this->userInfo['region_id'])) {
                $region_id = $this->userInfo['region_id'];
            }else{
                throw new \Exception('管理员所属区域为空');
            }
            $region = SysRegion::field('id, simple_code')->find($region_id);

            if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }
            if ($this->request->has('search') && $this->request->param('search') != '' ) {
                $where[] = ['address.address', 'like', '%' . $this->request->param('search') . '%'];
            }

            $is_select = '';
            if ($this->request->has('is_select') && ($this->result['is_select'] || $this->result['is_select'] == '0')) {
                $is_select = $this->result['is_select'];
            }
            $school = Schools::field('id, school_type')->find($school_id);

            $list = Db::table("deg_sys_address_{$region['simple_code']}")->alias('address');

            switch ($school['school_type']) {
                case 1:
                    if($is_select){
                        $where[] = ['address.primary_school_id', '=', $school['id']];
                    }
                    if($is_select == '0'){
                        $where[] = ['address.primary_school_id', '<=', 0];
                    }

                    $list = $list->join([
                        'deg_sys_school' => 'primary_school'
                    ], 'primary_school.id = address.primary_school_id', 'left')
                        ->join([
                            'deg_manage' => 'primary_admin'
                        ], 'primary_admin.id = address.primary_manager_id', 'left')
                        ->field([
                            'address.id',
                            'address.address',
                            'primary_school.school_name' => 'school_name',
                            'primary_admin.real_name' => 'admin_name',
                            'primary_admin.user_name' => 'admin_mobile',
                        ]);

                    break;
                case 2:
                    if($is_select){
                        $where[] = ['address.middle_school_id', '=', $school['id']];
                    }
                    if($is_select == '0'){
                        $where[] = ['address.middle_school_id', '<=', 0];
                    }

                    $list = $list->join([
                        'deg_sys_school' => 'middle_school'
                    ], 'middle_school.id = address.middle_school_id', 'left')
                        ->join([
                            'deg_manage' => 'middle_admin'
                        ], 'middle_admin.id = address.middle_manager_id', 'left')
                        ->field([
                            'address.id',
                            'address.address',
                            'middle_school.school_name' => 'school_name',
                            'middle_admin.real_name' => 'admin_name',
                            'middle_admin.user_name' => 'admin_mobile'
                        ]);

                    break;
            }

            $list = $list->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

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
                if (!$this->request->has('region_id') && !isset($this->userInfo['region_id'])) {
                    throw new \Exception('请选择区县');
                }
                $region_id = $this->request->param('region_id');
                if(!$region_id){
                    $region_id = $this->userInfo['region_id'];
                }
                $region = SysRegion::field('id, simple_code')->find($this->request->param('region_id'));

                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id'], 'region_id' => $region_id], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = Db::table("deg_sys_address_{$region['simple_code']}")->where('id', $this->result['id'])->hidden(['deleted'])->find();
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
     * 学校确认认领
     * @param id 完整地址勾选ID
     * @return mixed
     */
    public function actSave()
    {
        try {
            //开始事务
            Db::startTrans();

            if ( $this->userInfo['region_id'] > 0 ) {
                $region_id = $this->userInfo['region_id'];
            }else{
                throw new \Exception('管理员所属区域为空');
            }
            if ( $this->userInfo['school_id'] > 0 ) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }
            $ids = $this->request->param('id');
            $id_array = explode(',', $ids);
            if(count($id_array) == 0){
                throw new \Exception('请勾选完整地址');
            }
            $restrictions = true;
            if($this->request->has('cover') && $this->result['cover']){
                $restrictions = false;
            }
            $region = SysRegion::field('id, simple_code')->find($region_id);
            $school = Schools::field('id, school_type')->find($school_id);

            $address_model_name = $this->getModelNameByCode($region['simple_code']);
            if($address_model_name == ''){
                throw new \Exception('完整地址model名称获取失败');
            }
            $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
            $address_model = new $address_model_name();

            $where = [];
            $where[] = ['id', 'in', $id_array];
            $where[] = ['deleted', '=', 0];

            $exist = [];
            $update = [];
            switch ($school['school_type']) {
                case 1:
                    $exist = Db::table('deg_sys_address_'.$region['simple_code'])->where($where)
                        ->where('primary_school_id', '>', 0)->where('primary_school_id', '<>', $school_id)->findOrEmpty();
                    $update['primary_school_id'] = $school['id'];
                    $update['primary_manager_id'] = $this->userInfo['manage_id'];
                    //强制覆盖已认领地址
                    if($restrictions) {
                        $where[] = ['primary_school_id', '<=', 0];
                    }
                    break;
                case 2:
                    $exist = Db::table('deg_sys_address_'.$region['simple_code'])->where($where)
                        ->where('middle_school_id', '>', 0)->where('middle_school_id', '<>', $school_id)->findOrEmpty();

                    $update['middle_school_id'] = $school['id'];
                    $update['middle_manager_id'] = $this->userInfo['manage_id'];
                    //强制覆盖已认领地址
                    if($restrictions) {
                        $where[] = ['middle_school_id', '<=', 0];
                    }
                    break;
            }
            $address_res = $address_model->editData($update, $where);
            if($address_res['code'] == 0){
                throw new \Exception($address_res['msg']);
            }

            $res = [
                'code' => 1,
                'msg' => Lang::get('update_success')
            ];

            if( $exist && $restrictions ){
                $res = [
                    'code' => 10,
                    'id' => $ids,
                    'msg' => '勾选的地址已被其他学校认领，请与该学校确认沟通！'
                ];
            }

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
     * @param id 完整地址勾选ID
     * @return \think\response\Json
     */
    public function actCancel()
    {
        try {
            //开始事务
            Db::startTrans();

            if (isset($this->userInfo['region_id'])) {
                $region_id = $this->userInfo['region_id'];
            }else{
                throw new \Exception('管理员所属区域为空');
            }
            if (isset($this->userInfo['school_id'])) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }

            $region = SysRegion::field('id, simple_code')->find($region_id);
            $school = Schools::field('id, school_type')->find($school_id);

            $update = [];
            $where = [];
            switch ($school['school_type']) {
                case 1:
                    $where[] = ['primary_school_id', '=', $school['id']];
                    $update['primary_school_id'] = 0;
                    $update['primary_manager_id'] = 0;
                    break;
                case 2:
                    $where[] = ['middle_school_id', '=', $school['id']];
                    $update['middle_school_id'] = 0;
                    $update['middle_manager_id'] = 0;
                    break;
            }
            $ids = $this->request->param('id');
            $id_array = explode(',', $ids);
            if(count($id_array) == 0){
                throw new \Exception('请勾选缩略地址');
            }

            $address_model_name = $this->getModelNameByCode($region['simple_code']);
            if($address_model_name == ''){
                throw new \Exception('完整地址model名称获取失败');
            }
            $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
            $where[] = ['id', 'in', $id_array];
            $where[] = ['deleted', '=', 0];
            $address_model = new $address_model_name();
            $address_res = $address_model->editData($update, $where);
            if($address_res['code'] == 0){
                throw new \Exception($address_res['msg']);
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
        try {
        //php 脚本执行时间设置 无限制
        set_time_limit(0);

        if (isset($this->userInfo['region_id'])) {
            $region_id = $this->userInfo['region_id'];
        }else{
            throw new \Exception('管理员所属区域为空');
        }
        if (isset($this->userInfo['school_id'])) {
            $school_id = $this->userInfo['school_id'];
        }else{
            throw new \Exception('管理员没有关联学校ID');
        }

        $region = SysRegion::field('id, simple_code')->find($region_id);
        $school = Schools::field('id, school_type')->find($school_id);

        //搜索条件
        $where = [];
        if ($this->request->has('search') && $this->request->param('search') != '') {
            $where[] = ['address.address', 'like', '%' . $this->request->param('search') . '%'];
        }
        if ($this->request->has('is_select') && ($this->result['is_select'] || $this->result['is_select'] == '0')) {
            switch ($this->result['is_select']) {
                case 1:
                    $where[] = ['address.primary_school_id|address.middle_school_id', '>', 0];
                    break;
                case 0:
                    var_dump('sss');
                    $where[] = ['address.primary_school_id|address.middle_school_id', '<=', 0];
                    break;
            }
        }

        $tableName = "deg_sys_address_{$region['simple_code']}";
        $data = Db::table($tableName)->alias('address');

        switch ($school['school_type']) {
            case 1:
                $where[] = ['address.primary_school_id', '=', $school['id']];

                $data = $data->join([
                        'deg_sys_school' => 'primary_school'
                    ], 'primary_school.id = address.primary_school_id', 'left')
                    ->join([
                        'deg_manage' => 'primary_admin'
                    ], 'primary_admin.id = address.primary_manager_id', 'left')
                    ->field([
                        'address.id',
                        'address.address',
                        'primary_school.school_name' => 'school_name',
                        'primary_admin.real_name' => 'admin_name',
                        'primary_admin.user_name' => 'admin_mobile',
                    ]);
                break;
            case 2:
                $where[] = ['address.middle_school_id', '=', $school['id']];

                $data = $data->join([
                        'deg_sys_school' => 'middle_school'
                    ], 'middle_school.id = address.middle_school_id', 'left')
                    ->join([
                        'deg_manage' => 'middle_admin'
                    ], 'middle_admin.id = address.middle_manager_id', 'left')
                    ->field([
                        'address.id',
                        'address.address',
                        'middle_school.school_name' => 'school_name',
                        'middle_admin.real_name' => 'admin_name',
                        'middle_admin.user_name' => 'admin_mobile',
                    ]);
                break;
        }

        $data = $data->where($where)->order('address.id', 'ASC')->select();

        if(count($data) == 0){
            return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
        }
        if(count($data) > 1000){
            $data = array_slice($data, 0, 1000, true);
        }


        $headArr = ['编号', '完整地址', '学校', '学校负责人', '联系电话'];

        $this->excelExport('本校认领完整地址', $headArr, $data);

        } catch (\Exception $exception) {
            $res = [
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ];
        }
        return parent::ajaxReturn($res);
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
        /*$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(70);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(10);*/


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