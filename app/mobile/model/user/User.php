<?php

namespace app\mobile\model\user;

use app\mobile\model\user\Basic;
use app\common\model\SysRegion;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 注册用户信息
 * Class User
 * @package app\mobile\model\user
 */
class User extends Basic
{
    protected $name = 'user';
    /**
     * 獲取註冊用戶下所有入學申請
     * @return HasMany
     */
    public function relationApply(): HasMany
    {
        return $this->hasMany(Apply::class, 'user_id', 'user_id');
    }

    /**
     * 获取注册用户关联的补录区县
     * @return HasOne
     */
    public function relationRegion(): HasOne
    {
        return $this->hasOne(SysRegion::class, 'id', 'replenish_region_id')->joinType('left');
    }
}