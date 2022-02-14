<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\comprehensive;

use app\common\controller\Education;
use app\common\model\Department;
use app\common\model\SysRegion;
use think\facade\Lang;
use think\response\Json;
use app\mobile\validate\ChangeRegion as validate;
use think\facade\Db;

class ChangeRegion extends Education
{
    /**
     * 变更区域列表
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where_user = "";
                $where_child = "";
                if($this->userInfo['grade_id'] == $this->area_grade) {
                    $where[]= ['region_id','=',$this->userInfo['region_id']];
                }else{
                    $where[]= ['region_id','=', $this->result['region_id']];
                }

                if($this->request->has('mobile') && $this->result['mobile'])
                {
                    $where_user = ' AND u.user_name = '.$this->request['mobile'];
                }
                if($this->request->has('audit') && $this->result['audit'] !== "")
                {
                    $where_audit = $this->result['audit'];
                }else{
                    $where_audit = -1;
                }

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where_child = " AND (d.real_name like '%".$this->result['keyword']."%' or d.idcard like '%".$this->result['keyword']."%')";
                }
                $page = 1;
                if($this->request->has('curr') && $this->result['curr'])
                {
                    $page = $this->result['curr'];
                }

                $pageSize = 10;
                $preg = "/^\d+$/u";

                if ($this->request->has('pagesize') && $this->result['pagesize']) {
                    if (preg_match($preg, $this->result['pagesize']) == 0){
                        throw new \Exception(Lang::get('check_fail'));
                    }
                    $pageSize = intval($this->result['pagesize']);
                    if($pageSize < 10){
                        $pageSize = 10;
                    }
                }
                $sql_where = '';
                $sql = 'SELECT c.*,u.user_name,d.idcard as child_idcard ,d.real_name as child_name,a.id as apply_id,a.family_id,a.house_id,'.
                        ' (CASE local_region_audit WHEN 0 THEN "待审批" WHEN 1 THEN "审核通过" WHEN 2 THEN "审核不通过" ELSE "待审批" END) as local_region_audit_text, '.
                        ' (CASE go_region_audit WHEN 0 THEN "待审批" WHEN 1 THEN "审核通过" WHEN 2 THEN "审核不通过" ELSE "待审批" END)  as go_region_audit_text,'.
                        ' (CASE city_audit WHEN 0 THEN "待审批" WHEN 1 THEN "审核通过" WHEN 2 THEN "审核不通过" ELSE "待审批" END)  as city_audit_text,'.
                        ' (CASE '.$this->userInfo['region_id'].' WHEN local_region_id THEN 1 WHEN go_region_id THEN 2 ELSE 0 END)  as region_type,'.
                        ' (select region_name from deg_sys_region where id=c.local_region_id) as local_region_name, '.
                        ' (select region_name from deg_sys_region where id=c.go_region_id) as go_region_name FROM deg_change_region as c';
                if($this->userInfo['grade_id'] == $this->area_grade) {
                    $sql_where .= ' AND CASE'.
                                    ' WHEN c.local_region_id = '.$this->userInfo['region_id'].''.
                                    ' THEN c.local_region_id = '.$this->userInfo['region_id'].' AND IF('.$where_audit.' >= 0, c.local_region_audit = '.$where_audit.', 1=1)'.
                                    ' WHEN c.go_region_id = '.$this->userInfo['region_id'].' AND c.local_region_audit = 1 AND c.city_audit = 1'.
                                    ' THEN c.go_region_id = '.$this->userInfo['region_id'].' AND IF('.$where_audit.' >= 0, c.go_region_audit = '.$where_audit.', 1=1)'.
                                    ' ELSE 1=2 END';
                }else{
                    $sql_where .= ' AND c.local_region_audit=1 AND IF('.$where_audit.' >= 0, c.city_audit = '.$where_audit.', 1=1)';
                }
                $sql .= ' LEFT JOIN  deg_user_child d ON c.child_id = d.id and d.deleted=0';
                $sql .= ' LEFT JOIN  deg_user_apply a ON a.child_id = c.child_id and a.deleted=0 and a.voided = 0';
                $sql .= ' LEFT JOIN  deg_user u ON u.id = a.user_id and u.deleted=0 and u.disabled=0';
                $sql .= ' WHERE 1=1 and c.deleted = 0 '.$where_user.$where_child .$sql_where;

                $offset = intval(($page - 1) * $pageSize);

                $query_result = Db::query($sql);
                $total = count($query_result);
                $sql .= " LIMIT " . $offset . ", " . $pageSize . " ";
                $result = Db::query($sql);
                foreach($result as $key => $value){
                    if($value['local_region_audit'] == 0 || $value['go_region_audit'] == 0){
                        $result[$key]['total_status'] = '审核中';
                    }
                    if($value['local_region_audit'] == 2 || $value['go_region_audit'] == 2){
                        $result[$key]['total_status'] = '审核拒绝';
                    }
                    if($value['local_region_audit'] == 1 && $value['go_region_audit'] == 1){
                        $result[$key]['total_status'] = '审核通过';
                    }
                }
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
     * 审核
     * @return Json
     */
    public function audit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {

                $data = $this->request->only([
                    'id',
                    'remark',
                    'audit',
                    'hash'
                ]);

                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'audit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                if(strlen($data['remark']) > 200){
                    throw new \Exception('意见凭证在200字符以内');
                }

                $info = Db::name("ChangeRegion")
                    ->where('id',$data['id'])
                    ->where('deleted',0)
                    ->findOrEmpty();
                if(!$info){
                    throw new \Exception("数据不存在");
                }

                $update_data = [];
                $add_data = [];
                if($this->userInfo['grade_id'] > $this->area_grade) {
                    if($info['local_region_audit'] == 1){
                        $update_data = [
                            'id'=>$data['id'],
                            'city_audit'=>$data['audit'],
                            'city_remark'=>$data['remark']
                        ];
                    }else{
                        throw new \Exception("变更之前的区域尚未审核");
                    }

                }else{
                   if($this->userInfo['region_id'] == $info['local_region_id']) {
                       $update_data = [
                           'id'=>$data['id'],
                           'local_region_audit'=>$data['audit'],
                           'local_remark'=>$data['remark']
                       ];
                   }else{
                       if($info['local_region_audit'] == 1 && $info['city_audit'] == 1){
                           $update_data = [
                               'id'=>$data['id'],
                               'go_region_audit'=>$data['audit'],
                               'go_remark'=>$data['remark']
                           ];

                           $add_data['apply_id'] = $info['apply_id'];
                           $add_data['user_id'] = $info['user_id'];
                           $add_data['region_ids'] = $info['go_region_id'];
                           $add_data['region_names'] = (new SysRegion())->where('id',$info['go_region_id'])->value('region_name');
                           $add_data['school_attr'] = '1,2';
                           $add_data['school_attr_text'] = '公办/民办';
                           $add_data['start_time'] = time();
                           $add_data['end_time'] = time() + 72 * 3600;

                       }else{
                           throw new \Exception("变更之前的区域和市局尚未审核");
                       }
                   }
                }

                $result = (new \app\mobile\model\user\ChangeRegion())->editData($update_data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }else{
                    $child_name = Db::name("user_child")->where('id',$info['child_id'])->where('deleted',0)->value('real_name');
                    $mobile = Db::name("user")->where('id',$info['user_id'])->where('deleted',0)->value('user_name');
                    //审核成功给用户发送消息
                    if($info['local_region_audit'] == 1 && $info['city_audit'] == 1 && $data['audit'] == 1){
                        $message['type'] = 5;
                        $message['child_name'] = $child_name;
                        $message['user_id'] = $info['user_id'];
                        $message['mobile'] = $mobile;
                        $message['user_apply_id'] = $info['apply_id'];
                        $res = $this->sendAutoMessage($message);
                        if($res['code'] == 0){
                            throw new \Exception($res['msg']);
                        }

                    }
                    //审核不成功给用户发送消息
                    if($data['audit'] == 2){
                        $message['type'] = 6;
                        $message['child_name'] = $child_name;
                        $message['user_id'] = $info['user_id'];
                        $message['mobile'] = $mobile;
                        $message['user_apply_id'] = $info['apply_id'];
                        $res = $this->sendAutoMessage($message);
                        if($res['code'] == 0){
                            throw new \Exception($res['msg']);
                        }
                    }

                }

                if($add_data){
                    //删除之前的变更区域
                    Db::name('change_region')->where('apply_id',$info['apply_id'])->update(['deleted'=>0]);
                    if($data['audit'] == 1){
                        //删除之前补录
                        (new \app\common\model\ApplyReplenished())->editData(['deleted'=>1],['apply_id'=>$info['apply_id']]);
                        //添加新的补录
                        $add = (new \app\common\model\ApplyReplenished())->addData($add_data,1);
                        if($add['code'] == 0){
                            throw new \Exception($add['msg']);
                        }
                    }
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
     * 获取区县资源
     * @return Json
     */
    public function getRegion(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = [];
                if($this->userInfo['grade_id'] > $this->area_grade) {
                    $data = (new Department())
                        ->where('parent_id','<>',0)
                        ->where('deleted',0)
                        ->where('disabled',0)
                        ->field(['region_id','department_name'])
                        ->select()
                        ->toArray();
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
     * @param int child_id
     * @return Json
     */
    public function getChildDetail(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'apply_id'
                ]);

                if(!isset($data['apply_id']) || intval($data['apply_id']) <= 0){
                    throw new \Exception('系统错误');
                }

                $result = $this->getChildDetails($data['apply_id']);
                $res = [
                    'code' => 1,
                    'data' => $result,
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