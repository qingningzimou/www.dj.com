<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\basic;

use think\Validate;

class AreaManage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_name' => 'require|mobile',
        'real_name' => 'require|chsAlpha|length:2,24',
        'password' => 'requireCallback:checkPassword|regex:^[A-Za-z0-9-_~%^&!#$*@?.]{6,20}$',
        'new_password' => 'requireCallback:checkNewPassword|regex:^[A-Za-z0-9-_~%^&!#$*@?.]{6,20}$',
        'disabled' => 'in:0,1',
        'state' => 'in:0,1',
        'main_account' => 'in:0,1',
        'public_school_status' => 'in:0,1',
        'civil_school_status' => 'in:0,1',
        'primary_school_status' => 'in:0,1',
        'junior_middle_school_status' => 'in:0,1',
        'deleted' => 'eq:1',
        'department_id' => 'require|number',
        'mobile' => 'mobile',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'user_name.require' => '登录账号不能为空',
        'user_name.mobile' => '登录账号格式不准确，必须为手机号码',
        'real_name.require' => '管理人员实名不能为空',
        'real_name.chsAlpha' => '管理人员实名只能为汉字、字母',
        'real_name.length' => '管理人员实名在2-24字符内',
        'password.require' => '密码不能为空',
        'password.regex' => '密码只能为6-20内的允许字符',
        'disabled.in' => '非法提交',
        'state.in' => '非法提交',
        'deleted.eq' => '非法提交',
        'department_id.number' => '非法操作',
        'public_school_status.number' => '非法操作',
        'civil_school_status.number' => '非法操作',
        'primary_school_status.number' => '非法操作',
        'junior_middle_school_status.number' => '非法操作',
        'mobile.mobile' => '手机号码格式错误',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'user_name',
            'password',
            'mobile',
            'real_name',
            'disabled',
            'main_account',
            'public_school_status',
            'civil_school_status',
            'primary_school_status',
            'junior_middle_school_status',
            'department_id',

        ],
        'edit' => [
            'id',
            'user_name',
            'real_name',
            'disabled',
            'main_account',
            'public_school_status',
            'civil_school_status',
            'primary_school_status',
            'junior_middle_school_status',
            'department_id',
            'mobile'
        ],
        'delete' => [
            'id',
            'deleted'
        ],
        'state' =>[
            'id',
            'state',
        ],
    ];

    /**
     * 当密码不为空的时候检测密码（如果scene为add的时候，必须检测密码）
     * @param $value
     * @param $data
     * @return bool
     */
    public function checkPassword($value, $data)
    {
        if ($this->currentScene != 'add') {
            return false;
        }
        return true;
    }
    /**
     * 当手势密码不为空的时候检测密码
     * @param $value
     * @param $data
     * @return bool
     */
    public function checkTouchPassword($value, $data)
    {
        if (empty($data['touchpwd'])) {
            return false;
        }
        return true;
    }
    /**
     * 当新密码不为空的时候检测新密码
     * @param $value
     * @param $data
     * @return bool
     */
    public function checkNewPassword($value, $data)
    {
        if (empty($data['new_password'])) {
            return false;
        }
        return true;
    }
}