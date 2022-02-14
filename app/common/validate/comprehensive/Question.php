<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\comprehensive;

use think\Validate;

class Question extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'title' => 'require|length:2,128',
        'content' => 'require',
        'deleted' => 'eq:1',
        //'tag' => 'require',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'title.require' => '标题不能为空',
        'title.length' => '标题在2-128字符内',
        'content.require' => '内容不能为空',
        //'tag.require' => '关键词不能为空',
        'deleted.eq' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'title',
            'content',
            //'tag',
        ],
        'edit' => [
            'id',
            'title',
            'content',
            //'tag',
        ],
        'delete' => [
            'id',
            'deleted',
        ],

    ];
}