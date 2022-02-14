<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/20
 * Time: 21:35
 */

namespace subTable;
use think\facade\Db;

class SysTablePartition
{
    /**
     * @param string $type
     * @param null $time
     * @return array
     */
    public function getTablePartition(string $type, $time = null): array
    {
        try {
            $time = $time ? $time : time();
            if (preg_match('/^[0-9]{10}$/', $time)) {
                //  如果是时间戳
                $tableTime = $time;
            } elseif (preg_match('/^\d{4}(\-|\/|.)\d{1,2}\1\d{1,2}$/', $time)) {
                $tableTime = strtotime($time);
            } else {
                //  如果不是时间戳或者date格式，则返回错误
                throw new \Exception('时间格式错误');
            }
            //  拼接模板表表名
            $tableTmp = $type . '_tmp';
            $tableName = $type . '_' . date('Ym', $tableTime);
            $isHasTable = Db::query("SHOW TABLES LIKE '".$tableName . "'");
            if ($isHasTable) {
                return [
                    'code' => 1,
                    'table_name' => $tableName
                ];
            }
            $createSql = 'create table ' . $tableName . ' like ' . $tableTmp;
            Db::execute($createSql);
            Db::name('SysTablePartition')
                ->insert([
                    'table_name' => $tableName,
                    'table_type' => $type,
                    'table_time' => $tableTime,
                    'create_time' => time()
                ]);
            $res = [
                'code' => 1,
                'table_name' => $tableName
            ];
        } catch (\Exception $exception) {
            //  抛出数据库异常数据
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: '获取分表失败'
            ];
        }
        return $res;
    }

    /**
     * @param $tableList
     * @return array
     */
    public function checkExistsTable($tableList): array
    {
        try {
            $tableListData =  Db::name('SysTablePartition')
                ->where('deleted',0)
                ->whereIn('table_name',$tableList)
                ->select()->toArray();
            $tableData =  array_column($tableListData,'table_name');
            $res = [
                'code' => 1,
                'data' => $tableData
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return $res;
    }
}