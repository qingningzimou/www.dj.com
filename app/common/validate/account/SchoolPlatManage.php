<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\account;

use think\Validate;

class SchoolPlatManage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_name' => 'require|regex:^[A-Za-z0-9_]{4,18}$',
        'real_name' => 'require|chsAlpha|length:2,24',
//        'password' => 'requireCallback:checkPassword|regex:^[A-Za-z0-9-_~%^&!#$*@?.]{6,20}$',
        'node_ids' => 'require|regex:^\d+(,\d+)*$',
        'mobile' => 'mobile',
        'disabled' => 'require|in:0,1',
        'state' => 'require|in:0,1',
        'deleted' => 'eq:1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'user_name.require' => '管理员帐号不能为空',
        'user_name.regex' => '管理员帐号只能为字母数字4-18位内',
        'real_name.require' => '姓名不能为空',
        'real_name.chsAlpha' => '姓名只能为汉字、字母',
        'real_name.length' => '姓名在2-24字符内',
//        'password.require' => '密码不能为空',
//        'password.regex' => '密码只能为6-20内的允许字符',
        'mobile.mobile' => '手机号码不正确',
        'node_ids.require' => '资源权限不能为空',
        'node_ids.regex' => '资源权限不正确',
        'disabled.require' => '状态不能为空',
        'disabled.in' => '状态只能为数字0-1',
        'state.require' => '状态不能为空',
        'state.in' => '状态只能为数字0-1',
        'deleted.eq' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'user_name',
//            'password',
            'real_name',
            'mobile',
            'node_ids',
            'disabled',
        ],
        'edit' => [
            'id',
            'user_name',
//            'password',
            'real_name',
            'mobile',
            'node_ids',
            'disabled',
        ],
        'delete' => [
            'id',
            'deleted'
        ],
        'state' => [
            'id',
            'state'
        ]
    ];

    /**
     * 当密码不为空的时候检测密码（如果scene为add的时候，必须检测密码）
     * @param $value
     * @param $data
     * @return bool
     */
    public function checkPassword($value, $data)
    {
        if (empty($data['password']) && $this->currentScene != 'add') {
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