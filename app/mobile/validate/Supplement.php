<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\mobile\validate;

use think\Validate;

class Supplement extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_apply_id' => 'require|number',
        'status' => 'in:0,1',
        'child_id' => 'require|number',
        'json_data' => 'require',
        'deleted' => 'in:0,1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'user_apply_id.require' => '请选择需要操作的信息',
        'user_apply_id.number' => '非法操作',
        'child_id.require' => '请选择需要操作的信息',
        'json_data.require' => '请选择需要补充的资料',
        'child_id.number' => '非法操作',
        'status.in' => '非法操作',
        'deleted.in' => '非法操作',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' =>[
            'json_data',
            'statue',
            'child_id',
        ],

    ];
}