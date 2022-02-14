<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/22
 * Time: 11:49
 */

namespace app\api\controller\parent;

use app\common\controller\Education;
use app\common\model\RegionSetTime as modle;
use app\common\model\SysNodes;
use app\common\model\SysRegion;
use app\common\model\WorkCityConfig;
use app\common\validate\parent\RegionSetTime as validate;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;

class Config extends Education
{

    protected $type = [1=>'幼升小',2=>'小升初'];
    /**
     * 获取时间配置列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            $where = [];
            $dictionary = new FilterData();
            $getData = $dictionary->findValue('dictionary','SYSJSQX','SYSQYQX');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            if($this->userInfo['grade_id'] == $getData['data']){
                $where[] = ['region_id','=',$this->userInfo['region_id']];
            }

            try {
                $list = (new modle())->where($where)->order('region_id','ASC')->order('grade_id','ASC')->select()->toArray();
                foreach($list as $key=>$value){
                    $list[$key]['start_time_format'] = $value['start_time'] ? date("Y年m月d日",$value['start_time']) : '';
                    $list[$key]['end_time_format'] = $value['end_time'] ? date("Y年m月d日",$value['end_time']) : '';
                    $list[$key]['start_time_format_one'] = $value['start_time'] ? date("Y-m-d H:i:s",$value['start_time']) : '';
                    $list[$key]['end_time_format_one'] = $value['end_time'] ? date("Y-m-d H:i:s",$value['end_time']) : '';
                }
                $lists = [];
                foreach ($list as $key=>$value){
                    $lists[$value['region_id']][] = $value;
                }
                $lists = array_merge($lists);

                $timeData = [];
                $data = [];
                $data['isCity'] = false;
                if($this->userInfo['grade_id'] > $getData['data']) {
                    $xjxx = (new WorkCityConfig())->where('item_key','XJXX')->where('deleted',0)->find();
                    $bgjy = (new WorkCityConfig())->where('item_key','BGQY')->where('deleted',0)->find();

                    if(isset($xjxx['item_value']) && !empty($xjxx['item_value'])) {
                        $time = [];
                        $time[] = json_decode($xjxx['item_value'],true);
                        if(is_array($time) && !empty($time)) {
                            foreach ($time as $key => $value) {
                                $time[$key]['start_time_format'] = date("Y年m月d日", date_to_unixtime($value['startTime']));
                                $time[$key]['end_time_format'] = date("Y年m月d日", date_to_unixtime($value['endTime']));
                                $time[$key]['start_time_format_one'] = date("Y-m-d", date_to_unixtime($value['startTime']));
                                $time[$key]['end_time_format_one'] = date("Y-m-d", date_to_unixtime($value['endTime']));
                            }
                            $timeData['xjxx'] = $time;
                        }
                    }
                    if(isset($bgjy['item_value']) && !empty($bgjy['item_value'])) {
                        $time = [];
                        $time[] = json_decode($bgjy['item_value'],true);
                        if(is_array($time) && !empty($time)) {
                            foreach ($time as $key => $value) {
                                $time[$key]['start_time_format'] = date("Y年m月d日", date_to_unixtime($value['startTime']));
                                $time[$key]['end_time_format'] = date("Y年m月d日", date_to_unixtime($value['endTime']));
                                $time[$key]['start_time_format_one'] = date("Y-m-d", date_to_unixtime($value['startTime']));
                                $time[$key]['end_time_format_one'] = date("Y-m-d", date_to_unixtime($value['endTime']));
                            }
                            $timeData['bgqy'] = $time;
                        }
                    }

                    $plan = (new \app\common\model\Plan())
                        ->where('deleted',0)
                        ->whereRaw('plan_time = YEAR(NOW())')
                        ->field(['id,public_start_time','public_end_time','private_start_time','private_end_time','school_type'])
                        ->select()->toArray();
                    foreach($plan as $key=>$value){
                        $plan[$key]['public_start_time_format'] = date("Y年m月d日",$value['public_start_time']);
                        $plan[$key]['public_end_time_format'] = date("Y年m月d日",$value['public_end_time']);
                        $plan[$key]['private_start_time_format'] = date("Y年m月d日",$value['private_start_time']);
                        $plan[$key]['private_end_time_format'] = date("Y年m月d日",$value['private_end_time']);
                        $plan[$key]['public_start_time_format_one'] = date("Y-m-d H:i:s",$value['public_start_time']);
                        $plan[$key]['public_end_time_format_one'] = date("Y-m-d H:i:s",$value['public_end_time']);
                        $plan[$key]['private_start_time_format_one'] = date("Y-m-d H:i:s",$value['private_start_time']);
                        $plan[$key]['private_end_time_format_one'] = date("Y-m-d H:i:s",$value['private_end_time']);

                        $plan[$key]['school_type_name'] = $this->type[$value['school_type']];
                    }
                    $data['headerTime'] = $timeData;
                    $data['plan'] = $plan;
                    $data['isCity'] = true;
                }
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
                $data['data'] = $lists;
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
     * 获取时间设置
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = (new modle())->where('id',$this->result['id'])->where('region_id',$this->userInfo['region_id'])->find();
                if(empty($data))
                {
                    throw new \Exception('记录不存在');
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
     * 新增
     * @param int grade_id 班级id
     * @param int start_time 开始时间
     * @param int end_time 结束时间
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'grade_id',
                    'start_time',
                    'hash',
                    'end_time',
                ]);
                $checkdata = $this->checkData($data, validate::class,'add');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }

                $result = (new modle())->where('grade_id',$data['grade_id'])->where("deleted",0)->where('region_id',$this->userInfo['region_id'])->find();
                if($result){
                    throw new \Exception('数据已存在，请勿重复添加');
                }
                $data['start_time'] = strtotime($data['start_time']);
                $data['end_time'] = strtotime($data['end_time']);
                $data['region_id'] = $this->userInfo['region_id'];
                $data['region_name'] = (new SysRegion())->where('id',$this->userInfo['region_id'])->value('region_name');
                $data['grade_name'] = '';

                $start_time = strtotime('1970-01-01 00:00:00');
                $end_time = strtotime('2037-12-31 23:59:59');

                if($data['start_time'] < $start_time || $data['end_time'] > $end_time){
                    throw new \Exception('开始时间太小，结束时间太大！');
                }

                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary','SYSBJXX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }

                foreach ($getData['data'] as $value){
                    if($value['dictionary_value'] == $data['grade_id']){
                        $data['grade_name'] = $value['dictionary_name'];
                    }
                }
                if(!$data['grade_name']) {
                    throw new \Exception('没有找到班级信息');
                }
                $res = (new modle())->addData($data);
                if($res['code'] == 0){
                    throw new \Exception($res['msg']);
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
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 编辑
     * @param int id id
     * @param int grade_id 班级id
     * @param int start_time 开始时间
     * @param int end_time 结束时间
     * @return Json
     */
    public function actEdit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'start_time',
                    'hash',
                    'end_time',
                ]);

                $checkdata = $this->checkData($postData, validate::class,'edit');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }

                $result = (new modle())->find(['id'=>$postData['id']]);
                if(!$result){
                    throw new \Exception('数据不存在');
                }
                $data['id'] = $postData['id'];
                $data['start_time'] = strtotime($postData['start_time']);
                $data['end_time'] = strtotime($postData['end_time']);

                $start_time = strtotime('1970-01-01 00:00:00');
                $end_time = strtotime('2037-12-31 23:59:59');

                if( $data['start_time'] > $data['end_time'] ){
                    throw new \Exception('开始时间不能大于结束时间！');
                }
                if( $data['start_time'] < $start_time ){
                    throw new \Exception('开始时间太小！');
                }
                if( $data['end_time'] > $end_time ){
                    throw new \Exception('结束时间太大！');
                }

                $res = (new modle())->editData($data);
                if($res['code'] == 0){
                    throw new \Exception($res['msg']);
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

    public function handleHeaderTime(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'item_name',
                    'item_key',
                    'item_value',
                ]);

                $checkdata = $this->checkData($postData, \app\common\validate\basic\WorkCityConfig::class,'add');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }
                $config = (new WorkCityConfig())->where('item_key',$postData['item_key'])->find();
                if(!empty($config)) {
                    $postData['id'] = $config['id'];
                    $result = (new WorkCityConfig())->editData($postData);
                }else{
                    $result = (new WorkCityConfig())->addData($postData);
                }
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];
                Db::commit();
            }catch (\Exception $exception){
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
     * @param string code
     * @return Json
     */
    public function getTimeData(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'item_key',
                ]);

                $checkdata = $this->checkData($postData, \app\common\validate\basic\WorkCityConfig::class,'info');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }
                $data = (new WorkCityConfig())->where('item_key',$postData['item_key'])->find();
                $res = [
                    'code' => 1,
                    'data' => $data,
                ];
                Db::commit();
            }catch (\Exception $exception){
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }
}