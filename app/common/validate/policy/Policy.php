<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\policy;

use think\Validate;

class Policy extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'title' => 'require|length:2,64',
        'type' => 'require|in:1,2',
        'region_id' => 'require|number|max:5',
        'department_id' => 'require|number',
        'content' => 'require',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'title.require' => '标题不能为空',
        'title.length' => '标题在2-64字符内',
        'type.require' => '类型不能为空',
        'type.in' => '类型只能为数字1-2',
        'department_id.require' => '部门机构不能为空',
        'department_id.number' => '部门机构只能为数字',
        'region_id.require' => '区县ID不能为空',
        'region_id.number' => '区县ID只能为数字',
        'region_id.max' => '区县ID在5字符内',
        'content.require' => '内容不能为空',
        'deleted.eq' => '非法提交'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'title',
            'region_id',
            'department_id',
            'type',
            'content',
        ],
        'edit' => [
            'id',
            'title',
            'type',
            'content',
        ],
        'delete' => [
            'id',
            'deleted',
        ],
        'list' => [
            'type',
        ]
    ];
}