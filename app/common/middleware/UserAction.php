<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/17
 * Time: 17:53
 */
namespace app\common\middleware;
use think\facade\Cache;
use think\facade\Lang;
use app\common\controller\RedisLock;
use app\common\controller\GmCrypt;
use dictionary\FilterData;
use think\facade\Db;
use think\facade\Config;
use think\facade\Log;
class UserAction
{
    /**
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed|\think\Response
     */
    public function handle($request, \Closure $next)
    {
        //  如果是post请求
        if ($request->isPost()) {
            header('content-type:application:json;charset=utf8');
            header('Access-Control-Allow-Origin:*');
            header('Access-Control-Allow-Methods:POST');
            header('Access-Control-Allow-Headers:x-requested-with, content-type, token');
            header('Access-Control-Max-Age:3600');
            $host = $request->ip() . ' ' . $request->method() . ' ' . $request->url(true);
            Log::write($host, 'HOST');
            Log::write($request->header(), 'HEADER');
            Log::write($request->param(), 'PARAM');
            //  如果是登录，则直接跳过检测token
            if (strtolower($request->controller()) == 'login' || strtolower($request->controller()) == 'captcha' || strtolower($request->controller()) == 'task') {
                return $next($request);
            }
            //  获取token
            if (get_token()){
                $token = get_token();
            }else{
                $token = trim($request->param('token'));
            }
            //  检测token是否为空
            if (empty($token)) {
                return response([
                    'code' => 0,
                    'msg' => Lang::get('block_access')
                ],200,[],'json');
            }
            //检查token是否合法
            $checkToken = $this->checkToken($token);
            if ($checkToken['code'] != 1) {
                return response($checkToken,200,[],'json');
            }
            $check_nodes = 0;
            $node_type = 0;
            $authority = 0;
            $nodes = Cache::get('nodes',[]);
            foreach ($nodes as $key=> $value) {
                if (strtolower($value['module_name']) == strtolower(app('http')->getName()) && strtolower($value['controller_name']) == strtolower($request->controller()) && strtolower($value['method_name']) == strtolower($request->action())){
                    $node_type = $value['node_type'];
                    $authority = $value['authority'];
                    break;
                }
            }
            if($node_type){
                return response([
                    'code' => 0,
                    'msg' => Lang::get('inmode_err')
                ],200,[],'json');
            }
            if($authority){
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSXXQX');
                if(!$getData['code']){
                    return response([
                        'code' => 0,
                        'msg' => Lang::get('dictionary_err')
                    ],200,[],'json');
                }
                $school_grade = $getData['data'];
                if($school_grade == $checkToken['userInfo']['grade_id']){
                    //功能管控
                    $module = Cache::get('module');
                    $defNodes = array_values(filter_by_value($nodes, 'defaulted', 1));
                    $theTime = time();
                    $moduleNodes = [];
                    $moduleNodes = array_merge($moduleNodes,array_column($defNodes,'id'));
                    foreach ((array)$module as $item){
                        if(date_to_unixtime($item['start_time']) < $theTime && date_to_unixtime($item['end_time']) > $theTime && $item['region_id'] == $checkToken['userInfo']['region_id']){
                            $node_ids = Db::name('moduleNodes')->where([
                                'module_id' => $item['id'],
                                'deleted' => 0,
                            ])->column('node_id');
                            $moduleNodes = array_merge($moduleNodes,$node_ids);
                        }
                    }
                    $exceptionNodes = Db::name('SysSchoolNodes')->where([
                        'school_id' => $checkToken['userInfo']['school_id'],
                        'deleted' => 0,
                    ])->column('node_id');

                    $moduleNodes = array_unique(array_merge($moduleNodes,$exceptionNodes));

                    //获取触发管控条件的资源与用户资源的交集
                    $moduleIds = array_intersect(array_column($checkToken['userInfo']['nodes'],'id'),$moduleNodes);

                    foreach ($checkToken['userInfo']['nodes'] as $key=> $value) {
                        if(in_array($value['id'],$moduleIds)){
                            if (strtolower($value['module_name']) == strtolower(app('http')->getName()) && strtolower($value['controller_name']) == strtolower($request->controller())){
//                    if (strtolower($value['module_name']) == strtolower(app('http')->getName()) && strtolower($value['controller_name']) == strtolower($request->controller()) && strtolower($value['method_name']) == strtolower($request->action())){
                                $check_nodes = 1;
                                break;
                            }
                        }
                    }
                }else{
                    foreach ($checkToken['userInfo']['nodes'] as $key=> $value) {
                        if (strtolower($value['module_name']) == strtolower(app('http')->getName()) && strtolower($value['controller_name']) == strtolower($request->controller())){
//                    if (strtolower($value['module_name']) == strtolower(app('http')->getName()) && strtolower($value['controller_name']) == strtolower($request->controller()) && strtolower($value['method_name']) == strtolower($request->action())){
                            $check_nodes = 1;
                            break;
                        }
                    }
                }
            }else{
                $check_nodes = 1;
            }

           /* if($checkToken['userInfo']['defaulted'] == 1 && Cache::get('app_run') == 0){
                $check_nodes = 1;
            }*/
            if($checkToken['userInfo']['defaulted'] == 1){
                $check_nodes = 1;
            }
            if ($check_nodes == 0) {
                return response([
                    'code' => 0,
                    'msg' => Lang::get('block_access')
                ],200,[],'json');
            }
            $request->userInfo = $checkToken['userInfo'];
            $request->new_token = $checkToken['token'];
            $request->refresh = $checkToken['refresh'];
            return $next($request);
        }elseif ($request->isOptions()) {
            header('content-type:application:json;charset=utf8');
            header('Access-Control-Allow-Origin:*');
            header('Access-Control-Allow-Methods:POST');
            header('Access-Control-Allow-Headers:x-requested-with, content-type, token');
            return response([
                'code' => 0,
                'msg' => Lang::get('check_options')
            ],200,[],'json');
        }else{
            //  如果是其它请求
            return $next($request);
        }
    }

    private function checkToken( $token)
    {
        $gmCrypt = new GmCrypt();
        if ($gmCrypt->checkExpire($token)){
            return [
                'code' => 100,
                'msg' => Lang::get('token_expire')
            ];
        }
        //  如果解密失败，则返回错误
        $token_data = $gmCrypt->gmDecrypt($token);
        if (empty($token_data)) {
            return [
                'code' => 103,
                'msg' => Lang::get('token_fail')
            ];
        }
        //  解析出token中的user_id
        $user_id = $token_data['user_id'];

        $refresh = false;
        if ($gmCrypt->checkRefreshExpire($token)) {
            //根据当前登录用户锁定redis
            (new RedisLock())->lock(Config::get('cache.stores.redis.prefix').'user'.md5($user_id.'lock'), $expire = 5, $num = 0);
            //获取上次更新Token时间
            $token_time = Cache::get('user'.md5($user_id.'token_time'));
            //如果缓存中没有找到数据则返回
            if (!$token_time){
                return [
                    'code' => 101,
                    'msg' => Lang::get('account_drop')
                ];
            }
            //如果当前时间在缓存中的时间300秒后
            if (time() - $token_time >300){
                //设置缓存中当前用户的更新时间
                Cache::set('user'.md5($user_id.'token_time'),time());
                //获取新的Token
                $new_token = $gmCrypt->setToken($user_id);
                $refresh = true;
            }
            //打开redis的锁定状态
            (new RedisLock())->unlock(Config::get('cache.stores.redis.prefix').'user'.md5($user_id.'lock')) ;
        }
        //获取缓存中的用户数据
        $userInfo = Cache::get('user'.md5($user_id));
        //  如果账号不存在
        if (empty($userInfo)) {
            return [
                'code' => 101,
                'msg' => Lang::get('account_drop')
            ];
        }
        //  如果账号被禁用
        if ($userInfo['disabled'] == 1) {
            return [
                'code' => 0,
                'msg' => Lang::get('account_forbid')
            ];
        }
        if($token != $userInfo['token'] && $token != Cache::get('user'.md5($user_id.'old_token'))){
            return [
                'code' => 102,
                'msg' => Lang::get('repeat_login')
            ];
        }
        //如果更新了Token
        if ($refresh){
            // 将更新后的用户token存放进用户数据库
            //model::where('id', $user_id)->update(['token' => $new_token]);
            unset($userInfo['password']);
            //更新用户数据缓存中的Token为新的Token
            $userInfo['token'] = $new_token;
            Cache::set('user'.md5($user_id),$userInfo);
            Cache::set('user'.md5($user_id.'old_token'),$token);
            Cache::set('user'.md5($user_id.'token_time'),time());
            $token = $new_token;
        }
        return [
            'code' => 1,
            'userInfo' => $userInfo,
            'token' => $token,
            'refresh' => $refresh,
        ];
    }

}
