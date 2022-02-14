<?php
/**
 * Created by PhpStorm.
 * User: PhpStorm
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\recruit;

use app\common\controller\Education;
use app\common\model\ApplyReplenished;
use app\common\model\Schools;
use app\common\model\UserApply;
use app\common\model\UserApply as model;
use app\common\model\UserApplyDetail;
use app\common\model\UserApplyStatus;
use app\common\model\UserApplyTmp;
use app\common\model\UserChild;
use app\common\model\UserDispatch;
use app\common\model\UserFamily;
use app\common\model\UserHouse;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use app\common\validate\recruit\Apply as validate;
use think\facade\Db;
use think\facade\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BusinessCentral extends Education
{
    /**
     * 业务办理列表信息
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['a.deleted','=',0];
                $where[] = ['d.deleted','=',0];
                $where[] = ['a.offlined','=',0];

                if($this->request->has('voided') && $this->result['voided'] > 0)
                {
                    $where[] = ['a.voided', '=', 1];//作废
                }else{
                    $where[] = ['a.voided', '=', 0];//没有作废
                }
                //教管会管理的学校
                $school_ids = [];
                if ($this->userInfo['central_id'] > 0) {
                    //区县角色排除市直学校
                    $central_id = $this->userInfo['central_id'];
                    $central_where = [];
                    $central_where[] = ['deleted','=',0];
                    $central_where[] = ['disabled','=',0];
                    $central_where[] = ['directly','=',0];//不是市直
                    $central_where[] = ['central_id', '=', $central_id];
                    $school_ids = Db::name("SysSchool")->where($central_where)->column('id');
                }else{
                    throw new \Exception('教管会管理员所属教管会ID设置错误');
                }
                //条件集合
                $_condition = $this->getCondition();
                if($_condition['code'] == 0){
                    throw new \Exception($_condition['msg']);
                }
                $condition = [];
                if($_condition['data']){
                    $condition = $_condition['data'];
                }else{
                    //无搜索条件 不显示数据
                    if($this->request->has('voided') && $this->result['voided'] == '') {
                        $where[] = [Db::raw(1), '=', 0];
                    }
                }
                if($this->request->has('keyword') && $this->result['keyword'] == ''){
                    if ($this->userInfo['region_id']) {
                        $where[] = ['a.region_id', '=', $this->userInfo['region_id']];
                    }else{
                        throw new \Exception('教管会管理员所属区县ID设置错误');
                    }

                    //公办、民办、小学、初中权限
                    $school_where = $this->getSchoolWhere('a');
                    $where[] = $school_where['school_attr'];
                    $where[] = $school_where['school_type'];

                    if($school_ids){
                        $where[] = Db::Raw(" a.apply_school_id in (" . implode(',', $school_ids) .
                            ") OR a.public_school_id in (" . implode(',', $school_ids) .
                            ") OR a.result_school_id in (" . implode(',', $school_ids) . ") ");
                    }
                }
                //最后一条消息
                $query = Db::name('UserMessage')->where('user_apply_id', Db::raw('a.id'))
                    ->field(['title'])->order('create_time', 'DESC')->limit(1)->buildSql();

                $data = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id', 'LEFT')
                    ->join([
                        'deg_user_supplement' => 'p'
                    ], 'p.user_apply_id = a.id and p.deleted=0', 'LEFT');
                if($this->request->has('message_title') && $this->result['message_title'] !== '' ) {
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
                    ->where($where)->where($condition)
                    ->order('a.id', 'DESC')->order('a.region_id', 'ASC')->order('a.result_school_id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                //批量查询没有查询到的数据
                $not_check_id_cards = [];
                if ($this->request->has('batch') && $this->result['batch'] !== '') {
                    $cache_id_card = Cache::get('importBatchCardIds_' . $this->userInfo['manage_id'], []);
                    foreach ($cache_id_card as $k => $v){
                        $special_card = explode("_", $v );
                        if(count($special_card) == 2){
                            $child_ids[] = $special_card[1];
                            unset($cache_id_card[$k]);
                        }
                    }
                    $query_id_card = Db::name('UserApply')->alias('a')
                        ->join([
                            'deg_user_apply_detail' => 'd'
                        ], 'd.child_id = a.child_id', 'LEFT')
                        ->join([
                            'deg_user_supplement' => 'p'
                        ], 'p.user_apply_id = a.id and p.deleted=0', 'LEFT')
                        ->where($where)->where($condition)->column('d.child_idcard');

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
                    if($v['region_id'] != $this->userInfo['region_id'] ){
                        $data['data'][$k] = $empty_data;
                        $msg = '';
                        if($search_length > 11){
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '】';
                            }
                            $msg .= '申报区域非本区域！';
                        }else{
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】';
                            }
                            $msg .= '申报区域非本区域！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }
                    if(!in_array($v['apply_school_id'], $school_ids) &&
                        !in_array($v['public_school_id'], $school_ids) &&
                        !in_array($v['result_school_id'], $school_ids)){
                        $data['data'][$k] = $empty_data;
                        $msg = '';
                        if($search_length > 11){
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '】';
                            }
                            $msg .= '申报学校无权限管理！';
                        }else{
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】';
                            }
                            $msg .= '申报学校无权限管理！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }

                    if($this->userInfo['public_school_status'] == 0 && $v['school_attr'] == 1 ){
                        $data['data'][$k] = $empty_data;
                        $msg = '';
                        if($search_length > 11){
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '】';
                            }
                            $msg .= '账号没有【公办】学校权限！！';
                        }else{
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】';
                            }
                            $msg .= '账号没有【公办】学校权限！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }
                    if($this->userInfo['civil_school_status'] == 0 && $v['school_attr'] == 2 ){
                        $data['data'][$k] = $empty_data;
                        $msg = '';
                        if($search_length > 11){
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '】';
                            }
                            $msg .= '账号没有【民办】学校权限！！';
                        }else{
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】';
                            }
                            $msg .= '账号没有【民办】学校权限！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }
                    if($this->userInfo['primary_school_status'] == 0 && $v['school_type'] == 1 ){
                        $data['data'][$k] = $empty_data;
                        $msg = '';
                        if($search_length > 11){
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '】';
                            }
                            $msg .= '账号没有【小学】学校权限！！';
                        }else{
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】';
                            }
                            $msg .= '账号没有【小学】学校权限！';
                        }
                        $data['data'][$k]['msg'] = $msg;
                        continue;
                    }
                    if($this->userInfo['junior_middle_school_status'] == 0 && $v['school_attr'] == 2 ){
                        $data['data'][$k] = $empty_data;
                        $msg = '';
                        if($search_length > 11){
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '】';
                            }
                            $msg .= '账号没有【初中】学校权限！！';
                        }else{
                            if($this->request->has('keyword') && $this->result['keyword'] != ''){
                                $msg .= '【' . $this->result['keyword'] . '：' . $v['student_id_card'] . '】';
                            }
                            $msg .= '账号没有【初中】学校权限！';
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

                    if($v['school_attr'] == 1){
                        $data['data'][$k]['primary_lost_status'] = '-';
                    }elseif($v['school_attr'] == 2) {
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
        }else{}
    }

    /**
     * 业务办理页面资源
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
                $data['school_list'] = [];
                foreach ($result['school_list'] as $item){
                    if($item['onlined'] == 1){
                        $data['school_list'][] = $item;
                    }
                }

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

    /**
     * 查看
     * @return Json
     */
    public function getDetail(): Json
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
     * 获取勾选学生信息
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'id',
                ]);
                $ids = $data['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选需要操作的学生');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 1];
                $voided_count = Db::name('UserApply')->where($where)->count();
                if($voided_count > 0 ){
                    throw new \Exception('选择的学生信息已作废！');
                }

                $where = [];
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['d.deleted', '=', 0];
                $where[] = ['a.voided', '=', 0];//没有作废
                $where[] = ['a.id', 'in', $ids];

                $list = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id', 'LEFT')
                    ->field([
                        'a.id' => 'id',
                        'd.child_name' => 'real_name',
                        'd.child_idcard' => 'id_card',
                        'd.mobile' => 'mobile',
                    ])
                    ->where($where)->select()->toArray();

               /* $where[] = ['a.prepared|a.resulted', '=', 1];
                $admission_count = Db::name('UserApply')->alias('a')->where($where)->count();
                if ( $admission_count > 0) {
                    throw new \Exception('勾选的学生已录取');
                }*/

                $res = [
                    'code' => 1,
                    'data' => $list,
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

    /**
     * 派遣单
     * @return Json
     */
    public function actDispatch(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'school_id',
                ]);
                $ids = $data['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选需要操作的学生');
                }

                $school = Db::name('SysSchool')->field(['school_name', 'school_attr', 'school_type', 'region_id', 'onlined', 'regulated'])->find($data['school_id']);
                if(!$school){
                    throw new \Exception('学校信息错误');
                }
                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 1];
                $voided_count = Db::name('UserApply')->where($where)->count();
                if($voided_count > 0 ){
                    throw new \Exception('选择的学生信息已作废！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['paid', '=', 1];
                $paid_count = Db::name('UserApply')->where($where)->count();
                if($paid_count > 0 ){
                    throw new \Exception('选择的学生已民办缴费！');
                }

                //申请资料更改
                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['paid', '=', 0];//没有民办缴费

                $update = [];
                $update['prepared'] = 1;
                $update['resulted'] = 1;
                $update['signed'] = 0;
                $update['admission_type'] = 4;//录取方式 派遣
                $update['result_school_id'] = $data['school_id'];
                $update['region_id'] = $school['region_id'];
                $update['school_type'] = $school['school_type'];
                $update['school_attr'] = $school['school_attr'];

                //线下招生方式
                if($school['onlined'] == 0){
                    $update['offlined'] = 1;//线下方式
                }else{
                    $update['offlined'] = 0;
                }
                if($school['school_attr'] == 1){
                    //$update['public_school_id'] = $data['school_id'];
                    $update['apply_status'] = 3;//公办录取
                }
                if($school['school_attr'] == 2){
                    //$update['apply_school_id'] = $data['school_id'];
                    $update['apply_status'] = 6;//民办已缴费
                    $update['paid'] = 1;//民办缴费状态
                }

                $result = (new model())->editData($update, $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //统计数据更改 变换学校类型、学校性质
                $child_ids = Db::name('UserApply')->where($where)->column('child_id');
                $where = [];
                $where[] = ['child_id', 'in', $child_ids];
                $where[] = ['deleted', '=', 0];
                $update = [];
                $update['school_type'] = $school['school_type'];
                $update['school_attr'] = $school['school_attr'];
                $result = (new UserApplyDetail())->editData($update, $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //派遣单
                foreach ($id_array as $id) {
                    $dispatch = [];
                    $dispatch['user_apply_id'] = $id;
                    $dispatch['school_id'] = $data['school_id'];
                    $dispatch['department_id'] = isset($this->userInfo['department_id']) ? $this->userInfo['department_id'] : 0;
                    $result = (new UserDispatch())->addData($dispatch);

                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }

                    //审核日志
                    $log = [];
                    $log['user_apply_id'] = $id;
                    $log['admin_id'] = $this->userInfo['manage_id'];
                    $log['school_id'] = $data['school_id'];
                    $log['education'] = 1;
                    $log['remark'] = '业务办理-派遣单-' . $school['school_name'];
                    $log['status'] = 1;
                    Db::name('UserApplyAuditLog')->save($log);
                }

                //中心学校录取统计
                $result = $this->getMiddleAdmissionStatistics($data['school_id']);
                if ($result['code'] == 0) {
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

    /**
     * 生源指派【预先判断】
     * @return \think\response\Json
     */
    public function actPrejudge(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'id',
                    'school_id',
                ]);
                $ids = $data['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选需要操作的学生');
                }
                if(!$data['school_id']){
                    throw new \Exception('学校ID参数错误');
                }

                $school = Db::name('SysSchool')->field(['school_attr', 'school_type', 'region_id', 'onlined', 'regulated'])->find($data['school_id']);
                if(!$school){
                    throw new \Exception('学校信息错误');
                }

                $full = false;
                if($school['school_attr'] == 2){
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['a.voided', '=', 0];//没有作废
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.resulted', '=', 1];//录取
                    $where[] = ['a.school_attr', '=', 2];
                    $where[] = ['a.result_school_id', '=', $data['school_id']];
                    $prepared_total = Db::name('UserApply')->alias('a')->where($where)->count();

                    //民办审批学位数量
                    $degree_total = Db::name('PlanApply')
                        ->where('school_id', '>', 0)->where('status', 1)
                        ->where('deleted', 0)->where('school_id', '=', $data['school_id'])
                        ->sum('spare_total');

                    if($prepared_total >= $degree_total){
                        $full = true;
                    }
                }

                $res = [
                    'code' => 1,
                    'id' => $ids,
                    'school_id' => $data['school_id'],
                    'msg' => '确认指派？'
                ];

                if ($full) {
                    $res = [
                        'code' => 10,
                        'id' => $ids,
                        'school_id' => $data['school_id'],
                        'msg' => '您选择的学校学位已满，确认指派？'
                    ];
                }
            } catch (\Exception $exception) {
                $res = [
                    'code' => $exception->getCode(),
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }else{}
    }

    /**
     * 生源指派（调剂）
     * @return Json
     */
    public function actAdjustment(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'school_id',
                ]);
                $ids = $data['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选需要操作的学生');
                }
                if(!$data['school_id']){
                    throw new \Exception('学校ID参数错误');
                }

                $school = Db::name('SysSchool')->field(['school_name', 'school_attr', 'school_type', 'region_id', 'onlined', 'regulated'])->find($data['school_id']);
                if(!$school){
                    throw new \Exception('学校信息错误');
                }
                if($this->userInfo['grade_id'] < $this->city_grade) {
                    if ($school['school_attr'] == 2 && $school['regulated'] == 0) {
                        throw new \Exception('选择的民办学校不能调剂');
                    }
                }
                //线下招生方式
                if($school['onlined'] == 0){
                    throw new \Exception('选择的学校招生方式为【线下】！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 1];
                $voided_count = Db::name('UserApply')->where($where)->count();
                if($voided_count > 0 ){
                    throw new \Exception('选择的学生信息已作废！');
                }
                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['paid', '=', 1];
                $paid_count = Db::name('UserApply')->where($where)->count();
                if($paid_count > 0 ){
                    throw new \Exception('选择的学生已民办缴费！');
                }
//                if($school['school_attr'] == 1) {
//                    $where = [];
//                    $where[] = ['id', 'in', $id_array];
//                    $where[] = ['deleted', '=', 0];
//                    $where[] = ['voided', '=', 0];
//                    $where[] = ['resulted', '=', 1];
//                    $resulted_count = Db::name('UserApply')->where($where)->count();
//                    if ($resulted_count > 0) {
//                        throw new \Exception('选择的学生已最终录取！');
//                    }
//                }
                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['signed', '=', 1];
                $signed_count = Db::name('UserApply')->where($where)->count();
                if($signed_count > 0 ){
                    throw new \Exception('选择的学生已入学报到！');
                }

                //申请资料更改
                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['signed', '=', 0];//没有入学报到
                $where[] = ['paid', '=', 0];//没有民办缴费

                //民办学校没开启调剂 不能调出学生
                if($this->userInfo['grade_id'] < $this->city_grade) {
                    $school_ids = [];
                    $result_school_ids = Db::name('UserApply')->where($where)->where('apply_status', '<>', 4)
                        ->where('school_attr', 2)->column('result_school_id');
                    $apply_school_ids = Db::name('UserApply')->where($where)->where('apply_status', '<>', 4)
                        ->where('school_attr', 2)->column('apply_school_id');
                    if ($result_school_ids && $apply_school_ids) {
                        $school_ids = array_merge($result_school_ids, $apply_school_ids);
                    } elseif ($result_school_ids) {
                        $school_ids = $result_school_ids;
                    } elseif ($apply_school_ids) {
                        $school_ids = $apply_school_ids;
                    }
                    $exist = Db::name('SysSchool')->where('id', 'in', $school_ids)->where('regulated', 0)->column('id');
                    if ($exist) {
                        throw new \Exception('选择的学生中有不能调剂的民办学校的学生');
                    }
                }

                $update = [];
                $update['prepared'] = 1;
                $update['admission_type'] = 5;//录取方式 指派/调剂
                //$update['result_school_id'] = $data['school_id'];
                $update['region_id'] = $school['region_id'];
                $update['school_type'] = $school['school_type'];
                $update['school_attr'] = $school['school_attr'];

                //公办预录取
                if($school['school_attr'] == 1){
                    $update['result_school_id'] = 0;
                    $update['resulted'] = 0;
                    $update['paid'] = 0;
                    $update['public_school_id'] = $data['school_id'];
                    $update['apply_status'] = 2;//公办预录取
                }
                //民办预录取
                if($school['school_attr'] == 2){
                    //$update['apply_school_id'] = $data['school_id'];
                    $update['result_school_id'] = $data['school_id'];
                    $update['resulted'] = 1;
                    $update['paid'] = 0;
                    $update['apply_status'] = 5;//民办录取

                    //中心学校录取统计
                    $result = $this->getMiddleAdmissionStatistics($data['school_id']);
                    if ($result['code'] == 0) {
                        throw new \Exception($result['msg']);
                    }
                }

                $result = (new model())->editData($update, $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //统计数据更改 变换学校类型、学校性质
                $child_ids = Db::name('UserApply')->where($where)->column('child_id');
                $where = [];
                $where[] = ['child_id', 'in', $child_ids];
                $where[] = ['deleted', '=', 0];
                $update = [];
                $update['school_type'] = $school['school_type'];
                $update['school_attr'] = $school['school_attr'];
                $result = (new UserApplyDetail())->editData($update, $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //派遣单
                foreach ($id_array as $id) {
                    $dispatch = [];
                    $dispatch['user_apply_id'] = $id;
                    $dispatch['school_id'] = $data['school_id'];
                    $dispatch['department_id'] = isset($this->userInfo['department_id']) ? $this->userInfo['department_id'] : 0;
                    $result = (new UserDispatch())->addData($dispatch);

                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }

                    //审核日志
                    $log = [];
                    $log['user_apply_id'] = $id;
                    $log['admin_id'] = $this->userInfo['manage_id'];
                    $log['school_id'] = $data['school_id'];
                    $log['education'] = 1;
                    $log['remark'] = '业务办理-生源指派-' . $school['school_name'];;
                    $log['status'] = 1;
                    Db::name('UserApplyAuditLog')->save($log);
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
    /**
     * 不符标记区域
     * @return Json
     */
    public function actUnqualified(): Json
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
                    throw new \Exception('请勾选需要操作的学生');
                }
                $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                $where = [];
                $where[] = ['user_apply_id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['false_region_id', 'in', $region_ids];
                $unqualified_count = Db::name('UserApplyDetail')->where($where)->count();
                if($unqualified_count > 0 ){
                    throw new \Exception('选择的学生信息已标记不符！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];//没有作废
                $where[] = ['prepared', '=', 0];//没有预录取
                $where[] = ['resulted', '=', 0];//没有录取
                $where[] = ['admission_type', '=', 0];//没有录取方式
                $apply_data = Db::name('UserApply')->field([
                    'id',
                    'user_id',
                    'region_id'
                ])->where($where)->select()->toArray();
                if(count($apply_data) <> count($id_array) ){
                    throw new \Exception('选择的学生有已录取信息！');
                }
//                $region = Cache::get('region');
//                if(!$region){
//                    throw new \Exception('区县缓存错误！');
//                }
                foreach ($apply_data as $item){
//                    $replenished = [];
//                    Db::name('ApplyReplenished')->where('user_id',$item['user_id'])->update(['deleted' => 1]);
//                    foreach ($region as $k => $v){
//                        if($v['id'] > 1 && $v != $item['region_id']) {
//                            $replenished[$k]['user_id'] = $item['user_id'];
//                            $replenished[$k]['region_ids'] = $v['id'];
//                            $replenished[$k]['region_names'] = $v['region_name'];
//                            $replenished[$k]['school_attr'] = "1,2";
//                            $replenished[$k]['school_attr_text'] = "公办/民办";
//                            $replenished[$k]['start_time'] = time();
//                            $replenished[$k]['end_time'] = strtotime("+3 days", time());
//                        }
//                    }
//                    Db::name('ApplyReplenished')->insertAll($replenished);
                    $where = [];
                    $where[] = ['user_apply_id', '=', $item['id']];
                    $where[] = ['deleted', '=', 0];
                    $update['false_region_id'] = $item['region_id'];
                    $result = (new UserApplyDetail())->editData($update, $where);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }

                    //审核日志
                    $log = [];
                    $log['user_apply_id'] = $item['id'];
                    $log['admin_id'] = $this->userInfo['manage_id'];
                    $log['education'] = 1;
                    $log['remark'] = '业务办理-不符';
                    $log['status'] = 1;
                    Db::name('UserApplyAuditLog')->save($log);
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

    /**
     * 作废
     * @return Json
     */
    public function actVoid(): Json
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
                    throw new \Exception('请勾选需要操作的学生');
                }
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSPROCESS','SYSDSJDB');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['paid', '=', 1];
                $paid_count = Db::name('UserApply')->where($where)->count();
                if($paid_count > 0 ){
                    throw new \Exception('选择的学生信息已缴费！');
                }

                if(time() >= $getData['data']){
                    $where = [];
                    $where[] = ['id', 'in', $id_array];
                    $where[] = ['deleted', '=', 0];
                    $where[] = ['voided', '=', 1];
                    $voided_count = Db::name('UserApply')->where($where)->count();
                    if($voided_count > 0 ){
                        throw new \Exception('选择的学生信息已作废！');
                    }

                    $where = [];
                    $where[] = ['id', 'in', $id_array];
                    $where[] = ['deleted', '=', 0];
                    $where[] = ['voided', '=', 0];

                    $child_ids = Db::name('UserApply')->where($where)->column('child_id');

                    $update['voided'] = 1;
                    $result = (new model())->editData($update, $where);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    //作废申请，如果有变更区域数据 删除
                    Db::name("change_region")->whereIn('child_id',$child_ids)->where("status",0)->update(['deleted'=>1]);
                }else{
                    $where = [];
                    $where[] = ['id', 'in', $id_array];
                    $where[] = ['deleted', '=', 1];
                    $voided_count = Db::name('UserApply')->where($where)->count();
                    if($voided_count > 0 ){
                        throw new \Exception('选择的学生信息已作废！');
                    }

                    $where = [];
                    $where[] = ['id', 'in', $id_array];
                    $where[] = ['deleted', '=', 0];

                    $child_ids = Db::name('UserApply')->where($where)->column('child_id');

                    $update['deleted'] = 1;
                    $result = (new model())->editData($update, $where);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }

                    $result = (new UserApplyDetail())->editData(['deleted' => 1], [['child_id', 'in', $child_ids]]);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }

                    $result = (new UserApplyStatus())->editData(['deleted' => 1], [['user_apply_id', 'in', $id_array]]);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                }

                foreach ($id_array as $id) {
                    //审核日志
                    $log = [];
                    $log['user_apply_id'] = $id;
                    $log['admin_id'] = $this->userInfo['manage_id'];
                    $log['education'] = 1;
                    $log['remark'] = '业务办理-作废';
                    $log['status'] = 1;
                    Db::name('UserApplyAuditLog')->save($log);
                }

                /*$result = (new UserApplyDetail())->editData(['deleted' => 1], [['child_id', 'in', $child_ids]]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $result = (new UserApplyStatus())->editData(['deleted' => 1], [['user_apply_id', 'in', $id_array]]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }*/

                //删除学生信息和临时关系信息
                /*$result = (new UserChild())->editData(['deleted' => 1], [['id', 'in', $child_ids]]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                (new UserApplyTmp())->editData(['deleted' => 1], [['apply_id', 'in', $id_array]]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }*/

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

    /**
     * 开启意愿学校
     * @return Json
     */
    public function actOpen(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $ids = '';
                if(isset($this->result['id'])){
                    $ids = $this->result['id'];
                }
                $id_array = [];
                if($ids != '') {
                    $id_array = explode(',', $ids);
                }
                if (count($id_array) == 0) {
                    //throw new \Exception('请勾选需要操作的学生');
                    $where = [];
                    $where[] = ['a.deleted','=',0];
                    $where[] = ['d.deleted','=',0];
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

                    $_condition = $this->getCondition();
                    if($_condition['code'] == 0){
                        throw new \Exception($_condition['msg']);
                    }
                    //$condition = [];
                    if($_condition['data']){
                        $condition = $_condition['data'];
                    }else{
                        //无搜索条件 不显示数据
                        throw new \Exception('请选择搜索条件搜索信息后操作！');
                    }

                    $id_array = Db::name('UserApply')->alias('a')
                        ->join([
                            'deg_user_apply_detail' => 'd'
                        ], 'd.child_id = a.child_id', 'LEFT')
                        ->join([
                            'deg_user_supplement' => 'p'
                        ], 'p.user_apply_id = a.id and p.deleted=0', 'LEFT')
                        ->where($where)->where($condition)
                        ->column('a.id');

                }
                if (count($id_array) == 0) {
                    throw new \Exception('没有查询到操作的数据！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 1];
                $voided_count = Db::name('UserApply')->where($where)->count();
                if($voided_count > 0 ){
                    throw new \Exception('选择的学生信息已作废！');
                }
                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['school_attr', '=', 2];
                $civilian_count = Db::name('UserApply')->where($where)->count();
                if($civilian_count > 0 ){
                    throw new \Exception('选择的学生信息有民办申请信息！');
                }

                $where = [];
                $where[] = ['a.id', 'in', $id_array];
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['a.voided', '=', 0];
                $where[] = ['a.prepared', '=', 1];

                $prepared = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                    ->where($where)
                    ->column('d.child_idcard');

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['prepared', '=', 0];

                $update['open_school'] = 1;
                $result = (new model())->editData($update, $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }


                $res = [
                    'code' => 1,
                    'prepared' => $prepared,
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

    /**
     * 导入民办摇号录取名单
     * @return Json
     */
    public function actImportCivil(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                set_time_limit(0);
                $file_size = $_FILES['file']['size'];
                if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                    throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
                }
                //限制上传表格类型
                $fileExtendName = substr(strrchr($_FILES['file']["name"], '.'), 1);

                if ( $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    if ($fileExtendName == 'xls') {
                        $objReader = IOFactory::createReader('Xls');
                    } else {
                        $objReader = IOFactory::createReader('Xlsx');
                    }

                    $filename = $_FILES['file']['tmp_name'];
                    $objPHPExcel = $objReader->load($filename);
                    $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                    $highestRow = $sheet->getHighestRow();       // 取得总行数
                    //$highestColumn = $sheet->getHighestColumn();   // 取得总列数

                    //导入模板检测
                    $check_res = $this->checkCivilTemplate($objPHPExcel);
                    if($check_res['code'] == 0){
                        throw new \Exception($check_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的六年级学生信息。
                    $data = [];
                    $repeat = [];

                    //循环读取excel表格，整合成数组。model
                    $hasChildIdCardData = Db::name('UserChild')->where('idcard', '<>', '')
                        ->where('deleted',0)->column('id','idcard');

                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    for ($j = 3; $j <= $highestRow; $j++) {
                        $student_name = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();
                        $school_type_name = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();
                        $school_attr_name = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();
                        $result_school_name = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();

                        //if($student_name == '' && $student_id_card == '' && $school_type_name == '' && $school_attr_name == '' && $result_school_name == '' ) continue;

                        $tmp[$j - 3] = [
                            'student_name' => trim($student_name,"\0\t\n\x0B\r "),
                            'student_id_card' => strtoupper(trim($student_id_card,"\0\t\n\x0B\r ")),
                            'student_id_card_original' => trim($student_id_card,"\0\t\n\x0B\r "),
                            'school_type_name' => trim($school_type_name,"\0\t\n\x0B\r "),
                            'school_attr_name' => trim($school_attr_name,"\0\t\n\x0B\r "),
                            'result_school_name' => trim($result_school_name,"\0\t\n\x0B\r "),
                        ];
                        array_push($data, $tmp[$j - 3]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的身份证号、学籍号
                    $student_id_card_data = array_column($data,'student_id_card');
                    $student_id_card_data = array_filter($student_id_card_data);
                    $repeat_data_student = array_count_values($student_id_card_data);

                    $successNum = 0;
                    $apply_data = [];
                    $school_id_name = [];
                    $admission_school = [];
                    $civil_school_ids = [];
                    $error_school_id = [];
                    foreach ($data as $key=>$item) {
                        $row = $key + 3;

                        if($item['result_school_name'] == ''){
                            $error[] = '第' . $row . '行录取学校不能为空';
                            continue;
                        }

                        $school = Db::name('sys_school')->where([['school_name', '=', $item['result_school_name']],
                            ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行录取学校名称为【' . $item['result_school_name'] . '】不存在';
                            continue;
                        }

                        if($item['student_name'] == ''){
                            $error[] = '第' . $row . '行学生姓名为空';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }
                        $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                        if (preg_match($preg, $item['student_name']) == 0){
                            $error[] = '第' . $row . '行学生姓名只能为汉字';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }
                        if($item['student_id_card'] == ''){
                            $error[] = '第' . $row . '行学生身份证号为空';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }
                        if($item['school_type_name'] == ''){
                            $error[] = '第' . $row . '行学校类型为空';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }
                        if($item['school_attr_name'] == ''){
                            $error[] = '第' . $row . '行学校性质为空';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }
                        if($item['school_attr_name'] != '民办'){
                            $error[] = '第' . $row . '行学校性质只能为【民办】';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }

                        if($school['school_attr'] == 1){
                            $error[] = '第' . $row . '行录取学校名称为【' . $item['result_school_name'] . '】性质为【公办】';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }
                        if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                            $error[] = '第' . $row . '行录取学校名称为【' . $item['result_school_name'] . '】无权限管理';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }
                        if($school['school_type'] == 1){
                            if($item['school_type_name'] != '小学'){
                                $error[] = '第' . $row . '行学校类型错误';
                                if(!in_array($school['id'], $error_school_id)) {
                                    $error_school_id[] = $school['id'];
                                }
                                continue;
                            }
                        }
                        if($school['school_type'] == 2){
                            if($item['school_type_name'] != '初中'){
                                $error[] = '第' . $row . '行学校类型错误';
                                if(!in_array($school['id'], $error_school_id)) {
                                    $error_school_id[] = $school['id'];
                                }
                                continue;
                            }
                        }

                        $special_card = explode("_", $item['student_id_card'] );
                        //特殊情况导入 无身份证情况
                        if(count($special_card) == 2){
                            $mobile = $special_card[0];
                            $child_id = $special_card[1];

                            $where = [];
                            $where[] = ['deleted', '=', 0];
                            $where[] = ['mobile', '=', $mobile];
                            $where[] = ['child_id', '=', $child_id];
                            $detail = Db::name('UserApplyDetail')->where($where)->find();
                            if(!$detail){
                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】申请详细信息不存在';
                                if(!in_array($school['id'], $error_school_id)) {
                                    $error_school_id[] = $school['id'];
                                }
                                continue;
                            }
                        }else {
                            $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                            if (preg_match($preg, $item['student_id_card']) == 0) {
                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】不正确';
                                if(!in_array($school['id'], $error_school_id)) {
                                    $error_school_id[] = $school['id'];
                                }
                                continue;
                            }
                            if ($repeat_data_student[$item['student_id_card']] > 1) {
                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】重复';
                                if(!in_array($school['id'], $error_school_id)) {
                                    $error_school_id[] = $school['id'];
                                }
                                continue;
                            }
                            if (!in_array($item['student_id_card'], array_keys($hasChildIdCardData))) {
                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】学生信息不存在';
                                if(!in_array($school['id'], $error_school_id)) {
                                    $error_school_id[] = $school['id'];
                                }
                                continue;
                            }
                            $child_id = $hasChildIdCardData[$item['student_id_card']];
                        }

                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['voided', '=', 0];//没有作废
                        $where[] = ['child_id', '=', $child_id];
                        //$where[] = ['apply_school_id', '=', $school['id']];
                        $apply = Db::name('UserApply')->where($where)->find();
                        if(!$apply){
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】申请信息不存在';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }else{
                            if($apply['school_attr'] == 1){
                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】的申请学校性质为公办';
                                if(!in_array($school['id'], $error_school_id)) {
                                    $error_school_id[] = $school['id'];
                                }
                                continue;
                            }
//                            if($apply['apply_school_id'] != $school['id']){
//                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】申请学校与导入学校不匹配';
//                                if(!in_array($school['id'], $error_school_id)) {
//                                    $error_school_id[] = $school['id'];
//                                }
//                                continue;
//                            }
                        }

                        $where[] = ['resulted', '=', 1];
                        $admission_count = Db::name('UserApply')->where($where)->count();
                        if($admission_count > 0){
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】已录取';
                            if(!in_array($school['id'], $error_school_id)) {
                                $error_school_id[] = $school['id'];
                            }
                            continue;
                        }

                        $apply_data[] = [
                            'id' => $apply['id'],
                            'region_id' => $school['region_id'],
                            'school_attr' => $school['school_attr'],
                            'school_type' => $school['school_type'],
                            'prepared' => 1,
                            'resulted' => 1,
                            'student_id_card' => $item['student_id_card'],
                            'admission_type' => 1,//派位(摇号)录取方式
                            'result_school_id' => $school['id'],
                            'apply_status' => 5,//民办录取
                        ];
                        if(isset($admission_school[$school['id']])){
                            $admission_school[$school['id']] += 1;
                        }else{
                            $admission_school[$school['id']] = 1;
                        }
                        $school_id_name[$school['id']] = $school['school_name'];

                        /*$result = (new model())->editData($apply_data);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }

                        //中心学校录取统计
                        $result = $this->getMiddleAdmissionStatistics($school['id']);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }*/

                        if(!in_array($school['id'], $civil_school_ids)) {
                            $civil_school_ids[] = $school['id'];
                        }
                        //$successNum++;
                    }
                    //民办录取数量
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['a.voided', '=', 0];//没有作废
                    $where[] = ['a.school_attr', '=', 2];
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.resulted', '=', 1];//录取
                    $where[] = ['a.offlined', '=', 0];//不是线下录取
                    $where[] = ['a.admission_type', '>', 0];
                    $where[] = ['a.result_school_id', 'in', $civil_school_ids];//录取学校
                    $used = Db::name('UserApply')->alias('a')->group('result_school_id')
                        ->where($where)->column('COUNT(*)', 'result_school_id');

                    //民办审批学位数量
                    $degree = Db::name('PlanApply')
                        ->where('school_id', '>', 0)->where('status', 1)
                        ->where('deleted', 0)->where('school_id', 'in', $civil_school_ids)
                        ->group('school_id')->column('SUM(spare_total)', 'school_id');

                    foreach ($civil_school_ids as $k => $school_id){
                        $degree_count = isset($degree[$school_id]) ? $degree[$school_id] : 0;
                        $used_count = isset($used[$school_id]) ? $used[$school_id] : 0;
                        $admission_count = isset($admission_school[$school_id]) ? $admission_school[$school_id] : 0;

                        if( ($used_count + $admission_count) > $degree_count){
                            $error[] = '学校【' . $school_id_name[$school_id] . '】学位数量不足！';
                            $error_school_id[] = $school_id;
                            unset($civil_school_ids[$k]);
                        }
                        //申报人数
                        $where = [];
                        $where[] = ['a.deleted', '=', 0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['a.school_attr', '=', 2];
                        $where[] = ['a.apply_school_id|a.result_school_id', '=', $school_id];//录取学校
                        $apply_count = Db::name('UserApply')->alias('a')->where($where)->count();

                        if($apply_count <= $degree_count){
                            $error[] = '学校【' . $school_id_name[$school_id] . '】申报人数没有超过审批学位数量，不用摇号！';
                            $error_school_id[] = $school_id;
                            unset($civil_school_ids[$k]);
                        }
                    }

                    $success_student_id_card = [];
                    foreach ($apply_data as $item){
                        if(!in_array($item['result_school_id'], $error_school_id)){
                            $student_id_card = $item['student_id_card'];
                            unset($item['student_id_card']);
                            $result = (new model())->editData($item);
                            if($result['code'] == 0){
                                //$error[] = $result['msg'];
                                //continue;
                                throw new \Exception($result['msg']);

                            }

                            //中心学校录取统计
                            $result = $this->getMiddleAdmissionStatistics($item['result_school_id']);
                            if ($result['code'] == 0) {
                                $error[] = $result['msg'];
                                continue;
                            }
                            $success_student_id_card[] = $student_id_card;
                            $successNum++;
                        }
                    }

                    $error[] = '成功导入' . $successNum . '条数据';

                    //民办摇号落选的人自动开启补录
                    if($civil_school_ids) {
                        $result = $this->autoCivilReplenished($civil_school_ids);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }
                    }

                    $msg['success_num'] = $successNum;
                    $msg['repeat_num'] = count($repeat);

                    Cache::set('repeat', $repeat);
                    Cache::set('importErrorBusiness_'.$this->userInfo['manage_id'], $error);
                    Cache::set('importBatchCardIds_'.$this->userInfo['manage_id'], $success_student_id_card);

                    $res = [
                        'code' => 1,
                        'data' => $msg
                    ];
                }
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        } else{}
    }

    /**
     * 导入政策生名单
     * @return Json
     */
    public function actImportPolicy(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                set_time_limit(0);
                $file_size = $_FILES['file']['size'];
                if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                    throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
                }
                //限制上传表格类型
                $fileExtendName = substr(strrchr($_FILES['file']["name"], '.'), 1);

                if ( $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    if ($fileExtendName == 'xls') {
                        $objReader = IOFactory::createReader('Xls');
                    } else {
                        $objReader = IOFactory::createReader('Xlsx');
                    }

                    $filename = $_FILES['file']['tmp_name'];
                    $objPHPExcel = $objReader->load($filename);
                    $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                    $highestRow = $sheet->getHighestRow();       // 取得总行数
                    //$highestColumn = $sheet->getHighestColumn();   // 取得总列数

                    //导入模板检测
                    $check_res = $this->checkPolicyTemplate($objPHPExcel);
                    if($check_res['code'] == 0){
                        throw new \Exception($check_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的六年级学生信息。
                    $data = [];
                    $repeat = [];

                    //循环读取excel表格，整合成数组。model
                    $hasChildIdCardData = Db::name('UserChild')->where('idcard', '<>', '')
                        ->where('deleted',0)->column('id','idcard');

                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    for ($j = 3; $j <= $highestRow; $j++) {
                        $student_name = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();
                        //$mobile = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();
                        //$school_type_name = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();
                        //$school_attr_name = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();
                        $result_school_name = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();
                        $policy_reason = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();

                        //if($student_name == '' && $student_id_card == '' && $school_type_name == '' && $school_attr_name == '' && $result_school_name == '' ) continue;

                        $tmp[$j - 3] = [
                            'student_name' => trim($student_name,"\0\t\n\x0B\r "),
                            'student_id_card' => strtoupper(trim($student_id_card,"\0\t\n\x0B\r ")),
                            'student_id_card_original' => trim($student_id_card,"\0\t\n\x0B\r "),
                            //'mobile' => trim($mobile,"\0\t\n\x0B\r "),
                            //'school_type_name' => trim($school_type_name,"\0\t\n\x0B\r "),
                            //'school_attr_name' => trim($school_attr_name,"\0\t\n\x0B\r "),
                            'result_school_name' => trim($result_school_name,"\0\t\n\x0B\r "),
                            'policy_reason' => trim($policy_reason,"\0\t\n\x0B\r "),
                        ];
                        array_push($data, $tmp[$j - 3]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的身份证号、学籍号
                    $student_id_card_data = array_column($data,'student_id_card');
                    $student_id_card_data = array_filter($student_id_card_data);
                    $repeat_data_student = array_count_values($student_id_card_data);

                    $successNum = 0;
                    $apply_data = [];
                    $school_id_name = [];
                    $admission_school = [];
                    $policy_school_ids = [];
                    foreach ($data as $key=>$item) {
                        $row = $key + 3;
                        if($item['student_name'] == ''){
                            $error[] = '第' . $row . '行学生姓名为空';
                            continue;
                        }
                        $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                        if (preg_match($preg, $item['student_name']) == 0){
                            $error[] = '第' . $row . '行学生姓名只能为汉字';
                            continue;
                        }
                        if($item['student_id_card'] == ''){
                            $error[] = '第' . $row . '行学生身份证号为空';
                            continue;
                        }
//                        if($item['mobile'] == ''){
//                            $error[] = '第' . $row . '行联系手机号为空';
//                            continue;
//                        }
//                        $preg = '/^1[3-9]\d{9}$/';
//                        if (preg_match($preg, $item['mobile']) == 0){
//                            $error[] = '第' . $row . '行联系手机号格式错误';
//                            continue;
//                        }
//                        if($item['school_type_name'] == ''){
//                            $error[] = '第' . $row . '行学校类型为空';
//                            continue;
//                        }
//                        if($item['school_attr_name'] == ''){
//                            $error[] = '第' . $row . '行学校性质为空';
//                            continue;
//                        }
                        if($item['result_school_name'] == ''){
                            $error[] = '第' . $row . '行学校名称不能为空';
                            continue;
                        }

                        $school = Db::name('sys_school')->where([['school_name', '=', $item['result_school_name']],
                            ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行学校名称为【' . $item['result_school_name'] . '】不存在';
                            continue;
                        }
                        if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                            $error[] = '第' . $row . '行学校名称为【' . $item['result_school_name'] . '】无权限管理';
                            continue;
                        }
//                        if($school['school_type'] == 1){
//                            if($item['school_type_name'] != '小学'){
//                                $error[] = '第' . $row . '行学校类型错误';
//                                continue;
//                            }
//                        }
//                        if($school['school_type'] == 2){
//                            if($item['school_type_name'] != '初中'){
//                                $error[] = '第' . $row . '行学校类型错误';
//                                continue;
//                            }
//                        }
//                        if($school['school_attr'] == 1){
//                            if($item['school_attr_name'] != '公办'){
//                                $error[] = '第' . $row . '行学校性质错误';
//                                continue;
//                            }
//                        }
//                        if($school['school_attr'] == 2){
//                            if($item['school_attr_name'] != '民办'){
//                                $error[] = '第' . $row . '行学校性质错误';
//                                continue;
//                            }
//                        }

                        $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                        if (preg_match($preg, $item['student_id_card']) == 0){
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】不正确';
                            continue;
                        }
                        if($repeat_data_student[$item['student_id_card']] > 1) {
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】重复';
                            continue;
                        }
                        //学生信息不存在的情况、新增学生信息
                        if( !in_array($item['student_id_card'], array_keys($hasChildIdCardData)) ){
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】学生信息不存在';
                            continue;

//                            $child = [];
//                            $child['mobile'] = $item['mobile'];
//                            $child['real_name'] = $item['student_name'];
//                            $child['idcard'] = $item['student_id_card'];
//                            $result = (new UserChild())->addData($child, 1);
//                            if ($result['code'] == 0) {
//                                $error[] = $result['msg'];
//                                continue;
//                            }
//                            $child_id = $result['insert_id'];
//                            //监护人信息
//                            $family = [];
//                            $family['parent_name'] = $item['student_name'] . '的监护人';
//                            $result = (new UserFamily())->addData($family, 1);
//                            if($result['code'] == 0){
//                                $error[] = $result['msg'];
//                                continue;
//                            }
//                            $family_id = $result['insert_id'];
//                            //房产信息
//                            $house = [];
//                            $house['family_id'] = $family_id;
//                            $result = (new UserHouse())->addData($house, 1);
//                            if($result['code'] == 0){
//                                $error[] = $result['msg'];
//                                continue;
//                            }
//                            $house_id = $result['insert_id'];
//
//                            $apply_data[$key] = [
//                                'enrol_year' => date('Y'),
//                                'region_id' => $school['region_id'],
//                                'school_attr' => $school['school_attr'],
//                                'school_type' => $school['school_type'],
//                                'child_id' => $child_id,
//                                'family_id' => $family_id,
//                                'house_id' => $house_id,
//                                'prepared' => 1,//预录取
//                                'resulted' => 1,//最终录取
//                                'policyed' => 1,//政策生
//                                'admission_type' => 6,//政策生录取方式
//                                'policy_reason' => $item['policy_reason'] ?? '',
//                            ];
//                            if($school['school_attr'] == 1){
//                                $apply_data[$key]['public_school_id'] = $school['id'];
//                                $apply_data[$key]['result_school_id'] = $school['id'];
//                                $apply_data[$key]['apply_status'] = 3;//公办录取
//                            }
//                            if($school['school_attr'] == 2){
//                                $apply_data[$key]['apply_school_id'] = $school['id'];
//                                $apply_data[$key]['result_school_id'] = $school['id'];
//                                $apply_data[$key]['paid'] = 1;
//                                $apply_data[$key]['apply_status'] = 6;//民办已缴费
//                            }
//                            $apply_data[$key]['mobile'] = $item['mobile'];
//                            $apply_data[$key]['student_name'] = $item['student_name'];
//                            $apply_data[$key]['student_id_card'] = $item['student_id_card'];
//                            $result = (new model())->addData($apply_data, 1);
//                            if($result['code'] == 0){
//                                $error[] = $result['msg'];
//                                continue;
//                            }
//                            $user_apply_id = $result['insert_id'];
//                            //统计信息
//                            $detail = [];
//                            $detail['user_apply_id'] = $user_apply_id;
//                            $detail['child_id'] = $child_id;
//                            $detail['mobile'] = $item['mobile'];
//                            $detail['child_name'] = $item['student_name'];
//                            $detail['child_idcard'] = $item['student_id_card'];
//                            $detail['school_attr'] = $school['school_attr'];
//                            $detail['school_type'] = $school['school_type'];
//                            $result = (new UserApplyDetail())->addData($detail);
//                            if($result['code'] == 0){
//                                $error[] = $result['msg'];
//                                continue;
//                            }
//
//                            //比对状态
//                            $status = [];
//                            $status['user_apply_id'] = $user_apply_id;
//                            $result = (new UserApplyStatus())->addData($status);
//                            if($result['code'] == 0){
//                                $error[] = $result['msg'];
//                                continue;
//                            }

                        }else{
                            $child_id = $hasChildIdCardData[$item['student_id_card']];

                            $where = [];
                            $where[] = ['deleted', '=', 0];
                            $where[] = ['voided', '=', 0];//没有作废
                            $where[] = ['child_id', '=', $child_id];
                            $apply = Db::name('UserApply')->where($where)->find();
                            if(!$apply){
                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】申请信息不存在';
                                continue;
                            }

                            if ($this->userInfo['grade_id'] < $this->city_grade) {
                                if ($apply['region_id'] != $school['region_id']) {
                                    $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】已在其他区填报信息';
                                    continue;
                                }
                            }
//                            if ($apply['school_attr'] != $school['school_attr']) {
//                                $error[] = '第' . $row . '行学校名称为【' . $item['result_school_name'] . '】学校性质与填报的学校性质不一致';
//                                continue;
//                            }
//                            if ($apply['school_type'] != $school['school_type']) {
//                                $error[] = '第' . $row . '行学校名称为【' . $item['result_school_name'] . '】学校类型与填报的学校类型不一致';
//                                continue;
//                            }

                            $where[] = ['prepared', '=', 1];
                            $admission_count = Db::name('UserApply')->where($where)->count();
                            if($admission_count > 0){
//                                $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】已预录取';
//                                continue;
                            }

                            $apply_data[$key] = [
                                'id' => $apply['id'],
                                'region_id' => $school['region_id'],
                                'child_id' => $child_id,
                                'school_attr' => $school['school_attr'],
                                'school_type' => $school['school_type'],
                                'prepared' => 1,//预录取
                                'resulted' => 1,//最终录取
                                'policyed' => 1,//政策生
                                'admission_type' => 6,//政策生录取方式
                                'policy_reason' => $item['policy_reason'] ?? '',
                            ];
                            if($school['school_attr'] == 1){
                                $apply_data[$key]['public_school_id'] = $school['id'];
                                $apply_data[$key]['result_school_id'] = $school['id'];
                                $apply_data[$key]['apply_status'] = 3;//公办录取
                            }
                            if($school['school_attr'] == 2){
                                $apply_data[$key]['apply_school_id'] = $school['id'];
                                $apply_data[$key]['result_school_id'] = $school['id'];
                                $apply_data[$key]['paid'] = 1;
                                $apply_data[$key]['apply_status'] = 6;//民办已缴费
                            }
                            //$apply_data[$key]['mobile'] = $item['mobile'];
                            $apply_data[$key]['student_name'] = $item['student_name'];
                            $apply_data[$key]['student_id_card'] = $item['student_id_card'];
//                            $result = (new model())->editData($apply_data);
//                            if($result['code'] == 0){
//                                $error[] = $result['msg'];
//                                continue;
//                            }
                        }

                        if(isset($admission_school[$school['id']])){
                            $admission_school[$school['id']] += 1;
                        }else{
                            $admission_school[$school['id']] = 1;
                        }
                        $school_id_name[$school['id']] = $school['school_name'];

                        if(!in_array($school['id'], $policy_school_ids)) {
                            $policy_school_ids[] = $school['id'];
                        }
                    }
                    //学校录取数量
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['a.voided', '=', 0];//没有作废
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.resulted', '=', 1];//录取
                    $where[] = ['a.offlined', '=', 0];//线下录取
                    $where[] = ['a.result_school_id', 'in', $policy_school_ids];//录取学校
                    $used = Db::name('UserApply')->alias('a')->group('result_school_id')
                        ->where($where)->column('COUNT(*)', 'result_school_id');

                    //公办审批学位数量
                    $degree = Db::name('PlanApply')
                        ->where('school_id', '>', 0)->where('status', 1)
                        ->where('deleted', 0)->where('school_id', 'in', $policy_school_ids)
                        ->group('school_id')->column('SUM(spare_total)', 'school_id');

                    foreach ($policy_school_ids as $k => $school_id){
                        $degree_count = isset($degree[$school_id]) ? $degree[$school_id] : 0;
                        $used_count = isset($used[$school_id]) ? $used[$school_id] : 0;
                        $admission_count = isset($admission_school[$school_id]) ? $admission_school[$school_id] : 0;

                        if( ($used_count + $admission_count) > $degree_count){
                            $error[] = '学校【' . $school_id_name[$school_id] . '】学位数量不足！';
                            unset($policy_school_ids[$k]);
                        }
                    }

                    $success_student_id_card = [];
                    foreach ($apply_data as $k => $item){
                        if(in_array($item['result_school_id'], $policy_school_ids)){
                            $child_id = $item['child_id'];
                            //$mobile = $item['mobile'];
                            //$student_name = $item['student_name'];
                            $student_id_card = $item['student_id_card'];

                            unset($item['mobile']);
                            unset($item['student_name']);
                            unset($item['student_id_card']);

                            if(isset($item['id'])){
                                $result = (new model())->editData($item);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $user_apply_id = $item['id'];
                            }else{
                                $result = (new model())->addData($item, 1);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $user_apply_id = $result['insert_id'];
                            }

                            //统计信息
                            $where = [];
                            $where[] = ['deleted', '=', 0];
                            $where[] = ['child_id', '=', $child_id];
                            $applyDetail = Db::name('UserApplyDetail')->where($where)->find();
                            if($applyDetail){
                                $detail = [];
                                $detail['id'] = $applyDetail['id'];
//                                $detail['user_apply_id'] = $user_apply_id;
//                                $detail['child_id'] = $child_id;
//                                $detail['mobile'] = $mobile;
//                                $detail['child_name'] = $student_name;
                                $detail['child_idcard'] = $student_id_card;
                                $detail['school_attr'] = $item['school_attr'];
                                $detail['school_type'] = $item['school_type'];

                                $result = (new UserApplyDetail())->editData($detail);
                                if($result['code'] == 0){
                                    $error[] = $result['msg'];
                                    continue;
                                }
                            }else {
//                                $detail = [];
//                                $detail['user_apply_id'] = $user_apply_id;
//                                $detail['child_id'] = $child_id;
//                                $detail['mobile'] = $mobile;
//                                $detail['child_name'] = $student_name;
//                                $detail['child_idcard'] = $student_id_card;
//                                $detail['school_attr'] = $item['school_attr'];
//                                $detail['school_type'] = $item['school_type'];
//                                $result = (new UserApplyDetail())->addData($detail);
//                                if ($result['code'] == 0) {
//                                    $error[] = $result['msg'];
//                                    continue;
//                                }
                                $error[] = '学生身份证号【' . $student_id_card . '】申请详细信息不存在';
                                continue;
                            }

                            //比对状态
                            $where = [];
                            $where[] = ['deleted', '=', 0];
                            $where[] = ['user_apply_id', '=', $user_apply_id];
                            $applyStatus = Db::name('UserApplyStatus')->where($where)->find();
                            if(!$applyStatus) {
                                $status = [];
                                $status['user_apply_id'] = $user_apply_id;
                                $result = (new UserApplyStatus())->addData($status);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                            }

                            //审核日志
                            $log = [];
                            $log['user_apply_id'] = $user_apply_id;
                            $log['admin_id'] = $this->userInfo['manage_id'];
                            $log['school_id'] = $item['result_school_id'];
                            $log['education'] = 1;
                            $log['remark'] = '业务办理-政策生导入-' . $school_id_name[$item['result_school_id']];
                            $log['status'] = 1;
                            Db::name('UserApplyAuditLog')->save($log);

                            //中心学校录取统计
                            $result = $this->getMiddleAdmissionStatistics($item['result_school_id']);
                            if ($result['code'] == 0) {
                                throw new \Exception($result['msg']);
                            }

                            $success_student_id_card[] = $student_id_card;
                            $successNum++;
                        }
                    }

                    $error[] = '成功导入' . $successNum . '条数据';

                    $msg['success_num'] = $successNum;
                    $msg['repeat_num'] = count($repeat);

                    Cache::set('repeat', $repeat);
                    Cache::set('importBatchCardIds_'.$this->userInfo['manage_id'], $success_student_id_card);
                    Cache::set('importErrorBusiness_'.$this->userInfo['manage_id'], $error);

                    $res = [
                        'code' => 1,
                        'data' => $msg
                    ];
                }
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        } else{}
    }


    /**
     * 导入摸排学生名单
     * @return Json
     */
    public function actImportFollowCheck(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                set_time_limit(0);
                $file_size = $_FILES['file']['size'];
                if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                    throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
                }
                //限制上传表格类型
                $fileExtendName = substr(strrchr($_FILES['file']["name"], '.'), 1);

                if ( $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    if ($fileExtendName == 'xls') {
                        $objReader = IOFactory::createReader('Xls');
                    } else {
                        $objReader = IOFactory::createReader('Xlsx');
                    }

                    $filename = $_FILES['file']['tmp_name'];
                    $objPHPExcel = $objReader->load($filename);
                    $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                    $highestRow = $sheet->getHighestRow();       // 取得总行数
                    //$highestColumn = $sheet->getHighestColumn();   // 取得总列数

                    //导入模板检测
                    $check_res = $this->checkFollowCheckTemplate($objPHPExcel);
                    if($check_res['code'] == 0){
                        throw new \Exception($check_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的六年级学生信息。
                    $data = [];
                    $repeat = [];

                    //循环读取excel表格，整合成数组。model
                    $hasChildIdCardData = Db::name('UserChild')->where('idcard', '<>', '')
                        ->where('deleted',0)->column('id','idcard');

                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    for ($j = 3; $j <= $highestRow; $j++) {
                        $student_name = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();
                        $school_type_name = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();
                        $school_attr_name = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();
                        $result_school_name = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();

                        //if($student_name == '' && $student_id_card == '' && $school_type_name == '' && $school_attr_name == '' && $result_school_name == '' ) continue;

                        $tmp[$j - 3] = [
                            'student_name' => trim($student_name,"\0\t\n\x0B\r "),
                            'student_id_card' => strtoupper(trim($student_id_card,"\0\t\n\x0B\r ")),
                            'student_id_card_original' => trim($student_id_card,"\0\t\n\x0B\r "),
                            'school_type_name' => trim($school_type_name,"\0\t\n\x0B\r "),
                            'school_attr_name' => trim($school_attr_name,"\0\t\n\x0B\r "),
                            'result_school_name' => trim($result_school_name,"\0\t\n\x0B\r "),
                        ];
                        array_push($data, $tmp[$j - 3]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的身份证号、学籍号
                    $student_id_card_data = array_column($data,'student_id_card');
                    $student_id_card_data = array_filter($student_id_card_data);
                    $repeat_data_student = array_count_values($student_id_card_data);

                    $successNum = 0;
                    $apply_data = [];
                    $school_id_name = [];
                    $admission_school = [];
                    $flow_school_ids = [];
                    $success_student_id_card = [];
                    foreach ($data as $key=>$item) {
                        $row = $key + 3;
                        if($item['student_name'] == ''){
                            $error[] = '第' . $row . '行学生姓名为空';
                            continue;
                        }
                        $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                        if (preg_match($preg, $item['student_name']) == 0){
                            $error[] = '第' . $row . '行学生姓名只能为汉字';
                            continue;
                        }
                        if($item['student_id_card'] == ''){
                            $error[] = '第' . $row . '行学生身份证号为空';
                            continue;
                        }

                        if($item['school_type_name'] == ''){
                            $error[] = '第' . $row . '行学校类型为空';
                            continue;
                        }
                        if($item['school_attr_name'] == ''){
                            $error[] = '第' . $row . '行学校性质为空';
                            continue;
                        }
                        if($item['result_school_name'] == ''){
                            $error[] = '第' . $row . '行录取学校不能为空';
                            continue;
                        }

                        $school = Db::name('sys_school')->where([['school_name', '=', $item['result_school_name']],
                            ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行学校名称为【' . $item['result_school_name'] . '】不存在';
                            continue;
                        }
                        if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                            $error[] = '第' . $row . '行学校名称为【' . $item['result_school_name'] . '】无权限管理';
                            continue;
                        }
                        if($school['school_type'] == 1){
                            if($item['school_type_name'] != '小学'){
                                $error[] = '第' . $row . '行学校类型错误';
                                continue;
                            }
                        }
                        if($school['school_type'] == 2){
                            if($item['school_type_name'] != '初中'){
                                $error[] = '第' . $row . '行学校类型错误';
                                continue;
                            }
                        }
                        if($school['school_attr'] == 1){
                            if($item['school_attr_name'] != '公办'){
                                $error[] = '第' . $row . '行学校性质错误';
                                continue;
                            }
                        }
                        if($school['school_attr'] == 2){
                            $error[] = '第' . $row . '行学校性质错误,只能公办导入';
                            continue;
                        }

                        $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                        if (preg_match($preg, $item['student_id_card']) == 0){
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】不正确';
                            continue;
                        }
                        if($repeat_data_student[$item['student_id_card']] > 1) {
                            $error[] = '第' . $row . '行学生身份证号为【' . $item['student_id_card_original'] . '】重复';
                            continue;
                        }

                        if( !in_array($item['student_id_card'], array_keys($hasChildIdCardData)) ){
                            $error[] = '第' . $row . '行没有查找到相关学生信息【' . $item['student_id_card_original'] . '】';
                            continue;
                        }
                        $apply = Db::name('user_apply')->where('child_id',$hasChildIdCardData[$item['student_id_card']])
                            ->where('deleted',0)
                            //->where('school_attr',1)
                            //->where('region_id',$this->userInfo['region_id'])
                            ->where('voided',0)
                            ->find();
                        if(!$apply){
                            $error[] = '第' . $row . '行学生没有查找到申报信息【' . $item['student_id_card_original'] . '】';
                            continue;
                        }

                        if($apply['region_id'] != $school['region_id'] ){
                            $error[] = '第' . $row . '行学生【' . $item['student_name'] . '】填报申请区县与录取学校区县不相符';
                            continue;
                        }
                        if($apply['school_type'] != $school['school_type']){
                            $error[] = '第' . $row . '行学生【' . $item['student_name'] . '】填报申请学校类型与录取学校类型不相符';
                            continue;
                        }
                        if($apply['school_attr'] != $school['school_attr']){
                            $error[] = '第' . $row . '行学生【' . $item['student_name'] . '】填报申请学校性质为民办';
                            continue;
                        }
                        if($apply['prepared'] == 1){
                            $error[] = '第' . $row . '行学生已经被预录取【' . $item['student_id_card_original'] . '】';
                            continue;
                        }

                        if($apply['resulted'] == 1){
                            $error[] = '第' . $row . '行学生已经被录取【' . $item['student_id_card_original'] . '】';
                            continue;
                        }

                        $apply_data[$key]['public_school_id'] = $school['id'];
                        $apply_data[$key]['result_school_id'] = $school['id'];
                        $apply_data[$key]['apply_status'] = 3;//公办录取
                        $apply_data[$key]['resulted'] = 1;//公办录取
                        $apply_data[$key]['prepared'] = 1;
                        $apply_data[$key]['admission_type'] = 8;
                        $apply_data[$key]['id'] = $apply['id'];
                        $apply_data[$key]['student_id_card'] = $item['student_id_card'];

                        if(isset($admission_school[$school['id']])){
                            $admission_school[$school['id']] += 1;
                        }else{
                            $admission_school[$school['id']] = 1;
                        }
                        $school_id_name[$school['id']] = $school['school_name'];

                        if(!in_array($school['id'], $flow_school_ids)) {
                            $flow_school_ids[] = $school['id'];
                        }

                        /*$result = (new model())->editData($apply_data);
                        if($result['code'] == 0){
                            $error[] = '第' . $row . '行数据更新失败【' . $item['student_id_card_original'] . '】';
                            continue;
                        }

                        //中心学校录取统计
                        $result = $this->getMiddleAdmissionStatistics($school['id']);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }

                        $success_student_id_card[] = $item['student_id_card'];
                        $successNum++;*/
                    }

                    //学校录取数量
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['a.voided', '=', 0];//没有作废
                    $where[] = ['a.school_attr', '=', 1];//公办
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.resulted', '=', 1];//录取
                    $where[] = ['a.offlined', '=', 0];//线下录取
                    $where[] = ['a.result_school_id', 'in', $flow_school_ids];//录取学校
                    $used = Db::name('UserApply')->alias('a')->group('result_school_id')
                        ->where($where)->column('COUNT(*)', 'result_school_id');

                    //公办审批学位数量
                    $degree = Db::name('PlanApply')
                        ->where('school_id', '>', 0)->where('status', 1)
                        ->where('deleted', 0)->where('school_id', 'in', $flow_school_ids)
                        ->group('school_id')->column('SUM(spare_total)', 'school_id');

                    foreach ($flow_school_ids as $k => $school_id){
                        $degree_count = isset($degree[$school_id]) ? $degree[$school_id] : 0;
                        $used_count = isset($used[$school_id]) ? $used[$school_id] : 0;
                        $admission_count = isset($admission_school[$school_id]) ? $admission_school[$school_id] : 0;

                        if( ($used_count + $admission_count) > $degree_count){
                            $error[] = '学校【' . $school_id_name[$school_id] . '】学位数量不足！';
                            unset($flow_school_ids[$k]);
                        }
                    }

                    foreach ($apply_data as $k => $item){
                        if(in_array($item['result_school_id'], $flow_school_ids)){
                            $student_id_card = $item['student_id_card'];
                            unset($item['student_id_card']);

                            if(isset($item['id'])){
                                $result = (new model())->editData($item);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }

                            }

                            //审核日志
                            $log = [];
                            $log['user_apply_id'] = $item['id'];
                            $log['admin_id'] = $this->userInfo['manage_id'];
                            $log['school_id'] = $item['result_school_id'];
                            $log['education'] = 1;
                            $log['remark'] = '业务办理-摸排导入-' . $school_id_name[$item['result_school_id']];
                            $log['status'] = 1;
                            Db::name('UserApplyAuditLog')->save($log);

                            //中心学校录取统计
                            $result = $this->getMiddleAdmissionStatistics($item['result_school_id']);
                            if ($result['code'] == 0) {
                                throw new \Exception($result['msg']);
                            }

                            $success_student_id_card[] = $student_id_card;
                            $successNum++;
                        }
                    }

                    $error[] = '成功导入' . $successNum . '条数据';

                    $msg['success_num'] = $successNum;
                    $msg['repeat_num'] = count($repeat);

                    Cache::set('repeat', $repeat);
                    Cache::set('importErrorBusiness_'.$this->userInfo['manage_id'], $error);
                    Cache::set('importBatchCardIds_'.$this->userInfo['manage_id'], $success_student_id_card);

                    $res = [
                        'code' => 1,
                        'data' => $msg
                    ];
                }
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


    /**
     * 特殊情况处理【录取方式算线下】
     * @return Json
     */
    public function actSpecial(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                set_time_limit(0);
                $file_size = $_FILES['file']['size'];
                if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                    throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
                }
                //限制上传表格类型
                $fileExtendName = substr(strrchr($_FILES['file']["name"], '.'), 1);

                /*if ($this->userInfo['grade_id'] != $this->area_grade) {
                    throw new \Exception('该功能只能区级管理员操作！');
                }
                if ( isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1 ) {
                    $region_id = $this->userInfo['region_id'];
                }else{
                    throw new \Exception('区级管理员所属区县ID设置错误！');
                }*/

                if ( $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    if ($fileExtendName == 'xls') {
                        $objReader = IOFactory::createReader('Xls');
                    } else {
                        $objReader = IOFactory::createReader('Xlsx');
                    }

                    $filename = $_FILES['file']['tmp_name'];
                    $objPHPExcel = $objReader->load($filename);
                    $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                    $highestRow = $sheet->getHighestRow();       // 取得总行数
                    //$highestColumn = $sheet->getHighestColumn();   // 取得总列数

                    //导入模板检测
                    $check_res = $this->checkSpecialTemplate($objPHPExcel);
                    if($check_res['code'] == 0){
                        throw new \Exception($check_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的学生信息。
                    $data = [];
                    $repeat = [];

                    //循环读取excel表格，整合成数组。model
                    $hasChildIdCardData = Db::name('UserChild')->where('idcard', '<>', '')
                        ->where('deleted',0)->column('id','idcard');
                    $hasFamilyIdCardData = Db::name('UserFamily')->where('idcard', '<>', '')
                        ->where('deleted',0)->column('id','idcard');

                    //获取角色所管理的学校ID
                    $school_res = $this->getSchoolIdsByRole();
                    if($school_res['code'] == 0){
                        throw new \Exception($school_res['msg']);
                    }

                    for ($j = 3; $j <= $highestRow; $j++) {
                        $mobile = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                        $student_name = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();
                        $student_card_type = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();
                        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();
                        $guardian_name = $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue();
                        $guardian_card_type = $objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue();
                        $guardian_id_card = $objPHPExcel->getActiveSheet()->getCell("G" . $j)->getValue();
                        $house_type_name = $objPHPExcel->getActiveSheet()->getCell("H" . $j)->getValue();
                        $house_code = $objPHPExcel->getActiveSheet()->getCell("I" . $j)->getValue();
                        $family_name = $objPHPExcel->getActiveSheet()->getCell("J" . $j)->getValue();
                        $house_address = $objPHPExcel->getActiveSheet()->getCell("K" . $j)->getValue();
                        $region_name = $objPHPExcel->getActiveSheet()->getCell("L" . $j)->getValue();
                        $school_type_name = $objPHPExcel->getActiveSheet()->getCell("M" . $j)->getValue();
                        $school_attr_name = $objPHPExcel->getActiveSheet()->getCell("N" . $j)->getValue();
                        $result_school_name = $objPHPExcel->getActiveSheet()->getCell("O" . $j)->getValue();

                        //if($student_name == '' && $student_id_card == '' && $guardian_name == '' && $guardian_id_card == '' ) continue;

                        $tmp[$j - 3] = [
                            'mobile' => trim($mobile,"\0\t\n\x0B\r "),
                            'student_name' => trim($student_name,"\0\t\n\x0B\r "),
                            'student_id_card' => strtoupper(trim($student_id_card,"\0\t\n\x0B\r ")),
                            'student_id_card_original' => trim($student_id_card,"\0\t\n\x0B\r "),
                            'guardian_name' => trim($guardian_name,"\0\t\n\x0B\r "),
                            'guardian_id_card' => strtoupper(trim($guardian_id_card,"\0\t\n\x0B\r ")),
                            'guardian_id_card_original' => trim($guardian_id_card,"\0\t\n\x0B\r "),
                            'house_type_name' => trim($house_type_name,"\0\t\n\x0B\r "),
                            'family_name' => trim($family_name,"\0\t\n\x0B\r "),
                            'house_address' => strtoupper(trim($house_address,"\0\t\n\x0B\r ")),
                            'region_name' => trim($region_name,"\0\t\n\x0B\r "),
                            'school_type_name' => trim($school_type_name,"\0\t\n\x0B\r "),
                            'school_attr_name' => trim($school_attr_name,"\0\t\n\x0B\r "),
                            'result_school_name' => trim($result_school_name,"\0\t\n\x0B\r "),
                            'student_card_type' => trim($student_card_type,"\0\t\n\x0B\r "),
                            'guardian_card_type' => trim($guardian_card_type,"\0\t\n\x0B\r "),
                            'house_code' => trim($house_code,"\0\t\n\x0B\r "),
                        ];
                        array_push($data, $tmp[$j - 3]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的身份证号、学籍号
                    $student_id_card_data = array_column($data,'student_id_card');
                    $student_id_card_data = array_filter($student_id_card_data);
                    $repeat_data_student = array_count_values($student_id_card_data);

//                    $guardian_id_card_data = array_column($data,'guardian_id_card');
//                    $guardian_id_card_data = array_filter($guardian_id_card_data);
//                    $repeat_data_guardian = array_count_values($guardian_id_card_data);

                    $region = Cache::get('region');
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');

                    $successNum = 0;
                    $apply_data = [];
                    $success_student_id_card = [];
                    $school_id_name = [];
                    $admission_school = [];
                    $special_school_ids = [];
                    foreach ($data as $key=>$item) {
                        $row = $key + 3;
                        if($item['mobile'] == ''){
                            $error[] = '第' . $row . '行联系手机号码为空';
                            continue;
                        }
                        $preg = '/^1[3-9]\d{9}$/';
                        if (preg_match($preg, $item['mobile']) == 0){
                            $error[] = '第' . $row . '行联系手机号码格式错误';
                            continue;
                        }
                        if($item['student_name'] == ''){
                            $error[] = '第' . $row . '行学生姓名为空';
                            continue;
                        }
                        $preg = '/^[\x{4e00}-\x{9fa5}\x{9fa6}-\x{9fef}\x{3400}-\x{4db5}\x{20000}-\x{2ebe0}]+$/u';
                        if (preg_match($preg, $item['student_name']) == 0){
                            $error[] = '第' . $row . '行学生姓名只能为汉字';
                            continue;
                        }
                        if($item['guardian_name'] == ''){
                            $error[] = '第' . $row . '行监护人姓名为空';
                            continue;
                        }
                        if (preg_match($preg, $item['guardian_name']) == 0){
                            $error[] = '第' . $row . '行监护人姓名只能为汉字';
                            continue;
                        }
                        if($item['house_type_name'] === ''){
                            $error[] = '第' . $row . '行房产类型为空';
                            continue;
                        }
                        $house_type = $this->getHouseType($item['house_type_name']);
                        if($house_type == 0){
                            $error[] = '第' . $row . '行房产类型错误';
                            continue;
                        }
                        if($item['family_name'] == ''){
                            $error[] = '第' . $row . '行房产所有人不能为空';
                            continue;
                        }
//                        if($item['guardian_name'] !=$item['family_name'] ){
//                            $error[] = '第' . $row . '行房产所有人和监护人不一致';
//                            continue;
//                        }
                        if($item['house_address'] == ''){
                            $error[] = '第' . $row . '行房产地址不能为空';
                            continue;
                        }
                        if($item['school_type_name'] == ''){
                            $error[] = '第' . $row . '行学校类型不能为空';
                            continue;
                        }
                        if($item['school_attr_name'] == ''){
                            $error[] = '第' . $row . '行学校性质不能为空';
                            continue;
                        }
                        if($item['school_attr_name'] == '民办' && $item['result_school_name'] == ''){
                            $error[] = '第' . $row . '行学校性质为民办，就读学校不能为空';
                            continue;
                        }
                        if($item['school_type_name'] != '小学' && $item['school_type_name'] != '初中'){
                            $error[] = '第' . $row . '行学校类型只能为【小学】、【初中】';
                            continue;
                        }
                        if($item['school_attr_name'] != '公办' && $item['school_attr_name'] != '民办'){
                            $error[] = '第' . $row . '行学校类型只能为【公办】、【民办】';
                            continue;
                        }
                        if($item['region_name'] == ''){
                            $error[] = '第' . $row . '申请区县不能为空';
                            continue;
                        }
                        $region_id = 0;
                        $regionData = filter_value_one($region, 'region_name', $item['region_name']);
                        if (count($regionData) > 0){
                            $region_id = $regionData['id'];
                        }
                        if(!$region_id){
                            $error[] = '第' . $row . '申请区县填写错误';
                            continue;
                        }
                        if(!in_array($region_id, $region_ids)){
                            $error[] = '第' . $row . '申请区县【' . $item['region_name'] . '】无权限管理';
                            continue;
                        }

                        $school_type = 0;
                        if($item['school_type_name'] == '小学'){
                            $school_type = 1;
                        }
                        if($item['school_type_name'] == '初中'){
                            $school_type = 2;
                        }
                        $school_attr = 0;
                        if($item['school_attr_name'] == '公办'){
                            $school_attr = 1;
                        }
                        if($item['school_attr_name'] == '民办'){
                            $school_attr = 2;
                        }

                        $school_id = 0;
                        if($item['result_school_name'] != ''){
                            $school = Db::name('SysSchool')->where([['school_name', '=', $item['result_school_name']],
                                ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
                            if(!$school){
                                $error[] = '第' . $row . '行就读学校名称为【' . $item['result_school_name'] . '】不存在';
                                continue;
                            }
                            $school_id = $school['id'];
                            if($school['onlined'] == 0){
                                $error[] = '第' . $row . '行就读学校名称为【' . $item['result_school_name'] . '】招生方式为【线下】';
                                continue;
                            }
                            if($school_res['bounded'] && !in_array($school['id'], $school_res['school_ids']) ){
                                $error[] = '第' . $row . '行就读学校名称为【' . $item['result_school_name'] . '】无权限管理';
                                continue;
                            }
                            if($school['school_type'] != $school_type){
                                $error[] = '第' . $row . '行学校类型错误';
                                continue;
                            }
                            if($school['school_attr'] != $school_attr){
                                $error[] = '第' . $row . '行学校性质错误';
                                continue;
                            }
                            if($school['region_id'] != $region_id){
                                $error[] = '第' . $row . '行学校所属区县与申请区县不一致';
                                continue;
                            }
                            if($school['yaohao_end_status'] == 1){
                                $error[] = '第' . $row . '行就读学校名称为【' . $item['result_school_name'] . '】已经摇号结束';
                                continue;
                            }
                            $region_id = $school['region_id'];

                        }

                        if($item['student_id_card'] && $repeat_data_student[$item['student_id_card']] > 1) {
                            $error[] = '第' . $row . '行学生证件号为【' . $item['student_id_card_original'] . '】重复';
                            continue;
                        }

                        if( $item['student_id_card'] !== '' && in_array($item['student_id_card'], array_keys($hasChildIdCardData)) ){
                            $child_id = $hasChildIdCardData[$item['student_id_card']];
                            $where = [];
                            $where[] = ['deleted', '=', 0];
                            $where[] = ['voided', '=', 0];//没有作废
                            $where[] = ['child_id', '=', $child_id];
                            $apply = Db::name('UserApply')->where($where)->find();
                            if($apply){
                                $error[] = '第' . $row . '行学生证件号为【' . $item['student_id_card_original'] . '】申请信息已存在';
                                continue;
                            }
                            //$error[] = '第' . $row . '行学生证件号为【' . $item['student_id_card_original'] . '】已存在';
                            //continue;
                        }else{
                            $detail_where = [];
                            $detail_where[] = ['deleted', '=', 0];
                            $detail_where[] = ['child_name', '=', $item['student_name'] ];
                            $detail_where[] = ['mobile', '=', $item['mobile'] ];
                            $child_id = Db::name('UserApplyDetail')->where($detail_where)->value('child_id');

                            if($child_id){
                                $where = [];
                                $where[] = ['deleted', '=', 0];
                                $where[] = ['voided', '=', 0];//没有作废
                                $where[] = ['child_id', '=', $child_id];
                                $apply = Db::name('UserApply')->where($where)->find();
                                if($apply){
                                    $error[] = '第' . $row . '行学生证件号为【' . $item['student_id_card_original'] . '】申请信息已存在';
                                    continue;
                                }
                                //$error[] = '第' . $row . '行学生姓名为【' . $item['student_name'] . '】已存在';
                                //continue;
                            }else {
                                $child = [];
                                $child['mobile'] = $item['mobile'];
                                $child['real_name'] = $item['student_name'];
                                $child['idcard'] = $item['student_id_card'];
                                $result = (new UserChild())->addData($child, 1);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                                $child_id = $result['insert_id'];
                            }
                        }

                        /*if($repeat_data_guardian[$item['guardian_id_card']] > 1) {
                            $error[] = '第' . $row . '行监护人身份证号为【' . $item['guardian_id_card_original'] . '】重复';
                            continue;
                        }*/
                        if( in_array($item['guardian_id_card'], array_keys($hasFamilyIdCardData)) ){
                            //$error[] = '第' . $row . '行监护人身份证号为【' . $item['guardian_id_card_original'] . '】已存在';
                            //continue;
                            $family_id = $hasFamilyIdCardData[$item['guardian_id_card']];
                        }else{
                            $family = [];
                            $family['parent_name'] = $item['guardian_name'];
                            $family['idcard'] = $item['guardian_id_card'];
                            $result = (new UserFamily())->addData($family, 1);
                            if($result['code'] == 0){
                                $error[] = $result['msg'];
                                continue;
                            }
                            $family_id = $result['insert_id'];
                        }
                        //房产信息
                        $house = [];
                        $house['family_id'] = $family_id;
                        $house['house_type'] = $house_type;
                        $house['code'] = $item['house_code'];
                        $house['house_address'] = $item['house_address'];
                        $result = (new UserHouse())->addData($house, 1);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }
                        $house_id = $result['insert_id'];

                        $apply_data[$key] = [
                            'enrol_year' => date('Y'),
                            'region_id' => $region_id,
                            'school_attr' => $school_attr,
                            'school_type' => $school_type,
                            'child_id' => $child_id,
                            'family_id' => $family_id,
                            'house_id' => $house_id,
                            //'prepared' => 1,
                            //'resulted' => 1,
                            //'offlined' => 1,
                            'specialed' => 1,
                            //'admission_type' => 5,//录取方式 调剂
                            //'result_school_id' => $school['id'],
                            'mobile' => $item['mobile'],
                            'child_name' => $item['student_name'],
                            'child_idcard' => $item['student_id_card'],
                            'house_type' => $house_type,
                            'school_id' => $school_id,
                        ];
                        //公办
                        if($school_attr == 1){
                            if($school_id > 0 ){
                                $apply_data[$key]['prepared'] = 1;
                                $apply_data[$key]['public_school_id'] = $school_id;
                                $apply_data[$key]['apply_status'] = 2;//公办预录取
                                $apply_data[$key]['admission_type'] = 5;//录取方式 指派
                            }
                        }
                        //民办
                        if($school_attr == 2){
                            $apply_data[$key]['prepared'] = 1;
                            $apply_data[$key]['resulted'] = 1;
                            $apply_data[$key]['apply_status'] = 5;//民办录取
                            $apply_data[$key]['apply_school_id'] = $school_id;
                            $apply_data[$key]['result_school_id'] = $school_id;
                            $apply_data[$key]['admission_type'] = 5;//录取方式 调剂
                        }

                        if($school_id > 0) {
                            //民办判断学位
                            if($school_attr == 2) {
                                if (isset($admission_school[$school_id])) {
                                    $admission_school[$school_id] += 1;
                                } else {
                                    $admission_school[$school_id] = 1;
                                }

                                $school_id_name[$school_id] = $school['school_name'];

                                if (!in_array($school_id, $special_school_ids)) {
                                    $special_school_ids[] = $school_id;
                                }
                            }
                        }

//                        $result = (new model())->addData($apply_data, 1);
//                        if($result['code'] == 0){
//                            $error[] = $result['msg'];
//                            continue;
//                        }
//                        $user_apply_id = $result['insert_id'];
//
//                        //统计信息
//                        $detail = [];
//                        $detail['user_apply_id'] = $user_apply_id;
//                        $detail['child_id'] = $child_id;
//                        $detail['mobile'] = $item['mobile'];
//                        $detail['child_name'] = $item['student_name'];
//                        $detail['child_idcard'] = $item['student_id_card'];
//                        $detail['school_attr'] = $school_attr;
//                        $detail['school_type'] = $school_type;
//                        $detail['house_type'] = $house_type;
//                        $result = (new UserApplyDetail())->addData($detail);
//                        if($result['code'] == 0){
//                            $error[] = $result['msg'];
//                            continue;
//                        }
//
//                        //比对状态
//                        $status = [];
//                        $status['user_apply_id'] = $user_apply_id;
//                        $result = (new UserApplyStatus())->addData($status);
//                        if($result['code'] == 0){
//                            $error[] = $result['msg'];
//                            continue;
//                        }
//
//                        if($school_id > 0) {
//                            //中心学校录取统计
//                            $result = $this->getMiddleAdmissionStatistics($school_id);
//                            if ($result['code'] == 0) {
//                                throw new \Exception($result['msg']);
//                            }
//
//                            //线下录取统计
//                            $result = $this->getOfflineAdmissionStatistics($school_id);
//                            if ($result['code'] == 0) {
//                                throw new \Exception($result['msg']);
//                            }
//                        }
//
//                        $success_student_id_card[] = $item['student_id_card'];
//                        $successNum++;
                    }

                    //学校录取数量
                    $where = [];
                    $where[] = ['a.deleted', '=', 0];
                    $where[] = ['a.voided', '=', 0];//没有作废
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.resulted', '=', 1];//录取
                    $where[] = ['a.offlined', '=', 0];//线下录取
                    $where[] = ['a.school_attr', '=', 2];//民办
                    $where[] = ['a.result_school_id', 'in', $special_school_ids];//录取学校
                    $used = Db::name('UserApply')->alias('a')->group('result_school_id')
                        ->where($where)->column('COUNT(*)', 'result_school_id');

                    //审批学位数量
                    $degree = Db::name('PlanApply')
                        ->where('school_id', '>', 0)->where('status', 1)
                        ->where('deleted', 0)->where('school_id', 'in', $special_school_ids)
                        ->group('school_id')->column('SUM(spare_total)', 'school_id');

                    foreach ($special_school_ids as $k => $school_id){
                        $degree_count = isset($degree[$school_id]) ? $degree[$school_id] : 0;
                        $used_count = isset($used[$school_id]) ? $used[$school_id] : 0;
                        $admission_count = isset($admission_school[$school_id]) ? $admission_school[$school_id] : 0;

                        if( ($used_count + $admission_count) > $degree_count){
                            $error[] = '学校【' . $school_id_name[$school_id] . '】学位数量不足！';
                            unset($special_school_ids[$k]);
                        }
                    }

                    foreach ($apply_data as $k => $v){
                        if($v['school_id'] == 0 || ($v['school_attr'] == 1 && $v['public_school_id'] > 0 ) ||
                            (in_array($v['apply_school_id'], $special_school_ids) && $v['school_attr'] == 2 ) ) {
                            $school_id = $v['school_id'];
                            $student_id_card = $v['child_idcard'];
                            $student_name = $v['child_name'];
                            $mobile = $v['mobile'];
                            $house_type = $v['house_type'];
                            $special_card = $v['mobile'] . "_" . $v['child_id'];

                            unset($v['school_id']);
                            unset($v['child_idcard']);
                            unset($v['child_name']);
                            unset($v['mobile']);
                            unset($v['house_type']);

                            $result = (new model())->addData($v, 1);
                            if ($result['code'] == 0) {
                                $error[] = $result['msg'];
                                continue;
                            }
                            $user_apply_id = $result['insert_id'];

                            $detailInfo = Db::name('UserApplyDetail')->where('child_id', $v['child_id'])
                                ->where('deleted', 0)->find();
                            if($detailInfo){
                                $result = (new UserApplyDetail())->editData(['deleted' => 1], ['child_id' => $v['child_id'] ]);
                                if ($result['code'] == 0) {
                                    $error[] = $result['msg'];
                                    continue;
                                }
                            }
                            //统计信息
                            $detail = [];
                            $detail['user_apply_id'] = $user_apply_id;
                            $detail['child_id'] = $v['child_id'];
                            $detail['mobile'] = $mobile;
                            $detail['child_name'] = $student_name;
                            $detail['child_idcard'] = $student_id_card;
                            $detail['school_attr'] = $v['school_attr'];
                            $detail['school_type'] = $v['school_type'];
                            $detail['house_type'] = $house_type;
                            $result = (new UserApplyDetail())->addData($detail);
                            if ($result['code'] == 0) {
                                $error[] = $result['msg'];
                                continue;
                            }

                            //比对状态
                            $status = [];
                            $status['user_apply_id'] = $user_apply_id;
                            $result = (new UserApplyStatus())->addData($status);
                            if ($result['code'] == 0) {
                                $error[] = $result['msg'];
                                continue;
                            }

                            if ($school_id > 0) {
                                //中心学校录取统计
                                $result = $this->getMiddleAdmissionStatistics($school_id);
                                if ($result['code'] == 0) {
                                    throw new \Exception($result['msg']);
                                }

                                //线下录取统计
                                $result = $this->getOfflineAdmissionStatistics($school_id);
                                if ($result['code'] == 0) {
                                    throw new \Exception($result['msg']);
                                }
                            }

                            if($student_id_card == ''){
                                $student_id_card = $special_card;
                            }
                            $success_student_id_card[] = $student_id_card;
                            $successNum++;
                        }
                    }

                    $error[] = '成功导入' . $successNum . '条数据';

                    $msg['success_num'] = $successNum;
                    $msg['repeat_num'] = count($repeat);

                    Cache::set('repeat', $repeat);
                    Cache::set('importErrorBusiness_'.$this->userInfo['manage_id'], $error);
                    Cache::set('importBatchCardIds_'.$this->userInfo['manage_id'], $success_student_id_card);

                    $res = [
                        'code' => 1,
                        'data' => $msg
                    ];
                }
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }else{}
    }

    /**
     * 导入错误信息
     * @return Json
     */
    public function getImportError(){
        if ($this->request->isPost()) {
            try {
                $errData = Cache::get('importErrorBusiness_'.$this->userInfo['manage_id']);
                if(empty($errData)){
                    $errData = [];
                }
                $data['data'] = $errData;
                $data['total'] = count($errData) - 1;
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
     * 开启退费
     * @return Json
     */
    public function actRefund(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'ids',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $ids = $this->request->param('ids');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选录取的学生');
                }
                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 1];
                $voided_count = Db::name('UserApply')->where($where)->count();
                if($voided_count > 0 ){
                    throw new \Exception('选择的学生信息已作废！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['school_attr', '=', 1];
                $public_count = Db::name('UserApply')->where($where)->count();
                if($public_count > 0 ){
                    throw new \Exception('选择的学生信息有公办申请！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['paid', '=', 0];
                $pay_count = Db::name('UserApply')->where($where)->count();
                if($pay_count > 0 ){
                    throw new \Exception('选择的学生信息有未缴费！');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['paid', '=', 1];
                $where[] = ['school_attr', '=', 2];

                $update['open_refund'] = 1;
                $update['refund_status'] = 0;
                $result = (new model())->editData($update, $where);
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

    /**
     * 民办自动录取
     * @return Json
     */
    public function actEnroll(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选录取的学生');
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];

                $update['signed'] = 1;
                $update['signe_time'] = date('Y-m-d H:i:s', time());

                $result = (new model())->editData($update, $where);
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
            case 8:
                $name = '摸排';
                break;
        }
        return $name;
    }

    //民办摇号导入模板检测
    private function checkCivilTemplate($objPHPExcel): array
    {
        $title = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($title != '民办学校摇号导入模板') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        $student_name = $objPHPExcel->getActiveSheet()->getCell("A2")->getValue();
        if($student_name != '学生姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("B2")->getValue();
        if($student_id_card != '身份证号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_type_name = $objPHPExcel->getActiveSheet()->getCell("C2")->getValue();
        if($school_type_name != '学校类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_attr_name = $objPHPExcel->getActiveSheet()->getCell("D2")->getValue();
        if($school_attr_name != '学校性质') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_name = $objPHPExcel->getActiveSheet()->getCell("E2")->getValue();
        if($school_name != '录取学校') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

    //政策生导入模板检测
    private function checkPolicyTemplate($objPHPExcel): array
    {
        $title = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($title != '政策生导入模板') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        $student_name = $objPHPExcel->getActiveSheet()->getCell("A2")->getValue();
        if($student_name != '姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("B2")->getValue();
        if($student_id_card != '身份证号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
//        $mobile = $objPHPExcel->getActiveSheet()->getCell("C2")->getValue();
//        if($mobile != '联系手机号') {
//            return ['code' => 0, 'msg' => '导入模板错误！'];
//        }
//        $school_type_name = $objPHPExcel->getActiveSheet()->getCell("D2")->getValue();
//        if($school_type_name != '学校类型') {
//            return ['code' => 0, 'msg' => '导入模板错误！'];
//        }
//        $school_attr_name = $objPHPExcel->getActiveSheet()->getCell("E2")->getValue();
//        if($school_attr_name != '学校性质') {
//            return ['code' => 0, 'msg' => '导入模板错误！'];
//        }
        $school_name = $objPHPExcel->getActiveSheet()->getCell("C2")->getValue();
        if($school_name != '学校名称') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $remark = $objPHPExcel->getActiveSheet()->getCell("D2")->getValue();
        if($remark != '属性') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }


    //模板生导入模板检测
    private function checkFollowCheckTemplate($objPHPExcel): array
    {
        $title = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($title != '学生摸排导入模板') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        $student_name = $objPHPExcel->getActiveSheet()->getCell("A2")->getValue();
        if($student_name != '学生姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("B2")->getValue();
        if($student_id_card != '身份证号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_type_name = $objPHPExcel->getActiveSheet()->getCell("C2")->getValue();
        if($school_type_name != '学校类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_attr_name = $objPHPExcel->getActiveSheet()->getCell("D2")->getValue();
        if($school_attr_name != '学校性质') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_name = $objPHPExcel->getActiveSheet()->getCell("E2")->getValue();
        if($school_name != '录取学校') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

    //特殊情况处理导入模板
    private function checkSpecialTemplate($objPHPExcel): array
    {
        $title = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($title != '特殊情况学生名单导入') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        $mobile = $objPHPExcel->getActiveSheet()->getCell("A2")->getValue();
        if($mobile != '联系手机号') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_name = $objPHPExcel->getActiveSheet()->getCell("B2")->getValue();
        if($student_name != '学生姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_card_type = $objPHPExcel->getActiveSheet()->getCell("C2")->getValue();
        if($student_card_type != '学生证件类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $student_id_card = $objPHPExcel->getActiveSheet()->getCell("D2")->getValue();
        if($student_id_card != '学生证件号码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $family_name = $objPHPExcel->getActiveSheet()->getCell("E2")->getValue();
        if($family_name != '监护人姓名') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $family_card_type = $objPHPExcel->getActiveSheet()->getCell("F2")->getValue();
        if($family_card_type != '监护人证件类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $family_id_card = $objPHPExcel->getActiveSheet()->getCell("G2")->getValue();
        if($family_id_card != '监护人证件号码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $house_type = $objPHPExcel->getActiveSheet()->getCell("H2")->getValue();
        if($house_type != '房产类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $house_code = $objPHPExcel->getActiveSheet()->getCell("I2")->getValue();
        if($house_code != '房产证件号码') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $house_owner = $objPHPExcel->getActiveSheet()->getCell("J2")->getValue();
        if($house_owner != '房产所有人') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $house_address = $objPHPExcel->getActiveSheet()->getCell("K2")->getValue();
        if($house_address != '房产地址') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_type_name = $objPHPExcel->getActiveSheet()->getCell("L2")->getValue();
        if($school_type_name != '申请区县') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_type_name = $objPHPExcel->getActiveSheet()->getCell("M2")->getValue();
        if($school_type_name != '申请学校类型') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_attr_name = $objPHPExcel->getActiveSheet()->getCell("N2")->getValue();
        if($school_attr_name != '申请学校性质') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $result_school = $objPHPExcel->getActiveSheet()->getCell("O2")->getValue();
        if($result_school != '就读学校（学校名称需要与平台名称一致）') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

    private function getHouseType($name): int
    {
        $house_type = 0;
        if($name == '产权房'){
            $house_type = 1;
        }
        if($name == '租房'){
            $house_type = 2;
        }
        if($name == '自建房'){
            $house_type = 3;
        }
        if($name == '置换房'){
            $house_type = 4;
        }
        if($name == '公租房'){
            $house_type = 5;
        }
        if($name == '三代同堂'){
            $house_type = 6;
        }
        return $house_type;
    }

    //民办摇号落选自动开启补录
    private function autoCivilReplenished($school_ids): array
    {
        try {
            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['voided', '=', 0];//没有作废
            $where[] = ['prepared', '=', 0];//没有预录取
            $where[] = ['school_attr', '=', 2];//民办
            $where[] = ['resulted', '=', 0];//没有录取
            $where[] = ['admission_type', '=', 0];//没有录取方式
            $where[] = ['apply_status', '<>', 5];//没有民办录取
            $where[] = ['apply_school_id', 'in', $school_ids];
            $apply_list = Db::name('UserApply')->where($where)->select()->toArray();

            //更改学校摇号结束状态
            $result = (new Schools())->editData(['yaohao_end_status' => 1], [['id', 'in', $school_ids]]);
            if($result['code'] == 0){
                throw new \Exception($result['msg']);
            }

            $region = Cache::get('region');
            if(!$region){
                throw new \Exception('区县缓存错误！');
            }

            foreach ($apply_list as $item){
                $replenished = [];
                foreach ($region as $k => $v){
                    if($v['id'] > 1) {
                        $replenished[$k]['user_id'] = $item['user_id'];
                        $replenished[$k]['region_ids'] = $v['id'];
                        $replenished[$k]['region_names'] = $v['region_name'];
                        $replenished[$k]['school_attr'] = "1,2";
                        $replenished[$k]['school_attr_text'] = "公办/民办";
                        $replenished[$k]['start_time'] = time();
                        $replenished[$k]['replenished'] = 1;
                        $replenished[$k]['end_time'] = strtotime("+3 days", time());

                        $region_replenished = Db::name('ApplyReplenished')->where('user_id', $item['user_id'])
                            ->where('region_ids', $v['id'])->where('deleted', 0)->find();
                        if($region_replenished) {
                            (new ApplyReplenished())->editData(['deleted' => 1], ['user_id' => $item['user_id'], 'region_ids' => $v['id']]);
                        }
                    }
                }
                Db::name('ApplyReplenished')->insertAll($replenished);
            }
            //民办落选
            $result = (new UserApply())->editData(['apply_status' => 4], $where);
            if($result['code'] == 0){
                throw new \Exception($result['msg']);
            }
            return ['code' => 1, 'msg' => '自动开启补录成功！'];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
        }
    }



    /**
     * 业务办理导出
     * @return Json
     */
    public function exportSource(): Json
    {
        if($this->request->isPost())
        {
            try {
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
                    //'api_live_code' => '大数据对比居住证号',
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
//                if(count($data) > 10000){
//                    $total = count($data);
//                    $count_excel = ceil($total / 10000);
//                    for ($i = 0; $i < $count_excel; $i++){
//                        $offset = $i * 10000;
//                        $length = ($i + 1) * 10000;
//                        if($i == ($count_excel - 1)){
//                            $length = $total;
//                        }
//                        $data = array_slice($data, $offset, $length, true);
//                        $this->excelExport('业务管理_' . ($i + 1) . '_', $headArr, $data);
//                    }
//                }else {
                      $this->excelExport('业务管理_', $headArr, $data);
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

    //生源明细 列表数据
    private function getListData($is_limit = true): array
    {
        try {
            $where = [];
            $where[] = ['a.deleted','=',0];
            $where[] = ['d.deleted','=',0];
            $where[] = ['a.offlined','=',0];

            if($this->request->has('voided') && $this->result['voided'] > 0)
            {
                $where[] = ['a.voided', '=', 1];//作废
            }else{
                $where[] = ['a.voided', '=', 0];//没有作废
            }
            //教管会管理的学校
            $school_ids = [];
            if ($this->userInfo['central_id'] > 0) {
                //区县角色排除市直学校
                $central_id = $this->userInfo['central_id'];
                $central_where = [];
                $central_where[] = ['deleted','=',0];
                $central_where[] = ['disabled','=',0];
                $central_where[] = ['directly','=',0];//不是市直
                $central_where[] = ['central_id', '=', $central_id];
                $school_ids = Db::name("SysSchool")->where($central_where)->column('id');
            }else{
                throw new \Exception('教管会管理员所属教管会ID设置错误');
            }
            //条件集合
            $_condition = $this->getCondition();
            if($_condition['code'] == 0){
                throw new \Exception($_condition['msg']);
            }
            $condition = [];
            if($_condition['data']){
                $condition = $_condition['data'];
            }
            if ($this->userInfo['region_id']) {
                $where[] = ['a.region_id', '=', $this->userInfo['region_id']];
            }else{
                throw new \Exception('教管会管理员所属区县ID设置错误');
            }

            //公办、民办、小学、初中权限
            $school_where = $this->getSchoolWhere('a');
            $where[] = $school_where['school_attr'];
            $where[] = $school_where['school_type'];

            if($school_ids){
                $where[] = Db::Raw(" a.apply_school_id in (" . implode(',', $school_ids) .
                    ") OR a.public_school_id in (" . implode(',', $school_ids) .
                    ") OR a.result_school_id in (" . implode(',', $school_ids) . ") ");
            }

            //最后一条消息
            $query = Db::name('UserMessage')->where('user_apply_id', Db::raw('a.id'))
                ->field(['title'])->order('create_time', 'DESC')->limit(1)->buildSql();
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
                ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                ->join([
                    'deg_user_supplement' => 'p'
                ], 'p.user_apply_id = a.id and p.deleted = 0 ', 'LEFT')
                ->join([
                    'deg_user_house' => 'h'
                ], 'a.house_id = h.id and h.deleted = 0 ', 'LEFT')
                ->join([
                    'deg_user_child' => 'c'
                ], 'a.child_id = c.id and c.deleted = 0 ', 'LEFT')
                ->join([
                    'deg_user_insurance' => 'i'
                ], 'i.house_id = a.house_id and i.deleted = 0 ', 'LEFT')
                ->join([
                    'deg_user_residence' => 'r'
                ], 'r.house_id = a.house_id and r.deleted = 0 ', 'LEFT')
                ->join([
                    'deg_user_company' => 'y'
                ], 'y.house_id = a.house_id and y.deleted = 0', 'LEFT')
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
                    $relation_query => 'relation',
                    $parent_name => 'parent_name',
                    'IF(a.specialed = 1, CONCAT(d.mobile, "_", a.child_id), "")' => 'special_card',
                    'CASE s.auto_check_ancestor WHEN -1 THEN "未比对成功" WHEN 0 THEN "未比对成功" WHEN 1 THEN "属实" WHEN 2 THEN "不属实" ELSE "-" END' => 'check_ancestor_name',
                    'd.false_region_id' => 'false_region_id',
                ])
                ->where($where)->where($condition)
                ->order('a.id', 'DESC')->order('a.region_id', 'ASC')->order('a.result_school_id', 'ASC');
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

                if($v['school_attr'] == 1){
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
        $spreadsheet->getActiveSheet()->getStyle('V')->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        $spreadsheet->getActiveSheet()->getColumnDimension('V')->setWidth(30);
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
                    //$objPHPExcel->setCellValue($span . $column, $value);
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

    //获取前端条件
    private function getCondition(): array
    {
        $where = [];

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
//            $user_apply_ids = Db::name('UserMessage')->where('deleted', 0)
//                ->where('title', $this->result['message_title'])->column('user_apply_id');
//            $where[] = ['a.id','in', $user_apply_ids];
//            $where[] = ['m.title','=', $this->result['message_title']];
            $where[] = Db::Raw(" m.id in (SELECT MAX(id) FROM deg_user_message WHERE deleted = 0 GROUP BY user_apply_id )");
        }
        //补充资料状态
        if($this->request->has('supplement_status') && $this->result['supplement_status'] !== '' )
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
                return ['code' => 0, 'msg' => $result['msg'] ];
            }
            $id_cards = $result['data'];
            //特殊情况的身份证含有学生ID
            $child_ids = [];
            $other_cards = [];
            foreach ($id_cards as $key => $item){
                $special_card = explode("_", $item );
                if(count($special_card) == 2){
                    $child_ids[] = $special_card[1];
                    unset($id_cards[$key]);
                }else {
                    $other_cards[] = "'" . strtoupper($item) . "'";
                }
            }
            if($child_ids){
                if($other_cards) {
                    $where[] = Db::Raw(" d.child_idcard in (" . implode(',', $other_cards) .
                        ") OR d.child_id in (" . implode(',', $child_ids) . ") ");
                }else{
                    $where[] = ['d.child_id', 'in', $child_ids];
                }

            }else {
                $where[] = ['d.child_idcard', 'in', $id_cards];
            }
        }

        return ['code' => 1, 'data' => $where ];
    }

}