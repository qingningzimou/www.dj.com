<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\parent;

use app\common\controller\Education;
use think\App;
use think\facade\Lang;
use think\response\Json;
use app\common\model\FaceConfig as model;
use app\common\validate\comprehensive\FaceConfig as validate;
use think\facade\Db;

class FaceConfig extends Education
{
    public $type = [];
    function __construct(App $app)
    {
        parent::__construct($app);
        $this->type = [
            '1' => '点对点',
        ];

    }

    /**
     * 列表
     * @return \think\response\Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $is_city = true;
                if($this->userInfo['grade_id'] == $this->area_grade){
                    $is_city = false;
                    $where[] = ['region_id','=',$this->userInfo['region_id']];
                }
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    if($this->userInfo['grade_id'] > $this->area_grade){
                        $where[] = ['region_id','=',$this->result['region_id']];
                    }
                }
                $data = Db::name('face_config')
                    ->alias('f')
                    ->join('SysRegion r','r.id=f.region_id')
                    ->where('f.deleted',0)
                    ->where($where)
                    ->field(['f.*','r.region_name'])
                    ->hidden(['f.deleted'])
                    ->order('f.id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();
                foreach($data['data'] as $key=>$value){
                    $data['data'][$key]['type_name'] = $this->type[$value['type']];
                }
                $data['is_city'] = $is_city;
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
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {

                $data = $this->request->only([
                    'type',
                    'start_time',
                    'end_time',
                    'status',
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
                $info = Db::name("face_config")
                    ->where('type',$data['type'])
                    ->where('deleted',0)
                    ->where('region_id', $this->userInfo['region_id'])
                    ->find();
                if($info){
                    throw new \Exception('类型已经存在，不能重复添加');
                }
                $data['region_id'] = $this->userInfo['region_id'];
                $result = (new model())->addData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
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
     * @param int id
     * @param int type  1已派位双符，2已派位房产单符，3未派位房产单符，4未派位户籍单符 ，5未派位年龄不足，6未派位年龄不符，7未派位为对比成功
     * @param string start_time
     * @param string end_time
     * @param int status
     * @return Json
     */
    public function actEdit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'type',
                    'start_time',
                    'end_time',
                    'status',
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

                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
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
     * @param int id
     * @return Json
     */
    public function actDelete(): Json
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

                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
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
     * @param int id
     * @param int status  0关闭 1开启
     * @return Json
    */
    public function setStatus(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'status',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'status');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }

                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
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
     * @return Json
    */
    public function resArray(): Json
    {
        $data['code'] = 1;
        $data['data'] = [
            [
                'name' => '点对点',
                'id' => 1,
            ],


        ];
        return parent::ajaxReturn($data);
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