<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:37
 */
namespace app\api\controller\system;

use app\common\controller\Education;
use app\common\model\SysRoles as model;
use app\common\model\Manage;
use app\common\model\ManageNodes;
use app\common\model\SysNodes;
use app\common\model\SysRoleNodes;
use app\common\validate\system\SysRoles as validate;
use think\facade\Lang;
use think\facade\Cache;
use think\response\Json;
use dictionary\FilterData;
use think\facade\Db;

class Roles extends Education
{
    /**
     * 获取角色列表
     * @return Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if($this->request->has('enabled') && $this->result['enabled'])
                {
                    $where[] = ['disabled','=', 0];
                }
                $list = (new model())->where($where)->where('role_grade', '<=',$this->userInfo['grade_id'])->hidden(['deleted'])->select()->toArray();
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSXTQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $data['data'] = $list;
                $data['count'] = count($list);
                $data['defaulted'] = $this->userInfo['defaulted'];
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
     * 获取单一角色信息
     * @param id 角色表ID
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
                $data = (new model())->where('id', $this->result['id'])->where('role_grade', '<=',$this->userInfo['grade_id'])->hidden(['deleted'])->find();
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
     * 获取角色职能
     * @return Json
     */
    public function getGradeList()
    {
        if ($this->request->isPost()) {
            try {
                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary','SYSJSQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $grade =[];
                foreach ($getData['data'] as $item){
                    if($item['dictionary_value'] <= $this->userInfo['grade_id']){
                        array_push($grade,$item);
                    }
                }
                $grade = array_values($grade);
                $res = [
                    'code' => 1,
                    'data' => $grade
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
                    'role_name',
                    'role_grade',
                    'role_type',
                    'node_ids',
                    'remarks',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                if ($data['role_grade'] > $this->userInfo['grade_id']){
                    throw new \Exception('权限不足以进行此操作');
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['remarks'] = htmlspecialchars($data['remarks']);
                $node_ids = explode(',', $data['node_ids']);
                unset($data['node_ids']);
                unset($data['hash']);
                $role_id = Db::name('SysRoles')->insertGetId($data);
                $node_ids = array_unique($node_ids);
                $saveData = [];
                $nodeData = Db::name('SysNodes')
                    ->field([
                        'id',
                        'node_type'
                    ])
                    ->whereIn('id', $node_ids)
                    ->where('deleted', 0)
                    ->select()->toArray();
                foreach($nodeData as $k => $v)
                {
                    if($v['id']){
                        $saveData[] = [
                            'node_id' => $v['id'],
                            'role_id' => $role_id,
                            'node_type' => $v['node_type']
                        ];
                    }
                }
                //  保存新增的节点信息
                Db::name('SysRoleNodes')->insertAll($saveData);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('roles', $dataList);
                }
                //  操作成功
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('res_success')
                ];
                //  提交事务
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
     * @param id        角色ID
     * @param role_type     角色类型
     * @param remarks    角色说明
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
                    'role_name',
                    'role_grade',
                    'role_type',
                    'node_ids',
                    'remarks',
                    'disabled',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                if ($data['role_grade'] > $this->userInfo['grade_id']){
                    throw new \Exception('权限不足以进行此操作');
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['remarks'] = htmlspecialchars($data['remarks']);
                (new model())->editData($data);
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSXTQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                if ($data['role_grade'] >= $this->userInfo['grade_id'] && $this->userInfo['grade_id'] < $getData['data']){
                    throw new \Exception('只能调整下一层级的角色权限');
                }
                $node_ids = explode(',', $data['node_ids']);
                $node_ids = array_unique($node_ids);
                $nodeData = Db::name('SysNodes')
                    ->field([
                        'id',
                        'node_type'
                    ])
                    ->whereIn('id', $node_ids)
                    ->where('deleted', 0)
                    ->select()->toArray();
                //  查询所有目前应存在的节点信息
                $hasData = Db::name('SysRoleNodes')->where([
                    'role_id' => $data['id'],
                ])->column('node_id,id,deleted','node_id');
                $mapData = array_column($hasData,'node_id');
                $saveData = [];
                foreach($nodeData as $k => $v)
                {
                    if(in_array($v['id'],$mapData))
                    {
                        Db::name('SysRoleNodes')->where('id',$hasData[$v['id']]['id'])->update(['deleted' => 0]);
                        unset($hasData[$v['id']]);
                    }else{
                        if($v['id']){
                            $saveData[] = [
                                'node_id' => $v['id'],
                                'role_id' => $data['id'],
                                'node_type' => $v['node_type']
                            ];
                        }
                    }
                }
                //  保存新增的节点信息
                Db::name('SysRoleNodes')->insertAll($saveData);
                //  删除多余的节点信息
                Db::name('SysRoleNodes')->whereIn('id', array_column($hasData,'id'))->update([
                    'deleted' => 1
                ]);
                $manageIds = Db::name('manage')->where('role_id',$data['id'])->where('deleted',0)->column('id');
                Db::name('ManageNodes')->whereIn('manage_id', $manageIds)->whereNotIn('node_id',$node_ids)->update(['deleted' => 1]);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('roles', $dataList);
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
     * @param id    角色ID
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
                if (!$this->userInfo['defaulted']){
                    throw new \Exception('角色删除需初始化账户操作');
                }
//                $roleData = (new model())->where('id', $this->result['id'])->find();
//                if ($roleData['role_grade'] > $role_grade){
//                    throw new \Exception('权限不足以进行此操作');
//                }
                Db::name('SysRoles')->where('id',$data['id'])->update(['deleted' => 1]);
                Db::name('SysRoleNodes')->where('role_id',$data['id'])->update(['deleted' => 1]);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('roles', $dataList);
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

    /**
     * 获取角色资源列表
     * @return \think\response\Json
     */
    public function getTypeList()
    {
        if ($this->request->isPost()) {
            try {
                $node_ids = (new SysRoleNodes())->where('role_id', $this->userInfo['role_id'])->column('node_id');
                if (!$this->userInfo['defaulted']){
                    $data = (new SysNodes())
                        ->whereIn('id',$node_ids)
                        ->hidden(['deleted'])
                        ->order('order_num')
                        ->select()->toArray();
                }else{
                    $data = (new SysNodes())
                        ->hidden(['deleted'])
                        ->order('order_num')
                        ->select()->toArray();
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
     * 获取指定角色的的权限节点
     * @param role_id   角色ID
     * @return \think\response\Json
     */
    public function getRoleList()
    {
        if ($this->request->isPost()) {
            try {
                $role = (new model())->field(['id' => 'role_id','role_name','role_grade','role_type','remarks'])->where('id', $this->result['role_id'])->find();
                $node_ids = (new SysRoleNodes())->where([
                    'role_id' => $this->result['role_id'],
                ])->column('node_id');
                $list['role'] = $role;
                $list['nodes'] = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->order('order_num')->select()->toArray();
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
     * 获取角色资源列表
     * @return \think\response\Json
     */
    public function getTypeList1()
    {
        if ($this->request->isPost()) {
            try {
                $node_ids = (new SysRoleNodes())->where('role_id', $this->userInfo['role_id'])->column('node_id');
                if (!$this->userInfo['defaulted']){
                    $data = (new SysNodes())
                        ->whereIn('id',$node_ids)
                        ->hidden(['deleted'])
                        ->order('order_num')
                        ->select()->toArray();
                }else{
                    $data = (new SysNodes())
                        ->hidden(['deleted'])
                        ->order('order_num')
                        ->select()->toArray();
                }
                $data = $this->list_to_tree($data);
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
     * 获取指定角色的的权限节点
     * @param role_id   角色ID
     * @return \think\response\Json
     */
    public function getRoleList1()
    {
        if ($this->request->isPost()) {
            try {
                $role = (new model())->field(['id' => 'role_id','role_name','role_grade','role_type','remarks'])->where('id', $this->result['role_id'])->find();
                $node_ids = (new SysRoleNodes())->where([
                    'role_id' => $this->result['role_id'],
                ])->column('node_id');
                $list['role'] = $role;
                $list['nodes'] = (new SysNodes())->whereIn('id',$node_ids)->field(['id'])->hidden(['deleted'])->order('order_num')->select()->toArray();
                $ids = [];
                foreach($list['nodes'] as $key => $value){
                    $ids[] = $value['id'];
                }
                $ids = implode(',',$ids);
                $res = [
                    'code' => 1,
                    'data' => $ids
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
     * 保存角色的节点信息
     * @param role_id       角色ID
     * @param node_type     权限节点类型
     * @nodes               权限节点集合 以,隔开
     * @return \think\response\Json
     */
    public function actSave()
    {
        if ($this->request->isPost()) {
            //  开启事务
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'role_id',
                    'node_ids',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  如果role_id数据不合法，则返回
                $preg = '/^\d+$/u';
                if (preg_match($preg,$postData['role_id']) == 0){
                    throw new \Exception('角色ID应该为数字');
                }
                if (!empty($postData['node_ids'])){
                    $preg = '/^\d+(,\d+)*$/u';
                    if (preg_match($preg,$postData['node_ids']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                }
                $post_grade = (new model())->where('id', $postData['role_id'])->value('role_grade');
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSXTQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                if ($post_grade >= $this->userInfo['grade_id'] && $this->userInfo['grade_id'] < $getData['data']){
                    throw new \Exception('只能调整下一层级的角色权限');
                }
                $node_ids = explode(',', $this->result['node_ids']);
                $node_ids = array_unique($node_ids);
                //  查询所有目前应存在的节点信息
                $hasData = Db::name('SysRoleNodes')->where([
                    'role_id' => $postData['role_id'],
                ])->column('node_id,id,deleted');
                $mapData = array_column($hasData,'node_id');
                $saveData = [];
                foreach($node_ids as $node_id)
                {
                    if(in_array($node_id,$mapData))
                    {
                        Db::name('SysRoleNodes')->where('id',$hasData[$node_id]['id'])->update(['deleted' => 0]);
                        unset($hasData[$node_id]);
                    }else{
                        if($node_id){
                            $saveData[] = [
                                'node_id' => $node_id,
                                'role_id' => $postData['role_id'],
                            ];
                        }
                    }
                }
                //  保存新增的节点信息
                Db::name('SysRoleNodes')->insertAll($saveData);
                //  删除多余的节点信息
                Db::name('SysRoleNodes')->whereIn('id', array_column($hasData,'id'))->update([
                    'deleted' => 1
                ]);
                $manageIds = Db::name('manage')->where('role_id',$postData['role_id'])->where('deleted',0)->column('id');
                Db::name('ManageNodes')->whereIn('manage_id', $manageIds)->whereNotIn('node_id',$node_ids)->update(['deleted' => 1]);
                //  操作成功
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('res_success')
                ];
                //  提交事务
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('res_fail')
                ];
                //  事务回滚
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }


}