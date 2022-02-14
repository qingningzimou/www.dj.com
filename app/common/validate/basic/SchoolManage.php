<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\basic;

use think\Validate;

class SchoolManage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_name' => 'require|mobile',
        'real_name' => 'chsAlpha|length:2,24',
        'password' => 'requireCallback:checkPassword|regex:^[A-Za-z0-9-_~%^&!#$*@?.]{8,20}$',
        'enroll_mode' => 'require|in:1,2',
        'mobile' => 'mobile',
        'region_id' => 'require|number',
        'school_id' => 'require|number',
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
        'real_name.chsAlpha' => '姓名只能为汉字、字母',
        'real_name.length' => '姓名在2-24字符内',
        'password.requireCallback' => '密码不能为空',
        'password.regex' => '密码只能为8-20内的允许字符',
        'disabled.in' => '非法提交',
        'state.in' => '非法提交',
        'school_attr.require' => '学校性质不能为空',
        'school_attr.in' => '非法提交',
        'enroll_mode.require' => '招生方式不能为空',
        'enroll_mode.in' => '非法提交',
        'school_name.require' => '学校名称不能为空',
        'school_name.chsAlpha' => '学校名称只能为汉字、字母',
        'school_name.length' => '学校名称在2-24字符内',
        'department_id.number' => '非法操作',
        'mobile.mobile' => '手机号码格式错误',
        'region_id.require' => '区县ID不能为空',
        'region_id.number' => '区县ID只能为数字',
        'school_id.require' => '学校ID不能为空',
        'school_id.number' => '学校ID只能为数字',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'user_name',
            'real_name',
            'mobile',
            'region_id',
            'school_id',
            'main_account',
            'disabled',

        ],
        'edit' => [
            'id',
            'user_name',
            'real_name',
            'mobile',
            'disabled',
            'main_account',
        ],
        'delete' => [
            'id',
            'deleted'
        ],
        'state' => [
            'id',
            'state',
        ],
        'getSchool' => [
          'department_id'
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