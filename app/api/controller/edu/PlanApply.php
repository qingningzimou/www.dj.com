<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\PlanApply as model;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;
use think\facade\Cache;

class PlanApply extends Education
{

    /**
     * 申请计划列表
     * @return \think\response\Json
     */
    public function getList()
    {
        try {

            $school_id = $this->userInfo['school_id'];
            if(!$school_id){
                throw new \Exception('学校管理员学校ID设置错误');
            }
            $school = Db::name("sys_school")->where('id', $school_id)->find();

            $field = ['*'];
            if($school['school_attr'] == 1){
                //公办学校
                $field = [
                    'p.plan_name' => 'plan_name',
                    'a.apply_total' => 'apply_total',
                    'a.spare_total' => 'spare_total',
                    'a.create_time' => 'create_time',
                    'a.status' => 'status',
                    'a.remark' => 'remark',
                    'p.public_start_time' => 'start_time',
                    'p.public_end_time' => 'end_time',
                ];
            }elseif ($school['school_attr'] == 2){
                //民办学校
                $field = [
                    'p.plan_name' => 'plan_name',
                    'a.apply_total' => 'apply_total',
                    'a.spare_total' => 'spare_total',
                    'a.create_time' => 'create_time',
                    'a.status' => 'status',
                    'a.remark' => 'remark',
                    'p.private_start_time' => 'start_time',
                    'p.private_end_time' => 'end_time',
                ];
            }

            $where = [];
            $where[] = ['a.deleted', '=', 0];
            $where[] = ['a.school_id', '=', $school_id];
            if ($this->request->has('search') && $this->result['search'] != '') {
                $where[] = ['a.remark|p.plan_name', 'like', '%' . $this->request->param('search') . '%'];
            }
            if ($this->request->has('plan_id') && $this->result['plan_id'] > 0 ) {
                $where[] = ['a.plan_id', '=', $this->request->param('plan_id')];
            }

            $list = Db::table("deg_plan_apply")
                ->alias('a')
                ->join([
                    'deg_plan' => 'p'
                ], 'p.id = a.plan_id' )
                ->field($field)->where($where)
                ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            foreach ($list['data'] as $k => $v){
                //$list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $list['data'][$k]['start_time'] = date('Y-m-d H:i:s', $v['start_time']);
                $list['data'][$k]['end_time'] = date('Y-m-d H:i:s', $v['end_time']);
                $list['data'][$k]['status_name'] = $this->getStatusName($v['status']);
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
     * 新增计划
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $school_id = $this->userInfo['school_id'];
                if(!$school_id){
                    throw new \Exception('管理员没有关联学校ID');
                }

                $data = $this->request->only([
                    'plan_id',
                    'apply_total',
                    'hash'
                ]);
                $data['school_id'] = $school_id;
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                if($data['plan_id'] <= 0){
                    throw new \Exception('请选择招生计划');
                }
                if(!preg_match("/^[1-9][0-9]*$/" , $data['apply_total'])){
                    throw new \Exception('申请学位数量请填写正整数');
                }
                if($data['apply_total'] > 999999){
                    throw new \Exception('申请学位数量太大');
                }

                unset($data['hash']);
                //$data['create_time'] = time();
                $result = (new model())->addData($data);
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

    public function getPlanList()
    {
        if ($this->request->isPost()) {
            try {
                $school_id = $this->userInfo['school_id'];
                if (!$school_id) {
                    throw new \Exception('管理员没有关联学校ID');
                }
                $school = Db::name("sys_school")->where('id', $school_id)->find();

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['school_type', '=', $school['school_type']];
                $where[] = ['plan_time', '=', date('Y')];

                $list = Db::table("deg_plan")
                    ->alias('p')
                    ->field(['id', 'plan_name'])->where($where)->select();

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

    private function getStatusName($status)
    {
        $status_name = '';
        switch ($status){
            case 0:
                $status_name = '待审';
                break;
            case 1:
                $status_name = '通过';
                break;
            case 2:
                $status_name = '拒绝';
                break;
        }
        return $status_name;
    }

}