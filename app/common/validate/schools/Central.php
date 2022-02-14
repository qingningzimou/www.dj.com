<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\schools;

use think\Validate;

class Central extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'central_name' => 'require|unique:central_school,deleted=0|length:2,64',
        'region_id' => 'require|number|max:5',
        'police_id' => 'require|number|max:5',
        'disabled' => 'require|in:0,1',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'central_name.require' => '教管会名称不能为空',
        'central_name.unique' => '您的信息已注册，请与当地教育局联系',
        //'central_name.chsDash' => '教管会名称只能为汉字、字母数字和连接符',
        'central_name.length' => '教管会名称在2-64字符内',
        'region_id.require' => '区县ID不能为空',
        'region_id.number' => '区县ID只能为数字',
        'region_id.max' => '区县ID在5字符内',
        'police_id.require' => '派出所ID不能为空',
        'police_id.number' => '派出所ID只能为数字',
        'police_id.max' => '派出所ID在5字符内',
        'disabled.require' => '教管会状态不能为空',
        'disabled.in' => '非法提交',
        'deleted.eq' => '非法提交'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'central_name',
            'region_id',
            'police_id',
        ],
        'edit' => [
            'id',
            'central_name',
            'region_id',
            'police_id',
            //'disabled',
        ],
        'delete' => [
            'id',
            'deleted',
        ]
    ];
}