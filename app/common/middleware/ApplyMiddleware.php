<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/17
 * Time: 17:53
 */
namespace app\common\middleware;
use app\common\model\Plan;
use app\mobile\model\user\Apply;
use app\mobile\model\user\Child;
use think\facade\Db;
use think\facade\Lang;

class ApplyMiddleware
{
    /**
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed|\think\Response
     */
    public function handle(\think\Request $request, \Closure $next)
    {
        //  如果是post请求
        if ($request->isPost()) {
            $userInfo = $request->userInfo;
            $data['school_attr'] = $request->param('school_attr');
            $data['school_type'] = $request->param('school_type');
            $data['child_id'] = $request->param('child_id');
            if(!isset($data['school_attr']) || !isset($data['school_type']) || !isset($data['child_id'])){
                return $next($request);
            }
            if((!in_array($data['school_attr'],[1,2]) || !in_array($data['school_type'],[1,2])) && $request->action() != 'resArray') {
                return response([
                    'code' => 0,
                    'msg' => '非法操作',
                ],200,[],'json');
            }
            //  获取招生计划
            $planInfo = Plan::where('plan_time', date('Y'))->where("school_type",$data['school_type'])->findOrEmpty();
            if(!$planInfo) {
                return response([
                    'code' => 0,
                    'msg' => '申请通道关闭',
                ],200,[],'json');
            }
            //  补录是不收招生计划时间限制的
            //  变更区域是不收招生计划时间限制的
            //  根据当前的申请获取招生计划的 学校类型，判断时间是否在招生时间内
            //  公办申请
            if ($data['school_attr'] == 1) {
                //是否有公办补录权限
                $replenished_public = Db::name("apply_replenished")
                    ->where("user_id",$userInfo['id'])
                    ->whereRaw('FIND_IN_SET(1,school_attr)')
                    ->where('status',0)
                    ->where('deleted',0)
                    ->find();
                //判断是否有变更权限
               /* $change_region = Db::name("change_region")
                                ->where("user_id",$userInfo['id'])
                                ->where('status',0)
                                ->where('local_region_audit',1)
                                ->where('go_region_audit',1)
                                ->where('city_audit',1)
                                ->find();*/
                if (time() < $planInfo['public_start_time'] || time() > $planInfo['public_end_time']) {
                    if($replenished_public){
                        //判断补录时间
                        if(time() < $replenished_public['start_time'] || time() > $replenished_public['end_time']) {
                            return response([
                                'code' => 0,
                                'msg' => '补录时间已过期',
                            ],200,[],'json');
                        }
                        return $next($request);
                    }
                    //变更区域有权限直接进入
                    /*if($change_region) {
                        return $next($request);
                    }*/
                    return response([
                        'code' => 0,
                        'msg' => '申请通道尚未开启',
                    ],200,[],'json');
                }else{
                    //已经被预录取 不能修改资料
                    if(isset($data['child_id']) && intval($data['child_id'] > 0)){
                        /*$apply = (new Apply())
                            ->where('user_id',$userInfo['id'])
                            ->where('child_id',$data['child_id'])
                            ->field(['id','prepared'])
                            ->find();
                        if($apply) {
                            if($apply['prepared'] == 1){
                                return response([
                                    'code' => 0,
                                    'msg' => '该学生已经被预录取，不能再次提交申请',
                                ],200,[],'json');
                            }
                        }*/

                    }

                }
                //  民办申请
            } elseif ($data['school_attr'] == 2) {
                //是否有民办补录权限
                $replenished_private = Db::name("apply_replenished")
                    ->where("user_id",$userInfo['id'])
                    ->whereRaw('FIND_IN_SET(2,school_attr)')
                    ->where('status',0)
                    ->where('deleted',0)
                    ->find();
                if (time() < $planInfo['private_start_time'] || time() > $planInfo['private_end_time']) {
                    //判断补录时间
                    if($replenished_private){
                        if(time() < $replenished_private['start_time'] || time() > $replenished_private['end_time']) {
                            return response([
                                'code' => 0,
                                'msg' => '补录时间已过期',
                            ],200,[],'json');
                        }
                        return $next($request);
                    }
                    return response([
                        'code' => 0,
                        'msg' => '申请通道尚未开启',
                    ],200,[],'json');
                }else{
                    //已经被预录取 不能修改资料
                    if(isset($data['child_id']) && intval($data['child_id'] > 0)){
                        /*$apply = (new Apply())
                            ->where('user_id',$userInfo['id'])
                            ->where('child_id',$data['child_id'])
                            ->field(['id','prepared'])
                            ->find();
                        if($apply) {
                            if($apply['prepared'] == 1){
                                return response([
                                    'code' => 0,
                                    'msg' => '该学生已经被预录取，不能再次提交申请',
                                ],200,[],'json');
                            }
                        }*/
                    }
                }
            }

            return $next($request);
        }else{
            //  如果是其它请求
            return response([
                'code' => 0,
                'msg' => '非法操作',
            ],200,[],'json');
        }
    }
}
