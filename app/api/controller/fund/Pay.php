<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */

namespace app\api\controller\fund;

use app\common\controller\Education;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\facade\Db;
use think\response\Json;

class Pay extends Education
{
    /**
     * 缴费统计列表
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                //公办、民办、小学、初中权限
                $condition = [];
                $school_where = $this->getSchoolWhere();
                $condition[] = $school_where['school_attr'];
                $condition[] = $school_where['school_type'];

                $_total_where = [];
                //市级以上权限  按区县统计
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('school_type') && $this->result['school_type'] > 0) {
                        $condition[] = ['school_type', '=', $this->result['school_type'] ];
                        $plan = Db::name("Plan")->where('deleted', 0)->where('plan_time', date('Y') )
                            ->where('school_type', $this->result['school_type'])->find();
                        if($plan){
                            $_total_where[] = ['plan_id', '=', $plan['id'] ];
                        }
                    }

                    $where = [];
                    $where[] = ['disabled', '=', 0];
                    $where[] = ['deleted', '=', 0];
                    //$where[] = ['parent_id', '>', 0];

                    $list = Db::name('sys_region')->where($where)->field(['id', 'region_name',])
                        ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v) {
                        if($v['id'] == 1){
                            $result[$k]['region_name'] = '市教育局';
                            //市直学校
                            $school_ids = Db::name('sys_school')->where($condition)
                                ->where([['deleted', '=', 0], ['disabled', '=', 0], ['school_attr', '=', 2], ['directly', '=', 1], ])
                                ->column('id');
                            $fee_array = Db::name('sys_school')->where($condition)
                                ->where([['deleted', '=', 0], ['disabled', '=', 0], ['school_attr', '=', 2], ['directly', '=', 1], ])
                                ->column('fee','id');
                            if(count($school_ids) == 0){
                                unset($list['data'][$k]);
                                continue;
                            }
                        }else{
                            $school_ids = Db::name('sys_school')->where($condition)
                                ->where([['deleted', '=', 0], ['region_id', '=', $v['id']], ['school_attr', '=', 2],
                                    ['disabled', '=', 0], ['directly', '=', 0], ])
                                ->column('id');
                            $fee_array = Db::name('sys_school')->where($condition)
                                ->where([['deleted', '=', 0], ['region_id', '=', $v['id']], ['school_attr', '=', 2],
                                    ['disabled', '=', 0], ['directly', '=', 0], ])
                                ->column('fee', 'id');
                        }
                        //审批学位数量
                        $total_where = [];
                        $total_where[] = ['school_id', 'in', $school_ids];
                        $total_where[] = ['deleted', '=', 0];
                        $total_where[] = ['status', '=', 1];
                        $spare_total = Db::name('PlanApply')->where($total_where)->where($_total_where)->sum('spare_total');
                        //预计人数
                        $apply_where = [];
                        $apply_where[] = ['deleted', '=', 0];
                        $apply_where[] = ['voided', '=', 0];
                        $apply_where[] = ['prepared', '=', 1];
                        $apply_where[] = ['resulted', '=', 1];
                        $apply_where[] = ['result_school_id', 'in', $school_ids];
                        $prepared_total = Db::name('UserApply')->where($apply_where)->count();
                        $prepared_array = Db::name('UserApply')->where($apply_where)->column('result_school_id');
                        //预计学费
                        $fee_total = 0;
                        foreach ($prepared_array as $item) {
                            $fee_total += $fee_array[$item] ?? 0;
                        }
                        $fee_total = round($fee_total, 2);
                        //未缴费
                        $apply_where[] = ['paid', '=', 0];
                        $not_pay_array = Db::name('UserApply')->where($apply_where)->column('result_school_id');
                        $not_pay_total = 0;
                        foreach ($not_pay_array as $item) {
                            $not_pay_total += $fee_array[$item] ?? 0;
                        }
                        $not_pay_total = round($not_pay_total, 2);
                        //已缴费
                        $pay_where = [];
                        $pay_where[] = ['deleted', '=', 0];
                        $pay_where[] = ['status', '=', 1];
                        $pay_where[] = ['refund_status', '=', 0];
                        $pay_where[] = ['school_id', 'in', $school_ids];
                        $pay_total = Db::name('UserCostPay')->where($pay_where)->sum('amount');
                        //申请退款数量
                        $refund_where = [];
                        $refund_where[] = ['deleted', '=', 0];
                        $refund_where[] = ['region_status', '=', 1];
                        $refund_where[] = ['school_id', 'in', $school_ids];
                        $refund_total = Db::name('UserCostRefund')->where($refund_where)->count();

                        $list['data'][$k]['region_id'] = $v['id'];
                        $list['data'][$k]['region_name'] = $v['region_name'];
                        $list['data'][$k]['school_count'] = count($school_ids);
                        $list['data'][$k]['spare_total'] = $spare_total;
                        $list['data'][$k]['prepared_total'] = $prepared_total;
                        $list['data'][$k]['fee_total'] = $fee_total;
                        $list['data'][$k]['pay_total'] = $pay_total;
                        $list['data'][$k]['not_pay_total'] = $not_pay_total;
                        $list['data'][$k]['refund_total'] = $refund_total;
                    }
                    $key = count($list['data']);
                    $list['data'][$key]['region_name'] = '合计';
                    $list['data'][$key]['school_count'] = round(array_sum(array_column($list['data'], 'school_count')), 2);
                    $list['data'][$key]['spare_total'] = round(array_sum(array_column($list['data'], 'spare_total')), 2);
                    $list['data'][$key]['prepared_total'] = round(array_sum(array_column($list['data'], 'prepared_total')), 2);
                    $list['data'][$key]['fee_total'] = round(array_sum(array_column($list['data'], 'fee_total')), 2);
                    $list['data'][$key]['pay_total'] = round(array_sum(array_column($list['data'], 'pay_total')), 2);
                    $list['data'][$key]['not_pay_total'] = round(array_sum(array_column($list['data'], 'not_pay_total')), 2);
                    $list['data'][$key]['refund_total'] = round(array_sum(array_column($list['data'], 'refund_total')), 2);
                }


                //区级 按学校统计
                if($this->userInfo['grade_id'] == $this->area_grade){
                    $where = [];
                    $where[] = ['s.deleted', '=', 0];
                    $where[] = ['s.deleted', '=', 0];
                    $where[] = ['s.directly', '=', 0];

                    $school_where = $this->getSchoolWhere('s');
                    $where[] = $school_where['school_attr'];
                    $where[] = $school_where['school_type'];

                    if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1) {
                        $region_id = $this->userInfo['region_id'];
                        $where[] = ['s.region_id', '=', $region_id];
                    }else{
                        throw new \Exception('区县管理员所属区县ID设置错误');
                    }
                    if($this->request->has('keyword') && $this->result['keyword'] != '')
                    {
                        $where[] = ['s.school_name|s.school_code|s.simple_code','like', '%' . $this->result['keyword'] . '%'];
                    }
                    $where[] = ['s.school_attr', '=', 2];//民办

                    //预计人数
                    $prepared_where = [];
                    $prepared_where[] = ['a.deleted', '=', 0];
                    $prepared_where[] = ['a.voided', '=', 0];//没有作废
                    $prepared_where[] = ['a.prepared', '=', 1];//预录取
                    $prepared_where[] = ['a.resulted', '=', 1];//录取
                    $prepared_where[] = ['a.school_attr', '=', 2];
                    $queryPrepared = Db::name('UserApply')->alias('a')
                        ->field('result_school_id AS school_id, COUNT(*) AS prepared_total')
                        ->group('result_school_id')
                        ->where($prepared_where)->buildSql();
                    //预录取人数
                    $prepared_where[] = ['a.paid', '=', 0];
                    $prepared = Db::name('UserApply')->alias('a')
                        ->field('result_school_id AS school_id, COUNT(*) AS prepared_total')
                        ->group('result_school_id')
                        ->where($prepared_where)->buildSql();
                    //已缴学费
                    $queryPay = Db::name('UserCostPay')
                        ->field('school_id, SUM(amount) AS amount_total')
                        ->group('school_id')->where('refund_status', 0)
                        ->where('status', 1)->where('deleted', 0)->buildSql();
                    //退费申请
                    $queryRefund = Db::name('UserCostRefund')
                        ->field('school_id, COUNT(*) AS refund_total')
                        ->group('school_id')
                        ->where('region_status', 1)->where('deleted', 0)->buildSql();

                    $list = Db::name('SysSchool')->alias('s')
                        ->join([
                            $queryPrepared => 'prepared'
                        ], 's.id = prepared.school_id', 'LEFT')
                        ->join([
                            $prepared => 'pre'
                        ], 's.id = pre.school_id', 'LEFT')
                        ->join([
                            $queryPay => 'pay'
                        ], 's.id = pay.school_id', 'LEFT')
                        ->join([
                            $queryRefund => 'refund'
                        ], 's.id = refund.school_id', 'LEFT')
                        ->field([
                            's.id',
                            's.school_name',
                            's.fee' => 'fee_total',
                            'IFNULL(prepared.prepared_total, 0) * s.fee' => 'prepared_total',
                            'IFNULL(pre.prepared_total, 0) * s.fee' => 'not_pay_total',
                            'IFNULL(pay.amount_total, 0)' => 'amount_total',
                            'IFNULL(refund.refund_total, 0)' => 'refund_total',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

//                    foreach ($list['data'] as $k => $v){
//                        $list['data'][$k]['not_pay_total'] = round($v['prepared_total'] - $v['amount_total'], 2);
//                    }

                    $res_data['region_auth'] = [
                        'status_name' => '区级权限',
                        'grade_status' => 1
                    ];
                }


                //学校 按人统计
                if($this->userInfo['grade_id'] == $this->school_grade){
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['a.voided', '=', 0];//没有作废
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.resulted', '=', 1];//录取
                    $where[] = ['a.school_attr', '=', 2];
                    //$where[] = ['c.status', '=', 1];

                    if (isset($this->userInfo['school_id'])) {
                        $school_id = $this->userInfo['school_id'];
                        $where[] = ['a.result_school_id', '=', $school_id];
                        $fee = Db::name('SysSchool')->where('id', $school_id)->value('fee');
                    } else {
                        throw new \Exception('管理员没有关联学校ID');
                    }
                    if($this->request->has('keyword') && $this->result['keyword'] != '')
                    {
                        $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
                    }
                    if($this->request->has('status') && $this->result['status'] !== '')
                    {
                        if($this->result['status'] == 1) {
                            $where[] = ['c.status', '=', 1];
                        }else{
                            $user_apply_ids = Db::name('UserApply')->alias('a')
                                ->join([
                                    'deg_user_cost_pay' => 'c'
                                ], 'c.user_apply_id = a.id and c.status = 1 and c.refund_status = 0 and c.deleted = 0 ', 'LEFT')
                                ->where($where)->where('c.status', 1)->column('a.id');
                            $where[] = ['a.id', 'not in', $user_apply_ids];
                        }
                    }

                    $list = Db::name('UserApply')->alias('a')
                        ->join([
                            'deg_user_apply_detail' => 'd'
                        ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                        ->join([
                            'deg_user_cost_pay' => 'c'
                        ], 'c.user_apply_id = a.id and c.status = 1 and c.refund_status = 0 ', 'LEFT')
                        ->field([
                            'd.child_name' => 'real_name',
                            'd.child_idcard' => 'id_card',
                            'd.mobile' => 'mobile',
                            'IFNULL(c.pay_amount, 0)' => 'amount',
                            'IFNULL(c.status, 0)' => 'status',
                            "IFNULL(c.pay_time, '-')" => 'pay_time',
                        ])
                        ->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v){
                        $list['data'][$k]['total_fee'] = $fee;
                        if(isset($v['status']) && $v['status'] == 1){
                            $list['data'][$k]['status_name'] = '已缴费';
                        }else{
                            $list['data'][$k]['status_name'] = '未缴费';
                        }
                    }

                    //预录取人数
                    $prepared_where = [];
                    $prepared_where[] = ['a.deleted', '=', 0];
                    $prepared_where[] = ['a.voided', '=', 0];//没有作废
                    $prepared_where[] = ['a.prepared', '=', 1];//预录取
                    $prepared_where[] = ['a.resulted', '=', 1];//录取
                    $prepared_where[] = ['a.school_attr', '=', 2];
                    $prepared_where[] = ['a.result_school_id', '=', $school_id];
                    $prepared_total_count = Db::name('UserApply')->alias('a')->where($prepared_where)->count();
                    $prepared_where[] = ['a.paid', '=', 0];//未缴费
                    $prepared_count = Db::name('UserApply')->alias('a')->where($prepared_where)->count();
                    //预计应收学费
                    $prepared_total = round($prepared_total_count * $fee, 2);
                    //已缴费
                    $pay_where = [];
                    $pay_where[] = ['deleted', '=', 0];
                    $pay_where[] = ['status', '=', 1];
                    $pay_where[] = ['refund_status', '=', 0];
                    $pay_where[] = ['school_id', '=', $school_id];
                    $pay = Db::name('UserCostPay')
                        ->field(['COUNT(*)' => 'pay_count', 'SUM(pay_amount)' => 'total_amount'])
                        ->where($pay_where)->find();

                    $list['payment'] = [
                        'prepared_count' => $prepared_count,
                        'fee' => $fee,
                        'prepared_total' => $prepared_total,
                        'pay_count' => $pay['pay_count'],
                        'pay_amount' => $pay['total_amount'] ?? 0,
                        'not_pay_count' => $prepared_count,//$prepared_total_count - $pay['pay_count'],
                        'not_pay_amount' => round($prepared_count * $fee, 2),//round($prepared_total - $pay['total_amount'], 2),
                    ];

                    $res_data['school_auth'] = [
                        'status_name' => '学校权限',
                        'grade_status' => 1
                    ];
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
     * 市级查看
     * @return \think\response\Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $list = [];
                //市级查看按学校分页展示
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    $where = [];
                    $where[] = ['s.deleted', '=', 0];
                    $where[] = ['s.disabled', '=', 0];
                    $where[] = ['s.school_attr', '=', 2];

                    if ($this->request->has('school_type') && $this->result['school_type'] > 0) {
                        $where[] = ['s.school_type', '=', $this->result['school_type'] ];
                    }
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $region_id = $this->result['region_id'];
                        if($region_id == 1){
                            $where[] = ['s.directly', '=', 1];
                        }else{
                            $where[] = ['s.directly', '=', 0];
                            $where[] = ['s.region_id', '=', $region_id];
                        }
                    } else {
                        throw new \Exception('区县ID参数错误！');
                    }

                    $school_where = $this->getSchoolWhere('s');
                    $where[] = $school_where['school_attr'];
                    $where[] = $school_where['school_type'];

                    //预计人数
                    $prepared_where = [];
                    $prepared_where[] = ['a.deleted', '=', 0];
                    $prepared_where[] = ['a.voided', '=', 0];//没有作废
                    $prepared_where[] = ['a.prepared', '=', 1];//预录取
                    $prepared_where[] = ['a.resulted', '=', 1];//录取
                    $prepared_where[] = ['a.school_attr', '=', 2];
                    $queryPrepared = Db::name('UserApply')->alias('a')
                        ->field('result_school_id AS school_id, COUNT(*) AS prepared_total')
                        ->group('result_school_id')
                        ->where($prepared_where)->buildSql();
                    //预录取人数
                    $prepared_where[] = ['a.paid', '=', 0];
                    $prepared = Db::name('UserApply')->alias('a')
                        ->field('result_school_id AS school_id, COUNT(*) AS prepared_total')
                        ->group('result_school_id')
                        ->where($prepared_where)->buildSql();
                    //已缴学费
                    $queryPay = Db::name('UserCostPay')
                        ->field('school_id, SUM(amount) AS amount_total')
                        ->group('school_id')->where('refund_status', 0)
                        ->where('status', 1)->where('deleted', 0)->buildSql();
                    //退费申请
                    $queryRefund = Db::name('UserCostRefund')
                        ->field('school_id, COUNT(*) AS refund_total')
                        ->group('school_id')
                        ->where('region_status', 1)->where('deleted', 0)->buildSql();

                    $list = Db::name('SysSchool')->alias('s')
                        ->join([
                            $queryPrepared => 'prepared'
                        ], 's.id = prepared.school_id', 'LEFT')
                        ->join([
                            $prepared => 'pre'
                        ], 's.id = pre.school_id', 'LEFT')
                        ->join([
                            $queryPay => 'pay'
                        ], 's.id = pay.school_id', 'LEFT')
                        ->join([
                            $queryRefund => 'refund'
                        ], 's.id = refund.school_id', 'LEFT')
                        ->field([
                            's.id',
                            's.school_name',
                            's.fee' => 'fee_total',
                            'IFNULL(prepared.prepared_total, 0)' => 'prepared',
                            'IFNULL(prepared.prepared_total, 0) * s.fee' => 'prepared_total',
                            'IFNULL(pre.prepared_total, 0) * s.fee' => 'not_pay_total',
                            'IFNULL(pay.amount_total, 0)' => 'amount_total',
                            'IFNULL(refund.refund_total, 0)' => 'refund_total',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

//                    foreach ($list['data'] as $k => $v){
//                        $list['data'][$k]['not_pay_total'] = round($v['prepared_total'] - $v['amount_total'], 2);
//                    }
                }


                //区级查看 按申报学生展示
                if($this->userInfo['grade_id'] == $this->area_grade)
                {
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['d.deleted', '=', 0];
                    $where[] = ['c.deleted', '=', 0];
                    $where[] = ['a.voided', '=', 0];//没有作废
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.resulted', '=', 1];//录取
                    $where[] = ['a.school_attr', '=', 2];

                    if ($this->request->has('school_id') && $this->result['school_id'] > 0) {
                        $school_id = $this->result['school_id'];
                        $where[] = ['a.result_school_id', '=', $school_id];
                        $fee = Db::name('SysSchool')->where('id', $school_id)->value('fee');
                    } else {
                        throw new \Exception('学校ID参数错误！');
                    }

                    $list = Db::name('UserApply')->alias('a')
                        ->join([
                            'deg_user_apply_detail' => 'd'
                        ], 'd.child_id = a.child_id', 'LEFT')
                        ->join([
                            'deg_user_cost_pay' => 'c'
                        ], 'c.user_apply_id = a.id and c.status = 1 and c.refund_status = 0 ', 'LEFT')
                        ->field([
                            'd.child_name' => 'real_name',
                            'd.child_idcard' => 'id_card',
                            'd.mobile' => 'mobile',
                            'IFNULL(c.pay_amount, 0)' => 'amount',
                            'IFNULL(c.status, 0)' => 'status',
                            "IFNULL(c.pay_time, '-')" => 'pay_time',
                        ])
                        ->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v){
                        $list['data'][$k]['total_fee'] = $fee;
                        if(isset($v['status']) && $v['status'] == 1){
                            $list['data'][$k]['status_name'] = '已缴费';
                        }else{
                            $list['data'][$k]['status_name'] = '未缴费';
                        }
                    }
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
     * 资源
     * @return Json
     */
    public function resView(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = [];

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary', 'SYSXXLX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                foreach ($getData['data'] as $value){
                    $data['school_type'][] =[
                        'id' => $value['dictionary_value'],
                        'type_name' => $value['dictionary_name']
                    ];
                }
                $data['status_list'][] =['id' => 0, 'name' => '未缴费'];
                $data['status_list'][] =['id' => 1, 'name' => '已缴费'];

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
     * 学校缴费明细导出
     * @return Json
     */
    public function export(): Json
    {
        if($this->request->isPost())
        {
            try {
                $where = [];
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['a.voided', '=', 0];//没有作废
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.school_attr', '=', 2];

                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                    $where[] = ['a.result_school_id', '=', $school_id];
                    $fee = Db::name('SysSchool')->where('id', $school_id)->value('fee');
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }
                if($this->request->has('keyword') && $this->result['keyword'] !== '')
                {
                    $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('status') && $this->result['status'] !== '')
                {
                    //$where[] = ['c.status','=', $this->result['status'] ];
                    if($this->result['status'] == 1) {
                        $where[] = ['c.status', '=', 1];
                    }else{
                        $user_apply_ids = Db::name('UserApply')->alias('a')
                            ->join([
                                'deg_user_cost_pay' => 'c'
                            ], 'c.user_apply_id = a.id and c.status = 1 and c.refund_status = 0 and c.deleted = 0 ', 'LEFT')
                            ->where($where)->where('c.status', 1)->column('a.id');
                        $where[] = ['a.id', 'not in', $user_apply_ids];
                    }
                }

                $list = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                    ->join([
                        'deg_user_cost_pay' => 'c'
                    ], 'c.user_apply_id = a.id and c.status = 1 and c.refund_status = 0 ', 'LEFT')
                    ->field([
                        'd.child_name' => 'real_name',
                        'd.child_idcard' => 'id_card',
                        'd.mobile' => 'mobile',
                        'IFNULL(c.pay_amount, 0)' => 'amount',
                        'IFNULL(c.status, 0)' => 'status',
                        "IFNULL(c.pay_time, '-')" => 'pay_time',
                    ])
                    ->where($where)->select()->toArray();

                $data = [];
                foreach ($list as $k => $v){
                    $data[$k]['real_name'] = $v['real_name'];
                    $data[$k]['id_card'] = $v['id_card'];
                    $data[$k]['mobile'] = $v['mobile'];
                    $data[$k]['total_fee'] = $fee;
                    $data[$k]['amount'] = $v['amount'];
                    $data[$k]['status_name'] = '未缴费';
                    $data[$k]['pay_time'] = $v['pay_time'];

                    if(isset($v['status']) && $v['status'] == 1){
                        $data[$k]['status_name'] = '已缴费';
                    }
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }


                $headArr = ['姓名', '身份证号', '家长手机号', '学费标准', '缴费金额', '缴费状态', '缴费时间'];
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
                        $this->excelExport('生源明细_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('生源明细_', $headArr, $data);
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

}