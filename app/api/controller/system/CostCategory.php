<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:37
 */
namespace app\api\controller\system;

use app\common\controller\Education;
use app\common\model\Schools;
use app\common\model\SysCostCategory as model;
use app\common\model\SysSchoolCost;
use think\facade\Lang;
use think\response\Json;
use think\facade\Cache;
use think\facade\Db;

class CostCategory extends Education
{
    /**
     * 获取费用类目列表
     * @return Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted','=', 0];

                $list = (new model())->where($where)->hidden(['deleted'])->select()->toArray();
                $data['data'] = $list;
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
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
        }
    }
    /**
     * 获取费用类目详细信息
     * @param id 费用类目ID
     * @return Json
     */
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                //  如果数据不合法，则返回
                if (!$this->result['id']) {
                    throw new \Exception('费用类目ID错误');
                }
                $data = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();
                if (empty($data)) {
                    throw new \Exception(Lang::get('info_no_found'));
                }
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
     * 新增
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'cost_name',
                    'remark',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  如果数据不合法，则返回
                if ($data['cost_name'] == '') {
                    throw new \Exception('请填写费用类目名称');
                }
                $data['remark'] = htmlspecialchars($data['remark']);
                if(mb_strlen($data['cost_name'], 'UTF-8') > 60){
                    throw new \Exception('费用类目名称太长！');
                }
                if(mb_strlen($data['remark'], 'UTF-8') > 100){
                    throw new \Exception('备注太长！');
                }
                unset($data['hash']);

                $cost_id = (new model())->insertGetId($data);
                if(!$cost_id) {
                    throw new \Exception('新增费用类目失败');
                }
                if (Cache::get('update_cache')) {
                    $costList = Db::name('SysCostCategory')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('cost', $costList);
                }
                //  操作成功
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('res_success')
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
     * 编辑
     * @param id        费用类目ID
     * @param cost_name  费用名称
     * @param remark    备注说明
     * @param hash      表单hash
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'cost_name',
                    'remark',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  如果数据不合法，则返回
                if ($data['id'] <= 0) {
                    throw new \Exception('费用ID错误');
                }
                if ($data['cost_name'] == '') {
                    throw new \Exception('请填写费用类目名称');
                }
                $data['remark'] = htmlspecialchars($data['remark']);
                unset($data['hash']);

                (new model())->editData($data);

                if (Cache::get('update_cache')) {
                    $costList = Db::name('SysCostCategory')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('cost', $costList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
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
     * @param id    费用类目ID
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

                //  如果数据不合法，则返回
                if ($data['id'] <= 0) {
                    throw new \Exception('费用ID错误');
                }

                $result = (new model())->deleteData($data);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                //删除学校费用类目
                $result = (new SysSchoolCost())->editData(['deleted' => 1], ['cost_id' => $data['id'] ]);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }
                //重新计算民办学校费用
                $cost_list = Db::name('SysSchoolCost')->where('deleted', 0)
                    ->group('school_id')->column('SUM(cost_total) AS fee', 'school_id');
                foreach ((array)$cost_list as $k => $v){
                    $id = $k;
                    $fee = $v;
                    $result = (new Schools())->editData(['id' => $id, 'fee' => $fee]);
                    if ($result['code'] == 0) {
                        throw new \Exception($result['msg']);
                    }
                }

                if (Cache::get('update_cache')) {
                    $costList = Db::name('SysCostCategory')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('cost', $costList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('delete_success')
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


}