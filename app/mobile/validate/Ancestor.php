<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\mobile\validate;

use think\Validate;

class Ancestor extends Validate
{
    protected $rule = [
        'child_id' => 'require|number|max:19',
        'family_id' => 'require|number|max:19',
        'parent_name' => 'require|chs|length:2,24',
        'relation' => 'require|in:3,4,5,6',
        'other_name' => 'requireIf:singled,0|chs|length:2,24',
        'other_idcard' => 'requireIf:singled,0',
        'other_relation' => 'requireIf:singled,0|in:1,2',
        'singled' => 'require|in:0,1',
    ];

    protected $message = [
        'child_id.require' => '请返回重新填写学生信息',
        'child_id.number' => '非法操作',
        'child_id.max' => '非法操作',
        'family_id.require' => '请返回重新填写监护人信息',
        'family_id.number' => '非法操作',
        'family_id.max' => '非法操作',
        'parent_name.require' => '请填写祖辈姓名',
        'parent_name.chs' => '祖辈姓名只能录入汉字',
        'parent_name.length' => '祖辈姓名长度不正确',
        'relation.require' => '请选择祖辈与学生关系',
        'relation.in' => '非法操作',
        'other_name.requireIf' => '请填写其它监护人姓名',
        'other_name.chs' => '其它监护人姓名只能录入汉字',
        'other_name.length' => '其它监护人姓名长度不正确',
        'other_idcard.requireIf' => '请填写其它监护人身份证',
        'other_relation.requireIf' => '非法操作',
        'other_relation.in' => '非法操作',
        'singled.require' => '请选择是否为单亲家庭',
        'singled.in' => '非法操作',
    ];

    protected $scene = [
        'save' => [
            'child_id',
            'family_id',
            'parent_name',
            'relation',
            'other_name',
            'other_idcard',
            'other_relation',
            'singled'
        ],
    ];
}