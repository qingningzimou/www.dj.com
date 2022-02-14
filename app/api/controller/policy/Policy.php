<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\policy;

use app\common\controller\Education;
use app\common\model\SysDictionary;
use think\facade\Lang;
use think\response\Json;
use app\common\model\Policy as model;
use app\common\validate\policy\Policy as validate;
use think\facade\Db;
use think\facade\Cache;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Policy extends Education
{
    /**
     * 按分页获取政策公告信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['p.deleted','=',0];

                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['title|content','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('type') && $this->result['type'])
                {
                    $where[] = ['p.type','=', $this->result['type']];
                }
                if($this->request->has('department_id') && $this->result['department_id'])
                {
                    $where[] = ['p.department_id','=', $this->result['department_id']];
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['p.region_id', 'in', $region_ids];


                $data = (new model())
                    ->alias('p')
                    ->join([
                        'deg_department' => 'd'
                    ], 'd.id = p.department_id' )
                    ->field([
                        'p.id',
                        'p.type',
                        'd.department_name',
                        'p.title',
                        'p.read_count',
                        'p.create_time',
                    ])
                    ->where($where)
                    ->order('id desc')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                //$dictionaryList = (new SysDictionary())->select()->toArray();
                //Cache::set('dictionary', $dictionaryList);
                $dictionary = new FilterData();
                $getData = $dictionary->resArray('dictionary','SYSZCGGLX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }

                foreach ($data['data'] as $k => $v){
                    $schoolTypeData = filter_value_one($getData['data'], 'dictionary_value', $v['type']);
                    $data['data'][$k]['type_name'] = '';
                    if (count($schoolTypeData) > 0){
                        $data['data'][$k]['type_name'] = $schoolTypeData['dictionary_name'];
                    }

                    //$data['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                }

                //区县权限
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSQYQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $role_id = $getData['data'];
                //区县角色隐藏发布机构
                $select_department = true;
                if($this->userInfo['role_id'] <= $role_id){
                    $select_department = false;
                }
                $data['select_department'] = $select_department;

                $res = [
                    'code' => 1,
                    'data' => $data,
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
     * 获取指定政策公告信息
     * @param id 学校ID
     * @return Json
     */
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();
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
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                if ( $this->userInfo['department_id'] > 0 ) {
                    $department_id = $this->userInfo['department_id'];

                    $department = Db::name('department')
                        ->where('id', $department_id)
                        ->where('disabled',0)
                        ->where('deleted',0)
                        ->find();
                    if (empty($department)) {
                        throw new \Exception('行政归属未找到');
                    }
                    $region_id = $department['region_id'];
                }else{
                    throw new \Exception('管理员所属部门机构为空');
                }

                $data = $this->request->only([
                    'title',
                    'type',
                    'region_id' => $region_id,
                    'department_id' => $department_id,
                    'content',
                    'hash'
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
                unset($data['hash']);

                //$data['content'] = htmlspecialchars(addslashes($data['content']));
                $data['content'] = $this->clearHtml($data['content'], '');
                $result = (new model())->addData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                if (Cache::get('update_cache')) {
                    $policyList = Db::name('PolicyNotice')->master(true)->where('deleted',0)->select()->toArray();
                    Cache::set('policy', $policyList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success'),
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
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'title',
                    'type',
                    'content',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'edit');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                unset($data['hash']);
                $data['content'] = $this->clearHtml($data['content'], '');
                //$data['content'] = htmlspecialchars(addslashes($data['content']));
                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                if (Cache::get('update_cache')) {
                    $policyList = Db::name('PolicyNotice')->master(true)->where('deleted',0)->select()->toArray();
                    Cache::set('policy', $policyList);
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
     * 删除
     * @return Json
     */
    public function actDelete()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'deleted' => 1
                ]);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'delete');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                Db::name('policy_notice')->where('id', $data['id'])->update(['deleted' => 1 ]);
                if (Cache::get('update_cache')) {
                    $policyList = Db::name('PolicyNotice')->master(true)->where('deleted',0)->select()->toArray();
                    Cache::set('policy', $policyList);
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
     * 市、区部门列表
     * @return Json
     */
    public function getDepartmentList()
    {
        if ($this->request->isPost()) {
            try {
                $list = Db::name("department")->where('grade_id', '>=', 3)
                    ->field(['id', 'parent_id' => 'pid', 'department_name' => 'name'])->select()->toArray();
                //$data = $this->getTree($list, 0, '');

                $res = [
                    'code' => 1,
                    'data' => $list,
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