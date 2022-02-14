<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\basic;

use think\Validate;

class PlanApply extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'plan_id' => 'require|number',
        'school_id' => 'require|number',
        'apply_total' => 'require|number|max:5',
        'spare_total' => 'require|number|max:5',
        'remark' => 'max:100',
        'status' => 'in:0,1,2',
        'deleted' => 'eq:1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'manage_name.require' => '管理员帐号不能为空',
        'manage_name.unique' => '管理员帐号重复',
        'manage_name.mobile' => '管理员帐号必须为手机号',
        'real_name.require' => '管理人员实名不能为空',
        'real_name.chsAlpha' => '管理人员实名只能为汉字、字母',
        'real_name.length' => '管理人员实名在2-24字符内',
//        'nick_name.chsDash' => '管理人员别名只能为汉字、字母、数字和连接符',
//        'nick_name.length' => '管理人员别名在2-24字符内',
        'apply_total.require' => '申请学位数量不能为空',
        'apply_total.number' => '申请学位数量只能为数字',
        'apply_total.max' => '申请学位数量在5位数内',
        'spare_total.require' => '批复学位数量不能为空',
        'spare_total.number' => '批复学位数量只能为数字',
        'spare_total.max' => '批复学位数量在5位数内',
        'remark.max' => '备注不能超过100个字符',
        'status.in' => '非法提交',
        'disabled.in' => '非法提交',
        'deleted.eq' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'audit' => [
            'id',
            'status',
            'spare_total',
            'remark',
        ],
        /*'add' => [
            'manage_name',
            'real_name',
            'role_ids',
            'mobile',
            'idcard',
            'password',
            'region_id',
            'department_id'
        ],
        'edit' => [
            'id',
            'manage_name',
            'real_name',
            'role_ids',
            'mobile',
            'idcard',
            'password',
            'department_id'
        ],*/
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