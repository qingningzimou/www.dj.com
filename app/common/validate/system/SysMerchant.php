<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:41
 */

namespace app\common\validate\system;

use think\Validate;

class SysMerchant extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'fee_code' => 'require|alphaDash|length:20',
        'merchant_num' => 'require|number',
        'onlinepay' => 'require|in:0,1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'fee_code.require' => '缴费项编码不能为空',
        'fee_code.alphaDash' => '缴费项编码只能为字母数字',
        'fee_code.length' => '缴费项编码长度为20字符',
        'merchant_num.require' => '配置编号不能为空',
        'merchant_num.number' => '配置编号只能为数字',
        'onlinepay.require' => '线上缴费状态不能为空',
        'onlinepay.in' => '线上缴费状态只能为数字0-1',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'save' => [
            'id',
            'fee_code',
            'merchant_num',
            'onlinepay'
        ],
    ];
}