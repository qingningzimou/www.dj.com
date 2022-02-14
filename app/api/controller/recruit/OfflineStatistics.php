<?php
/**
 * Created by PhpStorm.
 * User: PhpStorm
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\recruit;

use app\common\controller\Education;
use app\mobile\model\user\Child;
use app\mobile\model\user\Family;
use app\mobile\model\user\House;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use app\mobile\model\user\Apply as model;
use app\common\validate\recruit\Apply as validate;
use think\facade\Db;
use think\facade\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OfflineStatistics extends Education
{
    /**
     * 线下录取 学校统计报表 信息
     * @return \think\response\Json
     */
    public function getSchoolList(): Json
    {
        if ($this->request->isPost()) {
            try {
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                //市级以上权限  按区县统计
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::name("Plan")->find($plan_id);
                    } else {
                        throw new \Exception('计划ID参数错误');
                    }

                    $where = [];
                    $where[] = ['disabled', '=', 0];
                    $where[] = ['deleted', '=', 0];
                    //$where[] = ['parent_id', '>', 0];

                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $where[] = ['id', '=', $this->result['region_id']];
                    }

                    $list = Db::name('sys_region')->where($where)->field(['id', 'region_name',])
                        ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v) {
                        $list['data'][$k]['plan_id'] = $plan_id;
                        $list['data'][$k]['region_id'] = $v['id'];
                        //$list['data'][$k]['plan_name'] = isset($plan['plan_name']) ?? '';
                        $list['data'][$k]['region_name'] = $v['region_name'];

                        $total_where = [];
                        if($v['id'] == 1){
                            $list['data'][$k]['region_name'] = '市教育局';
                            //市直学校
                            $school_ids = Db::name('sys_school')->where('school_type', $plan['school_type'])
                                ->where([['deleted', '=', 0], ['disabled', '=', 0], ['directly', '=', 1], ['onlined', '=', 0], ])
                                ->column('id');
                        }else{
                            //不是教学点
                            $school_ids = Db::name('sys_school')->where('school_type', $plan['school_type'])
                                ->where([['deleted', '=', 0], ['region_id', '=', $v['id']], ['central_id', '=', 0],
                                    ['disabled', '=', 0], ['directly', '=', 0], ['onlined', '=', 0], ])
                                ->column('id');
                        }
                        $list['data'][$k]['school_count'] = count($school_ids);

                        $total_where[] = ['school_id', 'in', $school_ids];

                        //批复学位
                        $total_where[] = ['plan_id', '=', $plan_id];
                        $total_where[] = ['deleted', '=', 0];
                        $total_where[] = ['status', '=', 1];
                        $spare_total = Db::name('PlanApply')->where($total_where)->sum('spare_total');

                        //线下录取人数
                        $where = [];
                        $where[] = ['a.result_school_id', 'in', $school_ids];
                        $where[] = ['a.deleted','=',0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['a.prepared', '=', 1];//预录取
                        $where[] = ['a.resulted', '=', 1];//录取
                        $where[] = ['a.offlined', '=', 1];//线下录取
                        $where[] = ['a.region_id', '=', $v['id'] ];//区县
                        $where[] = ['a.school_type', '=', $plan['school_type'] ];//学校类型
                        $offline_count = Db::name('UserApply')->alias('a')->where($where)->count();

                        $list['data'][$k]['spare_total'] = $spare_total;
                        $list['data'][$k]['offline_count'] = $offline_count;
                        //剩余学位数量
                        $degree_surplus = $spare_total - $offline_count;
                        $degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;

                        $list['data'][$k]['degree_surplus'] = $degree_surplus;
                    }

                }else{
                    //区级、教管会按学校统计
                    $where = [];
                    $where[] = ['s.deleted', '=', 0];
                    $where[] = ['s.disabled', '=', 0];

                    if($this->request->has('plan_id') && $this->result['plan_id'] > 0 )
                    {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::name("Plan")->find($plan_id);
                        if (!$plan) {
                            throw new \Exception('未找到招生计划信息');
                        }
                        $where[] = ['s.school_type', '=', $plan['school_type']];
                    }
                    $role_school = $this->getSchoolIdsByRole(true);//不含教学点
                    if($role_school['code'] == 0) {
                        throw new \Exception($role_school['msg']);
                    }
                    $role_school['school_ids'] = Db::name('sys_school')->where('onlined', 0)
                        ->where('id', 'in', $role_school['school_ids'])
                        ->column('id');

                    $where[] = ['s.id', 'in', $role_school['school_ids']];
                    //批复学位
                    $querySpare = Db::name('PlanApply')
                        ->field('school_id, SUM(spare_total) AS spare_total')
                        ->group('school_id')->where('school_id', 'in', $role_school['school_ids'] )
                        ->where('status', 1)->where('deleted', 0)->buildSql();
                    //申请学位
                    $queryApply = Db::name('PlanApply')
                        ->field('school_id, SUM(apply_total) AS apply_total')
                        ->group('school_id')->where('school_id', 'in', $role_school['school_ids'] )
                        ->where('deleted', 0)->buildSql();
                    //录取人数
                    $queryOffline = Db::name('UserApply')
                        ->field('result_school_id, COUNT(*) AS admission_total')
                        ->group('result_school_id')
                        ->where('result_school_id', 'in', $role_school['school_ids'] )
                        ->where('deleted', 0)->where('voided', 0)->where('prepared', 1)
                        ->where('resulted', 1)->where('offlined', 1)->buildSql();

                    $list = Db::name('SysSchool')->alias('s')
                        ->join([
                            $querySpare => 'spare'
                        ], 's.id = spare.school_id', 'LEFT')
                        ->join([
                            $queryApply => 'apply'
                        ], 's.id = apply.school_id', 'LEFT')
                        ->join([
                            $queryOffline => 'offline'
                        ], 's.id = offline.result_school_id', 'LEFT')
                        ->field([
                            's.id',
                            's.school_name' => 'school_name',
                            'IFNULL(apply.apply_total, 0)' => 'apply_total',
                            'IFNULL(spare.spare_total, 0)' => 'spare_total',
                            'IFNULL(offline.admission_total, 0)' => 'offline_count',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v){
                        $degree_surplus = $v['spare_total'] - $v['offline_count'];//剩余学位数量
                        $degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;
                        $list['data'][$k]['degree_surplus'] = $degree_surplus;//剩余学位数量
                    }

                }
                $list['resources'] = $res_data;

                $res = [
                    'code' => 1,
                    'data' => $list,
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
     * 学校统计 查看
     * @return Json
     */
    public function getSchoolInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                //市级查看按学校分页展示
                if ($this->userInfo['grade_id'] >= $this->city_grade) {

                    $where = [];
                    $where[] = ['s.deleted', '=', 0];
                    $where[] = ['s.disabled', '=', 0];

                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::name("Plan")->find($plan_id);
                        if (!$plan) {
                            throw new \Exception('未找到招生计划信息');
                        }
                        $where[] = ['s.school_type', '=', $plan['school_type'] ];
                    } else {
                        throw new \Exception('招生计划ID为空');
                    }
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $region_id = $this->result['region_id'];
                        $where[] = ['s.region_id', '=', $region_id ];
                    } else {
                        throw new \Exception('区县ID为空');
                    }

                    if($region_id == 1){
                        //市直
                        $where[] = ['s.directly', '=', 1 ];

                        $school_ids = Db::name('sys_school')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['directly', '=', 1], ['onlined', '=', 0] ])
                            ->column('id');
                    }else {
                        $where[] = ['s.directly', '=', 0 ];
                        $where[] = ['s.central_id', '=', 0 ];

                        $school_ids = Db::name('sys_school')
                            ->where([['deleted', '=', 0], ['region_id', '=', $region_id], ['directly', '=', 0], ['onlined', '=', 0],
                                ['school_type', '=', $plan['school_type']], ['disabled', '=', 0], ['central_id', '=', 0] ])
                            ->column('id');
                    }
                    $where[] = ['s.id', 'in', $school_ids];

                    //批复学位
                    $querySpare = Db::name('PlanApply')
                        ->field('school_id, SUM(spare_total) AS spare_total')
                        ->group('school_id')->where('school_id', 'in', $school_ids )
                        ->where('status', 1)->where('deleted', 0)->buildSql();
                    //录取人数
                    $queryOffline = Db::name('UserApply')
                        ->field('result_school_id, COUNT(*) AS admission_total')
                        ->group('result_school_id')
                        ->where('result_school_id', 'in', $school_ids )
                        ->where('deleted', 0)->where('voided', 0)->where('prepared', 1)
                        ->where('resulted', 1)->where('offlined', 1)->buildSql();

                    $list = Db::name('SysSchool')->alias('s')
                        ->join([
                            $querySpare => 'spare'
                        ], 's.id = spare.school_id', 'LEFT')
                        ->join([
                            $queryOffline => 'offline'
                        ], 's.id = offline.result_school_id', 'LEFT')
                        ->field([
                            's.id',
                            's.school_name' => 'school_name',
                            'IFNULL(spare.spare_total, 0)' => 'spare_total',
                            'IFNULL(offline.admission_total, 0)' => 'admission_total',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v){
                        $degree_surplus = $v['spare_total'] - $v['admission_total'];//剩余学位数量
                        $degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;
                        $list['data'][$k]['degree_surplus'] = $degree_surplus;//剩余学位数量
                    }
                }else{
                    $list = [];
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
     * 线下录取 教管会统计报表 信息
     * @return \think\response\Json
     */
    public function getCentralList(): Json
    {
        if ($this->request->isPost()) {
            try {
                //市级及以上
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::name("Plan")->find($plan_id);
                        if (!$plan) {
                            throw new \Exception('未找到招生计划信息');
                        }
                    } else {
                        throw new \Exception('计划ID参数错误');
                    }

                    $where = [];
                    $where[] = ['disabled', '=', 0];
                    $where[] = ['deleted', '=', 0];
                    $where[] = ['parent_id', '>', 0];

                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $where[] = ['id', '=', $this->result['region_id']];
                    }

                    $list = Db::name('SysRegion')->where($where)->field(['id', 'region_name',])
                        ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v) {
                        $list['data'][$k]['plan_id'] = $plan_id;
                        $list['data'][$k]['region_id'] = $v['id'];
                        $list['data'][$k]['region_name'] = $v['region_name'];

                        //区教管会ID
                        $central_ids = Db::name('CentralSchool')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['region_id', '=', $v['id']] ])
                            ->column('id');

                        $total_where = [];
                        $total_where[] = ['central_id', 'in', $central_ids];
                        $total_where[] = ['plan_id', '=', $plan_id];
                        $total_where[] = ['deleted', '=', 0];
                        $total_where[] = ['status', '=', 1];
                        $spare_total = Db::name('PlanApply')->where($total_where)->sum('spare_total');

                        //教管会所属学校ID
                        $school_ids = Db::name('SysSchool')
                            ->where([['deleted', '=', 0], ['directly', '=', 0], ['school_type', '=', $plan['school_type']],
                                ['disabled', '=', 0], ['onlined', '=', 0], ['central_id', 'in', $central_ids] ])->column('id');

                        $admission_total = Db::name('UserApply')
                            ->where('result_school_id', 'in', $school_ids )
                            ->where('deleted', 0)->where('voided', 0)->where('prepared', 1)
                            ->where('resulted', 1)->where('offlined', 1)->count();

                        $degree_surplus = $spare_total - $admission_total;//剩余学位数量
                        $degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;

                        $list['data'][$k]['spare_total'] = $spare_total;
                        $list['data'][$k]['offline_count'] = $admission_total;
                        $list['data'][$k]['degree_surplus'] = $degree_surplus;//剩余学位数量
                    }
                }else{
                    $where = [];
                    $where[] = ['c.deleted', '=', 0];
                    $where[] = ['c.disabled', '=', 0];
                    //区级按教管会统计
                    if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1) {
                        $region_id = $this->userInfo['region_id'];
                        //教管会ID
                        $central_ids = Db::name('central_school')
                            ->where([['deleted', '=', 0], ['disabled', '=', 0], ['region_id', '=', $region_id] ])
                            ->column('id');
                        $where[] = ['c.region_id', '=', $region_id];
                    }else{
                        throw new \Exception('区县管理员所属区县ID设置错误');
                    }
                    $plan_id = 0;
                    $condition = [];
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::name("Plan")->find($plan_id);
                        if (!$plan) {
                            throw new \Exception('未找到招生计划信息');
                        }
                        $condition[] = ['school_type', '=', $plan['school_type'] ];
                    }
                    //申请数量
                    $queryApply = Db::name('PlanApply')
                        ->field('central_id, SUM(apply_total) AS apply_total')
                        ->group('central_id')
                        ->where('central_id', 'in', $central_ids)
                        ->where('deleted', 0)->buildSql();
                    //批复数量
                    $querySpare = Db::name('PlanApply')
                        ->field('central_id, SUM(spare_total) AS spare_total')
                        ->group('central_id')
                        ->where('central_id', 'in', $central_ids)
                        ->where('status', 1)->where('deleted', 0)->buildSql();

                    $list = Db::name('CentralSchool')->alias('c')
                        ->join([
                            $queryApply => 'apply'
                        ], 'c.id = apply.central_id', 'LEFT')
                        ->join([
                            $querySpare => 'spare'
                        ], 'c.id = spare.central_id', 'LEFT')
                        ->field([
                            'c.id',
                            'c.central_name' => 'central_name',
                            'IFNULL(apply.apply_total, 0)' => 'apply_total',
                            'IFNULL(spare.spare_total, 0)' => 'spare_total',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ((array)$list['data'] as $k => $v){
                        $list['data'][$k]['plan_id'] = $plan_id;
                        //线下录取数量
                        $school_ids = Db::name('SysSchool')->where($condition)
                            ->where([['deleted', '=', 0], ['central_id', '=', $v['id'] ],
                                ['disabled', '=', 0], ['directly', '=', 0], ['onlined', '=', 0], ])
                            ->column('id');
                        $offline_count = Db::name('UserApply')
                            ->where('result_school_id', 'in', $school_ids )
                            ->where('deleted', 0)->where('voided', 0)->where('prepared', 1)
                            ->where('resulted', 1)->where('offlined', 1)->count();
                        $list['data'][$k]['offline_count'] = $offline_count;

                        $degree_surplus = $v['spare_total'] - $offline_count;//剩余学位数量
                        $degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;
                        $list['data'][$k]['degree_surplus'] = $degree_surplus;//剩余学位数量
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
        }else{}
    }

    /**
     * 监管户统计 查看
     * @return Json
     */
    public function getCentralInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                //市级查看按教管会分页展示
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    $where = [];
                    $where[] = ['c.deleted', '=', 0];
                    $where[] = ['c.disabled', '=', 0];

                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $region_id = $this->result['region_id'];
                        $where[] = ['c.region_id', '=', $region_id];
                    } else {
                        throw new \Exception('区县ID为空');
                    }
                    //教管会ID
                    $central_ids = Db::name('central_school')
                        ->where([['deleted', '=', 0], ['disabled', '=', 0], ['region_id', '=', $region_id] ])
                        ->column('id');

                    $condition = [];
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::name("Plan")->find($plan_id);
                        if (!$plan) {
                            throw new \Exception('未找到招生计划信息');
                        }
                        $condition[] = ['school_type', '=', $plan['school_type']];
                    } else {
                        throw new \Exception('招生计划ID为空');
                    }

                    //申请数量
                    $queryApply = Db::name('PlanApply')
                        ->field('central_id, SUM(apply_total) AS apply_total')
                        ->group('central_id')
                        ->where('central_id', 'in', $central_ids)
                        ->where('deleted', 0)->buildSql();
                    //批复数量
                    $querySpare = Db::name('PlanApply')
                        ->field('central_id, SUM(spare_total) AS spare_total')
                        ->group('central_id')
                        ->where('central_id', 'in', $central_ids)
                        ->where('status', 1)->where('deleted', 0)->buildSql();

                    $list = Db::name('CentralSchool')->alias('c')
                        ->join([
                            $queryApply => 'apply'
                        ], 'c.id = apply.central_id', 'LEFT')
                        ->join([
                            $querySpare => 'spare'
                        ], 'c.id = spare.central_id', 'LEFT')
                        ->field([
                            'c.id',
                            'c.central_name' => 'central_name',
                            'IFNULL(apply.apply_total, 0)' => 'apply_total',
                            'IFNULL(spare.spare_total, 0)' => 'spare_total',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ((array)$list['data'] as $k => $v){
                        $list['data'][$k]['plan_id'] = $plan_id;
                        //线下录取数量
                        $school_ids = Db::name('sys_school')->where($condition)
                            ->where([['deleted', '=', 0], ['central_id', '=', $v['id'] ],
                                ['disabled', '=', 0], ['directly', '=', 0], ['onlined', '=', 0], ])
                            ->column('id');
                        $offline_count = Db::name('UserApply')
                            ->where('result_school_id', 'in', $school_ids )
                            ->where('deleted', 0)->where('voided', 0)->where('prepared', 1)
                            ->where('resulted', 1)->where('offlined', 1)->count();
                        $list['data'][$k]['offline_count'] = $offline_count;

                        $degree_surplus = $v['spare_total'] - $offline_count;//剩余学位数量
                        $degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;
                        $list['data'][$k]['degree_surplus'] = $degree_surplus;//剩余学位数量
                    }
                }else{
                    $where = [];
                    $where[] = ['s.deleted', '=', 0];
                    $where[] = ['s.disabled', '=', 0];
                    $where[] = ['s.directly', '=', 0];
                    $where[] = ['s.onlined', '=', 0];

                    //区级统计教管会所属教学点
                    if ($this->request->has('central_id') && $this->result['central_id'] > 0) {
                        $central_id = $this->result['central_id'];
                        $where[] = ['s.central_id', '=', $central_id];
                    } else {
                        throw new \Exception('教管会ID参数错误');
                    }
                    if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                        $plan_id = $this->result['plan_id'];
                        $plan = Db::name("Plan")->find($plan_id);
                        if (!$plan) {
                            throw new \Exception('未找到招生计划信息');
                        }
                        $where[] = ['s.school_type', '=', $plan['school_type'] ];
                    }

                    $school_ids = Db::name('SysSchool')->alias('s')->where($where)->column('id');
                    //批复学位
                    $querySpare = Db::name('PlanApply')
                        ->field('school_id, SUM(spare_total) AS spare_total')
                        ->group('school_id')->where('school_id', 'in', $school_ids )
                        ->where('status', 1)->where('deleted', 0)->buildSql();
                    //申请学位
                    $queryApply = Db::name('PlanApply')
                        ->field('school_id, SUM(apply_total) AS apply_total')
                        ->group('school_id')->where('school_id', 'in', $school_ids )
                        ->where('deleted', 0)->buildSql();
                    //录取人数
                    $queryOffline = Db::name('UserApply')
                        ->field('result_school_id, COUNT(*) AS admission_total')
                        ->group('result_school_id')
                        ->where('result_school_id', 'in', $school_ids )
                        ->where('deleted', 0)->where('voided', 0)->where('prepared', 1)
                        ->where('resulted', 1)->where('offlined', 1)->buildSql();

                    $list = Db::name('SysSchool')->alias('s')
                        ->join([
                            $querySpare => 'spare'
                        ], 's.id = spare.school_id', 'LEFT')
                        ->join([
                            $queryApply => 'apply'
                        ], 's.id = apply.school_id', 'LEFT')
                        ->join([
                            $queryOffline => 'offline'
                        ], 's.id = offline.result_school_id', 'LEFT')
                        ->field([
                            's.id',
                            's.school_name' => 'school_name',
                            'IFNULL(apply.apply_total, 0)' => 'apply_total',
                            'IFNULL(spare.spare_total, 0)' => 'spare_total',
                            'IFNULL(offline.admission_total, 0)' => 'offline_count',
                        ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                    foreach ($list['data'] as $k => $v){
                        $degree_surplus = $v['spare_total'] - $v['offline_count'];//剩余学位数量
                        $degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;
                        $list['data'][$k]['degree_surplus'] = $degree_surplus;//剩余学位数量
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
     * 下拉资源
     * @return Json
     */
    public function getSelectList(): Json
    {
        if ($this->request->isPost()) {
            $result = $this->getAllSelectByRoleList();

            $code = $result['code'];
            unset($result['police_list']);
            unset($result['central_list']);
            unset($result['school_list']);
            unset($result['code']);

            /*foreach ((array)$result['school_list'] as $k => $v){
                //过滤民办
                if($v['school_attr'] == 2){
                    unset($result['school_list'][$k]);
                }
            }*/

            $res = [
                'code' => $code,
                'data' => $result,
            ];
            return parent::ajaxReturn($res);
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

}