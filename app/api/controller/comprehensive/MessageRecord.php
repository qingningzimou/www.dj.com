<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\comprehensive;

use app\common\controller\Education;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use app\common\model\UserMessage as model;
use app\common\validate\comprehensive\SysMessage as validate;
use think\facade\Db;

class MessageRecord extends Education
{
    /**
     * 消息发送记录列表
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['m.deleted','=',0];

                //公办、民办、小学、初中权限
                $school_where = $this->getSchoolWhere('a');
                $where[] = $school_where['school_attr'];
                $where[] = $school_where['school_type'];

                //区县ID
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $where[] = ['a.region_id', '=', $this->result['region_id'] ];
                    }
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['a.region_id', 'in', $region_ids];

                //姓名、身份证、手机号模糊查询
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
                }
                //学校类型
                if($this->request->has('school_type') && $this->result['school_type'] > 0)
                {
                    $where[] = ['a.school_type','=', $this->result['school_type']];
                }
                //学校性质
                if($this->request->has('school_attr') && $this->result['school_attr'] > 0)
                {
                    $where[] = ['a.school_attr','=', $this->result['school_attr']];
                }
                //户籍
                if($this->request->has('birthplace_status') && $this->result['birthplace_status'] > 0)
                {
                    $where[] = ['d.birthplace_status','=', $this->result['birthplace_status']];
                }
                //监护人关系
                if($this->request->has('relation_status') && $this->result['relation_status'] > 0)
                {
                    $where[] = ['d.guardian_relation','=', $this->result['relation_status']];
                }
                //房产类型
                if($this->request->has('house_type') && $this->result['house_type'] > 0)
                {
                    $house_type_arr = explode(',', $this->result['house_type']);
                    if(in_array(6, $house_type_arr)){
                        foreach ($house_type_arr as $k => $v){
                            if($v == 6) unset($house_type_arr[$k]);
                        }
                        if($house_type_arr){
                            $where[] = Db::Raw(" d.house_type in (" . implode(',', $house_type_arr) . ") OR a.ancestor_id > 0 ");
                        }else{
                            $where[] = ['a.ancestor_id', '>', 0 ];
                        }
                    }else{
                        $where[] = ['d.house_type', 'in', $house_type_arr ];
                    }
                }
                //三证情况
                if($this->request->has('three_syndromes_status') && $this->result['three_syndromes_status'] !== '')
                {
                    $where[] = ['d.house_type','=', 2];
                    $status_array = explode(',', $this->result['three_syndromes_status']);
                    if(in_array(0, $status_array)){
                        $where[] = ['d.business_license_status','=', 0];
                        $where[] = ['d.insurance_status','=', 0];
                        $where[] = ['d.residence_permit_status','=', 0];
                        $where[] = ['d.three_syndromes_status','=', 0];
                    }else if(in_array(4, $status_array)){
                        $where[] = ['d.business_license_status','=', 0];
                        $where[] = ['d.insurance_status','=', 0];
                        $where[] = ['d.residence_permit_status','=', 0];
                        $where[] = ['d.three_syndromes_status','=', 1];
                    } else{
                        if(in_array(1, $status_array)){
                            $where[] = ['d.business_license_status','=', 1];
                        }
                        if(in_array(2, $status_array)){
                            $where[] = ['d.insurance_status','=', 1];
                        }
                        if(in_array(3, $status_array)){
                            $where[] = ['d.residence_permit_status','=', 1];
                        }
                    }
                }
                //学籍情况
                if($this->request->has('school_roll_status') && $this->result['school_roll_status'] > 0 )
                {
                    $where[] = ['d.student_status','=', $this->result['school_roll_status']];
                }
                //申请状态
                if($this->request->has('status') && $this->result['status'] > 0 )
                {
                    $where[] = ['a.apply_status','=', $this->result['status']];
                }
                //房产匹配情况
                if($this->request->has('house_status') && $this->result['house_status'] > 0 )
                {
                    $where[] = ['d.house_status','=', $this->result['house_status']];
                }
                //学位审查学校
                if($this->request->has('public_school_id') && $this->result['public_school_id'] > 0 )
                {
                    $where[] = ['a.public_school_id','=', $this->result['public_school_id']];
                }
                //申报学校
                if($this->request->has('apply_school_id') && $this->result['apply_school_id'] > 0 )
                {
                    $where[] = ['a.apply_school_id','=', $this->result['apply_school_id']];
                }
                //最终录取学校
                if($this->request->has('result_school_id') && $this->result['result_school_id'] > 0 )
                {
                    $where[] = ['a.result_school_id','=', $this->result['result_school_id']];
                }
                //消息标题
                if($this->request->has('message_title') && $this->result['message_title'] !== '')
                {
                    $where[] = ['m.title','=', $this->result['message_title']];
                }
                //消息批次
                if($this->request->has('sys_batch_id') && $this->result['sys_batch_id'] > 0 )
                {
                    $where[] = ['m.sys_batch_id','=', $this->result['sys_batch_id']];
                }
                //是否政策生
                if($this->request->has('policy_status') && $this->result['policy_status'] !== '' )
                {
                    $where[] = ['a.policyed','=', $this->result['policy_status']];
                }
                //开始时间
                if(isset($this->result['start_time']) && $this->result['start_time'] !== ''){
                    $where[] = ['m.create_time','>=', $this->result['start_time']];
                }
                //结束时间
                if(isset($this->result['end_time']) && $this->result['end_time'] !== ''){
                    $where[] = ['m.create_time','<=', $this->result['end_time']];
                }

                $data = Db::name('UserMessage')->alias('m')
                    ->join([
                        'deg_user_apply' => 'a'
                    ], 'a.id = m.user_apply_id and a.deleted = 0 and a.offlined = 0 and a.voided = 0 ', 'LEFT')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                    ->field([
                        'a.id',
                        'm.id' => 'user_message_id',
                        'm.sys_batch_id' => 'sys_batch_id',
                        'm.title' => 'message_title',
                        'm.contents' => 'content',
                        'm.sys_batch_code' => 'sys_batch_code',
                        'a.region_id' => 'region_id',
                        'a.school_type' => 'school_type',
                        'a.school_attr' => 'school_attr',
                        'a.house_school_id' => 'house_school_id',
                        'a.public_school_id' => 'public_school_id',
                        'a.result_school_id' => 'result_school_id',
                        'a.admission_type' => 'admission_type',
                        'd.child_name' => 'real_name',
                        'd.child_idcard' => 'student_id_card',
                        'd.child_age_status' => 'child_age_status',
                        'd.birthplace_status' => 'birthplace_status',
                        'd.guardian_relation' => 'guardian_relation',
                        'd.house_status' => 'house_status',
                        'd.three_syndromes_status' => 'three_syndromes_status',
                        'd.student_status' => 'student_status',
                        'a.policyed' => 'policy_status',
                        'm.create_time' => 'send_time',
                        'd.insurance_status' => 'insurance_status',
                        'd.business_license_status' => 'business_license_status',
                        'd.residence_permit_status' => 'residence_permit_status',
                        'd.house_type' => 'house_type',
                    ])
                    ->where($where)
                    ->order('a.region_id', 'ASC')->order('a.result_school_id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');
                $school = Cache::get('school');
                $dictionary = new FilterData();
                $typeData = $dictionary->resArray('dictionary','SYSXXLX');
                if(!$typeData['code']){
                    throw new \Exception($typeData['msg']);
                }
                $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
                if(!$attrData['code']){
                    throw new \Exception($attrData['msg']);
                }

                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = $result['data'];


                foreach ($data['data'] as $k => $v){
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }
                    $data['data'][$k]['house_school_name'] = '-';
                    $houseSchoolData = filter_value_one($school, 'id', $v['house_school_id']);
                    if (count($houseSchoolData) > 0){
                        $data['data'][$k]['house_school_name'] = $houseSchoolData['school_name'];
                    }
                    $data['data'][$k]['public_school_name'] = '-';
                    $publicSchoolData = filter_value_one($school, 'id', $v['public_school_id']);
                    if (count($publicSchoolData) > 0){
                        $data['data'][$k]['public_school_name'] = $publicSchoolData['school_name'];
                    }
                    $data['data'][$k]['result_school_name'] = '-';
                    $resultSchoolData = filter_value_one($school, 'id', $v['result_school_id']);
                    if (count($resultSchoolData) > 0){
                        $data['data'][$k]['result_school_name'] = $resultSchoolData['school_name'];
                    }
                    $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $v['school_type']);
                    $data['data'][$k]['school_type_name'] = '';
                    if (count($schoolTypeData) > 0){
                        $data['data'][$k]['school_type_name'] = $schoolTypeData['dictionary_name'];
                    }
                    $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $v['school_attr']);
                    $data['data'][$k]['school_attr_name'] = '';
                    if (count($schoolAttrData) > 0){
                        $data['data'][$k]['school_attr_name'] = $schoolAttrData['dictionary_name'];
                    }

                    $data['data'][$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'], $v['school_attr']);

                    $student_age_name = isset($result['age_list'][$v['child_age_status']]) ? $result['age_list'][$v['child_age_status']] : '-';
                    $relation_name = isset($result['relation_list'][$v['guardian_relation']]) ? $result['relation_list'][$v['guardian_relation']] : '-';
                    $birthplace_status_name = isset($result['birthplace_list'][$v['birthplace_status']]) ? $result['birthplace_list'][$v['birthplace_status']] : '-';
                    $house_status_name = isset($result['house_list'][$v['house_status']]) ? $result['house_list'][$v['house_status']] : '-';
                    //$three_syndromes_name = isset($result['three_syndromes_list'][$v['three_syndromes_status']]) ? $result['three_syndromes_list'][$v['three_syndromes_status']] : '-';
                    $student_status_name = isset($result['school_roll_list'][$v['student_status']]) ? $result['school_roll_list'][$v['student_status']] : '-';
                    $three_syndromes_name = $this->getThreeSyndromesName($v);


                    $data['data'][$k]['student_age_name'] = $student_age_name;
                    $data['data'][$k]['relation_name'] = $relation_name;
                    $data['data'][$k]['birthplace_status_name'] = $birthplace_status_name;
                    $data['data'][$k]['house_status_name'] = $house_status_name;
                    $data['data'][$k]['three_syndromes_name'] = $three_syndromes_name;
                    $data['data'][$k]['student_status_name'] = $student_status_name;
                    $data['data'][$k]['policy_status_name'] = $v['policy_status'] == 1 ? '是' : '否';
                }
                
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 批量发送页面资源
     * @return \think\response\Json
     */
    public function resView(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = $result['data'];
                foreach ($result['age_list'] as $k => $v){
                    $data['age_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['relation_list'] as $k => $v){
                    $data['relation_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['birthplace_list'] as $k => $v){
                    $data['birthplace_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['house_list'] as $k => $v){
                    $data['house_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['three_syndromes_list'] as $k => $v){
                    $data['three_syndromes_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['school_roll_list'] as $k => $v){
                    $data['school_roll_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['house_type_list'] as $k => $v){
                    $data['house_type_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['apply_status_list'] as $k => $v){
                    $data['apply_status_list'][] = ['id' => $k, 'name' => $v];
                }

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['status', '=', 1];
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                if ($this->userInfo['grade_id'] < $this->city_grade) {
                    array_push($region_ids, 1);
                }
                $where[] = ['region_id', 'in', $region_ids];
                $data['message_list'] = Db::name('SysMessage')->field(['id', 'title'])->where($where)->select()->toArray();

//                $where = [];
//                $where[] = ['deleted', '=', 0];
//                //$where[] = ['sended', '=', 1];
//                $data['message_batch_list'] = Db::name('UserMessageBatch')->field(['id', 'sys_batch_code'])
//                    ->where($where)->limit(50)->select()->toArray();

                $result = $this->getAllSelectByRoleList();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data['school_list'] = $result['school_list'];
                $data['region_list'] = $result['region_list'];

                $data['public_school_list'] = [];
                $data['apply_school_list'] = [];
                foreach ($data['school_list'] as $item){
                    if($item['school_attr'] == 1){
                        $data['public_school_list'][] = $item;
                    }
                    if($item['school_attr'] == 2){
                        $data['apply_school_list'][] = $item;
                    }
                }

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary', 'SYSXXLX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                foreach ($getData['data'] as $value){
                    if($this->userInfo['primary_school_status'] == 1 && $value['dictionary_value'] == 1) {
                        $data['school_type'][] = [
                            'id' => $value['dictionary_value'],
                            'type_name' => $value['dictionary_name']
                        ];
                    }
                    if($this->userInfo['junior_middle_school_status'] == 1 && $value['dictionary_value'] == 2) {
                        $data['school_type'][] = [
                            'id' => $value['dictionary_value'],
                            'type_name' => $value['dictionary_name']
                        ];
                    }
                }
                $getData = $dictionary->resArray('dictionary', 'SYSXXXZ');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                foreach ($getData['data'] as $value){
                    if($this->userInfo['public_school_status'] == 1 && $value['dictionary_value'] == 1) {
                        $data['school_attr'][] = [
                            'id' => $value['dictionary_value'],
                            'attr_name' => $value['dictionary_name']
                        ];
                    }
                    if($this->userInfo['civil_school_status'] == 1 && $value['dictionary_value'] == 2) {
                        $data['school_attr'][] = [
                            'id' => $value['dictionary_value'],
                            'attr_name' => $value['dictionary_name']
                        ];
                    }
                }

                $res = [
                    'code' => 1,
                    'data' => $data,
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }else{}
    }

    public function searchMessageBatch(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['sys_batch_code','like', '%' . $this->result['keyword'] . '%'];
                }
                $where[] = ['deleted', '=', 0];
                $data = Db::name('UserMessageBatch')->field(['id', 'sys_batch_code'])
                    ->where($where)->limit(50)->select()->toArray();

                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 消息批量发送 查看
     * @param id 申请信息ID
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $param = $this->request->only(['id',]);
                //  如果数据不合法，则返回
                if ( !$param['id'] ) {
                    throw new \Exception('ID参数错误');
                }
                /*$region = Cache::get('region');
                $dictionary = new FilterData();
                $typeData = $dictionary->resArray('dictionary','SYSXXLX');
                if(!$typeData['code']){
                    throw new \Exception($typeData['msg']);
                }
                $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
                if(!$attrData['code']){
                    throw new \Exception($attrData['msg']);
                }

                $view = $this->getTypeData();
                if($view['code'] == 0){
                    throw new \Exception($view['msg']);
                }

                $data = [];
                $result = Db::name('UserApply')->where('id', $this->result['id'])
                    ->where('deleted', 0)->where('voided', 0)->find();
                if(!$result){
                    throw new \Exception('无申请资料信息');
                }

                $regionData = filter_value_one($region, 'id', $result['region_id']);
                if (count($regionData) > 0){
                    $data['basic']['region_name'] = $regionData['region_name'];
                }
                $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $result['school_type']);
                if (count($schoolTypeData) > 0){
                    $data['basic']['school_type_name'] = $schoolTypeData['dictionary_name'];
                }
                $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $result['school_attr']);
                if (count($schoolAttrData) > 0){
                    $data['basic']['school_attr_name'] = $schoolAttrData['dictionary_name'];
                }
                //学生信息
                $data['child'] = Db::name('UserChild')->field(
                    ['real_name', 'idcard', 'api_area', 'api_address', 'api_policestation',
                        'CASE sex WHEN 1 THEN "男" WHEN 2 THEN "女" ELSE "未知" END' => 'sex', 'picurl', 'api_card', 'api_relation'])
                    ->where('id', $result['child_id'])->where('deleted', 0)->find();
                if($data['child']){
                    if($data['child']['idcard']){
                        $data['child']['administrative_code'] = substr($data['child']['idcard'],0,6);
                    }
                }
                //监护人信息
                $data['family'] = Db::name('UserFamily')->hidden(
                    ['deleted',  'create_time', 'api_region_id', 'is_area_main',])
                    ->where('id', $result['family_id'])->where('deleted', 0)->find();
                if($data['family']){
                    if($data['family']['idcard']){
                        $data['family']['administrative_code'] = substr($data['family']['idcard'],0,6);
                    }
                }
                //房产信息
                $house = Db::name('UserHouse')->hidden(['deleted',  'create_time',])
                    ->where('id', $result['house_id'])->where('deleted', 0)->find();
                if($house){
                    $house_type_name = isset($view['data']['house_list'][$house['house_type']]) ? $view['data']['house_list'][$house['house_type']] : '-';
                    $code_type_name = isset($view['data']['code_list'][$house['code_type']]) ? $view['data']['code_list'][$house['code_type']] : '-';
                    $house['house_type_name'] = $house_type_name;
                    $house['code_type_name'] = $code_type_name;
                    if($house['code_type'] == 0){
                        $house['code_type'] = '';
                    }
                }
                $data['house'] = $house ?? (object)array();
                //补充资料
                $data['supplement'] = Db::name('UserSupplementAttachment')->hidden(['deleted', 'create_time',])
                    ->where('user_apply_id', $result['id'])->where('deleted', 0)->select()->toArray();
                //权限学校
                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0){
                    throw new \Exception($role_school['msg']);
                }
                //操作日志
                $data['operation'] = Db::name('UserApplyAuditLog')->hidden(['deleted',])->where('deleted', 0)
                    ->where('user_apply_id', $result['id'])
                    ->where('school_id', 'in', $role_school['school_ids'])->select()->toArray();
                if($data['operation']){
                    $manage = Cache::get('manage');
                    foreach ($data['operation'] as $k => $v){
                        $manageData = filter_value_one($manage, 'id', $v['admin_id']);
                        if (count($manageData) > 0){
                            $data['operation'][$k]['real_name'] = $manageData['real_name'];
                        }
                    }
                }
                //用户消息
                $message_list = Db::name('UserMessage')
                    ->field(['contents' => 'remark', 'create_time'])
                    ->where('user_apply_id', $result['id'])
                    ->where('deleted', 0)->select()->toArray();
                $operation = array_merge($data['operation'], $message_list);
                $data['operation'] = $this->arraySort($operation, 'create_time', SORT_DESC);

                //操作状态
                $operation_status = Db::name('UserApplyStatus')->where('deleted', 0)
                    ->where('user_apply_id', $result['id'])->find();
                $status = [];
                if($operation_status){
                    $status['check_relation']['value'] = ($operation_status['auto_check_relation'] == 1 || $operation_status['check_relation'] == 1) ? true : false;
                    $status['check_birthplace_area']['value'] = ($operation_status['auto_check_birthplace_area'] == 1 || $operation_status['check_birthplace_area'] == 1) ? true : false;
                    $status['check_birthplace_main_area']['value'] = ($operation_status['auto_check_birthplace_main_area'] == 1 || $operation_status['check_birthplace_main_area']) ? true : false;
                    $status['check_house_area']['value'] = ($operation_status['auto_check_house_area'] == 1 || $operation_status['check_house_area'] == 1 ) ? true : false;
                    $status['check_house_main_area']['value'] = ($operation_status['auto_check_house_main_area'] == 1 || $operation_status['check_house_main_area'] == 1) ? true : false;
                    $status['check_company']['value'] = ($operation_status['auto_check_company'] == 1 || $operation_status['check_company'] == 1 ) ? true : false;
                    $status['check_insurance']['value'] = ($operation_status['auto_check_insurance'] == 1 || $operation_status['check_insurance'] == 1) ? true : false;
                    $status['check_residence']['value'] = ($operation_status['auto_check_residence'] == 1 || $operation_status['check_residence'] == 1) ? true : false;
                }
                $data['operation_status'] = $status ?? (object)array();*/

                $result = $this->getChildDetails($this->result['id'], 0);
                if ( $result['code'] == 0 ) {
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'data' => $result['data']
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 删除
     * @return Json
     */
    public function actDelete(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                ]);
                $ids = $data['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选需要删除的消息记录');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];

                $result = (new model())->editData(['deleted' => 1 ], $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success'),
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }

    //录取方式
    private function getAdmissionTypeName($type, $school_attr): string
    {
        $name = '-';
        switch ($type){
            case 1:
                if($school_attr == 1){
                    $name = '派位';
                }
                if($school_attr == 2){
                    $name = '摇号';
                }
                break;
            case 2:
                $name = '线上审核';
                break;
            case 3:
                $name = '到校审核';
                break;
            case 4:
                $name = '派遣';
                break;
            case 5:
                $name = '调剂';
                break;
            case 6:
                $name = '政策生';
                break;
            case 7:
                $name = '自动录取';
                break;
        }
        return $name;
    }

}