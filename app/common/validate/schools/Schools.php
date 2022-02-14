<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:16
 */
namespace app\common\validate\schools;

use think\Validate;

class Schools extends Validate
{
    protected $rule = [
        'id' => 'require|number',
        'school_name' => 'require|unique:sys_school,deleted=0|length:2,64',
        'region_id' => 'require|number|max:5',
        'school_type' =>  'require|in:1,2',
        'school_attr' =>  'require|in:1,2',
        'setup_time' => 'require|date',
        'org_code' => 'require|unique:sys_school,deleted=0|alphaDash|length:2,64',
        'school_code' => 'require|unique:sys_school,deleted=0|alphaDash|length:2,64',
        'address' => 'require|length:2,128',
        'state' => 'in:0,1',
        /*'registered_capital' => 'require|float|max:5|egt:0',
        'school_licence' => 'max:64',
        'issuing_authority' => 'number',
        'attributes' => 'require|in:0,1',
        'contacts' => 'require|length:2,48',
        'real_name' => 'require|length:2,10',*/
        'telephone' => 'length:7,20|requireCallback:checkTelephone',
        //'contact_address' => 'require|length:2,128',
        'deleted' => 'eq:1'
    ];

    protected $message = [
        'id.require' => '请选择需要操作的信息',
        'id.number' => '非法操作',
        'school_name.require' => '学校名称不能为空',
        'school_name.unique' => '您的信息已注册，请与当地教育局联系',
        //'school_name.chsDash' => '学校名称只能为汉字、字母数字和连接符',
        'school_name.length' => '学校名称在63字符内',
        'school_type.require' => '学校类型不能为空',
        'school_type.in' => '学校类型只能为数字1-2',
        'school_attr.require' => '学校类型不能为空',
        'school_attr.in' => '学校类型只能为数字1-2',
        //'setup_time.require' => '设立时间不能为空',
        //'setup_time.date' => '设立时间不是正确的时间格式',
        'org_code.require' => '登记证号不能为空',
        'org_code.unique' => '登记证号重复',
        'org_code.alphaDash' => '登记证号只能为字母、数字和连接符',
        'org_code.length' => '登记证号在64字符内',
        'school_code.require' => '学校标识码不能为空',
        'school_code.unique' => '学校标识码重复',
        'school_code.alphaDash' => '学校标识码只能为字母、数字和连接符',
        'school_code.length' => '学校标识码在2-64字符内',
        'department_id.require' => '部门不能为空',
        'department_id.number' => '部门只能为数字',
        'region_id.require' => '区县ID不能为空',
        'region_id.number' => '区县ID只能为数字',
        'region_id.max' => '区县ID在5字符内',
        'address.require' => '地址不能为空',
        'address.length' => '地址在2-127字符内',
        /*'registered_capital.require' => '注册资金不能为空',
        'registered_capital.float' => '注册资金只能为数字',
        'registered_capital.egt' => '注册资金必须为正值',
        'registered_capital.max' => '注册资金单位万元，长度在5字符内',
        'school_licence.max' => '办学许可证在64字符内',
        'issuing_authority.number' => '许可证发证机关只能为数字',
        'attributes.require' => '举办者属性不能为空',
        'attributes.in' => '举办者属性只能为数字0-1',
        'contacts.require' => '举办者名称不能为空',
        'contacts.length' => '举办者名称在48字符内',*/
        //'telephone.require' => '联系电话不能为空',
        'telephone.length' => '联系电话在20个字符内',
        'telephone.regex' => '联系电话不正确',
        /*'contact_address.require' => '举办者通讯地址不能为空',
        'contact_address.length' => '举办者通讯地址在128字符内',
        'real_name.require' => '填报人实名不能为空',
        'real_name.length' => '填报人实名在10字符内',*/
        'state.in' => '非法提交',
        'deleted.eq' => '非法提交',
        'telephone.requireCallback' => '联系电话格式错误'
    ];

    protected $scene = [
        'info' => [
            'id'
        ],
        'add' => [
            'school_name',
            'school_type',
            'school_attr',
            //'school_code',
            'region_id',
            //'address',
            'telephone',
        ],
        'edit' => [
            'id',
            'school_name',
            'school_type',
            'school_attr',
            //'school_code',
            'region_id',
            //'address',
            'telephone',
        ],
        'state' => [
            'id',
            'state',
        ],
        'delete' => [
            'id',
            'deleted',
        ]
    ];

    /**
     * 检测手机号码或者固话
     * @param $value
     * @param $data
     * @return bool
     */
    public function checkTelephone($value, $data)
    {
        $tel = $data['telephone'];
        $isMob="/^1[3-8]{1}[0-9]{9}$/";
        $isTel="/^([0-9]-)?[0-9]$/";
        if(!preg_match($isMob,$tel) && !preg_match($isTel,$tel))
        {
            return false;
        }
        return true;
    }
}