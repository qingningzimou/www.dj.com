<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\common\middleware\ApplyMiddleware;
use app\mobile\model\user\House as model;
use app\mobile\model\user\Residence;
use app\mobile\model\user\Insurance;
use app\mobile\model\user\Company;
use app\mobile\model\user\ApplyTmp;
use think\facade\Cache;
use think\facade\Db;
use think\response\Json;
use app\mobile\validate\House as validate;
use comparison\Reckon;

class House extends MobileEducation
{
    protected $middleware = [
        ApplyMiddleware::class
    ];

    /**
     * 保存房产信息
     * @param int family_id                 监护人自增ID
     * @param int house_type                房产类型（1：产权房；2：租房；3：自建房；4：置换房；5：公租房；6：三代同堂）
     * @param string house_address                   填写的住址
     * @param int code_type                 证件类型（1：房产证；2：不动产证；3：网签合同；4：公租房）
     * @param string cert_code                      证件号码(后面数字)
     * @param string code                      证件号码
     * @param int is_main_private           是否主城区自建房
     * @param string attachment                附件
     * @param string attachment_replacement    置换协议
     * @param string attachment_demolition     拆迁总指挥部证明
     * @param string insurance_code            社保卡号
     * @param string attachment_insurance      社保附件
     * @param string residence_code            居住证号
     * @param string attachment_residence      居住证附件
     * @param string company_name              企业名称
     * @param string company_code              统一信用编号
     * @param string attachment_company        企业附件
     * @param string other_attachment        其他附件
     * @param string rental_start_time        租房开始时间
     * @param string Hash                      表单hash
     * @return Json
     */
    public function actSave(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'child_id',
                    'family_id',
                    'house_type',
                    'house_address',
                    'standard_id',
                    'rental_start_time',
                    'other_attachment',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'], $this->result['user_id']);
                if ($checkHash['code'] == 0) {
                    throw new \Exception($checkHash['msg']);
                }
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate($data, validate::class, 'save');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $child_id = $data['child_id'];
                unset($data['child_id']);
                //  根据房产信息的类型，接受字段
                $postData = [];
                switch ($data['house_type']) {
                    //  产权房
                    case 1:
                        $postData = $this->request->only([
                            'code_type',
                            'code',
                            'cert_code',
                            'attachment'
                        ]);
                        switch ($postData['code_type']) {
                            //  房产证
                            case 1:
                                $reg = '/^([0-9a-zA-Z]{1}[0-9\-\/]+)$/';
                                $postData['cert_code'] = strtoupper(trim($postData['cert_code']));
                                if (!preg_match($reg, trim($postData['cert_code']))) {
                                    throw new \Exception('房产证号码格式不正确');
                                }
                                if (strlen($postData['cert_code']) < 6 || strlen($postData['cert_code']) > 15) {
                                    throw new \Exception('房产证号码长度不正确');
                                }
                                if (mb_strlen($postData['code']) < 9 || mb_strlen($postData['code']) > 32) {
                                    throw new \Exception('房产证号填写不正确');
                                }
                                if (empty($postData['attachment'])) {
                                    throw new \Exception('请上传房产证照片');
                                }
                                if (strlen($postData['attachment']) > 1024) {
                                    throw new \Exception('房产证照片超出限制');
                                }
                                break;
                            //  不动产
                            case 2:
                                $reg = '/^[0-9\-]+$/';
                                if (!preg_match($reg, trim($postData['cert_code']))) {
                                    throw new \Exception('不动产权证号码格式不正确');
                                }
                                if (strlen($postData['cert_code']) < 5 || strlen($postData['cert_code']) > 15) {
                                    throw new \Exception('不动产权证号码长度不正确');
                                }
                                if (mb_strlen($postData['code']) < 21 || mb_strlen($postData['code']) > 48) {
                                    throw new \Exception('不动产权证号填写不正确');
                                }
                                if (empty($postData['attachment'])) {
                                    throw new \Exception('请上传不动产权证照片');
                                }
                                if (strlen($postData['attachment']) > 1024) {
                                    throw new \Exception('不动产权证照片超出限制');
                                }
                                break;
                            //  网签
                            case 3:
                                $postData['code'] = strtoupper(trim($postData['code']));
                                $reg = '/^[A-Za-z0-9\-]+$/';
                                if (!preg_match($reg, $postData['code'])) {
                                    throw new \Exception('网签合同编号格式不正确');
                                }
                                if (strlen($postData['code']) < 6 || strlen($postData['code']) > 18) {
                                    throw new \Exception('网签合同编号填写不正确');
                                }
                                if (empty($postData['attachment'])) {
                                    throw new \Exception('请上传网签合同照片');
                                }
                                if (strlen($postData['attachment']) > 1024) {
                                    throw new \Exception('不动产权证照片超出限制');
                                }
                                $postData['cert_code'] = $postData['code'];
                                break;
                            default:
                                throw new \Exception('非法提交');
                        }
                        break;
                    //  租房
                    case 2:
                        //  社保信息
                        $insurancePostData = $this->request->only([
                            'insurance_code',
                            'attachment_insurance'
                        ]);
                        if (!empty($insurancePostData['insurance_code'])) {
                            $insurancePostData['insurance_code'] = strtoupper(trim($insurancePostData['insurance_code']));
                            if (!check_Idcard($insurancePostData['insurance_code'])) {
                                throw new \Exception('社保/身份证格式不正确');
                            }
                            if (strlen($insurancePostData['attachment_insurance']) > 255) {
                                throw new \Exception('社保照片超出限制');
                            }
                        }
                        $insuranceData = [
                            'family_id' => $data['family_id'],
                            'social_code' => $insurancePostData['insurance_code'],
                            'attachment' => $insurancePostData['attachment_insurance']
                        ];
                        //  居住证信息
                        $residencePostData = $this->request->only([
                            'residence_code',
                            'attachment_residence'
                        ]);
                        $residence_code = '';
                        if (!empty($residencePostData['residence_code'])) {
                            $residence_code= strtoupper(trim($residencePostData['residence_code']));
                            $preg = '/^[A-Za-z0-9]+$/';
                            if (preg_match($preg, $residence_code) == 0){
                                throw new \Exception('居住证只能是数字和英文');
                            }
                            if (strlen($residence_code) != 17 && strlen($residence_code) != 18) {
                                throw new \Exception('居住证号长度为17或18位');
                            }
                            if (strlen($residencePostData['attachment_residence']) > 255) {
                                throw new \Exception('居住证照片超出限制');
                            }
                        }
                        $residenceData = [
                            'family_id' => $data['family_id'],
                            'live_code' => $residence_code,
                            'attachment' => $residencePostData['attachment_residence']
                        ];
                        //  企业信息
                        $companyPostData = $this->request->only([
                            'company_code',
                            'attachment_company'
                        ]);
                        $company_code = '';
                        if (!empty($companyPostData['company_code'])) {
                            $company_code = strtoupper(trim($companyPostData['company_code']));
                            $preg = '/^[^IOZSV]+$/u';
                            if (!preg_match($preg,$company_code)){
                                throw new \Exception('执照号码不应有字母IOZSV');
                            }
                            $preg = '/^([0-9A-HJ-NPQRTUWXY]{2}\d{6}[0-9A-HJ-NPQRTUWXY]{10}|[1-9]\d{14})$/u';
                            if (preg_match($preg,$company_code) == 0){
                                throw new \Exception('营业执照号码不正确');
                            }
                            if (strlen($companyPostData['attachment_company']) > 255) {
                                throw new \Exception('营业执照照片超出限制');
                            }
                        }
                        $companyData = [
                            'family_id' => $data['family_id'],
                            'org_code' => $company_code,
                            'attachment' => $companyPostData['attachment_company']
                        ];
                        break;
                    //  自建房
                    case 3:
                        $postData = $this->request->only([
                            'is_main_private',
                            'attachment'
                        ]);
                        if (empty($postData['is_main_private'])) {
                            throw new \Exception('请选择是否为主城区自建房');
                        }
                        if (!in_array($postData['is_main_private'], [1, 2])) {
                            throw new \Exception('主城区自建房选择不正确');
                        }
                        if (empty($postData['attachment'])) {
                            throw new \Exception('请上传自建房资料照片');
                        }
                        if (strlen($postData['attachment']) > 1024) {
                            throw new \Exception('自建房资料照片超出限制');
                        }
                        break;
                    //  置换房
                    case 4:
                        $postData = $this->request->only([
                            'attachment_replacement',
                            'attachment_demolition'
                        ]);
                        if (empty($postData['attachment_replacement'])) {
                            throw new \Exception('请上传置换协议照片');
                        }
                        if (strlen($postData['attachment_replacement']) > 1024) {
                            throw new \Exception('置换协议照片超出限制');
                        }
                        if (empty($postData['attachment_demolition'])) {
                            throw new \Exception('请上传拆迁指挥部证明照片');
                        }
                        if (strlen($postData['attachment_demolition']) > 1024) {
                            throw new \Exception('拆迁指挥部证明照片超出限制');
                        }
                        break;
                    //  公租房
                    case 5:
                        $postData = $this->request->only([
                            'code',
                            'attachment'
                        ]);
                        $postData['code'] = strtoupper(trim($postData['code']));
                        if (strlen($postData['code']) < 2 || strlen($postData['code']) > 32) {
                            throw new \Exception('请输入正确的合同号');
                        }
                        if (empty($postData['attachment'])) {
                            throw new \Exception('请上传公租房合同照片');
                        }
                        if (strlen($postData['attachment']) > 1024) {
                            throw new \Exception('公租房合同照片超出限制');
                        }
                        break;
                    default:
                        throw new \Exception('非法提交');
                }
                //  合并数据(仅当房产类型不为租房的时候)
                if ($data['house_type'] != 2) {
                    $data = array_merge($data, $postData);
                }
                $tmpData = (new ApplyTmp())->where([
                    'user_id' =>$this->userInfo['id'],
                    'child_id' => $child_id,
                    'completed' => 0
                ])->find();
                if(empty($tmpData)){
                    throw new \Exception('信息保存失败，请返回重新填写学生信息');
                }
                $idcard = Db::name("user_family")->where("id",$data['family_id'])->value('idcard');
                $tmp_id = $tmpData['id'];
                $reckon = new Reckon();
                $house_type = $data['house_type'];
                $code_type = isset($data['code_type'])?$data['code_type']:'';
                $cert_code = isset($data['cert_code'])?$data['cert_code']:'';
                //房产类型 1：产权房；2：租房；3：自建房；4：置换房；5：公租房；6 : 三代同堂
                //  如果房产信息存在，则更新
                if ($tmpData['house_id']) {
                    $house_id = $tmpData['house_id'];
                    $data['id'] = $house_id;
                    switch ($data['house_type']) {
                        //  产权房
                        case 1:
                            Db::name('UserHouse')
                                ->where('id', $house_id)
                                ->update([
                                    'tmp_id' => $tmp_id,
                                    'family_id' => $data['family_id'],
                                    'house_type' => $data['house_type'],
                                    'house_address' => $data['house_address'],
                                    'standard_id' => $data['standard_id'],
                                    'code_type' => $data['code_type'],
                                    'code' => $data['code'],
                                    'cert_code' => $data['cert_code'],
                                    'attachment' => $data['attachment'],
                                ]);
                            break;
                        //  租房
                        case 2:
                            Db::name('UserHouse')
                                ->where('id', $house_id)
                                ->update([
                                    'tmp_id' => $tmp_id,
                                    'family_id' => $data['family_id'],
                                    'house_type' => $data['house_type'],
                                    'house_address' => $data['house_address'],
                                    'standard_id' => $data['standard_id'],
                                    'code_type' => 0,
                                    'code' => '',
                                    'cert_code' => '',
                                    'rental_start_time' => $data['rental_start_time'],
                                    'other_attachment' => $data['other_attachment'],
                                ]);
                            break;
                        //  自建房
                        case 3:
                            Db::name('UserHouse')
                                ->where('id', $house_id)
                                ->update([
                                    'tmp_id' => $tmp_id,
                                    'family_id' => $data['family_id'],
                                    'house_type' => $data['house_type'],
                                    'house_address' => $data['house_address'],
                                    'standard_id' => $data['standard_id'],
                                    'is_main_private' => $data['is_main_private'],
                                    'attachment' => $data['attachment'],
                                ]);
                            break;
                        //  置换房
                        case 4:
                            Db::name('UserHouse')
                                ->where('id', $house_id)
                                ->update([
                                    'tmp_id' => $tmp_id,
                                    'family_id' => $data['family_id'],
                                    'house_type' => $data['house_type'],
                                    'house_address' => $data['house_address'],
                                    'standard_id' => $data['standard_id'],
                                    'attachment_replacement' => $data['attachment_replacement'],
                                    'attachment_demolition' => $data['attachment_demolition'],
                                ]);
                            break;
                        //  公租房
                        case 5:
                            Db::name('UserHouse')
                                ->where('id', $house_id)
                                ->update([
                                    'tmp_id' => $tmp_id,
                                    'family_id' => $data['family_id'],
                                    'house_type' => $data['house_type'],
                                    'house_address' => $data['house_address'],
                                    'standard_id' => $data['standard_id'],
                                    'code' => $data['code'],
                                    'attachment' => $data['attachment'],
                                ]);
                            break;
                        default:
                            throw new \Exception('非法提交');
                    }
                } else {
                    $data['tmp_id'] = $tmp_id;
                    switch ($data['house_type']) {
                        //  产权房
                        case 1:
                            $house_id = Db::name('UserHouse')->insertGetId([
                                'tmp_id' => $tmp_id,
                                'family_id' => $data['family_id'],
                                'house_type' => $data['house_type'],
                                'house_address' => $data['house_address'],
                                'standard_id' => $data['standard_id'],
                                'code_type' => $data['code_type'],
                                'code' => $data['code'],
                                'cert_code' => $data['cert_code'],
                                'attachment' => $data['attachment'],
                            ]);
                            break;
                        //  租房
                        case 2:
                            $house_id = Db::name('UserHouse')->insertGetId([
                                'tmp_id' => $tmp_id,
                                'family_id' => $data['family_id'],
                                'house_type' => $data['house_type'],
                                'house_address' => $data['house_address'],
                                'standard_id' => $data['standard_id'],
                                'rental_start_time' => $data['rental_start_time'],
                                'other_attachment' => $data['other_attachment'],
                            ]);
                            break;
                        //  自建房
                        case 3:
                            $house_id = Db::name('UserHouse')->insertGetId([
                                'tmp_id' => $tmp_id,
                                'family_id' => $data['family_id'],
                                'house_type' => $data['house_type'],
                                'house_address' => $data['house_address'],
                                'standard_id' => $data['standard_id'],
                                'is_main_private' => $data['is_main_private'],
                                'attachment' => $data['attachment'],
                            ]);
                            break;
                        //  置换房
                        case 4:
                            $house_id = Db::name('UserHouse')->insertGetId([
                                'tmp_id' => $tmp_id,
                                'family_id' => $data['family_id'],
                                'house_type' => $data['house_type'],
                                'house_address' => $data['house_address'],
                                'standard_id' => $data['standard_id'],
                                'attachment_replacement' => $data['attachment_replacement'],
                                'attachment_demolition' => $data['attachment_demolition'],
                            ]);
                            break;
                        //  公租房
                        case 5:
                            $house_id = Db::name('UserHouse')->insertGetId([
                                'tmp_id' => $tmp_id,
                                'family_id' => $data['family_id'],
                                'house_type' => $data['house_type'],
                                'house_address' => $data['house_address'],
                                'standard_id' => $data['standard_id'],
                                'code' => $data['code'],
                            ]);
                            break;
                        default:
                            throw new \Exception('非法提交');
                    }
                    $data['id'] = $house_id;
                    Db::name('UserApplyTmp')
                        ->where('id',$tmp_id)
                        ->update(['house_id' => $house_id]);
                }
                $real_house_check = Cache::get('real_house_check');
                if($real_house_check){
                    //  目前只有当房产类型为产权房进行比对，其它返回无结果
                    $resReckon = $reckon->CheckHouse($tmp_id,$house_id,$idcard,$house_type,$code_type,$cert_code);
                    if(!$resReckon['code']){
                        throw new \Exception($resReckon['msg']);
                    }
                }else{
                    Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_house' => 0]);
                }
                //  如果是租房则处理  居住证、社保信息、企业信息
                if ($data['house_type'] == 2) {
                    $insurance_id = 0;
                    //  如果存在社保信息，则更新
                    if ($tmpData['insurance_id']) {
                        $insurance_id = $tmpData['insurance_id'];
                        $insuranceData['id'] = $insurance_id;
                        $insuranceData['tmp_id'] = $tmp_id;
                        $insuranceData['house_id'] = $house_id;
                        $deleted = 0;
                        if(!$insuranceData['social_code']){
                            Db::name('user_apply_tmp')
                                ->where('id',$tmp_id)
                                ->update([
                                    'insurance_id' => 0,
                                    'check_insurance' => 0,
                                ]);
                            $deleted = 1;
                        }
                        Db::name('UserInsurance')
                            ->where('id',$insurance_id)
                            ->update([
                                'family_id' => $insuranceData['family_id'],
                                'social_code' => $insuranceData['social_code'],
                                'attachment' => $insuranceData['attachment'],
                                'deleted' => $deleted
                            ]);
                        if($deleted){
                            $insuranceData['id'] = 0;
                            $insurance_id = 0;
                        }else{
                            $resInsurance['data'] = $insuranceData;
                        }
                    } else {
                        //  如果提交了社保信息，则新增
                        if(!empty($insuranceData['social_code'])){
                            $insuranceData['tmp_id'] = $tmp_id;
                            $insuranceData['house_id'] = $house_id;
                            $insurance_id = Db::name('UserInsurance')->insertGetId([
                                'tmp_id' => $tmp_id,
                                'house_id' => $house_id,
                                'family_id' => $insuranceData['family_id'],
                                'social_code' => $insuranceData['social_code'],
                                'attachment' => $insuranceData['attachment'],
                            ]);
                            $insuranceData['id'] = $insurance_id;
                            Db::name('UserApplyTmp')
                                ->where('id',$tmp_id)
                                ->update(['insurance_id' => $insurance_id]);
                            $resInsurance['data'] = $insuranceData;
                        }
                    }
                    $real_insurance_check = Cache::get('real_insurance_check');
                    //如果存在社保信息则进行比对
                    if($insurance_id){
                        if($real_insurance_check){
                            $resReckon = $reckon->CheckInsurance($tmp_id,$insurance_id,$insuranceData['social_code']);
                            if(!$resReckon['code']){
                                throw new \Exception($resReckon['msg']);
                            }
                        }else{
                            Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_insurance' => 0]);
                        }
                    }
                    $residence_id = 0;
                    //  如果存在居住证信息，则更新
                    if ($tmpData['residence_id']) {
                        $residence_id = $tmpData['residence_id'];
                        $residenceData['id'] = $residence_id;
                        $residenceData['tmp_id'] = $tmp_id;
                        $residenceData['house_id'] = $house_id;
                        $deleted = 0;
                        if(!$residenceData['live_code']){
                            Db::name('user_apply_tmp')
                                ->where('id',$tmp_id)
                                ->update([
                                    'residence_id' => 0,
                                    'check_residence' => 0,
                                ]);
                            $deleted = 1;
                        }
                        Db::name('UserResidence')
                            ->where('id',$residence_id)
                            ->update([
                                'family_id' => $residenceData['family_id'],
                                'live_code' => $residenceData['live_code'],
                                'attachment' => $residenceData['attachment'],
                                'deleted' => $deleted
                            ]);
                        if($deleted){
                            $residenceData['id'] = 0;
                            $residence_id = 0;
                        }else{
                            $resResidence['data'] = $residenceData;
                        }
                    } else {
                        //  如果提交了居住证信息，则新增
                        if(!empty($residenceData['live_code'])){
                            $residenceData['tmp_id'] = $tmp_id;
                            $residenceData['house_id'] = $house_id;
                            $residence_id = Db::name('UserResidence')->insertGetId([
                                'tmp_id' => $tmp_id,
                                'house_id' => $house_id,
                                'family_id' => $residenceData['family_id'],
                                'live_code' => $residenceData['live_code'],
                                'attachment' => $residenceData['attachment'],
                            ]);
                            $residenceData['id'] = $residence_id;
                            Db::name('UserApplyTmp')
                                ->where('id',$tmp_id)
                                ->update(['residence_id' => $residence_id]);
                            $resResidence['data'] = $residenceData;
                        }
                    }
                    $real_residence_check = Cache::get('real_residence_check');
                    //如果存在居住证信息则进行比对
                    if($residence_id){
                        if($real_residence_check){
                            $resReckon = $reckon->CheckResidence($tmp_id,$residence_id,$residenceData['live_code']);
                            if(!$resReckon['code']){
                                throw new \Exception($resReckon['msg']);
                            }
                        }else{
                            Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_residence' => 0]);
                        }
                    }
                    $company_id = 0;
                    //  如果存在企业信息，则更新
                    if ($tmpData['company_id']) {
                        $company_id = $tmpData['company_id'];
                        $companyData['id'] = $company_id;
                        $companyData['tmp_id'] = $tmp_id;
                        $companyData['house_id'] = $house_id;
                        $deleted = 0;
                        if(!$companyData['org_code']){
                            Db::name('user_apply_tmp')
                                ->where('id',$tmp_id)
                                ->update([
                                    'company_id' => 0,
                                    'check_company' => 0,

                                ]);
                            $deleted = 1;
                        }
                        Db::name('UserCompany')
                            ->where('id',$company_id)
                            ->update([
                                'family_id' => $companyData['family_id'],
                                'org_code' => $companyData['org_code'],
                                'attachment' => $companyData['attachment'],
                                'deleted' => $deleted
                            ]);
                        if($deleted){
                            $companyData['id'] = 0;
                            $company_id = 0;
                        }else{
                            $resCompany['data'] = $companyData;
                        }
                    } else {
                        //  如果提交了企业信息，则新增
                        if(!empty($companyData['org_code'])) {
                            $companyData['tmp_id'] = $tmp_id;
                            $companyData['house_id'] = $house_id;
                            $company_id = Db::name('UserCompany')->insertGetId([
                                'tmp_id' => $tmp_id,
                                'house_id' => $house_id,
                                'family_id' => $companyData['family_id'],
                                'org_code' => $companyData['org_code'],
                                'attachment' => $companyData['attachment'],
                            ]);
                            $companyData['id'] = $company_id;
                            Db::name('UserApplyTmp')
                                ->where('id',$tmp_id)
                                ->update(['company_id' => $company_id]);
                            $resCompany['data'] = $companyData;
                        }
                    }
                    $real_company_check = Cache::get('real_company_check');
                    //如果存在企业信息则进行比对
                    if($company_id){
                        if($real_company_check){
                            $resReckon = $reckon->CheckCompany($tmp_id,$company_id,$companyData['org_code']);
                            if(!$resReckon['code']){
                                throw new \Exception($resReckon['msg']);
                            }
                        }else{
                            Db::name('user_apply_tmp')->where('id',$tmp_id)->update(['check_company' => 0]);
                        }
                    }
                    //  如果是租房，则附加到返回数据中
                    $data['insurance_data'] = $resInsurance['data'] ?? null;
                    $data['residence_data'] = $resResidence['data'] ?? null;
                    $data['company_data'] = $resCompany['data'] ?? null;
                }
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }
}