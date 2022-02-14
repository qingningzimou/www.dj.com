<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/22
 * Time: 8:31
 */
namespace app\common\validate;

use think\Validate;

class Index extends Validate
{
    protected $rule = [
        'source' => 'require|alpha',
        'source_id' => 'require|number',
        'source_type' => 'max:36'
    ];

    protected $message = [
        'source.require' => '来源不能为空',
        'source.number' => '来源只能为字母',
        'source_id.require' => '来源ID不能为空',
        'source_id.number' => '来源ID只能为数字',
        'source_type.max' => '来源类型在36字符内'
    ];

    protected $scene = [
        'upload' => [
            'source',
            'source_id',
            'source_type'
        ]
    ];
}