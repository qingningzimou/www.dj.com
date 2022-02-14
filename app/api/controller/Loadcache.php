<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 0:57
 */
namespace app\api\controller;

use think\facade\Cache;
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

use app\common\controller\RedisLock;
use think\facade\Lang;
use think\response\Json;
use think\facade\Log;
use think\facade\Config;
class LoadCache
{
    public function setCache()
    {
        try {
            ignore_user_abort(true);
            set_time_limit(0);
            (new RedisLock ())->lock(Config::get('cache.stores.redis.prefix').'load_cache_lock', $expire = 5, $num = 0);
            if (!Cache::has('nodes')) {
                $nodesList = (new SysNodes())->select()->toArray();
                Cache::set('nodes', $nodesList);
            }
//            if (!Cache::has('user_nodes')) {
//                $userNodesList = (new SysUserNodes())->select()->toArray();
//                Cache::set('user_nodes', $userNodesList);
//            }
            if (!Cache::has('roles')) {
                $rolesList = (new SysRoles())->where('disabled',0)->select()->toArray();
                Cache::set('roles', $rolesList);
            }
            if (!Cache::has('region')) {
                $regionList = (new SysRegion())->where('disabled',0)->select()->toArray();
                Cache::set('region', $regionList);
            }
            if (!Cache::has('department')) {
                $departmentList = (new Department())->where('disabled',0)->select()->toArray();
                Cache::set('department', $departmentList);
            }
            if (!Cache::has('dictionary')) {
                $dictionaryList = (new SysDictionary())->select()->toArray();
                Cache::set('dictionary', $dictionaryList);
            }
            if (!Cache::has('manage')) {
                $manageList = (new Manage())->where('disabled',0)->select()->toArray();
                Cache::set('manage', $manageList);
            }
            if (!Cache::has('school')) {
                $schoolList = (new Schools())->where('disabled',0)->select()->toArray();
                Cache::set('school', $schoolList);
            }
            if (!Cache::has('central')) {
                $centralList = (new CentralSchool())->where('disabled',0)->select()->toArray();
                Cache::set('central', $centralList);
            }
            if (!Cache::has('police')) {
                $policeList = (new PoliceStation())->select()->toArray();
                Cache::set('police', $policeList);
            }
            if (!Cache::has('module')) {
                $moduleList = (new Module())->select()->where('disabled',0)->toArray();
                Cache::set('module', $moduleList);
            }
            if (!Cache::has('cost')) {
                $costList = (new SysCostCategory())->select()->where('disabled',0)->toArray();
                Cache::set('cost', $costList);
            }
            if (!Cache::has('birthplace')) {
                $birthplaceList = (new SysAddressBirthplace())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
                Cache::set('birthplace', $birthplaceList);
            }
            if (!Cache::has('intact')) {
                $intactList = (new SysAddressIntact())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
                Cache::set('intact', $intactList);
            }
//            if (!Cache::has('420626')) {
//                $baokangList = (new AddressBaoKang())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420626', $baokangList);
//            }
//            if (!Cache::has('420608')) {
//                $dongjinList = (new AddressDongJin())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420608', $dongjinList);
//            }
//            if (!Cache::has('420606')) {
//                $fanchengList = (new AddressFanCheng())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420606', $fanchengList);
//            }
//            if (!Cache::has('420685')) {
//                $gaoxinList = (new AddressGaoXin())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420685', $gaoxinList);
//            }
//            if (!Cache::has('420625')) {
//                $guchengList = (new AddressGuCheng())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420625', $guchengList);
//            }
//            if (!Cache::has('420682')) {
//                $laohekouList = (new AddressLaoHeKou())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420682', $laohekouList);
//            }
//            if (!Cache::has('420624')) {
//                $nanzhangList = (new AddressNanZhang())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420624', $nanzhangList);
//            }
//            if (!Cache::has('420602')) {
//                $xiangchengList = (new AddressXiangCheng())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420602', $xiangchengList);
//            }
//            if (!Cache::has('420607')) {
//                $xiangzhouList = (new AddressXiangZhou())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420607', $xiangzhouList);
//            }
//            if (!Cache::has('420684')) {
//                $yichengList = (new AddressYiCheng())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420684', $yichengList);
//            }
//            if (!Cache::has('420683')) {
//                $zhaoyangList = (new AddressZaoYang())->where('primary_school_id','>',0)->whereOr('middle_school_id','>',0)->select()->toArray();
//                Cache::set('420683', $zhaoyangList);
//            }
            if (!Cache::has('standard')) {
                $regionStandardList = (new SysRegionStandard())->where('disabled',0)->select()->toArray();
                Cache::set('standard', $regionStandardList);
            }
            if (!Cache::has('sysmessage')) {
                $messageList = (new SysMessage())->select()->toArray();
                Cache::set('sysmessage', $messageList);
            }
            if (!Cache::has('assignset')) {
                $assignsetList = (new AssignmentSetting())->select()->toArray();
                Cache::set('assignset', $assignsetList);
            }
            if (!Cache::has('course')) {
                $courseList = (new Course())->where('disabled',0)->select()->toArray();
                Cache::set('course', $courseList);
            }
            if (!Cache::has('plan')) {
                $planList = (new Plan())->select()->toArray();
                Cache::set('plan', $planList);
            }
            if (!Cache::has('policy')) {
                $policyList = (new Policy())->select()->toArray();
                Cache::set('policy', $policyList);
            }
            if (!Cache::has('regionage')) {
                $regionageList = (new RegionSetTime())->select()->toArray();
                Cache::set('regionage', $regionageList);
            }
            //打开redis的锁定状态
            (new RedisLock ())->unlock(Config::get('cache.stores.redis.prefix').'load_cache_lock') ;
            return json([
                'code' => 1,
                'msg' => Lang::get('res_success')
            ]);
        } catch (\Exception $exception) {
            return json([
                'code' => 0,
                'msg' => $exception->getMessage() ?: Lang::get('system_error')
            ]);
        }
    }

}