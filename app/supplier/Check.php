<?php
// 自定义工具类 ConfigService.php
namespace app\supplier;

use think\facade\Log;
use app\service\ConfigService;

class Check
{

    private $baseUrl = "https://api.88xuezi.com/api/";
    /**
     * 生成签名
     */
    private function generateSignature(array $params, string $signKey): string
    {
        // 1. 参数过滤和排序
        $filteredParams = [];

        foreach ($params as $key => $value) {
            // 过滤掉null、空字符串和"sign"字段
            if (($value !== null) && ($value !== '') && ($key !== 'sign') && ($key !== 'text') && ($key !== 'file')) {
                $filteredParams[$key] = $value;
            }
        }

        // 按键名ASCII码从小到大排序
        ksort($filteredParams);

        // 2. 拼接参数
        $sortedParams = http_build_query($filteredParams);
        $sortedParams = urldecode($sortedParams);

        // 3. 拼接签名密钥
        $signContent = $sortedParams . '&key=' . $signKey;
        return md5($signContent);
    }

    public function getUploadParam($checkid,$notify){
        $config = ConfigService::get("checkkey");
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        $params = [
            'user_id'=>$config['userid'],
            'timestamp'=>time(),
            'product_id'=>$checkid,
            'notify'=>$notify
        ];
        $sign = $this->generateSignature($params, $config['key']);
        $params['sign'] = $sign;
        return $params;

    }

    //获取货源信息
    public function getProductInfo()
    {
        $config = ConfigService::get("checkkey");
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }

        if (empty($config['userid'])) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        if (empty($config['key'])) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        //获取信息
        $params = [
            'user_id' => $config['userid'],
            'timestamp' => time(),
        ];
        $sign = $this->generateSignature($params, $config['key']);
        $params['sign'] = $sign;
        Log::write($params);
        $result = $this->httpPostFormUrlencoded($params, $this->baseUrl . "product_info");
        if (isset($result['error_msg'])) {
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '系统错误',
            ];
        }
        return $result;
    }

    public function payOrder($orderid,$title,$author,$end_date){
        $config = ConfigService::get("checkkey");
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }

        if (empty($config['userid'])) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        if (empty($config['key'])) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        $params = [
            'user_id' => $config['userid'],
            'timestamp' => time(),
            'order_id'=>$orderid,
            'title'=>$title,
            'author'=>$author,
        ];
        if(!empty($end_date)){
            $params['end_date'] = $end_date;
        }
        $sign = $this->generateSignature($params, $config['key']);
        $params['sign'] = $sign;
        $result = $this->httpPostFormUrlencoded($params, $this->baseUrl . "pay_order");
        if (isset($result['error_msg'])) {
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '系统错误',
            ];
        }
        return $result;
    }

    //获取订单状态
    public function getOrderStatus($orderid){
        $config = ConfigService::get("checkkey");
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }

        if (empty($config['userid'])) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        if (empty($config['key'])) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        $params = [
            'user_id' => $config['userid'],
            'timestamp' => time(),
            'order_id'=>$orderid,
        ];
        $sign = $this->generateSignature($params, $config['key']);
        $params['sign'] = $sign;
        $result = $this->httpPostFormUrlencoded($params, $this->baseUrl . "get_order_status");
        if (isset($result['error_msg'])) {
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '系统错误',
            ];
        }
        return $result;

    }

    //获取余额
    public function getBalance(){
        $config = ConfigService::get("checkkey");
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }

        if (empty($config['userid'])) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        if (empty($config['key'])) {
            return [
                'code' => 1,
                'msg' => '配置错误'
            ];
        }
        $params = [
            'user_id' => $config['userid'],
            'timestamp' => time(),
        ];
        $sign = $this->generateSignature($params, $config['key']);
        $params['sign'] = $sign;
        $result = $this->httpPostFormUrlencoded($params, $this->baseUrl . "get_balance");
        if (isset($result['error_msg'])) {
            Log::write($result);
            return [
                'code' => 1,
                'msg' => '系统错误',
            ];
        }
        return $result;
    }

    //post包装请求
    /**
     * 封装POST请求（application/x-www-form-urlencoded格式，JSON响应转数组）
     * @param array $params POST请求参数数组
     * @param string $url 请求目标地址
     * @return array 成功返回解析后的业务数组，失败返回包含error_msg的错误数组
     */
    function httpPostFormUrlencoded(array $params, string $url): array
    {
        // 1. 参数合法性校验
        if (empty($url)) {
            return ['error_msg' => '请求地址不能为空'];
        }
        if (!is_array($params)) {
            return ['error_msg' => '请求参数必须为数组格式'];
        }

        // 2. 处理请求参数：数组转为application/x-www-form-urlencoded格式字符串
        $postData = http_build_query($params, '', '&');

        // 3. 初始化cURL会话
        $ch = curl_init();
        if (!$ch) {
            return ['error_msg' => 'cURL扩展初始化失败，请确认已开启cURL扩展'];
        }

        // 4. 设置cURL请求选项
        curl_setopt_array($ch, [
            CURLOPT_URL => $url, // 请求地址
            CURLOPT_POST => true, // 启用POST请求
            CURLOPT_POSTFIELDS => $postData, // POST请求数据
            CURLOPT_HTTPHEADER => [ // 设置请求头，指定数据格式和编码
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                'Content-Length: ' . strlen($postData)
            ],
            CURLOPT_RETURNTRANSFER => true, // 响应结果不直接输出，返回给变量
            CURLOPT_FOLLOWLOCATION => true, // 自动跟随3xx重定向
            CURLOPT_TIMEOUT => 30, // 请求超时时间（秒）
            CURLOPT_CONNECTTIMEOUT => 10, // 连接超时时间（秒）
            CURLOPT_SSL_VERIFYPEER => false, // 关闭SSL证书校验（开发环境可用，生产环境建议开启并配置证书）
            CURLOPT_SSL_VERIFYHOST => false, // 关闭SSL主机名校验（开发环境可用，生产环境建议开启为2）
            CURLOPT_ENCODING => 'gzip, deflate', // 支持gzip解压响应数据
        ]);

        // 5. 执行cURL请求，获取响应结果
        $response = curl_exec($ch);

        // 6. 捕获cURL请求错误
        if (curl_errno($ch)) {
            $errorMsg = 'cURL请求失败：' . curl_error($ch);
            curl_close($ch);
            return ['error_msg' => $errorMsg];
        }

        // 7. 关闭cURL会话，释放资源
        curl_close($ch);

        // 8. 处理响应结果：JSON字符串转为PHP数组
        $result = json_decode($response, true);

        // 9. 捕获JSON解析错误
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'error_msg' => 'JSON数据解析失败',
                'raw_response' => $response // 返回原始响应，方便调试
            ];
        }

        // 10. 返回最终解析结果（关联数组）
        return is_array($result) ? $result : ['data' => $result];
    }
}
