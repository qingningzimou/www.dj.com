<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 10:01
 */
namespace app\common\model;

class ManageNodes extends Basic
{
    public function relationSysNodes()
    {
        return $this->hasOne('SysNodes', 'id', 'node_id')->where('deleted',0)->field('id,node_name,parent_id,icon,router_path,router_name,order_num,remarks,node_type,defaulted,displayed');
    }
}