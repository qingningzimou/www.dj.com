<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\comprehensive;

use think\Validate;

class CommonReply extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'region_id' => 'require|number',
        'content' => 'require',
        'deleted' => 'eq:1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'region_id.require' => '区县id不能为空',
        'region_id.number' => '非法操作',
        'content.require' => '内容不能为空',
        'deleted.eq' => '非法提交',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'content',
        ],
        'edit' => [
            'id',
            'content',
        ],
        'delete' => [
            'id',
            'deleted',
        ],

    ];
}