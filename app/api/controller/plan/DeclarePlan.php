<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\plan;

use app\common\controller\Education;
use app\common\model\PlanApply as model;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;
use think\facade\Cache;

class DeclarePlan extends Education
{

    /**
     * 申报学位列表【教管会】
     * @return \think\response\Json
     */
    public function getList()
    {
        try {
            if (isset($this->userInfo['central_id']) && $this->userInfo['central_id'] > 0 ) {
                $central_id = $this->userInfo['central_id'];
            }else{
                throw new \Exception('教管会管理员所属教管会ID设置错误');
            }

            $where = [];
            $where[] = ['a.deleted', '=', 0];
            if ($this->request->has('search') && $this->result['search'] != '') {
                $where[] = ['a.remark|p.plan_name', 'like', '%' . $this->request->param('search') . '%'];
            }
            if ($this->request->has('plan_id') && $this->result['plan_id'] > 0 ) {
                $where[] = ['a.plan_id', '=', $this->request->param('plan_id')];
            }
            $where[] = ['a.central_id', '=', $central_id];
            $where[] = ['c.deleted', '=', 0];
            $where[] = ['c.disabled', '=', 0];

            $list = Db::table("deg_plan_apply")
                ->alias('a')
                ->join([
                    'deg_plan' => 'p'
                ], 'p.id = a.plan_id' )
                ->join([
                    'deg_central_school' => 'c'
                ], 'c.id = a.central_id', 'RIGHT')
                ->field([
                    'a.id' => 'id',
                    'c.central_name' => 'central_name',
                    'p.plan_name' => 'plan_name',
                    'a.apply_total' => 'apply_total',
                    'a.spare_total' => 'spare_total',
                    'a.create_time' => 'create_time',
                    'a.remark' => 'remark',
                    'a.status' => 'status',
                ])->where($where)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            foreach ((array)$list['data'] as $k => $v){
                //$list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $list['data'][$k]['status_name'] = $this->getStatusName($v['status']);
            }

            $res = [
                'code' => 1,
                'data' => $list
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ];
        }
        return parent::ajaxReturn($res);
    }

    /**
     * 申报详细
     * @return Json
     */
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {

                if ($this->result['id'] <= 0) {
                    throw new \Exception('申报ID错误');
                }
                $data = (new model())->where('id', $this->result['id'])->find();

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

    /**
     * 新增计划
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                if (isset($this->userInfo['central_id']) && $this->userInfo['central_id'] > 0 ) {
                    $central_id = $this->userInfo['central_id'];
                }else{
                    throw new \Exception('教管会管理员所属教管会ID设置错误');
                }

                $data = $this->request->only([
                    'plan_id',
                    'central_id' => $central_id,
                    'apply_total',
                    'school_id',
                    //'create_time' => time(),
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                if($data['plan_id'] <= 0){
                    throw new \Exception('请选择招生计划');
                }
                if(!preg_match("/^[1-9][0-9]*$/" , $data['apply_total'])){
                    throw new \Exception('申请学位数量请填写正整数');
                }
                if($data['apply_total'] > 999999){
                    throw new \Exception('申请学位数量太大');
                }

                unset($data['hash']);
                $result = (new model())->addData($data);
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
     * 导入
     * @return Json
     */
    public function actImport()
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

                if ($fileExtendName != 'csv' && $fileExtendName != 'xls' && $fileExtendName != 'xlsx') {
                    throw new \Exception('必须为excel表格xls或xlsx格式');
                }
                if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                    // 有Csv,Xls和Xlsx格式三种
                    if ($fileExtendName == 'csv') {
                        $objReader = IOFactory::createReader('Csv');
                    } elseif ($fileExtendName == 'xls') {
                        $objReader = IOFactory::createReader('Xls');
                    } else {
                        $objReader = IOFactory::createReader('Xlsx');
                    }

                    $filename = $_FILES['file']['tmp_name'];
                    $objPHPExcel = $objReader->load($filename);
                    $sheet = $objPHPExcel->getSheet(0);   //excel中的第一张sheet
                    $highestRow = $sheet->getHighestRow();       // 取得总行数

                    //定义$data，循环表格的时候，找出已存在的六年级学生信息。
                    $data = [];
                    $repeat = [];
                    //循环读取excel表格，整合成数组。model
                    $year_start = strtotime(date('Y',time()).'-01-01 00:00:00');//今年
                    $hasData = (new model())->where([['school_id', 'in', $school_ids],
                         ['create_time', '>', $year_start], ['deleted', '=',0] ])
                        ->column('school_id');

                    //从第三行开始读取数据
                    for ($j = 3; $j <= $highestRow; $j++) {
                        $plan_name = $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue();
                        $school_name = $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue();
                        $apply_total = $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue();
                        $remark = $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getValue();

                        if($plan_name == '' && $school_name == '' && intval($apply_total) == 0) continue;

                        $tmp[$j - 3] = [
                            'plan_name' => $plan_name,
                            'school_name' => $school_name,
                            'apply_total' => $apply_total,
                            'remark' => $remark,
                        ];

                        array_push($data, $tmp[$j - 3]);
                    }

                    $error = [];
                    // 过滤上传数据中重复的学校名称
                    $tmp = $data;
                    $data = array_unique_value($data, 'school_name');
                    $repeat_tmp = array_values(array_diff(array_column($tmp, 'school_name'), array_column($data, 'school_name')));

                    if(count($repeat_tmp) > 0) {
                        $error[] = ['msg' => '表格重复学校名称', 'data' => $repeat_tmp];
                    }

                    $successNum = 0;
                    $row = 3;
                    foreach ($data as $item) {
                        if($item['plan_name'] == ''){
                            $error[] = '第' . $row . '行招生计划为空';
                            continue;
                        }
                        if($item['school_name'] == ''){
                            $error[] = '第' . $row . '行学校名称为空';
                            continue;
                        }
                        if(intval($item['apply_total']) == 0){
                            $error[] = '第' . $row . '申请学位为零';
                            continue;
                        }

                        $plan = Db::name('plan')->where([['plan_name', '=', $item['plan_name']],
                            ['plan_time', '=', date('Y')], ['deleted', '=', 0]])->findOrEmpty();
                        if(!$plan){
                            $error[] = '第' . $row . '行招生计划为【' . $item['plan_name'] . '】不存在';
                            continue;
                        }
                        $school = Db::name('sys_school')->where([['school_name', '=', $item['school_name']],
                            ['deleted','=',0], ['directly', '=', 0], ['disabled', '=', 0] ])->findOrEmpty();
                        if(!$school){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】不存在';
                            continue;
                        }
                        if(!in_array($school['id'], $school_ids)){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】不属于该教管会';
                            continue;
                        }
                        if(in_array($school['id'], $hasData)){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】已申报计划';
                            continue;
                        }
                        if($item['apply_total'] <= 0){
                            $error[] = '第' . $row . '行学校名称为【' . $item['school_name'] . '】的申请学位数量小于等于零';
                            continue;
                        }

                        $apply_data = [
                            'plan_id' => $plan['id'],
                            'school_id' => $school['id'],
                            'apply_total' => $item['apply_total'],
                            'remark' => $item['remark'],
                        ];
                        $result = (new model())->addData($apply_data);
                        if($result['code'] == 0){
                            $error[] = $result['msg'];
                            continue;
                        }

                        $successNum++;
                        $row++;
                    }

                    //导入错误信息写入缓存
                    Cache::set('importError_'.$this->userInfo['manage_id'], $error);

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
    public function getImportError(){
        $res = [
            'code' => 1,
            'data' => Cache::get('importError_'.$this->userInfo['manage_id'])
        ];
        return parent::ajaxReturn($res);
    }

    /**
     * 下拉列表
     * @return Json
     */
    public function getSelectList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['plan_time', '=', date('Y')];

                $plan_list = Db::table("deg_plan")
                    ->alias('p')
                    ->field(['id', 'plan_name'])->where($where)->select()->toArray();

                //公办、民办、小学、初中权限
                $_where = [];
                $school_where = $this->getSchoolWhere();
                $_where[] = $school_where['school_attr'];
                $_where[] = $school_where['school_type'];
                $school_list = [];
                if ( isset($this->userInfo['central_id']) && $this->userInfo['central_id'] > 0 ) {
                    $central_id = $this->userInfo['central_id'];
                    $school_list = Db::name("sys_school")
                        ->where([['central_id', '=', $central_id], ['deleted','=',0],
                            ['directly', '=', 0]])->where($_where)
                        ->field(['id', 'school_name'])->select()->toArray();
                }else{
                    throw new \Exception('教管会管理员所属教管会ID设置错误');
                }

                $res = [
                    'code' => 1,
                    'plan_list' => $plan_list,
                    'school_list' => $school_list,
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

    private function getStatusName($status)
    {
        $status_name = '';
        switch ($status){
            case 0:
                $status_name = '待审';
                break;
            case 1:
                $status_name = '通过';
                break;
            case 2:
                $status_name = '拒绝';
                break;
        }
        return $status_name;
    }

}