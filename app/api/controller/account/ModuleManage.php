<?php
/**
 * Created by PhpStorm.
 * manage: aiwes
 * Date: 2020/4/25
 * Time: 11:10
 */
namespace app\api\controller\account;

use app\common\controller\Education;
use app\common\model\Manage;
use app\common\model\Module;
use app\common\model\ModuleNodes;
use app\common\model\RelationManageRegion;
use app\common\model\SysRegion;
use app\common\model\SysNodes;
use app\common\model\SysRoles;
use app\common\model\SysRoleNodes;
use app\common\validate\basic\ModuleManage as validate;
use think\App;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Overtrue\Pinyin\Pinyin;
use think\facade\Cache;
use dictionary\FilterData;
use think\facade\Log;
/*
 * 修正了ThinkPHP框架的验证器类，以支持联合索引类型的Unique验证
 * */
class ModuleManage extends Education
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 按分页获取信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $region_ids = [];
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['region_id','=',$this->result['region_id']];
                }

                if ($this->userInfo['relation_region']){
                    $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                }
                $where[] = ['region_id', 'in', $region_ids];
                $data = Db::name('module_config')
                    ->where($where)
                    ->where('deleted',0)
                    ->field([
                        'id',
                        'module_name',
                        'start_time',
                        'end_time',
                        'region_id',
                        'disabled',
                    ])
                    ->paginate(['list_rows'=> $this->pageSize,'var_page' => 'curr'])->toArray();
                $region = Cache::get('region');
                foreach($data['data'] as $key=>&$value){
                    $value['disabled'] ? $value['disabled'] = false : $value['disabled'] = true;
                    $value['region_name'] = '';
                    if (!empty($value['region_id'])){
                        $regionData = filter_value_one($region, 'id', $value['region_id']);
                        if (count($regionData) > 0) {
                            $value['region_name'] = $regionData['region_name'];
                        }
                    }
                }
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
     * 页面资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            $dictionary = new FilterData();
            $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSGBXXBM');
            $public_role = $getData['data'];
            $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSMBXXBM');
            $civil_role = $getData['data'];
            $node_ids = (new SysRoleNodes())->where('role_id',$public_role)->whereOr('role_id',$civil_role)->column('node_id');
            $nodes = (new SysNodes())
                ->whereIn('id',$node_ids)
                ->where(function ($query) {
                    $query->where('authority', 1)
                        ->whereOr('displayed', 1);
                })
                ->where('defaulted',0)
                ->where('node_type',0)
                ->hidden(['deleted'])
                ->order('order_num')->select()->toArray();
            $data['nodes'] = $this->list_to_tree($nodes);
            $region = Cache::get('region');
            $data['region'] = filter_by_value($region, 'grade_id', $this->area_grade);
            $res = [
                'code' => 1,
                'data' => $data,
            ];
            return parent::ajaxReturn($res);
        }
    }

    //查看信息
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->only([
                    'id',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $postData['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['data'] = (new Module())->find($postData['id'])->toArray();
                $data['nodes'] = Db::name('moduleNodes')->where([
                    'module_id' => $this->result['id'],
                    'deleted' => 0,
                ])->column('node_id');
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
     * 新增学校管控
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'module_name',
                    'start_time',
                    'end_time',
                    'node_ids',
                    'public_school_status',
                    'civil_school_status',
                    'primary_school_status',
                    'junior_middle_school_status',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //市级权限 以上需要选择区域
                if($this->userInfo['grade_id'] >= $this->city_grade){
                    $region_id = $this->result['region_id'];
                    if(!$region_id){
                        throw new \Exception('市级以上权限请选择区县');
                    }
                }else{
                    $region_id = $this->userInfo['region_id'];
                }
                $postData['region_id'] = $region_id;
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if( strtotime($postData['start_time']) > strtotime($postData['end_time']) ){
                    throw new \Exception('开始时间不能大于结束时间！');
                }
                if( strtotime($postData['start_time']) < strtotime(date('Y-m-d')) ){
                    throw new \Exception('开始时间不能小于当前日期！');
                }
                if( strtotime($postData['end_time']) > strtotime('2037-12-31 23:59:59') ){
                    throw new \Exception('结束时间太大！');
                }

                $node_ids = explode(',', $postData['node_ids']);
                $node_ids = array_unique($node_ids);
                unset($postData['hash']);
                unset($postData['node_ids']);

                $dictionary = new FilterData();
                $postData['region_id'] = $this->userInfo['role_id'] >= $this->city_grade ? $postData['region_id'] : $this->userInfo['region_id'];
                //添加管控表数据
                $moduleData = (new Module())->addData($postData,1);
                if($moduleData['code']){
                    $module_id = $moduleData['insert_id'];
                }else{
                    throw new \Exception('新增学校功能管控失败');
                }
                $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSGBXXBM');
                $public_role = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSMBXXBM');
                $civil_role = $getData['data'];
                $not_node_ids = (new SysNodes())
                    ->where('authority', 0)
                    ->where('displayed', 0)
                    ->column('id');
                $nodeIds = (new SysRoleNodes())
                    ->whereNotIn('node_id',$not_node_ids)
                    ->where(function ($query) use($public_role, $civil_role) {
                    $query->where('role_id', $public_role)
                        ->whereOr('role_id', $civil_role);
                    })
                    ->whereIn('node_id',$node_ids)
                    ->field(['node_id','node_type'])->distinct(true)->select()->toArray();
                //添加权限管控信息
                $saveData = [];
                foreach($nodeIds as $key => $value){
                    if($value['node_id']){
                        $saveData[] = [
                            'module_id' => $module_id,
                            'node_id' => $value['node_id'],
                            'node_type' => $value['node_type']
                        ];
                    }
                }
                Db::name('ModuleNodes')->insertAll($saveData);
                if (Cache::get('update_cache')) {
                    $dataList = (new Module())->where('disabled',0)->select()->toArray();
                    Cache::set('module', $dataList);
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
     * 编辑学校管控
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'module_name',
                    'start_time',
                    'end_time',
                    'node_ids',
                    'public_school_status',
                    'civil_school_status',
                    'primary_school_status',
                    'junior_middle_school_status',
                    'hash',
                ]);
                 //  验证表单hash
                 $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                 if($checkHash['code'] == 0)
                 {
                     throw new \Exception($checkHash['msg']);
                 }
                //市级权限 以上需要选择区域
                if($this->userInfo['grade_id'] >= $this->city_grade){
                    $region_id = $this->result['region_id'];
                    if(!$region_id){
                        throw new \Exception('市级以上权限请选择区县');
                    }
                }else{
                    $region_id = $this->userInfo['region_id'];
                }
                $postData['region_id'] = $region_id;
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $node_ids = explode(',', $postData['node_ids']);
                $node_ids = array_unique($node_ids);

                unset($postData['hash']);
                unset($postData['node_ids']);

                if( strtotime($postData['start_time']) > strtotime($postData['end_time']) ){
                    throw new \Exception('开始时间不能大于结束时间！');
                }
                if( strtotime($postData['start_time']) < strtotime(date('Y-m-d')) ){
                    throw new \Exception('开始时间不能小于当前日期！');
                }
                if( strtotime($postData['end_time']) > strtotime('2037-12-31 23:59:59') ){
                    throw new \Exception('结束时间太大！');
                }

                $dictionary = new FilterData();
                $postData['region_id'] = $this->userInfo['role_id'] >= $this->city_grade ? $postData['region_id'] : $this->userInfo['region_id'];
                $module_update = (new Module())->editData($postData);
                if($module_update['code'] == 0){
                    throw new \Exception($module_update['msg']);
                }
                //学校角色权限节点
                $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSGBXXBM');
                $public_role = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSMBXXBM');
                $civil_role = $getData['data'];
                //处理管控关联资源
                $chk_node_ids = (new SysNodes())
                    ->whereIn('id',$node_ids)
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })->column('id');
                $roleNodes = (new SysRoleNodes())
                    ->where(function ($query) use($public_role, $civil_role) {
                        $query->where('role_id', $public_role)
                            ->whereOr('role_id', $civil_role);
                    })
                    ->whereIn('node_id',$chk_node_ids)
                    ->field(['node_id','node_type'])
                    ->distinct(true)->select()->toArray();
                $hasData = Db::name('ModuleNodes')->where([
                    'module_id' => $postData['id'],
                ])->column('node_id,id,deleted','node_id');
                $chkData = array_values(filter_by_value($hasData, 'deleted', 0));
                $chkData = array_column($chkData,'node_id');
                $newData = array_column($roleNodes,'node_id');
                $mergeData = array_values(array_unique(array_merge($newData,$chkData)));
                $diffData = array_diff($mergeData,$newData);
                if(!count($diffData)){
                    $diffData = array_diff($mergeData,$chkData);
                }
                if(count($diffData)){
                    $mapData = array_column($hasData,'node_id');
                    $saveData = [];
                    foreach($roleNodes as $k => $v){
                        if(in_array($v['node_id'],$mapData))
                        {
                            Db::name('ModuleNodes')->where('id',$hasData[$v['node_id']]['id'])->update(['deleted' => 0]);
                            unset($hasData[$v['node_id']]);
                        }else{
                            if($v['node_id']){
                                $saveData[] = [
                                    'node_id' => $v['node_id'],
                                    'module_id' => $postData['id'],
                                    'node_type' => $v['node_type']
                                ];
                            }
                        }
                    }
                    //  保存新增的节点信息
                    Db::name('ModuleNodes')->insertAll($saveData);
                    //  删除多余的节点信息
                    Db::name('ModuleNodes')->whereIn('id', array_column($hasData,'id'))->update(['deleted' => 1]);
                }

                if (Cache::get('update_cache')) {
                    $dataList = (new Module())->where('disabled',0)->select()->toArray();
                    Cache::set('module', $dataList);
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
     * 删除管控
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
                Db::name('ModuleNodes')->where('module_id',$data['id'])->update(['deleted' => 1]);
                (new Module())->editData($data);
                if (Cache::get('update_cache')) {
                    $dataList = (new Module())->where('disabled',0)->select()->toArray();
                    Cache::set('module', $dataList);
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


    public function setState(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            Db::name('ModuleConfig')->where('id',$postData['id'])->update(['disabled' => $postData['state']]);
            if (Cache::get('update_cache')) {
                $dataList = (new Module())->where('disabled',0)->select()->toArray();
                Cache::set('module', $dataList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
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