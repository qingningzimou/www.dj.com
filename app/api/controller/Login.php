<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 15:10
 */
namespace app\api\controller;
use app\common\controller\Education;
use app\common\model\Schools;
use app\common\model\User as model;
use app\common\model\Manage;
use app\common\model\SysNodes;
use app\common\model\SysRoles;
use app\common\model\SysRegion;
use app\common\model\ManageNodes;
use app\common\controller\GmCrypt;
use dictionary\FilterData;
use subTable\SysTablePartition;
use think\captcha\Captcha;
use think\facade\Cache;
use think\facade\Lang;
use think\facade\Db;
use sm\TxSm2;
use sm\TxSm4;
use sms\SmsSend;

class Login extends Education
{
    public function test()
    {
        if ($this->request->isPost()) {
            try {
                //  获取请求数据
                $userdata = $this->request->only([
                    'username',
                    'password',
                ]);
                $user_info = Db::name('user')
                    ->where('user_name', $userdata['username'])
                    ->where('deleted', 0)
                    ->find();
                //  如果不存在，则返回
                if (empty($user_info)) {
                    throw new \Exception(Lang::get('account_none'));
                }
                //  如果账号禁用，则返回
                if ($user_info['disabled'] == 1) {
                    throw new \Exception(Lang::get('account_forbid'));
                }
                //  如果达到限制失败次数，则返回
                if ($user_info['err_num'] >= 5 && (time() - strtotime($user_info['last_time'])) <3600) {
                    throw new \Exception(Lang::get('account_locked'));
                }
                $grade_id = 0;
                $role_id = 0;
                $user_info['nodes'] = [];// 用户当前资源
                $user_info['role_ids'] = [$role_id];// 用户拥有的角色
                $user_info['role_id'] = $role_id;// 用户当前角色
                $user_info['grade_id'] = $grade_id; // 用户管理职能
                $user_info['manage_id'] = 0; // 用户的管理ID
                $user_info['main_account'] = 0; // 用户主账户标识
                $user_info['defaulted'] = 0; // 初始化账户标识
                $user_info['relation_region'] = []; // 初始化账户标识
                $department = [];
                if($user_info['user_type']){
                    $where = [];
                    $manage = (new Manage())->with('relationRegion')
                        ->where($where)
                        ->where('user_id', $user_info['id'])
                        ->where('deleted', 0)
                        ->select()->toArray();
                    //  如果有管理关联
                    if (count($manage)) {
                        // 获取取管理职能最高的账户
                        $last_names = array_column($manage,'role_grade');
                        array_multisort($last_names,SORT_DESC,$manage);
                        $manageData = $manage[0];
                        //获取管理账号对应的资源
                        $node_ids = (new ManageNodes())->where('manage_id',$manageData['id'])->column('node_id');
                        $user_info['nodes'] = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->select()->toArray();
                        //更新用户信息
                        $user_info['manage_id'] = $manageData['id'];
                        $user_info['grade_id'] = $manageData['role_grade'];
                        $user_info['role_ids'] = array_merge($user_info['role_ids'],array_column($manage,'role_id'));
                        $user_info['role_id'] = $manageData['role_id'];
                        $user_info['main_account'] = $manageData['main_account'];
                        $user_info['defaulted'] = $manageData['defaulted'];
                        if($manageData['defaulted']){
                            $regionIds = (new SysRegion())->where('disabled',0)->column('id');
                            $relationRegion = [];
                            foreach ($regionIds as $region_id){
                                $relationRegion[] = [
                                    'manage_id' => $manageData['id'],
                                    'region_id' => $region_id,
                                ];
                            }
                            $user_info['relation_region'] = $relationRegion;
                        }else{
                            $user_info['relation_region'] = $manageData['relationRegion'];
                        }

                        $department = Db::name('department')->find($manageData['department_id']);
                    }
                }else{
                    //  如果维护状态，则返回
                    if (Cache::get('app_run') == 0) {
                        throw new \Exception('应用系统维护中...');
                    }
                }
                $userdata['last_ip'] = $this->request->ip();
                //  验证密码是否正确
                if (!password_verify($userdata['password'], $user_info['password'])) {
                    Db::name('user')->where('id', $user_info['id'])->inc('err_num')->update();
                    self::userLoginLog($department['region_id'], $user_info['user_name'],$user_info['real_name'],$userdata['last_ip'],1);
                    throw new \Exception(Lang::get('password_fail'));
                }else{
                    self::userLoginLog($department['region_id'], $user_info['user_name'],$user_info['real_name'],$userdata['last_ip']);
                }
                if (Cache::get('user'.md5($user_info['id']))){
                    Cache::delete('user'.md5($user_info['id']));
                    Cache::delete('user'.md5($user_info['id'].'old_token'));
                }
                $gmCrypt = new GmCrypt();
                $token = $gmCrypt->setToken($user_info['id']);
                // 更新用户登录信息
                (new model())->where('id', $user_info['id'])->update(['last_ip' => $userdata['last_ip'],'last_time' => date('Y-m-d H:i:s'),'err_num' => 0]);
                unset($user_info['password']);
                $user_info['token'] = $token;
                Cache::set('user'.md5($user_info['id']),$user_info);
                Cache::set('user'.md5($user_info['id'].'token_time'),time());
                unset($user_info['token']);
                unset($user_info['nodes']);
                //调用异步加载
                self::setCache();
                $res =  [
                    'code' => 1,
                    'data' => [
                        'token' => $token,
                    ],
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return json($res);
        }else{
            $res = [
                'code' => 0,
                'msg' => Lang::get('check_get')
            ];
            return json($res);
        }
    }
    public function index()
    {
        if ($this->request->isPost()) {
            try {
                //  获取请求数据
                $data = $this->request->only([
                    'data',
                    'key',
                ]);
                $sm2 = new TxSm2();
                $key = $sm2->privateDecrypt($data['key']);
                if (empty($key)){
                    throw new \Exception('数据错误解密失败');
                }
                $sm4 = new TxSm4();
                $userdata = json_decode($sm4->decrypt($key, $data['data']),true);
//                $userdata =  json_decode(openssl_decrypt($data['data'] , 'sm4-ecb' , $key),true);
                //  如果username数据不合法，则返回
                $preg = '/^[A-Za-z0-9_]{4,18}$/u';
                if (preg_match($preg,$userdata['username']) == 0){
                    throw new \Exception('提交的用户名不正确');
                }
                /*if (!empty($userdata['captcha'])){
                    //  如果captcha数据不合法，则返回
                    $preg = '/^[a-zA-Z0-9]{4}$/u';
                    if (preg_match($preg,$userdata['captcha']) == 0){
                        throw new \Exception(Lang::get('captcha_fail'));
                    }
                    $captcha = new Captcha();
                    if (!$captcha->check($userdata['captcha'],$userdata['id'])){
                        throw new \Exception(Lang::get('captcha_fail'));
                    }
                }else{
                    throw new \Exception(Lang::get('captcha_none'));
                }*/
                $user_info = Db::name('user')
                    ->where('user_name', $userdata['username'])
                    ->where('deleted', 0)
                    ->find();
                //  如果不存在，则返回
                if (empty($user_info)) {
                    throw new \Exception(Lang::get('account_none'));
                }
                //  如果账号禁用，则返回
                if ($user_info['disabled'] == 1) {
                    throw new \Exception(Lang::get('account_forbid'));
                }
                //  如果达到限制失败次数，则返回
                if ($user_info['err_num'] >= 5 && (time() - strtotime($user_info['last_time'])) <3600) {
                    throw new \Exception(Lang::get('account_locked'));
                }
                $grade_id = 0;
                $role_id = 0;
                $user_info['nodes'] = [];// 用户当前资源
                $user_info['role_ids'] = [];// 用户拥有的角色
                $user_info['role_id'] = $role_id;// 用户当前角色
                $user_info['grade_id'] = $grade_id; // 用户管理职能
                $user_info['manage_id'] = 0; // 用户的管理ID
                $user_info['main_account'] = 0; // 用户主账户标识
                $user_info['defaulted'] = 0; // 初始化账户标识
                $user_info['relation_region'] = []; // 初始化账户标识
                if($user_info['user_type']){
                    $where = [];
//                    //  如果运行状态检查是否为初始化账号登录
//                    if (Cache::get('app_run') == 1) {
//                        $where[] = ['defaulted','=',0];
//                    }
//                    //  如果维护状态
//                    if (Cache::get('app_run') == 0) {
//                        $where[] = ['defaulted','=',1];
//                    }
                    $manage = (new Manage())->with('relationRegion')
                        ->where($where)
                        ->where('user_id', $user_info['id'])
                        ->where('deleted', 0)
                        ->where('disabled', 0)
                        ->select()->toArray();
                    //  如果有管理关联
                    if (count($manage)) {

                        // 获取取管理职能最高的账户
                        $last_names = array_column($manage,'role_grade');
                        array_multisort($last_names,SORT_DESC,$manage);
                        $manageData = $manage[0];
                        //获取管理账号对应的资源
                        $node_ids = (new ManageNodes())->where('manage_id',$manageData['id'])->column('node_id');
                        if($manageData['defaulted']){
                            $user_info['nodes'] = (new SysNodes())->hidden(['deleted'])->select()->toArray();
                        }else{
                            $user_info['nodes'] = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->select()->toArray();
                        }
                        //获取管理账号所有角色
                        $role_ids = (new SysRoles())->whereIn('id', array_column($manage,'role_id'))->where('disabled', 0)->column('id,role_name');

                        //学校被禁用 账号不能登录
                        $dictionary = new FilterData();
                        $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSXXQX');
                        if(!$getData['code']){
                            throw new \Exception($getData['msg']);
                        }
                        $school_disabled = (new Schools())->where('id',$manageData['school_id'])->value('disabled');
                        if($school_disabled == 1 && $manageData['role_grade'] == $getData['data']){
                            throw new \Exception('学校已被禁用');
                        }
                        $user_info['role_ids'] = $role_ids;
                        //更新用户信息
                        $user_info['manage_id'] = $manageData['id'];
                        $user_info['grade_id'] = $manageData['role_grade'];
                        $user_info['school_id'] = $manageData['school_id'];
                        $user_info['role_id'] = $manageData['role_id'];
                        $user_info['defaulted'] = $manageData['defaulted'];
                        $user_info['main_account'] = $manageData['main_account'];
                        $user_info['department_id'] = $manageData['department_id'];
                        $user_info['region_id'] = $manageData['region_id'];
                        $user_info['public_school_status'] = $manageData['public_school_status'];
                        $user_info['civil_school_status'] = $manageData['civil_school_status'];
                        $user_info['primary_school_status'] = $manageData['primary_school_status'];
                        $user_info['junior_middle_school_status'] = $manageData['junior_middle_school_status'];
                        $user_info['central_id'] = $manageData['central_id'];

                        if($manageData['defaulted']){
                            $regionIds = (new SysRegion())->where('disabled',0)->column('id');
                            $relationRegion = [];
                            foreach ($regionIds as $region_id){
                                $relationRegion[] = [
                                    'manage_id' => $manageData['id'],
                                    'region_id' => $region_id,
                                ];
                            }
                            $user_info['relation_region'] = $relationRegion;
                        }else{
                            $user_info['relation_region'] = $manageData['relationRegion'];
                        }
                    }else{
                        $ck_manage = (new Manage())
                            ->where($where)
                            ->where('user_id', $user_info['id'])
                            ->where('deleted', 0)
                            ->count('id');
                        if($ck_manage){
                            throw new \Exception('管理员账号被禁用');
                        }else{
                            //  如果维护状态，则返回
                            if (Cache::get('app_run') == 0) {
                                throw new \Exception('应用系统维护中...');
                            }else{
                                throw new \Exception('管理员不存在');
                            }
                        }
                    }
                }else{
                    throw new \Exception('非管理人员不可访问');
                }
                if($userdata['ip']){
                    $userdata['last_ip'] = $userdata['ip'];
                }else{
                    $userdata['last_ip'] = '0.0.0.0';
                }
                $user_info['last_ip'] = $userdata['last_ip'];
                if (!password_verify($userdata['password'], $user_info['password'])) {
                    Db::name('user')->where('id', $user_info['id'])->inc('err_num')->update();
                    self::userLoginLog($user_info['region_id'], $user_info['user_name'],$user_info['real_name'],$userdata['last_ip'],1);
                    throw new \Exception(Lang::get('password_fail'));
                }else{
                    self::userLoginLog($user_info['region_id'], $user_info['user_name'],$user_info['real_name'],$userdata['last_ip']);
                }
                if (Cache::get('user'.md5($user_info['id']))){
                    Cache::delete('user'.md5($user_info['id']));
                    Cache::delete('user'.md5($user_info['id'].'old_token'));
                }
                $gmCrypt = new GmCrypt();
                $token = $gmCrypt->setToken($user_info['id']);
                // 更新用户登录信息
                (new model())->where('id', $user_info['id'])->update(['last_ip' => $userdata['last_ip'],'last_time' => date('Y-m-d H:i:s'),'err_num' => 0]);
                unset($user_info['password']);
                $user_info['token'] = $token;
                $user_info['last_ip'] = $userdata['last_ip'];
                Cache::set('user'.md5($user_info['id']),$user_info);
                Cache::set('user'.md5($user_info['id'].'token_time'),time());
                unset($user_info['token']);
                unset($user_info['nodes']);
                //调用异步加载
                $this->setCache();
                $res =  [
                    'code' => 1,
                    'data' => [
                        'token' => $token,
                    ],
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return json($res);
        }else{
            $res = [
                'code' => 0,
                'msg' => Lang::get('check_get')
            ];
            return json($res);
        }
    }
    /**
     * 忘记密码
     * @return \think\response\Json
     */
    public function forgetPassword()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                //  获取请求数据
                $data = $this->request->only([
                    'data',
                    'key',
                ]);
                $sm2 = new TxSm2();
                $key = $sm2->privateDecrypt($data['key']);
                if (empty($key)){
                    throw new \Exception('数据错误解密失败');
                }
                $sm4 = new TxSm4();
                $userdata = json_decode($sm4->decrypt($key, $data['data']),true);
//                $userdata =  json_decode(openssl_decrypt($data['data'] , 'sm4-ecb' , $key),true);
                //如果password数据不合法，则返回
                $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                if (preg_match($preg,$userdata['password']) == 0){
                    throw new \Exception(Lang::get('password_format_fail'));
                }
                if (!empty($userdata['verify_code'])){
                    //  如果verify_code数据不合法，则返回
                    $preg = '/^[0-9]{6}$/u';
                    if (preg_match($preg,$userdata['verify_code']) == 0){
                        throw new \Exception(Lang::get('captcha_fail'));
                    }
                    if ($userdata['verify_code'] != Cache::get('code_'.md5($userdata['id']))){
                        throw new \Exception(Lang::get('captcha_fail'));
                    }
                }else{
                    throw new \Exception(Lang::get('captcha_none'));
                }
                $user = Db::name('user')
                    ->where('user_name', $userdata['username'])
                    ->where('disabled', 0)
                    ->where('deleted', 0)
                    ->find();
                //  如果不存在，则返回
                if (empty($user)) {
                    throw new \Exception(Lang::get('account_none'));
                }
                $password = password_hash($userdata['password'], PASSWORD_DEFAULT);
                Db::name('user')
                    ->where('id', $user['id'])
                    ->update([
                        'password' => $password,
                    ]);
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
            return json($res);
        }
    }


    /**
     * 获取手机验证码
     * @return \think\response\Json
     */
    public function getCode()
    {
        if ($this->request->isPost()) {
            try {
                //  获取请求数据
                $data = $this->request->only([
                    'data',
                    'key',
                ]);
                $sm2 = new TxSm2();
                $key = $sm2->privateDecrypt($data['key']);
                if (empty($key)){
                    throw new \Exception('数据错误解密失败');
                }
                $sm4 = new TxSm4();
                $getdata = json_decode($sm4->decrypt($key, $data['data']),true);
//                $userdata =  json_decode(openssl_decrypt($data['data'] , 'sm4-ecb' , $key),true);
                //  如果username数据不合法，则返回
                $preg = '/^(13[0-9]|14[5|7]|15[0-9]|16[0-9]|17[0-9]|18[0-9])\d{8}$/u';
                if (preg_match($preg,$getdata['mobile']) == 0){
                    throw new \Exception('手机号码不正确');
                }
                if($getdata['type']){
                    $user = (new model())
                        ->where('user_name', $getdata['mobile'])
                        ->find();
                    //  如果存在，则返回
                    if (!empty($user)) {
                        throw new \Exception('手机号码已经注册');
                    }
                }
                $code = mt_rand(100,999).mt_rand(100,999);
                $sms = new SmsSend();
                $msg = "$code 是您在襄阳市干部学历呈报系统的验证码，本条验证码有效期10分钟。【顽磁科技】";
//                $res = $sms->send_sms($getdata['mobile'],$msg);
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];
                if($res['code']){
                    Cache::set('code_'.md5($getdata['id']),$code,600);
                }else{
                    throw new \Exception($res['msg']);
                }
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return json($res);
        }else{
            $res = [
                'code' => 0,
                'msg' => Lang::get('check_get')
            ];
            return json($res);
        }
    }

    /**
     * ixy授权登录
     * 系统中未注册账号，自动生成家长账号
     * @return \think\response\Json
     */

    public function ixyRegister()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                //  获取请求数据
                $data = $this->request->only([
                    'data',
                    'key',
                ]);

                $sm2 = new TxSm2();
                $key = $sm2->privateDecrypt($data['key']);
                if (empty($key)){
                    throw new \Exception('数据错误解密失败');
                }
                $sm4 = new TxSm4();
                $userdata = json_decode($sm4->decrypt($key, $data['data']),true);
                $saveData = [
                    'sgasession' => $userdata['sgasession'] ? $userdata['sgasession'] : '',
                    'identlevel' => $userdata['identlevel'] ? $userdata['identlevel'] : '',
                    'loginflag' => $userdata['loginflag'] ? $userdata['loginflag'] : '',
                    'type' => $userdata['type'] ? $userdata['type'] : '',
                    'ixy_id' => $userdata['userid'] ? $userdata['userid'] : '',
                    'user_name' => $userdata['username'] ? $userdata['username'] : '',
                    'mobile' => $userdata['username'] ? $userdata['username'] : '',
                    'last_ip' => $this->request->ip()
                ];
                if(isset($userdata['idcard']) && $userdata['idcard']){
                    $saveData['idcard'] = $userdata['idcard'];
                }
                if(isset($userdata['mobilephone']) && $userdata['mobilephone']){
                    $saveData['mobilephone'] = $userdata['mobilephone'];
                }
                if(isset($userdata['name']) && $userdata['name']){
                    $saveData['real_name'] = $userdata['name'];
                }
                if(isset($userdata['sex']) && $userdata['sex']){
                    $saveData['sex'] = $userdata['sex'];
                }

                // 检查是否存在IXY账号
                $user_info =  Db::name('user')
                    ->where('ixy_id', $saveData['ixy_id'])
                    ->where('deleted', 0)
                    ->find();
                if (empty($user_info)) {
                    //判断手机号是否已注册
                    $chkUsers = Db::name('user')->where('user_name', $userdata['username'])->where('deleted',0)->find();
                    if(empty($chkUsers)){
                        $saveData['password'] = password_hash('123456', PASSWORD_DEFAULT);
                        //$saveData['user_name'] = 'ix_'.generateRandomStrings();
                        $user_id = Db::name('user')->insertGetId($saveData);
                        $user_info = Db::name('user')->where('id',$user_id)->where('deleted',0)->find();
                    }else{
                        unset($saveData['user_name']);
                        Db::name('user')
                            ->where('id', $chkUsers['id'])
                            ->update($saveData);
                        $user_info = Db::name('user')->where('id',$chkUsers['id'])->where('deleted',0)->find();
                    }
                }else{
                    if($userdata['loginflag'] > 1 && $userdata['identlevel'] > 1){
                        unset($saveData['ixy_id']);
                        unset($saveData['user_name']);
                        Db::name('user')->where('id', $user_info['id'])->update($saveData);
                    }
                }
  /*              $getData = (new FilterData())->findValue('dictionary','SYSJSQX','SYSJBQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $grade_id = $getData['data'];
                $roles = Cache::get('roles');
                $rolesData = filter_value_one($roles, 'role_grade', $grade_id);
                $roleid = 0;
                if (count($rolesData)) {
                    $roleid = $rolesData['id'];
                }*/
                $user_info['nodes'] = [];// 用户当前资源
                $user_info['role_ids'] = [];// 用户拥有的角色
                $user_info['role_id'] = 0;// 用户当前角色
                $user_info['grade_id'] = 0; // 用户管理职能
                $user_info['nodes'] = [];
                $user_info['manage_id'] = 0;
                $user_info['main_account'] = 0;
                $user_info['defaulted'] = 0;
                $department = [];
                /*if($user_info['user_type']){
                    $where = [];
                    //  如果运行状态检查是否为初始化账号登录
                    if (Cache::get('app_run') == 1) {
                        $where[] = ['defaulted','=',0];
                    }
                    //  如果维护状态
                    if (Cache::get('app_run') == 0) {
                        $where[] = ['defaulted','=',1];
                    }
                    $manage = Db::name('manage')
                        ->where($where)
                        ->where('user_id', $user_info['id'])
                        ->where('deleted', 0)
                        ->select()->toArray();
                    //  如果有管理关联
                    if (count($manage)) {
                        // 获取默认账户
                        $manageData = filter_value_one($manage, 'mastered', 1);
                        // 如果没有默认账户取管理职能最高的账户
                        if (!count($manageData)) {
                            $last_names = array_column($manage,'role_grade');
                            array_multisort($last_names,SORT_DESC,$manage);
                            $manageData = $manage[0];
                        }
                        //获取管理账号对应的资源
                        $node_ids = (new ManageNodes())->where('manage_id',$manageData['id'])->column('node_id');
                        $user_info['nodes'] = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->select()->toArray();
                        //更新用户信息
                        $user_info['manage_id'] = $manageData['id'];
                        $user_info['grade_id'] = $manageData['role_grade'];
                        $user_info['role_ids'] = array_merge($user_info['role_ids'],array_column($manage,'role_id'));
                        $user_info['role_id'] = $manageData['role_id'];
                        $user_info['main_account'] = $manageData['main_account'];
                        $user_info['defaulted'] = $manageData['defaulted'];

                        $department = Db::name('department')->find($manageData['department_id']);
                    }
                }else{
                    //  如果维护状态，则返回
                    if (Cache::get('app_run') == 0) {
                        throw new \Exception('应用系统维护中...');
                    }
                }*/
                if (Cache::get('user'.md5($user_info['id']))){
                    Cache::delete('user'.md5($user_info['id']));
                    Cache::delete('user'.md5($user_info['id'].'old_token'));
                }
                self::userLoginLog(0, $user_info['user_name'],$user_info['real_name'],$saveData['last_ip']);
                $gmCrypt = new GmCrypt();
                $token = $gmCrypt->setToken($user_info['id']);
                // 更新用户登录信息
                (new model())->where('id', $user_info['id'])->update(['last_ip' => $saveData['last_ip'],'last_time' => date('Y-m-d H:i:s'),'err_num' => 0]);
                unset($user_info['password']);
                $user_info['token'] = $token;
                Cache::set('user'.md5($user_info['id']),$user_info);
                Cache::set('user'.md5($user_info['id'].'token_time'),time());
                unset($user_info['token']);
                unset($user_info['nodes']);
                $res =  [
                    'code' => 1,
                    'data' => [
                        'token' => $token,
                        //'role_ids' => $user_info['role_ids']
                    ],
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return json($res);
        }
    }

    /**
     * 登录信息记录
     * @param string $user_name
     * @param string $real_name
     * @param string $login_ip
     * @param string $failed
     * @return
     */
    private function userLoginLog($region_id,$user_name,$real_name,$login_ip,$failed = 0){
        try {
            $tableTmp = "deg_log_user_login";
            $logTable = (new SysTablePartition())->getTablePartition($tableTmp);
            if ($logTable['code'] != 1){
                throw new \Exception('登录日志分表错误');
            }
            /*(new LogUserLogin())->addData([
                'region_id' => $region_id,
                'user_name' => $user_name,
                'real_name' => $real_name,
                'login_ip' => $login_ip,
                'failed' => $failed,
            ]);*/
            Db::table($logTable["table_name"])->insert([
                'region_id' => $region_id,
                'user_name' => $user_name,
                'real_name' => $real_name,
                'login_ip' => $login_ip,
                'failed' => $failed,
            ]);
            $res = [
                'code' => 1,
                'msg' => '记录成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return $res;
    }
    /**
     * 异步加载缓存数据
     */
    private function setCache(){
        try {
            $sys_url = Cache::get('sys_url');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $sys_url."/api/LoadCache/setCache");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            $mh = curl_multi_init(); //1 创建批处理cURL句柄
            curl_multi_add_handle($mh, $ch); //2 增加句柄
            $active = null;
            do {
                while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM) ;
                if ($mrc != CURLM_OK) { break; }
                // a request was just completed -- find out which one
                while ($done = curl_multi_info_read($mh)) {
                    // get the info and content returned on the request
                    $info = curl_getinfo($done['handle']);
                    $error = curl_error($done['handle']);
                    $result[] = curl_multi_getcontent($done['handle']);
                    // $responses[$map[(string) $done['handle']]] = compact('info', 'error', 'results');
                    // remove the curl handle that just completed
                    curl_multi_remove_handle($mh, $done['handle']);
                    curl_close($done['handle']);
                }
                // Block for data in / output; error handling is done by curl_multi_exec
                if ($active > 0) {
                    curl_multi_select($mh);
                }
            } while ($active);
            curl_multi_close($mh); //7 关闭全部句柄
            $res =  [
                'code' => 1,
                'msg' => Lang::get('res_success'),
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