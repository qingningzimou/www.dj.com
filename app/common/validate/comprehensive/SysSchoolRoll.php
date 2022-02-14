<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\comprehensive;

use think\Validate;

class SysSchoolRoll extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'id_card' => 'require|unique:sys_school_roll,deleted=0|alphaNum|idCard|length:15,18',
        'real_name' => 'require|chs|length:2,64',
        'student_name' => 'require|chs|length:2,64',
        'region_id' => 'require|number|max:5',
        'school_id' => 'require|number|max:6',
        'student_code' => 'require|unique:sys_school_roll,deleted=0|alphaDash|length:5,24',
        'graduation_school_id' => 'require|number',
        'graduation_school_code' => 'require',
        'graduation_school' => 'require',
        'middle_school_id' => 'require|number',
        'is_send' => 'require|in:0,1',
        'deleted' => 'eq:1',
        'police_name' => 'require|length:2,64'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'id_card.require' => '身份证号不能为空',
        'id_card.unique' => '身份证号已存在',
        'id_card.alphaNum' => '身份证号只能字母数字',
        'id_card.idCard' => '身份证号格式错误',
        'id_card.length' => '身份证号在18字符内',
        'region_id.require' => '登记区县ID不能为空',
        'region_id.number' => '登记区县ID只能为数字',
        'region_id.max' => '登记区县ID在5字符内',
        'school_id.require' => '学校ID不能为空',
        'school_id.number' => '学校ID只能为数字',
        'school_id.max' => '学校ID在6字符内',
        'real_name.require' => '学生姓名不能为空',
        'real_name.chs' => '学生姓名只能为汉字',
        'real_name.length' => '学生姓名在64字符内',
        'student_code.require' => '个人标识码不能为空',
        'student_code.alphaDash' => '个人标识码只能为字母数字和连接符',
        'student_code.length' => '个人标识码在5-24字符内',
        'student_code.unique' => '个人标识码已存在',
        'student_name.require' => '学生姓名不能为空',
        'student_name.chs' => '学生姓名只能为汉字',
        'student_name.length' => '学生姓名在2-64字符内',
        'graduation_school_code.require' => '毕业学校标识码不能为空',
        'graduation_school.require' => '毕业学校不能为空',
        'is_send.require' => '类型不能为空',
        'is_send.in' => '类型只能为数字0-1',
        'deleted.eq' => '非法提交',
        'police_name.require' => '所属派出所不能为空',
        'police_name.length' => '所属派出所在2-64字符内',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'student_code',
            'id_card',
            'student_name',
            'graduation_school_code',
            'graduation_school',
        ],
        'edit' => [
            'id',
            'student_code',
            'id_card',
            'student_name',
            'graduation_school_code',
            'graduation_school',
        ],
        'delete' => [
            'id',
            'deleted',
        ]
    ];
}