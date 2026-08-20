<?php

namespace app\tool;

use app\service\ConfigService;
use Qcloud\Cos\Client;
use think\facade\Log;
class QcloudCosTool
{
    private Client|null $cosClient = null;
    private ?string $bucket = null;
    public function __construct()
    {
        $storageconfig = ConfigService::get("storage");
        if (!empty($storageconfig)) {
            if ($storageconfig['storageType'] == "tencent") {
                $this->cosClient = new Client(
                    array(
                        'region' => $storageconfig['tencent']['region'],
                        'scheme' => 'https', //协议头部，默认为http
                        'signHost' => true, //默认签入Header Host；您也可以选择不签入Header Host，但可能导致请求失败或安全漏洞,若不签入host则填false
                        'credentials' => array(
                            'secretId'  => $storageconfig['tencent']['secretId'],
                            'secretKey' => $storageconfig['tencent']['secretKey']
                        )
                    )
                );

                $this->bucket = $storageconfig['tencent']['bucket'];
            }
        }
    }

    //传见上传链接
    public function getPutObjectUrl(string $filekey):string|null
    {
        if ($this->cosClient == null) {
            return null;
        }
        try {
            $signedUrl = $this->cosClient->getPreSignedUrl('putObject', array(
                'Bucket' => $this->bucket, //存储桶，格式：BucketName-APPID
                'Key' => $filekey, //对象在存储桶中的位置，即对象键
                'Body' => 'string', //可为空或任意字符串
                'Params' => array(), //http 请求参数，传入的请求参数需与实际请求相同，能够防止用户篡改此HTTP请求的参数,默认为空
                'Headers' => array(), //http 请求头部，传入的请求头部需包含在实际请求中，能够防止用户篡改签入此处的HTTP请求头部,默认签入host
            ), '+10 minutes'); //签名的有效时间
            // 请求成功
            return $signedUrl;
        } catch (\Exception $e) {
            // 请求失败
            Log::error($e);
            return null;
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
        try {
            $result = $this->cosClient->upload(
                $bucket = $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                $key = $file_key, //此处的 key 为对象键
                $body = fopen($file, 'rb'),
                $options = array(
                    'ContentDisposition' => 'attachment; filename="' . $file_namez . '"'
                )
            );
            // 请求成功
        } catch (\Exception $e) {
            // 请求失败
            Log::error("QcloudCos 上传文件失败");
            //Log::write($e);
            return -2;
        }
        return 0;
    }

    public function get_down_url(string $file_key):string
    {
        $signedUrl = '';
        try {
            $bucket = $this->bucket; //存储桶，格式：BucketName-APPID
            $key = $file_key;  //此处的 key 为对象键，对象键是对象在存储桶中的唯一标识
            $signedUrl = $this->cosClient->getObjectUrl($bucket, $key, '+7 days');
            // 请求成功

        } catch (\Exception $e) {
            // 请求失败
            Log::error("QcloudCos获取文件链接失败");
            //Log::write($e);

        }
        return $signedUrl;
    }

    public function delete_dir(string $dir): void
    {
        // 删除该前缀下的所有对象，例如 "cos/folder"
        // 统一规范为以 / 结尾，避免误删同前缀的其它文件（如 cos/folder2 下的对象）
        $cos_prefix = rtrim($dir, '/') . '/';
        $nextMarker = '';
        $isTruncated = true;
        while ($isTruncated) {
            try {
                $result = $this->cosClient->listObjects(
                    [
                        'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                        'Delimiter' => '',
                        'EncodingType' => 'url',
                        'Marker' => $nextMarker,
                        'Prefix' => $cos_prefix,
                        'MaxKeys' => 1000
                    ]
                );
                // 目录不存在或为空时，响应中没有 Contents / IsTruncated / NextMarker 字段，需用默认值兜底
                $isTruncated = $result['IsTruncated'] ?? false;
                $contents = $result['Contents'] ?? [];
                $lastKey = '';
                foreach ($contents as $content) {
                    $cos_file_path = $content['Key'];
                    $lastKey = $cos_file_path;
                    try {
                        $this->cosClient->deleteObject([
                            'Bucket' => $this->bucket,
                            'Key' => $cos_file_path,
                        ]);
                    } catch (\Exception $e) {
                        // 单个对象删除失败，记录后继续删除其余对象
                        Log::error("QcloudCos 删除对象失败: {$cos_file_path}");
                    }
                }
                // 注意：EncodingType=url 时接口不会返回 NextMarker，需用最后一个 Key 作为下一页 Marker
                $nextMarker = $result['NextMarker'] ?? $lastKey;
                if (empty($nextMarker) && $isTruncated) {
                    Log::error('QcloudCos 分页无法获取 nextMarker，终止删除');
                    break;
                }
            } catch (\Exception $e) {
                Log::error('QcloudCos 列目录失败: ' . $e->getMessage());
                break; // 出错时退出循环，避免死循环
            }
        }
    }
}
