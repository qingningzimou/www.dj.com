<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\mobile\validate;

use think\Validate;

class House extends Validate
{
    protected $rule = [
        'child_id' => 'require|number|max:19',
        'family_id' => 'require|number|max:19',
        'house_type' => 'require|in:1,2,3,4,5',
        'house_address' => 'require|length:2,255',
        'standard_id' => 'number|max:10',
        'rental_start_time' => 'date',
        'other_attachment' => 'max:1024',
    ];

    protected $message = [
        'child_id.require' => '请返回重新填写学生信息',
        'child_id.number' => '非法操作',
        'child_id.max' => '非法操作',
        'family_id.require' => '请返回重新填写监护人信息',
        'family_id.number' => '非法操作',
        'family_id.max' => '非法操作',
        'house_type.require' => '请选择房产类型',
        'house_type.in' => '房产类型错误',
        'house_address.require' => '房产地址不能为空',
        'house_address.length' => '房产地址长度在255字符内',
        'standard_id.number' => '地址区划只能为数字',
        'standard_id.max' => '非法操作',
        'rental_start_time.date' => '租房时间格式错误',
        'other_attachment.max' => '其它相关资料照片超出限制',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'save' => [
            'child_id',
            'family_id',
            'house_type',
            'houser_address',
            'standard_id',
            'rental_start_time',
            'other_attachment',
        ],

    ];
}