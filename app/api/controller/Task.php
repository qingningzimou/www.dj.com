<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 15:10
 */
namespace app\api\controller;


use app\common\model\PoliceStation;
use app\common\model\UserApply;
use app\common\model\UserApplyStatus;
use app\common\model\UserMessageBatch;
use app\mobile\model\user\Child;
use app\mobile\model\user\Company;
use app\mobile\model\user\Family;
use app\mobile\model\user\Insurance;
use app\mobile\model\user\Residence;
use app\mobile\model\user\UserApplyDetail;
use appPush\AppMsg;
use think\App;
use sm\TxSm4;
use think\facade\Cache;
use think\facade\Db;
use \think\response\Json;

class Task
{
    //个人信息用户查询(学生，家长)
    public function CheckUser(App $app): Json
    {

        if ($app->request->IsPost()) {
            Db::startTrans();
            try {
               /* $request_data = $app->request->only([
                    'start',
                    'end',
                ]);

                if(intval($request_data['start']) <= 0 || intval($request_data['end']) <= 0) {
                    throw new \Exception('系统错误');
                }*/
                $code = 'check_user';
                $url = Cache::get('comparison_url');
                $data = Db::name('user_apply')
                    ->where("deleted",0)
                    ->where('voided',0)
                    //->limit($request_data['start'],$request_data['end'])
                    ->order('id ASC')
                    ->select()
                    ->toArray();
                foreach($data as $key=>$value){
                    $status = Db::name("user_apply_status")->field(['id','auto_check_child','auto_check_family'])->where('deleted',0)->where("user_apply_id",$value['id'])->find();
                    if($status){
                        //查询学生信息
                        if($status['auto_check_child'] == 0){
                            $status_upadte_data = [];
                            $detail_upadte_data = [];
                            $child = Db::name("user_child")->field(['idcard'])->where("id",$value['child_id'])->find();
                            $user = $this->sendApi($code,['SFZH' => $child['idcard']],$url);
                            if($user['code'] == 1){
                                if($user['status'] == 1){
                                    $region_id = 0;
                                    $is_area_main = 0;
                                    if(isset($user['data']['police_station']))
                                    {
                                        //$police = PoliceStation::where('name', $user['data']['police_station'])->find();
                                        $police = Cache::get('police',[]);
                                        if($police){
                                            $region_id = $police['region_id'];
                                            $is_area_main = $police['is_main_area'];
                                        }
                                    }
                                    $update_data = [
                                        'api_name' => isset($user['data']['name']) ? $user['data']['name'] : '',
                                        'api_region_id' => $region_id,
                                        'api_card' => isset($user['data']['number']) ? $user['data']['number'] : '',
                                        'api_area' => isset($user['data']['area']) ? $user['data']['area'] : '',
                                        'api_policestation' => isset($user['data']['police_station']) ? $user['data']['police_station'] : '',
                                        'api_address' => isset($user['data']['address']) ? $user['data']['address'] : '',
                                        'api_relation' => isset($user['data']['holder_relation']) ? $user['data']['holder_relation'] : '',
                                        'api_sex' => isset($user['data']['sex']) ? $user['data']['sex'] : 0,
                                        'is_area_main' => $is_area_main,
                                    ];
                                    $detail_upadte_data['child_policstation'] = isset($user['data']['police_station']) ? $user['data']['police_station'] : '';
                                    $detail_upadte_data['child_area'] = $region_id;

                                    $update_data['id'] = $value['child_id'];
                                    //更新学生大数据信息
                                    //Db::name("user_child")->field(['idcard'])->where("id",$value['child_id'])->update($update_data);
                                    $child_update = (new Child())->editData($update_data);
                                    if($child_update['code'] == 0){
                                        throw new \Exception($child_update['msg']);
                                    }
                                    if($region_id > 0){
                                        //如果对比公安局区县和申请的区县一样 则是本区县学生
                                        if($region_id == $value['region_id']){
                                            $status_upadte_data['auto_check_birthplace_area'] = 1;
                                            $detail_upadte_data['birthplace_status'] = 2;

                                            if($is_area_main == 1){   //是否主城区
                                                $status_upadte_data['auto_check_child'] = 2;
                                                $status_upadte_data['auto_check_birthplace_main_area'] = 1;
                                                $detail_upadte_data['birthplace_status'] = 1;
                                            }else{
                                                $status_upadte_data['auto_check_child'] = 1;
                                                $status_upadte_data['auto_check_birthplace_main_area'] = 2;
                                            }
                                        }else{
                                            //申报区域 查询数据字典，
                                            //查询房产
                                            if($region_id == 3){ //如果是樊城区
                                                if($user['data']['police_station'] == '襄阳市公安局紫贞派出所'){  //如果是紫贞派出所

                                                }
                                            }

                                            //不是本区域
                                            $status_upadte_data['auto_check_child'] = -1;
                                            $status_upadte_data['auto_check_birthplace_area'] = 2;
                                            $detail_upadte_data['birthplace_status'] = 3;

                                        }
                                    }else{
                                        //非襄阳市本区
                                        $detail_upadte_data['birthplace_status'] = 4;
                                    }
                                }else{
                                    $status_upadte_data['auto_check_child'] = -2; //没有查询到结果
                                    $status_upadte_data['auto_check_birthplace_area'] = -1;
                                    $status_upadte_data['auto_check_birthplace_main_area'] = -1;
                                    $detail_upadte_data['birthplace_status'] = 5;
                                }

                                //更新user_apply_detail
                                $detail_upadte_table = (new UserApplyDetail())->editData($detail_upadte_data,['child_id'=>$value['child_id']]);
                                if($detail_upadte_table['code'] == 0){
                                    throw new \Exception($detail_upadte_table['msg']);
                                }

                                //更新学生比对结果
                                $status_upadte_data['id'] = $status['id'];
                                $update_status_table = (new UserApplyStatus())->editData($status_upadte_data);
                                if($update_status_table['code'] == 0){
                                    throw new \Exception($update_status_table['msg']);
                                }

                            }

                        }

                        //查询家长信息
                        if($status['auto_check_family'] == 0){
                            $status_upadte_data = [];
                            $family = Db::name("user_family")->field(['idcard'])->where("id",$value['family_id'])->find();
                            $user = $this->sendApi($code,['SFZH' => $family['idcard']],$url);

                            if($user['code'] == 1){
                                if($user['status'] == 1){
                                    $region_id = 0;
                                    $is_area_main = 0;
                                    if(isset($user['data']['police_station']))
                                    {
                                        $police = PoliceStation::where('name', $user['data']['police_station'])->find();
                                        if($police){
                                            $region_id = $police['region_id'];
                                            $is_area_main = $police['is_main_area'];
                                        }
                                    }
                                    //判断家长是否有房
                                    $isHasHouse = 0;
                                    $checkHouse = $this->sendApi('new_check_house',['QLRZJHM'=>$family['idcard']],$url);
                                    if ($checkHouse['code'] == 1) {
                                        if($checkHouse['status'] == 1){
                                            $isHasHouse = 1;
                                        }
                                    }

                                    $update_data = [
                                        'api_name' => isset($user['data']['name']) ? $user['data']['name'] : '',
                                        'api_region_id' => $region_id,
                                        'api_card' => isset($user['data']['number']) ? $user['data']['number'] : '',
                                        'api_area' => isset($user['data']['area']) ? $user['data']['area'] : '',
                                        'api_policestation' => isset($user['data']['police_station']) ? $user['data']['police_station'] : '',
                                        'api_address' => isset($user['data']['address']) ? $user['data']['address'] : '',
                                        'api_relation' => isset($user['data']['holder_relation']) ? $user['data']['holder_relation'] : '',
                                        'api_sex' => isset($user['data']['sex']) ? $user['data']['sex'] : 0,
                                        'is_area_main' => $is_area_main,
                                        'api_has_house' => $isHasHouse,
                                    ];
                                    $update_data['id'] = $value['family_id'];
                                    //更新家长大数据信息
                                    $family_update = (new Family())->editData($update_data);
                                    if($family_update['code'] == 0){
                                        throw new \Exception($family_update['msg']);
                                    }
                                    //Db::name("user_family")->field(['idcard'])->where("id",$value['family_id'])->update($update_data);
                                    if($region_id > 0){
                                        //如果对比公安局区县和申请的区县一样 则是本区县学生
                                        if($region_id == $value['region_id']){
                                            if($is_area_main == 1){   //是否主城区
                                                $status_upadte_data['auto_check_family'] = 2;
                                            }else{
                                                $status_upadte_data['auto_check_family'] = 1;
                                            }
                                        }else{
                                            $status_upadte_data['auto_check_family'] = -1;
                                        }
                                    }

                                }else{
                                    $status_upadte_data['auto_check_family'] = -2; //没有查询到结果
                                }
                            }
                            //更新家长比对结果
                            $status_upadte_data['id'] = $status['id'];
                            $update_status_table = (new UserApplyStatus())->editData($status_upadte_data);
                            if($update_status_table['code'] == 0){
                                throw new \Exception($update_status_table['msg']);
                            }
                        }

                    }

                }

                $res = [
                    'code' => 1,
                    'msg' => 'ok',
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return json($res);
        }

    }


    //查询监护人关系是否属实
    public function CheckRelation(App $app): Json
    {
        if ($app->request->IsPost()) {
            Db::startTrans();
            try {
                $code = 'check_guardian';
                $url = Cache::get('comparison_url');
                $data = Db::name('user_apply')
                    ->where("deleted", 0)
                    ->where('voided', 0)
                    ->select()
                    ->toArray();
                foreach ($data as $key => $value) {

                    $status = Db::name("user_apply_status")->field(['id','auto_check_relation'])->where('deleted',0)->where("user_apply_id", $value['id'])->find();
                    if ($status) {
                        $status_upadte_data = [];
                        $detail_upadte_data = [];
                        //查询学生信息
                        if ($status['auto_check_relation'] == 0) {
                            $child = Db::name("user_child")->field(['idcard', 'real_name'])->where("id", $value['child_id'])->find();
                            $family = Db::name("user_family")->field(['idcard', 'parent_name', 'relation'])->where("id", $value['family_id'])->find();
                            $user = $this->sendApi($code, ['GMSFHM' => $child['idcard'], 'XM' => $child['real_name']],$url);
                            if ($user['code'] == 1) {
                                if ($user['status'] == 1) {
                                    switch ($family['relation']) {
                                        case 1:  //父亲
                                            if ($family['idcard'] == $user['data']['father_idcard'] && $family['parent_name'] == $user['data']['father_name']) {
                                                $status_upadte_data['auto_check_relation'] = 1;
                                                $detail_upadte_data['guardian_relation'] = 1;
                                            } else {
                                                $status_upadte_data['auto_check_relation'] = -1;
                                                $detail_upadte_data['guardian_relation'] = 4;
                                            }
                                            break;
                                        case 2: //母亲
                                            if ($family['idcard'] == $user['data']['mother_idcard'] && $family['parent_name'] == $user['data']['mother_name']) {
                                                $status_upadte_data['auto_check_relation'] = 1;
                                                $detail_upadte_data['guardian_relation'] = 1;
                                            } else {
                                                $status_upadte_data['auto_check_relation'] = -1;
                                                $detail_upadte_data['guardian_relation'] = 4;
                                            }
                                            break;
                                        case 3: //其他
                                                $status_upadte_data['auto_check_relation'] = 1;
                                                $detail_upadte_data['guardian_relation'] = 3;
                                            break;
                                    }

                                }else{
                                    $status_upadte_data['auto_check_relation'] = -1;
                                    $detail_upadte_data['guardian_relation'] = 4;
                                }

                                //更新user_apply_detail
                                $detail_upadte_table = (new UserApplyDetail())->editData($detail_upadte_data,['child_id'=>$value['child_id']]);
                                if($detail_upadte_table['code'] == 0){
                                    throw new \Exception($detail_upadte_table['msg']);
                                }

                                //更新比对结果
                                $status_upadte_data['id'] = $status['id'];
                                $update_status_table = (new UserApplyStatus())->editData($status_upadte_data);
                                if ($update_status_table['code'] == 0) {
                                    throw new \Exception($update_status_table['msg']);
                                }
                            }
                        }

                    }
                }
                $res = [
                    'code' => 1,
                    'msg' => 'ok',
                ];
                Db::commit();

            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return json($res);
        }

    }


    //对比房产信息 产权房
    public function CheckHouse(App $app): Json
    {
        if ($app->request->IsPost()) {
            Db::startTrans();
            try {
                $url = Cache::get('comparison_url');
                $data = Db::name('user_apply')
                    ->where("deleted", 0)
                    ->where('voided', 0)
                    ->select()
                    ->toArray();

                foreach ($data as $key => $value) {

                    $status = Db::name("user_apply_status")->field(['id','auto_check_house_area'])->where('deleted',0)->where("user_apply_id", $value['id'])->find();
                    if ($status) {
                        $status_upadte_data = [];
                        $detail_upadte_data = [];
                        $apply_upadte_data = [];
                        //查询学生信息
                        if ($status['auto_check_house_area'] == 0) {
                            $house = Db::name("user_house")->field(['house_type', 'code'])->where('deleted',0)->where("id", $value['house_id'])->find();
                            $family = Db::name("user_family")->field(['idcard', 'parent_name'])->where('deleted',0)->where("id", $value['family_id'])->find();
                            if(!$house || !$family){
                                continue;
                                //throw new \Exception('系统错误');
                            }
                           //房产类型（1：产权房；2：租房；3：自建房；4：置换房；5：公租房；6：三代同堂）
                            if($house['house_type'] == 1) {
                                if($house['code_type'] == 3){  //网签合同单独查接口
                                    $api_house_info = $this->sendApi('check_online_sign', ['jyzzjhm' => $family['idcard'], 'htbhhh' => $house['code']],$url);
                                }else{
                                    $api_house_info = $this->sendApi('new_check_house', ['QLRZJHM' => $family['idcard']],$url);
                                }
                                if($api_house_info['code'] == 1){
                                    if($api_house_info['status'] == 1){
                                        foreach($api_house_info['data'] as $_key=>$_value) {
                                            if ($house['code_type'] != 3) {
                                                //如果是产权房  和不动产房  则查找比较证件后面数字
                                                if (strpos($_value['property_number'], $house['cert_code']) !== false) {

                                                   $region = Cache::get("region",[]);
                                                    $regionData = filter_value_one($region, 'simple_code', $_value['area_code']);
                                                   if(count($regionData) > 0){
                                                       //是本区房子
                                                        if($regionData['id'] == $value['region_id']){
                                                            $status_upadte_data['auto_check_house_area'] = 1;

                                                            //房产地址匹配学校
                                                            $address = Cache::get($_value['area_code'],[]); // 获取相关区县缓存
                                                            if($address){
                                                                $addressData = filter_value_one($address, 'address', $_value['address']);
                                                                if($addressData){
                                                                    $detail_upadte_data['house_status'] = 1; //匹配学校本区
                                                                    if($value['school_type'] == 1){
                                                                        if($addressData['primary_school_id'] > 0){
                                                                            $detail_upadte_data['house_matching_school_id'] = $addressData['primary_school_id'];
                                                                            $apply_upadte_data['house_school_id'] = $addressData['primary_school_id'];
                                                                        }
                                                                    }

                                                                    if($value['school_type'] == 2){
                                                                        if($addressData['middle_school_id'] > 0){
                                                                            $apply_upadte_data['house_matching_school_id'] = $addressData['middle_school_id'];
                                                                            $apply_upadte_data['house_school_id'] = $addressData['middle_school_id'];
                                                                        }
                                                                    }
                                                                }else{
                                                                    //缩略地址没查到  查完整地址
                                                                   $intactData = Cache::get('intact',[]);
                                                                   if($intactData) {
                                                                       $detail_upadte_data['house_status'] = 1; //匹配学校本区
                                                                       if($value['school_type'] == 1){
                                                                           if($intactData['primary_school_id'] > 0){
                                                                               $detail_upadte_data['house_matching_school_id'] = $intactData['primary_school_id'];
                                                                               $apply_upadte_data['house_school_id'] = $addressData['primary_school_id'];
                                                                           }
                                                                       }

                                                                       if($value['school_type'] == 2){
                                                                           if($intactData['middle_school_id'] > 0){
                                                                               $detail_upadte_data['house_matching_school_id'] = $intactData['middle_school_id'];
                                                                               $apply_upadte_data['house_school_id'] = $addressData['middle_school_id'];
                                                                           }
                                                                       }
                                                                   }

                                                                }
                                                            }else{
                                                                $detail_upadte_data['house_status'] = 3; //无匹配学校
                                                            }

                                                        }else{
                                                            //非本区
                                                            //房产地址匹配学校
                                                            $address = Cache::get($_value['area_code'],[]);
                                                            if($address){
                                                                $addressData = filter_value_one($address, 'address', $_value['address']);
                                                                if($addressData){
                                                                    $detail_upadte_data['house_status'] = 1; //匹配学校本区
                                                                    if($value['school_type'] == 1){
                                                                        if($addressData['primary_school_id'] > 0){
                                                                            $detail_upadte_data['house_matching_school_id'] = $addressData['primary_school_id'];
                                                                            $apply_upadte_data['house_school_id'] = $addressData['primary_school_id'];
                                                                        }
                                                                    }

                                                                    if($value['school_type'] == 2){
                                                                        if($addressData['middle_school_id'] > 0){
                                                                            $apply_upadte_data['house_matching_school_id'] = $addressData['middle_school_id'];
                                                                            $apply_upadte_data['house_school_id'] = $addressData['middle_school_id'];
                                                                        }
                                                                    }
                                                                }else{
                                                                    //缩略地址没查到  查完整地址
                                                                    $intactData = Cache::get('intact',[]);
                                                                    if($intactData) {
                                                                        $detail_upadte_data['house_status'] = 1; //匹配学校本区
                                                                        if($value['school_type'] == 1){
                                                                            if($intactData['primary_school_id'] > 0){
                                                                                $detail_upadte_data['house_matching_school_id'] = $intactData['primary_school_id'];
                                                                                $apply_upadte_data['house_school_id'] = $addressData['primary_school_id'];
                                                                            }
                                                                        }

                                                                        if($value['school_type'] == 2){
                                                                            if($intactData['middle_school_id'] > 0){
                                                                                $detail_upadte_data['house_matching_school_id'] = $intactData['middle_school_id'];
                                                                                $apply_upadte_data['house_school_id'] = $addressData['middle_school_id'];
                                                                            }
                                                                        }
                                                                    }

                                                                }
                                                            }else{
                                                                $detail_upadte_data['house_status'] = 3; //无匹配学校
                                                            }

                                                            $status_upadte_data['auto_check_house_area'] = 2;
                                                            $status_upadte_data['auto_check_other_house'] = 1;
                                                            $status_upadte_data['auto_check_house_main_area'] = 2;
                                                        }

                                                   }else{
                                                       //不是本区县房子
                                                       $detail_upadte_data['house_status'] = 4; //匹配学校本区

                                                       $status_upadte_data['auto_check_house_area'] = 2;
                                                       $status_upadte_data['auto_check_house_main_area'] = 2;
                                                   }
                                                }else{
                                                    //无结果
                                                    $detail_upadte_data['house_status'] = 4; //匹配学校本区
                                                    $status_upadte_data['auto_check_house_area'] = -1;
                                                    $status_upadte_data['auto_check_house_main_area'] = -1;
                                                }
                                            }else{
                                                $region = Cache::get("region",[]);
                                                $regionData = filter_value_one($region, 'simple_code', $_value['area_code']);
                                                if(count($regionData) > 0){
                                                    //是本区房子
                                                    if($regionData['id'] == $value['region_id']){
                                                        $status_upadte_data['auto_check_house_area'] = 1;

                                                        //房产地址匹配学校
                                                        $address = Cache::get($_value['area_code'],[]); // 获取相关区县缓存
                                                        if($address){
                                                            $addressData = filter_value_one($address, 'address', $_value['address']);
                                                            if($addressData){
                                                                $detail_upadte_data['house_status'] = 1; //匹配学校本区
                                                                if($value['school_type'] == 1){  //小学
                                                                    if($addressData['primary_school_id'] > 0){
                                                                        $detail_upadte_data['house_matching_school_id'] = $addressData['primary_school_id'];
                                                                    }
                                                                }

                                                                if($value['school_type'] == 2){  //初中
                                                                    if($addressData['middle_school_id'] > 0){
                                                                        $detail_upadte_data['house_matching_school_id'] = $addressData['middle_school_id'];
                                                                    }
                                                                }
                                                            }else{
                                                                //缩略地址没查到  查完整地址
                                                                $intactData = Cache::get('intact',[]);
                                                                if($intactData) {
                                                                    $detail_upadte_data['house_status'] = 1; //匹配学校本区
                                                                    if($value['school_type'] == 1){
                                                                        if($intactData['primary_school_id'] > 0){
                                                                            $detail_upadte_data['house_matching_school_id'] = $intactData['primary_school_id'];
                                                                        }
                                                                    }

                                                                    if($value['school_type'] == 2){
                                                                        if($intactData['middle_school_id'] > 0){
                                                                            $detail_upadte_data['house_matching_school_id'] = $intactData['middle_school_id'];
                                                                        }
                                                                    }
                                                                }

                                                            }
                                                        }

                                                    }else{
                                                        //不是本区房子
                                                        //非本区
                                                        //房产地址匹配学校
                                                        $address = Cache::get($_value['area_code'],[]);
                                                        if($address){
                                                            $addressData = filter_value_one($address, 'address', $_value['address']);
                                                            if($addressData){
                                                                $detail_upadte_data['house_status'] = 1; //匹配学校本区
                                                                if($value['school_type'] == 1){
                                                                    if($addressData['primary_school_id'] > 0){
                                                                        $detail_upadte_data['house_matching_school_id'] = $addressData['primary_school_id'];
                                                                        $apply_upadte_data['house_school_id'] = $addressData['primary_school_id'];
                                                                    }
                                                                }

                                                                if($value['school_type'] == 2){
                                                                    if($addressData['middle_school_id'] > 0){
                                                                        $apply_upadte_data['house_matching_school_id'] = $addressData['middle_school_id'];
                                                                        $apply_upadte_data['house_school_id'] = $addressData['middle_school_id'];
                                                                    }
                                                                }
                                                            }else{
                                                                //缩略地址没查到  查完整地址
                                                                $intactData = Cache::get('intact',[]);
                                                                if($intactData) {
                                                                    $detail_upadte_data['house_status'] = 1; //匹配学校本区
                                                                    if($value['school_type'] == 1){
                                                                        if($intactData['primary_school_id'] > 0){
                                                                            $detail_upadte_data['house_matching_school_id'] = $intactData['primary_school_id'];
                                                                            $apply_upadte_data['house_school_id'] = $addressData['primary_school_id'];
                                                                        }
                                                                    }

                                                                    if($value['school_type'] == 2){
                                                                        if($intactData['middle_school_id'] > 0){
                                                                            $detail_upadte_data['house_matching_school_id'] = $intactData['middle_school_id'];
                                                                            $apply_upadte_data['house_school_id'] = $addressData['middle_school_id'];
                                                                        }
                                                                    }
                                                                }

                                                            }
                                                        }else{
                                                            $detail_upadte_data['house_status'] = 3; //无匹配学校
                                                        }
                                                        $status_upadte_data['auto_check_house_area'] = 2;
                                                        $status_upadte_data['auto_check_other_house'] = 1;
                                                        $status_upadte_data['auto_check_house_main_area'] = 2;
                                                    }

                                                }else{
                                                    //不是本区县房子
                                                    $status_upadte_data['auto_check_house_area'] = 2;
                                                    $status_upadte_data['auto_check_house_main_area'] = 2;
                                                }
                                            }
                                        }


                                    }else{
                                        $status_upadte_data['auto_check_house_area'] = -1;
                                        $status_upadte_data['auto_check_house_main_area'] = -1;
                                        $detail_upadte_data['house_status'] = 4; //为对比成功产权房
                                    }

                                    //更新user_apply_detail
                                    $detail_upadte_table = (new UserApplyDetail())->editData($detail_upadte_data,['child_id'=>$value['child_id']]);
                                    if($detail_upadte_table['code'] == 0){
                                        continue;
                                        //throw new \Exception($detail_upadte_table['msg']);
                                    }

                                    //更新比对结果
                                    $status_upadte_data['id'] = $status['id'];
                                    $update_status_table = (new UserApplyStatus())->editData($status_upadte_data);
                                    if ($update_status_table['code'] == 0) {
                                        continue;
                                        //throw new \Exception($update_status_table['msg']);
                                    }

                                    //更新申报表
                                    $apply_upadte_data['id'] = $value['id'];
                                    $update_apply_table = (new UserApplyStatus())->editData($apply_upadte_data);
                                    if ($update_apply_table['code'] == 0) {
                                        continue;
                                        //throw new \Exception($update_status_table['msg']);
                                    }
                                }
                            }
                        }
                    }
                }
                $res = [
                    'code' => 1,
                    'msg' => 'ok',
                ];
                Db::commit();

            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return json($res);
        }

    }


    //对比租房信息
    public function CheckHouseRenting(App $app): Json
    {
        if ($app->request->IsPost()) {
            Db::startTrans();
            try {
                $url = Cache::get('examine_url');
                $data = Db::name('user_apply')
                    ->where("deleted", 0)
                    ->where('voided', 0)
                    ->select()
                    ->toArray();
                foreach ($data as $key => $value) {

                    $status = Db::name("user_apply_status")
                        ->field(['id','auto_check_company','auto_check_insurance','auto_check_residence'])
                        ->where('deleted',0)->where("user_apply_id", $value['id'])->find();
                    if ($status) {
                        $status_upadte_data = [];
                        $detail_upadte_data = [];
                        //查询学生信息

                            $house = Db::name("user_house")->field(['house_type', 'code'])->where('deleted',0)->where("id", $value['house_id'])->find();
                            $family = Db::name("user_family")->field(['idcard', 'parent_name'])->where('deleted',0)->where("id", $value['family_id'])->find();
                            $child = Db::name("user_child")->field(['idcard', 'real_name'])->where('deleted',0)->where("id", $value['child_id'])->find();
                            if(!$house || !$family || !$child){
                                continue;
                                //throw new \Exception('系统错误');
                            }
                            //房产类型（1：产权房；2：租房；3：自建房；4：置换房；5：公租房；6：三代同堂）
                            if($house['house_type'] == 2) {

                                $region = Cache::get("region",[]);
                                $region = filter_value_one($region, 'id', $value['region_id']);
                                $area_code = $region['simple_code'];

                                if($status['auto_check_residence'] == 0) {
                                    //居住证
                                    $residence = (new Residence())->where('house_id', $house['id'])->find();
                                    if ($residence) {
                                        //判断居住证是否属实
                                        $api_house_info = $this->sendApi('CHKCJZZ', [ 'reside_code'=>$residence['live_code'],
                                                                                            'region_code'=>$area_code,
                                                                                            'idcard'=>$family['idcard']],$url);

                                        if ($api_house_info['code'] == 1) {
                                            if ($api_house_info['status'] == 1) {
                                                $status_upadte_data['auto_check_residence_relation'] = 1;
                                                $detail_upadte_data['residence_permit_status'] = 1;
                                            } else {
                                                $status_upadte_data['auto_check_residence_relation'] = -1;
                                            }
                                        }

                                        //判断居住证是否在片区
                                        $api_house_info = $this->sendApi('CHKJZCX', ['reside_code'=>$residence['live_code'],'region_code'=>$area_code],$url);
                                        if ($api_house_info['code'] == 1) {
                                            if ($api_house_info['status'] == 1) {
                                                if($api_house_info['data']['chk_region'] == 1){
                                                    $status_upadte_data['auto_check_residence'] = 1;
                                                }else{
                                                    $status_upadte_data['auto_check_residence'] = 2;
                                                }

                                            } else {
                                                $status_upadte_data['auto_check_residence'] = -1;
                                            }
                                        }

                                    }
                                }
                                if($status['auto_check_insurance'] == 0) {
                                    //社保
                                    $insurance = (new Insurance())->where('house_id', $house['id'])->find();
                                    if ($insurance) {
                                        //判断社保是否属实
                                        $api_house_info = $this->sendApi('CHKSBXX', ['idcard' => $family['idcard'],'region_code' => $area_code],$url);
                                        if ($api_house_info['status'] == 1) {
                                            $status_upadte_data['auto_check_insurance_relation'] = 1;
                                            $detail_upadte_data['insurance_status'] = 1;
                                        } else {
                                            $status_upadte_data['auto_check_insurance_relation'] = -1;
                                        }

                                        //判断社保是否在片区
                                        $api_house_info = $this->sendApi('CHKSBCX', ['idcard' => $family['idcard'],'region_code' => $area_code],$url);
                                        if ($api_house_info['code'] == 1) {
                                            if ($api_house_info['status'] == 1) {
                                                if($api_house_info['data']['chk_region'] == 1){
                                                    $status_upadte_data['auto_check_insurance'] = 1;
                                                }else{
                                                    $status_upadte_data['auto_check_insurance'] = 2;
                                                }

                                            } else {
                                                $status_upadte_data['auto_check_insurance'] = -1;
                                            }
                                        }
                                    }
                                }
                                if($status['auto_check_company'] == 0) {
                                    //公司营业执照
                                    $company = (new Company())->where('house_id', $house['id'])->find();
                                    if ($company) {
                                        //判营业执照是否属实
                                        $api_house_info = $this->sendApi('CHKGSXX', [
                                            'parent_idcard' => $family['idcard'],
                                            'child_idcard' => $child['idcard'],
                                            'org_code' => $company['org_code'],
                                            'region_code' => $area_code,
                                        ],$url);

                                        if ($api_house_info['status'] == 1) {
                                            $status_upadte_data['auto_check_company_relation'] = 1;
                                            $detail_upadte_data['business_license_status'] = 1;
                                        } else {
                                            $status_upadte_data['auto_check_company_relation'] = -1;
                                        }

                                        //判断营业执照是否在片区
                                        $api_house_info = $this->sendApi('CHKGSCX', ['org_code' => $company['org_code'],'region_code' => $area_code,],$url);
                                        if ($api_house_info['code'] == 1) {
                                            if ($api_house_info['status'] == 1) {
                                                if($api_house_info['data']['chk_region'] == 1){
                                                    $status_upadte_data['auto_check_company'] = 1;
                                                }else{
                                                    $status_upadte_data['auto_check_company'] = 2;
                                                }

                                            } else {
                                                $status_upadte_data['auto_check_company'] = -1;
                                            }
                                        }
                                    }
                                }

                                //更新user_apply_detail
                                $detail_upadte_table = (new UserApplyDetail())->editData($detail_upadte_data,['child_id'=>$value['child_id']]);
                                if($detail_upadte_table['code'] == 0){
                                    continue;
                                    //throw new \Exception($detail_upadte_table['msg']);
                                }

                                //更新比对结果
                                $status_upadte_data['id'] = $status['id'];
                                $update_status_table = (new UserApplyStatus())->editData($status_upadte_data);
                                if ($update_status_table['code'] == 0) {
                                    continue;
                                    //throw new \Exception($update_status_table['msg']);
                                }

                            }

                    }
                }
                $res = [
                    'code' => 1,
                    'msg' => 'ok',
                ];
                Db::commit();

            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return json($res);
        }

    }


    //对比三代同堂房产信息
    public function CheckHouseAncestor(App $app): Json
    {
        if ($app->request->IsPost()) {
            Db::startTrans();
            try {
                $url = Cache::get('examine_url');
                $data = Db::name('user_apply')
                    ->where("deleted", 0)
                    ->where('voided', 0)
                    ->select()
                    ->toArray();
                foreach ($data as $key => $value) {
                    $ancestor = Db::name("user_ancestor")->where("user_id", $value['user_id'])->where('deleted',0)->find();
                    if(!$ancestor){
                        continue;
                    }

                    $status = Db::name("user_apply_status")->field(['id','auto_check_house_area'])->where('deleted',0)->where("user_apply_id", $value['id'])->find();
                    if ($status) {
                        $status_upadte_data = [];
                        //查询学生信息
                        if ($status['check_ancestor'] == 0) {
                            $house = Db::name("user_house")->field(['house_type', 'code'])->where('deleted',0)->where("id", $value['house_id'])->find();
                            $parent = Db::name("user_family")->field(['idcard', 'parent_name'])->where("id", $ancestor['father_id'])->find();
                            $grandparent = Db::name("user_family")->field(['idcard'])->where("id", $ancestor['family_id'])->find();
                            $child = Db::name("user_child")->field(['idcard'])->where('deleted',0)->where("id", $value['child_id'])->find();

                            if(!$house || !$child || !$parent || !$grandparent){
                                continue;
                                //throw new \Exception('系统错误');
                            }
                            $sendData = [
                                'child_idcard' => $child['idcard'],
                                'parent_idcard' => $parent['idcard'],
                                'grandparent_idcard' => $grandparent['idcard'],
                                'house_code' => $house['code'],
                            ];
                            $api_house_info = $this->sendApi('CHKSDTT', $sendData,$url);
                            if($api_house_info['code'] == 1){
                                if($api_house_info['status'] == 1){
                                    $status_upadte_data['check_ancestor'] = 1;
                                }else{
                                    $status_upadte_data['check_ancestor'] = -1;
                                }
                            }

                            //更新比对结果
                            $status_upadte_data['id'] = $status['id'];
                            $update_status_table = (new UserApplyStatus())->editData($status_upadte_data);
                            if ($update_status_table['code'] == 0) {
                                throw new \Exception($update_status_table['msg']);
                            }
                        }
                    }

                }
                $res = [
                    'code' => 1,
                    'msg' => 'ok',
                ];
                Db::commit();

            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return json($res);
        }

    }


    // 配置各区县派位设置 单双符
    public function AetAreaAssignmentValue(App $app): Json
    {
        if ($app->request->IsPost()) {
            Db::startTrans();
            try {
               $assignment = Db::name("assignment_setting_region")
                   ->alias('r')
                   ->leftJoin('assignment_setting s','r.assignment_id=s.id')
                   ->field([
                       's.*',
                       'r.region_id',
                       'r.single_double as r_single_double'
                   ])
                   ->where("r.deleted",0)
                   ->where("s.deleted",0)
                   ->whereIn('r.single_double',[1,2])
                   ->order('r.single_double DESC')
                   ->select()->toArray();
               if(!$assignment){
                   throw new \Exception('系统错误');
               }
                $data = Db::name('user_apply')
                    ->where("deleted",0)
                    ->where('voided',0)
                    ->select()
                    ->toArray();
                foreach($data as $key=>$value) {

                    //年龄不符 直接跳过
                    $detail = Db::name("user_apply_detail")
                        ->where('child_id',$value['child_id'])
                        ->where('child_age_status',1)
                        ->where('deleted',0)
                        ->find();
                    if(!$detail){
                        continue;
                    }

                    $status = Db::name("user_apply_status")->field(['id', 'auto_check_child', 'auto_check_family'])->where('deleted', 0)->where("user_apply_id", $value['id'])->find();
                    $detail = Db::name("user_apply_detail")->where("child_id", $value['child_id'])->where('child_age_status',1)->where('deleted',0)->find();
                    $house = Db::name("user_house")->field(['house_type', 'code'])->where('deleted',0)->where("id", $value['house_id'])->find();
                    if (!$status || !$detail || !$house) {
                        throw new \Exception('系统错误');
                    }

                    if($detail['single_double'] == 0){
                        foreach($assignment as $_key=>$_value){
                            $flag = true;
                            if($_value['house_type'] != $house['house_type']) {
                                $flag = false;
                            }
                            //判断是否主城区户籍
                            if($_value['main_area_birthplace_status'] == 1){
                                if($status['auto_check_birthplace_main_area'] == 0){
                                    continue;
                                }
                                if($status['auto_check_birthplace_main_area'] == 2 || $status['auto_check_birthplace_main_area'] == -1){
                                    //判断是否片区户籍
                                    if($_value['area_birthplace_status'] == 1){
                                        if($status['auto_check_birthplace_main_area'] == 0){
                                            continue;
                                        }
                                        if($status['auto_check_birthplace_main_area'] == 2 || $status['auto_check_birthplace_main_area'] == -1){
                                            $flag = false;
                                        }
                                    }
                                }
                            }


                            //判断片区主城区是否有房
                            if($_value['main_area_house_status'] == 1){
                                if($status['auto_check_house_main_area'] == 0){
                                    continue;
                                }
                                if($status['auto_check_house_main_area'] == 2 || $status['auto_check_house_main_area'] == -1){
                                    //判断是否片区有房
                                    if($_value['area_house_status'] == 1){
                                        if($status['auto_check_house_area'] == 0){
                                            continue;
                                        }
                                        if($status['auto_check_house_area'] == 2 || $status['auto_check_house_area'] == -1){
                                            $flag = false;
                                        }
                                    }
                                }
                            }

                            //工商营业执照
                            if($_value['area_business_status'] == 1){
                                if($status['auto_check_company'] == 0){
                                    continue;
                                }
                                if($status['auto_check_company'] != 1 || $status['auto_check_company_relation'] != 1){
                                    $flag = false;
                                }
                            }

                            //居住证
                            if($_value['area_residence_status'] == 1){
                                if($status['auto_check_insurance'] == 0){
                                    continue;
                                }
                                if($status['auto_check_insurance'] != 1 || $status['auto_check_insurance_relation'] != 1){
                                    $flag = false;
                                }
                            }

                            //社保
                            if($_value['area_social_insurance_status'] == 1){
                                if($status['auto_check_residence'] == 0){
                                    continue;
                                }
                                if($status['auto_check_residence'] != 1 || $status['auto_check_residence_relation'] != 1){
                                    $flag = false;
                                }
                            }

                            if($value['house_school_id'] == 0){
                                $flag = false;
                            }

                            if($flag){
                                $detail_update_data['id'] = $detail['id'];
                                $detail_update_data['single_double'] = $_value['r_single_double'];
                            }else{
                                $detail_update_data['id'] = $detail['id'];
                                $detail_update_data['single_double'] = -1;
                            }
                            $detail_update_table = (new UserApplyDetail())->editData($detail_update_data);
                            if($detail_update_table['code'] == 0){
                                throw new \Exception($detail_update_table['msg']);
                            }
                        }
                    }
                }


               $res = [
                   'code' => 1,
                   'msg' => 'ok',
               ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return json($res);
        }
    }


    //民办学校人数不足【自动录取】
    public function autoCivilAdmission()
    {
        Db::startTrans();
        try{
            $degree_list = Db::name('PlanApply')
                ->field(['school_id', 'SUM(spare_total)' => 'spare_total'])
                ->where('school_id', '>', 0)
                ->where('status', 1)
                ->where('deleted', 0)->group('school_id')->select()->toArray();
            $degree_school = [];
            foreach ($degree_list as $item){
                $degree_school[$item['school_id']] = $item['spare_total'];
            }

            $where = [];
            $where[] = ['deleted','=',0];
            $where[] = ['voided','=',0];
            $where[] = ['prepared','=',0];
            $where[] = ['resulted','=',0];
            $where[] = ['school_attr','=',2];
            $where[] = ['apply_school_id','>',0];
            //民办申请数量情况
            $apply_list = Db::name('UserApply')->field(['apply_school_id' => 'school_id', 'COUNT(*)' => 'total'])
                ->where($where)->group('apply_school_id')->select()->toArray();

            $school_ids = [];
            foreach ($apply_list as $item){
                $degree_count = isset($degree_school[$item['school_id']]) ? $degree_school[$item['school_id']] : 0;
                //申请数量小于等于 学校批复的学位数量
                if($item['total'] <= $degree_count){
                    $school_ids[] = $item['school_id'];
                }
            }

            $where = [];
            $where[] = ['a.deleted','=',0];
            $where[] = ['a.voided','=',0];
            $where[] = ['a.prepared','=',0];
            $where[] = ['a.resulted','=',0];
            $where[] = ['a.school_attr','=',2];
            $where[] = ['a.apply_school_id','in',$school_ids];
            $where[] = ['d.child_age','=',1];
            $list = Db::name('UserApply')->alias('a')
                ->field(['a.*'])
                ->join([
                    'deg_user_apply_detail' => 'd'
                ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                ->where($where)->select()->toArray();
            $count = 0;
            foreach ($list as $item){
                $apply_data = [
                    'id' => $item['id'],
                    'prepared' => 1,
                    'resulted' => 1,
                    'admission_type' => 7,//自动录取 录取方式
                    'result_school_id' => $item['apply_school_id'],
                    'apply_status' => 5,//民办录取
                ];
                $result = (new UserApply())->editData($apply_data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $count++;

            }
            Db::commit();
            echo json_encode([
                'code' => 1,
                'data' => $count,
            ]);
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
            Db::rollback();
            echo json_encode($res,256);
        }
    }


    //APP消息推送
    public function autoSendMessage(App $app): Json
    {
        if ($app->request->isPost()) {
            Db::startTrans();
            try{
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['sended', '=', 0];

                $list = Db::name('UserMessageBatch')->where($where)->select()->toArray();

                $appMsg = new AppMsg();
                foreach ($list as $k => $v ){
                    $result = $appMsg->getBatch($v['sys_batch_code']);
                    if($result['code'] == 1){
                        $update = (new UserMessageBatch())->editData(['id' => $v['id'], 'sended' => 1]);
                        if($update['code'] == 0){
                            throw new \Exception($update['msg']);
                        }
                    }else{
                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['sys_batch_id', '=', $v['id']];
                        $message_list = Db::name('UserMessage')->where($where)->select()->toArray();

                        $title = '';
                        $push_object = [];
                        foreach ($message_list as $key => $val){
                            if($key == 0){
                                $title = $val['title'];
                            }
                            $push_object[] = [
                                "phone" => $val['mobile'],
                                "content" => $val['contents'],
                                "url" => "",//跳转url,为空时，取msgUrl的值
                            ];
                        }
                        //调取发送消息接口
                        $msg_url = "http://223.75.196.188:7001/wap/#/pages/news/news";
                        $result = $appMsg->send_msg(1, $v['sys_batch_code'], $title, $push_object, '', $msg_url);
                        if($result['code'] == 1){
                            $update = (new UserMessageBatch())->editData(['id' => $v['id'], 'sended' => 1]);
                            if($update['code'] == 0){
                                throw new \Exception($update['msg']);
                            }
                        }
                    }
                }

                $res = [
                    'code' => 1,
                    'msg' => '消息推送处理成功！',
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return json($res);
        }
    }


    private function sendApi(string $code,array $sendData,string $url): array
    {

        //$url = Cache::get('comparison_url');
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
        if($getData['code'] == 1){
            return [
                'code' => 1,
                'status' => $getData['status'],
                'data' => $getData['data'],
            ];
        }else{
            return [
                'code' => 0,
                'msg' => '查询失败',
            ];
        }
    }


    public function test(){

        $list = [
            ['id' => 1,'userId' => 5],
            ['id' => 2,'userId' => 5],
            ['id' => 2,'userId' => 6],
            ['id' => 3,'userId' => 6],
        ];
        $userId = 6;


        $aa = array_sum(array_intersect_key(array_column($list ,'id'),array_flip(array_keys(array_column($list ,'userId'),$userId))));
        return $aa;


        return array_map(2,$arr);

        $url = 'https://data.xysmartedu.com/ApiExamineReckon/checkApiData';
        //$data = ['org_code'=>'92420606MA49UKFR7B','region_code'=>'420606'];
       //$api_house_info = $this->sendApi('CHKGSCX', $data,$url);

        //三代同党
        $data = ['child_idcard' => '420606200705011021','grandparent_idcard'=>'420601194807203534','parent_idcard'=>'420605197612291051','house_code'=>'鄂（2017）襄阳市不动产权第0060141号'];
        $api_house_info = $this->sendApi('CHKSDTT', $data,$url);

        //$api_house_info = $this->sendApi('CHKCJZZ', ['reside_code' => '42060620190000786','idcard'=>'429001198109291673','region_code'=>'420602']);
        //$api_house_info = $this->sendApi('CHKCJZZ', ['reside_code' => '42060620190000786','idcard'=>'429001198109291673','region_code'=>'420602']);
        return json($api_house_info);
    }

    public function recurDir($pathName = 'D:\wwwroot\edu'){
        //$pathName = 'D:\wwwroot\edu';
        //将结果保存在result变量中
        $result = array();
        $temp = array();
        //判断传入的变量是否是目录
        if(!is_dir($pathName) || !is_readable($pathName)) {
            return null;
        }
        //取出目录中的文件和子目录名,使用scandir函数
        $allFiles = scandir($pathName);
        //遍历他们
        foreach($allFiles as $fileName) {
            //判断是否是.和..
            if(in_array($fileName, array('.', '..','.git','.idea'))) {
                continue;
            }
            //路径加文件名
            $fullName = $pathName.'/'.$fileName;
            //如果是目录的话就继续遍历这个目录
            if(is_dir($fullName)) {
                //将这个目录中的文件信息存入到数组中
                $result[] = [
                    //指定展示名
                    'title' => $fileName,
                    // 'spread'=>true,   tree组件默认展开
                    //循环处理
                    'children' => $this->recurDir($fullName)
                ];
            }else {
                //如果是文件就先存入临时变量
                $temp[] = [
                    'filename'=>$fileName,
                    'fullname'=> $fullName
                ];
            }
        }
        //取出文件
        if($temp) {
            foreach($temp as $f) {
                $result[] = [
                    'title' => $f['filename'],
                    'href' => $f['fullname']
                ];
            }
        }
        return $result;

    }


}