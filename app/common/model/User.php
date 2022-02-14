<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 11:36
 */

namespace app\common\model;

use app\mobile\model\user\Apply;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

class User extends Basic
{
    /**
     * 关联获取单位信息
     * @return \think\model\relation\HasOne
     */
    public function relationUnit()
    {
        return $this->hasOne('Unit','id','unit_id')->where('disabled',0)->where('deleted',0);
    }

    /**
     * 关联获取区域信息
     * @return \think\model\relation\hasMany
     */
    public function relationRegion()
    {
        return $this->hasMany('RelationUserRegion','user_id','id')->where('deleted',0);
    }

    public function relationApply(): HasMany
    {
        return $this->HasMany(Apply::class, 'user_id', 'id');
    }

    public function relationApplyReplenished(): HasMany
    {
        return $this->HasMany(ApplyReplenished::class, 'user_id', 'id');
    }


}