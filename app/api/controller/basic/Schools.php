<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\basic;

use app\common\controller\Education;
use app\common\model\SysAddressBirthplace;
use app\common\model\SysAddressIntact;
use app\common\model\SysAddressSimple;
use app\common\model\SysRoleNodes;
use app\common\model\SysNodes;
use app\common\model\SysSchoolNodes;
use think\facade\Lang;
use think\response\Json;
use app\common\model\Schools as model;
use app\common\validate\schools\Schools as validate;
use Overtrue\Pinyin\Pinyin;
use think\App;
use think\facade\Db;
use think\facade\Cache;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Schools extends Education
{

    public function __construct(App $app)
    {
        parent::__construct($app);
    }
    /**
     * 按分页获取学校信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted','=',0];
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['region_id','=', $this->result['region_id']];
                }
                if($this->request->has('school_attr') && $this->result['school_attr'] > 0)
                {
                    $where[] = ['school_attr','=', $this->result['school_attr']];
                }
                if($this->request->has('school_type') && $this->result['school_type'] > 0)
                {
                    $where[] = ['school_type','=', $this->result['school_type']];
                }
                if($this->request->has('enroll_mode') && $this->result['enroll_mode'])
                {
                    $dictionary = new FilterData();
                    $getData = $dictionary->findValue('dictionary', 'SYSXXZSFS', 'SYSZSFSXS');
                    if(!$getData['code']){
                        throw new \Exception('招生方式数据字典错误');
                    }
                    if($this->result['enroll_mode'] == $getData['data']){
                        $where[] = ['onlined','=', 1];
                    }else{
                        $where[] = ['onlined','=', 0];
                    }
                }
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['org_code|school_name|simple_code','like', '%' . $this->result['keyword'] . '%'];
                }
                if ($this->userInfo['relation_region']){
                    $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                    $where[] = ['region_id', 'in', $region_ids];
                }
                //是否教学点
                if($this->request->has('central_status') && $this->result['central_status'] !== '' )
                {
                    if($this->result['central_status'] == 1) {
                        $where[] = ['central_id', '>', 0];
                    }
                    if($this->result['central_status'] == 0) {
                        $where[] = ['central_id', '=', 0];
                    }
                }
                // 教管会权限
                if($this->middle_grade == $this->userInfo['grade_id'] && $this->userInfo['central_id']){
                    $where[] = ['central_id','=', $this->userInfo['central_id']];
                }
                // 市级权限
                if($this->city_grade > $this->userInfo['grade_id']){
                    $where[] = ['directly','=', 0];
                }else{
                    if($this->request->has('directly') && ($this->result['directly'] || $this->result['directly'] == '0'))
                    {
                        $where[] = ['directly','=', $this->result['directly']];
                    }
                }
                //公办、民办、小学、初中权限
                $school_where = $this->getSchoolWhere();
                $where[] = $school_where['school_attr'];
                $where[] = $school_where['school_type'];

                $data = Db::name('sys_school')
                    ->field([
                        'id',
                        'school_name',
                        'school_type',
                        'school_attr',
                        'org_code',
                        'region_id',
                        'central_id',
                        'telephone',
                        'staffs_num',
                        'students_num',
                        'sort_order',
                        'displayed',
                        'singled',
                        'directly',
                        'regulated',
                        'applied',
                        'onlined',
                        'localed',
                        'disabled',
                    ])
                    ->where($where)->master(true)
                    ->order('sort_order', 'ASC')
                    ->order('id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');
                $dictionary = new FilterData();
                $typeData = $dictionary->resArray('dictionary','SYSXXLX');
                if(!$typeData['code']){
                    throw new \Exception($typeData['msg']);
                }
                $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
                if(!$attrData['code']){
                    throw new \Exception($attrData['msg']);
                }
                foreach ($data['data'] as $k => $v){
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
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
                    $data['data'][$k]['displayed'] = $v['displayed'] == 1 ? true : false;
                    $data['data'][$k]['singled'] = $v['singled'] == 1 ? true : false;
                    $data['data'][$k]['directly'] = $v['directly'] == 1 ? true : false;
                    $data['data'][$k]['regulated'] = $v['regulated'] == 1 ? true : false;
                    $data['data'][$k]['applied'] = $v['applied'] == 1 ? true : false;
                    $data['data'][$k]['onlined'] = $v['onlined'] == 1 ? true : false;
                    $data['data'][$k]['localed'] = $v['localed'] == 1 ? true : false;
                    $data['data'][$k]['disabled'] = $v['disabled'] == 1 ? false : true;
                }
                $middlemanage = true;
                if($this->userInfo['grade_id'] <= $this->middle_grade){
                    $middlemanage = false;
                }
                $data['middlemanage'] = $middlemanage;
                $res_data = parent::getResources($this->userInfo,$this->request->controller());
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
     * 学校页面资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            try {
                $region = Cache::get('region');
                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary',  'SYSFYDW');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                foreach ($getData['data'] as $value){
                    $data['fee_units'][] =[
                        'fee_name' => $value['dictionary_name'],
                        'fee_value' => $value['dictionary_value']
                    ];
                }
                //cost_total 为前端赋值用，勿删
                $cost_list = Db::name('SysCostCategory')->where('deleted', 0)
                    ->field(['id', 'cost_name', 'cost_total'])->select()->toArray();
                $data['cost_list'] = $cost_list;

                $data['region'] = filter_by_value($region, 'grade_id', $this->area_grade);
                $data['school_type'] = [];

                if($this->userInfo['primary_school_status']){
                    $getData = $dictionary->findAll('dictionary', 'SYSXXLX','SYSXXLXXX');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $data['school_type'][] =[
                        'id' => $getData['data']['dictionary_value'],
                        'type_name' => $getData['data']['dictionary_name']
                    ];
                }
                if($this->userInfo['junior_middle_school_status']){
                    $getData = $dictionary->findAll('dictionary', 'SYSXXLX','SYSXXLXCZ');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $data['school_type'][] =[
                        'id' => $getData['data']['dictionary_value'],
                        'type_name' => $getData['data']['dictionary_name']
                    ];
                }
                if($this->userInfo['public_school_status']){
                    $getData = $dictionary->findAll('dictionary', 'SYSXXXZ','SYSXXXZGB');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $data['school_attr'][] =[
                        'id' => $getData['data']['dictionary_value'],
                        'attr_name' => $getData['data']['dictionary_name']
                    ];
                }
                if($this->userInfo['civil_school_status']){
                    $getData = $dictionary->findAll('dictionary', 'SYSXXXZ','SYSXXXZMB');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $data['school_attr'][] =[
                        'id' => $getData['data']['dictionary_value'],
                        'attr_name' => $getData['data']['dictionary_name']
                    ];
                }
                $getData = $dictionary->resArray('dictionary', 'SYSXXZSFS');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                foreach ($getData['data'] as $value){
                    $data['enroll_mode'][] =[
                        'id' => $value['dictionary_value'],
                        'mode_name' => $value['dictionary_name']
                    ];
                }
                $getData = $dictionary->findValue('dictionary', 'SYSGLZY', 'SYSCZGLZY');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $filter_school = $getData['data'];
                $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSGBXXBM');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $public_role = $getData['data'];
                $node_ids = (new SysRoleNodes())->where('role_id',$public_role)->column('node_id');
                $nodes = (new SysNodes())
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })
                    ->whereIn('id',$node_ids)
                    ->where('defaulted',0)
                    ->where('node_type',0)
                    ->field([
                        'id',
                        'parent_id',
                        'node_name'
                    ])
                    ->order('order_num')
                    ->select()->toArray();
                $data['public_primary_nodes'] = $this->list_to_tree($nodes);
                $nodes = (new SysNodes())
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })
                    ->where('controller_name','<>', $filter_school)
                    ->whereIn('id',$node_ids)
                    ->where('defaulted',0)
                    ->where('node_type',0)
                    ->field([
                        'id',
                        'parent_id',
                        'node_name'
                    ])
                    ->order('order_num')
                    ->select()->toArray();
                $data['public_middle_nodes'] = $this->list_to_tree($nodes);
                $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSMBXXBM');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $civil_role = $getData['data'];
                $node_ids = (new SysRoleNodes())->where('role_id',$civil_role)->column('node_id');
                $nodes = (new SysNodes())
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })
                    ->whereIn('id',$node_ids)
                    ->where('defaulted',0)
                    ->where('node_type',0)
                    ->field([
                        'id',
                        'parent_id',
                        'node_name'
                    ])
                    ->order('order_num')
                    ->select()->toArray();
                $data['civil_primary_nodes'] = $this->list_to_tree($nodes);
                $nodes = (new SysNodes())
                    ->where(function ($query) {
                        $query->where('authority', 1)
                            ->whereOr('displayed', 1);
                    })
                    ->where('controller_name','<>', $filter_school)
                    ->whereIn('id',$node_ids)
                    ->where('defaulted',0)
                    ->where('node_type',0)
                    ->field([
                        'id',
                        'parent_id',
                        'node_name'
                    ])
                    ->order('order_num')
                    ->select()->toArray();
                $data['civil_middle_nodes'] = $this->list_to_tree($nodes);
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
     * 获取指定学校信息
     * @param id 学校ID
     * @return Json
     */
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $dictionary = new FilterData();
                $getData = $dictionary->findAll('dictionary', 'SYSXXXZ','SYSXXXZMB');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $data = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();
                $list = Db::name('sys_school_cost')
                    ->where('school_id', $this->result['id'])->select()->toArray();
                $school_cost_list = [];
                foreach ($list as $item){
                    $school_cost_list[$item['cost_id']] = $item['cost_total'];
                }
                //费用列表
                $feeData = Db::name('SysCostCategory')->where('deleted', 0)
                    ->field(['id','cost_code', 'cost_name'])->select()->toArray();
                $cost_list = [];
                if($data['school_attr'] == $getData['data']['dictionary_value']){
                    foreach ($feeData as $item){
                        $cost_list[] = [
                            'id' => $item['id'],
                            'cost_name' => $item['cost_name'],
                            'cost_total' => isset($school_cost_list[$item['id']]) ? $school_cost_list[$item['id']] : 0,
                        ];
                    }
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

    /**
     * 新增
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'school_name',
                    'school_type',
                    'school_attr',
                    'school_code',
                    'address',
                    'telephone',
                    'school_area',
                    'preview_img',
                    'content',
                    'fee',
                    'fee_unit',
                    'fee_remark',
                    'fee_data',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //市级权限 以上需要选择区域
                if($this->userInfo['grade_id'] >= $this->city_grade){
                    $region_id = $this->result['region_id'];
                    if(!$region_id){
                        throw new \Exception('市级以上权限请选择区县');
                    }
                }else{
                    $region_id = $this->userInfo['region_id'];
                }
                $data['region_id'] = $region_id;
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                /*if($data['school_attr'] == 1){
                    if($data['school_area'] == ''){
                        throw new \Exception('公办学校请填写招生片区！');
                    }
                }*/

                $cost_array = json_decode($data['fee_data'], true);
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSGBXXBM');
                if(!$getData['code']){
                    throw new \Exception('公办学校数据字典错误');
                }
                $public_role = $getData['data'];
                $node_ids = (new SysRoleNodes())->where('role_id',$public_role)->column('node_id');
                $nodes = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->order('order_num')->select()->toArray();
                $data['public_nodes'] = $this->list_to_tree($nodes);
                $getData = $dictionary->findValue('dictionary', 'SYSXXZSFS', 'SYSZSFSXS');
                if(!$getData['code']){
                    throw new \Exception('线上招生方式数据字典错误');
                }
                $online_mode = $getData['data'];
                if($data['school_attr'] == 2){
                    if($data['fee_unit'] == ''){
                        //throw new \Exception('民办学校请填写费用单位！');
                    }
                    $fee = 0;
                    if(is_array($cost_array)) {
                        foreach ($cost_array as $k => $v) {
                            if (isset($v['cost_total']) && $v['cost_total'] > 0) {
                                $fee += $v['cost_total'];
                            }
                        }
                    }else{
                        //throw new \Exception('民办学校费用参数错误！');
                    }
                    if($fee == 0){
                        //throw new \Exception('民办学校请填写费用！');
                    }
                    $data['fee'] = $fee;
                    $data['regulated'] = 1;
                }
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['school_name']);
                $data['sort_order'] = 999;
                $data['onlined'] = 1;
                $data['enroll_mode'] = $online_mode;
                unset($data['hash']);
                unset($data['fee_data']);
                $data['central_id'] = $this->userInfo['central_id'];
                //获取学校定位信息  及逆地理信息
                $gdgeo_key = Cache::get('gdgeo_key');
                $geocode = Cache::get('geocode');

                if($data['address']) {
                    $getData = http_get($geocode . $gdgeo_key . '&city=襄阳&address=' . $data['address']);
                    if (!$getData) {
                        throw new \Exception('高德服务接口连接失败');
                    }
                    $getData = json_decode($getData, true);
                    if ($getData['status'] == 0) {
                        throw new \Exception('地理数据获取失败');
                    }
                    if ($getData['count'] == 0) {
                        throw new \Exception('地址信息不明确，请填写详细地址');
                    }
                    $location = $getData['geocodes'][0]['location'];
                    $locationData = explode(",",$location);
                    if ($locationData[0] == 0) {
                        throw new \Exception('地址信息不明确，请填写详细地址');
                    }
                    $data['formatted_address'] = $getData['geocodes'][0]['formatted_address'];
                    $data['longitude'] = $locationData[0];
                    $data['latitude'] = $locationData[1];

                    $geocode = Cache::get('regeocode');
                    $getData = http_get($geocode .$gdgeo_key. '&location='.$data['longitude'].','.$data['latitude']);
                    if ($getData) {
                        $getData = json_decode($getData, true);
                        if ($getData['status']) {
                            if ($getData['regeocode']) {
                                if ($getData['regeocode']['formatted_address']) {
                                    $data['formatted_address'] = $getData['regeocode']['formatted_address'];
                                }
                            }
                        }
                    }
                }

                $result = (new model())->addData($data, 1);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $school_id = $result['insert_id'];

                if(is_array($cost_array)){
                    foreach ($cost_array as $k => $v){
                        if(isset($v['cost_total']) && $v['cost_total'] > 0) {
                            $school_cost = [];
                            $school_cost['cost_id'] = $v['id'];
                            $school_cost['cost_total'] = $v['cost_total'];
                            $school_cost['school_id'] = $school_id;
                            Db::name('sys_school_cost')->insert($school_cost);
                        }
                    }
                }
                if (Cache::get('update_cache')) {
                    $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('school', $schoolList);
                }

                //校情统计
                $result = $this->getFinishedStatistics($school_id);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
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
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'school_name',
                    'school_type',
                    'school_attr',
                    'school_code',
                    'address',
                    'telephone',
                    'school_area',
                    'preview_img',
                    'content',
                    'fee',
                    'fee_unit',
                    'fee_remark',
                    'fee_data',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //市级权限 以上需要选择区域
                if($this->userInfo['grade_id'] >= $this->city_grade){
                    $region_id = $this->result['region_id'];
                    if(!$region_id){
                        throw new \Exception('市级以上权限请选择区县');
                    }
                }else{
                    $region_id = $this->userInfo['region_id'];
                }
                $data['region_id'] = $region_id;
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                /*if($data['school_attr'] == 1){
                    if($data['school_area'] == ''){
                        throw new \Exception('公办学校请填写招生片区！');
                    }
                }*/

                $cost_array = json_decode($data['fee_data'], true);
                if($data['school_attr'] == 2){
                    if($data['fee_unit'] == ''){
                        //throw new \Exception('民办学校请填写费用单位！');
                    }
                    $fee = 0;
                    if(is_array($cost_array)) {
                        foreach ($cost_array as $k => $v) {
                            if (isset($v['cost_total']) && $v['cost_total'] > 0) {
                                $fee += $v['cost_total'];
                            }
                        }
                    }else{
                        //throw new \Exception('民办学校请填写费用参数不是数组！');
                    }
                    if($fee == 0){
                        //throw new \Exception('民办学校请填写费用！');
                    }
                    $data['fee'] = $fee;
                }
//                if($this->userInfo['central_id'] && $this->middle_grade == $this->userInfo['grade_id']){
//                    $data['central_id'] = $this->userInfo['central_id'];
//                }
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['school_name']);
                unset($data['hash']);
                unset($data['fee_data']);
                $schoolData = (new model())->where('id', $data['id'])->where('deleted',0)->find();
                //如果涉及到主要信息变更，则删除学校关联账号数据
                if($data['school_type'] != $schoolData['school_type'] || $data['school_attr'] != $schoolData['school_attr'] || $data['region_id'] != $schoolData['region_id']){
                    $manageIds = Db::name('manage')->where('school_id',$data['id'])->column('id');
                    Db::name('RelationManageRegion')->whereIn('manage_id',$manageIds)->update(['deleted' => 1]);
                    Db::name('ManageNodes')->whereIn('manage_id',$manageIds)->update(['deleted' => 1]);
                    Db::name('manage')->whereIn('id',$manageIds)->update(['deleted' => 1]);
                }

                if($data['address']) {
                    //获取学校定位信息  及逆地理信息
                    $gdgeo_key = Cache::get('gdgeo_key');
                    $geocode = Cache::get('geocode');
                    $getData = http_get($geocode . $gdgeo_key . '&city=襄阳&address=' . $data['address']);
                    if (!$getData) {
                        throw new \Exception('高德服务接口连接失败');
                    }
                    $getData = json_decode($getData, true);
                    if ($getData['status'] == 0) {
                        throw new \Exception('地理数据获取失败');
                    }
                    if ($getData['count'] == 0) {
                        throw new \Exception('地址信息不明确，请填写详细地址');
                    }
                    $location = $getData['geocodes'][0]['location'];
                    $locationData = explode(",", $location);
                    if ($locationData[0] == 0) {
                        throw new \Exception('地址信息不明确，请填写详细地址');
                    }
                    $data['formatted_address'] = $getData['geocodes'][0]['formatted_address'];
                    $data['longitude'] = $locationData[0];
                    $data['latitude'] = $locationData[1];

                    $geocode = Cache::get('regeocode');
                    $getData = http_get($geocode . $gdgeo_key . '&location=' . $data['longitude'] . ',' . $data['latitude']);
                    if ($getData) {
                        $getData = json_decode($getData, true);
                        if ($getData['status']) {
                            if ($getData['regeocode']) {
                                if ($getData['regeocode']['formatted_address']) {
                                    $data['formatted_address'] = $getData['regeocode']['formatted_address'];
                                }
                            }
                        }
                    }
                }

                $data['finished'] = 1;//校情已完成

                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                if(is_array($cost_array)){
                    $has_cost = Db::name('sys_school_cost')
                        ->where('school_id', $data['id'])->column('cost_id');
                    $cost_ids = [];
                    foreach ($cost_array as $k => $v){
                        if(isset($v['cost_total']) && $v['cost_total'] > 0){
                            if(in_array($v['id'], $has_cost)){
                                Db::name('sys_school_cost')
                                    ->where([['cost_id', '=', $v['id']], ['school_id', '=', $data['id'] ] ])
                                    ->update(['cost_total' => $v['cost_total'], 'deleted' => 0]);
                            }else{
                                $school_cost = [];
                                $school_cost['cost_id'] = $v['id'];
                                $school_cost['cost_total'] = $v['cost_total'];
                                $school_cost['school_id'] = $data['id'];
                                Db::name('sys_school_cost')->insert($school_cost);
                            }
                            $cost_ids[] = $v['id'];
                        }
                    }
                    //删除多余的
                    foreach ($has_cost as $cost_id){
                        if(!in_array($cost_id, $cost_ids)){
                            Db::name('sys_school_cost')
                                ->where([['cost_id', '=', $cost_id], ['school_id', '=', $data['id'] ] ])
                                ->update(['cost_total' => 0, 'deleted' => 1]);
                        }
                    }
                }
                if (Cache::get('update_cache')) {
                    $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('school', $schoolList);
                }

                //校情统计
                $result = $this->getFinishedStatistics($data['id']);
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
     * 获取学校勾选的节点
     * @return Json
     */
    public function getNodes()
    {
        if ($this->request->isPost()) {
            try {
                if(!$this->result['id']){
                    throw new \Exception('学校ID错误');
                }
                $data = Db::name('SysSchoolNodes')->where([
                    'school_id' => $this->result['id'],
                    'deleted' => 0,
                ])->column('node_id');
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
     * 保存学校权限节点
     * @return Json
     */
    public function setNodes()
    {
        if ($this->request->isPost()) {
            try {
                Db::startTrans();
                $data = $this->request->only([
                    'id',
                    'node_ids',
                    'hash',
                ]);
                 //  验证表单hash
                 $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                 if($checkHash['code'] == 0)
                 {
                     throw new \Exception($checkHash['msg']);
                 }

                if (!$data['id']) {
                    throw new \Exception('学校ID错误');
                }
                if (!empty($data['node_ids'])){
                    $preg = '/^\d+(,\d+)*$/u';
                    if (preg_match($preg,$data['node_ids']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                }
                $node_ids = explode(',', $data['node_ids']);
                $node_ids = array_unique($node_ids);

                //学校性质
                $school_attr = Db::name('SysSchool')->where('id',$data['id'])->where('deleted',0)->value('school_attr');
                if(empty($school_attr)){
                    throw new \Exception('学校数据错误');
                }
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSXXXZ', 'SYSXXXZMB');
                if(!$getData['code']){
                    throw new \Exception('学校性质数据字典错误');
                }
                $civil_school = $getData['data'];
                if($school_attr == $civil_school){
                    $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSMBXXBM');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $role_id = $getData['data'];

                }else{
                    $getData = $dictionary->findValue('dictionary', 'SYSJSBM', 'SYSGBXXBM');
                    if(!$getData['code']){
                        throw new \Exception($getData['msg']);
                    }
                    $role_id = $getData['data'];
                }
                //处理账号关联资源
                $roleNodes = (new SysRoleNodes())->where('role_id',$role_id)->whereIn('node_id',$node_ids)->field(['node_id','node_type'])->select()->toArray();
                $hasData = Db::name('SysSchoolNodes')->where([
                    'school_id' => $data['id'],
                ])->column('node_id,id,deleted','node_id');
                $chkData = array_values(filter_by_value($hasData, 'deleted', 0));
                $chkData = array_column($chkData,'node_id');
                $newData = array_column($roleNodes,'node_id');
                $mergeData = array_values(array_unique(array_merge($newData,$chkData)));
                $diffData = array_diff($mergeData,$newData);
                if(!count($diffData)){
                    $diffData = array_diff($mergeData,$chkData);
                }
                if(count($diffData)){
                    $mapData = array_column($hasData,'node_id');
                    $saveData = [];
                    foreach($roleNodes as $k => $v){
                        if(in_array($v['node_id'],$mapData))
                        {
                            Db::name('SysSchoolNodes')->where('id',$hasData[$v['node_id']]['id'])->update(['deleted' => 0]);
                            unset($hasData[$v['node_id']]);
                        }else{
                            if($v['node_id']){
                                $saveData[] = [
                                    'node_id' => $v['node_id'],
                                    'school_id' => $data['id'],
                                    'node_type' => $v['node_type']
                                ];
                            }
                        }
                    }
                    //  保存新增的节点信息
                    (new SysSchoolNodes())->saveAll($saveData);
                    //  删除多余的节点信息
                    Db::name('SysSchoolNodes')->whereIn('id', array_column($hasData,'id'))->update(['deleted' => 1]);
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
                //学校信息
                $school = Db::name('SysSchool')->field(['id', 'school_type', 'region_id'])->find($data['id']);

                $code = rand(100, 999);
                Db::name('sys_school')->where('id', $data['id'])->update(['deleted' => 1, 'org_code' => time().$code]);
                Db::name('manage')->where('school_id',$data['id'])->update(['deleted' => 1, 'user_name'=>time().$code]);

                if (Cache::get('update_cache')) {
                    $manageList = Db::name('manage')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('manage', $manageList);
                    $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('school', $schoolList);
                }
                //删除学校管理员缓存
                $manage_user_ids = Db::name('Manage')
                    ->where('school_id', $data['id'])->group('user_id')->column('user_id');
                foreach ($manage_user_ids as $user_id) {
                    if(Cache::has('user' . md5($user_id))) {
                        Cache::delete('user' . md5($user_id));
                    }
                }

                //删除学校认领地址
                $result = $this->deleteSchoolAddress($school);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                //校情统计
                $result = $this->getFinishedStatistics($data['id']);
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

    public function setDisplay(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'displayed' => $postData['state']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }
    public function setSingle(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'singled' => $postData['state']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }
    public function setDirectly(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'directly' => $postData['state']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }
    public function setSort(){
        try {
            $postData = $this->request->only([
                'id',
                'sort_order',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'sort_order' => $postData['sort_order']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }
    public function setRegulate(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'regulated' => $postData['state']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }
    public function setApplie(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'applied' => $postData['state']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }
    public function setOnline(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'onlined' => $postData['state']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }

    public function setLocal(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'localed' => $postData['state']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }

    public function setState(){
        try {
            $postData = $this->request->only([
                'id',
                'state',
            ]);
            $checkData = parent::checkValidate($postData, validate::class, 'state');
            //  如果数据不合法，则返回
            if ($checkData['code'] != 1) {
                throw new \Exception($checkData['msg']);
            }
            if($this->middle_grade > $this->userInfo['grade_id']  && !$this->userInfo['defaulted']){
                throw new \Exception('权限不足不能进行此操作');
            }
            (new model())->editData([
                'id' => $postData['id'],
                'disabled' => $postData['state']
            ]);
            if (Cache::get('update_cache')) {
                $schoolList = Db::name('SysSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            //禁用状态
            if($postData['state'] == 1){
                //删除学校管理员缓存
                $manage_user_ids = Db::name('Manage')
                    ->where('school_id', $postData['id'])->group('user_id')->column('user_id');
                foreach ($manage_user_ids as $user_id) {
                    if(Cache::has('user' . md5($user_id))) {
                        Cache::delete('user' . md5($user_id));
                    }
                }
                /*$manage_ids = Db::name('Manage')->where('school_id', $postData['id'])->column('id');
                foreach ($manage_ids as $id) {
                    (new \app\common\model\Manage())->editData(['id' => $id, 'disabled' => 1]);
                }*/
            }

            $res = [
                'code' => 1,
                'msg' => '操作成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }

    /**
     * 上传图片
     * @return Json
     */
    public function upload(): Json
    {
        if ($this->request->isPost()) {
            try {
                @ini_set("memory_limit","512M");
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

                $image_validate =   validate(['file'=>[
                    'fileSize' => 15 * 1024 * 1024,
                    'fileExt' => Cache::get('file_server_pic'),
                    //'fileMime' => 'image/jpeg,image/png,image/gif', //这个一定要加上，很重要我认为！
                ]])->check(['file' => $file]);

                if(!$image_validate){
                    throw new \Exception('文件格式错误');
                }

                // 返回图片的宽度
                $image = \think\Image::open($info['tmp_name']);
                $width = $image->width();
                $height = $image->height();
                $originalPath = 'public' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
                $randName = md5(time().mt_rand(0,1000).$info['tmp_name']);
                $fileName = $randName.'.'.$extend_name;
                $small_name = $randName.'-s.'.$extend_name;
                $actualPath = str_replace("\\","/", env('root_path').$originalPath);
                $file_size = $info['size'];
                $max_size = Cache::get('file_max_size');
                if ($width > $max_size || $height > $max_size){
                    $image->thumb($max_size, $max_size)->save($originalPath.$fileName);
                    $file_size = filesize($originalPath.$fileName);
                }else{
                    $position = strrpos($actualPath,'/');
                    $path = substr($actualPath,0,$position);
                    if(!file_exists($path)){
                        mkdir($path,0777,true);
                        chmod($path, 0777);
                    }
                    if(!move_uploaded_file($info['tmp_name'],$actualPath. $fileName)){
                        throw new \Exception('上传文件失败');
                    };
                }
                $image->thumb(480, 480)->save($originalPath.$small_name);
                // 保存路径
                //Cache::get('file_server_path')
                $savePath =  DIRECTORY_SEPARATOR . Cache::get('file_server_path') . DIRECTORY_SEPARATOR. date('Y-m-d-H');
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
                            if(!$isChdir){
                                throw new \Exception('文件服务器路径生成失败');
                            }
                        }
                    }
                }
                if ($pathStatus) {
                    $isFile = @ftp_put($ftpConn, $fileName, $actualPath. $fileName, FTP_BINARY);
                    $isSmallFile = @ftp_put($ftpConn, $small_name, $actualPath.$small_name, FTP_BINARY);
                    if (!$isFile || !$isSmallFile) {
                        throw new \Exception('文件上传错误请重新上传');
                    }
                } else {
                    throw new \Exception('文件服务器路径错误');
                }
                @ftp_close($ftpConn);
                unlink($actualPath.$fileName);
                unlink($actualPath.$small_name);
                $full_url = preg_replace('/\\\\/', '/', DIRECTORY_SEPARATOR ."uploads" . $savePath . DIRECTORY_SEPARATOR . $fileName);
                $small_url = preg_replace('/\\\\/', '/', DIRECTORY_SEPARATOR ."uploads" . $savePath . DIRECTORY_SEPARATOR . $small_name);
                $file_id = Db::name('upload_files')
                    ->insertGetId([
                        'manage_id' => $this->userInfo['manage_id'],
                        'file_path' => $full_url,
                        'file_small' => $small_url,
                        'file_type' => $extend_name,
                        'file_size' => $file_size,
                        'source' => 'edu_school',
                        'create_ip' => $this->request->ip()
                    ]);
                //$data['file_id'] =$file_id;
                //$data['file_path'] = $small_url;
                $res = [
                    'code' => 1,
                    'url' => $full_url,
                    'msg' => '图片上传成功'
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

    //删除学认领地址
    private function deleteSchoolAddress($school): array
    {
        try {
            if(!$school){
                throw new \Exception('学校信息不存在');
            }
            $region = Db::name('SysRegion')->field('id, simple_code')->find($school['region_id']);

            $where = [];
            $where[] = ['deleted', '=', 0];
            $update = [];
            if ($school['school_type'] == 1) {
                $update['primary_school_id'] = 0;
                $where[] = ['primary_school_id', '=', $school['id'] ];
            }
            if ($school['school_type'] == 2) {
                $update['middle_school_id'] = 0;
                $where[] = ['middle_school_id', '=', $school['id'] ];
            }
            //缩略地址
            $simple_res = (new SysAddressSimple())->editData($update, $where);
            if ($simple_res['code'] == 0) {
                throw new \Exception($simple_res['msg']);
            }

            //房产地址详细
            $address_model_name = $this->getModelNameByCode($region['simple_code']);
            if ($address_model_name == '') {
                throw new \Exception('完整地址model名称获取失败');
            }
            $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
            $address_model = new $address_model_name();

            //详细部分认领情况
            $simple_ids = $address_model->where($where)->group('simple_id')->column('count(*)', 'simple_id');
            $address_res = $address_model->editData($update, $where);
            if ($address_res['code'] == 0) {
                throw new \Exception($address_res['msg']);
            }

            //完整地址
            $intact_res = (new SysAddressIntact())->editData($update, $where);
            if ($intact_res['code'] == 0) {
                throw new \Exception($intact_res['msg']);
            }

            //户籍地址
            $birthplace_res = (new SysAddressBirthplace())->editData($update, $where);
            if ($birthplace_res['code'] == 0) {
                throw new \Exception($birthplace_res['msg']);
            }

            //缩略地址 勾选数量
            foreach ((array)$simple_ids as $k => $v) {
                $data = [];
                $data['id'] = $k;
                switch ($school['school_type']) {
                    case 1:
                        $data['primary_school_num'] = Db::raw('IF(primary_school_num < ' . $v . ', 0, primary_school_num - ' . $v . ' )');
                        break;
                    case 2:
                        $data['middle_school_num'] = Db::raw('IF(middle_school_num < ' . $v . ', 0, middle_school_num - ' . $v . ' )');
                        break;
                }
                $simple_res = (new SysAddressSimple())->editData($data);
                if ($simple_res['code'] == 0) {
                    throw new \Exception($simple_res['msg']);
                }
            }

            return ['code' => 1, 'msg' => '处理成功'];
        } catch (\Exception $exception) {
            return ['code' => 0, 'msg' => $exception->getMessage() ?: Lang::get('system_error')];
        }
    }

    /**
     * 根据行政代码获取model名称
     * @param $code
     * @return string
     */
    private function getModelNameByCode($code): string
    {
        $model_name = '';
        switch ($code){
            case 420602:
                $model_name = "AddressXiangCheng"; break;
            case 420606:
                $model_name = "AddressFanCheng"; break;
            case 420685:
                $model_name = "AddressGaoXin"; break;
            case 420608:
                $model_name = "AddressDongJin"; break;
            case 420607:
                $model_name = "AddressXiangZhou"; break;
            case 420683:
                $model_name = "AddressZaoYang"; break;
            case 420684:
                $model_name = "AddressYiCheng"; break;
            case 420682:
                $model_name = "AddressLaoHeKou"; break;
            case 420624:
                $model_name = "AddressNanZhang"; break;
            case 420626:
                $model_name = "AddressBaoKang"; break;
            case 420625:
                $model_name = "AddressGuCheng"; break;
            case 420652:
                $model_name = "AddressYuLiangZhou"; break;
        }
        return $model_name;
    }

}