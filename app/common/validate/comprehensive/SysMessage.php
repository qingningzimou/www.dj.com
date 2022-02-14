<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\comprehensive;

use think\facade\Db;
use think\Validate;

class SysMessage extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'department_id' => 'require|number|max:5',
        'title' => 'require|length:2,64',
        'content' => 'require',
        'remark' => 'max:100',
        'deleted' => 'eq:1',
        'status' => 'require|number',
        'type' => 'in:1,2',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'title.require' => '标题不能为空',
        'title.unique' => '标题不能重复',
        'title.length' => '标题在2-64字符内',
        'department_id.require' => '部门机构ID不能为空',
        'department_id.number' => '部门机构ID只能为数字',
        'department_id.max' => '部门机构ID在5字符内',
        'content.require' => '消息内容不能为空',
        'remark.max' => '备注不能超过100个字符',
        'deleted.eq' => '非法提交',
        'status.require' => '非法提交',
        'status.number' => '非法提交',
        'type.in' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'title',
            'content',
        ],
        'edit' => [
            'id',
            'title',
            'content',
        ],
        'delete' => [
            'id',
            'deleted',
        ],
        'audit' => [
            'id',
            'status',
            'title',
            'content',
            'remark',
        ]
    ];

}