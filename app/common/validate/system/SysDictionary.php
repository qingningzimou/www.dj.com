<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\system;

use think\Validate;

class SysDictionary extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'parent_id' => 'require|number',
        'dictionary_name' => 'require|unique:sys_dictionary,deleted=0&parent_id=0|chsDash|length:2,31',
        'dictionary_code' => 'require|unique:sys_dictionary,deleted=0|alpha|length:2,31',
        'dictionary_value' => 'regex:^[A-Za-z0-9\.]+$|length:1,31',
        'dictionary_type' => 'require|in:1,2,3,4',
        'order_num' => 'require|number|max:5',
        'remarks' => 'max:100',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'parent_id.require' => '父字典ID不能为空',
        'parent_id.number' => '父字典ID只能为数字',
        'dictionary_name.require' => '字典名称不能为空',
        'dictionary_name.unique' => '字典名称重复',
        'dictionary_name.chsDash' => '字典名称只能为汉字、字母数字和连接符',
        'dictionary_name.length' => '字典名称在2-31字符内',
        'dictionary_code.require' => '字典编码不能为空',
        'dictionary_code.unique' => '字典编码重复',
        'dictionary_code.alpha' => '字典编码只能为字母',
        'dictionary_code.length' => '字典编码在2-31字符内',
        'dictionary_value.regex' => '字典数值只能为字母数字小数点',
        'dictionary_value.length' => '字典数值在1-31字符内',
        'dictionary_type.require' => '字典分类不能为空',
        'dictionary_type.in' => '字典分类只能为数字1-4',
        'order_num.require' => '列表排序不能为空',
        'order_num.number' => '列表排序只能为数字',
        'order_num.max' => '列表排序长度在5个字符内',
        'remarks.max' => '字典说明为100字符内',
        'deleted.eq' => '非法提交'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'parent_id',
            'dictionary_name',
            'dictionary_code',
            'dictionary_type',
            'dictionary_value',
            'order_num',
            'remarks'
        ],
        'edit' => [
            'id',
            'dictionary_name',
            'dictionary_code',
            'dictionary_type',
            'dictionary_value',
            'order_num',
            'remarks'
        ],
        'delete' => [
            'id',
            'deleted'
        ]
    ];
}