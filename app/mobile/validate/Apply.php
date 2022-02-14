<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\mobile\validate;

use think\Validate;

class Apply extends Validate
{
    protected $rule = [
        'user_id' => 'require|number|max:19',
        'region_id' => 'require|number|max:4',
        'plan_id' => 'require|number|max:4',
        'school_attr' => 'require|in:1,2',
        'school_type' => 'require|in:1,2',
        'child_id' => 'require|number|max:19',
        'family_id' => 'require|number|max:19',
        'house_id' => 'require|number|max:19',
        'insurance_id' => 'number|max:10',
        'company_id' => 'number|max:10',
        'residence_id' => 'number|max:10',
        'house_type' => 'require|in:1,2,3,4,5',
        'ancestor_id' => 'number|max:19',
        'apply_school_id' => 'number|max:8',
        'ancestor_family_id' => 'number|max:19',
        'agree' => 'eq:1',
        'supplement_info' => 'require',
        'prepared' => 'in:0,1',
        'resulted' => 'in:0,1',
        'signed' => 'in:0,1',
        'voided' => 'in:0,1',
        'replenished' => 'in:0,1',
        'supplemented' => 'in:0,1',
        'audited' => 'in:0,1,2,3',
        'deleted' => 'in:0,1',
        'json_data' => 'require',
    ];

    protected $message = [
        'user_id.require' => '请返回填报窗口重新填报',
        'user_id.number' => '非法操作',
        'user_id.max' => '非法操作',
        'region_id.require' => '请返回填报窗口重新填报',
        'region_id.number' => '非法操作',
        'region_id.max' => '非法操作',
        'plan_id.require' => '请返回填报窗口重新填报',
        'plan_id.number' => '非法操作',
        'plan_id.max' => '非法操作',
        'school_attr.require' => '请返回填报窗口重新填报',
        'school_attr.in' => '非法操作',
        'school_type.require' => '请返回填报窗口重新填报',
        'school_type.in' => '非法操作',
        'child_id.require' => '请返回重新填写学生信息',
        'child_id.number' => '非法操作',
        'child_id.max' => '非法操作',
        'family_id.require' => '请返回重新填写监护人信息',
        'family_id.number' => '非法操作',
        'family_id.max' => '非法操作',
        'house_id.require' => '请返回重新填写房产信息',
        'house_id.number' => '非法操作',
        'house_id.max' => '非法操作',
        'company_id.number' => '工商信息数据错误',
        'company_id.max' => '非法操作',
        'insurance_id.number' => '社保信息数据错误',
        'insurance_id.max' => '非法操作',
        'residence_id.number' => '居住证信息数据错误',
        'residence_id.max' => '非法操作',
        'house_type.require' => '请返回重新填写房产信息',
        'house_type.in' => '房产类型错误',
        'ancestor_id.number' => '非法操作',
        'ancestor_id.max' => '非法操作',
        'apply_school_id.number' => '非法操作',
        'apply_school_id.max' => '非法操作',
        'prepared.in' => '非法操作',
        'resulted.in' => '非法操作',
        'signed.in' => '非法操作',
        'voided.in' => '非法操作',
        'replenished.in' => '非法操作',
        'supplemented.in' => '非法操作',
        'audited.in' => '非法操作',
        'deleted.in' => '非法操作',
        'agree.eq' => '请勾选协议',
        'supplement_info.require' => '请填写补充资料',
        'json_data.require' => '请填写补充资料',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'getSchool' => [
            'school_attr',
            'school_type',
        ],
        'save' => [
            'user_id',
            'region_id',
            'plan_id',
            'school_type',
            'school_attr',
            'child_id',
            'family_id',
            'house_id',
            'company_id',
            'insurance_id',
            'residence_id',
            'house_type',
            'ancestor_id',
            'apply_school_id',
        ],
        'showInfo' => [
            'user_id',
            'plan_id',
            'school_attr',
            'child_id',
            'family_id',
            'house_id',
            'ancestor_family_id',
            'apply_school_id',
        ],
    ];
}