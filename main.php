<?php
$root_dir = dirname(__FILE__);
include $root_dir.'/config.php';
require_once $root_dir.'/libs/BaiduPCS.class.php';

if($argc >= 2) {
    if(!in_array($argv[1], array('-u','-d','-m','-D','-init_upload'))) {
        bdSync::showHelp();exit;
    }
    $bdSync = new bdSync;
    $source = isset($argv[2]) ? $argv[2] : '';
    switch ($argv[1]) {
        case '-u':
            $bdSync->uploadFile($source);
            break;
        case '-d':
            $bdSync->deleteFile($source);
            break;
        case '-m':
            break;
        case '-D':
            break;
        case '-init_upload':
            echo "目录".$source."上传中....\n";
            if($source && is_dir($source)) {
                $bdSync->init_upload($source);
            } else {
                exit('dir '.$source." not exists \n");
            }
            break;
        default:
            # code...
            break;
    }
} else {
    bdSync::showHelp();exit;
}

class bdSync
{
    public static $help = "
********************************************************************************
*
*    bdSync 基于百度网盘和inotify-tools构建的快速文件备份程序
*      (a file synchronization program based on baidu netdisk and inotify-tools)      
*    author: kong                                             
*    email:  249717835@qq.com                                 
*                                                            
********************************************************************************

usage
    参数：
        -h ：显示帮助 (show help)
        -u ：上传文件 (upload file to baidu netdisk)
        -init_upload:上传目录,后面跟目录路径 如-init_upload ~/Desktop/soft/
        -d ：删除文件 (delete file)
        -m ：创建目录 (make dir)
        -D ：删除目录 (remove dir)
";
    
    public $appname        = 'bdSync';
    public $root_dir       = 'myApp';
    private $tokenFile     = './token';
    
    private $access_token  = ACCESS_TOKEN;
    private $refresh_token = REFRESH_TOKEN;
    private $resource      = '';

    public static function showHelp() {
        echo self::$help;
    }

    public function uploadFile($file,$pre_dir) {
        //上传文件的目标保存路径，此处表示保存到应用根目录下
        $targetPath = '/apps/'.$this->appname."/".$this->root_dir."/";

        if($pre_dir) $targetPath = $targetPath . str_replace($pre_dir."/", '', $file);
        $targetPathArr = explode('/', $targetPath);
        array_pop($targetPathArr);
        $targetPath = implode('/', $targetPathArr) . "/";
        //要上传的本地文件路径
        $file = $file ? $file : dirname(__FILE__) . '/' . 'config.sample.jpg';
        //文件名称
        $fileName = basename($file);
        //新文件名，为空表示使用原有文件名
        $newFileName = '';
        $access_token = $this->getAccessToken();
        //echo $access_token;exit;

        $pcs = new BaiduPCS($access_token);
        echo "copy file 2 ".$targetPath . "\n";

        if (!file_exists($file)) {
            exit('file '.$file.' not exists');
        } else {
            $fileSize = filesize($file);
            $handle = fopen($file, 'rb');
            $fileContent = fread($handle, $fileSize);

            $result = $pcs->upload($fileContent, $targetPath, $fileName, $newFileName);
            fclose($handle);
            
            $returnArr =  json_decode(htmlspecialchars_decode($result,ENT_COMPAT),true);
            if(isset($returnArr['error_msg'])) {
                //echo $file ." upload error,error msg: ". $returnArr['error_msg'];
                $this->error_log($file ." upload error,error msg: ". $returnArr['error_msg']);
            }
        }

    }
    public function deleteFile($file) {
        $file = '/apps/'.$this->appname."/".$this->root_dir."/".$file."/";
        $access_token = $this->getAccessToken();
        $pcs = new BaiduPCS($access_token);
        $result = $pcs->deleteSingle($file);
        $returnArr =  json_decode(htmlspecialchars_decode($result,ENT_COMPAT),true);
        if(isset($returnArr['error_msg'])) {
            $this->error_log($file ." delete error,error msg: ". $returnArr['error_msg']);
        }
    }
    public function mkdir($path) {
        $path = '/apps/'.$this->appname."/".$this->root_dir."/".$path."/";
        $access_token = $this->getAccessToken();
        $pcs = new BaiduPCS($access_token);
        $result = $pcs->makeDirectory($path);
        $returnArr =  json_decode(htmlspecialchars_decode($result,ENT_COMPAT),true);
        if(isset($returnArr['error_msg'])) {
            $this->error_log($path ." add error,error msg: ". $returnArr['error_msg']);
        }

    }
    public function removeDir($path) {
        $path = '/apps/'.$this->appname."/".$this->root_dir."/".$path."/";
        $access_token = $this->getAccessToken();
        $pcs = new BaiduPCS($access_token);
        $result = $pcs->deleteSingle($path);
        $returnArr =  json_decode(htmlspecialchars_decode($result,ENT_COMPAT),true);
        if(isset($returnArr['error_msg'])) {
            $this->error_log($path ." remove error,error msg: ". $returnArr['error_msg']);
        }

    }

    public function init_upload($source) {
        if(!$this->resource) $this->resource = $source;
        $d = dir($source);
        while (false !== ($entry = $d->read())) {
            if($entry!='.' && $entry!='..') {
                $entry = $source.'/'.$entry;
                $entry = realpath($entry);
                if(is_dir($entry)) {
                    $this->init_upload($entry);
                } else {
                    $this->uploadFile($entry,$this->resource);
                    echo "upload file ". $entry ." successed \n";
                }
            }
        }
        $d->close();
        //$this->resource = '';
    }
    private function getAccessToken() {

        $tokenFile = $this->tokenFile;
        if(file_exists($tokenFile)) {
            $tokenStr = file_get_contents($tokenFile);
            $arr =  json_decode(htmlspecialchars_decode($tokenStr,ENT_COMPAT),true);
            $this->refresh_token = $arr['refresh_token'];
            if(filemtime($tokenFile) - 2592000 >= time()) {
                $this->refreshToken();
            } else {
                $this->access_token  = $arr['access_token'];
            }
        } 
        if (!$this->access_token) { 
            //write error log
            exit();
            return false;
        }
        return $this->access_token;
    }
    private function refreshToken() {
        $remote_url = 'https://openapi.baidu.com/oauth/2.0/token?grant_type=refresh_token&refresh_token='.$this->refresh_token.'&client_id='.API_KEY.'&client_secret='.SEC_KEY.'&scope=netdisk';
        $tokenStr = @file_get_contents($remote_url);

        if($tokenStr) {
            $fp = fopen($this->tokenFile, 'w');
            @fwrite($fp, $tokenStr);
            fclose($fp);
            $arr =  json_decode(htmlspecialchars_decode($tokenStr,ENT_COMPAT),true);
            $this->access_token  = isset($arr['access_token']) ? $arr['access_token'] : false;
            //$this->refresh_token = $arr['refresh_token'];
            return $this->access_token;
        } else {
            //write error log
            return false;
        }

    }
    private function error_log($msg) {
        $fp = fopen('./error.log', 'a');
        @fwrite($fp, $msg."\n");
        fclose($fp);
    }
}


?>