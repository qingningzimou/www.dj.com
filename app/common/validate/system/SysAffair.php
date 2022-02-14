<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:41
 */

namespace app\common\validate\system;

use think\Validate;

class SysAffair extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'affair_name' => 'require|unique:sys_affair,deleted=0|chsAlpha|length:2,24',
        'affair_method' => 'require|alphaDash|length:2,48',
        'affair_controller' => 'require|regex:^[A-Za-z\.]+$|length:2,48',
        'explain' => 'max:100',
        'node_id' => 'require|number',
        'actived' => 'require|in:0,1',
        'disabled' => 'require|in:0,1',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'affair_name.require' => '事务名称不能为空',
        'affair_name.unique' => '事务名称重复',
        'affair_name.chsAlpha' => '事务名称只能为汉字、字母',
        'affair_name.length' => '事务名称长度为2-24字符',
        'affair_method.require' => '事务方法不能为空',
        'affair_method.alphaDash' => '事务方法只能为字母',
        'affair_method.length' => '事务方法长度为2-48字符',
        'affair_controller.require' => '事务控制器不能为空',
        'affair_controller.regex' => '事务控制器只能为字母和标点',
        'affair_controller.length' => '事务控制器长度为2-48字符',
        'explain.max' => '事务说明为100字符内',
        'node_id.require' => '事务节点ID不能为空',
        'node_id.number' => '事务节点ID只能为数字',
        'actived.require' => '事务主动监视不能为空',
        'actived.in' => '事务主动监视只能为数字0-1',
        'disabled.require' => '事务禁用状态不能为空',
        'disabled.in' => '事务禁用状态只能为数字0-1',
        'deleted.eq' => '非法提交'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'affair_name',
            'affair_method',
            'affair_controller',
            'explain',
            'actived'
        ],
        'edit' => [
            'id',
            'affair_name',
            'affair_method',
            'affair_controller',
            'explain',
            'actived',
            'disabled'
        ],
        'delete' => [
            'id',
            'deleted'
        ]
    ];
}