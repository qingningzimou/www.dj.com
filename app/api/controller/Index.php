<?php
namespace app\api\controller;

use app\common\controller\Education;
use think\facade\Cache;
use app\common\model\User as model;
use app\common\model\Manage;
use app\common\model\SysRegion;
use app\common\model\SysRoleNodes;
use app\common\model\Schools;
use app\common\model\ManageNodes;
use app\common\model\SysNodes;
use app\common\validate\Index as validate;
use think\response\Json;
use think\facade\Lang;
use think\facade\Db;
use dictionary\FilterData;
use Overtrue\Pinyin\Pinyin;
use sm\TxSm2;
use sm\TxSm4;


class Index extends Education
{
    public function school()
    {
        try {
            set_time_limit(0);
            ob_end_clean();
            ob_implicit_flush();
            session_write_close();
            header('X-Accel-Buffering: no'); // 关键是加了这一行。
            header('Cache-Control: no-cache');
            header('Connection:keep-alive');
            echo str_pad('',1024);
//            self::actRecurse('420600000000');
//            $ddd = decrypt('cn1YRgK7nJXeA6poAekpxQ34/y8kt74mfSvKUZMhTpa+A6D8m7Ca9dwbI72MZjuh');
//dump($ddd);
//            $data = Db::name('unit')
//                ->where('disabled',0)
//                ->where('deleted',0)
//                ->select()->toArray();
//            $pinyin = new Pinyin();

//            $tableData =  array_column($tableListData,'id');
//            dump($tableData);
//foreach ($data as $value){
//    Db::name('unit')
//        ->where('id',$value['id'])
//        ->update([
//            'simple_code' => $pinyin->abbr($value['unit_name'])
//        ]);
//}

//            $flag = Cache::delete('g'.md5(196));
            echo 'end';
            die;

            $res = [
                'code' => 1,
                'data' => $rest
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return json($res);
    }

    public function upid()
    {
        try {
            set_time_limit(0);
            ob_end_clean();
            ob_implicit_flush();
            session_write_close();
            header('X-Accel-Buffering: no'); // 关键是加了这一行。
            header('Cache-Control: no-cache');
            header('Connection:keep-alive');
            echo str_pad('',1024);

            $tableListData =  Db::table('deg_sys_address_temp')
                ->where('deleted',0)
                ->select()->toArray();
            $num = 0;
            foreach ($tableListData as $k => $v){
                $addressData =Db::table('deg_sys_address_420626_copy')
                    ->where('address',$v['address'])->find();
                 Db::table('deg_sys_address_420626_copy')
                    ->where('id',$addressData['id'])
                    ->update([
                        'primary_school_name' => $v['primary_school_name'],
                        'primary_school_id' => $v['primary_school_id']
                    ]);
                Db::table('deg_sys_address_simple_copy')->where('id',$addressData['simple_id'])
                    ->update([
                        'primary_school_name' => $v['primary_school_name'],
                        'primary_school_id' => $v['primary_school_id']
                    ]);
                $num += 1;
                echo $v['id'].'<br>';
            }
            echo 'num='.$num.'<br>';
//            $tableData =  array_column($tableListData,'id');
//            dump($tableData);
//foreach ($data as $value){
//    Db::name('unit')
//        ->where('id',$value['id'])
//        ->update([
//            'simple_code' => $pinyin->abbr($value['unit_name'])
//        ]);
//}

//            $flag = Cache::delete('g'.md5(196));
            echo 'end';
            die;

            $res = [
                'code' => 1,
                'data' => $rest
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return json($res);
    }

    public function index()
    {
        try {
            set_time_limit(0);
            ob_end_clean();
            ob_implicit_flush();
            session_write_close();
            header('X-Accel-Buffering: no'); // 关键是加了这一行。
            header('Cache-Control: no-cache');
            header('Connection:keep-alive');
            echo str_pad('',1024);
//            self::actRecurse('420600000000');
//            $ddd = decrypt('cn1YRgK7nJXeA6poAekpxQ34/y8kt74mfSvKUZMhTpa+A6D8m7Ca9dwbI72MZjuh');
//dump($ddd);
//            $data = Db::name('unit')
//                ->where('disabled',0)
//                ->where('deleted',0)
//                ->select()->toArray();
//            $pinyin = new Pinyin();

//            $tableData =  array_column($tableListData,'id');
//            dump($tableData);
//foreach ($data as $value){
//    Db::name('unit')
//        ->where('id',$value['id'])
//        ->update([
//            'simple_code' => $pinyin->abbr($value['unit_name'])
//        ]);
//}

//            $flag = Cache::delete('g'.md5(196));
            echo 'end';
            die;

            $res = [
                'code' => 1,
                'data' => $rest
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return json($res);
    }


    /**
     * 获取指定配置项数据
     * @param keyword    关键词
     * @return Json
     */
    public function getConfig()
    {
        if ($this->request->isPost()) {
            try {
                $data = Cache::get($this->result['item_key']);
                if(empty($data))
                {
                   throw new \Exception('配置不存在');
                }
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
     * 获取字段选项
     * @param field_name 字段名
     * @return Json
     */
    public function getDictionary()
    {
        if ($this->request->isPost()) {
            try {
                if(!$this->request->has('field_name') || !$this->result['field_name'])
                {
                    throw new \Exception('提交的字段为空');
                }
                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary',$this->result['field_name']);
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $res = [
                    'code' => 1,
                    'data' => $getData['data']
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
     * 获取用户信息/菜单
     * @return Json
     */
    public function info()
    {
        if ($this->request->isPost()) {
            try {
                //  如果资源分类数据不合法，则返回
                $preg= '/^\d+$/u';
                if (preg_match($preg,$this->result['node_type']) == 0){
                    throw new \Exception('资源分类应该为数字');
                }
                $roles = Db::name('SysRoles')
                    ->where('id', $this->userInfo['role_id'])
                    ->where('disabled', 0)
                    ->where('deleted', 0)
                    ->field([
                        'role_name',
                        'disabled'
                    ])
                    ->find();
                if($this->userInfo['role_id'] && empty($roles) && !$this->userInfo['defaulted']){
                    throw new \Exception('角色已经禁用');
                }
                $node_ids = (new ManageNodes())->where([
                    'manage_id' => $this->userInfo['manage_id'],
                    'node_type' => $this->result['node_type']
                ])->column('node_id');
                if($this->school_grade == $this->userInfo['grade_id']) {
                    //功能模块管控
                    $module = Cache::get('module',[]);
                    $sysnodes = Cache::get('nodes');
                    $defNodes = array_values(filter_by_value($sysnodes, 'defaulted', 1));
                    $theTime = time();
                    $moduleNodes = [];
                    $moduleNodes = array_merge($moduleNodes,array_column($defNodes,'id'));
                    foreach ($module as $item) {
                        if (date_to_unixtime($item['start_time']) < $theTime && date_to_unixtime($item['end_time']) > $theTime  && $item['region_id'] == $this->userInfo['region_id']) {
                            $nodeIds = Db::name('moduleNodes')->where([
                                'module_id' => $item['id'],
                                'deleted' => 0,
                            ])->column('node_id');
                            $moduleNodes = array_merge($moduleNodes, $nodeIds);
                        }
                    }
                    $exceptionNodes = Db::name('SysSchoolNodes')->where([
                        'school_id' => $this->userInfo['school_id'],
                        'deleted' => 0,
                    ])->column('node_id');
                    $moduleNodes = array_unique(array_merge($moduleNodes, $exceptionNodes));

                    //获取触发管控条件的资源与用户资源的交集
                    $moduleIds = array_intersect($node_ids, $moduleNodes);
                    //如果是线下招生需要过滤部分资源
                    $school = (new Schools())->where('id',$this->userInfo['school_id'])->find();
                    $dictionary = new FilterData();
                    if(!$school['onlined']){
                        $getData = $dictionary->resArray('dictionary', 'SYSXXGLZY');
                        if(!$getData['code']){
                            throw new \Exception($getData['msg']);
                        }
                        $filter_data = array_column($getData['data'],'dictionary_value');
                        $filter_ids = (new SysNodes())->whereIn('controller_name',$filter_data)->column('id');
                        $moduleIds = array_diff($moduleIds,$filter_ids);
                    }else{
                        $getData = $dictionary->resArray('dictionary', 'SYSXSGLZY');
                        if(!$getData['code']){
                            throw new \Exception($getData['msg']);
                        }
                        $filter_data = array_column($getData['data'],'dictionary_value');
                        $filter_ids = (new SysNodes())->whereIn('controller_name',$filter_data)->column('id');
                        $moduleIds = array_diff($moduleIds,$filter_ids);
                    }
                    $nodes = (new SysNodes())->whereIn('id',$moduleIds)->where('displayed',1)->hidden(['deleted'])->order('order_num')->select()->toArray();

                }else{
                    if($this->userInfo['defaulted']){
                        $nodes = (new SysNodes())->where('displayed',1)->hidden(['deleted'])->order('order_num')->select()->toArray();
                    }else{
                        $nodes = (new SysNodes())->whereIn('id',$node_ids)->where('displayed',1)->hidden(['deleted'])->order('order_num')->select()->toArray();
                    }
                }


                $role_name = $roles['role_name'];
//                $msg_num = (new Message())
//                    ->where('from_type',2)
//                    ->whereOr('from_type',4)
//                    ->where([
//                        'to_id' => $this->result['user_id'],
//                        'readed' => 0
//                    ])->count();
                $res = [
                    'code' => 1,
                    'data' => [
                        'avatar' => $this->userInfo['avatar'],
                        'nick_name' => $this->userInfo['nick_name'],
                        'user_name' => $this->userInfo['user_name'],
                        'role_ids' => $this->userInfo['role_ids'],
                        'refresh_pass' => $this->userInfo['refresh_pass'],
                        'role_name' => $role_name,
                        'nodes' => $nodes,
                        'msg_num' => 0,
                    ]
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
     * 获取页面表单hash
     * @param page_hash 页面唯一标识
     * @param user_id   用户自增ID
     * @return Json
     */
    public function getHash()
    {
        if ($this->request->isPost()) {
            try {
                $hash = set_hash('user'.$this->result['user_id'],$this->result['page_hash']);
                $hashKey = 'hash_' . md5($this->result['page_hash'].'user'.$this->result['user_id']);
                //  生成hash
                if (!Cache::set($hashKey, $hash,600)) {
                    throw new \Exception(Lang::get('hash_get_fail'));
                }
                $res = [
                    'code' => 1,
                    'data' => $hash
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
     * 读取消息列表
     * @param user_id   管理员自增ID
     * @return Json
     */
    public function getMsgList()
    {
        if ($this->request->isPost()) {
            try {
                $list = (new Message())->where(['to_user_id' => $this->result['user_id']])->select()->toArray();
                $res = [
                    'code' => 1,
                    'data' => $list
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
     * 退出登录
     * @param user_id   用户自增ID
     * @return Json
     */
    public function logout()
    {
        if ($this->request->isPost()) {
            $flag = Cache::delete('user'.md5($this->result['user_id']));
            if ($flag) {
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('logout_success')
                ];
            } else {
                $res = [
                    'code' => 0,
                    'msg' => Lang::get('logout_fail')
                ];
            }
            return json($res);
        }
    }

    /**
     * 更改密码
     * @return \think\response\Json
     */
    public function changePassword()
    {
        if ($this->request->isPost()) {
            try {
                //  获取请求数据
                $data = $this->request->only([
                    'data',
                    'key',
                ]);
                $sm2 = new TxSm2();
                $key = $sm2->privateDecrypt($data['key']);
                if (empty($key)){
                    throw new \Exception('数据错误解密失败');
                }
                $sm4 = new TxSm4();
                $userdata = json_decode($sm4->decrypt($key, $data['data']),true);
//                $userdata =  json_decode(openssl_decrypt($data['data'] , 'sm4-ecb' , $key),true);
                //如果password数据不合法，则返回
                $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                if (preg_match($preg,$userdata['password']) == 0){
                    throw new \Exception(Lang::get('password_format_fail'));
                }
                $password = password_hash($userdata['password'], PASSWORD_DEFAULT);
                Db::name('users')
                    ->where('id', $this->result['user_id'])
                    ->update([
                        'password' => $password,
                    ]);
                Cache::set('user'.md5($this->result['user_id']),$this->userInfo);
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?? Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 获取路径地图配置信息
     * @return Json
     */
    public function getWelConfig()
    {
        if ($this->request->isPost()) {
            try {
                $data['line_map_url'] = Cache::get('line_map_url');
                $data['map_key'] = Cache::get('map_key');
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?? Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 获取关联的区域信息
     * @return Json
     */
    public function getRegion()
    {
        if ($this->request->isPost()) {
            try {
                $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                $data = (new SysRegion())
                    ->whereIn('id',$region_ids)->hidden(['deleted'])
                    ->where('parent_id','>',0)
                    ->where('disabled',0)
                    ->select()->toArray();
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
     * 获取任务信息
     * @return Json
     */
    public function getTask()
    {
        if ($this->request->isPost()) {
            try {
                $audit_year = Cache::get('audit_year');
                $data['grade_id'] = $this->userInfo['grade_id'];
                if ($this->userInfo['relation_region']){
                    $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                    $where[] = ['institute.region_id', 'in', $region_ids];
                    $data['worksheet'] = Db::name('InstituteWorksheet')
                        ->alias('institute_worksheet')
                        ->join('Institute institute',['institute_worksheet.institute_id = institute.id', 'institute.disabled = 0','institute.deleted = 0'])
                        ->join('InstituteAudit institute_audit',['institute_audit.institute_id = institute.id', 'institute_audit.disabled = 0','institute_audit.deleted = 0'])
                        ->where($where)
                        ->where([
                            'institute_audit.audit_time' => $audit_year,
                            'institute_worksheet.apply_state' => 0,
                            'institute_worksheet.disabled' => 0,
                            'institute_worksheet.deleted' => 0,
                        ])
                        ->count('institute_worksheet.id');
                    $data['complaint'] = Db::name('Complaint')
                        ->whereIn('region_id',$region_ids)
                        ->where('completed',0)
                        ->where('disabled',0)
                        ->where('deleted',0)
                        ->count();
                }
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
     * 获取信息
     * @return Json
     */
    public function getChart()
    {
        if ($this->request->isPost()) {
            try {
                $region = Cache::get('region');
                $guardian = Cache::get('guardian');
                $students = Cache::get('students');
                $dictionary = Cache::get('dictionary');
                $regionIds = array_column($region,'id');
                $grade_id = $this->userInfo['grade_id'];
                $audit_year = Cache::get('audit_year');
                $complaint_step = Cache::get('complaint_step');
                $data['audit_year'] = $audit_year;
                if($grade_id > 2){
                    $complaint = Db::name('Complaint')
                        ->where([
                            'disabled' => 0,
                            'deleted' => 0,
                        ])
                        ->select()->toArray();
                    $institute = Db::name('Institute')
                        ->alias('institute')
                        ->join('InstituteWorksheet institute_worksheet',['institute_worksheet.institute_id = institute.id', 'institute_worksheet.disabled = 0','institute_worksheet.deleted = 0','institute_worksheet.audit_time = '.$audit_year],'left')
                        ->where([
                            'institute.disabled' => 0,
                            'institute.deleted' => 0,
                        ])
                        ->field([
                            'institute.id',
                            'institute.region_id',
                            'institute.blacklist',
                            'institute_worksheet.apply_state'
                        ])
                        ->select()->toArray();
                    foreach ($regionIds as $region_id){
                        $guardianData = array_values(filter_by_value($guardian, 'region_id', $region_id));
                        $regionData = array_values(filter_by_value($region, 'id', $region_id));
                        $complaintData  = array_values(filter_by_value($complaint, 'region_id', $region_id));
                        $instituteData  = array_values(filter_by_value($institute, 'region_id', $region_id));
                        $instituteUniqueData = array_unique_value($instituteData, 'id');
                        $instituteWait  = array_values(filter_by_value($instituteData, 'apply_state', 0));
                        $institutePass  = array_values(filter_by_value($instituteData, 'apply_state', 3));
                        $instituteBlack  = array_values(filter_by_value($instituteData, 'blacklist', 1));
                        $data['data'][] = [
                            'region_name' => $regionData[0]['region_name'],
                            'guardian_num' => count($guardianData),
                            'complaint_num' => count($complaintData),
                            'institute_num' => count($instituteUniqueData),
                            'institute_wait' => count($instituteWait),
                            'institute_pass' => count($institutePass),
                            'institute_black' => count($instituteBlack),
                        ];
                    }
                    $data['other'] = count(array_values(filter_by_value($guardian, 'region_id', 0)));
                }else{
                    $guardianData = array_values(filter_by_value($guardian, 'region_id', $this->userInfo['region_id']));
                    $studentsData = array_values(filter_by_value($students, 'region_id', $this->userInfo['region_id']));
                    $scheduleNum = Db::name('RelationStudentsSchedule')
                        ->field([
                            'students_id',
                            'schedule_id',
                        ])
                        ->distinct(true)
                        ->where([
                            'region_id' => $this->userInfo['region_id'],
                            'disabled' => 0,
                            'deleted' => 0,
                        ])
                        ->group(['students_id','schedule_id'])
                        ->count();
                    $data['guardian'] = [
                        'guardian_num' => count($guardianData),
                        'students_num' => count($studentsData),
                        'students_institute_num' => $scheduleNum,
                    ];
                    $complaintDatas = Db::name('Complaint')
                        ->where('region_id',$this->userInfo['region_id'])
                        ->where([
                            'disabled' => 0,
                            'deleted' => 0,
                        ])
                        ->select()->toArray();
                    $data['step'] = [];
                    $complaintStepData = array_unique(array_values(array_column($complaintDatas,'step_state')));
                    foreach ($complaintStepData as $step_id){
                        $stepData = array_values(filter_by_value($complaint_step, 'id', $step_id));
                        $complaintData  = array_values(filter_by_value($complaintDatas, 'step_state', $step_id));
                        if(count($stepData) > 0){
                            $data['step'][] = [
                                'step_name' => $stepData[0]['step_name'],
                                'complaint_num' => count($complaintData),
                            ];
                        }else{
                            $data['step'][] = [
                                'step_name' => '尚未受理',
                                'complaint_num' => count($complaintData),
                            ];
                        }
                    }
                    $instituteData = Db::name('Institute')->where([
                            'disabled' => 0,
                            'deleted' => 0,
                            'region_id' => $this->userInfo['region_id']
                        ])
                        ->select()->toArray();
                    $instituteAuditData = Db::name('InstituteAudit')
                        ->where([
                            'disabled' => 0,
                            'deleted' => 0,
                            'audit_time' => $audit_year,
                            'region_id' => $this->userInfo['region_id']
                        ])
                        ->select()->toArray();
                    $instituteWait = array_values(filter_by_value($instituteAuditData, 'audited', 0));
                    $institutePass  = array_values(filter_by_value($instituteAuditData, 'audited', 1));
                    $instituteBlack  = array_values(filter_by_value($instituteData, 'blacklist', 1));
                    $instituteIds = array_column($institutePass,'institute_id');
                    $data['institute'] = [
                        'institute_num' => count($instituteData),
                        'institute_wait' => count($instituteWait),
                        'institute_pass' => count($institutePass),
                        'institute_black' => count($instituteBlack),
                    ];
                    $categoryDatas = Db::name('RelationInstituteCategory')
                        ->whereIn('institute_id',$instituteIds)
                        ->where([
                            'disabled' => 0,
                            'deleted' => 0,
                        ])
                        ->select()->toArray();
                    $dictionaryDatas = array_values(filter_by_value($dictionary, 'field_name', '培训类型'));
                    if (count($dictionaryDatas) > 0){
                        $dictionaryDatas = array_values(filter_by_value($dictionary, 'parent_id', $dictionaryDatas[0]['id']));
                    }
                    foreach ($dictionaryDatas as $category_id){
                        $dictionaryData = array_values(filter_by_value($dictionaryDatas, 'field_value', $category_id['field_value']));
                        if (count($dictionaryData) > 0) {
                            $data['institute']['category'][] = [
                                'category_name' => $dictionaryData[0]['field_name'],
                                'category_num'  => count(array_values(filter_by_value($categoryDatas, 'category_id', $category_id)))
                            ];
                        }else{
                            $data['institute']['category'] = [];
                        }
                    }
                }
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
     * 文件上传
     * @return Json
     */
    public function upfiles()
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->only([
                    'source',
                    'source_id',
                    'source_type',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'upload');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                // 获取上传的文件，如果有上传错误，会抛出异常
                $file = request()->file('file');
                // 如果上传的文件为null，手动抛出一个异常，统一处理异常
                if (null === $file) {
                    // 异常代码使用UPLOAD_ERR_NO_FILE常量，方便需要进一步处理异常时使用
                    throw new \Exception('请上传文件');
                }
                // 保存路径
                $savePath = 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR;
                $info = $file->validate(['size' => 1 * 1024 * 1024,'ext' => 'doc,docx,xls,xlsx'])->move($savePath);
                if (!$info) {
                    throw new \Exception('文件格式不符合要求，大小在1M内');
                };
                // 拼接URL路径
                $url = str_replace("\\","/", DIRECTORY_SEPARATOR.$savePath . str_replace('\\', '/', $info->getSaveName()));
                $file_id = Db::name('UploadFiles')
                    ->insertGetId([
                        'manage_id' => $this->result['user_id'],
                        'file_path' => str_replace("\\","/",$url),
                        'file_small' => str_replace("\\","/",$url),
                        'file_type' => $info->getExtension(),
                        'file_size' => $info->getSize(),
                        'source' => $postData['source'],
                        'source_id' => $postData['source_id'],
                        'source_type' => $postData['source_type'],
                        'create_time' => time(),
                        'create_ip' => ip2long($this->request->ip())
                    ]);
                $data['file_id'] =$file_id;
                $data['file_path'] = $url;
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
     * 图片上传
     * @return Json
     */
    public function uploads()
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->only([
                    'source',
                    'source_id',
                    'source_type',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'upload');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                // 获取上传的文件，如果有上传错误，会抛出异常
                $file = request()->file('file');
                // 如果上传的文件为null，手动抛出一个异常，统一处理异常
                if (null === $file) {
                    // 异常代码使用UPLOAD_ERR_NO_FILE常量，方便需要进一步处理异常时使用
                    throw new \Exception('请上传文件');
                }
                $image = \think\Image::open($file);
                // 保存路径
                $savePath = 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR;
                $info = $file->validate(['size' => 5 * 1024 * 1024,'ext' => 'jpg,jpeg,png,gif,bmp'])->move($savePath);
                if (!$info) {
                    throw new \Exception('文件格式不符合要求，大小在5M内');
                };
                // 拼接URL路径
                $url = str_replace("\\","/", DIRECTORY_SEPARATOR.$savePath . str_replace('\\', '/', $info->getSaveName()));
                $tmp_name = $info->getFilename();
                $tmp_name = explode(".",$tmp_name)[0].'-s.'.$info->getExtension();
                $savePath = $savePath.date("Ymd").DIRECTORY_SEPARATOR;
                $smallurl = str_replace("\\","/", DIRECTORY_SEPARATOR.$savePath . str_replace('\\', '/', $tmp_name));
                $image->thumb(200, 200)->save($savePath.$tmp_name);
                $file_id = Db::name('UploadFiles')
                    ->insertGetId([
                        'manage_id' => $this->result['user_id'],
                        'file_path' => str_replace("\\","/",$url),
                        'file_small' => str_replace("\\","/",$smallurl),
                        'file_type' => $info->getExtension(),
                        'file_size' => $info->getSize(),
                        'source' => $postData['source'],
                        'source_id' => $postData['source_id'],
                        'source_type' => $postData['source_type'],
                        'create_time' => time(),
                        'create_ip' => ip2long($this->request->ip())
                    ]);
                $data['file_id'] =$file_id;
                $data['file_path'] = $url;
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

    /* UEditor设置 */
    public function getUeditorSet(){
        header("Content-Type: text/html; charset=utf-8");
        error_reporting(E_ERROR);
        $action = request()->get('action');
        $pageid = request()->get('pageid');
        // 登录状态
        $CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents($_SERVER['DOCUMENT_ROOT'].'/public/config.json')), true);

        switch ($action){
            case 'config':
                $result = json_encode($CONFIG);
                break;
            case 'uploadimage':
                $fieldName = $CONFIG['imageFieldName'];
                $maxSize = $CONFIG['imageMaxSize'];
                $this->upimage($fieldName,$maxSize,$pageid);
                break;
            case 'uploadscrawl':
                $fieldName = $CONFIG['scrawlFieldName'];
                $maxSize = $CONFIG['scrawlMaxSize'];
                $this->upBase64($fieldName,$maxSize,$pageid);
                break;
            case 'uploadvideo':
                $fieldName = $CONFIG['videoFieldName'];
                $maxSize = $CONFIG['videoMaxSize'];
                $this->upvideo($fieldName,$maxSize,$pageid);
                break;
            case 'uploadfile':
                $fieldName = $CONFIG['fileFieldName'];
                $maxSize = $CONFIG['fileMaxSize'];
                $this->upfile($fieldName,$maxSize,$pageid);
                break;
            case 'listimage':
                $result = $this->ueditorList(1);
                break;
            case 'listfile':
                $result = $this->ueditorList(2);
                break;
            case 'catchimage':
                $fieldName = $CONFIG['catcherFieldName'];
                $maxSize = $CONFIG['catcherMaxSize'];
                $result = $this->ueditorCrawler($fieldName,$maxSize,$pageid);
                break;
            default:
                $result = json(array(
                    'state'=> '请求地址出错'
                ));
                break;
        }
        if(request()->get('callback')){
            if(preg_match("/^[\w_]+$/", request()->get('callback'))){
                echo htmlspecialchars(request()->get('callback')) . '(' . $result . ')';
            }else{
                echo json_encode(array('state'=> 'callback参数不合法'));
            }
        }else{
            echo $result;
        }
    }
    /**
     * 处理base64编码的图片上传
     * @echo json {
    "state":"SUCCESS",
    "url":"返回的地址",
    "title":"新文件名",
    "original":"原始文件名",
    }
     */
    private function upBase64($fieldName,$maxSize,$pageid){
        $base64Data = request()->post($fieldName);
        $img = base64_decode($base64Data);
        $fileSize = strlen($img);
        $sys_url = Cache::get('sys_url');
        if ($fileSize > $maxSize) {
            $data = ['state' => '文件大小超出限制'];
            echo json_encode($data);
            return;
        }
        $type = 'png';
        $tmpdir = $_SERVER['DOCUMENT_ROOT']. DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
        $tmppath = DIRECTORY_SEPARATOR. 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
        $tmpnames = md5(microtime(TRUE)).'.'.$type;
        if (!is_dir($tmpdir)){
            mkdir($tmpdir);
        }
        if (file_put_contents($_SERVER['DOCUMENT_ROOT'].$tmpdir.$tmpnames, $img)){
            Db::name('UploadFiles')
                ->insert([
                    'manage_id' => $this->result['user_id'],
                    'file_path' => str_replace("\\","/",$tmppath.$tmpnames),
                    'file_small' => str_replace("\\","/",$tmppath.$tmpnames),
                    'file_type' => $type,
                    'file_size' => $fileSize,
                    'source' => 'base64',
                    'source_id' => 0,
                    'source_type' => $pageid,
                    'create_time' => time(),
                    'create_ip' => ip2long($this->request->ip())
                ]);
            $data = ['state' => 'SUCCESS','url' => $sys_url.str_replace("\\","/",$tmppath.$tmpnames),'title' => $tmpnames,'original' => str_replace("\\","/",$tmppath.$tmpnames)];
        }else{
            $data = ['state' => '意外错误'];
        }
        echo json_encode($data);
    }
    /**
     * 图片上传
     * @echo json {
    "state":"SUCCESS",
    "url":"返回的地址",
    "title":"新文件名",
    "original":"原始文件名",
    }
     */
    private function upimage($fieldName,$maxSize,$pageid){
        $file = request()->file($fieldName);
        if (!$file) {
            $data = ['state' => '找不到上传文件'];
            echo json_encode($data);
            return;
        }
        $sys_url = Cache::get('sys_url');
        $info = $file->getInfo();
        $image = \think\Image::open($file);
        $type = $image->type();
        if ($type == 'jpeg'){
            $type = 'jpg';
        }
        $result = $file->validate(['size'=>$maxSize,'ext'=>'jpg,jpeg,png,gif,bmp']);
        if($result){
            $tmpmicro = md5(microtime(TRUE));
            $tmpdir = $_SERVER['DOCUMENT_ROOT']. DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
            $tmppath = DIRECTORY_SEPARATOR. 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
            $tmpname = $tmpmicro.'.'.$type;
            $tmpnames = $tmpmicro.'-s.'.$type;
            if (!is_dir($tmpdir)){
                mkdir($tmpdir);
            }
            $image->save($tmpdir.$tmpname);
            $image->thumb(200, 200)->save($tmpdir.$tmpnames);
            Db::name('UploadFiles')
                ->insert([
                    'manage_id' => $this->result['user_id'],
                    'file_path' => str_replace("\\","/",$tmppath.$tmpname),
                    'file_small' => str_replace("\\","/",$tmppath.$tmpnames),
                    'file_type' => $type,
                    'file_size' => $info['size'],
                    'source' => 'Activity',
                    'source_id' => 0,
                    'source_type' => $pageid,
                    'create_time' => time(),
                    'create_ip' => ip2long($this->request->ip())
                ]);
            $data = ['state' => 'SUCCESS','url' => $sys_url.str_replace("\\","/",$tmppath.$tmpname),'title' => $tmpname,'original' => $info['name']];
        }else{
            $data = ['state' => $file->getError()];
        }
        echo json_encode($data);
    }
    /**
     * 视频上传
     * @echo json {
    "state":"SUCCESS",
    "url":"返回的地址",
    "title":"新文件名",
    "original":"原始文件名",
    }
     */
    private function upvideo($fieldName,$maxSize,$pageid){
        $file = request()->file($fieldName);
        if (!$file) {
            $data = ['state' => '找不到上传文件'];
            echo json_encode($data);
            return;
        }
        $sys_url = Cache::get('sys_url');
        $info = $file->getInfo();
        $type = substr(strrchr($info['name'],'.'),1);
        $tmpmicro = md5(microtime(TRUE));
        $tmpdir = $_SERVER['DOCUMENT_ROOT']. DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
        $tmppath = DIRECTORY_SEPARATOR. 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
        $tmpname = $tmpmicro.'.'.$type;
        $result = $file->validate(['size'=>$maxSize,'ext'=>'flv,swf,mkv,avi,rm,rmvb,mpeg,mpg,ogg,ogv,mov,wmv,mp4,webm,mp3,wav,mid'])->move($tmpdir,$tmpname);
        if($result){
            Db::name('UploadFiles')
                ->insert([
                    'manage_id' => $this->result['user_id'],
                    'file_path' => str_replace("\\","/",$tmppath.$tmpname),
                    'file_type' => $type,
                    'file_size' => $info['size'],
                    'source' => 'Activity',
                    'source_id' => 0,
                    'source_type' => $pageid,
                    'create_time' => time(),
                    'create_ip' => ip2long($this->request->ip())
                ]);
            $data = ['state' => 'SUCCESS','url' => $sys_url.str_replace("\\","/",$tmppath.$tmpname),'title' => $tmpname,'original' => $info['name']];
        }else{
            $data = ['state' => $file->getError()];
        }
        echo json_encode($data);
    }
    /**
     * 文件上传
     * @echo json {
    "state":"SUCCESS",
    "url":"返回的地址",
    "title":"新文件名",
    "original":"原始文件名",
    }
     */
    private function upfile($fieldName,$maxSize,$pageid){
        $file = request()->file($fieldName);
        if (!$file) {
            $data = ['state' => '找不到上传文件'];
            echo json_encode($data);
            return;
        }
        $sys_url = Cache::get('sys_url');
        $info = $file->getInfo();
        $type = substr(strrchr($info['name'],'.'),1);
        $tmpmicro = md5(microtime(TRUE));
        $tmpdir = $_SERVER['DOCUMENT_ROOT']. DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
        $tmppath = DIRECTORY_SEPARATOR. 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR;
        $tmpname = $tmpmicro.'.'.$type;
        $file_type = Cache::get('file_type');
        $result = $file->validate(['size'=>$maxSize,'ext'=> $file_type ])->move($tmpdir,$tmpname);
        if($result){
            Db::name('UploadFiles')
                ->insert([
                    'manage_id' => $this->result['user_id'],
                    'file_path' => str_replace("\\","/",$tmppath.$tmpname),
                    'file_type' => $type,
                    'file_size' => $info['size'],
                    'source' => 'Activity',
                    'source_id' => 0,
                    'source_type' => $pageid,
                    'create_time' => time(),
                    'create_ip' => ip2long($this->request->ip())
                ]);
            $data = ['state' => 'SUCCESS','url' => $sys_url.str_replace("\\","/",$tmppath.$tmpname),'title' => $tmpname,'original' => $info['name']];
            echo json_encode($data);
            return;
        }else{
            $data = ['state' => $file->getError()];
            echo json_encode($data);
            return;
        }
    }
    /**
     * 抓取图片
     * @return json json_encode(array(
    'state' => count($list) ? 'SUCCESS':'ERROR',
    'list'  => array(
    array(
    "url"       => $info["url"],
    "source"    => htmlspecialchars($imgUrl),
    "state"     => $info["state"]
    ),
    array(
    "url"       => $info["url"],
    "source"    => htmlspecialchars($imgUrl),
    "state"     => $info["state"]
    )
    )
    ));
     */
    private function ueditorCrawler($fieldName,$maxSize,$pageid){
        set_time_limit(0);
        $list = array();
        if (request()->post($fieldName.'/a')) {
            $source = request()->post($fieldName.'/a');
        } else {
            $source = request()->get($fieldName.'/a');
        }
        foreach ($source as $imgUrl) {
            if (strpos($imgUrl, "http") !== 0) {
                $data = ['state' => '链接不是http链接'];
                echo json_encode($data);
                return;
            }
            //获取请求头并检测死链
            $heads = get_headers($imgUrl);
            if (!(stristr($heads[0], "200") && stristr($heads[0], "OK"))) {
                $data = ['state' => '链接不可用'];
                echo json_encode($data);
                return;
            }
            //格式验证(扩展名验证和Content-Type验证)
            $fileType = strtolower(strrchr($imgUrl, '.'));
            if (!in_array($fileType, [".png", ".jpg", ".jpeg", ".gif", ".bmp"]) || stristr($heads['Content-Type'], "image")) {
                $data = ['state' => '链接contentType不正确'];
                echo json_encode($data);
                return;
            }
        }
        die;
        foreach ($source as $imgUrl) {
            $file = new Imgdown($imgUrl,true);
            $image = \think\Image::open($file);
            $width = $image->width();
            $type = $image->type();
            if ($type == 'jpeg'){
                $type = 'jpg';
            }
            $result = $file->validate(['size'=>$maxSize,'ext'=>'jpg,jpeg,png,gif,bmp']);
            if($result){
                $tmpmicro = explode(".",microtime(TRUE))[1];
                $tmpdir = ROOT_PATH.config("appsconf.upload_file_url").DS.format_date(DEF_NOW_TIME,7).DS;
                $tmpname = format_date(DEF_NOW_TIME,9).$tmpmicro.'.'.$type;
                $tmpnames = DS.format_date(DEF_NOW_TIME,9).$tmpmicro.'s.'.$type;
                $tmppath = DS.config("appsconf.upload_file_url").DS.format_date(DEF_NOW_TIME,7).DS;
                if (!is_dir($tmpdir)){
                    mkdir($tmpdir);
                }
                if ($width > 620){
                    $image->save($tmpdir.$tmpname);
                    $image->thumb(620, 960)->text(config("appsconf.upload_version_string"),'msyhbd.ttf',11,'#eeeeee',9,-16)->save($tmpdir.$tmpnames);
                    Db::name('upload')->data([
                        'adminid' => $this->gCkuserid,
                        'photodir' => str_replace("\\","/",$tmppath.$tmpname),
                        'sphotodir' => str_replace("\\","/",$tmppath.$tmpnames),
                        'filetype' => 1,
                        'issource' => 0,
                    ])->insert();
                }else{
                    $image->text(config("appsconf.upload_version_string"),'msyhbd.ttf',11,'#eeeeee',9,-16)->save($tmpdir.$tmpnames);
                    Db::name('upload')->data([
                        'adminid' => $this->gCkuserid,
                        'photodir' => str_replace("\\","/",$tmppath.$tmpnames),
                        'sphotodir' => str_replace("\\","/",$tmppath.$tmpnames),
                        'filetype' => 1,
                        'issource' => 0,
                    ])->insert();
                }
                array_push($list, array(
                    "url" => str_replace("\\","/",$tmppath.$tmpnames),
                    "source" => htmlspecialchars($imgUrl),
                    "state" => 'SUCCESS',
                ));
            }
        }
        $data = ['state' => count($list) ? 'SUCCESS':'ERROR',"list" => $list];
        echo json_encode($data);
        return;
    }

    //查询当前用户所有角色
    public function getUserRoles(){
        if($this->request->isPost()){
            try {
                $userId = $this->userInfo['id'];
                $manangeList  = ( new Manage() )->with('relationRoles')->where('user_id',$userId)->select()->toArray();
                if(count($manangeList) <= 1){
                    throw new \Exception('单个角色不能切换');
                }
                $rolesList = array_column($manangeList,'relationRoles');
                $rolesList = [];
                foreach($manangeList as $key=>$value){
                    foreach($value['relationRoles'] as $_key=>$_value){
                        $rolesList[$_key] = $_value;
                        $rolesList[$_key]['active'] = 0;
                        if($_value['role_grade'] == $this->userInfo['role_id']) {
                            $rolesList[$_key]['active'] = 1;
                        }
                    }
                }
                $res = [
                    'code' => 1,
                    'data' => $rolesList,
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('res_fail')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    //切换角色
    public function switchUserRoles(){
        if($this->request->isPost()) {
            try {
                $data = $this->request->only(['role_id']);
                $checkData = parent::checkValidate($data, \app\common\validate\system\SysRoles::class, 'switch');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                if($data['role_id'] == $this->userInfo['role_id']) {
                    throw new \Exception('不能切换至当前角色');
                };
                $manage = (new Manage())->with('relationRegion')->where('user_id',$this->userInfo['id'])->where('role_id',$data['role_id'])->find()->toArray();
                if(!$manage){
                    throw new \Exception('当前用户没有此角色，不能切换');
                }

                $user_info = $this->userInfo;
                //获取管理账号对应的资源
                $node_ids = (new ManageNodes())->where('manage_id',$manage['id'])->column('node_id');
                $user_info['nodes'] = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->select()->toArray();
                $user_info['manage_id'] = $manage['id'];
                $user_info['grade_id'] = $manage['role_grade'];
                $user_info['school_id'] = $manage['school_id'];
                $user_info['role_id'] = $manage['role_id'];
                $user_info['defaulted'] = $manage['defaulted'];
                $user_info['main_account'] = $manage['main_account'];
                $user_info['central_id'] = $manage['central_id'];
                $user_info['department_id'] = $manage['department_id'];
                $user_info['region_id'] = $manage['region_id'];
                $user_info['public_school_status'] = $manage['public_school_status'];
                $user_info['civil_school_status'] = $manage['civil_school_status'];
                $user_info['primary_school_status'] = $manage['primary_school_status'];
                $user_info['junior_middle_school_status'] = $manage['junior_middle_school_status'];
                if($manage['defaulted']){
                    $regionIds = (new SysRegion())->where('disabled',0)->column('id');
                    $relationRegion = [];
                    foreach ($regionIds as $region_id){
                        $relationRegion[] = [
                            'manage_id' => $manage['id'],
                            'region_id' => $region_id,
                        ];
                    }
                    $user_info['relation_region'] = $relationRegion;
                }else{
                    $user_info['relation_region'] = $manage['relationRegion'];
                }
                Cache::set('user'.md5($user_info['id']),$user_info);
                $res =  [
                    'code' => 1,
                    'msg' => '切换成功',
                ];

            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('res_fail')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }




    /**
     * 修改密码
     * @return \think\response\Json
     */
    public function modifyPassword(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                //  获取请求数据
                $data = $this->request->only([
                    'data',
                    'key',
                ]);
                $sm2 = new TxSm2();
                $key = $sm2->privateDecrypt($data['key']);
                if (empty($key)){
                    throw new \Exception('数据错误解密失败');
                }
                $sm4 = new TxSm4();
                $userdata = json_decode($sm4->decrypt($key, $data['data']),true);

/*                $userdata['old_password'] = 's85217277701~';
                $userdata['password'] = 's85217277700~';
                $userdata['password_confirm'] = 's85217277700~';*/
                //如果password数据不合法，则返回
                $preg = '/^(?=.*[0-9])(?=.*[a-zA-Z])(?=.*[`~!@#$%^&*()_\-+=|\\/?])[0-9a-zA-Z`~!@#$%^&*()_\-+=|\\/?]{8,18}$/u';
                if (preg_match($preg,$userdata['password']) == 0){
                    throw new \Exception(Lang::get('password_format_fail'));
                }

                if ($userdata['password'] != $userdata['password_confirm']){
                    throw new \Exception('两次密码输入不一样');
                }

                if (!$userdata['old_password']){
                    throw new \Exception('原始密码不能为空');
                }

                /*if (!empty($userdata['verify_code'])){
                    //  如果verify_code数据不合法，则返回
                    $preg = '/^[0-9]{6}$/u';
                    if (preg_match($preg,$userdata['verify_code']) == 0){
                        throw new \Exception(Lang::get('captcha_fail'));
                    }
                    if ($userdata['verify_code'] != Cache::get('code_'.md5($userdata['id']))){
                        throw new \Exception(Lang::get('captcha_fail'));
                    }
                }else{
                    throw new \Exception(Lang::get('captcha_none'));
                }*/
                $user = Db::name('user')
                    ->where('id', $this->userInfo['id'])
                    ->where('disabled', 0)
                    ->where('deleted', 0)
                    ->field(['password','id'])
                    ->findOrEmpty();
                //  如果不存在，则返回
                if (empty($user)) {
                    throw new \Exception('账号不存在');
                }
                if(!password_verify($userdata['old_password'], $user['password'])){
                    throw new \Exception('原始密码填写不正确');
                }
                if(password_verify($userdata['password'], $user['password'])){
                    throw new \Exception('新密码不能与原始密码一样');
                }
                $password = password_hash($userdata['password'], PASSWORD_DEFAULT);
                $update_user = (new model())->editData(['id'=>$user['id'],'password'=>$password,'refresh_pass'=>1]);

                if($update_user['code'] == 0){
                    throw new \Exception($update_user['msg']);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return json($res);
        }
    }
}
