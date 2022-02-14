<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\mobile\validate;

use think\Validate;

class Child extends Validate
{
    protected $rule = [
        'id' => 'require|number|max:19',
        'real_name' => 'require|chs|length:2,24',
        'idcard' => 'require|idCard',
        'picurl' => 'require|max:255',
        'kindgarden_name' => 'chsDash|length:2,255',
        'apply_school_id' => 'number|max:8',
        'region_id' => 'require|number|max:4',
        'plan_id' => 'require|number|max:4',
        'school_attr' => 'require|in:1,2',
        'school_type' => 'require|in:1,2',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'real_name.require' => '请填写学生姓名',
        'real_name.chs' => '学生姓名只能录入汉字',
        'real_name.length' => '学生姓名长度不正确',
        'idcard.require' => '请填写身份证号',
        'idcard.idCard' => '身份证号格式不正确',
        'picurl.require' => '请上传户口簿照片',
        'picurl.max' => '户口簿照片数据错误',
        'kindgarden_name.chsDash' => '毕业学校名称只能是汉字、字母、数字',
        'kindgarden_name.length' => '毕业学校长度在255字符内',
        'region_id.require' => '请返回填报窗口重新填报',
        'region_id.number' => '非法操作',
        'region_id.max' => '非法操作',
        'plan_id.require' => '请返回填报窗口重新填报',
        'plan_id.number' => '非法操作',
        'plan_id.max' => '非法操作',
        'apply_school_id.number' => '非法操作',
        'apply_school_id.max' => '非法操作',
        'school_attr.require' => '请返回填报窗口重新填报',
        'school_attr.in' => '非法操作',
        'school_type.require' => '请返回填报窗口重新填报',
        'school_type.in' => '非法操作',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' =>[
            'real_name',
            'idcard',
            'picurl',
            'plan_id',
            'region_id',
            'apply_school_id',
            'school_attr',
            'school_type',
            'kindgarden_name',
        ],

        'edit' =>[
            'id',
            'real_name',
            'idcard',
            'picurl',
            'plan_id',
            'region_id',
            'apply_school_id',
            'school_attr',
            'school_type',
            'kindgarden_name',
        ],

    ];
}