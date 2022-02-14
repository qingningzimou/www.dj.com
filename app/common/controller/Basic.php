<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 0:57
 */
namespace app\common\controller;

use dictionary\FilterData;
use think\App;
use think\exception\ValidateException;
use think\facade\Cache;
use app\common\model\SysConfig;
use app\common\model\SysAffair;
use app\common\model\SysDictionary;
use app\common\model\SysForbidIp;
use think\facade\Lang;
use think\response\Json;
use think\facade\Log;
use think\facade\Db;
use think\facade\Config;
abstract class Basic
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;
    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;
    /**
     * 构造方法
     * @access public
     * @param App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    /**
     *初始化
     */
    public function initialize()
    {
        try {
            //  获取系统配置
            $this->setConfig();
            //  获取事务配置
            $this->setAffair();
            //  获取字典配置
            $this->setDictionary();
            //  如果系统配置开启ip过滤功能
            if (Cache::get('enable_forbid_ip')) {
                //  检测当前访问IP是否在禁止IP段中
                $checkForbidIp = self::checkForbidIp($this->request->ip());
                if ($checkForbidIp) {
                    die(Lang::get('block_access'));
                }
            }
            //  如果baidu智能Token过期
            if (Cache::get('baidu_refresh')+20*24*60*60 < time()) {
                (new RedisLock ())->lock(Config::get('cache.stores.redis.prefix').'baidu_refresh_lock', $expire = 5, $num = 0);
                $url = 'https://aip.baidubce.com/oauth/2.0/token';
                $post_data['grant_type']   = 'client_credentials';
                $post_data['client_id'] = Cache::get('baidu_apikey');
                $post_data['client_secret'] = Cache::get('baidu_secret');
                $getData = httpPost($url, $post_data);
                if ($getData) {
                    $getData = json_decode($getData, true);
                    if(array_key_exists('access_token',$getData)){
                        Cache::set('baidu_token',$getData['access_token']);
                        Cache::set('baidu_refresh',time());
                        Db::name('SysConfig')
                            ->where('item_key','baidu_token')
                            ->update([
                                'item_value' => $getData['access_token'],
                            ]);
                        Db::name('SysConfig')
                            ->where('item_key','baidu_refresh')
                            ->update([
                                'item_value' => time(),
                            ]);
                    }
                }
                (new RedisLock ())->unlock(Config::get('cache.stores.redis.prefix').'baidu_refresh_lock') ;
            }
            //  如果ixy推送Token过期
            if (Cache::get('push_refresh')+2*24*60*60 < time()) {
                (new RedisLock ())->lock(Config::get('cache.stores.redis.prefix').'ixypush_refresh_lock', $expire = 5, $num = 0);
                $push_url = Cache::get('push_url');
                $push_url  = $push_url.'/token/getToken';
                $post_data['username'] = Cache::get('push_user');
                $post_data['password'] = Cache::get('push_pass');
                $getData = httpPost($push_url, $post_data);
                if ($getData) {
                    $getData = json_decode($getData, true);
                    if($getData['state']){
                        $push_refresh = time();
                        Cache::set('push_token',$getData['token']);
                        Cache::set('push_refresh',$push_refresh);
                        Db::name('SysConfig')
                            ->where('item_key','push_token')
                            ->update([
                                'item_value' => $getData['token'],
                            ]);
                        Db::name('SysConfig')
                            ->where('item_key','push_refresh')
                            ->update([
                                'item_value' => $push_refresh,
                            ]);
                    }
                }else{
                    Log::record('i襄阳认证Token获取失败');
                }
                (new RedisLock ())->unlock(Config::get('cache.stores.redis.prefix').'ixypush_refresh_lock') ;
            }
        } catch (\Exception $exception) {
            Log::record($exception->getMessage());
        }
    }

    /**
     * 获取系统配置
     */
    private function setConfig()
    {
        try {
            if (!Cache::has('configed')) {
                (new RedisLock ())->lock(Config::get('cache.stores.redis.prefix').'get_config_lock', $expire = 5, $num = 0);
                //  获取所有映射参数
                $configList = (new SysConfig())
                    ->field(['item_key','item_value'])
                    ->select()->toArray();
                //  将系统配置写入缓存
                foreach ($configList as $item=> $value) {
                    if ($value['item_key']){
                        Cache::set($value['item_key'], $value['item_value']);
                    }
                }
                Cache::set('configed', 1);
                (new RedisLock ())->unlock(Config::get('cache.stores.redis.prefix').'get_config_lock') ;
            }
        } catch (\Exception $exception) {
            Log::record($exception->getMessage());
        }
    }
    /**
     * 获取字典数据
     */
    private function setDictionary()
    {
        try {
            if (!Cache::has('dictionary')) {
                (new RedisLock ())->lock(Config::get('cache.stores.redis.prefix').'get_dictionary_lock', $expire = 5, $num = 0);
                $dictionaryList = (new SysDictionary())
                    ->field([
                        'id',
                        'parent_id',
                        'dictionary_name',
                        'dictionary_code',
                        'dictionary_value',
                        'dictionary_type',
                        'order_num',
                        'remarks',
                    ])
                    ->select()->toArray();
                Cache::set('dictionary', $dictionaryList);
                (new RedisLock ())->unlock(Config::get('cache.stores.redis.prefix').'get_dictionary_lock') ;
            }
        } catch (\Exception $exception) {
            Log::record($exception->getMessage());
        }
    }
    /**
     * 获取事务配置
     */
    private function setAffair()
    {
        try {
            if (!Cache::has('affair')) {
                (new RedisLock ())->lock(Config::get('cache.stores.redis.prefix').'get_affair_lock', $expire = 5, $num = 0);
                $affairList = (new SysAffair())
                    ->field(['id','affair_controller','affair_method','node_id','actived'])
                    ->where('disabled',0)
                    ->select()->toArray();
                Cache::set('affair', $affairList);
                (new RedisLock ())->unlock(Config::get('cache.stores.redis.prefix').'get_affair_lock') ;
            }
        } catch (\Exception $exception) {
            Log::record($exception->getMessage());
        }
    }

    /**
     * 检测当前访问IP是否在禁止IP段中
     * @param $ip
     * @return bool
     */
    private function checkForbidIp($ip)
    {
        try {
            $NewIP = ip2long($ip);
            if (!Cache::has('forbid_ip')) {
                (new RedisLock ())->lock(Config::get('cache.stores.redis.prefix').'forbid_ip_lock', $expire = 5, $num = 0);
                //  获取禁止IP段列表
                $forbidList = (new SysForbidIp())
                    ->cache('forbid_list',3600)
                    ->field(['ip_start','ip_end'])
                    ->where('expires_time','>',time())
                    ->where('disabled',0)
                    ->select()->toArray();
                Cache::set('forbid_ip', $forbidList);
                (new RedisLock ())->unlock(Config::get('cache.stores.redis.prefix').'forbid_ip_lock') ;
            }
            $forbidList = Cache::get('forbid_ip');
            if(count($forbidList)){
                foreach ($forbidList as $item){
                    if ($NewIP >= $item['ip_start'] && $NewIP <= $item['ip_end']){
                        return true;
                    }
                }
            }
            return false;
        } catch (\Exception $exception) {
            Log::record($exception->getMessage());
            return false;
        }
    }
    /**
     * 验证请求数据
     * @param array $data
     * @param class $class
     * @param string $scene
     * @return array
     */
    protected function checkValidate(array $data, $class, string $scene) :array
    {
        try {
            $check = validate($class)->scene($scene)->check($data);
            if ($check === true) {
                $res = [
                    'code' => 1
                ];
            }else{
                $res = [
                    'code' => 0,
                    'msg' => validate($class)->getError()
                ];
            }
        } catch (ValidateException $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getError()
            ];
        }
        return $res;
    }

    /**
     * 格式化返回数据
     * @param array $res
     * @return Json
     */
    public function ajaxReturn(array $res) :Json
    {
        try {
            if ($this->request->refresh){
                $basic = [
                    'token' => $this->request->new_token
                ];
            }else{
                $basic = [
                    'token' => ''
                ];
            }

            if(!isset($res['data']['resources']) && $this->request->action() == 'getList' && strtolower(app('http')->getName()) == 'api'){
                $res['data']['resources'] = $this->getResources($this->userInfo, $this->request->controller());
            }
            $result = array_merge($res,$basic);
        } catch (\Exception $exception) {
            $result = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return json($result);
    }

    /**
     * 获取当前登录账户控制器方法及其它权限
     * @param $user_info
     * @param $controller_name
     * @return array
     */
    public function getResources($user_info,$controller_name): array
    {
        try {
            $user_nodes = $user_info['nodes'] ?? [];
            $nodes = Cache::get('nodes',[]);
            $nodes_data = [];
            //当前控制器下资源
            $node_data = filter_value_one(array_map(function($value){
                $value['controller_name'] = strtolower($value['controller_name']);
                return $value;
            },$nodes), 'controller_name', strtolower($controller_name));
//            $node_data = filter_value_one($nodes, 'controller_name', strtolower($controller_name));
            if (count($node_data) > 0) {
                $nodes_data = array_values(filter_by_value($nodes,'parent_id',$node_data['id']));
            }
            //鉴权资源
            if (count($nodes_data) > 0) {
                $nodes_data = array_values(filter_by_value($nodes_data,'authority',1));
            }
            //用户拥有的当前控制器下的资源
            $user_nodes_data = [];
            $user_node_data = filter_value_one(array_map(function($value){
                $value['controller_name'] = strtolower($value['controller_name']);
                return $value;
            },$user_nodes), 'controller_name', strtolower($controller_name));

            //$user_node_data = filter_value_one($user_nodes, 'controller_name', $controller_name);

            if (count($user_node_data) > 0) {
                $user_nodes_data = array_values(filter_by_value($user_nodes,'parent_id',$user_node_data['id']));
            }
            $dictionary = new FilterData();
            $school_grade = 0;
            $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSXXQX');
            if($getData['code']){
                $school_grade = $getData['data'];
            }
            if($school_grade == $user_info['grade_id']) {
                //功能模块管控
                $module = Cache::get('module');
                $theTime = time();
                $moduleNodes = [];
                foreach ($module as $item){
                    if(date_to_unixtime($item['start_time']) < $theTime && date_to_unixtime($item['end_time']) > $theTime  && $item['region_id'] == $user_info['region_id']){
                        $node_ids = Db::name('moduleNodes')->where([
                            'module_id' => $item['id'],
                            'deleted' => 0,
                        ])->column('node_id');
                        $moduleNodes = array_merge($moduleNodes,$node_ids);
                    }
                }
                $exceptionNodes = Db::name('SysSchoolNodes')->where([
                    'school_id' => $user_info['school_id'],
                    'deleted' => 0,
                ])->column('node_id');

                $moduleNodes = array_unique(array_merge($moduleNodes,$exceptionNodes));

                //获取触发管控条件的资源与用户资源的交集
                $moduleIds = array_intersect(array_column($user_nodes_data,'id'),$moduleNodes);
                foreach ($user_nodes_data as $value){
                    if(!in_array($value['id'],$moduleIds)){
                        $user_nodes_data = remove_by_value($user_nodes_data, 'id', $value['id']);
                    }
                }
                $user_nodes_data = array_values($user_nodes_data);
            }
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
            //return $res_data;
        } catch (\Exception $exception) {
            $res_data = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res_data;
    }

}