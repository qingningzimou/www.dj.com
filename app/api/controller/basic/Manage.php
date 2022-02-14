<?php
/**
 * Created by PhpStorm.
 * manage: aiwes
 * Date: 2020/4/25
 * Time: 11:10
 */
namespace app\api\controller\basic;

use app\common\controller\Education;
use app\common\model\Manage as model;
use app\common\model\RelationManageRegion;
use app\common\model\Department;
use app\common\model\SysRegion;
use app\common\model\SysNodes;
use app\common\model\ManageNodes;
use app\common\model\SysRoles;
use app\common\model\SysRoleNodes;
use app\common\validate\basic\Manage as validate;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;
use sms\SmsSend;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Overtrue\Pinyin\Pinyin;
use think\facade\Cache;
use dictionary\FilterData;
use think\facade\Log;
/*
 * 修正了ThinkPHP框架的验证器类，以支持联合索引类型的Unique验证
 * */
class Manage extends Education
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
                $manageIds = [];
                if($this->request->has('region_id') && $this->result['region_id']){
                    $where[] = ['region_id','=',$this->result['region_id']];
                }
                if ($this->userInfo['relation_region']){
                    $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                    $tmp = (new RelationManageRegion())
                        ->whereIn('region_id',$region_ids)
                        ->where($where)
                        ->where('disabled',0)
                        ->column('manage_id');
                    $manageIds = array_unique(array_merge($manageIds,$tmp));
                }
                $where = [];
                if ($this->userInfo['relation_region']){
                    $where[] = ['manage.id', 'in', $manageIds];
                }
                if($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['manage.user_name|manage.real_name|manage.simple_code|manage.mobile', 'like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('department_id') && $this->result['department_id'])
                {
                    $where[] = ['manage.department_id','=', $this->result['department_id']];
                }
                if(!$this->userInfo['main_account']){
                    $role_id = $this->userInfo['role_id'];
                    $manageIds = Db::name('Manage')->where('role_id',$role_id)->where('main_account',1)->column('id');
                    $where[] = ['manage.id','not in', $manageIds];
                }
                $data = Db::name('Manage')
                    ->alias('manage')
                    ->join('SysRoles roles','manage.role_id = roles.id && roles.disabled = 0 && roles.deleted = 0')
                    ->where($where)
                    ->where('manage.role_grade','<=',$this->userInfo['grade_id'])
                    ->field([
                        'manage.id',
                        'manage.user_name',
                        'manage.real_name',
                        'roles.role_name',
                        'manage.mobile',
                        'manage.role_id',
                        'manage.department_id',
                        'manage.disabled',
                    ])
                    ->order('manage.id desc')
                    ->paginate(['list_rows'=> $this->pageSize,'var_page' => 'curr'])->toArray();
                $department = Cache::get('department');
                foreach ($data['data'] as $key => $manage){
                    $data['data'][$key]['department_name'] = '';
                    if (!empty($manage['department_id'])){
                        $departmentData = filter_value_one($department, 'id', $manage['department_id']);
                        if (count($departmentData) > 0) {
                            $data['data'][$key]['department_name'] = $departmentData['department_name'];
                        }
                    }
                };
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
     * 获取指定管理人员信息
     * @param id 管理人员manage表ID
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
                $data = (new model())->with(['relationRegion','relationDepartment'])->where('id',$this->result['id'])->find()->toArray();
                $role_ids = array_column($this->userInfo['role_ids'],'id');
                $data['range'] =  (new SysRoles())->whereIn('id', $role_ids)->max('role_grade');
                $adm_region_ids = array_column($this->userInfo['relation_region'],'region_id');
                $manage_region_ids = array_column($data['relationRegion'],'region_id');
                $chkeck_region = 0;
                foreach ($adm_region_ids as $value){
                    if(in_array($value,$manage_region_ids)){
                        $chkeck_region = 1;
                    }
                }
                if (!$chkeck_region && !$this->userInfo['defaulted']) {
                    throw new \Exception('无权操作');
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
     * 获取管理员角色的权限节点
     * @param role_id   角色ID
     * @return \think\response\Json
     */
    public function getManageRoleList()
    {
        if ($this->request->isPost()) {
            try {
                $roleNode = (new SysRoleNodes())->where('role_id', $this->result['role_id'])->column('node_id');
                $roleData = (new SysRoles())->where('id', $this->result['role_id'])->find();
                $hasNode = Db::name('manageNodes')->where([
                    'manage_id' => $this->userInfo['manage_id'],
                    'deleted' => 0,
                ])->column('node_id');
                if ($this->userInfo['defaulted']){
                    $hasNode = $roleNode;
                }else{
                    if($this->userInfo['grade_id'] > $roleData['role_grade']){
                        $hasNode = $roleNode;
                    }elseif($this->userInfo['grade_id'] == $roleData['role_grade'] && $this->userInfo['main_account']){
                        $hasNode = $roleNode;
                    }
                }
                $node_ids = array_intersect($roleNode,$hasNode);
                $nodes = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->order('order_num')->select()->toArray();
                $res = [
                    'code' => 1,
                    'data' => $nodes
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
                    'role_id',
                    'mobile',
                    'idcard',
                    'region_id',
                    'node_ids',
                    'main_account',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                if (preg_match($preg,$this->result['password']) == 0){
                    throw new \Exception('密码应包含数字、字母和特殊字符!');
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                unset($postData['hash']);
                $role_grade = (new SysRoles())->where('id', $postData['role_id'])->value('role_grade');
                if ($role_grade > $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足以进行此操作');
                }
                $manage_info = Db::name('manage')
                    ->where('user_name', $postData['user_name'])
                    ->where('role_id', $postData['role_id'])
                    ->where('deleted', 0)
                    ->find();
                if (!empty($manage_info)){
                    throw new \Exception('管理人员已有相同角色账号存在');
                }
                //  获取管理人员的关联信息
                $adm_region_ids = array_column($this->userInfo['relation_region'],'region_id');
                $chkeck_region = 0;
                foreach ($adm_region_ids as $value){
                    if($value == $postData['region_id']){
                        $chkeck_region = 1;
                    }
                }
                if (!$chkeck_region && !$this->userInfo['defaulted']) {
                    throw new \Exception('权限不足以进行此操作');
                }
                $password= password_hash($postData['password'], PASSWORD_DEFAULT);
                $pinyin = new Pinyin();
                $postData['simple_code'] = $pinyin->abbr($postData['real_name']);
                $user_info = Db::name('user')
                    ->where('user_name', $postData['user_name'])
                    ->where('deleted', 0)
                    ->find();
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSSJQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                if (!empty($user_info)) {
                    $user_id = $user_info['id'];
                }else{
                    $user_id = Db::name('user')->insertGetId([
                        'user_name' => $postData['user_name'],
                        'password' => $password,
                        'real_name' => $postData['real_name'],
                        'simple_code' => $postData['simple_code'],
                        'region_id' => $postData['region_id'],
                        'idcard' => $postData['idcard'],
                        'mobile' => $postData['mobile'],
                        'user_type' => 1,
                    ]);
                }
                $manage_id = Db::name('manage')->insertGetId([
                    'user_id' => $user_id,
                    'user_name' => $postData['user_name'],
                    'real_name' => $postData['real_name'],
                    'simple_code' => $postData['simple_code'],
                    'role_id' => $postData['role_id'],
                    'role_grade' => $role_grade,
                    'region_id' => $postData['region_id'],
                    'department_id' => $postData['department_id'],
                    'mobile' => $postData['mobile'],
                    'idcard' => $postData['idcard'],
                    'main_account' => $postData['main_account'],
                    'public_school_status' => 1,
                    'civil_school_status' => 1,
                    'primary_school_status' => 1,
                    'junior_middle_school_status' => 1,
                ]);
                if ($role_grade < $getData['data']){
                    //添加关联区域信息
                    Db::name('RelationManageRegion')->insert([
                        'manage_id' => $manage_id,
                        'region_id' => $postData['region_id']
                    ]);
                }else{
                    $region = Cache::get('region');
                    $regionIds = array_column($region,'id');
                    if($role_grade >= $getData['data']){
                        foreach ($regionIds as $region_id){
                            Db::name('RelationManageRegion')->insert([
                                'manage_id' => $manage_id,
                                'region_id' => $region_id
                            ]);
                        }
                    }else{
                        Db::name('RelationManageRegion')->insert([
                            'manage_id' => $manage_id,
                            'region_id' => $postData['region_id']
                        ]);
                    }
                }
                //添加权限信息
                $roleNodes = (new SysRoleNodes())->where('role_id',$postData['role_id'])->whereIn('node_id',$postData['node_ids'])->field(['node_id','node_type'])->select()->toArray();
                $saveData = [];
                foreach($roleNodes as $key => $value){
                    $saveData[] = [
                        'manage_id' => $manage_id,
                        'node_id' => $value['node_id'],
                        'node_type' => $value['node_type']
                    ];
                }
                Db::name('ManageNodes')->insertAll($saveData);
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
                    'user_name',
                    'password',
                    'real_name',
                    'role_id',
                    'mobile',
                    'idcard',
                    'region_id',
                    'node_ids',
                    'main_account',
                    'disabled',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'edit');
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $role_grade = (new SysRoles())->where('id', $data['role_id'])->value('role_grade');
                if ($role_grade > $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足以进行此操作');
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
                $chk_manage = Db::name('manage')
                    ->where('user_name', $data['user_name'])
                    ->where('role_id', $data['role_id'])
                    ->where('id','<>',$data['id'])
                    ->where('deleted', 0)
                    ->find();
                if (!empty($chk_manage)){
                    throw new \Exception('管理人员已有相同角色账号存在');
                }
                $manage_info = Db::name('manage')
                    ->where('id', $data['id'])
                    ->where('deleted', 0)
                    ->find();
                $user_info = Db::name('user')
                    ->where('user_name', $data['user_name'])
                    ->where('id','<>',$manage_info['user_id'])
                    ->where('deleted', 0)
                    ->find();
                if (!empty($user_info)) {
                    throw new \Exception('登录名称重复请检查');
                }
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSSJQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['real_name']);
                $user_save = [
                    'user_name' => $data['user_name'],
                    'real_name' => $data['real_name'],
                    'simple_code' => $data['simple_code'],
                    'region_id' => $data['region_id'],
                    'mobile' => $data['mobile'],
                ];
                //  如果修改密码，则编译密码
                if (!empty($data['password'])) {
                    $user_save['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                unset($data['password']);
                $manage_info = Db::name('manage')
                    ->where('id', $data['id'])
                    ->where('deleted', 0)
                    ->find();
                if($manage_info['region_id'] != $data['region_id']){
                    if ($role_grade < $getData['data']) {
                        //处理账号关联区域
                        $hasData = Db::name('RelationManageRegion')->where([
                            'manage_id' => $data['id'],
                        ])->column('region_id,id,deleted','region_id');
                        $mapData = array_column($hasData,'region_id');
                        $saveData = [];
                        if(in_array($data['region_id'],$mapData))
                        {
                            Db::name('RelationManageRegion')->where('id',$hasData[$data['region_id']]['id'])->update(['deleted' => 0]);
                            unset($hasData[$data['region_id']]);
                        }else{
                            if($data['region_id']){
                                $saveData[] = [
                                    'region_id' => $data['region_id'],
                                    'manage_id' => $data['id'],
                                ];
                            }
                        }
                        //  保存新增的区域信息
                        (new RelationManageRegion())->saveAll($saveData);
                        //  删除多余的区域信息
                        Db::name('RelationManageRegion')->whereIn('id', array_column($hasData,'id'))->update(['deleted' => 1]);
                    }else{
                        $region = Cache::get('region');
                        $regionIds = array_column($region,'region_id');
                        $hasData = Db::name('RelationManageRegion')->where([
                            'manage_id' => $data['id'],
                        ])->column('region_id,id,deleted','region_id');
                        $mapData = array_column($hasData,'region_id');
                        $saveData = [];
                        foreach($regionIds as $region_id){
                            if(in_array($region_id,$mapData))
                            {
                                Db::name('RelationManageRegion')->where('id',$hasData[$region_id]['id'])->update(['deleted' => 0]);
                                unset($hasData[$region_id]);
                            }else{
                                if($region_id){
                                    $saveData[] = [
                                        'region_id' => $region_id,
                                        'manage_id' => $data['id'],
                                    ];
                                }
                            }
                        }
                        //  保存新增的区域信息
                        (new RelationManageRegion())->saveAll($saveData);
                    }
                }
                //处理账号关联资源
                $roleNodes = (new SysRoleNodes())->where('role_id',$data['role_id'])->whereIn('node_id',$data['node_ids'])->field(['node_id','node_type'])->select()->toArray();
                $hasData = Db::name('manageNodes')->where([
                    'manage_id' => $data['id'],
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
                                    'manage_id' => $data['id'],
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
                Db::name('user')
                    ->where('id',$manage_info['user_id'])
                    ->update($user_save);
                unset($data['node_ids']);
                unset($data['hash']);
                $data['role_grade'] = $role_grade;
                Db::name('manage')->where('id',$data['id'])->update($data);
                Cache::delete('user'.md5($manage_info['user_id']));
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
                $code = rand(100, 999);
                Db::name('RelationManageRegion')->where('manage_id',$data['id'])->update(['deleted' => 1]);
                Db::name('ManageNodes')->where('manage_id',$data['id'])->update(['deleted' => 1]);
                Db::name('manage')->where('id',$data['id'])->update(['deleted' => 1,'user_name' => time().$code]);
                Cache::delete('user'.md5($data['id']));
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

    /**
     * 初始化管理人员密码
     * @return Json
     */
    public function resetPassword()
    {
        if ($this->request->isPost()) {
            try {
                //  获取管理人员的关联信息
                $manage_region_ids = (new RelationManageRegion())->where(['manage_id' => $this->result['id']])->where('disabled',0)->column('region_id');
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
                $manage_info = Db::name('manage')
                    ->where('id', $this->result['id'])
                    ->where('deleted', 0)
                    ->find();
                $password = random_code_type();
                Db::name('user')
                    ->where('id',$manage_info['user_id'])
                    ->update([
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'err_num' => 0
                    ]);
                $sms = new SmsSend();
                $msg = "$password 是您在襄阳市干部学历呈报系统的新密码，请尽快登录修改。【顽磁科技】";
                Cache::delete('user'.md5($manage_info['user_id']));
                $res = $sms->send_sms($manage_info['mobile'],$msg);
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
     * 获取角色列表
     * @return Json
     */
    public function getRoleList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if($this->request->has('enabled') && $this->result['enabled'])
                {
                    $where[] = ['disabled','=', 0];
                }
                $data = (new SysRoles())->where($where)->where('role_grade', '<=',$this->userInfo['grade_id'])->select()->toArray();
                foreach ($data as $k => $v){
                    $data[$k]['main'] = 1;
                    if($v['role_grade'] == $this->userInfo['grade_id'] && !$this->userInfo['defaulted']) {
                        $data[$k]['main'] = $this->userInfo['main_account'];
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
     * 获取管理人员角色的权限节点
     * @param node_type 权限节点类型
     * @param role_id   角色ID
     * @return \think\response\Json
     */
    public function getmanageRuleList()
    {
        if ($this->request->isPost()) {
            try {
                $role_name = (new SysRoles())->where('id', $this->userInfo['role_id'])->value('role_name');
                $node_ids = (new SysRoleNodes())->whereIn('role_id', $this->userInfo['role_id'])
                    ->column('node_id');
                $list['id'] = $this->userInfo['manage_id'];
                $list['user_name'] = $this->userInfo['user_name'];
                $list['role_names'] = $role_name;
                $list['nodes'] = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->order('order_num')->select()->toArray();
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
     * 获取管理人员的权限节点
     * @param node_type 权限节点类型
     * @param role_id   角色ID
     * @return \think\response\Json
     */
    public function getmanageNodesList()
    {
        if ($this->request->isPost()) {
            try {
                $node_ids = (new manageNodes())->where([
                    'manage_id' => $this->result['id'],
                ])->column('node_id');
                $list = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->order('order_num')->select()->toArray();
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
     * 保存管理人员的节点信息
     * @param manageid       管理人员ID
     * @param node_type     权限节点类型
     * @nodes               权限节点集合 以,隔开
     * @return \think\response\Json
     */
    public function actmanageRuleSave()
    {
        if ($this->request->isPost()) {
            //  开启事务
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'node_type',
                    'node_ids',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  如果manageid数据不合法，则返回
                $preg = '/^\d+$/u';
                if (preg_match($preg,$postData['id']) == 0){
                    throw new \Exception('管理人员ID应该为数字');
                }
                //  如果node_type数据不合法，则返回
                if (preg_match($preg,$postData['node_type']) == 0){
                    throw new \Exception('节点类型应该为数字');
                }
                if (!empty($data['node_ids'])){
                    $preg = '/^\d+(,\d+)*$/u';
                    if (preg_match($preg,$data['node_ids']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                }
                $node_ids = explode(',', $this->result['node_ids']);
                $node_ids = array_unique($node_ids);
                //  查询所有目前应存在的节点信息
                $hasData = Db::name('manageNodes')->where([
                    'manage_id' => $postData['id'],
                    'node_type' => $postData['node_type']
                ])->column('node_id,id,deleted');
                $mapData = array_column($hasData,'node_id');
                $saveData = [];
                foreach($node_ids as $node_id)
                {
                    if(in_array($node_id,$mapData))
                    {
                        Db::name('manageNodes')->where('id',$hasData[$node_id]['id'])->update(['deleted' => 0]);
                        unset($hasData[$node_id]);
                    }else{
                        if($node_id){
                            $saveData[] = [
                                'node_id' => $node_id,
                                'manage_id' => $postData['id'],
                                'node_type' => $postData['node_type']
                            ];
                        }
                    }
                }
                //  保存新增的节点信息
                (new manageNodes())->saveAll($saveData);
                //  删除多余的节点信息
                Db::name('manageNodes')->whereIn('id', array_column($hasData,'id'))->update([
                    'deleted' => 1
                ]);
                Cache::delete('m'.md5($postData['id']));
                //  操作成功
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('res_success')
                ];
                //  提交事务
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('res_fail')
                ];
                //  事务回滚
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 获取角色相应的区域列表
     * @return Json
     */
    public function getRoleRegion()
    {
        if ($this->request->isPost()) {
            try {
                $defaulted = $this->userInfo['defaulted'];
                if($defaulted){
                    $ids = (new SysRegion())->where('disabled',0)->column('id');
                }else{
                    $ids = array_column($this->userInfo['relation_region'],'region_id');
                }
                $role_grade = (new SysRoles())->where('id', $this->result['role_id'])->value('role_grade');
                if ($role_grade > $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足以进行此操作');
                }
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSQYQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                if($role_grade > $getData['data']){
                    $list = (new SysRegion())->where('disabled',0)->where('parent_id',0)->whereIn('id', $ids)->select()->toArray();
                }else{
                    $list = (new SysRegion())->where('disabled',0)->where('parent_id','>',0)->whereIn('id', $ids)->select()->toArray();
                }
                $data['data'] = $list;
                $data['grade_id'] = $role_grade;
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
                $list = (new SysRegion())->whereIn('id', $ids)
                    ->where('disabled',0)
                    ->field([
                        'id',
                        'parent_id',
                        'region_name'
                    ])
                    ->hidden(['deleted','disabled'])->select()->toArray();
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
     * 获取部门列表
     * @return Json
     */
    public function getDepartmentList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if($this->request->has('region_id') && $this->result['region_id'])
                {
                    $where[] = ['region_id','=', $this->result['region_id']];
                }
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['department_name|department_code','like', '%'.$this->result['keyword'].'%'];
                }
                $data = (new Department())
                    ->where($where)
                    ->where('disabled',0)
                    ->where('deleted',0)
                    ->limit(8)
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
     * 获取管理人员关联的区域信息
     * @return \think\response\Json
     */
    public function getManageRegion()
    {
        if ($this->request->isPost()) {
            try {
                $ids = (new RelationManageRegion())->where([
                    'manage_id' => $this->result['id']
                ])->where('disabled',0)->column('region_id');
                $list['manage'] = (new model())->where('id', $this->result['id'])->field(['id','manage_name'])->find();
                $defaulted = $this->userInfo['defaulted'];
                if($defaulted){
                    $list['range'] = (new SysRegion())
                        ->where('disabled',0)
                        ->column('id');
                }else{
                    $list['range'] = array_column($this->userInfo['relation_region'],'region_id');
                }
                $list['data'] = (new SysRegion())->whereIn('id', $ids)
                    ->where('disabled',0)
                    ->field([
                        'id',
                        'region_name'
                    ])
                    ->hidden(['deleted','disabled'])->select()->toArray();
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
     * 保存管理人员与区域的关系数据
     * @return \think\response\Json
     */
    public function saveManageRegion()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'relate_ids',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                $preg = '/^\d+$/u';
                if (preg_match($preg,$data['id']) == 0){
                    throw new \Exception(Lang::get('check_fail'));
                }
                if (!empty($data['relate_ids'])){
                    $preg = '/^\d+(,\d+)*$/u';
                    if (preg_match($preg,$data['relate_ids']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                }
                //  解析请求的配送中心ID集合数据
                $postIds = explode(',', $data['relate_ids']);
                //  获取所有的已经存在的关系集合（禁用，未禁用）
                $hasIdsArray = (new RelationManageRegion())->where('manage_id', $data['id'])->column('region_id,id,disabled');
                //  解析出部门id
                $hasIds = array_column($hasIdsArray, 'region_id');
                //  定义需要新增的数据
                $saveData = [];
                //  循环判断请求的数据是否存在
                foreach ($postIds as $id) {
                    //  如果存在已经有的数据
                    if (in_array($id, $hasIds)) {
                        //  如果数据存在，更新成未禁用状态
                        Db::name('RelationManageRegion')->where('id',$hasIdsArray[$id]['id'])->update(['disabled' => 0]);
                        //  从已经存在的数据中剔除
                        unset($hasIdsArray[$id]);
                    } else {
                        //  如果$id不为空
                        if ($id) {
                            //  增加到需要新增的数据中
                            $saveData[] = [
                                'manage_id' => $data['id'],
                                'region_id' => $id
                            ];
                        }
                    }
                }
                //  保存所有需要新增的数据
                Db::name('RelationManageRegion')->insertAll($saveData);
                //  未剔除完的数据，则禁用
                Db::name('RelationManageRegion')->whereIn('id', array_column($hasIdsArray, 'id'))->update([
                    'disabled' => 1
                ]);
                Cache::delete('m'.md5($data['id']));
                //  操作成功
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
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 导入管理人员信息
     */
    public function import()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $file_size = $_FILES['file']['size'];
                if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                    throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
                }
                //限制上传表格类型
                $fileExtendName = substr(strrchr($_FILES['file']["name"], '.'), 1);

                if ($fileExtendName != 'csv' && $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    if ($fileExtendName == 'csv') {
                        $objReader = IOFactory::createReader('Csv');
                    } elseif ($fileExtendName == 'xls') {
                        $objReader = IOFactory::createReader('Xls');
                    } else {
                        $objReader = IOFactory::createReader('Xlsx');
                    }

                    $filename = $_FILES['file']['tmp_name'];
                    $objPHPExcel = $objReader->load($filename);
                    $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                    $highestRow = $sheet->getHighestRow();       // 取得总行数
                    //$highestColumn = $sheet->getHighestColumn();   // 取得总列数
                }else{
                    throw new \Exception('获取上传文件失败');
                }
                //定义$data，循环表格的时候，找出已存在的管理人员。
                $data = [];
                $repeat = [];
                $pinyin = new Pinyin();
                //循环读取excel表格，整合成数组。
                $hasData = (new model())->field('manage_name,idcard')->select()->toArray();
                $role_id = (new SysRoles())->where(['disabled' => 0,'defaulted' => 1])->value('id');
                for ($j = 2; $j <= $highestRow; $j++) {
                    $tmp[$j - 2] = [
                        'manage_name' => $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue(),
                        'real_name' => $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue(),
                        'simple_code' => $pinyin->abbr($objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue()),
                        'idcard' => $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue(),
                        'mobile' => $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getFormattedValue(),
                        'region_id' => $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue(),
                        'center_id' => $objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue(),
                        'supermarket_id' => $objPHPExcel->getActiveSheet()->getCell("G" . $j)->getValue(),
                        'password' => password_hash('123456', PASSWORD_DEFAULT),
                        'role_ids' => $role_id
                    ];
                    if (!in_array($tmp[$j - 2]['manage_name'], array_column($hasData, 'manage_name')) &&
                        !in_array($tmp[$j - 2]['idcard'], array_column($hasData, 'idcard'))
                    ) {
                        array_push($data, $tmp[$j - 2]);
                    } else {
                        array_push($repeat, $tmp[$j - 2]['manage_name']);
                    }
                }
                // 过滤上传数据中重复的管理人员名/编号和身份证数据
                $tmp = $data;
                $data = array_unique_value($data, 'manage_name');
                $data = array_unique_value($data, 'idcard');
                $repeat = array_merge($repeat,array_values(array_diff_assoc(array_column($tmp, 'manage_name'), array_column($data, 'manage_name'))));
                $successNum = 0;
                //获取默认权限信息
                $node_ids = (new SysNodes())->where('defaulted',1)->field(['id','node_type'])->select()->toArray();
                foreach ($data as $manage) {
                    $manage_id = Db::name('manage')->insertGetId($manage);
                    //自动添加关联配送中心信息
                    if ($manage['center_id']){
                        Db::name('RelationmanageCenter')->insert([
                            'manage_id' => $manage_id,
                            'center_id' => $manage['center_id']
                        ]);
                    }
                    //自动添加关联门店信息
                    if ($manage['supermarket_id']){
                        Db::name('RelationmanageSupermarket')->insert([
                            'manage_id' => $manage_id,
                            'supermarket_id' => $manage['supermarket_id']
                        ]);
                    }
                    $saveData = [];
                    if ($node_ids){
                        foreach($node_ids as $key => $value){
                            $saveData[] = [
                                'manage_id' => $manage_id,
                                'node_id' => $value['id'],
                                'node_type' => $value['node_type']
                            ];
                        }
                        Db::name('manageNodes')->insertAll($saveData);
                    }
                    $successNum +=1;
                }
                $res = [
                    'code' => 1,
                    'count' => $successNum,
                    'repeat' => $repeat
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