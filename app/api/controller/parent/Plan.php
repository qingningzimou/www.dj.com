<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/22
 * Time: 11:49
 */

namespace app\api\controller\parent;

use app\common\controller\Education;
use app\common\model\Plan as modle;
use app\common\validate\parent\Plan as validate;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;
use think\response\Json;

class Plan extends Education
{
    protected $type = [1=>'幼升小',2=>'小升初'];
    /**
     * 获取时间配置列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {

            try {
                $list = (new modle())->where('deleted',0)->select()->toArray();
                foreach($list as $key=>$value){
                    $list[$key]['public_start_time_format'] = date("Y年m月d日",$value['public_start_time']);
                    $list[$key]['public_end_time_format'] = date("Y年m月d日",$value['public_end_time']);
                    $list[$key]['private_start_time_format'] = date("Y年m月d日",$value['private_start_time']);
                    $list[$key]['private_end_time_format'] = date("Y年m月d日",$value['private_end_time']);
                    $list[$key]['school_type_name'] = $this->type[$value['school_type']];
                }
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
     * 获取时间设置
     * @param int id
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->only([
                    'id',
                ]);
                if(!$this->request->has('id')){
                    throw new \Exception('数据错误');
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $postData['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $data = (new modle())->where('id',$this->result['id'])->find();
                if(empty($data))
                {
                    throw new \Exception('记录不存在');
                }
                $data['public_start_time_format'] = date("Y年m月d日",$data['public_start_time']);
                $data['public_end_time_format'] = date("Y年m月d日",$data['public_end_time']);
                $data['private_start_time_format'] = date("Y年m月d日",$data['private_start_time']);
                $data['private_end_time_format'] = date("Y年m月d日",$data['private_end_time']);

                $data['public_start_time_format_one'] = date("Y-m-d H:i:s",$data['public_start_time']);
                $data['public_end_time_format_one'] = date("Y-m-d H:i:s",$data['public_end_time']);
                $data['private_start_time_format_one'] = date("Y-m-d H:i:s",$data['private_start_time']);
                $data['private_end_time_format_one'] = date("Y-m-d H:i:s",$data['private_end_time']);

                $data['school_type_name'] = $this->type[$data['school_type']];

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
     * @param string plan_name
     * @param int school_type
     * @param int public_start_time
     * @param int public_end_time
     * @param int private_start_time
     * @param int private_end_time
     * @param string agreement
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'plan_name',
                    'school_type',
                    'public_start_time',
                    'public_end_time',
                    'private_start_time',
                    'private_end_time',
                    'agreement',
                ]);
                $checkdata = $this->checkData($data,validate::class,'add');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }
                $result = (new modle())->where('school_type',$data['school_type'])->where('plan_time',date("Y",time()))->find();
                if($result){
                    throw new \Exception('数据已存在，请勿重复添加');
                }
                $data['public_start_time'] = strtotime($data['public_start_time']);
                $data['public_end_time'] = strtotime($data['public_end_time']);
                $data['private_start_time'] = strtotime($data['private_start_time']);
                $data['private_end_time'] = strtotime($data['private_end_time']);
                $data['plan_time'] = date("Y",time());
                $res = (new modle())->addData($data);
                if($res['code'] == 0){
                    throw new \Exception($res['msg']);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('insert_success')
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
     * @param string plan_name
     * @param int school_type
     * @param int public_start_time
     * @param int public_end_time
     * @param int private_start_time
     * @param int private_end_time
     * @param string agreement
     * @return Json
     */
    public function actEdit(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $postData = $this->request->only([
                    'id',
                    'plan_name',
                    'school_type',
                    'public_start_time',
                    'public_end_time',
                    'private_start_time',
                    'private_end_time',
                    'agreement',
                ]);
                $checkdata = $this->checkData($postData,validate::class,'edit');
                if($checkdata['code'] == 0){
                    throw new \Exception($checkdata['msg']);
                }
                $result = (new modle())->find(['id'=>$postData['id']]);
                if(!$result){
                    throw new \Exception('数据不存在');
                }
                $result = (new modle())
                    ->where('school_type',$postData['school_type'])
                    ->where('plan_time',date("Y",time()))
                    ->where('id','<>',$postData['id'])
                    ->find();
                if($result){
                    throw new \Exception('招生计划此类型数据已存在，请勿重复添加');
                }
                $start_time = strtotime(date('Y', time()) . '-01-01 00:00:00');
                $end_time = strtotime(date('Y', time()) . '-12-31 23:59:59');

                $postData['public_start_time'] = strtotime($postData['public_start_time']);
                $postData['public_end_time'] = strtotime($postData['public_end_time']);
                $postData['private_start_time'] = strtotime($postData['private_start_time']);
                $postData['private_end_time'] = strtotime($postData['private_end_time']);

                if( $postData['public_start_time'] > $postData['public_end_time'] ){
                    throw new \Exception('公办开始时间不能大于公办结束时间！');
                }
                if( $postData['public_start_time'] < $start_time ){
                    throw new \Exception('公办开始时间不能小于年初！');
                }
                if( $postData['public_end_time'] > $end_time ){
                    throw new \Exception('公办结束时间不能大于年末！');
                }
                if( $postData['private_start_time'] > $postData['private_end_time'] ){
                    throw new \Exception('民办开始时间不能大于民办结束时间！');
                }
                if( $postData['private_start_time'] < $start_time ){
                    throw new \Exception('民办开始时间不能小于年初！');
                }
                if( $postData['private_end_time'] > $end_time ){
                    throw new \Exception('民办结束时间不能大于年末！');
                }

                $res = (new modle())->editData($postData);
                if($res['code'] == 0){
                    throw new \Exception($res['msg']);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
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
     * 上传图片
     * @return Json
     */
    public function upload()
    {
        if ($this->request->isPost()) {
            try {
                @ini_set("memory_limit","512M");
                // 获取上传的文件，如果有上传错误，会抛出异常
                $file = $this->request->file('file');
                // 如果上传的文件为null，手动抛出一个异常，统一处理异常
                if (null === $file) {
                    // 异常代码使用UPLOAD_ERR_NO_FILE常量，方便需要进一步处理异常时使用
                    throw new \Exception('请上传文件');
                }
                // $info = $file->getInfo();
                $info['tmp_name'] = $file->getPathname();
                $info['name'] = $file->getOriginalName();
                $info['size'] = $file->getSize();
                $extend_name = strtolower(substr(strrchr($info['name'], '.'), 1));

                $image_validate =   validate(['file'=>[
                    'fileSize' => 15 * 1024 * 1024,
                    'fileExt' => Cache::get('file_server_pic'),
                    //'fileMime' => 'image/jpeg,image/png,image/gif', //这个一定要加上，很重要我认为！
                ]])->check(['file' => $file]);

                if(!$image_validate){
                    throw new \Exception('文件格式错误');
                }

                // 返回图片的宽度
                $image = \think\Image::open($info['tmp_name']);
                $width = $image->width();
                $height = $image->height();
                $originalPath = 'public' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
                $randName = md5(time().mt_rand(0,1000).$info['tmp_name']);
                $fileName = $randName.'.'.$extend_name;
                $small_name = $randName.'-s.'.$extend_name;
                $actualPath = str_replace("\\","/", env('root_path').$originalPath);
                $file_size = $info['size'];
                $max_size = Cache::get('file_max_size');
                if ($width > $max_size || $height > $max_size){
                    $image->thumb($max_size, $max_size)->save($originalPath.$fileName);
                    $file_size = filesize($originalPath.$fileName);
                }else{
                    $position = strrpos($actualPath,'/');
                    $path = substr($actualPath,0,$position);
                    if(!file_exists($path)){
                        mkdir($path,0777,true);
                        chmod($path, 0777);
                    }
                    if(!move_uploaded_file($info['tmp_name'],$actualPath. $fileName)){
                        throw new \Exception('上传文件失败');
                    };
                }
                $image->thumb(480, 480)->save($originalPath.$small_name);
                // 保存路径
                //Cache::get('file_server_path')
                $savePath =  DIRECTORY_SEPARATOR . Cache::get('file_server_path') . DIRECTORY_SEPARATOR. date('Y-m-d-H');
                $savePath = preg_replace('/\\\\/', '/', $savePath);
                $ftpConn = @ftp_connect(Cache::get('file_server_host'));
                if (!$ftpConn) {
                    throw new \Exception('文件服务器链接失败');
                }
                $ftpLogin = @ftp_login($ftpConn, Cache::get('file_server_user'), Cache::get('file_server_pass'));
                if (!$ftpLogin) {
                    throw new \Exception('文件服务器登录失败');
                }
                @ftp_pasv($ftpConn, true);
                $savePathArr = explode('/', $savePath);
                array_filter($savePathArr);
                $pathStatus = false;
                foreach ($savePathArr as $path) {
                    if ($path) {
                        $isChdir = false;
                        try {
                            $isChdir = @ftp_chdir($ftpConn, $path);
                        } catch (\Exception $exception) {

                        }
                        if ($isChdir) {
                            $pathStatus = true;
                        } else {
                            $pathStatus = @ftp_mkdir($ftpConn, $path);
                            $isChdir = @ftp_chdir($ftpConn, $path);
                            if(!$isChdir){
                                throw new \Exception('文件服务器路径生成失败');
                            }
                        }
                    }
                }
                if ($pathStatus) {
                    $isFile = @ftp_put($ftpConn, $fileName, $actualPath. $fileName, FTP_BINARY);
                    $isSmallFile = @ftp_put($ftpConn, $small_name, $actualPath.$small_name, FTP_BINARY);
                    if (!$isFile || !$isSmallFile) {
                        throw new \Exception('文件上传错误请重新上传');
                    }
                } else {
                    throw new \Exception('文件服务器路径错误');
                }
                @ftp_close($ftpConn);
                unlink($actualPath.$fileName);
                unlink($actualPath.$small_name);
                $full_url = preg_replace('/\\\\/', '/', DIRECTORY_SEPARATOR ."uploads" . $savePath . DIRECTORY_SEPARATOR . $fileName);
                $small_url = preg_replace('/\\\\/', '/', DIRECTORY_SEPARATOR ."uploads" . $savePath . DIRECTORY_SEPARATOR . $small_name);
                $file_id = Db::name('upload_files')
                    ->insertGetId([
                        'manage_id' => $this->userInfo['manage_id'],
                        'file_path' => $full_url,
                        'file_small' => $small_url,
                        'file_type' => $extend_name,
                        'file_size' => $file_size,
                        'source' => 'edu_school',
                        'create_ip' => $this->request->ip()
                    ]);
                //$data['file_id'] =$file_id;
                //$data['file_path'] = $small_url;
                $res = [
                    'code' => 1,
                    'url' => $full_url,
                    'msg' => '图片上传成功'
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