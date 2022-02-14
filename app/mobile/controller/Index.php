<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */

namespace app\mobile\controller;

use app\common\model\Course;
use app\common\controller\MobileEducation;
use app\common\model\Department;
use app\common\model\Schools;
use app\common\model\UserReadLog;
use app\common\model\WorkCityConfig;
use app\mobile\model\user\Apply;
use think\facade\Cache;
use think\facade\Filesystem;
use think\facade\Lang;
use think\response\Json;
use app\common\validate\policy\Policy as validate;
use app\common\model\SysRegionStandard;
use think\facade\Db;
use dictionary\FilterData;
use think\facade\Log;

class Index extends MobileEducation
{

    public function index(): Json
    {
        if ($this->request->isPost()) {
            try {
                $policy = Cache::get('policy', []);
                array_multisort(array_column($policy, 'id'), SORT_DESC, $policy);
                //政策
                $data['zc'] = array_map(function ($item){
                    return [
                        'id' => $item['id'],
                        'activity_id' => $item['id'],
                        'title' => $item['title'],
                    ];
                },array_slice(array_values(filter_by_value($policy,'type',1)),0,10));
                //公告
                $data['gg'] = array_map(function ($item){
                    return [
                        'id' => $item['id'],
                        'activity_id' => $item['id'],
                        'title' => $item['title'],
                    ];
                },array_slice(array_values(filter_by_value($policy,'type',2)),0,10));
                //区县电话号码
                $department = Cache::get('department', []);
                $tmp_array = [];
                if($department){
                    foreach($department as $key => $value){
                        if($value['parent_id'] == 0){
                            $value['department_name'] = '襄阳市教育局';
                            $tmp_array[] = $value;
                            unset($department[$key]);
                        }
                    }
                    $department = array_merge($department,$tmp_array);
                    $data['telephone'] = array_map(function ($item){
                        return [
                            'department_name' => $item['department_name'],
                            'telephone' => $item['telephone'],
                        ];
                    }, (array)$department);
                }


                //最新3条消息
                $data['message_list'] = Db::name('user_message')
                    ->where("user_id", $this->userInfo['id'])
                    ->where("deleted", 0)
                    ->field(['contents', 'create_time'])
                    ->order('create_time', 'DESC')
                    ->limit(3)
                    ->select()
                    ->toArray();
                //消息未读数量
                $data['message_no_read'] = Db::name('user_message')
                    ->where("user_id", $this->userInfo['id'])
                    ->where("deleted", 0)
                    ->where("read_total", 0)
                    ->count();

                $data['region'] = Cache::get('region');
                $data['consulting_service'] = 1;
                $data['open_school'] = 0;
                //是否显示线上面审
                $apply = (new Apply())
                    ->where('user_id', $this->userInfo['id'])
                    ->where('resulted', 0)
                    ->where('school_attr', 1)
                    ->where('voided', 0)
                    ->where('refuse_count', '<', 3)
                    ->where('prepared', 0)
                    ->select()
                    ->toArray();

                if ($apply) {
                    foreach ($apply as $_key => $_value) {
                        $detail = Db::name('user_apply_detail')
                            ->where('child_id', $_value['child_id'])
                            ->where('deleted', 0)
                            ->find();
                        if ($detail) {
                            $result = Db::name('face_config')
                                ->where('deleted', 0)
                                ->where('region_id', $_value['region_id'])
                                ->where('status', 1)
                                ->order('id', 'ASC')
                                ->select()
                                ->toArray();
                            $status = Db::name("user_apply_status")
                                ->where('user_apply_id', $_value['id'])
                                ->where('deleted', 0)
                                ->find();
                            if ($status && $result) {
                                foreach ($result as $key => $value) {
                                    switch ($value['type']) {
                                        case 1: //点对点开启
                                            if (time() >= strtotime($value['start_time']) && time() <= strtotime($value['end_time']) && $_value['open_school'] == 1) {
                                                $data['open_school'] = 1;
                                            }
                                            break;
                                    }
                                }
                            }
                        }
                    }
                }

                //学籍是否显示
                $data['open_child_roll'] = 0;
                $config = (new WorkCityConfig())
                    ->where('item_key', 'XJXX')
                    ->where('deleted', '0')
                    ->find();
                if ($config) {
                    $time = json_decode($config['item_value'], true);

                    if (time() >= strtotime($time['startTime']) && time() <= strtotime($time['endTime'])) {
                        $data['open_child_roll'] = 1;
                    }
                }

                //工作进度
                $data['process'] = 0;
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSPROCESS','SYSTB');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                if( time() >= $getData['data']){
                    $data['process'] = 1;
                }

                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSPROCESS','SYSDSJDB');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                if( time() >= $getData['data']){
                    $data['process'] = 2;
                }
                $getData = $dictionary->findValue('dictionary','SYSPROCESS','SYSXXFS');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }

                if(time() >= $getData['data']){
                    $data['process'] = 3;
                }

                $getData = $dictionary->findValue('dictionary','SYSPROCESS','SYSLQTZ');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }

                if(time() >= $getData['data']){
                    $data['process'] = 4;
                }

                //费用缴纳
                $data['open_pay'] = 0;
                $apply_for_school = Db::name("user_apply")
                    ->alias('a')
                    ->leftJoin('sys_school s','a.result_school_id=s.id and s.deleted=0 and s.disabled=0')
                    ->where('a.user_id', $this->userInfo['id'])
                    ->where('a.resulted', 1)
                    ->where('a.school_attr', 2)
                    ->where('a.voided', 0)
                    ->where('a.prepared', 1)
                    ->where('a.deleted', 0)
                    ->where('a.paid', 0)
                    ->where('s.onlinepay', 1)
                    ->where('s.fee_code', '<>','')
                    ->where('s.fee', '>',0)
                    ->field(['a.id','a.paid','s.onlinepay','s.fee_code'])
                    ->select()
                    ->toArray();
                if($apply_for_school){
                    $data['open_pay'] = 1;
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

    /**
     * 按分页获取政策公告信息
     * @return Json
     */
    public function getPolicList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                    if ($this->result['region_id'] != 1) {
                        $regions = [1, $this->result['region_id']];
                        $where[] = ['p.region_id', 'in', $regions];
                    }
                }
                $pageSize = $this->result['pageSize'] ? $this->result['pageSize'] : $this->pageSize;
                $data = Db::name('policy_notice')
                    ->alias('p')
                    ->join([
                        'deg_department' => 'd'
                    ], 'd.id = p.department_id')
                    ->field([
                        'p.id',
                        'p.type',
                        'd.department_name',
                        'p.title',
                        'p.read_count',
                        'p.create_time',
                    ])
                    ->where($where)
                    ->where('p.deleted', 0)
                    ->order('p.region_id ASC,p.create_time DESC')
                    ->paginate(['list_rows' => $pageSize, 'var_page' => 'curr'])->toArray();

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary', 'SYSZCGGLX');
                if (!$getData['code']) {
                    throw new \Exception($getData['msg']);
                }

                //判断用户有没查看过
                $user_read_data = Db::name("user_read_log")
                    ->where('user_id', $this->userInfo['id'])
                    ->where('type_name', '政策')
                    ->select()
                    ->toArray();
                $read_id = array_unique(array_column($user_read_data, 'data_id'), SORT_NUMERIC);

                foreach ($data['data'] as $k => $v) {
                    $schoolTypeData = filter_value_one($getData['data'], 'dictionary_value', $v['type']);
                    $data['data'][$k]['type_name'] = '';
                    if (count($schoolTypeData) > 0) {
                        $data['data'][$k]['type_name'] = $schoolTypeData['dictionary_name'];
                    }
                    $data['data'][$k]['create_date'] = $v['create_time'];
                    $data['data'][$k]['read'] = 0;
                    if (in_array($v['id'], $read_id)) {
                        $data['data'][$k]['read'] = 1;
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


    /**
     * 获取指定政策公告信息
     * @param int id
     * @return Json
     */
    public function getPolicDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = Db::name('policy_notice')
                    ->alias('p')
                    ->join(['deg_department' => 'd'], 'd.id = p.department_id')
                    ->field([
                        'p.id',
                        'p.type',
                        'd.department_name',
                        'p.title',
                        'p.content',
                        'p.read_count',
                        'p.create_time',
                    ])
                    ->where('p.id', $this->result['id'])
                    ->where('p.deleted', 0)
                    ->find();
                if ($data) {
                    $data['create_date'] = $data['create_time'];
                    $read_log = (new UserReadLog())
                        ->where('user_id', $this->userInfo['id'])
                        ->where('data_id', $data['id'])
                        ->where('type_name', '政策')
                        ->find();
                    if (!$read_log) {
                        Db::name('policy_notice')->where('id', $this->result['id'])->inc('read_count')->update();
                        //记录日志
                        $this->UserReadLog($this->userInfo['id'], $this->userInfo['user_name'], $data['id'], $data['title'], '政策');
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

    /**使用教程列表
     * @return Json
     */
    public function getCourseList(): Json
    {
        if ($this->request->isPost()) {
            $where = [];

            try {
                $list = (new Course())->where('deleted', '=', 0)
                    ->hidden(['content', 'deleted', 'disabled'])
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();;
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

    /**使用教程详情
     * @param int id
     * @return Json
     */
    public function getCourseDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], \app\common\validate\parent\Course::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = (new Course())->where('id', $this->result['id'])->where('deleted', 0)->find();
                if (empty($data)) {
                    throw new \Exception('记录不存在');
                }

                $read_log = (new UserReadLog())
                    ->where('user_id', $this->userInfo['id'])
                    ->where('data_id', $data['id'])
                    ->where('type_name', '教程')
                    ->find();
                if (!$read_log) {
                    Db::name('course')->where('id', $this->result['id'])->inc('read_num')->update();
                    //记录日志
                    $this->UserReadLog($this->userInfo['id'], $this->userInfo['user_name'], $data['id'], $data['name'], '教程');
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

    //校情列表
    public function getSchoolList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted', '=', 0];
                if ($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['school_name', 'like', '%' . $this->result['keyword'] . '%'];
                }
                if ($this->request->has('school_type') && $this->result['school_type'] > 0) {
                    $where[] = ['school_type', '=', $this->result['school_type']];
                }
                if ($this->request->has('school_attr') && $this->result['school_attr'] > 0) {
                    $where[] = ['school_attr', '=', $this->result['school_attr']];
                }
                if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                    $where[] = ['region_id', '=', $this->result['region_id']];
                }
                $pageSize = $this->result['pageSize'] ? $this->result['pageSize'] : $this->pageSize;
                $data = Db::name('sys_school')
                    ->field([
                        'id',
                        'school_name',
                        'school_type',
                        'school_attr',
                        'telephone',
                        'preview_img',
                        'address',
                        'longitude',
                        'latitude',
                    ])
                    ->where($where)
                    ->where('displayed', 1)
                    ->where('disabled', 0)
                    ->where('deleted', 0)
                    ->where('onlined', 1)
                    ->order('sort_order ASC')
                    ->paginate(['list_rows' => $pageSize, 'var_page' => 'curr'])->toArray();

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary', 'SYSXXLX');
                if (!$getData['code']) {
                    throw new \Exception($getData['msg']);
                }
                $attrData = $dictionary->resArray('dictionary', 'SYSXXXZ');
                if (!$getData['code']) {
                    throw new \Exception($getData['msg']);
                }
                foreach ($data['data'] as $k => $v) {
                    $schoolTypeData = filter_value_one($getData['data'], 'dictionary_value', $v['school_type']);
                    $data['data'][$k]['school_type_name'] = '';
                    if (count($schoolTypeData) > 0) {
                        $data['data'][$k]['school_type_name'] = $schoolTypeData['dictionary_name'];
                    }
                    $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $v['school_attr']);
                    $data['data'][$k]['school_attr_name'] = '';
                    if (count($schoolAttrData) > 0) {
                        $data['data'][$k]['school_attr_name'] = $schoolAttrData['dictionary_name'];
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

    //校情详情
    public function getSchoolDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], \app\common\validate\schools\Schools::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $data = (new Schools())
                    ->where('id', $this->result['id'])
                    ->where('deleted', 0)
                    ->where('disabled', 0)
                    ->where('displayed', 1)
                    ->field([
                        'id',
                        'school_name',
                        'content',
                        'preview_img',
                        'address',
                        'telephone',
                        'school_type',
                        'school_attr',
                        'point',
                        'school_area',
                        'fee_unit',
                        'longitude',
                        'latitude',
                        '2 as cate'
                    ])
                    ->hidden(['deleted'])->find();

                $list = Db::name('sys_school_cost')
                    ->where('school_id', $this->result['id'])->select()->toArray();
                $school_cost_list = [];
                foreach ($list as $item) {
                    $school_cost_list[$item['cost_id']] = $item['cost_total'];
                }

                //费用列表
                $feeData = Db::name("sys_cost_category")
                    ->where("deleted", 0)
                    ->where("disabled", 0)
                    ->select()
                    ->toArray();
                $cost_list = [];
                foreach ($feeData as $item) {
                    $cost_list[] = [
                        'id' => $item['id'],
                        'cost_name' => $item['cost_name'],
                        'cost_total' => isset($school_cost_list[$item['id']]) ? $school_cost_list[$item['id']] : 0,
                    ];
                }

                $res = [
                    'code' => 1,
                    'data' => $data,
                    'cost_list' => $cost_list,
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

    public function getRegion(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data['region'] = \think\facade\Cache::get('region', []);

                foreach ($data['region'] as $k => $v) {
                    if ($v['parent_id'] == '0') {
                        unset($data['region'][$k]);
                    }
                }
                //$data = array_values($data);
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
     * 获取页面表单hash
     * @param string page_hash 页面唯一标识
     * @param int user_id   用户自增ID
     * @return Json
     */
    public function getHash(): Json
    {
        if ($this->request->isPost()) {
            try {
                $hash = set_hash('user' . $this->result['user_id'], $this->result['page_hash']);
                $hashKey = 'hash_' . md5($this->result['page_hash'] . 'user' . $this->result['user_id']);
                //  生成hash
                if (!Cache::set($hashKey, $hash, 600)) {
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
     * 获取标准区划列表
     * @return Json
     */
    public function getStandardList()
    {
        if ($this->request->isPost()) {
            try {
//                $regionStandardData = Cache::get('standard');
                $regionStandardData = (new SysRegionStandard())->cache(true)->where('disabled',0)->select()->toArray();
                if ($this->request->has('all') && $this->result['all']) {
                    $regionData = [];
                    $regionData = array_merge($regionData, array_values(filter_by_value($regionStandardData, 'level', 2)));
                    $regionData = array_merge($regionData, array_values(filter_by_value($regionStandardData, 'level', 3)));
                    $regionData = array_merge($regionData, array_values(filter_by_value($regionStandardData, 'level', 4)));
                } else {
                    $regionData = [];
                    $regionData = array_merge($regionData, array_values(filter_by_value($regionStandardData, 'level', 2)));
                    $regionData = array_merge($regionData, array_values(filter_by_value($regionStandardData, 'level', 3)));
                }
                if ($this->request->has('parent_id') && $this->result['parent_id']) {
                    $regionData = array_values(filter_by_value($regionStandardData, 'parent_id', $this->result['parent_id']));
                }
                $res = [
                    'code' => 1,
                    'data' => $regionData
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
    public function upload()
    {
        if ($this->request->isPost()) {
            try {
                @ini_set("memory_limit", "512M");
                // 获取上传的文件，如果有上传错误，会抛出异常
                $file = $this->request->file('file');
                // 如果上传的文件为null，手动抛出一个异常，统一处理异常
                if (null === $file) {
                    // 异常代码使用UPLOAD_ERR_NO_FILE常量，方便需要进一步处理异常时使用
                    throw new \Exception('请上传文件');
                }
                // $info = $file->getInfo();
                $info['tmp_name'] = $file->getPathname();
                $info['name'] = $file->getOriginalName();
                $info['size'] = $file->getSize();
                $extend_name = strtolower(substr(strrchr($info['name'], '.'), 1));
                $image = \think\Image::open($info['tmp_name']);

                $image_validate = validate(['file' => [
                    'fileSize' => 4 * 1024 * 1024,
                    'fileExt' => Cache::get('file_server_pic'),
                    //'fileMime' => 'image/jpeg,image/png,image/gif', //这个一定要加上，很重要我认为！
                ]])->check(['file' => $file]);
                if (!$image_validate) {
                    throw new \Exception('文件格式错误');
                }

                // 返回图片的宽度
                $width = $image->width();
                $height = $image->height();
                $originalPath = 'public' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
                $randName = md5(time() . mt_rand(0, 1000) . $info['tmp_name']);
                $fileName = $randName . '.' . $extend_name;
                $small_name = $randName . '-s.' . $extend_name;
                $actualPath = str_replace("\\", "/", env('root_path') . $originalPath);
                $file_size = $info['size'];
                $max_size = Cache::get('file_max_size');
                if ($width > $max_size || $height > $max_size) {
                    $image->thumb($max_size, $max_size)->save($originalPath . $fileName);
                    $file_size = filesize($originalPath . $fileName);
                } else {
                    $position = strrpos($actualPath, '/');
                    $path = substr($actualPath, 0, $position);
                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                        chmod($path, 0777);
                    }
                    if (!move_uploaded_file($info['tmp_name'], $actualPath . $fileName)) {
                        throw new \Exception('上传文件失败');
                    };
                }
                $image->thumb(480, 480)->save($originalPath . $small_name);
                // 保存路径

                $savePath = DIRECTORY_SEPARATOR . Cache::get('file_server_path') . DIRECTORY_SEPARATOR . date('Y-m-d-H');
                $savePath = preg_replace('/\\\\/', '/', $savePath);
                $ftpConn = @ftp_connect(Cache::get('file_server_host'));
                if (!$ftpConn) {
                    throw new \Exception('文件服务器链接失败');
                }
                $ftpLogin = @ftp_login($ftpConn, Cache::get('file_server_user'), Cache::get('file_server_pass'));
                if (!$ftpLogin) {
                    throw new \Exception('文件服务器登录失败');
                }
                @ftp_pasv($ftpConn, true);
                $savePathArr = explode('/', $savePath);
                array_filter($savePathArr);
                $pathStatus = false;
                foreach ($savePathArr as $path) {
                    if ($path) {
                        $isChdir = false;
                        try {
                            $isChdir = @ftp_chdir($ftpConn, $path);
                        } catch (\Exception $exception) {

                        }
                        if ($isChdir) {
                            $pathStatus = true;
                        } else {
                            $pathStatus = @ftp_mkdir($ftpConn, $path);
                            $isChdir = @ftp_chdir($ftpConn, $path);
                            if (!$isChdir) {
                                throw new \Exception('文件服务器路径生成失败');
                            }
                        }
                    }
                }
                if ($pathStatus) {
                    $isFile = @ftp_put($ftpConn, $fileName, $actualPath . $fileName, FTP_BINARY);
                    $isSmallFile = @ftp_put($ftpConn, $small_name, $actualPath . $small_name, FTP_BINARY);
                    if (!$isFile || !$isSmallFile) {
                        throw new \Exception('文件上传错误请重新上传');
                    }
                } else {
                    throw new \Exception('文件服务器路径错误');
                }
                @ftp_close($ftpConn);
                unlink($actualPath . $fileName);
                unlink($actualPath . $small_name);
                $full_url = preg_replace('/\\\\/', '/', DIRECTORY_SEPARATOR . "uploads" . $savePath . DIRECTORY_SEPARATOR . $fileName);
                $small_url = preg_replace('/\\\\/', '/', DIRECTORY_SEPARATOR . "uploads" . $savePath . DIRECTORY_SEPARATOR . $small_name);
                $file_id = Db::name('upload_files')
                    ->insertGetId([
                        'user_id' => $this->userInfo['id'],
                        'file_path' => $full_url,
                        'file_small' => $small_url,
                        'file_type' => $extend_name,
                        'file_size' => $file_size,
                        'source' => 'edu_mobile',
                        'create_ip' => $this->request->ip()
                    ]);
                $data['file_id'] = $file_id;
                $data['file_path'] = $small_url;
                $res = [
                    'code' => 1,
                    'data' => $small_url
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
}