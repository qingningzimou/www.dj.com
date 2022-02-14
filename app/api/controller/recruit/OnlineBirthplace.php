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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;
use think\facade\Cache;

class OnlineBirthplace extends Education
{


    /**
     * 线上户籍情况统计列表信息
     * @return \think\response\Json
     */
    public function getBirthplaceList(): Json
    {
        if ($this->request->isPost()) {
            try {
                //权限学校
                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0){
                    throw new \Exception($role_school['msg']);
                }

                $school_list = $this->getSchoolList($role_school['school_ids'], $role_school['bounded']);
                if($school_list['code'] == 0){
                    throw new \Exception($school_list['msg']);
                }
                $data = $school_list['data'];

                $region = Cache::get('region');
                //预录取、录取数据
                $result = $this->getAdmissionData($role_school['school_ids']);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $resulted = $result['data']['resulted'];

                //户籍情况
                $result = $this->getSchoolBirthplaceData($role_school['school_ids']);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $birthplace = $result['list'];

                //学校学位审批数量
                $res_degree = $this->getSchoolDegree($role_school['school_ids']);
                if($res_degree['code'] == 0){
                    throw new \Exception($res_degree['msg']);
                }

                foreach ($data['data'] as $k => $v){
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }

                    $main_area = isset($birthplace[$v['id']][1]) ? $birthplace[$v['id']][1] : 0;//主城区
                    $not_main_area = isset($birthplace[$v['id']][2]) ? $birthplace[$v['id']][2] : 0;//非主城区
                    $city_not_area = isset($birthplace[$v['id']][3]) ? $birthplace[$v['id']][3] : 0;//襄阳市非本区
                    $not_city = isset($birthplace[$v['id']][4]) ? $birthplace[$v['id']][4] : 0;//非襄阳市
                    $not_result = isset($birthplace[$v['id']][5]) ? $birthplace[$v['id']][5] : 0;//未比对成功
                    $not_comparison = isset($birthplace[$v['id']][-1]) ? $birthplace[$v['id']][-1] : 0;//未比对

                    $data['data'][$k]['main_area'] = $main_area;//主城区
                    $data['data'][$k]['not_main_area'] = $not_main_area;//非主城区
                    $data['data'][$k]['city_not_area'] = $city_not_area;//襄阳市非本区
                    $data['data'][$k]['not_city'] = $not_city;//非襄阳市
                    $data['data'][$k]['not_result'] = $not_result;//未比对成功
                    $data['data'][$k]['not_comparison'] = $not_comparison;//未比对
                    $data['data'][$k]['birthplace_total'] = $main_area + $not_main_area + $city_not_area +
                        $not_city + $not_result + $not_comparison;//合计

                    $resulted_total = 0;
                    if($v['school_attr'] == 1) {
                        $resulted_total = isset($resulted[$v['id']]) ? array_sum($resulted[$v['id']]) : 0;//最终录取合计
                    }elseif ($v['school_attr'] == 2){
                        $where = [];
                        $where[] = ['a.deleted', '=', 0];
                        $where[] = ['a.voided', '=', 0];//没有作废
                        $where[] = ['a.school_attr', '=', 2];
                        $where[] = ['a.prepared', '=', 1];//预录取
                        $where[] = ['a.resulted', '=', 1];//录取
                        $where[] = ['a.offlined', '=', 0];//不是线下录取
                        $where[] = ['a.admission_type', '>', 0];
                        $where[] = ['a.result_school_id', '=', $v['id'] ];//录取学校
                        $resulted_total = Db::name('UserApply')->alias('a')->where($where)->count();//使用学位
                    }
                    $data['data'][$k]['degree_total'] = isset($res_degree['data'][$v['id']]) ? $res_degree['data'][$v['id']] : 0;//审批学位数量
                    $degree_surplus = $data['data'][$k]['degree_total'] - $resulted_total;//剩余学位数量
                    $degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;
                    $data['data'][$k]['degree_surplus'] = $degree_surplus;//剩余学位数量
                }

                $data['select_region'] = !$role_school['bounded'];

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


    private function getAdmissionData($school_ids): array
    {
        try {
            //公办学校
            $public_school_ids = Db::name("SysSchool")->where('id', 'in', $school_ids )
                ->where('school_attr', 1)->column('id');
            //民办学校
            $civil_school_ids = Db::name("SysSchool")->where('id', 'in', $school_ids )
                ->where('school_attr', 2)->column('id');

            $prepared = [];
            //公办预录取
            $result = $this->getSchoolEnroll(1, $public_school_ids, $prepared);
            if ($result['code'] == 0) {
                return ['code' => 0, 'msg' => $result['msg'] ];
            }
            $prepared = $result['data'];
            //民办预录取
            $result = $this->getSchoolEnroll(2, $civil_school_ids, $prepared);
            if ($result['code'] == 0) {
                return ['code' => 0, 'msg' => $result['msg'] ];
            }
            $prepared = $result['data'];

            $resulted = [];
            //公办最终录取
            $result = $this->getSchoolEnroll(3, $public_school_ids, $resulted);
            if ($result['code'] == 0) {
                return ['code' => 0, 'msg' => $result['msg'] ];
            }
            $resulted = $result['data'];
            //民办最终录取
            $result = $this->getSchoolEnroll(4, $civil_school_ids, $resulted);
            if ($result['code'] == 0) {
                return ['code' => 0, 'msg' => $result['msg'] ];
            }
            $resulted = $result['data'];

            return ['code' => 1, 'data' => ['prepared' => $prepared, 'resulted' => $resulted] ];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }


    //学校列表
    private function getSchoolList($school_ids, $bounded): array
    {
        try {
            $where = [];
            $where[] = ['deleted','=',0];
            $where[] = ['disabled','=',0];
            $where[] = ['onlined','=',1];

            if($this->request->has('keyword') && $this->result['keyword'])
            {
                $where[] = ['school_name','like', '%' . $this->result['keyword'] . '%'];
            }
            if($this->request->has('school_type') && $this->result['school_type'] > 0)
            {
                $where[] = ['school_type','=', $this->result['school_type']];
            }
            if($this->request->has('school_attr') && $this->result['school_attr'] > 0)
            {
                $where[] = ['school_attr','=', $this->result['school_attr']];
            }
            if($this->request->has('region_id') && $this->result['region_id'] > 0)
            {
                $where[] = ['region_id','=', $this->result['region_id']];
            }
            if( $bounded ){
                $where[] = ['id', 'in', $school_ids ];
            }

            $data = Db::name('SysSchool')
                ->field([
                    'id',
                    'region_id',
                    'school_name',
                    'school_attr',
                ])
                ->where($where)
                ->order('sort_order', 'ASC')
                ->order('id', 'ASC')
                ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            //权限节点
            $res_data = $this->getResources($this->userInfo, $this->request->controller());
            $data['resources'] = $res_data;

            return ['code' => 1, 'data' => $data ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error') ];
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
                //消息标题列表
                $data['message_list'] = Db::name('UserMessage')->where('deleted', 0)->where('title', '<>', '')
                    ->group('title')->column('title');

                $result = $this->getAllSelectByRoleList();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data['region_list'] = $result['region_list'];
                $data['school_list'] = $result['school_list'];

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

    private function getSchoolBirthplaceData($school_ids): array
    {
        try {
            //公办学校
            $public_school_ids = Db::name("SysSchool")->where('id', 'in', $school_ids )
                ->where('school_attr', 1)->column('id');
            //民办学校
            $civil_school_ids = Db::name("SysSchool")->where('id', 'in', $school_ids )
                ->where('school_attr', 2)->column('id');

            $list = [];
            //公办最终录取
            $result = $this->getSchoolBirthplace(1, $public_school_ids, $list);
            if ($result['code'] == 0) {
                return ['code' => 0, 'msg' => $result['msg'] ];
            }
            $list = $result['data'];
            //民办最终录取
            $result = $this->getSchoolBirthplace(2, $civil_school_ids, $list);
            if ($result['code'] == 0) {
                return ['code' => 0, 'msg' => $result['msg'] ];
            }
            $list = $result['data'];

            return ['code' => 1, 'list' => $list ];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //统计学校学位数量
    private function getSchoolDegree($school_ids): array
    {
        try {
            $data = Db::name('PlanApply')
                //->field(['school_id', 'SUM(spare_total)' => 'spare_total'])
                ->where('status', 1)
                ->where('deleted', 0)
                ->where('school_id', '>', 0)
                ->where('school_id', 'in', $school_ids)
                ->group('school_id')->column('SUM(spare_total)', 'school_id');

            return ['code' => 1, 'data' => $data ];
        }  catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }


    //公办、民办预录取、最终录取统计
    private function getSchoolEnroll($type, $school_ids, $result_school): array
    {
        try {
            $where = [];
            $where[] = ['a.deleted','=',0];
            $where[] = ['a.voided','=',0];
            $where[] = ['a.prepared', '=', 1];//预录取
            $where[] = ['a.offlined', '=', 0];//不是线下录取
            $where[] = ['a.admission_type', '>', 0];//线上录取

            $field = ['result_school_id' => 'school_id', 'admission_type' => 'type', 'COUNT(*)' => 'enroll_num'];
            $group = 'result_school_id, admission_type';
            //公办预录取
            if($type == 1){
                $where[] = ['a.school_attr', '=', 1];//公办
                $where[] = ['a.resulted', '=', 0];//录取
                $where[] = ['a.public_school_id|a.house_school_id', 'in', $school_ids];

                $field = ['public_school_id' => 'public_school_id', 'house_school_id' => 'house_school_id',
                    'admission_type' => 'type', 'COUNT(*)' => 'enroll_num'];
                $group = 'public_school_id, house_school_id, admission_type';
            }
            //民办预录取
            if($type == 2){
                $where[] = ['a.school_attr', '=', 2];//民办
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.paid', '=', 0];//未缴费
                $where[] = ['a.result_school_id', 'in', $school_ids];
            }
            //公办最终录取
            if($type == 3){
                $where[] = ['a.school_attr', '=', 1];//公办
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.result_school_id', 'in', $school_ids];
            }
            //民办最终录取
            if($type == 4){
                $where[] = ['a.school_attr', '=', 2];//民办
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.paid', '=', 1];//已缴费
                $where[] = ['a.result_school_id', 'in', $school_ids];
            }

            $list = Db::name('UserApply')->alias('a')->field($field)->where($where)
                ->group($group)->select()->toArray();

            foreach ($list as $v){
                if(isset($v['school_id']) && $v['school_id'] > 0) {
                    if(isset($result_school[$v['school_id']][$v['type']])) {
                        $result_school[$v['school_id']][$v['type']] += $v['enroll_num'];
                    }else{
                        $result_school[$v['school_id']][$v['type']] = $v['enroll_num'];
                    }
                }
                if(isset($v['public_school_id']) && $v['public_school_id'] > 0) {
                    if(isset($result_school[$v['public_school_id']][$v['type']])) {
                        $result_school[$v['public_school_id']][$v['type']] += $v['enroll_num'];
                    }else{
                        $result_school[$v['public_school_id']][$v['type']] = $v['enroll_num'];
                    }
                }
                if(isset($v['house_school_id']) && $v['house_school_id'] > 0) {
                    if(isset($result_school[$v['house_school_id']][$v['type']])) {
                        $result_school[$v['house_school_id']][$v['type']] += $v['enroll_num'];
                    }else{
                        $result_school[$v['house_school_id']][$v['type']] = $v['enroll_num'];
                    }
                }
            }

            return ['code' => 1, 'data' => $result_school];

        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }


    //公办、民办最终录取户籍情况统计
    private function getSchoolBirthplace($type, $school_ids, $result_school): array
    {
        try {
            $where = [];
            $where[] = ['a.deleted','=',0];
            $where[] = ['a.voided','=',0];
            $where[] = ['a.prepared', '=', 1];//预录取
            $where[] = ['a.offlined', '=', 0];//不是线下录取
            $where[] = ['a.admission_type', '>', 0];//线上录取
            $where[] = ['a.result_school_id', 'in', $school_ids];

            //公办最终录取
            if($type == 1){
                $where[] = ['a.school_attr', '=', 1];//公办
                $where[] = ['a.resulted', '=', 1];//录取
            }
            //民办最终录取
            if($type == 2){
                $where[] = ['a.school_attr', '=', 2];//民办
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.paid', '=', 1];//已缴费
            }

            $list = Db::name('UserApply')->alias('a')
                ->join([
                    'deg_user_apply_detail' => 'd'
                ], 'a.child_id = d.child_id and d.deleted = 0 ', 'left')
                ->field([
                    'a.result_school_id' => 'school_id',
                    'IFNULL(d.birthplace_status, -1)' => 'status',
                    'COUNT(*)' => 'birthplace_num'
                ])->where($where)
                ->group('a.result_school_id, d.birthplace_status')->select()->toArray();

            foreach ($list as $v){
                $result_school[$v['school_id']][$v['status']] = $v['birthplace_num'];
            }

            return ['code' => 1, 'data' => $result_school];

        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }
}