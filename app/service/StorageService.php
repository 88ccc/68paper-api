<?php

namespace app\service;


use app\service\ConfigService;
use think\facade\Config;
use think\facade\Log;
use app\tool\QcloudCosTool;
use app\tool\AliOSSTool;
use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class StorageService
{
    public static function save($file, $file_name)
    {
        //file_name 不含扩展名
        $storageconfig = ConfigService::get("storage");
        $type = 1; //1=本地，2=腾讯云，3=阿里云
        if (empty($storageconfig)) {
            $type = 1;
        } else {
            if ($storageconfig['storageType'] == "tencent") {
                $type = 2;
            } else if ($storageconfig['storageType'] == "ali") {
                $type = 3;
            }
        }
        if ($type == 1) {
            $save_path = public_path() .  '/report/' . date('Ymd/');
            if (!file_exists($save_path)) {
                $ret = mkdir($save_path, 0777, true);
                if ($ret === false) {
                    Log::error("save_report 创建文件夹失败 " . $save_path);
                    return null;
                }
            }
            $file_t = pathinfo($file, PATHINFO_EXTENSION);
            $save_file = $save_path . $file_name . '.' . $file_t;
            if ($save_file != $file) {
                copy($file, $save_file);
            }
            //下载地址
            $down_url = Config::get('website.api_domain') .  '/report/' . date('Ymd/') . urlencode($file_name) . '.' . $file_t;
            return $down_url;
        }
        if ($type == 2) {
            $basename = pathinfo($file, PATHINFO_BASENAME);
            $file_key = 'ppxuezi/report/' . date('Ymd/') . $basename;
            $cos = new QcloudCosTool();
            $ret = $cos->up_file($file, $file_key, $file_name);
            if ($ret === 0) {
                $down_url = $cos->get_down_url($file_key);
                return $down_url;
            }
            return null;
        }
        if ($type == 3) {
            $basename = pathinfo($file, PATHINFO_BASENAME);
            $file_key = 'ppxuezi/report/' . date('Ymd/') . $basename;
            $oss = new AliOSSTool();
            $ret = $oss->up_file($file, $file_key, $file_name);
            if ($ret === 0) {
                $down_url = $oss->get_down_url($file_key);
                return $down_url;
            }
            return null;
        }
    }

    public function clean_report()
    {
        $time = date("Ymd", strtotime("-7 day"));
        $time1 = date("Ymd", strtotime("-8 day"));
        $storageconfig = ConfigService::get("storage");
        $type = 1; //1=本地，2=腾讯云，3=阿里云
        if (empty($storageconfig)) {
            $type = 1;
        } else {
            if ($storageconfig['storageType'] == "tencent") {
                $type = 2;
            } else if ($storageconfig['storageType'] == "ali") {
                $type = 3;
            }
        }
        if ($type == 1) {
            $path = public_path() .  '/report/' . $time;
            $path1 = public_path() .  '/report/' . $time1;
            try {
                $this->deleteDirectory($path);
                $this->deleteDirectory($path1);
            } catch (Exception $e) {
                // 捕获异常并输出错误信息
            }
        } else if ($type == 2) {
            $cos = new QcloudCosTool();
            $cos->delete_dir('ppxuezi/report/' . $time);
            $cos->delete_dir('ppxuezi/report/' . $time1);
        } else if ($type == 3) {
            $oss = new AliOSSTool();
            $oss->delete_dir('ppxuezi/report/' . $time);
            $oss->delete_dir('ppxuezi/report/' . $time1);
        }
    }


    private  function deleteDirectory(string $dirPath): bool
    {
        // 1. 标准化路径，确保路径格式统一
        $dirPath = rtrim($dirPath, '/') . '/';

        // 2. 验证路径是否存在
        if (!file_exists($dirPath)) {
            throw new Exception("文件夹不存在：{$dirPath}");
        }

        // 3. 验证路径是否为目录
        if (!is_dir($dirPath)) {
            throw new Exception("指定路径不是文件夹：{$dirPath}");
        }

        // 4. 安全校验：限制删除范围（可选，防止误删系统目录）
        $allowedBaseDir = '/www/wwwroot/data/';
        if (strpos($dirPath, $allowedBaseDir) !== 0) {
            throw new Exception("禁止删除非授权目录：{$dirPath}");
        }

        // 5. 递归删除文件夹内的所有内容
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            // 判断是文件还是文件夹，分别处理
            if ($fileInfo->isFile() || $fileInfo->isLink()) {
                // 删除文件/软链接
                if (!unlink($fileInfo->getPathname())) {
                    throw new Exception("无法删除文件：{$fileInfo->getPathname()}，请检查权限");
                }
            } elseif ($fileInfo->isDir()) {
                // 删除空文件夹
                if (!rmdir($fileInfo->getPathname())) {
                    throw new Exception("无法删除文件夹：{$fileInfo->getPathname()}，请检查权限");
                }
            }
        }

        // 6. 删除最外层空文件夹
        if (!rmdir($dirPath)) {
            throw new Exception("无法删除根文件夹：{$dirPath}，请检查权限");
        }

        return true;
    }
}
