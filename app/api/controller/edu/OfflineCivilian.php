<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\UserApplyDetail;
use app\common\model\UserApplyStatus;
use app\common\model\UserHouse;
use app\common\model\UserSupplement;
use app\common\model\UserSupplementAttachment;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use app\common\model\UserApply as model;
use app\mobile\validate\Apply as validate;
use think\facade\Db;

class OfflineCivilian extends Education
{
    /***********************************************************************************/
    //----------------------------民办到校审核页面--------------------------------------
    /***********************************************************************************/
    /**
     * 民办到校审核列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getListByType(2, true);
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
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    /**
     * 公办线下面审页面资源
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
                $house_type_list = [];
                foreach ($result['data']['house_type_list'] as $k => $v){
                    $house_type_list[] = ['id' => $k, 'name' => $v];
                }
                unset($result['data']['house_type_list']);
                $result['data']['house_type_list'] = $house_type_list;
                //补充资料类目
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['disabled', '=', 0];

                $list = Db::name('SysSupplementCategory')->where($where)->select()->toArray();
                foreach ($list as $item){
                    $result['data']['supplement'][$item['id']] = $item['supplement_name'];
                }

                $view = $this->getTypeData();
                if($view['code'] == 0){
                    throw new \Exception($view['msg']);
                }
                //$result['data']['house_type_list'] = $view['data']['house_list'];
                $result['data']['code_type_list'] = $view['data']['code_list'];
                $result['data']['code_react_list'] = $view['data']['code_react_list'];

                $res = [
                    'code' => 1,
                    'data' => $result['data'],
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
     * 详细信息
     * @param id 申请信息ID
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = $this->getChildDetails($this->result['id'],1);
                if($data['code'] == 0){
                    throw new \Exception($data['msg']);
                }
                $res = [
                    'code' => 1,
                    'data' => $data['data']
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
     * 公办线下面审 审核
     * @return Json
     */
    public function auditOffline(): Json
    {
        if ($this->request->isPost()) {
            //开始事务
            Db::startTrans();
            try {
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }

                $data = $this->request->only([
                    'id',
                    'status',
                    'category_ids',
                    'remark',
                    'hash'
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'], $this->result['user_id']);
                if ($checkHash['code'] == 0) {
                    throw new \Exception($checkHash['msg']);
                }

                $apply = Db::name('UserApply')->find($this->result['id']);
                if( !$apply ){
                    throw new \Exception('申请资料不存在');
                }
                $detail = Db::name('UserApplyDetail')->where('child_id', $apply['child_id'])
                    ->where('deleted', 0)->find();
                if( !$detail ){
                    throw new \Exception('学生详细信息不存在');
                }
                //消息发送参数
                $param = [];
                $param['user_id'] = $apply['user_id'];
                $param['user_apply_id'] = $data['id'];
                $param['mobile'] = $detail['mobile'];
                $param['child_name'] = $detail['child_name'];
                $param['school_id'] = $school_id;

                $update['id'] = $data['id'];
                //面审通过
                if($data['status'] == 1) {
                    $update['audited'] = 1;
                    $update['prepared'] = 1;//预录取
                    $update['resulted'] = 1;//录取
                    $update['admission_type'] = 3;//到校面审
                    $update['result_school_id'] = $school_id;//意愿学校
                    $update['apply_status'] = 5;//民办已录取

                    if(!isset($data['remark']) || $data['remark'] == ''){
                        $data['remark'] = "民办到校审核通过";
                    }

                    //不需要预录取验证的区县 自动发消息
                    $dictionary = new FilterData();
                    $getData = $dictionary->resArray('dictionary', 'SYSYLQYZ');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $filter_region = array_column($getData['data'],'dictionary_value');
                    if(!in_array($apply['region_id'], $filter_region )) {
                        $region_name = '';
                        $region = Cache::get('region');
                        $regionData = filter_value_one($region, 'id', $apply['region_id']);
                        if (count($regionData) > 0){
                            $region_name = $regionData['region_name'];
                        }
                        //发消息
                        $param['type'] = 8;
                        $param['area_name'] = $region_name;
                        $this->sendAutoMessage($param);
                    }
                }
                //到校审核拒绝
                if($data['status'] == 2) {
                    $update['audited'] = 2;

                    if(!isset($data['remark']) || $data['remark'] == ''){
                        $data['remark'] = "民办到校审核不通过";
                    }
                    $data['remark'] = "拒绝原因--" . $data['remark'];

                    //发消息
                    $school_name = Db::name('SysSchool')->where('id', $school_id)->value('school_name');
                    $param['school_name'] = $school_name;
                    $param['type'] = 9;
                    $this->sendAutoMessage($param);
                }
                //补充资料
                if($data['status'] == 3) {
                    $category_ids = $data['category_ids'];
                    $cate_id_array = explode(',', $category_ids);
                    if (count($cate_id_array) == 0) {
                        throw new \Exception('请勾选需要补充的资料');
                    }
                    //补充资料类目
                    $supplement_cate = Db::name('SysSupplementCategory')->where('disabled', 0)
                        ->where('deleted', 0)->select()->toArray();
                    $supplement_cate_array = [];
                    foreach ($supplement_cate as $item){
                        $supplement_cate_array[$item['id']] = $item['supplement_name'];
                    }

                    $update['supplemented'] = 1;

                    //学生补充资料
                    $supplement_model = new UserSupplement();
                    $supplement = Db::name('UserSupplement')->where('user_apply_id', $data['id'])
                        ->where('deleted', 0)->find();
                    if($supplement){
                        $supplement['remark'] = $data['remark'] ?? '';
                        $supplement['child_id'] = $apply['child_id'];
                        $supplement['user_id'] = $apply['user_id'];
                        $supplement['status'] = 0;
                        $result = $supplement_model->editData($supplement);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }
                        $supplement_id = $supplement['id'];
                    }else {
                        $supplement = [];
                        $supplement['user_apply_id'] = $data['id'];
                        $supplement['child_id'] = $apply['child_id'];
                        $supplement['user_id'] = $apply['user_id'];
                        $supplement['remark'] = $data['remark'] ?? '';
                        $result = $supplement_model->addData($supplement, 1);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }
                        $supplement_id = $result['insert_id'];
                    }

                    foreach ($cate_id_array as $cate_id){
                        $attachment = new UserSupplementAttachment();
                        $detail = Db::name('UserSupplementAttachment')->where('user_apply_id', $data['id'])->where('cate_id', $cate_id)
                            ->where('supplement_id', $supplement_id)->where('deleted', 0)->find();
                        if($detail){
                            $result = $attachment->editData(['id' => $detail['id'], 'status' => 0 ]);
                            if ($result['code'] == 0) {
                                throw new \Exception($result['msg']);
                            }
                        }else{
                            $attachment_data = [
                                'user_apply_id' => $data['id'],
                                'supplement_id' => $supplement_id,
                                'cate_id' => $cate_id,
                                'cate_name' => $supplement_cate_array[$cate_id],
                            ];
                            $result = $attachment->addData($attachment_data);
                            if ($result['code'] == 0) {
                                throw new \Exception($result['msg']);
                            }
                        }
                    }
                    if(!isset($data['remark']) || $data['remark'] == ''){
                        $data['remark'] = "民办补充资料";
                    }

                    //发消息
                    $param['type'] = 10;
                    $this->sendAutoMessage($param);
                }
                $result = (new model())->editData($update);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                //审核日志
                $log = [];
                $log['user_apply_id'] = $data['id'];
                $log['admin_id'] = $this->userInfo['manage_id'];
                $log['school_id'] = $school_id;
                $log['remark'] = $data['remark'];
                $log['status'] = $data['status'];
                Db::name('UserApplyAuditLog')->save($log);

                $res = [
                    'code' => 1,
                    'data' => $data
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
     * 更改状态
     * @param id 申请信息ID
     * @return Json
     */
    public function updateStatus(): Json
    {
        if ($this->request->isPost()) {
            //开始事务
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'status',
                    'field',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $field_arr = Db::name('UserApplyStatus')->getTableFields();
                if( !in_array($data['field'], $field_arr ) ) {
                    throw new \Exception('非法字段');
                }
                /*if($data['field'] != 'check_relation') {
                    if (!in_array($data['field'] . "_relation", $field_arr)) {
                        throw new \Exception('非法字段');
                    }
                }*/

//                $operation_status = Db::name('UserApplyStatus')->field([
//                    'auto_check_relation',
//                    'auto_check_birthplace_area',
//                    'auto_check_birthplace_main_area',
//                    'auto_check_house_area',
//                    'auto_check_house_main_area',
//                    'auto_check_company',
//                    'auto_check_insurance',
//                    'auto_check_residence',
//                ])->where('deleted', 0)
//                    ->where('user_apply_id', $data['id'])->find();
//                if($operation_status) {
//                    if ($operation_status['auto_check_relation'] == 0) {
//                        $operation_status['check_relation'] = true;
//                    }
//                    if ($operation_status['auto_check_birthplace_area'] <= 0) {
//                        $operation_status['check_birthplace_area'] = true;
//                    }
//                    if ($operation_status['auto_check_birthplace_main_area'] <= 0) {
//                        $operation_status['check_birthplace_main_area'] = true;
//                    }
//                    if ($operation_status['auto_check_house_area'] <= 0) {
//                        $operation_status['check_house_area'] = true;
//                    }
//                    if ($operation_status['auto_check_house_main_area'] <= 0) {
//                        $operation_status['check_house_main_area'] = true;
//                    }
//                    if ($operation_status['auto_check_company'] <= 0) {
//                        $operation_status['check_company'] = true;
//                    }
//                    if ($operation_status['auto_check_insurance'] <= 0) {
//                        $operation_status['check_insurance'] = true;
//                    }
//                    if ($operation_status['auto_check_residence'] <= 0) {
//                        $operation_status['check_residence'] = true;
//                    }
//                }
//                if(!isset($operation_status[$data['field']]) ){
//                    throw new \Exception('信息已比对，不能更改');
//                }

                $status = $data['status'];
//                $relation_status = intval($data['status']);
                if($status == 0){
                    $status = -1;
                }
                $update[$data['field']] = $status;
//                if($data['field'] == 'check_relation') {
//                    $update[$data['field']] = $relation_status;
//                }

                $result = (new UserApplyStatus())->editData($update, ['user_apply_id' => $data['id'] ]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => '更新成功'
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
     * 更改房产
     * @return Json
     */
    public function updateHouse(): Json
    {
        if ($this->request->isPost()) {
            //开始事务
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'apply_id',
                    'house_id',
                    'type',
                    'val',
                ]);
                //  验证器验证请求的数据是否合法
                if($data['apply_id'] <= 0){
                    throw new \Exception('申请资料ID错误');
                }
                if($data['house_id'] <= 0){
                    throw new \Exception('房产信息ID错误');
                }

                $operation_status = Db::name('UserApplyStatus')->field([
                    'auto_check_house_area',
                    'auto_check_house_main_area',
                ])->where('deleted', 0)
                    ->where('user_apply_id', $data['apply_id'])->find();
                if($operation_status['auto_check_house_area'] > 0 || $operation_status['auto_check_house_main_area'] > 0){
                    throw new \Exception('房产信息已比对，不能更改');
                }
                if($data['val'] == 0 || $data['val'] == ''){
                    throw new \Exception('请选择信息');
                }

                //学校端不在更改房产类型和证件类型
                if($data['type'] == 1) {
                    $update['id'] = $data['house_id'];
                    $update['house_type'] = $data['val'];
                    $update['code_type'] = 0;
                    $result = (new UserHouse())->editData($update);
                    if ($result['code'] == 0) {
                        throw new \Exception($result['msg']);
                    }

                    $result = (new UserApplyDetail())->editData(['house_type' => $data['val'] ], [['user_apply_id', '=', $data['apply_id']], ]);
                    if ($result['code'] == 0) {
                        throw new \Exception($result['msg']);
                    }
                }else if($data['type'] == 2) {
                    $update['id'] = $data['house_id'];
                    $update['code_type'] = $data['val'];
                    $result = (new UserHouse())->editData($update);
                    if ($result['code'] == 0) {
                        throw new \Exception($result['msg']);
                    }
                }

                $res = [
                    'code' => 1,
                    'msg' => '更新成功'
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

    private function getListByType($type, $is_limit = true): array
    {
        try {
            if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                $school_id = $this->userInfo['school_id'];
            } else {
                return ['code' => 0, 'msg' => '学校管理员学校ID设置错误' ];
            }
            $school = Db::name('SysSchool')->field('id, region_id, school_type, school_attr')->find($school_id);
            if(!$school){
                return ['code' => 0, 'msg' => '学校管理员学校ID关联学校不存在' ];
            }

            $where = [];
            //到校审核
            if ($type == 2) {
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['d.deleted', '=', 0];
                $where[] = ['a.voided', '=', 0];//没有作废

                //无搜索条件 不显示数据
                if(isset($this->result['keyword']) && empty($this->result['keyword']) ){
                    $where[] = [Db::raw(1), '=', 0];
                }
            }

            //关键字搜索
            if($this->request->has('keyword') && $this->result['keyword'])
            {
                $where[] = ['d.child_name|d.child_idcard|d.mobile','like', '%' . $this->result['keyword'] . '%'];
            }
            if($this->request->has('admission_type') && $this->result['admission_type'] > 0 )
            {
                $where[] = ['a.admission_type','=', $this->result['admission_type'] ];
            }
            //最后一条消息
            $query = Db::name('UserMessage')->alias('m')->where('m.user_apply_id', Db::raw('a.id'))
                ->field(['contents'])->order('m.create_time', 'DESC')->limit(1)->buildSql();
            //六年级毕业学校
            $school_query = Db::name('SixthGrade')->where('id_card', Db::raw('d.child_idcard'))
                ->where('id_card', '<>', '')->where('deleted', 0)
                ->field(['graduation_school_id'])->limit(1)->buildSql();

            $list = Db::name('UserApply')->alias('a')
                ->join([
                    'deg_user_apply_detail' => 'd'
                ], 'd.child_id = a.child_id', 'LEFT')
                ->field([
                    'a.id' => 'id',
                    'a.region_id',
                    'a.school_attr',
                    'a.school_type',
                    'a.prepared',
                    'a.resulted',
                    'a.house_school_id' => 'house_school_id',
                    'd.child_name' => 'real_name',
                    'd.child_idcard' => 'id_card',
                    'd.mobile' => 'mobile',
                    'd.birthplace_status' => 'birthplace_status',
                    'd.guardian_relation' => 'relation',
                    'd.house_status' => 'house_status',
                    'd.three_syndromes_status' => 'three_syndromes_status',
                    'd.student_status' => 'student_status',
                    'd.child_age_status' => 'child_age_status',
                    'a.fill_time' => 'fill_time',
                    'a.create_time' => 'create_time',
                    $query => 'last_message',
                    'a.admission_type' => 'admission_type',
                    'CASE a.signed WHEN 1 THEN "是" ELSE "否" END' => 'sign_name',
                    'CASE a.signed WHEN 1 THEN a.signe_time ELSE "-" END' => 'signe_time',
                    'd.house_type' => 'house_type',
                    'd.insurance_status' => 'insurance_status',
                    'd.business_license_status' => 'business_license_status',
                    'd.residence_permit_status' => 'residence_permit_status',
                    $school_query => 'graduation_school_id',
                ])
                ->where($where)
                ->order('a.signed', 'ASC')->order('a.id', 'ASC');

            $result = $this->getViewData();
            if($result['code'] == 0){
                return ['code' => 0, 'msg' => $result['msg'] ];
            }
            $result = $result['data'];

            $schools = Cache::get('school');
            if($is_limit) {
                $list = $list->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $merge = 0;
                $empty_data = [
                    'admission_type_name' => '',
                    'birthplace_status_name' => '',
                    'business_license_status' => '',
                    'create_time' => '',
                    'house_status_name' => '',
                    'id_card' => '',
                    'last_message' => '',
                    'mobile' => '',
                    'real_name' => '',
                    'relation_name' => '',
                    'sign_name' => '',
                    'signe_time' => '',
                    'student_status_name' => '',
                    'three_syndromes_name' => '',
                    'house_school_name' => '',
                    'six_school_name' => '',
                ];

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary', 'SYSDXSHQY');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $filter_region = array_column($getData['data'],'dictionary_value');
                $getData = $dictionary->resArray('dictionary', 'SYSDXSHNLYZQY');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $filter_age_region = array_column($getData['data'],'dictionary_value');

                foreach ($list['data'] as $k => $v){
                    if($v['region_id'] != $school['region_id']){
                        $list['data'][$k] = $empty_data;
                        $list['data'][$k]['msg'] = '【' . $this->result['keyword']. '】申报区域非本区域！';
                        $merge = 1;
                        continue;
                    }
                    if($v['school_attr'] != $school['school_attr']){
                        $list['data'][$k] = $empty_data;
                        $list['data'][$k]['msg'] = '【' . $this->result['keyword']. '】申报为公办学校！';
                        $merge = 1;
                        continue;
                    }
                    if($v['school_type'] != $school['school_type']){
                        $list['data'][$k] = $empty_data;
                        $list['data'][$k]['msg'] = '【' . $this->result['keyword']. '】申报的学校类型不一致！';
                        $merge = 1;
                        continue;
                    }
                    if ($v['prepared'] == 1 && $v['resulted'] == 1) {
                        $list['data'][$k] = $empty_data;
                        $list['data'][$k]['msg'] = '【' . $this->result['keyword'] . '】申报的信息已录取！';
                        $merge = 1;
                        continue;
                    }
                    if( !in_array($v['region_id'], $filter_region ) ) {
                        if ($v['prepared'] == 1) {
                            $list['data'][$k] = $empty_data;
                            $list['data'][$k]['msg'] = '【' . $this->result['keyword'] . '】申报的信息已预录取！';
                            $merge = 1;
                            continue;
                        }
                    }
                    if($v['school_type'] == 1){
                        if( in_array($v['region_id'], $filter_age_region) && $v['child_age_status'] == 2 ) {
                            $list['data'][$k] = $empty_data;
                            $list['data'][$k]['msg'] = '【' . $this->result['keyword'] . '】申报人年龄不足！';
                            $merge = 1;
                            continue;
                        }
                    }

                    $birthplace_status_name = isset($result['birthplace_list'][$v['birthplace_status']]) ? $result['birthplace_list'][$v['birthplace_status']] : '-';
                    $relation_name = isset($result['relation_list'][$v['relation']]) ? $result['relation_list'][$v['relation']] : '-';
                    $house_status_name = isset($result['house_list'][$v['house_status']]) ? $result['house_list'][$v['house_status']] : '-';
                    //$three_syndromes_name = isset($result['three_syndromes_list'][$v['three_syndromes_status']]) ? $result['three_syndromes_list'][$v['three_syndromes_status']] : '-';
                    $student_status_name = isset($result['school_roll_list'][$v['student_status']]) ? $result['school_roll_list'][$v['student_status']] : '-';
                    $three_syndromes_name = $this->getThreeSyndromesName($v);


                    $list['data'][$k]['birthplace_status_name'] = $birthplace_status_name;
                    $list['data'][$k]['relation_name'] = $relation_name;
                    $list['data'][$k]['house_status_name'] = $house_status_name;
                    $list['data'][$k]['three_syndromes_name'] = $three_syndromes_name;
                    $list['data'][$k]['student_status_name'] = $student_status_name;
                    $list['data'][$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type']);

                    $list['data'][$k]['house_school_name'] = '-';
                    $houseSchoolData = filter_value_one($schools, 'id', $v['house_school_id']);
                    if (count($houseSchoolData) > 0){
                        $list['data'][$k]['house_school_name'] = $houseSchoolData['school_name'];
                    }
                    $list['data'][$k]['six_school_name'] = '-';
                    if($v['graduation_school_id']) {
                        $graduationSchoolData = filter_value_one($schools, 'id', $v['graduation_school_id']);
                        if (count($graduationSchoolData) > 0) {
                            $list['data'][$k]['six_school_name'] = $graduationSchoolData['school_name'];
                        }
                    }
                }
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
                $list['resources'] = $res_data;
                $list['merge'] = $merge;

            }else{
                $list = $list->select()->toArray();

                foreach ($list as $k => $v){
                    $birthplace_status_name = isset($result['birthplace_list'][$v['birthplace_status']]) ? $result['birthplace_list'][$v['birthplace_status']] : '-';
                    $relation_name = isset($result['relation_list'][$v['relation']]) ? $result['relation_list'][$v['relation']] : '-';
                    $house_status_name = isset($result['house_list'][$v['house_status']]) ? $result['house_list'][$v['house_status']] : '-';
                    //$three_syndromes_name = isset($result['three_syndromes_list'][$v['three_syndromes_status']]) ? $result['three_syndromes_list'][$v['three_syndromes_status']] : '-';
                    $student_status_name = isset($result['school_roll_list'][$v['student_status']]) ? $result['school_roll_list'][$v['student_status']] : '-';
                    $three_syndromes_name = $this->getThreeSyndromesName($v);

                    $list[$k]['birthplace_status_name'] = $birthplace_status_name;
                    $list[$k]['relation_name'] = $relation_name;
                    $list[$k]['house_status_name'] = $house_status_name;
                    $list[$k]['three_syndromes_name'] = $three_syndromes_name;
                    $list[$k]['student_status_name'] = $student_status_name;
                    $list[$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type'] );
                }
            }

            return ['code' => 1, 'list' => $list ];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //录取方式
    private function getAdmissionTypeName($type): string
    {
        $name = '-';
        switch ($type){
            case 1: $name = '派位'; break;
            case 2: $name = '线上审核'; break;
            case 3: $name = '到校审核'; break;
            case 4: $name = '派遣'; break;
            case 5: $name = '调剂'; break;
            case 6: $name = '政策生'; break;
        }
        return $name;
    }
}