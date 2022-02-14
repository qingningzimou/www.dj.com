<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\OnlineFace;
use app\common\model\UserApplyDetail;
use app\common\model\UserApplyStatus;
use app\common\model\UserHouse;
use app\common\model\UserSupplement;
use app\common\model\UserSupplementAttachment;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use app\common\model\UserApply as model;
use app\mobile\validate\Apply as validate;
use think\facade\Db;

class OnlineStateRun extends Education
{
    /***********************************************************************************/
    //-----------------------------线上面审页面--------------------------------
    /***********************************************************************************/
    /**
     * 线上面审列表
     * @return \think\response\Json
     */
    public function getOnlineList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getListByType(1, true);
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
     * 公办招生页面资源
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
                foreach ($view['data']['house_list'] as $k => $v){
                    $data['house_type_list'][] = ['id' => $k, 'name' => $v];
                }
                foreach ($view['data']['code_list'] as $k => $v){
                    $data['code_type_list'][] = ['id' => $k, 'name' => $v];
                }
                //$result['data']['house_type_list'] = $view['data']['house_list'];
                $result['data']['code_type_list'] = $view['data']['code_list'];
                $result['data']['code_react_list'] = $view['data']['code_react_list'];

                $result['data']['status_list'] = ['0' => '未补充', '1' => '已补充'];

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
     * 面审
     * @param id 申请信息ID
     * @return Json
     */
    public function actAudit(): Json
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
                if($apply['public_school_id'] != $school_id){
                    throw new \Exception('意愿学校不属于本学校');
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
                    $update['admission_type'] = 2;//线上面审
                    $update['prepared'] = 1;//预录取
                    $update['apply_status'] = 2;//公办预录取

                    if(!isset($data['remark']) || $data['remark'] == ''){
                        $data['remark'] = "线上审核通过";
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
                        $param['type'] = 1;
                        $param['area_name'] = $region_name;
                        $this->sendAutoMessage($param);
                    }
                }
                //面审拒绝
                if($data['status'] == 2) {
                    $update['audited'] = 2;
                    $update['public_school_id'] = 0;
                    $update['refuse_count'] = Db::raw('refuse_count + 1');

                    if(!isset($data['remark']) || $data['remark'] == ''){
                        $data['remark'] = "线上审核不通过";
                    }
                    $data['remark'] = "拒绝原因--" . $data['remark'];

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
                        $data['remark'] = "补充资料";
                    }

                    //发消息
                    $param['type'] = 3;
                    $this->sendAutoMessage($param);
                }
                $result = (new model())->editData($update);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                if($data['status'] == 2) {
                    //发消息
                    $school_name = Db::name('SysSchool')->where('id', $school_id)->value('school_name');
                    $param['school_name'] = $school_name;

                    //三次被拒
                    $refuse_count = Db::name('UserApply')->where('id', $data['id'])->value('refuse_count');
                    if($refuse_count >= 3){
                        //发消息
                        $param['type'] = 4;
                        $this->sendAutoMessage($param);
                    }else{
                        $param['type'] = 2;
                        $this->sendAutoMessage($param);
                    }
                }

                //面审状态
                if($data['status'] == 1 || $data['status'] == 2){
                    $face = Db::name('OnlineFace')->where('apply_id', $this->result['id'])
                        ->where('deleted', 0)->order('id', 'DESC')->find();
                    if($face){
                        $result = (new OnlineFace())->editData(['status' => $data['status'], 'id' => $face['id'] ]);
                        if ($result['code'] == 0) {
                            throw new \Exception($result['msg']);
                        }
                    }
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
                    'msg' => '操作成功'
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

    /**
     * 线上面审信息导出
     * @return Json
     */
    public function exportOnline(): Json
    {
        if($this->request->isPost())
        {
            try {
//                $result = $this->getListByType(1, false);
//                if($result['code'] == 0){
//                    throw new \Exception($result['msg']);
//                }
//                $data = $result['list'];

                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('学校管理员学校ID设置错误');
                }
                $school = Db::name('SysSchool')->field('id, region_id, school_type, school_attr')->find($school_id);
                if(!$school){
                    throw new \Exception('学校管理员学校ID关联学校不存在');
                }

                $where = [];
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['d.deleted', '=', 0];
                $where[] = ['a.voided', '=', 0];//没有作废
                $where[] = ['a.school_attr', '=', $school['school_attr']];
                $where[] = ['a.school_type', '=', $school['school_type']];
                $where[] = ['a.prepared', '=', 0];//没有预录取
                $where[] = ['a.resulted', '=', 0];//没有录取
                $where[] = ['a.signed', '=', 0];//没有入学报到
                $where[] = ['a.public_school_id', '=', $school_id];//线上面审意愿学校

                //关键字搜索
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['d.child_name|d.child_idcard|d.mobile','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('admission_type') && $this->result['admission_type'] > 0 )
                {
                    $where[] = ['a.admission_type','=', $this->result['admission_type'] ];
                }
                //补充资料状态
                if($this->request->has('status') && $this->result['status'] !== '' )
                {
                    $where[] = ['p.status','=', $this->result['status'] ];
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

                $list = Db::name('UserApply')->alias('a')
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
                    ], 's.user_apply_id = a.id and s.deleted = 0 and a.ancestor_id > 0 ', 'LEFT')
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
                        'a.primary_lost_status' => 'primary_lost_status',
                        'a.create_time' => 'apply_create_time',
                        $school_query => 'graduation_school_id',
                        $relation_query => 'relation',
                        $parent_name => 'parent_name',
                        'IF(a.specialed = 1, CONCAT(d.mobile, "_", a.child_id), "")' => 'special_card',
                        'CASE s.auto_check_ancestor WHEN -1 THEN "未比对成功" WHEN 0 THEN "未比对成功" WHEN 1 THEN "属实" WHEN 2 THEN "不属实" ELSE "-" END' => 'check_ancestor_name',
                        'd.false_region_id' => 'false_region_id',
                        'a.fill_time' => 'fill_time',

                    ])
                    ->where($where)->select()->toArray();

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

                    $list[$k]['admission_type_name'] = $this->getAdmissionTypeName($v['admission_type']);

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
                    //'student_api_address' => '户籍地址',
                    //'student_api_area' => '户籍行政划分区域',
                    'birthplace_status_name' => '户籍',
                    //'parent_name' => '填报监护人姓名',
                    'fill_relation_name' => '填报监护人关系',
                    'relation_name' => '监护人关系对比',
                    //'check_ancestor_name' => '三代同堂关系',
                    //'student_api_relation' => '与户主关系',
                    'house_type_name' => '房产类型',
                    'house_address' => '房产填报地址',
                    //'api_house_address' => '大数据房产对比地址',
                    'house_status_name' => '房产',
                    //'three_syndromes_name' => '三证情况',
                    //'student_status_name' => '学籍情况',
                    //'apply_school_name' => '申报学校',
                    'house_school_name' => '房产匹配学校',
                    'insurance_area' => '社保所在区域',
                    'company_area' => '营业执照所在区域',
                    'residence_area' => '居住证所在区域',
                    //'graduation_name' => '填报毕业学校',
                    //'six_school_name' => '小升初毕业学校',
                    'public_school_name' => '线上审核学校',
                    //'result_school_name' => '最终录取学校',
                    //'admission_type_name' => '录取途径',
                    //'apply_status_name' => '状态',
                    'supplement_status_name' => '补充资料状态',
                    'last_message' => '最后一条信息',
                    //'prepare_status_name' => '预录取验证状态',
                    //'verification_name' => '派位验证状态',
                    //'primary_lost_status' => '民办落选状态',
                    'fill_time' => '选报时间',
                    //'factor_region_status' => '条件符合情况',
                ];

                $data = [];
                foreach($headArr as $key => $value){
                    foreach($list as $_key => $_value){
                        foreach($_value as $__key => $__value){
                            if($key == $__key){
                                $data[$_key][$__key] = $__value;
                            }
                        }
                    }
                }

                if(count($data) == 0){
                    return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
                }

                if(count($data) > 5000){
                    $total = count($data);
                    $count_excel = ceil($total / 5000);
                    for ($i = 0; $i < $count_excel; $i++){
                        $offset = $i * 5000;
                        $length = ($i + 1) * 5000;
                        if($i == ($count_excel - 1)){
                            $length = $total;
                        }
                        $data = array_slice($data, $offset, $length, true);
                        $this->excelExport('线上审核_' . ($i + 1) . '_', $headArr, $data);
                    }
                }else {
                    $this->excelExport('线上审核', $headArr, $data);
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

                if($keyName == 'student_id_card'){
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
            $where[] = ['a.deleted', '=', 0];
            $where[] = ['d.deleted', '=', 0];
            $where[] = ['a.voided', '=', 0];//没有作废
            $where[] = ['a.school_attr', '=', $school['school_attr']];
            $where[] = ['a.school_type', '=', $school['school_type']];

            //线上面审
            if ($type == 1) {
                $where[] = ['a.prepared', '=', 0];//没有预录取
                $where[] = ['a.resulted', '=', 0];//没有录取
                $where[] = ['a.signed', '=', 0];//没有入学报到
                $where[] = ['a.public_school_id', '=', $school_id];//线上面审意愿学校
            }
            //线下面审
            if ($type == 2) {
                $where[] = ['a.prepared', '=', 0];//预录取
                $where[] = ['a.resulted', '=', 0];//没有录取
                $where[] = ['a.signed', '=', 0];//没有入学报到
                $where[] = ['a.region_id', '=', $school['region_id'] ];//公办学校所属区县

                //无搜索条件 不显示数据
                if(isset($this->result['keyword']) && empty($this->result['keyword']) ){
                    $where[] = [Db::raw(1), '=', 0];
                }
            }
            //预录取
            if ($type == 3) {
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 0];//录取
                $where[] = ['a.signed', '=', 0];//没有入学报到
                $where[] = ['a.public_school_id|a.house_school_id', '=', $school_id];//公办预录取学校
            }
            //录取
            if ($type == 4) {
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.offlined', '=', 0];//不是线下录取
                $where[] = ['a.admission_type', '>', 0];
                $where[] = ['a.result_school_id', '=', $school_id];//最终录取学校
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
            //补充资料状态
            if($this->request->has('status') && $this->result['status'] !== '' )
            {
                $where[] = ['s.status','=', $this->result['status'] ];
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
                ->join([
                    'deg_user_supplement' => 's'
                ], 's.user_apply_id = a.id and s.deleted = 0 ', 'LEFT')
                ->field([
                    'a.id' => 'id',
                    'a.house_school_id' => 'house_school_id',
                    'd.child_name' => 'real_name',
                    'd.child_idcard' => 'id_card',
                    'd.mobile' => 'mobile',
                    'd.birthplace_status' => 'birthplace_status',
                    'd.guardian_relation' => 'relation',
                    'd.house_status' => 'house_status',
                    'd.three_syndromes_status' => 'three_syndromes_status',
                    'd.student_status' => 'student_status',
                    'a.fill_time' => 'fill_time',
                    $query => 'last_message',
                    'a.admission_type' => 'admission_type',
                    'CASE a.signed WHEN 1 THEN "是" ELSE "否" END' => 'sign_name',
                    'CASE a.signed WHEN 1 THEN a.signe_time ELSE "-" END' => 'signe_time',
                    'CASE s.status WHEN 1 THEN "资料已补充" WHEN 0 THEN "资料补充中" ELSE "-" END' => 'supplement_status',
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

                foreach ($list['data'] as $k => $v){
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
            case 5: $name = '指派'; break;
            case 6: $name = '政策生'; break;
        }
        return $name;
    }


}