<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\comprehensive;

use app\common\controller\Education;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use app\common\model\SysMessage as model;
use app\common\validate\comprehensive\SysMessage as validate;
use think\facade\Db;

class MessageSetting extends Education
{
    /**
     * 消息文案设置审批列表
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['message.deleted','=',0];

                if($this->request->has('status') && $this->result['status'] !== "")
                {
                    $where[] = ['status','=',$this->result['status']];
                }
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['message.region_id','=',$this->result['region_id']];
                }
                //市级权限以下只能看自己部门消息
                if($this->userInfo['grade_id'] < $this->city_grade){
                    if ( isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 0 ) {
                        $region_id = $this->userInfo['region_id'];
                        $where[] = ['message.region_id','=', $region_id];
                    }else{
                        throw new \Exception('管理员所属区域为空');
                    }
                }

                $data = Db::name('sys_message')->alias('message')
                    ->join([
                        'deg_sys_region' => 'region'
                    ], 'region.id = message.region_id' )
                    ->field([
                        'message.id',
                        'region.region_name',
                        'message.title',
                        'message.content',
                        'message.remark',
                        'message.create_time',
                        'message.audit_time',
                        'message.status',
                    ])
                    ->where($where)
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                foreach ((array)$data['data'] as $k => $v){
                    //$data['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                    $data['data'][$k]['audit_time'] = $v['audit_time'] ? date('Y-m-d H:i:s', $v['audit_time']) : '';
                    $data['data'][$k]['status_name'] = $this->getStatusName($v['status']);
                }

                if($this->userInfo['grade_id'] >= $this->city_grade){
                    $data['isCity'] = true;
                    $data['region_name'] = '市教育局';
                }else{
                    $data['isCity'] = false;
                    $region = Cache::get('region');
                    if($region) {
                        $region = filter_value_one($region, 'id', $this->userInfo['region_id']);
                        $data['region_name'] = $region['region_name'];
                    }else{
                        throw new \Exception('区县无缓存数据');
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
     * 获取指定六年级信息
     * @param id 六年级ID
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
                $data = (new model())->where('id', $this->result['id'])->where('deleted',0)->find();
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
                    'title',
                    'content',
                    'remark',
                    'hash'
                ]);
                //  验证表单hash
                $checkdata = $this->checkData($data,\app\common\validate\comprehensive\SysMessage::class,'add');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }
                unset($data['hash']);
                $hasTitle = Db::name('SysMessage')->where('title',$data['title'])->where('deleted', 0)
                    ->where('region_id',$this->userInfo['region_id'])->find();
                if($hasTitle){
                    throw new \Exception('消息标题不能重复！');
                }
                //市级权限
                if($this->userInfo['grade_id'] < $this->city_grade){
                    $data['region_id'] = $this->userInfo['region_id'];
                }else{
                    $data['region_id'] = 1;
                    $data['status'] = 1;
                }
                if(mb_strlen($data['content'], 'UTF-8') > 10000){
                    throw new \Exception('消息内容太长！');
                }
                if(mb_strlen($data['remark'], 'UTF-8') > 100){
                    throw new \Exception('备注太长！');
                }

                $result = (new model())->addData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success'),
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
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'title',
                    'content',
                    'remark',
                    'hash'
                ]);
                //  验证
                $checkdata = $this->checkData($data,\app\common\validate\comprehensive\SysMessage::class,'edit');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }

                $hasTitle = Db::name('SysMessage')->where('title',$data['title'])->where('deleted', 0)
                    ->where('region_id',$this->userInfo['region_id'])->where('id', '<>', $data['id'])->find();
                if($hasTitle){
                    throw new \Exception('消息标题不能重复！');
                }

                $result = (new model())->where('id',$data['id'])->where("deleted",0)->findOrEmpty();
                if(!$result){
                    throw new \Exception('数据不存在');
                }
                if($result['status'] != 1){
                    $data['status'] = 0;
                }
                if(mb_strlen($data['content'], 'UTF-8') > 10000){
                    throw new \Exception('消息内容太长！');
                }
                if(mb_strlen($data['remark'], 'UTF-8') > 100){
                    throw new \Exception('备注太长！');
                }

                unset($data['hash']);
                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success'),
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
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'delete');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success'),
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

    private function getStatusName($status)
    {
        $status_name = '';
        switch ($status){
            case 0 :
                $status_name = '待审核';
                break;
            case 1 :
                $status_name = '已通过';
                break;
            case 2 :
                $status_name = '未通过';
                break;
        }
        return $status_name;
    }

    public function getSearchData(){
        if ($this->request->isPost()) {
            try {
                $data['region'] = Cache::get('region');
                $data['status'] = [
                  0 => '待审核',
                  1 => '已通过',
                  2 => '未通过',
                ];
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
}