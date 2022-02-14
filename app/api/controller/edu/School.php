<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/28
 * Time: 16:32
 */
namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\Schools;
use think\facade\Filesystem;
use think\facade\Lang;
use think\response\Json;
use app\common\model\Schools as model;
use app\common\validate\schools\Schools as validate;
use Overtrue\Pinyin\Pinyin;
use think\facade\Db;
use think\facade\Cache;
use dictionary\FilterData;
use PhpOffice\PhpSpreadsheet\IOFactory;

class School extends Education
{

    /**
     * 获取学校信息
     * @param id 学校ID
     * @return Json
     */
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                //  验证器验证请求的数据是否合法
                $school_id = $this->userInfo['school_id'];
                if(!$school_id){
                    throw new \Exception('学校账号的学校ID错误');
                }
                $data = (new model())->where('id', $school_id)->find();
                $list = Db::name('sys_school_cost')
                    ->where('school_id', $school_id)->select()->toArray();
                $school_cost_list = [];
                foreach ($list as $item){
                    $school_cost_list[$item['cost_id']] = $item['cost_total'];
                }

                //费用列表
                $feeData = Db::name('SysCostCategory')->where('deleted', 0)
                    ->field(['id','cost_code', 'cost_name'])->select()->toArray();
                $cost_list = [];
                foreach ($feeData as $item){
                    $cost_list[] = [
                        'id' => $item['id'],
                        'cost_name' => $item['cost_name'],
                        'cost_total' => isset($school_cost_list[$item['id']]) ? $school_cost_list[$item['id']] : 0,
                    ];
                }
                $res = [
                    'code' => 1,
                    'data' => $data,
                    'cost_list' => $cost_list,
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
     * 编辑
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $school_id = $this->userInfo['school_id'];
                if(!$school_id){
                    throw new \Exception('学校账号的学校ID错误');
                }
                $school = (new model())->where('id', $school_id)->find();
                $data = $this->request->only([
                    'id' => $school_id,
                    'school_name',
                    'school_type',
                    'school_attr',
                    'school_code',
                    'school_area',
                    'address',
                    'telephone',
                    'preview_img',
                    'content',
                    'hash'
                ]);
                $data['region_id'] = $school['region_id'];
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
                if ($data['preview_img'] == '') {
                    throw new \Exception('请上传学校预览图片');
                }
                /*if ($data['school_attr'] == 1) {
                    if($data['school_area'] == '') {
                        throw new \Exception('公办学校请输入招生片区');
                    }
                }*/

                $data['finished'] = 1;//校情已完成
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['school_name']);
                unset($data['hash']);

                //Db::name('sys_school')->where('id',$data['id'])->update($data);
                $data['content'] = $this->clearHtml($data['content'], '');
                $result = (new Schools())->editData($data);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }

                if (Cache::get('update_cache')) {
                    $schoolsList = Db::name('SysSchool')->where('disabled',0)
                        ->where('deleted',0)->select()->toArray();
                    Cache::set('school', $schoolsList);
                }

                //校情统计
                $result = $this->getFinishedStatistics($school_id);
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