<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/22
 * Time: 11:49
 */

namespace app\api\controller\system;

use app\common\controller\Education;
use app\common\model\Schools as model;
use app\common\validate\system\SysMerchant as validate;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;

class Merchant extends Education
{
    /**
     * 获取缴费项列表
     * @return Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where[] = ['disabled','=',0];
                $where[] = ['merchant_num','>',0];
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['school_name|simple_code|fee_code','like', '%' . $this->result['keyword'] . '%'];
                }
                $data['data'] = (new model())
                    ->where($where)
                    ->field([
                        'id',
                        'school_name',
                        'fee',
                        'fee_unit',
                        'fee_code',
                        'merchant_num',
                        'onlinepay',
                    ])
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr']);
                $res_data = parent::getResources($this->userInfo,$this->request->controller());
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
     * 获取指定缴费项数据
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
                $data = (new model())
                    ->where('id',$this->result['id'])
                    ->field([
                        'school_name',
                        'fee',
                        'fee_unit',
                        'fee_code',
                        'merchant_num',
                        'onlinepay',
                    ])
                    ->find();
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

    //查找学校信息
    public function getSchool()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $limit = 8;
                if($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['school_name|simple_code', 'like', '%' . $this->result['keyword'] . '%'];
                }
                $data = (new model())
                    ->where($where)
                    ->field([
                        'id',
                        'school_name'
                    ])
                    ->limit($limit)
                    ->select()->toArray();
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
     * 保存缴费项
     * @param id      学校ID
     * @param fee_code     缴费项编码
     * @param merchant_num    配置编号
     * @param onlinepay    线上缴费状态
     * @param hash      表单hash
     * @return Json
     */
    public function actSave()
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'id',
                    'fee_code',
                    'merchant_num',
                    'onlinepay',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'save');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $res = (new model())->editData($data);
                if (Cache::get('update_cache')) {
                    $schoolList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('school', $schoolList);
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
     * @param id
     * @return Json
     */
    public function actDelete()
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'id',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['fee_code'] = '';
                $data['merchant_num'] = 0;
                $data['onlinepay'] = 0;
                $res = (new model())->editData($data);
                if (Cache::get('update_cache')) {
                    $schoolList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('school', $schoolList);
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