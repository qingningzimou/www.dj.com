<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\basic;

use app\common\controller\Education;
use think\facade\Lang;
use think\App;
use think\facade\Db;
use think\facade\Cache;
use dictionary\FilterData;
use think\response\Json;

class Users extends Education
{

    public function __construct(App $app)
    {
        parent::__construct($app);
    }
    /**
     * 按分页获取学校信息
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {

                $where[] = ['u.deleted','=',0];
                $where[] = ['u.disabled','=',0];
                if($this->userInfo['grade_id'] == $this->area_grade){
                    $where[] = ['c.region_id','=',$this->userInfo['region_id']];
                }
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    if($this->userInfo['grade_id'] > $this->area_grade){
                        $where[] = ['c.region_id','=',$this->result['region_id']];
                    }
                }
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['u.user_name|c.idcard|c.real_name','like', '%' . $this->result['keyword'] . '%'];
                }

                $data = Db::name("user_child")
                    ->alias('c')
                    ->leftJoin('user_apply_tmp t','c.id=t.child_id and t.deleted = 0')
                    ->leftJoin('user u','u.id=t.user_id ')
                    ->where($where)
                    ->where('c.deleted',0)
                    ->field([
                        'u.user_name',
                        'c.real_name',
                        'c.idcard',
                        'c.id as child_id',
                        'u.id as user_id',
                        'c.create_time',
                        'c.unbind_time',
                        'c.region_id',
                    ])
                    ->order('c.id', 'DESC')
                    ->group('c.id')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();
                $region = Cache::get('region',[]);
                if($data['data']){
                    foreach($data['data'] as $key=>$value){
                        if($value['unbind_time'] > 0){
                            $data['data'][$key]['unbind_time'] = date('Y-m-d H:i:s',$value['unbind_time']);
                        }else{
                            $data['data'][$key]['unbind_time'] = '-';
                        }
                        $regionData = filter_value_one($region,'id',$value['region_id']);
                        $data['data'][$key]['region_name'] = isset($regionData['region_name']) ? $regionData['region_name'] : '';
                    }
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

    public function unbindUser(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'child_id',
                    'user_id',
                    'user_name',
                ]);
                if(intval($data['child_id']) <= 0 || intval($data['user_id']) <= 0) {
                    throw new \Exception('系统错误');
                }

                $preg = '/^1[3-9]\d{9}$/';
                if (preg_match($preg,$data['user_name']) == 0){
                    throw new \Exception('手机号码格式错误');
                }

                $user = Db::name('user')
                    ->where('deleted',0)
                    ->where('disabled',0)
                    ->where('id',$data['user_id'])
                    ->find();
                if(!$user){
                    throw new \Exception('用户不存在');
                }

                $result = Db::name('user')
                    ->where('deleted',0)
                    ->where('disabled',0)
                    ->where('user_name',$data['user_name'])
                    ->find();
                if(!$result){
                    throw new \Exception('绑定的手机号码不存在');
                }

                $child = Db::name('user_child')
                    ->where('deleted',0)
                    ->where('id',$data['child_id'])
                    ->find();
                if(!$child){
                    throw new \Exception('学生信息不存在');
                }
                $childdata['id'] = $child['id'];
                $childdata['mobile'] = $data['user_name'];
                $childdata['unbind_time'] = time();
                $childbind = (new \app\common\model\UserChild())->editData($childdata);
                if($childbind['code'] == 0){
                    throw new \Exception('解绑失败');
                }

                if($result['id'] == $data['user_id']){
                    throw new \Exception('修改手机号和原手机号相同');
                }

                $tmp = Db::name('user_apply_tmp')
                    ->where('deleted',0)
                    ->where('child_id',$data['child_id'])
                    ->where('user_id',$data['user_id'])
                    ->find();
                if(!$tmp){
                    throw new \Exception('信息不存在');
                }
                $tmpdata['id'] = $tmp['id'];
                $tmpdata['user_id'] = $result['id'];
                $tmpbind = (new \app\common\model\UserApplyTmp())->editData($tmpdata);
                if($tmpbind['code'] == 0){
                    throw new \Exception('解绑失败');
                }

                $apply = Db::name('user_apply')
                    ->where('deleted',0)
                    ->where('child_id',$data['child_id'])
                    ->where('user_id',$data['user_id'])
                    ->find();

                if($apply){
                    $applydata['id'] = $apply['id'];
                    $applydata['user_id'] = $result['id'];
                    $applybind = (new \app\common\model\UserApply())->editData($applydata);
                    if($applybind['code'] == 0){
                        throw new \Exception('解绑失败');
                    }

                    $applydetail = Db::name('user_apply_detail')
                        ->where('deleted',0)
                        ->where('child_id',$data['child_id'])
                        ->find();

                    if($applydetail){
                        $applydetaildata['id'] = $applydetail['id'];
                        $applydetaildata['mobile'] = $data['user_name'];
                        $applydetailbind = (new \app\common\model\UserApplyDetail())->editData($applydetaildata);
                        if($applydetailbind['code'] == 0){
                            throw new \Exception('解绑失败');
                        }

                    }
                }

                $res = [
                    'code' => 1,
                    'msg' => '解绑成功'
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

    public function deleteChild(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'child_id',
                ]);

                if(intval($data['child_id']) <= 0 ) {
                    throw new \Exception('系统错误');
                }

                //删除申请数据
                $apply = Db::name('user_apply')
                    ->where('deleted',0)
                    ->where('child_id',$data['child_id'])
                    ->find();
                if(!$apply){
                    //删除学生
                    $child = Db::name('user_child')
                        ->where('deleted',0)
                        ->where('id',$data['child_id'])
                        ->find();
                    if(!$child){
                        throw new \Exception('学生不存在');
                    }
                    $childdata['id'] = $child['id'];
                    $childdata['deleted'] = 1;
                    $childdelete = (new \app\common\model\UserChild())->editData($childdata);
                    if($childdelete['code'] == 0){
                        throw new \Exception('删除失败');
                    }
                    /*//删除关联信息
                    $tmp = Db::name('user_apply_tmp')
                        ->where('deleted',0)
                        ->where('child_id',$data['child_id'])
                        ->find();
                    if($tmp){
                        $tmpdata['id'] = $tmp['id'];
                        $tmpdata['deleted'] = 1;
                        $tmpdelete = (new \app\common\model\UserApplyTmp())->editData($tmpdata);
                        if($tmpdelete['code'] == 0){
                            throw new \Exception('删除失败');
                        }
                    }
                    $applydata['id'] = $apply['id'];
                    $applydata['deleted'] = 1;
                    $applydelete = (new \app\common\model\UserApply())->editData($applydata);
                    if($applydelete['code'] == 0){
                        throw new \Exception('删除失败');
                    }

                    //删除申请状态数据
                    $applystatus = Db::name('user_apply_status')
                        ->where('deleted',0)
                        ->where('user_apply_id',$apply['id'])
                        ->find();
                    if($applystatus){
                        $applystatusdata['id'] = $applystatus['id'];
                        $applystatusdata['deleted'] = 1;
                        $applystatusdelete = (new \app\common\model\UserApplyStatus())->editData($applystatusdata);
                        if($applystatusdelete['code'] == 0){
                            throw new \Exception('删除失败');
                        }
                    }

                    //删除申请详细数据
                    $applydetail = Db::name('user_apply_detail')
                        ->where('deleted',0)
                        ->where('child_id',$data['child_id'])
                        ->find();
                    if($applydetail){
                        $applydetaildata['id'] = $applydetail['id'];
                        $applydetaildata['deleted'] = 1;
                        $applydetaildelete = (new \app\common\model\UserApplyDetail())->editData($applydetaildata);
                        if($applydetaildelete['code'] == 0){
                            throw new \Exception('删除失败');
                        }
                    }*/
                }else{
                    throw new \Exception('学生已提交入学申请，请作废后变更手机号');
                }

                $res = [
                    'code' => 1,
                    'msg' => '删除成功'
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

    public function getRegion(): Json
    {
        if ($this->request->isPost()) {
            try {
                $region = \think\facade\Cache::get('region',[]);

                foreach ($region as $k => $v){
                    if($v['parent_id'] == '0'){
                        unset($region[$k]);
                    }
                }
                //$data = array_values($data);
                $res = [
                    'code' => 1,
                    'data' => $region,
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