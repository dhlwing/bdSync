#!/usr/bin/php
<?php
define('ROOT_DIR',dirname(__FILE__));
include ROOT_DIR .'/config.php';
include ROOT_DIR .'/libs/BaiduPCS.class.php';

if($argc >= 2) {
    if(!in_array($argv[1], array('-u','-d','-m','-D','-init','-init_upload','-quota'))) {
        bdSync::showHelp();exit;
    }
    $bdSync  = new bdSync;
    $source  = isset($argv[2]) ? $argv[2] : '';
    $pre_dir = isset($argv[3]) ? $argv[3] : '';
    switch ($argv[1]) {
        case '-u':
            $bdSync->uploadFile($source,$pre_dir);
            break;
        case '-d':
            $bdSync->deleteFile($source);
            break;
        case '-m':
            break;
        case '-D':
            break;
        case '-quota':
            $bdSync->getQuota();
            break;
        case '-init':
            $bdSync->init();
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
        -h ：  显示帮助 (show help)
        -u ：  上传文件 (upload file to baidu netdisk)
        -init: 初始化 授权访问 获取权限
        -init_upload:上传目录,后面跟目录路径 如-init_upload ~/Desktop/soft/
        -quota: 获取空间配额信息
        -d ：  删除文件 (delete file)
        -m ：  创建目录 (make dir)
        -D ：  删除目录 (remove dir)
";
    
    public $appname        = 'bdSync';
    public $root_dir       = 'myApp';

    private $tokenFile     = 'token';
    
    private $access_token  = ACCESS_TOKEN;
    private $refresh_token = REFRESH_TOKEN;
    private $resource      = '';

    public static function showHelp() {
        echo self::$help;
    }

    public function init() {
        $para         = 'client_id='.API_KEY.'&response_type=device_code&scope=basic,netdisk';
        $remote_url   = 'https://openapi.baidu.com/oauth/2.0/device/code?'.$para;
        $tokenStr     = @file_get_contents($remote_url);
        $device_array = json_decode($tokenStr,true);
        !$device_array && exit('error for visit remote server,please try again');
        if(isset($device_array['error'])){
            echo('OAuth error '.$device_array['error'].' : '.$device_array['error_description']);
            exit();
        }
        echo "open your browser and type the url:".$device_array['verification_url'] . "\nInput ".$device_array['user_code']." as the user code if asked\n 打开浏览器 访问网址：".$device_array['verification_url'].",如有要求请输入".$device_array['user_code']."\n";
        echo "type the enter for continue...";
        trim(fgets(STDIN));
        for (;  ; ) { 
            $token_para='grant_type=device_token&code=' . $device_array['device_code'] . '&client_id=' . API_KEY . '&client_secret=' . SEC_KEY;
            $tokenStr = @file_get_contents('https://openapi.baidu.com/oauth/2.0/token?'.$token_para);
            $token_array = json_decode($tokenStr,true);
            if($token_array && !isset($token_array['error'])){
                $this->writeNewToken($tokenStr);
                echo "初始化成功，现在可以使用bdSync进行文件同步了\n";
                break;
            }else{
                echo("Something wrong. Please check the error message and try again.\n");
                echo "open your browser and type the url:".$device_array['verification_url'] . "\nInput ".$device_array['user_code']." as the user code if asked\n 打开浏览器 访问网址：".$device_array['verification_url'].",如有要求请输入".$device_array['user_code']."\n";
                echo "type the enter for continue...";
                trim(fgets(STDIN));
                continue;
            }
            break;
        }

    }

    /**
     * @desc 上传文件
     * @param $file 要上传的文件
     * @param $pre_dir 上传文件要删除的字符,可选
     * @ex  上传文件 uploadFile('/home/www/text.txt','/home') 则上传到网盘根目录下的/www/text.txt 
     */
    public function uploadFile($file,$pre_dir='') {
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
        echo "copy file to ".$targetPath . "\n";

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
            } else {
                if(DEBUG) {
                    echo "file ".$file ." upload successed\n";
                }
            }
        }

    }
    public function deleteFile($file,$pre_dir) {
        $targetPath = '/apps/'.$this->appname."/".$this->root_dir."/";

        if($pre_dir) $file = $targetPath . str_replace($pre_dir."/", '', $file);
        //$targetPathArr = explode('/', $targetPath);
        //array_pop($targetPathArr);
        //$file = implode('/', $targetPathArr) . "/";
        $access_token = $this->getAccessToken();
        $pcs = new BaiduPCS($access_token);
        $result = $pcs->deleteSingle($file);
        $returnArr =  json_decode(htmlspecialchars_decode($result,ENT_COMPAT),true);
        if(isset($returnArr['error_msg'])) {
            $this->error_log($file ." delete error,error msg: ". $returnArr['error_msg']);
        } else {
            if(DEBUG) {
                echo "file ".$file ." delete successed\n";
            }
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
    //获取配额信息
    public function getQuota() {
        $access_token = $this->getAccessToken();
        $pcs = new BaiduPCS($access_token);
        $quota = $pcs->getQuota();
        $quotaArr =  json_decode(htmlspecialchars_decode($quota,ENT_COMPAT),true);
        if(!$quotaArr || isset($quotaArr['error_msg'])) {
            echo "error for gey quota,try again.\n";
        } else {
            echo "Space : " . $this->formatSize(intval($quotaArr['quota']))."\n";
            echo "Used : " . $this->formatSize( intval( $quotaArr['used']) )."\n";
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
                    if(DEBUG)
                        echo "upload file ". $entry ." successed \n";
                }
            }
        }
        $d->close();
        //$this->resource = '';
    }
    private function getAccessToken() {

        $tokenFile = ROOT_DIR."/".$this->tokenFile;
        if(file_exists($tokenFile)) {
            $tokenStr = file_get_contents($tokenFile);
            $arr =  json_decode(htmlspecialchars_decode($tokenStr,ENT_COMPAT),true);
            $this->refresh_token = $arr['refresh_token'];
            if(filemtime($tokenFile) + 2592000 <= time()) {
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
        $tokenArr = json_decode(htmlspecialchars_decode($tokenStr,ENT_COMPAT),true);
        if(isset($device_array['error'])){
            $error = 'OAuth error '.$tokenArr['error'].' : '.$tokenArr['error_description'];
            if (DEBUG)
                echo $error."\n";
            $this->error_log($error);
            return false;
        }
        return $this->writeNewToken($tokenStr);

    }
    private function writeNewToken($tokenStr) {
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
        $fp = fopen(ROOT_DIR . '/error.log', 'a');
        @fwrite($fp, date("Y-m-d H:i:s")."\t".$msg ."\n");
        fclose($fp);
    }
    private function formatSize($size) {
        $bytes = array('','K','M','G','T');
        foreach($bytes as $val)  {
            if($size > 1024) {
                $size = $size / 1024;
            } else {
                break;
            }
        }
        return round($size, 1)." ".$val;
    }
}


?>