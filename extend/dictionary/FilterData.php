<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/20
 * Time: 21:35
 */

namespace dictionary;
use think\facade\Cache;

class FilterData
{
    /**
     * 通过字典获取字段值
     * @param $cache_name
     * @param $main_name
     * @param $sub_name
     * @return array
     */
    public function findValue($cache_name, $main_name,  $sub_name)
    {
        try {
            $cacheData = Cache::get($cache_name);
            $mainData = filter_value_one($cacheData, 'dictionary_code', $main_name);
            if(!count($mainData))
            {
                throw new \Exception('未找到字典类型');
            }
            $subData = array_values(filter_by_value($cacheData, 'parent_id', $mainData['id']));
            if(!count($subData))
            {
                throw new \Exception('未设置字典字段');
            }
            $data = filter_value_one($subData, 'dictionary_code', $sub_name);
            if(!count($data))
            {
                throw new \Exception('未找到所查字典字段');
            }
            $res = [
                'code' => 1,
                'data' => $data['dictionary_value']
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: '字典查找失败'
            ];
        }
        return $res;
    }
    /**
     * 通过字典获取所有数据
     * @param $cache_name
     * @param $main_name
     * @param $sub_name
     * @return array
     */
    public function findAll($cache_name, $main_name,  $sub_name)
    {
        try {
            $cacheData = Cache::get($cache_name);
            $mainData = filter_value_one($cacheData, 'dictionary_code', $main_name);
            if(!count($mainData))
            {
                throw new \Exception('未找到字典类型');
            }
            $subData = array_values(filter_by_value($cacheData, 'parent_id', $mainData['id']));
            if(!count($subData))
            {
                throw new \Exception('未设置字典字段');
            }
            $data = filter_value_one($subData, 'dictionary_code', $sub_name);
            if(!count($data))
            {
                throw new \Exception('未找到所查字典字段');
            }
            $res = [
                'code' => 1,
                'data' => $data
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: '字典查找失败'
            ];
        }
        return $res;
    }
    /**
     * 通过字典名称获取字段值
     * @param $cache_name
     * @param $main_name
     * @param $sub_name
     * @return array
     */
    public function findNameValue($cache_name, $main_name,  $sub_name)
    {
        try {
            $cacheData = Cache::get($cache_name);
            $mainData = filter_value_one($cacheData, 'dictionary_code', $main_name);
            if(!count($mainData))
            {
                throw new \Exception('未找到字典类型');
            }
            $subData = array_values(filter_by_value($cacheData, 'parent_id', $mainData['id']));
            if(!count($subData))
            {
                throw new \Exception('未设置字典字段');
            }
            $data = filter_value_one($subData, 'dictionary_name', $sub_name);
            if(!count($data))
            {
                throw new \Exception('未找到所查字典字段');
            }
            $res = [
                'code' => 1,
                'data' => $data['dictionary_value']
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: '字典查找失败'
            ];
        }
        return $res;
    }
    /**
     * 通过字典字段值获取字典名称
     * @param $cache_name
     * @param $main_name
     * @param $sub_value
     * @return array
     */
    public function findName($cache_name, $main_name,  $sub_value)
    {
        try {
            $cacheData = Cache::get($cache_name);
            $mainData = filter_value_one($cacheData, 'dictionary_code', $main_name);
            if(!count($mainData))
            {
                throw new \Exception('未找到字典类型');
            }
            $subData = array_values(filter_by_value($cacheData, 'parent_id', $mainData['id']));
            if(!count($subData))
            {
                throw new \Exception('未设置字典字段');
            }
            $data = filter_value_one($subData, 'dictionary_value', $sub_value);
            if(!count($data))
            {
                throw new \Exception('未找到所查字典字段');
            }
            $res = [
                'code' => 1,
                'data' => $data['dictionary_name']
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: '字典查找失败'
            ];
        }
        return $res;
    }

    /**
     * 获取字典列表
     * @param $cache_name
     * @param $main_name
     * @return array
     */
    public function resArray($cache_name, $main_name)
    {
        try {
            $cacheData = Cache::get($cache_name);
            $mainData = filter_value_one($cacheData, 'dictionary_code', $main_name);
            if(!count($mainData))
            {
                throw new \Exception('未找到字典类型');
            }
            $subData = array_values(filter_by_value($cacheData, 'parent_id', $mainData['id']));
            if(!count($subData))
            {
                throw new \Exception('未找到字典字段');
            }
            $res = [
                'code' => 1,
                'data' => $subData
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: '字典查找失败'
            ];
        }
        return $res;
    }
}