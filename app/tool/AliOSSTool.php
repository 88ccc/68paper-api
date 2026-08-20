<?php

namespace app\tool;

use app\service\ConfigService;
use think\facade\Log;
use AlibabaCloud\Oss\V2 as Oss;

class AliOSSTool
{

    private Oss\Client|null $ossClient = null;
    private string $bucket = "";

    public function __construct()
    {
        $storageconfig = ConfigService::get("storage");
        if (!empty($storageconfig)) {
            if ($storageconfig['storageType'] == "ali") {
                $config = $storageconfig['ali'];
                $credentialsProvider = new Oss\Credentials\StaticCredentialsProvider($config['accessKeyId'], $config['accessKeySecret']);
                $cfg = Oss\Config::loadDefault();
                $cfg->setCredentialsProvider(credentialsProvider: $credentialsProvider);
                $cfg->setRegion(region: $config['region']);
                $this->ossClient = new Oss\Client($cfg);
                $this->bucket = $config['bucket'];
            }
        }
    }

    public function up_file(string $file, string $file_key, string $file_name = ''):int
    { //文件名不得包含后缀
        $file_t = pathinfo($file, PATHINFO_EXTENSION);
        $file_namez = pathinfo($file, PATHINFO_BASENAME);
        if (!empty($file_name)) {
            $file_namez = $file_name . '.' . $file_t;
        }
        if (!file_exists($file)) {
            Log::error("file not exists " . $file);
            return -1;
        }
        if ($this->ossClient == null) {
            Log::error("ossClient is null");
            return -2;
        }
        $body = Oss\Utils::streamFor(fopen($file, 'r'));
        $disposition = 'attachment; filename="' . $file_namez . '"';
        $request = new Oss\Models\PutObjectRequest(bucket: $this->bucket, key: $file_key, contentDisposition:$disposition);
        $request->body = $body;
        $result = $this->ossClient->putObject($request);
        if ($result->statusCode != 200) {
            Log::error("ali oss putObject fail statusCode =".$result->statusCode);
            Log::error("ali oss putObject fail requestId =".$result->requestId);
            return -3;
        }
        return 0;
    }

    public function get_down_url(string $file_key):string
    {
        $signedUrl = '';
        try {
            $request = new Oss\Models\GetObjectRequest(bucket: $this->bucket, key: $file_key);
            // 调用presign方法生成预签名URL，设置过期时间
            $expire = 7;
            $result = $this->ossClient->presign($request, [
                'expires' => new \DateInterval("P{$expire}D") // PT表示Period Time，S表示秒
            ]);
            $signedUrl = $result->url;
        } catch (\Exception $e) {
            // 请求失败
            Log::error("获取文件下载地址失败");
            Log::write($e);
        }
        return $signedUrl;
    }
    public function delete_dir(string $dir): void
    {
        if ($this->ossClient == null) {
            Log::error('ossClient is null');
            return;
        }
        // 删除该前缀下的所有对象，例如 "folder"
        // 统一规范为以 / 结尾，避免误删同前缀的其它对象（如 folder2 下的对象）
        $prefix = rtrim($dir, '/') . '/';
        $paginator = new Oss\Paginator\ListObjectsV2Paginator(client: $this->ossClient);
        $iter = $paginator->iterPage(new Oss\Models\ListObjectsV2Request(
            bucket: $this->bucket,
            prefix: $prefix, // 设置前缀，用于筛选指定目录下的对象
        )); // 初始化分页迭代器，自动翻页直到遍历完所有对象

        try {
            // 遍历对象分页结果
            foreach ($iter as $page) {
                $contents = $page->contents ?? [];
                if (empty($contents)) {
                    continue;
                }
                // 收集本页对象，批量删除（单次请求上限 1000 个，正好与每页数量对应）
                $objects = [];
                foreach ($contents as $object) {
                    $objects[] = new Oss\Models\DeleteObject(key: $object->key);
                }
                $request = new Oss\Models\DeleteMultipleObjectsRequest(
                    bucket: $this->bucket,
                    objects: $objects,
                    quiet: true, // 静默模式，失败时不逐个返回删除信息，减少响应体
                );
                $result = $this->ossClient->deleteMultipleObjects($request);
                if ($result->statusCode != 200) {
                    Log::error("ali oss deleteMultipleObjects fail statusCode = {$result->statusCode}, requestId = {$result->requestId}");
                }
            }
        } catch (\Exception $e) {
            Log::error('ali oss 删除目录失败: ' . $e->getMessage());
        }
    }
}
