<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\basic;

use think\Validate;

class Department extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'parent_id' => 'require|number',
//        'grade_id' => 'require|number',
        'region_id' => 'require|number',
        'department_name' => 'require|unique:department,deleted=0|length:2,30',
        'disabled' => 'require|in:0,1',
        'telephone' => 'require|length:7,20|requireCallback:checkTelephone',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'parent_id.require' => '上级部门ID不能为空',
        'parent_id.number' => '上级部门ID只能为数字',
//        'grade_id.require' => '部门权限不能为空',
//        'grade_id.number' => '部门权限只能为数字',
        'region_id.require' => '区域ID不能为空',
        'region_id.number' => '区域ID只能为数字',
        'department_name.require' => '部门名称不能为空',
        'department_name.unique' => '部门名称重复',
        'department_name.length' => '部门名称在2-30字符内',
        'disabled.require' => '部门禁用状态不能为空',
        'disabled.in' => '部门禁用状态只能为数字0-1',
        'deleted.eq' => '非法提交',
        'telephone.require' => '联系电话不能为空',
        'telephone.length' => '联系电话在7-20个字符内',
        'telephone.regex' => '联系电话不正确',
        'telephone.requireCallback' => '联系电话格式错误'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'parent_id',
//            'grade_id',
            'telephone',
            'region_id',
            'department_name',
        ],
        'edit' => [
            'id',
//            'grade_id',
            'telephone',
            'region_id',
            'department_name',
            'disabled',
        ],
        'delete' => [
            'id',
            'deleted'
        ]
    ];

    /**
     * 检测手机号码或者固话
     * @param $value
     * @param $data
     * @return bool
     */
    public function checkTelephone($value, $data)
    {
        $tel = $data['telephone'];
        $isMob="/^1[3-8]{1}[0-9]{9}$/";
        $isTel="/^([0-9]-)?[0-9]$/";
        if(!preg_match($isMob,$tel) && !preg_match($isTel,$tel))
        {
            return false;
        }
        return true;
    }
}