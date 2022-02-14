<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 15:25
 */

namespace app\common\model;

class SysAffair extends Basic
{
    /**
     * 关联获取节点信息
     * @return \think\model\relation\HasOne
     */
    public function affairNodes()
    {
        return $this->hasOne('SysNodes', 'id', 'node_id')->where('deleted',0)->field('id,node_name,remarks,node_type');
    }
}