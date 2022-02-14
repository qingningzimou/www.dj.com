<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */

namespace app\api\controller\fund;

use app\common\controller\Education;
use app\common\model\UserApply;
use app\common\model\UserCostPay;
use app\common\model\UserCostRefund;
use app\common\model\Schools;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Db;
use think\response\Json;
require_once (__DIR__.'/../../../common/controller/AbcPay.php');

class Refund extends Education
{
    /**
     * 【退费申请】列表
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getListData(true);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['list'];

                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
                if ($this->userInfo['grade_id'] == $this->area_grade){
                    $res_data['region_auth'] = [
                        'status_name' => '区级权限',
                        'grade_status' => 1
                    ];
                }
                if ($this->userInfo['grade_id'] == $this->school_grade){
                    $res_data['school_auth'] = [
                        'status_name' => '学校权限',
                        'grade_status' => 1
                    ];
                }
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
        }else{}
    }

    /**
     * 资源
     * @return Json
     */
    public function resView(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = [];

                $result = $this->getAllSelectByRoleList();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data['region_list'] = $result['region_list'];
                $data['school_list'] = $result['school_list'];

                $data['status_list'] = [['id' => 0, 'name' => '待审'], ['id' => 1, 'name' => '通过'], ['id' => 2, 'name' => '拒绝'],];

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
     * 查看
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $param = $this->request->only(['id', 'user_apply_id']);
                //  如果数据不合法，则返回
                if ( !$param['id'] ) {
                    throw new \Exception('退费ID参数错误');
                }
                if ( !$param['user_apply_id'] ) {
                    throw new \Exception('申请资料ID参数错误');
                }

                $result = $this->getChildDetails($this->result['user_apply_id'], 0);
                if ( $result['code'] == 0 ) {
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'data' => $result['data']
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
     * 审批退费操作
     * @return Json
     */
    public function actAudit(): Json
    {
        if ($this->request->isPost()) {
            //开始事务
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'user_apply_id',
                    'status',
                    'remark',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'], $this->result['user_id']);
                if ($checkHash['code'] == 0) {
                    throw new \Exception($checkHash['msg']);
                }
                //  如果数据不合法，则返回
                if ( !$data['id'] ) {
                    throw new \Exception('ID参数错误');
                }
                if ( !$data['status'] ) {
                    throw new \Exception('审核状态参数错误');
                }
                if ( !$data['user_apply_id'] ) {
                    throw new \Exception('申请资料ID参数错误');
                }
                $costRefund = (new UserCostRefund())->where('id',$data['id'])->find();
                if(empty($costRefund)){
                    throw new \Exception('退费申请信息不存在');
                }
                $applyInfo = Db::name('UserApply')->find($this->result['user_apply_id']);
                if( !$applyInfo ){
                    throw new \Exception('申请资料不存在');
                }
                $detail = Db::name('UserApplyDetail')->where('child_id', $applyInfo['child_id'])
                    ->where('deleted', 0)->find();
                if( !$detail ){
                    throw new \Exception('学生详细信息不存在');
                }
                //学校审核
                if($this->userInfo['grade_id'] == $this->school_grade){
                    if($costRefund['school_status']){
                        throw new \Exception('退费申请已审核，请勿重复提交');
                    }
                    $update = [];
                    $update['id'] = $data['id'];
                    $update['school_status'] = $data['status'];
                    $update['school_manage'] = $this->userInfo['manage_id'];
                    $update['school_audit_time'] = date('Y-m-d H:i:s');
                    $update['school_remark'] = $this->result['remark'] ?? '';

                    $result = (new UserCostRefund())->editData($update);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }

                    $apply = [];
                    $apply['id'] = $data['user_apply_id'];
                    if($data['status'] == 1){
                        $apply['refund_status'] = 2;
                    }else{
                        $apply['refund_status'] = 3;
                    }
                    $result = (new UserApply())->editData($apply);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                }else{
                    if($costRefund['region_status']){
                        throw new \Exception('退费申请已审核，请勿重复提交');
                    }
                    $update = [];
                    $update['id'] = $data['id'];
                    $update['region_status'] = $data['status'];
                    $update['region_manage'] = $this->userInfo['manage_id'];
                    $update['region_audit_time'] = date('Y-m-d H:i:s');
                    $update['region_remark'] = $this->result['remark'] ?? '';
                    //审核通过，费用原路退回
                    if($data['status'] == 1){
                        $merchant_num = (new Schools())->where('id',$costRefund['school_id'])->value('merchant_num');
                        if(empty($merchant_num)){
                            throw new \Exception('退费配置信息不存在');
                        }
                        $pay = new \AbcPay();
                        $pay_time = $costRefund['pay_time'];
                        $order_code = $costRefund['order_code'];
                        $pay_amount = (string)floatval($costRefund['amount']);
                        $res = $pay->actRefund($pay_time, $order_code, $pay_amount,$merchant_num);
                        //如果请求成功
                        if($res['code'] == 1){
                            $update['refund_code'] = $res['data']['refund_code'];
                            $update['refund_status'] = $res['data']['refund_status'];
                            //如果退费成功
                            if($res['data']['refund_status']){
                                $updatePay = [
                                    'id' => $costRefund['user_cost_pay_id'],
                                    'refund_status' => 1,
                                    'refund_code' => $res['data']['refund_code'],
                                    'refund_time' => date('Y-m-d H:i:s'),
                                    'refund_amount' => $res['data']['refund_amount'],
                                ];
                                (new UserCostPay())->editData($updatePay);
                            }
                        }else{
                            throw new \Exception($res['msg']);
                        }
                    }
                    $result = (new UserCostRefund())->editData($update);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    $apply = [];
                    $apply['id'] = $data['user_apply_id'];
                    $apply['open_refund'] = 0;
                    if($data['status'] == 1){
                        $apply['refund_status'] = 4;
                        $apply['paid'] = 0;
                        $apply['apply_status'] = 4;//民办落选
                        $apply['prepared'] = 0;
                        $apply['resulted'] = 0;
                        $apply['result_school_id'] = 0;
                    }else{
                        $apply['refund_status'] = 5;
                    }
                    $result = (new UserApply())->editData($apply);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }


                    //消息发送参数
                    $param = [];
                    $param['user_id'] = $applyInfo['user_id'];
                    $param['user_apply_id'] = $data['id'];
                    $param['mobile'] = $detail['mobile'];
                    $param['child_name'] = $detail['child_name'];
                    $param['school_id'] = $applyInfo['result_school_id'];
                    if($data['status'] == 1){
                        $param['type'] = 11;
                    }else{
                        $param['type'] = 12;
                    }
                    $this->sendAutoMessage($param);

                    $education = 0;
                    if ($this->userInfo['grade_id'] >= $this->city_grade){
                        $education = 2;
                    }
                    if ($this->userInfo['grade_id'] == $this->area_grade){
                        $education = 1;
                    }
                    //审核日志
                    $log = [];
                    $log['user_apply_id'] = $data['user_apply_id'];
                    $log['admin_id'] = $this->userInfo['manage_id'];
                    $log['school_id'] = $costRefund['school_id'];
                    $log['education'] = $education;
                    $log['remark'] = $data['remark'] ? '退费审核：' . $data['remark'] : '退费审核';
                    $log['status'] = $data['status'];
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
     * 学校缴费明细导出
     * @return Json
     */
    public function export(): Json
    {
        if($this->request->isPost())
        {
            try {
                $result = $this->getListData(false);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['list'];

                foreach ($data as $k => $v){
                    unset($data[$k]['id']);
                    unset($data[$k]['user_apply_id']);
                    unset($data[$k]['region_name']);
                    unset($data[$k]['school_name']);
                    unset($data[$k]['apply_status_name']);
                    unset($data[$k]['region_status_name']);
                    unset($data[$k]['region_status']);
                    unset($data[$k]['school_status']);
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = ['学生姓名', '身份证号', '家长手机号', '缴费金额', '申请时间', '申请原因', '退费处理状态'];
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
                        $this->excelExport('学生退费处理_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('学生退费处理_', $headArr, $data);
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

    //退费申请数据
    private function getListData($is_limit = true): array
    {
        try {
            $where = [];
            $where[] = ['a.deleted', '=', 0];
            $where[] = ['a.voided', '=', 0];
            $where[] = ['c.deleted', '=', 0];

            //学生姓名、身份证号、手机号模糊查询
            if($this->request->has('keyword') && $this->result['keyword'])
            {
                $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
            }
            //区县ID
            if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                $where[] = ['c.region_id', '=', $this->result['region_id']];
            }
            //学校ID
            if ($this->request->has('school_id') && $this->result['school_id'] > 0) {
                $where[] = ['c.school_id', '=', $this->result['school_id']];
            }
            //审批状态
            if ($this->request->has('status') && $this->result['status'] !== '') {
                if($this->userInfo['grade_id'] == $this->school_grade) {
                    $where[] = ['c.school_status', '=', $this->result['status']];
                }else{
                    $where[] = ['c.region_status', '=', $this->result['status']];
                }
            }

            $role_school = $this->getSchoolIdsByRole();
            if($role_school['code'] == 0){
                throw new \Exception($role_school['msg']);
            }
            if($role_school['bounded']  ){
                $where[] = ['c.school_id', 'in', $role_school['school_ids']];
            }
            $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
            $where[] = ['c.region_id', 'in', $region_ids];

            $data = Db::name("UserCostRefund")->alias('c')
                ->join([
                    'deg_user_apply' => 'a'
                ], 'a.id = c.user_apply_id')
                ->join([
                    'deg_user_apply_detail' => 'd'
                ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                ->field([
                    'c.id',
                    'c.user_apply_id',
                    'c.region_id',
                    'c.school_id',
                    'd.child_name' => 'real_name',
                    'd.child_idcard' => 'id_card',
                    'd.mobile',
                    'a.apply_status',
                    'c.amount',
                    'c.create_time',
                    'c.reason',
                    'c.school_status',
                    'c.region_status',
                ])
                ->where($where)->order('a.create_time', 'DESC')->master(true);

            if($is_limit) {
                $data = $data->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();
                $list = $data['data'];
            }else{
                $list = $data->select()->toArray();
            }

            $region = Cache::get('region');
            $school = Cache::get('school');

            $result = $this->getViewData();
            if($result['code'] == 0){
                throw new \Exception($result['msg']);
            }
            $view = $result['data'];

            $_data = [];
            foreach ($list as $k => $v) {
                $apply_status_name = isset($view['apply_status_list'][$v['apply_status']]) ? $view['apply_status_list'][$v['apply_status']] : '-';

                $regionData = filter_value_one($region, 'id', $v['region_id']);
                $region_name = '';
                if (count($regionData) > 0){
                    $region_name = $regionData['region_name'];
                }
                $schoolData = filter_value_one($school, 'id', $v['school_id']);
                $school_name = '';
                if (count($schoolData) > 0){
                    $school_name = $schoolData['school_name'];
                }

                $_data[$k]['id'] = $v['id'];
                $_data[$k]['user_apply_id'] = $v['user_apply_id'];
                $_data[$k]['region_name'] = $region_name;
                $_data[$k]['school_name'] = $school_name;
                $_data[$k]['real_name'] = $v['real_name'];
                $_data[$k]['mobile'] = $v['mobile'];
                $_data[$k]['id_card'] = $v['id_card'];
                $_data[$k]['amount'] = $v['amount'];
                $_data[$k]['create_time'] = $v['create_time'];
                $_data[$k]['apply_status_name'] = $apply_status_name;
                $_data[$k]['reason'] = $v['reason'];
                $_data[$k]['school_status'] = $v['school_status'];
                $_data[$k]['school_status_name'] = $this->getStatusName($v['school_status']);
                $_data[$k]['region_status'] = $v['region_status'];
                $_data[$k]['region_status_name'] = $this->getStatusName($v['region_status']);
//                if ($this->userInfo['grade_id'] == $this->school_grade){
//                    if($v['region_status'] > 0){
//                        $_data[$k]['school_status'] = $v['region_status'];
//                        $_data[$k]['school_status_name'] = $_data[$k]['region_status_name'];
//                    }else{
//                        $_data[$k]['school_status_name'] = $this->getStatusName($v['school_status']);
//                    }
//                }
            }

            if($is_limit) {
                $data['data'] = $_data;
                return ['code' => 1, 'list' => $data];
            }else{
                return ['code' => 1, 'list' => $_data];
            }

        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
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
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(40);
        $spreadsheet->getActiveSheet()->getStyle('B')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(50);

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

    private function getStatusName($status): string
    {
        $name = "待审";
        switch ($status){
            case 0:
                $name = "待审";
                break;
            case 1:
                $name = "通过";
                break;
            case 2:
                $name = "拒绝";
                break;
            default:
                break;
        }

        return $name;
    }

}