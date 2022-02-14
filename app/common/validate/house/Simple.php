<?php


namespace app\common\validate\house;

use think\Validate;


class Simple extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'region_id' => 'require|number',
        'address' => 'require|unique',
        'primary_school_id' => 'require|number',
        'middle_school_id' => 'require|number',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'region_id.require' => '请选择需区域信息',
        'region_id.number' => '非法操作',
        'address.require' => '地址信息不能为空',
        'address.unique' => '地址信息重复',
        'primary_school_id.require' => '小学ID不能为空',
        'primary_school_id.number' => '小学ID只能为数字',
        'middle_school_id.require' => '中学ID不能为空',
        'middle_school_id.number' => '中学ID只能为数字',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'region_id',
            'affair_method',
            'affair_controller',
            'explain',
            'actived'
        ],
        'edit' => [
            'id',
            'affair_name',
            'address',
        ],
        'delete' => [
            'id',
        ]
    ];
}