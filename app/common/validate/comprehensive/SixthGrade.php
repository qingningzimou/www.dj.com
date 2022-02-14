<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\comprehensive;

use think\Validate;

class SixthGrade extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'id_card' => 'require|unique:sixth_grade,deleted=0|alphaNum|idCard|length:15,18',
        'student_name' => 'require|chs|length:2,64',
        'region_id' => 'require|number|max:5',
        'police_id' => 'require|number|max:6',
        'student_code' => 'require|alphaDash|length:5,24|unique:sixth_grade,deleted=0',
        'graduation_school_id' => 'require|number',
        'middle_school_id' => 'require|number',
        'address' => 'require',
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
        'police_id.require' => '户籍所在派出所ID不能为空',
        'police_id.number' => '户籍所在派出所ID只能为数字',
        'police_id.max' => '户籍所在派出所ID在6字符内',
        'student_name.require' => '学生姓名不能为空',
        'student_name.chs' => '学生姓名只能为汉字',
        'student_name.length' => '学生姓名在64字符内',
        'student_code.require' => '学籍号不能为空',
        'student_code.alphaDash' => '学籍号只能为字母数字和连接符',
        'student_code.length' => '学籍号在5-24字符内',
        'student_code.unique' => '学籍号已存在',
        'address.require' => '住址不能为空',
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
            'id_card',
            //'police_id',
            'student_name',
            'region_id',
            //'address',
            'student_code',
            'graduation_school_id',
            //'police_name',
        ],
        'edit' => [
            'id',
            'id_card',
            //'police_id',
            'student_name',
            'region_id',
            //'address',
            'student_code',
            'graduation_school_id',
            //'police_name',
        ],
        'delete' => [
            'id',
            'deleted',
        ]
    ];
}