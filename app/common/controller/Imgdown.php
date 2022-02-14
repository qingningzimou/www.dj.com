<?php
namespace app\common\controller;
use think\File;
/**
 * 远程图片下载
 * 依赖http_down函数
 */
class Imgdown extends File {
    //下载地址
    private $url = '';
    private $path = '';
    private $error = '';
    /**
     * 
     * @param string  $url  图片地址
     * @param boolean $replace 是否覆盖
     */
    function __construct($url, $replace = false) {
        $path = ROOT_PATH . 'public' . DS;
        $this->url = trim(urldecode($url));
        //检查域名
        $host = parse_url($this->url, PHP_URL_HOST);
        if ($host == $_SERVER['HTTP_HOST']) {
            $filename = $path . str_replace(request()->domain(), '', $this->url);
        } else {
            $ext = pathinfo($this->url, PATHINFO_EXTENSION);
            //网址中不存在文件扩展名
            if (empty($ext)) {
                //获取url中的header信息
                $head = get_head($this->url);
                if (!empty($head)) {
                    //从headers中获得文件名
                    $headers = explode("\n", $head);
                    foreach ($headers as $v) {
                        $item = explode(':', $v);
                        if (count($item) > 1) {
                            $name = strtolower($item[0]);
                            if ($name == 'location') {
                                //302跳转
                                $this->url = count($item) == 2 ? trim($item[1]) : trim($item[1]) . ':' . trim($item[2]); //防止http:被解析
                                $ext = pathinfo($this->url, PATHINFO_EXTENSION);
                                break;
                            } else if ($name == 'content-disposition') {
                                //可能是Content-Disposition: attachment; filename=".$file_name
                                //获得MIME： Content-Type
                                $item[1] = trim($item[1]);
                                $tmps = explode("filename=", $item[1]);
                                $tmp = count($tmps) > 1 ? $tmps[1] : $tmps[0];
                                $ext = pathinfo($tmp, PATHINFO_EXTENSION);
                                break;
                            }
                        }
                    }
                }
            }
            $filename = $path . 'uploads' . DS . 'temp' . DS . md5($this->url) . '.' . $ext;
            if (!is_file($filename) || $replace) {
                if (http_down($this->url, $filename) === false) {
                    $this->error = '下载文件失败';
                }
            }
        }
        parent::__construct($filename, 'r');
        $this->setUploadInfo(['name' => pathinfo($filename, PATHINFO_BASENAME)]);
        $this->path = $path;
    }
    /**
     * 检测是否合法的下载文件
     * @return bool
     */
    public function isValid() {
        return is_file($this->filename);
    }
    /**
     * 获取文件名
     * @param boolean $realpath 是否返回绝对路径
     * @return false|string
     */
    public function getFileName($realpath = false) {
        // 检测合法性
        if (!$this->isValid()) {
            $this->error = '非法下载文件';
            return false;
        }
        // 验证下载
        if (!$this->check()) {
            return false;
        }
        if (!empty($this->error)) {
            return false;
        }
        return $realpath ? $this->filename : substr($this->filename, strlen($this->path));
    }
}
