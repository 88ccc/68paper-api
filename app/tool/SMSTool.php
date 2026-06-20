<?php

namespace app\tool;

use app\service\ConfigService;
use Overtrue\EasySms\EasySms;
use think\facade\Log;

class SMSTool
{
    private $easySms;
    private $isInit = false;
    private $codeTemplate = "";
    private $config = [
        // HTTP 请求的超时时间（秒）
        'timeout' => 5.0,
    
        // 默认发送配置
        'default' => [
            // 网关调用策略，默认：顺序调用
            'strategy' => \Overtrue\EasySms\Strategies\OrderStrategy::class,
    
            // 默认可用的发送网关
            'gateways' => [
                'aliyun',
            ],
        ],
        // 可用的网关配置
        'gateways' => [
            'errorlog' => [
                'file' => '/tmp/easy-sms.log',
            ],
            'aliyun' => [
                'access_key_id' => '',
                'access_key_secret' => '',
                'sign_name' => '',
            ],
            'qcloud' => [
                'sdk_app_id' => '',
                'secret_id' => '',
                'secret_key' => '',
                'sign_name' => '',
            ],
        ],
    ];

    public function __construct($logfile = "")
    {
        if (!empty($logfile)) {
            $this->config['gateways']['errorlog']['file'] = $logfile;
        }
        $smsconfig = ConfigService::get("sms");

        if (!empty($smsconfig)) {
            if ($smsconfig['smsType'] == "ali") {
                //阿里云短信
                $this->config['default']['gateways'] = ['aliyun'];
                $this->config['gateways']['aliyun']['access_key_id'] = $smsconfig['ali']['accessKeyId'];
                $this->config['gateways']['aliyun']['access_key_secret'] = $smsconfig['ali']['accessKeySecret'];
                $this->config['gateways']['aliyun']['sign_name'] = $smsconfig['ali']['signature'];
                $this->codeTemplate = $smsconfig['ali']['template'];
                $this->isInit = true;
            } else if ($smsconfig['smsType'] == "tencent") {
                //腾讯云
                $this->config['default']['gateways'] = ['qcloud'];
                $this->config['gateways']['qcloud']['sdk_app_id'] = $smsconfig['tencent']['sdkAppID'];
                $this->config['gateways']['qcloud']['secret_id'] = $smsconfig['tencent']['accessKeyId'];
                $this->config['gateways']['qcloud']['secret_key'] = $smsconfig['tencent']['accessKeySecret'];
                $this->config['gateways']['qcloud']['sign_name'] = $smsconfig['tencent']['signature'];
                $this->codeTemplate = $smsconfig['tencent']['template'];
                $this->isInit = true;
            }
        }
        if ($this->isInit) {
            $this->easySms = new EasySms($this->config);
        }
    }

    public function sendCode($mobile, $code)
    {
        return $this->send($mobile, $this->codeTemplate, ['code' => $code]);
    }
    public function send($mobile, $template, $param)
    {
        if (!$this->isInit) {
            return [
                'success' => false,
                'message' => '没有配置短信服务',
                'result' => null
            ];
        }
        try {
            $result = $this->easySms->send($mobile, [
                'template' => $template,
                'data' => $param,
            ]);
            if ($result['aliyun']['status'] === 'success') {
                return [
                    'success' => true,
                    'message' => '短信发送成功',
                    'result' => $result
                ];
            } else {
                Log::error('短信发送失败: ' . ($result['aliyun']['error']['message'] ?? '未知错误'), 'error');
                return [
                    'success' => false,
                    'message' => '短信发送失败',
                    'result' => $result
                ];
            }
        } catch (\Exception $e) {
            Log::error('短信发送失败: ' . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => '短信发送失败',
            ];
        }
    }
}
