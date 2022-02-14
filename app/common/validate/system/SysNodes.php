<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:41
 */

namespace app\common\validate\system;

use think\Validate;

class SysNodes extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'node_name' => 'require|unique:sys_nodes,deleted=0|chsAlpha|length:2,24',
        'parent_id' => 'require|number',
        'icon' => 'length:2,24',
        'color_set' => 'length:2,31',
        'router_path' => 'length:2,63',
//        'router_name' => 'Alpha|length:2,48',
        'file_path' => 'length:2,63',
        'order_num' => 'require|number|max:5',
        'module_name' => 'Alpha|length:2,24',
        'controller_name' => 'length:2,31|regex:^[A-Za-z\.]+$',
        'method_name' => 'Alpha|length:2,31',
        'remarks' => 'max:100',
        'authority' => 'require|in:0,1',
        'signin' => 'require|in:0,1',
        'defaulted' => 'require|in:0,1',
        'abreast' => 'require|in:0,1',
        'displayed' => 'require|in:0,1',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'node_name.require' => '资源名称不能为空',
        'node_name.unique' => '资源名称重复',
        'node_name.chsAlpha' => '资源名称只能为汉字、字母',
        'node_name.length' => '资源名称长度为2-24字符',
        'parent_id.require' => '父资源ID不能为空',
        'parent_id.number' => '父资源ID只能为数字',
        'icon.length' => '图标长度为2-24字符',
        'color_set.length' => '配色长度为2-31字符',
        'router_path.length' => '路由路径长度为2-31字符',
//        'router_name.Alpha' => '路由名称只能为字母',
//        'router_name.length' => '路由名称长度为2-24字符',
        'file_path.length' => '文件路径长度为2-63字符',
        'order_num.require' => '列表排序不能为空',
        'order_num.number' => '列表排序只能为数字',
        'order_num.max' => '列表排序长度在5个字符内',
        'module_name.alpha' => '模块名称只能为字母',
        'module_name.length' => '模块名称长度为2-24字符',
        'controller_name.regex' => '控制器名称只能为字母和小数点',
        'controller_name.length' => '控制器名称长度为2-31字符',
        'method_name.alpha' => '方法名称只能为字母',
        'method_name.length' => '方法名称长度为2-31字符',
        'remarks.max' => '资源说明为100字符内',
        'authority.require' => '鉴权控制标志不能为空',
        'authority.in' => '鉴权控制标志只能为数字0-1',
        'signin.require' => '要求登录标志不能为空',
        'signin.in' => '要求登录标志只能为数字0-1',
        'defaulted.require' => '默认赋予标志不能为空',
        'defaulted.in' => '默认赋予标志只能为数字0-1',
        'abreast.require' => '并列显示标志不能为空',
        'abreast.in' => '并列显示标志只能为数字0-1',
        'displayed.require' => '菜单显示标志不能为空',
        'displayed.in' => '菜单显示标志只能为数字0-1',
        'deleted.eq' => '非法提交'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'node_name',
            'parent_id',
            'icon',
            'color_set',
            'router_path',
            'router_name',
            'file_path',
            'order_num',
            'module_name',
            'controller_name',
            'remarks',
            'authority',
            'signin',
            'defaulted',
            'abreast',
            'displayed'
        ],
        'edit' => [
            'id',
            'node_name',
            'icon',
            'color_set',
            'router_path',
            'router_name',
            'file_path',
            'order_num',
            'module_name',
            'controller_name',
            'remarks',
            'authority',
            'signin',
            'defaulted',
            'abreast',
            'displayed'
        ],
        'delete' => [
            'id',
            'deleted'
        ]
    ];
}