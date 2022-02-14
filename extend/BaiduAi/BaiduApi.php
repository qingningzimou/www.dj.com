<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/3/2
 * Time: 16:07
 */
namespace baiduAi;

use think\Db;
use think\Image;
use think\facade\Cache;
use think\facade\Lang;
use think\response\Json;
use Overtrue\Pinyin\Pinyin;
use subTable\SysTablePartition;

class BaiduApi
{
    protected $baidu_token;
    protected $template_private;
    protected $template_school_old;
    protected $template_school_new;
    protected $template_utility;

    public function __construct(){
        $this->baidu_token = Cache::get('baidu_token');
        $this->template_private = Cache::get('template_private');
        $this->template_school_old = Cache::get('template_school_old');
        $this->template_school_new = Cache::get('template_school_new');
        $this->template_utility = Cache::get('template_utility');
    }
    /**
     * 上传文件
     * @return
     */
    private function uploadFile($source,$user_id,$ip,$size) {
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
                    throw new \Exception('上传文件失败');
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
            $res = [
                'code' => 1,
                'data' => [
                    'file_id' => $file_id,
                    'file_table' => $guardianUploadTable['table_name'],
                    'file_small' => $smallPath,
                    'file_path' => $urlPath,
                ]
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
     * 营业执照识别
     * @param string[] $url
     * @return
     */
    public function getBusiness($userID,$ip,$telephone){
        try {
            $apiUrl = 'https://aip.baidubce.com/rest/2.0/ocr/v1/business_license?access_token='. $this->baidu_token;
            $source = 'business';
            $getFile = $this->uploadFile($source,$userID,$ip,3840);
            if(!$getFile['code']){
                throw new \Exception('文件上传失败，原因：'.$getFile['msg']);
            }
            $image = file_get_contents(env('root_path').$getFile['data']['file_path']);
            $baseImg = base64_encode($image);
            $bodys = array(
                'image' => $baseImg
            );
            $getData = httpPost($apiUrl, $bodys);
            if (!$getData) {
                throw new \Exception('人工智能接口连接失败');
            }
            $getData = json_decode($getData, true);
            if(!array_key_exists('words_result',$getData)){
                throw new \Exception('人工智能返回数据错误');
            }
            $keyword = [
                '社会信用代码' => 'org_code',
                '类型' => 'organizers_attributes',
                '组成形式' => 'formalize',
                '经营范围' => 'business_scope',
                '法人' => 'legal_person',
                '注册资本' => 'capital_chars',
                '单位名称' => 'institute_name',
                '有效期' => 'validity_period',
                '成立日期' => 'establish_date',
                '地址' => 'reg_address',
                '登记机关' => 'registration_departments'
            ];
            $data = [];
            foreach ($keyword as $k => $v) {
                if (isset($getData['words_result'][$k])) {
                    $data[$v] = $getData['words_result'][$k]['words'];
                }else{
                    $data[$v] = '';
                }
            }
            $data['setup_time'] = 0;
            if($data['establish_date']){
                $setup_time = str_replace(['年', '月'], '-', $data['establish_date']);
                $setup_time = str_replace(['日'], '', $setup_time);
                if(strtotime($setup_time)){
                    $data['setup_time'] = strtotime($setup_time);
                }
            }
            $data['registered_capital'] = 0;
            if($data['capital_chars']){
                if(preg_match('/\d+(\.\d+)?/',$data['capital_chars'],$arr)){
                    $data['registered_capital'] = $arr[0];
                }
                if(!$data['registered_capital']){
                    $data['capital_chars'] = str_ireplace(['人民币', '万元', '万圆整'], '', $data['capital_chars']);
                    $data['registered_capital'] = checkNatInt($data['capital_chars']);
                }
            }
            if($data['registered_capital'] >= 10000){
                $data['registered_capital'] = $data['registered_capital'] / 10000;
            }
            $data['attributes'] = 0;
            if($data['organizers_attributes'] && $data['organizers_attributes'] != '个体工商户'){
                $data['attributes'] = 1;
            }
            if($data['institute_name']){
                $institute_fast_id = Db::name('InstituteFast')
                    ->where([
                        'guardian_id' => $userID,
                        'deleted' => 0
                    ])
                    ->value('id');
                $dictionary = Cache::get('dictionary');
                $data['registration_department'] = 0;
                $departmentsMain = filter_value_one($dictionary, 'field_name', '登记部门');
                if (count($departmentsMain) > 0){
                    $departmentsSub = array_values(filter_by_value($dictionary, 'parent_id', $departmentsMain['id']));
                    $departmentData = filter_value_one($departmentsSub, 'field_name', '市场监督部门');
                    if (count($departmentData) > 0){
                        $data['registration_department'] = $departmentData['field_value'];
                    }
                }
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['institute_name']);
                if(empty($institute_fast_id)){
                    $institute_fast_id = Db::name('InstituteFast')
                        ->insertGetId([
                            'guardian_id' => $userID,
                            'org_code' => $data['org_code'],
                            'registration_department' => $data['registration_department'],
                            'institute_name' => $data['institute_name'],
                            'simple_code' => $data['simple_code'],
                            'formalize' => $data['formalize'],
                            'business_scope' => $data['business_scope'],
                            'setup_time' => $data['setup_time'],
                            'validity_period' => $data['validity_period'],
                            'org_file_id' => $getFile['data']['file_id'],
                            'org_file_table' => $getFile['data']['file_table'],
                            'reg_address' => $data['reg_address'],
                            'registered_capital' => $data['registered_capital'],
                            'contacts' => $data['legal_person'],
                            'telephone' => $telephone,
                            'create_time' => time(),
                            'organizers_attributes' => $data['organizers_attributes'],
                            'attributes' => $data['attributes'],
                        ]);
                }else{
                    Db::name('InstituteFast')
                        ->where('id',$institute_fast_id)
                        ->update([
                            'org_code' => $data['org_code'],
                            'registration_department' => $data['registration_department'],
                            'institute_name' => $data['institute_name'],
                            'simple_code' => $data['simple_code'],
                            'formalize' => $data['formalize'],
                            'business_scope' => $data['business_scope'],
                            'setup_time' => $data['setup_time'],
                            'validity_period' => $data['validity_period'],
                            'org_file_id' => $getFile['data']['file_id'],
                            'org_file_table' => $getFile['data']['file_table'],
                            'reg_address' => $data['reg_address'],
                            'registered_capital' => $data['registered_capital'],
                            'contacts' => $data['legal_person'],
                            'organizers_attributes' => $data['organizers_attributes'],
                            'attributes' => $data['attributes'],
                            'org_confirm' => 0,
                        ]);
                }
            }else{
                throw new \Exception('营业执照识别失败');
            }
            $res = [
                'code' => 1,
                'data' => [
                    'institute_name' => $data['institute_name'],
                    'file_table' => $getFile['data']['file_table'],
                    'file_id' => $getFile['data']['file_id'],
                    'file_path' => $getFile['data']['file_small'],
                ]
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
     * 非企法人证识别
     * @param string[] $url
     * @return
     */
    public function getNonEnterprise($userID,$ip,$telephone){
        try {
            $apiUrl = 'https://aip.baidubce.com/rest/2.0/solution/v1/iocr/recognise?access_token='. $this->baidu_token;
            $source = 'non_enterprise';
            $getFile = $this->uploadFile($source,$userID,$ip,3840);
            if(!$getFile['code']){
                throw new \Exception('文件上传失败，原因：'.$getFile['msg']);
            }
            $image = file_get_contents(env('root_path').$getFile['data']['file_path']);
            $baseImg = base64_encode($image);
            $bodys = array(
                'image' => $baseImg,
                'templateSign' => $this->template_private
            );
            $getData = httpPost($apiUrl, $bodys);
            if (!$getData) {
                throw new \Exception('人工智能接口连接失败');
            }
            $getData = json_decode($getData, true);
            if(!array_key_exists('error_code',$getData)){
                throw new \Exception('人工智能返回数据错误');
            }
            if($getData['error_code']){
                throw new \Exception('人工智能错误:'.$getData['error_msg']);
            }
            if(!array_key_exists('data',$getData)){
                throw new \Exception('人工智能返回数据错误');
            }
            if(!array_key_exists('ret',$getData['data'])){
                throw new \Exception('人工智能返回数据错误');
            }
            $keyword = [
                '法定代表人' => 'legal_person',
                '住所' => 'reg_address',
                '名称' => 'institute_name',
                '业务主管单位' => 'registration_departments',
                '统一社会信用代码' => 'org_code',
                '开办资金' => 'capital_chars',
                '业务范围' => 'business_scope'
            ];
            $data = [];
            foreach ($keyword as $k => $v) {
                $data[$v] = '';
                foreach($getData['data']['ret'] as $kr => $vr){
                    if ($vr['word_name'] == $k) {
                        $data[$v] = $vr['word'];
                    }
                }
            }
            $data['attributes'] = 1;
            $data['institute_type'] = 1;
            if($data['institute_name']){
                $institute_fast_id = Db::name('InstituteFast')
                    ->where([
                        'guardian_id' => $userID,
                        'deleted' => 0
                    ])
                    ->value('id');
                $dictionary = Cache::get('dictionary');
                $data['registration_department'] = 0;
                $departmentsMain = filter_value_one($dictionary, 'field_name', '登记部门');
                if (count($departmentsMain) > 0){
                    $departmentsSub = array_values(filter_by_value($dictionary, 'parent_id', $departmentsMain['id']));
                    $departmentData = filter_value_one($departmentsSub, 'field_name', '民政部门');
                    if (count($departmentData) > 0){
                        $data['registration_department'] = $departmentData['field_value'];
                    }
                }
                $data['registered_capital'] = 0;
                if($data['capital_chars']){
                    if(preg_match('/\d+(\.\d+)?/',$data['capital_chars'],$arr)){
                        $data['registered_capital'] = $arr[0];
                    }
                    if(!$data['registered_capital']){
                        $data['capital_chars'] = str_ireplace(['人民币', '万元', '万圆整'], '', $data['capital_chars']);
                        $data['registered_capital'] = checkNatInt($data['capital_chars']);
                    }
                }
                if($data['registered_capital'] >= 10000){
                    $data['registered_capital'] = $data['registered_capital'] / 10000;
                }
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['institute_name']);
                if(empty($institute_fast_id)){
                    $institute_fast_id = Db::name('InstituteFast')
                        ->insertGetId([
                            'guardian_id' => $userID,
                            'org_code' => $data['org_code'],
                            'registration_department' => $data['registration_department'],
                            'institute_name' => $data['institute_name'],
                            'simple_code' => $data['simple_code'],
                            'business_scope' => $data['business_scope'],
                            'org_file_id' => $getFile['data']['file_id'],
                            'org_file_table' => $getFile['data']['file_table'],
                            'reg_address' => $data['reg_address'],
                            'registered_capital' => $data['registered_capital'],
                            'contacts' => $data['legal_person'],
                            'telephone' => $telephone,
                            'create_time' => time(),
                            'attributes' => $data['attributes'],
                            'institute_type' => $data['institute_type'],
                        ]);
                }else{
                    Db::name('InstituteFast')
                        ->where('id',$institute_fast_id)
                        ->update([
                            'org_code' => $data['org_code'],
                            'registration_department' => $data['registration_department'],
                            'institute_name' => $data['institute_name'],
                            'simple_code' => $data['simple_code'],
                            'business_scope' => $data['business_scope'],
                            'org_file_id' => $getFile['data']['file_id'],
                            'org_file_table' => $getFile['data']['file_table'],
                            'reg_address' => $data['reg_address'],
                            'registered_capital' => $data['registered_capital'],
                            'contacts' => $data['legal_person'],
                            'attributes' => $data['attributes'],
                            'institute_type' => $data['institute_type'],
                            'org_confirm' => 0,
                        ]);
                }
            }else{
                throw new \Exception('民办非企法人证识别失败');
            }
            $res = [
                'code' => 1,
                'data' => [
                    'institute_name' => $data['institute_name'],
                    'file_table' => $getFile['data']['file_table'],
                    'file_id' => $getFile['data']['file_id'],
                    'file_path' => $getFile['data']['file_small'],
                ]
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
     * 事业单位法人证识别
     * @param string[] $url
     * @return
     */
    public function getUtility($userID,$ip,$telephone){
        try {
            $apiUrl = 'https://aip.baidubce.com/rest/2.0/solution/v1/iocr/recognise?access_token='. $this->baidu_token;
            $source = 'non_enterprise';
            $getFile = $this->uploadFile($source,$userID,$ip,3840);
            if(!$getFile['code']){
                throw new \Exception('文件上传失败，原因：'.$getFile['msg']);
            }
            $image = file_get_contents(env('root_path').$getFile['data']['file_path']);
            $baseImg = base64_encode($image);
            $bodys = array(
                'image' => $baseImg,
                'templateSign' => $this->template_utility
            );
            $getData = httpPost($apiUrl, $bodys);
            if (!$getData) {
                throw new \Exception('人工智能接口连接失败');
            }
            $getData = json_decode($getData, true);
            if(!array_key_exists('error_code',$getData)){
                throw new \Exception('人工智能返回数据错误');
            }
            if($getData['error_code']){
                throw new \Exception('人工智能错误:'.$getData['error_msg']);
            }
            if(!array_key_exists('data',$getData)){
                throw new \Exception('人工智能返回数据错误');
            }
            if(!array_key_exists('ret',$getData['data'])){
                throw new \Exception('人工智能返回数据错误');
            }
            $keyword = [
                '法定代表人' => 'legal_person',
                '经费来源' => 'source',
                '住所' => 'reg_address',
                '名称' => 'institute_name',
                '举办单位' => 'organizer',
                '社会统一信用代码' => 'org_code',
                '开办资金' => 'capital_chars',
                '业务范围' => 'business_scope',
                '有效期' => 'validity_period'
            ];
            $data = [];
            foreach ($keyword as $k => $v) {
                $data[$v] = '';
                foreach($getData['data']['ret'] as $kr => $vr){
                    if ($vr['word_name'] == $k) {
                        $data[$v] = $vr['word'];
                    }
                }
            }
            $data['attributes'] = 1;
            $data['institute_type'] = 1;
            if($data['institute_name']){
                $institute_fast_id = Db::name('InstituteFast')
                    ->where([
                        'guardian_id' => $userID,
                        'deleted' => 0
                    ])
                    ->value('id');
                $dictionary = Cache::get('dictionary');
                $data['registration_department'] = 0;
                $departmentsMain = filter_value_one($dictionary, 'field_name', '登记部门');
                if (count($departmentsMain) > 0){
                    $departmentsSub = array_values(filter_by_value($dictionary, 'parent_id', $departmentsMain['id']));
                    $departmentData = filter_value_one($departmentsSub, 'field_name', '民政部门');
                    if (count($departmentData) > 0){
                        $data['registration_department'] = $departmentData['field_value'];
                    }
                }
                $data['registered_capital'] = 0;
                if($data['capital_chars']){
                    if(preg_match('/\d+(\.\d+)?/',$data['capital_chars'],$arr)){
                        $data['registered_capital'] = $arr[0];
                    }
                    if(!$data['registered_capital']){
                        $data['capital_chars'] = str_ireplace(['人民币', '万元', '万圆整'], '', $data['capital_chars']);
                        $data['registered_capital'] = checkNatInt($data['capital_chars']);
                    }
                }
                $pinyin = new Pinyin();
                $data['simple_code'] = $pinyin->abbr($data['institute_name']);
                if(empty($institute_fast_id)){
                    Db::name('InstituteFast')
                        ->insertGetId([
                            'guardian_id' => $userID,
                            'org_code' => $data['org_code'],
                            'registration_department' => $data['registration_department'],
                            'institute_name' => $data['institute_name'],
                            'simple_code' => $data['simple_code'],
                            'business_scope' => $data['business_scope'],
                            'org_file_id' => $getFile['data']['file_id'],
                            'org_file_table' => $getFile['data']['file_table'],
                            'reg_address' => $data['reg_address'],
                            'registered_capital' => $data['registered_capital'],
                            'contacts' => $data['legal_person'],
                            'validity_period' => $data['validity_period'],
                            'organizer' => $data['organizer'],
                            'attributes' => $data['attributes'],
                            'institute_type' => $data['institute_type'],
                            'telephone' => $telephone,
                            'create_time' => time(),
                        ]);
                }else{
                    Db::name('InstituteFast')
                        ->where('id',$institute_fast_id)
                        ->update([
                            'org_code' => $data['org_code'],
                            'registration_department' => $data['registration_department'],
                            'institute_name' => $data['institute_name'],
                            'simple_code' => $data['simple_code'],
                            'business_scope' => $data['business_scope'],
                            'org_file_id' => $getFile['data']['file_id'],
                            'org_file_table' => $getFile['data']['file_table'],
                            'reg_address' => $data['reg_address'],
                            'registered_capital' => $data['registered_capital'],
                            'contacts' => $data['legal_person'],
                            'validity_period' => $data['validity_period'],
                            'organizer' => $data['organizer'],
                            'attributes' => $data['attributes'],
                            'institute_type' => $data['institute_type'],
                            'org_confirm' => 0,
                        ]);
                }
            }else{
                throw new \Exception('事业单位法人证识别失败');
            }
            $res = [
                'code' => 1,
                'data' => [
                    'institute_name' => $data['institute_name'],
                    'file_table' => $getFile['data']['file_table'],
                    'file_id' => $getFile['data']['file_id'],
                    'file_path' => $getFile['data']['file_small'],
                ]
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
     * 办学许可证识别
     * @param string[] $url
     * @return
     */
    public function getSchoolLicense($userID,$ip,$telephone){
        try {
            $apiUrl = 'https://aip.baidubce.com/rest/2.0/solution/v1/iocr/recognise?access_token='. $this->baidu_token;
            $source = 'school_license';
            $getFile = $this->uploadFile($source,$userID,$ip,3840);
            if(!$getFile['code']){
                throw new \Exception('文件上传失败，原因：'.$getFile['msg']);
            }
            $image = file_get_contents(env('root_path').$getFile['data']['file_path']);
            $baseImg = base64_encode($image);
            $bodys = array(
                'image' => $baseImg,
                'templateSign' => $this->template_school_old
            );
            $getData = httpPost($apiUrl, $bodys);
            if (!$getData) {
                throw new \Exception('人工智能接口连接失败');
            }
            $getData = json_decode($getData, true);
            if(!array_key_exists('error_code',$getData)){
                throw new \Exception('人工智能返回数据错误');
            }
            if($getData['error_code']){
                throw new \Exception('人工智能错误:'.$getData['error_msg']);
            }
            if(!array_key_exists('data',$getData)){
                throw new \Exception('人工智能返回数据错误');
            }
            if(!array_key_exists('ret',$getData['data'])){
                throw new \Exception('人工智能返回数据错误');
            }
            $keyword = [
                '校长' => 'school_master',
                '办学内容' => 'school_scope',
                '教民' => 'school_licence',
                '颁发时间' => 'establish_date',
                '主管部门' => 'school_department',
                '名称' => 'school_name',
                '地址' => 'school_address',
                '学校类型' => 'school_type',
                '有效期限' => 'school_term'
            ];
            $data = [];
            foreach ($keyword as $k => $v) {
                $data[$v] = '';
                foreach($getData['data']['ret'] as $kr => $vr){
                    if ($vr['word_name'] == $k) {
                        $data[$v] = $vr['word'];
                    }
                }
            }
            $data['attributes'] = 1;
            if($data['school_name']){
                $institute_fast_id = Db::name('InstituteFast')
                    ->where([
                        'guardian_id' => $userID,
                        'deleted' => 0
                    ])
                    ->value('id');
                $dictionary = Cache::get('dictionary');
                $data['issuing_authority'] = 0;
                $authorityMain = filter_value_one($dictionary, 'field_name', '发证机关');
                if (count($authorityMain) > 0){
                    $authoritySub = array_values(filter_by_value($dictionary, 'parent_id', $authorityMain['id']));
                    $authorityData = filter_value_one($authoritySub, 'field_name', '教育行政部门');
                    if (count($authorityData) > 0){
                        $data['issuing_authority'] = $authorityData['field_value'];
                    }
                }
                if(empty($institute_fast_id)){
                    $institute_fast_id = Db::name('InstituteFast')
                        ->insertGetId([
                            'guardian_id' => $userID,
                            'school_scope' => $data['school_scope'],
                            'school_licence' => $data['school_licence'],
                            'school_department' => $data['school_department'],
                            'school_name' => $data['school_name'],
                            'school_address' => $data['school_address'],
                            'school_master' => $data['school_master'],
                            'school_file_id' => $getFile['data']['file_id'],
                            'school_file_table' => $getFile['data']['file_table'],
                            'issuing_authority' => $data['issuing_authority'],
                            'school_type' => $data['school_type'],
                            'school_term' => $data['school_term'],
                            'telephone' => $telephone,
                            'create_time' => time(),
                            'attributes' => $data['attributes'],
                        ]);
                }else{
                    Db::name('InstituteFast')
                        ->where('id',$institute_fast_id)
                        ->update([
                            'school_scope' => $data['school_scope'],
                            'school_licence' => $data['school_licence'],
                            'school_department' => $data['school_department'],
                            'school_name' => $data['school_name'],
                            'school_address' => $data['school_address'],
                            'school_master' => $data['school_master'],
                            'school_file_id' => $getFile['data']['file_id'],
                            'school_file_table' => $getFile['data']['file_table'],
                            'issuing_authority' => $data['issuing_authority'],
                            'school_type' => $data['school_type'],
                            'school_term' => $data['school_term'],
                            'attributes' => $data['attributes'],
                        ]);
                }
            }else{
                throw new \Exception('办学许可证识别失败');
            }
            $res = [
                'code' => 1,
                'data' => [
                    'school_name' => $data['school_name'],
                    'file_table' => $getFile['data']['file_table'],
                    'file_id' => $getFile['data']['file_id'],
                    'file_path' => $getFile['data']['file_small'],
                ]
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
     * 身份证识别
     * @param string[] $url
     * @return
     */
    public function getIdcard($userID,$ip,$telephone){
        try {
            $apiUrl = 'https://aip.baidubce.com/rest/2.0/ocr/v1/idcard?access_token='. $this->baidu_token;
            $source = 'id_card';
            $getFile = $this->uploadFile($source,$userID,$ip,1920);
            if(!$getFile['code']){
                throw new \Exception('文件上传失败，原因：'.$getFile['msg']);
            }
            $image = file_get_contents(env('root_path').$getFile['data']['file_path']);
            $baseImg = base64_encode($image);
            $bodys = array(
                'image' => $baseImg,
                'id_card_side' => 'front'
            );
            $getData = httpPost($apiUrl, $bodys);
            if (!$getData) {
                throw new \Exception('人工智能接口连接失败');
            }
            $getData = json_decode($getData, true);
            if(!array_key_exists('words_result',$getData) || !array_key_exists('image_status',$getData)){
                throw new \Exception('人工智能返回数据错误');
            }
            $data = [];
            $data['idcard_name'] = '';
            $data['idcard_authority'] = '';
            if($getData['image_status'] == 'normal'){
                if(!array_key_exists('idcard_number_type',$getData)){
                    throw new \Exception('人工智能返回数据错误');
                }
                if($getData['idcard_number_type'] != 1){
                    throw new \Exception('身份证号码验证错误');
                }
                $keyword = [
                    '住址' => 'contact_address',
                    '公民身份号码' => 'idcard',
                    '出生' => 'birthday',
                    '姓名' => 'idcard_name',
                    '性别' => 'idcard_sex',
                    '民族' => 'idcard_nation',
                ];
                foreach ($keyword as $k => $v) {
                    if (isset($getData['words_result'][$k])) {
                        $data[$v] = $getData['words_result'][$k]['words'];
                    }else{
                        $data[$v] = '';
                    }
                }
                if($data['idcard']){
                    $institute_fast_id = Db::name('InstituteFast')
                        ->where([
                            'guardian_id' => $userID,
                            'deleted' => 0
                        ])
                        ->value('id');
                    if(empty($institute_fast_id)){
                        $institute_fast_id = Db::name('InstituteFast')
                            ->insertGetId([
                                'guardian_id' => $userID,
                                'idcard' => $data['idcard'],
                                'idcard_name' => $data['idcard_name'],
                                'idcard_sex' => $data['idcard_sex'],
                                'idcard_nation' => $data['idcard_nation'],
                                'contact_address' => $data['contact_address'],
                                'front_file_id' => $getFile['data']['file_id'],
                                'card_file_table' => $getFile['data']['file_table'],
                                'telephone' => $telephone,
                                'create_time' => time(),
                            ]);
                    }else{
                        Db::name('InstituteFast')
                            ->where('id',$institute_fast_id)
                            ->update([
                                'idcard' => $data['idcard'],
                                'idcard_name' => $data['idcard_name'],
                                'idcard_sex' => $data['idcard_sex'],
                                'idcard_nation' => $data['idcard_nation'],
                                'contact_address' => $data['contact_address'],
                                'front_file_id' => $getFile['data']['file_id'],
                                'card_file_table' => $getFile['data']['file_table'],
                            ]);
                    }
                }else{
                    throw new \Exception('身份证照片面识别失败');
                }
            }else if($getData['image_status'] == 'reversed_side'){
                $keyword = [
                    '失效日期' => 'idcard_term',
                    '签发机关' => 'idcard_authority',
                    '签发日期' => 'idcard_time',
                ];
                foreach ($keyword as $k => $v) {
                    if (isset($getData['words_result'][$k])) {
                        $data[$v] = $getData['words_result'][$k]['words'];
                    }else{
                        $data[$v] = '';
                    }
                }
                if($data['idcard_authority']){
                    $institute_fast_id = Db::name('InstituteFast')
                        ->where([
                            'guardian_id' => $userID,
                            'deleted' => 0
                        ])
                        ->value('id');
                    if(empty($institute_fast_id)){
                        Db::name('InstituteFast')
                            ->insertGetId([
                                'guardian_id' => $userID,
                                'idcard_term' => $data['idcard_term'],
                                'idcard_authority' => $data['idcard_authority'],
                                'idcard_time' => $data['idcard_time'],
                                'back_file_id' => $getFile['data']['file_id'],
                                'card_file_table' => $getFile['data']['file_table'],
                                'telephone' => $telephone,
                                'create_time' => time(),
                            ]);
                    }else{
                        Db::name('InstituteFast')
                            ->where('id',$institute_fast_id)
                            ->update([
                                'idcard_term' => $data['idcard_term'],
                                'idcard_authority' => $data['idcard_authority'],
                                'idcard_time' => $data['idcard_time'],
                                'back_file_id' => $getFile['data']['file_id'],
                                'card_file_table' => $getFile['data']['file_table'],
                            ]);
                    }
                }else{
                    throw new \Exception('身份证国徽面识别失败');
                }
            }else{
                throw new \Exception('未能正确识别身份证');
            }

            $res = [
                'code' => 1,
                'data' => [
                    'idcard_name' => $data['idcard_name'],
                    'idcard_authority' => $data['idcard_authority'],
                    'file_table' => $getFile['data']['file_table'],
                    'file_id' => $getFile['data']['file_id'],
                    'file_path' => $getFile['data']['file_small'],
                ]
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