<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 10:32
 */


namespace app\common\controller;

use app\common\model\UserReadLog;
use sm\TxSm4;
use think\facade\Cache;
use think\facade\Lang;


class MobileEducation extends MobileBasic
{
    //  定义接受到的数据
    protected $result;
    //  定义用户信息
    protected $userInfo;
    //  定义是否刷新
    protected $refresh;
    //  定义分页基数
    protected $pageSize;


    public function initialize()
    {
        parent::initialize();
        $this->result = $this->request->param();
        if (strtolower(app('http')->getName()) == 'mobile' && strtolower($this->request->controller()) !== 'login' && strtolower($this->request->controller()) !== 'captcha' && $this->request->isPost()) {
             //  从解密后的数据中获取manage_id
            $this->result['user_id'] = $this->request->userInfo['id'];
            $this->refresh = $this->request->refresh;
            $this->result['new_token'] = $this->request->new_token;
            //  写入管理员信息到内存
            $this->userInfo = $this->request->userInfo;
            //  释放内存中的userInfo
            unset($this->result['userInfo']);
            //  释放内存中的token
            unset($this->result['token']);
            $this->pageSize = 15;
            $this->refreshToken = false;
            //  如果请求包含了分页基数
            if (isset($this->result['pagesize']) && intval($this->result['pagesize']) > 0) {
                $this->pageSize = intval($this->result['pagesize']);
            }

        }
    }

    /**
     * 阅读信息记录
     * @param string $user_name
     * @param int $user_id
     * @param string $data_name
     * @param string $type_name
     * @param int $data_id
     * @return array
     */
    public function UserReadLog(int $user_id,string $user_name,int $data_id,string $data_name,string $type_name): array
    {
        try {
            $data = (new UserReadLog())
            ->where('user_id',$user_id)
            ->where('data_id',$data_id)
            ->where('type_name',$type_name)
            ->find();
            if(!$data){
                (new UserReadLog())->insert([
                    'user_id' => $user_id,
                    'user_name' => $user_name,
                    'data_id' => $data_id,
                    'data_name' => $data_name,
                    'type_name' => $type_name,
                    'ip' => $this->request->ip(),
                ]);
            }

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
    /**
     * 检查图片地址是否为正确地址
     * @param $url
     * @return bool
     */
    public function checkImageUrl($url){
        if($url){
            $pos = strpos($url, 'uploads/');
            if($pos == 1){
                return true;
            }
        }
        return false;
    }
    /**
     * @param string $hash
     * @param string $user_id
     * @return array
     */
    public function checkHash($hash, $user_id)
    {
        $hash_data = decrypt($hash);
        if(empty($hash_data)){
            return [
                'code' => 0,
                'msg' => Lang::get('hash_error')
            ];
        }
        $hash_user_id = $hash_data['user_id'];
        $hashKey = $hash_data['sub'];
        $hashTime = $hash_data['time'];
        //  如果hash不存在，则返回错误
        if (!Cache::has($hashKey)) {
            return [
                'code' => 0,
                'msg' => Lang::get('hash_no_found')
            ];
        }
        if ('user'.$user_id != $hash_user_id) {
            return [
                'code' => 0,
                'msg' => Lang::get('hash_check_fail')
            ];
        }
        if ($hashTime < time()){
            return [
                'code' => 0,
                'msg' => Lang::get('hash_expire')
            ];
        }
        //  销毁hash，如果销毁失败，则验证失败
        if (!Cache::delete($hashKey)) {
            return [
                'code' => 0,
                'msg' => Lang::get('hash_check_fail')
            ];
        }
        return [
            'code' => 1,
            'msg' => Lang::get('hash_check_success')
        ];
    }

}