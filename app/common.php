<?php
// 应用公共文件
/**
 * 多个数组的笛卡尔积
 * @param unknown_type $data
 */

function combine_dika()
{
    $data = func_get_args();
    $data = current($data);
    $result = array();
    $arr1 = array_shift($data);
    foreach ($arr1 as $key => $item) {
        $result[] = array($item);
    }

    foreach ($data as $key => $item) {
        $result = combine_array($result, $item);
    }
    return $result;
}


/**
 * 两个数组的笛卡尔积
 * @param unknown_type $arr1
 * @param unknown_type $arr2
 */
function combine_array($arr1, $arr2)
{
    $result = array();
    foreach ($arr1 as $item1) {
        foreach ($arr2 as $item2) {
            $temp = $item1;
            $temp[] = $item2;
            $result[] = $temp;
        }
    }
    return $result;
}
/**
 * 大数取余
 */
function kmod($bn, $sn)
{
    return intval(fmod(floatval($bn), $sn));
}
/**
 * 对象转数组
 */
function object_to_array(&$object) {
    $object =  json_decode(json_encode( $object),true);
    return  $object;
}

/**
 * 随机密码
 * @param int $length
 * @param string $type
 * @return bool|string
 */
function random_code_type($length = 8,$type = 'alpha-number'){
    $code_arr = array(
        'alpha' => 'abcdefghjklmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ',
        'number'=> '23456789',
        'sign'  => '#$%@*-_',
    );

    $type_arr = explode('-',$type);

    foreach($type_arr as $t){
        if( ! array_key_exists($t,$code_arr)){
            trigger_error("Can not generate type ($t) code");
        }
    }

    $chars = '';

    foreach($type_arr as $t){
        $chars .= $code_arr[$t];
    }
    $chars = str_shuffle($chars);
    $number = $length > strlen($chars) - 1 ? strlen($chars) - 1:$length;
    return substr($chars,0,$number);
}
/**
 * 过滤二维数组中重复的值
 */
function array_unique_value($arr, $key)
{
    $tmp_arr = array();
    foreach ($arr as $k => $v) {
        if (in_array($v[$key], $tmp_arr))  //搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true
        {
            unset($arr[$k]); //销毁一个变量 如果$tmp_arr中已存在相同的值就删除该值
        } else {
            $tmp_arr[$k] = $v[$key]; //将不同的值放在该数组中保存
        }
    }
    return $arr;
}

/**
 * 多参数过滤二维数组中重复的值
 */

function array_unique_fb($arr = array(), $filter)
{
    $tmp = array();
    $res = array();
    $i = 0;
    foreach ($arr as $key => $value) {
        $newkey = '';
        if (is_array($filter)) {
            foreach ($filter as $fv) {
                $newkey .= $value[$fv];
            }
        } else {
            $newkey = $value[$filter];
        }
        foreach ($value as $vk => $va) {
            if (isset($tmp[$newkey])) {
                $tmp[$newkey][$vk] = $va;
            } else {
                $tmp[$newkey][$vk] = $va;
            }
        }
    }
    foreach ($tmp as $key => $value) {
        $res[$i] = $value;
        $i++;
    }
    return $res;
}

/**
 * 根据二维数组某个字段的值查找数组
 * @param $array
 * @param $index
 * @param $value
 * @return array
 */
function filter_by_value ($array, $index, $value){
    $newarray = [];
    if(is_array($array) && count($array)>0)
    {
        foreach(array_keys($array) as $key){
            $temp[$key] = $array[$key][$index];
            if ($temp[$key] == $value && strlen($temp[$key]) == strlen($value)){
                $newarray[$key] = $array[$key];
            }
        }
    }
    return $newarray;
}
/**
 * 往二维数组追加固定的键值对
 * @param $array
 * @param $index
 * @return array
 */
function add_key_value($array, $index, $value) {
    if (is_array($array) && count($array) > 0 && !empty($index) && (!empty($value) || $value == 0 || $value == '')) {
        foreach(array_keys($array) as $key) {
            $array[$key][$index] = $value;
        }
    }
    return $array;
}

/**
 * 根据二维数组某个键名从原有数组剔除该条目
 * @param $array
 * @param $index
 * @return array
 */

function remove_by_index($array, $index) {
    if (is_array($array) && count($array) > 0) {
        foreach(array_keys($array) as $key) {
            if ($array[$key] == $index && strlen($array[$key]) == strlen($index)){
                unset($array[$key]);
            }
        }
    }
    return $array;
}

/**
 * 根据二维数组某个字段的值从原有数组剔除该条目
 * @param $array
 * @param $index
 * @param $value
 * @return array
 */

function remove_by_value($array, $index, $value) {
    if (is_array($array) && count($array) > 0) {
        foreach(array_keys($array) as $key) {
            $temp[$key] = $array[$key][$index];
            if ($temp[$key] == $value && strlen($temp[$key]) == strlen($value)){
                unset($array[$key]);
            }
        }
    }
    return $array;
}

/**
 * 根据二维数组某个字段, 查询不为空值的数组
 * @param $array
 * @param $index
 * @param $value
 * @return array
 */
function filter_by_present_value($array, $index) {
    if (is_array($array) && count($array) > 0) {
        foreach(array_keys($array) as $key) {
            $temp[$key] = $array[$key][$index];
            if (empty($temp[$key])){
                unset($array[$key]);
            }
        }
    }
    return $array;
}

/**
 * 根据二维数组某个字段的值返回唯一记录
 * @param $array
 * @param $index
 * @param $value
 * @return array
 */
function filter_value_one ($array, $index, $value){
    $newarray = [];
    if(is_array($array) && count($array)>0)
    {
        foreach(array_keys($array) as $key){
            $temp[$key] = $array[$key][$index];
            if ($temp[$key] == $value && strlen($temp[$key]) == strlen($value)){
                $newarray = $array[$key];
                break;
            }
        }
    }
    return $newarray;
}

/**
 * 根据二维数组某个字段的值模糊查找数组
 * @param $array
 * @param $index
 * @param $value
 * @return array
 */
function filter_like_value ($array, $index, $value ,$other = ''){
    $newarray = [];
    if(is_array($array) && count($array)>0)
    {
        foreach(array_keys($array) as $key){
            $temp[$key] = $array[$key][$index];
            if ($other){
                $other_temp[$key] = $array[$key][$other];
                if (mb_strstr($temp[$key],$value) !== false || mb_strstr($other_temp[$key],$value) !== false){
                    $newarray[$key] = $array[$key];
                }
            }else{
                if (mb_strstr($temp[$key],$value) !== false){
                    $newarray[$key] = $array[$key];
                }
            }
        }
    }
    return $newarray;
}
/**
 * @param $var_arr
 * @param $index
 * @param $value
 * @return bool|int|string
 * 根据二维数组某字段值查找键值
 *
 */
function get_key_by_value($var_arr,$index, $value){
    foreach($var_arr as $k=>$v) {
        if ($v[$index] == $value && strlen($v[$index]) == strlen($value)){
            return $k;
        }
    }
    return false;
}

/**
 * 获取数组中接近条件值的数组
 * @param $number
 * @param $numberRangeArray
 * @param $negative
 * @return mixed
 */

function next_number_array($number, $numberRangeArray,$negative = 0){
    $w = 0;
    $c = -1;
    $l = count($numberRangeArray);
    for($pos=0; $pos < $l; $pos++){
        $n = $numberRangeArray[$pos];
        if ($negative == 1){
            $abstand = ($n < $number) ? 0 : $n - $number;
        }else{
            $abstand = ($n < $number) ? $number - $n : $n - $number;
        }
        if ($c == -1){
            $c = $abstand;
            continue;
        }else if ($abstand < $c && $abstand > 0){
            $c = $abstand;
            $w = $pos;
        }
    }
    return $numberRangeArray[$w];
}

/**
 * 获取满足条件的最优组合结果
 * @param $arr
 * @param $val
 * @return bool
 */
function optimal($arr,$val){
    $res = [];
    for ($i = 1; $i < 1 << count($arr); $i++) {
        $sum = 0;
        $temp = "";
        for ($j = 0; $j <count($arr); $j++) {
            if (($i & 1 << $j) != 0) {

                $sum += (double)$arr[$j];
                $temp .= (double)$arr[$j]."+";
            }
        }
        if ($sum >= $val) {
            $rst = explode("+",trim($temp,'+'));
            $res[] = [
                'total' => round(array_sum($rst),3),
                'count' => count($rst),
                'array' => $rst
            ];
        }
    }
    foreach ($res as $key => $row) {
        $count[$key] = $row['count'];
        $total[$key] = $row['total'];
    }
    if (count($res) > 0){
        array_multisort($count, SORT_ASC, $total,SORT_ASC, $count, $res);
        return $res[0];
    }else{
        return false;
    }
}
/*
* delMemberGetNewArray 得到一个新二维数组
* @ $data 原始数组
* @ $del_data mixd 传入的改变因子
* @ $flag bool 为false就是原始数组删除包含因子的成员，true就是提取包含因子的成员
*/
function del_member_getnew_array(array $data,array $del_data,$flag=false)
{
    if(!$data) return false;
    if(!$del_data) return false;
    $flag_array = array(false,true);
    if (!in_array($flag, $flag_array )) {
        return false;
    }
    $new_data = array();
    $count = sizeof($del_data);
    $org_count = sizeof($data[0]);
    if($count >= $org_count) return false;#如果del_data的个数大于或等于数组，返回false
    foreach($data as $key => $value)
    {
        #提取制定成员操作
        if($flag){
            #提取单个成员操作
            if(count($del_data) == 1){
                if(array_key_exists($del_data[0],$value))
                {
                    $new_data[$key][$del_data[0]] = $value[$del_data[0]];
                    if ($count == count($data)-1) {
                        return $new_data;
                    }
                }else{
                    return false;
                }
            }else{
                #提取多个成员
                $keys = array_keys($value);
                $new_array = array_diff($keys,$del_data);
                if (count($new_array) == 1) {
                    $extra_key = $new_array[key($new_array)];
                    unset($value[$extra_key]);
                    $new_data[] = $value;
                }else{

                }
                if($key == count($data)-1)
                {
                    return $new_data;
                }
            }
        }else{
            #传入数组删除操作
            foreach($del_data as $del_value)
            {
                unset($value[$del_value]);
            }
            #传入单个变量删除操作
            $new_data[$key]= $value;
        }
    }
    return $new_data;
}
/**
 * 获取最接近结果数组
 * @param $batch_num
 * @param $erp_num
 * @param $batch_arr
 * @return array
 */
function combination ($batch_num,$erp_num,$batch_arr) {
    $value = $batch_arr;
    $tmp[$batch_num][$batch_num] = [];
    $res[$batch_num] = [];
    for ($i=0; $i<=$batch_num; $i++) {
        $tmp[$i][0] = 0;
    }
    for ($i = 0; $i <= $erp_num; $i++) {
        $tmp[0][$i] = 0;
    }
    for ($i = 1;$i <= $batch_num;$i++) {
        for ($j=1; $j <= $erp_num; $j++) {
            if ($j < $batch_arr[$i-1]) {
                $tmp[$i][$j] = $tmp[$i-1][$j];
            } else {
                $tmp[$i][$j] = max($tmp[$i-1][$j],$tmp[$i-1][$j-$batch_arr[$i-1]] + $value[$i-1]);
            }
        }
    }
    $j = $erp_num;
    for ($i = $batch_num;$i > 0; $i--) {
        if ($tmp[$i][$j] > $tmp[$i-1][$j]) {
            $res[$i] = 1;
            $j -= $batch_arr[$i-1];
        } else {
            $res[$i] = 0;
        }
    }
    ksort($res);
    return $data = ['maxnum' => $tmp[$batch_num][$erp_num], 'res' => $res];
}

//翻译抛出异常的错误信息
function translate($str)
{
    $url = 'http://fanyi.youdao.com/translate?&doctype=json&type=AUTO&i=' . $str;
    $data = json_decode(http_get($url), true);
    return $data['translateResult'][0][0]['tgt'];
}

function object_array($array) {
    if(is_object($array)) {
        $array = (array)$array;
    } if(is_array($array)) {
        foreach($array as $key=>$value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}
/*
 * 获取HTTP_headers
 */
function get_header()
{
    $headers = array();
    foreach ($_SERVER as $key => $value) {
        if ('HTTP_' == substr($key, 0, 5)) {
            $headers[str_replace('_', '-', substr($key, 5))] = $value;
        }
        if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
            $header['AUTHORIZATION'] = $_SERVER['PHP_AUTH_DIGEST'];
        } elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $header['AUTHORIZATION'] = base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $header['CONTENT-LENGTH'] = $_SERVER['CONTENT_LENGTH'];
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $header['CONTENT-TYPE'] = $_SERVER['CONTENT_TYPE'];
        }
    }
    return $headers;
}

function get_token() {
    $token = '';
    $headers = get_header();
    if (array_key_exists('TOKEN', $headers)) {
        $token = $headers['TOKEN'];
    }
    return $token;
}


function encrypt($time,$user_id,$sub) {
    $openssl_key = md5('Txzn-Edu-Deg-Key');
    $openssl_iv = hex2bin(md5('Txzn-Edu-Deg-IV'));
//    $en_method = 'aes-256-cbc-hmac-sha256';
    $en_method = 'aes-256-cbc';
//    $en_method = 'sm4-cbc';
    $header = array(
        'sub' => $sub,
        'time' => $time,
        'user_id' => $user_id,
    );
    return openssl_encrypt(json_encode($header),$en_method,$openssl_key,0,$openssl_iv);
}

function decrypt($data) {
    $openssl_key = md5('Txzn-Edu-Deg-Key');
    $openssl_iv = hex2bin(md5('Txzn-Edu-Deg-IV'));
//    $en_method = 'aes-256-cbc-hmac-sha256';
    $en_method = 'aes-256-cbc';
    return json_decode(openssl_decrypt($data, $en_method, $openssl_key,0,$openssl_iv),1);
}


function checkExpire($data){
    $flag = false;
    $time = time();
    $tmp = decrypt($data);
    if (is_array($tmp)){
        if ($time >  $tmp['time']){
            $flag = true;
        }
    }
    return $flag;
}

function checkRefreshExpire($data){
    $flag = false;
    $time = time();
    $tmp = decrypt($data);
    if (is_array($tmp)){
        if ($tmp['time'] - $time < 3600){
            $flag = true;
        }
    }
    return $flag;
}

function set_token($user_id)
{
    $token_expire = 7200;
    $sub = substr(md5(time()),0,8);
    $time = strtotime('+'.$token_expire.' second');
    $str = encrypt($time,$user_id,$sub);
    return $str;
}

function get_rand_str($length){
    //字符组合
    $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len = strlen($str)-1;
    $randstr = '';
    for ($i=0;$i<$length;$i++) {
        $num=mt_rand(0,$len);
        $randstr .= $str[$num];
    }
    return $randstr;
}

function create_sign($str) {
    $sm3 = new \sm\TxSm3();
    return strtoupper(substr($sm3->sign($str),0,32));
}

function set_hash($user_id,$page)
{
    $token_expire = 600;
    $sub = 'hash_'.md5($page.$user_id);
    $time = strtotime('+'.$token_expire.' second');
    $str = encrypt($time,$user_id,$sub);
    return $str;
}

/**
 * 获取业务周期
 * @return false|int
 */
function getTaskTime($task_cycle)
{
    $orderTime = date('Y-m-d');
    $fullTime = date('Y-m-d') . ' ' . $task_cycle;
    //  如果当前时间小于完整的任务生成时间 且  大于当前天的时间，则显示为昨天的业务周期
    if(time() < strtotime($fullTime) && time() >= strtotime($orderTime))
    {
        $orderTime = date('Y-m-d',strtotime("-1day"));
    }
    return strtotime($orderTime);
}

/**
 * 修改扩展配置文件.
 *
 * @param array  $arr  需要更新或添加的配置
 * @param string $file 配置文件名(不需要后辍)
 *
 * @return bool
 */
function config_set($arr = [], $file = 'basic')
{
    if (is_array($arr)) {
        // 文件路径
        $filepath = \think\facade\Env::get('config_path').$file.'.php';
        // 检测是否存在,不存在新建
        if (!file_exists($filepath)) {
            $conf = '<?php return [];';
            file_put_contents($filepath, $conf);
        }
        // 添加配置项
        $conf = include $filepath;
        foreach ($arr as $key => $value) {
            $conf[$key] = $value;
        }
        // 修改配置项
        $str = "<?php\r\nreturn [\r\n";
        foreach ($conf as $key => $value) {
            // dump(gettype($value));
            switch (gettype($value)) {
                case 'string':
                    $str .= "\t'$key' => '$value',"."\r\n";
                    break;
                case 'integer':
                    $str .= "\t'$key' => $value,"."\r\n";
                    break;
                case 'double':
                    $str .= "\t'$key' => $value,"."\r\n";
                    break;
                case 'boolean':
                    $str .= "\t'$key' => ".($value?'true':'false').","."\r\n";
                    break;
                default:
                    # code...
                    break;
            }
        }
        $str .= '];';
        // 写入文件
        // dump($str);exit;
        file_put_contents($filepath, $str);

        return true;
    } else {
        return false;
    }
}
/**
 * get请求
 * @param $url
 * @return bool|string
 */
function http_get($url)
{
    $oCurl = curl_init();
    if (stripos($url, "https://") !== FALSE) {
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
    }
    curl_setopt($oCurl, CURLOPT_URL, $url);
    curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
    $sContent = curl_exec($oCurl);
    $aStatus = curl_getinfo($oCurl);
    curl_close($oCurl);

    if (intval($aStatus["http_code"]) == 200) {
        return $sContent;
    } else {
        return false;
    }
}
/**
 *
 * @param $url
 * @param $param
 * @param bool $post_file
 * @return bool|mixed
 */
function httpPost($url, $param,$post_raw = false, $post_file = false)
{
    $oCurl = curl_init();
    if (stripos($url, "https://") !== FALSE) {
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($oCurl, CURLOPT_SSLVERSION, 1);
    }
    if ($post_raw){
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, array(
                'X-AjaxPro-Method:ShowList',
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($param))
        );
    }
    if (is_string($param) || $post_file) {
        $strPOST = $param;
    } else {
        $aPOST = array();
        foreach ($param as $key => $val) {
            $aPOST[] = $key . "=" . urlencode($val);
        }
        $strPOST = join("&", $aPOST);
    }
    curl_setopt($oCurl, CURLOPT_URL, $url);
    curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($oCurl, CURLOPT_POST, true);
    curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);

    $sContent = curl_exec($oCurl);
    $aStatus = curl_getinfo($oCurl);

    curl_close($oCurl);
    if (intval($aStatus["http_code"]) == 200) {
        return $sContent;
    } else {
        return false;
    }
}

function check_image_type($image)
{
    $bits = array(
        'jpeg' => "\xFF\xD8\xFF",
        'gif' => "GIF",
        'png' => "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a",
        'bmp' => 'BM',
    );
    foreach ($bits as $type => $bit) {
        if (substr($image, 0, strlen($bit)) === $bit) {
            return $type;
        }
    }
    return false;
}

function getAverageRoundingData($arr,$total,$pointNum = 0)
{
    $column = array_column($arr,'order_num');
    array_multisort($column,SORT_DESC,$arr);
    $data = [];
    foreach ($arr as $key => $item)
    {
        $first = reckonAverageRoundingData($arr,$total,$pointNum);
        unset($arr[$key]);
        $data[] = $first;
        $total =  $total - $first['order_num'];
    }
    return $data;
}

function reckonAverageRoundingData($data,$total,$pointNum)
{
    foreach ($data as $key => $val)
    {
        $data[$key] = [
            'id' => $val['id'],
            'order_num' => $val['order_num'],
            'scale' => round($val['order_num']/array_sum(array_column($data,'order_num')),2)
        ];
    }
    $column = array_column($data,'scale');
    array_multisort($column,SORT_DESC,$data);
    $first = array_shift($data);
    $res = [
        'id' => $first['id'],
        'order_num' => round($first['scale'] * $total,$pointNum)
    ];
    return $res;
}

function getPartitionTableList($table, $startTime, $endTime)
{
    $tableList = [];
    $yearLength = (float)date('Y', strtotime($endTime)) - (float)date('Y', strtotime($startTime));
    if ($yearLength == 0) {
        $monthLength = (float)date('n', strtotime($endTime)) - (float)date('n', strtotime($startTime));
        for ($i = 0; $i <= $monthLength; $i++) {
            $tableList[] = $table . '_' . date('Y', strtotime($startTime)) . str_pad((intval(date('n', strtotime($startTime))) + $i), 2, "0", STR_PAD_LEFT);
        }
    } else {
        $monthLength = $yearLength * 12 + (float)date('n', strtotime($endTime)) - (float)date('n', strtotime($startTime));
        for ($i = 0; $i <= $monthLength; $i++) {
            $month = intval(date('n', strtotime($startTime))) + $i;
            if (intval($month / 12)) {
                $tableList[] = $table . '_' . (intval(date('Y', strtotime($startTime))) + intval($month / 12)) . ($month % 12 == 0 ? 12 : str_pad($month % 12, 2, "0", STR_PAD_LEFT));
            } else {
                $tableList[] = $table . '_' . date('Y', strtotime($startTime)) . str_pad($month, 2, "0", STR_PAD_LEFT);
            }
        }
    }
    return $tableList;
}

/**
 * 使用时间戳作为原始字符串，再随机生成制定次數的字符随机插入任意位置，生成新的字符串
 */
function generateRandomStrings($length = 5)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
    $string = time();
    for($length = 5; $length >=1; $length--)
    {
        $firstPosition = rand()%strlen($chars);
        $secondPosition = rand()%strlen($string);
        $string = substr_replace($string, substr($chars, $firstPosition, 1), $secondPosition, 0);
    }
    return $string;
}


/**
 * Calculates the great-circle distance between two points, with
 * the Vincenty formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function caculateDistance(
    $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
    // convert from degrees to radians
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $lonDelta = $lonTo - $lonFrom;
    $a = pow(cos($latTo) * sin($lonDelta), 2) +
        pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
    $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

    $angle = atan2(sqrt($a), $b);
    return $angle * $earthRadius;
}

/**
 * @todo 敏感词过滤
 * @param string $list  定义敏感词数据集
 * @param string $string 要过滤的内容
 */
function sensitive($list, $string){
    $count = 0; //违规词的个数
    $sensitiveWord = ''; //违规词
    $res = [];
    $list_data = explode(",",$list);
    $stringAfter = $string;
    $pattern = "/".implode("|",$list_data)."/i";
    if(preg_match_all($pattern, $string, $matches)){
        $patternList = $matches[0];
        $count = count($patternList);
        $sensitiveWord = implode(',', $patternList);
        $replaceArray = array_combine($patternList,array_fill(0,count($patternList),'*'));
        $stringAfter = strtr($string, $replaceArray);
    }
    $res['count'] = $count;
    $res['word'] = $sensitiveWord;
    $res['string'] = $stringAfter;
    return $res;
}
//时间戳转日期
function unixtime_to_date($unixtime, $timezone = 'PRC') {
    $datetime = new DateTime("@$unixtime"); //DateTime类的bug，加入@可以将Unix时间戳作为参数传入
    $datetime->setTimezone(new DateTimeZone($timezone));
    return $datetime->format("Y-m-d H:i:s");
}
//日期转Unix时间戳
function date_to_unixtime($date, $timezone = 'PRC') {
    $datetime= new DateTime($date, new DateTimeZone($timezone));
    return $datetime->format('U');
}
//计算年龄
function birthday($birthday){
    $age = strtotime($birthday);
    if($age === false){
        return false;
    }
    list($y1,$m1,$d1) = explode("-",date("Y-m-d",$age));
    $now = strtotime("now");
    list($y2,$m2,$d2) = explode("-",date("Y-m-d",$now));
    $age = $y2 - $y1;
    if((int)($m2.$d2) < (int)($m1.$d1))
        $age -= 1;
    return $age;
}
//创建编号
function create_code($id, $num_length, $prefix){
    // 基数
    $base = pow(10, $num_length);
    // 生成数字部分
    $mod = $id % $base;
    $digital = str_pad($mod, $num_length, 0, STR_PAD_LEFT);
    $code = sprintf('%s%s%s', $prefix, $digital,time());
    return $code;
}

function getCommentByFields(array $fields): array
{
    $list = array_column($fields, 'comment', 'name');
    foreach ($list as $k => &$v) {
        if (empty($v)) {
            $v = $k;
        }
    }
    return $list;
}

function x_unsetField(array $fields, array $data): array
{
    foreach ($data as $k => $v) {
        if (in_array($k, $fields)) {
            unset($data[$k]);
        }
    }
    return $data;
}

/**
 * 检测身份证号是否合法
 * @param $id
 * @return bool
 */
function check_Idcard($idcard)
{
    $idcard = strtoupper($idcard);
    $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
    $arr_split = array();
    if (!preg_match($regx, $idcard)) {
        return FALSE;
    }
    if (15 == strlen($idcard)) //检查15位
    {
        $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";
        @preg_match($regx, $idcard, $arr_split);
        //检查生日日期是否正确
        $dtm_birth = "19" . $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
        if (!strtotime($dtm_birth)) {
            return FALSE;
        } else {
            return TRUE;
        }
    } else //检查18位
    {
        $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
        @preg_match($regx, $idcard, $arr_split);
        $dtm_birth = $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
        if (!strtotime($dtm_birth)) //检查生日日期是否正确
        {
            return FALSE;
        } else {
            //检验18位身份证的校验码是否正确。
            //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
            $arr_int = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
            $arr_ch = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
            $sign = 0;
            for ($i = 0; $i < 17; $i++) {
                $b = (int)$idcard{$i};
                $w = $arr_int[$i];
                $sign += $b * $w;
            }
            $n = $sign % 11;
            $val_num = $arr_ch[$n];
            if ($val_num != substr($idcard, 17, 1)) {
                return FALSE;
            } else {
                return TRUE;
            }
        }
    }
}
/**
 * 通过身份证获取生日
 * @param $idcard
 * @return false|int
 */
function getBirthdayByIdcard($idcard)
{
    if (strlen($idcard) == 15) {
        $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";
        @preg_match($regx, $idcard, $arr_split);
        $dtm_birth = "19" . $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
    } else {
        $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
        @preg_match($regx, $idcard, $arr_split);
        $dtm_birth = $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
    }
    return strtotime($dtm_birth);
}

/**
 * 通过身份证获取性别
 * sex  1：男；2：女
 * @param $idcard
 * @return int
 */
function x_getSexByIdcard($idcard)
{
    $preg = '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/';
    if (preg_match($preg,$idcard) == 0){
        return 0;
    }
    $position = strlen($idcard) == 15 ? -1 : -2;
    if (substr($idcard, $position, 1) % 2 == 0) {
        $sex = 2;
    } else {
        $sex = 1;
    }
    return $sex;
}

function getAdcodeName($adcode)
{
    $adcodeName = '';
    switch ($adcode)
    {
        case "420600":
            $adcodeName = '市本级';
            break;
        case "420602":
            $adcodeName = '襄城区';
            break;
        case "420606":
            $adcodeName = '樊城区';
            break;
        case "420685":
            $adcodeName = '高新区';
            break;
        case "420608":
            $adcodeName = '东津新区';
            break;
        case "420607":
            $adcodeName = '襄州区';
            break;
        case "420682":
            $adcodeName = '老河口';
            break;
        case "420683":
            $adcodeName = '枣阳市';
            break;
        case "420684":
            $adcodeName = '宜城市';
            break;
        case "420624":
            $adcodeName = '南漳县';
            break;
        case "420625":
            $adcodeName = '谷城县';
            break;
        case "420626":
            $adcodeName = '保康县';
            break;
        default:
            $adcodeName = '未知';
            break;
    }
    return $adcodeName;
}

function getRelationByHgx($hgx)
{
    $relationTxt = '无法确定关系';
    switch ($hgx)
    {
        case "0":
            $relationTxt = '无法确定关系';
            break;
        case "":
            $relationTxt = '无法确定关系';
            break;
        case "01":
            $relationTxt = '本人';
            break;
        case "02":
            $relationTxt = '户主';
            break;
        case "03":
            $relationTxt = '小集体户户主';
            break;
        case "10":
            $relationTxt = '配偶';
            break;
        case "11":
            $relationTxt = '夫';
            break;
        case "12":
            $relationTxt = '妻';
            break;
        case "20":
            $relationTxt = '子';
            break;
        case "21":
            $relationTxt = '独生子';
            break;
        case "22":
            $relationTxt = '长子';
            break;
        case "23":
            $relationTxt = '次子';
            break;
        case "24":
            $relationTxt = '三子';
            break;
        case "25":
            $relationTxt = '四子';
            break;
        case "26":
            $relationTxt = '五子';
            break;
        case "27":
            $relationTxt = '养子或继子';
            break;
        case "28":
            $relationTxt = '女婿';
            break;
        case "29":
            $relationTxt = '其它儿子';
            break;
        case "30":
            $relationTxt = '女';
            break;
        case "31":
            $relationTxt = '独生女';
            break;
        case "32":
            $relationTxt = '长女';
            break;
        case "33":
            $relationTxt = '二女';
            break;
        case "34":
            $relationTxt = '三女';
            break;
        case "35":
            $relationTxt = '四女';
            break;
        case "36":
            $relationTxt = '五女';
            break;
        case "37":
            $relationTxt = '养女';
            break;
        case "38":
            $relationTxt = '儿媳';
            break;
        case "39":
            $relationTxt = '其它女儿';
            break;
        case "40":
            $relationTxt = '孙子,孙女或外孙子,外孙女';
            break;
        case "41":
            $relationTxt = '孙子';
            break;
        case "42":
            $relationTxt = '孙女';
            break;
        case "43":
            $relationTxt = '外孙子';
            break;
        case "44":
            $relationTxt = '外孙女';
            break;
        case "45":
            $relationTxt = '孙媳妇或外孙媳妇';
            break;
        case "46":
            $relationTxt = '孙女婿或外孙女婿';
            break;
        case "47":
            $relationTxt = '曾孙子或曾外孙子';
            break;
        case "48":
            $relationTxt = '曾孙女或曾外孙女';
            break;
        case "49":
            $relationTxt = '其他孙子,孙女或外孙子';
            break;
        case "50":
            $relationTxt = '父母';
            break;
        case "51":
            $relationTxt = '父亲';
            break;
        case "52":
            $relationTxt = '母亲';
            break;
        case "53":
            $relationTxt = '公公';
            break;
        case "54":
            $relationTxt = '婆婆';
            break;
        case "55":
            $relationTxt = '岳父';
            break;
        case "56":
            $relationTxt = '岳母';
            break;
        case "57":
            $relationTxt = '继父或养父';
            break;
        case "58":
            $relationTxt = '继母或养母';
            break;
        case "59":
            $relationTxt = '其它父母关系';
            break;
        case "60":
            $relationTxt = '祖父母或外祖父母';
            break;
        case "61":
            $relationTxt = '祖父';
            break;
        case "62":
            $relationTxt = '祖母';
            break;
        case "63":
            $relationTxt = '外祖父';
            break;
        case "64":
            $relationTxt = '外祖母';
            break;
        case "65":
            $relationTxt = '配偶的祖父母或外祖父母';
            break;
        case "66":
            $relationTxt = '曾祖父';
            break;
        case "67":
            $relationTxt = '曾祖母';
            break;
        case "68":
            $relationTxt = '配偶的曾祖父母';
            break;
        case "69":
            $relationTxt = '其它祖父母或外祖父母关系';
            break;
        case "70":
            $relationTxt = '兄弟姐妹';
            break;
        case "71":
            $relationTxt = '兄';
            break;
        case "72":
            $relationTxt = '嫂';
            break;
        case "73":
            $relationTxt = '弟';
            break;
        case "74":
            $relationTxt = '弟媳';
            break;
        case "75":
            $relationTxt = '姐姐';
            break;
        case "76":
            $relationTxt = '姐夫';
            break;
        case "77":
            $relationTxt = '妹妹';
            break;
        case "78":
            $relationTxt = '妹夫';
            break;
        case "79":
            $relationTxt = '其它兄弟姐妹';
            break;
        case "80":
            $relationTxt = '其他';
            break;
        case "81":
            $relationTxt = '伯父';
            break;
        case "82":
            $relationTxt = '伯母';
            break;
        case "83":
            $relationTxt = '叔父';
            break;
        case "84":
            $relationTxt = '婶母';
            break;
        case "85":
            $relationTxt = '舅父';
            break;
        case "86":
            $relationTxt = '舅母';
            break;
        case "87":
            $relationTxt = '姨父';
            break;
        case "88":
            $relationTxt = '姨母';
            break;
        case "89":
            $relationTxt = '姑父';
            break;
        case "90":
            $relationTxt = '姑母';
            break;
        case "91":
            $relationTxt = '堂兄弟,堂姐妹';
            break;
        case "92":
            $relationTxt = '表兄弟,表姐妹';
            break;
        case "93":
            $relationTxt = '侄子';
            break;
        case "94":
            $relationTxt = '侄女';
            break;
        case "95":
            $relationTxt = '外甥';
            break;
        case "96":
            $relationTxt = '外甥女';
            break;
        case "97":
            $relationTxt = '其他亲属';
            break;
        case "98":
            $relationTxt = '保姆';
            break;
        case "99":
            $relationTxt = '非亲属';
            break;
    }
    return $relationTxt;
}
/**
 * 获取字符串中包含连续数字长度
 * @param $srcStr
 * @return
 */
function getLongNum($srcStr) {
    $arrRes = [
        'str' => '',
        'length' => 0
    ];
    // 若为空，直接返回
    if (empty($srcStr)) {
        return 0;
    }

    // 若为纯数字，直接计算
    $length = strlen($srcStr);
    if (is_numeric($srcStr) && (strpos($srcStr, '.') === false)) {
        $arrRes = [
            'str' => $srcStr,
            'length' => $length,
        ];

        return $length;
    }

    // 非纯数字，需要计算
    $tmp = '';
    $maxLength = 0;
    $maxStr = '';
    for($i=0; $i<$length; $i++) {
        if($srcStr[$i]>='0' && $srcStr[$i]<='9') {
            while (($i < $length) && ($srcStr[$i]>='0') && ($srcStr[$i]<='9')) {
                $tmp .= $srcStr[$i++];
            }
            $tmpLength = strlen($tmp);
            if ($tmpLength > $maxLength) {
                $maxLength = $tmpLength;
                $maxStr = $tmp;
            }
        }
        $tmp = '';
    }

//    $arrRes = [
//        'str' => $maxStr,
//        'length' => $maxLength,
//    ];

    return $maxLength;
}

/*
 * 查询数组中某字段的模糊匹配结果
 */
function filter_like_values ($array, $index, $value ){
    $newarray = [];
    if(is_array($array) && count($array)>0)
    {
        foreach(array_keys($array) as $key){
            $temp[$key] = $array[$key][$index];
            if (mb_strstr($temp[$key],$value) !== false){
                $newarray = $array[$key];
                break;
            }
        }
    }
    return $newarray;
}

function getInsuranceTxt($organid)
{
    switch ($organid)
    {
        case '1000':
            $insuranceTxt = '襄阳市';
            break;
        case '1001':
            $insuranceTxt = '襄城区';
            break;
        case '1002':
            $insuranceTxt = '樊城区';
            break;
        case '1003':
            $insuranceTxt = '高新区';
            break;
        case '1006':
            $insuranceTxt = '襄州区';
            break;
        case '1007':
            $insuranceTxt = '南漳县';
            break;
        case '1008':
            $insuranceTxt = '谷城县';
            break;
        case '1009':
            $insuranceTxt = '保康县';
            break;
        case '1010':
            $insuranceTxt = '老河口市';
            break;
        case '1011':
            $insuranceTxt = '枣阳市';
            break;
        case '1012':
            $insuranceTxt = '宜城市';
            break;
        case '1020':
            $insuranceTxt = '东津新区';
            break;
        default:
            $insuranceTxt = '未知';
            break;
    }
    return $insuranceTxt;
}

function getAdcode($organid)
{
    switch ($organid) {
        case '1000':
            //	市直
            $adcode = '420600';
            break;
        case '1001':
            //	襄城区
            $adcode = '420602';
            break;
        case '1002':
            //	樊城区
            $adcode = '420606';
            break;
        case '1003':
            //	高新区
            $adcode = '420685';
            break;
        case '1006':
            //	襄州区
            $adcode = '420607';
            break;
        case '1007':
            //	南漳县
            $adcode = '420624';
            break;
        case '1008':
            //	谷城县
            $adcode = '420625';
            break;
        case '1009':
            //	保康县
            $adcode = '420626';
            break;
        case '1010':
            //	老河口市
            $adcode = '420682';
            break;
        case '1011':
            //	枣阳市
            $adcode = '420683';
            break;
        case '1012':
            //	宜城市
            $adcode = '420684';
            break;
        case '1020':
            //	东津新区
            $adcode = '420608';
            break;
        default:
            $adcode = 0;
            break;
    }
    return $adcode;
}