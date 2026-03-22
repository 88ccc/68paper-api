<?php

namespace app\tool;

use app\service\ConfigService;
use Qcloud\Cos\Client;
use think\facade\Log;
class QcloudCosTool
{
    private $cosClient = null;
    private $bucket = null;
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
    public function getPutObjectUrl($filekey)
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

    public function get_down_url($file_key)
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

    public function delete_dir($dir)
    {
        $cos_prefix = $dir; // 例如 "cos/folder"; 不得以/开头
        $nextMarker = '';
        $cos_file_path = '';
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
                $isTruncated = $result['IsTruncated'];
                $nextMarker = $result['NextMarker'];
                foreach ($result['Contents'] as $content) {
                    $cos_file_path = $content['Key'];
                    //$local_file_path = $content['Key'];
                    // 按照需求自定义拼接下载路径
                    try {
                        $this->cosClient->deleteObject(array(
                            'Bucket' => $this->bucket, //存储桶名称，由BucketName-Appid 组成，可以在COS控制台查看 https://console.cloud.tencent.com/cos5/bucket
                            'Key' => $cos_file_path,
                        ));
                        //echo ($cos_file_path . "\n");
                    } catch (\Exception $e) {
                        //echo ($e);
                    }
                }
            } catch (\Exception $e) {
                echo ($e);
            }
        }
    }
}
