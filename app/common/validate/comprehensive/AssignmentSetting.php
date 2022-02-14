<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\comprehensive;

use think\Validate;

class AssignmentSetting extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'name' => 'require|length:2,64',
        'house_type' => 'require|number',
        'area_birthplace_status' => 'require|in:0,1',
        'main_area_birthplace_status' => 'require|in:0,1',
        'area_house_status' => 'require|in:0,1',
        'main_area_house_status' => 'require|in:0,1',
        'area_business_status' => 'require|in:0,1',
        'area_residence_status' => 'require|in:0,1',
        'area_social_insurance_status' => 'require|in:0,1',
        'single_double' => 'require|number',
        'deleted' => 'eq:1',
        'status' => 'in:0,1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'name.require' => '名称不能为空',
        'name.length' => '名称在64字符内',
        'house_type.require' => '房产类型不能为空',
        'house_type.number' => '房产类型只能为数字',
        'area_birthplace_status.require' => '片区户籍不能为空',
        'area_birthplace_status.in' => '片区户籍只能为数字0-1',
        'main_area_birthplace_status.require' => '片区主城区户籍不能为空',
        'main_area_birthplace_status.in' => '片区主城区户籍只能为数字0-1',
        'area_house_status.require' => '片区有房不能为空',
        'area_house_status.in' => '片区有房只能为数字0-1',
        'main_area_house_status.require' => '片区主城区有房不能为空',
        'main_area_house_status.in' => '片区主城区有房只能为数字0-1',
        'area_business_status.require' => '片区经商不能为空',
        'area_business_status.in' => '片区经商只能为数字0-1',
        'area_residence_status.require' => '片区居住证不能为空',
        'area_residence_status.in' => '片区居住证只能为数字0-1',
        'area_social_insurance_status.require' => '片区社保不能为空',
        'area_social_insurance_status.in' => '片区社保只能为数字0-1',
        'single_double.require' => '单双符不能为空',
        'single_double.number' => '单双符数据只能为数字',
        'deleted.eq' => '非法提交',
        'status.in' => '非法操作'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'name',
            'house_type',
            'area_birthplace_status',
            'main_area_birthplace_status',
            'area_house_status',
            'main_area_house_status',
            'area_business_status',
            'area_residence_status',
            'area_social_insurance_status',
            'single_double',
        ],
        'edit' => [
            'id',
            'name',
            'house_type',
            'area_birthplace_status',
            'main_area_birthplace_status',
            'area_house_status',
            'main_area_house_status',
            'area_business_status',
            'area_residence_status',
            'area_social_insurance_status',
            'single_double',
        ],
        'delete' => [
            'id',
            'deleted',
        ],
        'status' => [
            'status',
            'id',
        ]
    ];
}