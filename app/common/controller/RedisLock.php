<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/5/12
 * Time: 10:40
 */
namespace app\common\controller;

use think\facade\Cache;

class RedisLock {

    private $_redis;

    public function __construct() {

        $handler = Cache::handler();
        return $this->_redis = $handler;
    }

    /**
     * 获取锁
     * @param  String  $key    锁标识
     * @param  Int     $expire 锁过期时间
     * @param  Int     $num    重试次数
     * @return Boolean
     */
    public function lock($key, $expire = 5, $num = 0){
        $is_lock = $this->_redis->setnx($key, time()+$expire);

        if(!$is_lock) {
            //获取锁失败则重试{$num}次
            for($i = 0; $i < $num; $i++){

                $is_lock = $this->_redis->setnx($key, time()+$expire);

                if($is_lock){
                    break;
                }
                sleep(1);
            }
        }

        // 不能获取锁
        if(!$is_lock){

            // 判断锁是否过期
            $lock_time = $this->_redis->get($key);

            // 锁已过期，删除锁，重新获取
            if(time()>$lock_time){
                $this->unlock($key);
                $is_lock = $this->_redis->setnx($key, time()+$expire);
            }
        }

        return $is_lock? true : false;
    }

    /**
     * 释放锁
     * @param  String  $key 锁标识
     * @return Boolean
     */
    public function unlock($key){
        return $this->_redis->del($key);
    }

}
