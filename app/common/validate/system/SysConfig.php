<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:41
 */

namespace app\common\validate\system;

use think\Validate;

class SysConfig extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'item_name' => 'require|chsDash|length:2,24',
        'item_key' => 'require|unique:sys_config,deleted=0|alphaDash|length:2,36',
        'item_value' => 'require|length:1,2048',
        'explain' => 'max:500',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'item_name.require' => '项目名称不能为空',
        'item_name.chsDash' => '项目名称只能为汉字、字母、数字及连接符',
        'item_name.length' => '项目名称长度为2-24字符',
        'item_key.require' => '映射值不能为空',
        'item_key.unique' => '映射值重复',
        'item_key.alphaDash' => '映射值只能为字母、数字及连接符',
        'item_key.length' => '映射值长度为2-36字符',
        'item_value.require' => '参数值不能为空',
        'item_value.length' => '参数值长度为1-2048字符',
        'explain.max' => '项目说明长度为500字符内',
        'deleted.eq' => '非法提交'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'item_name',
            'item_key',
            'item_value',
            'explain'
        ],
        'edit' => [
            'id',
            'item_name',
            'item_key',
            'item_value',
            'explain'
        ],
        'delete' => [
            'id',
            'deleted'
        ]
    ];
}