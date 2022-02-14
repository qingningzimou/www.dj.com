<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\basic;

use app\common\controller\Education;
use think\App;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Cache;
use think\response\Json;
use dictionary\FilterData;
use Overtrue\Pinyin\Pinyin;
use app\common\model\SysNodes;
use app\common\model\SysRegion;
use app\common\model\Department as model;
use app\common\validate\basic\Department as validate;

class Department extends Education
{
    protected $graded;
    /**
     * 获取部门列表
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['grade_id','<=', $this->userInfo['grade_id']];
                $list = (new model())->where($where)->select()->toArray();
                $data['data'] = $list;
                $data['grade_id'] = $this->userInfo['grade_id'];
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
     * 获取部门资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            try {
                $defaulted = $this->userInfo['defaulted'];
                if($defaulted){
                    $ids = (new SysRegion())->where('disabled',0)->column('id');
                }else{
                    $ids = array_column($this->userInfo['relation_region'],'region_id');
                }
                if($this->request->has('region_id') && $this->result['region_id']){
                    $grade_id = (new SysRegion())->where('disabled',0)->where('id',$this->result['region_id'])->value('grade_id');
                    if(empty($grade_id)){
                        throw new \Exception('记录不存在');
                    }
                    $list = (new SysRegion())->whereIn('id', $ids)
                        ->where('disabled',0)
                        ->where('grade_id','=',$grade_id)
                        ->field([
                            'id',
                            'region_name'
                        ])
                        ->hidden(['deleted','disabled'])->select()->toArray();
                }else{
                    $list = (new SysRegion())->whereIn('id', $ids)
                        ->where('disabled',0)
                        ->field([
                            'id',
                            'region_name'
                        ])
                        ->hidden(['deleted','disabled'])->select()->toArray();
                }
                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary','SYSJSQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $gradeData = [];
                foreach ($getData['data'] as $value){
                    if($value['dictionary_value'] <= $this->userInfo['grade_id']){
                        $gradeData[] = $value;
                    }
                }
                $res_data = parent::getResources($this->userInfo,$this->request->controller());
                $data['region'] = $list;
                $data['grade'] = $gradeData;
                $data['grade_id'] = $this->userInfo['grade_id'];
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
     * 获取可配置的区域信息
     * @return \think\response\Json
     */
    public function getRegion()
    {
        if ($this->request->isPost()) {
            try {
                $defaulted = $this->userInfo['defaulted'];
                if($defaulted){
                    $ids = (new SysRegion())->where('disabled',0)->column('id');
                }else{
                    $ids = array_column($this->userInfo['relation_region'],'region_id');
                }
                if($this->request->has('region_id') && $this->result['region_id']){
                    $grade_id = (new SysRegion())->where('disabled',0)->where('id',$this->result['region_id'])->value('grade_id');
                    if(empty($grade_id)){
                        throw new \Exception('记录不存在');
                    }
                    $list = (new SysRegion())->whereIn('id', $ids)
                        ->where('disabled',0)
                        ->where('grade_id','=',$grade_id)
                        ->field([
                            'id',
                            'region_name'
                        ])
                        ->hidden(['deleted','disabled'])->select();
                }else{
                    $list = (new SysRegion())->whereIn('id', $ids)
                        ->where('disabled',0)
                        ->field([
                            'id',
                            'region_name'
                        ])
                        ->hidden(['deleted','disabled'])->select();
                }
                $data['data'] = $list;
                $data['grade_id'] = $this->userInfo['grade_id'];
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
     * 获取指定部门信息
     * @param id 部门ID
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
                    'region_id',
                    'telephone',
                    'department_name',
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
                $this->graded = 1;
                self::chkGraded($data['parent_id']);
                if ($this->graded > 3) {
                    throw new \Exception('部门层级超出限制');
                }
                $pinyin = new Pinyin();
                $postData['department_code'] = strtoupper($pinyin->abbr($data['department_name']));
                $region = (new SysRegion())->where('id', $data['region_id'])
                    ->where('disabled',0)
                    ->find();
                if (!empty($region['simple_code'])) {
                    $data['region_code'] = $region['simple_code'];
                }
                $data['grade_id'] = $region['grade_id'];
                $res = (new model())->addData($data,1);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('department', $dataList);
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
                    'region_id',
                    'department_name',
                    'telephone',
                    'disabled',
                    'hash'
                ]);
                  //验证表单hash
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
                    self::actRecurse($data['id'], 0);
                }else{
                    if (self::chkRecurse($data['id'])){
                        throw new \Exception('分类树为禁用状态！');
                    }
                    $region_id = (new model())->where('id', $data['id'])>value('region_id');
                    if($region_id != $data['region_id']){
                        self::actRecurse($data['id'], 2, $data['region_id']);
                    }
                }
                unset($data['hash']);
                $pinyin = new Pinyin();
                $data['department_code'] = strtoupper($pinyin->abbr($data['department_name']));
                $region = (new SysRegion())->where('id', $data['region_id'])
                    ->where('disabled',0)
                    ->find();
                if (!empty($region['simple_code'])) {
                    $data['region_code'] = $region['simple_code'];
                }
                $data['grade_id'] = $region['grade_id'];
                Db::name('Department')
                    ->where('id',$data['id'])
                    ->update($data);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('department', $dataList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
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
                $departmentData = (new model())->where('id',$data['id'])->find();
                if(empty($departmentData)){
                    throw new \Exception('数据不存在或已删除');
                }
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSSJQX');
                if(!$getData['code']){
                    throw new \Exception('未找到所查数据字典');
                }
                if($departmentData['parent_id'] == 0 && $this->userInfo['grade_id'] <  $getData['data']){
                    throw new \Exception('权限不足以执行此操作');
                }
                if($data['deleted']){
                    $resData = self::actRecurse($data['id'], 1);
                    if ($resData['code'] == 0){
                        throw new \Exception('递归处理失败请重试');
                    }
                }
                $code = rand(100, 999);
                Db::name('Department')->where('id',$data['id'])->update(['deleted' => 1,'department_name' => time().$code]);
                $update_cache = Cache::get('update_cache');
                if ($update_cache){
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('department', $dataList);
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
     * 检查层级
     * @return bool
     */
    private function chkGraded($parent_id)
    {
        if($parent_id){
            $parent_id = (new model())->where('id', $parent_id)->value('parent_id');
            $this->graded += 1;
            self::chkGraded($parent_id);
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
     * @return
     */
    private function actRecurse($id, $type = 0,$region_id = 0)
    {
        try {
            $data = (new model())->where('parent_id', $id)->select()->toArray();
            foreach ($data as $k =>$v){
                if ($v['id']){
                    $code = rand(100, 999);
                    if ($type == 1){
                        Db::name('Department')->where('id',$v['id'])->update(['deleted' => 1,'department_name' => time().$code]);
                    }else if ($type == 2){
                        Db::name('Department')->where('id',$v['id'])->update(['region_id' => $region_id]);
                    }else{
                        Db::name('Department')->where('id',$v['id'])->update(['disabled' => 1]);
                    }
                    self::actRecurse($v['id'], $type, $region_id);
                }
            }
            $res = [
                'code' => 1,
                'msg' => Lang::get('update_success')
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return $res;
    }
}