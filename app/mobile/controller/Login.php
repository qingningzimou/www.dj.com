<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 15:10
 */
namespace app\mobile\controller;
use app\common\controller\MobileEducation;
use app\common\model\User as model;
use app\common\controller\GmCrypt;
use subTable\SysTablePartition;
use think\facade\Cache;
use think\facade\Lang;
use think\facade\Db;
use sm\TxSm2;
use sm\TxSm4;
use think\response\Json;

class Login extends MobileEducation
{


    /**
     * ixy授权登录
     * 系统中未注册账号，自动生成家长账号
     * @return Json
     */

    public function ixyRegister(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                //  获取请求数据
                $data = $this->request->only([
                    'data',
                    'key',
                ]);

                $sm2 = new TxSm2();
                $key = $sm2->privateDecrypt($data['key']);
                if (empty($key)){
                    throw new \Exception('数据错误解密失败');
                }
                $sm4 = new TxSm4();
                $userdata = json_decode($sm4->decrypt($key, $data['data']),true);
                $saveData = [
                    'sgasession' => $userdata['sgasession'] ? $userdata['sgasession'] : '',
                    'identlevel' => $userdata['identlevel'] ? $userdata['identlevel'] : '',
                    'loginflag' => $userdata['loginflag'] ? $userdata['loginflag'] : '',
                    'type' => $userdata['type'] ? $userdata['type'] : '',
                    'ixy_id' => $userdata['userid'] ? $userdata['userid'] : '',
                    'user_name' => $userdata['username'] ? $userdata['username'] : '',
                    'mobile' => $userdata['username'] ? $userdata['username'] : '',
                    'last_ip' => $this->request->ip()
                ];
                if(isset($userdata['idcard']) && $userdata['idcard']){
                    $saveData['idcard'] = $userdata['idcard'];
                }
                if(isset($userdata['mobilephone']) && $userdata['mobilephone']){
                    $saveData['mobilephone'] = $userdata['mobilephone'];
                }
                if(isset($userdata['name']) && $userdata['name']){
                    $saveData['real_name'] = $userdata['name'];
                }
                if(isset($userdata['sex']) && $userdata['sex']){
                    $saveData['sex'] = $userdata['sex'];
                }

                // 检查是否存在IXY账号
                $user_info =  Db::name('user')
                    ->where('ixy_id', $saveData['ixy_id'])
                    ->where('deleted', 0)
                    ->find();

                if (empty($user_info)) {
                    //判断手机号是否已注册
                    $chkUsers = Db::name('user')->where('user_name', $userdata['username'])->where('deleted',0)->find();
                    if(empty($chkUsers)){
                        $saveData['password'] = password_hash('xyrx@666', PASSWORD_DEFAULT);
                        $user_id = Db::name('user')->insertGetId($saveData);
                        $user_info = Db::name('user')->where('id',$user_id)->where('deleted',0)->find();
                    }else{
                        unset($saveData['user_name']);
                        Db::name('user')
                            ->where('id', $chkUsers['id'])
                            ->update($saveData);
                        $user_info = Db::name('user')->where('id',$chkUsers['id'])->where('deleted',0)->find();
                    }
                }else{
                    if($userdata['loginflag'] > 1 && $userdata['identlevel'] > 1){
                        unset($saveData['ixy_id']);
                        unset($saveData['user_name']);
                        Db::name('user')->where('id', $user_info['id'])->update($saveData);
                        $user_info = Db::name('user')->master(true)->where('id',$user_info['id'])->where('deleted',0)->find();
                    }
                }

                $user_info['nodes'] = [];// 用户当前资源
                $user_info['role_ids'] = [];// 用户拥有的角色
                $user_info['role_id'] = 0;// 用户当前角色
                $user_info['grade_id'] = 0; // 用户管理职能
                $user_info['nodes'] = [];
                $user_info['manage_id'] = 0;
                $user_info['main_account'] = 0;
                $user_info['defaulted'] = 0;

                if (Cache::get('mobile_user'.md5($user_info['id']))){
                    Cache::delete('mobile_user'.md5($user_info['id']));
                    Cache::delete('mobile_user'.md5($user_info['id'].'old_token'));
                }
                self::userLoginLog(0, $user_info['user_name'],$user_info['real_name'],$saveData['last_ip']);
                $gmCrypt = new GmCrypt();
                $token = $gmCrypt->setToken($user_info['id']);
                // 更新用户登录信息
                (new model())->where('id', $user_info['id'])->update(['last_ip' => $saveData['last_ip'],'last_time' => date('Y-m-d H:i:s'),'err_num' => 0]);
                unset($user_info['password']);
                $user_info['token'] = $token;
                Cache::set('mobile_user'.md5($user_info['id']),$user_info);
                Cache::set('mobile_user'.md5($user_info['id'].'token_time'),time());
                unset($user_info['token']);
                unset($user_info['nodes']);
                $res =  [
                    'code' => 1,
                    'data' => [
                        'token' => $token,
                    ],
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return json($res);
        }
    }

    /**
     * 登录信息记录
     * @param string $user_name
     * @param string $real_name
     * @param string $login_ip
     * @param string $failed
     * @return
     */
    private function userLoginLog($region_id,$user_name,$real_name,$login_ip,$failed = 0){
        try {
            $tableTmp = "deg_log_user_login";
            $logTable = (new SysTablePartition())->getTablePartition($tableTmp);
            if ($logTable['code'] != 1){
                throw new \Exception('登录日志分表错误');
            }
            /*(new LogUserLogin())->addData([
                'region_id' => $region_id,
                'user_name' => $user_name,
                'real_name' => $real_name,
                'login_ip' => $login_ip,
                'failed' => $failed,
            ]);*/
            Db::table($logTable["table_name"])->insert([
                'region_id' => $region_id,
                'user_name' => $user_name,
                'real_name' => $real_name,
                'login_ip' => $login_ip,
                'failed' => $failed,
            ]);
            $res = [
                'code' => 1,
                'msg' => '记录成功'
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return $res;
    }
}