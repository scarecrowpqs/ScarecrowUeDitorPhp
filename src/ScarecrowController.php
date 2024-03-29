<?php
namespace ScarecrowUeDitor;
/**
 * Created by PhpStorm.
 * User: someOne
 * Date: 2019/10/30
 * Time: 17:23
 */
class ScarecrowController {

    protected $CONFIG=[];

    public function __construct($config=[])
    {
        set_time_limit(0);
        $this->CONFIG = $config+json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents(__DIR__ . "/config.json")), true);
    }

    public function action($getArray = []) {
        $action = $getArray['action'];
        $result = call_user_func([$this, "Scarecrow_".$action],$getArray);
        $result = json_encode($result, JSON_UNESCAPED_UNICODE);
        /* 输出结果 */
        if (isset($getArray["callback"])) {
            if (preg_match("/^[\w_]+$/", $getArray["callback"])) {
                return htmlspecialchars($getArray["callback"]) . '(' . $result . ')';
            } else {
                return json_encode(array(
                    'state'=> 'callback参数不合法'
                ));
            }
        } else {
            return $result;
        }
    }

    /**
     * 获取配置文件
     * @return string
     */
    protected function Scarecrow_config() {
        $result =  $this->CONFIG;
        return $result;
    }

    /**
     * 上传文件
     * @param $get
     * @return array
     */
    protected function uploadFile($get) {
        /* 上传配置 */
        $base64 = "upload";
        switch (htmlspecialchars($get['action'])) {
            case 'uploadimage':
                $config = array(
                    "pathFormat" => $this->CONFIG['imagePathFormat'],
                    "maxSize" => $this->CONFIG['imageMaxSize'],
                    "allowFiles" => $this->CONFIG['imageAllowFiles']
                );
                $fieldName = $this->CONFIG['imageFieldName'];
                break;
            case 'uploadscrawl':
                $config = array(
                    "pathFormat" => $this->CONFIG['scrawlPathFormat'],
                    "maxSize" => $this->CONFIG['scrawlMaxSize'],
                    "allowFiles" => $this->CONFIG['scrawlAllowFiles'],
                    "oriName" => "scrawl.png"
                );
                $fieldName = $this->CONFIG['scrawlFieldName'];
                $base64 = "base64";
                break;
            case 'uploadvideo':
                $config = array(
                    "pathFormat" => $this->CONFIG['videoPathFormat'],
                    "maxSize" => $this->CONFIG['videoMaxSize'],
                    "allowFiles" => $this->CONFIG['videoAllowFiles']
                );
                $fieldName = $this->CONFIG['videoFieldName'];
                break;
            case 'uploadfile':
            default:
                $config = array(
                    "pathFormat" => $this->CONFIG['filePathFormat'],
                    "maxSize" => $this->CONFIG['fileMaxSize'],
                    "allowFiles" => $this->CONFIG['fileAllowFiles']
                );
                $fieldName = $this->CONFIG['fileFieldName'];
                break;
        }

        /* 生成上传实例对象并完成上传 */
        $up = new ScarecrowUploader($fieldName, $config, $base64);

        /**
         * 得到上传文件所对应的各个参数,数组结构
         * array(
         *     "state" => "",          //上传状态，上传成功时必须返回"SUCCESS"
         *     "url" => "",            //返回的地址
         *     "title" => "",          //新文件名
         *     "original" => "",       //原始文件名
         *     "type" => ""            //文件类型
         *     "size" => "",           //文件大小
         * )
         */

        /* 返回数据 */
        return $up->getFileInfo();
    }

    protected function Scarecrow_uploadimage($get = []) {
        return $this->uploadFile($get);
    }

    protected function Scarecrow_uploadscrawl($get = []) {
        return $this->uploadFile($get);
    }

    protected function Scarecrow_uploadvideo($get = []) {
        return $this->uploadFile($get);
    }

    protected function Scarecrow_uploadfile($get = []) {
        return $this->uploadFile($get);
    }

    /**
     * 获取文件列表
     * @param $get
     * @return array
     */
    protected function getFileList($get) {
        /* 判断类型 */
        switch ($get['action']) {
            /* 列出文件 */
            case 'listfile':
                $allowFiles = $this->CONFIG['fileManagerAllowFiles'];
                $listSize = $this->CONFIG['fileManagerListSize'];
                $path = $this->CONFIG['fileManagerListPath'];
                break;
            /* 列出图片 */
            case 'listimage':
            default:
                $allowFiles = $this->CONFIG['imageManagerAllowFiles'];
                $listSize = $this->CONFIG['imageManagerListSize'];
                $path = $this->CONFIG['imageManagerListPath'];
        }
        $allowFiles = substr(str_replace(".", "|", join("", $allowFiles)), 1);

        /* 获取参数 */
        $size = isset($get['size']) ? htmlspecialchars($get['size']) : $listSize;
        $start = isset($get['start']) ? htmlspecialchars($get['start']) : 0;
        $end = $start + $size;

        /* 获取文件列表 */
        $path = $_SERVER['DOCUMENT_ROOT'] . (substr($path, 0, 1) == "/" ? "":"/") . $path;
        $files = $this->getfiles($path, $allowFiles);
        if (!count($files)) {
            return array(
                "state" => "no match file",
                "list" => array(),
                "start" => $start,
                "total" => count($files)
            );
        }

        /* 获取指定范围的列表 */
        $len = count($files);
        for ($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i--){
            $list[] = $files[$i];
        }

        /* 返回数据 */
        $result = array(
            "state" => "SUCCESS",
            "list" => $list,
            "start" => $start,
            "total" => count($files)
        );

        return $result;
    }

    /**
     * 获取文件
     * @param $path
     * @param $allowFiles
     * @param array $files
     * @return array|null
     */
    protected function getfiles($path, $allowFiles, &$files = array()) {
        if (!is_dir($path)) return null;
        if(substr($path, strlen($path) - 1) != '/') $path .= '/';
        $handle = opendir($path);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getfiles($path2, $allowFiles, $files);
                } else {
                    if (preg_match("/\.(".$allowFiles.")$/i", $file)) {
                        $files[] = array(
                            'url'=> substr($path2, strlen($_SERVER['DOCUMENT_ROOT'])),
                            'mtime'=> filemtime($path2)
                        );
                    }
                }
            }
        }
        return $files;
    }

    protected function Scarecrow_listimage($get) {
        return $this->getFileList($get);
    }

    protected function Scarecrow_listfile($get) {
        return $this->getFileList($get);
    }

    protected function Scarecrow_catchimage($get) {
        /* 上传配置 */
        $config = array(
            "pathFormat" => $this->CONFIG['catcherPathFormat'],
            "maxSize" => $this->CONFIG['catcherMaxSize'],
            "allowFiles" => $this->CONFIG['catcherAllowFiles'],
            "oriName" => "remote.png"
        );
        $fieldName = $this->CONFIG['catcherFieldName'];

        /* 抓取远程图片 */
        $list = array();
        if (isset($_POST[$fieldName])) {
            $source = $_POST[$fieldName];
        } else {
            $source = $get[$fieldName];
        }
        foreach ($source as $imgUrl) {
            $item = new ScarecrowUploader($imgUrl, $config, "remote");
            $info = $item->getFileInfo();
            array_push($list, array(
                "state" => $info["state"],
                "url" => $info["url"],
                "size" => $info["size"],
                "title" => htmlspecialchars($info["title"]),
                "original" => htmlspecialchars($info["original"]),
                "source" => htmlspecialchars($imgUrl)
            ));
        }

        /* 返回抓取数据 */
        return array(
            'state'=> count($list) ? 'SUCCESS':'ERROR',
            'list'=> $list
        );
    }

    protected function Scarecrow_error() {
        return [
            'state' =>  '请求地址出错'
        ];
    }

}