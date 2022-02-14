<?php
/**
 * Created by PhpStorm.
 * manage: aiwes
 * Date: 2020/4/25
 * Time: 11:10
 */
namespace app\api\controller\account;

use app\api\controller\system\Region;
use app\common\controller\Education;
use app\common\model\Manage as model;
use app\common\model\RelationManageRegion;
use app\common\model\Department;
use app\common\model\Schools;
use app\common\model\SysNodes;
use app\common\model\ManageNodes;
use app\common\model\SysRoles;
use app\common\model\SysRoleNodes;
use app\common\model\User;
use app\common\validate\account\SchoolManage as validate;
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
class SchoolManage extends Education
{
    private $public_role_id;
    private $civil_role_id;
    private $main;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $dictionary = new FilterData();
        $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSGBXXBM');
        $this->public_role_id = $getData['data'];
        $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSMBXXBM');
        $this->civil_role_id = $getData['data'];

        $this->main = 0;
        if($this->school_grade < $this->userInfo['grade_id'] || $this->userInfo['main_account']){
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
                $schoolTypeIds = [];
                $schoolAttrIds = [];
                $school =  (new Schools())->select()->toArray();;
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['region_id','=',$this->result['region_id']];
                }
                if($this->request->has('school_id') && $this->result['school_id'] > 0)
                {
                    $where[] = ['school_id','=',$this->result['school_id']];
                }
                if($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['user_name|real_name|simple_code', 'like', '%' . $this->result['keyword'] . '%'];
                }
                //公办、民办、小学、初中权限
                $public_school = 0;
                $civil_school = 0;
                $middle_school = 0;
                $primary_school = 0;
                $central_id = 0;
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSXXXZ', 'SYSXXXZGB');
                if($getData['code']){
                    $public_school = $getData['data'];
                }
                $getData = $dictionary->findValue('dictionary', 'SYSXXXZ', 'SYSXXXZMB');
                if($getData['code']){
                    $civil_school = $getData['data'];
                }
                $getData = $dictionary->findValue('dictionary', 'SYSXXLX', 'SYSXXLXXX');
                if($getData['code']){
                    $primary_school = $getData['data'];
                }
                $getData = $dictionary->findValue('dictionary', 'SYSXXLX', 'SYSXXLXCZ');
                if($getData['code']){
                    $middle_school = $getData['data'];
                }
                if($this->userInfo['public_school_status'] == 1){
                    $schoolData = filter_by_value($school, 'school_attr', $public_school);
                    $schoolAttrIds = array_column($schoolData,'id');
                }
                if($this->userInfo['civil_school_status'] == 1){
                    $schoolData = filter_by_value($school, 'school_attr', $civil_school);
                    $schoolAttrIds = array_merge($schoolAttrIds,array_column($schoolData,'id'));
                }
                $schoolAttrIds = array_unique($schoolAttrIds);
                if($this->userInfo['primary_school_status'] == 1){
                    $schoolData = filter_by_value($school, 'school_type', $primary_school);
                    $schoolTypeIds = array_merge($schoolTypeIds,array_column($schoolData,'id'));
                }
                if($this->userInfo['junior_middle_school_status'] == 1){
                    $schoolData = filter_by_value($school, 'school_type', $middle_school);
                    $schoolTypeIds = array_merge($schoolTypeIds,array_column($schoolData,'id'));
                }
                $schoolTypeIds = array_unique($schoolTypeIds);
                $schoolIds = array_intersect($schoolAttrIds,$schoolTypeIds);
                if($this->request->has('school_attr') && $this->result['school_attr'])
                {
                    $schoolData = filter_by_value($school, 'school_attr', $this->result['school_attr']);
                    $schoolIds = array_intersect($schoolIds,array_column($schoolData,'id'));
                }
                if($this->request->has('school_type') && $this->result['school_type'])
                {
                    $schoolData = filter_by_value($school, 'school_type', $this->result['school_type']);
                    if(count($schoolIds)){
                        $schoolIds = array_intersect($schoolIds,array_column($schoolData,'id'));
                    }
                }
                // 市级权限以下不显示市直
                if($this->city_grade > $this->userInfo['grade_id']){
                    $schoolData = filter_by_value($school, 'directly', 0);
                    if(count($schoolIds)){
                        $schoolIds = array_intersect($schoolIds,array_column($schoolData,'id'));
                    }else{
                        $schoolIds = array_column($schoolData,'id');
                    }
                }
                // 教管会权限仅显示辖区学校
                if($this->middle_grade == $this->userInfo['grade_id'] && $this->userInfo['central_id']){
                    $central_id = $this->userInfo['central_id'];
                }
                if(count($schoolIds)){
                    $where[] = ['school_id','in',$schoolIds];
                }
                $data = $this->getManageList($where,$this->school_grade,$central_id);
                $res_data = parent::getResources($this->userInfo,$this->request->controller());
                $res_data['main_auth'] = [
                    'status_name' => '负责人权限',
                    'main_account' => $this->main
                ];
                $data['resources'] = $res_data;
                $dictionary = new FilterData();
                foreach($data['data'] as $key=>&$value){
                    $value['school_name'] = '';
                    $value['school_type'] = '';
                    $value['school_attr'] = '';
                    if (!empty($value['school_id'])){
                        $schoolData = filter_value_one($school, 'id', $value['school_id']);
                        if (count($schoolData) > 0) {
                            $value['school_name'] = $schoolData['school_name'];
                            $getData = $dictionary->findName('dictionary', 'SYSXXLX', $schoolData['school_type']);
                            if($getData['code']){
                                $value['school_type'] = $getData['data'];
                            }
                            $getData = $dictionary->findName('dictionary', 'SYSXXXZ', $schoolData['school_attr']);
                            if($getData['code']){
                                $value['school_attr'] = $getData['data'];
                            }
                        }
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

    //查找学校信息
    public function getSchool()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $limit = 100;
                $region_id = 0;
                if($this->request->has('region_id') && $this->result['region_id'])
                {
                    $region_id = $this->result['region_id'];
                    $where[] = ['region_id','=',$this->result['region_id']];
                }else{
                    $limit = 20;
                }
                if($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['school_name|simple_code', 'like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->city_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted'] && !$region_id){
                    throw new \Exception('当前用户区县数据错误');
                }
                // 市级权限以下不显示市直
                if($this->city_grade > $this->userInfo['grade_id']){
                    $where[] = ['directly','=',0];
                }
                // 教管会权限仅显示辖区学校
                if($this->middle_grade == $this->userInfo['grade_id'] && $this->userInfo['central_id']){
                    $where[] = ['central_id','=',$this->userInfo['central_id']];
                }
                $school_where = $this->getSchoolWhere();
                $where[] = $school_where['school_attr'];
                $where[] = $school_where['school_type'];
                $data = (new Schools())
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
                $data = (new model())
                    ->find($postData['id'])
                    ->hidden(['deleted','password']);
                $school = Cache::get('school');
                $data['school_name'] = '';
                if (!empty($data['school_id'])){
                    $schoolData = filter_value_one($school, 'id', $data['school_id']);
                    if (count($schoolData) > 0) {
                        $data['school_name'] = $schoolData['school_name'];
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
                    'region_id',
                    'school_id',
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
                $checkData = parent::checkValidate($postData, validate::class, 'add');
                if($checkData['code'] == 0){
                    throw new \Exception($checkData['msg']);
                }
                if($postData['password']){
                    $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                    if (preg_match($preg,$postData['password']) == 0){
                        throw new \Exception('密码应包含数字、字母和特殊字符!');
                    }
                    $postData['refresh_pass'] = 1;
                }
                if($this->school_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                    throw new \Exception('权限不足不能进行此操作');
                }
                $schoolData = Db::name('SysSchool')->where('id',$postData['school_id'])->where('deleted',0)->find();
                if(empty($schoolData)){
                    throw new \Exception('学校数据错误');
                }
                $school_type = $schoolData['school_type'];
                $school_attr = $schoolData['school_attr'];
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSXXLX', 'SYSXXLXXX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $primary_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSXXLX', 'SYSXXLXCZ');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $middle_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSXXXZ', 'SYSXXXZGB');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $public_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSXXXZ', 'SYSXXXZMB');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $civil_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSGLZY', 'SYSCZGLZY');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $filter_school = $getData['data'];
                if($school_attr == $civil_school){
                    $role_id = $this->civil_role_id;
                }else{
                    $role_id = $this->public_role_id;
                }
                if($school_type == $middle_school){
                    $chk_nodes = 1;
                }else{
                    $chk_nodes = 0;
                }
                $checkManageData['user_name'] = $postData['user_name'];
                $checkManageData['role_id'] = $role_id;
                $checkManageError = $this->checkManage($checkManageData);
                if($checkManageError['code'] == 0){
                    throw new \Exception($checkManageError['msg']);
                }
                //  编译密码
                $password = $postData['password'];
                $postData = $this->getAddPasswordData($postData);
                $postData['user_type'] = 1;
                $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                if(!in_array($postData['region_id'],$region_ids)){
                    throw new \Exception('区域不符不能进行此操作');
                }
                $school_id = $postData['school_id'];
                $mainAccount = $postData['main_account'];
                $disabled = $postData['disabled'];
                unset($postData['school_id']);
                unset($postData['main_account']);
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
                $manageData['role_grade'] = $this->school_grade;
                $manageData['role_id'] = $role_id;
                $manageData['region_id'] = $postData['region_id'];
                $manageData['school_id'] = $school_id;
                $manageData['central_id'] = $this->userInfo['central_id'];
                $manageData['main_account'] = $mainAccount;
                $manageData['disabled'] = $disabled;
                //对应权限
                if($schoolData['school_attr'] == $public_school){
                    $manageData['public_school_status'] = 1;
                }
                if($schoolData['school_attr'] == $civil_school){
                    $manageData['civil_school_status'] = 1;
                }
                if($schoolData['school_type'] == $primary_school){
                    $manageData['primary_school_status'] = 1;
                }
                if($schoolData['school_type'] == $middle_school){
                    $manageData['junior_middle_school_status'] = 1;
                }
                $manage_insert_data = (new model())->addData($manageData,1);
                if($manage_insert_data['code'] == 1) {
                    $resAdd = $this->addRelationRegion($postData['region_id'],$manage_insert_data['insert_id']);
                    if(!$resAdd){
                        throw new \Exception('关联区域添加失败');
                    }
                }else{
                    throw new \Exception('添加失败');
                }

                //自动添加默认权限信息
                if($chk_nodes){
                    $not_node_ids = (new SysNodes())
                        ->where(function ($query){
                            $query->where('authority', 0)
                                ->where('displayed', 0);
                        })
                        ->whereOr('controller_name', $filter_school)
                        ->column('id');
                    $roleNode = (new SysRoleNodes())->where('role_id', $role_id)
                        ->whereNotIn('node_id',$not_node_ids)
                        ->column(['node_id','node_type']);
                }else{
                    $not_node_ids = (new SysNodes())
                        ->where('authority', 0)
                        ->where('displayed', 0)
                        ->column('id');
                    $roleNode = (new SysRoleNodes())->where('role_id', $role_id)
                        ->whereNotIn('node_id',$not_node_ids)
                        ->column(['node_id','node_type']);
                }
                $saveData = [];
                foreach($roleNode as $key => $value){
                    $saveData[] = [
                        'manage_id' => $manage_insert_data['insert_id'],
                        'node_id' => $value['node_id'],
                        'node_type' => $value['node_type'],
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
                $checkData = parent::checkValidate($postData, validate::class, 'edit');
                if($checkData['code'] == 0){
                    throw new \Exception($checkData['msg']);
                }
                if ($this->school_grade > $this->userInfo['grade_id'] && !$this->userInfo['defaulted']){
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
                $manage_data = Db::name('manage')->where('id',$id)->where('deleted',0)->find();
                if(empty($manage_data)){
                    throw new \Exception('管理员数据错误');
                }
                $region_id = $manage_data['region_id'];
                $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                if(!in_array($region_id,$region_ids)){
                    throw new \Exception('区域不符不能进行此操作');
                }
                $checkUserError = $this->checkUser($manage_data['user_id'],$postData['user_name']);
                if($checkUserError['code'] == 0){
                    throw new \Exception($checkUserError['msg']);
                }
                //  编译密码
                $postData = $this->getUpdatePasswordData($postData);
                $postData['id'] = $manage_data['user_id'];
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
                $manageData['main_account'] = $mainAccount;
                if($this->userInfo['main_account'] || !$mainAccount){
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
                //删除user缓存
                Cache::delete('user'.md5($manage_data['user_id']));
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
                if ((!$chkeck_region || $this->school_grade >= $this->userInfo['grade_id']) &&  !$this->userInfo['defaulted']) {
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
     * 获取资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            try {
                $dictionary = new FilterData();
                $region = Cache::get('region');
                $data['region'] = filter_by_value($region, 'grade_id', $this->area_grade);
                $data['region_id'] = $this->userInfo['region_id'];
                $data['school_type'] = [];
                if($this->userInfo['primary_school_status']){
                    $getData = $dictionary->findAll('dictionary', 'SYSXXLX','SYSXXLXXX');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $data['school_type'][] =[
                        'id' => $getData['data']['dictionary_value'],
                        'type_name' => $getData['data']['dictionary_name']
                    ];
                }
                if($this->userInfo['junior_middle_school_status']){
                    $getData = $dictionary->findAll('dictionary', 'SYSXXLX','SYSXXLXCZ');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $data['school_type'][] =[
                        'id' => $getData['data']['dictionary_value'],
                        'type_name' => $getData['data']['dictionary_name']
                    ];
                }
                if($this->userInfo['public_school_status']){
                    $getData = $dictionary->findAll('dictionary', 'SYSXXXZ','SYSXXXZGB');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $data['school_attr'][] =[
                        'id' => $getData['data']['dictionary_value'],
                        'attr_name' => $getData['data']['dictionary_name']
                    ];
                }
                if($this->userInfo['civil_school_status']){
                    $getData = $dictionary->findAll('dictionary', 'SYSXXXZ','SYSXXXZMB');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $data['school_attr'][] =[
                        'id' => $getData['data']['dictionary_value'],
                        'attr_name' => $getData['data']['dictionary_name']
                    ];
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
     * 导入管理人员信息
     */
    public function importData()
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

                if (/*$fileExtendName != 'csv' &&*/ $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    /*if ($fileExtendName == 'csv') {
                        $objReader = IOFactory::createReader('Csv');
                    } else*/if ($fileExtendName == 'xls') {
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
                //导入模板检测
                $check_res = $this->checkTemplate($objPHPExcel);
                if($check_res['code'] == 0){
                    throw new \Exception($check_res['msg']);
                }

                //定义$data，循环表格的时候，找出已存在的管理人员。
                $data = [];
                $error = [];
                $account = 0;
                $pinyin = new Pinyin();
                $region = Cache::get('region');
                $schools = (new Schools())->where('disabled',0)->select()->toArray();
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSXXLX', 'SYSXXLXXX');
                if(!$getData['code']){
                    throw new \Exception('学校类型数据字典错误');
                }
                $primary_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSXXLX', 'SYSXXLXCZ');
                if(!$getData['code']){
                    throw new \Exception('学校类型数据字典错误');
                }
                $middle_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSXXXZ', 'SYSXXXZGB');
                if(!$getData['code']){
                    throw new \Exception('学校性质数据字典错误');
                }
                $public_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSXXXZ', 'SYSXXXZMB');
                if(!$getData['code']){
                    throw new \Exception('学校性质数据字典错误');
                }
                $civil_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSXXZSFS', 'SYSZSFSXS');
                if(!$getData['code']){
                    throw new \Exception('招生方式数据字典错误');
                }
                $online_mode = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSXXZSFS', 'SYSZSFSXX');
                if(!$getData['code']){
                    throw new \Exception('招生方式数据字典错误');
                }
                $down_mode = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSGLZY', 'SYSCZGLZY');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $filter_school = $getData['data'];
                //循环读取excel表格，整合成数组。
                for ($j = 2; $j <= $highestRow; $j++) {
                    $region_id = 0;
                    $school_id = 0;
                    $central_id = 0;
                    $directly = 0;
                    $school_type = 0;
                    $school_attr = 0;
                    $enroll_mode = 0;
                    $chk_err = 0;
                    $regionData = filter_value_one($region, 'region_name', trim($objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue()));
                    if (count($regionData) > 0) {
                        $region_id = $regionData['id'];
                    }
                    $getData = $dictionary->findNameValue('dictionary', 'SYSXXLX', trim($objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue()));
                    if($getData['code']){
                        $school_type = $getData['data'];
                    }
                    $getData = $dictionary->findNameValue('dictionary', 'SYSXXXZ', trim($objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue()));
                    if($getData['code']){
                        $school_attr = $getData['data'];
                    }
                    $getData = $dictionary->findNameValue('dictionary', 'SYSXXZSFS', trim($objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue()));
                    if($getData['code']){
                        $enroll_mode = $getData['data'];
                    }
                    $schoolData = filter_value_one($schools, 'school_name', trim($objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue()));
                    if (count($schoolData) > 0) {
                        $school_id = $schoolData['id'];
                        $central_id = $schoolData['central_id'];
                        $directly = $schoolData['directly'];
                        $region_id = $schoolData['region_id'];
                        $school_type = $schoolData['school_type'];
                        $school_attr = $schoolData['school_attr'];
                        if($schoolData['onlined']){
                            $enroll_mode = $online_mode;
                        }else{
                            $enroll_mode = $down_mode;
                        }
                    }
                    $tmp[$j - 2] = [
                        'school_name' => trim($objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue()),
                        'region_id' => $region_id,
                        'school_id' => $school_id,
                        'central_id' => $central_id,
                        'directly' => $directly,
                        'school_type' => $school_type,
                        'school_attr' => $school_attr,
                        'enroll_mode' => $enroll_mode,
                        'real_name' => trim($objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue()),
                        'simple_code' => $pinyin->abbr(trim($objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue())),
                        'user_name' => trim($objPHPExcel->getActiveSheet()->getCell("G" . $j)->getFormattedValue()),
                        'role_id' => 0,
                        'regulated' => 0,
                     ];
                    $chkManageData = Db::name('manage')
                        ->where('user_name', $tmp[$j - 2]['user_name'])
                        ->where('deleted', 0)
                        ->select()->toArray();
                    if (count($chkManageData) > 0) {
                        foreach ($chkManageData as $value){
                            if($value['role_id'] ==  $this->civil_role_id || $value['role_id'] ==  $this->public_role_id) {
                                $chk_err = 1;
                                $account += 1;
//                                $error[] = '第'.$j.'行:已有重复的登录账号存在';
                            }
                        }
                    }
                    if($school_attr == $civil_school){
                        $tmp[$j - 2]['regulated'] = 1;
                        $tmp[$j - 2]['role_id'] = $this->civil_role_id;
                    }else{
                        $tmp[$j - 2]['role_id'] = $this->public_role_id;
                    }
                    if($tmp[$j - 2]['school_name'] == '' && !$region_id && !$school_type && !$school_attr && !$enroll_mode && $tmp[$j - 2]['real_name'] && $tmp[$j - 2]['user_name'] = ''){
                        continue;
                    }
                    if (empty($tmp[$j - 2]['school_name'])){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:学校名称不能为空';
                    }
                    if (!$region_id){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:系统中不存在此区县';
                    }
                    if ($region_id != $this->userInfo['region_id'] && $this->city_grade > $this->userInfo['grade_id'] ){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:不可导入其它区县数据';
                    }
                    if (!$school_type){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:系统中不存在此学校类型';
                    }
                    if (!$school_attr){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:系统中不存在此学校性质';
                    }
                    if (!$enroll_mode){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:系统中不存在此招生方式';
                    }
                    if ($tmp[$j - 2]['school_attr'] == $public_school && !$this->userInfo['public_school_status']){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:账号没有公办学校权限';
                    }
                    if ($tmp[$j - 2]['school_attr'] == $civil_school && !$this->userInfo['civil_school_status']){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:账号没有民办学校权限';
                    }
                    if ($tmp[$j - 2]['school_type'] == $primary_school && !$this->userInfo['primary_school_status']){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:账号没有小学管理权限';
                    }
                    if ($tmp[$j - 2]['school_type'] == $middle_school && !$this->userInfo['junior_middle_school_status']){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:账号没有民办学校权限';
                    }
                    if ($tmp[$j - 2]['directly'] && $this->city_grade > $this->userInfo['grade_id']){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:没有此学校的管理权限';
                    }
                    if ($tmp[$j - 2]['school_id'] && $this->middle_grade == $this->userInfo['grade_id'] && $tmp[$j - 2]['central_id'] != $this->userInfo['central_id']){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:没有此学校的管辖权';
                    }
                    $preg = '/^1[3-9][0-9]\d{8}$/u';
                    if (preg_match($preg,$tmp[$j - 2]['user_name']) == 0){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:登录账号不是手机号码';
                    }
                    if (empty($tmp[$j - 2]['real_name'])){
                        $chk_err = 1;
                        $error[] = '第'.$j.'行:管理员姓名不能为空';
                    }
                    if(!$chk_err){
                        array_push($data, $tmp[$j - 2]);
                    }
                }
                // 过滤上传数据中重复的管理员名称
                $user_name_data = array_column($data,'user_name');
                $user_name_data = array_filter($user_name_data);
                $repeat_user_name = array_count_values($user_name_data);
                foreach ($data as $key => $value) {
                    if($repeat_user_name[$value['user_name']] > 1) {
                        $error[] = '用户名为[' . $value['user_name'] . ']的数据重复';
                        unset($data[$key]);
                    }
                }
                if($account){
                    $error[] = '有'.$account.'项已存在数据被忽略';
                }
                $successNum = 0;
                foreach ($data as $key => $value) {
                    $school_id = $value['school_id'];
                    if(!$value['school_id']){
                        //导入已经新增的学校 不再新增
                        $schoolInfo = Db::name('SysSchool')->where('school_name', $value['school_name'])->where('deleted', 0)->find();
                        if(!$schoolInfo) {
                            if ($value['enroll_mode'] == $online_mode || $value['school_attr'] == $civil_school) {
                                $onlined = 1;
                            } else {
                                $onlined = 0;
                            }
                            $result = (new Schools())->addData([
                                'region_id' => $value['region_id'],
                                'central_id' => $this->userInfo['central_id'],
                                'school_name' => $value['school_name'],
                                'simple_code' => $pinyin->abbr($value['school_name']),
                                'school_type' => $value['school_type'],
                                'school_attr' => $value['school_attr'],
                                'sort_order' => 999,
                                'displayed' => 1,
                                'regulated' => $value['regulated'],
                                'onlined' => $onlined,
                            ], 1);
                            if ($result['code'] == 0) {
                                throw new \Exception($result['msg']);
                            }
                            $school_id = $result['insert_id'];

                            //校情统计
                            $result = $this->getFinishedStatistics($school_id);
                            if ($result['code'] == 0) {
                                throw new \Exception($result['msg']);
                            }
                        }
                    }
                    $user = [];
                    $user['user_name'] = $value['user_name'];
                    $user['real_name'] = $value['real_name'];
                    $user['simple_code'] = $value['simple_code'];
                    $user['user_type'] = 1;
                    $user['password'] = password_hash('xyrx@666', PASSWORD_DEFAULT);
                    $user_info = Db::name('user')
                        ->where('user_name', $user['user_name'])
                        ->where('deleted', 0)
                        ->find();
                    if (!empty($user_info)) {
                        $user_id = $user_info['id'];
                        Db::name('user')->where('id',$user_info['id'])->update([
                            'real_name' => $user['real_name'],
                            'simple_code' => $user['simple_code'],
                            'password' => $user['password'],
                            'user_type' => 1,
                        ]);
                    }else{
                        $user_data = (new User())->addData($user,1);
                        if($user_data['code']){
                            $user_id = $user_data['insert_id'];
                        }else{
                            throw new \Exception('新增用户账户失败');
                        }
                    }
                    //添加管理员
                    $manageData = [];
                    $manageData['user_name'] = $value['user_name'];
                    $manageData['user_id'] = $user_id;
                    $manageData['region_id'] = $value['region_id'];
                    $manageData['central_id'] = $this->userInfo['central_id'];
                    $manageData['school_id'] = $school_id;
                    $manageData['role_id'] = $value['role_id'];
                    $manageData['role_grade'] = $this->school_grade;
                    $manageData['real_name'] = $value['real_name'];
                    $manageData['simple_code'] = $value['simple_code'];
                    $manageData['main_account'] = 1;
                    if($value['school_type'] == $primary_school){
                        $manageData['primary_school_status'] = 1;
                    }
                    if($value['school_type'] == $middle_school){
                        $manageData['junior_middle_school_status'] = 1;
                    }
                    if($value['school_attr'] == $public_school){
                        $manageData['public_school_status'] = 1;
                    }
                    if($value['school_attr'] == $civil_school){
                        $manageData['civil_school_status'] = 1;
                    }
                    $result = (new model())->addData($manageData,1);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    $manage_id = $result['insert_id'];

                    //区域关联
                    $resAdd = $this->addRelationRegion($value['region_id'],$manage_id);
                    if(!$resAdd){
                        throw new \Exception('关联区域添加失败');
                    }
                    //自动添加默认权限信息
                    if($value['school_type'] == $middle_school){
                        $chk_nodes = 1;
                    }else{
                        $chk_nodes = 0;
                    }
                    if($chk_nodes){
                        $not_node_ids = (new SysNodes())->where('controller_name',$filter_school)
                            ->column('id');
                        $sys_node_ids = (new SysRoleNodes())->where('role_id', $value['role_id'])
                            ->whereNotIn('node_id',$not_node_ids)
                            ->column(['node_id','node_type']);
                    }else{
                        $sys_node_ids = (new SysRoleNodes())->where('role_id', $value['role_id'])
                            ->column(['node_id','node_type']);
                    }
                    $saveData = [];
                    foreach($sys_node_ids as $k => $v){
                        $saveData[] = [
                            'manage_id' => $manage_id,
                            'node_id' => $v['node_id'],
                            'node_type' => $v['node_type'],
                        ];
                    }
                    Db::name('ManageNodes')->insertAll($saveData);

                    $successNum +=1;
                }
                $error[] = '成功导入'.$successNum.'条管理员数据';
                //导入信息写入缓存
                Cache::set('importSchoolManage_'.$this->userInfo['manage_id'],$error);
                if (Cache::get('update_cache')) {
                    $dataList = (new model())->where('disabled',0)->select()->toArray();
                    Cache::set('manage', $dataList);
                    $dataList = (new Schools())->where('disabled',0)->select()->toArray();
                    Cache::set('school', $dataList);
                }
                $res = [
                    'code' => 1,
                    'msg' => '成功导入'.$successNum.'条管理员数据',
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
            if($this->school_grade >= $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
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

    public function getImportError(){
        if ($this->request->isPost()) {
            try {
                $res = [
                    'code' => 1,
                    'data' => Cache::get('importSchoolManage_'.$this->userInfo['manage_id'])
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

    private function checkTemplate($objPHPExcel): array
    {
        $chkcell = trim($objPHPExcel->getActiveSheet()->getCell("A1")->getValue());
        if($chkcell != '学校名称') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        $chkcell = trim($objPHPExcel->getActiveSheet()->getCell("B1")->getValue());
        if($chkcell != '区域') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $chkcell = trim($objPHPExcel->getActiveSheet()->getCell("C1")->getValue());
        if($chkcell != '学校类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $chkcell = trim($objPHPExcel->getActiveSheet()->getCell("D1")->getValue());
        if($chkcell != '学校性质') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $chkcell = trim($objPHPExcel->getActiveSheet()->getCell("E1")->getValue());
        if($chkcell != '招生方式') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $chkcell = trim($objPHPExcel->getActiveSheet()->getCell("F1")->getValue());
        if($chkcell != '管理员姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $chkcell = trim($objPHPExcel->getActiveSheet()->getCell("G1")->getValue());
        if($chkcell != '管理员电话（登陆账号）') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }
}