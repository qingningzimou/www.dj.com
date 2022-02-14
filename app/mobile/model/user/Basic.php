<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 11:37
 */
namespace app\mobile\model\user;

use think\facade\Lang;
use think\Model;
use think\model\concern\SoftDelete;

class Basic extends Model
{
    use SoftDelete;

    protected $deleteTime = 'deleted';
    protected $defaultSoftDelete = 0;

    /**
     * 定义全局写入数据方法
     * @param array $data
     * @param number $isGetId 是否获取自增ID
     * @return array
     */
    public function addData(array $data, $isGetId = 0)
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
    public function editData(array $data, array $where=[])
    {
        try {
            //  如果保存成功
            if (parent::update($data ,$where)) {
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
     * @return array
     */
    public function deleteData(array $data)
    {
        try {
            //  如果删除成功
            if (parent::destroy($data)) {
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('delete_success')
                ];
            } else {
                //  如果删除失败
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
}