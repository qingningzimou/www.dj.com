<?php
/**
 * Created by PhpStorm.
 * User: PhpStorm
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\recruit;

use app\common\controller\Education;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;
use think\facade\Cache;

class OnlineStatistics extends Education
{


    /**
     * 线上生源明细信息
     * @return \think\response\Json
     */


    public function getSourceList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['a.deleted','=',0];
                $where[] = ['d.deleted','=',0];
                $where[] = ['a.offlined','=',0];

                //公办、民办、小学、初中权限
//                $school_where = $this->getSchoolWhere('a');
//                $where[] = $school_where['school_attr'];
//                $where[] = $school_where['school_type'];

                if($this->request->has('voided') && $this->result['voided'] > 0)
                {
                    $where[] = ['a.voided', '=', 1];//作废
                }else{
                    $where[] = ['a.voided', '=', 0];//没有作废
                }
                //区县角色隐藏申请区域
                $select_region = true;
                $directly_school_ids = [];
                if ($this->userInfo['grade_id'] < $this->city_grade) {
                    $select_region = false;
                    //区县角色排除市直学校
                    $directly_where = [];
                    $directly_where[] = ['deleted','=',0];
                    $directly_where[] = ['disabled','=',0];
                    $directly_where[] = ['directly','=',1];//市直
                    $directly_school_ids = Db::name("SysSchool")->where($directly_where)->column('id');

//                    $where[] = ['a.apply_school_id', 'not in', $school_ids ];
//                    $where[] = ['a.public_school_id', 'not in', $school_ids ];
//                    $where[] = ['a.result_school_id', 'not in', $school_ids ];
                }
                if($select_region) {
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $where[] = ['a.region_id', '=', $this->result['region_id'] ];
                    }
                }
//                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
//                $where[] = ['a.region_id', 'in', $region_ids];
                //姓名、身份证、手机号模糊查询
                if($this->request->has('keyword') && $this->result['keyword'] != '')
                {
                    $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
                }else{
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['a.region_id', 'in', $region_ids];

                    //公办、民办、小学、初中权限
                    $school_where = $this->getSchoolWhere('a');
                    $where[] = $school_where['school_attr'];
                    $where[] = $school_where['school_type'];

                    $where[] = ['a.apply_school_id', 'not in', $directly_school_ids ];
                    $where[] = ['a.public_school_id', 'not in', $directly_school_ids ];
                    $where[] = ['a.result_school_id', 'not in', $directly_school_ids ];
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
                if($this->request->has('house_type') && $this->result['house_type'] !== '')
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
                //年龄状态
                if($this->request->has('age_status') && $this->result['age_status'] > 0 )
                {
                    $where[] = ['d.child_age_status','=', $this->result['age_status']];
                }
                //消息标题
                if($this->request->has('message_title') && $this->result['message_title'] !== '' )
                {
//                    $user_apply_ids = Db::name('UserMessage')->where('deleted', 0)
//                        ->where('title', $this->result['message_title'])->column('user_apply_id');
//                    $where[] = ['a.id','in', $user_apply_ids];
//                    $where[] = ['m.title','=', $this->result['message_title']];
                    $where[] = Db::Raw(" m.id in (SELECT MAX(id) FROM deg_user_message WHERE deleted = 0 GROUP BY user_apply_id )");
                }
                //补充资料状态
                if($this->request->has('supplement_status') && $this->result['supplement_status'] !== "" )
                {
                    $where[] = ['p.status','=', $this->result['supplement_status']];
                }
                //线上面审被拒次数
                if($this->request->has('refuse_count') && $this->result['refuse_count'] !== '' )
                {
                    $where[] = ['a.refuse_count','=', $this->result['refuse_count']];
                }
                //民办落选状态
                if($this->request->has('primary_lost_status') && $this->result['primary_lost_status'] !== '' )
                {
                    $where[] = ['a.school_attr','=', 2];
                    $where[] = ['a.primary_lost_status','=', $this->result['primary_lost_status']];
                }
                //单双符标记
                if($this->request->has('single_double') && $this->result['single_double'] > 0 )
                {
                    $where[] = ['d.single_double','=', $this->result['single_double']];
                }
                //区县条件不符
                if($this->request->has('factor_region_status') && $this->result['factor_region_status'] !== '' )
                {
                    if($this->result['factor_region_status'] == 1){
                        $where[] = ['d.false_region_id','=', 0];
                    }else{
                        $where[] = ['d.false_region_id','>', 0];
                    }
                }
                //批量查询
                if($this->request->has('batch') && $this->result['batch'] !== '')
                {
                    $result = $this->getBatchCondition();
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    $id_cards = $result['data'];
                    $where[] = ['d.child_idcard','in', $id_cards ];
                }


                //最后一条消息
                $query = Db::name('UserMessage')->where('user_apply_id', Db::raw('a.id'))->where('deleted',0)
                    ->field(['title'])->order('create_time', 'DESC')->limit(1)->buildSql();

                $data = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id', 'LEFT')
                    ->join([
                        'deg_user_supplement' => 'p'
                    ], 'p.user_apply_id = a.id and p.deleted=0', 'LEFT');
                if($this->request->has('message_title') && $this->result['message_title'] !== '' ){
                    $data = $data->join([
                        'deg_user_message' => 'm'
                    ], "m.user_apply_id = a.id and m.deleted = 0 and m.title = '" . $this->result['message_title'] . "'", 'LEFT');
                }
                $data = $data->field([
                    'a.id',
                    'a.region_id' => 'region_id',
                    'a.school_type' => 'school_type',
                    'a.school_attr' => 'school_attr',
                    'a.apply_school_id' => 'apply_school_id',
                    'a.house_school_id' => 'house_school_id',
                    'a.public_school_id' => 'public_school_id',
                    'a.result_school_id' => 'result_school_id',
                    'a.admission_type' => 'admission_type',
                    'a.apply_status' => 'apply_status',
                    $query => 'last_message',
                    'd.child_name' => 'real_name',
                    'd.child_idcard' => 'student_id_card',
                    'd.mobile' => 'mobile',
                    'd.child_age_status' => 'child_age_status',
                    'd.birthplace_status' => 'birthplace_status',
                    'd.guardian_relation' => 'guardian_relation',
                    'd.house_status' => 'house_status',
                    'd.three_syndromes_status' => 'three_syndromes_status',
                    'd.student_status' => 'student_status',
                    'd.house_type' => 'house_type',
                    'd.insurance_status' => 'insurance_status',
                    'd.business_license_status' => 'business_license_status',
                    'd.residence_permit_status' => 'residence_permit_status',
                    'p.status' => 'supplement_status',
                    'a.refuse_count' => 'refuse_count',
                    'a.primary_lost_status' => 'primary_lost_status',
                    'a.create_time' => 'apply_create_time',
                    'd.single_double' => 'single_double',
                    'd.false_region_id' => 'false_region_id',
                ])
                    ->where($where)
                    ->order('a.id', 'ASC')->order('a.region_id', 'ASC')->order('a.result_school_id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                //批量查询没有查询到的数据
                $not_check_id_cards = [];
                if ($this->request->has('batch') && $this->result['batch'] !== '') {
                    $cache_id_card = Cache::get('importBatchCardIds_' . $this->userInfo['manage_id'], []);
                    $query_id_card = Db::name('UserApply')->alias('a')
                        ->join([
                            'deg_user_apply_detail' => 'd'
                        ], 'd.child_id = a.child_id', 'LEFT')
                        ->join([
                            'deg_user_supplement' => 'p'
                        ], 'p.user_apply_id = a.id and p.deleted=0', 'LEFT')
                        ->where($where)->column('d.child_idcard');

                    $not_check_id_cards = array_values(array_diff($cache_id_card, $query_id_card));
                }
                $data['not_check_id_cards'] = $not_check_id_cards;

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

                $house_type_list = $this->getTypeData();
                $house_type_list = $house_type_list['data'];
                $empty_data = [
                    'region_name' => '',
                    'real_name' => '',
                    'mobile' => '',
                    'student_id_card' => '',
                    'school_type_name' => '',
                    'school_attr_name' => '',
                    'student_age_name' => '',
                    'birthplace_status_name' => '',
                    'relation_name' => '',
                    'house_status_name' => '',
                    'house_type_name' => '',
                    'three_syndromes_name' => '',
                    'student_status_name' => '',
                    'house_school_name' => '',
                    'public_school_name' => '',
                    'apply_school_name' => '',
                    'result_school_name' => '',
                    'admission_type_name' => '',
                    'last_message' => '',
                    'apply_status_name' => '',
                    'supplement_status' => '',
                    'refuse_count' => '',
                    'primary_lost_status' => '',
                    'single_double_name' => '',
                    'factor_region_status' => '',
                ];
                $search_length = 0;
                if($this->request->has('keyword') && $this->result['keyword'] != ''){
                    $search_length = mb_strlen($this->result['keyword'], "utf-8");
                }
                foreach ($data['data'] as $k => $v){
                    if(!$select_region) {
                        if($v['region_id'] != $this->userInfo['region_id'] ){
                            $data['data'][$k] = $empty_data;
                            if($search_length > 11){
                                $msg = '【' . $this->result['keyword'] . '】申报区域非本区域！';
                            }else{
                                $msg = '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】申报区域非本区域！';
                            }
                            $data['data'][$k]['msg'] = $msg;
                            continue;
                        }
                        if(in_array($v['apply_school_id'], $directly_school_ids) ||
                            in_array($v['public_school_id'], $directly_school_ids) ||
                            in_array($v['result_school_id'], $directly_school_ids)){
                            $data['data'][$k] = $empty_data;
                            if($search_length > 11){
                                $msg = '【' . $this->result['keyword'] . '】申报学校为市直学校！';
                            }else{
                                $msg = '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】申报学校为市直学校！';
                            }
                            $data['data'][$k]['msg'] = $msg;
                            continue;
                        }
                    }
                    if($this->userInfo['public_school_status'] == 0 && $v['school_attr'] == 1 ){
                        $data['data'][$k] = $empty_data;
                        if($search_length > 11){
                            $msg = '【' . $this->result['keyword'] . '】账号没有【公办】学校权限！';
                        }else{
                            $msg = '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】账号没有【公办】学校权限！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }
                    if($this->userInfo['civil_school_status'] == 0 && $v['school_attr'] == 2 ){
                        $data['data'][$k] = $empty_data;
                        if($search_length > 11){
                            $msg = '【' . $this->result['keyword'] . '】账号没有【民办】学校权限！';
                        }else{
                            $msg = '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】账号没有【民办】学校权限！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }
                    if($this->userInfo['primary_school_status'] == 0 && $v['school_type'] == 1 ){
                        $data['data'][$k] = $empty_data;
                        if($search_length > 11){
                            $msg = '【' . $this->result['keyword'] . '】账号没有【小学】学校权限！';
                        }else{
                            $msg = '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】账号没有【小学】学校权限！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }
                    if($this->userInfo['junior_middle_school_status'] == 0 && $v['school_attr'] == 2 ){
                        $data['data'][$k] = $empty_data;
                        if($search_length > 11){
                            $msg = '【' . $this->result['keyword'] . '】账号没有【初中】学校权限！';
                        }else{
                            $msg = '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】账号没有【初中】学校权限！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }

                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }
                    $data['data'][$k]['apply_school_name'] = '-';
                    $applySchoolData = filter_value_one($school, 'id', $v['apply_school_id']);
                    if (count($applySchoolData) > 0){
                        $data['data'][$k]['apply_school_name'] = $applySchoolData['school_name'];
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

                    if($v['supplement_status'] === 0){
                        $data['data'][$k]['supplement_status'] = '资料补充中';
                    }else if($v['supplement_status'] == 1){
                        $data['data'][$k]['supplement_status'] = '资料已补充';
                    }else{
                        $data['data'][$k]['supplement_status'] = '-';
                    }

                    if($v['school_attr'] == 1) {
                        $data['data'][$k]['primary_lost_status'] = '-';
                    }elseif ($v['school_attr'] == 2) {
                        if ($v['primary_lost_status'] == 1) {
                            $data['data'][$k]['primary_lost_status'] = '是';
                        } else {
                            $data['data'][$k]['primary_lost_status'] = '否';
                        }
                    }

                    $data['data'][$k]['factor_region_status'] = '-';
                    if($v['false_region_id'] > 0) {
                        $regionData = filter_value_one($region, 'id', $v['false_region_id']);
                        if (count($regionData) > 0) {
                            $data['data'][$k]['factor_region_status'] = $regionData['region_name'] . "条件不符";
                        }
                    }

                    $student_age_name = isset($result['age_list'][$v['child_age_status']]) ? $result['age_list'][$v['child_age_status']] : '-';
                    $relation_name = isset($result['relation_list'][$v['guardian_relation']]) ? $result['relation_list'][$v['guardian_relation']] : '-';
                    $birthplace_status_name = isset($result['birthplace_list'][$v['birthplace_status']]) ? $result['birthplace_list'][$v['birthplace_status']] : '-';
                    $house_status_name = isset($result['house_list'][$v['house_status']]) ? $result['house_list'][$v['house_status']] : '-';
                    //$three_syndromes_name = isset($result['three_syndromes_list'][$v['three_syndromes_status']]) ? $result['three_syndromes_list'][$v['three_syndromes_status']] : '-';
                    $student_status_name = isset($result['school_roll_list'][$v['student_status']]) ? $result['school_roll_list'][$v['student_status']] : '-';
                    $apply_status_name = isset($result['apply_status_list'][$v['apply_status']]) ? $result['apply_status_list'][$v['apply_status']] : '-';
                    $single_double_name = isset($result['single_double_list'][$v['single_double']]) ? $result['single_double_list'][$v['single_double']] : '-';
                    $three_syndromes_name = $this->getThreeSyndromesName($v);

                    if($student_age_name == '足龄'){
                        $student_age_name = '-';
                    }

                    $data['data'][$k]['house_type_name'] = array_key_exists($v['house_type'],$house_type_list['house_list']) ? $house_type_list['house_list'][$v['house_type']] : '-';

                    $data['data'][$k]['student_age_name'] = $student_age_name;
                    $data['data'][$k]['relation_name'] = $relation_name;
                    $data['data'][$k]['birthplace_status_name'] = $birthplace_status_name;
                    $data['data'][$k]['house_status_name'] = $house_status_name;
                    $data['data'][$k]['three_syndromes_name'] = $three_syndromes_name;
                    $data['data'][$k]['student_status_name'] = $student_status_name;
                    $data['data'][$k]['apply_status_name'] = $apply_status_name;
                    $data['data'][$k]['single_double_name'] = $single_double_name;
                }

                $data['select_region'] = $select_region;
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                $data['resources'] = $res_data;
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
     * 查看
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
     * 生源明细导出
     * @return Json
     */
    public function exportSource(): Json
    {
        if($this->request->isPost())
        {
            try {
                @ini_set("memory_limit","4096M");
                $result = $this->getListData(false);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['list'];
                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }


                $headArr = [
                    'id' => '编号',
                    'real_name' => '姓名',
                    'student_id_card' => '身份证号',
                    'mobile' => '手机号码',
                    'region_name' => '申请区域',
                    'school_type_name' => '学校类型',
                    'school_attr_name' => '学校性质',
                    'student_age_name' => '年龄',
                    'student_api_policestation' => '大数据对比派出所',
                    'student_api_address' => '户籍地址',
                    'student_api_area' => '户籍行政划分区域',
                    'birthplace_status_name' => '户籍',
                    'parent_name' => '填报监护人姓名',
                    'fill_relation_name' => '填报监护人关系',
                    'relation_name' => '监护人关系对比',
                    'check_ancestor_name' => '三代同堂关系',
                    'student_api_relation' => '与户主关系',
                    'house_type_name' => '房产类型',
                    'code_type_name' => '证件类型',
                    'house_address' => '房产填报地址',
                    'api_house_address' => '大数据房产对比地址',
                    'house_code' => '填报证件号',
                    'api_house_code' => '大数据对比证件号',
                    'house_status_name' => '房产',
                    'three_syndromes_name' => '三证情况',
                    'student_status_name' => '学籍情况',
                    'apply_school_name' => '申报学校',
                    'house_school_name' => '房产匹配学校',
                    'insurance_area' => '社保所在区域',
                    'company_area' => '营业执照所在区域',
                    'residence_area' => '居住证所在区域',
                    'live_code' => '居住证号',
//                    'api_live_code' => '大数据对比居住证号',
                    'graduation_name' => '填报毕业学校',
                    'six_school_name' => '小升初毕业学校',
                    'public_school_name' => '线上审核学校',
                    'result_school_name' => '最终录取学校',
                    'admission_type_name' => '录取途径',
                    'apply_status_name' => '状态',
                    'supplement_status_name' => '补充资料状态',
                    'last_message' => '最后一条信息',
                    'prepare_status_name' => '预录取验证状态',
                    'verification_name' => '派位验证状态',
                    'primary_lost_status' => '民办落选状态',
                    'apply_create_time' => '申报时间',
                    'factor_region_status' => '条件符合情况',
                ];

                $list = [];
                foreach($headArr as $key => $value){
                    foreach($data as $_key => $_value){
                        foreach($_value as $__key => $__value){
                            if($key == $__key){
//                                if($__key == 'student_id_card'){
//                                    $list[$_key][$__key] = $__value.' ';
//                                }else{
                                $list[$_key][$__key] = $__value;
//                                }
                            }
                        }
                    }
                }

                $data = $list;
//                if(count($data) > 50000){
//                    $total = count($data);
//                    $count_excel = ceil($total / 50000);
//                    for ($i = 0; $i < $count_excel; $i++){
//                        $offset = $i * 50000;
//                        $length = ($i + 1) * 50000;
//                        if($i == ($count_excel - 1)){
//                            $length = $total;
//                        }
//                        $data = array_slice($data, $offset, $length, true);
//                        $this->excelExport('生源明细_' . ($i + 1) . '_', $headArr, $data);
//                    }
//                }else {
                $this->excelExport('生源明细_', $headArr, $data);
//                }

            } catch (\Exception $exception){
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?? Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 资源
     * @return Json
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
                foreach ($result['voided_list'] as $k => $v){
                    $data['voided_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['supplement_status_list'] as $k => $v){
                    $data['supplement_status_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['refuse_count_list'] as $k => $v){
                    $data['refuse_count_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['primary_lost_status'] as $k => $v){
                    $data['primary_lost_status'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['single_double_list'] as $k => $v){
                    $data['single_double_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['factor_region_status'] as $k => $v){
                    $data['factor_region_status'][] = ['id' => $k, 'name' => $v];
                }

                //消息标题列表
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                if ($this->userInfo['grade_id'] < $this->city_grade) {
                    //array_push($region_ids, 1);
                }
                /*$data['message_list'] = Db::name('UserMessage')->where('deleted', 0)
                    ->where('title', '<>', '')->where('region_id', 'in', $region_ids)
                    ->group('title')->column('title');*/
                $data['message_list'] = Db::name('SysMessage')->where('deleted', 0)
                    ->where('status', 1)->where('region_id', 'in', $region_ids)
                    ->group('title')->column('title');

                $result = $this->getAllSelectByRoleList();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                foreach ($result['school_list'] as $item){
                    if($item['onlined'] == 1){
                        $data['school_list'][] = $item;
                    }
                }
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
        }
    }


    //生源明细 列表数据
    private function getListData($is_limit = true): array
    {
        try {
            $where = [];
            $where[] = ['a.deleted','=',0];
            $where[] = ['a.offlined','=',0];

            //公办、民办、小学、初中权限
            $school_where = $this->getSchoolWhere('a');
            $where[] = $school_where['school_attr'];
            $where[] = $school_where['school_type'];

            if($this->request->has('voided') && $this->result['voided'] > 0)
            {
                $where[] = ['a.voided', '=', 1];//作废
            }else{
                $where[] = ['a.voided', '=', 0];//没有作废
            }
            //区县角色隐藏申请区域
            $select_region = true;
            if ($this->userInfo['grade_id'] < $this->city_grade) {
                $select_region = false;
                //区县角色排除市直学校
                $directly_where = [];
                $directly_where[] = ['deleted','=',0];
                $directly_where[] = ['disabled','=',0];
                $directly_where[] = ['directly','=',1];//市直
                $school_ids = Db::name("SysSchool")->where($directly_where)->column('id');

                $where[] = ['a.apply_school_id', 'not in', $school_ids ];
                $where[] = ['a.public_school_id', 'not in', $school_ids ];
                $where[] = ['a.result_school_id', 'not in', $school_ids ];
            }
            if($select_region) {
                if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                    $where[] = ['a.region_id', '=', $this->result['region_id'] ];
                }
            }
            $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
            $where[] = ['a.region_id', 'in', $region_ids];

            if($this->request->has('keyword') && $this->result['keyword'])
            {
                $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
            }
            if($this->request->has('school_type') && $this->result['school_type'] > 0)
            {
                $where[] = ['a.school_type','=', $this->result['school_type']];
            }
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
            if($this->request->has('house_type') && $this->result['house_type'] !== '')
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
            //年龄状态
            if($this->request->has('age_status') && $this->result['age_status'] > 0 )
            {
                $where[] = ['d.child_age_status','=', $this->result['age_status']];
            }
            //消息标题
            if($this->request->has('message_title') && $this->result['message_title'] !== '' )
            {
//                $user_apply_ids = Db::name('UserMessage')->where('deleted', 0)
//                    ->where('title', $this->result['message_title'])->column('user_apply_id');
//                $where[] = ['a.id','in', $user_apply_ids];
//                $where[] = ['m.title','=', $this->result['message_title']];
                $where[] = Db::Raw(" m.id in (SELECT MAX(id) FROM deg_user_message WHERE deleted = 0 GROUP BY user_apply_id )");
            }

            //补充资料状态
            if($this->request->has('supplement_status') && $this->result['supplement_status'] != "" )
            {
                $where[] = ['p.status','=', $this->result['supplement_status']];
            }
            //线上面审被拒次数
            if($this->request->has('refuse_count') && $this->result['refuse_count'] !== '' )
            {
                $where[] = ['a.school_attr','=', 2];
                $where[] = ['a.refuse_count','=', $this->result['refuse_count']];
            }
            //民办落选状态
            if($this->request->has('primary_lost_status') && $this->result['primary_lost_status'] !== '' )
            {
                $where[] = ['a.primary_lost_status','=', $this->result['primary_lost_status']];
            }
            //单双符标记
            if($this->request->has('single_double') && $this->result['single_double'] > 0 )
            {
                $where[] = ['d.single_double','=', $this->result['single_double']];
            }
            //区县条件不符
            if($this->request->has('factor_region_status') && $this->result['factor_region_status'] !== '' )
            {
                if($this->result['factor_region_status'] == 1){
                    $where[] = ['d.false_region_id','=', 0];
                }else{
                    $where[] = ['d.false_region_id','>', 0];
                }
            }
            //批量查询
            if($this->request->has('batch') && $this->result['batch'] !== '')
            {
                $result = $this->getBatchCondition();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $id_cards = $result['data'];
                $where[] = ['d.child_idcard','in', $id_cards ];
            }
            if(!$is_limit){
                if($this->request->has('id') && $this->result['id']){
                    $ids = $this->result['id'];
                    $id_array = explode(',', $ids);
                    $where[] = ['a.id','in', $id_array];
                }

            }
            //权限学校
            /*$role_school = $this->getSchoolIdsByRole();
            if($role_school['code'] == 0){
                throw new \Exception($role_school['msg']);
            }
            if($role_school['bounded']  ){
                $where[] = ['a.result_school_id|a.apply_school_id|a.public_school_id|a.house_school_id', 'in', $role_school['school_ids']];
            }*/

            //最后一条消息
            $query = Db::name('UserMessage')->alias('m')->where('m.user_apply_id', Db::raw('a.id'))->where('deleted',0)
                ->field(['title'])->order('m.create_time', 'DESC')->limit(1)->buildSql();
            //六年级毕业学校
            $school_query = Db::name('SixthGrade')->where('id_card', Db::raw('c.idcard'))
                ->where('id_card', '<>', '')->where('deleted', 0)
                ->field(['graduation_school_id'])->limit(1)->buildSql();
            //填报监护人关系
            $relation_query = Db::name('UserFamily')->where('id', Db::raw('a.family_id'))
                ->where('deleted', 0)->field(['relation'])->limit(1)->buildSql();
            //填报监护人姓名
            $parent_name = Db::name('UserFamily')->where('id', Db::raw('a.family_id'))
                ->where('deleted', 0)->field(['parent_name'])->limit(1)->buildSql();

            $data = Db::name('UserApply')->alias('a')
                ->join([
                    'deg_user_apply_detail' => 'd'
                ], 'd.child_id = a.child_id AND d.deleted = 0', 'LEFT')
                ->join([
                    'deg_user_supplement' => 'p'
                ], 'p.user_apply_id = a.id AND p.deleted = 0', 'LEFT')
                ->join([
                    'deg_user_house' => 'h'
                ], 'a.house_id = h.id AND h.deleted = 0', 'LEFT')
                ->join([
                    'deg_user_child' => 'c'
                ], 'a.child_id = c.id AND c.deleted = 0', 'LEFT')
//                ->join([
//                    'deg_sixth_grade' => 'g'
//                ], 'g.id_card = c.idcard AND g.deleted = 0', 'LEFT')
                ->join([
                    'deg_user_insurance' => 'i'
                ], 'i.house_id = a.house_id AND i.deleted = 0', 'LEFT')
                ->join([
                    'deg_user_residence' => 'r'
                ], 'r.house_id = a.house_id AND r.deleted = 0', 'LEFT')
                ->join([
                    'deg_user_company' => 'y'
                ], 'y.house_id = a.house_id AND y.deleted = 0', 'LEFT')
                ->join([
                    'deg_user_apply_status' => 's'
                ], 's.user_apply_id = a.id and s.deleted = 0 and a.ancestor_id > 0 ', 'LEFT');
            if($this->request->has('message_title') && $this->result['message_title'] !== '' ){
                $data = $data->join([
                    'deg_user_message' => 'm'
                ], "m.user_apply_id = a.id and m.deleted = 0 and m.title = '" . $this->result['message_title'] . "'", 'LEFT');
            }
            $data = $data->field([
                'a.id',
                'a.region_id' => 'region_id',
                'a.school_type' => 'school_type',
                'a.school_attr' => 'school_attr',
                'a.apply_school_id' => 'apply_school_id',
                'a.house_school_id' => 'house_school_id',
                'a.public_school_id' => 'public_school_id',
                'a.result_school_id' => 'result_school_id',
                'a.admission_type' => 'admission_type',
                'a.apply_status' => 'apply_status',
                'CASE a.prepare_status WHEN 1 THEN "验证通过" WHEN 2 THEN "验证不符" ELSE "-" END' => 'prepare_status_name',
                'd.child_name' => 'real_name',
                'd.child_idcard' => 'student_id_card',
                'd.mobile' => 'mobile',
                'd.child_age_status' => 'child_age_status',
                'd.birthplace_status' => 'birthplace_status',
                'd.guardian_relation' => 'guardian_relation',
                'd.house_status' => 'house_status',
                'd.three_syndromes_status' => 'three_syndromes_status',
                'd.student_status' => 'student_status',
                'd.house_type' => 'house_type',
                'd.insurance_status' => 'insurance_status',
                'd.business_license_status' => 'business_license_status',
                'd.residence_permit_status' => 'residence_permit_status',
                'CONCAT( CASE d.single_double WHEN 1 THEN "房产单符" WHEN 2 THEN "双符" ELSE "-" END, IF(d.single_double_verification = 1, "验证不符", "") )' => 'verification_name',
                'i.api_adcode' => 'insurance_api_adcode',
                'r.api_area_code' => 'residence_api_area',
                'r.live_code' => 'live_code',
                'r.api_code' => 'api_live_code',
                'y.api_area' => 'business_api_area_code',
                $query => 'last_message',
                'p.status' => 'supplement_status',
                'c.api_area' => 'student_api_area',
                'c.api_address' => 'student_api_address',
                'c.api_relation' => 'student_api_relation',
                'c.api_policestation' => 'student_api_policestation',
                'c.kindgarden_name' => 'graduation_name',
                'h.house_address' => 'house_address',
                'h.api_address' => 'api_house_address',
                'h.house_type' => 'house_type',
                'h.code_type' => 'code_type',
                'h.code' => 'house_code',
                'h.api_house_code' => 'api_house_code',
                'a.primary_lost_status' => 'primary_lost_status',
                'a.create_time' => 'apply_create_time',
                $school_query => 'graduation_school_id',
                'a.refuse_count' => 'refuse_count',
                $relation_query => 'relation',
                $parent_name => 'parent_name',
                'IF(a.specialed = 1, CONCAT(d.mobile, "_", a.child_id), "")' => 'special_card',
                'CASE s.auto_check_ancestor WHEN -1 THEN "未比对成功" WHEN 0 THEN "未比对成功" WHEN 1 THEN "属实" WHEN 2 THEN "不属实" ELSE "-" END' => 'check_ancestor_name',
                'd.false_region_id' => 'false_region_id',
            ])
                ->where($where)
                ->order('a.id', 'ASC')->order('a.region_id', 'ASC')->order('a.result_school_id', 'ASC');
            if($is_limit) {
                $data = $data->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();
                $list = $data['data'];
            }else{
                $list = $data->select()->toArray();
            }

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

            $house_type = $this->getTypeData();
            $house_type = $house_type['data'];

            foreach ($list as $k => $v){
                $regionData = filter_value_one($region, 'id', $v['region_id']);
                if (count($regionData) > 0){
                    $list[$k]['region_name'] = $regionData['region_name'];
                }
                $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $v['school_type']);
                $list[$k]['school_type_name'] = '';
                if (count($schoolTypeData) > 0){
                    $list[$k]['school_type_name'] = $schoolTypeData['dictionary_name'];
                }
                $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $v['school_attr']);
                $list[$k]['school_attr_name'] = '';
                if (count($schoolAttrData) > 0){
                    $list[$k]['school_attr_name'] = $schoolAttrData['dictionary_name'];
                }

                $student_age_name = isset($result['age_list'][$v['child_age_status']]) ? $result['age_list'][$v['child_age_status']] : '-';
                $birthplace_status_name = isset($result['birthplace_list'][$v['birthplace_status']]) ? $result['birthplace_list'][$v['birthplace_status']] : '-';
                $relation_name = isset($result['relation_list'][$v['guardian_relation']]) ? $result['relation_list'][$v['guardian_relation']] : '-';
                $house_status_name = isset($result['house_list'][$v['house_status']]) ? $result['house_list'][$v['house_status']] : '-';
                //$three_syndromes_name = isset($result['three_syndromes_list'][$v['three_syndromes_status']]) ? $result['three_syndromes_list'][$v['three_syndromes_status']] : '-';
                $student_status_name = isset($result['school_roll_list'][$v['student_status']]) ? $result['school_roll_list'][$v['student_status']] : '-';
                $three_syndromes_name = $this->getThreeSyndromesName($v);

                if($student_age_name == '足龄'){
                    $student_age_name = '-';
                }
                $fill_relation_name = isset($result['fill_relation_list'][$v['relation']]) ? $result['fill_relation_list'][$v['relation']] : '-';
                $list[$k]['fill_relation_name'] = $fill_relation_name;

                $list[$k]['student_age_name'] = $student_age_name;
                $list[$k]['birthplace_status_name'] = $birthplace_status_name;
                $list[$k]['relation_name'] = $relation_name;
                $list[$k]['house_status_name'] = $house_status_name;
                $list[$k]['three_syndromes_name'] = $three_syndromes_name;
                $list[$k]['student_status_name'] = $student_status_name;

                $list[$k]['apply_school_name'] = '-';
                $applySchoolData = filter_value_one($school, 'id', $v['apply_school_id']);
                if (count($applySchoolData) > 0){
                    $list[$k]['apply_school_name'] = $applySchoolData['school_name'];
                }
                $list[$k]['house_school_name'] = '-';
                $houseSchoolData = filter_value_one($school, 'id', $v['house_school_id']);
                if (count($houseSchoolData) > 0){
                    $list[$k]['house_school_name'] = $houseSchoolData['school_name'];
                }
                $list[$k]['public_school_name'] = '-';
                $publicSchoolData = filter_value_one($school, 'id', $v['public_school_id']);
                if (count($publicSchoolData) > 0){
                    $list[$k]['public_school_name'] = $publicSchoolData['school_name'];
                }
                $list[$k]['result_school_name'] = '-';
                $resultSchoolData = filter_value_one($school, 'id', $v['result_school_id']);
                if (count($resultSchoolData) > 0){
                    $list[$k]['result_school_name'] = $resultSchoolData['school_name'];
                }
                $list[$k]['six_school_name'] = '-';
                if($v['graduation_school_id']) {
                    $graduationSchoolData = filter_value_one($school, 'id', $v['graduation_school_id']);
                    if (count($graduationSchoolData) > 0) {
                        $list[$k]['six_school_name'] = $graduationSchoolData['school_name'];
                    }
                }

                $list[$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'], $v['school_attr']);

                $apply_status_name = isset($result['apply_status_list'][$v['apply_status']]) ? $result['apply_status_list'][$v['apply_status']] : '-';
                $list[$k]['apply_status_name'] = $apply_status_name;
                if($v['supplement_status'] === 0){
                    $list[$k]['supplement_status_name'] = '资料补充中';
                    $list[$k]['supplement_status'] = '资料补充中';
                }else if($v['supplement_status'] == 1){
                    $list[$k]['supplement_status_name'] = '资料已补充';
                    $list[$k]['supplement_status'] = '资料已补充';
                }else{
                    $list[$k]['supplement_status_name'] = '-';
                    $list[$k]['supplement_status'] = '-';
                }

                $list[$k]['insurance_area'] = $v['insurance_status'] == 1 ? '是' : '否';
                $list[$k]['company_area'] = $v['business_license_status'] == 1 ? '是' : '否';
                $list[$k]['residence_area'] = $v['residence_permit_status'] == 1 ? '是' : '否';
                $list[$k]['student_api_relation'] = getRelationByHgx($v['student_api_relation']);
                $list[$k]['house_type_name'] = "-";

                $list[$k]['house_type_name'] = array_key_exists($v['house_type'],$house_type['house_list']) ? $house_type['house_list'][$v['house_type']] : '-';
                $list[$k]['code_type_name'] = array_key_exists($v['code_type'],$house_type['code_list']) ? $house_type['code_list'][$v['code_type']] : '-';

                $list[$k]['insurance_area'] = '-';
                $list[$k]['residence_area'] = '-';
                $list[$k]['company_area'] = '-';

                if($v['insurance_api_adcode']) {
                    $regionData = filter_value_one($region, 'simple_code', $v['insurance_api_adcode']);
                    if (count($regionData) > 0){
                        $list[$k]['insurance_area'] = $regionData['region_name'];
                    }
                }

                if($v['residence_api_area']) {
                    $regionData = filter_value_one($region, 'simple_code', $v['residence_api_area']);
                    if (count($regionData) > 0){
                        $list[$k]['residence_area'] = $regionData['region_name'];
                    }
                }

                if($v['business_api_area_code']) {
                    $regionData = filter_value_one($region, 'simple_code', $v['business_api_area_code']);
                    if (count($regionData) > 0){
                        $list[$k]['company_area'] = $regionData['region_name'];
                    }
                }

                if($v['student_id_card'] == '' && $v['special_card']){
                    $list[$k]['student_id_card'] = $v['special_card'];
                }

                if($v['school_attr'] == 1) {
                    $list[$k]['primary_lost_status'] = '-';
                }elseif ($v['school_attr'] == 2) {
                    if ($v['primary_lost_status'] == 1) {
                        $list[$k]['primary_lost_status'] = '是';
                    } else {
                        $list[$k]['primary_lost_status'] = '否';
                    }
                }

                $list[$k]['factor_region_status'] = '-';
                if($v['false_region_id'] > 0) {
                    $regionData = filter_value_one($region, 'id', $v['false_region_id']);
                    if (count($regionData) > 0) {
                        $list[$k]['factor_region_status'] = $regionData['region_name'] . "条件不符";
                    }
                }
            }

            if($is_limit) {
                $data['select_region'] = $select_region;
                $data['data'] = $list;
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                $data['resources'] = $res_data;
                return ['code' => 1, 'list' => $data];
            }else{
                return ['code' => 1, 'list' => $list];
            }
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
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
            case 8:
                $name = '摸排';
                break;
        }
        return $name;
    }

    /**
     * excel表格导出
     * @param string $fileName 文件名称
     * @param array $headArr 表头名称
     * @param array $data 要导出的数据
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @author Mr.Lv   3063306168@qq.com
     */
    private function excelExport($fileName = '', $headArr = [], $data = [])
    {

        $fileName .= "_" . date("Y_m_d", time());
        $spreadsheet = new Spreadsheet();
        $objPHPExcel = $spreadsheet->getActiveSheet();
        $firstColumn = 'A';// 设置表头

        $i = 0;
        foreach ($headArr as $k => $v) {
            $key = floor($i / 26);
            $firstNum = '';
            if ($key > 0) {
                # 当$k等于1,第一个列标签还是A,所以需要减去1
                $firstNum = chr(ord($firstColumn) + $key - 1);
            }
            $secondKey = $i % 26;
            $secondNum = chr(ord($firstColumn) + $secondKey);
            $column = $firstNum . $secondNum;

            $objPHPExcel->setCellValue($column . '1', $v);
            $i++;
        }
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(40);
        $spreadsheet->getActiveSheet()->getStyle('C')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(60);

        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('N')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('P')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('R')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('T')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('U')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('V')->setWidth(30);
        $spreadsheet->getActiveSheet()->getStyle('V')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('W')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('X')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('Y')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('Z')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AA')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AB')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AC')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AD')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AE')->setWidth(120);
        $spreadsheet->getActiveSheet()->getStyle('AF')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getStyle('AG')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);

        $column = 2;
        foreach ($data as $k => $rows) { // 行写入
            $i = 0;
            foreach ($rows as $keyName => $value) { // 列写入
                $key = floor($i / 26);
                $firstNum = '';
                if ($key > 0) {
                    # 当$k等于1,第一个列标签还是A,所以需要减去1
                    $firstNum = chr(ord($firstColumn) + $key - 1);
                }
                $secondKey = $i % 26;
                $secondNum = chr(ord($firstColumn) + $secondKey);
                $span = $firstNum . $secondNum;

                if($keyName == 'student_id_card' || $keyName == 'house_code' || $keyName == 'live_code' || $keyName == 'api_live_code' ){
                    //$objPHPExcel->setCellValue($span . $column, '\'' . $value);
                    $spreadsheet->getActiveSheet()->setCellValueExplicit($span . $column, $value, DataType::TYPE_STRING);
                }else{
                    $objPHPExcel->setCellValue($span . $column, $value);
                }
                $i++;
            }
            $column++;

        }

        //$fileName = iconv("utf-8", "gbk//IGNORE", $fileName); // 重命名表（UTF8编码不需要这一步）
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        //删除清空：
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    }

}