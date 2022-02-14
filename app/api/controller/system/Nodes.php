<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\system;
use app\common\controller\Education;
use think\facade\Lang;
use think\facade\Cache;
use think\response\Json;
use app\common\model\SysNodes as model;
use app\common\validate\system\SysNodes as validate;
use think\facade\Db;

class Nodes extends Education
{
    /**
     * 按分页获取信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $main = [];
                if($this->request->has('id') && $this->result['id'])
                {
                    $preg= '/^\d+$/u';
                    if (preg_match($preg,$this->result['id']) == 0){
                        throw new \Exception('id应该为数字');
                    }
                    $where[] = ['parent_id','=',$this->result['id']];
                    $main = (new model())->where('id',$this->result['id'])->find();
                }
                $data = (new model())
                    ->where($where)
                    ->hidden(['deleted'])
                    ->order('order_num')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $data['main'] = $main;
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
     * 获取指定节点信息
     * @param id ID
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
                $data = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();
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
     * 新增
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'node_name',
                    'parent_id',
                    'icon',
                    'color_set',
                    'router_path',
                    'router_name',
                    'file_path',
                    'order_num',
                    'module_name',
                    'controller_name',
                    'method_name',
                    'remarks',
                    'node_type',
                    'authority',
                    'signin',
                    'defaulted',
                    'abreast',
                    'displayed',
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
                if($data['parent_id'] > 0){
                    $parent_id = (new model())->where('id',$data['parent_id'])->value('parent_id');
                    if ($parent_id > 0){
                        $parent_id = (new model())->where('id',$parent_id)->value('parent_id');
                        if ($parent_id > 0) {
                            $parent_id = (new model())->where('id',$parent_id)->value('parent_id');
                            if ($parent_id != 0) {
                                throw new \Exception('资源层级超出限制');
                            }
                        }
                    }
                    (new model())->where('id',$data['parent_id'])->update(['mainsort' => 1]);
                }
                $res = (new model())->addData($data,1);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->select()->toArray();
                    Cache::set('nodes', $dataList);
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
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'id',
                    'node_name',
                    'icon',
                    'color_set',
                    'router_path',
                    'router_name',
                    'file_path',
                    'order_num',
                    'module_name',
                    'controller_name',
                    'method_name',
                    'remarks',
                    'node_type',
                    'authority',
                    'signin',
                    'defaulted',
                    'abreast',
                    'displayed',
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
                unset($data['hash']);
                $res = (new model())->editData($data);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->select()->toArray();
                    Cache::set('nodes', $dataList);
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
                if($data['deleted']){
                    self::actRecurse($data['id']);
                }
                $parent_id = Db::name('SysNodes')
                    ->where('id',$data['id'])
                    ->where('deleted',0)
                    ->value('parent_id');
                Db::name('SysNodes')->where('id',$data['id'])->update(['deleted' => 1]);
                Db::name('SysRoleNodes')->where('node_id',$data['id'])->update(['deleted' => 1]);
                Db::name('ManageNodes')->where('node_id',$data['id'])->update(['deleted' => 1]);
                Db::name('SysSchoolNodes')->where('node_id',$data['id'])->update(['deleted' => 1]);
                if($parent_id){
                    $childrenNum = Db::name('SysNodes')
                        ->where('parent_id',$parent_id)
                        ->where('deleted',0)
                        ->count();
                    if(!$childrenNum){
                        Db::name('SysNodes')->where('id',$parent_id)->update(['mainsort' => 0]);
                    }
                }
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->select()->toArray();
                    Cache::set('nodes', $dataList);
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
     * 递归处理
     * @return Json
     */
    private function actRecurse($id)
    {
        $data = (new model())->where('parent_id', $id)->select()->toArray();
        foreach ($data as $k =>$v){
            if ($v['id']){
                $code = rand(100, 999);
                Db::name('SysNodes')->where('id', $v['id'])->update(['deleted' => 1]);
                Db::name('SysRoleNodes')->where('node_id',$v['id'])->update(['deleted' => 1]);
                Db::name('ManageNodes')->where('node_id',$v['id'])->update(['deleted' => 1]);
                Db::name('SysSchoolNodes')->where('node_id',$v['id'])->update(['deleted' => 1]);
                self::actRecurse($v['id']);
            }
        }
    }
}