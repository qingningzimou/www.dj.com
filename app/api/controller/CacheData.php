<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 0:57
 */
namespace app\api\controller;
use app\common\controller\Education;
use app\common\model\SysNodes;
use app\common\model\SysRoles;
use app\common\model\SysRegion;
use app\common\model\Department;
use app\common\model\SysDictionary;
use app\common\model\SysCostCategory;
use app\common\model\Manage;
use app\common\model\Schools;
use app\common\model\CentralSchool;
use app\common\model\PoliceStation;
use app\common\model\Module;
use app\common\model\SysAddressBirthplace;
use app\common\model\SysAddressIntact;

use app\common\model\AddressBaoKang;
use app\common\model\AddressDongJin;
use app\common\model\AddressFanCheng;
use app\common\model\AddressGaoXin;
use app\common\model\AddressGuCheng;
use app\common\model\AddressLaoHeKou;
use app\common\model\AddressNanZhang;
use app\common\model\AddressXiangCheng;
use app\common\model\AddressXiangZhou;
use app\common\model\AddressYiCheng;
use app\common\model\AddressZaoYang;

use app\common\model\SysRegionStandard;
use app\common\model\SysMessage;
use app\common\model\AssignmentSetting;
use app\common\model\Course;
use app\common\model\Plan;
use app\common\model\Policy;
use app\common\model\RegionSetTime;

use think\facade\Lang;
use think\facade\Cache;

class CacheData extends Education
{
    public function getCache()
    {
        try {
            @ini_set("memory_limit","1024M");
            $cache_arr = [];
            $cache_arr[] = ['nodes' => count(Cache::get('nodes',[]))];
            $cache_arr[] = ['roles' => count(Cache::get('roles',[]))];
            $cache_arr[] = ['region' => count(Cache::get('region',[]))];
            $cache_arr[] = ['department' => count(Cache::get('department',[]))];
            $cache_arr[] = ['dictionary' => count(Cache::get('dictionary',[]))];
            $cache_arr[] = ['manage' => count(Cache::get('manage',[]))];
            $cache_arr[] = ['school' => count(Cache::get('school',[]))];
            $cache_arr[] = ['central' => count(Cache::get('central',[]))];
            $cache_arr[] = ['police' => count(Cache::get('police',[]))];
            $cache_arr[] = ['module' => count(Cache::get('module',[]))];
            $cache_arr[] = ['cost' => count(Cache::get('cost',[]))];
            $cache_arr[] = ['birthplace' => count(Cache::get('birthplace',[]))];
            $cache_arr[] = ['intact' => count(Cache::get('intact',[]))];

            $cache_arr[] = ['standard' => count(Cache::get('standard',[]))];
            $cache_arr[] = ['sysmessage' => count(Cache::get('sysmessage',[]))];
            $cache_arr[] = ['assignset' => count(Cache::get('assignset',[]))];
            $cache_arr[] = ['course' => count(Cache::get('course',[]))];
            $cache_arr[] = ['plan' => count(Cache::get('plan',[]))];
            $cache_arr[] = ['policy' => count(Cache::get('policy',[]))];
            $cache_arr[] = ['regionage' => count(Cache::get('regionage',[]))];

            $res = [
                'code' => 1,
                'data' => $cache_arr
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }

    public function updateCache()
    {
        try {
            @ini_set("memory_limit","1024M");
            set_time_limit(0);
            session_write_close();
            $cache_arr = self::updateStatic();
            $res = [
                'code' => 1,
                'data' => $cache_arr
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }

    public function refreshCache()
    {
        try {
            @ini_set("memory_limit","1024M");
            set_time_limit(0);
            session_write_close();
            if (!isset($this->result['cache_name']) || !$this->result['cache_name']){
                throw new \Exception('刷新缓存名称不能为空');
            }
            $cache_num = self::refreshStatic($this->result['cache_name']);
            $res = [
                'code' => 1,
                'data' => $cache_num
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ];
        }
        return parent::ajaxReturn($res);
    }
    /**
     * 更新静态数据
     */
    private function updateStatic()
    {
        $data = [];
        $nodesList = (new SysNodes())->select()->toArray();
        Cache::set('nodes', $nodesList);
        $data[] = ['nodes' => count($nodesList)];
        $rolesList = (new SysRoles())->where('disabled',0)->select()->toArray();
        Cache::set('roles', $rolesList);
        $data[] = ['roles' => count($rolesList)];
        $regionList = (new SysRegion())->where('disabled',0)->select()->toArray();
        Cache::set('region', $regionList);
        $data[] = ['region' => count($regionList)];
        $schoolList = (new Schools())->where('disabled',0)->select()->toArray();
        Cache::set('school', $schoolList);
        $data[] = ['school' => count($schoolList)];
        $centralList = (new CentralSchool())->where('disabled',0)->select()->toArray();
        Cache::set('central', $centralList);
        $data[] = ['central' => count($centralList)];
        $departmentList = (new Department())->where('disabled',0)->select()->toArray();
        Cache::set('department', $departmentList);
        $data[] = ['department' => count($departmentList)];
        $dictionaryList = (new SysDictionary())->select()->toArray();
        Cache::set('dictionary', $dictionaryList);
        $data[] = ['dictionary' => count($dictionaryList)];
        $manageList = (new Manage())->where('disabled',0)->select()->toArray();
        Cache::set('manage', $manageList);
        $data[] = ['manage' => count($manageList)];
        $policeList = (new PoliceStation())->select()->toArray();
        Cache::set('police', $policeList);
        $data[] = ['police' => count($policeList)];
        $moduleList = (new Module())->select()->where('disabled',0)->toArray();
        Cache::set('module', $moduleList);
        $data[] = ['module' => count($moduleList)];
        $costList = (new SysCostCategory())->select()->where('disabled',0)->toArray();
        Cache::set('cost', $costList);
        $data[] = ['cost' => count($costList)];
        $birthplaceList = (new SysAddressBirthplace())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
        Cache::set('birthplace', $birthplaceList);
        $data[] = ['birthplace' => count($birthplaceList)];
        $intactList = (new SysAddressIntact())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
        Cache::set('intact', $intactList);
        $data[] = ['intact' => count($intactList)];
        $regionStandardList = (new SysRegionStandard())->where('disabled',0)->select()->toArray();
        Cache::set('standard', $regionStandardList);
        $data[] = ['standard' => count($regionStandardList)];
        $messageList = (new SysMessage())->select()->toArray();
        Cache::set('sysmessage', $messageList);
        $data[] = ['sysmessage' => count($messageList)];
        $assignsetList = (new AssignmentSetting())->select()->toArray();
        Cache::set('assignset', $assignsetList);
        $data[] = ['assignset' => count($assignsetList)];
        $courseList = (new Course())->where('disabled',0)->select()->toArray();
        Cache::set('course', $courseList);
        $data[] = ['course' => count($courseList)];
        $planList = (new Plan())->select()->toArray();
        Cache::set('plan', $planList);
        $data[] = ['plan' => count($planList)];
        $policyList = (new Policy())->select()->toArray();
        Cache::set('policy', $policyList);
        $data[] = ['policy' => count($policyList)];
        $regionageList = (new RegionSetTime())->select()->toArray();
        Cache::set('regionage', $regionageList);
        $data[] = ['regionage' => count($regionageList)];

        return $data;
    }
    /**
     * 刷新指定静态数据
     */
    private function refreshStatic($cache_name)
    {
        $data = 0;
        switch ($cache_name) {
            case "nodes" :
                $nodesList = (new SysNodes())->select()->toArray();
                Cache::set('nodes', $nodesList);
                $data = count($nodesList);
                break;
            case "roles" :
                $rolesList = (new SysRoles())->where('disabled',0)->select()->toArray();
                Cache::set('roles', $rolesList);
                $data = count($rolesList);
                break;
            case "region" :
                $regionList = (new SysRegion())->where('disabled',0)->select()->toArray();
                Cache::set('region', $regionList);
                $data = count($regionList);
                break;
            case "school" :
                $schoolList = (new Schools())->where('disabled',0)->select()->toArray();
                Cache::set('school', $schoolList);
                $data = count($schoolList);
                break;
            case "central" :
                $centralList = (new CentralSchool())->where('disabled',0)->select()->toArray();
                Cache::set('central', $centralList);
                $data = count($centralList);
                break;
            case "department" :
                $departmentList = (new Department())->where('disabled',0)->select()->toArray();
                Cache::set('department', $departmentList);
                $data = count($departmentList);
                break;
            case "dictionary" :
                $dictionaryList = (new SysDictionary())->select()->toArray();
                Cache::set('dictionary', $dictionaryList);
                $data = count($dictionaryList);
                break;
            case "manage" :
                $manageList = (new Manage())->where('disabled',0)->select()->toArray();
                Cache::set('manage', $manageList);
                $data = count($manageList);
                break;
            case "police" :
                $policeList = (new PoliceStation())->select()->toArray();
                Cache::set('police', $policeList);
                $data = count($policeList);
                break;
            case "module" :
                $moduleList = (new Module())->select()->where('disabled',0)->toArray();
                Cache::set('module', $moduleList);
                $data = count($moduleList);
                break;
            case "cost" :
                $costList = (new SysCostCategory())->select()->where('disabled',0)->toArray();
                Cache::set('cost', $costList);
                $data = count($costList);
                break;
            case "birthplace" :
                $birthplaceList = (new SysAddressBirthplace())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
                Cache::set('birthplace', $birthplaceList);
                $data = count($birthplaceList);
                break;
            case "intact" :
                $intactList = (new SysAddressIntact())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
                Cache::set('intact', $intactList);
                $data = count($intactList);
                break;
//            case "420626" :
//                $baokangList = (new AddressBaoKang())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420626', $baokangList);
//                $data = count($baokangList);
//                break;
//            case "420608" :
//                $dongjinList = (new AddressDongJin())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420608', $dongjinList);
//                $data = count($dongjinList);
//                break;
//            case "420606" :
//                $fanchengList = (new AddressFanCheng())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420606', $fanchengList);
//                $data = count($fanchengList);
//                break;
//            case "420685" :
//                $gaoxinList = (new AddressGaoXin())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420685', $gaoxinList);
//                $data = count($gaoxinList);
//                break;
//            case "420625" :
//                $guchengList = (new AddressGuCheng())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420625', $guchengList);
//                $data = count($guchengList);
//                break;
//            case "420682" :
//                $laohekouList = (new AddressLaoHeKou())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420682', $laohekouList);
//                $data = count($laohekouList);
//                break;
//            case "420624" :
//                $nanzhangList = (new AddressNanZhang())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420624', $nanzhangList);
//                $data = count($nanzhangList);
//                break;
//            case "420602" :
//                $xiangchengList = (new AddressXiangCheng())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420602', $xiangchengList);
//                $data = count($xiangchengList);
//                break;
//            case "420607" :
//                $xiangzhouList = (new AddressXiangZhou())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420607', $xiangzhouList);
//                $data = count($xiangzhouList);
//                break;
//            case "420684" :
//                $yichengList = (new AddressYiCheng())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420684', $yichengList);
//                $data = count($yichengList);
//                break;
//            case "420683" :
//                $zhaoyangList = (new AddressZaoYang())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420683', $zhaoyangList);
//                $data = count($zhaoyangList);
//                break;
            case "standard" :
                $regionStandardList = (new SysRegionStandard())->where('disabled',0)->select()->toArray();
                Cache::set('standard', $regionStandardList);
                $data = count($regionStandardList);
                break;
            case "sysmessage" :
                $messageList = (new SysMessage())->select()->toArray();
                Cache::set('sysmessage', $messageList);
                $data = count($messageList);
                break;
            case "assignset" :
                $assignsetList = (new AssignmentSetting())->select()->toArray();
                Cache::set('assignset', $assignsetList);
                $data = count($assignsetList);
                break;
            case "course" :
                $courseList = (new Course())->where('disabled',0)->select()->toArray();
                Cache::set('course', $courseList);
                $data = count($courseList);
                break;
            case "plan" :
                $planList = (new Plan())->select()->toArray();
                Cache::set('plan', $planList);
                $data = count($planList);
                break;
            case "policy" :
                $policyList = (new Policy())->select()->toArray();
                Cache::set('policy', $policyList);
                $data = count($policyList);
                break;
            case "regionage" :
                $regionageList = (new RegionSetTime())->select()->toArray();
                Cache::set('regionage', $regionageList);
                $data = count($regionageList);
                break;
            default:
        }

        return $data;
    }
}