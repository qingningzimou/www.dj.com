<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\basic;

use think\Validate;

class Manage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_name' => 'require|regex:^[A-Za-z0-9_]{4,18}$',
        'password' => 'requireCallback:checkPassword',
        'new_password' => 'requireCallback:checkNewPassword',
        'real_name' => 'require|chsAlpha|length:2,24',
//        'nick_name' => 'chsDash|length:2,24',
        'role_id' => 'require|number',
        'mobile' => 'mobile',
        'idcard' => 'idCard',
//        'avatar' => 'url|max:100',
        'region_id' => 'require|number',
        'department_id' => 'require|number',
        'node_ids' => 'regex:^\d+(,\d+)*$',
        'main_account' => 'require|in:0,1',
        'disabled' => 'require|in:0,1',
        'deleted' => 'eq:1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'user_name.require' => '管理员帐号不能为空',
        'user_name.regex' => '管理员帐号只能为字母数字',
        'user_name.length' => '管理员帐号在4-20字符内',
        'real_name.require' => '管理人员实名不能为空',
        'real_name.chsAlpha' => '管理人员实名只能为汉字、字母',
        'real_name.length' => '管理人员实名在2-24字符内',
//        'nick_name.chsDash' => '管理人员别名只能为汉字、字母、数字和连接符',
//        'nick_name.length' => '管理人员别名在2-24字符内',
        'role_id.require' => '管理人员角色不能为空',
        'role_id.number' => '管理人员角色只能为数字',
        'mobile.mobile' => '手机号码不正确',
        'idcard.idCard' => '身份证号不正确',
        'password.require' => '密码不能为空',
        'new_password.require' => '新密码不能为空',
//        'avatar.url' => '图标/头像路径必须为url',
//        'avatar.max' => '图标/头像路径在100字符内',
        'region_id.require' => '所属区域不能为空',
        'region_id.number' => '区域ID只能为数字',
        'department_id.require' => '所在部门不能为空',
        'department_id.number' => '部门ID只能为数字',
        'node_ids.regex' => '资源权限不正确',
        'main_account.require' => '主管权限不能为空',
        'main_account.number' => '主管权限只能为数字0-1',
        'disabled.require' => '账户状态不能为空',
        'disabled.in' => '账户状态只能为数字0-1',
        'deleted.eq' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'manage_name',
            'password',
            'real_name',
//            'nick_name',
            'role_id',
            'mobile',
            'idcard',
            'region_id',
            'node_ids',
            'main_account'
        ],
        'edit' => [
            'id',
            'manage_name',
            'password',
            'real_name',
//            'nick_name',
            'role_id',
            'mobile',
            'idcard',
            'region_id',
            'node_ids',
            'main_account',
            'disabled'
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