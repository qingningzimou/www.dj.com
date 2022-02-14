<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/7/13
 * Time: 9:16
 */

namespace app\api\controller\basic;
use app\common\controller\Education;
use sm\TxSm4;
use app\common\model\SysRegion;
use think\facade\Lang;
use think\facade\Cache;

class CheckInfo extends Education
{
    /**
     * 获取参数
     * @return \think\response\Json
     */
    public function getData()
    {
        if ($this->request->isPost()) {
            try {
                $postData = $this->request->param();
                if(!isset($postData['InterfaceCode']) || !$postData['InterfaceCode']){
                    throw new \Exception("提交的数据错误");
                }
                if (isset($postData['idcard']) && !check_Idcard($postData['idcard'])) {
                    throw new \Exception("身份证号码不正确");
                }
                $resData = [];
                $url = Cache::get('comparison_url');
                $key = Cache::get('comparison_key');
                $sm4 = new TxSm4();
                switch ($postData['InterfaceCode']) {
                    case "CHKHJXX" :  //户籍信息
                        if(!isset($postData['idcard'])) {
                            throw new \Exception("未提交身份证号码");
                        }
                        $data = [
                            'SFZH' => $postData['idcard'],
                            'InterfaceCode' => 'check_user'
                        ];
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('比对接口连接失败');
                        }
                        $deHJData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deHJData['code']) && !$deHJData['code']) {
                            throw new \Exception($deHJData['msg']);
                        }
                        if (!$deHJData['status']) {
                            throw new \Exception($deHJData['data']);
                        }
                        $resData = [
                            'full_name' => $deHJData['data']['name'],
                            'address' => $deHJData['data']['address'],
                            'police_station' => $deHJData['data']['police_station'],
                            'holder_relation' => getRelationByHgx($deHJData['data']['holder_relation']),
                            'sex' => $deHJData['data']['sex'] == 1?'男':'女',
                            'area_code' => getAdcodeName($deHJData['data']['area_code']),
                        ];
                        break;
                    case "CHKHYXX" :  //婚姻信息
                        if(!isset($postData['idcard'])) {
                            throw new \Exception("未提交身份证号码");
                        }
                        $data = ['InterfaceCode' => 'check_marriage'];
                        if(x_getSexByIdcard($postData['idcard']) == 1){
                            $data = array_merge($data,['MADJ1018' => $postData['idcard']]);
                        }else{
                            $data = array_merge($data,['MADJ1019' => $postData['idcard']]);
                        }
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deHYData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deHYData['code']) && !$deHYData['code']) {
                            throw new \Exception($deHYData['msg']);
                        }
                        if (isset($deHYData['status']) && !$deHYData['status']) {
                            throw new \Exception($deHYData['data']);
                        }
                        $resData = [
                            'status' => $deHYData['data']['status'],
                            'man_name' => $deHYData['data']['man_name'],
                            'man_idcard' => $deHYData['data']['man_idcard'],
                            'woman_name' => $deHYData['data']['woman_name'],
                            'woman_idcard' => $deHYData['data']['woman_idcard'],
                        ];
                        break;
                    case "CHKFCXX" :  //房产信息
                        if(!isset($postData['idcard'])) {
                            throw new \Exception("未提交身份证号码");
                        }
                        if(!isset($postData['area_code']) || !$postData['area_code']) {
                            throw new \Exception("未提交区县代码");
                        }
                        if(!isset($postData['ownership']) || !$postData['ownership']) {
                            throw new \Exception("未提交产权人姓名");
                        }
                        $data = [
                            'QLRZJHM' => $postData['idcard'],
                            'InterfaceCode' => 'new_check_house'
                        ];
                        if(isset($postData['property_number']) && $postData['property_number']){
                            $orgnum = getLongNum($postData['property_number']);
                            if($orgnum < 7){
                                throw new \Exception("房产证号输入有误");
                            }
                            $BDCQZH = $postData['property_number'];
                        }
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deFCData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deFCData['code']) && !$deFCData['code']) {
                            throw new \Exception($deFCData['msg']);
                        }
                        if (!$deFCData['status']) {
                            throw new \Exception($deFCData['data']);
                        }
                        $chk_house = 0;
                        $address = '';
                        foreach ($deFCData['data'] as $item){
                            if($item['area_code'] == $postData['area_code']){
                                $chk_house = 1;
                            }
                        }
                        if(isset($BDCQZH) && $BDCQZH){
                            $filter = filter_like_values($deFCData['data'],'property_number',$BDCQZH);
                            if(count($filter)){
                                $address = $filter['address'];
                            }
                        }
                        $resData = [
                            'chk_house' => $chk_house?'有房':'无房',
                            'address' => $address
                        ];
                        break;
                    case "CHKWQXX" :  //网签信息
                        if(!isset($postData['idcard'])) {
                            throw new \Exception("未提交身份证号码");
                        }
                        if(!isset($postData['area_code']) || !$postData['area_code']) {
                            throw new \Exception("未提交区县代码");
                        }
                        if(!isset($postData['ownership']) || !$postData['ownership']) {
                            throw new \Exception("未提交产权人姓名");
                        }
                        $data = [
                            'jyzzjhm' => $postData['idcard'],
                            'InterfaceCode' => 'check_online_sign'
                        ];
                        if(isset($postData['property_number']) && $postData['property_number']){
                            $contract_code = strtoupper($postData['property_number']);
                        }
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deWQData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deWQData['code']) && !$deWQData['code']) {
                            throw new \Exception($deWQData['msg']);
                        }
                        if (!$deWQData['status']) {
                            throw new \Exception($deWQData['data']);
                        }
                        $chk_house = 0;
                        $address = '';
                        foreach ($deWQData['data'] as $item){
                            if($item['area_code'] == $postData['area_code']){
                                $chk_house = 1;
                            }
                        }
                        if(isset($contract_code) && $contract_code){
                            $filter = filter_like_values($deWQData['data'],'contract_number',$contract_code);
                            if(count($filter)){
                                $address = $filter['address'];
                            }
                        }
                        $resData = [
                            'chk_house' => $chk_house?'有房':'无房',
                            'address' => $address
                        ];
                        break;
                    case "CHKJHXX" :  //监护人信息
                        if(!isset($postData['idcard'])) {
                            throw new \Exception("未提交学生身份证号码");
                        }
                        if(!isset($postData['child_name']) || !$postData['child_name']) {
                            throw new \Exception("未提交学生姓名");
                        }
                        $data = [
                            'GMSFHM' => $postData['idcard'],
                            'XM' => $postData['child_name'],
                            'InterfaceCode' => 'check_guardian'
                        ];
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deJHData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deJHData['code']) && !$deJHData['code']) {
                            throw new \Exception($deJHData['msg']);
                        }
                        if (!$deJHData['status']) {
                            throw new \Exception($deJHData['data']);
                        }
                        $resData = [
                            "father_idcard" => $deJHData['data']['father_idcard'],
                            "father_name" => $deJHData['data']['father_name'],
                            "mother_idcard" => $deJHData['data']['mother_idcard'],
                            "mother_name" => $deJHData['data']['mother_name'],
                            "child_idcard" => $deJHData['data']['child_idcard'],
                            "child_name" => $deJHData['data']['child_name'],
                        ];
                        break;
                    case "CHKCSXX" :  //出生信息
                        if(!isset($postData['idcard'])) {
                            throw new \Exception("未提交父母身份证号码");
                        }
                        if(!isset($postData['child_name']) || !$postData['child_name']) {
                            throw new \Exception("未提交学生姓名");
                        }
                        $data = ['InterfaceCode' => 'check_newborn'];
                        if(x_getSexByIdcard($postData['idcard']) == 1){
                            $data = array_merge($data,['FQZJ_HM' => $postData['idcard']]);
                        }else{
                            $data = array_merge($data,['MQZJ_HM' => $postData['idcard']]);
                        }
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deCSData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deCSData['code']) && !$deCSData['code']) {
                            throw new \Exception($deCSData['msg']);
                        }
                        if (!$deCSData['status']) {
                            throw new \Exception($deCSData['data']);
                        }
                        foreach ($deCSData['data'] as $item){
                            if($item['child_name'] == $postData['child_name']){
                                $resData = [
                                    'child_name' => $item['child_name'],
                                    'birthday' => date('Y-m-d',strtotime($item['birthday'])),
                                    'sex' => $item['sex'],
                                    'father_name' => $item['father_name'],
                                    'father_idcard' => $item['father_idcard'],
                                    'mother_name' => $item['mother_name'],
                                    'mother_idcard' => $item['mother_idcard'],
                                ];
                            }
                        }
                        break;
                    case "CHKSBXX" :  //社保信息
                        if(!isset($postData['idcard'])) {
                            throw new \Exception("未提交身份证号码");
                        }
                        $data = [
                            'GMSFHM' => $postData['idcard'],
                            'InterfaceCode' => 'check_insurance_card'
                        ];
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deSXData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deSXData['code']) && !$deSXData['code']) {
                            throw new \Exception($deSXData['msg']);
                        }
                        if (!$deSXData['status']) {
                            throw new \Exception($deSXData['data']);
                        }
                        //查询社保基本信息
                        $data = [
                            'GRSXH' => $deSXData['data']['GRSXH'],
                            'InterfaceCode' => 'check_insurance_basic'
                        ];
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deJBData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deJBData['code']) && !$deJBData['code']) {
                            throw new \Exception($deJBData['msg']);
                        }
                        if (!$deJBData['status']) {
                            throw new \Exception($deJBData['data']);
                        }
                        //查询社保信息
                        $data = [
                            'GRSXH' => $deSXData['data']['GRSXH'],
                            'KSSJ' => date("Ym", strtotime("-1 year")),
                            'ZZSJ' => date("Ym"),
                            'InterfaceCode' => 'check_insurance'
                        ];
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deSBData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deSBData['code']) && !$deSBData['code']) {
                            throw new \Exception($deSBData['msg']);
                        }
                        $company_name = '';
                        $end_time = '';
                        $subData = [];
                        if ($deSBData['status']) {
                            array_multisort(array_column($deSBData['data']['recode'], 'PAYDATE'), SORT_DESC, $deSBData['data']['recode']);
                            foreach ($deSBData['data']['recode'] as $item){
                                if($item['PAYTIME']){
                                    if(!$company_name && ($item['PERSONALPAY'] || $item['UNITPAY'])){
                                        $company_name = $item['DWMC'];
                                        $end_time = $item['JKRQ'];
                                    }
                                    if(!isset($subData[$item['PAYDATE']])){
                                        if($item['PERSONALPAY'] || $item['UNITPAY']){
                                            $subData[$item['PAYDATE']] = $item['PAYTIME'];
                                        }
                                    }
                                }
                            }
                        }
                        $year_num = count($subData);
                        $adcode = getAdcode($deJBData['data']['adcode']);
                        if($company_name){
                            //查询企业工商信息
                            $postData = [
                                'ENTNAME' => $company_name,
                                'InterfaceCode' => 'check_business'
                            ];
                            $postData = json_encode($postData, JSON_UNESCAPED_UNICODE);
                            $rawData = $sm4->encrypt($key, $postData);
                            $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                            if (!$getData) {
                                throw new \Exception('查询接口连接失败');
                            }
                            $deGSData = json_decode($sm4->decrypt($key, $getData),true);
                            if (isset($deGSData['code']) && !$deGSData['code']) {
                                throw new \Exception("企业信息查询失败");
                            }
                            if ($deGSData['status']) {
                                $adcode = $deGSData['data']['area_code'];
                            }
                        }
                        $resData = [
                            'full_name' => $deJBData['data']['full_name'],
                            'adcode' => getAdcodeName($adcode),
                            'start_time' => $deJBData['data']['start_time'],
                            'end_time' => $end_time,
                            'year_num' => $year_num,
                        ];
                        break;
                    case "CHKGSXX" :  //工商信息
                        if(!isset($postData['org_code']) || !$postData['org_code']) {
                            throw new \Exception("未提交营业执照号码");
                        }
                        $UNISCID = strtoupper($postData['org_code']);
                        $preg = '/^[iozsvIOZSV]+$/u';
                        if (preg_match($preg,$UNISCID)){
                            throw new \Exception('营业执照号码不应有字母IOZSV');
                        }
                        $preg = '/^([0-9A-HJ-NPQRTUWXY]{2}\d{6}[0-9A-HJ-NPQRTUWXY]{10}|[1-9]\d{14})$/u';
                        if (preg_match($preg,$UNISCID) == 0){
                            throw new \Exception('营业执照号码不正确');
                        }
                        if(strlen($UNISCID) == 18){
                            $data = [
                                'InterfaceCode' => 'check_business',
                                'UNISCID' => $UNISCID
                            ];
                            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                            $rawData = $sm4->encrypt($key, $data);
                            $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                            if (!$getData) {
                                throw new \Exception('查询接口连接失败');
                            }
                            $deGSData = json_decode($sm4->decrypt($key, $getData),true);
                            if (isset($deGSData['code']) && !$deGSData['code']) {
                                throw new \Exception($deGSData['msg']);
                            }
                            if(!$deGSData['status']){
                                $data = [
                                    'InterfaceCode' => 'check_individual_business',
                                    'UNISCID' => $UNISCID
                                ];
                                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                                $rawData = $sm4->encrypt($key, $data);
                                $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                                if (!$getData) {
                                    throw new \Exception('查询接口连接失败');
                                }
                                $deGSData = json_decode($sm4->decrypt($key, $getData),true);
                                if (isset($deGSData['code']) && !$deGSData['code']) {
                                    throw new \Exception($deGSData['msg']);
                                }
                            }
                        }else{
                            $data = [
                                'InterfaceCode' => 'check_individual_business',
                                'REGNO' => $UNISCID
                            ];
                            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                            $rawData = $sm4->encrypt($key, $data);
                            $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                            if (!$getData) {
                                throw new \Exception('查询接口连接失败');
                            }
                            $deGSData = json_decode($sm4->decrypt($key, $getData),true);
                            if (isset($deGSData['code']) && !$deGSData['code']) {
                                throw new \Exception($deGSData['msg']);
                            }
                        }
                        if (!$deGSData['status']) {
                            throw new \Exception($deGSData['data']);
                        }
                        $resData = [
                            'legal_person' => $deGSData['data']['legal_person'],
                            'establish_date' => date('Y-m-d',strtotime($deGSData['data']['establish_date'])),
                            'area_code' => getAdcodeName($deGSData['data']['area_code']),
                            'company_name' => $deGSData['data']['company_name'],
                            'address' => $deGSData['data']['address'],
                        ];
                        break;
                    case "CHKJZXX" :  //居住证信息
                        if(!isset($postData['reside_code']) || !$postData['reside_code']) {
                            throw new \Exception("未提交居住证号码");
                        }
                        $data = [
                            'InterfaceCode' => 'check_residence',
                            'JZZBH' => $postData['reside_code']
                        ];
                        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $rawData = $sm4->encrypt($key, $data);
                        $getData = httpPost($url.'/ApiReckon/checkApiData', $rawData, true);
                        if (!$getData) {
                            throw new \Exception('查询接口连接失败');
                        }
                        $deZZData = json_decode($sm4->decrypt($key, $getData),true);
                        if (isset($deZZData['code']) && !$deZZData['code']) {
                            throw new \Exception($deZZData['msg']);
                        }
                        if (!$deZZData['status']) {
                            throw new \Exception($deZZData['data']);
                        }
                        $status = '有效';
                        if(date_to_unixtime($deZZData['data']['validity_end_time']) < time()){
                            $status = '失效';
                        }
                        $resData = [
                            'status' => $status,
                            'full_name' => $deZZData['data']['full_name'],
                            'idcard' => $deZZData['data']['idcard'],
                            'household' => $deZZData['data']['household'],
                            'address' => $deZZData['data']['address'],
                            'start_time' => date('Y-m-d',strtotime($deZZData['data']['validity_start_time'])),
                            'end_time' => date('Y-m-d',strtotime($deZZData['data']['validity_end_time'])),
                        ];
                        break;
                    default:
                        throw new \Exception("未定义的查询");
                }
                $res = [
                    'code' => 1,
                    'data' => $resData
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
    public function getRegionList()
    {
        if ($this->request->isPost()) {
            try {
                $data = (new SysRegion())->where('disabled',0)->field(['id','simple_code', 'region_name',])->select();
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