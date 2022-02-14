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

class OnlineAdmission extends Education
{
    /**
     * 线上录取方式统计列表信息
     * @return \think\response\Json
     */
    public function getAdmissionList(): Json
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
                $prepared = $result['data']['prepared'];
                $resulted = $result['data']['resulted'];

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
                    //预录取
                    $data['data'][$k]['prepared_lottery'] = isset($prepared[$v['id']][1]) ? $prepared[$v['id']][1] : 0;//派位摇号
                    $data['data'][$k]['prepared_online'] = isset($prepared[$v['id']][2]) ? $prepared[$v['id']][2] : 0;//线上面审
                    $data['data'][$k]['prepared_offline'] = isset($prepared[$v['id']][3]) ? $prepared[$v['id']][3] : 0;//线下面审
                    $data['data'][$k]['prepared_assign'] = isset($prepared[$v['id']][5]) ? $prepared[$v['id']][5] : 0;//指派调剂
                    $data['data'][$k]['prepared_auto'] = isset($prepared[$v['id']][7]) ? $prepared[$v['id']][7] : 0;//民办自动录取
//                    $data['data'][$k]['prepared_total'] = $data['data'][$k]['prepared_lottery'] + $data['data'][$k]['prepared_online']
//                        + $data['data'][$k]['prepared_offline'] + $data['data'][$k]['prepared_assign'];//预录取合计

                    //最终录取
                    $data['data'][$k]['resulted_lottery'] = isset($resulted[$v['id']][1]) ? $resulted[$v['id']][1] : 0;//派位摇号
                    $data['data'][$k]['resulted_online'] = isset($resulted[$v['id']][2]) ? $resulted[$v['id']][2] : 0;//线上面审
                    $data['data'][$k]['resulted_offline'] = isset($resulted[$v['id']][3]) ? $resulted[$v['id']][3] : 0;//线下面审
                    $data['data'][$k]['resulted_dispatch'] = isset($resulted[$v['id']][4]) ? $resulted[$v['id']][4] : 0;//派遣
                    $data['data'][$k]['resulted_assign'] = isset($resulted[$v['id']][5]) ? $resulted[$v['id']][5] : 0;//指派调剂
                    $data['data'][$k]['resulted_policy'] = isset($resulted[$v['id']][6]) ? $resulted[$v['id']][6] : 0;//政策生
                    $data['data'][$k]['resulted_auto'] = isset($resulted[$v['id']][7]) ? $resulted[$v['id']][7] : 0;//民办自动录取
                    $data['data'][$k]['resulted_follow'] = isset($resulted[$v['id']][8]) ? $resulted[$v['id']][8] : 0;//公办摸排录取
//                    $data['data'][$k]['resulted_total'] = isset($resulted[$v['id']]) ? array_sum($resulted[$v['id']]) : 0;//最终录取合计

                    $where = [];
                    $where[] = ['a.deleted','=',0];
                    $where[] = ['a.voided','=',0];
                    $where[] = ['a.prepared', '=', 1];//预录取
                    $where[] = ['a.offlined', '=', 0];//不是线下录取
                    $where[] = ['a.admission_type', '>', 0];//线上录取

                    if($v['school_attr'] == 1) {
                        $where[] = ['a.school_attr', '=', 1];//公办

                        $data['data'][$k]['prepared_total'] = Db::name('UserApply')->alias('a')->where($where)
                            ->where('a.resulted', 0)->where('a.public_school_id', $v['id'])->count();
                        $data['data'][$k]['resulted_total'] = Db::name('UserApply')->alias('a')->where($where)
                            ->where('a.resulted', 1)->where('a.result_school_id', $v['id'])->count();
                    }elseif ($v['school_attr'] == 2){
                        $where[] = ['a.school_attr', '=', 2];//民办
                        $where[] = ['a.resulted','=',1];
                        $where[] = ['a.result_school_id','=',$v['id']];

                        $data['data'][$k]['prepared_total'] = Db::name('UserApply')->alias('a')
                            ->where($where)->where('a.paid', 0)->count();
                        $data['data'][$k]['resulted_total'] = Db::name('UserApply')->alias('a')
                            ->where($where)->where('a.paid', 1)->count();
                    }

                    $data['data'][$k]['degree_total'] = isset($res_degree['data'][$v['id']]) ? $res_degree['data'][$v['id']] : 0;//审批学位数量
                    $degree_surplus = 0;
                    if($v['school_attr'] == 1) {
                        $degree_surplus = $data['data'][$k]['degree_total'] - $data['data'][$k]['resulted_total'] - $data['data'][$k]['prepared_total'];//剩余学位数量
                    }elseif ($v['school_attr'] == 2){
                        $degree_surplus = $data['data'][$k]['degree_total'] - ($data['data'][$k]['prepared_total'] + $data['data'][$k]['resulted_total']);//剩余学位数量
                    }
                    //$degree_surplus = $degree_surplus < 0 ? 0 : $degree_surplus;
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
                $where[] = ['a.public_school_id', 'in', $school_ids];

                $field = ['public_school_id' => 'public_school_id',
                    'admission_type' => 'type', 'COUNT(*)' => 'enroll_num'];
                $group = 'public_school_id, admission_type';
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
}