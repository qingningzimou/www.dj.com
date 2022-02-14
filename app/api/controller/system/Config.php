<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/22
 * Time: 11:49
 */

namespace app\api\controller\system;

use app\common\controller\Education;
use app\common\model\SysConfig;
use app\common\validate\system\SysConfig as validate;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;

class Config extends Education
{
    /**
     * 获取配置列表
     * @return Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $list = (new SysConfig())->hidden(['deleted'])->paginate(['list_rows'=> $this->pageSize,'var_page'=>'curr'])->toArray();
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
     * 获取配置项数据
     * @param keyword    关键词
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
                $data = (new SysConfig())->where('id',$this->result['id'])->find()->hidden(['deleted']);
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
     * @param item_name 项目名称
     * @param item_key 映射值
     * @param item_value 参数值
     * @param remarks    项目说明
     * @param hash      表单hash
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'item_name',
                    'item_key',
                    'item_value',
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
                if ($data['remarks']) {
                    $data['remarks'] = htmlspecialchars($data['remarks']);
                }
                Cache::set($data['item_key'], $data['item_value']);
                $res = (new SysConfig())->addData($data);
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
     * @param id        ID
     * @param item_name 项目名称
     * @param item_key 映射值
     * @param item_value 参数值
     * @param remarks    项目说明
     * @param hash      表单hash
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->only([
                    'id',
                    'item_name',
                    'item_key',
                    'item_value',
                    'remarks',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['id'] = $postData['id'];
                $data['item_name'] = $postData['item_name'];
                $data['item_value'] = $postData['item_value'];
                if ($postData['remarks']) {
                    $data['remarks'] = htmlspecialchars($postData['remarks']);
                }
                Cache::set($postData['item_key'], $data['item_value']);
                $res = (new SysConfig())->editData($data);
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
                $res = (new SysConfig())->deleteData($data);
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