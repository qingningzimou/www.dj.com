<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:41
 */

namespace app\common\validate\system;

use think\Validate;

class SysRoles extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'role_id' => 'require|number',
        'role_name' => 'require|unique:sys_roles,deleted=0|length:2,24',
        'role_grade' => 'require|number|max:2',
        'role_type' => 'require',
        'node_ids' => 'regex:^\d+(,\d+)*$',
        'remarks' => 'max:63',
        'disabled' => 'require|in:0,1',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'role_id.require' => '请选择需要操作的信息',
        'role_id.number' => '非法操作',
        'role_name.require' => '角色名称不能为空',
        'role_name.unique' => '角色名称重复',
//        'role_name.chsAlpha' => '角色名称只能为汉字、字母',
        'role_name.length' => '角色名称长度为2-24字符',
        'role_grade.require' => '角色等级不能为空',
        'role_grade.number' => '角色等级只能为数字',
        'role_grade.max' => '角色等级在2字符内',
        'role_type.require' => '角色类型不能为空',
//        'node_ids.require' => '资源权限不能为空',
        'node_ids.regex' => '资源权限不正确',
        'explain.max' => '备注长度为20字符内',
        'disabled.require' => '角色禁用状态不能为空',
        'disabled.in' => '角色禁用状态只能为数字0-1',
        'deleted.eq' => '非法提交'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'role_name',
            'role_grade',
            'role_type',
            'node_ids',
            'explain'
        ],
        'edit' => [
            'id',
            'role_name',
            'role_grade',
            'role_type',
            'node_ids',
            'explain',
            'disabled'
        ],
        'switch' => [
            'role_id',
        ],
        'delete' => [
            'id',
            'deleted'
        ]
    ];
}