<?php

namespace app\tool;

use think\facade\Log;
use think\facade\Config;

class AgisoTool
{
    public function generateSign(array $params, string $appSecret)
    {
        ksort($params);
        $str = '';
        foreach ($params as $key => $value) {
            $str .= ($key . $value);
        }
        $str = $appSecret . $str . $appSecret;
        return md5($str);
    }

    public function getProviderInfo(array $params)
    {
        if (empty($params)) {
            Log::write("阿索奇获取供货商信息参数错误");
            return [
                'code' => 1,
                'msg' => '参数错误'
            ];
        }
        //查询列表
        if (empty($params['appid'])) {
            Log::write("阿索奇获取供货商信息appid不能为空");
            return [
                'code' => 1,
                'msg' => 'appid不能为空'
            ];
        }
        $appid = $params['appid'];
        if (empty($params['secret'])) {
            Log::write("阿索奇获取供货商信息secret不能为空");
            return [
                'code' => 1,
                'msg' => 'secret不能为空'
            ];
        }
        $secret = $params['secret'];

        $data = [
            'timestamp' => time(),
            'appId' => $appid,
        ];
        $sign = $this->generateSign($data, $secret);
        $data['sign'] = $sign;
        $url = 'https://gw-api.agiso.com/open/ProviderManage/QueryProviderList';
        $result = $this->httpPostForm($url, $data, ['ApiVersion' => "1"]);
        if ($result['code'] != 0) {
            Log::write("查询阿索奇标准货源供货商列表结果");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '查询列表失败'
            ];
        }
        //看有没有列表
        $data = $result['data'];
        if (empty($data['IsSuccess'])) {
            Log::write("查询阿索奇标准货源供货商列表结果");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '查询列表失败'
            ];
        }
        if ($data['IsSuccess'] != true) {
            Log::write("查询阿索奇标准货源供货商列表结果");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '查询列表失败'
            ];
        }
        if (empty($data['Data'])) {
            Log::write("查询阿索奇标准货源供货商列表结果");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '查询列表失败'
            ];
        }
        if (!isset($data['Data']['List'])) {
            Log::write("查询阿索奇标准货源供货商列表结果");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '查询列表失败'
            ];
        }
        $list = $data['Data']['List'];
        $need_add = false;
        $need_update = false;
        $providerId = "";
        $providerGuid = "";
        $host = Config::get('website.api_domain');
        if (empty($list)) {
            $need_add = true;
        } else {
            $pr = $list[0];
            $providerId = $pr['ProviderId'];
            $providerGuid = $pr['ProviderGuid'];
            if ($pr['Name'] != $params['name']) {
                $need_update = true;
            } else if ($pr['Host'] != $host) {
                $need_update = true;
            } else if ($pr['Phone'] != $params['phone']) {
                $need_update = true;
            }
        }
        if ($need_add) {
            $ret = $this->addProvider($params);
            if ($ret['code'] != 0) {
                return $ret;
            }
        } else if ($need_update) {
            $ret = $this->updateProvider($providerId, $params);
            if ($ret['code'] != 0) {
                return $ret;
            }
        } else {
            return [
                'code' => 0,
                'msg' => '阿索奇标准货源供货商已存在',
                'providerId' => $providerId,
                'providerGuid' => $providerGuid,
            ];
        }
        return [
            'code' => 0,
            'msg' => '需要重试'
        ];
    }

    public function updateProvider(string $providerId, array $params)
    {
        $host = Config::get('website.api_domain');
        $data = [
            'timestamp' => time(),
            'appId' => $params['appid'],
            'providerId' => $providerId,
            'providerName' => $params['name'],
            'providerHost' => $host,
            'providerPhone' => $params['phone'],
        ];
        $sign = $this->generateSign($data, $params['secret']);
        $data['sign'] = $sign;
        $url = 'https://gw-api.agiso.com/open/ProviderManage/UpdateProvider';
        $result = $this->httpPostForm($url, $data, ['ApiVersion' => "1"]);
        if ($result['code'] != 0) {
            Log::write("更新阿索奇标准货源供货商结果1");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '更新阿索奇标准货源供货商失败'
            ];
        }
        $data = $result['data'];
        if (empty($data['IsSuccess'])) {
            Log::write("更新阿索奇标准货源供货商结果2");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '更新阿索奇标准货源供货商失败'
            ];
        }
        if ($data['IsSuccess'] != true) {
            Log::write("更新阿索奇标准货源供货商结果3");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '更新阿索奇标准货源供货商失败'
            ];
        }
        return [
            'code' => 0,
            'msg' => '更新阿索奇标准货源供货商成功'
        ];
    }

    public function addProvider(array $params)
    {
        $host = Config::get('website.api_domain');
        $data = [
            'timestamp' => time(),
            'appId' => $params['appid'],
            'providerName' => $params['name'],
            'providerHost' => $host,
            'providerPhone' => $params['phone'],
        ];
        $sign = $this->generateSign($data, $params['secret']);
        $data['sign'] = $sign;
        $url = 'https://gw-api.agiso.com/open/ProviderManage/AddProvider';
        $result = $this->httpPostForm($url, $data, ['ApiVersion' => "1"]);

        if ($result['code'] != 0) {
            Log::write("新增阿索奇标准货源供货商结果");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '新增阿索奇标准货源供货商失败'
            ];
        }
        $data = $result['data'];
        if (empty($data['IsSuccess'])) {
            Log::write("新增阿索奇标准货源供货商结果");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '新增阿索奇标准货源供货商失败'
            ];
        }
        if ($data['IsSuccess'] != true) {
            Log::write("新增阿索奇标准货源供货商结果");
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '新增阿索奇标准货源供货商失败'
            ];
        }
        return [
            'code' => 0,
            'msg' => '新增阿索奇标准货源供货商成功'
        ];
    }

    /**
     * POST 请求，Content-Type: application/x-www-form-urlencoded
     * @param string $url 请求地址
     * @param array $params POST参数数组
     * @param array $header 请求头数组
     * @return array [code:int, data:array, msg:string]
     */
    function httpPostForm(string $url, array $params, array $header = []): array
    {
        $ch = curl_init($url);

        // 把数组转成 urlencoded 字符串
        $postString = http_build_query($params);

        // 强制设置表单content-type
        $header[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postString,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // CURL底层错误
        if ($curlErrno !== 0) {
            return [
                'code' => -1,
                'data' => [],
                'msg' => "CURL错误：{$curlError}"
            ];
        }

        // HTTP状态码判断
        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'code' => $httpCode,
                'data' => [],
                'msg' => "HTTP请求失败，状态码：{$httpCode}"
            ];
        }

        // 解析返回JSON为数组
        $resultData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'code' => -2,
                'data' => [],
                'msg' => "JSON解析失败: " . json_last_error_msg() . "，原始返回：{$response}"
            ];
        }

        return [
            'code' => 0,
            'data' => $resultData,
            'msg' => 'success'
        ];
    }
}
