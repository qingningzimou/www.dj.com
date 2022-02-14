<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\basic;

use think\Validate;

class System extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_name' => 'require|regex:^[A-Za-z0-9_]{4,18}$',
        'real_name' => 'require|chsAlpha|length:2,24',
        'password' => 'requireCallback:checkPassword|regex:^[A-Za-z0-9-_~%^&!#$*@?.]{8,20}$',
        'new_password' => 'requireCallback:checkNewPassword|regex:^[A-Za-z0-9-_~%^&!#$*@?.]{8,20}$',
        'node_ids' => 'require|regex:^\d+(,\d+)*$',
        'mobile' => 'mobile',
        'main_account' => 'require|in:0,1',
        'disabled' => 'require|in:0,1',
        'deleted' => 'eq:1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'user_name.require' => '管理员帐号不能为空',
        'user_name.unique' => '管理员帐号不能为空',
        'user_name.regex' => '管理员帐号只能为字母数字4-18位内',
        'real_name.require' => '管理人员实名不能为空',
        'real_name.chsAlpha' => '管理人员实名只能为汉字、字母',
        'real_name.length' => '管理人员实名在2-24字符内',
        'password.require' => '密码不能为空',
        'password.regex' => '密码只能为6-20内的允许字符',
        'mobile.mobile' => '手机号码不正确',
        'node_ids.require' => '资源权限不能为空',
        'node_ids.regex' => '资源权限不正确',
        'main_account.require' => '主账号权限不能为空',
        'main_account.number' => '主账号权限只能为数字0-1',
        'disabled.require' => '状态不能为空',
        'disabled.in' => '状态只能为数字0-1',
        'deleted.eq' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'user_name',
            'password',
            'real_name',
            'mobile',
            'node_ids',
            'main_account',
            'disabled',
        ],
        'edit' => [
            'id',
            'user_name',
            'password',
            'real_name',
            'mobile',
            'node_ids',
            'main_account',
            'disabled',
        ],
        'delete' => [
            'id',
            'deleted'
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