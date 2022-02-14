<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller;

use app\common\controller\Education;
use app\common\model\SysAddressIntact;
use app\common\model\WorkAreaReport;
use app\common\model\WorkMunicipalReport;
use think\facade\Filesystem;
use think\facade\Lang;
use think\response\Json;
use app\common\model\Schools as model;
use think\facade\Db;
use think\facade\Cache;
use dictionary\FilterData;

class Home extends Education
{

    /**
     * 首页
     * @return Json
     */
    public function index(): Json
    {
        set_time_limit(0);
        if ($this->request->isPost()) {
            try {
                //初始化
                /*$result = $this->initStatictics();
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }*/

                $grade = 0;
                $data = [];
                //人数统计
                $data['population'] = [];
                //待处理
                $data['pending'] = [];
                //招生时间
                $data['enrollment_time'] = [];
                //工作进度
                $data['work_list'] = [];
                $role_school_ids = [];
                if ( $this->userInfo['grade_id'] >= $this->middle_grade) {
                    $role_school = $this->getSchoolIdsByRole();
                    if($role_school['code'] == 0){
                        throw new \Exception($role_school['msg']);
                    }
                    if($role_school['bounded'] ){
                        $role_school_ids = $role_school['school_ids'];
                    }
                }
                //招生时间
                $plan_time = $this->getPlanTime();

                //市级权限
                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    $grade = 4;
                    $data['grade'] = $grade;
                    //人数统计
                    $result = $this->getUserApplyPopulation();
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                    $data['population'] = $result['data'];
                    /*$data['population'] = [
                        [
                            'name' => 'total',
                            'title' => '总填报人数',
                            'num' => 0,//总填报人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'add_day',
                            'title' => '日新增人数',
                            'num' => 0,//日新增人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'not_admission',
                            'title' => '未录取人数',
                            'num' => 0,//未录取人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'beforehand_admission',
                            'title' => '预录取人数',
                            'num' => 0,//预录取人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'admission',
                            'title' => '录取人数',
                            'num' => 0,//录取人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                    ];*/

                    //待处理
                    $message_count = $this->getMessageCount();
                    $region_entrance = $this->getRegionEntrance();
                    $return_apply = $this->getRefundCount();
                    $data['pending'] = [
                        'message_count' => $message_count,//消息文案未审批
                        'region_entrance' => $region_entrance,//申请修改入学区域未审批
                        'return_apply' => $return_apply,//退款申请
                    ];
                    //招生时间
                    $data['enrollment_time'] = [
                        'primary_plan' => $plan_time['primary_plan'],
                        'middle_plan' => $plan_time['middle_plan'],
                    ];

                    //工作进度
                    $schedule = $this->getCitySchedule();
                    if($schedule['code'] == 0){
                        throw new \Exception($schedule['msg']);
                    }
                    $data['work_list'] = $schedule['list'];
                }
                //区县权限
                if ($this->userInfo['grade_id'] < $this->city_grade && $this->userInfo['grade_id'] > $this->middle_grade) {
                    $grade = 3;
                    $data['grade'] = $grade;
                    //人数统计
                    if ( isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1 ) {
                        $region_id = $this->userInfo['region_id'];
                        $result = $this->getUserApplyPopulation($region_id);
                        if($result['code'] == 0){
                            throw new \Exception($result['msg']);
                        }
                        $data['population'] = $result['data'];
                    }else{
                        throw new \Exception('区级管理员所属区县ID设置错误');
                    }
                    /*$data['population'] = [
                        [
                            'name' => 'total',
                            'title' => '总填报人数',
                            'num' => 0,//总填报人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'add_day',
                            'title' => '日新增人数',
                            'num' => 0,//日新增人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'not_admission',
                            'title' => '未录取人数',
                            'num' => 0,//未录取人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'beforehand_admission',
                            'title' => '预录取人数',
                            'num' => 0,//预录取人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'admission',
                            'title' => '录取人数',
                            'num' => 0,//录取人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                    ];*/
                    //待处理
                    $region_res = $this->getRegionStatistics();
                    if($region_res['code'] == 0){
                        throw new \Exception($region_res['msg']);
                    }
                    $degree = $this->getDegreeCount($grade, $region_res['school_ids'], $region_res['central_ids']);
                    if($degree['code'] == 0){
                        throw new \Exception($degree['msg']);
                    }
                    $region_entrance = $this->getRegionEntrance();
                    $return_apply = $this->getRefundCount();
                    $data['pending'] = [
                        'degree_count' => $degree['degree_count'],//学位审批
                        'region_entrance' => $region_entrance,//申请修改入学区域审批
                        'return_apply' => $return_apply,//退款申请
                    ];
                    //招生时间
                    $data['enrollment_time'] = [
                        'primary_plan' => $plan_time['primary_plan'],
                        'middle_plan' => $plan_time['middle_plan'],
                    ];
                    //工作进度
                    //$schedule = $this->getSchedule($region_res['school_ids'], 0);
                    $schedule = $this->getAreaSchedule($role_school_ids, true);
                    if($schedule['code'] == 0){
                        throw new \Exception($schedule['msg']);
                    }
                    $data['work_list'] = $schedule['list'];
                    $data['work_list']['summation'] = $schedule['total'];
                }
                //教管会权限
                if ( $this->userInfo['grade_id'] == $this->middle_grade) {
                    $grade = 2;
                    $data['grade'] = $grade;
                    //人数统计
                    if ( isset($this->userInfo['central_id']) && $this->userInfo['central_id'] > 0 ) {
                        $central_id = $this->userInfo['central_id'];
                        $result = $this->getUserApplyPopulation(0, $central_id);
                        if($result['code'] == 0){
                            throw new \Exception($result['msg']);
                        }
                        $data['population'] = $result['data'];
                    }else{
                        throw new \Exception('教管会管理员所属教管会ID设置错误');
                    }
                    /*$data['population'] = [
                        [
                            'name' => 'total',
                            'title' => '总填报人数',
                            'num' => 0,//总填报人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                        [
                            'name' => 'admission',
                            'title' => '录取人数',
                            'num' => 0,//录取人数
                            'tooltip' => [
                                'public_num' => 0,//公办人数
                                'private_num' => 0,//民办人数
                            ],
                        ],
                    ];*/
                    //待处理
                    $degree = $this->getDegreeCount($grade, $role_school_ids, []);
                    if($degree['code'] == 0){
                        throw new \Exception($degree['msg']);
                    }
                    $data['pending'] = [
                        'degree_count' => $degree['degree_count'],//学位审批
                    ];
                    //招生时间
                    $data['enrollment_time'] = [
                        'primary_plan' => $plan_time['primary_plan'],
                        'middle_plan' => $plan_time['middle_plan'],
                    ];
                    //工作进度
                    $schedule = $this->getAreaSchedule($role_school_ids, true);
                    if($schedule['code'] == 0){
                        throw new \Exception($schedule['msg']);
                    }
                    $data['work_list'] = $schedule['list'];
                    $data['work_list']['summation'] = $schedule['total'];
                }
                //学校权限
                if ( $this->userInfo['grade_id'] == $this->school_grade) {
                    $grade = 1;
                    $data['grade'] = $grade;

                    if(isset( $this->userInfo['school_id'] ) && $this->userInfo['school_id'] > 0 ){
                        $school_id = $this->userInfo['school_id'];
                    }else{
                        throw new \Exception('学校账号的学校ID设置错误');
                    }

                    $school = (new model())->field(['id', 'school_attr', 'school_type', 'school_name'])
                        ->where('id', $school_id)->find();
                    if(!$school){
                        throw new \Exception('学校账号的学校ID对应学校不存在');
                    }
                    $data['school'] = $school;

                    $plan = Db::name('Plan')
                        ->field(['public_start_time', 'public_end_time', 'private_start_time', 'private_end_time'])
                        ->where([['school_type', '=', $school['school_type']], ['deleted', '=', 0],
                            ['plan_time', '=', date('Y')]])->findOrEmpty();
                    if(!$plan){
                        throw new \Exception('学校类型还没有招生计划');
                    }

                    //学校类型
                    if($school['school_type'] == 1){
                        $data['enrollment_time']['primary_plan'] = [];
                    }
                    if($school['school_type'] == 2){
                        $data['enrollment_time']['middle_plan'] = [];
                    }
                    if($school['school_attr'] == 1){
                        if(isset($data['enrollment_time']['primary_plan']) ){
                            $data['enrollment_time']['primary_plan'] = [
                                'public_start_time' => $plan['public_start_time'],
                                'public_end_time' => $plan['public_end_time'],
                                'public_start_time_format' => date('Y-m-d H:i', $plan['public_start_time']),
                                'public_end_time_format' => date('Y-m-d H:i', $plan['public_end_time']),
                            ];
                        }
                        if(isset($data['enrollment_time']['middle_plan']) ){
                            $data['enrollment_time']['middle_plan'] = [
                                'public_start_time' => $plan['public_start_time'],
                                'public_end_time' => $plan['public_end_time'],
                                'public_start_time_format' => date('Y-m-d H:i', $plan['public_start_time']),
                                'public_end_time_format' => date('Y-m-d H:i', $plan['public_end_time']),
                            ];
                        }
                    }
                    //民办退款申请
                    if($school['school_attr'] == 2){
                        $return_apply = $this->getRefundCount();
                        $data['pending']['return_apply'] = $return_apply;

                        if(isset($data['enrollment_time']['primary_plan']) ){
                            $data['enrollment_time']['primary_plan'] = [
                                'private_start_time' => $plan['private_start_time'],
                                'private_end_time' => $plan['private_end_time'],
                                'private_start_time_format' => date('Y年m月d日', $plan['private_start_time']),
                                'private_end_time_format' => date('Y年m月d日', $plan['private_end_time']),
                            ];
                        }
                        if(isset($data['enrollment_time']['middle_plan']) ){
                            $data['enrollment_time']['middle_plan'] = [
                                'private_start_time' => $plan['private_start_time'],
                                'private_end_time' => $plan['private_end_time'],
                                'private_start_time_format' => date('Y年m月d日', $plan['private_start_time']),
                                'private_end_time_format' => date('Y年m月d日', $plan['private_end_time']),
                            ];
                        }
                    }
                    unset($data['work_list']);
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
        }else{
            return parent::ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    /**
     * 市级查看
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'region_id',
                ]);
                $region_id = $data['region_id'];
                if(!$region_id){
                    throw new \Exception('区县ID参数设置错误');
                }

                //市直市教育局
                if($region_id == 1){
                    $where = [];
                    $where[] = ['disabled', '=', 0];
                    $where[] = ['deleted', '=', 0];
                    $where[] = ['directly', '=', 1];
                    $directly_ids = Db::name('SysSchool')->where($where)->column('id');

                    $schedule = $this->getAreaSchedule($directly_ids);
                    if($schedule['code'] == 0){
                        throw new \Exception($schedule['msg']);
                    }
                    $list = $schedule['list'];

                    $res = [
                        'code' => 1,
                        'data' => $list
                    ];
                }else{
                    $where = [];
                    $where[] = ['disabled', '=', 0];
                    $where[] = ['deleted', '=', 0];
                    $where[] = ['directly', '=', 0];
                    $where[] = ['region_id', '=', $region_id];
                    $school_ids = Db::name('SysSchool')->where($where)->column('id');

                    $schedule = $this->getAreaSchedule($school_ids);
                    if($schedule['code'] == 0){
                        throw new \Exception($schedule['msg']);
                    }
                    $list = $schedule['list'];

                    $res = [
                        'code' => 1,
                        'data' => $list
                    ];
                }
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return $this->ajaxReturn($res);
        }else{
            return $this->ajaxReturn(['code' => 0, 'msg' => '不是POST请求']);
        }
    }

    //获取招生时间
    private function getPlanTime(): array
    {
        $dictionary = new FilterData();
        $getData = $dictionary->findValue('dictionary', 'SYSXXLX', 'SYSXXLXXX');
        if($getData['code']){
            $primary_school_type = $getData['data'];
        }else{
            return ['code' => 0, '获取小学学校类型数据字典设置错误'];
        }
        $getData = $dictionary->findValue('dictionary', 'SYSXXLX', 'SYSXXLXCZ');
        if($getData['code']){
            $middle_school_type = $getData['data'];
        }
        else{
            return ['code' => 0, '获取初中学校类型数据字典设置错误'];
        }
        //小学招生时间
        $primary_plan = Db::name('Plan')
            ->field(['public_start_time', 'public_end_time', 'private_start_time', 'private_end_time'])
            ->where([['school_type', '=', $primary_school_type], ['deleted', '=', 0],
                ['plan_time', '=', date('Y')]])->findOrEmpty();
        if($primary_plan) {
            $primary_plan['public_start_time_format'] = date('Y-m-d H:i', $primary_plan['public_start_time']);
            $primary_plan['public_end_time_format'] = date('Y-m-d H:i', $primary_plan['public_end_time']);
            $primary_plan['private_start_time_format'] = date('Y-m-d H:i', $primary_plan['private_start_time']);
            $primary_plan['private_end_time_format'] = date('Y-m-d H:i', $primary_plan['private_end_time']);
        }
        //初中招生时间
        $middle_plan = Db::name('Plan')
            ->field(['public_start_time', 'public_end_time', 'private_start_time', 'private_end_time'])
            ->where([['school_type', '=', $middle_school_type], ['deleted', '=', 0],
                ['plan_time', '=', date('Y')]])->findOrEmpty();
        if($middle_plan) {
            $middle_plan['public_start_time_format'] = date('Y-m-d H:i', $middle_plan['public_start_time']);
            $middle_plan['public_end_time_format'] = date('Y-m-d H:i', $middle_plan['public_end_time']);
            $middle_plan['private_start_time_format'] = date('Y-m-d H:i', $middle_plan['private_start_time']);
            $middle_plan['private_end_time_format'] = date('Y-m-d H:i', $middle_plan['private_end_time']);
        }

        return ['code' => 1, 'primary_plan' => $primary_plan, 'middle_plan' => $middle_plan];
    }

    //消息未审批数量
    private function getMessageCount(): int
    {
        $message_count = 0;
        $where = [];
        $where[] = ['status', '=', 0];
        $where[] = ['deleted', '=', 0];
        $message_count = Db::name("SysMessage")->where($where)->count();

        return $message_count;
    }

    //申请修改入学区域未审批
    private function getRegionEntrance(): int
    {
        $where = [];
        $where_1 = [];
        $where_2 = [];
        $where[] = ['status', '=', 0];
        $where[] = ['deleted', '=', 0];
        if($this->userInfo['grade_id'] == $this->area_grade) {//区
            $where_1[] = ['local_region_audit', '=', 0];
            $where_2[] = ['local_region_audit', '=', 1];
            $where_2[] = ['city_audit', '=', 1];
            $where_2[] = ['go_region_audit', '=', 0];
            $where[] = ['local_region_id', '=', $this->userInfo['region_id']];
            $sql = 'SELECT COUNT(*) as count FROM deg_change_region WHERE CASE '.$this->userInfo['region_id'].' WHEN local_region_id THEN local_region_audit=0 WHEN go_region_id THEN local_region_audit=1 and city_audit=1 and go_region_audit=0 ELSE 1=2 END';
            $count = Db::query($sql);
            return $count[0]['count'];

        } else { // 市
            $where[] = ['local_region_audit', '=', 1];
            $where[] = ['city_audit', '=', 0];
            return Db::name("change_region")->where($where)->count();
        }

    }

    //退款待处理申请
    private function getRefundCount(): int
    {
        $refund_count = 0;
        if($this->userInfo['grade_id'] >= $this->city_grade) {
            $refund_count = Db::name("UserCostRefund")->where('region_status', 0)->where('deleted', 0)->count();
        }
        if($this->userInfo['grade_id'] == $this->area_grade) {
            $directly_where = [];
            $directly_where[] = ['deleted','=',0];
            $directly_where[] = ['disabled','=',0];
            $directly_where[] = ['directly','=',1];//市直
            $school_ids = Db::name("SysSchool")->where($directly_where)->column('id');

            $refund_count = Db::name("UserCostRefund")->where('region_status', 0)->whereNotIn('school_id', $school_ids)
                ->where('region_id', $this->userInfo['region_id'])->where('deleted', 0)->count();
        }
        if($this->userInfo['grade_id'] == $this->school_grade) {
            $refund_count = Db::name("UserCostRefund")->where('region_status', 0)->where('school_status', 0)
                ->where('school_id', $this->userInfo['school_id'])->where('deleted', 0)->count();
        }
        return $refund_count;
    }

    //学位待审批数量
    private function getDegreeCount($grade, $role_school_ids, $central_ids = []): array
    {
        $res = ['code' => 1, 'degree_count' => 0];
        //教管会
        if($grade == 2) {
            $degree_where = [];
            $degree_where[] = ['status', '=', 0];
            $degree_where[] = ['deleted', '=', 0];
            $degree_where[] = ['school_id', 'in', $role_school_ids];
            $degree_count = Db::name("PlanApply")->where($degree_where)->count();

            $res = ['code' => 1, 'degree_count' => $degree_count];
        }else{
            //区级
            if ( isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1 ) {
                $region_id = $this->userInfo['region_id'];

                //区级普通学校
                $degree_where = [];
                $degree_where[] = ['status', '=', 0];
                $degree_where[] = ['deleted', '=', 0];
                $degree_where[] = ['school_id', 'in', $role_school_ids];
                $degree_count = Db::name("PlanApply")->where($degree_where)->count();

                //区级教管会
                /*$degree_where = [];
                $degree_where[] = ['status', '=', 0];
                $degree_where[] = ['deleted', '=', 0];
                $degree_where[] = ['central_id', 'in', $central_ids];
                $degree_count += Db::name("PlanApply")->where($degree_where)->count();*/

                $res = ['code' => 1, 'degree_count' => $degree_count];
            }else{
                return ['code' => 0, 'msg' => '区级管理员所属区县ID设置错误' ];
            }
        }
        return $res;
    }

    //填报人数统计
    private function getUserApplyPopulation($region_id = 0, $central_id = 0): array
    {
        try {
            //教管会统计
            if($central_id > 0 ){
                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];//不是市直
                $where[] = ['central_id','=',$central_id];
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                $where = [];
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['a.voided', '=', 0];//没有作废
                $where[] = ['a.prepared', '=', 1];//预录取
                $where[] = ['a.resulted', '=', 1];//录取
                $where[] = ['a.result_school_id', 'in', $school_ids];//录取学校
                //公办录取人数
                $public_num = Db::name('UserApply')->alias('a')->where($where)->where('school_attr', 1)->count();
                //民办录取人数
                $private_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 2)->where('paid', 1)->count();
                //总录取人数
                $num = $public_num + $private_num;

                $data = [
                    [
                        'name' => 'admission',
                        'title' => '录取人数',
                        'num' => $num,//录取人数
                        'tooltip' => [
                            'public_num' => $public_num,//公办人数
                            'private_num' => $private_num,//民办人数
                        ],
                    ],
                ];
            }else {


                $where = [];
                $where[] = ['a.deleted', '=', 0];
                $where[] = ['a.voided', '=', 0];//没有作废
                if($region_id > 0){
                    $where[] = ['a.region_id', '=', $region_id];

                    $directly_where = [];
                    $directly_where[] = ['deleted','=',0];
                    $directly_where[] = ['disabled','=',0];
                    $directly_where[] = ['directly','=',1];//市直
                    $school_ids = Db::name("SysSchool")->where($directly_where)->column('id');

                    $where[] = ['a.apply_school_id', 'not in', $school_ids ];
                    $where[] = ['a.public_school_id', 'not in', $school_ids ];
                    $where[] = ['a.result_school_id', 'not in', $school_ids ];
                }

                //公办总填报人数
                $total_public_num = Db::name('UserApply')->alias('a')->where($where)->where('school_attr', 1)->count();
                //民办总填报人数
                $total_private_num = Db::name('UserApply')->alias('a')->where($where)->where('school_attr', 2)->count();
                $total = $total_public_num + $total_private_num;

                $date = date('Y-m-d', time());
                //公办日新增人数
                $add_day_public_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 1)->whereRaw("DATE_FORMAT(create_time, '%Y-%m-%d') = '" . $date . "'")->count();
                $add_day_private_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 2)->whereRaw("DATE_FORMAT(create_time, '%Y-%m-%d') = '" . $date . "'")->count();
                $add_day = $add_day_public_num + $add_day_private_num;

                //公办未录取人数
                $not_admission_public_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 1)->where('prepared', 0)->where('resulted', 0)
                    ->where('result_school_id', 0)->count();
                //民办未录取人数
                $not_admission_private_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 2)->where('prepared', 0)->where('resulted', 0)
                    ->where('result_school_id', 0)->count();
                $not_admission = $not_admission_public_num + $not_admission_private_num;

                //公办预录取人数
                $beforehand_admission_public_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 1)->where('prepared', 1)->where('resulted', 0)->count();
                //民办预录取人数
                $beforehand_admission_private_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 2)->where('prepared', 1)->where('resulted', 1)
                    ->where('paid', 0)->count();
                $beforehand_admission = $beforehand_admission_public_num + $beforehand_admission_private_num;

                //公办录取人数
                $admission_public_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 1)->where('prepared', 1)->where('resulted', 1)->count();
                //民办录取人数
                $admission_private_num = Db::name('UserApply')->alias('a')->where($where)
                    ->where('school_attr', 2)->where('prepared', 1)->where('resulted', 1)
                    ->where('paid', 1)->count();
                $admission = $admission_public_num + $admission_private_num;

                //市级、区县统计
                $data = [
                    [
                        'name' => 'total',
                        'title' => '总填报人数',
                        'num' => $total,//总填报人数
                        'tooltip' => [
                            'public_num' => $total_public_num,//公办人数
                            'private_num' => $total_private_num,//民办人数
                        ],
                    ],
                    [
                        'name' => 'add_day',
                        'title' => '日新增人数',
                        'num' => $add_day,//日新增人数
                        'tooltip' => [
                            'public_num' => $add_day_public_num,//公办人数
                            'private_num' => $add_day_private_num,//民办人数
                        ],
                    ],
                    [
                        'name' => 'not_admission',
                        'title' => '未录取人数',
                        'num' => $not_admission,//未录取人数
                        'tooltip' => [
                            'public_num' => $not_admission_public_num,//公办人数
                            'private_num' => $not_admission_private_num,//民办人数
                        ],
                    ],
                    [
                        'name' => 'beforehand_admission',
                        'title' => '预录取人数',
                        'num' => $beforehand_admission,//预录取人数
                        'tooltip' => [
                            'public_num' => $beforehand_admission_public_num,//公办人数
                            'private_num' => $beforehand_admission_private_num,//民办人数
                        ],
                    ],
                    [
                        'name' => 'admission',
                        'title' => '录取人数',
                        'num' => $admission,//录取人数
                        'tooltip' => [
                            'public_num' => $admission_public_num,//公办人数
                            'private_num' => $admission_private_num,//民办人数
                        ],
                    ],
                ];
            }
            return ['code' => 1, 'data' => $data ];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }
    }


    //工作进度【市级及以上】
    private function getCitySchedule_(): array
    {
        try {
            //市直学校ID
            $where = [];
            $where[] = ['disabled', '=', 0];
            $where[] = ['deleted', '=', 0];
            $where[] = ['directly', '=', 1];
            $directly_ids = Db::name('SysSchool')->where($where)->column('id');
            //区域学校数量
            $where = [];
            $where[] = ['disabled', '=', 0];
            $where[] = ['deleted', '=', 0];
            $where[] = ['directly', '=', 0];
            $querySchool = Db::name('SysSchool')->where($where)
                ->field('region_id, COUNT(*) AS school_count')
                ->group('region_id')->buildSql();
            //校情完成数量
            $where[] = ['finished', '=', 1];
            $querySchoolFinished = Db::name('SysSchool')->where($where)
                ->field('region_id, COUNT(*) AS school_count')
                ->group('region_id')->buildSql();
            //六年级信息
            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['graduation_school_id', 'not in', $directly_ids];
            $querySixthGrade = Db::name('SixthGrade')->where($where)
                ->field('region_id, COUNT(*) AS sixth_grade_count')
                ->group('region_id')->buildSql();

            $query = Db::name('SysRegion')->alias('sr')
                ->join([
                    $querySchool => 'school'
                ], 'school.region_id = sr.id', 'LEFT')
                ->join([
                    $querySchoolFinished => 'finished'
                ], 'finished.region_id = sr.id', 'LEFT')
                ->join([
                    $querySixthGrade => 'sixth'
                ], 'sixth.region_id = sr.id', 'LEFT')
                ->field([
                    'sr.id',
                    'sr.region_name' => 'region_name',
                    'IFNULL(school.school_count, 0)' => 'school_count',
                    'IFNULL(finished.school_count, 0)' => 'finished_count',
                    'IFNULL(sixth.sixth_grade_count, 0)' => 'sixth_grade_count',
                ])
                ->where('sr.parent_id', '>', 0)
                ->where('sr.disabled', 0)
                ->where('sr.deleted', 0);

            $list = $query->select()->toArray();

            $work_list = [];
            //市直统计情况
            $directly = $this->getDirectlyTotal();
            if($directly['code'] == 0){
                return ['code' => 0, 'msg' => $directly['msg']];
            }
            $work_list[] = $directly['data'];

            foreach ($list as $k => $v){
                $city_degree = $this->getCityDegreeCount($v['id']);
                if($city_degree['code'] == 0){
                    return ['code' => 0, 'msg' => $city_degree['msg']];
                }
                $city_address = $this->getCityAddressCount($v['id']);
                if($city_address['code'] == 0){
                    return ['code' => 0, 'msg' => $city_address['msg']];
                }
                $work_list[] = [
                    'id' => $v['id'],
                    'region_name' => $v['region_name'],
                    'school_count' => $v['school_count'],//学校数量
                    'degree_count' => $city_degree['degree_count'],//批复学位数量
                    'check_address' => $city_address['address_count'],//房产勾选数量
                    'not_check_address' => $city_address['not_check_count'],//房产未勾选数量
                    'finished_count' => $v['finished_count'],//校情添加
                    'sixth_grade_count' => $v['sixth_grade_count'],//小学六年级毕业生数量
                    'middle_admission' => 0,//中心学校录取名单数量
                    'school_roll' => 0,//学籍数量
                ];
            }
            $work_list[] = [
                'id' => '',
                'region_name' => '合计',
                'school_count' => array_sum(array_column($work_list,'school_count')),
                'degree_count' => array_sum(array_column($work_list,'degree_count')),
                'check_address' => array_sum(array_column($work_list,'check_address')),
                'not_check_address' => array_sum(array_column($work_list,'not_check_address')),
                'finished_count' => array_sum(array_column($work_list,'finished_count')),
                'sixth_grade_count' => array_sum(array_column($work_list,'sixth_grade_count')),
                'middle_admission' => array_sum(array_column($work_list,'middle_admission')),
                'school_roll' => array_sum(array_column($work_list,'school_roll')),
            ];

            $data = [
                'data' => $work_list,
                'total' => count($work_list),
            ];

            return ['code' => 1, 'list' => $data];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //市直统计情况
    private function getDirectlyTotal(): array
    {
        try {
            //市直学校情况
            $where = [];
            $where[] = ['disabled', '=', 0];
            $where[] = ['deleted', '=', 0];
            $where[] = ['directly', '=', 1];
            $directly_school = Db::name('SysSchool')->where($where)
                ->field('id, finished')->select()->toArray();
            $directly_school_ids = [];
            $directly_finished_count = 0;
            foreach ($directly_school as $k => $v) {
                if ($v['finished'] == 1) {
                    $directly_finished_count++;
                }
                $directly_school_ids[] = $v['id'];
            }
            //批复学位
            $city_degree = $this->getCityDegreeCount(1);
            if($city_degree['code'] == 0){
                return ['code' => 0, 'msg' => $city_degree['msg']];
            }
            //房产勾选
            $city_address = $this->getCityAddressCount(1);
            if($city_address['code'] == 0){
                return ['code' => 0, 'msg' => $city_address['msg']];
            }
            //六年级信息
            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['graduation_school_id', 'in', $directly_school_ids];
            $sixth_grade_count = Db::name('SixthGrade')->where($where)->count();

            return [
                'code' => 1,
                'data' => [
                    'id' => 1,
                    'region_name' => '市教育局',
                    'school_count' => count($directly_school),//学校数量
                    'degree_count' => $city_degree['degree_count'],//批复学位数量
                    'check_address' => $city_address['address_count'],//房产勾选数量
                    'not_check_address' => $city_address['not_check_count'],//房产未勾选数量
                    'finished_count' => $directly_finished_count,//校情添加
                    'sixth_grade_count' => $sixth_grade_count,//小学六年级毕业生数量
                    'middle_admission' => 0,//中心学校录取名单数量
                    'school_roll' => 0,//学籍数量
                ]
            ];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //市级查看批复学位数量
    private function getCityDegreeCount($region_id): array
    {
        try {
            $degree_count = 0;
            //市教育局市直学校数量
            if($region_id == 1){
                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',1];//市直
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                $degree_count = Db::name('PlanApply')
                    ->group('school_id')
                    ->where('school_id', 'in', $school_ids)
                    ->where('status', 1)->where('deleted', 0)->sum('spare_total');
                return ['code' => 1, 'degree_count' => $degree_count];
            }else{
                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];//不是市直
                $where[] = ['central_id','=',0];//不是教管会所管学校
                $where[] = ['region_id','=',$region_id];
                $school_ids = Db::name("SysSchool")->where($where)
                    ->column('id');

                $degree_count += Db::name('PlanApply')
                    ->group('school_id')
                    ->where('school_id', 'in', $school_ids)
                    ->where('status', 1)->where('deleted', 0)->sum('spare_total');

                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['region_id','=',$region_id];
                $central_ids = Db::name("CentralSchool")->where($where)
                    ->column('id');

                $degree_count += Db::name('PlanApply')
                    ->group('central_id')
                    ->where('central_id', 'in', $central_ids)
                    ->where('status', 1)->where('deleted', 0)->sum('spare_total');
            }
            return ['code' => 1, 'degree_count' => $degree_count];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //市级查看勾选房产数量
    private function getCityAddressCount($region_id): array
    {
        try {
            //市直市教育局
            if ($region_id == 1) {
                $address_count = 0;
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['disabled', '=', 0];
                $where[] = ['directly', '=', 1];//市直
                $school_list = Db::name("SysSchool")->field(['id', 'school_type'])->where($where);
                $school_ids = [];
                $primary_school_ids = [];
                $middle_school_ids = [];
                foreach ($school_list as $item){
                    if($item['school_type'] == 1){
                        $primary_school_ids[] = $item['id'];
                    }
                    if($item['school_type'] == 2){
                        $middle_school_ids[] = $item['id'];
                    }
                    $school_ids[] = $item['id'];
                }
                //市直小学缩略详细
                if($primary_school_ids){
                    $res_primary = $this->getIntactCount($primary_school_ids, 1);
                    if($res_primary['code'] == 0){
                        return ['code' => 0, 'msg' => $res_primary['msg'] ];
                    }
                    if($res_primary['list']){
                        $address_count = array_sum($res_primary['list']);
                    }
                }
                //市直初中缩略详细
                if($middle_school_ids){
                    $res_middle = $this->getIntactCount($middle_school_ids, 2);
                    if($res_middle['code'] == 0){
                        return ['code' => 0, 'msg' => $res_middle['msg'] ];
                    }
                    if($res_middle['list']){
                        $address_count += array_sum($res_middle['list']);
                    }
                }
                //完整地址
                if($school_ids) {
                    $address_count += Db::name('SysAddressIntact')->where('deleted', 0)
                        ->where('primary_school_id|middle_school_id', 'in', $school_ids)
                        ->count();
                }
                return ['code' => 1, 'address_count' => $address_count, 'not_check_count' => 0];
            }else{
                $address_count = 0;
                $not_check_count = 0;
                //区级学校ID
                $where = [];
                $where[] = ['deleted', '=', 0];
                $where[] = ['disabled', '=', 0];
                $where[] = ['directly', '=', 0];//不是市直
                $where[] = ['central_id', '=', 0];//不是教管会所属教学点
                $school_ids = Db::name("SysSchool")->where($where)->column('id');

                $simple_code = Db::name('sys_region')->where('disabled', 0)
                    ->where('id', $region_id)->where('deleted', 0)
                    ->value('simple_code');

                //缩略详细
                if($simple_code && $school_ids) {
                    $address_count += Db::name('sys_address_' . $simple_code)
                        ->where('primary_school_id|middle_school_id', 'in', $school_ids)
                        ->where('deleted', 0)
                        ->count();


                    $not_check_count += Db::name('sys_address_' . $simple_code)
                        ->whereOr([['primary_school_id', '<=', 0]], [['primary_school_id', '=', 'null']])
                        ->whereOr([['middle_school_id', '<=', 0]], [['middle_school_id', '=', 'null']])
                        //->whereRaw('primary_school_id <= 0 or primary_school_id is null')
                        //->whereRaw('middle_school_id <= 0 or middle_school_id is null')
                        ->where('deleted', 0)
                        ->count();
                }
                //完整地址
                if($school_ids) {
                    $address_count += Db::name('SysAddressIntact')->where('deleted', 0)
                        ->where('primary_school_id|middle_school_id', 'in', $school_ids)
                        ->count();

                    $not_check_count += Db::name('SysAddressIntact')
                        ->whereOr([['primary_school_id', '<=', 0]], [['primary_school_id', '=', 'null']])
                        ->whereOr([['middle_school_id', '<=', 0]], [['middle_school_id', '=', 'null']])
                        //->whereRaw('primary_school_id <= 0 or primary_school_id is null')
                        //->whereRaw('middle_school_id <= 0 or middle_school_id is null')
                        ->where('deleted', 0)
                        ->count();
                }
                return ['code' => 1, 'address_count' => $address_count, 'not_check_count' => $not_check_count];
            }

        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //工作进度【区级、教管会】
    private function getSchedule($school_ids, $is_middle = 0): array
    {
        try {
            //批复学位数量
            $querySpare = Db::name('PlanApply')
                ->field('school_id, SUM(spare_total) AS spare_total')
                ->group('school_id')
                ->where('school_id', 'in', $school_ids)
                ->where('status', 1)->where('deleted', 0)->buildSql();

            //六年级信息
            $querySixthGrade = Db::name('SixthGrade')
                ->field('graduation_school_id, COUNT(*) AS sixth_grade_count')
                ->where([['graduation_school_id', 'in', $school_ids], ['deleted', '=', 0] ])
                ->group('graduation_school_id')->buildSql();

            //区县、教管会按学校统计
            $list = Db::name('SysSchool')->alias('s')
                ->join([
                    $querySpare => 'spare'
                ], 's.id = spare.school_id', 'LEFT')
                ->join([
                    $querySixthGrade => 'sixth'
                ], 's.id = sixth.graduation_school_id', 'LEFT')
                ->field([
                    's.id',
                    's.school_name' => 'school_name',
                    's.school_attr' => 'school_attr',
                    's.school_type' => 'school_type',
                    'IFNULL(spare.spare_total, 0)' => 'degree_count',
                    's.finished' => 'finished_count',
                    'IFNULL(sixth.sixth_grade_count, 0)' => 'sixth_grade_count',
                ])
                ->where('s.id', 'in', $school_ids)
                ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            //勾选房产数量
            $res_address = $this->getStatisticsAddress($school_ids);
            if($res_address['code'] == 0){
                return ['code' => 0, 'msg' => $res_address['msg']];
            }
            //学校性质、类型数据字典
            $dictionary = new FilterData();
            $typeData = $dictionary->resArray('dictionary','SYSXXLX');
            if(!$typeData['code']){
                throw new \Exception($typeData['msg']);
            }
            $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
            if(!$attrData['code']){
                throw new \Exception($attrData['msg']);
            }

            foreach ($list['data'] as $k => $v){
                $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $v['school_type']);
                $list['data'][$k]['school_type_name'] = '';
                if (count($schoolTypeData) > 0){
                    $list['data'][$k]['school_type_name'] = $schoolTypeData['dictionary_name'];
                }
                $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $v['school_attr']);
                $list['data'][$k]['school_attr_name'] = '';
                if (count($schoolAttrData) > 0){
                    $list['data'][$k]['school_attr_name'] = $schoolAttrData['dictionary_name'];
                }

                $list['data'][$k]['check_address'] = $res_address['check_list'][$v['id']] ?? 0;
                //线下录取数量
                $list['data'][$k]['offline_admission'] = 0;
                //学籍数量
                $list['data'][$k]['school_roll'] = 0;
                //中心学校录取名单数量
                if($is_middle) {
                    $list['data'][$k]['middle_admission'] = 0;
                }
            }
            $total = [];
            if(!$is_middle) {
                $_total = $this->getScheduleTotal($school_ids);
                if ($_total['code'] == 0) {
                    return ['code' => 0, 'msg' => $_total['msg']];
                }
                $_total = $_total['data'];
                $total = [
                    '合计',
                    '-',
                    '-',
                    $_total['total_spare'],
                    array_sum($res_address['check_list']),
                    '-',
                    $_total['total_sixth'],
                    $_total['total_offline'],
                    $_total['school_roll'],
                ];
            }

            return ['code' => 1, 'list' => $list, 'total' => $total];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //【区级、教管会统计】
    private function getScheduleTotal($school_ids): array
    {
        try {
            //批复学位数量
            $total_spare = Db::name('PlanApply')
                ->group('school_id')
                ->where('school_id', 'in', $school_ids)
                ->where('status', 1)->where('deleted', 0)->sum('spare_total');

            //六年级信息
            $total_sixth = Db::name('SixthGrade')
                ->where([['graduation_school_id', 'in', $school_ids], ['deleted', '=', 0] ])
                ->group('graduation_school_id')->count();

            $total = [
                'total_spare' => $total_spare,
                'total_sixth' => $total_sixth,
                'total_offline' => 0,//线下录取数量
                'school_roll' => 0,//学籍数量
            ];

            return ['code' => 1, 'data' => $total];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //根据学校统计 缩略地址详细及完整地址
    private function getStatisticsAddress($school_ids): array
    {
        try {
            $school_list = Db::name('SysSchool')->field(['id', 'school_type'])
                ->where('id', 'in', $school_ids)->select()->toArray();
            $check_list = [];
            $primary_school_ids = [];
            $middle_school_ids = [];
            foreach ($school_list as $item){
                if($item['school_type'] == 1){
                    $primary_school_ids[] = $item['id'];
                }
                if($item['school_type'] == 2){
                    $middle_school_ids[] = $item['id'];
                }
                $check_list[$item['id']] = 0;
            }
            //小学类型勾选的详细房产
            if($primary_school_ids) {
                $res = $this->getIntactCount($primary_school_ids, 1);
                if($res['code'] == 0){
                    return [
                        'code' => 0,
                        'msg' => $res['msg']
                    ];
                }
                foreach ($res['list'] as $key => $count){
                    $check_list[$key] += $count;
                }
            }
            //初中类型勾选的详细房产
            if($middle_school_ids) {
                $res = $this->getIntactCount($middle_school_ids, 2);
                if($res['code'] == 0){
                    return [
                        'code' => 0,
                        'msg' => $res['msg']
                    ];
                }
                foreach ($res['list'] as $key => $count){
                    $check_list[$key] += $count;
                }
            }
            //小学类型勾选的完整地址
            if($primary_school_ids) {
                $list = Db::name('SysAddressIntact')->field('primary_school_id, COUNT(*) AS check_count')
                    ->where('primary_school_id', 'in', $primary_school_ids)->where('deleted', 0)
                    ->group('primary_school_id')->select()->toArray();
                foreach ($list as $item){
                    $check_list[$item['primary_school_id']] += $item['check_count'];
                }
            }
            //初中类型勾选的完整地址
            if($middle_school_ids) {
                $list = Db::name('SysAddressIntact')->field('middle_school_id, COUNT(*) AS check_count')
                    ->where('middle_school_id', 'in', $middle_school_ids)->where('deleted', 0)
                    ->group('middle_school_id')->select()->toArray();
                foreach ($list as $item){
                    $check_list[$item['middle_school_id']] += $item['check_count'];
                }
            }
            return ['code' => 1, 'check_list' => $check_list];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //获取小学或者初中勾选的详细房产数量
    private function getIntactCount($school_ids, $type): array
    {
        try {
            $field = '';
            if ($type == 1) {
                $field = 'primary_school_id';
            }
            if ($type == 2) {
                $field = 'middle_school_id';
            }
            $simple_code_list = Db::name('sys_region')->where('disabled', 0)
                ->where('parent_id', '>', 0)->where('deleted', 0)
                ->column('simple_code');
            $table_name_list = [];
            foreach ($simple_code_list as $simple_code){
                $table_name_list[] = 'sys_address_' . $simple_code;
            }
            $table_name_first = array_shift($table_name_list);

            $query = Db::name($table_name_first)->field($field . ', COUNT(*) AS check_count')
                ->where($field, 'in', $school_ids)->where('deleted', 0)
                ->group($field);

            foreach ($table_name_list as $table_name) {
                $child_query = Db::name($table_name)->field($field . ', COUNT(*) AS check_count')
                    ->where($field, 'in', $school_ids)->where('deleted', 0)
                    ->group($field)->buildSql();
                $query->unionAll($child_query);
            }
            $list = $query->select()->toArray();

            $check_list = [];
            foreach ($list as $item){
                if(isset($check_list[$item[$field]])){
                    $check_list[$item[$field]] += $item['check_count'];
                }else{
                    $check_list[$item[$field]] = $item['check_count'];
                }
            }

            return ['code' => 1, 'list' => $check_list];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }



    //工作进度【市级及以上】
    private function getCitySchedule(): array
    {
        try {
            $query = Db::name('WorkMunicipalReportForm')->alias('w')
                ->join([
                    'deg_sys_region' => 'r'
                ], 'w.region_id = r.id')
                ->field([
                    'r.id' => 'id',
                    'IF(w.region_id = 1, "市教育局", r.region_name)' => 'region_name',
                    'w.school_count' => 'school_count',
                    'w.degree_count' => 'degree_count',
                    'w.check_address_count' => 'check_address',
                    'w.not_check_address_count' => 'not_check_address',
                    'w.finished_count' => 'finished_count',
                    'w.sixth_grade_count' => 'sixth_grade_count',
                    'w.middle_admission_count' => 'middle_admission',
                    'w.school_roll_count' => 'school_roll',
                ])
                ->where('w.deleted', 0);

            $list = $query->order('w.region_id', 'ASC')->select()->toArray();

            $list[] = [
                'id' => '',
                'region_name' => '合计',
                'school_count' => array_sum(array_column($list,'school_count')),
                'degree_count' => array_sum(array_column($list,'degree_count')),
                'check_address' => array_sum(array_column($list,'check_address')),
                'not_check_address' => array_sum(array_column($list,'not_check_address')),
                'finished_count' => array_sum(array_column($list,'finished_count')),
                'sixth_grade_count' => array_sum(array_column($list,'sixth_grade_count')),
                'middle_admission' => array_sum(array_column($list,'middle_admission')),
                'school_roll' => array_sum(array_column($list,'school_roll')),
            ];

            $data = [
                'data' => $list,
                'total' => count($list),
            ];

            return ['code' => 1, 'list' => $data];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //工作进度【区级、教管会】
    private function getAreaSchedule($school_ids, $is_total = false): array
    {
        try {
            $list = Db::name('WorkAreaReportForm')->alias('w')
                ->join([
                    'deg_sys_school' => 's'
                ], 's.id = w.school_id')
                ->field([
                    's.id' => 'id',
                    's.school_name' => 'school_name',
                    's.school_attr' => 'school_attr',
                    's.school_type' => 'school_type',
                    'w.degree_count' => 'degree_count',
                    'w.check_address_count' => 'check_address',
                    'IF(w.finished_count = 1, "已添加", "未添加")' => 'finished_count',
                    'w.sixth_grade_count' => 'sixth_grade_count',
                    'w.offline_admission_count' => 'offline_admission',
                    'w.school_roll_count' => 'school_roll',
                ])
                ->where('w.deleted', 0)
                ->where('w.school_id', 'in', $school_ids)
                ->order('w.school_id', 'ASC')
                ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            //学校性质、类型数据字典
            $dictionary = new FilterData();
            $typeData = $dictionary->resArray('dictionary','SYSXXLX');
            if(!$typeData['code']){
                throw new \Exception($typeData['msg']);
            }
            $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
            if(!$attrData['code']){
                throw new \Exception($attrData['msg']);
            }

            foreach ($list['data'] as $k => $v){
                $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $v['school_type']);
                $list['data'][$k]['school_type_name'] = '';
                if (count($schoolTypeData) > 0){
                    $list['data'][$k]['school_type_name'] = $schoolTypeData['dictionary_name'];
                }
                $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $v['school_attr']);
                $list['data'][$k]['school_attr_name'] = '';
                if (count($schoolAttrData) > 0){
                    $list['data'][$k]['school_attr_name'] = $schoolAttrData['dictionary_name'];
                }
                if (isset($school_name_arr[$v['id']])){
                    $list['data'][$k]['school_attr_name'] = $schoolAttrData['dictionary_name'];
                }
            }

            $total = [];
            if($is_total) {
                $total_arr = Db::name('WorkAreaReportForm')
                    ->field([
                        'SUM(degree_count)' => 'degree_count',
                        'SUM(check_address_count)' => 'check_address_count',
                        'SUM(sixth_grade_count)' => 'sixth_grade_count',
                        'SUM(offline_admission_count)' => 'offline_admission_count',
                        'SUM(school_roll_count)' => 'school_roll_count',
                    ])
                    ->where('deleted', 0)->where('school_id', 'in', $school_ids)->findOrEmpty();

                $total = [
                    '合计',
                    '-',
                    '-',
                    $total_arr['degree_count'],
                    $total_arr['check_address_count'],
                    '-',
                    $total_arr['sixth_grade_count'],
                    //$total_arr['offline_admission_count'],
                    $total_arr['school_roll_count'],
                ];
            }

            return ['code' => 1, 'list' => $list, 'total' => $total];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //工作进度初始化
    public function initStatictics(){
        //开始事务
        Db::startTrans();
        try {
            //按学校统计
            $area_arr = Db::name('WorkAreaReportForm')->where('deleted', 0)->column('id', 'school_id');

            //学校学位统计
            $school_degree_arr = Db::name('PlanApply')->where('status', 1)
                ->where('deleted', 0)->group('school_id')->column('SUM(spare_total)', 'school_id');
            //房产统计
            $result = $this->getSchoolHouse();
            if($result['code'] == 0){
                return ['code' => 0, $result['msg']];
            }
            //六年级统计
            $sixth_grade_arr = Db::name('SixthGrade')->where('deleted', 0)
                ->group('graduation_school_id')->column('COUNT(*)', 'graduation_school_id');
            //线下录取统计
            $where = [];
            $where[] = ['deleted','=',0];
            $where[] = ['voided','=',0];
            $where[] = ['resulted','=',1];
            $where[] = ['offlined','=',1];//线下录取
            $where[] = ['admission_type','=',0];
            $offline_admission_arr = Db::name("UserApply")->where($where)
                ->group('result_school_id')->column('COUNT(*)', 'result_school_id');
            //学籍统计
            $where = [];
            $where[] = ['deleted', '=', 0];
            $where[] = ['registed', '=', 1];
            $school_roll = Db::name('SysSchoolRoll')->where($where)->group('school_id')->column('COUNT(*)', 'school_id');

            $where = [];
            $where[] = ['deleted','=',0];
            $where[] = ['disabled','=',0];
            $school_ids = Db::name('SysSchool')->field('id, finished')->where($where)->select()->toArray();

            foreach ($school_ids as $item){
                $area = new WorkAreaReport();
                $school_id = $item['id'];

                $data = [];
                $data['school_id'] = $school_id;
                $data['degree_count'] = isset($school_degree_arr[$school_id]) ? $school_degree_arr[$school_id] : 0;
                $data['check_address_count'] = isset($result['check_list'][$school_id]) ? $result['check_list'][$school_id] : 0;
                $data['finished_count'] = $item['finished'];
                $data['sixth_grade_count'] = isset($sixth_grade_arr[$school_id]) ? $sixth_grade_arr[$school_id] : 0;
                $data['offline_admission_count'] = isset($offline_admission_arr[$school_id]) ? $offline_admission_arr[$school_id] : 0;
                $data['school_roll_count'] = isset($school_roll[$school_id]) ? $school_roll[$school_id] : 0;

                if(isset($area_arr[$school_id])){
                    $data['id'] = $area_arr[$school_id];
                    WorkAreaReport::update($data);
                }else {
                    $area->save($data);
                }
            }

            //按区县统计
            $municipal_arr = Db::name('WorkMunicipalReportForm')->where('deleted', 0)->column('id', 'region_id');

            $res_municipal = $this->getMunicipalStatistics();
            if($res_municipal['code'] == 0){
                return ['code' => 0, 'msg' => $res_municipal['msg'] ];
            }

            $region = Cache::get('region');
            $municipal_data = $res_municipal['list'];
            foreach ((array)$region as $item){
                if($item['disabled'] == 0 && $item['deleted'] == 0 ) {
                    $municipal = new WorkMunicipalReport();
                    $region_id = $item['id'];

                    $data = [];
                    $data['region_id'] = $region_id;
                    $data['school_count'] = isset($municipal_data[$region_id]['school_count']) ? $municipal_data[$region_id]['school_count'] : 0;
                    $data['degree_count'] = isset($municipal_data[$region_id]['degree_count']) ? $municipal_data[$region_id]['degree_count'] : 0;
                    $data['check_address_count'] = isset($municipal_data[$region_id]['check_address_count']) ? $municipal_data[$region_id]['check_address_count'] : 0;
                    $data['finished_count'] = isset($municipal_data[$region_id]['finished_count']) ? $municipal_data[$region_id]['finished_count'] : 0;
                    $data['sixth_grade_count'] = isset($municipal_data[$region_id]['sixth_grade_count']) ? $municipal_data[$region_id]['sixth_grade_count'] : 0;
                    $data['middle_admission_count'] = isset($municipal_data[$region_id]['middle_admission_count']) ? $municipal_data[$region_id]['middle_admission_count'] : 0;
                    $data['school_roll_count'] = isset($municipal_data[$region_id]['school_roll_count']) ? $municipal_data[$region_id]['school_roll_count'] : 0;
                    $data['not_check_address_count'] = isset($result['not_check_list'][$region_id]) ? $result['not_check_list'][$region_id] : 0;

                    if(isset($municipal_arr[$region_id])){
                        $data['id'] = $municipal_arr[$region_id];
                        WorkMunicipalReport::update($data);
                    }else{
                        $municipal->save($data);
                    }
                }
            }

            Db::commit();

            return ['code' => 1, 'msg' => '初始化成功！'];
        } catch (\Exception $exception) {
            Db::rollback();
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }
    }

    //房产统计
    private function getSchoolHouse(): array
    {
        try {
            $region = Cache::get('region');

            $not_check_list = [];
            $table_name_list = [];
            foreach ((array)$region as $item){
                if($item['parent_id'] > 0 && $item['disabled'] == 0 && $item['deleted'] == 0 ) {
                    $table_name_list[] = 'sys_address_' . $item['simple_code'];

                    //区县未勾选数量
                    $not_check_list[$item['id']] = Db::name('sys_address_' . $item['simple_code'])
                        ->where('middle_school_id', 0)->where('primary_school_id', 0)
                        ->where('deleted', 0)->count();
                }
            }
            $table_name_first = array_shift($table_name_list);

            //缩略详细学校勾选情况
            $query = Db::name($table_name_first)
                ->field('primary_school_id, middle_school_id, COUNT(*) AS check_count')
                ->where('primary_school_id|middle_school_id', '>', 0)
                ->where('deleted', 0)
                ->group('primary_school_id,middle_school_id');

            foreach ($table_name_list as $table_name) {
                $child_query = Db::name($table_name)->field('primary_school_id, middle_school_id, COUNT(*) AS check_count')
                    ->where('primary_school_id|middle_school_id', '>', 0)->where('deleted', 0)
                    ->group('primary_school_id,middle_school_id')->buildSql();
                $query->unionAll($child_query);
            }
            $list = $query->select()->toArray();

            $check_list = [];
            foreach ($list as $item){
                if($item['primary_school_id'] > 0){
                    if(isset($check_list[$item['primary_school_id']])){
                        $check_list[$item['primary_school_id']] += $item['check_count'];
                    }else{
                        $check_list[$item['primary_school_id']] = $item['check_count'];
                    }
                }
                if($item['middle_school_id'] > 0){
                    if(isset($check_list[$item['middle_school_id']])){
                        $check_list[$item['middle_school_id']] += $item['check_count'];
                    }else{
                        $check_list[$item['middle_school_id']] = $item['check_count'];
                    }
                }
            }

            //完整地址学校勾选情况
            $list = Db::name('SysAddressIntact')
                ->field('primary_school_id, middle_school_id, COUNT(*) AS check_count')
                ->where('primary_school_id|middle_school_id', '>', 0)->where('deleted', 0)
                ->group('primary_school_id, middle_school_id')->select()->toArray();

            foreach ($list as $item){
                if($item['primary_school_id'] > 0){
                    if(isset($check_list[$item['primary_school_id']])){
                        $check_list[$item['primary_school_id']] += $item['check_count'];
                    }else{
                        $check_list[$item['primary_school_id']] = $item['check_count'];
                    }
                }
                if($item['middle_school_id'] > 0){
                    if(isset($check_list[$item['middle_school_id']])){
                        $check_list[$item['middle_school_id']] += $item['check_count'];
                    }else{
                        $check_list[$item['middle_school_id']] = $item['check_count'];
                    }
                }
            }

            //完整地址区县未勾选
            $not_check_arr = Db::name('SysAddressIntact')->where('primary_school_id', 0)
                ->where('middle_school_id', 0)->where('deleted', 0)
                ->group('region_id')->column('COUNT(*) AS not_check_count', 'region_id');
            foreach ($not_check_arr as $key => $val){
                if(isset($not_check_list[$key])){
                    $not_check_list[$key] += $val;
                }else{
                    $not_check_list[$key] = $val;
                }
            }

            return ['code' => 1, 'check_list' => $check_list, 'not_check_list' => $not_check_list];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
    }

    //区级统计
    private function getMunicipalStatistics(): array
    {
        try {
            $region = Cache::get('region');

            $list = [];
            foreach ((array)$region as $item) {
                if ($item['disabled'] == 0 && $item['deleted'] == 0) {
                    $region_id = $item['id'];
                    //市直
                    if ($region_id == 1) {
                        //市直学校ID
                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['disabled', '=', 0];
                        $where[] = ['directly', '=', 1];//市直
                        $directly_school_ids = Db::name("SysSchool")->where($where)->column('id');
                        //学校数量
                        $school_count = count($directly_school_ids);
                        $where[] = ['finished', '=', 1];
                        //校情完成数量
                        $finished_count = Db::name("SysSchool")->where($where)->count();
                        //审核学位数量
                        $degree_count = Db::name('PlanApply')
                            ->where('status', 1)
                            ->where('deleted', 0)->where('school_id', 'in', $directly_school_ids)
                            ->group('school_id')->sum('spare_total');
                        //房产勾选数量
                        $check_address_count = Db::name('WorkAreaReportForm')->where('deleted', 0)
                            ->where('school_id', 'in', $directly_school_ids)
                            ->sum('check_address_count');
                        //六年级数量
                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['graduation_school_id', 'in', $directly_school_ids];
                        $sixth_grade_count = Db::name('SixthGrade')->where($where)->count();
                        //学籍数量
                        $school_roll_count = Db::name('WorkAreaReportForm')->where('deleted', 0)
                            ->where('school_id', 'in', $directly_school_ids)
                            ->sum('school_roll_count');
                        //中心学校录取数量
                        $middle_admission_count = 0;
                    } else {
                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['disabled', '=', 0];
                        $where[] = ['directly', '=', 0];//市直
                        $where[] = ['region_id', '=', $region_id];//区县
                        $all_school_ids = Db::name("SysSchool")->where($where)->column('id');
                        //学校数量
                        $school_count = count($all_school_ids);;
                        //校情完成数量
                        $finished_count = Db::name("SysSchool")->where($where)->where('finished', 1)->count();
                        //不是教学点
                        $school_ids = Db::name("SysSchool")->where($where)->where('central_id', 0)->column('id');

                        //学校学位
                        $degree_count = Db::name('PlanApply')
                            ->where('status', 1)
                            ->where('deleted', 0)->where('school_id', 'in', $school_ids)
                            ->group('school_id')->sum('spare_total');

                        $where = [];
                        $where[] = ['deleted','=',0];
                        $where[] = ['disabled','=',0];
                        $where[] = ['region_id','=',$region_id];
                        $central_ids = Db::name("CentralSchool")->where($where)->column('id');

                        //教管会学位
                        $degree_count += Db::name('PlanApply')
                            ->where('central_id', 'in', $central_ids)
                            ->where('status', 1)->where('deleted', 0)->sum('spare_total');

                        //房产勾选数量
                        $check_address_count = Db::name('WorkAreaReportForm')->where('deleted', 0)
                            ->where('school_id', 'in', $all_school_ids)
                            ->sum('check_address_count');
                        //六年级数量
                        $where = [];
                        $where[] = ['deleted', '=', 0];
                        $where[] = ['graduation_school_id', 'in', $all_school_ids];
                        $sixth_grade_count = Db::name('SixthGrade')->where($where)->count();
                        //学籍数量
                        $school_roll_count = Db::name('WorkAreaReportForm')->where('deleted', 0)
                            ->where('school_id', 'in', $all_school_ids)
                            ->sum('school_roll_count');
                        //中心学校录取数量
                        $middle_admission_count = 0;
                        $where = [];
                        $where[] = ['deleted','=',0];
                        $where[] = ['voided','=',0];
                        $where[] = ['resulted','=',1];
                        $where[] = ['result_school_id','in', $all_school_ids];
                        $middle_admission_count += Db::name("UserApply")->where($where)->count();
                    }

                    $list[$region_id]['school_count'] = $school_count;
                    $list[$region_id]['degree_count'] = $degree_count;
                    $list[$region_id]['check_address_count'] = $check_address_count;
                    $list[$region_id]['finished_count'] = $finished_count;
                    $list[$region_id]['sixth_grade_count'] = $sixth_grade_count;
                    $list[$region_id]['middle_admission_count'] = $middle_admission_count;
                    $list[$region_id]['school_roll_count'] = $school_roll_count;
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

}