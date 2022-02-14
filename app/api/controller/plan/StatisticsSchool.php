<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */

namespace app\api\controller\plan;

use app\common\controller\Education;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\facade\Db;
use think\response\Json;
use app\common\validate\basic\PlanApply as validate;

class StatisticsSchool extends Education
{
    /**
     * 学校学位统计列表
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
                //市级以上权限  按区县统计
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    /*$plan_id = 0;
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::table("deg_plan")->find($plan_id);
                    } else {
                        //throw new \Exception('计划ID参数错误');
                    }

                    $where = [];
                    $where[] = ['disabled', '=', 0];
                    $where[] = ['deleted', '=', 0];
                    //$where[] = ['parent_id', '>', 0];

                    $list = Db::name('sys_region')->where($where)->field(['id', 'region_name',])
                        ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v) {
                        $used_total = 0;

                        $list['data'][$k]['plan_id'] = $plan_id;
                        $list['data'][$k]['region_id'] = $v['id'];
                        $list['data'][$k]['plan_name'] = isset($plan['plan_name']) ?? '';
                        $list['data'][$k]['region_name'] = $v['region_name'];

                        $total_where = [];
                        if($v['id'] == 1){
                            $result[$k]['region_name'] = '市教育局';
                            //市直学校
                            $school_ids = Db::name('sys_school')
                                ->where([['deleted', '=', 0], ['disabled', '=', 0], ['directly', '=', 1], ])
                                ->column('id');
                        }else{
                            $school_ids = Db::name('sys_school')
                                ->where([['deleted', '=', 0], ['region_id', '=', $v['id']],
                                    ['disabled', '=', 0], ['directly', '=', 0], ])
                                ->column('id');
                        }
                        $total_where[] = ['school_id', 'in', $school_ids];


                        $total_where[] = ['plan_id', '=', $plan_id];
                        $total_where[] = ['deleted', '=', 0];
                        $apply_total = Db::name('plan_apply')->where($total_where)->sum('apply_total');
                        $total_where[] = ['status', '=', 1];
                        $spare_total = Db::name('plan_apply')->where($total_where)->sum('spare_total');

                        $list['data'][$k]['apply_total'] = $apply_total;
                        $list['data'][$k]['spare_total'] = $spare_total;
                        $list['data'][$k]['used_total'] = $used_total;
                    }
                    $list['resources'] = $res_data;*/
                    $where = '';
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = intval($this->result['plan_id']);
                        $where = ' AND p.plan_id = ' . $plan_id . ' ';
                    }

                    $preg = '/^\d+$/u';
                    $page = 1;
                    if ($this->request->has('curr') && $this->result['curr']) {
                        if (preg_match($preg, $this->result['curr']) == 0){
                            throw new \Exception(Lang::get('check_fail'));
                        }
                        $page = $this->result['curr'];
                        if($page < 1){
                            $page = 1;
                        }
                    }
                    $pageSize = 10;
                    if ($this->request->has('pagesize') && $this->result['pagesize']) {
                        if (preg_match($preg, $this->result['pagesize']) == 0){
                            throw new \Exception(Lang::get('check_fail'));
                        }
                        $pageSize = intval($this->result['pagesize']);
                        if($pageSize < 10){
                            $pageSize = 10;
                        }
                    }
                    $offset = intval(($page - 1) * $pageSize);

                    $sql = "SELECT sr.id AS region_id, sr.region_name, " .
                        " p.plan_id, p.plan_name, p.school_type " .
                        " FROM deg_sys_region AS sr CROSS JOIN " .
                        " (SELECT id AS plan_id, plan_name, school_type FROM deg_plan " .
                        " WHERE plan_time = '" . date('Y') . "' AND deleted = 0 ) AS p " .
                        " WHERE sr.disabled = 0 AND sr.deleted = 0 " . $where;
                    $query_total = Db::query($sql);
                    $total = count($query_total);
                    $sql .= " ORDER BY sr.id LIMIT " . $offset . ", " . $pageSize . " ";
                    $result = Db::query($sql);

                    foreach ((array)$result as $k => $v) {
                        $used_total = 0;
                        $where = [];
                        $where[] = ['a.deleted', '=', 0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['a.prepared', '=', 1];//预录取
                        $where[] = ['a.resulted', '=', 1];//录取
                        $where[] = ['a.school_type', '=', $v['school_type']];//学校类型

                        if($v['region_id'] == 1){
                            $result[$k]['region_name'] = '市教育局';

                            //市直学校
                            $school_ids = Db::name('sys_school')
                                ->where([['deleted', '=', 0], ['disabled', '=', 0], ['directly', '=', 1], ])
                                ->column('id');

                            $where[] = ['a.result_school_id', 'in', $school_ids];//录取学校
                            $used_total = Db::name('UserApply')->alias('a')->where($where)->count();
                        }else{
                            $school_ids = Db::name('sys_school')
                                ->where([['deleted', '=', 0], ['region_id', '=', $v['region_id']],
                                    ['disabled', '=', 0], ['directly', '=', 0], ['central_id', '=', 0], ])
                                ->column('id');

                            $where[] = ['a.result_school_id', 'in', $school_ids];//录取学校
                            $used_total = Db::name('UserApply')->alias('a')->where($where)->count();
                        }
                        $total_where = [];
                        $total_where[] = ['school_id', 'in', $school_ids];
                        $total_where[] = ['plan_id', '=', $v['plan_id']];
                        $total_where[] = ['deleted', '=', 0];
                        $apply_total = Db::name('plan_apply')->where($total_where)->sum('apply_total');

                        $total_where[] = ['status', '=', 1];
                        $spare_total = Db::name('plan_apply')->where($total_where)->sum('spare_total');

                        $result[$k]['apply_total'] = $apply_total;
                        $result[$k]['spare_total'] = $spare_total;
                        $result[$k]['used_total'] = $used_total;
                    }
                    $list = [
                        'total' => $total,
                        'per_page' => $pageSize,
                        'current_page' => $page,
                        'data' => $result,
                    ];
                }else{
                    //区级、教管会按学校统计
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['s.deleted', '=', 0];
                    $where[] = ['s.disabled', '=', 0];

                    if($this->request->has('plan_id') && $this->result['plan_id'] > 0 )
                    {
                        $plan_id = $this->result['plan_id'];
                        $where[] = ['plan_id', '=', $plan_id];
                    }
                    /*
                    $_where = [];
                    //公办、民办、小学、初中权限
                    $school_where = $this->getSchoolWhere();
                    $_where[] = $school_where['school_attr'];
                    $_where[] = $school_where['school_type'];
                    $_where[] = ['disabled', '=', 0];

                    //教管会权限
                    if($this->userInfo['grade_id'] == $this->middle_grade) {
                        if (isset($this->userInfo['central_id']) && $this->userInfo['central_id'] > 0) {
                            $central_id = $this->userInfo['central_id'];

                            $school_ids = Db::name('sys_school')
                                ->where([['deleted', '=', 0], ['central_id', '=', $central_id]])
                                ->where($_where)->column('id');

                            $where[] = ['a.school_id', 'in', $school_ids];
                        } else {
                            throw new \Exception('教管会管理员所属教管会ID设置错误');
                        }
                        $res_data['middle_auth'] = [
                            'status_name' => '教管会权限',
                            'grade_status' => 1
                        ];
                    }else{
                        if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1) {
                            $region_id = $this->userInfo['region_id'];
                            $school_ids = Db::name('sys_school')
                                ->where([['deleted', '=', 0], ['region_id', '=', $region_id]])
                                ->where($_where)->column('id');

                            $where[] = ['a.school_id', 'in', $school_ids];
                        }else{
                            throw new \Exception('区县管理员所属区县ID设置错误');
                        }
                    }*/
                    $role_school = $this->getSchoolIdsByRole(true);//不含教学点
                    if($role_school['code'] == 0) {
                        throw new \Exception($role_school['msg']);
                    }
                    if($role_school['bounded'] ){
                        $where[] = ['a.school_id', 'in', $role_school['school_ids']];
                    }
                    if( isset($role_school['middle_auth']) ){
                        $res_data['middle_auth'] = $role_school['middle_auth'];
                    }
                    if( isset($role_school['region_auth']) ){
                        $res_data['region_auth'] = $role_school['region_auth'];
                    }
                    if( isset($role_school['school_auth']) ){
                        $res_data['school_auth'] = $role_school['school_auth'];
                    }

                    $query = Db::name('PlanApply')->alias('a')
                        ->group('a.school_id');
                    //批复学位
                    $querySpare = Db::name('PlanApply')
                        ->field('school_id, SUM(spare_total) AS spare_total')
                        ->group('school_id')
                        ->where('status', 1)->where('deleted', 0)->buildSql();
                    //占用学位
                    $condition = [];
                    $condition[] = ['a.deleted', '=', 0];
                    $condition[] = ['a.voided', '=', 0];//没有作废
                    $condition[] = ['a.prepared', '=', 1];//预录取
                    $condition[] = ['a.resulted', '=', 1];//录取
                    $queryUsed = Db::name('UserApply')->alias('a')
                        ->field('result_school_id AS school_id, COUNT(*) AS used_total')
                        ->group('result_school_id')
                        ->where($condition)->buildSql();

                    $list = $query->join([
                            $querySpare => 'spare'
                        ], 'a.school_id = spare.school_id', 'LEFT')
                        ->join([
                            $queryUsed => 'used'
                        ], 'a.school_id = used.school_id', 'LEFT')
                        ->join([
                            'deg_plan' => 'p'
                        ], 'p.id = a.plan_id' )
                        ->join([
                            'deg_sys_school' => 's'
                        ], 's.id = a.school_id')
                        ->field([
                            'a.id',
                            'p.plan_name',
                            's.school_name' => 'school_name',
                            'SUM(apply_total)' => 'apply_total',
                            'IFNULL(spare.spare_total, 0)' => 'spare_total',
                            'IFNULL(used.used_total, 0)' => 'used_total',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    /*$list = Db::name('plan_apply')->alias('a')
                        ->join([
                            'deg_plan' => 'p'
                        ], 'p.id = a.plan_id' )
                        ->join([
                            'deg_sys_school' => 's'
                        ], 's.id = a.school_id')
                        ->field([
                            'a.id',
                            'p.plan_name',
                            's.school_name' => 'school_name',
                            'a.apply_total' => 'apply_total',
                            'a.spare_total' => 'spare_total',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();*/

                    /*foreach ((array)$list['data'] as $k => $v){
                        $list['data'][$k]['used_total'] = 0;
                    }*/
                }
                $list['resources'] = $res_data;
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
     * 学位统计查看
     * @return \think\response\Json
     */
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                //市级查看按学校分页展示
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                    } else {
                        throw new \Exception('招生计划ID为空');
                    }
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $region_id = $this->result['region_id'];
                    } else {
                        throw new \Exception('区县ID为空');
                    }

                    $plan = Db::table("deg_plan")->find($plan_id);
                    if (!$plan) {
                        throw new \Exception('未找到计划信息');
                    }

                    if($region_id == 1){
                        //市直
                        $school_ids = Db::name('sys_school')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['directly', '=', 1], ])
                            ->column('id');
                    }else {
                        $school_ids = Db::name('sys_school')
                            ->where([['deleted', '=', 0], ['region_id', '=', $region_id], ['directly', '=', 0],
                                ['school_type', '=', $plan['school_type']], ['disabled', '=', 0], ['central_id', '=', 0] ])
                            ->column('id');
                    }

                    $where = [];
                    $where[] = ['school_id', 'in', $school_ids];
                    $where[] = ['plan_id', '=', $plan_id];
                    $where[] = ['a.deleted', '=', 0];

                    $list = Db::name('plan_apply')->alias('a')
                        ->join([
                            'deg_plan' => 'p'
                        ], 'p.id = a.plan_id')
                        ->join([
                            'deg_sys_school' => 's'
                        ], 's.id = a.school_id')
                        ->field([
                            'p.plan_name',
                            'a.school_id',
                            's.school_name' => 'school_name',
                            'SUM(a.apply_total)' => 'apply_total',
                            'SUM(a.spare_total)' => 'spare_total',
                        ])->where($where)->group('a.school_id,a.plan_id')
                        ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ((array)$list['data'] as $k => $v) {
                        $where = [];
                        $where[] = ['a.deleted', '=', 0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['a.prepared', '=', 1];//预录取
                        $where[] = ['a.resulted', '=', 1];//录取
                        $where[] = ['a.result_school_id', '=', $v['school_id']];//录取学校
                        $used_total = Db::name('UserApply')->alias('a')->where($where)->count();

                        $list['data'][$k]['used_total'] = $used_total;
                    }
                }else{
                    $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                    //  如果数据不合法，则返回
                    if ($checkData['code'] != 1) {
                        throw new \Exception($checkData['msg']);
                    }
                    $list = Db::name('plan_apply')->where('id', $this->result['id'])->find();
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
        }else{}
    }

    /**
     * 招生计划下拉列表
     * @return Json
     */
    public function getSelectList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted','=', 0];
                $where[] = ['plan_time','=', date('Y')];

                $data = Db::name('plan')->where($where)->field(['id', 'plan_name',])->select();
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
     * 学校学位统计导出【区级】
     * @return Json
     */
    public function export(): Json
    {
        if($this->request->isPost())
        {
            try {
                $data = [];
                if ($this->userInfo['grade_id'] == $this->area_grade){
                    //区级、教管会按学校统计
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['s.deleted', '=', 0];
                    $where[] = ['s.disabled', '=', 0];

                    if($this->request->has('plan_id') && $this->result['plan_id'] > 0 )
                    {
                        $plan_id = $this->result['plan_id'];
                        $where[] = ['plan_id', '=', $plan_id];
                    }

                    $role_school = $this->getSchoolIdsByRole(true);//不含教学点
                    if($role_school['code'] == 0) {
                        throw new \Exception($role_school['msg']);
                    }
                    if($role_school['bounded'] ){
                        $where[] = ['a.school_id', 'in', $role_school['school_ids']];
                    }

                    $query = Db::name('PlanApply')->alias('a')
                        ->group('a.school_id');
                    //批复学位
                    $querySpare = Db::name('PlanApply')
                        ->field('school_id, SUM(spare_total) AS spare_total')
                        ->group('school_id')
                        ->where('status', 1)->where('deleted', 0)->buildSql();
                    //占用学位
                    $condition = [];
                    $condition[] = ['a.deleted', '=', 0];
                    $condition[] = ['a.voided', '=', 0];//没有作废
                    $condition[] = ['a.prepared', '=', 1];//预录取
                    $condition[] = ['a.resulted', '=', 1];//录取
                    $queryUsed = Db::name('UserApply')->alias('a')
                        ->field('result_school_id AS school_id, COUNT(*) AS used_total')
                        ->group('result_school_id')
                        ->where($condition)->buildSql();

                    $data = $query->join([
                        $querySpare => 'spare'
                    ], 'a.school_id = spare.school_id', 'LEFT')
                        ->join([
                            $queryUsed => 'used'
                        ], 'a.school_id = used.school_id', 'LEFT')
                        ->join([
                            'deg_plan' => 'p'
                        ], 'p.id = a.plan_id' )
                        ->join([
                            'deg_sys_school' => 's'
                        ], 's.id = a.school_id')
                        ->field([
                            'a.id',
                            'p.plan_name',
                            's.school_name' => 'school_name',
                            'SUM(apply_total)' => 'apply_total',
                            'IFNULL(spare.spare_total, 0)' => 'spare_total',
                            'IFNULL(used.used_total, 0)' => 'used_total',
                        ])->where($where)->order('a.plan_id')->select()->toArray();
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                $headArr = [
                    'id' => '编号',
                    'plan_name' => '招生计划',
                    'school_name' => '学校',
                    'apply_total' => '申请学位数量（合计）',
                    'spare_total' => '批复学位数量（合计）',
                    'used_total' => '已使用学位数量（合计）',
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
                $this->excelExport('学校学位统计_', $headArr, $data);

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
    private function excelExport($fileName = '', $headArr = [], $data = [])
    {

        $fileName .= "_" . date("Y_m_d", time());
        $spreadsheet = new Spreadsheet();
        $objPHPExcel = $spreadsheet->getActiveSheet();
        $firstColumn = 'A';// 设置表头

        $i = 0;
        foreach ($headArr as $k => $v) {
            $key = floor($i / 26);
            $firstNum = '';
            if ($key > 0) {
                # 当$k等于1,第一个列标签还是A,所以需要减去1
                $firstNum = chr(ord($firstColumn) + $key - 1);
            }
            $secondKey = $i % 26;
            $secondNum = chr(ord($firstColumn) + $secondKey);
            $column = $firstNum . $secondNum;

            $objPHPExcel->setCellValue($column . '1', $v);
            $i++;
        }
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(40);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(20);

        $spreadsheet->getActiveSheet()->getStyle('D')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('E')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('F')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $column = 2;
        foreach ($data as $k => $rows) { // 行写入
            $i = 0;
            foreach ($rows as $keyName => $value) { // 列写入
                $key = floor($i / 26);
                $firstNum = '';
                if ($key > 0) {
                    # 当$k等于1,第一个列标签还是A,所以需要减去1
                    $firstNum = chr(ord($firstColumn) + $key - 1);
                }
                $secondKey = $i % 26;
                $secondNum = chr(ord($firstColumn) + $secondKey);
                $span = $firstNum . $secondNum;

                if($keyName == 'apply_total' || $keyName == 'spare_total' || $keyName == 'used_total' ){
                    //$objPHPExcel->setCellValue($span . $column, $value);
                    $spreadsheet->getActiveSheet()->setCellValueExplicit($span . $column, $value, DataType::TYPE_STRING);
                }else{
                    $objPHPExcel->setCellValue($span . $column, $value);
                }
                $i++;
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