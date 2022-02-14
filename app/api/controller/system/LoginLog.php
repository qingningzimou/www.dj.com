<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:37
 */
namespace app\api\controller\system;

use app\common\controller\Education;
use subTable\SysTablePartition;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;

class LoginLog extends Education
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
                    $this->result['start_time'] = date('Y-m-01 00:00:00',strtotime('-1 month'));
                }
                if(!isset($this->result['end_time']) || $this->result['end_time'] == ''){
                    $this->result['end_time'] = date('Y-m-d H:i:s');
                }
                $startTime = strtotime($this->result['start_time']);
                $endTime = strtotime($this->result['end_time']);
                if($startTime > $endTime){
                    throw new \Exception('时间范围选择错误');
                }
                $tableIndex = new SysTablePartition();
                $checkTable = $tableIndex->getTablePartition('deg_log_user_login');
                if ($checkTable['code'] == 0) {
                    throw new \Exception($checkTable['msg']);
                }
                $where = [];
                $where[] = ['log.deleted','=',0];
                $where[] = ['log.login_time','>=', $this->result['start_time']];
                $where[] = ['log.login_time','<=', $this->result['end_time']];

                if ($this->request->has('keyword') && $this->result['keyword']) {
                    $where[] = ['log.user_name|log.real_name','like', '%' . $this->result['keyword'] . '%'];
                }
                if ($this->request->has('failed') && $this->result['failed'] != ''){
                    $where[] = ['log.failed','=',$this->result['failed']];
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['region_id', 'in', $region_ids];

                $tableList = getPartitionTableList('deg_log_user_login',$this->result['start_time'], $this->result['end_time']);
                $checkTableList = $tableIndex->checkExistsTable($tableList);
                $tableList = $checkTableList['data'];
                $tableFirst = array_shift($tableList);
                $fields = 'log.id,log.user_name,log.login_ip,log.failed,log.login_time';

                $query = Db::table($tableFirst)->field($fields)
                    ->alias('log')
                    ->where($where);

                foreach ((array)$tableList as $table) {
                    if($table != $tableFirst){
                        $childQuery = Db::table($table)->field($fields)
                            ->alias('log')
                            ->where($where)
                            ->buildSql();
                        $query->unionAll($childQuery);
                    }
                }
                $page = 1;
                if ($this->request->has('curr') && $this->result['curr']) {
                    if (preg_match($preg, $this->result['curr']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                    $page = $this->result['curr'];
                    if($page < 1){
                        $page =1;
                    }
                }

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

                foreach ($list as $k => $v){
                    $list[$k]['failed_name'] = $v['failed'] == 0 ? '登录成功' : '登录失败';
                }

                $data = [
                    'total' => $total,
                    'per_page' => $pageSize,
                    'current_page' => $page,
                    'data' => $list,
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


}