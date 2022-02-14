<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/22
 * Time: 11:49
 */

namespace app\api\controller\parent;

use app\common\controller\Education;
use app\common\model\Course as modle;
use app\common\validate\parent\Course as validate;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;

class Course extends Education
{
    /**
     * 获取教程列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $list = (new modle())->where('deleted',0)
                    ->paginate(['list_rows'=> $this->pageSize,'var_page'=>'curr'])->toArray();
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
                $list['resources'] = $res_data;
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
     * 获取教程详情
     * @param int id
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = (new modle())->where('id',$this->result['id'])->where('deleted',0)->find();
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
     * @param string name 标题
     * @param string content 内容
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'name',
                    'content',
                ]);
                $data['create_time'] = date('Y-m-d H:i:s', time());

                $checkdata = $this->checkData($data, validate::class,'add');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }
                $data['content'] = $this->clearHtml($data['content']);
                $res = (new modle())->addData($data);
                if($res['code'] == 0){
                    throw new \Exception($res['msg']);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success')
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
     * 新增
     * @param int id id
     * @param string name 标题
     * @param string content 内容
     * @return Json
     */
    public function actEdit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'name',
                    'content',
                ]);
                $checkdata = $this->checkData($data, validate::class,'Edit');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }

                $res = (new modle())->editData($data);
                if($res['code'] == 0){
                    throw new \Exception($res['msg']);
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
     * @param int id
     * @return Json
     */
    public function actDelete(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                ]);
                $data['deleted'] = 1;
                $checkdata = $this->checkData($data, validate::class,'delete');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }
                $res = (new modle())->deleteData($data);
                if($res['code'] == 0){
                    throw new \Exception($res['msg']);
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
}