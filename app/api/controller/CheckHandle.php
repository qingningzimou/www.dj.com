<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2021/7/22
 * Time: 14:49
 */

namespace app\api\controller;
use app\common\controller\Education;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Lang;
use think\facade\Cache;
use comparison\Reckon;
use dictionary\FilterData;
use app\mobile\model\user\Apply;
use app\mobile\model\user\Child;
use app\mobile\model\user\Family;
use app\mobile\model\user\House;
use app\mobile\model\user\Ancestor;
use app\mobile\model\user\Residence;
use app\mobile\model\user\Insurance;
use app\mobile\model\user\Company;
use app\mobile\model\user\ApplyTmp;
use app\common\model\RegionSetTime;

class CheckHandle extends Education
{
    /**
     * 执行比对程序
     * @return array
     */
    public function ExecuteCheck()
    {
        try {
            ignore_user_abort(true);
            set_time_limit(0);
            ob_end_clean();
            ob_implicit_flush();
            session_write_close();
            header('X-Accel-Buffering: no'); // 关键是加了这一行。
            header('Cache-Control: no-cache');
            header('Connection:keep-alive');
            echo str_pad('',1024);
            $code = $this->result['code'];
            switch ($code) {
                case "check_child" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('child_id','>',0)
                        ->where('check_child',0)
                        ->where('completed',1)
                        ->column('id,child_id');
                    break;
                case "check_family" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('family_id','>',0)
                        ->where('check_family',0)
                        ->where('completed',1)
                        ->column('id,family_id');
                    break;
                case "check_other" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('other_family_id','>',0)
                        ->where('check_other',0)
                        ->where('completed',1)
                        ->column('id,other_family_id');
                    break;
                case "check_ancestors" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('ancestors_family_id','>',0)
                        ->where('check_ancestors',0)
                        ->where('completed',1)
                        ->column('id,ancestors_family_id');
                    break;
                case "check_house" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('house_id','>',0)
                        ->where('check_house',0)
                        ->where('completed',1)
                        ->column('id,house_id');
                    break;
                case "check_insurance" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('insurance_id','>',0)
                        ->where('check_insurance',0)
                        ->where('completed',1)
                        ->column('id,insurance_id');
                    break;
                case "check_residence" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('residence_id','>',0)
                        ->where('check_residence',0)
                        ->where('completed',1)
                        ->column('id,residence_id');
                    break;
                case "check_company" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('company_id','>',0)
                        ->where('check_company',0)
                        ->where('completed',1)
                        ->column('id,company_id');
                    break;
                case "check_generations" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('ancestor_id','>',0)
                        ->where('check_generations',0)
                        ->where('completed',1)
                        ->column('id,ancestor_id');
                    break;
                case "check_completed" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('check_completed',0)
                        ->where('completed',1)
                        ->select()->toArray();
                    break;
                case "check_sixth" :
                    $checkData =  Db::name('user_apply_detail')
                        ->field(['id','child_id','user_apply_id'])
                        ->where('deleted',0)
                        ->order('id asc')
                        ->select()->toArray();
                    break;
                case "check_birthplace" :
                    $checkData =  Db::name('user_apply_tmp')
                        ->where('child_id','>',0)
                        ->where('check_birthplace',0)
                        ->where('completed',1)
                        ->column('id,child_id');
//                    $checkData =  Db::name('user_child')
//                        ->field(['id','tmp_id','api_region_id','api_address'])
//                        ->where('api_policestation_id',99)
//                        ->where('api_region_id','>',0)
//                        ->where('deleted',0)
//                        ->order('id asc')
//                        ->select()->toArray();

                    break;
                case "check_age" :
                    $checkData =  Db::name('user_apply')
                        ->field(['id','child_id','region_id','school_type'])
                        ->where('deleted',0)
                        ->order('id asc')
                        ->select()->toArray();
                    break;
                case "check_guardian" :
                    $checkData =  Db::name('user_apply_status')
                        ->field(['id','user_apply_id'])
                        ->where('auto_check_relation','<>',1)
                        ->where('deleted',0)
                        ->order('id asc')
                        ->select()->toArray();
                    break;
                default:
                    throw new \Exception("未定义的处理程序");
            }
            Cache::set('handle_check_data', $checkData);
            $data_num = count($checkData);
            $base = 0;
            //线程数量
            $thread_count = 10;
            //每线程数据量
            $batch_count = 1000;
//            $sub_num =(int) ($data_num / ($thread_count*$batch_count));
            Cache::set('handle_completed', 0);
            echo json_encode([
                'code' => 1,
                'data' => $data_num,
            ]);
            if(!$data_num){
                die;
            }
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 10000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 20000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 30000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 40000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 50000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 60000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 70000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 80000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 90000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 100000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 110000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 120000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 130000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 140000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 150000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 160000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 170000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 180000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
            $base = 190000;
            $this->asyncContrast($code,$base,$thread_count,$batch_count);
//            for($i = 0;$i<=$sub_num;$i++){
//                $this->asyncContrast($code,$base,$thread_count,$batch_count);
//                $base += $thread_count*$batch_count;
//            }
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
            echo json_encode($res,256);
        }
    }

    /**
     * 处理情况反馈
     */
    public function showTask()
    {
        try {
            $handle_state = Cache::get('handle_completed');
            $res = [
                'code' => 1,
                'data' => $handle_state,
            ];
        }
        catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage()
            ];
        }
        return json($res);
    }

    /**
     * 单项数据比对程序
     */
    public function actChkData()
    {
        try{
            ignore_user_abort(true);
            set_time_limit(0);
            $minid = $this->result['minid'];
            $maxid = $this->result['maxid'];
            $code = $this->result['code'];
            $checkData = Cache::get('handle_check_data', []);
            $reckon = new Reckon();
            $checkNum = 0;
            switch ($code) {
                case "check_child" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $idcard = Child::where('id',$v['child_id'])->value('idcard');
                                if(empty($idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckChild($v['id'],$v['child_id'],$idcard);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_family" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $idcard = Family::where('id',$v['family_id'])->value('idcard');
                                if(empty($idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckFamily($v['id'],$v['family_id'],$idcard);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_other" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $idcard = Family::where('id',$v['other_family_id'])->value('idcard');
                                if(empty($idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckOther($v['id'],$v['other_family_id'],$idcard);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_ancestors" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $idcard = Family::where('id',$v['ancestors_family_id'])->value('idcard');
                                if(empty($idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckAncestors($v['id'],$v['ancestors_family_id'],$idcard);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_house" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $tmpData = (new ApplyTmp())->where('id', $v['id'])->find();
                                if(empty($tmpData)){
                                    $checkNum += 1;
                                    continue;
                                }
                                $idcard = Family::where('id',$tmpData['family_id'])->value('idcard');
                                if(empty($idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                if($tmpData['ancestor_id']){
                                    $idcard = Family::where('id',$tmpData['ancestors_family_id'])->value('idcard');
                                    if(empty($idcard)){
                                        $checkNum += 1;
                                        continue;
                                    }
                                }
                                $houseData = House::where('id',$v['house_id'])->find();
                                if(empty($houseData)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckHouse($v['id'],$v['house_id'],$idcard,$houseData['house_type'],$houseData['code_type'],$houseData['cert_code']);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_insurance" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $insuranceData = Insurance::where('id',$v['insurance_id'])->find();
                                if(empty($insuranceData)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckInsurance($v['id'],$v['insurance_id'],$insuranceData['social_code']);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_residence" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $residenceData = Residence::where('id',$v['residence_id'])->find();
                                if(empty($residenceData)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckResidence($v['id'],$v['residence_id'],$residenceData['live_code']);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_company" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $companyData = Company::where('id',$v['company_id'])->find();
                                if(empty($companyData)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckCompany($v['id'],$v['company_id'],$companyData['org_code']);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_generations" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $tmpData = (new ApplyTmp())->where('id', $v['id'])->find();
                                if(empty($tmpData)){
                                    $checkNum += 1;
                                    continue;
                                }
                                $child_idcard = Child::where('id',$tmpData['child_id'])->value('idcard');
                                if(empty($child_idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                $parent_idcard = Family::where('id',$tmpData['family_id'])->value('idcard');
                                if(empty($parent_idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                $grandparent_idcard = Family::where('id',$tmpData['ancestors_family_id'])->value('idcard');
                                if(empty($grandparent_idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                $other_idcard = '';
                                if($tmpData['other_family_id']){
                                    $other_idcard = Family::where('id',$tmpData['other_family_id'])->value('idcard');
                                    if(empty($other_idcard)){
                                        $other_idcard = '';
                                    }
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckAncestor($v['id'],$v['ancestor_id'],$child_idcard,$parent_idcard,$grandparent_idcard,$other_idcard);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_completed" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckCompleted($v);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_sixth" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                try{
                                    $region_id = Apply::where('id',$v['user_apply_id'])->value('region_id');
                                    if(empty($region_id)){
                                        $checkNum += 1;
                                        continue;
                                    }
                                    $idcard = Child::where('id',$v['child_id'])->value('idcard');
                                    if(empty($idcard)){
                                        $checkNum += 1;
                                        continue;
                                    }
                                    $sixthGrade = Db::name('SixthGrade')->where('id_card', $idcard)->find();
                                    if(!empty($sixthGrade)){
                                        $student_status = 2;//学生学籍不在本区
                                        if($region_id == $sixthGrade['region_id']){
                                            $student_status = 1;//学生学籍在本区
                                        }
                                    }else{
                                        $student_status = 3;//学生学籍无结果
                                    }
                                    Db::name('UserApplyDetail')->where('id',$v['id'])->update(['student_status' => $student_status]);
                                }catch (\Exception $exception){

                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_birthplace" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                $idcard = Child::where('id',$v['child_id'])->value('idcard');
                                if(empty($idcard)){
                                    $checkNum += 1;
                                    continue;
                                }
                                Db::startTrans();
                                try{
                                    $resReckon = $reckon->CheckBirthplace($v['id'],$v['child_id'],$idcard);
                                    if(!$resReckon['code']){
                                        throw new \Exception($resReckon['msg']);
                                    }
                                    Db::commit();
                                }catch (\Exception $exception){
                                    Db::rollback();
                                }
                                $checkNum += 1;

//                                try{
//                                    $chk_cross = 0;
//                                    $apply_id = ApplyTmp::where('id',$v['tmp_id'])->value('apply_id');
//                                    if(empty($apply_id)){
//                                        $checkNum += 1;
//                                        continue;
//                                    }
//                                    $region_id = Apply::where('id',$apply_id)->value('region_id');
//                                    if(empty($region_id)){
//                                        $checkNum += 1;
//                                        continue;
//                                    }
//                                    //匹配到跨区派出所并且申报区域未对应派出所区域
//                                    if($region_id != $v['api_region_id']){
//                                        $birthplaceData = Db::table("deg_sys_address_birthplace")->where('address',$v['api_address'])->find();;
//                                        if(!empty($birthplaceData)){
//                                            //如果户籍地址有学校认领
//                                            if($birthplaceData['primary_school_id'] == 21 || $birthplaceData['middle_school_id'] == 30){
//                                                $chk_cross = 1;
//                                            }
//                                        }
//                                    }
//                                    Db::name('UserApplyDetail')->where('user_apply_id',$apply_id)->update(['chk_cross' => $chk_cross]);

//                                }catch (\Exception $exception){
//                                }
//                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_age" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                try{
                                    //获取招生年龄配置
                                    $region_time = (new RegionSetTime())
                                        ->where('region_id',$v['region_id'])
                                        ->where('grade_id',$v['school_type'])
                                        ->find();
                                    if(empty($region_time)){
                                        $checkNum += 1;
                                        continue;
                                    }
                                    $birthday = Child::where('id',$v['child_id'])->value('birthday');
                                    if(empty($birthday)){
                                        $checkNum += 1;
                                        continue;
                                    }
                                    if($birthday >= $region_time['start_time'] && $birthday <= $region_time['end_time']){
                                        $child_age = 1;  //足龄
                                    }else if($birthday < $region_time['start_time']){
                                        $child_age = 3; // 超龄
                                    }else{
                                        $child_age = 2; //不足龄
                                    }
                                    Db::name('UserApplyDetail')->where('user_apply_id',$v['id'])->update(['child_age_status' => $child_age]);
                                }catch (\Exception $exception){
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                case "check_guardian" :
                    foreach ($checkData as $k => $v) {
                        if($k >= $minid-1 and $k <= $maxid -1){
                            if(isset($checkData[$k])){
                                try{
                                    $applyData = Apply::where('id',$v['user_apply_id'])->find();
                                    if(empty($applyData)){
                                        $checkNum += 1;
                                        continue;
                                    }
                                    $familyData = Family::where('id',$applyData['family_id'])->find();
                                    if(empty($familyData)){
                                        $checkNum += 1;
                                        continue;
                                    }
                                    $childData = Child::where('id',$applyData['child_id'])->find();
                                    if(empty($childData)){
                                        $checkNum += 1;
                                        continue;
                                    }
                                    $child_relation_other = ['20', '21', '22', '23', '24', '25', '26', '27', '29', '30', '31', '32', '33', '34', '35', '36', '37', '39'];
                                    $holder_relation_arr = ['01', '02', '03', '10', '11', '12'];
                                    if($childData['api_card'] && $familyData['api_card'] && ($childData['api_card'] == $familyData['api_card'])){
                                        if(in_array($childData['api_relation'],$child_relation_other) && in_array($familyData['api_relation'],$holder_relation_arr)){
                                            Db::name('UserApplyDetail')->where('user_apply_id',$v['user_apply_id'])->update(['guardian_relation' => 1]);
                                            Db::name('UserApplyStatus')->where('id',$v['id'])->update(['auto_check_relation' => 1]);
                                        }
                                    }
                                }catch (\Exception $exception){
                                }
                                $checkNum += 1;
                            }
                        }
                    }
                    break;
                default:
                    throw new \Exception("未定义的处理程序");
            }
            Cache::inc('handle_completed',$checkNum);
        }catch (\Exception $exception){
            Log::write($exception->getMessage() ?: Lang::get('system_error'));
            Db::rollback();
        }
    }
    /**
     * 多线程执行比对程序
     * @param $base
     * @param $count
     */
    private function asyncContrast($code,$base,$thread_count,$batch_count){
        try {
            $sys_url = Cache::get('special_use_url');
            for($i = 1;$i <= $thread_count;$i++){
                $num = ($i -1) * $batch_count;
                $num2 = $i * $batch_count;
                $tmp = 'ch'.$i;
                $$tmp = curl_init();
                curl_setopt($$tmp, CURLOPT_URL, $sys_url.'/api/CheckHandle/actChkData?code='.$code.'&minid='.($base + $num + 1).'&maxid='.($base + $num2));
                curl_setopt($$tmp, CURLOPT_RETURNTRANSFER, 1);
//                curl_setopt($$tmp, CURLOPT_TIMEOUT, 1);
            }
            $mh = curl_multi_init(); //1 创建批处理cURL句柄
            for($i = 1;$i <= $thread_count;$i++){
                $tmp = 'ch'.$i;
                curl_multi_add_handle($mh, $$tmp); //2 增加句柄
            }
            $active = null;
            do {
                while (($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM) ;
                if ($mrc != CURLM_OK) { break; }
                while ($done = curl_multi_info_read($mh)) {
                    $info = curl_getinfo($done['handle']);
                    $error = curl_error($done['handle']);
                    $result[] = curl_multi_getcontent($done['handle']);
                    // $responses[$map[(string) $done['handle']]] = compact('info', 'error', 'results');
                    curl_multi_remove_handle($mh, $done['handle']);
                    curl_close($done['handle']);
                }
                if ($active > 0) {
                    curl_multi_select($mh);
                }
            } while ($active);
            curl_multi_close($mh); //7 关闭全部句柄
        } catch (\Exception $exception) {

            Log::write($exception->getMessage() ?: Lang::get('system_error'));
        }
    }

}