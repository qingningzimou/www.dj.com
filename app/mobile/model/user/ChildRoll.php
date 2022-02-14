<?php

namespace app\mobile\model\user;

use app\mobile\model\user\Basic;
use app\common\model\SysSchoolRollExtend;
use app\common\model\SysSchoolRollGuardian;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

class ChildRoll extends Basic
{

    protected $name = 'sys_school_roll';

    /**
     * 关联获取学籍扩展信息
     */
    public function relationChildFamily():HasOne
    {
        return $this->hasOne(SysSchoolRollExtend::class,'school_roll_id','id');
    }

    /**
     * 关联获取学生家庭成员信息
     */
    public function relationFamilyHouse():HasMany
    {
        return $this->hasMany(SysSchoolRollGuardian::class,'school_roll_id','id');
    }

}