<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\mobile\validate;

use think\Validate;

class ConsultingService extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'region_id' => 'require|number',
        'content' => 'require',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'region_id.region_id' => '请选择需要操作的信息',
        'region_id.number' => '非法操作',
        'content' => 'require',
    ];

    protected $scene = [
        'info' => [
            'region_id'
        ],
        'add' =>[
        ],

    ];
}