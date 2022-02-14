<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\basic;

use think\Validate;

class WorkCityConfig extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'item_name' => 'require|chsAlpha|length:2,255',
        'item_key' => 'require|length:2,24',
        'item_value' => 'require|length:2,255',
        'remark' => 'length:2,255',
        'deleted' => 'in:0,1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'item_name.require' => '配置名称不能为空',
        'item_name.length' => '配置名称长度不正确',
        'item_name.chsAlpha' => '配置名格式不正确',
        'item_key.require' => '配置键不能为空',
        'item_key.length' => '配置键长度不正确',
        'item_value.require' => '配置值不能为空',
        'item_value.length' => '配置值长度不正确',
        'deleted.in' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'item_key'
        ],
        'add' => [
            'item_value',
            'item_name',
            'item_key',
        ],
        'edit' => [
            'item_key',
            'item_value',
            'item_name',
        ],
        'delete' => [
            'id',
            'deleted'
        ]
    ];

}