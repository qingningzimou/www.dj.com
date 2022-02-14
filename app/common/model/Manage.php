<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 11:36
 */

namespace app\common\model;

class Manage extends Basic
{
    /**
     * 关联获取部门信息
     * @return \think\model\relation\HasOne
     */
    public function relationDepartment()
    {
        return $this->hasOne('Department','id','department_id')->where('disabled',0)->where('deleted',0);
    }
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
        return $this->hasMany('RelationManageRegion','manage_id','id')->where('deleted',0);
    }
    /**
     * 关联获取管理员节点信息
     * @return \think\model\relation\HasOne
     */
    public function relationNodes()
    {
        return $this->hasMany('ManageNodes','manage_id','id')->with('relationSysNodes')->where('deleted',0);
    }

    /**
     * 关联获取管理员角色信息
     * @return \think\model\relation\HasOne
     */
    public function relationRoles()
    {
        return $this->hasMany('SysRoles','role_grade','role_id')->where('deleted',0);
    }
}