<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/3/1
 * Time: 15:22
 */
namespace aliyunAi;

use OSS\OssClient;
use OSS\Core\OssException;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use think\Db;
use think\Image;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use subTable\SysTablePartition;

class AliyunSdk
{

    protected $access_keyid;
    protected $accesskey_secret;
    protected $endpoint;
    protected $bucket;

    public function __construct(){
        $this->access_keyid = Cache::get('aliyun_access_key_id');
        $this->accesskey_secret = Cache::get('aliyun_access_key_secret');
        $this->endpoint = Cache::get('aliyun_oss_endpoint');
        $this->bucket = Cache::get('aliyun_oss_bucket_name');
    }
    /**
     * 使用AK&SK初始化账号Client
     * @return Ocr Client
     */
    public static function createClient($access_keyid,$accesskey_secret){
        AlibabaCloud::accessKeyClient($access_keyid, $accesskey_secret)
            ->regionId('cn-shanghai')
            ->asDefaultClient();
    }
    /**
     * 上传文件到OSS
     * @return
     */
    private function uploadOss($source,$user_id,$ip,$size) {
        try {
            set_time_limit(0);
            if(!isset($_FILES['file'])){
                throw new \Exception('未设置文件域：file');
            }
            $file = $_FILES['file'];
            if($file['error']){
                throw new \Exception('文件上传失败，code:'.$file['error']);
            }
            if ($file['size'] > 8 * 1024 * 1024) {
                throw new \Exception('文件大小不能超过8M');
            }
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new \Exception('未找到上传文件');
            }
            //限制上传文件类型
            $extend_name = strtolower(substr(strrchr($file['name'], '.'), 1));
            $extend_allow = ['jpg','jpeg','png','gif','bmp'];
            if (!in_array($extend_name, $extend_allow)) {
                throw new \Exception('上传文件必须为图片文件');
            }
            $image = Image::open($file['tmp_name']);
            // 返回图片的宽度
            $width = $image->width();
            $height = $image->height();
            $originalPath = 'public' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR;
            $compressPath = $originalPath.date("Ymd").DIRECTORY_SEPARATOR;
            $randName = md5(time().mt_rand(0,1000));
            $fileName = $randName.'.'.$extend_name;
            $small_name = $randName.'-s.'.$extend_name;
            $urlPath = str_replace("\\","/", DIRECTORY_SEPARATOR.$compressPath . str_replace('\\', '/', $fileName));
            $smallPath = str_replace("\\","/", DIRECTORY_SEPARATOR.$compressPath . str_replace('\\', '/', $small_name));
            $actualPath = str_replace("\\","/", env('root_path').$compressPath . str_replace('\\', '/', $fileName));
            $file_size = $file['size'];
            if ($width > $size || $height > $size){
                $image->thumb($size, $size)->save($compressPath.$fileName);
                $file_size = filesize($compressPath.$fileName);
            }else{
                $position = strrpos($actualPath,'/');
                $path = substr($actualPath,0,$position);
                if(!file_exists($path)){
                    mkdir($path,0777,true);
                    chmod($path, 0777);
                }
                if(!move_uploaded_file($file['tmp_name'],$actualPath)){
                    throw new \Exception('上传文件失败请重试');
                };
            }
            $image->thumb(480, 480)->save($compressPath.$small_name);
            //  上传到相应的分区表
            $guardianUploadTable = (new SysTablePartition())->getTablePartition('reg_guardian_upload', date('Y-m-d'));
            if($guardianUploadTable ['code'] == 0) {
                throw new \Exception($guardianUploadTable ['msg']);
            }
            $file_id = Db::table($guardianUploadTable['table_name'])
                ->insertGetId([
                    'guardian_id' => $user_id,
                    'file_path' => str_replace("\\","/",$urlPath),
                    'file_small' => str_replace("\\","/",$smallPath),
                    'file_type' => $extend_name,
                    'file_size' => $file_size,
                    'source' => $source,
                    'create_time' => time(),
                    'create_ip' => $ip
                ]);
            $ossClient = new OssClient($this->access_keyid,$this->accesskey_secret, $this->endpoint);
            $ossClient->uploadFile($this->bucket,$fileName, $actualPath);
            $replacedOSSEndpoint = str_replace('https://', '', $this->endpoint);
            $imageURL = "https://{$this->bucket}.{$replacedOSSEndpoint}/{$fileName}";
            $res = [
                'code' => 1,
                'data' => [
                    'file_id' => $file_id,
                    'file_table' => $guardianUploadTable['table_name'],
                    'file_small' => $smallPath,
                    'imageURL' => $imageURL,
                ]
            ];
        } catch (OssException  $e) {
            $res = [
                'code' => 0,
                'msg' => $e->getMessage() ?: Lang::get('system_error')
            ];
        }catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return $res;
    }
    /**
     * 门头照招牌识别
     * @param string[] $url
     * @return
     */
    public function getPoiName($userID,$ip){
        try {
            AliyunSdk::createClient($this->access_keyid,$this->accesskey_secret);
            $source = 'poiname';
            $getFile = $this->uploadOss($source,$userID,$ip,3840);
            if(!$getFile['code']){
                throw new \Exception('文件上传失败，原因：'.$getFile['msg']);
            }
            $result = AlibabaCloud::ocr()
                ->v20191230()
                ->recognizePoiName()
                ->withImageURL($getFile['data']['imageURL'])
                ->request()->toArray();
            $brand = '';
            if(array_key_exists('Data',$result)){
                if(array_key_exists('Summary',$result['Data'])){
                    if(array_key_exists('Brand',$result['Data']['Summary'])) {
                        $brand = $result['Data']['Summary']['Brand'];
                    }
                }
            }else{
                throw new \Exception('未能识别招牌内容');
            }
            $data = [
                'brand' => $brand,
                'file_table' => $getFile['data']['file_table'],
                'second_file_id' => $getFile['data']['file_id'],
                'file_path' => $getFile['data']['file_small'],
            ];
            $res = [
                'code' => 1,
                'data' => $data
            ];
        } catch (ClientException $e) {
            $res = [
                'code' => 0,
                'msg' => $e->getErrorMessage() ?: Lang::get('system_error')
            ];
        } catch (ServerException $e) {
            $res = [
                'code' => 0,
                'msg' => $e->getErrorMessage() ?: Lang::get('system_error')
            ];
        }catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return $res;
    }
    /**
     * 营业执照识别
     * @param string[] $url
     * @return
     */
    public function getBusiness($userID,$ip){
        try {
            AliyunSdk::createClient($this->access_keyid,$this->accesskey_secret);
            $source = 'business';
            $getFile = $this->uploadOss($source,$userID,$ip,3840);
            if(!$getFile['code']){
                throw new \Exception('文件上传失败，原因：'.$getFile['msg']);
            }
            $result = AlibabaCloud::rpc()
                ->product('ocr')
                ->version('2019-12-30')
                ->action('RecognizeBusinessLicense')
                ->method('POST')
                ->host('ocr.cn-shanghai.aliyuncs.com')
                ->options([
                    'query' => [
                        'ImageURL' => $getFile['data']['imageURL'],
                    ],
                ])
                ->request()->toArray();
            $brand = '';
            if(array_key_exists('Data',$result)){
                if(array_key_exists('Summary',$result['Data'])){
                    if(array_key_exists('Brand',$result['Data']['Summary'])) {
                        $brand = $result['Data']['Summary']['Brand'];
                    }
                }
            }else{
                throw new \Exception('未能识别招牌内容');
            }
            $data = [
                'brand' => $brand,
                'file_table' => $getFile['data']['file_table'],
                'second_file_id' => $getFile['data']['file_id'],
                'file_path' => $getFile['data']['file_small'],
            ];
            $res = [
                'code' => 1,
                'data' => $data
            ];
        } catch (ClientException $e) {
            $res = [
                'code' => 0,
                'msg' => $e->getErrorMessage() ?: Lang::get('system_error')
            ];
        } catch (ServerException $e) {
            $res = [
                'code' => 0,
                'msg' => $e->getErrorMessage() ?: Lang::get('system_error')
            ];
        }catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return $res;
    }
}

