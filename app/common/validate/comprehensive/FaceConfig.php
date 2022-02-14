<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\comprehensive;

use think\Validate;

class FaceConfig extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'type' => 'in:1,2,3,4,5,6,7,8,9',
        'start_time' => 'date',
        'end_time' => 'date',
        'status' => 'in:0,1',
        'deleted' => 'eq:1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'type.in' => '非法操作',
        'start_time.date' => '时间格式错误',
        'end_time.date' => '时间格式错误',
        'status.in' => '非法操作',
        'deleted.eq' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'type',
            'start_time',
            'end_time',
            'status',
        ],
        'edit' => [
            'id',
            'type',
            'start_time',
            'end_time',
            'status',
        ],

        [
            'id',
            'status'
        ],

        'delete' => [
            'id',
            'deleted',
        ],

    ];
}