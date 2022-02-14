<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\common\validate\recruit;

use think\Validate;

class Apply extends Validate
{
    protected $rule = [
        'real_name' => 'require|chsAlpha|length:2,24',
        'student_id_card' => 'require|idCard',
        'guardian_name' => 'require|chsAlpha|length:2,24',
        'guardian_id_card' => 'require|idCard',
        'mobile' => 'require|mobile',
        'house_type' => 'require|number',
        'house_owner_name' => 'require|chsAlpha|length:2,24',
        'house_address' => 'require',
        'house_id' => 'require|number',
        'graduation_school_name' => 'require',
        'prepared' => 'in:0,1',
        'resulted' => 'in:0,1',
        'signed' => 'in:0,1',
        'voided' => 'in:0,1',
        'replenished' => 'in:0,1',
        'supplemented' => 'in:0,1',
        'apply_school_id' => 'number',
        'public_school_id' => 'number',
        'result_school_id' => 'number',
        'admission_type' => 'number',
        'audited' => 'in:0,1,2,3',
        'deleted' => 'in:0,1',
    ];

    protected $message = [
        'real_name.require' => '请填写学生姓名',
        'real_name.chsAlpha' => '学生姓名格式不正确',
        'real_name.length' => '学生姓名长度必须在2-24以内',
        'student_id_card.require' => '请填写学生身份证号',
        'student_id_card.idCard' => '学生身份证号格式不正确',
        'guardian_name.require' => '请填写监护人姓名',
        'guardian_name.chsAlpha' => '监护人姓名格式不正确',
        'guardian_name.length' => '监护人姓名长度必须在2-24以内',
        'guardian_id_card.require' => '请填写监护人身份证号',
        'guardian_id_card.idCard' => '监护人身份证号格式不正确',
        'mobile.require' => '请选填写联系手机号',
        'mobile.mobile' => '联系手机号格式错误',
        'house_type.require' => '请选择房产类型',
        'house_type.number' => '房产类型数据错误',
        'house_owner_name.require' => '请填写房产所有人',
        'house_owner_name.chsAlpha' => '房产所有人格式不正确',
        'house_owner_name.length' => '房产所有人长度必须在2-24以内',
        'house_address.require' => '请填写房产地址',
        'graduation_school_name.require' => '请填写毕业学校',
        'child_id.require' => '学生不存在',
        'child_id.number' => '学生数据错误',
        'family_id.require' => '家长不存在',
        'family_id.number' => '家长数据错误',
        'house_id.require' => '房产信息不存在',
        'house_id.number' => '房产信息数据错误',
        'apply_school_id.number' => '申请民办学校信息数据错误',
        'public_school_id.number' => '申请公办学校信息数据错误',
        'result_school_id.number' => '录取学校信息数据错误',
        'admission_type.number' => '录取方式数据错误',
        'prepared.in' => '非法操作',
        'resulted.in' => '非法操作',
        'signed.in' => '非法操作',
        'voided.in' => '非法操作',
        'replenished.in' => '非法操作',
        'supplemented.in' => '非法操作',
        'audited.in' => '非法操作',

        'agree.eq' => '请勾选协议',
        'school_type.in' => '请选择学校类型',
        'school_attr.in' => '请选择学校性质',
        'supplement_info.require' => '请填写补充资料',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' =>[
            'real_name',
            'student_id_card',
            'guardian_name',
            'guardian_id_card',
            'mobile',
            'house_type',
            'house_owner_name',
            'house_address',
            'graduation_school_name',
        ],
        'edit' =>[
            'id',
            'real_name',
            'student_id_card',
            'guardian_name',
            'guardian_id_card',
            'mobile',
            'house_type',
            'house_owner_name',
            'house_address',
            'graduation_school_name',
        ],
        'delete' => [
            'id',
            'deleted',
        ]
 
    ];
}