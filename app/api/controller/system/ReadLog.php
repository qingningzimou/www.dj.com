<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:37
 */
namespace app\api\controller\system;

use app\common\controller\Education;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use think\facade\Db;

class ReadLog extends Education
{
    /**
     * 登录日志列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['m.deleted','=',0];

                if ($this->userInfo['grade_id'] >= $this->city_grade) {
                    if ($this->request->has('region_id') && $this->result['region_id'] > 0) {
                        $where[] = ['a.region_id', '=', $this->result['region_id'] ];
                    }
                }
                $region_ids = array_column(object_to_array($this->userInfo['relation_region']),'region_id');
                $where[] = ['a.region_id', 'in', $region_ids];

                if($this->request->has('keyword') && $this->result['keyword'] != '')
                {
                    $user_ids = Db::name('User')->whereLike('user_name', '%' . $this->result['keyword'] . '%')
                        ->where('deleted', 0)->column('id');
                    if($user_ids) {
                        $where[] = Db::Raw(" m.mobile like '%" . $this->result['keyword'] .
                            "' OR m.user_id in (" . implode(',', $user_ids) . ") ");
                    }else{
                        $where[] = ['m.mobile','like', '%' . $this->result['keyword'] . '%'];
                    }
                }

                //学生姓名
                $query = Db::name('UserApplyDetail')->where('user_apply_id', Db::raw('m.user_apply_id'))
                    ->where('deleted', 0)->field(['child_name'])->limit(1)->buildSql();
                //用户手机号
                $queryMobile = Db::name('User')->where('id', Db::raw('m.user_id'))
                    ->where('deleted', 0)->field(['user_name'])->limit(1)->buildSql();

                $data = Db::name('UserMessage')->alias('m')
                    ->join([
                        'deg_user_apply' => 'a'
                    ], 'm.user_apply_id = a.id and a.deleted = 0 and a.voided = 0 ', 'LEFT')
                    ->field([
                        'm.id',
                        'm.title',
                        'm.mobile',
                        'm.read_total',
                        'm.read_time',
                        'm.end_time',
                        'a.region_id' => 'region_id',
                        $query => 'real_name',
                        $queryMobile => 'user_name',
                    ])
                    ->where($where)
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $region = Cache::get('region');

                foreach ($data['data'] as $k => $v){
                    $data['data'][$k]['type_name'] = '消息';
                    $data['data'][$k]['region_name'] = '-';
                    $regionData = filter_value_one($region, 'id', $v['region_id']);
                    if (count($regionData) > 0){
                        $data['data'][$k]['region_name'] = $regionData['region_name'];
                    }
                    $data['data'][$k]['read_status_name'] = '未读';
                    if($v['read_total'] > 0 ){
                        $data['data'][$k]['read_status_name'] = '已读';
                    }
                    if($v['mobile'] == '' ){
                        $data['data'][$k]['mobile'] = $v['user_name'];
                    }

                    $data['data'][$k]['first_time'] = $v['read_time'] ? date('Y-m-d H:i:s', $v['read_time'] ) : '-';
                    $data['data'][$k]['last_time'] = $v['end_time'] ? date('Y-m-d H:i:s', $v['end_time'] ) : '-';
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

    /**
     * 获取区域下拉列表
     * @return \think\response\Json
     */
    public function getRegionList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted','=', 0];
                $where[] = ['disabled','=', 0];
                $where[] = ['parent_id','>', 0];

                $data = Db::name('sys_region')->where($where)->field(['id', 'region_name',])->select();
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

}