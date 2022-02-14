<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */

namespace app\api\controller\bureau;

use app\common\controller\Education;
use think\facade\Lang;
use think\facade\Db;
use think\response\Json;
use app\common\validate\basic\PlanApply as validate;

class Statistics extends Education
{
    /**
     * 学位统计列表【区级及教管会】
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['a.deleted', '=', 0];
                if($this->request->has('plan_id') && $this->result['plan_id'] > 0 )
                {
                    $plan_id = $this->result['plan_id'];
                    $where[] = ['plan_id', '=', $plan_id];
                }
                if($this->userInfo['department_id'] > 0 )
                {
                    $department_id = $this->userInfo['department_id'];
                    //公办、民办、小学、初中权限
                    $school_where = $this->getSchoolWhere();
                    $_where[] = $school_where['school_attr'];
                    $_where[] = $school_where['school_type'];

                    $school_ids = Db::name('sys_school')
                        ->where([['deleted', '=', 0], ['department_id', '=', $department_id],
                            ['directly', '=', 0 ]])->where($_where)
                        ->column('id');
                    $where[] = ['school_id', 'in', $school_ids];
                }else{
                    throw new \Exception('管理员部门机构ID错误');
                }

                $list = Db::name('plan_apply')->alias('a')
                    ->join([
                        'deg_plan' => 'p'
                    ], 'p.id = a.plan_id' )
                    ->join([
                        'deg_sys_school' => 's'
                    ], 's.id = a.school_id' )
                    ->field([
                        'a.id',
                        'p.plan_name',
                        's.school_name' => 'school_name',
                        'a.apply_total' => 'apply_total',
                        'a.spare_total' => 'spare_total',
                    ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                foreach ((array)$list['data'] as $k => $v){
                    $list['data'][$k]['used_total'] = 0;
                }

                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
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
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = Db::name('plan_apply')->where('id', $this->result['id'])->find();

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