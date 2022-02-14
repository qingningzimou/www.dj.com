<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 11:37
 */
namespace app\common\model;

use sm\TxSm4;
use subTable\SysTablePartition;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Request;
use think\Model;
use think\model\concern\SoftDelete;
use think\facade\Cache;
use think\facade\Log;

class Basic extends Model
{
    use SoftDelete;
    protected static $denyFields = [
        "update_time",
        "__token__",
        "__origin__"
    ];
    protected $deleteTime = 'deleted';
    protected $defaultSoftDelete = 0;
    protected $data;

    /**
     * 定义全局写入数据方法
     * @param array $data
     * @param number $isGetId 是否获取自增ID
     * @return array
     */
    public function addData(array $data, $isGetId = 0): array
    {
        try {
            //  如果保存成功
            //if ($this->allowField(true)->isUpdate(false)->save($data)) {
            if (parent::save($data)) {
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success')
                ];
                //  如果需要获取自增ID，则写入返回数据
                if ($isGetId) {
                    $res['insert_id'] = $this->id;
                }
            } else {
                //  如果保存失败
                $res = [
                    'code' => 0,
                    'msg' => Lang::get('insert_fail')
                ];
            }
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('insert_fail')
            ];
        }
        //  返回处理结果
        return $res;
    }

    /**
     * 更新信息
     * @param array $data
     * @param array $where
     * @return array
     */
    public function editData(array $data,array $where = []): array
    {
        try {
            //  如果保存成功
            if (parent::update($data,$where)) {
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];
            } else {
                //  如果保存失败
                $res = [
                    'code' => 0,
                    'msg' => Lang::get('update_fail')
                ];
            }
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('update_fail')
            ];
        }
        //  返回处理结果
        return $res;
    }

    /**
     * 删除信息
     * @param array $data
     * @param array $where
     * @return array
     */
    public function deleteData(array $data,array $where = []): array
    {
        try {
            //  如果保存成功
            if (parent::update($data,$where)) {
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('delete_success')
                ];
            } else {
                //  如果保存失败
                $res = [
                    'code' => 0,
                    'msg' => Lang::get('delete_fail')
                ];
            }
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('delete_fail')
            ];
        }
        //  返回处理结果
        return $res;
    }


    public static function onAfterInsert(Model $model): void
    {
        $request = Request::instance();
        if ($request->userInfo) {
            $tableTmp = "deg_operation_log";
            $logTable = (new SysTablePartition())->getTablePartition($tableTmp);
            if ($logTable['code'] != 1) return;
            $log = [];
            foreach (x_unsetField(self::$denyFields, $model->getData()) as $k => $v) {
                if ($k != $model->getPk()) {
                    $log[] = [
                        "fields" => $k,
                        "after" => $v
                    ];
                }
            }
            $sysNodes = (new SysNodes())
                        ->where('controller_name','=',$request->controller())
                        ->where('method_name',$request->action())
                        ->find();
            $node_name = $sysNodes ? $sysNodes['node_name'] : '';

            Db::table($logTable["table_name"])->insert([
                "controller" => $request->controller(),
                "action" => $request->action(),
                "do_ip" => $request->userInfo['last_ip'],
                "primary_id" => $model->getData($model->getPk()),
                "data" => json_encode($log, JSON_UNESCAPED_UNICODE),
                "admin_id" => $request->userInfo['manage_id'],
                "region_id" => $request->userInfo['region_id'],
                "table_name" => $model->getTable(),
                "model_name" => $model->name,
                "model_action" => "insert",
                "node_name" => $node_name
            ]);
        }
    }

    public static function onBeforeUpdate(Model $model): void
    {
        $model->id ? $where = ['id'=>$model->id] : $where = $model->getWhere();
        $orgionData = Db::table($model->getTable())->where($where)->find();
        $model->setAttr('orgionData',$orgionData);
    }

    public static function onAfterUpdate(Model $model): void
    {
        $request = Request::instance();
        if ($request->userInfo) {
            $tableTmp = "deg_operation_log";
            $logTable = (new SysTablePartition())->getTablePartition($tableTmp);
            if ($logTable['code'] != 1) return;
            //if (!array_key_exists("__origin__", $model->getData())) return;
            //$beforeData = $model->getOrigin();
            $beforeData = $model->getAttr('orgionData');
            if (!is_array($beforeData) || empty($beforeData)) return;
            $beforeData = x_unsetField(self::$denyFields, $beforeData);
            $afterData = x_unsetField(self::$denyFields, $model->getData());
            unset($afterData['orgionData']);
            $beforeArray = array_diff_assoc($beforeData, $afterData);
            $afterArray = array_diff_assoc($afterData, $beforeData);
            $updateLog = [];
            foreach ($afterArray as $k => $v) {
                if ($k != $model->getPk() && isset($beforeArray[$k])) {
                    $updateLog[] = [
                        "fields" => $k,
                        "before" => $beforeArray[$k],
                        "after" => $v
                    ];
                }
            }

            $sysNodes = (new SysNodes())
                ->where('controller_name','=',$request->controller())
                ->where('method_name',$request->action())
                ->find();
            $node_name = $sysNodes ? $sysNodes['node_name'] : '';

            Db::table($logTable["table_name"])->insert([
                "controller" => $request->controller(),
                "action" => $request->action(),
                "do_ip" => $request->userInfo['last_ip'],
                "primary_id" => $beforeData['id'],
                "data" => json_encode($updateLog, JSON_UNESCAPED_UNICODE),
                "admin_id" => $request->userInfo['manage_id'],
                "region_id" => $request->userInfo['region_id'],
                "table_name" => $model->getTable(),
                "model_name" => $model->name,
                "model_action" => "update",
                "node_name" => $node_name
            ]);
        }
    }

    public static function onAfterDelete(Model $model): void
    {
        $request = Request::instance();
        if ($request->userInfo) {
            $tableTmp = "deg_operation_log";
            $logTable = (new SysTablePartition())->getTablePartition($tableTmp);
            if ($logTable['code'] != 1) return;
            $log = [];
            foreach (x_unsetField(self::$denyFields, $model->getData()) as $k => $v) {
                if ($k != $model->getPk()) {
                    $log[] = [
                        "fields" => $k,
                        "before" => $v
                    ];
                }
            }
            $sysNodes = (new SysNodes())
                ->where('controller_name','=',$request->controller())
                ->where('method_name',$request->action())
                ->find();
            $node_name = $sysNodes ? $sysNodes['node_name'] : '';

            Db::table($logTable["table_name"])->insert([
                "controller" => $request->controller(),
                "action" => $request->action(),
                "do_ip" => $request->ip(),
                "primary_id" => $model->getData($model->getPk()),
                "data" => json_encode($log, JSON_UNESCAPED_UNICODE),
                "admin_id" => $request->userInfo['manage_id'],
                "table_name" => $model->getTable(),
                "model_name" => $model->name,
                "model_action" => "delete",
                "node_name" => $node_name
            ]);
        }
    }


    /**
     * @param string $code
     * @param array $sendData
     * @return array
     */
    public function sendApi(string $code,array $sendData): array
    {
        return [
            'code' => 0,
            'msg' => '暂不开发查询',
        ];
        $url = Cache::get('comparison_url');
        $key = Cache::get('comparison_key');
        $sm4 = new TxSm4();
        $data = [
            'InterfaceCode' => $code,
        ];
        $data = array_merge($data,$sendData);
        $rawData = $sm4->encrypt($key, json_encode($data,JSON_UNESCAPED_UNICODE));
        $getData = httpPost($url,$rawData,true);
        $getData = json_decode($getData,true);
        if($getData){
            if(intval($getData['code']) != 200){
                if($getData['message'] != '查询无果') {
                    return [
                        'code' => 0,
                        'msg' => $getData['message'],
                    ];
                }

            }
        }else{
            return [
                'code' => 0,
                'msg' => '查询失败',
            ];
        }

        return [
            'code' => 1,
            'data' => $getData['data'],
        ];
    }

}