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
use think\facade\Db;
use think\response\Json;
use dictionary\FilterData;
use app\common\model\SysRegion as model;
use app\common\validate\system\SysRegion as validate;

class Region extends Education
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
                if($this->request->has('enabled') && $this->result['enabled'])
                {
                    $where[] = ['disabled','=', 0];
                }
                $data = (new model())->where($where)->hidden(['deleted'])->paginate($this->pageSize)->toArray();
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
     * 获取指定区域信息
     * @param id 区域ID
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
                    'region_name',
                    'region_code',
                    'simple_code',
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
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSSJQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $sj_grade = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSQYQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $qx_grade = $getData['data'];
                if($data['parent_id'] == 0){
                    $data['grade_id'] = $sj_grade;
                }else{
                    $data['grade_id'] = $qx_grade;
                }
                $res = (new model())->addData($data,1);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->where('parent_id','<>',0)->select()->toArray();
                    Cache::set('region', $dataList);
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
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'region_name',
                    'region_code',
                    'simple_code',
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
                if($data['disabled']){
                    $resData = self::actRecurse($data['id'], 0);
                    if ($resData['code'] == 0){
                        throw new \Exception('递归处理失败请重试');
                    }
                }else{
                    if (self::chkRecurse($data['id'])){
                        throw new \Exception('分类树为禁用状态！');
                    }
                }
                Db::name('SysRegion')->where('id',$data['id'])->update(['disabled' => $data['disabled'],'region_name' => $data['region_name']]);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->where('parent_id','<>',0)->select()->toArray();
                    Cache::set('region', $dataList);
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
     * @return Json
     */
    public function actDelete()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'deleted' => 1,
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'delete');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if($data['deleted']){
                    $resData = self::actRecurse($data['id'], 1);
                    if ($resData['code'] == 0){
                        throw new \Exception('递归处理失败请重试');
                    }
                }
                $code = rand(100, 999);
                Db::name('SysRegion')->where('id',$data['id'])->update(['deleted' => 1,'region_name' => time().$code]);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->where('parent_id','<>',0)->select()->toArray();
                    Cache::set('region', $dataList);
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
     * 检查递归
     * @return bool
     */
    private function chkRecurse($id)
    {
        $parent_id = (new model())->where('id', $id)->value('parent_id');
        if($parent_id){
            $disabled = (new model())->where('id', $parent_id)->value('disabled');
            if ($disabled){
                return true;
            }
            self::chkRecurse($parent_id);
        }
        return false;
    }
    /**
     * 递归处理
     */
    private function actRecurse($id, $type = 0)
    {
        Db::startTrans();
        try {
            $data = (new model())->where('parent_id', $id)->select()->toArray();
            foreach ($data as $k =>$v){
                if ($v['id']){
                    $code = rand(100, 999);
                    if ($type == 1){
                        Db::name('SysRegion')->where('id',$v['id'])->update(['deleted' => 1,'region_name' => time().$code]);
                    }else{
                        Db::name('SysRegion')->where('id',$v['id'])->update(['disabled' => 1]);
                    }
                    self::actRecurse($v['id'], $type);
                }
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
        return $res;
    }
}