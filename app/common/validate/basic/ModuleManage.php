<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\basic;

use think\Validate;

class ModuleManage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'module_name' => 'require|length:2,31',
        'start_time' => 'require|date',
        'end_time' => 'require|date',
        'disabled' => 'in:0,1',
        'state' => 'require|in:0,1',
        'region_id' => 'require|number',
        'node_ids' => 'require|regex:^\d+(,\d+)*$',
        'public_school_status' => 'require|in:0,1',
        'civil_school_status' => 'require|in:0,1',
        'primary_school_status' => 'require|in:0,1',
        'junior_middle_school_status' => 'require|in:0,1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'module_name.require' => '描述不能为空',
        'module_name.length' => '描述在2-30字内',
        'start_time.require' => '开始时间不能为空',
        'start_time.date' => '格式不正确',
        'end_time.require' => '结束时间不能为空',
        'end_time.date' => '格式不正确',
        'disabled.in' => '非法提交',
        'region_id.require' => '区县不能为空',
        'region_id.number' => '非法操作',
        'node_ids.require' => '资源权限不能为空',
        'node_ids.regex' => '资源权限不正确',
        'public_school_status.require' => '公办不能为空',
        'public_school_status.number' => '非法操作',
        'civil_school_status.require' => '民办不能为空',
        'civil_school_status.number' => '非法操作',
        'primary_school_status.require' => '小学不能为空',
        'primary_school_status.number' => '非法操作',
        'junior_middle_school_status.require' => '初中不能为空',
        'junior_middle_school_status.number' => '非法操作',
        'state.require' => '状态不能为空',
        'state.in' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'region_id',
            'module_name',
            'start_time',
            'end_time',
            'node_ids',
            'public_school_status',
            'civil_school_status',
            'primary_school_status',
            'junior_middle_school_status',

        ],
        'edit' => [
            'id',
            'region_id',
            'module_name',
            'start_time',
            'end_time',
            'node_ids',
            'public_school_status',
            'civil_school_status',
            'primary_school_status',
            'junior_middle_school_status',
        ],
        'delete' => [
            'id',
            'deleted'
        ],
        'state' => [
            'id',
            'state',
        ],
    ];

}