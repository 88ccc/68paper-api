<?php

namespace app\tool;

use app\service\ConfigService;
use think\facade\Log;
use AlibabaCloud\Oss\V2 as Oss;

class AliOSSTool
{

    private $ossClient = null;
    private $bucket = "";

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

    public function up_file($file, $file_key, $file_name = '')
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

    public function get_down_url($file_key)
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
    public function delete_dir($dir)
    {
        $paginator = new Oss\Paginator\ListObjectsV2Paginator(client: $this->ossClient);
        $iter = $paginator->iterPage(new Oss\Models\ListObjectsV2Request(
            bucket: $this->bucket,
            prefix: $dir, // 设置前缀，用于筛选指定目录下的对象
        )); // 初始化分页迭代器

        // 遍历对象分页结果
        foreach ($iter as $page) {
            foreach ($page->contents ?? [] as $object) {
                // 打印每个对象的关键信息
                // 输出对象的Key、类型和大小
                print("Object: $object->key, $object->type, $object->size\n");
                $request = new Oss\Models\DeleteObjectRequest(bucket: $this->ossClient, key: $object->key);

                // 执行删除对象操作
                $result = $this->ossClient->deleteObject($request);
            }
        }
    }
}
