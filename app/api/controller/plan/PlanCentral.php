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
use think\facade\Cache;
use think\facade\Db;
use think\response\Json;
use app\common\model\PlanApply as model;
use app\common\validate\basic\PlanApply as validate;

class PlanCentral extends Education
{
    /**
     * 审批学位列表【学校】
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                $where = [];
                if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                    $where[] = ['a.plan_id', '=', $this->result['plan_id']];
                }
                if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                    $where[] = ['c.region_id', '=', $this->result['region_id']];
                }
                //学校ID
                if ($this->request->has('central_id') && $this->result['central_id'] > 0) {
                    $where[] = ['a.central_id', '=', $this->result['central_id']];
                }
                //审核状态
                if ($this->request->has('status') && $this->result['status'] !== '') {
                    $where[] = ['a.status', '=', $this->result['status']];
                }
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['c.deleted', '=', 0];
                $where[] = ['c.disabled', '=', 0];

                //市级一下权限
                if($this->userInfo['grade_id'] < $this->city_grade){
                    if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1 ) {
                        $region_id = $this->userInfo['region_id'];
                        $where[] = ['c.region_id','=', $region_id];
                    }else{
                        throw new \Exception('区局管理员所属区县ID设置错误');
                    }

                    $res_data['region_auth'] = [
                        'status_name' => '区局权限',
                        'grade_status' => 1
                    ];
                }

                $data = Db::name("plan_apply")->alias('a')
                    ->join([
                        'deg_plan' => 'p'
                    ], 'p.id = a.plan_id')
                    ->join([
                        'deg_central_school' => 'c'
                    ], 'c.id = a.central_id', 'RIGHT')
                    ->join([
                        'deg_sys_region' => 'r'
                    ], 'r.id = c.region_id')
                    ->field([
                        'a.id',
                        'p.plan_name',
                        'r.region_name',
                        'c.central_name' => 'central_name',
                        'a.apply_total',
                        'a.spare_total',
                        'a.create_time',
                        'a.remark',
                        'a.status',
                    ])
                    ->where($where)->order('a.create_time', 'DESC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                foreach ((array)$data['data'] as $k => $v) {
                    //$data['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                    $data['data'][$k]['status_name'] = $this->getStatusName($v['status']);
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
     * 审批学位审核操作
     * @return Json
     */
    public function actAudit()
    {
        if ($this->request->isPost()) {
            //开始事务
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'status',
                    'spare_total',
                    'remark',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'], $this->result['user_id']);
                if ($checkHash['code'] == 0) {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'audit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $update = $data;
                unset($update['hash']);
                $result = (new model())->editData($update);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //教管会学位统计
                if($data['status'] == 1) {
                    $central_id = Db::name('PlanApply')
                        ->where('id', $data['id'])->value('central_id');
                    $result = $this->getCentralDegreeStatistics($central_id);
                    if ($result['code'] == 0) {
                        throw new \Exception($result['msg']);
                    }
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
     * 删除
     * @return Json
     */
    public function actDelete()
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

                //教管会学位统计
                $central_id = Db::name('PlanApply')
                    ->where('id', $data['id'])->value('central_id');
                $result = $this->getCentralDegreeStatistics($central_id);
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
     * 获取下拉列表信息 招生计划、区县、学校、教管会
     * @return Json
     */
    public function getSelectList(): Json
    {
        if ($this->request->isPost()) {
            $result = $this->getAllSelectByRoleList();

            $code = $result['code'];
            unset($result['police_list']);
            unset($result['school_list']);
            unset($result['code']);

            $result['status_list'] = [ ['id' => 0, 'name' => '待审'], ['id' => 1, 'name' => '通过'], ['id' => 2, 'name' => '拒绝'], ];

            $res = [
                'code' => $code,
                'data' => $result,
            ];
            return parent::ajaxReturn($res);
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    private function getStatusName($status)
    {
        $name = "未审核";
        switch ($status){
            case 0:
                $name = "未审核";
                break;
            case 1:
                $name = "审核通过";
                break;
            case 2:
                $name = "审核不通过";
                break;
            default:
                break;
        }

        return $name;
    }

}