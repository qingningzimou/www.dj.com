<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/7/6
 * Time: 19:33
 */

namespace comparison;
use think\facade\Cache;
use think\facade\Db;
use sm\TxSm4;
use think\facade\Lang;
use think\facade\Log;

class Reckon
{
    /**
     * 学生信息比对
     * @param $child_id
     * @param $child_region
     * @return array
     */
    public function CheckChild($tmp_id,$child_id,$idcard)
    {
        try {
            $code = 'check_user';
            $url = Cache::get('comparison_url');
            $getData = $this->sendApi($code,['SFZH' => $idcard],$url);
            $check_child = 0;
            if($getData['code'] == 1){
                if($getData['status'] == 1){
                    $region_id = 0;
                    $is_main_area = 0;
                    $police_id = 0;
                    if(isset($getData['data']['police_station']))
                    {
                        $policeData = Db::table("deg_sys_police_station")->cache(true)->where('name',$getData['data']['police_station'])->find();
                        if(!empty($policeData)){
                            $region_id = $policeData['region_id'];
                            $is_main_area = $policeData['is_main_area'];
                            $police_id = $policeData['id'];
                        }
                    }
                    $update_data = [
                        'api_name' => isset($getData['data']['name']) ? $getData['data']['name'] : '',
                        'api_policestation' => isset($getData['data']['police_station']) ? $getData['data']['police_station'] : '',
                        'api_policestation_id' => $police_id,
                        'api_area' => isset($getData['data']['area']) ? $getData['data']['area'] : '',
                        'api_address' => isset($getData['data']['idcard_address']) ? $getData['data']['idcard_address'] : '',
                        'api_card' => isset($getData['data']['number']) ? $getData['data']['number'] : '',
                        'api_relation' => isset($getData['data']['holder_relation']) ? $getData['data']['holder_relation'] : '',
                        'api_region_id' => $region_id,
                        'is_main_area' => $is_main_area,
                    ];
                    Db::name('user_child')->where('id',$child_id)->update($update_data);
                    $check_child = 1;
                }else{
                    $update_data = [
                        'api_name' => '',
                        'api_policestation' => '',
                        'api_policestation_id' => 0,
                        'api_area' => '',
                        'api_address' => '',
                        'api_card' => '',
                        'api_relation' => '',
                        'api_region_id' => 0,
                        'is_main_area' => 0,
                    ];
                    Db::name('user_child')->where('id',$child_id)->update($update_data);
                    $check_child = -1;
                }
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'child_id' => $child_id,
                'check_child' => $check_child
            ]);
            $res = [
                'code' => 1,
                'data' => $check_child
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }

    /**
     * 监护人信息比对
     * @param $family_id
     * @param $idcard
     * @return array
     */
    public function CheckFamily($tmp_id,$family_id,$idcard)
    {
        try {
            $code = 'check_user';
            $url = Cache::get('comparison_url');
            $getData = $this->sendApi($code,['SFZH' => $idcard],$url);
            $check_family = 0;
            if($getData['code'] == 1){
                if($getData['status'] == 1){
                    $region_id = 0;
                    $is_area_main = 0;
                    $police_id = 0;
                    if(isset($getData['data']['police_station']))
                    {
                        $policeData = Db::table("deg_sys_police_station")->cache(true)->where('name',$getData['data']['police_station'])->find();
                        if(!empty($policeData)){
                            $region_id = $policeData['region_id'];
                            $is_area_main = $policeData['is_main_area'];
                            $police_id = $policeData['id'];
                        }
                    }
                    $update_data = [
                        'api_name' => isset($getData['data']['name']) ? $getData['data']['name'] : '',
                        'api_policestation' => isset($getData['data']['police_station']) ? $getData['data']['police_station'] : '',
                        'api_policestation_id' => $police_id,
                        'api_area' => isset($getData['data']['area']) ? $getData['data']['area'] : '',
                        'api_address' => isset($getData['data']['idcard_address']) ? $getData['data']['idcard_address'] : '',
                        'api_card' => isset($getData['data']['number']) ? $getData['data']['number'] : '',
                        'api_relation' => isset($getData['data']['holder_relation']) ? $getData['data']['holder_relation'] : '',
                        'api_region_id' => $region_id,
                        'is_area_main' => $is_area_main,
                    ];
                    Db::name('user_family')->where('id',$family_id)->update($update_data);
                    $check_family = 1;
                }else{
                    $update_data = [
                        'api_name' => '',
                        'api_policestation' => '',
                        'api_policestation_id' => 0,
                        'api_area' => '',
                        'api_address' => '',
                        'api_card' => '',
                        'api_relation' => '',
                        'api_region_id' => 0,
                        'is_area_main' => 0,
                    ];
                    Db::name('user_family')->where('id',$family_id)->update($update_data);
                    $check_family = -1;
                }
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'family_id' => $family_id,
                'check_family' => $check_family
            ]);
            $res = [
                'code' => 1,
                'data' => $check_family
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
    /**
     * 房产信息比对
     * @param $company_id
     * @return array
     */
    public function CheckHouse($tmp_id,$house_id,$idcard,$house_type,$code_type,$cert_code)
    {
        try {
            $url = Cache::get('examine_url');
            $check_house = 0;
            if($house_type == 1){
                if($code_type == 3){  //网签合同单独查接口
                    $getData = $this->sendApi('CHKWQHT', ['idcard' => $idcard ,'contract_code' => $cert_code],$url);
                }else{
                    $getData = $this->sendApi('CHKFDCQ', ['idcard' => $idcard ,'house_code' => $cert_code],$url);
                }
                if($getData['code'] == 1){
                    if($getData['status'] == 1){
                        $region_id = 0;
                        $region = Cache::get("region",[]);
                        $regionData = filter_value_one($region, 'simple_code', $getData['data']['area_code']);
                        if(count($regionData)){
                            $region_id = $regionData['id'];
                        }
                        if ($code_type != 3) {
                            $update_data = [
                                'api_address' => isset($getData['data']['address']) ? $getData['data']['address'] : '',
                                'api_house_code' => isset($getData['data']['property_number']) ? $getData['data']['property_number'] : '',
                                'api_area_code' => isset($getData['data']['area_code']) ? $getData['data']['area_code'] : '',
                                'api_region_id' => $region_id,
                            ];
                        }else{
                            $update_data = [
                                'api_address' => isset($getData['data']['address']) ? $getData['data']['address'] : '',
                                'api_house_code' => isset($getData['data']['advance_number']) ? $getData['data']['advance_number'] : '',
                                'api_area_code' => isset($getData['data']['area_code']) ? $getData['data']['area_code'] : '',
                                'api_region_id' => $region_id,
                            ];
                        }
                        Db::name('user_house')->where('id',$house_id)->update($update_data);
                        $check_house = 1;
                    }else{
                        $update_data = [
                            'api_address' => '',
                            'api_house_code' => '',
                            'api_area_code' => '',
                            'api_region_id' => 0,
                        ];
                        Db::name('user_house')->where('id',$house_id)->update($update_data);
                        $check_house = -1;
                    }
                }
            }else{
                $update_data = [
                    'api_address' => '',
                    'api_house_code' => '',
                    'api_area_code' => '',
                    'api_region_id' => 0,
                ];
                Db::name('user_house')->where('id',$house_id)->update($update_data);
                $check_house = -1;
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'house_id' => $house_id,
                'house_type' => $house_type,
                'check_house' => $check_house
            ]);
            $res = [
                'code' => 1,
                'data' => $check_house
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
    /**
     * 工商信息比对
     * @param $company_id
     * @return array
     */
    public function CheckCompany($tmp_id,$company_id, $org_code)
    {
        try {
            $check_company = 0;
            if($org_code){
                $code = 'CHKGSCX';
                $url = Cache::get('examine_url');
                $getData = $this->sendApi($code,['org_code' => $org_code],$url);
                if($getData['code'] == 1){
                    if($getData['status'] == 1){
                        $update_data = [
                            'api_lawer_name' => isset($getData['data']['legal_person']) ? $getData['data']['legal_person'] : '',
                            'api_company_name' => isset($getData['data']['company_name']) ? $getData['data']['company_name'] : '',
                            'api_address' => isset($getData['data']['address']) ? $getData['data']['address'] : '',
                            'api_organ' => isset($getData['data']['issuing_unit']) ? $getData['data']['issuing_unit'] : '',
                            'api_establish' => isset($getData['data']['establish_date']) ? $getData['data']['establish_date'] : '',
                            'api_company_status' => isset($getData['data']['status']) ? $getData['data']['status'] : '',
                            'api_area' => isset($getData['data']['area_code']) ? $getData['data']['area_code'] : '',
                        ];
                        Db::name('user_company')->where('id',$company_id)->update($update_data);
                        $check_company = 1;
                    }else{
                        $update_data = [
                            'api_lawer_name' =>  '',
                            'api_company_name' => '',
                            'api_address' => '',
                            'api_organ' => '',
                            'api_establish' => '',
                            'api_company_status' => '',
                            'api_area' => '',
                        ];
                        Db::name('user_company')->where('id',$company_id)->update($update_data);
                        $check_company = -1;
                    }
                }
            }else{
                $update_data = [
                    'api_lawer_name' =>  '',
                    'api_company_name' => '',
                    'api_address' => '',
                    'api_organ' => '',
                    'api_establish' => '',
                    'api_company_status' => '',
                    'api_area' => '',
                ];
                Db::name('user_company')->where('id',$company_id)->update($update_data);
                $check_company = -1;
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'company_id' => $company_id,
                'check_company' => $check_company
            ]);
            $res = [
                'code' => 1,
                'data' => $check_company
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
    /**
     * 社保信息比对
     * @param $company_id
     * @return array
     */
    public function CheckInsurance($tmp_id,$insurance_id,$idcard)
    {
        try {
            $check_insurance = 0;
            if($idcard){
                $code = 'CHKSBCX';
                $url = Cache::get('examine_url');
                $getData = $this->sendApi($code,['idcard' => $idcard],$url);
                if($getData['code'] == 1){
                    if($getData['status'] == 1){
                        $update_data = [
                            'api_code' => isset($getData['data']['card_code']) ? $getData['data']['card_code'] : '',
                            'api_join_time' => isset($getData['data']['start_time']) ? $getData['data']['start_time'] : '',
                            'api_end_time' => isset($getData['data']['end_time']) ? $getData['data']['end_time'] : '',
                            'api_company_name' => isset($getData['data']['company_name']) ? $getData['data']['company_name'] : '',
                            'api_adcode' => isset($getData['data']['adcode']) ? $getData['data']['adcode'] : '',
                        ];
                        Db::name('user_insurance')->where('id',$insurance_id)->update($update_data);
                        $check_insurance = 1;
                    }else{
                        $update_data = [
                            'api_code' => '',
                            'api_join_time' => '',
                            'api_end_time' => '',
                            'api_company_name' => '',
                            'api_adcode' => '',
                        ];
                        Db::name('user_insurance')->where('id',$insurance_id)->update($update_data);
                        $check_insurance = -1;
                    }
                }
            }else{
                $update_data = [
                    'api_code' => '',
                    'api_join_time' => '',
                    'api_end_time' => '',
                    'api_company_name' => '',
                    'api_adcode' => '',
                ];
                Db::name('user_insurance')->where('id',$insurance_id)->update($update_data);
                $check_insurance = -1;
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'insurance_id' => $insurance_id,
                'check_insurance' => $check_insurance
            ]);
            $res = [
                'code' => 1,
                'data' => $check_insurance
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
    /**
     * 居住证信息比对
     * @param $company_id
     * @return array
     */
    public function CheckResidence($tmp_id,$residence_id,$reside_code)
    {
        try {
            $check_residence = 0;
            if($reside_code){
                $code = 'CHKJZCX';
                $url = Cache::get('examine_url');
                $getData = $this->sendApi($code,['reside_code' => $reside_code],$url);
                if($getData['code'] == 1){
                    if($getData['status'] == 1){
                        $update_data = [
                            'api_address' => isset($getData['data']['address']) ? $getData['data']['address'] : '',
                            'api_hold_address' => isset($getData['data']['household']) ? $getData['data']['household'] : '',
                            'api_end_time' => isset($getData['data']['end_time']) ? $getData['data']['end_time'] : '',
                            'api_code' => isset($getData['data']['reside_code']) ? $getData['data']['reside_code'] : '',
                            'api_area_code' => isset($getData['data']['area_code']) ? $getData['data']['area_code'] : '',
                        ];
                        Db::name('user_residence')->where('id',$residence_id)->update($update_data);
                        $check_residence = 1;
                    }else{
                        $update_data = [
                            'api_address' => '',
                            'api_hold_address' => '',
                            'api_end_time' => '',
                            'api_code' => '',
                            'api_area_code' => '',
                        ];
                        Db::name('user_residence')->where('id',$residence_id)->update($update_data);
                        $check_residence = -1;
                    }
                }
            }else{
                $update_data = [
                    'api_address' => '',
                    'api_hold_address' => '',
                    'api_end_time' => '',
                    'api_code' => '',
                    'api_area_code' => '',
                ];
                Db::name('user_residence')->where('id',$residence_id)->update($update_data);
                $check_residence = -1;
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'residence_id' => $residence_id,
                'check_residence' => $check_residence
            ]);
            $res = [
                'code' => 1,
                'data' => $check_residence
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }

    /**
     * 祖辈信息比对
     * @param $family_id
     * @param $idcard
     * @return array
     */
    public function CheckAncestors($tmp_id,$ancestors_family_id,$idcard)
    {
        try {
            $code = 'check_user';
            $url = Cache::get('comparison_url');
            $getData = $this->sendApi($code,['SFZH' => $idcard],$url);
            $check_ancestors = 0;
            if($getData['code'] == 1){
                if($getData['status'] == 1){
                    $region_id = 0;
                    $is_area_main = 0;
                    $police_id = 0;
                    if(isset($getData['data']['police_station']))
                    {
                        $police = Cache::get('police',[]);
                        $policeData = filter_value_one($police, 'name', $getData['data']['police_station']);
                        if(count($policeData)){
                            $region_id = $policeData['region_id'];
                            $is_area_main = $policeData['is_main_area'];
                            $police_id = $policeData['id'];
                        }
                    }
                    $update_data = [
                        'api_name' => isset($getData['data']['name']) ? $getData['data']['name'] : '',
                        'api_policestation' => isset($getData['data']['police_station']) ? $getData['data']['police_station'] : '',
                        'api_policestation_id' => $police_id,
                        'api_area' => isset($getData['data']['area']) ? $getData['data']['area'] : '',
                        'api_address' => isset($getData['data']['idcard_address']) ? $getData['data']['idcard_address'] : '',
                        'api_card' => isset($getData['data']['number']) ? $getData['data']['number'] : '',
                        'api_relation' => isset($getData['data']['holder_relation']) ? $getData['data']['holder_relation'] : '',
                        'api_region_id' => $region_id,
                        'is_area_main' => $is_area_main,
                    ];
                    Db::name('user_family')->where('id',$ancestors_family_id)->update($update_data);
                    $check_ancestors = 1;
                }else{
                    $update_data = [
                        'api_name' => '',
                        'api_policestation' => '',
                        'api_policestation_id' => 0,
                        'api_area' => '',
                        'api_address' => '',
                        'api_card' => '',
                        'api_relation' => '',
                        'api_region_id' => 0,
                        'is_area_main' => 0,
                    ];
                    Db::name('user_family')->where('id',$ancestors_family_id)->update($update_data);
                    $check_ancestors = -1;
                }
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'ancestors_family_id' => $ancestors_family_id,
                'check_ancestors' => $check_ancestors
            ]);
            $res = [
                'code' => 1,
                'data' => $check_ancestors
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
    /**
     * 另一监护人信息比对
     * @param $family_id
     * @param $idcard
     * @return array
     */
    public function CheckOther($tmp_id,$other_family_id,$idcard)
    {
        try {
            $code = 'check_user';
            $url = Cache::get('comparison_url');
            $getData = $this->sendApi($code,['SFZH' => $idcard],$url);
            $check_other = 0;
            if($getData['code'] == 1){
                if($getData['status'] == 1){
                    $region_id = 0;
                    $is_area_main = 0;
                    $police_id = 0;
                    if(isset($getData['data']['police_station']))
                    {
                        $police = Cache::get('police',[]);
                        $policeData = filter_value_one($police, 'name', $getData['data']['police_station']);
                        if(count($policeData)){
                            $region_id = $policeData['region_id'];
                            $is_area_main = $policeData['is_main_area'];
                            $police_id = $policeData['id'];
                        }
                    }
                    $update_data = [
                        'api_name' => isset($getData['data']['name']) ? $getData['data']['name'] : '',
                        'api_policestation' => isset($getData['data']['police_station']) ? $getData['data']['police_station'] : '',
                        'api_policestation_id' => $police_id,
                        'api_area' => isset($getData['data']['area']) ? $getData['data']['area'] : '',
                        'api_address' => isset($getData['data']['idcard_address']) ? $getData['data']['idcard_address'] : '',
                        'api_card' => isset($getData['data']['number']) ? $getData['data']['number'] : '',
                        'api_relation' => isset($getData['data']['holder_relation']) ? $getData['data']['holder_relation'] : '',
                        'api_region_id' => $region_id,
                        'is_area_main' => $is_area_main,
                    ];
                    Db::name('user_family')->where('id',$other_family_id)->update($update_data);
                    $check_other = 1;
                }else{
                    $update_data = [
                        'api_name' => '',
                        'api_policestation' => '',
                        'api_policestation_id' => 0,
                        'api_area' => '',
                        'api_address' => '',
                        'api_card' => '',
                        'api_relation' => '',
                        'api_region_id' => 0,
                        'is_area_main' => 0,
                    ];
                    Db::name('user_family')->where('id',$other_family_id)->update($update_data);
                    $check_other = -1;
                }
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'other_family_id' => $other_family_id,
                'check_other' => $check_other
            ]);
            $res = [
                'code' => 1,
                'data' => $check_other
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
    /**
     * 三代同堂关系比对
     * @param $company_id
     * @return array
     */
    public function CheckAncestor($tmp_id,$ancestor_id,$child_idcard,$parent_idcard,$grandparent_idcard,$other_idcard = '')
    {
        try {
            $url = Cache::get('examine_url');
            $postData = [
                'child_idcard' => $child_idcard,
                'parent_idcard' => $parent_idcard,
                'grandparent_idcard' => $grandparent_idcard,
            ];
            if($other_idcard){
                $postData = array_merge($postData,['other_idcard' => $other_idcard]);
            }
            $getData = $this->sendApi('CHKSDGX', $postData,$url);
            $check_generations = 0;
            if($getData['code'] == 1){
                if($getData['status'] == 1){
                    $update_data = [
                        'api_child_relation' => isset($getData['data']['chk_child_relation']) ? $getData['data']['chk_child_relation'] : '',
                        'api_parent_relation' => isset($getData['data']['chk_parent_relation']) ? $getData['data']['chk_parent_relation'] : '',
                        'api_grandparent_relation' => isset($getData['data']['chk_grandparent_relation']) ? $getData['data']['chk_grandparent_relation'] : '',
                        'api_father_idcard' => isset($getData['data']['father_idcard']) ? $getData['data']['father_idcard'] : '',
                        'api_mother_idcard' => isset($getData['data']['mother_idcard']) ? $getData['data']['mother_idcard'] : '',
                        'api_other_status' => isset($getData['data']['check_other']) ? $getData['data']['check_other'] : '',
                        'api_marriage_status' => isset($getData['data']['marriage_status']) ? $getData['data']['marriage_status'] : '',
                        'api_parent_status' => isset($getData['data']['chk_parent']) ? $getData['data']['chk_parent'] : '',
                        'api_grandparent_status' => isset($getData['data']['chk_grandparent']) ? $getData['data']['chk_grandparent'] : '',
                        'api_child_house' => isset($getData['data']['chk_child_house']) ? $getData['data']['chk_child_house'] : '',
                        'api_father_house' => isset($getData['data']['chk_father_hous']) ? $getData['data']['chk_father_hous'] : '',
                        'api_mother_house' => isset($getData['data']['chk_mother_house']) ? $getData['data']['chk_mother_house'] : '',
                    ];
                    Db::name('user_ancestor')->where('id',$ancestor_id)->update($update_data);
                    $check_generations = 1;
                }else{
                    $update_data = [
                        'api_child_relation' => '',
                        'api_parent_relation' => '',
                        'api_grandparent_relation' => '',
                        'api_father_idcard' => '',
                        'api_mother_idcard' => '',
                        'api_other_status' => '',
                        'api_marriage_status' => 0,
                        'api_parent_status' => 0,
                        'api_grandparent_status' => 0,
                        'api_child_house' => 0,
                        'api_father_house' => 0,
                        'api_mother_house' => 0,
                    ];
                    Db::name('user_ancestor')->where('id',$ancestor_id)->update($update_data);
                    $check_generations = -1;
                }
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'ancestor_id' => $ancestor_id,
                'check_generations' => $check_generations
            ]);
            $res = [
                'code' => 1,
                'data' => $check_generations
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
    /**
     * 监护人关系比对
     * @return array
     */
    public function CheckGuardian($child_idcard,$parent_idcard)
    {
        try {
            $url = Cache::get('examine_url');
            $postData = [
                'child_idcard' => $child_idcard,
                'parent_idcard' => $parent_idcard,
            ];
            $getData = $this->sendApi('CHKJHGX', $postData,$url);
            if(!$getData['code']){
                throw new \Exception('监护人关系比对失败');
            }
            $res = [
                'code' => 1,
                'data' => $getData
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
    /**
     * 户籍信息比对
     * @return array
     */
    public function CheckBirthplace($tmp_id,$child_id,$idcard)
    {
        try {
            $url = Cache::get('comparison_url');
            $postData = [
                'CZRK_GMSFHM' => $idcard,
            ];
            $getData = $this->sendApi('check_birthplace', $postData,$url);
            $check_birthplace = 0;
            if($getData['code'] == 1){
                if($getData['status'] == 1){
                    $region_id = 0;
                    $is_main_area = 0;
                    $police_id = 0;
                    if(isset($getData['data']['police_station']))
                    {
                        $policeData = Db::table("deg_sys_police_station")->cache(true)->where('name',$getData['data']['police_station'])->find();
                        if(!empty($policeData)){
                            $region_id = $policeData['region_id'];
                            $is_main_area = $policeData['is_main_area'];
                            $police_id = $policeData['id'];
                        }
                    }
                    $update_data = [
                        'api_name_new' => isset($getData['data']['name']) ? $getData['data']['name'] : '',
                        'api_policestation_new' => isset($getData['data']['police_station']) ? $getData['data']['police_station'] : '',
                        'api_policestation_id_new' => $police_id,
                        'api_area_new' => isset($getData['data']['area']) ? $getData['data']['area'] : '',
                        'api_address_new' => isset($getData['data']['idcard_address']) ? $getData['data']['idcard_address'] : '',
                        'api_card_new' => isset($getData['data']['number']) ? $getData['data']['number'] : '',
                        'api_region_id_new' => $region_id,
                        'is_main_area_new' => $is_main_area,
                    ];
                    Db::name('user_child')->where('id',$child_id)->update($update_data);
                    $check_birthplace = 1;
                }else{
                    $check_birthplace = -1;
                }
            }
            Db::name('user_apply_tmp')->where('id',$tmp_id)->update([
                'child_id' => $child_id,
                'check_birthplace' => $check_birthplace
            ]);
            $res = [
                'code' => 1,
                'data' => $getData
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }

    /**
     * 检查比对项目是否均已完成
     * @param $tmpData
     * @return array
     */
    public function CheckCompleted($tmpData){
        try {
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
            Db::name('user_apply_tmp')->where('id',$tmpData['id'])->update(['check_completed' => $check_completed]);
            $res = [
                'code' => 1,
                'data' => $check_completed
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;

    }
    /**
     * 数据比对接口
     */
    private function sendApi(string $code,array $sendData,string $url): array
    {
        $key = Cache::get('comparison_key');
        $sm4 = new TxSm4();
        $data = [
            'InterfaceCode' => $code,
        ];
        $data = array_merge($data,$sendData);
        $rawData = $sm4->encrypt($key, json_encode($data,JSON_UNESCAPED_UNICODE));
        $getData = httpPost($url,$rawData,true);
        $getData = $sm4->decrypt($key,$getData);
        $getData = json_decode($getData,true);

//        Log::write($getData);
        if($getData['code'] == 1){
            return [
                'code' => 1,
                'status' => $getData['status'],
                'data' => $getData['data'],
            ];
        }else{
            return [
                'code' => 0,
                'msg' => $getData['msg'],
            ];
        }
    }
}