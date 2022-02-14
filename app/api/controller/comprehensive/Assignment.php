<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\comprehensive;

use app\common\controller\Education;
use app\common\model\AssignmentSettingRegion;
use app\common\model\UserApply;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use app\common\model\AssignmentSetting as model;
use app\common\validate\comprehensive\AssignmentSetting as validate;
use think\facade\Db;

class Assignment extends Education
{
    /**
     * 派位设置列表信息
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['assignment.deleted','=',0];
                $where[] = ['r.deleted','=',0];

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['name','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('region_id') && $this->result['region_id']) {
                        $where[] = ['r.region_id', '=', $this->result['region_id']];
                    }
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['r.region_id', 'in', $region_ids];

                $data = Db::name('AssignmentSetting')->alias('assignment')
                    ->join([
                        'deg_assignment_setting_region' => 'r'
                    ], 'assignment.id = r.assignment_id', 'LEFT')
                    ->field([
                        'assignment.id',
                        'assignment.name',
                        'assignment.house_type',
                        'assignment.area_birthplace_status',
                        'assignment.main_area_birthplace_status',
                        'assignment.area_house_status',
                        'assignment.main_area_house_status',
                        'assignment.area_business_status',
                        'assignment.area_residence_status',
                        'assignment.area_social_insurance_status',
                        'r.region_id',
                        'r.single_double',
                    ])
                    ->where($where)->order('r.region_id', 'ASC')->order('assignment.id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSSJQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $houseData = $dictionary->resArray('dictionary', 'SYSFCLX');
                if(!$houseData['code']){
                    throw new \Exception($houseData['msg']);
                }
                //房产类型
                $house_type_list = [];
                foreach ($houseData['data'] as $item){
                    $house_type_list[$item['dictionary_value']] = $item['dictionary_name'];
                }
                $data['house_type_list'] = $house_type_list;

                //单双符
                $allocation_data = $dictionary->resArray('dictionary', 'SYSPWSZ');
                if(!$allocation_data['code']){
                    throw new \Exception($allocation_data['msg']);
                }
                $allocation_type_list = [];
                foreach ($allocation_data['data'] as $item){
                    $allocation_type_list[$item['dictionary_value']] = $item['dictionary_name'];
                }
                $data['allocation_type_list'] = $allocation_type_list;

                foreach ($data['data'] as $k => $v){
                    $data['data'][$k]['area_birthplace_status'] = $v['area_birthplace_status'] == 1 ? true : false;
                    $data['data'][$k]['main_area_birthplace_status'] = $v['main_area_birthplace_status'] == 1 ? true : false;
                    $data['data'][$k]['area_house_status'] = $v['area_house_status'] == 1 ? true : false;
                    $data['data'][$k]['main_area_house_status'] = $v['main_area_house_status'] == 1 ? true : false;
                    $data['data'][$k]['area_business_status'] = $v['area_business_status'] == 1 ? true : false;
                    $data['data'][$k]['area_residence_status'] = $v['area_residence_status'] == 1 ? true : false;
                    $data['data'][$k]['area_social_insurance_status'] = $v['area_social_insurance_status'] == 1 ? true : false;
                    $data['data'][$k]['single_double_text'] = isset($allocation_type_list[$v['single_double']]) ? $allocation_type_list[$v['single_double']] : '-';
                    $data['data'][$k]['house_type_text'] = $house_type_list[$v['house_type']];

                    if($v['region_id'] == 1){
                        $data['data'][$k]['region_name'] = '市教育局';
                    }else{
                        $regionData = filter_value_one($region, 'id', $v['region_id']);
                        if (count($regionData) > 0){
                            $data['data'][$k]['region_name'] = $regionData['region_name'];
                        }
                    }
                }

                //区县角色隐藏发布机构
                $is_edit = true;
                if($this->userInfo['grade_id'] < $this->city_grade){
                    $is_edit = false;
                }
                $data['is_edit'] = $is_edit;

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
     * 获取指定六年级信息
     * @param id 六年级ID
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if(!$this->request->has('region_id') || $this->result['region_id'] == 0){
                    throw new \Exception('区县ID参数错误');
                }

                $data = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();
                $single_double = Db::name('AssignmentSettingRegion')->where('assignment_id', $this->result['id'])
                    ->where('region_id', $this->result['region_id'])->value('single_double');
                $data['single_double'] = $single_double;

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
     * 获取区域下拉列表
     * @return \think\response\Json
     */
    public function getRegionList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['disabled','=', 0];
                $where[] = ['deleted','=', 0];
                //$where[] = ['parent_id','>', 0];

                $data = Db::name('SysRegion')->where($where)->field(['id', 'region_name',])->select();
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
     * 新增
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'name',
                    'house_type',
                    'area_birthplace_status',
                    'main_area_birthplace_status',
                    'area_house_status',
                    'main_area_house_status',
                    'area_business_status',
                    'area_residence_status',
                    'area_social_insurance_status',
                    'single_double',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                unset($data['hash']);
                $result = (new model())->addData($data, 1);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                //区级详细设置
                $assignment_id = $result['insert_id'];
                $region = Cache::get('region');
                foreach ($region as $item){
                    $detail = [];
                    $detail['assignment_id'] = $assignment_id;
                    $detail['region_id'] = $item['id'];
                    $detail['single_double'] = $data['single_double'];

                    $result = (new AssignmentSettingRegion())->addData($detail);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success'),
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
     * 编辑
     * @return Json
     */
    public function actEdit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'region_id',
                    'name',
                    'house_type',
                    'area_birthplace_status',
                    'main_area_birthplace_status',
                    'area_house_status',
                    'main_area_house_status',
                    'area_business_status',
                    'area_residence_status',
                    'area_social_insurance_status',
                    'single_double',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                if($data['single_double'] == '-'){
                    $data['single_double'] = 0;
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if(!$data['region_id']){
                    throw new \Exception('区县ID参数错误');
                }

                $single_double = $data['single_double'];
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    unset($data['single_double']);
                }

                unset($data['hash']);
                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $where = [];
                $where[] = ['region_id', '=', $data['region_id']];
                $where[] = ['assignment_id', '=', $data['id']];
                $result = (new AssignmentSettingRegion())->editData(['single_double' => $single_double], $where);
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
                    'region_id',
                    'deleted' => 1,
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'delete');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if(!$data['region_id']){
                    throw new \Exception('区县ID参数错误');
                }

                if($data['region_id'] == 1) {
                    $result = (new model())->editData(['id' => $data['id'], 'deleted' => 1 ]);
                    if ($result['code'] == 0) {
                        throw new \Exception($result['msg']);
                    }
                }

                //可以单独删除某个区县的设置 市教育局删除则所有区县删除
                $where = [];
                $where[] = ['assignment_id', '=', $data['id']];
                if($data['region_id'] > 1) {
                    $where[] = ['region_id', '=', $data['region_id']];
                }
                $result = (new AssignmentSettingRegion())->editData(['deleted' => 1], $where);
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
     * 开始派位
     * @return Json
     */
    public function actAssignment(){
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                //区级不用选择区县ID
                $where = [];
                if ($this->userInfo['grade_id'] < $this->city_grade) {
                    if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1){
                        $where[] = ['a.region_id', '=', $this->userInfo['region_id'] ];
                    }else{
                        throw new \Exception('管理员区县ID设置错误');
                    }
                    $data = $this->request->only(['school_type', ]);
                    $school_type = $data['school_type'];
                    if($school_type != 1 && $school_type != 2){
                        throw new \Exception('学校类型错误');
                    }
                    $where[] = ['a.school_type', '=', $school_type ];
                }else{
                    $data = $this->request->only(['region_id', 'school_type']);
                    $region_id = $data['region_id'];
                    if(!$region_id){
                        throw new \Exception('请选择区县ID');
                    }
                    $school_type = $data['school_type'];
                    if($school_type != 1 && $school_type != 2){
                        throw new \Exception('学校类型错误');
                    }
                    $where[] = ['a.region_id', '=', $region_id ];
                    $where[] = ['a.school_type', '=', $school_type ];
                }


                $where[] = ['a.deleted', '=', 0 ];
                $where[] = ['d.deleted', '=', 0 ];
                $where[] = ['a.voided', '=', 0 ];
                $where[] = ['a.prepared', '=', 0 ];//没有预录取
                $where[] = ['a.school_attr', '=', 1];//公办
                $where[] = ['d.single_double', '>', 0];//单双符
                $where[] = ['a.house_school_id', '>', 0];//房产匹配学校
                $where[] = ['d.single_double_verification', '=', 0];//单双符验证符合
                $where[] = ['a.assignmented', '=', 0];//未派位

                $list = Db::name('UserApply')->alias('a')
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
                        'd.child_name' => 'real_name',
                        'd.child_idcard' => 'student_id_card',
                        'd.child_age_status' => 'child_age_status',
                        'd.birthplace_status' => 'birthplace_status',
                        'd.guardian_relation' => 'guardian_relation',
                        'd.house_status' => 'house_status',
                        'd.three_syndromes_status' => 'three_syndromes_status',
                        'd.student_status' => 'student_status',
                    ])
                    ->where($where)
                    ->select()->toArray();

                foreach ($list as $k => $v){
                    $data = [];
                    $data['id'] = $v['id'];
                    $data['prepared'] = 1;//预录取
                    $data['assignmented'] = 1;//已派位
                    $data['apply_status'] = 2;//公办预录取
                    $data['admission_type'] = 1;//录取方式【派位】
                    $data['public_school_id'] = $v['house_school_id'];

                    $result = (new UserApply())->editData($data);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
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


    public function areaBirthplaceStatus(){
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'status',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['id'] = $postData['id'];
                $data['area_birthplace_status'] = $postData['status'];
                $result = (new model())->editData($data);
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

    public function mainAreaBirthplaceStatus(){
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'status',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['id'] = $postData['id'];
                $data['main_area_birthplace_status'] = $postData['status'];
                $result = (new model())->editData($data);
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

    public function areaHouseStatus(){
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'status',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['id'] = $postData['id'];
                $data['area_house_status'] = $postData['status'];
                $result = (new model())->editData($data);
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

    public function mainAreaHouseStatus(){
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'status',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['id'] = $postData['id'];
                $data['main_area_house_status'] = $postData['status'];
                $result = (new model())->editData($data);
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

    public function areaBusinessStatus(){
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'status',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['id'] = $postData['id'];
                $data['area_business_status'] = $postData['status'];
                $result = (new model())->editData($data);
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

    public function areaResidenceStatus(){
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'status',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['id'] = $postData['id'];
                $data['area_residence_status'] = $postData['status'];
                $result = (new model())->editData($data);
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

    public function areaSocialInsuranceStatus(){
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'status',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data['id'] = $postData['id'];
                $data['area_social_insurance_status'] = $postData['status'];
                $result = (new model())->editData($data);
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

}