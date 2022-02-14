<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */

namespace app\api\controller\plan;

use app\common\controller\Education;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Db;
use think\response\Json;
use app\common\model\PlanApply as model;
use app\common\validate\basic\PlanApply as validate;

class PlanSchool extends Education
{
    /**
     * 审批学位列表【学校】
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());

                $where = [];
                if ($this->request->has('plan_id') && $this->result['plan_id'] > 0) {
                    $where[] = ['a.plan_id', '=', $this->result['plan_id']];
                }
                if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                    $where[] = ['s.region_id', '=', $this->result['region_id']];
                }
                //学校ID
                if ($this->request->has('school_id') && $this->result['school_id'] > 0) {
                    $where[] = ['a.school_id', '=', $this->result['school_id']];
                }
                //审批状态
                if ($this->request->has('status') && $this->result['status'] !== '') {
                    $where[] = ['a.status', '=', $this->result['status']];
                }
                //学校性质
                if ($this->request->has('school_attr') && $this->result['school_attr'] > 0 ) {
                    $where[] = ['s.school_attr', '=', $this->result['school_attr']];
                }
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['s.deleted', '=', 0];
                $where[] = ['s.disabled', '=', 0];

                if($this->userInfo['grade_id'] < $this->city_grade){
                    //教管会权限
                    if($this->userInfo['grade_id'] == $this->middle_grade){
                        if (isset($this->userInfo['central_id']) && $this->userInfo['central_id'] > 0 ) {
                            $central_id = $this->userInfo['central_id'];
                            $where[] = ['s.central_id','=', $central_id];
                            $where[] = ['s.directly','=', 0];
                        }else{
                            throw new \Exception('教管会管理员所属教管会ID设置错误');
                        }

                        $res_data['middle_auth'] = [
                            'status_name' => '教管会权限',
                            'grade_status' => 1
                        ];
                    }else{
                        if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1 ) {
                            $region_id = $this->userInfo['region_id'];
                            $where[] = ['s.region_id','=', $region_id];
                            $where[] = ['s.central_id','=', 0];
                            $where[] = ['s.directly','=', 0];
                        }else{
                            throw new \Exception('区局管理员所属区县ID设置错误');
                        }

                        $res_data['region_auth'] = [
                            'status_name' => '区局权限',
                            'grade_status' => 1
                        ];
                    }
                }else{
                    $where[] = ['s.central_id','=', 0];
                }
                //公办、民办、小学、初中权限
                $school_where = $this->getSchoolWhere('s');
                $where[] = $school_where['school_attr'];
                $where[] = $school_where['school_type'];

                $data = Db::name("plan_apply")->alias('a')
                    ->join([
                        'deg_plan' => 'p'
                    ], 'p.id = a.plan_id')
                    ->join([
                        'deg_sys_school' => 's'
                    ], 's.id = a.school_id', 'RIGHT')
                    ->join([
                        'deg_sys_region' => 'r'
                    ], 'r.id = s.region_id')
                    ->field([
                        'a.id',
                        'p.plan_name',
                        'r.region_name',
                        's.school_name' => 'school_name',
                        'a.apply_total',
                        'a.spare_total',
                        'a.create_time',
                        'a.remark',
                        'a.status',
                    ])
                    ->where($where)->order('a.create_time', 'DESC')->master(true)
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                foreach ((array)$data['data'] as $k => $v) {
                    //$data['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                    $data['data'][$k]['status_name'] = $this->getStatusName($v['status']);
                }

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
        }else{}
    }

    /**
     * 审批学位审核操作
     * @return Json
     */
    public function actAudit(): Json
    {
        if ($this->request->isPost()) {
            //开始事务
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'status',
                    'spare_total',
                    'remark',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'], $this->result['user_id']);
                if ($checkHash['code'] == 0) {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'audit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                //审核通过
                if($data['status'] == 1) {
                    //教管会角色审批 ，教管会学位足够才能批复
                    if ($this->userInfo['grade_id'] == $this->middle_grade) {
                        if (isset($this->userInfo['central_id']) && $this->userInfo['central_id'] > 0) {
                            $plan_id = Db::name('plan_apply')->where('id', $data['id'])->value('plan_id');
                            $central_id = $this->userInfo['central_id'];
                            //教管会当前计划批复的总学位
                            $total_spare = Db::name('PlanApply')
                                //->field('plan_id, SUM(spare_total) as spare_total')
//                                ->group('central_id')
                                ->where('central_id', $central_id)->where('status', 1)
                                ->where('deleted', 0)->where('plan_id', $plan_id)->sum('spare_total');

                            if($total_spare == 0){
                                throw new \Exception('教管会没有学位批复');
                            }

                            $school_ids = Db::name('SysSchool')->where([['deleted', '=', 0], ['directly', '=', 0],
                                ['disabled', '=', 0], ['central_id', '=', $central_id] ])->column('id');
                            //教管会下的教学点已批复的总学位
                            $total_school_spare = Db::name('PlanApply')
                                ->group('school_id')
                                ->where('school_id', 'in', $school_ids)->where('plan_id', $plan_id)
                                ->where('status', 1)->where('deleted', 0)->sum('spare_total');
                            $reply_total = $total_school_spare + intval($data['spare_total']);
                            if($total_spare < $reply_total){
                                $surplus_total = $total_spare - $total_school_spare;
                                throw new \Exception('教管会的学位不足，剩余学位' . $surplus_total);
                            }
                        } else {
                            throw new \Exception('教管会管理员所属教管会ID设置错误');
                        }
                    }
                }

                $update = $data;
                //unset($update['id']);
                unset($update['hash']);
                /*Db::name('plan_apply')->where('id', $data['id'])
                    ->update($update);*/
                $result = (new model())->editData($update);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //学校学位统计
                if($data['status'] == 1) {
                    $school_id = Db::name('PlanApply')
                        ->where('id', $data['id'])->value('school_id');
                    $result = $this->getSchoolDegreeStatistics($school_id);
                    if ($result['code'] == 0) {
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

    /**
     * 删除
     * @return Json
     */
    public function actDelete()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'deleted' => 1
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'delete');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //学校学位统计
                $school_id = Db::name('PlanApply')
                    ->where('id', $data['id'])->value('school_id');
                $result = $this->getSchoolDegreeStatistics($school_id);
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
     * 获取下拉列表信息 招生计划、区县、学校、教管会
     * @return Json
     */
    public function getSelectList(): Json
    {
        if ($this->request->isPost()) {
            $result = $this->getAllSelectByRoleList();

            $code = $result['code'];
            unset($result['police_list']);
            unset($result['central_list']);
            unset($result['code']);

            if($this->userInfo['grade_id'] != $this->middle_grade) {
                //教学点去掉
                foreach ($result['school_list'] as $k => $v) {
                    if ($v['central_id'] > 0) {
                        unset($result['school_list'][$k]);
                    }
                }
            }
            $result['status_list'] = [ ['id' => 0, 'name' => '待审'], ['id' => 1, 'name' => '通过'], ['id' => 2, 'name' => '拒绝'], ];

            $dictionary = new FilterData();
            $getData = $dictionary->resArray('dictionary', 'SYSXXXZ');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            foreach ($getData['data'] as $value){
                if($this->userInfo['public_school_status'] == 1 && $value['dictionary_value'] == 1) {
                    $result['school_attr'][] = [
                        'id' => $value['dictionary_value'],
                        'attr_name' => $value['dictionary_name']
                    ];
                }
                if($this->userInfo['civil_school_status'] == 1 && $value['dictionary_value'] == 2) {
                    $result['school_attr'][] = [
                        'id' => $value['dictionary_value'],
                        'attr_name' => $value['dictionary_name']
                    ];
                }
            }

            $res = [
                'code' => $code,
                'data' => $result,
            ];
            return parent::ajaxReturn($res);
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    private function getStatusName($status): string
    {
        $name = "未审核";
        switch ($status){
            case 0:
                $name = "未审核";
                break;
            case 1:
                $name = "审核通过";
                break;
            case 2:
                $name = "审核不通过";
                break;
            default:
                break;
        }

        return $name;
    }

    /**
     * 导入【教管会独有】
     * @return Json
     */
    public function actImport(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                set_time_limit(0);
                $file_size = $_FILES['file']['size'];
                if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                    throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
                }

                //教管会所管学校
                $school_ids = [];
                $role_school = $this->getSchoolIdsByRole();
                if($role_school['code'] == 0 ){
                    throw new \Exception($role_school['msg']);
                }
                if($role_school['bounded']  ){
                    $school_ids = $role_school['school_ids'];
                }

                //限制上传表格类型
                $fileExtendName = substr(strrchr($_FILES['file']["name"], '.'), 1);

                if (/*$fileExtendName != 'csv' &&*/ $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    /*if ($fileExtendName == 'csv') {
                        $objReader = IOFactory::createReader('Csv');
                    } else*/if ($fileExtendName == 'xls') {
                        $objReader = IOFactory::createReader('Xls');
                    } else {
                        $objReader = IOFactory::createReader('Xlsx');
                    }

                    $filename = $_FILES['file']['tmp_name'];
                    $objPHPExcel = $objReader->load($filename);
                    $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                    $highestRow = $sheet->getHighestRow();       // 取得总行数

                    //导入模板检测
                    $check_res = $this->checkTemplate($objPHPExcel);
                    if($check_res['code'] == 0){
                        throw new \Exception($check_res['msg']);
                    }

                    //定义$data，循环表格的时候，找出已存在的六年级学生信息。
                    $data = [];
                    $repeat = [];
                    //循环读取excel表格，整合成数组。model
                    /*$year_start = strtotime(date('Y',time()).'-01-01 00:00:00');//今年
                    $hasData = (new model())->where([['school_id', 'in', $school_ids],
                         ['create_time', '>', $year_start], ['deleted', '=',0] ])
                        ->column('school_id');*/

                    //从第三行开始读取数据
                    for ($j = 3; $j <= $highestRow; $j++) {
                        $plan_name = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                        $school_name = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();
                        $apply_total = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();
                        $remark = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();

                        //if($plan_name == '' && $school_name == '' && intval($apply_total) == 0) continue;

                        $tmp[$j - 3] = [
                            'plan_name' => $plan_name,
                            'school_name' => trim($school_name),
                            'apply_total' => $apply_total,
                            'remark' => $remark,
                        ];

                        array_push($data, $tmp[$j - 3]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的学校名称
                    $tmp = $data;
                    //$data = array_unique_value($data, 'school_name');
                    //$school_name_data = array_column($data,'school_name');
                    //$repeat_school_name = array_count_values($school_name_data);
                    //$repeat_tmp = array_values(array_diff(array_column($tmp, 'school_name'), array_column($data, 'school_name')));

                    /*if(count($repeat_tmp) > 0) {
                        $error[] = ['msg' => '表格重复学校名称', 'data' => $repeat_tmp];
                    }*/

                    $successNum = 0;
                    foreach ($data as $key => $item) {
                        $row = $key + 3;
                        if($item['plan_name'] == ''){
                            $error[] = '第' . $row . '行招生计划为空';
                            continue;
                        }
                        if($item['school_name'] == ''){
                            $error[] = '第' . $row . '行学校名称为空';
                            continue;
                        }
                        if(!preg_match("/^[1-9][0-9]*$/" , $item['apply_total'])){
                            $error[] = '第' . $row . '行【申请数量】请填写正整数';
                            continue;
                        }
                        if($item['apply_total'] > 999999){
                            $error[] = '第' . $row . '行【申请数量】数量太大';
                            continue;
                        }

                        $plan = Db::name('plan')->where([['plan_name', '=', $item['plan_name']],
                            ['plan_time', '=', date('Y')], ['deleted', '=', 0]])->findOrEmpty();
                        if(!$plan){
                            $error[] = '第' . $row . '行招生计划为【' . $item['plan_name'] . '】不存在';
                            continue;
                        }
                        $school = Db::name('sys_school')->where([['school_name', '=', $item['school_name']],
                            ['deleted','=',0], ['disabled', '=', 0] ])->findOrEmpty();
                        if(!isset($school['id'])){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】不存在';
                            continue;
                        }
                        if($school['school_type'] != $plan['school_type']){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】学校类型和计划类型不相符';
                            continue;
                        }
                        if(!in_array($school['id'], $school_ids)){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】无权限管理';
                            continue;
                        }
                        /*if(in_array($school['id'], $hasData)){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】已申报计划';
                            continue;
                        }*/
                        if($item['apply_total'] < 0){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】的申请数量小于零';
                            continue;
                        }
                        /*if($repeat_school_name[$item['school_name']] > 1) {
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】重复';
                            continue;
                        }*/

                        $apply_data = [
                            'plan_id' => $plan['id'],
                            'school_id' => $school['id'],
                            'apply_total' => $item['apply_total'],
                            'remark' => $item['remark'] ?? '',
                        ];
                        $result = (new model())->addData($apply_data);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }

                        $successNum++;
                    }
                    $error[] = '成功导入'.$successNum.'条数据';
                    //导入错误信息写入缓存
                    Cache::set('importError_planSchool_'.$this->userInfo['manage_id'], $error);

                    $res = [
                        'code' => 1,
                        'success_num' => $successNum,
                        'repeat_num' => $repeat,
                        'error' => $error,
                    ];
                    Db::commit();
                }else{
                    throw new \Exception('获取上传文件失败');
                }
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
     * 获取导入错误信息
     * @return Json
     */
    public function getImportError(): Json
    {
        $res = [
            'code' => 1,
            'data' => Cache::get('importError_planSchool_'.$this->userInfo['manage_id'])
        ];
        return parent::ajaxReturn($res);
    }

    private function checkTemplate($objPHPExcel): array
    {
        $title = $objPHPExcel->getActiveSheet()->getCell("A1")->getValue();
        if($title != '襄阳市教管会申报学位信息表') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        $plan_name = $objPHPExcel->getActiveSheet()->getCell("A2")->getValue();
        if($plan_name != '招生计划') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $school_name = $objPHPExcel->getActiveSheet()->getCell("B2")->getValue();
        if($school_name != '学校名称') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $apply_total = $objPHPExcel->getActiveSheet()->getCell("C2")->getValue();
        if($apply_total != '申请数量') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }
        $remark = $objPHPExcel->getActiveSheet()->getCell("D2")->getValue();
        if($remark != '备注') {
            return ['code' => 0, 'msg' => '导入模板错误！'];
        }

        return  ['code' => 1, 'msg' => '模板正确！'];
    }

}