<?php


namespace app\api\controller\house;

use app\common\controller\Education;
use app\common\model\Schools;
use app\common\model\SysRegion;
use app\common\validate\house\Address as validate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
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
            //区县角色隐藏发布机构
            $select_region = true;
            if ($this->userInfo['grade_id'] < $this->city_grade) {
                $select_region = false;
                if($this->userInfo['region_id'] > 1){
                    $region_id = $this->userInfo['region_id'];
                }else{
                    throw new \Exception('管理员区县ID错误');
                }
            }else{
                //市级账号默认没有选读取关联区县第二个
                if ($this->request->has('region_id') && $this->result['region_id'] > 0 ) {
                    $region_id = $this->result['region_id'];
                }else{
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    if(count($region_ids) > 1){
                        $region_id = $region_ids[1];
                    }else{
                        throw new \Exception('市级管理员关联区县ID小于2');
                    }
                }
            }
            $region = (new SysRegion())->field('id, simple_code, region_name')->find($region_id);
            if(!$region){
                throw new \Exception('未找到区县');
            }

            $where = [];
            if ($this->request->has('search') && $this->result['search'] != '') {
                $where[] = ['address.address', 'like', '%' . $this->request->param('search') . '%'];
            }
            if ($this->request->has('school_id') && $this->result['school_id'] > 0 ) {
                $where[] = ['address.primary_school_id|address.middle_school_id', '=', $this->request->param('school_id')];
            }
            if ($this->request->has('is_select') && $this->result['is_select'] ) {
                switch ($this->request->param('is_select')) {
                    case 1:
                        $where[] = ['address.primary_school_id', '>', 0];
                        break;
                    case 2:
                        $where[] = ['address.primary_school_id', '<=', 0];
                        break;
                    case 3:
                        $where[] = ['address.middle_school_id', '>', 0];
                        break;
                    case 4:
                        $where[] = ['address.middle_school_id', '<=', 0];
                        break;
                }
            }
            $tableName = "deg_sys_address_{$region['simple_code']}";
            $isHasTable = Db::query("SHOW TABLES LIKE '".$tableName . "'");
            if (!$isHasTable) {
                throw new \Exception('表' . $tableName . '不存在');
            }
            $list = Db::table("deg_sys_address_{$region['simple_code']}")
                ->alias('address')
                ->join([
                    'deg_sys_school' => 'primary_school'
                ], 'primary_school.id = address.primary_school_id', 'left')
                ->join([
                    'deg_manage' => 'primary_admin'
                ], 'primary_admin.id = address.primary_manager_id', 'left')
                ->join([
                    'deg_sys_school' => 'middle_school'
                ], 'middle_school.id = address.middle_school_id', 'left')
                ->join([
                    'deg_manage' => 'middle_admin'
                ], 'middle_admin.id = address.middle_manager_id', 'left')
                ->field([
                    'address.*',
                    'primary_school.school_name' => 'primary_school_name',
                    'primary_admin.real_name' => 'primary_admin_name',
                    'primary_admin.user_name' => 'primary_admin_mobile',
                    'middle_school.school_name' => 'middle_school_name',
                    'middle_admin.real_name' => 'middle_admin_name',
                    'middle_admin.user_name' => 'middle_admin_mobile'
                ])
                ->where($where)
                ->paginate(['list_rows' => $this->pageSize,'var_page' => 'curr'])->toArray();

            $list['select_region'] = $select_region;
            //权限节点
            $res_data = $this->getResources($this->userInfo, $this->request->controller());
            $list['resources'] = $res_data;

            foreach ($list['data'] as $k => $v){
                $list['data'][$k]['region_name'] = $region['region_name'];
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
     * 指派学校
     * @param region_id 区域ID
     * @param type 类型
     * @param school_id 学校ID
     * @param id 缩略/完整地址勾选ID
     * @return mixed
     */
    public function actSave()
    {
        try {
            //开始事务
            Db::startTrans();

            $region = SysRegion::field('id, simple_code')->find($this->request->param('region_id'));
            $schoolInfo = Schools::find($this->request->param('school_id'));

            $update = [];
            switch ($schoolInfo['school_type']) {
                case 1:
                    $update['primary_school_id'] = $schoolInfo['id'];
                    $update['primary_manager_id'] = $this->userInfo['manage_id'];
                    break;
                case 2:
                    $update['middle_school_id'] = $schoolInfo['id'];
                    $update['middle_manager_id'] = $this->userInfo['manage_id'];
                    break;
            }
            $ids = $this->request->param('id');
            $id_array = explode(',', $ids);
            if(count($id_array) == 0){
                throw new \Exception('请勾选完整地址');
            }
            if(!$region['simple_code']){
                throw new \Exception('区域行政代码不存在');
            }

            $address_model_name = $this->getModelNameByCode($region['simple_code']);
            if($address_model_name == ''){
                throw new \Exception('完整地址model名称获取失败');
            }
            $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
            $address_where = [];
            $address_where[] = ['id', 'in', $id_array];
            $address_where[] = ['deleted', '=', 0];
            $address_model = new $address_model_name();
            $address_res = $address_model->editData($update, $address_where);
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
     * 完整地址导出
     * @throws \think\Exception
     */
    public function actExport()
    {
        try {
            //php 脚本执行时间设置 无限制
            set_time_limit(0);
            //ini_set(‘memory_limit’,’1024M’);
            $perSize = 1000;//每次导出的条数

            //$dataNum = Db::connect('excel')->name('innodb')->count(); // 数据量
            if (!$this->request->has('region_id') && !isset($this->request->userInfo['region_id'])) {
                throw new \Exception('请选择区县');
            }
            $region_id = $this->request->param('region_id');
            if (isset($this->request->userInfo['region_id'])) $region_id = $this->request->userInfo['region_id'];
            $region = SysRegion::field('id, simple_code')->find($this->request->param('region_id'));
            $where = [];//$where[] = ['address.id', '<', 10000];
            if ($this->request->has('search')) $where[] = ['address.address', 'like', '%' . $this->request->param('search') . '%'];
            if ($this->request->has('school_id')) $where[] = ['address.primary_school_id|address.middle_school_id', '=', $this->request->param('school_id')];
            $dataNum = Db::table("deg_sys_address_{$region['simple_code']}")
                ->alias('address')
                ->where($where)->count();

            $pages = ceil($dataNum / $perSize); //循环多少次
            $lastId = 0;  // 最后一次数据的id
            $excel_sql = Db::table("deg_sys_address_{$region['simple_code']}")
                ->alias('address')
                ->join([
                    'deg_sys_school' => 'primary_school'
                ], 'primary_school.id = address.primary_school_id', 'left')
                ->join([
                    'deg_manage' => 'primary_admin'
                ], 'primary_admin.id = address.primary_manager_id', 'left')
                ->join([
                    'deg_sys_school' => 'middle_school'
                ], 'middle_school.id = address.middle_school_id', 'left')
                ->join([
                    'deg_manage' => 'middle_admin'
                ], 'middle_admin.id = address.middle_manager_id', 'left')
                ->field([
                    'address.id',
                    'address.address',
                    'primary_school.name' => 'primary_school_name',
                    'primary_admin.real_name' => 'primary_admin_name',
                    'primary_admin.user_name' => 'primary_admin_mobile',
                    'middle_school.name' => 'middle_school_name',
                    'middle_admin.real_name' => 'middle_admin_name',
                    'middle_admin.user_name' => 'middle_admin_mobile'
                ])
                ->where($where)
                ->limit($perSize)
                ->order('address.id', 'ASC');


            $title = [
                'id' => ['title' => '编号', 'width' => 120],
                'address' => ['title' => '缩略地址', 'width' => 300],
                'primary_school_name' => ['title' => '小学', 'width' => 200],
                'primary_admin_name' => ['title' => '小学负责人', 'width' => 100],
                'middle_school_name' => ['title' => '中学', 'width' => 200],
                'middle_admin_name' => ['title' => '中学负责人', 'width' => 100],
            ];
            $dataTitle = [
                'id',
                'address',
                'primary_school_name',
                'primary_admin_name',
                'middle_school_name',
                'middle_admin_name',
            ];

            $heard = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            //设置工作表标题名称
            $sheet->setTitle('工作表格1'); //不知道为什么在csv文件中没有，可能是代码问题

            // 标题
            foreach ($dataTitle as $key => $value) {
                $sheet->setCellValue($heard[$key] . '1', $title[$value]['title']);
                //$sheet->getColumnDimension($heard[$key])->setWidth($title[$value]['width']); // 宽度该方法无用
            }
            // 标题end

            $writer = new Csv($spreadsheet);
            $fileName = '完整地址_' . date('Y-m-d');
            $fileType = 'csv';

            // 设置输出头部信息
            header('Content-Encoding: UTF-8');
            header("Content-Type: text/csv; charset=UTF-8");
            header('Content-Description: File Transfer');
            header("Expires: 0");
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            // 输出Excel07版本
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            // 输出名称
            header('Content-Disposition: attachment;filename=' . $fileName . '.' . $fileType);
            //禁止缓存
            header('Cache-Control: max-age=0');
            $writer->setUseBOM(true);  // csv 中文乱码问题
            $writer->save('php://output');
            // 清除数据
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            // 清除数据end
            $fp = fopen('php://output', 'a');//打开output流

            for ($i = 1; $i <= $pages; $i++) {
                $data = $excel_sql
                    ->where('address.id', '>', $lastId)
                    ->select();
                foreach ($data as $item) {
                    $lastId = $item['id'];
                    fputcsv($fp, $item);
                }
                //刷新输出缓冲到浏览器
                ob_flush();
                flush();//必须同时使用 ob_flush() 和flush() 函数来刷新输出缓冲。
            }
            fclose($fp); // 关闭文件
            unset($data);
            exit(); // 防止调试模式中输出html代码
        } catch (\Exception $exception) {
            $res = [
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ];
            return parent::ajaxReturn($res);
        }
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
                    //throw new \Exception('请选择区域');
                }
                if($this->request->has('school_type') && $this->result['school_type'] > 0 )
                {
                    $where[] = ['school_type','=', $this->result['school_type']];
                }
                $data = Db::name('sys_school')
                    ->field(['id', 'school_name',])
                    ->where($where)->order('id desc')->select();

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