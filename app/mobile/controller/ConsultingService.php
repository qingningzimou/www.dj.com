<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\mobile\model\user\ApplyLog;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;
use app\mobile\model\user\ConsultingService as model;

class ConsultingService extends MobileEducation
{
    /**
     * 获取所有区域
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = Db::name("department")
                    ->alias('r')
                    ->leftJoin('consulting_service c','c.region_id=r.region_id and c.id in(select Max(c.id) from deg_consulting_service as c where user_id='.$this->userInfo['id'].' group by c.region_id) AND c.user_id='.$this->userInfo['id'])
                    ->where('r.deleted',0)
                    ->where('r.disabled',0)
                    //->where('c.user_id',$this->userInfo['id'])
                    ->field([
                        'r.region_id as rid',
                        'r.department_name as name',
                        'c.*',
                        '1 as cate',
                    ])
                    ->order('rid','ASC')
                    ->select()
                    ->toArray();
                if(!$data){
                    throw new \Exception( Lang::get('system_error'));
                }

                $school_data = Db::name("consulting_service")
                    ->alias('c')
                    ->leftJoin('sys_school s','c.school_id=s.id and c.id in(select Max(c.id) from deg_consulting_service as c where user_id='.$this->userInfo['id'].' group by c.school_id) AND c.user_id='.$this->userInfo['id'])
                    ->where('s.deleted',0)
                    ->where('s.disabled',0)
                    //->where('c.user_id',$this->userInfo['id'])
                    ->field([
                        's.id as rid',
                        's.school_name as name',
                        'c.*',
                        '2 as cate',
                    ])
                    ->order('rid','ASC')
                    ->select()
                    ->toArray();
                if($school_data){
                    $data = array_merge($data,$school_data);
                }

                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * @paramt int region_id 区域id
     * @return Json
    */
    public function getDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'data_id',
                    'cate_id'
                ]);
                if(!is_numeric($data['data_id']) || !in_array($data['cate_id'],[1,2])) {
                    throw new \Exception('系统错误');
                }
                if($data['cate_id'] == 1){
                    $where[] = ['region_id','=',$data['data_id']];
                }else{
                    $where[] = ['school_id','=',$data['data_id']];
                }
                $data = Db::name("consulting_service")
                    ->where('user_id',$data['user_id'])
                    ->where($where)
                    ->order('create_time','ASC')
                    ->select()->toArray();
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * @param int region_id 区域id
     * @param string content 咨询内容
     * @return Json
    */
    public function setContent(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'data_id',
                    'cate_id',
                    'content',
                    'type' => 1,
                ]);
                if(!is_numeric($data['data_id']) || !in_array($data['cate_id'],[1,2])) {
                    throw new \Exception('系统错误');
                }
                if($data['content'] == ""){
                    throw new \Exception('咨询内容不能为空');
                }

                if($data['cate_id'] == 1){
                    $data['region_id'] = $data['data_id'];
                }else{
                    $data['school_id'] = $data['data_id'];
                }

                $insert = (new model())->addData($data);
                if($insert['code'] == 0){
                    throw new \Exception($insert['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => '内容已提交',
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }
}