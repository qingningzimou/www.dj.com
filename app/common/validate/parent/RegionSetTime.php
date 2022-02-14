<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:41
 */

namespace app\common\validate\parent;

use think\Validate;

class RegionSetTime extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'grade_id' => 'require|in:1,2',
        'start_time' => 'require|date',
        'end_time' => 'require|date',
        'start_code' => 'require',
        'end_code' => 'require',
        'code' => 'require',
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'grade_id.in' => '班级数据错误',
        'start_time.require' => '开始时间不能为空',
        'start_time.date' => '开始时间格式不正确',
        'end_time.require' => '结束时间不能为空',
        'end_time.date' => '结束时间格式不正确',
        'start_code.require' => '非法操作',
        'end_code.require' => '非法操作',
        'code.require' => '非法操作',
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'grade_id',
            'start_time',
            'end_time',
        ],
        'edit' => [
            'id',
            'start_time',
            'end_time',
        ],
        'editTime' => [
          'code',
          'start_time',
          'end_time',
          'start_code',
          'end_code',
        ],
        'getTime' => [
            'code',
        ]
    ];
}