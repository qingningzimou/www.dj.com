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
use app\common\model\SysDictionary as model;
use app\common\validate\system\SysDictionary as validate;
use think\facade\Db;

class Dictionary extends Education
{
    protected $treedata = [];
    /**
     * 获取选项信息
     * @return \think\response\Json
     */
    public function getMainList()
    {
        if ($this->request->isPost()) {
            try {
               $where[] = ['parent_id','=', 0];
                $data = (new model())->where($where)->hidden(['deleted'])
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();
                foreach($data['data'] as $key=>$value){
                   $child = (new model())->where('parent_id',$value['id'])->where('deleted',0)->find();
                   if($child){
                       $data['data'][$key]['hasChildren'] = true;
                   }
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
     * 获取树结构信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $preg= '/^\d+$/u';
                if (preg_match($preg,$this->result['id']) == 0){
                    throw new \Exception('id应该为数字');
                }
                $main = (new model())->where('id',$this->result['id'])->find()->toArray();
                array_push($this->treedata,$main);
                $this->getRecurse($this->result['id']);
                $data['data'] = $this->treedata;
                $res = [
                    'code' => 1,
                    'data' => [
                        'data' => $this->treedata,
                        'main' => $main
                    ]
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
     * 递归处理
     * @return bool
     */
    private function getRecurse($id)
    {
        $data = (new model())->where('parent_id', $id)->select()->toArray();
        foreach ($data as $k =>$v){
            if ($v['id']){
                $tmp = (new model())->where('id', $v['id'])->find()->toArray();
                array_push($this->treedata,$tmp);
                $this->getRecurse($v['id']);
            }
        }
    }
    /**
     * 按分页获取信息
     * @return \think\response\Json
     */
    public function getLists()
    {
        if ($this->request->isPost()) {
            try {
                $main = [];
                $where = [];
                if($this->request->has('id') && $this->result['id'])
                {
                    $preg= '/^\d+$/u';
                    if (preg_match($preg,$this->result['id']) == 0){
                        throw new \Exception('id应该为数字');
                    }
                    $where[] = ['parent_id','=',$this->result['id']];
                }
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['dictionary_name|dictionary_code','=',$this->result['keyword']];
                    $main = (new model())->where($where)->find();
                }
                $data = (new model())
                    ->where($where)
                    ->hidden(['deleted'])
                    ->order('order_num')
                    ->paginate($this->pageSize,false,['var_page' => 'curr'])->toArray();
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
                    'parent_id',
                    'dictionary_name',
                    'dictionary_code',
                    'dictionary_value',
                    'dictionary_type',
                    'order_num',
                    'remarks',
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
                                throw new \Exception('字典层级超出限制');
                            }
                        }
                    }
                }
                $data['dictionary_code'] = strtoupper($data['dictionary_code']);
                $res = (new model())->addData($data,1);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->select()->toArray();
                    Cache::set('dictionary', $dataList);
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
                    'dictionary_name',
                    'dictionary_code',
                    'dictionary_value',
                    'dictionary_type',
                    'order_num',
                    'remarks',
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
                $data['dictionary_code'] = strtoupper($data['dictionary_code']);
                unset($data['hash']);
                $res = (new model())->editData($data);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->select()->toArray();
                    Cache::set('dictionary', $dataList);
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
                $code = rand(100, 999);
                Db::name('SysDictionary')->where('id',$data['id'])->update(['deleted' => 1,'dictionary_code' => time().$code]);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->select()->toArray();
                    Cache::set('dictionary', $dataList);
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
                Db::name('SysDictionary')->where('id', $v['id'])->update(['deleted' => 1,'dictionary_code' => time().$code]);
                self::actRecurse($v['id']);
            }
        }
    }
}