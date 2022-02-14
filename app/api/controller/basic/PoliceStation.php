<?php

namespace app\api\controller\basic;

use app\common\controller\Education;
use dictionary\FilterData;
use think\facade\Db;
use think\facade\Lang;
use app\common\model\PoliceStation as model;

class PoliceStation extends Education
{
    /**
     * 派出所列表
     * @return \think\response\Json
     */
    public function getList()
    {
        try {
            $where = [];
            if ($this->request->has('region_id') && $this->result['region_id'] ) {
                $where[] = ['region_id', '=', $this->result['region_id']];
            }

            $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
            $where[] = ['region_id', 'in', $region_ids];

            $region_list = Db::name('sys_region')
                ->where([['disabled','=', 0], ['parent_id','>', 0], ['id','<>', 13]])
                ->field(['id', 'region_name',])->select();
            $region = [];
            foreach ($region_list as $k => $v){
                $region[$v['id']] = $v['region_name'];
            }

            $list = Db::name('sys_police_station')->where($where)
                ->order('id asc')->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            foreach ($list['data'] as $k => $v){
                $list['data'][$k]['region_name'] = isset($region[$v['region_id']]) ? $region[$v['region_id']] : '';
                $list['data'][$k]['is_main_area'] = $v['is_main_area'] == 1 ? true : false;
            }

            //区县权限
            $dictionary = new FilterData();
            $getData = $dictionary->findValue('dictionary', 'SYSJSQX', 'SYSQYQX');
            if(!$getData['code']){
                throw new \Exception($getData['msg']);
            }
            $role_id = $getData['data'];
            //区县角色隐藏发布机构
            $select_region = true;
            if($this->userInfo['role_id'] <= $role_id){
                $select_region = false;
            }
            $list['select_region'] = $select_region;

            $res = [
                'code' => 1,
                'data' => $list
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ];
        }
        return parent::ajaxReturn($res);
    }

    /**
     * 是否主城区
     * @return \think\response\Json
     */
    public function actIsMainArea()
    {
        if ($this->request->isPost()) {
            try {

                $info = model::find($this->request->param('id'));
                if (empty($info)) throw new \Exception('未找到该派出所');
                $data = [
                    'id' => $info['id'],
                    'is_main_area' => $this->request->param('is_main_area') ?? 0,
                ];
                $res = (new model())->editData($data);
            } catch (\Exception $exception) {
                $res = [
                    'code' => $exception->getCode(),
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 获取区域下拉列表
     * @return \think\response\Json
     */
    public function getSelectList()
    {
        if ($this->request->isPost()) {
            try {
                $result = $this->getAllSelectByRoleList();

                $code = $result['code'];
                $res = [
                    'code' => $code,
                    'data' => $result['region_list'],
                ];
                return parent::ajaxReturn($res);

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