<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\comprehensive;

use app\common\controller\Education;
use app\common\model\UserApplyDetail;
use app\common\model\UserMessageBatch;
use app\common\model\UserMessagePrepare;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use app\common\model\UserApplyDetail as model;
use think\facade\Db;

class Verification extends Education
{
    /**
     * 派位验证
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['a.deleted','=',0];
                $where[] = ['a.voided','=',0];
                $where[] = ['d.deleted','=',0];
                $where[] = ['a.offlined','=',0];//线上招生方式
                $where[] = ['a.school_attr','=',1];//公办
                $where[] = ['d.single_double','>',0];//单双符
                $where[] = ['a.assignmented','=',0];//未派位

                //派出所
                if($this->request->has('police_station_id') && $this->result['police_station_id'] > 0)
                {
                    $where[] = ['c.api_policestation_id','=', $this->result['police_station_id']];
                }
                //学校类型
                if($this->request->has('school_type') && $this->result['school_type'] > 0)
                {
                    $where[] = ['a.school_type','=', $this->result['school_type']];
                }
                //派位学校
                if($this->request->has('house_school_id') && $this->result['house_school_id'] > 0 )
                {
                    $where[] = ['a.house_school_id','=', $this->result['house_school_id']];
                }
                //单双符状态
                if($this->request->has('single_double') && $this->result['single_double'] > 0 )
                {
                    $where[] = ['d.single_double','=', $this->result['single_double']];
                }
                //验证状态
                if($this->request->has('verification') && $this->result['verification'] !== '' )
                {
                    $where[] = ['d.single_double_verification','=', $this->result['verification']];
                }
                //关键字搜索
                if($this->request->has('keyword') && $this->result['keyword'] != ''){
                    $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
                }
                //批量搜索
                if ($this->request->has('batch') && $this->result['batch'] !== ''){
                    $result = $this->getBatchCondition();
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    $id_cards = $result['data'];
                    $where[] = ['d.child_idcard','in', $id_cards ];
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['a.region_id', 'in', $region_ids];

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary', 'SYSPWYZ');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $filter_region = array_column($getData['data'],'dictionary_value');
                $where[] = ['a.region_id', 'in', $filter_region];

                $data = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id', 'LEFT')
                    ->join([
                        'deg_user_child' => 'c'
                    ], 'c.id = a.child_id', 'LEFT')
                    ->join([
                        'deg_user_house' => 'h'
                    ], 'h.id = a.house_id', 'LEFT')
                    ->field([
                        'd.id',
                        'a.id' => 'user_apply_id',
                        'a.region_id' => 'region_id',
                        'a.school_type' => 'school_type',
                        'a.house_school_id' => 'house_school_id',
                        'd.child_name' => 'real_name',
                        'd.child_idcard' => 'student_id_card',
                        'd.single_double' => 'single_double',
                        'd.single_double_verification' => 'verification',
                        'c.api_policestation' => 'police_station_name',
                        'h.house_address' => 'house_address',
                    ])
                    ->where($where)
                    ->order('a.region_id', 'ASC')->order('a.result_school_id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $school = Cache::get('school');
                $dictionary = new FilterData();
                $typeData = $dictionary->resArray('dictionary','SYSXXLX');
                if(!$typeData['code']){
                    throw new \Exception($typeData['msg']);
                }

                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = $result['data'];

                foreach ($data['data'] as $k => $v){
                    $data['data'][$k]['house_school_name'] = '-';
                    $houseSchoolData = filter_value_one($school, 'id', $v['house_school_id']);
                    if (count($houseSchoolData) > 0){
                        $data['data'][$k]['house_school_name'] = $houseSchoolData['school_name'];
                    }

                    $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $v['school_type']);
                    $data['data'][$k]['school_type_name'] = '';
                    if (count($schoolTypeData) > 0){
                        $data['data'][$k]['school_type_name'] = $schoolTypeData['dictionary_name'];
                    }

                    $single_double_name = isset($result['single_double_list'][$v['single_double']]) ? $result['single_double_list'][$v['single_double']] : '-';

                    $data['data'][$k]['single_double_name'] = $single_double_name;
                    $data['data'][$k]['verification_name'] = $v['verification'] > 0 ? '不符合' : '';
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
                $data = [];
                $result = $this->getViewData();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $result = $result['data'];
                foreach ($result['single_double_list'] as $k => $v){
                    $data['single_double_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($result['verification_list'] as $k => $v){
                    $data['verification_list'][] = ['id' => $k, 'name' => $v];
                }

                $result = $this->getAllSelectByRoleList();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $school_list = $result['school_list'];

                foreach ($school_list as $item){
                    if($item['school_attr'] == 1){
                        $data['school_list'][] = $item;
                    }
                }
                $data['police_list'] = $result['police_list'];

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary', 'SYSXXLX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                foreach ($getData['data'] as $value){
                    $data['school_type'][] =[
                        'id' => $value['dictionary_value'],
                        'type_name' => $value['dictionary_name']
                    ];
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
     * 验证不符
     * @return Json
     */
    public function inconformity(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $param = $this->request->only(['id',]);
                //  如果数据不合法，则返回
                if (!$param['id']) {
                    throw new \Exception('ID参数错误');
                }
                $ids = $param['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选学生信息');
                }

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['id', 'in', $id_array];
                $result = (new UserApplyDetail())->editData(['single_double_verification' => 1], $where);
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
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 取消验证不符
     * @return Json
     */
    public function cancel(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $param = $this->request->only(['id',]);
                //  如果数据不合法，则返回
                if (!$param['id']) {
                    throw new \Exception('ID参数错误');
                }
                $ids = $param['id'];
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选学生信息');
                }

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['single_double_verification', '=', 1];
                $where[] = ['id', 'in', $id_array];
                $result = (new UserApplyDetail())->editData(['single_double_verification' => 0], $where);
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

    private function getListData(){
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['a.deleted','=',0];
                $where[] = ['a.voided','=',0];
                $where[] = ['a.offlined','=',0];//线上招生方式
                $where[] = ['a.school_attr','=',1];//公办
                $where[] = ['d.single_double','>',0];//单双符

                //派出所
                if($this->request->has('police_station_id') && $this->result['police_station_id'] > 0)
                {
                    $where[] = ['c.api_policestation_id','=', $this->result['police_station_id']];
                }
                //学校类型
                if($this->request->has('school_type') && $this->result['school_type'] > 0)
                {
                    $where[] = ['a.school_type','=', $this->result['school_type']];
                }
                //派位学校
                if($this->request->has('house_school_id') && $this->result['house_school_id'] > 0 )
                {
                    $where[] = ['a.house_school_id','=', $this->result['house_school_id']];
                }
                //单双符状态
                if($this->request->has('single_double') && $this->result['single_double'] > 0 )
                {
                    $where[] = ['d.single_double','=', $this->result['single_double']];
                }
                //验证状态
                if($this->request->has('verification') && $this->result['verification'] !== '' )
                {
                    $where[] = ['d.single_double_verification','=', $this->result['verification']];
                }
                //关键字搜索
                if($this->request->has('keyword') && $this->result['keyword'] != ''){
                    $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
                }
                //批量搜索
                if ($this->request->has('batch') && $this->result['batch'] !== ''){
                    $result = $this->getBatchCondition();
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    $id_cards = $result['data'];
                    $where[] = ['d.child_idcard','in', $id_cards ];
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['a.region_id', 'in', $region_ids];

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary', 'SYSPWYZ');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $filter_region = array_column($getData['data'],'dictionary_value');
                $where[] = ['a.region_id', 'in', $filter_region];

                //最后一条消息
                $query = Db::name('UserMessage')->alias('m')->where('m.user_apply_id', Db::raw('a.id'))
                    ->field(['title'])->order('m.create_time', 'DESC')->limit(1)->buildSql();
                //六年级毕业学校
                $school_query = Db::name('SixthGrade')->where('id_card', Db::raw('c.idcard'))
                    ->where('id_card', '<>', '')->where('deleted', 0)
                    ->field(['graduation_school_id'])->limit(1)->buildSql();
                //填报监护人关系
                $relation_query = Db::name('UserFamily')->where('id', Db::raw('a.family_id'))
                    ->where('deleted', 0)->field(['relation'])->limit(1)->buildSql();

                $list = Db::name('UserApply')->alias('a')
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
//                    ->join([
//                        'deg_sixth_grade' => 'g'
//                    ], 'g.id_card = c.idcard AND g.deleted = 0', 'LEFT')
                    ->join([
                        'deg_user_insurance' => 'i'
                    ], 'i.house_id = a.house_id AND i.deleted = 0', 'LEFT')
                    ->join([
                        'deg_user_residence' => 'r'
                    ], 'r.house_id = a.house_id AND r.deleted = 0', 'LEFT')
                    ->join([
                        'deg_user_company' => 'y'
                    ], 'y.house_id = a.house_id AND y.deleted = 0', 'LEFT')
                    ->field([
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
                        $school_query => 'graduation_school_id',
                        $relation_query => 'relation',
                        'IF(a.specialed = 1, CONCAT(d.mobile, "_", a.child_id), "")' => 'special_card',
                    ])
                    ->where($where)
                    ->order('a.region_id', 'ASC')->order('a.result_school_id', 'ASC')->order('a.id', 'ASC')
                    ->select()
                    ->toArray();

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

                }
                return ['code' => 1, 'list' => $list];
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
     * 派位验证导出
     * @return Json
     */
    public function exportSource(): Json
    {
        if($this->request->isPost())
        {
            try {
                $result = $this->getListData();
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
                    'fill_relation_name' => '填报监护人关系',
                    'relation_name' => '监护人关系对比',
                    'student_api_relation' => '与户主关系',
                    'house_type_name' => '房产类型',
                    'house_address' => '房产填报地址',
                    'api_house_address' => '大数据房产对比地址',
                    'house_status_name' => '房产',
                    'three_syndromes_name' => '三证情况',
                    'student_status_name' => '学籍情况',
                    'apply_school_name' => '申报学校',
                    'house_school_name' => '房产匹配学校',
                    'insurance_area' => '是否本区社保',
                    'company_area' => '是否在本区经商',
                    'residence_area' => '是否本区居住证',
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
                if(count($data) > 10000){
                    $total = count($data);
                    $count_excel = ceil($total / 10000);
                    for ($i = 0; $i < $count_excel; $i++){
                        $offset = $i * 10000;
                        $length = ($i + 1) * 10000;
                        if($i == ($count_excel - 1)){
                            $length = $total;
                        }
                        $data = array_slice($data, $offset, $length, true);
                        $this->excelExport('派位验证_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('派位验证_', $headArr, $data);
                }

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
        $spreadsheet->getActiveSheet()->getColumnDimension('W')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('X')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('Y')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('Z')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AA')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AB')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AC')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AD')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('AE')->setWidth(120);

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

                if($keyName == 'student_id_card' || $keyName == 'one_id_card' || $keyName == 'two_id_card'){
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