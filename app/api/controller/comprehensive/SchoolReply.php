<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\comprehensive;

use app\common\controller\Education;
use app\mobile\model\user\ConsultingService;
use dictionary\FilterData;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;

class SchoolReply extends Education
{
    /**
     * 咨询列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {

                $pageSize = 10;
                $preg = '/^\d+$/u';
                $page = 1;
                $where = '';
                if ($this->request->has('pagesize') && $this->result['pagesize']) {
                    if (preg_match($preg, $this->result['pagesize']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                    $pageSize = intval($this->result['pagesize']);
                    if($pageSize < 10){
                        $pageSize = 10;
                    }
                }

                if ($this->request->has('curr') && $this->result['curr']) {
                    $page = $this->result['curr'];
                }

                if ($this->request->has('keyword') && $this->result['keyword']) {
                    $where .= " AND u.user_name ='".$this->result['keyword']."'";
                }

                if($this->request->has('status') && $this->result['status'] !== "")
                {
                    if($this->request['status'] == 1){
                        $where .= ' AND (t5.num = 0 or t5.num is NULL)';
                    }else{
                        $where .= ' AND t5.num > 0';
                    }

                }


                $sql =  'SELECT t4.*,t5.num,u.user_name FROM'.
                        ' (SELECT * FROM ( SELECT * FROM deg_consulting_service ORDER BY create_time DESC LIMIT 10000 ) t) t4'.
                        ' LEFT JOIN (SELECT count(t1.id) AS num,t1.user_id,t1.school_id,t1.deleted FROM deg_consulting_service t1'.
                        ' LEFT JOIN (SELECT * FROM ( SELECT * FROM deg_consulting_service WHERE type = 2 and deleted=0 ORDER BY create_time DESC LIMIT 10000 ) t GROUP BY user_id,school_id ) t3 ON t1.user_id = t3.user_id and t1.school_id=t3.school_id'.
                        ' WHERE (t1.create_time > t3.create_time OR t3.id IS NULL and t1.deleted=0) GROUP BY t1.user_id,t1.school_id) t5 ON t4.user_id = t5.user_id and t4.school_id=t5.school_id'.
                        ' LEFT JOIN deg_user u on t4.user_id=u.id where 1=1 and t4.deleted = 0 and t4.school_id='.$this->userInfo['school_id'].$where.'  group by t4.school_id ,t4.user_id ';

                $offset = intval(($page - 1) * $pageSize);

                $query_result = Db::query($sql);
                $total = count($query_result);
                $sql .= " LIMIT " . $offset . ", " . $pageSize . " ";
                $result = Db::query($sql);
                $list = [
                    'total' => $total,
                    'per_page' => $pageSize,
                    'current_page' => $page,
                    'data' => $result,
                ];
                $res = [
                    'code' => 1,
                    'data' => $list
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
     * @paramt int user_id 用户id
     * @return Json
     */
    public function getDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id',
                    'school_id' => $this->userInfo['school_id'],
                ]);

                $data = Db::name("consulting_service")
                    ->where('user_id',$data['user_id'])
                    ->where('school_id',$data['school_id'])
                    ->where('deleted',0)
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
     * @param int school_id 学校id
     * @param string content 咨询内容
     * @param int user_id 用户id
     * @return Json
     */
    public function setContent(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id',
                    'school_id' => $this->userInfo['school_id'],
                    'content',
                    'type' => 2,
                ]);

                if($data['content'] == ""){
                    throw new \Exception('回复内容不能为空');
                }
                if(strlen($data['content']) > 2048){
                    throw new \Exception('回复内容长度超过限制');
                }

                $result = (new ConsultingService())
                    ->where('user_id',$data['user_id'])
                    ->where('school_id',$data['school_id'])
                    ->find();
                if(!$result){
                    throw new \Exception('没有找到相关数据');
                }
                $insert = (new \app\mobile\model\user\ConsultingService())->addData($data);
                if($insert['code'] == 0){
                    throw new \Exception($insert['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => '内容已提交',
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
     * 删除
     * @return Json
     */
    public function actDelete(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'user_id',
                    'school_id' => $this->userInfo['school_id']
                ]);

                $info = (new ConsultingService())
                    ->where('user_id',$data['user_id'])
                    ->where('school_id',$data['school_id'])
                    ->where('deleted',0)
                    ->select()->toArray();
                if($info){
                    $result = (new ConsultingService())->editData(['deleted'=>1],['user_id'=>$data['user_id'],'school_id'=>$data['school_id']]);
                    if($result['code'] == 0){
                        throw new \Exception($result['msg']);
                    }
                }else{
                    throw new \Exception('没有可操作的数据');
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success'),
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
     * 获取学生
     */
    public function getChild(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'user_id',
                ]);
                $data = Db::name('user_child')
                    ->alias('c')
                    ->leftJoin('user_apply u','c.id=u.child_id')
                    ->where("c.deleted",0)
                    ->where("u.deleted",0)
                    ->where("u.user_id",$data['user_id'])
                    ->field(['c.real_name','c.idcard','c.id','u.id as apply_id'])
                    ->select()
                    ->toArray();

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
     * 查看
     * @return Json
     */
    public function getChildDetail(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'apply_id'
                ]);

                if(!isset($data['apply_id']) || intval($data['apply_id']) <= 0){
                    throw new \Exception('系统错误');
                }

                $result = $this->getChildDetails($data['apply_id']);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }else{
                    $res = [
                        'code' => 1,
                        'data' => $result['data'],
                    ];
                }
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