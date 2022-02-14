<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\edu;

use app\common\controller\Education;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use app\common\model\UserApply as model;
use app\mobile\validate\Apply as validate;
use think\facade\Db;

class ApplyStateRun extends Education
{
    /**
     * 预录取列表
     * @return Json
     */
    public function getPrepareList(): Json
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
     * 公办预录取信息页面资源
     * @return \think\response\Json
     */
    public function resView(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $view = $this->getTypeData();
                if($view['code'] == 0){
                    throw new \Exception($view['msg']);
                }
                $result['data']['house_type_list'] = $view['data']['house_list'];
                $result['data']['code_type_list'] = $view['data']['code_list'];

                $result['data']['admission_type_list'][] = ['id' => 1, 'name' => '派位'];
                $result['data']['admission_type_list'][] = ['id' => 2, 'name' => '线上审核'];
                $result['data']['admission_type_list'][] = ['id' => 3, 'name' => '到校审核'];
                $result['data']['admission_type_list'][] = ['id' => 5, 'name' => '调剂'];
                $result['data']['admission_type_list'][] = ['id' => 6, 'name' => '政策生'];

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
     * 详细信息
     * @param id 申请信息ID
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }
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
     * 最终录取
     * @return Json
     */
    public function actAdmission(): Json
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
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选预录取的学生');
                }

                //录取情况
                $result = $this->getSchoolAdmission();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $admission_count = $result['data']['admission_count'];

                $degree_count = Db::name('PlanApply')
                    ->where('school_id', '>', 0)->where('status', 1)
                    ->where('deleted', 0)->where('school_id', '=', $school_id)
                    ->group('school_id')->sum('spare_total');
                if( (count($id_array) + $admission_count) > $degree_count){
                    throw new \Exception('学校学位数量不足！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];

                $update['resulted'] = 1;
                $update['apply_status'] = 3;//公办录取
                $update['result_school_id'] = $school_id;
                $result = (new model())->editData($update, $where);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                foreach ($id_array as $user_apply_id) {
                    //审核日志
                    $log = [];
                    $log['user_apply_id'] = $user_apply_id;
                    $log['admin_id'] = $this->userInfo['manage_id'];
                    $log['school_id'] = $school_id;
                    $log['remark'] = "最终录取";
                    $log['status'] = 4;
                    Db::name('UserApplyAuditLog')->save($log);
                }

                //中心学校录取统计
                $result = $this->getMiddleAdmissionStatistics($school_id);
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
     * 预录取导出
     * @return Json
     */
    public function exportPrepare(): Json
    {
        if($this->request->isPost())
        {
            try {
                $result = $this->getListByType(3, false);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['list'];

                foreach ($data as $k => $v){
                    unset($data[$k]['birthplace_status']);
                    unset($data[$k]['relation']);
                    unset($data[$k]['house_status']);
                    unset($data[$k]['three_syndromes_status']);
                    unset($data[$k]['student_status']);
                    unset($data[$k]['fill_time']);
                    unset($data[$k]['admission_type']);
                    unset($data[$k]['sign_name']);
                    unset($data[$k]['last_message']);
                    unset($data[$k]['signe_time']);
                    unset($data[$k]['house_type']);
                    unset($data[$k]['insurance_status']);
                    unset($data[$k]['business_license_status']);
                    unset($data[$k]['residence_permit_status']);
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = ['编号', '姓名', '身份证号', '手机号', '户籍', '监护人关系', '房产', '三证情况', '学籍情况', '录取方式'];
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
                        $this->excelExport('公办学校预录取_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('公办学校预录取_', $headArr, $data);
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
            $school = Db::name('SysSchool')->field('id, region_id, school_type, school_attr')->find($school_id);
            if(!$school){
                return ['code' => 0, 'msg' => '学校管理员学校ID关联学校不存在' ];
            }

            $where = [];
            $where[] = ['a.deleted', '=', 0];
            $where[] = ['d.deleted', '=', 0];
            $where[] = ['a.voided', '=', 0];//没有作废
            $where[] = ['a.school_attr', '=', $school['school_attr']];
            $where[] = ['a.school_type', '=', $school['school_type']];

            //线上面审
            if ($type == 1) {
                $where[] = ['a.prepared', '=', 0];//没有预录取
                $where[] = ['a.resulted', '=', 0];//没有录取
                $where[] = ['a.signed', '=', 0];//没有入学报到
                $where[] = ['a.public_school_id', '=', $school_id];//线上面审意愿学校
            }
            //线下面审
            if ($type == 2) {
                $where[] = ['a.prepared', '=', 0];//预录取
                $where[] = ['a.resulted', '=', 0];//没有录取
                $where[] = ['a.signed', '=', 0];//没有入学报到
                $where[] = ['a.region_id', '=', $school['region_id'] ];//公办学校所属区县

                //无搜索条件 不显示数据
                if(isset($this->result['keyword']) && empty($this->result['keyword']) ){
                    $where[] = [Db::raw(1), '=', 0];
                }
            }
            //预录取
            if ($type == 3) {
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 0];//录取
                $where[] = ['a.signed', '=', 0];//没有入学报到
                $where[] = ['a.public_school_id', '=', $school_id];//公办预录取学校
            }
            //录取
            if ($type == 4) {
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.offlined', '=', 0];//不是线下录取
                $where[] = ['a.admission_type', '>', 0];
                $where[] = ['a.result_school_id', '=', $school_id];//最终录取学校
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
            //最后一条消息
            $query = Db::name('UserMessage')->alias('m')->where('m.user_apply_id', Db::raw('a.id'))
                ->field(['contents'])->order('m.create_time', 'DESC')->limit(1)->buildSql();

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
                    'a.fill_time' => 'fill_time',
                    $query => 'last_message',
                    'a.admission_type' => 'admission_type',
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
                    $three_syndromes_name = $this->getThreeSyndromesName($v);

                    $list['data'][$k]['birthplace_status_name'] = $birthplace_status_name;
                    $list['data'][$k]['relation_name'] = $relation_name;
                    $list['data'][$k]['house_status_name'] = $house_status_name;
                    $list['data'][$k]['three_syndromes_name'] = $three_syndromes_name;
                    $list['data'][$k]['student_status_name'] = $student_status_name;
                    $list['data'][$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type']);
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
                    $three_syndromes_name = $this->getThreeSyndromesName($v);

                    $list[$k]['birthplace_status_name'] = $birthplace_status_name;
                    $list[$k]['relation_name'] = $relation_name;
                    $list[$k]['house_status_name'] = $house_status_name;
                    $list[$k]['three_syndromes_name'] = $three_syndromes_name;
                    $list[$k]['student_status_name'] = $student_status_name;
                    $list[$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'] );
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

    //录取方式
    private function getAdmissionTypeName($type): string
    {
        $name = '-';
        switch ($type){
            case 1: $name = '派位'; break;
            case 2: $name = '线上审核'; break;
            case 3: $name = '到校审核'; break;
            case 4: $name = '派遣'; break;
            case 5: $name = '调剂'; break;
            case 6: $name = '政策生'; break;
        }
        return $name;
    }
}