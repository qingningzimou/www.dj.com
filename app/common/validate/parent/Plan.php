<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:41
 */

namespace app\common\validate\parent;

use think\Validate;

class Plan extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'plan_name' => 'require|length:2,255',
        'school_type' => 'require|in:1,2',
        'public_start_time' => 'require|date',
        'public_end_time' => 'require|date',
        'private_start_time' => 'require|date',
        'private_end_time' => 'require|date',
        'agreement' => 'require',
        'deleted' => 'in:1,2',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'plan_name.require' => '名称不能为空',
        'plan_name.length' => '名称长度不正确',
        'school_type.require' => '非法操作',
        'public_start_time.require' => '民办开始时间不能为空',
        'public_start_time.date' => '民办开始时间格式不正确',
        'private_end_time.require' => '公办开始时间不能为空',
        'private_end_time.date' => '公办开始时间格式不正确',
        'agreement.require' => '用户协议不能为空',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'plan_name',
            'school_type',
            'public_start_time',
            'public_end_time',
            'private_start_time',
            'private_end_time',
            'agreement',
        ],
        'edit' => [
            'id',
            'plan_name',
            'school_type',
            'public_start_time',
            'public_end_time',
            'private_start_time',
            'private_end_time',
            'agreement',
        ],
    ];

}