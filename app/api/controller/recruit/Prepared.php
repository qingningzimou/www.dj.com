<?php
/**
 * Created by PhpStorm.
 * User: PhpStorm
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\recruit;

use app\common\controller\Education;
use app\common\model\UserApply;
use app\common\model\UserApplyDetail;
use app\common\model\UserMessageBatch;
use app\common\model\UserMessagePrepare;
use appPush\AppMsg;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;
use think\facade\Cache;

class Prepared extends Education
{
    /**
     * 预录取验证 列表
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getListData(true);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['list'];

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
                foreach ($result['prepare_status_list'] as $k => $v){
                    $data['prepare_status_list'][] = ['id' => $k, 'name' => $v];
                }

                $data['admission_type_list'][] = ['id' => 1, 'name' => '派位'];
                $data['admission_type_list'][] = ['id' => 2, 'name' => '线上审核'];
                $data['admission_type_list'][] = ['id' => 3, 'name' => '到校审核'];
                $data['admission_type_list'][] = ['id' => 5, 'name' => '调剂'];
                $data['admission_type_list'][] = ['id' => 6, 'name' => '政策生'];

                $result = $this->getAllSelectByRoleList();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $school_list = $result['school_list'];

                $data['school_list'] = [];
                foreach ($school_list as $item){
                    if($item['school_attr'] == 1){
                        $data['school_list'][] = $item;
                    }
                }

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['status', '=', 1];
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['region_id', 'in', $region_ids];
                $data['message_list'] = Db::name('SysMessage')->field(['id', 'title'])->where($where)->select()->toArray();

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
                    if($house['attachment']){
                        $house['attachment'] = explode(',', str_replace('-s.', '.', $house['attachment']));
                    }

                    //租房
                    if($house['house_type'] == 2){
                        //经商情况
                        $company = Db::name('UserCompany')->field(['house_id', 'org_code', 'attachment',])
                            ->where('house_id', $result['house_id'])->where('deleted', 0)->find();
                        if($company && $company['attachment']){
                            $house['renting'][] = [
                                'title' => '营业执照',
                                'attachment' => explode(',', str_replace('-s.', '.', $company['attachment'])),
                            ];
                        }
                        //社保
                        $insurance = Db::name('UserInsurance')->field(['house_id', 'social_code', 'attachment',])
                            ->where('house_id', $result['house_id'])->where('deleted', 0)->find();
                        if($insurance && $insurance['attachment']){
                            $house['renting'][] = [
                                'title' => '社保',
                                'attachment' => explode(',', str_replace('-s.', '.', $insurance['attachment'])),
                            ];
                        }
                        //居住证
                        $residence = Db::name('UserResidence')->field(['house_id', 'live_code', 'attachment',])
                            ->where('house_id', $result['house_id'])->where('deleted', 0)->find();
                        if($residence && $residence['attachment']){
                            $house['renting'][] = [
                                'title' => '居住证',
                                'attachment' => explode(',', str_replace('-s.', '.', $residence['attachment'])),
                            ];
                        }
                        //其他附件
                        if($house['other_attachment']){
                            $house['renting'][] = [
                                'title' => '其他附件',
                                'attachment' => explode(',', str_replace('-s.', '.', $house['other_attachment'])),
                            ];
                        }
                    }

                    //置换房
                    if($house['house_type'] == 4){
                        if($house['attachment_replacement']) {
                            $house['replacement'][] = [
                                'title' => '置换协议',
                                'attachment' => explode(',', str_replace('-s.', '.', $house['attachment_replacement'])),
                            ];
                        }
                        if($house['attachment_demolition']) {
                            $house['replacement'][] = [
                                'title' => '拆迁附件',
                                'attachment' => explode(',', str_replace('-s.', '.', $house['attachment_demolition'])),
                            ];
                        }
                    }
                    unset($house['other_attachment']);
                    unset($house['attachment_replacement']);
                    unset($house['attachment_demolition']);
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
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['a.voided', '=', 0];
                $where[] = ['a.id', 'in', $id_array];

                $list = Db::name('UserApply')->alias('a')
                    ->join([
                        'deg_user_apply_detail' => 'd'
                    ], 'd.child_id = a.child_id and d.deleted = 0 ', 'LEFT')
                    ->join([
                        'deg_sys_school' => 's'
                    ], 's.id = a.public_school_id and s.deleted = 0 ', 'LEFT')
                    ->field([
                        'a.id' => 'user_apply_id',
                        's.id' => 'school_id',
                        's.school_name' => 'school_name',
                        'd.child_name' => 'child_name',
                        'd.mobile' => 'mobile',
                        'a.user_id' => 'user_id',
                    ])
                    ->where($where)->select()->toArray();

                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['voided', '=', 0];
                $where[] = ['id', 'in', $id_array];

                $result = (new UserApply())->editData(['prepared' => 0, 'prepare_status' => 2, 'refuse_count' => Db::raw('refuse_count + 1'),
                    'apply_status' => 1, 'public_school_id' => 0 ], $where);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //发消息
                $data = [];
                $data['type'] = 7;
                $this->sendAutoMessage($data, $list);

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
     * 发送消息
     * @return Json
     */
    public function sendMessage(): Json
    {
        if ($this->request->isPost()) {
            try {
                ignore_user_abort(true);
                set_time_limit(0);
                /*$param = $this->request->only(['id', 'hash']);
                //  如果数据不合法，则返回
                if (!$param['id']) {
                    throw new \Exception('ID参数错误');
                }
                $message = Db::name('SysMessage')->field(['id', 'title', 'content'])->find($param['id']);
                if(!$message){
                    throw new \Exception('找不到消息内容！');
                }*/
                $message['title'] = '预录取验证通过消息';
                $message['content'] = "（child_name）符合（area_name）就学条件，请等待入学通知。";
                $message['id'] = Db::name('SysMessage')->where('title', $message['title'])
                    ->where('deleted', 0)->value('id');
                $message['id'] = $message['id'] ?? 0;
                //  验证表单hash
                $checkHash = parent::checkHash($this->result['hash'], $this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                $result = $this->getListData(false);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }
                $hasData = $result['list'];
                if (!count($hasData)) {
                    throw new \Exception('没有操作数据！');
                }
                $chunkData = array_chunk($hasData, 10);
                $user_apply_id = [];
                $manage_id = $this->userInfo['manage_id'];
                $department_id = $this->userInfo['department_id'];
                $sys_message_id = $message['id'];
                $region = Cache::get('region');
                foreach ($chunkData as $new_list){
                    Db::startTrans();
                    try{
                        $max_id = Db::name('UserMessageBatch')->master(true)->where('deleted', 0)->max('id');
                        $temp_num = 100000000;
                        $new_num = $max_id + $temp_num + 1;
                        $sys_batch_code = 'YL'.substr($new_num,1,8);
                        //批次ID
                        $sys_batch_id = Db::name('UserMessageBatch')->insertGetId([
                            'sys_batch_code' => $sys_batch_code,
                            'department_id' => $department_id,
                            'sys_message_id' => $sys_message_id,
                            'manage_id' => $manage_id,
                        ]);
                        $user_message = [];
                        foreach ($new_list as $item){
                            $region_name = '';
                            $regionData = filter_value_one($region, 'id', $item['region_id']);
                            if (count($regionData) > 0){
                                $region_name = $regionData['region_name'];
                            }
                            $content = $message['content'];
                            $content = preg_replace('/child_name/', $item['real_name'], $content);
                            $content = preg_replace('/area_name/', $region_name, $content);
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
                            $user_apply_id[] = $item['user_apply_id'];
                        }
                        Db::name('UserMessage')->insertAll($user_message);
                        Db::commit();
                    }catch (\Exception $exception){
                        Db::rollback();
                    }
                }
                $result = (new UserApply())->editData(['prepare_status' => 1], [['id', 'in', $user_apply_id]]);
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

    /**
     * 预录取验证导出
     * @return Json
     */
    public function export(): Json
    {
        if($this->request->isPost())
        {
            try {
                $result = $this->getListData(false);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $data = $result['list'];

                foreach ($data as $k => $v){
                    unset($data[$k]['mobile']);
                    unset($data[$k]['user_id']);
                    unset($data[$k]['region_id']);
                    unset($data[$k]['school_type']);
                    unset($data[$k]['school_attr']);
                    unset($data[$k]['house_school_id']);
                    unset($data[$k]['public_school_id']);
                    unset($data[$k]['result_school_id']);
                    unset($data[$k]['admission_type']);
                    unset($data[$k]['child_age_status']);
                    unset($data[$k]['birthplace_status']);
                    unset($data[$k]['guardian_relation']);
                    unset($data[$k]['house_status']);
                    unset($data[$k]['three_syndromes_status']);
                    unset($data[$k]['student_status']);
                    unset($data[$k]['apply_status']);
                    unset($data[$k]['prepare_status']);
                    unset($data[$k]['house_type']);
                    unset($data[$k]['insurance_status']);
                    unset($data[$k]['business_license_status']);
                    unset($data[$k]['residence_permit_status']);

                    $data[$k]['student_id_card'] = "'" . $v['student_id_card'];
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }


                $headArr = ['编号', '姓名', '身份证号', '申请区域', '学校类型', '学校性质', '年龄', '户籍', '监护人关系', '房产',
                    '三证情况', '学籍情况', '房产匹配学校', '线上审核学校', '最终录取学校', '录取途径', '状态', ];
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
                        $this->excelExport('预录取验证_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('预录取验证_', $headArr, $data);
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


    //预录取 列表数据
    private function getListData($is_limit = true): array
    {
        try {
            $where = [];
            $where[] = ['a.deleted','=',0];
            $where[] = ['d.deleted','=',0];
            $where[] = ['a.voided', '=', 0];//没有作废
            $where[] = ['a.school_attr', '=', 1];//公办
            $where[] = ['a.prepared', '=', 1];//预录取
            $where[] = ['a.resulted', '=', 0];
            $where[] = ['a.public_school_id', '>', 0];
            $where[] = ['a.admission_type', 'in', [2, 3]];//线上审核、到校审核
            $where[] = ['a.prepare_status', '=', 0];//发消息后不再显示

            //公办、民办、小学、初中权限
            $school_where = $this->getSchoolWhere('a');
            $where[] = $school_where['school_attr'];
            $where[] = $school_where['school_type'];

            //区县角色隐藏申请区域
            $select_region = true;
            if ($this->userInfo['grade_id'] < $this->city_grade) {
                $select_region = false;
            }
            if($select_region) {
                if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                    $where[] = ['a.region_id', '=', $this->result['region_id'] ];
                }
            }
            $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
            $where[] = ['a.region_id', 'in', $region_ids];

            $dictionary = new FilterData();
            $getData = $dictionary->resArray('dictionary', 'SYSYLQYZ');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $filter_region = array_column($getData['data'],'dictionary_value');
            $where[] = ['a.region_id', 'in', $filter_region];

            if($this->request->has('keyword') && $this->result['keyword'])
            {
                $where[] = ['d.child_idcard|d.child_name|d.mobile','like', '%' . $this->result['keyword'] . '%'];
            }
            //录取方式
            if($this->request->has('admission_type') && $this->result['admission_type'] > 0 )
            {
                $where[] = ['a.admission_type','=', $this->result['admission_type']];
            }
            //预录取验证状态
            if($this->request->has('prepare_status') && $this->result['prepare_status'] !== '' )
            {
                $where[] = ['a.prepare_status','=', $this->result['prepare_status']];
            }
            //预录取通过学校
            if($this->request->has('school_id') && $this->result['school_id'] > 0 )
            {
                $where[] = ['a.public_school_id|a.house_school_id','=', $this->result['school_id']];
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
            //选择性导出
            if(!$is_limit){
                if($this->request->has('user_apply_id') && $this->result['user_apply_id']){
                    $ids = $this->result['user_apply_id'];
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
            /*$query = Db::name('UserMessage')->alias('m')->where('m.user_apply_id', Db::raw('a.id'))
                ->field(['contents'])->order('m.create_time', 'DESC')->limit(1)->buildSql();*/

            $data = Db::name('UserApply')->alias('a')
                ->join([
                    'deg_user_apply_detail' => 'd'
                ], 'd.child_id = a.child_id', 'LEFT')
                ->field([
                    'a.id' => 'user_apply_id',
                    'a.region_id' => 'region_id',
                    'a.user_id' => 'user_id',
                    'd.mobile' => 'mobile',
                    'a.school_type' => 'school_type',
                    'a.school_attr' => 'school_attr',
                    'a.house_school_id' => 'house_school_id',
                    'a.public_school_id' => 'public_school_id',
                    'a.result_school_id' => 'result_school_id',
                    'a.admission_type' => 'admission_type',
                    'a.apply_status' => 'apply_status',
                    //$query => 'last_message',
                    'd.child_name' => 'real_name',
                    'd.child_idcard' => 'student_id_card',
                    'd.child_age_status' => 'child_age_status',
                    'd.birthplace_status' => 'birthplace_status',
                    'd.guardian_relation' => 'guardian_relation',
                    'd.house_status' => 'house_status',
                    'd.three_syndromes_status' => 'three_syndromes_status',
                    'd.student_status' => 'student_status',
                    'a.prepare_status' => 'prepare_status',
                    'd.house_type' => 'house_type',
                    'd.insurance_status' => 'insurance_status',
                    'd.business_license_status' => 'business_license_status',
                    'd.residence_permit_status' => 'residence_permit_status',
                ])
                ->where($where)
                ->order('a.region_id', 'ASC')->order('a.result_school_id', 'ASC')->order('a.id', 'ASC');
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

                $list[$k]['student_age_name'] = $student_age_name;
                $list[$k]['birthplace_status_name'] = $birthplace_status_name;
                $list[$k]['relation_name'] = $relation_name;
                $list[$k]['house_status_name'] = $house_status_name;
                $list[$k]['three_syndromes_name'] = $three_syndromes_name;
                $list[$k]['student_status_name'] = $student_status_name;

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

                $list[$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type']);

                $apply_status_name = isset($result['apply_status_list'][$v['apply_status']]) ? $result['apply_status_list'][$v['apply_status']] : '-';
                $list[$k]['apply_status_name'] = $apply_status_name;
                $prepare_status_name = isset($result['prepare_status_list'][$v['prepare_status']]) ? $result['prepare_status_list'][$v['prepare_status']] : '-';
                $list[$k]['prepare_status_name'] = $prepare_status_name;
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
    private function getAdmissionTypeName($type): string
    {
        $name = '-';
        switch ($type){
            case 1:
                $name = '派位';
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
    private function excelExport($fileName = '', $headArr = [], $data = []) {

        $fileName       .= "_" . date("Y_m_d", time());
        $spreadsheet    = new Spreadsheet();
        $objPHPExcel    = $spreadsheet->getActiveSheet();
        $key = ord("A"); // 设置表头

        foreach ($headArr as $v) {
            $colum = chr($key);
            $objPHPExcel->setCellValue($colum . '1', $v);
            //$spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
            $key += 1;
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
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(60);

        $column = 2;
        foreach ($data as $key => $rows) { // 行写入
            $span = ord("A");
            foreach ($rows as $keyName => $value) { // 列写入
                if($keyName == 'student_id_card' ){
                    //$objPHPExcel->setCellValue(chr($span) . $column, '\'' . $value);
                    $spreadsheet->getActiveSheet()->setCellValueExplicit(chr($span) . $column, $value, DataType::TYPE_STRING);
                }else{
                    $objPHPExcel->setCellValue(chr($span) . $column, $value);
                }
                $span++;
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