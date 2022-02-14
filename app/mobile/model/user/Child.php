<?php

namespace app\mobile\model\user;

use app\mobile\model\user\Basic;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

class Child extends Basic
{

    protected $name = 'user_child';

    /**
     * 关联获取大数据比对的父母信息
     */
    public function relationChildFamily():HasOne
    {
        return $this->hasOne(ChildFamily::class,'child_id','id');
    }

    /**
     * 关联获取大数据比对的父母房产信息
     */
    public function relationFamilyHouse():HasMany
    {
        return $this->hasMany(FamilyHouse::class,'child_id','id');
    }

    /**
     * 关联用户注册信息
     * @return HasOne
     */
    public function relationUser(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'user_id');
    }

}