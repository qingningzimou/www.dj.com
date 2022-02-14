<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:41
 */

namespace app\common\validate\parent;

use think\Validate;

class Course extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'name' => 'require|length:2,255',
        'content' => 'require',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'name.require' => '标题不能为空',
        'name.length' => '标题长度不正确',
        'content.require' => '内容不能为空',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'name',
            'content',
        ],
        'edit' => [
            'id',
            'name',
            'content',
        ],
        'delete' => [
            'id',
        ],
    ];
}