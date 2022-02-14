<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\model\Plan;
use app\common\model\SysRegion;
use app\common\model\UserApplyStatus;
use app\mobile\model\user\ApplyLog;
use think\response\Json;

class Progress extends MobileEducation
{
    /**
     * 获取填报申请进度
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $apply = (new \app\mobile\model\user\Apply())
                    ->where("user_id",$this->userInfo['id'])
                    ->where('voided',0)
                    ->select()->toArray();
                if(!$apply) {
                    $res = [
                        'code' => 1,
                        'msg' => '',
                    ];
                }else{
                    $data = [];
                    foreach($apply as $key=>$value){
                        $data[$key]['child_name'] = (new \app\mobile\model\user\Child())->where('id',$value['child_id'])->value('real_name');
                        $data[$key]['region_name'] = (new SysRegion())->where('id',$value['region_id'])->value('region_name');
                        $school_type = (new Plan())->where('id',$value['plan_id'])->value('school_type');
                        $data[$key]['plan_name'] = $school_type == 1 ? "小学" : "初中";
                        $data[$key]['list'] = ApplyLog::where([
                            'user_apply_id' => $value['id'],
                            'is_show' => 1,
                            'is_progress' => 1
                        ])->field([
                            'id',
                            'apply_message',
                            'create_time'
                        ])->order('create_time desc')->select()->toArray();
                    }

                    $res = [
                        'code' => 1,
                        'data' => $data
                    ];
                }

            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }
}