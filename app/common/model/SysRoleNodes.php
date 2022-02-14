<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 10:18
 */

namespace app\common\model;

class SysRoleNodes extends Basic
{
    /**
     * 关联获取权限信息
     * @return \think\model\relation\HasOne
     */
    public function relationNodes()
    {
        return $this->hasOne('SysNodes', 'id', 'node_id')->where('deleted',0)->field('id,node_name,parent_id,icon,router_path,order_num,remarks,authority,signin,defaulted,displayed');
    }
}