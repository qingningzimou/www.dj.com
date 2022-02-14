<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/7/18
 * Time: 18:14
 */

namespace app\mobile\controller;
use app\common\controller\MobileEducation;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;
use app\mobile\model\user\Apply;
use app\mobile\model\user\Child;
use app\mobile\model\user\Family;
use app\mobile\model\user\House;
use app\mobile\model\user\Ancestor;
use app\mobile\model\user\Residence;
use app\mobile\model\user\Insurance;
use app\mobile\model\user\Company;
use app\mobile\model\user\ApplyTmp;
use app\mobile\model\user\SixthGrade;
use app\mobile\model\user\UserApplyStatus;
use app\common\model\RegionSetTime;
use comparison\Reckon;
use think\facade\Log;
use dictionary\FilterData;


class Comparison extends MobileEducation
{
    /**
     * 入学申请比对
     * @return array
     */
    public function actConduct(){
        try {
            ignore_user_abort(true);
            set_time_limit(0);
            $preg = '/^\d+$/u';
            if (preg_match($preg,$this->result['id']) == 0){
                throw new \Exception(Lang::get('check_fail'));
            }
            $tmp_id = $this->result['id'];
            $tmpData = (new ApplyTmp())->where([
                'user_id' => $this->result['user_id'],
                'id' => $tmp_id
            ])->find();
            if(empty($tmpData)){
                throw new \Exception('错误的参数或无权限访问');
            }
            $applyData = (new Apply())->where([
                'id' => $tmpData['apply_id'],
            ])->find();
            if(empty($applyData)){
                throw new \Exception('未找到入学申报信息');
            }
            $applyStatusData = (new UserApplyStatus())->where([
                'user_apply_id' => $tmpData['apply_id'],
            ])->find();
            if(empty($applyStatusData)){
                throw new \Exception('未找到申报状态信息');
            }
            $dictionary = new FilterData();
            $reckon = new Reckon();
            $region = Cache::get('region',[]);
            //获取学生信息
            $childData = Child::field([
                'id',
                'real_name',
                'idcard',
                'birthday',
                'api_name',
                'api_policestation',
                'api_policestation_id',
                'api_area',
                'api_address',
                'api_card',
                'api_relation',
                'api_region_id',
                'is_main_area',
            ])->find($tmpData['child_id']);
            if(empty($childData)){
                throw new \Exception('学生信息错误');
            }
            //获取监护人信息
            $familyData = Family::field([
                'id',
                'parent_name',
                'idcard',
                'relation',
                'api_name',
                'api_policestation',
                'api_policestation_id',
                'api_area',
                'api_address',
                'api_card',
                'api_relation',
                'api_region_id',
                'is_area_main',
            ])->find($tmpData['family_id']);
            if(empty($familyData)){
                throw new \Exception('监护人信息错误');
            }
            //获取房产信息
            $houseData = House::field([
                'id',
                'family_id',
                'house_type',
                'code_type',
                'cert_code',
                'api_address',
                'api_house_code',
                'api_area_code',
                'api_region_id',
            ])->find($tmpData['house_id']);
            if(empty($houseData)){
                throw new \Exception('房产信息错误');
            }
            $auto_check_child = 0;//学生信息系统比对状态
            $auto_check_family = 0;//家长信息系统比对状态
            $auto_check_relation = 0;//系统比对关系是否属实
            $auto_check_birthplace_area = 0;//片区户籍信息系统比对状态
            $auto_check_birthplace_main_area = 0;//主城区户籍信息系统比对状态
            $auto_check_house_area = 0;//片区房产信息系统比对状态
            $auto_check_house_main_area = 0;//片区主城区房产信息系统比对状态
            $auto_check_company = 0;//工商信息系统比对状态
            $auto_check_insurance = 0;//社保信息系统比对状态
            $auto_check_residence = 0;//居住证信息系统比对状态
            $auto_check_ancestor = 0;//三代同堂关系是否属实


            $chk_cross = 0;//定义跨区处理标识（对应紫贞派出所和二十中）
            $birthplace_status = 0;//定义户籍情况
            $guardian_relation = 0;//定义监护人情况
            $house_status = 0;//定义匹配学校情况
            $insurance_status = 0;//定义社保情况
            $business_license_status = 0;//定义工商情况
            $residence_permit_status = 0;//定义居住证情况
            $house_matching_school_id = 0;//定义房产匹配学校

            //如果未比对学生信息
            if($tmpData['check_child'] == 0){
                Db::startTrans();
                try{
                    $resReckon = $reckon->CheckChild($tmp_id,$tmpData['child_id'],$childData['idcard']);
                    if($resReckon['code']){
                        $childData = Child::field([
                            'id',
                            'real_name',
                            'idcard',
                            'birthday',
                            'api_name',
                            'api_policestation',
                            'api_policestation_id',
                            'api_area',
                            'api_address',
                            'api_card',
                            'api_relation',
                            'api_region_id',
                            'is_main_area',
                        ])->master(true)->find($tmpData['child_id']);
                        $tmpData['check_child'] = $resReckon['data'];
                    }else{
                        throw new \Exception($resReckon['msg']);
                    }
                    Db::commit();
                }catch (\Exception $exception){
                    Log::write($exception->getMessage() ?: Lang::get('system_error'));
                    Db::rollback();
                }
            }
            //如果未比对监护人信息
            if($tmpData['check_family'] == 0){
                Db::startTrans();
                try{
                    $resReckon = $reckon->CheckFamily($tmp_id,$tmpData['family_id'],$familyData['idcard']);
                    if($resReckon['code']){
                        $familyData = Family::field([
                            'id',
                            'parent_name',
                            'idcard',
                            'relation',
                            'api_name',
                            'api_policestation',
                            'api_policestation_id',
                            'api_area',
                            'api_address',
                            'api_card',
                            'api_relation',
                            'api_region_id',
                            'is_area_main',
                        ])->master(true)->find($tmpData['family_id']);
                        $tmpData['check_family'] = $resReckon['data'];
                    }else{
                        throw new \Exception($resReckon['msg']);
                    }
                    Db::commit();
                }catch (\Exception $exception){
                    Log::write($exception->getMessage() ?: Lang::get('system_error'));
                    Db::rollback();
                }
            }
            //如果有祖辈信息
            if($tmpData['ancestors_family_id']){
                //获取祖辈信息
                $ancestorsFamilyData = Family::field([
                    'id',
                    'parent_name',
                    'idcard',
                    'relation',
                    'api_name',
                    'api_policestation',
                    'api_policestation_id',
                    'api_area',
                    'api_address',
                    'api_card',
                    'api_relation',
                    'api_region_id',
                    'is_area_main',
                ])->find($tmpData['ancestors_family_id']);
                if(empty($ancestorsFamilyData)){
                    throw new \Exception('祖辈信息错误');
                }
                //如果未比对祖辈信息
                if($tmpData['check_ancestors'] == 0){
                    Db::startTrans();
                    try{
                        $resReckon = $reckon->CheckAncestors($tmp_id,$tmpData['ancestors_family_id'],$ancestorsFamilyData['idcard']);
                        if($resReckon['code']){
                            $ancestorsFamilyData = Family::field([
                                'id',
                                'parent_name',
                                'idcard',
                                'relation',
                                'api_name',
                                'api_policestation',
                                'api_policestation_id',
                                'api_area',
                                'api_address',
                                'api_card',
                                'api_relation',
                                'api_region_id',
                                'is_area_main',
                            ])->master(true)->find($tmpData['ancestors_family_id']);
                            $tmpData['check_ancestors'] = $resReckon['data'];
                        }else{
                            throw new \Exception($resReckon['msg']);
                        }
                        Db::commit();
                    }catch (\Exception $exception){
                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                        Db::rollback();
                    }
                }
            }
            //如果未比对房产信息
            if($tmpData['check_house'] == 0){
                if($tmpData['ancestor_id'] && $tmpData['ancestors_family_id']){
                    $chk_idcard = $ancestorsFamilyData['idcard'];
                }else{
                    $chk_idcard = $familyData['idcard'];
                }
                Db::startTrans();
                try{
                    $resReckon = $reckon->CheckHouse($tmp_id,$tmpData['house_id'],$chk_idcard,$houseData['house_type'],$houseData['code_type'],$houseData['cert_code']);
                    if($resReckon['code']){
                        $houseData = House::field([
                            'id',
                            'family_id',
                            'house_type',
                            'code_type',
                            'cert_code',
                            'api_address',
                            'api_house_code',
                            'api_area_code',
                            'api_region_id',
                        ])->master(true)->find($tmpData['house_id']);
                        $tmpData['check_house'] = $resReckon['data'];
                    }else{
                        throw new \Exception($resReckon['msg']);
                    }
                    Db::commit();
                }catch (\Exception $exception){
                    Log::write($exception->getMessage() ?: Lang::get('system_error'));
                    Db::rollback();
                }
            }
            //如果有其它监护人信息
            if($tmpData['other_family_id']){
                //获取其它监护人信息
                $otherFamilyData = Family::field([
                    'id',
                    'parent_name',
                    'idcard',
                    'relation',
                    'api_name',
                    'api_policestation',
                    'api_policestation_id',
                    'api_area',
                    'api_address',
                    'api_card',
                    'api_relation',
                    'api_region_id',
                    'is_area_main',
                ])->find($tmpData['other_family_id']);
                //如果未比对其它监护人信息
                if($tmpData['check_other'] == 0 && !empty($otherFamilyData)){
                    Db::startTrans();
                    try{
                        $resReckon = $reckon->CheckFamily($tmp_id,$tmpData['other_family_id'],$otherFamilyData['idcard']);
                        if($resReckon['code']){
                            $otherFamilyData = Family::field([
                                'id',
                                'parent_name',
                                'idcard',
                                'relation',
                                'api_name',
                                'api_policestation',
                                'api_policestation_id',
                                'api_area',
                                'api_address',
                                'api_card',
                                'api_relation',
                                'api_region_id',
                                'is_area_main',
                            ])->master(true)->find($tmpData['other_family_id']);
                            $tmpData['check_other'] = $resReckon['data'];
                        }else{
                            throw new \Exception($resReckon['msg']);
                        }
                        Db::commit();
                    }catch (\Exception $exception){
                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                        Db::rollback();
                    }
                }
            }

            //如果有三代同堂关系信息
            if($tmpData['ancestor_id']){
                //获取三代同堂关系信息
                $ancestorData = Ancestor::field([
                    'id',
                    'child_id',
                    'family_id',
                    'father_id',
                    'mother_id',
                    'singled',
                    'api_child_relation',
                    'api_parent_relation',
                    'api_grandparent_relation',
                    'api_father_idcard',
                    'api_mother_idcard',
                    'api_other_status',
                    'api_marriage_status',
                    'api_parent_status',
                    'api_grandparent_status',
                    'api_child_house',
                    'api_father_house',
                    'api_mother_house',
                ])->find($tmpData['ancestor_id']);
                if(empty($ancestorData)){
                    throw new \Exception('三代同堂关系信息错误');
                }
                //如果未比对三代同堂关系信息
                if($tmpData['check_generations'] == 0){
                    if(!isset($ancestorsFamilyData) || empty($ancestorsFamilyData)){
                        throw new \Exception('祖辈信息错误');
                    }
                    $other_idcard = '';
                    if(isset($otherFamilyData) || !empty($otherFamilyData)){
                        $other_idcard = $otherFamilyData['idcard'];
                    }
                    Db::startTrans();
                    try{
                        $resReckon = $reckon->CheckAncestor($tmp_id,$tmpData['ancestor_id'],$childData['idcard'],$familyData['idcard'],$ancestorsFamilyData['idcard'],$other_idcard);
                        if($resReckon['code']){
                            $ancestorData = Ancestor::field([
                                'id',
                                'child_id',
                                'family_id',
                                'father_id',
                                'mother_id',
                                'singled',
                                'api_child_relation',
                                'api_parent_relation',
                                'api_grandparent_relation',
                                'api_father_idcard',
                                'api_mother_idcard',
                                'api_other_status',
                                'api_marriage_status',
                                'api_parent_status',
                                'api_grandparent_status',
                                'api_child_house',
                                'api_father_house',
                                'api_mother_house',
                            ])->master(true)->find($tmpData['ancestor_id']);
                            $tmpData['check_generations'] = $resReckon['data'];
                        }else{
                            throw new \Exception($resReckon['msg']);
                        }
                        Db::commit();
                    }catch (\Exception $exception){
                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                        Db::rollback();
                    }
                }
            }
            //如果有工商信息
            if($tmpData['company_id']){
                //获取工商信息
                $companyData = Company::field([
                    'id',
                    'family_id',
                    'house_id',
                    'org_code',
                    'api_lawer_name',
                    'api_company_name',
                    'api_address',
                    'api_organ',
                    'api_establish',
                    'api_company_status',
                    'api_area',
                ])->find($tmpData['company_id']);
                //如果未比对工商信息
                if($tmpData['check_company'] == 0 && !empty($companyData)){
                    Db::startTrans();
                    try{
                        $resReckon = $reckon->CheckCompany($tmp_id,$tmpData['company_id'],$companyData['org_code']);
                        if($resReckon['code']){
                            $companyData = Company::field([
                                'id',
                                'family_id',
                                'house_id',
                                'org_code',
                                'api_lawer_name',
                                'api_company_name',
                                'api_address',
                                'api_organ',
                                'api_establish',
                                'api_company_status',
                                'api_area',
                            ])->master(true)->find($tmpData['company_id']);
                            $tmpData['check_company'] = $resReckon['data'];
                        }else{
                            throw new \Exception($resReckon['msg']);
                        }
                        Db::commit();
                    }catch (\Exception $exception){
                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                        Db::rollback();
                    }
                }
            }
            //如果有居住证信息
            if($tmpData['residence_id']){
                //获取居住证信息
                $residenceData = Residence::field([
                    'id',
                    'family_id',
                    'house_id',
                    'live_code',
                    'api_address',
                    'api_hold_address',
                    'api_end_time',
                    'api_code',
                    'api_area_code',
                ])->find($tmpData['residence_id']);
                //如果未比对居住证信息
                if($tmpData['check_residence'] == 0 && !empty($residenceData)){
                    Db::startTrans();
                    try{
                        $resReckon = $reckon->CheckResidence($tmp_id,$tmpData['residence_id'],$residenceData['live_code']);
                        if($resReckon['code']){
                            $residenceData = Residence::field([
                                'id',
                                'family_id',
                                'house_id',
                                'live_code',
                                'api_address',
                                'api_hold_address',
                                'api_end_time',
                                'api_code',
                                'api_area_code',
                            ])->master(true)->find($tmpData['residence_id']);
                            $tmpData['check_residence'] = $resReckon['data'];
                        }else{
                            throw new \Exception($resReckon['msg']);
                        }
                        Db::commit();
                    }catch (\Exception $exception){
                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                        Db::rollback();
                    }
                }
            }
            //如果有社保信息
            if($tmpData['insurance_id']){
                //获取社保信息
                $insuranceData = Insurance::field([
                    'id',
                    'family_id',
                    'house_id',
                    'social_code',
                    'api_code',
                    'api_join_time',
                    'api_end_time',
                    'api_company_name',
                    'api_adcode',
                ])->find($tmpData['insurance_id']);
                //如果未比对社保信息
                if($tmpData['check_insurance'] == 0 && !empty($insuranceData)){
                    Db::startTrans();
                    try{
                        $resReckon = $reckon->CheckInsurance($tmp_id,$tmpData['insurance_id'],$insuranceData['social_code']);
                        if($resReckon['code']){
                            $insuranceData = Insurance::field([
                                'id',
                                'family_id',
                                'house_id',
                                'social_code',
                                'api_code',
                                'api_join_time',
                                'api_end_time',
                                'api_company_name',
                                'api_adcode',
                            ])->master(true)->find($tmpData['insurance_id']);
                            $tmpData['check_insurance'] = $resReckon['data'];
                        }else{
                            throw new \Exception($resReckon['msg']);
                        }
                        Db::commit();
                    }catch (\Exception $exception){
                        Log::write($exception->getMessage() ?: Lang::get('system_error'));
                        Db::rollback();
                    }
                }
            }
            //如果学生信息已比对
            if($tmpData['check_child'] == 1){
                //如果有比对后的区域ID
                if($childData['api_region_id'] > 0){
                    //如果对比申请的区县和派出所所在区县一样 则是本区县学生
                    if($childData['api_region_id'] == $applyData['region_id']){
                        //本区县非主城区
                        $auto_check_child = 1;//学生信息本区县非主城区
                        $auto_check_birthplace_area = 1;//是片区户籍
                        $auto_check_birthplace_main_area = 2;//不是主城区户籍
                        $birthplace_status = 2;//非主城区
                        //派出所对应主城区
                        if($childData['is_main_area'] == 1){
                            //本区县主城区
                            $auto_check_child = 2;//学生信息本区县主城区
                            $auto_check_birthplace_main_area = 1;//是主城区户籍
                            $birthplace_status = 1;//主城区
                        }
                        //不是本区域
                    }else{
                        $auto_check_child = -1;//学生信息不在本区
                        $auto_check_birthplace_area = 2;//不是片区户籍
                        $auto_check_birthplace_main_area = 2;//不是主城区户籍
                        $birthplace_status = 3;//襄阳市非本区
                    }
                    //如果学生比对有派出所ID
                    if($childData['api_policestation_id']){
                        //获取跨区派出所
                        $getData = $dictionary->findValue('dictionary', 'SYSKQCL', 'SYSKQPCS');
                        if(!$getData['code']){
                            throw new \Exception($getData['msg']);
                        }
                        $filter_police_id = $getData['data'];
                        //匹配到跨区派出所并且申报区域未对应派出所区域
                        if($childData['api_policestation_id'] == $filter_police_id && $applyData['region_id'] != $childData['api_region_id']){
                            $getData = $dictionary->findValue('dictionary', 'SYSKQPCS','SYSKQXXYI');
                            if(!$getData['code']){
                                throw new \Exception($getData['msg']);
                            }
                            $primary_school_id =  $getData['data'];
                            $getData = $dictionary->findValue('dictionary', 'SYSKQPCS','SYSKQXXER');
                            if(!$getData['code']){
                                throw new \Exception($getData['msg']);
                            }
                            $middle_school_id =  $getData['data'];
                            $birthplaceData = Db::table("deg_sys_address_birthplace")->where('address',$childData['api_address'])->find();;
                            if(!empty($birthplaceData)){
                                //如果户籍地址有学校认领
                                if($birthplaceData['primary_school_id'] == $primary_school_id || $birthplaceData['middle_school_id'] == $middle_school_id){
                                    $chk_cross = 1;
                                }
                            }
                        }
                    }
                }else{
                    //无匹配派出所
                    $auto_check_child = -2;//学生信息无结果
                    $auto_check_birthplace_area = -1;//片区户籍无结果
                    $auto_check_birthplace_main_area = -1;//主城区户籍无结果
                    $birthplace_status = 5;
                }
            }elseif($tmpData['check_child'] == -1){
                $auto_check_child = -2;//学生信息无结果
                $auto_check_birthplace_area = -1;//片区户籍无结果
                $auto_check_birthplace_main_area = -1;//主城区户籍无结果
                $birthplace_status = 5;//未对比成功
                //非襄阳市身份证
                if(substr($childData['idcard'],0,4) != '4206'){
                    $birthplace_status = 4;//非襄阳市
                }
            }
            //比对学籍信息
            $sixthGrade = (new SixthGrade())->where('id_card', $childData['idcard'])->find();
            if(!empty($sixthGrade)){
                $student_status = 2;//学生学籍不在本区
                if($applyData['region_id'] == $sixthGrade['region_id']){
                    $student_status = 1;//学生学籍在本区
                }
            }else{
                $student_status = 3;//学生学籍无结果
            }
            //获取招生年龄配置
            $region_time = (new RegionSetTime())
                ->where('region_id',$applyData['region_id'])
                ->where('grade_id',$applyData['school_type'])
                ->find();
            $child_age = 0;
            if($region_time){
                //                        $birthday = strtotime($childInfo['birthday']);
                $birthday = $childData['birthday'];
                if($birthday >= $region_time['start_time'] && $birthday <= $region_time['end_time']){
                    $child_age = 1;  //足龄
                }else if($birthday < $region_time['start_time']){
                    $child_age = 3; // 超龄
                }else{
                    $child_age = 2; //不足龄
                }
            }
            //如果家长信息已比对
            if($tmpData['check_family'] == 1){
                //如果有比对后的区域ID
                if($familyData['api_region_id'] > 0){
                    //如果对比申请的区县和公安局区县一样 则是本区县户籍
                    if($familyData['api_region_id'] == $applyData['region_id']){
                        //本区县非主城区
                        $auto_check_family = 1;//家长信息本区县非主城区
                        //派出所对应主城区
                        if($familyData['is_area_main'] == 1){
                            //本区县主城区
                            $auto_check_family = 2;//家长信息本区县主城区
                        }
                        //不是本区域
                    }else{
                        $auto_check_family = -1;//家长信息不在本区
                    }
                }else{
                    //无匹配派出所
                    $auto_check_family = -2;//家长信息无结果
                }
            }elseif($tmpData['check_family'] == -1){
                $auto_check_family = -2;//家长信息无结果
            }
            //比对监护人关系
            $resReckon = $reckon->CheckGuardian($childData['idcard'],$familyData['idcard']);
            if($resReckon['code']){
                if($resReckon['data']['status'] == 1){
                    $auto_check_relation = 1;//属实
                    $guardian_relation = 1;
                }elseif($resReckon['data']['status'] == -1){
                    $auto_check_relation = 2;//不属实
                    $guardian_relation = 2;
                }else{
                    $auto_check_relation = -1;//无结果
                    $guardian_relation = 3;
                }
            }
            //如果房产信息已比对
            if($tmpData['check_house'] == 1){
                $regionData = filter_value_one($region, 'id', $applyData['region_id']);
                if(count($regionData)){
                    $region_code = $regionData['simple_code'];
                }else{
                    throw new \Exception('入学申报区域数据错误');
                }
                $school = Cache::get('school',[]);
                $central = Cache::get('central',[]);
                $police = Cache::get('police',[]);
                //获取申报区域已勾选缩略对应详细房产信息
                $addressData = Db::table("deg_sys_address_{$region_code}")->where('address',$houseData['api_address'])->find();;
                if(!empty($addressData)){
                    $auto_check_house_area = 1;//是片区有房
                    $auto_check_house_main_area = 1;//是片区主城区有房
                    $house_status = 2;//无匹配学校
                    if($applyData['school_type'] == 1 && $addressData['primary_school_id']){
                        //房产匹配学校-小学
                        $house_status = 1;//有匹配学校
                        $house_matching_school_id = $addressData['primary_school_id'];
                        //获取学校信息
                        $schoolData = filter_value_one($school, 'id', $addressData['primary_school_id']);
                        //如果学校信息存在
                        if(count($schoolData)){
                            //如果学校受教管会管理
                            if($schoolData['central_id']){
                                //获取教管会信息
                                $centralData = filter_value_one($central, 'id', $schoolData['central_id']);
                                if(count($centralData)){
                                    //获取派出所信息
                                    $policeData = filter_value_one($police, 'id', $centralData['police_id']);
                                    if(count($policeData)){
                                        if(!$policeData['is_main_area']){
                                            $auto_check_house_main_area = 2;//不是片区主城区有房
                                        }
                                    }else{
                                        throw new \Exception('派出所数据错误');
                                    }
                                }else{
                                    throw new \Exception('教管会数据错误');
                                }
                            }
                        }else{
                            throw new \Exception('学校数据错误');
                        }
                    }
                    if($applyData['school_type'] == 2 && $addressData['middle_school_id']){
                        //房产匹配学校-初中
                        $house_status = 1;//有匹配学校
                        $house_matching_school_id = $addressData['middle_school_id'];
                        //获取学校信息
                        $schoolData = filter_value_one($school, 'id', $addressData['middle_school_id']);
                        //如果学校信息存在
                        if(count($schoolData)){
                            //如果学校受教管会管理
                            if($schoolData['central_id']){
                                //获取教管会信息
                                $centralData = filter_value_one($central, 'id', $schoolData['central_id']);
                                if(count($centralData)){
                                    //获取派出所信息
                                    $policeData = filter_value_one($police, 'id', $centralData['police_id']);
                                    if(count($policeData)){
                                        if(!$policeData['is_main_area']){
                                            $auto_check_house_main_area = 2;//不是片区主城区有房
                                        }
                                    }else{
                                        throw new \Exception('派出所数据错误');
                                    }
                                }else{
                                    throw new \Exception('教管会数据错误');
                                }
                            }
                        }else{
                            throw new \Exception('学校数据错误');
                        }
                    }
                }else{
                    $auto_check_house_area = 2;//不是片区有房
                    $auto_check_house_main_area = 2;//不是片区主城区有房
                    $house_status = 2;//无匹配学校
                }
                if($auto_check_house_area == 0){
                    //获取申报区域完整地址房产信息
                    $addressData = Db::table("deg_sys_address_intact")->where('address',$houseData['api_address'])->find();;
                    if(!empty($addressData)){
                        $auto_check_house_area = 1;//是片区有房
                        $auto_check_house_main_area = 1;//是片区主城区有房
                        $house_status = 2;//无匹配学校
                        if($applyData['school_type'] == 1 && $addressData['primary_school_id']){
                            //房产匹配学校-小学
                            $house_status = 1;//有匹配学校
                            $house_matching_school_id = $addressData['primary_school_id'];
                            //获取学校信息
                            $schoolData = filter_value_one($school, 'id', $addressData['primary_school_id']);
                            //如果学校信息存在
                            if(count($schoolData)){
                                //如果学校受教管会管理
                                if($schoolData['central_id']){
                                    //获取教管会信息
                                    $centralData = filter_value_one($central, 'id', $schoolData['central_id']);
                                    if(count($centralData)){
                                        //获取派出所信息
                                        $policeData = filter_value_one($police, 'id', $centralData['police_id']);
                                        if(count($policeData)){
                                            if(!$policeData['is_main_area']){
                                                $auto_check_house_main_area = -1;//不是片区主城区有房
                                            }
                                        }else{
                                            throw new \Exception('派出所数据错误');
                                        }
                                    }else{
                                        throw new \Exception('教管会数据错误');
                                    }
                                }
                            }else{
                                throw new \Exception('学校数据错误');
                            }
                        }
                        if($applyData['school_type'] == 2 && $addressData['middle_school_id']){
                            //房产匹配学校-初中
                            $house_status = 1;//有匹配学校
                            $house_matching_school_id = $addressData['middle_school_id'];
                            //获取学校信息
                            $schoolData = filter_value_one($school, 'id', $addressData['middle_school_id']);
                            //如果学校信息存在
                            if(count($schoolData)){
                                //如果学校受教管会管理
                                if($schoolData['central_id']){
                                    //获取教管会信息
                                    $centralData = filter_value_one($central, 'id', $schoolData['central_id']);
                                    if(count($centralData)){
                                        //获取派出所信息
                                        $policeData = filter_value_one($police, 'id', $centralData['police_id']);
                                        if(count($policeData)){
                                            if(!$policeData['is_main_area']){
                                                $auto_check_house_main_area = -1;//不是片区主城区有房
                                            }
                                        }else{
                                            throw new \Exception('派出所数据错误');
                                        }
                                    }else{
                                        throw new \Exception('教管会数据错误');
                                    }
                                }
                            }else{
                                throw new \Exception('学校数据错误');
                            }
                        }
                    }else{
                        $auto_check_house_area = 2;//不是片区有房
                        $auto_check_house_main_area = 2;//不是片区主城区有房
                        $house_status = 2;//无匹配学校
                    }
                }
            }elseif($tmpData['check_house'] == -1){
                $auto_check_house_area = -1;//片区有房无结果
                $auto_check_house_main_area = -1;//片区主城区有房无结果
                $house_status = 3;//房产信息无结果
            }
            //如果工商信息已比对
            if($tmpData['check_company'] == 1){
                $company_region_id = 0;
                $companyRegion = filter_value_one($region, 'simple_code', $companyData['api_area']);
                if(count($companyRegion)){
                    $company_region_id = $companyRegion['id'];
                }
                if(($companyData['api_company_status'] == '存续（在营、开业、在册）') && ($familyData['parent_name'] == $companyData['api_lawer_name']) && $company_region_id == $applyData['region_id']){
                    $auto_check_company = 1;//本区经商
                    $business_license_status = 1;
                }else{
                    $auto_check_company = 2;//不是本区经商
                }
            }elseif($tmpData['check_company'] == -1){
                $auto_check_company = -1;//工商信息无结果
            }
            //如果社保信息已比对
            if($tmpData['check_insurance'] == 1){
                $insurance_region_id = 0;
                $insuranceRegion = filter_value_one($region, 'simple_code', $insuranceData['api_adcode']);
                if(count($insuranceRegion)){
                    $insurance_region_id = $insuranceRegion['id'];
                }
                if(!empty($insuranceData['api_end_time']) && strtotime('-1year') > strtotime($insuranceData['api_join_time'])  && $insurance_region_id == $applyData['region_id']){
                    $auto_check_insurance = 1;//本区社保
                    $insurance_status = 1;
                }else{
                    $auto_check_insurance = 2;//不是本区社保
                }
            }elseif($tmpData['check_insurance'] == -1){
                $auto_check_insurance = -1;//社保无结果
            }
            //如果居住证已比对
            if($tmpData['check_residence'] == 1){
                $residence_region_id = 0;
                $residenceRegion = filter_value_one($region, 'simple_code', $residenceData['api_area_code']);
                if(count($residenceRegion)){
                    $residence_region_id = $residenceRegion['id'];
                }
                if(date_to_unixtime($residenceData['api_end_time']) > time() && $residence_region_id == $applyData['region_id']){
                    $auto_check_residence = 1;//本区居住证
                    $residence_permit_status = 1;
                }else{
                    $auto_check_residence = 2;//不是本区居住证
                }
            }elseif($tmpData['check_insurance'] == -1){
                $auto_check_residence = -1;//社保无结果
            }
            //如果三代同堂信息已比对
            if($tmpData['check_generations'] == 1){
                $auto_check_ancestor = -1;//无结果
                //如果学生和监护人以及祖辈户口同本
                if($ancestorData['api_parent_status'] == 1 && $ancestorData['api_grandparent_status'] == 1){
                    $auto_check_ancestor = 1;//属实
                }
                //如果单亲状态比对为结婚
                if($ancestorData['singled'] && $ancestorData['api_marriage_status']){
                    $auto_check_ancestor = 2;//不属实
                }
                //如果学生和监护人以及祖辈存在户口不同本
                if($ancestorData['api_parent_status'] == -1 || $ancestorData['api_grandparent_status'] == -1){
                    $auto_check_ancestor = 2;//不属实
                }
                //如果学生、父母名下有房
                if($ancestorData['api_child_house'] == 1 || $ancestorData['api_father_house'] == 1 || $ancestorData['api_mother_house'] == 1){
                    $auto_check_ancestor = 2;//不属实
                }
            }
            //检查比对状态是否均已完成
            $auto_check_completed = 1;
            if($auto_check_child == 0 || $auto_check_family == 0 || $auto_check_relation == 0 ){
                $auto_check_completed = 0;
            }
            if($auto_check_birthplace_area == 0 || $auto_check_birthplace_main_area == 0 || $auto_check_house_area == 0 || $auto_check_house_main_area == 0){
                $auto_check_completed = 0;
            }
            if($auto_check_birthplace_area == 0 || $auto_check_birthplace_main_area == 0 || $auto_check_house_area == 0 || $auto_check_house_main_area == 0){
                $auto_check_completed = 0;
            }
            if($applyStatusData['need_check_company'] && $auto_check_company == 0){
                $auto_check_completed = 0;
            }
            if($applyStatusData['need_check_insurance'] && $auto_check_insurance == 0){
                $auto_check_completed = 0;
            }
            if($applyStatusData['need_check_residence'] && $auto_check_residence == 0){
                $auto_check_completed = 0;
            }
            if($applyStatusData['need_check_ancestor'] && $auto_check_ancestor == 0){
                $auto_check_completed = 0;
            }
            $status_data['auto_check_child'] = $auto_check_child;
            $status_data['auto_check_family'] = $auto_check_family;
            $status_data['auto_check_relation'] = $auto_check_relation;
            $status_data['auto_check_birthplace_area'] = $auto_check_birthplace_area;
            $status_data['auto_check_birthplace_main_area'] = $auto_check_birthplace_main_area;
            $status_data['auto_check_house_area'] = $auto_check_house_area ;
            $status_data['auto_check_house_main_area'] = $auto_check_house_main_area ;
            $status_data['auto_check_company'] = $auto_check_company;
            $status_data['auto_check_insurance'] = $auto_check_insurance;
            $status_data['auto_check_residence'] = $auto_check_residence;
            $status_data['auto_check_ancestor'] = $auto_check_ancestor;
            $status_data['auto_check_completed'] = $auto_check_completed;

            $detail_data['chk_cross'] = $chk_cross;
            $detail_data['child_age_status'] = $child_age;
            $detail_data['birthplace_status'] = $birthplace_status;
            $detail_data['guardian_relation'] = $guardian_relation;
            $detail_data['house_status'] = $house_status;
            $detail_data['student_status'] = $student_status;
            $detail_data['insurance_status'] = $insurance_status;
            $detail_data['business_license_status'] = $business_license_status;
            $detail_data['residence_permit_status'] = $residence_permit_status;
            $detail_data['house_matching_school_id'] = $house_matching_school_id;
            //检查比对项目是否均已完成
            $check_completed = 1;
            if($tmpData['check_child'] == 0 || $tmpData['check_family'] == 0 || $tmpData['check_house'] == 0){
                $check_completed = 0;
            }
            if($tmpData['other_family_id'] && $tmpData['check_other'] == 0){
                $check_completed = 0;
            }
            if($tmpData['ancestors_family_id'] && $tmpData['check_ancestors'] == 0){
                $check_completed = 0;
            }
            if($tmpData['company_id'] && $tmpData['check_company'] == 0){
                $check_completed = 0;
            }
            if($tmpData['insurance_id'] && $tmpData['check_insurance'] == 0){
                $check_completed = 0;
            }
            if($tmpData['residence_id'] && $tmpData['check_residence'] == 0){
                $check_completed = 0;
            }
            if($tmpData['ancestor_id'] && $tmpData['check_generations'] == 0){
                $check_completed = 0;
            }
            Db::startTrans();
            try{
                Db::name('UserApply')->where('id', $tmpData['apply_id'])->update(['house_school_id' => $house_matching_school_id]);
                Db::name('UserApplyTmp')->where('id', $tmp_id)->update(['check_completed' => $check_completed]);
                Db::name('UserApplyStatus')->where('user_apply_id', $tmpData['apply_id'])->update($status_data);
                Db::name('UserApplyDetail')->where('user_apply_id',$tmpData['apply_id'])->update($detail_data);
                Db::commit();
            }catch (\Exception $exception){
                Log::write($exception->getMessage() ?: Lang::get('system_error'));
                Db::rollback();
            }
            $res = [
                'code' => 1,
                'data' => ''
            ];

        } catch (\Exception $exception) {
            Log::write($exception->getMessage() ?: Lang::get('system_error'));
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return json($res);
    }
}