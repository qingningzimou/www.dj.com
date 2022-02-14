<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/17
 * Time: 17:53
 */
namespace app\common\middleware;

use app\common\controller\GmCrypt;
use app\common\controller\RedisLock;
use think\facade\Cache;
use think\facade\Lang;
use think\facade\Config;
use think\facade\Log;
class MobileMiddleware
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
            if ($request->controller() == 'Login' || $request->controller() == 'Captcha' || $request->controller() == 'logout') {
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
//
    }

    private function checkToken( $token)
    {
        $gmCrypt = new GmCrypt();
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
        $new_token = '';

        if ($gmCrypt->checkExpire($token)){
            //根据当前登录用户锁定redis
            (new RedisLock())->lock(Config::get('cache.stores.redis.prefix').'mobile_user'.md5($user_id.'lock'), $expire = 5, $num = 0);
            //获取上次更新Token时间
            $token_time = Cache::get('mobile_user'.md5($user_id.'token_time'));
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
                Cache::set('mobile_user'.md5($user_id.'token_time'),time());
                //获取新的Token
                $new_token = $gmCrypt->setToken($user_id);
                $refresh = true;
            }
            //打开redis的锁定状态
            (new RedisLock())->unlock(Config::get('cache.stores.redis.prefix').'mobile_user'.md5($user_id.'lock')) ;
        }

        //获取缓存中的用户数据
        $userInfo = Cache::get('mobile_user'.md5($user_id));
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
        /*if($token != $userInfo['token'] && $token != Cache::get('mobile_user'.md5($user_id.'old_token'))){
            return [
                'code' => 102,
                'msg' => Lang::get('repeat_login')
            ];
        }*/
        //如果更新了Token
        if ($refresh){
            unset($userInfo['password']);
            //更新用户数据缓存中的Token为新的Token
            $userInfo['token'] = $new_token;
            Cache::set('mobile_user'.md5($user_id),$userInfo);
            Cache::set('mobile_user'.md5($user_id.'old_token'),$token);
            Cache::set('mobile_user'.md5($user_id.'token_time'),time());
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
