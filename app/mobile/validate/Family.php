<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\mobile\validate;

use think\Validate;

class Family extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'parent_name' => 'require|chs|length:2,24',
        'idcard' => 'require|idCard',
        'child_id' => 'require|number|max:19',
        'relation' => 'require|in:0,1,2,3,4,5,6',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'parent_name.require' => '请填写监护人真实姓名',
        'parent_name.chs' => '监护人姓名只能录入汉字',
        'parent_name.length' => '监护人真实姓名长度不正确',
        'idcard.require' => '请填写监护人身份证号',
        'idcard.idCard' => '监护人身份证号格式不正确',
        'relation.require' => '请选择监护人与学生关系',
        'relation.in' => '非法操作',
        'child_id.require' => '请返回重新填写学生信息',
        'child_id.number' => '非法操作',
        'child_id.max' => '非法操作',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'parent_name',
            'idcard',
            'child_id',
            'relation',
        ],
        'edit' => [
            'id',
            'parent_name',
            'idcard',
            'child_id',
            'relation'
        ],

    ];
}