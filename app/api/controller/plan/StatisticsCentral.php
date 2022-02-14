<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */

namespace app\api\controller\plan;

use app\common\controller\Education;
use think\facade\Lang;
use think\facade\Db;
use think\response\Json;
use app\common\validate\basic\PlanApply as validate;

class StatisticsCentral extends Education
{
    /**
     * 教管会学位统计列表
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $plan_list = Db::name('Plan')->where('deleted', 0)
                    ->where('plan_time', date('Y'))->select()->toArray();
                $plan_school_type = [];
                foreach ((array)$plan_list as $k => $v){
                    $plan_school_type[$v['id']] = $v['school_type'];
                }

                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    /*if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::table("deg_plan")->find($plan_id);
                    } else {
                        //throw new \Exception('计划ID参数错误');
                    }

                    $where = [];
                    $where[] = ['disabled', '=', 0];
                    $where[] = ['deleted', '=', 0];
                    $where[] = ['parent_id', '>', 0];

                    $list = Db::name('sys_region')->where($where)->field(['id', 'region_name',])
                        ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v) {
                        $used_total = 0;

                        $list['data'][$k]['plan_id'] = $plan_id;
                        $list['data'][$k]['region_id'] = $v['id'];
                        $list['data'][$k]['plan_name'] = isset($plan['plan_name']) ?? '';
                        $list['data'][$k]['region_name'] = $v['region_name'];

                        $total_where = [];
                        //区教管会ID
                        $central_ids = Db::name('central_school')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['region_id', '=', $v['id']] ])
                            ->column('id');
                        $total_where[] = ['central_id', 'in', $central_ids];

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
                        " p.plan_id, p.plan_name " .
                        " FROM deg_sys_region AS sr CROSS JOIN " .
                        " (SELECT id AS plan_id, plan_name FROM deg_plan " .
                        " WHERE plan_time = '" . date('Y') . "' AND deleted = 0 ) AS p " .
                        " WHERE sr.disabled = 0 AND sr.deleted = 0 AND sr.parent_id > 0 " . $where;
                    $query_total = Db::query($sql);
                    $total = count($query_total);
                    $sql .= " ORDER BY sr.id LIMIT " . $offset . ", " . $pageSize . " ";
                    $result = Db::query($sql);

                    foreach ((array)$result as $k => $v) {
                        $used_total = 0;
                        //区教管会ID
                        $central_ids = Db::name('central_school')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['region_id', '=', $v['region_id']] ])
                            ->column('id');

                        $total_where = [];
                        $total_where[] = ['central_id', 'in', $central_ids];
                        $total_where[] = ['plan_id', '=', $v['plan_id']];
                        $total_where[] = ['deleted', '=', 0];
                        $apply_total = Db::name('plan_apply')->where($total_where)->sum('apply_total');

                        $total_where[] = ['status', '=', 1];
                        $spare_total = Db::name('plan_apply')->where($total_where)->sum('spare_total');

                        $school_type = isset($plan_school_type[$v['plan_id']]) ? $plan_school_type[$v['plan_id']] : $v['plan_id'];
                        //占用学位
                        $school_ids = Db::name('SysSchool')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['directly', '=', 0], ['central_id', 'in', $central_ids], ])
                            ->column('id');
                        $where = [];
                        $where[] = ['a.deleted', '=', 0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['a.school_type', '=', $school_type];//学校类型
                        $where[] = ['a.prepared', '=', 1];//预录取
                        $where[] = ['a.resulted', '=', 1];//录取
                        $where[] = ['a.result_school_id', 'in', $school_ids];//录取学校
                        $used_total = Db::name('UserApply')->alias('a')->where($where)->count();


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
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    //区级按教管会统计
                    if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1) {
                        $region_id = $this->userInfo['region_id'];
                        //教管会ID
                        $central_ids = Db::name('central_school')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['region_id', '=', $region_id] ])
                            ->column('id');
                        $where[] = ['a.central_id', 'in', $central_ids];
                    }else{
                        throw new \Exception('区县管理员所属区县ID设置错误');
                    }
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $where[] = ['a.plan_id', '=', $plan_id];
                    }

                    $query = Db::name('PlanApply')->alias('a')
                        ->group('a.central_id,a.plan_id');

                    $querySpare = Db::name('PlanApply')
                        ->field('central_id, plan_id, SUM(spare_total) AS spare_total')
                        ->group('central_id, plan_id')
                        ->where('status', 1)->where('deleted', 0)->buildSql();

                    $list = $query->join([
                        $querySpare => 'spare'
                    ], 'a.central_id = spare.central_id and a.plan_id = spare.plan_id', 'LEFT')
                        ->join([
                            'deg_plan' => 'p'
                        ], 'p.id = a.plan_id' )
                        ->join([
                            'deg_central_school' => 'c'
                        ], 'c.id = a.central_id' )
                        ->field([
                            'a.id',
                            'a.plan_id',
                            'a.central_id',
                            'p.plan_name',
                            'c.central_name' => 'central_name',
                            'SUM(apply_total)' => 'apply_total',
                            'IFNULL(spare.spare_total, 0)' => 'spare_total',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ((array)$list['data'] as $k => $v){
                        $school_type = isset($plan_school_type[$v['plan_id']]) ? $plan_school_type[$v['plan_id']] : $v['plan_id'];
                        //占用学位
                        $school_ids = Db::name('SysSchool')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['directly', '=', 0], ['central_id', '=', $v['central_id']], ])
                            ->column('id');
                        $where = [];
                        $where[] = ['a.deleted', '=', 0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['a.school_type', '=', $school_type];//学校类型
                        $where[] = ['a.prepared', '=', 1];//预录取
                        $where[] = ['a.resulted', '=', 1];//录取
                        $where[] = ['a.result_school_id', 'in', $school_ids];//录取学校
                        $used_total = Db::name('UserApply')->alias('a')->where($where)->count();

                        $list['data'][$k]['used_total'] = $used_total;
                    }
                }
                //权限节点
                $list['resources'] = $this->getResources($this->userInfo, $this->request->controller());
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
                if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                    $plan_id = $this->result['plan_id'];
                } else {
                    throw new \Exception('招生计划ID为空');
                }
                $plan = Db::table("deg_plan")->find($plan_id);
                if (!$plan) {
                    throw new \Exception('未找到计划信息');
                }
                $where = [];
                $where[] = ['a.plan_id', '=', $plan_id];
                $where[] = ['a.deleted', '=', 0];
                //市级查看按学校分页展示
                if ($this->userInfo['grade_id'] >= $this->city_grade) {

                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $region_id = $this->result['region_id'];
                    } else {
                        throw new \Exception('区县ID为空');
                    }
                    //教管会ID
                    $central_ids = Db::name('central_school')
                        ->where([['deleted', '=', 0], ['disabled', '=', 0], ['region_id', '=', $region_id] ])
                        ->column('id');
                    $where[] = ['a.central_id', 'in', $central_ids];

                    $list = Db::name('PlanApply')->alias('a')
                        ->join([
                            'deg_plan' => 'p'
                        ], 'p.id = a.plan_id')
                        ->join([
                            'deg_central_school' => 'c'
                        ], 'c.id = a.central_id' )
                        ->field([
                            'p.plan_name',
                            'a.central_id',
                            'c.central_name' => 'central_name',
                            'SUM(a.apply_total)' => 'apply_total',
                            'SUM(a.spare_total)' => 'spare_total',
                        ])->where($where)->group('a.central_id,a.plan_id')
                        ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ((array)$list['data'] as $k => $v) {
                        //占用学位
                        $school_ids = Db::name('SysSchool')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['directly', '=', 0], ['central_id', '=', $v['central_id']], ])
                            ->column('id');
                        $where = [];
                        $where[] = ['a.deleted', '=', 0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['a.school_type', '=', $plan['school_type']];
                        $where[] = ['a.prepared', '=', 1];//预录取
                        $where[] = ['a.resulted', '=', 1];//录取
                        $where[] = ['a.result_school_id', 'in', $school_ids];//录取学校
                        $used_total = Db::name('UserApply')->alias('a')->where($where)->count();

                        $list['data'][$k]['used_total'] = $used_total;
                    }
                }else{
                    //区级统计教管会所属教学点
                    if ($this->request->has('central_id') && $this->result['central_id'] > 0) {
                        $central_id = $this->result['central_id'];
                    } else {
                        throw new \Exception('教管会ID参数错误');
                    }
                    $school_ids = Db::name('SysSchool')
                        ->where([['deleted', '=', 0], ['directly', '=', 0], ['disabled', '=', 0],
                            ['central_id', '=', $central_id] ])
                        ->column('id');
                    $where[] = ['a.school_id', 'in', $school_ids];

                    $used_where = [];
                    $used_where[] = ['a.deleted', '=', 0];
                    $used_where[] = ['a.voided', '=', 0];//没有作废
                    $used_where[] = ['a.school_type', '=', $plan['school_type']];
                    $used_where[] = ['a.prepared', '=', 1];//预录取
                    $used_where[] = ['a.resulted', '=', 1];//录取
                    $used_where[] = ['a.result_school_id', 'in', $school_ids];//录取学校
                    $queryUsed = Db::name('UserApply')->alias('a')->where($used_where)
                        ->field('result_school_id AS school_id, COUNT(*) AS used_total')
                        ->group('result_school_id')->buildSql();


                    $list = Db::name('PlanApply')->alias('a')
                        ->join([
                            'deg_plan' => 'p'
                        ], 'p.id = a.plan_id')
                        ->join([
                            'deg_sys_school' => 's'
                        ], 's.id = a.school_id' )
                        ->join([
                            $queryUsed => 'u'
                        ], 'u.school_id = a.school_id', 'LEFT')
                        ->field([
                            'p.plan_name',
                            's.school_name' => 'school_name',
                            'SUM(a.apply_total)' => 'apply_total',
                            'SUM(a.spare_total)' => 'spare_total',
                            'IFNULL(u.used_total, 0)' => 'used_total',
                        ])->where($where)->group('a.school_id,a.plan_id')
                        ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();
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
        }
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
}