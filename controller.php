<?php
//header('Access-Control-Allow-Origin: http://www.baidu.com'); //设置http://www.baidu.com允许跨域访问
//header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With'); //设置允许的跨域header
date_default_timezone_set("Asia/chongqing");
error_reporting(E_ERROR);
header("Content-Type: text/html; charset=utf-8");
include "./vendor/autoload.php";
$config = [
    'imagePathFormat'=>'/ueditor/image/{yyyy}{mm}{dd}/{time}{rand:6}'
];
$c = new \ScarecrowUeDitor\ScarecrowController($config);
echo $c->action($_GET);
