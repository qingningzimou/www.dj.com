<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\system;

use think\Validate;

class SysRegion extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'parent_id' => 'require|number',
        'region_name' => 'require|unique:sys_region,deleted=0|chsDash|length:2,30',
        'region_code' => 'require|number|length:12,12',
        'simple_code' => 'require|number|length:6,6',
        'disabled' => 'require|in:0,1',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'parent_id.require' => '上级区域ID不能为空',
        'parent_id.number' => '上级区域ID只能为数字',
        'region_name.require' => '区域名称不能为空',
        'region_name.unique' => '区域名称重复',
        'region_name.chsDash' => '区域名称只能为汉字、字母数字和连接符',
        'region_name.length' => '区域名称在2-30字符内',
        'region_code.require' => '区域编码不能为空',
        'region_code.number' => '区域编码只能为数字',
        'region_code.length' => '区域编码为12字符',
        'simple_code.require' => '区域简码不能为空',
        'simple_code.number' => '区域简码只能为数字',
        'simple_code.length' => '区域简码为6字符',
        'disabled.require' => '区域禁用状态不能为空',
        'disabled.in' => '区域禁用状态只能为数字0-1',
        'deleted.eq' => '非法提交'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'parent_id',
            'region_name',
            'region_code',
            'simple_code',
        ],
        'edit' => [
            'id',
            'region_name',
            'region_code',
            'simple_code',
            'disabled',
        ],
        'delete' => [
            'id',
            'deleted'
        ]
    ];
}