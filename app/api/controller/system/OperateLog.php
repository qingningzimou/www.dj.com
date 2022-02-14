<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:37
 */
namespace app\api\controller\system;

use app\common\controller\Education;
use dictionary\FilterData;
use subTable\SysTablePartition;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;

class OperateLog extends Education
{
    /**
     * 登录日志列表
     * @return Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $preg = '/^\d+$/u';
                //默认开始时间上个月
                if(!isset($this->result['start_time']) || $this->result['start_time'] == ''){
                    $this->result['start_time'] = date('Y-m-d H:i:s',strtotime('-1 week'));
                }
                if(!isset($this->result['end_time']) || $this->result['end_time'] == ''){
                    $this->result['end_time'] = date('Y-m-d H:i:s');
                }
                $startTime = $this->result['start_time'];
                $endTime = $this->result['end_time'];
                if($startTime > $endTime){
                    throw new \Exception('时间范围选择错误');
                }
                $tableIndex = new SysTablePartition();
                $checkTable = $tableIndex->getTablePartition('deg_operation_log');
                if ($checkTable['code'] == 0) {
                    throw new \Exception($checkTable['msg']);
                }
                $where = [];
                $where[] = ['log.create_time','>=', $startTime];
                $where[] = ['log.create_time','<=', $endTime];

                if ($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['manage.user_name|manage.mobile','like', '%' . $this->result['keyword'] . '%'];
                }
                if ($this->request->has('controller_name') && $this->result['controller_name'] != '' &&
                    $this->request->has('method_name') && $this->result['method_name'] != ''
                ){
                    $where[] = [['log.action','=',$this->result['method_name']],['log.controller','=',$this->result['controller_name']]];
                }
                if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                    $where[] = ['log.region_id', '=', $this->result['region_id']];
                }

                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['log.region_id', 'in', $region_ids];

                $tableList = getPartitionTableList('deg_operation_log',$this->result['start_time'],$this->result['end_time']);
                $checkTableList = $tableIndex->checkExistsTable($tableList);
                $tableList = $checkTableList['data'];
                $tableFirst = array_shift($tableList);
                $fields = 'log.id,manage.user_name,manage.mobile,manage.real_name,log.table_name,log.primary_id,log.controller,log.action,log.model_action,log.create_time,log.do_ip';

                $query = Db::table($tableFirst)->field($fields)
                    ->alias('log')
                    ->join(['deg_manage' => 'manage'], 'manage.id = log.admin_id and manage.deleted = 0')
                    ->where($where);
                foreach ($tableList as $table) {
                    if($table != $tableFirst){
                        $childQuery = Db::table($table)->field($fields)
                            ->alias('log')
                            ->join(['deg_manage' => 'manage'], 'manage.id = log.admin_id and manage.deleted = 0')
                            ->where($where)
                            ->buildSql();
                        $query->unionAll($childQuery);
                    }
                }

                $page = 1;
                if ($this->request->has('curr') && $this->result['curr']) {
                    if (preg_match($preg,$this->result['curr']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                    $page = $this->result['curr'];
                    if($page < 1){
                        $page =1;
                    }
                }
                $pageSize = 10;
                if ($this->request->has('pagesize') && $this->result['pagesize']) {
                    if (preg_match($preg,$this->result['pagesize']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                    $pageSize = $this->result['pagesize'];
                    if($pageSize < 10){
                        $pageSize =10;
                    }
                }

                $queryTotal = $query->select()->toArray();
                $total = count($queryTotal);

                $offset = ($page - 1) * $pageSize;
                $list = $query->order('id', 'desc')->limit($offset, $pageSize)->select()->toArray();

                $nodes = Cache::get('nodes',[]);
                foreach ($list as $k => $v){
                    $list[$k]['model_action_text'] = $v['model_action'] == 'insert' ? '新增' : '更新';
                    foreach($nodes as $key=>$value){
                        if(strtoupper($value['controller_name']) == strtoupper($v['controller']) &&
                            strtoupper($value['method_name']) == strtoupper($v['action'])){
                            $list[$k]['model_controller_text'] = $value['node_name'];
                        }
                    }

                }

                //区县权限
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSQYQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $role_id = $getData['data'];
                //区县角色隐藏发布机构
                $select_region = true;
                if($this->userInfo['role_id'] <= $role_id){
                    $select_region = false;
                }

                $data = [
                    'total' => $total,
                    'per_page' => $pageSize,
                    'current_page' => $page,
                    'data' => $list,
                    'select_region' => $select_region,
                ];

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
    public function getRegionList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['disabled','=', 0];
                $where[] = ['parent_id','>', 0];
                $where[] = ['id','<>', 13];

                $data = Db::name('sys_region')->where($where)->field(['id', 'region_name',])->select();
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

    public function resArray(): Json
    {
        if ($this->request->isPost()) {
            try {
                $nodes = $this->userInfo['nodes'];
                $nodes_array = [];
                foreach($nodes as $key => $value){
                    $nodes_object = new \stdClass;
                    if($value['controller_name'] && $value['method_name']) {
                        $nodes_object->controller = $value['controller_name'];
                        $nodes_object->method = $value['method_name'];
                        $nodes_object->name = $value['node_name'];
                        $nodes_object->id = $value['id'];
                        $nodes_array[] = $nodes_object;
                    }
                }
                $res = [
                    'code' => 1,
                    'data' => $nodes_array,
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