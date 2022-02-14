<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\comprehensive;

use app\common\controller\Education;
use app\common\model\UserMessageBatch;
use app\common\model\UserMessagePrepare;
use appPush\AppMsg;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use app\common\model\SysMessage as model;
use app\common\validate\comprehensive\SysMessage as validate;
use think\facade\Db;

class MessageBatch extends Education
{
    /**
     * 消息批量发送
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
                $where[] = ['a.offlined','=',0];

                //无搜索条件 不显示数据
                if($this->request->has('keyword') && $this->result['keyword'] == '' && $this->result['batch'] == '' ){
                    $where[] = [Db::raw(1), '=', 0];
                }
                if($this->request->has('keyword') && $this->result['keyword'] != ''){
                    $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
                }elseif ($this->request->has('batch') && $this->result['batch'] !== ''){
                    $result = $this->getBatchCondition();
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    $id_cards = $result['data'];
                    $where[] = ['d.child_idcard','in', $id_cards ];
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['a.region_id', 'in', $region_ids];

                //最后一条消息
                $query = Db::name('UserMessage')->alias('m')->where('m.user_apply_id', Db::raw('a.id'))
                    ->field(['title'])->order('m.create_time', 'DESC')->limit(1)->buildSql();

                $data = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id', 'LEFT')
                    ->field([
                        'a.id',
                        'a.region_id' => 'region_id',
                        'a.school_type' => 'school_type',
                        'a.school_attr' => 'school_attr',
                        'a.house_school_id' => 'house_school_id',
                        'a.public_school_id' => 'public_school_id',
                        'a.result_school_id' => 'result_school_id',
                        'a.admission_type' => 'admission_type',
                        $query => 'last_message',
                        'd.child_name' => 'real_name',
                        'd.child_idcard' => 'student_id_card',
                        'd.child_age_status' => 'child_age_status',
                        'd.birthplace_status' => 'birthplace_status',
                        'd.guardian_relation' => 'guardian_relation',
                        'd.house_status' => 'house_status',
                        'd.three_syndromes_status' => 'three_syndromes_status',
                        'd.student_status' => 'student_status',
                        'd.insurance_status' => 'insurance_status',
                        'd.business_license_status' => 'business_license_status',
                        'd.residence_permit_status' => 'residence_permit_status',
                        'a.policyed' => 'policyed',
                        'a.apply_status' => 'apply_status',
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

                $not_check_id_cards = [];
                //搜索结果存入预发送表
                if($this->request->has('curr') && $this->result['curr'] == 1) {
                    $list = Db::name('UserApply')->alias('a')
                        ->join([
                            'deg_user_apply_detail' => 'd'
                        ], 'd.child_id = a.child_id', 'LEFT')
                        ->field([
                            'a.id' => 'user_apply_id',
                            'a.school_attr' => 'school_attr',
                            'a.apply_school_id' => 'apply_school_id',
                            'a.public_school_id' => 'public_school_id',
                            'a.result_school_id' => 'result_school_id',
                            'a.region_id' => 'region_id',
                            'a.user_id' => 'user_id',
                            'd.child_name' => 'child_name',
                            'd.mobile' => 'mobile',
                            'd.child_idcard' => 'id_card',
                        ])
                        ->where($where)->select()->toArray();
                    if(count($list) > 0) {
                        $cache_id_card = Cache::get('importBatchCardIds_'.$this->userInfo['manage_id'] , []);
                        $query_id_card = [];
                        $manage_id = $this->userInfo['manage_id'];
                        $result = (new UserMessagePrepare())->editData(['deleted' => 1], ['manage_id' => $manage_id, 'sended' => 0]);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }
                        $prepare = [];
                        foreach ($list as $item) {
                            $area_name = '';
                            $school_name = '';
                            $regionData = filter_value_one($region, 'id', $item['region_id']);
                            if (count($regionData) > 0){
                                $area_name = $regionData['region_name'];
                            }
                            if($item['school_attr'] == 1){
                                if($item['public_school_id'] ){
                                    $schoolData = filter_value_one($school, 'id', $item['public_school_id']);
                                    if (count($schoolData) > 0){
                                        $school_name = $schoolData['school_name'];
                                    }
                                }
                            }
                            if($item['school_attr'] == 2){
                                if($item['apply_school_id'] ){
                                    $schoolData = filter_value_one($school, 'id', $item['apply_school_id']);
                                    if (count($schoolData) > 0){
                                        $school_name = $schoolData['school_name'];
                                    }
                                }
                            }
                            if($item['result_school_id'] ){
                                $schoolData = filter_value_one($school, 'id', $item['result_school_id']);
                                if (count($schoolData) > 0){
                                    $school_name = $schoolData['school_name'];
                                }
                            }

                            $val = [];
                            $val['user_id'] = $item['user_id'];
                            $val['user_apply_id'] = $item['user_apply_id'];
                            $val['child_name'] = $item['child_name'];
                            $val['area_name'] = $area_name;
                            $val['school_name'] = $school_name;
                            $val['manage_id'] = $manage_id;
                            $val['mobile'] = $item['mobile'];

                            $query_id_card[] = $item['id_card'];

                            $prepare[] = $val;
                        }
                        if ($this->request->has('batch') && $this->result['batch'] !== ''){
                            $not_check_id_cards = array_values(array_diff($cache_id_card, $query_id_card));
                        }
                        // 分批写入 每次最多1000条数据
                        Db::name('UserMessagePrepare')->limit(1000)->insertAll($prepare);
                    }
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
                    $apply_status_name = isset($result['apply_status_list'][$v['apply_status']]) ? $result['apply_status_list'][$v['apply_status']] : '-';
                    $three_syndromes_name = $this->getThreeSyndromesName($v);

                    if($student_age_name == '足龄'){
                        $student_age_name = '-';
                    }

                    $data['data'][$k]['student_age_name'] = $student_age_name;
                    $data['data'][$k]['relation_name'] = $relation_name;
                    $data['data'][$k]['birthplace_status_name'] = $birthplace_status_name;
                    $data['data'][$k]['house_status_name'] = $house_status_name;
                    $data['data'][$k]['three_syndromes_name'] = $three_syndromes_name;
                    $data['data'][$k]['student_status_name'] = $student_status_name;
                    $data['data'][$k]['apply_status_name'] = $apply_status_name;
                    $data['data'][$k]['policyed'] = $v['policyed'] == 1 ? '是' : '否';
                }
                $data['not_check_id_cards'] = $not_check_id_cards;
                
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
                foreach ($result['voided_list'] as $k => $v){
                    $data['voided_list'][] = ['id' => $k, 'name' => $v];
                }

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['status', '=', 1];
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['region_id', 'in', $region_ids];
                $data['message_list'] = Db::name('SysMessage')->field(['id', 'title'])->where($where)->select()->toArray();

                $result = $this->getAllSelectByRoleList();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
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
                    $data['school_type'][] =[
                        'id' => $value['dictionary_value'],
                        'type_name' => $value['dictionary_name']
                    ];
                }
                $getData = $dictionary->resArray('dictionary', 'SYSXXXZ');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                foreach ($getData['data'] as $value){
                    $data['school_attr'][] =[
                        'id' => $value['dictionary_value'],
                        'attr_name' => $value['dictionary_name']
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
     * 根据消息ID获取消息内容
     * @return Json
     */
    public function getMessage(): Json
    {
        if ($this->request->isPost()) {
            try {
                $param = $this->request->only(['id',]);
                //  如果数据不合法，则返回
                if (!$param['id']) {
                    throw new \Exception('ID参数错误');
                }
                $data = Db::name('SysMessage')->find($param['id']);

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
     * 消息发送
     * @return Json
     */
    public function sendMessage(): Json
    {
        if ($this->request->isPost()) {
            try {
                ignore_user_abort(true);
                set_time_limit(0);
                $param = $this->request->only(['id', 'hash']);
                //  如果数据不合法，则返回
                if (!$param['id']) {
                    throw new \Exception('ID参数错误');
                }
                $message = Db::name('SysMessage')->field(['id', 'title', 'content'])->find($param['id']);
                if(!$message){
                    throw new \Exception('找不到消息内容！');
                }
                //  验证表单hash
                $checkHash = parent::checkHash($param['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                $manage_id = $this->userInfo['manage_id'];
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['sended', '=', 0];
                $where[] = ['manage_id', '=', $manage_id];
                $hasData = Db::name('UserMessagePrepare')->where($where)->select()->toArray();
                if(!count($hasData)){
                    throw new \Exception('没有发送消息的数据！');
                }
                $chunkData = array_chunk($hasData, 10);
                $department_id = $this->userInfo['department_id'];
                $sys_message_id = $param['id'];
                foreach ($chunkData as $new_list){
                    Db::startTrans();
                    try{
                        $max_id = Db::name('UserMessageBatch')->master(true)->where('deleted', 0)->max('id');
                        $temp_num = 100000000;
                        $new_num = $max_id + $temp_num + 1;
                        $sys_batch_code = 'MS'.substr($new_num,1,8);
                        //批次ID
                        $sys_batch_id = Db::name('UserMessageBatch')->insertGetId([
                            'sys_batch_code' => $sys_batch_code,
                            'department_id' => $department_id,
                            'sys_message_id' => $sys_message_id,
                            'manage_id' => $this->userInfo['manage_id'],
                        ]);
                        $user_message = [];
                        foreach ($new_list as $item){
                            $content = $message['content'];
                            $content = preg_replace('/child_name/', $item['child_name'], $content);
                            $content = preg_replace('/area_name/', $item['area_name'], $content);
                            if($item['school_name']){
                                $content = preg_replace('/school_name/', $item['school_name'], $content);
                            }
                            //用户消息内容
                            $user_message[] = [
                                'user_id' => $item['user_id'],
                                'user_apply_id' => $item['user_apply_id'],
                                'mobile' => $item['mobile'],
                                'sys_batch_id' => $sys_batch_id,
                                'sys_batch_code' => $sys_batch_code,
                                'sys_message_id' => $sys_message_id,
                                'title' => $message['title'],
                                'contents' => $content,
                            ];
                        }
                        Db::name('UserMessage')->insertAll($user_message);
                        Db::commit();
                    }catch (\Exception $exception){
                        Db::rollback();
                    }
                }
                $result = (new UserMessagePrepare())->editData(['sended' => 1], $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => '发送成功！',
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