<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\model\SysRegion;
use app\common\model\WorkCityConfig;
use app\mobile\model\user\Child;
use app\mobile\model\user\ChangeRegion as model;
use think\facade\Cache;
use think\facade\Db;
use think\response\Json;
use app\mobile\validate\ChangeRegion as validate;
use function GuzzleHttp\Psr7\str;

class ChangeRegion extends MobileEducation
{

    /**
     * 填报变更区域信息
     * @return Json
     */
    public function AddInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $config = (new WorkCityConfig())
                    ->where('item_key', 'BGQY')
                    ->where('deleted', '0')
                    ->find();
                if (!$config) {
                    throw new \Exception('系统错误');
                }


                $child = Db::name('user_apply')
                    ->alias('u')
                    ->leftJoin('user_child c','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.prepared",0)
                    ->where("u.resulted",0)
                    ->where("u.voided",0)
                    ->where("u.user_id",$this->userInfo['id'])
                    ->field(['c.real_name','c.idcard','c.id'])
                    ->select()
                    ->toArray();

                $time = json_decode($config['item_value'], true);

                if (time() < strtotime($time['startTime']) || time() > strtotime($time['endTime'])) {
                    throw new \Exception('变更区域功能尚未开启');
                }

                $data['child'] = $child;
                //$data['region'] = cache::get('region');
                $data['region'] = Db::name('sys_region')
                    ->where('disabled',0)
                    ->where('deleted',0)
                    ->where('parent_id','<>',0)
                    ->field([
                        'id',
                        'parent_id',
                        'region_name'
                    ])
                    ->hidden(['deleted','disabled'])->select()->toArray();

                $res = [
                    'code' => 1,
                    'data' => $data,
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
     * 添加区域变更信息
     * @param int child_id 学生id
     * @param int go_region_id 需要变更的公办区域id
     * @param string description 描述凭证
     * @param string file 学生id
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'child_id',
                    'go_region_id',
                    'description',
                    'file',
                    'hash',
                ]);

                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'add');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                //判断如果学生被预录取不能区域变更
                $apply = (new \app\mobile\model\user\Apply())
                    ->where('child_id', $data['child_id'])
                    ->where('school_attr', 1)
                    ->where('voided', 0)
                    ->where('user_id', $data['user_id'])
                    ->where('prepared', 0)
                    ->find();
                if(!$apply){
                    throw new \Exception('没有查找到相关申请资料或该学生已被预录取');
                }

                $change_info = Db::name("change_region")
                    ->where('user_id',$data['user_id'])
                    ->where('child_id',$data['child_id'])
                    ->where('deleted',0)
                    ->order('create_time DESC')
                    ->find();
                if($change_info){
                    if(
                        $change_info['local_region_audit'] == 0 ||
                        ($change_info['city_audit'] == 0 && $change_info['local_region_audit'] == 1) ||
                        ($change_info['go_region_audit'] == 0 && $change_info['local_region_audit'] == 1 && $change_info['city_audit'] == 1)
                    ){
                        throw new \Exception('正在审核中，请耐心等待！');
                    }
                }

                if($apply['region_id'] == $data['go_region_id']){
                    throw new \Exception('不能变更现在申报的区域');
                }

                $data['apply_id'] = $apply['id'];
                $data['local_region_id'] = $apply['region_id'];
                //市教育局直接审核通过
                $data['city_audit'] = 1;
                $insert = (new model())->addData($data);
                if ($insert['code'] == 0) {
                    throw new \Exception($insert['msg']);
                }
                $child_name = Db::name("user_child")->where('id',$apply['child_id'])->where('deleted',0)->value('real_name');
                Db::name("UserMessage")->insertGetId([
                    'user_id' => $data['user_id'],
                    'contents' => '（'.$child_name.'）您的变更入学区域申请已经提交，请等待平台审核结果消息通知。',
                ]);
                $res = [
                    'code' => 1,
                    'msg' => 'insert_success',
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
     * //查询学生所属区域
     * @param string idcard
     * @return Json
    */
    public function SearchRegion(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id' => $this->userInfo['id'],
                    'idcard',
                ]);
                $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
                if (preg_match($preg,$data['id_card']) == 0){
                    throw new \Exception('身份证格式错误');
                }
                $child = (new Child())
                    ->where("deleted",0)
                    ->where('idcard',$data['idcard'])
                    ->field([
                        'real_name',
                        'idcard',
                        'api_addredd',
                    ])
                    ->find();
                $res = [
                    'code' => 1,
                    'data' => $child,
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
}