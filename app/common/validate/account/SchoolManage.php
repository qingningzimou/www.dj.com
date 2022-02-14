<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\account;

use think\Validate;

class SchoolManage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_name' => 'require|mobile',
        'real_name' => 'require|chsAlpha|length:2,24',
        'password' => 'requireCallback:checkPassword|regex:^[A-Za-z0-9-_~%^&!#$*@?.]{8,20}$',
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
        'real_name.require' => '姓名不能为空',
        'real_name.chsAlpha' => '姓名只能为汉字、字母',
        'real_name.length' => '姓名在2-24字符内',
        'password.requireCallback' => '密码不能为空',
        'password.regex' => '密码只能为8-20内的允许字符',
        'mobile.mobile' => '手机号码格式错误',
        'region_id.require' => '区县ID不能为空',
        'region_id.number' => '区县ID只能为数字',
        'school_id.require' => '学校ID不能为空',
        'school_id.number' => '学校ID只能为数字',
        'disabled.in' => '非法提交',
        'state.in' => '非法提交',
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