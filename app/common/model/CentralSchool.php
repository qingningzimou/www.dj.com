<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 21:36
 */

namespace app\common\model;

class CentralSchool extends Basic
{
    /**
     * 关联获取区域信息
     * @return \think\model\relation\HasOne
     */
    public function relationRegion()
    {
        return $this->hasOne('SysRegion','id','region_id')->where('disabled',0)->where('deleted',0);
    }

}