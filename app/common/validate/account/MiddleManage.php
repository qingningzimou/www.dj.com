<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\account;

use think\Validate;

class MiddleManage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_name' => 'require|mobile',
        'real_name' => 'require|chsAlpha|length:2,24',
        'public_school_status' => 'in:0,1',
        'civil_school_status' => 'in:0,1',
        'primary_school_status' => 'in:0,1',
        'junior_middle_school_status' => 'in:0,1',
        'central_id' => 'require|number',
        'mobile' => 'mobile',
        'main_account' => 'in:0,1',
        'disabled' => 'in:0,1',
        'deleted' => 'eq:1',
        'state' => 'in:0,1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'user_name.require' => '登录账号不能为空',
        'user_name.mobile' => '登录账号格式不准确，必须为手机号码',
        'real_name.require' => '姓名不能为空',
        'real_name.chsAlpha' => '姓名只能为汉字、字母',
        'real_name.length' => '姓名在2-24字符内',
        'central_id.require' => '教管会不能为空',
        'central_id.number' => '非法操作',
        'public_school_status.number' => '非法操作',
        'civil_school_status.number' => '非法操作',
        'primary_school_status.number' => '非法操作',
        'junior_middle_school_status.number' => '非法操作',
        'mobile.mobile' => '手机号码格式不正确',
        'main_account.in' => '非法提交',
        'disabled.in' => '非法提交',
        'state.in' => '非法提交',
        'deleted.eq' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'user_name',
            'real_name',
            'mobile',
            'public_school_status',
            'civil_school_status',
            'primary_school_status',
            'junior_middle_school_status',
            'central_id',
            'main_account',
            'disabled',
        ],
        'edit' => [
            'id',
            'user_name',
            'real_name',
            'mobile',
            'public_school_status',
            'civil_school_status',
            'primary_school_status',
            'junior_middle_school_status',
            'central_id',
            'main_account',
            'disabled',
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