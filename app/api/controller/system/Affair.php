<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/22
 * Time: 11:49
 */

namespace app\api\controller\system;

use app\common\controller\Education;
use app\common\model\SysAffair as model;
use app\common\validate\system\SysAffair as validate;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;

class Affair extends Education
{
    /**
     * 获取事务配置列表
     * @return Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['affair_controller|affair_name','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('enabled') && $this->result['enabled'])
                {
                    $where[] = ['disabled','=', 0];
                }
                $list = (new model())->with('affairNodes')->where($where)->hidden(['deleted'])->paginate($this->pageSize,false,['var_page' => 'curr']);
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
     * 获取指定事务数据
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
                $data = (new model())->with('affairNodes')->where('id',$this->result['id'])->find()->hidden(['deleted']);
                if(empty($data))
                {
                    throw new \Exception('记录不存在');
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
     * 获取节点信息
     * @return \think\response\Json
     */
    public function getNodeList()
    {
        if ($this->request->isPost()) {
            try {
                $nodes = Cache::get('nodes');
                $res = [
                    'code' => 1,
                    'data' => $nodes
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
     * @param affair_name      事务名称
     * @param affair_method     事务方法
     * @param affair_controller    事务控制器
     * @param node_id    事务节点ID
     * @param remarks    事务说明
     * @param actived    事务主动监视
     * @param hash      表单hash
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'affair_name',
                    'affair_method',
                    'affair_controller',
                    'node_id',
                    'remarks',
                    'actived',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['remarks'] = htmlspecialchars($data['remarks']);
                $res = (new model())->addData($data);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('affair', $dataList);
                }
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
     * 编辑
     * @param id        事务ID
     * @param affair_name      事务名称
     * @param affair_method     事务方法
     * @param affair_controller    事务控制器
     * @param affair_resume    事务简述
     * @param node_id    事务节点ID
     * @param remarks    事务说明
     * @param actived    事务主动监视
     * @param disabled    事务禁用状态
     * @param hash      表单hash
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'id',
                    'affair_name',
                    'affair_method',
                    'affair_controller',
                    'node_id',
                    'remarks',
                    'actived',
                    'disabled',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['remarks'] = htmlspecialchars($data['remarks']);
                $res = (new model())->editData($data);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('affair', $dataList);
                }
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
     * 删除
     * @param id    事务ID
     * @return Json
     */
    public function actDelete()
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'id',
                    'affair_name' => time(),
                    'deleted' => 1,
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'delete');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $res = (new model())->deleteData($data);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('affair', $dataList);
                }
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