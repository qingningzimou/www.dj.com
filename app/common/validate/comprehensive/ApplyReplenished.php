<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\comprehensive;

use think\Validate;

class ApplyReplenished extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_id' => 'require|number',
        'region_ids' => 'require',
        'start_time' => 'require|number',
        'end_time' => 'require|number',
        'school_attr' => 'require',
        'user_ids' => 'require',
        'school_attrs' => 'require',
        'status' => 'in:0,1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'user_id.require' => '请选择需要操作的信息',
        'user_id.number' => '非法操作',
        'region_ids.require' => '区域id不能为空',
        'user_ids.require' => '用户id不能为空',
        'school_attrs.require' => '学校性质不能为空',
        'start_time.require' => '开始时间不能为空',
        'start_time.number' => '开始时间格式不正确',
        'end_time.require' => '结束时间不能为空',
        'end_time.number' => '结束时间格式不正确',
        'school_attr.require' => '非法错误',
        'status.in' => '非法操作',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'school_attrs',
            'user_ids',
            'status',
        ],
        'school_add' => [
            'user_ids',
        ],
        'edit' => [
            'id',
            'title',
            'content',
            'tag',
        ],
        'status' => [
            'status',
            'user_id',
            'school_attr',
            'id',
        ],
        'delete' => [
            'id',
            'deleted',
        ],

    ];
}