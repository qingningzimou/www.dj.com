<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 10:32
 */


namespace app\common\controller;


use app\common\model\AssignmentSetting;
use app\common\model\CentralSchool;
use app\common\model\Department;
use app\common\model\Manage;
use app\common\model\ManageNodes;
use app\common\model\RelationManageRegion;
use app\common\model\Schools;
use app\common\model\SysRegion;
use app\common\model\SysRoleNodes;
use app\common\model\SysNodes;
use app\common\model\User;
use app\common\model\UserMessageBatch;
use app\common\model\WorkAreaReport;
use app\common\model\WorkMunicipalReport;
use app\mobile\model\user\UserMessage;
use appPush\AppMsg;
use Overtrue\Pinyin\Pinyin;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SubTable\SysTablePartition;
use think\facade\Cache;
use think\facade\Lang;
use think\facade\Log;
use think\facade\Db;

class Education extends Basic
{
    //  定义全局权限验证
    /*protected $middleware = [
        UserAction::class
    ];*/
    //  定义接受到的数据
    protected $result;
    //  定义用户信息
    protected $userInfo;
    //  定义是否刷新
    protected $refresh;
    //  定义分页基数
    protected $pageSize;
    //  定义市级权限
    protected $city_grade;
    //  定义区县权限
    protected $area_grade;
    //  定义教管会权限
    protected $middle_grade;
    //  定义学校权限
    protected $school_grade;

    public function initialize()
    {
        parent::initialize();
        $this->request->filter('trim');//过滤参数空格
        $this->result = $this->request->param();
        if (strtolower(app('http')->getName()) == 'api' && strtolower($this->request->controller()) !== 'login' && strtolower($this->request->controller()) !== 'captcha' && $this->request->isPost()) {
             //  从解密后的数据中获取manage_id
            $this->result['user_id'] = $this->request->userInfo['id'];
            $this->refresh = $this->request->refresh;
            $this->result['new_token'] = $this->request->new_token;
            //  写入管理员信息到内存
            $this->userInfo = $this->request->userInfo;
            //  释放内存中的userInfo
            unset($this->result['userInfo']);
            //  释放内存中的token
            unset($this->result['token']);
            $this->pageSize = 15;
            $this->refreshToken = false;
            //  如果请求包含了分页基数
            if (isset($this->result['pagesize']) && intval($this->result['pagesize']) > 0) {
                $this->pageSize = intval($this->result['pagesize']);
            }
            if (!self::getGrade()){
                die(Lang::get('dictionary_err'));
            }

            /*$affair_data = Cache::get('affair');
            foreach ($affair_data as $key=> $value) {
                if (strtolower($value['affair_controller']) == strtolower($this->request->controller()) && strtolower($value['affair_method']) == strtolower($this->request->action()) && $value['actived'] == 1){
                    //  获取事务记录分表信息
                    $affairLogTable = (new SysTablePartition())->getTablePartition('reg_sys_affair_log', date('Y-m-d'));
                    //  如果获取分表信息成功
                    if ($affairLogTable['code'] != 0) {
                        Db::table($affairLogTable['table_name'])->insert([
                            'affair_id' => $value['id'],
                            'node_id' => $value['node_id'],
                            'user_id' => $this->result['user_id'],
                            'post_data' => json_encode($this->request->post(),256),
                            'create_time' => time()
                        ]);
                    }
                }
            }*/
        }
    }

    /**
     * 获取各级管理权限
     * @param $user_nodes
     * @param $controller_name
     * @return array
     */
    private function getGrade(){
        try {
            $dictionary = new FilterData();
            $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSSJQX');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $this->city_grade = $getData['data'];
            $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSQYQX');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $this->area_grade = $getData['data'];
            $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSXGHQX');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $this->middle_grade = $getData['data'];
            $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSXXQX');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $this->school_grade = $getData['data'];
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }


    public function checkHash($hash, $user_id)
    {
        $hash_data = decrypt($hash);
        if(empty($hash_data)){
            return [
                'code' => 0,
                'msg' => Lang::get('hash_error')
            ];
        }
        $hash_user_id = $hash_data['user_id'];
        $hashKey = $hash_data['sub'];
        $hashTime = $hash_data['time'];
        //  如果hash不存在，则返回错误
        if (!Cache::has($hashKey)) {
            return [
                'code' => 0,
                'msg' => Lang::get('hash_no_found')
            ];
        }
        if ('user'.$user_id != $hash_user_id) {
            return [
                'code' => 0,
                'msg' => Lang::get('hash_check_fail')
            ];
        }
        if ($hashTime < time()){
            return [
                'code' => 0,
                'msg' => Lang::get('hash_expire')
            ];
        }
        //  销毁hash，如果销毁失败，则验证失败
        if (!Cache::delete($hashKey)) {
            return [
                'code' => 0,
                'msg' => Lang::get('hash_check_fail')
            ];
        }
        return [
            'code' => 1,
            'msg' => Lang::get('hash_check_success')
        ];
    }
    /**
     * 获取当前登录账户控制器方法及其它权限
     * @param $user_nodes
     * @param $controller_name
     * @return array
     */
   /* public function getResources($user_info,$controller_name){
        try {
            $user_nodes = $user_info['nodes'];
            $nodes = Cache::get('nodes');
            $nodes_data = [];
            //当前控制器下资源
            $node_data = filter_value_one($nodes, 'controller_name', $controller_name);
            if (count($node_data) > 0) {
                $nodes_data = array_values(filter_by_value($nodes,'parent_id',$node_data['id']));
            }
            //鉴权资源
            if (count($nodes_data) > 0) {
                $nodes_data = array_values(filter_by_value($nodes_data,'authority',1));
            }
            //用户拥有的当前控制器下的资源
            $user_nodes_data = [];
            $user_node_data = filter_value_one($user_nodes, 'controller_name', $controller_name);
            if (count($user_node_data) > 0) {
                $user_nodes_data = array_values(filter_by_value($user_nodes,'parent_id',$user_node_data['id']));
            }


            //功能模块管控
//            $module = Cache::get('module');
//            $theTime = time();
//            $moduleNodes = [];
//            foreach ($module as $item){
//                if(date_to_unixtime($item['start_time']) < $theTime && date_to_unixtime($item['end_time']) > $theTime){
//                    $node_ids = Db::name('moduleNodes')->where([
//                        'module_id' => $item['id'],
//                        'deleted' => 0,
//                    ])->column('node_id');
//                    $moduleNodes = array_merge($moduleNodes,$node_ids);
//                }
//            }
//            //获取触发管控条件的资源与用户资源的交集
//            $moduleIds = array_intersect(array_column($user_nodes_data,'id'),$moduleNodes);
//            foreach ($moduleNodes as $value){
//                $user_nodes_data = remove_by_value($user_nodes_data, 'id', $value);
//            }
//            $user_nodes_data = array_values($user_nodes_data);
//            var_dump($user_nodes_data);


            //用户拥有的当前控制器下的方法名
            $user_method = array_column($user_nodes_data,'method_name');
            $res_data = [];
            $res_data['method_auth'] =[];
            $res_data['grade_auth'] = [];
            foreach ($nodes_data as $value){
                if(in_array($value['method_name'],$user_method) || $user_info['defaulted']){
                    $res_data['method_auth'][] = [
                        'node_name' => $value['node_name'],
                        'method_name' => $value['method_name'],
                        'exist' => 1
                    ];
                }else{
                    $res_data['method_auth'][] = [
                        'node_name' => $value['node_name'],
                        'method_name' => $value['method_name'],
                        'exist' => 0
                    ];
                }
            }
            $res_data['status_auth'][] = [
                'status_name' => '公办权限',
                'auth_name' => 'public_school_status',
                'status' => $user_info['public_school_status']
            ];
            $res_data['status_auth'][] = [
                'status_name' => '民办权限',
                'auth_name' => 'civil_school_status',
                'status' => $user_info['civil_school_status']
            ];
            $res_data['status_auth'][] = [
                'status_name' => '小学权限',
                'auth_name' => 'primary_school_status',
                'status' => $user_info['primary_school_status']
            ];
            $res_data['status_auth'][] = [
                'status_name' => '初中权限',
                'auth_name' => 'junior_middle_school_status',
                'status' => $user_info['junior_middle_school_status']
            ];
            $dictionary = new FilterData();
            $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSSJQX');
            if($getData['code']){
                $grade_id = $getData['data'];
                if($grade_id <= $user_info['grade_id']){
                    $res_data['grade_auth'] = [
                        'status_name' => '市局权限',
                        'grade_status' => 1
                    ];
                }else{
                    $res_data['grade_auth'] = [
                        'status_name' => '市局权限',
                        'grade_status' => 0
                    ];
                }
            }else{
                $res_data['grade_auth'] = [
                    'status_name' => '市局权限',
                    'grade_status' => 0
                ];
            }
            $res_data['main_auth'] = [
                'status_name' => '负责人权限',
                'main_status' => $user_info['main_account']
            ];
            return $res_data;
        } catch (\Exception $exception) {
            return [];
        }
    }*/
    //更新公办学校状态
    public function publicSchoolState($manage_id,$state){
        try {
            $result = Db::name('Manage')
                ->where('id',$manage_id)
                ->where('deleted',0)
                ->find();
            if($result){
                Db::name('Manage')->where('id',$manage_id)->update(['public_school_status'=>$state]);
                return true;
            }else{
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }

    //更新民办学校状态
    public function civilSchoolState($manage_id,$state){
        try {
            $result = Db::name('Manage')
                ->where('id',$manage_id)
                ->where('deleted',0)
                ->find();
            if($result){
                Db::name('Manage')->where('id',$manage_id)->update(['civil_school_status'=>$state]);
                return true;
            }else{
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }

    //更新小学校状态
    public function primarySchoolState($manage_id,$state){
        try {
            $result = Db::name('Manage')
                ->where('id',$manage_id)
                ->where('deleted',0)
                ->find();
            if($result){
                Db::name('Manage')->where('id',$manage_id)->update(['primary_school_status'=>$state]);
                return true;
            }else{
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }

    //更新初中状态
    public function juniorSchoolState($manage_id,$state){
        try {
            $result = Db::name('Manage')
                ->where('id',$manage_id)
                ->where('deleted',0)
                ->find();
            if($result){
                Db::name('Manage')->where('id',$manage_id)->update(['junior_middle_school_status'=>$state]);
                return true;
            }else{
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }

    //更新禁用状态
    public function DisabledState($manage_id,$state){
        try {
            $result = Db::name('Manage')
                ->where('id',$manage_id)
                ->where('deleted',0)
                ->find();
            if($result){
                Db::name('Manage')->where('id',$manage_id)->update(['disabled'=>$state]);
                $this->deleteUserCacheWithDisabled($state,$result['user_id']);
                return true;
            }else{
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }

    //部门机构树形结构
    function getTree($list, $pid = 0, $itemprefix = '') {
        static $icon = ['　　', '　　├── ', '　　└── '];
        static $arr = [];
        $number = 1;
        foreach($list as $row) {
            if($row['pid'] == $pid) {
                $brotherCount = 0;
                //判断当前有多少个兄弟分类
                foreach($list as $r) {
                    if($row['pid'] == $r['pid']) {
                        $brotherCount++;
                    }
                }
                if($brotherCount > 0 ) {
                    $j = '';
                    if($number == $brotherCount) {
                        $j .= $icon[2];
                        $k = '';
                    }else{
                        $j .= $icon[1];
                        $k = $icon[0];
                    }
                    $spacer = $row['pid'] > 0 ? $itemprefix . $j : '';
                    $row['name'] = $spacer . $row['name'];
                    $arr[] = $row;
                    $number++;
                    $this->getTree($list, $row['id'],$itemprefix . $k);
                }
            }
        }
        return  $arr;
    }

    public function addUser($data){
        try {
            $user_info = Db::name('user')
                ->where('user_name', $data['user_name'])
                ->where('deleted', 0)
                ->find();
            if (!empty($user_info)) {
                $user_id = $user_info['id'];
            }else{
                $user_data = (new User())->addData($data,1);
                $user_id = $user_data['insert_id'];
            }
            return $user_id;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function checkUser($user_id,$user_name = ''){
        try {
            if($user_name){
                $user_info = Db::name('user')
                    ->where('user_name', $user_name)
                    ->where('id','<>',$user_id)
                    ->where('deleted', 0)
                    ->find();
                if (!empty($user_info)) {
                    return [
                        'code' => 0,
                        'msg' => '登录账号重复请检查',
                    ];
                }else{
                    $userInfo = Db::name("user")
                        ->where('id',$user_id)
                        ->where('deleted',0)
                        ->find();
                    return [
                        'code' => 1,
                        'data' => $userInfo,
                    ];
                }
            }else{
                $userInfo = Db::name("user")
                    ->where('id',$user_id)
                    ->where('deleted',0)
                    ->find();
                if($userInfo){
                    return [
                        'code' => 1,
                        'data' => $userInfo,
                    ];
                }else{
                    return [
                        'code' => 0,
                        'msg' => '没有找到用户数据',
                    ];
                }
            }
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage(),
            ];
        }
    }

    public function getDepartment($id){
        $department = Cache::get('department');
        $departmentData = filter_value_one($department, 'id', $id);
        return $departmentData;
    }
    public function getCentral($id){
        $central = Cache::get('central');
        $centralData = filter_value_one($central, 'id', $id);
        return $centralData;
    }
    public function checkManage($postData,$id = null){
        try {
            if($id){
                $manage_info = Db::name("manage")
                ->where('id',$id)
                ->where('deleted',0)
                ->find();
                if (empty($manage_info)){
                    return [
                        'code' => 0,
                        'msg' => '管理人员不存在',
                    ];
                }
                return [
                    'code' => 1,
                    'data' => $manage_info,
                ];
            }else{
                $manage_info = Db::name('manage')
                    ->where('user_name', $postData['user_name'])
                    ->where('role_id', $postData['role_id'])
                    ->where('deleted', 0)
                    ->find();
                if (!empty($manage_info)){
                    return [
                        'code' => 0,
                        'msg' => '管理人员已有相同角色账号存在',
                    ];
                }
                return [
                    'code' => 1,
                    'data' => $manage_info,
                ];
            }
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage(),
            ];
        }
    }


    public function addSysNodes($role_id,$manage_id){
        try {
            $chk_node_ids = (new SysNodes())
                ->where('authority',0)
                ->where('displayed',0)
                ->column('id');
            $sys_node_ids = (new SysRoleNodes())
                ->whereNotIn('node_id',$chk_node_ids)
                ->where('role_id', $role_id)
                ->column(['node_id','node_type']);
            $saveData = [];
            foreach($sys_node_ids as $key => $value){
                $saveData[] = [
                    'manage_id' => $manage_id,
                    'node_id' => $value['node_id'],
                    'node_type' => $value['node_type'],
                ];
            }
            Db::name('ManageNodes')->insertAll($saveData);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function addRelationRegion($region_id = 0,$manage_id){
        try {
            if($region_id == 0){
                $region = Cache::get('region');
                $regionIds = array_column($region,'id');
                foreach ($regionIds as $regionId){
                    Db::name('RelationManageRegion')->insert([
                        'manage_id' => $manage_id,
                        'region_id' => $regionId
                    ]);
                }
            }else{
                Db::name('RelationManageRegion')->insert([
                    'manage_id' => $manage_id,
                    'region_id' => $region_id
                ]);
            }
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function checkData($postData,$class,$method){
          //  验证表单hash
        /*$checkHash = $this->checkHash($postData['hash'],$this->result['user_id']);
        if($checkHash['code'] == 0)
        {
            return [
                'code' => 0,
                'msg' => $checkHash['msg']
            ];

        }*/
        //  验证器验证请求的数据是否合法
        $checkData = parent::checkValidate($postData, $class, $method);
        //  如果数据不合法，则返回
        if ($checkData['code'] != 1) {
            return [
                'code' => 0,
                'msg' => $checkData['msg']
            ];
        }

        return [
            'code' => 1,
            'msg' => 'ok',
        ];
    }

    public function getManageList($where,$role_grade,$central_id = 0,$school_id = 0,$main = 1){
        try {
            if($central_id){
                $where[] = ['central_id','=',$central_id];
            }
            if($school_id){
                $where[] = ['school_id','=',$school_id];
            }
            if(!$main && $role_grade > $this->userInfo['grade_id']){
                $where[] = ['main_account','=',0];
            }
            $region_ids = [];
            if ($this->userInfo['relation_region']){
                $region_ids = array_column($this->userInfo['relation_region'],'region_id');
            }
            $where[] = ['region_id', 'in', $region_ids];
            $data = Db::name('Manage')
                ->where($where)
                ->where('role_grade','=',$role_grade)
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
                    'central_id',
                    'region_id',
                    'main_account',
                    'school_id',
                ])
                ->order('id','DESC')->master(true)
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
            return $data;
        } catch (\Exception $exception) {
            return [];
        }
    }

    function deleteManage($id){
        try {
            $checkManage = $this->checkManage([],$id);
            if($checkManage['code'] == 0){
                throw new \Exception($checkManage['msg']);
            }
            Db::name('RelationManageRegion')->where('manage_id',$id)->update(['deleted' => 1]);
            Db::name('ManageNodes')->where('manage_id',$id)->update(['deleted' => 1]);
            (new Manage())->deleteData(['deleted' => 1],['id'=>$id]);
            Cache::delete('user'.md5($checkManage['data']['user_id']));
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }


    function list_to_tree($list, $pk='id', $pid = 'parent_id', $child = 'children', $root = 0) {
        try {
            //创建Tree
            $tree = array();
            if (is_array($list)) {
                //创建基于主键的数组引用
                $refer = array();

                foreach ($list as $key => $data) {
                    $refer[$data[$pk]] = &$list[$key];
                }

                foreach ($list as $key => $data) {
                    //判断是否存在parent
                    $parantId = $data[$pid];

                    if ($root == $parantId) {
                        $tree[] = &$list[$key];
                    } else {
                        if (isset($refer[$parantId])) {
                            $parent = &$refer[$parantId];
                            $parent[$child][] = &$list[$key];
                        }
                    }
                }
            }

            return $tree;
        } catch (\Exception $exception) {
            return [];
        }
    }

    function getUpdatePasswordData($postData){
        if(!empty($postData['password'])) {
            $postData['password'] = password_hash($postData['password'], PASSWORD_DEFAULT);
        }else{
            unset($postData['password']);
        }
        $pinyin = new Pinyin();
        $postData['simple_code'] = $pinyin->abbr($postData['real_name']);
        return $postData;
    }

    function getAddPasswordData($postData){
        if(empty($postData['password'])){
            $postData['password'] = 'xyrx@666';
        }
        $postData['password'] = password_hash($postData['password'], PASSWORD_DEFAULT);
        $pinyin = new Pinyin();
        $postData['simple_code'] = $pinyin->abbr($postData['real_name']);
        $postData['last_ip'] = $this->request->ip();
        return $postData;
    }

    function deleteUserCacheWithDisabled($disabled,$user_id){
        if(intval($disabled) == 1){
            Cache::delete('user'.md5($user_id));
            return true;
        }
        return true;
    }

    //获取公办、民办、小学、初中的限制条件
    function getSchoolWhere($prefix = '')
    {
        $where = [];
        //公办、民办、小学、初中权限
        $public_school = 0;
        $civil_school = 0;
        $middle_school = 0;
        $primary_school = 0;
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
        $school_attr = [];
        $school_type = [];
        if($this->userInfo['public_school_status'] == 1){
            $school_attr[] = $public_school;
        }
        if($this->userInfo['civil_school_status'] == 1){
            $school_attr[] = $civil_school;
        }
        if($this->userInfo['primary_school_status'] == 1){
            $school_type[] = $primary_school;
        }
        if($this->userInfo['junior_middle_school_status'] == 1){
            $school_type[] = $middle_school;
        }
        $attr_field = $prefix ? $prefix . '.school_attr' : 'school_attr';
        $type_field = $prefix ? $prefix . '.school_type' : 'school_type';

        $where['school_attr'] = [$attr_field, 'in', $school_attr];
        $where['school_type'] = [$type_field, 'in', $school_type];

        return $where;
    }

    //根据角色获取下拉列表
    function getAllSelectByRoleList()
    {
        $data['code'] = 1;
        $data['region_list'] = [];//区县 市级以上
        $data['school_list'] = [];//学校 学校以上
        $data['police_list'] = [];//派出所
        $data['plan_list'] = [];//招生计划
        $data['central_list'] = [];//教管会列表

        try {
            //招生计划列表
            $data['plan_list'] = Db::name('plan')
                ->where([['deleted', '=', 0], ['plan_time', '=', date('Y')] ])
                ->field(['id', 'plan_name',])->select()->toArray();

            $public_where = [];
            if ($this->request->has('school_attr') && $this->result['school_attr']) {
                $public_where[] = ['school_attr', '=', $this->result['school_attr']];
            }
            if ($this->request->has('school_type') && $this->result['school_type']) {
                $public_where[] = ['school_type', '=', $this->result['school_type']];
            }
            //公办、民办、小学、初中权限
            $school_where = $this->getSchoolWhere();
            $public_where[] = $school_where['school_attr'];
            $public_where[] = $school_where['school_type'];
            //市级以上权限
            if ($this->userInfo['grade_id'] >= $this->city_grade) {
                //市级区县列表
                $region_list = Db::name('sys_region')->where('disabled', 0)
                    ->where('deleted', 0)->field(['id', 'region_name'])
                    ->where('parent_id', '<>', 0)->select()->toArray();
                $data['region_list'] = $region_list;

                //市级学校列表
                $where = [];
                if ($this->request->has('region_id') && $this->result['region_id']) {
                    $where[] = ['region_id', '=', $this->result['region_id']];
                } else {
                    /*$region_ids = array_column(object_to_array($this->userInfo['relation_region']), 'region_id');
                    if (count($region_ids) > 1) {
                        $where[] = ['region_id', '=', $region_ids[1]];
                    }*/
                    //$where[] = ['region_id', '=', 1];
                }

                $data['school_list'] = Db::name('sys_school')
                    ->field(['id', 'school_attr', 'school_type', 'school_name', 'central_id', 'onlined'])
                    ->where('deleted', 0)->where($where)
                    ->where('disabled', 0)->where($public_where)->order('id', 'desc')->select()->toArray();

                //派出所列表
                $data['police_list'] = Db::name('sys_police_station')
                    ->field(['id', 'name',])->where('deleted', 0)->where($where)
                    ->order('id', 'desc')->select()->toArray();

                //教管会列表
                $data['central_list'] = Db::name('central_school')
                    ->field(['id', 'central_name',])->where('deleted', 0)->where($where)
                    ->order('id', 'desc')->select()->toArray();
            } else {
                if ($this->userInfo['region_id'] <= 1) {
                    return ['code' => 0, 'msg' => '管理员所属区县设置错误'];
                }
                //教管会权限
                if ($this->userInfo['grade_id'] == $this->middle_grade) {
                    if ($this->userInfo['central_id'] > 0) {
                        $central_id = $this->userInfo['central_id'];
                        $where = [];
                        $where[] = ['central_id', '=', $central_id];

                        $data['school_list'] = Db::name('sys_school')
                            ->field(['id', 'school_attr', 'school_type', 'school_name', 'central_id', 'onlined'])->where('deleted', 0)
                            ->where($where)->where($public_where)->where('disabled', 0)
                            ->order('id', 'desc')->select()->toArray();
                    } else {
                        return ['code' => 0, 'msg' => '教管会管理员所属教管会ID设置错误'];
                    }
                }else{
                    //区级权限
                    $data['school_list'] = Db::name('sys_school')
                        ->field(['id', 'school_attr', 'school_type', 'school_name', 'central_id', 'onlined'])
                        ->where('deleted', 0)->where('disabled', 0)->where('directly',  0)//非市直
                        ->where('region_id', '=', $this->userInfo['region_id'])
                        ->where($public_where)->order('id', 'desc')->select()->toArray();

                    //教管会列表
                    $data['central_list'] = Db::name('central_school')
                        ->field(['id', 'central_name',])->where('deleted', 0)
                        ->where('region_id', '=', $this->userInfo['region_id'])
                        ->order('id', 'desc')->select()->toArray();
                }

                //派出所列表
                $data['police_list'] = Db::name('sys_police_station')
                    ->field(['id', 'name',])->where('deleted', 0)
                    ->where('region_id', '=', $this->userInfo['region_id'] )
                    ->order('id', 'desc')->select()->toArray();
            }
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }

        return $data;
    }

    //根据角色获取学校ID $is_contain_central_school区级是否包含教学点学校 默认false包含教学点
    function getSchoolIdsByRole($is_contain_central_school = false)
    {
        try {
            $res = ['code' => 1, 'bounded' => false];
            //市级获取所有
            if ($this->userInfo['grade_id'] >= $this->city_grade) {
                $school_ids = Db::name("sys_school")
                    ->where([['deleted','=',0],
                        ['disabled', '=', 0]])
                    ->column('id');
                $res['school_ids'] = $school_ids;
            }else{
                $where = [];
                //公办、民办、小学、初中权限
                $public = $this->getSchoolWhere();
                $where[] = $public['school_attr'];
                $where[] = $public['school_type'];

                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];//不是市直
                //教管会权限
                if ($this->userInfo['grade_id'] == $this->middle_grade) {
                    if ( isset($this->userInfo['central_id']) && $this->userInfo['central_id'] > 0 ) {
                        $central_id = $this->userInfo['central_id'];
                        $school_ids = Db::name("sys_school")
                            ->where('central_id', '=', $central_id)->where($where)
                            ->column('id');

                        $res['bounded'] = true;
                        $res['school_ids'] = $school_ids;
                        $res['middle_auth'] = [
                            'status_name' => '教管会权限',
                            'grade_status' => 1
                        ];
                    }else{
                        return ['code' => 0, 'msg' => '教管会管理员所属教管会ID设置错误' ];
                    }
                }
                //区级权限
                if ($this->userInfo['grade_id'] > $this->middle_grade){
                    if ( isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1 ) {
                        $region_id = $this->userInfo['region_id'];
                        //不包含教学点
                        if($is_contain_central_school){
                            $where[] = ['central_id', '=', 0];//不是教学点
                        }
                        $school_ids = Db::name("sys_school")
                            ->where('region_id', '=', $region_id)->where($where)
                            ->column('id');

                        $res['bounded'] = true;
                        $res['school_ids'] = $school_ids;
                        $res['region_auth'] = [
                            'status_name' => '区级权限',
                            'grade_status' => 1
                        ];
                    }else{
                        return ['code' => 0, 'msg' => '区级管理员所属区县ID设置错误' ];
                    }
                }
                //学校权限
                if ($this->userInfo['grade_id'] < $this->middle_grade){
                    if ( isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0 ) {
                        $region_id = $this->userInfo['region_id'];
                        $school_ids = Db::name("sys_school")
                            ->where('region_id', '=', $region_id)->where($where)
                            ->column('id');

                        $res['bounded'] = true;
                        $res['school_ids'] = [$this->userInfo['school_id']];
                        $res['school_auth'] = [
                            'status_name' => '学校权限',
                            'grade_status' => 1
                        ];
                    }else{
                        return ['code' => 0, 'msg' => '区级管理员所属区县ID设置错误' ];
                    }
                }
            }
            return $res;
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //获取区级统计的学校ID和教管会ID，排除教管会的所属学校
    function getRegionStatistics()
    {
        try {
            if ( isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1 ) {
                $where = [];
                //公办、民办、小学、初中权限
                $public = $this->getSchoolWhere();
                $where[] = $public['school_attr'];
                $where[] = $public['school_type'];

                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];//不是市直
                $where[] = ['central_id','=',0];//不是教管会所管学校

                $region_id = $this->userInfo['region_id'];
                $school_ids = Db::name("SysSchool")
                    ->where('region_id', '=', $region_id)->where($where)
                    ->column('id');

                $central_ids = Db::name("CentralSchool")->where('deleted', 0)
                    ->where('region_id', '=', $region_id)->where('disabled', 0)
                    ->column('id');

                return ['code' => 1, 'school_ids' => $school_ids, 'central_ids' => $central_ids ];
            }else{
                return ['code' => 0, 'msg' => '区级管理员所属区县ID设置错误' ];
            }
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //过滤word多余标签
    function clearHtml($content, $allowtags='') {
        mb_regex_encoding('UTF-8');
        $search = array('/&lsquo;/u', '/&rsquo;/u', '/&ldquo;/u', '/&rdquo;/u', '/&mdash;/u');
        $replace = array('\'', '\'', '"', '"', '-');
        $content = preg_replace($search, $replace, $content);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        if(mb_stripos($content, '/*') !== FALSE){
            $content = mb_eregi_replace('#/\*.*?\*/#s', '', $content, 'm');
        }
        $content = preg_replace(array('/<([0-9]+)/'), array('< $1'), $content);

        //$content = strip_tags($content, $allowtags);
        //$content = preg_replace(array('/^\s\s+/', '/\s\s+$/', '/\s\s+/u'), array('', '', ' '), $content);
        $search = array('#<(strong|b)[^>]*>(.*?)</(strong|b)>#isu', '#<(em|i)[^>]*>(.*?)</(em|i)>#isu', '#<u[^>]*>(.*?)</u>#isu');
        $replace = array('<b>$2</b>', '<i>$2</i>', '<u>$1</u>');
        $content = preg_replace($search, $replace, $content);

        $num_matches = preg_match_all("/\<!--/u", $content, $matches);
        if($num_matches){
            $content = preg_replace('/\<!--(.)*--\>/isu', '', $content);
        }
        return $content;
    }

    //校情完成统计
    function getFinishedStatistics($school_id): array
    {
        try {
            $region_school_count = 0;
            $region_finished_count = 0;

            $school = Db::name('SysSchool')->field('id, region_id, directly, finished, deleted')->find($school_id);

            $area = new WorkAreaReport();
            $area_report = $area->where('school_id', $school_id)->find();
            if($area_report){
                $update = [];
                $update['id'] = $area_report['id'];
                $update['deleted'] = $school['deleted'];
                $update['finished_count'] = $school['finished'];
                $res = $area->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $area->school_id = $school_id;
                $area->deleted = $school['deleted'];
                $area->finished_count = $school['finished'];
                $insert = $area->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '区级工作进度【校情统计】新增失败' ];
                }
            }

            $municipal = new WorkMunicipalReport();
            $region_id = $school['region_id'];
            //市直 市教育局
            if ($school['directly'] == 1) {
                $region_id = 1;

                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',1];//市直
                $region_school_count += Db::name("SysSchool")->where($where)->count();

                $where[] = ['finished','=',1];//校情完成
                $region_finished_count += Db::name("SysSchool")->where($where)->count();
            }else{
                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];//不是市直
                $where[] = ['region_id','=',$region_id];
                $region_school_count += Db::name("SysSchool")->where($where)->count();

                $where[] = ['finished','=',1];//校情完成
                $region_finished_count += Db::name("SysSchool")->where($where)->count();
            }

            $municipal_report = $municipal->where('region_id', $region_id)->find();
            if($municipal_report){
                $update = [];
                $update['id'] = $municipal_report['id'];
                $update['school_count'] = $region_school_count;
                $update['finished_count'] = $region_finished_count;
                $res = $municipal->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $municipal->region_id = $region_id;
                $municipal->school_count = $region_school_count;
                $municipal->finished_count = $region_finished_count;
                $insert = $municipal->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '市级工作进度【校情统计】新增失败' ];
                }
            }
            return ['code' => 1, 'msg' => '校情统计成功'];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //学校学位统计
    function getSchoolDegreeStatistics($school_id): array
    {
        try {
            $school_degree_count = 0;
            $region_degree_count = 0;

            $school = Schools::field('id, region_id, directly')->find($school_id);

            //学校学位总数
            $school_degree_count += Db::name('PlanApply')->where('school_id', '>', 0)
                ->where('school_id', $school_id)->where('status', 1)
                ->where('deleted', 0)->group('school_id')->sum('spare_total');

            $area = new WorkAreaReport();
            $area_report = $area->where('school_id', $school_id)->find();
            if($area_report){
                $update = [];
                $update['id'] = $area_report['id'];
                $update['degree_count'] = $school_degree_count;
                $res = $area->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $area->school_id = $school_id;
                $area->degree_count = $school_degree_count;
                $insert = $area->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '区级工作进度【学位统计】新增失败' ];
                }
            }

            $municipal = new WorkMunicipalReport();
            $region_id = $school['region_id'];
            //市直 市教育局
            if ($school['directly'] == 1) {
                $region_id = 1;

                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',1];//市直
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                $region_degree_count += Db::name('PlanApply')
                    ->where('school_id', '>', 0)->where('status', 1)
                    ->where('deleted', 0)->where('school_id', 'in', $school_ids)
                    ->group('school_id')->sum('spare_total');
            }else{
                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];//不是市直
                $where[] = ['central_id','=',0];//不是教管会所管学校
                $where[] = ['region_id','=',$region_id];
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                //学校学位
                $region_degree_count += Db::name('PlanApply')
                    ->where('school_id', '>', 0)->where('status', 1)
                    ->where('deleted', 0)->where('school_id', 'in', $school_ids)
                    ->group('school_id')->sum('spare_total');

                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['region_id','=',$region_id];
                $central_ids = Db::name("CentralSchool")->where($where)->column('id');

                //教管会学位
                $region_degree_count += Db::name('PlanApply')
                    ->group('central_id')->where('central_id', '>', 0)
                    ->where('central_id', 'in', $central_ids)
                    ->where('status', 1)->where('deleted', 0)->sum('spare_total');
            }
            $municipal_report = $municipal->where('region_id', $region_id)->find();
            if($municipal_report){
                $update = [];
                $update['id'] = $municipal_report['id'];
                $update['degree_count'] = $region_degree_count;
                $res = $municipal->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $municipal->region_id = $region_id;
                $municipal->degree_count = $region_degree_count;
                $insert = $municipal->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '市级工作进度【学位统计】新增失败' ];
                }
            }

            return ['code' => 1, 'msg' => '学位统计成功'];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //教管会学位统计
    function getCentralDegreeStatistics($central_id): array
    {
        try {
            $region_degree_count = 0;

            $central = CentralSchool::field('id, region_id')->find($central_id);

            $municipal = new WorkMunicipalReport();
            $region_id = $central['region_id'];

            $where = [];
            $where[] = ['deleted','=',0];
            $where[] = ['disabled','=',0];
            $where[] = ['directly','=',0];//不是市直
            $where[] = ['central_id','=',0];//不是教管会所管学校
            $where[] = ['region_id','=',$region_id];
            $school_ids = Db::name("SysSchool")->where($where)->column('id');

            //学校学位
            $region_degree_count += Db::name('PlanApply')
                ->where('school_id', '>', 0)->where('status', 1)
                ->where('deleted', 0)->where('school_id', 'in', $school_ids)
                ->group('school_id')->sum('spare_total');

            $where = [];
            $where[] = ['deleted','=',0];
            $where[] = ['disabled','=',0];
            $where[] = ['region_id','=',$region_id];
            $central_ids = Db::name("CentralSchool")->where($where)->column('id');

            //教管会学位
            $region_degree_count += Db::name('PlanApply')
                ->where('central_id', '>', 0)->where('status', 1)
                ->where('central_id', 'in', $central_ids)
                ->where('deleted', 0)->group('central_id')->sum('spare_total');

            $municipal_report = $municipal->where('region_id', $region_id)->find();
            if($municipal_report){
                $update = [];
                $update['id'] = $municipal_report['id'];
                $update['degree_count'] = $region_degree_count;
                $res = $municipal->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $municipal->region_id = $region_id;
                $municipal->degree_count = $region_degree_count;
                $insert = $municipal->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '市级工作进度【学位统计】新增失败' ];
                }
            }

            return ['code' => 1, 'msg' => '学位统计成功'];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //房产统计
    function getAddressStatistics($school_id): array
    {
        try {
            $school_address_count = 0;
            $region_address_count = 0;
            $not_region_address_count = 0;

            $school = Schools::field('id, region_id, school_type, directly')->find($school_id);
            $region = SysRegion::field('id, simple_code')->find($school['region_id']);

            $field = "";
            if ($school['school_type'] == 1) {
                $field = "primary_school_id";
            }
            if ($school['school_type'] == 2) {
                $field = "middle_school_id";
            }
            //学校***完整地址勾选数量
            $school_address_count += Db::name('SysAddressIntact')
                ->where($field, $school_id)->where('deleted', 0)->count();
            //学校***缩略详细勾选数量
            $school_address_count += Db::name('sys_address_' . $region['simple_code'])
                ->where($field, $school_id)->where('deleted', 0)->count();

            $area = new WorkAreaReport();
            $area_report = $area->where('school_id', $school_id)->find();
            if($area_report){
                $update = [];
                $update['id'] = $area_report['id'];
                $update['check_address_count'] = $school_address_count;
                $res = $area->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $area->school_id = $school_id;
                $area->check_address_count = $school_address_count;
                $insert = $area->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '区级工作进度【房产统计】新增失败' ];
                }
            }

            //市直学校ID
            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['disabled', '=', 0];
            $where[] = ['directly', '=', 1];//市直
            $school_ids = Db::name("SysSchool")->where($where)->column('id');

            $municipal = new WorkMunicipalReport();
            $region_id = $school['region_id'];
            //市直 市教育局
            if ($school['directly'] == 1) {
                $region_id = 1;
                //市直***完整地址***勾选数量
                $region_address_count += Db::name('SysAddressIntact')->where('deleted', 0)
                    ->where('primary_school_id|middle_school_id', 'in', $school_ids)->count();
                //市直***缩略详细***勾选数量
                $region_address_count += Db::name('sys_address_' . $region['simple_code'])->where('deleted', 0)
                    ->where('primary_school_id|middle_school_id', 'in', $school_ids)->count();
            }else {
                //区县***完整地址***勾选数量
                $region_address_count += Db::name('SysAddressIntact')
                    ->where('deleted', 0)->where('region_id', $school['region_id'])
                    ->where('primary_school_id|middle_school_id', 'not in', $school_ids)
                    ->where('primary_school_id|middle_school_id', '>', 0)->count();
                //区县***完整地址***未勾选数量
                $not_region_address_count += Db::name('SysAddressIntact')
                    ->where('deleted', 0)->where('region_id', $school['region_id'])
                    ->where('primary_school_id', 0)->where('middle_school_id', 0)->count();
                //区县***缩略详细***勾选数量
                $region_address_count += Db::name('sys_address_' . $region['simple_code'])->where('deleted', 0)
                    ->where('primary_school_id|middle_school_id', 'not in', $school_ids)
                    ->where('primary_school_id|middle_school_id', '>', 0)->count();
                //区县***缩略详细***未勾选数量
                $not_region_address_count += Db::name('sys_address_' . $region['simple_code'])
                    ->where('deleted', 0)->where('primary_school_id', 0)
                    ->where('middle_school_id', 0)->count();
            }
            $municipal_report = $municipal->where('region_id', $region_id)->find();
            if($municipal_report){
                $update = [];
                $update['id'] = $municipal_report['id'];
                $update['check_address_count'] = $region_address_count;
                $update['not_check_address_count'] = $not_region_address_count;
                $res = $municipal->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $municipal->region_id = $region_id;
                $municipal->check_address_count = $region_address_count;
                $municipal->not_check_address_count = $not_region_address_count;
                $insert = $municipal->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '市级工作进度【房产统计】新增失败' ];
                }
            }
            return ['code' => 1, 'msg' => '房产统计成功' ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //六年级统计
    function getSixthGradeStatistics($school_id): array
    {
        try {
            $school_sixth_grade_count = 0;
            $region_sixth_grade_count = 0;

            $school = Schools::field('id, region_id, school_type, directly')->find($school_id);

            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['graduation_school_id', '=', $school_id];
            $school_sixth_grade_count = Db::name('SixthGrade')->where($where)->count();

            $area = new WorkAreaReport();
            $area_report = $area->where('school_id', $school_id)->find();
            if($area_report){
                $update = [];
                $update['id'] = $area_report['id'];
                $update['sixth_grade_count'] = $school_sixth_grade_count;
                $res = $area->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $area->school_id = $school_id;
                $area->sixth_grade_count = $school_sixth_grade_count;
                $insert = $area->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '区级工作进度【六年级统计】新增失败' ];
                }
            }


            $municipal = new WorkMunicipalReport();
            $region_id = $school['region_id'];
            //市直 市教育局
            if ($school['directly'] == 1) {
                $region_id = 1;

                //市直学校ID
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['disabled', '=', 0];
                $where[] = ['directly', '=', 1];//市直
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['graduation_school_id', 'in', $school_ids];
                $region_sixth_grade_count = Db::name('SixthGrade')->where($where)->count();
            }else{
                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];//不是市直
                $where[] = ['region_id','=',$region_id];
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['graduation_school_id', 'in', $school_ids];
                $region_sixth_grade_count = Db::name('SixthGrade')->where($where)->count();
            }
            $municipal_report = $municipal->where('region_id', $region_id)->find();
            if($municipal_report){
                $update = [];
                $update['id'] = $municipal_report['id'];
                $update['sixth_grade_count'] = $region_sixth_grade_count;
                $res = $municipal->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $municipal->region_id = $region_id;
                $municipal->sixth_grade_count = $region_sixth_grade_count;
                $insert = $municipal->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '市级工作进度【六年级统计】新增失败' ];
                }
            }
            return ['code' => 1, 'msg' => '六年级统计成功' ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //中心学校录取统计【市级工作进度统计】
    function getMiddleAdmissionStatistics($school_id): array
    {
        try {
            $middle_admission_count = 0;
            $school = Schools::field('id, region_id, central_id, directly')->find($school_id);
            if($school){
                if($school['directly'] == 0 && $school['central_id'] > 0){
                    $municipal = new WorkMunicipalReport();
                    $region_id = $school['region_id'];

                    $where = [];
                    $where[] = ['deleted','=',0];
                    $where[] = ['disabled','=',0];
                    $where[] = ['directly','=',0];//不是市直
                    $where[] = ['central_id','=',$school['central_id']];
                    $school_ids = Db::name("SysSchool")->where($where)->column('id');

                    $where = [];
                    $where[] = ['deleted','=',0];
                    $where[] = ['voided','=',0];
                    $where[] = ['resulted','=',1];
                    $where[] = ['result_school_id','in',$school_ids];
                    $middle_admission_count += Db::name("UserApply")->where($where)->count();

                    $municipal_report = $municipal->where('region_id', $region_id)->find();
                    if($municipal_report){
                        $update = [];
                        $update['id'] = $municipal_report['id'];
                        $update['middle_admission_count'] = $middle_admission_count;
                        $res = $municipal->editData($update);
                        if($res['code'] == 0){
                            return ['code' => 0, 'msg' => $res['msg'] ];
                        }
                    }else{
                        $municipal->region_id = $region_id;
                        $municipal->middle_admission_count = $middle_admission_count;
                        $insert = $municipal->save();
                        if(!$insert){
                            return ['code' => 0, 'msg' => '市级工作进度【中心学校录取统计】新增失败' ];
                        }
                    }
                }
            }else{
                return ['code' => 0, 'msg' => '学校不存在' ];
            }

            return ['code' => 1, 'msg' => '中心学校录取统计成功' ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //线下录取统计【区级工作进度统计】
    function getOfflineAdmissionStatistics($school_id): array
    {
        try {
            $offline_admission_count = 0;

            $school = Schools::field('id, directly')->find($school_id);
            if(!$school){
                return ['code' => 0, 'msg' => '学校不存在' ];
            }

            $where = [];
            $where[] = ['deleted','=',0];
            $where[] = ['voided','=',0];
            $where[] = ['resulted','=',1];
            $where[] = ['offlined','=',1];//线下录取
            $where[] = ['admission_type','=',0];
            $where[] = ['result_school_id','=',$school_id];
            $offline_admission_count = Db::name("UserApply")->where($where)->count();

            $area = new WorkAreaReport();
            $area_report = $area->where('school_id', $school_id)->find();
            if($area_report){
                $update = [];
                $update['id'] = $area_report['id'];
                $update['offline_admission_count'] = $offline_admission_count;
                $res = $area->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $area->school_id = $school_id;
                $area->offline_admission_count = $offline_admission_count;
                $insert = $area->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '区级工作进度【线下录取统计】新增失败' ];
                }
            }

            return ['code' => 1, 'msg' => '线下录取统计成功' ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //学籍统计
    function getSchoolRollStatistics($school_id): array
    {
        try {
            $school = Db::name('SysSchool')->field('id, region_id, school_type, directly')->find($school_id);

            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['registed', '=', 1];
            $where[] = ['school_id', '=', $school_id];
            $school_roll_count = Db::name('SysSchoolRoll')->where($where)->count();

            $area = new WorkAreaReport();
            $area_report = $area->where('school_id', $school_id)->find();
            if($area_report){
                $update = [];
                $update['id'] = $area_report['id'];
                $update['school_roll_count'] = $school_roll_count;
                $res = $area->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $area->school_id = $school_id;
                $area->school_roll_count = $school_roll_count;
                $insert = $area->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '区级工作进度【学籍统计】新增失败' ];
                }
            }


            $municipal = new WorkMunicipalReport();
            $region_id = $school['region_id'];
            //市直 市教育局
            if ($school['directly'] == 1) {
                $region_id = 1;

                //市直学校ID
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['disabled', '=', 0];
                $where[] = ['directly', '=', 1];//市直
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['registed', '=', 1];
                $where[] = ['school_id', 'in', $school_ids];
                $region_school_roll_count = Db::name('SysSchoolRoll')->where($where)->count();
            }else{
                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];//不是市直
                $where[] = ['region_id','=',$region_id];
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['registed', '=', 1];
                $where[] = ['school_id', 'in', $school_ids];
                $region_school_roll_count = Db::name('SysSchoolRoll')->where($where)->count();
            }
            $municipal_report = $municipal->where('region_id', $region_id)->find();
            if($municipal_report){
                $update = [];
                $update['id'] = $municipal_report['id'];
                $update['school_roll_count'] = $region_school_roll_count;
                $res = $municipal->editData($update);
                if($res['code'] == 0){
                    return ['code' => 0, 'msg' => $res['msg'] ];
                }
            }else{
                $municipal->region_id = $region_id;
                $municipal->school_roll_count = $region_school_roll_count;
                $insert = $municipal->save();
                if(!$insert){
                    return ['code' => 0, 'msg' => '市级工作进度【六年级统计】新增失败' ];
                }
            }

            return ['code' => 1, 'msg' => '学籍统计计成功' ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //学校录取情况
    function getSchoolAdmission(): array
    {
        try {
            if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                $school_id = $this->userInfo['school_id'];
            } else {
                return ['code' => 0, 'msg' => '学校管理员学校ID设置错误' ];
            }
            $school = Db::name('SysSchool')->field('id, region_id, school_type, school_attr')->find($school_id);
            if(!$school){
                return ['code' => 0, 'msg' => '学校管理员学校ID关联学校不存在' ];
            }
            $data = [];

            $where = [];
            $where[] = ['a.deleted', '=', 0];
            $where[] = ['a.voided', '=', 0];//没有作废
            $where[] = ['a.school_attr', '=', $school['school_attr']];
            $where[] = ['a.school_type', '=', $school['school_type']];
            $where[] = ['a.prepared', '=', 1];//预录取
            $where[] = ['a.resulted', '=', 1];//录取
            $where[] = ['a.offlined', '=', 0];//不是线下录取
            $where[] = ['a.admission_type', '>', 0];
            $where[] = ['a.result_school_id', '=', $school_id];//录取学校
            //公办
            if($school['school_attr'] == 1) {
                //录取人数
                $admission_count = Db::name('UserApply')->alias('a')->where($where)->count();
                $data['admission_count'] = $admission_count;
                //入学报到人数
                $where[] = ['a.signed', '=', 1];
                $signed_count = Db::name('UserApply')->alias('a')->where($where)->count();
                $data['signed_count'] = $signed_count;
            }
            //民办
            if($school['school_attr'] == 2) {
                //预录取人数
                $prepared_count = Db::name('UserApply')->alias('a')->where($where)->where('a.paid', 0)->count();
                $data['prepared_count'] = $prepared_count;

                //录取人数【已缴费】
                $where[] = ['a.paid', '=', 1];
                $admission_count = Db::name('UserApply')->alias('a')->where($where)->count();
                $data['admission_count'] = $admission_count;

                //入学报到人数
                $where[] = ['a.signed', '=', 1];
                $signed_count = Db::name('UserApply')->alias('a')->where($where)->count();
                $data['signed_count'] = $signed_count;

                //总费用
                $pay_where = [];
                $pay_where[] = ['deleted', '=', 0];
                $pay_where[] = ['status', '=', 1];
                $pay_where[] = ['refund_status', '=', 0];
                $pay_where[] = ['school_id', '=', $school_id];
                $data['total_fee'] = Db::name('UserCostPay')->where($pay_where)->sum('pay_amount');
            }

            //批复学位数量
            $degree_count = Db::name('PlanApply')->where('school_id', '>', 0)
                ->where('school_id', $school_id)->where('status', 1)
                ->where('deleted', 0)->group('school_id')->sum('spare_total');
            $data['degree_count'] = $degree_count;

            return ['code' => 1, 'data' => $data ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //状态数据
    function getViewData(): array
    {
        //年龄情况
        $data['age_list'] = ['1' => '足龄', '2' => '年龄不足', '3' => '年龄不符', ];
        //监护人关系
        $data['relation_list'] = ['1' => '属实', '2' => '不属实', '3' => '未比对成功'];
        //填报监护人关系
        $data['fill_relation_list'] = ['1' => '父亲', '2' => '母亲', '3' => '爷爷', '4' => '奶奶', '5' => '外公', '6' => '外婆', '0' => '其他'];
        //户籍情况
        $data['birthplace_list'] = ['1' => '主城区', '2' => '非主城区', '3' => '襄阳市非本区', '4' => '非襄阳市', '5' => '未比对成功'];
        //房产情况
        $data['house_list'] = ['1' => '有匹配学校', '2' => '无匹配学校', '3' => '未比对成功'];
        //三证情况
        $data['three_syndromes_list'] = ['0' => '三证全无', '1' => '营业执照', '2' => '社保', '3' => '居住证', '4' => '未比对成功'];
        //学籍情况
        $data['school_roll_list'] = ['1' => '本区学籍', '2' => '襄阳市非本区', '3' => '无学籍信息', ];
        //房产类型
        $data['house_type_list'] = ['1' => '产权房', '2' => '租房', '3' => '自建房', '4' => '置换房', '5' => '公租房', '6' => '三代同堂',];
        //申请状态
        $data['apply_status_list'] = ['1' => '未录取', '2' => '公办预录取', '3' => '公办录取', '8' => '公办入学报到', '4' => '民办落选', '5' => '民办录取',
            '6' => '民办已缴费', '9' => '民办已退费', '7' => '民办入学报到'];
        //作废情况
        $data['voided_list'] = ['1' => '已作废', ];
        //单双符状态
        $data['single_double_list'] = ['1' => '房产单符', '2' => '双符'];
        //验证状态
        $data['verification_list'] = ['0' => '符合', '1' => '不符合'];
        //民办退费状态
        $data['refund_list'] = ['0' => '无申请', '1' => '未处理', '2' => '学校通过', '3' => '学校拒绝', '4' => '教育局通过', '5' => '教育局拒绝'];
        //预录取状态
        $data['prepare_status_list'] = ['0' => '未确认', '1' => '已确认'];
        //补充资料状态
        $data['supplement_status_list'] = ['0' => '资料补充中', '1' => '资料已补充'];
        //线上面审被拒次数
        $data['refuse_count_list'] = ['0' => '0次被拒', '1' => '一次被拒','2' => '二次被拒','3' => '三次被拒'];
        //是否民办落选
        $data['primary_lost_status'] = ['0' => '否', '1' => '是'];
        //是否区县条件符合
        $data['factor_region_status'] = ['0' => '条件不符', '1' => '条件符合'];

        return ['code' => 1, 'data' => $data,];
    }

    //房产、证件类型数据
    function getTypeData(): array
    {
        //房产类型
        $data['house_list'] = ['1' => '产权房', '2' => '租房', '3' => '自建房', '4' => '置换房', '5' => '公租房', '6' => '三代同堂',];
        //证件类型
        $data['code_list'] = ['1' => '房产证', '2' => '不动产证', '3' => '网签合同', '4' => '公租房', ];
        //关联关系
        $data['code_react_list'] = [
            '1' => ['0' => '-', '1' => '房产证', '2' => '不动产证', '3' => '网签合同'],
            '2' => ['0' => '-', ],
            '3' => ['0' => '-', '1' => '房产证', '2' => '不动产证', ],
            '4' => ['0' => '-', '1' => '房产证', '2' => '不动产证', ],
            '5' => ['0' => '-', ],
            '6' => ['0' => '-', '1' => '房产证', '2' => '不动产证', '3' => '网签合同'],
        ];

        return ['code' => 1, 'data' => $data,];
    }

    //获取学校负责人账号信息
    function getSchoolMainAccount($manage_list, $school_id): array
    {
        $manage = ['admin_name' => '', 'admin_mobile' => '', ];
        foreach ((array)$manage_list as $k => $v){
            if($v['disabled'] == 0 && $v['deleted'] == 0 && $v['school_id'] == $school_id ){
                $manage = ['admin_name' => $v['real_name'], 'admin_mobile' => $v['user_name'], ];
                if($v['main_account'] == 1){
                    $manage = ['admin_name' => $v['real_name'], 'admin_mobile' => $v['user_name'], ];
                    break;
                }
            }
        }
        return $manage;
    }

    //查看学生详细信息
    function getChildDetails($user_apply_id, $edit_status = 0): array
    {
        try {
            $region = Cache::get('region');
            $dictionary = new FilterData();
            $typeData = $dictionary->resArray('dictionary','SYSXXLX');
            if(!$typeData['code']){
                throw new \Exception($typeData['msg']);
            }
            $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
            if(!$attrData['code']){
                throw new \Exception($attrData['msg']);
            }

            $view = $this->getTypeData();
            if($view['code'] == 0){
                throw new \Exception($view['msg']);
            }

            $data = [];
            $result = Db::name('UserApply')->where('id', $user_apply_id)
                //->where('deleted', 0)
                ->find();
            if(!$result){
                throw new \Exception('无申请资料信息');
            }

            $regionData = filter_value_one($region, 'id', $result['region_id']);
            if (count($regionData) > 0){
                $data['basic']['region_name'] = $regionData['region_name'];
            }
            $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $result['school_type']);
            if (count($schoolTypeData) > 0){
                $data['basic']['school_type_name'] = $schoolTypeData['dictionary_name'];
            }
            $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $result['school_attr']);
            if (count($schoolAttrData) > 0){
                $data['basic']['school_attr_name'] = $schoolAttrData['dictionary_name'];
            }

            //学生信息
            $data['child'] = Db::name('UserChild')->field(
                ['real_name', 'mobile', 'idcard', 'api_name','api_area', 'api_address', 'api_policestation',
                    'CASE sex WHEN 1 THEN "男" WHEN 2 THEN "女" ELSE "-" END' => 'sex',
                    'picurl', 'api_card', 'api_relation', 'kindgarden_name'])
                ->where('id', $result['child_id'])->where('deleted', 0)->find();
            if($data['child']){
                if($data['child']['idcard']){
                    //$data['child']['administrative_code'] = substr($data['child']['idcard'],0,6);
                    $api_sex = x_getSexByIdcard($data['child']['idcard']);
                    $api_sex_name = '-';
                    if($api_sex == 1) $api_sex_name = '男';
                    if($api_sex == 2) $api_sex_name = '女';
                    $data['child']['api_sex'] = $api_sex_name;
                }
                //与户主关系
                if($data['child']['api_relation']) {
                    $data['child']['api_relation'] = getRelationByHgx($data['child']['api_relation']);
                }else{
                    $data['child']['api_relation'] = '-';
                }
            }

            //监护人信息
            $data['family'] = Db::name('UserFamily')->hidden(
                ['deleted',  'create_time', 'is_area_main',])
                ->where('id', $result['family_id'])->where('deleted', 0)->find();
            if($data['family']){
                if($data['family']['idcard']){
                    //$data['family']['administrative_code'] = substr($data['family']['idcard'],0,6);
                    $api_sex = x_getSexByIdcard($data['family']['idcard']);
                    $api_sex_name = '-';
                    if($api_sex == 1) $api_sex_name = '男';
                    if($api_sex == 2) $api_sex_name = '女';
                    $data['family']['api_sex'] = $api_sex_name;
                }
                if($data['family']['api_region_id']){
                    $regionData = filter_value_one($region, 'id', $data['family']['api_region_id']);
                    if (count($regionData) > 0){
                        $data['family']['administrative_code'] = $regionData['simple_code'];
                    }
                }
                //与户主关系
                if($data['family']['api_relation']) {
                    $data['family']['api_relation'] = getRelationByHgx($data['family']['api_relation']);
                }else{
                    $data['family']['api_relation'] = '-';
                }
                if($data['family']['relation'] == 1) {
                    $data['family']['relation_name'] = '父亲';
                }elseif($data['family']['relation'] == 2) {
                    $data['family']['relation_name'] = '母亲';
                }else{
                    $data['family']['relation_name'] = '其他';
                }
            }

            //祖辈信息
            if($result['ancestor_id']){
                $data['ancestor'] = Db::name('UserFamily')->hidden(
                    ['deleted',  'create_time', 'is_area_main',])
                    ->where('id', $result['ancestor_id'])->where('deleted', 0)->find();
//                if($data['ancestor']['idcard']){
//                    $data['ancestor']['administrative_code'] = substr($data['ancestor']['idcard'],0,6);
//                }
                if($data['ancestor']['api_region_id']){
                    $regionData = filter_value_one($region, 'id', $data['ancestor']['api_region_id']);
                    if (count($regionData) > 0){
                        $data['ancestor']['administrative_code'] = $regionData['simple_code'];
                    }
                }
                //与户主关系
                if($data['ancestor']['api_relation']) {
                    $data['ancestor']['api_relation'] = getRelationByHgx($data['ancestor']['api_relation']);
                }else{
                    $data['ancestor']['api_relation'] = '-';
                }
                if($data['ancestor']['relation'] == 3) {
                    $data['ancestor']['relation_name'] = '爷爷';
                }elseif($data['ancestor']['relation'] == 4) {
                    $data['ancestor']['relation_name'] = '奶奶';
                }elseif($data['ancestor']['relation'] == 5) {
                    $data['ancestor']['relation_name'] = '外公';
                }elseif($data['ancestor']['relation'] == 6) {
                    $data['ancestor']['relation_name'] = '外婆';
                }else {
                    $data['ancestor']['relation_name'] = '其他';
                }
            }

            //房产信息
            $house = Db::name('UserHouse')->hidden(['deleted', 'create_time',])
                ->where('id', $result['house_id'])->where('deleted', 0)->find();
            if($house){
                $house_type_name = isset($view['data']['house_list'][$house['house_type']]) ? $view['data']['house_list'][$house['house_type']] : '-';
                $code_type_name = isset($view['data']['code_list'][$house['code_type']]) ? $view['data']['code_list'][$house['code_type']] : '-';
                $house['house_type_name'] = $house_type_name;
                $house['code_type_name'] = $code_type_name;
                if($house['code_type'] == 0){
                    $house['code_type'] = '';
                }
                if($house['attachment']){
                    $house['attachment'] = explode(',', str_replace('-s.', '.', $house['attachment']));
                }

                //租房
                if($house['house_type'] == 2){
                    //经商情况
                    $company = Db::name('UserCompany')->field(['house_id', 'org_code', 'attachment',])
                        ->where('house_id', $result['house_id'])->where('deleted', 0)->find();
                    if($company){
                        if($company['attachment']) {
                            $house['renting'][] = [
                                'title' => '营业执照',
                                'attachment' => explode(',', str_replace('-s.', '.', $company['attachment'])),
                            ];
                        }
                        $house['org_code'] = $company['org_code'];
                    }
                    //社保
                    $insurance = Db::name('UserInsurance')->field(['house_id', 'social_code', 'attachment',])
                        ->where('house_id', $result['house_id'])->where('deleted', 0)->find();
                    if($insurance){
                        if($insurance['attachment']) {
                            $house['renting'][] = [
                                'title' => '社保',
                                'attachment' => explode(',', str_replace('-s.', '.', $insurance['attachment'])),
                            ];
                        }
                        $house['social_code'] = $insurance['social_code'];
                    }
                    //居住证
                    $residence = Db::name('UserResidence')->field(['house_id', 'live_code', 'attachment',])
                        ->where('house_id', $result['house_id'])->where('deleted', 0)->find();
                    if($residence){
                        if($residence['attachment']) {
                            $house['renting'][] = [
                                'title' => '居住证',
                                'attachment' => explode(',', str_replace('-s.', '.', $residence['attachment'])),
                            ];
                        }
                        $house['live_code'] = $residence['live_code'];
                    }
                    //其他附件
                    if($house['other_attachment']){
                        $house['renting'][] = [
                            'title' => '其他附件',
                            'attachment' => explode(',', str_replace('-s.', '.', $house['other_attachment'])),
                        ];
                    }
                }

                //置换房
                if($house['house_type'] == 4){
                    if($house['attachment_replacement']) {
                        $house['replacement'][] = [
                            'title' => '置换协议',
                            'attachment' => explode(',', str_replace('-s.', '.', $house['attachment_replacement'])),
                        ];
                    }
                    if($house['attachment_demolition']) {
                        $house['replacement'][] = [
                            'title' => '拆迁附件',
                            'attachment' => explode(',', str_replace('-s.', '.', $house['attachment_demolition'])),
                        ];
                    }
                }
                unset($house['other_attachment']);
                unset($house['attachment_replacement']);
                unset($house['attachment_demolition']);
            }
            $data['house'] = $house ?? (object)array();

            //补充资料
            $data['supplement'] = Db::name('UserSupplementAttachment')->hidden(['deleted', 'create_time',])
                ->where('user_apply_id', $result['id'])->where('deleted', 0)->select()->toArray();

            //权限学校
            $role_school = $this->getSchoolIdsByRole();
            if($role_school['code'] == 0){
                throw new \Exception($role_school['msg']);
            }
            if ($this->userInfo['grade_id'] > $this->school_grade){
                array_push($role_school['school_ids'], 0);
            }
            //操作日志
            $operation = Db::name('UserApplyAuditLog')->hidden(['deleted',])->where('deleted', 0)
                ->where('user_apply_id', $result['id'])
                ->where('school_id', 'in', $role_school['school_ids']);
            if ($this->userInfo['grade_id'] == $this->school_grade){
                $operation = $operation->where('education', 0);
            }
            if ($this->userInfo['grade_id'] == $this->area_grade){
                $operation = $operation->where('education', '<', 2);
            }
            $data['operation'] = $operation->select()->toArray();
            $manage = Cache::get('manage');
            if($data['operation']){
                foreach ($data['operation'] as $k => $v){
                    $manageData = filter_value_one($manage, 'id', $v['admin_id']);
                    if (count($manageData) > 0){
                        $data['operation'][$k]['real_name'] = $manageData['real_name'];
                    }
                }
            }
            $school_ids = $role_school['school_ids'];
            array_push($school_ids, 0);
            //用户消息
            $list = Db::name('UserMessage')->alias('m')
                ->join([
                    'deg_user_message_batch' => 'b'
                ], 'm.sys_batch_id = b.id and b.deleted = 0 ', 'LEFT')
                ->field(['m.contents' => 'remark', 'm.create_time', 'b.manage_id',])
                ->where('m.user_apply_id', $result['id'])
                ->where('m.school_id', 'in', $school_ids )
                ->where('m.deleted', 0)->select()->toArray();
            $message_list = [];
            foreach ($list as $k => $v){
                $real_name = "";
                if($v['manage_id']) {
                    $manageData = filter_value_one($manage, 'id', $v['manage_id']);
                    if (count($manageData) > 0) {
                        $real_name = $manageData['real_name'];
                    }
                }
                $message_list[] = [
                    'remark' => $v['remark'],
                    'create_time' => $v['create_time'],
                    'real_name' => $real_name,
                ];
            }
//            $message_list = Db::name('UserMessage')
//                ->field(['contents' => 'remark', 'create_time'])
//                ->where('user_apply_id', $result['id'])
//                ->where('school_id', 'in', $school_ids )
//                ->where('deleted', 0)->select()->toArray();
            $operation = array_merge($data['operation'], $message_list);
            $data['operation'] = $this->arraySort($operation, 'create_time', SORT_DESC);

            //操作状态
            $operation_status = Db::name('UserApplyStatus')
                //->where('deleted', 0)
                ->where('user_apply_id', $result['id'])->find();
            $status = [];
            if($operation_status){
                //状态是否可编辑
//                if($edit_status){
//                    $status['check_relation']['is_edit'] = false;
//                    if($operation_status['auto_check_relation'] == 0){
//                        $status['check_relation']['is_edit'] = true;
//                    }
//                    $status['check_birthplace_area']['is_edit'] = false;
//                    if($operation_status['auto_check_birthplace_area'] <= 0){
//                        $status['check_birthplace_area']['is_edit'] = true;
//                    }
//                    $status['check_birthplace_main_area']['is_edit'] = false;
//                    if($operation_status['auto_check_birthplace_main_area'] <= 0){
//                        $status['check_birthplace_main_area']['is_edit'] = true;
//                    }
//                    $status['check_house_area']['is_edit'] = false;
//                    if($operation_status['auto_check_house_area'] <= 0){
//                        $status['check_house_area']['is_edit'] = true;
//                    }
//                    $status['check_house_main_area']['is_edit'] = false;
//                    if($operation_status['auto_check_house_main_area'] <= 0){
//                        $status['check_house_main_area']['is_edit'] = true;
//                    }
//                    $status['check_company']['is_edit'] = false;
//                    if($operation_status['auto_check_company'] <= 0){
//                        $status['check_company']['is_edit'] = true;
//                    }
//                    $status['check_insurance']['is_edit'] = false;
//                    if($operation_status['auto_check_insurance'] <= 0){
//                        $status['check_insurance']['is_edit'] = true;
//                    }
//                    $status['check_residence']['is_edit'] = false;
//                    if($operation_status['auto_check_residence'] <= 0){
//                        $status['check_residence']['is_edit'] = true;
//                    }
//                    $status['house_code']['is_edit'] = false;
//                    if($operation_status['auto_check_house_area'] <= 0){
//                        $status['house_code']['is_edit'] = true;
//                    }
//                }else{
//                    $status['check_relation']['is_edit'] = false;
//                    $status['check_birthplace_area']['is_edit'] = false;
//                    $status['check_birthplace_main_area']['is_edit'] = false;
//                    $status['check_house_area']['is_edit'] = false;
//                    $status['check_house_main_area']['is_edit'] = false;
//                    $status['check_company']['is_edit'] = false;
//                    $status['check_insurance']['is_edit'] = false;
//                    $status['check_residence']['is_edit'] = false;
//                    $status['house_code']['is_edit'] = false;
//                }
//                $status['check_relation']['value'] = ($operation_status['auto_check_relation'] == 1 || $operation_status['check_relation'] == 1) ? true : false;
//                $status['check_birthplace_area']['value'] = ($operation_status['auto_check_birthplace_area'] == 1 || $operation_status['check_birthplace_area'] == 1) ? true : false;
//                $status['check_birthplace_main_area']['value'] = ($operation_status['auto_check_birthplace_main_area'] == 1 || $operation_status['check_birthplace_main_area']) ? true : false;
//                $status['check_house_area']['value'] = ($operation_status['auto_check_house_area'] == 1 || $operation_status['check_house_area'] == 1 ) ? true : false;
//                $status['check_house_main_area']['value'] = ($operation_status['auto_check_house_main_area'] == 1 || $operation_status['check_house_main_area'] == 1 ) ? true : false;
//                $status['check_company']['value'] = ($operation_status['auto_check_company'] == 1 || $operation_status['check_company'] == 1 ) ? true : false;
//                $status['check_insurance']['value'] = ($operation_status['auto_check_insurance'] == 1 || $operation_status['check_insurance'] == 1) ? true : false;
//                $status['check_residence']['value'] = ($operation_status['auto_check_residence'] == 1 || $operation_status['check_residence'] == 1) ? true : false;

                if($edit_status) {
                    $status['check_relation']['is_edit'] = true;
                    $status['check_birthplace_area']['is_edit'] = true;
                    $status['check_birthplace_main_area']['is_edit'] = true;
                    $status['check_house_area']['is_edit'] = true;
                    $status['check_house_main_area']['is_edit'] = true;
                    $status['check_company']['is_edit'] = true;
                    $status['check_insurance']['is_edit'] = true;
                    $status['check_residence']['is_edit'] = true;
                    $status['house_code']['is_edit'] = true;

                    if($operation_status['check_relation'] != 0){
                        $check_relation = $operation_status['check_relation'];
                    }else{
                        $check_relation = $operation_status['auto_check_relation'];
                    }
                    if($operation_status['check_birthplace_area'] != 0){
                        $check_birthplace_area = $operation_status['check_birthplace_area'];
                    }else{
                        $check_birthplace_area = $operation_status['auto_check_birthplace_area'];
                    }
                    if($operation_status['check_birthplace_main_area'] != 0){
                        $check_birthplace_main_area = $operation_status['check_birthplace_main_area'];
                    }else{
                        $check_birthplace_main_area = $operation_status['auto_check_birthplace_main_area'];
                    }
                    if($operation_status['check_house_area'] != 0){
                        $check_house_area = $operation_status['check_house_area'];
                    }else{
                        $check_house_area = $operation_status['auto_check_house_area'];
                    }
                    if($operation_status['check_house_main_area'] != 0){
                        $check_house_main_area = $operation_status['check_house_main_area'];
                    }else{
                        $check_house_main_area = $operation_status['auto_check_house_main_area'];
                    }
                    if($operation_status['check_company'] != 0){
                        $check_company = $operation_status['check_company'];
                    }else{
                        $check_company = $operation_status['auto_check_company'];
                    }
                    if($operation_status['check_insurance'] != 0){
                        $check_insurance = $operation_status['check_insurance'];
                    }else{
                        $check_insurance = $operation_status['auto_check_insurance'];
                    }
                    if($operation_status['check_residence'] != 0){
                        $check_residence = $operation_status['check_residence'];
                    }else{
                        $check_residence = $operation_status['auto_check_residence'];
                    }
                    $status['check_relation']['value'] = $check_relation == 1 ? true : false;
                    $status['check_birthplace_area']['value'] = $check_birthplace_area == 1 ? true : false;
                    $status['check_birthplace_main_area']['value'] = $check_birthplace_main_area == 1 ? true : false;
                    $status['check_house_area']['value'] = $check_house_area == 1 ? true : false;
                    $status['check_house_main_area']['value'] = $check_house_main_area == 1 ? true : false;
                    $status['check_company']['value'] = $check_company == 1 ? true : false;
                    $status['check_insurance']['value'] = $check_insurance == 1 ? true : false;
                    $status['check_residence']['value'] = $check_residence == 1 ? true : false;
                }else{
                    $status['check_relation']['is_edit'] = false;
                    $status['check_birthplace_area']['is_edit'] = false;
                    $status['check_birthplace_main_area']['is_edit'] = false;
                    $status['check_house_area']['is_edit'] = false;
                    $status['check_house_main_area']['is_edit'] = false;
                    $status['check_company']['is_edit'] = false;
                    $status['check_insurance']['is_edit'] = false;
                    $status['check_residence']['is_edit'] = false;
                    $status['house_code']['is_edit'] = false;

                    $status['check_relation']['value'] = ($operation_status['auto_check_relation'] == 1 || $operation_status['check_relation'] == 1) ? true : false;
                    $status['check_birthplace_area']['value'] = ($operation_status['auto_check_birthplace_area'] == 1 || $operation_status['check_birthplace_area'] == 1) ? true : false;
                    $status['check_birthplace_main_area']['value'] = ($operation_status['auto_check_birthplace_main_area'] == 1 || $operation_status['check_birthplace_main_area']) ? true : false;
                    $status['check_house_area']['value'] = ($operation_status['auto_check_house_area'] == 1 || $operation_status['check_house_area'] == 1 ) ? true : false;
                    $status['check_house_main_area']['value'] = ($operation_status['auto_check_house_main_area'] == 1 || $operation_status['check_house_main_area'] == 1 ) ? true : false;
                    $status['check_company']['value'] = ($operation_status['auto_check_company'] == 1 || $operation_status['check_company'] == 1 ) ? true : false;
                    $status['check_insurance']['value'] = ($operation_status['auto_check_insurance'] == 1 || $operation_status['check_insurance'] == 1) ? true : false;
                    $status['check_residence']['value'] = ($operation_status['auto_check_residence'] == 1 || $operation_status['check_residence'] == 1) ? true : false;
                }

            }
            $data['operation_status'] = $status ?? (object)array();

            return ['code' => 1, 'data' => $data];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //批量搜索模板
    function getBatchCondition(): array
    {
        try {
            set_time_limit(0);
            $data = [];
            if(!isset($_FILES['file']) ){
                $data = Cache::get('importBatchCardIds_'.$this->userInfo['manage_id'] , []);
                return ['code' => 1, 'data' => $data];
                /*if($data) {
                }else{
                    throw new \Exception('请上传批量查询文件！');
                }*/
            }
            $file_size = $_FILES['file']['size'];
            if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
            }
            //限制上传表格类型
            $fileExtendName = substr(strrchr($_FILES['file']["name"], '.'), 1);
            if ( $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                throw new \Exception('必须为excel表格xls或xlsx格式');
            }
            if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                if ($fileExtendName == 'xls') {
                    $objReader = IOFactory::createReader('Xls');
                } else {
                    $objReader = IOFactory::createReader('Xlsx');
                }

                $filename = $_FILES['file']['tmp_name'];
                $objPHPExcel = $objReader->load($filename);
                $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                $highestRow = $sheet->getHighestRow();       // 取得总行数

                $student_name = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
                if($student_name != '姓名') {
                    throw new \Exception('导入模板错误！');
                }

                $id_card = $objPHPExcel->getActiveSheet()->getCell("B1")->getValue();
                if($id_card != '身份证号') {
                    throw new \Exception('导入模板错误！');
                }

                for ($j = 2; $j <= $highestRow; $j++) {
                    $id_card = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();

                    if(trim($id_card) !== '' ) {
                        $data[] = strtoupper(trim($id_card,"\0\t\n\x0B\r "));
                    }
                }
            }

            Cache::set('importBatchCardIds_'.$this->userInfo['manage_id'], $data);
            return ['code' => 1, 'data' => $data ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }

    //自动发送消息
    protected function sendAutoMessage($data, $list = []): array
    {
        try {
            $max_id = Db::name('UserMessageBatch')->master(true)->where('deleted', 0)->max('id');
            $temp_num = 100000000;
            $new_num = $max_id + $temp_num + 1;
            $sys_batch_code = 'TZ'.substr($new_num,1,8);

            $batch = [];
            $batch['department_id'] = $this->userInfo['department_id'];
            $batch['sys_message_id'] = 0;
            $batch['sys_batch_code'] = $sys_batch_code;
            $batch['manage_id'] = $this->userInfo['manage_id'];
            $result = (new UserMessageBatch())->addData($batch, 1);
            if($result['code'] == 0){
                throw new \Exception($result['msg']);
            }
            $sys_batch_id = $result['insert_id'];

            $title = '';
            $content = '';
            $sys_message_id = 0;
            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['status', '=', 1];
            switch ($data['type']){
                //线上审核通过
                case 1:
                    $title = '线上审核申请通过';
                    $content = "（child_name）符合（area_name）就学条件，请等待入学通知。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //线上审核拒绝
                case 2:
                    $title = '线上审核申请拒绝';
                    $content = "（child_name）不符合（school_name）就学条件，请选择其他学校进行申请。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //线上审核补充资料
                case 3:
                    $title = '线上审核补充资料';
                    $content = "（child_name）资料提供不充分，请登陆招生平台“个人中心”-“补充资料”处，在线提交补充的资料。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //线上审核三次被拒
                case 4:
                    $title = '线上审核第三次申请拒绝';
                    $content = "（child_name）不符合（school_name）就学条件，请等待平台下一步操作通知。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //变更区域审核通过
                case 5:
                    $title = '变更入学区域申请通过';
                    $content = "（child_name）您的申请已经通过审核，请在招生平台“入学申请”中进行填报。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //变更区域审核未通过
                case 6:
                    $title = '变更入学区域申请未通过';
                    $content = "（child_name）您的申请未通过审核，如有疑问可联系教育局进行咨询。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //验证不符
                case 7:
                    $title = '预录取验证不符合';
                    $content = "（child_name）不符合（school_name）就学条件，请选择其他学校进行审核。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //到校审核通过
                case 8:
                    $title = '到校审核通过';
                    $content = "（child_name）符合（area_name）就学条件，请等待入学通知。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //到校审核被拒绝
                case 9:
                    $title = '到校审核被拒绝';
                    $content = "（child_name）不符合（school_name）就学条件。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //到校审核补充资料
                case 10:
                    $title = '到校审核补充资料';
                    $content = "（child_name）资料提供不充分，请登陆招生平台“个人中心”-“补充资料”处，在线提交补充的资料。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //退费审核通过
                case 11:
                    $title = '民办退费审核通过';
                    $content = "（child_name）您的退费已成功，请关注平台相关信息。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
                //退费审核被拒绝
                case 12:
                    $title = '民办退费审核不通过';
                    $content = "（child_name）您的退费未成功，请联系当地教育局进行咨询。";
                    $sys_message_id = Db::name('SysMessage')->where('title', $title)->where($where)->value('id');
                    $sys_message_id = $sys_message_id ?? 0;
                    break;
            }

            $push_object = [];
            if($list){
                $user_message = [];
                foreach ($list as $k => $v){
                    if(isset($v['child_name'])) {
                        $content = preg_replace('/child_name/', $v['child_name'], $content);
                    }
                    if(isset($v['area_name'])) {
                        $content = preg_replace('/area_name/', $v['area_name'], $content);
                    }
                    if(isset($v['school_name'])) {
                        $content = preg_replace('/school_name/', $v['school_name'], $content);
                    }
                    //用户消息内容
                    $user_message[] = [
                        'user_id' => $v['user_id'],
                        'user_apply_id' => $v['user_apply_id'],
                        'mobile' => $v['mobile'],
                        'sys_batch_id' => $sys_batch_id,
                        'sys_batch_code' => $sys_batch_code,
                        'sys_message_id' => $sys_message_id,
                        'school_id' => $v['school_id'] ?? 0,
                        'title' => $title,
                        'contents' => $content,
                    ];
                    //app推送消息
                    $push_object[] = [
                        "phone" => $v['mobile'],
                        "content" => $content,
                        "url" => "",//跳转url,为空时，取msgUrl的值
                    ];
                }
                Db::name('UserMessage')->insertAll($user_message);
            }else {
                if(isset($data['child_name'])) {
                    $content = preg_replace('/child_name/', $data['child_name'], $content);
                }
                if(isset($data['area_name'])) {
                    $content = preg_replace('/area_name/', $data['area_name'], $content);
                }
                if(isset($data['school_name'])) {
                    $content = preg_replace('/school_name/', $data['school_name'], $content);
                }

                $user_message = [
                    'user_id' => $data['user_id'],
                    'user_apply_id' => $data['user_apply_id'],
                    'mobile' => $data['mobile'],
                    'sys_batch_id' => $sys_batch_id,
                    'sys_batch_code' => $sys_batch_code,
                    'sys_message_id' => $sys_message_id,
                    'school_id' => $data['school_id'] ?? 0,
                    'title' => $title,
                    'contents' => $content,
                ];
                $result = (new UserMessage())->addData($user_message);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }
                //app推送消息
                $push_object[] = [
                    "phone" => $data['mobile'],
                    "content" => $content,
                    "url" => "",//跳转url,为空时，取msgUrl的值
                ];
            }
            //调取发送消息接口
            $msg_url = "/sga/sgah5/enrollStudent/#/pages/news/news";
            $appMsg = new AppMsg();
            $result = $appMsg->send_msg(1, $sys_batch_code, $title, $push_object, '', $msg_url);
            if($result['code'] == 1){
                $update = (new UserMessageBatch())->editData(['id' => $sys_batch_id, 'sended' => 1]);
                if($update['code'] == 0){
                    throw new \Exception($update['msg']);
                }
            }

            return ['code' => 1, 'msg' => '消息发送成功！'];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }
    }

    //获取三证情况
    protected function getThreeSyndromesName($data): string
    {
        if(isset($data['house_type']) && $data['house_type'] == 2){
            $three_syndromes_name = '';
            if (isset($data['insurance_status']) && $data['insurance_status'] == 1){
                $three_syndromes_name .= '社保，';
            }
            if (isset($data['business_license_status']) && $data['business_license_status'] == 1){
                $three_syndromes_name .= '营业执照，';
            }
            if (isset($data['residence_permit_status']) && $data['residence_permit_status'] == 1){
                $three_syndromes_name .= '居住证，';
            }
            if ($three_syndromes_name != ''){
                $three_syndromes_name = rtrim($three_syndromes_name, '，');
            }
            if($three_syndromes_name == ''){
                if (isset($data['three_syndromes_status']) && $data['three_syndromes_status'] == 1){
                    $three_syndromes_name = '未比对成功';
                }else {
                    $three_syndromes_name = '三证全无';
                }
            }
        }else{
            $three_syndromes_name = '-';
        }

        return $three_syndromes_name;
    }

    /**
     * 二维数组根据某个字段排序
     * @param array $array 要排序的数组
     * @param string $keys   要排序的键字段
     * @param string $sort  排序类型  SORT_ASC     SORT_DESC
     * @return array 排序后的数组
     */
    private function arraySort($array, $keys, $sort = SORT_DESC): array
    {
        $keysValue = [];
        foreach ($array as $k => $v) {
            $keysValue[$k] = $v[$keys];
        }
        array_multisort($keysValue, $sort, $array);
        return $array;
    }

}