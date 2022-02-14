<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\system;
use app\common\controller\Education;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Cache;
use think\response\Json;
use app\common\model\SysDictionary as model;
use app\common\validate\system\SysDictionary as validate;

class Dictionary extends Education
{
    /**
     * 获取选项信息
     * @return \think\response\Json
     */
    public function getMainList()
    {
        if ($this->request->isPost()) {
            try {
                $where[] = ['parent_id','=', 0];
                $data = (new model())->where($where)->hidden(['deleted'])->select()->toArray();
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
     * 获取指定字典信息
     * @param id 字典ID
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
                $where[] = ['parent_id','=', $this->result['id']];
                $data = (new model())->where($where)->hidden(['deleted'])->select()->toArray();
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
                    'field_name',
                    'field_value',
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
                    'parent_id',
                    'field_name',
                    'field_value',
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
                $where[] = ['parent_id','=', $data['parent_id']];
                $where[] = ['field_name','=', $data['field_name']];
                $where[] = ['id','<>', $data['id']];
                $chkdata = (new model())->where($where)->find();
                if(!empty($chkdata))
                {
                    throw new \Exception('名称重复');
                }
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
                Db::name('SysDictionary')->where('id',$data['id'])->update(['deleted' => 1]);
                Db::name('SysDictionary')->where('parent_id',$data['id'])->update(['deleted' => 1]);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->select()->toArray();
                    Cache::set('dictionary', $dataList);
                }
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
            }
            return parent::ajaxReturn($res);
        }
    }

}