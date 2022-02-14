<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\basic;

use app\common\controller\Education;
use app\common\model\Manage;
use app\common\model\Schools;
use think\facade\Filesystem;
use think\facade\Lang;
use think\response\Json;
use app\common\model\CentralSchool as model;
use app\common\validate\schools\Central as validate;
use Overtrue\Pinyin\Pinyin;
use think\facade\Db;
use think\facade\Cache;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Central extends Education
{
    /**
     * 按分页获取学校信息
     * @return \think\response\Json
     */
    public function getList()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted','=',0];
                if($this->request->has('keyword') && $this->result['keyword'])
                {
                    $where[] = ['central_code|central_name|simple_code','like', '%' . $this->result['keyword'] . '%'];
                }
                if($this->request->has('region_id') && $this->result['region_id'] > 0)
                {
                    $where[] = ['region_id','=', $this->result['region_id']];
                }
                if($this->request->has('police_id') && $this->result['police_id'] > 0)
                {
                    $where[] = ['police_id','=', $this->result['police_id']];
                }
                if ($this->userInfo['relation_region']){
                    $region_ids = array_column($this->userInfo['relation_region'],'region_id');
                    $where[] = ['region_id', 'in', $region_ids];
                }
                $data = Db::name('CentralSchool')
                    ->field([
                        'id',
                        'region_id',
                        'police_id',
                        'central_name',
                        'disabled',
                    ])
                    ->where($where)
                    //->order('id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');
                $police = Cache::get('police');
                foreach ($data['data'] as $k => $v){
                    $data['data'][$k]['region_name'] = '';
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }
                    $data['data'][$k]['police_name'] = '';
                    $policeData = filter_value_one($police, 'id', $v['police_id']);
                    if (count($policeData) > 0){
                        $data['data'][$k]['police_name'] = $policeData['name'];
                    }
                }
                //权限节点
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
                $data['resources'] = $res_data;

                //获取区县区域
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSQYQX');
                $data['isArea'] = $this->userInfo['grade_id'] > $getData['data'] ? false : true;
                $data['region_id'] = $data['isArea'] ? $this->userInfo['region_id'] : 0;

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
     * 获取指定学校信息
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
                //$data = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();

                $where = [];
                $where[] = ['deleted','=',0];
                $where[] = ['disabled','=',0];
                $where[] = ['directly','=',0];
                $where[] = ['central_id','=', $this->result['id']];
                //公办、民办、小学、初中权限
                $school_where = $this->getSchoolWhere();
                $where[] = $school_where['school_attr'];
                $where[] = $school_where['school_type'];

                $data = Db::name('SysSchool')
                    ->field([
                        'id',
                        'school_name',
                        'school_type',
                        'school_attr',
                        'telephone',
                    ])
                    ->where($where)
                    ->order('sort_order', 'asc')->order('id', 'desc')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $dictionary = new FilterData();
                $typeData = $dictionary->resArray('dictionary','SYSXXLX');
                if(!$typeData['code']){
                    throw new \Exception($typeData['msg']);
                }
                $attrData = $dictionary->resArray('dictionary','SYSXXXZ');
                if(!$attrData['code']){
                    throw new \Exception($attrData['msg']);
                }
                foreach ($data['data'] as $k => $v){
                    $schoolTypeData = filter_value_one($typeData['data'], 'dictionary_value', $v['school_type']);
                    $data['data'][$k]['school_type_name'] = '';
                    if (count($schoolTypeData) > 0){
                        $data['data'][$k]['school_type_name'] = $schoolTypeData['dictionary_name'];
                    }
                    $schoolAttrData = filter_value_one($attrData['data'], 'dictionary_value', $v['school_attr']);
                    $data['data'][$k]['school_attr_name'] = '';
                    if (count($schoolAttrData) > 0){
                        $data['data'][$k]['school_attr_name'] = $schoolAttrData['dictionary_name'];
                    }
                }

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
     * 学校页面资源
     * @return \think\response\Json
     */
    public function resView()
    {
        if ($this->request->isPost()) {
            try {
                $region = Cache::get('region');
                $dictionary = new FilterData();
                $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSQYQX');
                if(!$getData['code']){
                    throw new \Exception($getData['msg']);
                }
                $role_grade = $getData['data'];
                $data['region'] = array_values(filter_by_value($region, 'grade_id', $role_grade));
                $police = Cache::get('police');
                $data['police'] = $police;
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
     * 新增
     * @return Json
     */
    public function actAdd()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'central_name',
                    'region_id',
                    'police_id',
                    //'disabled',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'], $this->result['user_id']);
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
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['central_name']);
                //$data['create_time'] = time();

                unset($data['hash']);

                $result = (new model())->addData($data, 1);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                if (Cache::get('update_cache')) {
                    $centralList = Db::name('central_school')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('central', $centralList);
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
                    'central_name',
                    'region_id',
                    'police_id',
                    //'disabled',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'], $this->result['user_id']);
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
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['central_name']);
                unset($data['hash']);

                $result = (new model())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                if (Cache::get('update_cache')) {
                    $centralList = Db::name('CentralSchool')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('central', $centralList);
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
                $schoolNum = (new Schools())->where('central_id',$data['id'])->where('deleted',0)->count();
                if($schoolNum > 0){
                    throw new \Exception('教管会下有管辖学校不能删除');
                }
                $result = (new model())->deleteData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                $code = rand(100, 999);
                $manage = [
                    'deleted' => 1,
                    'user_name' => time().$code,
                ];
                $result = (new Manage())->deleteData($manage, ['central_id' => $data['id'] ]);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
                //删除教管会管理员缓存
                $manage_user_ids = Db::name('Manage')
                    ->where('central_id', $data['id'])->group('user_id')->column('user_id');
                foreach ($manage_user_ids as $user_id) {
                    if(Cache::has('user' . md5($user_id))) {
                        Cache::delete('user' . md5($user_id));
                    }
                }

                if (Cache::get('update_cache')) {
                    $centralList = Db::name('central_school')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('central', $centralList);
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



}