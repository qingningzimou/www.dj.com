<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 0:57
 */
namespace app\common\controller;

use think\App;
use think\exception\ValidateException;
use think\facade\Cache;
use app\common\model\SysForbidIp;
use think\facade\Lang;
use think\response\Json;
use think\facade\Log;
use think\facade\Config;

abstract class MobileBasic
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
            //  如果系统配置开启ip过滤功能
            if (Cache::get('enable_forbid_ip')) {
                //  检测当前访问IP是否在禁止IP段中
                $checkForbidIp = self::checkForbidIp($this->request->ip());
                if ($checkForbidIp) {
                    die(Lang::get('block_access'));
                }
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
            foreach ($forbidList as $item){
                if ($NewIP >= $item['ip_start'] && $NewIP <= $item['ip_end']){
                    return true;
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
            $result = array_merge($res,$basic);
        } catch (\Exception $exception) {
            $result = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return json($result);
    }


}