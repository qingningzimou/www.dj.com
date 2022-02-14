<?php
/**
 * Created by PhpStorm.
 * manage: aiwes
 * Date: 2020/4/25
 * Time: 11:10
 */
namespace app\api\controller\basic;

use app\api\controller\system\Region;
use app\common\controller\Education;
use app\common\model\Module;
use app\common\model\ModuleNodes;
use app\common\model\RelationManageRegion;
use app\common\model\SysRegion;
use app\common\model\SysNodes;
use app\common\model\SysRoles;
use app\common\model\SysRoleNodes;
use app\common\validate\basic\ModuleManage as validate;
use think\App;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Overtrue\Pinyin\Pinyin;
use think\facade\Cache;
use dictionary\FilterData;
use think\facade\Log;
/*
 * 修正了ThinkPHP框架的验证器类，以支持联合索引类型的Unique验证
 * */
class ModuleManage extends Education
{

    /**
     * 按分页获取信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['module.region_id','=',$this->result['region_id']];
                }

                $data = Db::name('module_config')
                    ->alias('module')
                    ->join('SysRegion region','module.region_id = region.id')
                    ->where($where)
                    ->where('region.disabled',0)
                    ->where('region.deleted',0)
                    ->field([
                        'module.id',
                        'module.name',
                        'module.start_time',
                        'module.end_time',
                        'region.region_name',
                        'module.disabled',
                    ])
                    ->paginate(['list_rows'=> $this->pageSize,'var_page' => 'curr'])->toArray();
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


    //查看管理员信息
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->only([
                    'id',
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $postData['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = (new Module())->with(['relationRegion'])
                    ->find($postData['id'])->toArray();

                $adm_region_ids = array_column($this->userInfo['relation_region'],'region_id');
                $manage_region_ids = array_column(object_to_array($data['relation_region']),'region_id');
                $chkeck_region = 0;
                foreach ($adm_region_ids as $value){
                    if(in_array($value,$manage_region_ids)){
                        $chkeck_region = 1;
                    }
                }

                if (!$chkeck_region && !$this->userInfo['defaulted']) {
                    throw new \Exception('无权操作');
                }

                $data['data'] = $data;
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSXTQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                if($this->userInfo['role_id'] >= $getData['data']) {
                    $region = (new SysRegion())->whereIn('id', $adm_region_ids)->select()->toArray();
                    $data['region'] = $region;
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

    //获取module节点信息
    public function getModuleNodesList()
    {
        if ($this->request->isPost()) {
            $postData = $this->request->only([
                'id',
            ]);
            try {
                $node_ids = (new moduleNodes())->where([
                    'module_id' => $postData['id'],
                ])->column('node_id');
                $list = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->order('order_num')->select();
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
     * 新增管理人员
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'name',
                    'start_time',
                    'end_time',
                    'node_ids',
                    'hash',
                    'public_school_status',
                    'civil_school_status',
                    'primary_school_status',
                    'junior_middle_school_status',
                    'region_id',
                ]);
               /* //  验证表单hash
                $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }*/
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                if (!empty($postData['node_ids'])){
                    $preg = '/^\d+(,\d+)*$/u';
                    if (preg_match($preg,$postData['node_ids']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                }
                $node_ids = explode(',', $postData['node_ids']);
                $node_ids = array_unique($node_ids);

                unset($postData['hash']);
                unset($postData['node_ids']);

                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSSJQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }

                $postData['region_id'] = $this->userInfo['role_id'] >= $getData['data'] ? $postData['region_id'] : $this->userInfo['region_id'];
                $postData['start_time'] = strtotime($postData['start_time']);
                $postData['end_time'] = strtotime($postData['end_time']);
                //添加user表数据
                $module_id = Db::name('module_config')->insertGetId($postData);

                if($module_id) {
                    //自动添加默认权限信息
                    $sys_node_ids = (new SysRoleNodes())->where('role_id', $this->userInfo['role_id'])
                        //->where('node_type' ,$postData['node_type'])
                        ->column('node_id');
                    $saveData = [];
                    if ($node_ids){
                        foreach($node_ids as $key => $value){
                            if(in_array($value,$sys_node_ids)) {
                                $saveData[] = [
                                    'module_id' => $module_id,
                                    'node_id' => $value,
                                ];
                            }
                        }
                        Db::name('ModuleNodes')->insertAll($saveData);
                    }
                }else{
                    throw new \Exception('添加失败');
                }

                if (Cache::get('update_cache')) {
                    $dataList = (new Module())->where('disabled',0)->select()->toArray();
                    Cache::set('module_config', $dataList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success')
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            Db::rollback();
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 编辑管理员信息
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'name',
                    'start_time',
                    'end_time',
                    'node_ids',
                    'hash',
                    'public_school_status',
                    'civil_school_status',
                    'primary_school_status',
                    'junior_middle_school_status',
                    'region_id',
                ]);
                /* //  验证表单hash
                 $checkHash = parent::checkHash($postData['hash'],$this->result['user_id']);
                 if($checkHash['code'] == 0)
                 {
                     throw new \Exception($checkHash['msg']);
                 }*/
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($postData, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                if (!empty($postData['node_ids'])){
                    $preg = '/^\d+(,\d+)*$/u';
                    if (preg_match($preg, $postData['node_ids']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                }

                $node_ids = explode(',', $postData['node_ids']);
                $node_ids = array_unique($node_ids);

                unset($postData['hash']);
                unset($postData['node_ids']);

                $moduleInfo = Db::name("module_config")
                    ->where('id',$postData['id'])
                    ->where('disabled',0)
                    ->where('deleted',0)
                    ->find();
                if(!$moduleInfo){
                    throw new \Exception(Lang::get('数据不存在'));
                }

                //当前角色权限节点
                $sys_node_ids = (new SysRoleNodes())->where('role_id', $this->userInfo['role_id'])
                    ->column('node_id');
                //去掉当前角色权限节点以外的节点
                foreach($node_ids as $key => $item){
                    if(!in_array($item, $sys_node_ids)) {
                        unset($node_ids[$key]);
                    }
                }

                //更新表数据
                Db::name('module_config')->where('id',$postData['id'])->update($postData);

                //  查询所有目前应存在的节点信息
                $hasDataList = Db::name('ModuleNodes')->where([
                    'module_id' => $postData['id'],
                ])->column('node_id,id,deleted');
                $hasData = [];
                foreach($hasDataList as $item){
                    $hasData[$item['node_id']] = $item;
                }

                $mapData = array_column($hasData,'node_id');
                $saveData = [];
                foreach($node_ids as $node_id)
                {
                    if(in_array($node_id,$mapData))
                    {
                        Db::name('ModuleNodes')->where('id',$hasData[$node_id]['id'])->update(['deleted' => 0]);
                        unset($hasData[$node_id]);
                    }else{
                        if($node_id){
                            $saveData[] = [
                                'node_id' => $node_id,
                                'module_id' => $postData['id'],
                            ];
                        }
                    }
                }

                if($hasData) {
                    if(count($saveData) > 0 ) {
                        //  保存新增的节点信息
                        (new ModuleNodes())->saveAll($saveData);
                    }
                    //  删除多余的节点信息
                    Db::name('ModuleNodes')->whereIn('id', array_column($hasData,'id'))->update([
                        'deleted' => 1
                    ]);
                }else{
                    if(count($saveData) > 0 ) {
                        //新增
                        (new ModuleNodes())->insertAll($saveData);
                    }
                }

                if (Cache::get('update_cache')) {
                    $dataList = (new Module())->where('disabled',0)->select()->toArray();
                    Cache::set('module_config', $dataList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];
                Db::commit();
            } catch (\Exception $exception) {
                Db::rollback();
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }

            return parent::ajaxReturn($res);
        }
    }

    /**
     * 删除管理人员
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
                //  获取管理人员的关联信息
                $manage_region_ids = (new RelationManageRegion())->where(['manage_id' => $data['id']])->where('disabled',0)->column('region_id');
                $adm_region_ids = array_column($this->userInfo['relation_region'],'region_id');
                $chkeck_region = 0;
                foreach ($adm_region_ids as $value){
                    if(in_array($value,$manage_region_ids)){
                        $chkeck_region = 1;
                    }
                }
                if (!$chkeck_region && !$this->userInfo['defaulted']) {
                    throw new \Exception('权限不足以进行此操作');
                }
                $code = rand(100, 999);
                Db::name('RelationManageRegion')->where('manage_id',$data['id'])->update(['deleted' => 1]);
                Db::name('ManageNodes')->where('manage_id',$data['id'])->update(['deleted' => 1]);
                Db::name('manage')->where('id',$data['id'])->update(['deleted' => 1,'manage_name' => time().$code]);
                Cache::delete('m'.md5($data['id']));
                if (Cache::get('update_cache')) {
                    $dataList = (new Module())->where('disabled',0)->select()->toArray();
                    Cache::set('manage', $dataList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('delete_success')
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            Db::rollback();
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 状态，启用、禁用
     * @return Json
     */
    public function actIsDisabled()
    {
        try {
            $postData = $this->request->only([
                'id',
                'disabled',
            ]);

            $result = Db::name('module_config')
                ->where('id', $postData['id'])
                ->find();
            if(!$result){
                throw new \Exception('没有查找到数据');
            }
            Db::name('module_config')->where('id', $postData['id'])->update(['disabled' => $postData['disabled']]);
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
     * 管理员新增页面数据
     * 获取管理人员角色的权限节点
     * @param node_type 权限节点类型
     * @param role_id   角色ID
     * @return \think\response\Json
     */
    public function addView()
    {
        if ($this->request->isPost()) {
            try {
                $manage = (new \app\common\model\Manage())->where('id', $this->result['manage_id'])->field(['id','user_name','role_id'])->find();
                $role_names_arr = (new SysRoles())->whereIn('id', $manage['role_id'])->column('role_name');
                $node_ids = (new SysRoleNodes())->whereIn('role_id', $manage['role_id'])
                    ->where('node_type' ,$this->result['node_type'])
                    ->column('node_id');
                $list['id'] = $manage['id'];
                $list['manage_name'] = $manage['user_name'];
                $list['role_names'] = implode(",",array_values($role_names_arr));
                $list['nodes'] = (new SysNodes())->whereIn('id',$node_ids)->hidden(['deleted'])->order('order_num')->select()->toArray();
                $region_list  = (new SysRegion())->where('disabled',0)->select()->toArray();
                $res = [
                    'code' => 1,
                    'data' => ['list' => $list,'region_list' => $region_list],
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
     * 导入管理人员信息
     */
    public function import()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $file_size = $_FILES['file']['size'];
                if ($file_size > cache::get("file_excel_max_size") * 1024 * 1024) {
                    throw new \Exception('文件大小不能超过'.cache::get("file_excel_max_size").'M');
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
                    //$highestColumn = $sheet->getHighestColumn();   // 取得总列数
                }else{
                    throw new \Exception('获取上传文件失败');
                }
                //定义$data，循环表格的时候，找出已存在的管理人员。
                $data = [];
                $repeat = [];
                $pinyin = new Pinyin();
                //循环读取excel表格，整合成数组。
                $hasData = (new model())->field('manage_name,idcard')->select()->toArray();
                $role_id = (new SysRoles())->where(['disabled' => 0,'defaulted' => 1])->value('id');
                for ($j = 2; $j <= $highestRow; $j++) {
                    $tmp[$j - 2] = [
                        'manage_name' => $objPHPExcel->getActiveSheet()->getCell("A" . $j)->getValue(),
                        'real_name' => $objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue(),
                        'simple_code' => $pinyin->abbr($objPHPExcel->getActiveSheet()->getCell("B" . $j)->getValue()),
                        'idcard' => $objPHPExcel->getActiveSheet()->getCell("C" . $j)->getValue(),
                        'mobile' => $objPHPExcel->getActiveSheet()->getCell("D" . $j)->getFormattedValue(),
                        'region_id' => $objPHPExcel->getActiveSheet()->getCell("E" . $j)->getValue(),
                        'center_id' => $objPHPExcel->getActiveSheet()->getCell("F" . $j)->getValue(),
                        'supermarket_id' => $objPHPExcel->getActiveSheet()->getCell("G" . $j)->getValue(),
                        'password' => password_hash('123456', PASSWORD_DEFAULT),
                        'role_ids' => $role_id
                    ];
                    if (!in_array($tmp[$j - 2]['manage_name'], array_column($hasData, 'manage_name')) &&
                        !in_array($tmp[$j - 2]['idcard'], array_column($hasData, 'idcard'))
                    ) {
                        array_push($data, $tmp[$j - 2]);
                    } else {
                        array_push($repeat, $tmp[$j - 2]['manage_name']);
                    }
                }
                // 过滤上传数据中重复的管理人员名/编号和身份证数据
                $tmp = $data;
                $data = array_unique_value($data, 'manage_name');
                $data = array_unique_value($data, 'idcard');
                $repeat = array_merge($repeat,array_values(array_diff_assoc(array_column($tmp, 'manage_name'), array_column($data, 'manage_name'))));
                $successNum = 0;
                //获取默认权限信息
                $node_ids = (new SysNodes())->where('defaulted',1)->field(['id','node_type'])->select()->toArray();
                foreach ($data as $manage) {
                    $manage_id = Db::name('manage')->insertGetId($manage);
                    //自动添加关联配送中心信息
                    if ($manage['center_id']){
                        Db::name('RelationmanageCenter')->insert([
                            'manage_id' => $manage_id,
                            'center_id' => $manage['center_id']
                        ]);
                    }
                    //自动添加关联门店信息
                    if ($manage['supermarket_id']){
                        Db::name('RelationmanageSupermarket')->insert([
                            'manage_id' => $manage_id,
                            'supermarket_id' => $manage['supermarket_id']
                        ]);
                    }
                    $saveData = [];
                    if ($node_ids){
                        foreach($node_ids as $key => $value){
                            $saveData[] = [
                                'manage_id' => $manage_id,
                                'node_id' => $value['id'],
                                'node_type' => $value['node_type']
                            ];
                        }
                        Db::name('manageNodes')->insertAll($saveData);
                    }
                    $successNum +=1;
                }
                $res = [
                    'code' => 1,
                    'count' => $successNum,
                    'repeat' => $repeat
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

    public function publicState(){
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
            $result = Db::name('Module')
                ->where('id',$postData['id'])
                ->where('disabled',0)
                ->where('deleted',0)
                ->find();
            if(!$result){
                throw new \Exception('没有查找到数据');
            }
            Db::name('Module')->where('id',$postData)->update(['public_school_status'=>$postData['state']]);
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

    public function primaryState(){
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
            $result = Db::name('Module')
                ->where('id',$postData['id'])
                ->where('disabled',0)
                ->where('deleted',0)
                ->find();
            if(!$result){
                throw new \Exception('没有查找到数据');
            }
            Db::name('Module')->where('id',$postData)->update(['primary_school_status'=>$postData['state']]);
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

    public function civilState(){
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
            $result = Db::name('Module')
                ->where('id',$postData['id'])
                ->where('disabled',0)
                ->where('deleted',0)
                ->find();
            if(!$result){
                throw new \Exception('没有查找到数据');
            }
            Db::name('Module')->where('id',$postData)->update(['civil_school_status'=>$postData['state']]);
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

    public function juniorState(){
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
            $result = Db::name('Module')
                ->where('id',$postData['id'])
                ->where('disabled',0)
                ->where('deleted',0)
                ->find();
            if(!$result){
                throw new \Exception('没有查找到数据');
            }
            Db::name('Module')->where('id',$postData)->update(['junior_school_status'=>$postData['state']]);
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
}