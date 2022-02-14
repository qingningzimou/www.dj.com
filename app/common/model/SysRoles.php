<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:40
 */

namespace app\common\model;

class SysRoles extends Basic
{
    /**
     * 关联获取权限信息
     * @return \think\model\relation\hasMany
     */
    public function relationRoleNodes()
    {
        return $this->hasMany('SysRoleNodes','role_id','id')->where('deleted',0)->field('id,role_id,node_type,node_id');
    }


}