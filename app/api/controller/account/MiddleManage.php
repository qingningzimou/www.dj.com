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
use app\common\model\User;
use app\common\validate\basic\MiddleManage as validate;
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
class MiddleManage extends Education
{
    private $role_id;
    private $main;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $dictionary = new FilterData();
        $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSJGHBM');
        $this->role_id = $getData['data'];
        $this->main = 0;
        if($this->middle_grade < $this->userInfo['grade_id'] || $this->userInfo['main_account']){
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
                if($this->request->has('region_id') && $this->result['region_id'])
                {
                    $where[] = ['region_id','=',$this->result['region_id']];
                }
                if($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['user_name|real_name|simple_code', 'like', '%' . $this->result['keyword'] . '%'];
                }
                $data = $this->getManageList($where,$this->middle_grade,$this->userInfo['central_id']);
                $central = Cache::get('central');
                foreach($data['data'] as $key=>&$value){
                    $value['central_name'] = '';
                    if (!empty($value['central_id'])){
                        $centralData = filter_value_one($central, 'id', $value['central_id']);
                        if (count($centralData) > 0) {
                            $value['central_name'] = $centralData['central_name'];
                        }
                    }
                }
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
                    'central_id',
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
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if($this->middle_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
                    $postData['refresh_pass'] = 1;
                }
                $password = $postData['password'];
                $postData = $this->getAddPasswordData($postData);
                $postData['user_type'] = 1;
                $central_id = $postData['central_id'];
                $central = $this->getCentral($central_id);
                if(!$central){
                    throw new \Exception('教管会数据错误');
                }
                $region_id = $central['region_id'];
                $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                if(!in_array($region_id,$region_ids)){
                    throw new \Exception('区域不符不能进行此操作');
                }
                $mainAccount = $postData['main_account'];
                $disabled = $postData['disabled'];
                unset($postData['main_account']);
                unset($postData['central_id']);
                unset($postData['disabled']);
                unset($postData['hash']);
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
                $manageData['role_grade'] = $this->middle_grade;
                $manageData['main_account'] = $mainAccount;
                $manageData['disabled'] = $disabled;
                $manageData['region_id'] = $region_id;
                $manageData['central_id'] = $central_id;

                $manageData['public_school_status'] = 1;
                $manageData['civil_school_status'] = 1;
                $manageData['primary_school_status'] = 1;
                $manageData['junior_middle_school_status'] = 1;

                $manage_insert_data = (new model())->addData($manageData,1);
                if($manage_insert_data['code'] == 1) {
                    $resAdd = $this->addRelationRegion($region_id,$manage_insert_data['insert_id']);
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
                    'mobile',
                    'main_account',
                    'central_id',
                    'disabled',
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
                if ($this->middle_grade > $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
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
                $central_id = $postData['central_id'];
                $central = $this->getCentral($central_id);
                if(!count($central)){
                    throw new \Exception('教管会数据错误');
                }
                $region_id = $central['region_id'];
                $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                if(!in_array($region_id,$region_ids)){
                    throw new \Exception('区域不符不能进行此操作');
                }
                $mainAccount = $postData['main_account'];
                $disabled = $postData['disabled'];
                unset($postData['id']);
                unset($postData['main_account']);
                unset($postData['central_id']);
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
                $manageData['mobile'] = $postData['mobile'];
                $manageData['region_id'] = $region_id;
                $manageData['central_id'] = $central_id;
                $manageData['main_account'] = $mainAccount;
                if($this->userInfo['main_account'] || !$mainAccount){
                    $manageData['disabled'] = $disabled;
                }
                if($checkManageError['data']['region_id'] != $region_id){
                    Db::name('RelationManageRegion')->insert([
                        'manage_id' => $id,
                        'region_id' => $region_id
                    ]);
                    Db::name('RelationManageRegion')->where('manage_id',$id)->where('region_id','<>',$region_id)->update(['deleted' => 1]);
                }
                //Db::name('manage')->where('id',$id)->update($manageData);
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
                if ((!$chkeck_region || $this->middle_grade >= $this->userInfo['grade_id']) &&  !$this->userInfo['defaulted']) {
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
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 管理员页面资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            $centralData = Cache::get('central');
            if (($this->city_grade > $this->userInfo['grade_id']) &&  !$this->userInfo['defaulted']) {
                $centralData = filter_by_value($centralData, 'region_id', $this->userInfo['region_id']);
            }
            $data['central'] = $centralData;
            $region = Cache::get('region');
            $data['region'] = filter_by_value($region, 'grade_id', $this->area_grade);
            $res = [
                'code' => 1,
                'data' => $data,
            ];
            return parent::ajaxReturn($res);
        }
    }
    public function publicState(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);;
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
            ]);;
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
            ]);;
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
            ]);;
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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
            if($this->middle_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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