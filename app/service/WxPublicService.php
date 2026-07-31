<?php
// 自定义工具类 
namespace app\service;


use think\facade\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Config;
use think\facade\Cache;

class WxPublicService
{

    public $appid = '';
    private $appSecret = '';
    protected Client $httpClient;

    public  function __construct()
    {
        $this->updateConfig();
        $this->httpClient = new Client([
            'timeout' => 10.0
        ]);
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



    public function authorizeUrl(string $redirectUri, $state = "1"): string
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


    public function getAccessToken(): string
    {
        $cacheKey = 'wechat_official_stable_token_' . $this->appid;
        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }

        $apiUrl = "https://api.weixin.qq.com/cgi-bin/stable_token";
        $postJson = [
            "grant_type"    => "client_credential",
            "appid"         => $this->appid,
            "secret"        => $this->appSecret,
            "force_refresh" => false // 不强制刷新，核心稳定特性
        ];

        $res = $this->httpClient->post($apiUrl, [
            'json' => $postJson,
            'timeout' => 10
        ]);
        $data = json_decode($res->getBody()->getContents(), true);

        if (!empty($data['errcode'])) {
            Log::error("获取StableAccessToken失败：{$data['errcode']} {$data['errmsg']}");
            return "";
        }
        // 缓存7000秒，预留200秒缓冲
        $expires = $data['expires_in'] - 200;
        Cache::set($cacheKey, $data['access_token'], $expires);
        return $data['access_token'];
    }



    public function getAccessOpenID(string $code): array
    {

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
            $response = $this->httpClient->get($url, [
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

    /**
     * 发送模板消息
     * @param string $openid 用户openid
     * @param string $templateId 模板ID
     * @param array $data 模板字段数据
     * @param string $url 跳转链接（可选）
     * @param array $miniProgram 小程序跳转（可选）
     * @return array
     */
    public function sendTemplateMsg(string $openid, string $templateId, array $data, string $url = '', array $miniProgram = []): array
    {
        $accessToken = $this->getAccessToken();
        $apiUrl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$accessToken}";

        $postData = [
            'touser'      => $openid,
            'template_id' => $templateId,
            'data'        => $data
        ];
        // 跳转链接
        if (!empty($url)) {
            $postData['url'] = $url;
        }
        // 关联小程序
        if (!empty($miniProgram)) {
            $postData['miniprogram'] = $miniProgram;
        }

        $response = $this->httpClient->post($apiUrl, [
            'json' => $postData
        ]);
        $result = json_decode($response->getBody()->getContents(), true);
        return $result;
    }
}
