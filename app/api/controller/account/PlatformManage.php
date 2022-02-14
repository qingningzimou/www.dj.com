<?php
/**
 * Created by PhpStorm.
 * manage: aiwes
 * Date: 2020/4/25
 * Time: 11:10
 */
namespace app\api\controller\account;

use app\common\controller\Education;
use app\common\model\Manage as model;
use app\common\model\RelationManageRegion;
use app\common\model\Department;
use app\common\model\User;
use app\common\model\Schools;
use app\common\model\SysNodes;
use app\common\model\ManageNodes;
use app\common\model\SysRoles;
use app\common\model\SysRoleNodes;
use app\common\validate\account\PlatManage as validate;
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
class PlatformManage extends Education
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
                $manageIds = [];
                if ($this->userInfo['relation_region']){
                    $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                    $tmp = (new RelationManageRegion())
                        ->whereIn('region_id',$region_ids)
                        ->where('disabled',0)
                        ->column('manage_id');
                    $manageIds = array_unique(array_merge($manageIds,$tmp));
                    $where[] = ['id', 'in', $manageIds];
                }
                if(!$this->userInfo['main_account']){
                    $where[] = ['main_account','=',0];
                }
                if($this->userInfo['central_id'] && $this->middle_grade == $this->userInfo['grade_id']){
                    $where[] = ['central_id','=',$this->userInfo['central_id']];
                }
                $where[] = ['role_grade','=',$this->userInfo['grade_id']];
                $data = Db::name('Manage')
                    ->where($where)
                    ->where('role_id','=',$this->userInfo['role_id'])
                    ->where('deleted','=',0)
                    ->field([
                        'id',
                        'user_name',
                        'real_name',
                        'mobile',
                        'public_school_status',
                        'civil_school_status',
                        'primary_school_status',
                        'junior_middle_school_status',
                        'disabled',
                        'department_id',
                        'region_id',
                        'main_account',
                        'school_id',
                    ])
                    ->order('id','DESC')
                    ->paginate(['list_rows'=> $this->pageSize,'var_page'=>'curr'])->toArray();
                $department = Cache::get('department');
                $region = Cache::get('region');
                foreach($data['data'] as $key=>&$value){
                    $value['public_school_status'] ? $value['public_school_status'] = true : $value['public_school_status'] = false;
                    $value['civil_school_status'] ? $value['civil_school_status'] = true : $value['civil_school_status'] = false;
                    $value['primary_school_status'] ? $value['primary_school_status'] = true : $value['primary_school_status'] = false;
                    $value['junior_middle_school_status'] ? $value['junior_middle_school_status'] = true : $value['junior_middle_school_status'] = false;
                    $value['disabled'] ? $value['disabled'] = false : $value['disabled'] = true;
                    $value['main_account'] ? $value['main_account'] = true : $value['main_account'] = false;
                    $value['department_name'] = '';
                    if (!empty($value['department_id'])){
                        $departmentData = filter_value_one($department, 'id', $value['department_id']);
                        if (count($departmentData) > 0) {
                            $value['department_name'] = $departmentData['department_name'];
                        }
                    }
                    $value['region_name'] = '';
                    if (!empty($value['region_id'])){
                        $regionData = filter_value_one($region, 'id', $value['region_id']);
                        if (count($regionData) > 0) {
                            $value['region_name'] = $regionData['region_name'];
                        }
                    }
                }
                $res_data = parent::getResources($this->userInfo,$this->request->controller());
                $res_data['main_auth'] = [
                    'status_name' => '负责人权限',
                    'main_account' => $this->userInfo['main_account']
                ];
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


    //查看管理员信息
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
                $data = (new model())->find($this->result['id'])->toArray();
                if($data['role_grade'] != $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足不能进行此操作');
                }
                $data['nodes'] = Db::name('manageNodes')->where([
                    'manage_id' => $this->result['id'],
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
     * 获取账号资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            try {
                $roleNode = (new SysRoleNodes())->where('role_id', $this->userInfo['role_id'])->column('node_id');
                $hasNode = Db::name('manageNodes')->where([
                    'manage_id' => $this->userInfo['manage_id'],
                    'deleted' => 0,
                ])->column('node_id');
                if($this->userInfo['main_account']){
                    $hasNode = $roleNode;
                }
                $node_ids = array_intersect($roleNode,$hasNode);
                $nodes = (new SysNodes())
                    ->whereIn('id',$node_ids)
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })
                    ->hidden(['deleted'])->order('order_num')->select()->toArray();
                $data = $this->list_to_tree($nodes);
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
     * 新增管理人员
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'user_name',
                    'password',
                    'real_name',
                    'node_ids',
                    'mobile',
                    'public_school_status',
                    'civil_school_status',
                    'primary_school_status',
                    'junior_middle_school_status',
                    'disabled',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $checkManageData['user_name'] = $postData['user_name'];
                $checkManageData['role_id'] = $this->userInfo['role_id'];
                $checkManageError = $this->checkManage($checkManageData);
                if($checkManageError['code'] == 0){
                    throw new \Exception($checkManageError['msg']);
                }
                $node_ids = explode(',', $this->result['node_ids']);
                $node_ids = array_unique($node_ids);
                //  编译密码
                if($postData['password']){
                    $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                    if (preg_match($preg,$postData['password']) == 0){
                        throw new \Exception('密码应包含数字、字母和特殊字符!');
                    }
                    $postData['refresh_pass'] = 1;
                }
                $password = $postData['password'];
                $postData = $this->getAddPasswordData($postData);
                $postData['user_type'] = 1;
                $public_school_status = $postData['public_school_status'];
                $civil_school_status = $postData['civil_school_status'];
                $primary_school_status = $postData['primary_school_status'];
                $junior_middle_school_status = $postData['junior_middle_school_status'];
                $disabled = $postData['disabled'];
                unset($postData['public_school_status']);
                unset($postData['civil_school_status']);
                unset($postData['primary_school_status']);
                unset($postData['junior_middle_school_status']);
                unset($postData['disabled']);
                unset($postData['hash']);
                unset($postData['node_ids']);
                $postData['region_id'] = $this->userInfo['region_id'];

                $user_info = Db::name('user')
                    ->where('user_name', $postData['user_name'])
                    ->where('deleted', 0)
                    ->find();
                if (!empty($user_info)) {
                    $user_id = $user_info['id'];
                    if(!$user_info['user_type']){
                        (new User())->editData([
                            'id' => $user_id,
                            'user_type' => 1
                        ]);
                    }
                    if($password){
                        (new User())->editData([
                            'id' => $user_id,
                            'password' => $postData['password'],
                            'refresh_pass' => 1
                        ]);
                    }
                    Db::name('manage')
                        ->where('user_id',$user_id)
                        ->update([
                            'user_name' => $postData['user_name'],
                            'real_name' => $postData['real_name'],
                            'simple_code' => $postData['simple_code']
                        ]);
                }else{
                    //添加user表数据
                    $userData = (new User())->addData($postData,1);
                    if($userData['code']){
                        $user_id = $userData['insert_id'];
                    }else{
                        throw new \Exception('新增用户账户失败');
                    }
                }
                $manageData['user_id'] = $user_id;
                $manageData['user_name'] = $postData['user_name'];
                $manageData['real_name'] = $postData['real_name'];
                $manageData['simple_code'] = $postData['simple_code'];
                $manageData['mobile'] = $postData['mobile'];
                $manageData['role_id'] = $this->userInfo['role_id'];
                $manageData['role_grade'] = $this->userInfo['grade_id'];
                $manageData['region_id'] = $this->userInfo['region_id'];
                $manageData['central_id'] = $this->userInfo['central_id'];
                $manageData['department_id'] = $this->userInfo['department_id'];
                $manageData['public_school_status'] = $public_school_status;
                $manageData['civil_school_status'] = $civil_school_status;
                $manageData['primary_school_status'] = $primary_school_status;
                $manageData['junior_middle_school_status'] = $junior_middle_school_status;
                $manageData['disabled'] = $disabled;
                $getManageData = (new model())->addData($manageData,1);
                if($getManageData['code']){
                    $manage_id = $getManageData['insert_id'];
                }else{
                    throw new \Exception('新增管理账户失败');
                }
                if($this->userInfo['grade_id'] >= $this->city_grade){
                    $region = Cache::get('region');
                    $regionIds = array_column($region,'id');
                    foreach ($regionIds as $region_id){
                        Db::name('RelationManageRegion')->insert([
                            'manage_id' => $manage_id,
                            'region_id' => $region_id
                        ]);
                    }
                }else{
                    Db::name('RelationManageRegion')->insert([
                        'manage_id' => $manage_id,
                        'region_id' => $this->userInfo['region_id']
                    ]);
                }
                //添加权限信息
                $chk_node_ids = (new SysNodes())
                    ->whereIn('id',$node_ids)
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })->column('id');
                $roleNodes = (new SysRoleNodes())
                    ->where('role_id', $this->userInfo['role_id'])
                    ->whereIn('node_id',$chk_node_ids)
                    ->field(['node_id','node_type'])
                    ->select()
                    ->toArray();
                $saveData = [];
                if ($roleNodes){
                    foreach($roleNodes as $key => $value){
                        $saveData[] = [
                            'manage_id' => $manage_id,
                            'node_id' => $value['node_id'],
                            'node_type' => $value['node_type'],
                        ];
                    }
                    Db::name('ManageNodes')->insertAll($saveData);
                }
                Cache::delete('user'.md5($user_id));
                if (Cache::get('update_cache')) {
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('manage', $dataList);
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
     * 编辑管理员信息
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'user_name',
                    'real_name',
                    'password',
                    'node_ids',
                    'mobile',
                    'public_school_status',
                    'civil_school_status',
                    'primary_school_status',
                    'junior_middle_school_status',
                    'disabled',
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
                if($postData['password']){
                    $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                    if (preg_match($preg,$this->result['password']) == 0){
                        throw new \Exception('密码应包含数字、字母和特殊字符!');
                    }
                    $postData['refresh_pass'] = 1;
                }
                $node_ids = explode(',', $this->result['node_ids']);
                $node_ids = array_unique($node_ids);

                $id = $postData['id'];
                $public_school_status = $postData['public_school_status'];
                $civil_school_status = $postData['civil_school_status'];
                $primary_school_status = $postData['primary_school_status'];
                $junior_middle_school_status = $postData['junior_middle_school_status'];
                $disabled = $postData['disabled'];
                unset($postData['public_school_status']);
                unset($postData['civil_school_status']);
                unset($postData['primary_school_status']);
                unset($postData['junior_middle_school_status']);
                unset($postData['disabled']);
                unset($postData['hash']);
                unset($postData['node_ids']);
                unset($postData['id']);
                $checkManageData['user_name'] = $postData['user_name'];
                $checkManageData['role_id'] = $this->userInfo['role_id'];
                $checkManageError = $this->checkManage($checkManageData,$id);
                if($checkManageError['code'] == 0){
                    throw new \Exception($checkManageError['msg']);
                }
                if($checkManageError['data']['role_grade'] != $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足不可进行此操作');
                }
                $checkUserError = $this->checkUser($checkManageError['data']['user_id'],$checkManageData['user_name']);
                if($checkUserError['code'] == 0){
                    throw new \Exception($checkUserError['msg']);
                }
                $userInfo = $checkUserError['data'];
                //  编译密码
                $postData = $this->getUpdatePasswordData($postData);
                $postData['id'] = $checkManageError['data']['user_id'];
                $postData['err_num'] = 0;
                //更新user表数据
                (new User())->editData($postData);
                //更新manage表
                $manageData['id'] = $id;
                $manageData['user_name'] = $postData['user_name'];
                $manageData['real_name'] = $postData['real_name'];
                $manageData['mobile'] = $postData['mobile'];
                $manageData['public_school_status'] = $public_school_status;
                $manageData['civil_school_status'] = $civil_school_status;
                $manageData['primary_school_status'] = $primary_school_status;
                $manageData['junior_middle_school_status'] = $junior_middle_school_status;
                $manageData['disabled'] = $disabled;
                $manage_update = (new model())->editData($manageData);
                if($manage_update['code'] == 0){
                    throw new \Exception($manage_update['msg']);
                }
                Db::name('manage')
                    ->where('id','<>',$id)
                    ->where('user_id',$postData['id'])
                    ->update([
                        'user_name' => $postData['user_name'],
                        'real_name' => $postData['real_name'],
                        'simple_code' => $postData['simple_code']
                    ]);
                //更新权限节点
                $chk_node_ids = (new SysNodes())
                    ->whereIn('id',$node_ids)
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })
                    ->column('id');
                $roleNodes = (new SysRoleNodes())
                    ->where('role_id',$this->userInfo['role_id'])
                    ->whereIn('node_id',$chk_node_ids)
                    ->field(['node_id','node_type'])->select()->toArray();
                $hasData = Db::name('manageNodes')->where([
                    'manage_id' => $id,
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
                            Db::name('manageNodes')->where('id',$hasData[$v['node_id']]['id'])->update(['deleted' => 0]);
                            unset($hasData[$v['node_id']]);
                        }else{
                            if($v['node_id']){
                                $saveData[] = [
                                    'node_id' => $v['node_id'],
                                    'manage_id' => $id,
                                    'node_type' => $v['node_type']
                                ];
                            }
                        }
                    }
                    //  保存新增的节点信息
                    (new manageNodes())->saveAll($saveData);
                    //  删除多余的节点信息
                    Db::name('manageNodes')->whereIn('id', array_column($hasData,'id'))->update(['deleted' => 1]);
                }
                Cache::delete('user'.md5($userInfo['id']));
                if (Cache::get('update_cache')) {
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('manage', $dataList);
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
     * 删除管理人员
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
                //  获取管理人员的关联信息
                $region_id = (new RelationManageRegion())->where(['manage_id' => $data['id']])->where('disabled',0)->value('region_id');
                $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                $chkeck_region = 0;
                if(in_array($region_id,$region_ids)){
                    $chkeck_region = 1;
                }
                $data = (new model())->find($data['id'])->toArray();
                if(($data['role_grade'] != $this->userInfo['grade_id'] || !$chkeck_region) && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足不能进行此操作');
                }
                $resDel = $this->deleteManage($data['id']);
                if(!$resDel){
                    throw new \Exception('删除管理员失败');
                }
                if (Cache::get('update_cache')) {
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('manage', $dataList);
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
            $data = (new model())->find($postData['id'])->toArray();
            if($data['role_grade'] != $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            $checkManage = $this->checkManage([],$postData['id']);
            if($checkManage['code'] == 0){
                throw new \Exception($checkManage['msg']);
            }
            $state = $this->DisabledState($postData['id'],$postData['state']);
            if(!$state){
                throw new \Exception('操作失败');
            }
            Cache::delete('user'.md5($checkManage['data']['user_id']));
            if (Cache::get('update_cache')) {
                $dataList = (new model())->where('disabled',0)->select()->toArray();
                Cache::set('manage', $dataList);
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
    public function publicState(){
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
            $data = (new model())->find($postData['id'])->toArray();
            if($data['role_grade'] != $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            $checkManage = $this->checkManage([],$postData['id']);
            if($checkManage['code'] == 0){
                throw new \Exception($checkManage['msg']);
            }
            $state = $this->publicSchoolState($postData['id'],$postData['state']);
            if(!$state){
                throw new \Exception('操作失败');
            }
            Cache::delete('user'.md5($checkManage['data']['user_id']));
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

    public function primaryState(){
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
            $data = (new model())->find($postData['id'])->toArray();
            if($data['role_grade'] != $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            $checkManage = $this->checkManage([],$postData['id']);
            if($checkManage['code'] == 0){
                throw new \Exception($checkManage['msg']);
            }
            $state = $this->primarySchoolState($postData['id'],$postData['state']);
            if(!$state){
                throw new \Exception('操作失败');
            }
            Cache::delete('user'.md5($checkManage['data']['user_id']));
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

    public function civilState(){
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
            $data = (new model())->find($postData['id'])->toArray();
            if($data['role_grade'] != $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            $checkManage = $this->checkManage([],$postData['id']);
            if($checkManage['code'] == 0){
                throw new \Exception($checkManage['msg']);
            }
            $state = $this->civilSchoolState($postData['id'],$postData['state']);
            if(!$state){
                throw new \Exception('操作失败');
            }
            Cache::delete('user'.md5($checkManage['data']['user_id']));
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

    public function juniorState(){
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
            $data = (new model())->find($postData['id'])->toArray();
            if($data['role_grade'] != $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            $checkManage = $this->checkManage([],$postData['id']);
            if($checkManage['code'] == 0){
                throw new \Exception($checkManage['msg']);
            }
            $state = $this->juniorSchoolState($postData['id'],$postData['state']);
            if(!$state){
                throw new \Exception('操作失败');
            }
            Cache::delete('user'.md5($checkManage['data']['user_id']));
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