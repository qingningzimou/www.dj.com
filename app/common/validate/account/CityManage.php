<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\account;

use think\Validate;

class CityManage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_name' => 'require|mobile',
        'real_name' => 'require|chsAlpha|length:2,24',
        'mobile' => 'mobile',
        'disabled' => 'in:0,1',
        'main_account' => 'in:0,1',
        'public_school_status' => 'in:0,1',
        'civil_school_status' => 'in:0,1',
        'primary_school_status' => 'in:0,1',
        'junior_middle_school_status' => 'in:0,1',
        'deleted' => 'eq:1',
        'state' => 'in:0,1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'user_name.require' => '管理员帐号不能为空',
        'user_name.mobile' => '管理员帐号应该为手机号',
        'real_name.require' => '姓名不能为空',
        'real_name.chsAlpha' => '姓名只能为汉字、字母',
        'real_name.length' => '姓名在2-24字符内',
        'disabled.in' => '非法提交',
        'deleted.eq' => '非法提交',
        'public_school_status.number' => '非法操作',
        'civil_school_status.number' => '非法操作',
        'primary_school_status.number' => '非法操作',
        'junior_middle_school_status.number' => '非法操作',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'user_name',
            'real_name',
            'mobile',
            'disabled',
            'main_account',
            'public_school_status',
            'civil_school_status',
            'primary_school_status',
            'junior_middle_school_status',

        ],
        'edit' => [
            'id',
            'user_name',
            'real_name',
            'mobile',
            'disabled',
            'main_account',
            'public_school_status',
            'civil_school_status',
            'primary_school_status',
            'junior_middle_school_status',
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