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
use app\common\model\SysDictionary;
use app\common\model\SysRegion;
use app\common\model\SysNodes;
use app\common\model\ManageNodes;
use app\common\model\User;
use app\common\model\SysRoles;
use app\common\model\SysRoleNodes;
use app\common\validate\basic\System as validate;
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
class System extends Education
{
    private $role_grade;
    private $role_id;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $dictionary = new FilterData();
        $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSXTQX');
        $this->role_grade = $getData['data'];
        $roles = Cache::get('roles');
        $rolesData = filter_value_one($roles, 'role_grade', $this->role_grade);
        if (count($rolesData) > 0) {
            $this->role_id = $rolesData['id'];
        }
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
                $data = $this->getManageList($where,$this->role_grade);
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
                $data = (new model())->find($this->result['id']);
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
                    'main_account',
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
                $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                if (preg_match($preg,$this->result['password']) == 0){
                    throw new \Exception('密码应包含数字、字母和特殊字符!');
                }
                if ($this->role_grade > $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足以进行此操作');
                }
                $checkManageData['user_name'] = $postData['user_name'];
                $checkManageData['role_id'] = $this->role_id;
                $checkManageError = $this->checkManage($checkManageData);
                if($checkManageError['code'] == 0){
                    throw new \Exception($checkManageError['msg']);
                }
                $node_ids = explode(',', $postData['node_ids']);
                $node_ids = array_unique($node_ids);
                //  编译密码
                $password = $postData['password'];
                if($password){
                    $postData['refresh_pass'] = 1;
                }
                $postData = $this->getAddPasswordData($postData);
                $postData['user_type'] = 1;
                $main_account = $postData['main_account'];
                $disabled = $postData['disabled'];
                unset($postData['main_account']);
                unset($postData['disabled']);
                unset($postData['hash']);
                unset($postData['node_ids']);
                $department = Cache::get('department');
                $last_names = array_column($department,'grade_id');
                array_multisort($last_names,SORT_DESC,$department);
                $departmentData = $department[0];
                $postData['region_id'] = $departmentData['region_id'];

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
                $manageData['role_id'] = $this->role_id;
                $manageData['role_grade'] = $this->role_grade;
                $manageData['region_id'] = $postData['region_id'];
                $manageData['department_id'] = $departmentData['id'];
                $manageData['public_school_status'] = 1;
                $manageData['civil_school_status'] = 1;
                $manageData['primary_school_status'] = 1;
                $manageData['junior_middle_school_status'] = 1;
                $manageData['main_account'] = $main_account;
                $manageData['disabled'] = $disabled;
                $getManageData = (new model())->addData($manageData,1);
                if($getManageData['code']){
                    $manage_id = $getManageData['insert_id'];
                }else{
                    throw new \Exception('新增管理账户失败');
                }
                $region = Cache::get('region');
                $regionIds = array_column($region,'id');
                foreach ($regionIds as $region_id){
                    Db::name('RelationManageRegion')->insert([
                        'manage_id' => $manage_id,
                        'region_id' => $region_id
                    ]);
                }
                $chk_node_ids = (new SysNodes())->whereIn('id',$node_ids)->where(function ($query) {
                    $query->where('authority', 1)
                        ->whereOr('displayed', 1);
                })->column('id');
                //添加权限信息
                $roleNodes = (new SysRoleNodes())->where('role_id',$this->role_id)->whereIn('node_id',$chk_node_ids)->field(['node_id','node_type'])->select()->toArray();
                $saveData = [];
                foreach($roleNodes as $key => $value){
                    $saveData[] = [
                        'manage_id' => $manage_id,
                        'node_id' => $value['node_id'],
                        'node_type' => $value['node_type']
                    ];
                }
                Db::name('ManageNodes')->insertAll($saveData);
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
                    'main_account',
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
                if ($this->role_grade > $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足以进行此操作');
                }
                $node_ids = explode(',', $postData['node_ids']);
                $node_ids = array_unique($node_ids);

                $id = $postData['id'];
                $main_account = $postData['main_account'];
                $disabled = $postData['disabled'];
                unset($postData['main_account']);
                unset($postData['disabled']);
                unset($postData['hash']);
                unset($postData['node_ids']);
                unset($postData['id']);

                $checkManageData['user_name'] = $postData['user_name'];
                $checkManageData['role_id'] = $this->role_id;
                $checkManageError = $this->checkManage($checkManageData,$id);
                if($checkManageError['code'] == 0){
                    throw new \Exception($checkManageError['msg']);
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
                $user_update = (new User())->editData($postData);
                if($user_update['code'] == 0){
                    throw new \Exception($user_update['msg']);
                }
                //更新manage表
                $manageData['id'] = $id;
                $manageData['user_name'] = $postData['user_name'];
                $manageData['real_name'] = $postData['real_name'];
                $manageData['mobile'] = $postData['mobile'];
                $manageData['main_account'] = $main_account;
                if($this->userInfo['main_account'] || !$main_account){
                    $manageData['disabled'] = $disabled;
                }
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
                //处理账号关联资源
                $chk_node_ids = (new SysNodes())
                    ->whereIn('id',$node_ids)
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })
                    ->column('id');
                $roleNodes = (new SysRoleNodes())
                    ->where('role_id',$this->role_id)
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
                $manage_region_ids = (new RelationManageRegion())->where(['manage_id' => $data['id']])->where('disabled',0)->column('region_id');
                $adm_region_ids = array_column($this->userInfo['relation_region'],'region_id');
                $chkeck_region = 0;
                foreach ($adm_region_ids as $value){
                    if(in_array($value,$manage_region_ids)){
                        $chkeck_region = 1;
                    }
                }
                if (!$chkeck_region && !$this->userInfo['defaulted']) {
                    throw new \Exception('权限不足以进行此操作');
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
            }
            Db::rollback();
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 获取系统账号资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            try {
                $roleNode = (new SysRoleNodes())->where('role_id', $this->role_id)->column('node_id');
                $hasNode = Db::name('manageNodes')->where([
                    'manage_id' => $this->userInfo['manage_id'],
                    'deleted' => 0,
                ])->column('node_id');
                if ($this->userInfo['defaulted']){
                    $hasNode = $roleNode;
                }else{
                    if($this->userInfo['grade_id'] > $this->role_grade){
                        $hasNode = $roleNode;
                    }elseif($this->userInfo['grade_id'] == $this->role_grade && $this->userInfo['main_account']){
                        $hasNode = $roleNode;
                    }
                }
                $node_ids = array_intersect($roleNode,$hasNode);
                $nodes = (new SysNodes())->whereIn('id',$node_ids)
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

}