<?php
// 自定义工具类 
namespace app\service;


use think\facade\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Config;

class WxPublicService
{

    public $appid = '';
    private $appSecret = '';

    public  function __construct()
    {
        $this->updateConfig();
    }

    // 获取单例实例的方法


    public  function updateConfig()
    {
        $config = ConfigService::get("wxpublic");
        if (!empty($config)) {
            if (!empty($config['appid'])) {
                $this->appid = $config['appid'];
            }
            if (!empty($config['appSecret'])) {
                $this->appSecret = $config['appSecret'];
            }
        }
    }

    public function isInit()
    {
        if (empty($this->appid)) {
            Log::error("微信 appid is empty");
            return false;
        }
        if (empty($this->appSecret)) {
            Log::error("微信 appSecret is empty");
            return false;
        }
        return true;
    }

    public function authorizeUrl($redirectUri, $state = "1")
    {
        $wechat_url = Config::get('website.wx_auth_domain');
        $redirect = $redirectUri;
        if (!empty($wechat_url)) {
            $url = preg_replace('#^https?://#i', '', $redirect);
            $redirect = $wechat_url . "/" . $url;
        }
        $url = urlencode($redirect);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$url}&response_type=code&scope=snsapi_base&state={$state}#wechat_redirect";
    }



    public function getAccessOpenID(string $code): array
    {
        // 初始化Guzzle客户端
        $client = new Client();

        // 拼接请求参数（全部已知固定）
        $params = [
            'appid'      => $this->appid,
            'secret'     => $this->appSecret,
            'code'       => $code,
            'grant_type' => 'authorization_code'
        ];
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token';

        try {
            // 发送GET请求
            $response = $client->get($url, [
                'query' => $params
            ]);

            // 获取响应体并转为数组
            $result = json_decode($response->getBody()->getContents(), true);
            Log::write($result);
            // 判断是否请求成功（微信错误返回包含errcode）
            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                return [
                    'code'    => $result['errcode'],
                    'msg'     => $result['errmsg']
                ];
            }

            // 成功返回数据
            return [
                'code' => 0,
                'data'    => $result
            ];
        } catch (GuzzleException $e) {
            // 网络/请求异常处理
            return [
                'code'    => -1,
                'msg'     => '请求失败：' . $e->getMessage()
            ];
        }
    }
}
