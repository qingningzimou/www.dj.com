<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\edu;

use app\common\controller\Education;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use app\common\model\UserApply as model;
use app\mobile\validate\Apply as validate;
use think\facade\Db;
use think\facade\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Civilian extends Education
{
    /**
     * 录取名单列表
     * @return Json
     */
    public function getResultList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getListByType(3, true);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['list'];

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
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    /**
     * 民办录取页面资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $result['data']['admission_type_list'][] = ['id' => 1, 'name' => '摇号'];
                $result['data']['admission_type_list'][] = ['id' => 4, 'name' => '派遣'];
                $result['data']['admission_type_list'][] = ['id' => 5, 'name' => '调剂'];
                $result['data']['admission_type_list'][] = ['id' => 6, 'name' => '政策生'];
                $result['data']['admission_type_list'][] = ['id' => 7, 'name' => '自动录取'];

                $result['data']['signed_list'][] = ['id' => 0, 'name' => '否'];
                $result['data']['signed_list'][] = ['id' => 1, 'name' => '是'];

                $res = [
                    'code' => 1,
                    'data' => $result['data'],
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
     * 录取情况
     * @return Json
     */
    public function getAdmission(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getSchoolAdmission();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['data'];

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
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    /**
     * 详细信息
     * @param id 申请信息ID
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
                $data = $this->getChildDetails($this->result['id'],0);
                if($data['code'] == 0){
                    throw new \Exception($data['msg']);
                }
                $res = [
                    'code' => 1,
                    'data' => $data['data']
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
     * 【验证退回】学生申请状态初始化
     * @return Json
     */
    public function actInit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }

                $data = $this->request->only([
                    'id',
                ]);
                $ids = $data['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选需要操作的学生');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['paid', '=', 1];
                $where[] = ['admission_type', '<>', 7];
                $paid_count = Db::name('UserApply')->where($where)->count();
                if($paid_count > 0 ){
                    throw new \Exception('选择的学生中有录取方式不是自动录取的学生！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['paid', '=', 1];
                $where[] = ['admission_type', '=', 7];

                $update = [];
                $update['prepared'] = 0;//预录取状态
                $update['resulted'] = 0;//录取状态
                $update['signed'] = 0;//录取状态
                $update['paid'] = 0;//未缴费
                $update['apply_status'] = 1;//未录取
                $update['prepare_status'] = 0;//预录取验证状态
                //$update['apply_school_id'] = 0;//民办申请学校ID
                $update['refuse_count'] = 0;//面审被拒次数
                $update['result_school_id'] = 0;//最终录取学校ID
                $update['admission_type'] = 0;//录取方式

                $result = (new model())->editData($update, $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                foreach ($id_array as $id) {
                    //审核日志
                    $log = [];
                    $log['user_apply_id'] = $id;
                    $log['admin_id'] = $this->userInfo['manage_id'];
                    $log['school_id'] = $school_id;
                    $log['remark'] = '民办验证退回';
                    $log['status'] = 1;
                    Db::name('UserApplyAuditLog')->save($log);
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
     * 录取导出
     * @return Json
     */
    public function exportResult(): Json
    {
        if($this->request->isPost())
        {
            try {
                $result = $this->getListByType(3, false);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['list'];

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = [
                    'id' => '编号',
                    'real_name' => '姓名',
                    'id_card' => '身份证号',
                    'mobile' => '手机号',
                    'birthplace_status_name' => '户籍',
                    'relation_name' => '监护人关系',
                    'house_status_name' => '房产',
                    'three_syndromes_name' => '三证情况',
                    'student_status_name' => '学籍情况',
                    'last_message' => '最后一条消息',
                    'admission_type_name' => '录取方式',
                    'sign_name' => '入学状态',
                    'signe_time' => '入学时间'
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
                        $this->excelExport('民办学校最终录取_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('民办学校最终录取_', $headArr, $data);
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
                $update['apply_status'] = 7;//民办入学报到
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
     * 处理退款
     * @return Json
     */
    public function actRefund(): Json
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
     * excel表格导出
     * @param string $fileName 文件名称
     * @param array $headArr 表头名称
     * @param array $data 要导出的数据
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
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
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(40);
        $spreadsheet->getActiveSheet()->getStyle('C')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(60);

        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(60);

        $column = 2;
        foreach ($data as $key => $rows) { // 行写入
            $span = ord("A");
            foreach ($rows as $keyName => $value) { // 列写入
                if($keyName == 'id_card' ){
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

    private function getListByType($type, $is_limit = true): array
    {
        try {
            if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                $school_id = $this->userInfo['school_id'];
            } else {
                return ['code' => 0, 'msg' => '学校管理员学校ID设置错误' ];
            }
            $school = Db::name('SysSchool')->field('id, school_type, school_attr')->find($school_id);
            if(!$school){
                return ['code' => 0, 'msg' => '学校管理员学校ID关联学校不存在' ];
            }

            $where = [];
            $where[] = ['a.deleted', '=', 0];
            $where[] = ['d.deleted', '=', 0];
            $where[] = ['a.voided', '=', 0];//没有作废
            //$where[] = ['a.apply_school_id', '=', $school_id];//申请学校ID
            $where[] = ['a.school_attr', '=', $school['school_attr']];
            $where[] = ['a.school_type', '=', $school['school_type']];

            //报名信息
            if ($type == 1) {
                $where[] = ['a.prepared', '=', 0];//没有预录取
                $where[] = ['a.resulted', '=', 0];//没有录取
                $where[] = ['a.signed', '=', 0];//没有入学报到
            }
            //预录取
            if ($type == 2) {
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.paid', '=', 0];//未缴费
                $where[] = ['a.signed', '=', 0];//没有入学报到
            }
            //录取
            if ($type == 3) {
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.paid', '=', 1];//已缴费
                $where[] = ['a.result_school_id', '=', $school_id];//录取学校
            }
            //关键字搜索
            if($this->request->has('keyword') && $this->result['keyword'])
            {
                $where[] = ['d.child_name|d.child_idcard|d.mobile','like', '%' . $this->result['keyword'] . '%'];
            }
            if($this->request->has('admission_type') && $this->result['admission_type'] > 0 )
            {
                $where[] = ['a.admission_type','=', $this->result['admission_type'] ];
            }
            if($this->request->has('signed') && $this->result['signed'] !== '' )
            {
                $where[] = ['a.signed','=', $this->result['signed'] ];
            }

            $list = Db::name('UserApply')->alias('a')
                ->join([
                    'deg_user_apply_detail' => 'd'
                ], 'd.child_id = a.child_id', 'LEFT')
                ->field([
                    'a.id' => 'id',
                    'd.child_name' => 'real_name',
                    'd.child_idcard' => 'id_card',
                    'd.mobile' => 'mobile',
                    'd.birthplace_status' => 'birthplace_status',
                    'd.guardian_relation' => 'relation',
                    'd.house_status' => 'house_status',
                    'd.three_syndromes_status' => 'three_syndromes_status',
                    'd.student_status' => 'student_status',
                    'a.admission_type' => 'admission_type',
                    'a.refund_status' => 'refund_status',
                    'CASE a.signed WHEN 1 THEN "是" ELSE "否" END' => 'sign_name',
                    'CASE a.signed WHEN 1 THEN a.signe_time ELSE "-" END' => 'signe_time',
                    'd.house_type' => 'house_type',
                    'd.insurance_status' => 'insurance_status',
                    'd.business_license_status' => 'business_license_status',
                    'd.residence_permit_status' => 'residence_permit_status',
                ])
                ->where($where)
                ->order('a.signed', 'ASC')->order('a.id', 'ASC');

                $result = $this->getViewData();
                if($result['code'] == 0){
                    return ['code' => 0, 'msg' => $result['msg'] ];
                }
                $result = $result['data'];

                if($is_limit) {
                    $list = $list->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v){
                        $birthplace_status_name = isset($result['birthplace_list'][$v['birthplace_status']]) ? $result['birthplace_list'][$v['birthplace_status']] : '-';
                        $relation_name = isset($result['relation_list'][$v['relation']]) ? $result['relation_list'][$v['relation']] : '-';
                        $house_status_name = isset($result['house_list'][$v['house_status']]) ? $result['house_list'][$v['house_status']] : '-';
                        //$three_syndromes_name = isset($result['three_syndromes_list'][$v['three_syndromes_status']]) ? $result['three_syndromes_list'][$v['three_syndromes_status']] : '-';
                        $student_status_name = isset($result['school_roll_list'][$v['student_status']]) ? $result['school_roll_list'][$v['student_status']] : '-';
                        $refund_status_name = isset($result['refund_list'][$v['refund_status']]) ? $result['refund_list'][$v['refund_status']] : '-';
                        $three_syndromes_name = $this->getThreeSyndromesName($v);

                        $list['data'][$k]['birthplace_status_name'] = $birthplace_status_name;
                        $list['data'][$k]['relation_name'] = $relation_name;
                        $list['data'][$k]['house_status_name'] = $house_status_name;
                        $list['data'][$k]['three_syndromes_name'] = $three_syndromes_name;
                        $list['data'][$k]['student_status_name'] = $student_status_name;
                        $list['data'][$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'] );
                        $list['data'][$k]['refund_status_name'] = $refund_status_name;

                    }
                    //权限节点
                    $res_data = $this->getResources($this->userInfo, $this->request->controller());
                    $list['resources'] = $res_data;
                }else{
                    $list = $list->select()->toArray();

                    foreach ($list as $k => $v){
                        $birthplace_status_name = isset($result['birthplace_list'][$v['birthplace_status']]) ? $result['birthplace_list'][$v['birthplace_status']] : '-';
                        $relation_name = isset($result['relation_list'][$v['relation']]) ? $result['relation_list'][$v['relation']] : '-';
                        $house_status_name = isset($result['house_list'][$v['house_status']]) ? $result['house_list'][$v['house_status']] : '-';
                        //$three_syndromes_name = isset($result['three_syndromes_list'][$v['three_syndromes_status']]) ? $result['three_syndromes_list'][$v['three_syndromes_status']] : '-';
                        $student_status_name = isset($result['school_roll_list'][$v['student_status']]) ? $result['school_roll_list'][$v['student_status']] : '-';
                        $refund_status_name = isset($result['refund_list'][$v['refund_status']]) ? $result['refund_list'][$v['refund_status']] : '-';
                        $three_syndromes_name = $this->getThreeSyndromesName($v);


                        $list[$k]['birthplace_status_name'] = $birthplace_status_name;
                        $list[$k]['relation_name'] = $relation_name;
                        $list[$k]['house_status_name'] = $house_status_name;
                        $list[$k]['three_syndromes_name'] = $three_syndromes_name;
                        $list[$k]['student_status_name'] = $student_status_name;
                        $list[$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'] );
                        $list[$k]['refund_status_name'] = $refund_status_name;
                    }
                }

            return ['code' => 1, 'list' => $list ];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    private function getAdmissionTypeName($type): string
    {
        $name = '-';
        switch ($type){
            case 1: $name = '摇号'; break;
            case 4: $name = '派遣'; break;
            case 5: $name = '调剂'; break;
            case 6: $name = '政策生'; break;
            case 7: $name = '自动录取'; break;
        }
        return $name;
    }

}