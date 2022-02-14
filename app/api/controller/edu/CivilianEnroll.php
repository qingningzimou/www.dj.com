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

class CivilianEnroll extends Education
{
    /**
     * 民办报名信息列表
     * @return \think\response\Json
     */
    public function getEnrollList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getListByType(1, true);
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
     * 民办招生页面资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            try {
                $data = [];
                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = $result['data'];
                foreach ($result['relation_list'] as $k => $v){
                    $data['relation_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['birthplace_list'] as $k => $v){
                    $data['birthplace_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['house_list'] as $k => $v){
                    $data['house_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['three_syndromes_list'] as $k => $v){
                    $data['three_syndromes_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['school_roll_list'] as $k => $v){
                    $data['school_roll_list'][] = ['id' => $k, 'name' => $v];
                }

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
        }else{}
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
     * 民办报名信息导出
     * @return Json
     */
    public function exportEnroll()
    {
        if($this->request->isPost())
        {
            try {
                $result = $this->getListByType(1, false);
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
                    unset($data[$k]['admission_type']);
                    unset($data[$k]['refund_status']);
                    unset($data[$k]['sign_name']);
                    unset($data[$k]['signe_time']);
                    unset($data[$k]['house_type']);
                    unset($data[$k]['insurance_status']);
                    unset($data[$k]['business_license_status']);
                    unset($data[$k]['residence_permit_status']);

                    //$data[$k]['id_card'] = "'" . $v['id_card'];
                    if($v['id_card'] == '' && $v['special_card']){
                        $data[$k]['id_card'] = $v['special_card'];
                    }
                    unset($data[$k]['special_card']);
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = ['编号', '姓名', '身份证号', '监护人手机号', '户籍', '监护人关系', '房产', '三证情况', '学籍情况', '学校类型', '学校性质', '学校名称'];
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
                        $this->excelExport('民办报名信息_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('民办报名信息_', $headArr, $data);
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
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(30);
        $spreadsheet->getActiveSheet()->getStyle('C')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(60);

        $column = 2;
        foreach ($data as $key => $rows) { // 行写入
            $span = ord("A");
            foreach ($rows as $keyName => $value) { // 列写入
                if($keyName == 'id_card' || $keyName == 'student_code'){
                    //$objPHPExcel->setCellValue(chr($span) . $column, $value . " ");
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
            $school = Db::name('SysSchool')->field('id, school_name, school_type, school_attr')->find($school_id);
            if(!$school){
                return ['code' => 0, 'msg' => '学校管理员学校ID关联学校不存在' ];
            }
            $dictionary = new FilterData();
            $typeData = $dictionary->resArray('dictionary','SYSXXLX');
            if(!$typeData['code']){
                throw new \Exception($typeData['msg']);
            }
            $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
            if(!$attrData['code']){
                throw new \Exception($attrData['msg']);
            }
            $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $school['school_type']);
            $school_type_name = '';
            if (count($schoolTypeData) > 0){
                $school_type_name = $schoolTypeData['dictionary_name'];
            }
            $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $school['school_attr']);
            $school_attr_name = '';
            if (count($schoolAttrData) > 0){
                $school_attr_name = $schoolAttrData['dictionary_name'];
            }

            $where = [];
            $where[] = ['a.deleted', '=', 0];
            $where[] = ['d.deleted', '=', 0];
            $where[] = ['a.voided', '=', 0];//没有作废
            $where[] = ['a.apply_school_id', '=', $school_id];//申请学校ID
            $where[] = ['a.school_attr', '=', $school['school_attr']];
            $where[] = ['a.school_type', '=', $school['school_type']];

            //报名信息
            if ($type == 1) {
                $where[] = ['a.prepared', '=', 0];//没有预录取
                $where[] = ['a.resulted', '=', 0];//没有录取
                $where[] = ['a.signed', '=', 0];//没有入学报到

                //户籍
                if($this->request->has('birthplace_status') && $this->result['birthplace_status'] > 0)
                {
                    $where[] = ['d.birthplace_status','=', $this->result['birthplace_status']];
                }
                //监护人关系
                if($this->request->has('relation_status') && $this->result['relation_status'] > 0)
                {
                    $where[] = ['d.guardian_relation','=', $this->result['relation_status']];
                }
                //房产匹配情况
                if($this->request->has('house_status') && $this->result['house_status'] > 0 )
                {
                    $where[] = ['d.house_status','=', $this->result['house_status']];
                }
                //三证情况
                if($this->request->has('three_syndromes_status') && $this->result['three_syndromes_status'] !== '')
                {
                    $where[] = ['d.house_type','=', 2];
                    $status_array = explode(',', $this->result['three_syndromes_status']);
                    if(in_array(0, $status_array)){
                        $where[] = ['d.business_license_status','=', 0];
                        $where[] = ['d.insurance_status','=', 0];
                        $where[] = ['d.residence_permit_status','=', 0];
                        $where[] = ['d.three_syndromes_status','=', 0];
                    }else if(in_array(4, $status_array)){
                        $where[] = ['d.business_license_status','=', 0];
                        $where[] = ['d.insurance_status','=', 0];
                        $where[] = ['d.residence_permit_status','=', 0];
                        $where[] = ['d.three_syndromes_status','=', 1];
                    } else{
                        if(in_array(1, $status_array)){
                            $where[] = ['d.business_license_status','=', 1];
                        }
                        if(in_array(2, $status_array)){
                            $where[] = ['d.insurance_status','=', 1];
                        }
                        if(in_array(3, $status_array)){
                            $where[] = ['d.residence_permit_status','=', 1];
                        }
                    }
                }
                //学籍情况
                if($this->request->has('school_roll_status') && $this->result['school_roll_status'] > 0 )
                {
                    $where[] = ['d.student_status','=', $this->result['school_roll_status']];
                }
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
                    'IF(a.specialed = 1, CONCAT(d.mobile, "_", a.child_id), "")' => 'special_card',
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
                        //$refund_status_name = isset($result['refund_list'][$v['refund_status']]) ? $result['refund_list'][$v['refund_status']] : '-';
                        $three_syndromes_name = $this->getThreeSyndromesName($v);

                        $list[$k]['birthplace_status_name'] = $birthplace_status_name;
                        $list[$k]['relation_name'] = $relation_name;
                        $list[$k]['house_status_name'] = $house_status_name;
                        $list[$k]['three_syndromes_name'] = $three_syndromes_name;
                        $list[$k]['student_status_name'] = $student_status_name;
                        //$list[$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'] );
                        //$list[$k]['refund_status_name'] = $refund_status_name;
                        $list[$k]['school_type_name'] = $school_type_name;
                        $list[$k]['school_attr_name'] = $school_attr_name;
                        $list[$k]['school_name'] = $school['school_name'];
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