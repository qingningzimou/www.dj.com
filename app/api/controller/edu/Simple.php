<?php


namespace app\api\controller\edu;

use app\common\controller\Education;
use app\common\model\Schools;
use app\common\model\SysAddressSimple as model;
use app\common\model\SysRegion;
use app\common\validate\house\Simple as validate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;


class Simple extends Education
{
    /**
     * 缩略地址列表
     * @return \think\response\Json
     */
    public function getList()
    {
        try {
            $where = [];
            if (isset($this->userInfo['region_id']) && $this->userInfo['region_id'] > 1) {
                $where[] = ['simple.region_id', '=', $this->userInfo['region_id']];
            }else{
                throw new \Exception('学校管理员所属区县设置错误');
            }
            if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0 ) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('学校管理员学校ID设置错误');
            }

            if ($this->request->has('search') && $this->result['search'] != '') {
                $where[] = ['simple.address', 'like', '%' . $this->result['search'] . '%'];
            }
            $is_select = -1;
            if ($this->request->has('is_select') && $this->result['is_select'] != '') {
                $is_select = intval($this->result['is_select']);
            }
            $check_all_status = -1;
            if ($this->request->has('check_all_status') && $this->result['check_all_status'] != '') {
                $check_all_status = intval($this->result['check_all_status']);
            }

            $schoolInfo = Db::name('SysSchool')->field('id, region_id, school_type')->find($school_id);
            $regionInfo = (new SysRegion())->field('id, simple_code')->find($this->userInfo['region_id']);
            if(!$regionInfo){
                throw new \Exception('未找到区县');
            }
            if ($schoolInfo['region_id'] != $this->userInfo['region_id']) {
                throw new \Exception('管理员区县ID和学校区县ID不一样');
            }

            /*$res_manage = $this->getSchoolMainAccountIds();
            if($res_manage['code'] == 0){
                throw new \Exception($res_manage['msg']);
            }
            $manage_ids = $res_manage['manage_ids'];*/

            //usleep(300000);//延时300毫秒

            /*$list = Db::name('sys_address_simple')->alias('simple')
                ->join(['deg_sys_region' => 'region'], 'region.id = simple.region_id');*/

            $where_status = [];
            $address_where = [];
            switch ($schoolInfo['school_type']) {
                case 1:
                    //本校认领
                    if($is_select == 1){
                        $where[] = ['simple.primary_school_id', '=', $schoolInfo['id']];
                    }
                    //已认领（全部）
                    if($is_select == 2){
                        $where[] = ['simple.primary_school_id', '>', 0];
                    }
                    //未认领
                    if($is_select == 3){
                        $where[] = ['simple.primary_school_id', '<=', 0];
                    }
                    if($check_all_status == 1){
                        $where_status = ['simple.sub_num', '=', 'simple.primary_school_num'];
                    }
                    if($check_all_status === 0){
                        $where_status = ['simple.sub_num', '<>', 'simple.primary_school_num'];
                    }

                    /*$list = $list->join([
                            'deg_sys_school' => 'primary_school'
                        ], 'primary_school.id = simple.primary_school_id', 'left')
                        ->join([
                            'deg_manage' => 'primary_admin'
                        ], 'primary_admin.school_id = simple.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ') and primary_admin.deleted = 0', 'left')
                        ->field([
                            'simple.id',
                            'region.region_name' => 'region_name',
                            'simple.address',
                            'simple.sub_num',
                            'simple.primary_school_num' => 'check_num',
                            'IF(simple.sub_num <> simple.primary_school_num, "有未认领地址","")' => 'claim_status',
                            'primary_school.school_name' => 'school_name',
                            'primary_admin.real_name' => 'admin_name',
                            'primary_admin.user_name' => 'admin_mobile',
                        ]);*/

                    $address_where[] = ['primary_school_id', '=', $schoolInfo['id']];
                    break;
                case 2:
                    if($is_select == 1){
                        $where[] = ['simple.middle_school_id', '=', $schoolInfo['id']];
                    }
                    if($is_select == 2){
                        $where[] = ['simple.middle_school_id', '>', 0];
                    }
                    if($is_select == 3){
                        $where[] = ['simple.middle_school_id', '<=', 0];
                    }
                    if($check_all_status == 1){
                        $where_status = ['simple.sub_num', '=', 'simple.middle_school_num'];
                    }
                    if($check_all_status === 0){
                        $where_status = ['simple.sub_num', '<>', 'simple.middle_school_num'];
                    }

                    /*$list = $list->join([
                        'deg_sys_school' => 'middle_school'
                    ], 'middle_school.id = simple.middle_school_id', 'left')
                        ->join([
                            'deg_manage' => 'middle_admin'
                        ], 'middle_admin.school_id = simple.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ') and middle_admin.deleted = 0', 'left')
                        ->field([
                            'simple.id',
                            'region.region_name' => 'region_name',
                            'simple.address',
                            'simple.sub_num',
                            'simple.middle_school_num' => 'check_num',
                            'IF(simple.sub_num <> simple.middle_school_num, "有未认领地址","")' => 'claim_status',
                            'middle_school.school_name' => 'school_name',
                            'middle_admin.real_name' => 'admin_name',
                            'middle_admin.user_name' => 'admin_mobile'
                        ]);*/

                    $address_where[] = ['middle_school_id', '=', $schoolInfo['id']];
                    break;
            }

            $list = (new model())->alias('simple')
                ->field([
                    'id',
                    'address',
                    'region_id',
                    'sub_num',
                    'primary_school_num',
                    'middle_school_num',
                    'primary_school_id',
                    'middle_school_id',
                ])
                ->where($where);

            if($where_status){
                $list = $list->whereColumn($where_status[0], $where_status[1], $where_status[2]);
            }
            $list = $list->master(true)->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

            $school = Cache::get('school');
            $region = Cache::get('region');
            $manage = Cache::get('manage');
            //禁用学校
            $schoolList = Db::name('SysSchool')->where('disabled',1)->where('deleted',0)->select()->toArray();

            foreach($list['data'] as $key => &$val){
                $val['region_name'] = '';
                $val['check_num'] = 0;
                $val['claim_status'] = '';
                $val['school_name'] = '';
                $val['admin_name'] = '';
                $val['admin_mobile'] = '';

                if (!empty($val['region_id'])){
                    $regionData = filter_value_one($region, 'id', $val['region_id']);
                    if (count($regionData) > 0) {
                        $val['region_name'] = $regionData['region_name'];
                    }
                }

                switch ($schoolInfo['school_type']) {
                    case 1:
                        if($val['primary_school_id'] > 0){
                            $schoolData = filter_value_one($school, 'id', $val['primary_school_id']);
                            if(!$schoolData){
                                $schoolData = filter_value_one($schoolList, 'id', $val['primary_school_id']);
                            }
                            if (count($schoolData) > 0) {
                                $val['school_name'] = $schoolData['school_name'];
                                /*$manageData = filter_value_one($manage, 'school_id', $val['primary_school_id']);
                                if (count($manageData) > 0) {
                                    $val['admin_name'] = $manageData['real_name'];
                                    $val['admin_mobile'] = $manageData['user_name'];
                                }*/
                                $manageData = $this->getSchoolMainAccount($manage, $val['primary_school_id']);
                                $val['admin_name'] = $manageData['admin_name'];
                                $val['admin_mobile'] = $manageData['admin_mobile'];
                            }
                        }
                        $val['check_num'] = $val['primary_school_num'];
                        if($val['sub_num'] != $val['primary_school_num']){
                            $val['claim_status'] = '有未认领地址';
                        }
                        break;

                    case 2:
                        if($val['middle_school_id'] > 0){
                            $schoolData = filter_value_one($school, 'id', $val['middle_school_id']);
                            if(!$schoolData){
                                $schoolData = filter_value_one($schoolList, 'id', $val['middle_school_id']);
                            }
                            if (count($schoolData) > 0) {
                                $val['school_name'] = $schoolData['school_name'];
                                /*$manageData = filter_value_one($manage, 'school_id', $val['middle_school_id']);
                                if (count($manageData) > 0) {
                                    $val['admin_name'] = $manageData['real_name'];
                                    $val['admin_mobile'] = $manageData['user_name'];
                                }*/
                                $manageData = $this->getSchoolMainAccount($manage, $val['middle_school_id']);
                                $val['admin_name'] = $manageData['admin_name'];
                                $val['admin_mobile'] = $manageData['admin_mobile'];
                            }
                        }
                        $val['check_num'] = $val['middle_school_num'];
                        if($val['sub_num'] != $val['middle_school_num']){
                            $val['claim_status'] = '有未认领地址';
                        }
                        break;
                }
            }

            $address_where[] = ['deleted', '=', 0];
            $address_total = Db::name("sys_address_{$regionInfo['simple_code']}")->where($address_where)->count();
            $list['address_total'] = $address_total;

            $res = [
                'code' => 1,
                'data' => $list
            ];
        } catch (\Exception $exception) {
            $res = [
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ];
        }
        return parent::ajaxReturn($res);
    }

    /**
     * 缩略地址详情
     * @return \think\response\Json
     */
    public function getInfo()
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                if (isset($this->userInfo['school_id']) && $this->userInfo['school_id'] > 0 ) {
                    $school_id = $this->userInfo['school_id'];
                }else{
                    throw new \Exception('学校管理员学校ID设置错误');
                }
                $school = Schools::field('id, school_type')->find($school_id);
                //  验证器验证请求的数据是否合法
                $checkData = parent::checkValidate(['id' => $this->result['id']], validate::class, 'info');
                //  如果数据不合法，则返回
                if ($checkData['code'] != 1) {
                    throw new \Exception($checkData['msg']);
                }
                $info = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();
                if (!$info) {
                    throw new \Exception('找不到缩略地址信息');
                }
                $where[] = ['address.simple_id', '=', $this->result['id']];
                $region = (new SysRegion())->field('id, simple_code')->find($info['region_id']);
                if(!$region){
                    throw new \Exception('未找到区县');
                }
                if ($this->request->has('search') && $this->result['search'] != '') {
                    $where[] = ['address.address', 'like', '%' . $this->result['search'] . '%'];
                }
                $is_select = -1;
                if ($this->request->has('is_select') && $this->result['is_select'] != '') {
                    $is_select = intval($this->result['is_select']);
                }

                /*$res_manage = $this->getSchoolMainAccountIds();
                if($res_manage['code'] == 0){
                    throw new \Exception($res_manage['msg']);
                }
                $manage_ids = $res_manage['manage_ids'];*/

                $field = [];
                $list = Db::table("deg_sys_address_{$region['simple_code']}")->alias('address');
                switch ($school['school_type']) {
                    case 1:
                        $field = [
                            'address.*',
                            'primary_school.school_name' => 'school_name',
                            //'primary_admin.real_name' => 'admin_name',
                            //'primary_admin.user_name' => 'admin_mobile',
                        ];
                        if($is_select == 1){
                            $where[] = ['address.primary_school_id', '=', $school['id']];
                        }
                        if($is_select == 2){
                            $where[] = ['address.primary_school_id', '>', '0'];
                        }
                        if($is_select == 3){
                            $where[] = ['address.primary_school_id', '<=', '0'];
                        }

                        $list = $list->join([
                                'deg_sys_school' => 'primary_school'
                            ], 'primary_school.id = address.primary_school_id', 'left');
                            /*->join([
                                'deg_manage' => 'primary_admin'
                            ], 'primary_admin.school_id = address.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ')', 'left');*/
                        break;
                    case 2:
                        $field = [
                            'address.*',
                            'middle_school.school_name' => 'school_name',
                            //'middle_admin.real_name' => 'admin_name',
                            //'middle_admin.user_name' => 'admin_mobile',
                        ];
                        if($is_select == 1){
                            $where[] = ['address.middle_school_id', '=', $school['id']];
                        }
                        if($is_select == 2){
                            $where[] = ['address.middle_school_id', '>', 0];
                        }
                        if($is_select == 3){
                            $where[] = ['address.middle_school_id', '<=', 0];
                        }
                        $list = $list->join([
                                'deg_sys_school' => 'middle_school'
                            ], 'middle_school.id = address.middle_school_id', 'left');
                            /*->join([
                                'deg_manage' => 'middle_admin'
                            ], 'middle_admin.school_id = address.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ')', 'left');*/
                        break;
                }

                //$where[] = ['address.address', 'like', '%' . $this->request->param('search') . '%'];
                $list = $list
                    ->field($field)
                    ->where($where)
                    //->whereLike('address.address', "{$info['address']}%")
                    ->order('address.id', 'ASC')
                    ->paginate(['list_rows' => $this->pageSize, 'var_page' => 'curr'])->toArray();

                $manage = Cache::get('manage');
                foreach($list['data'] as $key => &$val){
                    $val['admin_name'] = '';
                    $val['admin_mobile'] = '';

                    switch ($school['school_type']) {
                        case 1:
                            if($val['primary_school_id'] > 0){
                                if($val['school_name']) {
                                    $manageData = $this->getSchoolMainAccount($manage, $val['primary_school_id']);
                                    $val['admin_name'] = $manageData['admin_name'];
                                    $val['admin_mobile'] = $manageData['admin_mobile'];
                                }
                            }
                            break;

                        case 2:
                            if($val['middle_school_id'] > 0){

                                if($val['school_name']) {
                                    $manageData = $this->getSchoolMainAccount($manage, $val['middle_school_id']);
                                    $val['admin_name'] = $manageData['admin_name'];
                                    $val['admin_mobile'] = $manageData['admin_mobile'];
                                }
                            }
                            break;
                    }
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
     * 认领预先判断
     * @return \think\response\Json
     */
    public function actPrejudge()
    {
        if ($this->request->isPost()) {
            try {
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员学校ID设置错误');
                }
                if ( $this->userInfo['region_id'] > 0 ) {
                    $region_id = $this->userInfo['region_id'];
                }else{
                    throw new \Exception('管理员所属区县ID设置错误');
                }

                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选缩略地址');
                }

                $region = SysRegion::field('id, simple_code')->find($region_id);
                $school = Schools::field('id, school_type')->find($school_id);

                $simple_where = [];
                $simple_where[] = ['id', 'in', $id_array];
                $simple_where[] = ['deleted', '=', 0];

                $exist = [];
                $address_where = [];
                switch ($school['school_type']) {
                    case 1:
                        $exist = Db::name('SysAddressSimple')->where($simple_where)
                            ->where([['primary_school_id', '>', 0], ['primary_school_id', '<>', $school_id] ])->findOrEmpty();
                        $address_where[] = ['primary_school_id', '>', 0];
                        $address_where[] = ['primary_school_id', '<>', $school_id];
                        break;
                    case 2:
                        $exist = Db::name('SysAddressSimple')->where($simple_where)
                            ->where([['middle_school_id', '>', 0], ['middle_school_id', '<>', $school_id] ])->findOrEmpty();
                        $address_where[] = ['middle_school_id', '>', 0];
                        $address_where[] = ['middle_school_id', '<>', $school_id];
                        break;
                }

                $exist_address = [];
                if(!$exist) {
                    $address_model_name = $this->getModelNameByCode($region['simple_code']);
                    if ($address_model_name == '') {
                        throw new \Exception('完整地址model名称获取失败');
                    }
                    $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
                    $address_model = new $address_model_name();
                    foreach ((array)$id_array as $id) {
                        $address_where[] = ['simple_id', '=', $id];
                        $address_where[] = ['deleted', '=', 0];
                        $exist_address = $address_model->where($address_where)->find();
                        if($exist_address) break;
                    }
                }

                $res = [
                    'code' => 1,
                    'id' => $ids,
                    'msg' => '确认认领勾选地址？'
                ];

                if ($exist) {
                    $res = [
                        'code' => 10,
                        'id' => $ids,
                        'msg' => '选择的房产地址已被别的学校认领，请与学校联系并进行确认！'
                    ];
                }
                if ($exist_address) {
                    $res = [
                        'code' => 10,
                        'id' => $ids,
                        'msg' => '选择的房产地址已被别的学校认领，请与学校联系并进行确认！'
                    ];
                }


            } catch (\Exception $exception) {
                $res = [
                    'code' => $exception->getCode(),
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }else{}
    }

    /**
     * 学校确认认领
     * @param id 缩略地址勾选ID
     * @return mixed
     */
    public function actSave()
    {
        if ($this->request->isPost()) {
            try {
                //开始事务
                Db::startTrans();

                if (isset($this->userInfo['region_id'])) {
                    $region_id = $this->userInfo['region_id'];
                } else {
                    throw new \Exception('管理员所属区域为空');
                }
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员没有关联学校ID');
                }

                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选缩略地址');
                }
                $restrictions = false;

                $region = SysRegion::field('id, simple_code')->find($region_id);
                $school = Schools::field('id, school_type')->find($school_id);

                $simple_where = [];
                $simple_where[] = ['id', 'in', $id_array];
                $simple_where[] = ['deleted', '=', 0];

                $update = [];
                $_address_update = [];
                $_address_where = [];
                switch ($school['school_type']) {
                    case 1:
                        $_address_update['primary_school_id'] = $school['id'];

                        $update['primary_school_id'] = $school['id'];
                        $update['primary_school_num'] = Db::raw('sub_num');
                        //$update['primary_manager_id'] = $this->userInfo['manage_id'];
                        //强制覆盖已认领地址
                        if ($restrictions) {
                            $simple_where[] = ['primary_school_id', '<=', 0];
                            $_address_where = ['primary_school_id', '<=', 0];
                        }
                        //$sql = "UPDATE deg_sys_address_simple SET primary_school_num = sub_num WHERE id in (" . $ids . ")";
                        break;
                    case 2:
                        $_address_update['middle_school_id'] = $school['id'];

                        $update['middle_school_id'] = $school['id'];
                        $update['middle_school_num'] = Db::raw('sub_num');
                        //$update['middle_manager_id'] = $this->userInfo['manage_id'];
                        //强制覆盖已认领地址
                        if ($restrictions) {
                            $simple_where[] = ['middle_school_id', '<=', 0];
                            $_address_where = ['middle_school_id', '<=', 0];
                        }
                        //$sql = "UPDATE deg_sys_address_simple SET middle_school_num = sub_num WHERE id in (" . $ids . ")";
                        break;
                }

                //Db::execute($sql);
                $simple_res = (new model())->editData($update, $simple_where);
                if ($simple_res['code'] == 0) {
                    throw new \Exception($simple_res['msg']);
                }

                $address_model_name = $this->getModelNameByCode($region['simple_code']);
                if ($address_model_name == '') {
                    throw new \Exception('完整地址model名称获取失败');
                }
                $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
                foreach ((array)$id_array as $id) {
                    //$addressInfo = model::field('id,address')->find($id);
                    $address_where = [];
                    //$address_where[] = ['address', 'like', "{$addressInfo['address']}%"];
                    $address_where[] = ['simple_id', '=', $id];
                    $address_where[] = ['deleted', '=', 0];
                    if ($restrictions) {
                        $address_where[] = $_address_where;
                    }
                    $address_model = new $address_model_name();
                    $address_res = $address_model->editData($_address_update, $address_where);
                    if ($address_res['code'] == 0) {
                        throw new \Exception($address_res['msg']);
                    }
                }

                //房产统计
                $result = $this->getAddressStatistics($school_id);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];

                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => $exception->getCode(),
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }else{}
    }

    /**
     * 取消认领
     * @param id 缩略地址勾选ID
     * @return \think\response\Json
     */
    public function actCancel()
    {
        try {
            //开始事务
            Db::startTrans();

            if (isset($this->userInfo['region_id'])) {
                $region_id = $this->userInfo['region_id'];
            }else{
                throw new \Exception('管理员所属区域为空');
            }
            if (isset($this->userInfo['school_id'])) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }

            $region = SysRegion::field('id, simple_code')->find($region_id);
            $school = Schools::field('id, school_type')->find($school_id);

            $update = [];
            $where = [];
            $_address_where = [];
            $_address_update = [];
            switch ($school['school_type']) {
                case 1:
                    $where[] = ['primary_school_id', '=', $school['id']];
                    $update['primary_school_id'] = 0;
                    //$update['primary_school_num'] = 0;

                    $_address_where = ['primary_school_id', '=', $school['id']];
                    $_address_update['primary_school_id'] = 0;
                    //$update['primary_manager_id'] = 0;
                    break;
                case 2:
                    $where[] = ['middle_school_id', '=', $school['id']];
                    $update['middle_school_id'] = 0;
                    //$update['middle_school_num'] = 0;

                    $_address_where[] = ['middle_school_id', '=', $school['id']];
                    $_address_update['middle_school_id'] = 0;
                    //$update['middle_manager_id'] = 0;
                    break;
            }
            $ids = $this->request->param('id');
            $id_array = explode(',', $ids);
            if(count($id_array) == 0){
                throw new \Exception('请勾选缩略地址');
            }

            $where[] = ['id', 'in', $id_array];
            $where[] = ['deleted', '=', 0];
            $simple_res = (new model())->editData($update, $where);
            if($simple_res['code'] == 0){
                throw new \Exception($simple_res['msg']);
            }

            $address_model_name = $this->getModelNameByCode($region['simple_code']);
            if($address_model_name == ''){
                throw new \Exception('完整地址model名称获取失败');
            }
            $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
            foreach ((array)$id_array as $id) {
                //$addressInfo = model::field('id,address')->find($id);
                $address_where = [];
                //$address_where[] = ['address', 'like', "{$addressInfo['address']}%"];
                $address_where[] = ['simple_id', '=', $id];
                $address_where[] = $_address_where;
                $address_where[] = ['deleted', '=', 0];
                $address_model = new $address_model_name();
                $address_res = $address_model->editData($_address_update, $address_where);
                if($address_res['code'] == 0){
                    throw new \Exception($address_res['msg']);
                }
            }

            foreach ((array)$id_array as $simple_id) {
                $simple = [];
                $simple['id'] = $simple_id;
                $address_model = new $address_model_name();
                switch ($school['school_type']) {
                    case 1:
                        //总勾选的详细地址
                        $check_count = $address_model
                            ->where('primary_school_id', '>', 0)->where('deleted', 0)
                            ->where('simple_id', '=', $simple_id)->count();
                        $simple['primary_school_num'] = $check_count;
                        if ($check_count == 0) {
                            $simple['primary_school_id'] = 0;
                        }
                        //详细地址还有别的学校勾选的情况
                        if ($check_count > 0) {
                            //缩略地址更改为勾选最多的
                            $address_school = $address_model->field(['COUNT(*)' => 'total', 'primary_school_id'])
                                ->where('simple_id', $simple_id)->where('deleted', 0)
                                ->where('primary_school_id', '>', 0)->group('primary_school_id')
                                ->order('total', 'DESC')->find();
                            if ($address_school) {
                                $simple['primary_school_id'] = $address_school['primary_school_id'];
                            }
                        }
                        break;
                    case 2:
                        //总勾选的详细地址
                        $check_count = $address_model
                            ->where('middle_school_id', '>', 0)->where('deleted', 0)
                            ->where('simple_id', '=', $simple_id)->count();
                        $simple['middle_school_num'] = $check_count;

                        if ($check_count == 0) {
                            $simple['middle_school_id'] = 0;
                        }
                        //详细地址还有别的学校勾选的情况
                        if ( $check_count > 0) {
                            //缩略地址更改为勾选最多的
                            $address_school = $address_model->field(['COUNT(*)' => 'total', 'middle_school_id'])
                                ->where('simple_id', $simple_id)->where('deleted', 0)
                                ->where('middle_school_id', '>', 0)->group('middle_school_id')
                                ->order('total', 'DESC')->find();
                            if ($address_school) {
                                $simple['middle_school_id'] = $address_school['middle_school_id'];
                            }
                        }
                        break;
                }
                $result = (new model())->editData($simple);
                if($result['code'] == 0){
                    throw new \Exception($result['msg']);
                }
            }

            //房产统计
            $result = $this->getAddressStatistics($school_id);
            if ($result['code'] == 0) {
                throw new \Exception($result['msg']);
            }

            $res = [
                'code' => 1,
                'msg' => Lang::get('update_success')
            ];
            Db::commit();
        } catch (\Exception $exception) {
            $res = [
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ];
            Db::rollback();
        }
        return parent::ajaxReturn($res);
    }

    /**
     * 缩略详情认领预先判断
     * @return \think\response\Json
     */
    public function actAddressPrejudge()
    {
        if ($this->request->isPost()) {
            try {
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员学校ID设置错误');
                }
                /*if ( $this->userInfo['region_id'] > 0 ) {
                    $region_id = $this->userInfo['region_id'];
                }else{
                    throw new \Exception('管理员所属区县ID设置错误');
                }*/

                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选缩略地址');
                }

                $school = Schools::field('id, region_id, school_type')->find($school_id);
                $region = SysRegion::field('id, simple_code')->find($school['region_id']);

                $address_model_name = $this->getModelNameByCode($region['simple_code']);
                if ($address_model_name == '') {
                    throw new \Exception('完整地址model名称获取失败');
                }
                $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
                $address_model = new $address_model_name();

                $address_where = [];
                $address_where[] = ['id', 'in', $id_array];
                $address_where[] = ['deleted', '=', 0];

                switch ($school['school_type']) {
                    case 1:
                        $address_where[] = ['primary_school_id', '>', 0];
                        $address_where[] = ['primary_school_id', '<>', $school_id];
                        break;
                    case 2:
                        $address_where[] = ['middle_school_id', '>', 0];
                        $address_where[] = ['middle_school_id', '<>', $school_id];
                        break;
                }

                $exist = $address_model->where($address_where)->find();

                $res = [
                    'code' => 1,
                    'id' => $ids,
                    'msg' => '确认认领勾选地址？'
                ];

                if ($exist) {
                    $res = [
                        'code' => 10,
                        'id' => $ids,
                        'msg' => '选择的房产地址已被别的学校认领，请与学校联系并进行确认！'
                    ];
                }

            } catch (\Exception $exception) {
                $res = [
                    'code' => $exception->getCode(),
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }else{}
    }

    /**
     * 缩略详情确认认领
     * @param id 详情地址勾选ID
     * @return mixed
     */
    public function actAddressSave()
    {
        if ($this->request->isPost()) {
            try {
                //开始事务
                Db::startTrans();

                if (isset($this->userInfo['region_id'])) {
                    $region_id = $this->userInfo['region_id'];
                } else {
                    throw new \Exception('管理员所属区县ID设置错误');
                }
                if (isset($this->userInfo['school_id'])) {
                    $school_id = $this->userInfo['school_id'];
                } else {
                    throw new \Exception('管理员学校ID设置错误');
                }
                if ($this->request->has('simple_id') && $this->result['simple_id'] > 0) {
                    $simple_id = $this->result['simple_id'];
                }else{
                    throw new \Exception('缩略地址ID参数错误');
                }

                $ids = $this->request->param('id');
                $id_array = explode(',', $ids);
                if (count($id_array) == 0) {
                    throw new \Exception('请勾选缩略详细地址');
                }

                $region = SysRegion::field('id, simple_code')->find($region_id);
                $school = Schools::field('id, school_type')->find($school_id);

                $address_model_name = $this->getModelNameByCode($region['simple_code']);
                if ($address_model_name == '') {
                    throw new \Exception('完整地址model名称获取失败');
                }

                $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间
                $address_model = new $address_model_name();

                $update = [];
                switch ($school['school_type']) {
                    case 1:
                        $update['primary_school_id'] = $school['id'];
                        break;
                    case 2:
                        $update['middle_school_id'] = $school['id'];
                        break;
                }

                $where = [];
                $where[] = ['id', 'in', $id_array];
                $where[] = ['deleted', '=', 0];

                $address_res = $address_model->editData($update, $where);
                if ($address_res['code'] == 0) {
                    throw new \Exception($address_res['msg']);
                }

                $update = [];
                switch ($school['school_type']) {
                    case 1:
                        //勾选的详细地址
                        $check_count = $address_model
                            ->where('primary_school_id', '>', 0)
                            ->where('simple_id', '=', $simple_id)->count();
                        $update['primary_school_num'] = $check_count;
                        //缩略地址是否存学校ID
                        $simple_primary_school_id =(new model())->where('id', $simple_id)->value('primary_school_id');
                        if(!$simple_primary_school_id){
                            $update['primary_school_id'] = $school['id'];
                        }
                        break;
                    case 2:
                        //勾选的详细地址
                        $check_count = $address_model
                            ->where('middle_school_id', '>', 0)
                            ->where('simple_id', '=', $simple_id)->count();
                        $update['middle_school_num'] = $check_count;
                        //缩略地址是否存学校ID
                        $simple_middle_school_id =(new model())->where('id', $simple_id)->value('middle_school_id');
                        if(!$simple_middle_school_id){
                            $update['middle_school_id'] = $school['id'];
                        }
                        break;
                }

                $update['id'] = $simple_id;
                $simple_res = (new model())->editData($update);
                if($simple_res['code'] == 0){
                    throw new \Exception($simple_res['msg']);
                }

                //房产统计
                $result = $this->getAddressStatistics($school_id);
                if ($result['code'] == 0) {
                    throw new \Exception($result['msg']);
                }

                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];

                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => $exception->getCode(),
                    'msg' => $exception->getMessage()
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }else{}
    }

    /**
     * 缩略详情取消认领
     * @param id 缩略地址勾选ID
     * @return \think\response\Json
     */
    public function actAddressCancel()
    {
        try {
            //开始事务
            Db::startTrans();

            if (isset($this->userInfo['region_id'])) {
                $region_id = $this->userInfo['region_id'];
            }else{
                throw new \Exception('管理员所属区域为空');
            }
            if (isset($this->userInfo['school_id'])) {
                $school_id = $this->userInfo['school_id'];
            }else{
                throw new \Exception('管理员没有关联学校ID');
            }
            if ($this->request->has('simple_id') && $this->result['simple_id'] > 0) {
                $simple_id = $this->result['simple_id'];
            }else{
                throw new \Exception('缩略地址ID参数错误');
            }

            $region = SysRegion::field('id, simple_code')->find($region_id);
            $school = Schools::field('id, school_type')->find($school_id);

            $update = [];
            $where = [];
            switch ($school['school_type']) {
                case 1:
                    $where[] = ['primary_school_id', '=', $school['id']];
                    $update['primary_school_id'] = 0;

                    break;
                case 2:
                    $where[] = ['middle_school_id', '=', $school['id']];
                    $update['middle_school_id'] = 0;

                    break;
            }

            $ids = $this->request->param('id');
            $id_array = explode(',', $ids);
            if(count($id_array) == 0){
                throw new \Exception('请勾选缩略地址');
            }

            $address_model_name = $this->getModelNameByCode($region['simple_code']);
            if ($address_model_name == '') {
                throw new \Exception('完整地址model名称获取失败');
            }
            $address_model_name = 'app\common\model\\' . $address_model_name;//加上命名空间

            $where[] = ['id', 'in', $id_array];
            $where[] = ['deleted', '=', 0];

            $address_model = new $address_model_name();
            $address_res = $address_model->editData($update, $where);
            if ($address_res['code'] == 0) {
                throw new \Exception($address_res['msg']);
            }

            $model = new model();
            $update = [];
            $simple = $model->find($simple_id);
            switch ($school['school_type']) {
                case 1:
                    //总勾选的详细地址
                    $check_count = $address_model
                        ->where('primary_school_id', '>', 0)->where('deleted', 0)
                        ->where('simple_id', '=', $simple_id)->count();
                    $update['primary_school_num'] = $check_count;
                    if($check_count == 0) {
                        $update['primary_school_id'] = 0;
                    }
                    //本校认领地址
                    $self_check_count = $address_model
                        ->where('primary_school_id', '=', $school['id'] )->where('deleted', 0)
                        ->where('simple_id', $simple_id)->count();
                    if($self_check_count == 0 && $check_count > 0){
                        //本校认领的详细地址全部取消，缩略地址更改为勾选最多的
                        if($simple['primary_school_id'] == $school['id']){
                            $address_school = $address_model->field(['COUNT(*)' => 'total', 'primary_school_id'])
                                ->where('simple_id', $simple_id)->where('deleted', 0)
                                ->where('primary_school_id', '>', 0)->group('primary_school_id')
                                ->order('total', 'DESC')->find();
                            if($address_school){
                                $update['primary_school_id'] = $address_school['primary_school_id'];
                            }
                        }
                    }
                    break;
                case 2:
                    //总勾选的详细地址
                    $check_count = $address_model
                        ->where('middle_school_id', '>', 0)->where('deleted', 0)
                        ->where('simple_id', '=', $simple_id)->count();
                    $update['middle_school_num'] = $check_count;

                    if($check_count == 0) {
                        $update['middle_school_id'] = 0;
                    }
                    //本校认领地址
                    $self_check_count = $address_model
                        ->where('middle_school_id', '=', $school['id'] )->where('deleted', 0)
                        ->where('simple_id', $simple_id)->count();
                    if($self_check_count == 0 && $check_count > 0){
                        //本校认领的详细地址全部取消，缩略地址更改为勾选最多的
                        if($simple['middle_school_id'] == $school['id']){
                            $address_school = $address_model->field(['COUNT(*)' => 'total', 'middle_school_id'])
                                ->where('simple_id', $simple_id)->where('deleted', 0)
                                ->where('middle_school_id', '>', 0)->group('middle_school_id')
                                ->order('total', 'DESC')->find();
                            if($address_school){
                                $update['middle_school_id'] = $address_school['middle_school_id'];
                            }
                        }
                    }
                    break;
            }

            $update['id'] = $simple_id;
            $simple_res = $model->editData($update);
            if($simple_res['code'] == 0){
                throw new \Exception($simple_res['msg']);
            }

            //房产统计
            $result = $this->getAddressStatistics($school_id);
            if ($result['code'] == 0) {
                throw new \Exception($result['msg']);
            }

            $res = [
                'code' => 1,
                'msg' => Lang::get('update_success')
            ];

            Db::commit();
        } catch (\Exception $exception) {
            $res = [
                'code' => $exception->getCode(),
                'msg' => $exception->getMessage()
            ];
            Db::rollback();
        }
        return parent::ajaxReturn($res);
    }

    /**
     * 缩略地址导出
     * @throws \think\Exception
     */
    public function actExport()
    {
        //php 脚本执行时间设置 无限制
        set_time_limit(0);

        if (isset($this->userInfo['region_id'])) {
            $region_id = $this->userInfo['region_id'];
        }else{
            throw new \Exception('管理员所属区域为空');
        }
        if (isset($this->userInfo['school_id'])) {
            $school_id = $this->userInfo['school_id'];
        }else{
            throw new \Exception('管理员没有关联学校ID');
        }

        //学校负责人账号去重
        /*$res_manage = $this->getSchoolMainAccountIds();
        if($res_manage['code'] == 0){
            throw new \Exception($res_manage['msg']);
        }
        $manage_ids = $res_manage['manage_ids'];*/

        $school = Schools::field('id, region_id, school_type')->find($school_id);
        $region = SysRegion::field('id, simple_code')->find($school['region_id']);

        //搜索条件
        $where = [];
        //$where[] = ['simple.region_id', '=', $region_id];
        if ($this->request->has('search') && $this->result['search'] != '') {
            $where[] = ['simple.address', 'like', '%' . $this->result['search'] . '%'];
        }
        /*if ($this->request->has('is_select') && $this->request->param('is_select') >= 0 ) {
            switch ($this->request->param('is_select')) {
                case 1:
                    $where[] = ['simple.primary_school_id|simple.middle_school_id', '>', 0];
                    break;
                case 0:
                    $where[] = ['simple.primary_school_id|simple.middle_school_id', '<=', 0];
                    break;
            }
        }*/
        $tableName = "deg_sys_address_{$region['simple_code']}";
        $data = Db::table($tableName)->alias('address');
            //->join(['deg_sys_region' => 'region'], 'region.id = address.region_id');

        switch ($school['school_type']) {
            case 1:
                $where[] = ['address.primary_school_id', '=', $school['id']];

                $data = $data->join([
                    'deg_sys_school' => 'primary_school'
                    ], 'primary_school.id = address.primary_school_id', 'left')
                    /*->join([
                        'deg_manage' => 'primary_admin'
                    ], 'primary_admin.school_id = address.primary_school_id and primary_admin.id in (' . implode(',', $manage_ids) . ') and primary_admin.deleted = 0 ', 'left')*/
                    ->field([
                        'address.id',
                        //'region.region_name' => 'region_name',
                        'address.address',
                        'address.primary_school_id',
                        'primary_school.school_name' => 'school_name',
                        //'primary_admin.real_name' => 'admin_name',
                        //'primary_admin.user_name' => 'admin_mobile',
                    ]);

                break;
            case 2:
                $where[] = ['address.middle_school_id', '=', $school['id']];

                $data = $data->join([
                        'deg_sys_school' => 'middle_school'
                    ], 'middle_school.id = address.middle_school_id', 'left')
                    /*->join([
                        'deg_manage' => 'middle_admin'
                    ], 'middle_admin.school_id = address.middle_school_id and middle_admin.id in (' . implode(',', $manage_ids) . ') and middle_admin.deleted = 0 ', 'left')*/
                    ->field([
                        'address.id',
                        //'region.region_name' => 'region_name',
                        'address.address',
                        'address.middle_school_id',
                        'middle_school.school_name' => 'school_name',
                        //'middle_admin.real_name' => 'admin_name',
                        //'middle_admin.user_name' => 'admin_mobile'
                    ]);

                break;
        }
        $manage = Cache::get('manage');

        $data = $data->where($where)->order('address.id', 'ASC')->select()->toArray();
        foreach ($data as $k => &$val){
            $val['admin_name'] = '';
            $val['admin_mobile'] = '';

            switch ($school['school_type']) {
                case 1:
                    if($val['primary_school_id'] > 0){
                        if ($val['school_name']) {
                            $manageData = $this->getSchoolMainAccount($manage, $val['primary_school_id']);
                            $val['admin_name'] = $manageData['admin_name'];
                            $val['admin_mobile'] = $manageData['admin_mobile'];
                        }
                    }
                    unset($data[$k]['primary_school_id']);
                    break;
                case 2:
                    if($val['middle_school_id'] > 0){
                        if ($val['school_name']) {
                            $manageData = $this->getSchoolMainAccount($manage, $val['middle_school_id']);
                            $val['admin_name'] = $manageData['admin_name'];
                            $val['admin_mobile'] = $manageData['admin_mobile'];
                        }
                    }
                    unset($data[$k]['middle_school_id']);
                    break;
            }
        }

        if(count($data) == 0){
            return parent::ajaxReturn(['code' => 0, 'msg' => '无导出数据']);
        }

        $headArr = ['编号', '详细地址', '学校', '学校负责人', '联系电话'];
        if(count($data) > 5000){
            $total = count($data);
            $count_excel = ceil($total / 5000);
            for ($i = 0; $i < $count_excel; $i++){
                $offset = $i * 5000;
                $length = ($i + 1) * 5000;
                if($i == ($count_excel - 1)){
                    $length = $total;
                }
                $data = array_slice($data, $offset, $length, true);
                $this->excelExport('本校认领缩略地址_' . ($i + 1) . '_', $headArr, $data);
            }
        }else {
            $this->excelExport('本校认领缩略地址', $headArr, $data);
        }
        //$this->excelExport('本校认领地址', $headArr, $data);
    }

    /**
     * excel表格导出
     * @param string $fileName 文件名称
     * @param array $headArr 表头名称
     * @param array $data 要导出的数据
     * @author Mr.Lv   3063306168@qq.com
     */
    public function excelExport($fileName = '', $headArr = [], $data = []) {

        $fileName       .= "_" . date("Y_m_d", time());
        $spreadsheet    = new Spreadsheet();
        $objPHPExcel    = $spreadsheet->getActiveSheet();
        $key = ord("A"); // 设置表头

        foreach ($headArr as $v) {
            $colum = chr($key);
            $objPHPExcel->setCellValue($colum . '1', $v);
            //$spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
            $key += 1;
        }
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(70);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(70);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(30);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        //$spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(40);
        //$spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(10);


        $column = 2;
        foreach ($data as $key => $rows) { // 行写入
            $span = ord("A");
            foreach ($rows as $keyName => $value) { // 列写入
                $objPHPExcel->setCellValue(chr($span) . $column, $value);
                $span++;
            }
            $column++;

        }

        //$fileName = iconv("utf-8", "gbk//IGNORE", $fileName); // 重命名表（UTF8编码不需要这一步）
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        //删除清空：
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    }

    /**
     * 根据行政代码获取model名称
     * @param $code
     * @return string
     */
    private function getModelNameByCode($code)
    {
        $model_name = '';
        switch ($code){
            case 420602:
                $model_name = "AddressXiangCheng"; break;
            case 420606:
                $model_name = "AddressFanCheng"; break;
            case 420685:
                $model_name = "AddressGaoXin"; break;
            case 420608:
                $model_name = "AddressDongJin"; break;
            case 420607:
                $model_name = "AddressXiangZhou"; break;
            case 420683:
                $model_name = "AddressZaoYang"; break;
            case 420684:
                $model_name = "AddressYiCheng"; break;
            case 420682:
                $model_name = "AddressLaoHeKou"; break;
            case 420624:
                $model_name = "AddressNanZhang"; break;
            case 420626:
                $model_name = "AddressBaoKang"; break;
            case 420625:
                $model_name = "AddressGuCheng"; break;
            case 420652:
                $model_name = "AddressYuLiangZhou"; break;
        }
        return $model_name;
    }
}