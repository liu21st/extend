<?php
/**
 * 在命令行中运行 php build.php <platform> <name> <package> [<version>]
 *
 * 参数说明：
 * platform ：输入android或ios
 * name ：应用名称
 * package： 应用的包名，如：com.think.yourname ，一般为一个域名的倒序。
 * version: 应用版本， 如为1.0
 * 
 * @author luofei614<weibo.com/luofei614>
 */
//判断是否为命令行
//error_reporting(0);
if('cli'!=PHP_SAPI){
    exit("must run from cmd\n");
}
$platform=isset($argv[1])?$argv[1]:'android';
$name=isset($argv[2])?$argv[2]:'TPM';
$package=isset($argv[3])?$argv[3]:'cn.thinkphp.tpm';
$version=isset($argv[4])?$argv[4]:'1.0';
if(!preg_match('/^\d{1,2}(\.\d{1,2}){0,2}$/',$version)){
     exit("invalid version\n");
}
//判断必须的php模块
if(!extension_loaded('zip')){
    exit("please install the zip extension\n");
}
if(!extension_loaded('curl')){
    exit("please install the curl extension\n");
}
define('__ROOT__',dirname(__FILE__).'/');
define('__API__','http://tpmbuild.thinkphp.cn/api.php');
//生成压缩包
$wwwzip=__ROOT__.'www.zip';
$zip=new ZipArchive();
if(!$zip->open($wwwzip,ZipArchive::CREATE)){
    exit("create zip failed!\n");
}
$filelist=get_file_list(__ROOT__);
foreach($filelist as $filepath=>$filename){
    $zip->addFile($filepath,$filename);
}
$zip->close();
//打包文件不能过大
if(filesize($wwwzip)>5242880){
   unlink($wwwzip);
   exit('the package directory too big! please limit to 5M'); 
}
//请求接口

$params=array(
    'type'=>$platform,
    'zip'=>'@'.$wwwzip,
    'name'=>$name,
    'package'=>$package,
    'version'=>$version
);
echo 'building...';
$ret=http(__API__,$params);
//循环查询打包结果
$times=0;
$url=__API__.'?token='.$ret['token'];
while($times<=40){
    echo '.';
    if(40==$times){
        $url.='&last_time=1';
    }
    $ret=http($url);
    if(isset($ret['url'])){
        //打包成功
        $apkpath=__ROOT__.$name.'-'.$version.substr($ret['url'],-4);
        @file_put_contents($apkpath,file_get_contents($ret['url']));
        if(!file_exists($apkpath)){
            exit("down apk failed!\n");
        }
        exit("\nsuccess!\n");
    } 
    $times++;
    sleep(1);
}


function http($url,$params=array()){
    global $wwwzip;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if(!empty($params)){
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $txt = curl_exec($ch);
    //删除压缩包
    if(file_exists($wwwzip))
        unlink($wwwzip);
    if (curl_errno($ch)) {
        exit("curl error :".curl_error($ch)."\n");
    }
    curl_close($ch);
    $ret = json_decode($txt, true);
    if(!$ret){
        exit("server response error:$ret\n");
    }
    if($ret['code']){
        exit("api error,[{$ret['code']}:{$ret['msg']}]\n");
    }
    return $ret;
}
function get_file_list($dir){
    $ret=array();
    $list=glob($dir.'*');
    foreach($list as $file){
        $ext=substr($file, -4);
        if(in_array($ext,array('.svn','.php','.apk','.ipa','.zip','.rar')))//过滤svn文件夹和php等文件。
           continue;
        if(is_dir($file)){
            $ret=  array_merge($ret,  get_file_list($file.'/'));
        }else{
            $ret[$file]=  substr($file,strlen(__ROOT__));
        }
    }
    return $ret;
}
