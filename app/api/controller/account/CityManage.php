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
use app\common\model\SysRegion;
use app\common\model\SysNodes;
use app\common\model\ManageNodes;
use app\common\model\SysRoles;
use app\common\model\SysRoleNodes;
use app\common\model\User;
use app\common\validate\account\CityManage as validate;
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
class CityManage extends Education
{
    private $role_id;
    private $main;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $dictionary = new FilterData();
        $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSSJBM');
        $this->role_id = $getData['data'];
        $this->main = 0;
        if($this->city_grade < $this->userInfo['grade_id'] || $this->userInfo['main_account']){
            $this->main = 1;
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
                $data = $this->getManageList($where,$this->city_grade);
                $res_data = parent::getResources($this->userInfo,$this->request->controller());
                $res_data['main_auth'] = [
                    'status_name' => '负责人权限',
                    'main_account' => $this->main
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
                $postData = $this->request->only([
                    'id',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $postData['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = (new model())->find($postData['id'])->hidden(['deleted','password']);
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
                    'mobile',
                    'main_account',
                    'disabled',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'add');
                if($checkData['code'] == 0){
                   throw new \Exception($checkData['msg']);
                }
                if($this->city_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足不能进行此操作');
                }
                $checkManageData['user_name'] = $postData['user_name'];
                $checkManageData['role_id'] = $this->role_id;
                $checkManageError = $this->checkManage($checkManageData);
                if($checkManageError['code'] == 0){
                    throw new \Exception($checkManageError['msg']);
                }
                //  编译密码
                if($postData['password']){
                    $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                    if (preg_match($preg,$this->result['password']) == 0){
                        throw new \Exception('密码应包含数字、字母和特殊字符!');
                    }
                }
                $password = $postData['password'];
                if($password){
                    $postData['refresh_pass'] = 1;
                }
                $postData = $this->getAddPasswordData($postData);
                $postData['user_type'] = 1;
                $mainAccount = $postData['main_account'];
                $disabled = $postData['disabled'];
                unset($postData['hash']);
                unset($postData['main_account']);
                unset($postData['disabled']);

                $region = Cache::get('region');
                $regionData = filter_value_one($region, 'grade_id', $this->city_grade);
                if (count($regionData) > 0) {
                    $postData['region_id'] = $regionData['id'];
                }else{
                    throw new \Exception('行政区域配置错误');
                }
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
                $department = Cache::get('department');
                $departmentData = filter_value_one($department, 'grade_id', $this->city_grade);
                if (count($departmentData) > 0) {
                    $department_id = $departmentData['id'];
                }else{
                    throw new \Exception('部门权限配置错误');
                }
                $manageData['user_id'] = $user_id;
                $manageData['user_name'] = $postData['user_name'];
                $manageData['real_name'] = $postData['real_name'];
                $manageData['simple_code'] = $postData['simple_code'];
                $manageData['role_id'] = $this->role_id;
                $manageData['region_id'] = $postData['region_id'];
                $manageData['role_grade'] = $this->city_grade;
                $manageData['main_account'] = $mainAccount;
                $manageData['disabled'] = $disabled;
                $manageData['department_id'] = $department_id;
                $manageData['mobile'] = $postData['mobile'];

                $manageData['public_school_status'] = 1;
                $manageData['civil_school_status'] = 1;
                $manageData['primary_school_status'] = 1;
                $manageData['junior_middle_school_status'] = 1;

                //$manage_id = Db::name('manage')->insertGetId($manageData);
                $manage_insert_data = (new model())->addData($manageData,1);
                if($manage_insert_data['code'] == 1) {
                    //市级账号拥有所有区域
                    $resAdd = $this->addRelationRegion(0,$manage_insert_data['insert_id']);
                    if(!$resAdd){
                        throw new \Exception('关联区域添加失败');
                    }
                }else{
                    throw new \Exception('添加失败');
                }

                //自动添加默认权限信息
                $this->addSysNodes($this->role_id,$manage_insert_data['insert_id']);

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
                    'password',
                    'real_name',
                    'main_account',
                    'disabled',
                    'mobile',
                    'hash',
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                $checkData = parent::checkValidate($postData, validate::class, 'edit');
                if($checkData['code'] == 0){
                    throw new \Exception($checkData['msg']);
                }
                if ($this->city_grade > $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足以进行此操作');
                }
                if($postData['password']){
                    $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                    if (preg_match($preg,$this->result['password']) == 0){
                        throw new \Exception('密码应包含数字、字母和特殊字符!');
                    }
                    $postData['refresh_pass'] = 1;
                }
                $id = $postData['id'];
                $mainAccount = $postData['main_account'];
                $disabled = $postData['disabled'];
                unset($postData['id']);
                unset($postData['main_account']);
                unset($postData['disabled']);
                unset($postData['hash']);

                $checkManageData['user_name'] = $postData['user_name'];
                $checkManageData['role_id'] = $this->role_id;
                $checkManageError = $this->checkManage($checkManageData,$id);
                if($checkManageError['code'] == 0){
                    throw new \Exception($checkManageError['msg']);
                }
                $checkUserError = $this->checkUser($checkManageError['data']['user_id'],$postData['user_name']);
                if($checkUserError['code'] == 0){
                    throw new \Exception($checkUserError['msg']);
                }
                $userInfo = $checkUserError['data'];

                //  编译密码
                $postData = $this->getUpdatePasswordData($postData);
                $postData['id'] = $checkManageError['data']['user_id'];
                $postData['err_num'] = 0;

                $user_update = (new User())->editData($postData);
                if($user_update['code'] == 0){
                    throw new \Exception($user_update['msg']);
                }

                //更新manage表
                $manageData['id'] = $id;
                $manageData['user_name'] = $postData['user_name'];
                $manageData['real_name'] = $postData['real_name'];
                $manageData['main_account'] = $mainAccount;
                $manageData['mobile'] = $postData['mobile'];
                if($this->userInfo['main_account'] || !$mainAccount){
                    $manageData['disabled'] = $disabled;
                }
                /*$manageData['public_school_status'] = $mainAccount == 1 ?  1 : 0;
                $manageData['civil_school_status'] = $mainAccount == 1 ?  1 : 0;
                $manageData['primary_school_status'] = $mainAccount == 1 ?  1 : 0;
                $manageData['junior_middle_school_status'] = $mainAccount == 1 ?  1 : 0;*/

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
                //删除user缓存
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
            }
            Db::rollback();
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
                if ((!$chkeck_region || $this->city_grade >= $this->userInfo['grade_id']) &&  !$this->userInfo['defaulted']) {
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
     * 获取管理人员资源
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            $res = [
                'code' => 1,
                'data' => '',
            ];
            return parent::ajaxReturn($res);
        }
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
            if($this->city_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
            if($this->city_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
            if($this->city_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
            if($this->city_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
            if($this->city_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
}