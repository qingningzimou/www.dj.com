<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 21:36
 */

namespace app\common\model;

class Schools extends Basic
{
    protected $table = 'deg_sys_school';
    /**
     * 关联获取区域信息
     * @return \think\model\relation\HasOne
     */
    public function relationRegion()
    {
        return $this->hasOne('SysRegion','id','region_id')->where('disabled',0)->where('deleted',0);
    }

}