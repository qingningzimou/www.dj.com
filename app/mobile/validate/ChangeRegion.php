<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/25
 * Time: 11:21
 */

namespace app\mobile\validate;

use think\Validate;

class ChangeRegion extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'user_id' => 'require|number',
        'child_id' => 'require|number',
        'local_region_id' => 'require|number',
        'go_region_id' => 'require|number',
        'description' => 'require',
        'file' => 'require',
        'audit' => 'in:0,1,2',
        'deleted' => 'in:0,1',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的数据',
        'id.number' => '非法操作',
        'user_id.require' => '用户数据不存在',
        'user_id.number' => '非法操作',
        'child_id.require' => '学生数据不存在',
        'child_id.number' => '非法操作',
        'local_region_id.require' => '区县数据不存在',
        'local_region_id.number' => '非法操作',
        'go_region_id.require' => '区县数据不存在',
        'go_region_id.number' => '非法操作',
        'description.require' => '请填写描述凭证',
        'file.require' => '请上传证明文件',
        'deleted.in' => '非法操作',
        'audit.in' => '非法操作',
    ];

    protected $scene = [
        'add' => [
          'user_id',
          'child_id',
          'go_region_id',
          'description',
          'file',
          'deleted',
        ],
        'audit' => [
          'id',
          'audit',
        ],
 
    ];
}