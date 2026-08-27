<?php

namespace app\controller;

use app\BaseController;
use app\model\AdminModel;
use app\model\ArticleModel;
use app\model\AttachModel;
use app\model\CardModel;
use app\service\ConfigService;
use app\service\WxPublicService;
use think\facade\Log;
use app\model\PayModeModel;
use app\model\PaySetModel;
use app\supplier\Check as CheckSupplier;
use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\UserModel;
use app\model\WithdrawModel;
use app\model\ProductTipsModel;
use app\model\UserNoticeModel;
use app\model\BalanceModel;
use app\model\EmailModel;
use app\model\PayRecordModel;
use app\model\PointsModel;
use app\model\SmsModel;
use app\model\UserCheckModel;
use app\model\UserWebModel;
use app\service\PayService;
use think\facade\Config;
use think\facade\Queue;
use app\tool\QueueJob;

class  Manage extends BaseController
{
    public function adminInfo()
    {
        $userid = $this->request->userid;
        $user = AdminModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
            ]
        ]);
    }


    public function getStorageConfig()
    {
        $config = ConfigService::get("storage");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置存储信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }

    public function setStorageConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $storageType = $this->request->param('storageType');
        if ($storageType == 'local') {
            ConfigService::set('storage', [
                'storageType' => $storageType,
                'local' => [
                    'path' => '/uploads/'
                ]
            ], '存储配置');
        } else if ($storageType == 'ali') {
            $aliConfig = $this->request->param('aliConfig');
            ConfigService::set('storage', [
                'storageType' => $storageType,
                'ali' => $aliConfig
            ], '存储配置');
        } else if ($storageType == 'tencent') {
            $tencentConfig = $this->request->param('tencentConfig');
            ConfigService::set('storage', [
                'storageType' => $storageType,
                'tencent' => $tencentConfig
            ], '存储配置');
        } else {
            return json([
                'code' => 1,
                'msg' => '存储类型错误'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function setCustomConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $file = request()->file('file');
        if (empty($file)) {
            return json([
                'code' => 1,
                'msg' => '请上传文件'
            ]);
        }
        $file_ex = $file->getOriginalExtension();
        if (!in_array($file_ex, ['jpg', 'png', 'jpeg', 'gif'])) {
            return json([
                'code' => 1,
                'msg' => '网站图标格式错误'
            ], 400);
        }
        $path = public_path() . '/static/images/';
        $imagepath = '/static/images/custom.' . $file_ex;
        try {
            $file->move($path, 'custom.' . $file_ex);
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '上传失败'
            ]);
        }
        ConfigService::set('custom', [
            'url' => $imagepath
        ], '客服配置');
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }


    public function clearWxPublicConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        ConfigService::clear('wxpublic');
        return json([
            'code' => 0,
            'msg' => '清空成功'
        ]);
    }

    public function getWxPublicConfig()
    {
        $config = ConfigService::get("wxpublic");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置微信信息'
            ]);
        } else {
            if (Config::get('website.is_test')) {
                //演示网站
                if (!empty($config['appid'])) {
                    $config['appid'] = 'wx5185******89';
                }
                if (!empty($config['appSecret'])) {
                    $config['appSecret'] = 'fb17b*********661e28e';
                }
            }
            return json([
                'code' => 0,
                'data' => $config
            ]);
        }
    }

    public function setWxPublicConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写微信配置'
            ]);
        }
        ConfigService::set('wxpublic', $config, '微信公众号配置');
        (new WxPublicService())->updateConfig();
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function setSaleWebConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写销售网站信息'
            ]);
        }
        ConfigService::set('sale_web', $config, '销售网站配置');
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function setInviteConfig()
    {

        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写邀请配置'
            ]);
        }
        $yconfig = ConfigService::get("invite");
        if (!empty($yconfig)) {
            if ($yconfig['enable'] == $config['enable']) {
                return json([
                    'code' => 0,
                    'msg' => '设置成功'
                ]);
            }
        }

        ConfigService::set('invite', $config, '邀请配置');
        CheckModel::where('1=1')->update(['reward' => 0]);
        $msg = "设置成功";
        $str = trim(strtolower($config['enable']));
        if ($str === 'true') {
            $msg = "开启成功，请确保已在产品配置中设置了邀请奖励金额";
        } else {
            $msg = "关闭成功";
        }
        return json([
            'code' => 0,
            'msg' => $msg
        ]);
    }

    public function setFunctionConfig()
    {

        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写邀请配置'
            ]);
        }
        ConfigService::set('function', $config, '功能设置');
        $msg = "设置成功";
        return json([
            'code' => 0,
            'msg' => $msg
        ]);
    }

    public function setAgisoConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写配置'
            ]);
        }
        ConfigService::set('91kaj', $config, '阿索奇标准货源');
        $msg = "设置成功";
        return json([
            'code' => 0,
            'msg' => $msg
        ]);
    }

    public function getAgisoConfig()
    {
        $config = ConfigService::get("91kaj");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置存储信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }

    public function clearAgisoConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        ConfigService::clear('91kaj');
        return json([
            'code' => 0,
            'msg' => '清空成功'
        ]);
    }

    public function setPayMode()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $id = $this->request->post('id');
        $type = $this->request->post('type');
        $name = $this->request->post('name');
        $count = PayModeModel::count();
        if (empty($type)) {
            return json([
                'code' => 1,
                'msg' => '请选择支付方式'
            ]);
        } else {
            $type = trim($type);
        }
        if (empty($name)) {
            return json([
                'code' => 1,
                'msg' => '请填写模版名称'
            ]);
        } else {
            $name = trim($name);
        }
        $isModify = false;
        $paymode = PayModeModel::where('id', $id)->find();
        if (empty($paymode)) {
            //确认name没有被使用
            $paymode = PayModeModel::where('name', $name)->find();
            if (!empty($paymode)) {
                return json([
                    'code' => 1,
                    'msg' => '模版名称已存在'
                ]);
            }
            if ($count >= 50) {
                return json([
                    'code' => 1,
                    'msg' => '最多只能添加50个支付模板'
                ]);
            }
            $paymode = new PayModeModel();
        } else {
            $isModify = true;
        }
        $paymode->type = $type;
        $paymode->name = $name;
        $certBasePath = root_path() . '/cert/';
        $uniqidcode = uniqid();
        if ($type == 'wxpay') {
            $appid = $this->request->post('appId');
            $mchid = $this->request->post('mchId');
            $mchSecretKey = $this->request->post('mchSecretKey');
            $pubkeyId = $this->request->post('pubKeyId');
            if (empty($appid)) {
                return json([
                    'code' => 1,
                    'msg' => '请填写微信appid'
                ]);
            } else {
                $appid = trim($appid);
                $paymode->appid = $appid;
            }
            if (empty($mchid)) {
                return json([
                    'code' => 1,
                    'msg' => '请填写微信商户id'
                ]);
            } else {
                $mchid = trim($mchid);
                $paymode->mchid = $mchid;
            }
            if (empty($mchSecretKey)) {
                return json([
                    'code' => 1,
                    'msg' => '请填写微信商户密钥'
                ]);
            } else {
                $mchSecretKey = trim($mchSecretKey);
                $paymode->mchkey = $mchSecretKey;
            }

            if (empty($pubkeyId)) {
                return json([
                    'code' => 1,
                    'msg' => '请填写微信支付公钥id'
                ]);
            } else {
                $pubkeyId = trim($pubkeyId);
            }

            $files = request()->file();

            if (isset($files['mchSecretCert']) && (!empty($files['mchSecretCert']))) {
                //检查文件大小
                $file = $files['mchSecretCert'];
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return json([
                        'code' => 1,
                        'msg' => '微信商户私钥证书大小不对'
                    ]);
                }
                $file_ex = $file->getOriginalExtension();
                if ($file_ex != 'pem') {
                    return json([
                        'code' => 1,
                        'msg' => '微信商户私钥证书后缀不对'
                    ]);
                }
                $fileName = 'apiclient_key-' . $uniqidcode . '.' . $file_ex;
                if (!empty($paymode->mchsecretpath)) {
                    $fileName =  $paymode->mchsecretpath;
                }
                try {
                    $file->move($certBasePath, $fileName);
                } catch (\Exception $e) {
                    return json([
                        'code' => 1,
                        'msg' => '微信商户私钥证书上传失败'
                    ]);
                }
                $paymode->mchsecretpath = $fileName;
            } else {
                if (!$isModify) {
                    return json([
                        'code' => 1,
                        'msg' => '请上传商户私钥证书'
                    ]);
                }
            }

            if (isset($files['mchPublicCert']) && (!empty($files['mchPublicCert']))) {
                //检查文件大小
                $file = $files['mchPublicCert'];
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return json([
                        'code' => 1,
                        'msg' => '微信商户公钥证书大小不对'
                    ]);
                }
                $file_ex = $file->getOriginalExtension();
                if ($file_ex != 'pem') {
                    return json([
                        'code' => 1,
                        'msg' => '微信商户公钥证书后缀不对'
                    ]);
                }
                $fileName = 'apiclient_cert-' . $uniqidcode . '.' . $file_ex;
                if (!empty($paymode->mchpublicpath)) {
                    $fileName =  $paymode->mchpublicpath;
                }
                try {
                    $file->move($certBasePath, $fileName);
                } catch (\Exception $e) {
                    return json([
                        'code' => 1,
                        'msg' => '微信商户公钥证书上传失败'
                    ]);
                }
                $paymode->mchpublicpath = $fileName;
            } else {
                if (!$isModify) {
                    return json([
                        'code' => 1,
                        'msg' => '请上传商户公钥证书'
                    ]);
                }
            }

            if (isset($files['pubkeyFile']) && (!empty($files['pubkeyFile']))) {
                //检查文件大小
                $file = $files['pubkeyFile'];
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return json([
                        'code' => 1,
                        'msg' => '微信支付公钥证书大小不对'
                    ]);
                }
                $file_ex = $file->getOriginalExtension();
                if ($file_ex != 'pem') {
                    return json([
                        'code' => 1,
                        'msg' => '微信支付公钥证书后缀不对'
                    ]);
                }
                $fileName = $pubkeyId . '--' . $uniqidcode . '.' . $file_ex;
                if (!empty($paymode->alipublicpath)) {
                    $fileName =  $paymode->alipublicpath;
                }
                try {
                    $file->move($certBasePath, $fileName);
                } catch (\Exception $e) {
                    return json([
                        'code' => 1,
                        'msg' => '微信支付公钥证书上传失败'
                    ]);
                }
                $paymode->alipublicpath = $fileName;
            } else {
                if (!$isModify) {
                    return json([
                        'code' => 1,
                        'msg' => '请上传微信支付公钥证书'
                    ]);
                }
            }


            $paymode->update_time = date('Y-m-d H:i:s');
            if (!$isModify) {
                $paymode->create_time = date('Y-m-d H:i:s');
            }
            try {
                $ret = $paymode->save();
                if ($ret == false) {
                    return json([
                        'code' => 1,
                        'msg' => '保存失败'
                    ]);
                } else {
                    return json([
                        'code' => 0,
                        'msg' => '保存成功'
                    ]);
                }
            } catch (\Exception $e) {
                Log::write('paymode save error', $e);
                return json([
                    'code' => 2,
                    'msg' => '保存失败'
                ]);
            }
        } else if ($type == 'alipay') {
            $appid = $this->request->post('appId');
            $encryptType = $this->request->post('encryptType');
            $appSecretCert = $this->request->post('appSecretCert');
            if (empty($appid)) {
                return json([
                    'code' => 1,
                    'msg' => '请填写支付宝appid'
                ]);
            } else {
                $appid = trim($appid);
                $paymode->appid = $appid;
            }
            if (empty($encryptType)) {
                return json([
                    'code' => 1,
                    'msg' => '请选择支付宝加密方式'
                ]);
            } else {
                $encryptType = trim($encryptType);
                $paymode->entype = $encryptType;
            }
            if (empty($appSecretCert)) {
                return json([
                    'code' => 1,
                    'msg' => '请填写支付宝应用私钥'
                ]);
            } else {
                $appSecretCert = trim($appSecretCert);
                $paymode->appsecret = $appSecretCert;
            }
            $files = request()->file();
            if (isset($files['appPublicKey']) && (!empty($files['appPublicKey']))) {
                //检查文件大小
                $file = $files['appPublicKey'];
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝应用公钥证书大小不对'
                    ]);
                }
                $file_ex = $file->getOriginalExtension();
                if ($file_ex != 'crt') {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝应用公钥证书后缀不对'
                    ]);
                }
                $fileName = 'appCertPublicKey-' . $uniqidcode . '.' . $file_ex;
                if (!empty($paymode->apppublicpath)) {
                    $fileName =  $paymode->apppublicpath;
                }
                try {
                    $file->move($certBasePath, $fileName);
                } catch (\Exception $e) {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝应用公钥证书上传失败'
                    ]);
                }
                $paymode->apppublicpath = $fileName;
            } else {
                if (!$isModify) {
                    return json([
                        'code' => 1,
                        'msg' => '请上传支付宝应用公钥证书'
                    ]);
                }
            }
            if (isset($files['aliPublicKey']) && (!empty($files['aliPublicKey']))) {
                //检查文件大小
                $file = $files['aliPublicKey'];
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝公钥证书大小不对'
                    ]);
                }
                $file_ex = $file->getOriginalExtension();
                if ($file_ex != 'crt') {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝公钥证书后缀不对'
                    ]);
                }
                $fileName = 'alipayCertPublicKey-' . $uniqidcode . '.' . $file_ex;
                if (!empty($paymode->alipublicpath)) {
                    $fileName =  $paymode->alipublicpath;
                }
                try {
                    $file->move($certBasePath, $fileName);
                } catch (\Exception $e) {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝公钥证书上传失败'
                    ]);
                }
                $paymode->alipublicpath = $fileName;
            } else {
                if (!$isModify) {
                    return json([
                        'code' => 1,
                        'msg' => '请上传支付宝公钥证书'
                    ]);
                }
            }
            if (isset($files['aliRootKey']) && (!empty($files['aliRootKey']))) {
                //检查文件大小
                $file = $files['aliRootKey'];
                if ($file->getSize() > 5 * 1024 * 1024) {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝根证书大小不对'
                    ]);
                }
                $file_ex = $file->getOriginalExtension();
                if ($file_ex != 'crt') {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝根证书后缀不对'
                    ]);
                }
                $fileName = 'alipayRootCert-' . $uniqidcode . '.' . $file_ex;
                if (!empty($paymode->alirootpath)) {
                    $fileName =  $paymode->alirootpath;
                }
                try {
                    $file->move($certBasePath, $fileName);
                } catch (\Exception $e) {
                    return json([
                        'code' => 1,
                        'msg' => '支付宝根证书上传失败'
                    ]);
                }
                $paymode->alirootpath = $fileName;
            } else {
                if (!$isModify) {
                    return json([
                        'code' => 1,
                        'msg' => '请上传支付宝根证书'
                    ]);
                }
            }

            $paymode->update_time = date('Y-m-d H:i:s');
            if (!$isModify) {
                $paymode->create_time = date('Y-m-d H:i:s');
            }
            try {
                $ret = $paymode->save();
                if ($ret == false) {
                    return json([
                        'code' => 3,
                        'msg' => '保存失败'
                    ]);
                } else {
                    return json([
                        'code' => 0,
                        'msg' => '保存成功'
                    ]);
                }
            } catch (\Exception $e) {
                return json([
                    'code' => 4,
                    'msg' => '保存失败'
                ]);
            }
        } else {
            return json([
                'code' => 1,
                'msg' => '不支持的支付方式'
            ]);
        }
    }

    public function getAllPayMode()
    {
        $list["code"] = 0;
        $list["count"] = 0;
        $list["msg"] = "";
        $count = PayModeModel::count();
        $users = PayModeModel::field('id,name,type,appid,mchid,create_time,update_time')->order('create_time', 'desc')->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }

    public function getPayMode()
    {
        $list["code"] = 0;
        $list["count"] = 0;
        $list["msg"] = "";

        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = PayModeModel::count();
        $users = PayModeModel::field('id,name,type,appid,mchid,create_time,update_time')->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }
    public function deletePayMode()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }

        $id = input("post.id");
        if (empty($id)) {
            return json([
                'code' => 1,
                'msg' => '请选择要删除的支付方式'
            ]);
        }
        $paymode = PayModeModel::where('id', $id)->find();
        $delFile = array();
        if (!empty($paymode->mchsecretpath)) {
            array_push($delFile, $paymode->mchsecretpath);
        }
        if (!empty($paymode->mchpublicpath)) {
            array_push($delFile, $paymode->mchpublicpath);
        }
        if (!empty($paymode->apppublicpath)) {
            array_push($delFile, $paymode->apppublicpath);
        }
        if (!empty($paymode->alipublicpath)) {
            array_push($delFile, $paymode->alipublicpath);
        }
        if (!empty($paymode->alirootpath)) {
            array_push($delFile, $paymode->alirootpath);
        }
        if (empty($paymode)) {
            return json([
                'code' => 1,
                'msg' => '支付方式不存在'
            ]);
        }
        try {
            $ret = $paymode->delete();
            if ($ret == false) {
                return json([
                    'code' => 1,
                    'msg' => '删除失败'
                ]);
            }
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '删除失败'
            ]);
        }

        foreach ($delFile as $file) {
            $filePath = app()->getRootPath() . 'cert/' . $file;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        PaySetModel::deleteModelId($id);
        return json([
            'code' => 0,
            'msg' => '删除成功'
        ]);
    }

    public function getPaySet()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $users = PaySetModel::select();
        $list["data"] = $users;
        return json($list);
    }
    public function setPaySet()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $data = input("post.data");
        try {
            $ret = PaySetModel::saveAll($data);
            if ($ret == false) {
                return json([
                    'code' => 1,
                    'msg' => '保存失败'
                ]);
            }
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '保存失败'
            ]);
        }

        return json([
            'code' => 0,
            'msg' => '保存成功'
        ]);
    }



    //获取管理员
    public function getAdminData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = AdminModel::count();
        $users = AdminModel::limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }

    public function editAdmin()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $id = input("post.id");
        if (empty($id)) {
            return json([
                'code' => 1,
                'msg' => '请选择要编辑的管理员'
            ]);
        }
        $admin = AdminModel::where('id', $id)->find();
        if (empty($admin)) {
            return json([
                'code' => 1,
                'msg' => '管理员不存在'
            ]);
        }
        $admin->name = input("post.name");
        $password = input("post.password");
        if (!empty($password)) {
            $admin->pass = md5($password);
        }
        // 检查头像
        $avatar = request()->file('avatar');
        if (!empty($avatar)) {
            if ($avatar->getSize() > 500 * 1024) {
                return json([
                    'code' => 1,
                    'msg' => '头像不能超过1M'
                ]);
            }
            //检查文件是否是图片
            $file_ex = $avatar->getOriginalExtension();
            if (!in_array($file_ex, ['jpg', 'png', 'jpeg', 'gif'])) {
                return json([
                    'code' => 1,
                    'msg' => '头像格式错误'
                ]);
            }
            $path = public_path() . '/static/images/avatar/';
            $fileName = 'admin-' . $id . "." . $file_ex;
            $userAvatar = '/static/images/avatar/' . $fileName;
            try {
                $avatar->move($path, $fileName);
            } catch (\Exception $e) {
                return json([
                    'code' => 1,
                    'msg' => '头像上传失败'
                ]);
            }
            if (!empty($admin->avatar)) {
                if (($admin->avatar != '/images/avatar/default.png') && ($admin->avatar != $userAvatar) && (file_exists(public_path() . $admin->avatar))) {
                    unlink(public_path() . $admin->avatar);
                }
            }
            $admin->avatar = $userAvatar;
        }
        try {
            $ret = $admin->save();
            if ($ret == false) {
                return json([
                    'code' => 1,
                    'msg' => '保存失败'
                ]);
            }
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '保存失败'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '保存成功'
        ]);
    }

    public function setCheckKeyConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写配置'
            ]);
        }
        ConfigService::set('checkkey', $config, '检测密钥配置');
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }
    public function getCheckKeyConfig()
    {
        $config = ConfigService::get("checkkey");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置检测秘钥'
            ]);
        } else {
            if (Config::get('website.is_test')) {
                if (!empty($config['key'])) {
                    $config['key'] = 'fb45d*******986c2';
                }
            }

            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }
    public function clearCheckKeyConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        ConfigService::clear('checkkey');
        return json([
            'code' => 0,
            'msg' => '清除成功'
        ]);
    }

    //同步检测货源
    public function syncCheckSystem()
    {
        $supplier = new CheckSupplier();
        $res = $supplier->getProductInfo();
        if ($res['code'] != 0) {
            //异常
            Log::write($res);
            return json($res);
        }
        $data = $res['data'];
        if (!is_array($data)) {
            //异常
            Log::write($res);
            return json($res);
        }
        $ids = [];
        $length = count($data);
        for ($i = 0; $i < $length; $i++) {
            $check = $data[$i];
            $now = date('Y-m-d H:i:s', time());
            $ids[] = $check['id'];
            $mycheck = CheckModel::where('id', $check['id'])->find();
            if (empty($mycheck)) {
                $mycheck = new CheckModel;
                $mycheck->status = $check['status'];
                $mycheck->create_time = $now;
            }
            $mycheck->id = $check['id'];
            $mycheck->name = $check['name'];
            $mycheck->cost = $check['price'];
            if (empty($mycheck->price)) {
                $mycheck->price = $check['price'];
            } else {
                if ($mycheck->price < $check['price']) {
                    $mycheck->price = $check['price'];
                }
            }
            $mycheck->unit = $check['unit'];
            $mycheck->low_price = $check['mini_price'];
            if (empty($mycheck->mini_price)) {
                $mycheck->mini_price = $check['mini_price'];
            } else {
                if ($mycheck->mini_price < $check['mini_price']) {
                    $mycheck->mini_price = $check['mini_price'];
                }
            }
            if (empty($mycheck->remark)) {
                $mycheck->remark = $check['remark'];
            }
            $mycheck->supplier_status = $check['status'];
            $mycheck->config = $check['config'];
            $mycheck->update_time = $now;
            $mycheck->save();
        }

        $allcheck = CheckModel::field(['id'])->select();
        foreach ($allcheck as $check) {
            if (!in_array($check['id'], $ids)) {
                CheckModel::where('id', $check['id'])->update(['supplier_status' => 3]);
            }
        }
        return json([
            'code' => 0,
            'msg' => ''
        ]);
    }

    public function getCheckData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = CheckModel::count();
        $products = CheckModel::limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $products;
        return json($list);
    }


    public function deleteCheckProduct()
    {
        $id = $this->request->post("id");
        CheckModel::where('id', $id)->delete();
        return json([
            'code' => 0,
            'msg' => '',
        ]);
    }



    public function getCheckOrderData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $type = input("get.type");
        if (empty($type)) {
            $type = 0;
        }
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $where = [];
        $orderid = input("get.orderid") ? input("get.orderid") : "";
        $orderid = trim($orderid);
        if (!empty($orderid)) {
            $where[] = ['id', 'LIKE', "%" . $orderid . "%"];
        }
        $payid = input("get.payid") ? input("get.payid") : "";
        $payid = trim($payid);
        if (!empty($payid)) {
            $where[] = ['payid', 'LIKE', "%" . $payid . "%"];
        }
        $userid = input("get.userid") ? input("get.userid") : "";
        $userid = trim($userid);
        if (!empty($userid)) {
            $where[] = ['userid', '=', $userid];
        }
        $count = CheckOrderModel::where($where)->count();
        $products = CheckOrderModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $products;
        return json($list);
    }

    public function orderRefund()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $orderid = $this->request->post("orderid");
        $order = CheckOrderModel::where('id', $orderid)->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => "该订单不存在"
            ]);
        }
        if ($order->status < 4) {
            return json([
                'code' => 1,
                'msg' => "该订单未支付"
            ]);
        }
        if ($order->status == 9) {
            return json([
                'code' => 1,
                'msg' => "该订单已经退款"
            ]);
        }
        $payRecord = PayRecordModel::where("id", $order->payid)->find();
        if (!empty($payRecord)) {
            $ret  = (new PayService())->refund($order->payid);
            if ($ret['code'] == 0) {
                return json([
                    'code' => 0,
                    'msg' => "退款成功"
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => $ret['msg']
                ]);
            }
        }
        //可能是检测卡支付，只需要退还成本即可
        $card = CardModel::where("id", $order->payid)->find();
        if (empty($card)) {
            return json([
                'code' => 1,
                'msg' => "没有找到支付记录"
            ]);
        }

        $order = CheckOrderModel::where(["id" => $orderid])->find();
        $user = UserModel::where('id', $order->userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => "检测卡支付，销售员不存在"
            ]);
        }
        $user->increasePoints($order->cost, 3, $order->id, '订单退款');
        CheckOrderModel::where(["id" => $orderid])->update(["status" => 9, "update_time" => date('Y-m-d H:i:s')]);
        if (!empty($order->tid)) {
            $tuser = UserModel::where("id", $order->tid)->find();
            if (!empty($tuser)) {
                $tuser->decreaseBalance($order->tprofit, 3, $order->id, '邀请奖励退款(销售:' . $order->userid . ')');
                UserModel::where("id", $order->tid)->dec('money', $order->tprofit)->update();
                UserModel::where("id", $order->userid)->dec('tmoney', $order->tprofit)->update();
            }
        }
        return json([
            'code' => 0,
            'msg' => "退款成功"
        ]);
    }

    public function homeData()
    {
        //今日订单数
        $sub_count = CheckOrderModel::whereDay('create_time')->count();
        $data = CheckOrderModel::whereDay('create_time')->where([['status', 'IN', [4, 5, 6, 7, 8, 10]]])->field('count(id) as mun_count, SUM(total_price) as sales,SUM(pprofit) as myprofit')->select()
            ->toArray();
        $today = date('Y-m-d');
        $sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));
        $rechargePays = CheckOrderModel::field("DATE_FORMAT(create_time, '%Y-%m-%d') as date, SUM(total_price) as sales")
            ->whereBetween('create_time', [$sevenDaysAgo . ' 00:00:00', $today . ' 23:59:59'])
            ->where([['status', 'IN', [4, 5, 6, 7, 8, 10]]])
            ->group('date')
            ->select()
            ->toArray();
        $payDateRange = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $payDateRange[$date] = 0;
        }
        // 填充充值
        foreach ($rechargePays as $item) {
            if (isset($payDateRange[$item['date']])) {
                $payDateRange[$item['date']] = (float)bcdiv($item['sales'], 100, 2);
            }
        }
        $pay_count = 0;
        $pay_amount = 0;
        $myprofit = 0;
        if (!empty($data[0]['mun_count'])) {
            $pay_count = $data[0]['mun_count'];
        }
        if (!empty($data[0]['sales'])) {
            $pay_amount = (float)bcdiv($data[0]['sales'], 100, 2);
        }
        if (!empty($data[0]['myprofit'])) {
            $myprofit = (float)bcdiv($data[0]['myprofit'], 100, 2);
        }
        //获取余额
        $ret = (new CheckSupplier())->getBalance();
        $balance = 0;
        if ($ret['code'] == 0) {
            $balance = (float)bcdiv($ret['data']['balance'], 100, 2);
        }

        return json([
            'code' => 0,
            "msg" => "",
            "data" => [
                "sub_count" => $sub_count,
                "pay_count" => $pay_count,
                "pay_amount" => $pay_amount,
                'profit' => $myprofit,
                "balance" => $balance,
                "dates" => array_keys($payDateRange),
                'pay' => array_values($payDateRange)
            ]
        ]);
    }

    public function clearEmailConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        ConfigService::clear('email');
        return json([
            'code' => 0,
            'msg' => '清空成功'
        ]);
    }

    public function setWithdrawConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写提现配置'
            ]);
        }
        ConfigService::set('withdraw', $config, '提现配置');
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function getEmailConfig()
    {
        $config = ConfigService::get("email");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置邮件信息'
            ]);
        } else {
            if (Config::get('website.is_test')) {
                if (!empty($config['password'])) {
                    $config['password'] = "********";
                }
            }
            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }

    public function setEmailConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写邮件配置'
            ]);
        }
        ConfigService::set('email', $config, '邮件配置');
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function getCacheConfig()
    {
        $config = ConfigService::get("cache_set");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置缓存信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }

    public function setCacheConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $config = $this->request->param('config');
        if (empty($config)) {
            return json([
                'code' => 1,
                'msg' => '请填写邮件配置'
            ]);
        }
        ConfigService::set('cache_set', $config, '邮件配置');
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function clearSmsConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        ConfigService::clear('sms');
        return json([
            'code' => 0,
            'msg' => '清空成功'
        ]);
    }

    public function getSmsConfig()
    {
        $config = ConfigService::get("sms");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置短信信息'
            ]);
        } else {
            if (Config::get('website.is_test')) {
                if (!empty($config['ali']['accessKeyId'])) {
                    $config['ali']['accessKeyId'] = "********";
                }
                if (!empty($config['ali']['accessKeySecret'])) {
                    $config['ali']['accessKeySecret'] = "********";
                }
                if (!empty($config['tencent']['accessKeyId'])) {
                    $config['tencent']['accessKeyId'] = "********";
                }
                if (!empty($config['tencent']['accessKeySecret'])) {
                    $config['tencent']['accessKeySecret'] = "********";
                }
                if (!empty($config['tencent']['signature'])) {
                    $config['tencent']['signature'] = "********";
                }
            }
            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }
    public function setSmsConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $smsType = $this->request->param('smsType');
        if ($smsType == 'ali') {
            $aliConfig = $this->request->param('aliConfig');
            ConfigService::set('sms', [
                'smsType' => $smsType,
                'ali' => $aliConfig
            ], '短信配置');
        } else if ($smsType == 'tencent') {
            $tencentConfig = $this->request->param('tencentConfig');
            ConfigService::set('sms', [
                'smsType' => $smsType,
                'tencent' => $tencentConfig
            ], '短信配置');
        } else {
            return json([
                'code' => 1,
                'msg' => '短信引擎错误'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }
    public function setLoginRegisterConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $regList = $this->request->param('regList');
        ConfigService::set('loginRegister', [
            'regList' => $regList,
        ], '登录注册配置');
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function getUserData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $qid = input("get.id");
        $qstatus = input("get.status");
        $qmobile = input("get.mobile");
        $qemail = input("get.email");
        $where = [];
        if (!empty($qid)) {
            $where['id'] = $qid;
        }
        if (!empty($qstatus)) {
            $where['status'] = $qstatus;
        }
        if (!empty($qmobile)) {
            $where['mobile'] = $qmobile;
        }
        if (!empty($qemail)) {
            $where['email'] = $qemail;
        }
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = UserModel::where($where)->count();
        $users = UserModel::where($where)->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }

    //修改用户状态
    public function editUserStatus()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $data = request()->post();
        if (empty($data)) {
            return json([
                'code' => 1,
                'msg' => '请填写用户信息'
            ]);
        }
        if (!isset($data['id'])) {
            return json([
                'code' => 1,
                'msg' => '请填写用户ID'
            ]);
        }
        if (!isset($data['status'])) {
            return json([
                'code' => 1,
                'msg' => '请填写用户状态'
            ]);
        }
        if ($data['status'] != 0 && $data['status'] != 1 && $data['status'] != 2) {
            return json([
                'code' => 1,
                'msg' => '不支持的用户状态'
            ]);
        }
        $remark = "";
        if (isset($data['remark'])) {
            $remark = $data['remark'];
        }

        UserModel::where('id', $data['id'])->update(['status' => $data['status'],  'tips' => $remark]);
        return json([
            'code' => 0,
            'msg' => '成功'
        ]);
    }
    //修改用户金额
    public function editUserBalance()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $businessNo = "";
        $data = request()->post();
        if (empty($data)) {
            return json([
                'code' => 1,
                'msg' => '请填写用户信息'
            ]);
        }
        if (!isset($data['id'])) {
            return json([
                'code' => 1,
                'msg' => '请填写用户ID'
            ]);
        }
        if (!isset($data['amount'])) {
            return json([
                'code' => 1,
                'msg' => '请填写amount'
            ]);
        }
        $remark = "管理员操作";
        if (!empty($data['remark'])) {
            $remark = $data['remark'];
        }
        if (isset($data['businessNo'])) {
            $businessNo = trim($data['businessNo']);
        }
        $amount = intval($data['amount']);
        if ($amount == 0) {
            return json([
                'code' => 0,
                'msg' => '成功'
            ]);
        }
        if (!isset($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '类型必须填入'
            ]);
        }
        $type = intval($data['type']);
        if ($type == 0) {
            return json([
                'code' => 1,
                'msg' => '类型必须填入'
            ]);
        }
        $user = UserModel::where('id', $data['id'])->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        if ($amount < 0) {
            $p = abs($amount);
            $ret = $user->decreaseBalance($p, $type, $businessNo,  $remark, 0, 2);
            if ($ret) {
                return json([
                    'code' => 0,
                    'msg' => '成功'
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => '失败'
                ]);
            }
        } else {
            $p = abs($amount);
            $ret = $user->increaseBalance($p, $type, $businessNo, $remark, 0, 2);
            if ($ret) {
                return json([
                    'code' => 0,
                    'msg' => '成功'
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => '失败'
                ]);
            }
        }
    }
    //修改用户积分
    public function editUserPoints()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $businessNo = "";
        $data = request()->post();
        if (empty($data)) {
            return json([
                'code' => 1,
                'msg' => '请填写用户信息'
            ]);
        }
        if (!isset($data['id'])) {
            return json([
                'code' => 1,
                'msg' => '请填写用户ID'
            ]);
        }
        if (!isset($data['amount'])) {
            return json([
                'code' => 1,
                'msg' => '请填写amount'
            ]);
        }
        $remark = "管理员操作";
        if (!empty($data['remark'])) {
            $remark = $data['remark'];
        }
        if (isset($data['businessNo'])) {
            $businessNo = trim($data['businessNo']);
        }
        $amount = intval($data['amount']);
        if ($amount == 0) {
            return json([
                'code' => 0,
                'msg' => '成功'
            ]);
        }
        if (!isset($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '类型必须填入'
            ]);
        }
        $type = intval($data['type']);
        if ($type == 0) {
            return json([
                'code' => 1,
                'msg' => '类型必须填入'
            ]);
        }
        $user = UserModel::where('id', $data['id'])->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        if ($amount < 0) {
            $p = abs($amount);
            $befor_points = $user->points;
            $ret = $user->decreasePoints($p, $type, $businessNo,  $remark, 0, 2);
            if ($ret) {
                return json([
                    'code' => 0,
                    'msg' => '成功'
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => '失败'
                ]);
            }
        } else {
            $p = abs($amount);
            $ret = $user->increasePoints($p, $type, $businessNo, $remark, 0, 2);
            if ($ret) {
                return json([
                    'code' => 0,
                    'msg' => '成功'
                ]);
            } else {
                return json([
                    'code' => 1,
                    'msg' => '失败'
                ]);
            }
        }
    }

    //修改用户姓名
    public function editUserName()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $data = request()->post();
        if (empty($data)) {
            return json([
                'code' => 1,
                'msg' => '请填写用户信息'
            ]);
        }
        if (!isset($data['id'])) {
            return json([
                'code' => 1,
                'msg' => '请填写用户ID'
            ]);
        }
        $name = "";
        if (!isset($data['name'])) {
            return json([
                'code' => 1,
                'msg' => '请填写用户姓名'
            ]);
        } else {
            $name = trim($data['name']);
            if (empty($name)) {
                return json([
                    'code' => 1,
                    'msg' => '请填写用户姓名'
                ]);
            }
        }
        UserModel::where('id', $data['id'])->update(['name' => $name]);
        return json([
            'code' => 0,
            'msg' => '成功'
        ]);
    }

    public function updateCheckProduct()
    {
        $data = request()->post();
        if (empty($data)) {
            return json([
                'code' => 1,
                'msg' => '请填写信息'
            ]);
        }
        if (!isset($data['id'])) {
            return json([
                'code' => 1,
                'msg' => '请填写ID'
            ]);
        }
        $check = CheckModel::where('id', $data['id'])->find();
        if (empty($check)) {
            return json([
                'code' => 1,
                'msg' => '找不到这个产品'
            ]);
        }
        $price = 0;
        if (empty($data['price'])) {
            return json([
                'code' => 1,
                'msg' => '请填写供货价'
            ]);
        } else {
            $price = intval($data['price']);
        }
        $mini_price = 0;
        if (empty($data['mini_price'])) {
            return json([
                'code' => 1,
                'msg' => '请填写最低零售价'
            ]);
        } else {
            $mini_price = intval($data['mini_price']);
        }
        $reward = 0;
        if (!empty($data['reward'])) {
            $reward = intval($data['reward']);
        }
        if ($price <= 0 || $mini_price <= 0) {
            return json([
                'code' => 1,
                'msg' => '价格不能小于等于0'
            ]);
        }
        if ($mini_price < $price) {
            return json([
                'code' => 1,
                'msg' => '最低零售价不能小于供货价'
            ]);
        }
        if ($mini_price < $check->low_price) {
            return json([
                'code' => 1,
                'msg' => '最低零售价不能小于88学子规定的最低零售价'
            ]);
        }
        if ($price < $check->cost) {
            return json([
                'code' => 1,
                'msg' => '供货价不能小于成本价'
            ]);
        }
        if ($price - $reward < $check->cost) {
            return json([
                'code' => 1,
                'msg' => '供货价减去奖励不能小于成本价,这样可能亏本'
            ]);
        }
        if (empty($data['remark'])) {
            $remark = "";
        } else {
            $remark = trim($data['remark']);
        }
        if (empty($data['status'])) {
            return json([
                'code' => 1,
                'msg' => '请填写状态'
            ]);
        }
        $status = intval($data['status']);
        if ($status != 1 && $status != 2) {
            return json([
                'code' => 1,
                'msg' => '状态错误'
            ]);
        }
        CheckModel::where('id', $data['id'])->update(['price' => $price, 'mini_price' => $mini_price, 'reward' => $reward, 'status' => $status, 'remark' => $remark, 'update_time' => date('Y-m-d H:i:s')]);
        return json([
            'code' => 0,
            'msg' => '成功'
        ]);
    }

    public function getAttachData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $userid = input("get.userid") ? input("get.userid") : '';
        $status = input("get.status") ? input("get.status") : '';
        $where = [];
        if (!empty($userid)) {
            $where[] = ['userid', '=', $userid];
        }
        if (!empty($status)) {
            $where[] = ['file_status', '=', $status];
        }
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = AttachModel::where($where)->count();
        $prdate = AttachModel::where($where)->order('update_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $prdate;
        return json($list);
    }
    public function attachAudit()
    {
        $userid = $this->request->post("userid");
        $status = $this->request->post("status");
        if (empty($userid)) {
            return json([
                'code' => 1,
                'msg' => '没有userid'
            ]);
        }
        $attach = AttachModel::where("userid", $userid)->find();
        if (empty($attach)) {
            return json([
                'code' => 1,
                'msg' => "该用户没有附件"
            ]);
        }
        AttachModel::where("userid", $userid)->update(['file_status' => $status, 'update_time' => date('Y-m-d H:i:s')]);
        return json([
            'code' => 0,
            'msg' => ''
        ]);
    }

    public function setNotice()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $content = $this->request->post('content');
        $notice = ArticleModel::where('id', 'notice')->find();
        if (empty($notice)) {
            ArticleModel::insert([
                'id' => 'notice',
                'content' => $content,
                'update_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            ArticleModel::where('id', 'notice')->update(['content' => $content, 'update_time' => date('Y-m-d H:i:s')]);
        }
        return json([
            'code' => 0,
            'msg' => '修改成功'
        ]);
    }
    public function setPrivacyPolicy()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $content = $this->request->post('content');
        $article = ArticleModel::where('id', 'privacyPolicy')->find();
        if (empty($article)) {
            ArticleModel::insert([
                'id' => 'privacyPolicy',
                'content' => $content,
                'update_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            ArticleModel::where('id', 'privacyPolicy')->update(['content' => $content, 'update_time' => date('Y-m-d H:i:s')]);
        }
        return json([
            'code' => 0,
            'msg' => '修改成功'
        ]);
    }

    public function setUserAgreement()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $content = $this->request->post('content');
        $article = ArticleModel::where('id', 'userAgreement')->find();
        if (empty($article)) {
            ArticleModel::insert([
                'id' => 'userAgreement',
                'content' => $content,
                'update_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            ArticleModel::where('id', 'userAgreement')->update(['content' => $content, 'update_time' => date('Y-m-d H:i:s')]);
        }
        return json([
            'code' => 0,
            'msg' => '修改成功'
        ]);
    }

    public function getWithdrawList()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $userid = input("get.userid") ? input("get.userid") : '';
        $status = input("get.status") ? input("get.status") : '';
        $where = [];
        if (!empty($userid)) {
            $where[] = ['userid', '=', $userid];
        }
        if (!empty($status)) {
            $where[] = ['status', '=', $status];
        }
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = WithdrawModel::where($where)->count();
        $prdate = WithdrawModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $prdate;
        return json($list);
    }
    public function getWithdrawInfo()
    {
        $userid =  $this->request->post("userid");
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        //本月提现次数
        return json([
            'code' => 0,
            'msg' => '',
            'data' => [
                'balance' => $user->balance,
                'status' =>  $user->status,
            ]
        ]);
    }

    public function withdrawHandle()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $data =  $this->request->post("");
        if (empty($data['id'])) {
            return json([
                'code' => 1,
                'msg' => 'id必须填写'
            ]);
        }
        if (empty($data['userid'])) {
            return json([
                'code' => 1,
                'msg' => 'userid必须填写'
            ]);
        }
        $user = UserModel::where('id', $data['userid'])->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '该用户不存在'
            ]);
        }
        if (($data['charge'] != 0) && (empty($data['charge']))) {
            return json([
                'code' => 1,
                'msg' => 'charge必须填写'
            ]);
        }
        if (($data['amount'] != 0) && (empty($data['amount']))) {
            return json([
                'code' => 1,
                'msg' => 'amount必须填写'
            ]);
        }
        if (empty($data['status'])) {
            return json([
                'code' => 1,
                'msg' => 'status必须填写'
            ]);
        }
        $remark = "";
        if (!empty($data['remark'])) {
            $remark = trim($data['remark']);
        }
        $withdraw =  WithdrawModel::where('id', $data['id'])->find();
        if (empty($withdraw)) {
            return json([
                'code' => 1,
                'msg' => '找不到该提现记录'
            ]);
        }
        if ($withdraw->userid != $data['userid']) {
            return json([
                'code' => 1,
                'msg' => '该提现记录的userid不对'
            ]);
        }
        if ($withdraw->status != 1) {
            return json([
                'code' => 1,
                'msg' => '已经处理，无需再处理'
            ]);
        }
        $status = intval($data['status']);
        $amount = intval($data['amount']);
        $charge = intval($data['charge']);
        if (($amount + $charge) != $withdraw->money) {
            return json([
                'code' => 1,
                'msg' => '到账金额加手续费不等于提现金额'
            ]);
        }
        if ($user->balance < $withdraw->money) {
            return json([
                'code' => 1,
                'msg' => '用户余额不足'
            ]);
        }
        if ($status != 1 && $status != 2 && $status != 3) {
            return json([
                'code' => 1,
                'msg' => 'status错误'
            ]);
        }
        if ($status != $withdraw->status) {
            if ($status == 2) {
                $ret = $user->decreaseBalance($withdraw->money, 5, $withdraw->id, '');
                if (!$ret) {
                    return json([
                        'code' => 1,
                        'msg' => '扣除余额失败'
                    ]);
                }
            }
        }
        WithdrawModel::where('id', $data['id'])->update(['charge' => $data['charge'], 'amount' => $data['amount'], 'status' => $status, 'remark' => $remark, 'do_time' => date('Y-m-d H:i:s')]);
        if ($status == 2) {
            //提现成功
            $data = [
                'job' => 'send_submsg',
                'userid' => $user->id,
                'event' => "txsucc"
            ];
            Queue::push(QueueJob::class,  $data,  'default');
        } else if ($status == 3) {
            //提现失败
            $data = [
                'job' => 'send_submsg',
                'userid' => $user->id,
                'event' => "txfail"
            ];
            Queue::push(QueueJob::class,  $data,  'default');
        }
        return json([
            'code' => 0,
            'msg' => '成功'
        ]);
    }
    public function setWebsiteConfig()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $webName = $this->request->param('webName');
        $files = request()->file();
        $iconpath = "";
        if (isset($files['webLogo']) && (!empty($files['webLogo']))) {
            //检查文件大小
            $file = $files['webLogo'];
            if ($file->getSize() > 1024 * 1024) {
                return json([
                    'code' => 1,
                    'msg' => '网站Logo大小不能超过1M'
                ]);
            }
            //检查文件是否是图片
            $file_ex = $file->getOriginalExtension();
            if (!in_array($file_ex, ['jpg', 'png', 'jpeg', 'gif'])) {
                return json([
                    'code' => 1,
                    'msg' => '网站Logo格式错误'
                ]);
            }
            $path = public_path() . '/static/images/';
            $iconpath = '/static/images/websiteicon.' . $file_ex;
            try {
                $file->move($path, 'websiteicon.' . $file_ex);
            } catch (\Exception $e) {
                return json([
                    'code' => 1,
                    'msg' => '网站Logo上传失败'
                ]);
            }
        }
        $faviconpath = "";
        if (isset($files['favicon']) && (!empty($files['favicon']))) {
            $faviconFile = $files['favicon'];

            //检查文件大小
            if ($faviconFile->getSize() > 100 * 1024) {
                return json([
                    'code' => 1,
                    'msg' => '网站favicon大小不能超过100K'
                ]);
            }
            //检查文件是否是图片
            $file_ex = $faviconFile->getOriginalExtension();
            if (!in_array($file_ex, ['ico'])) {
                return json([
                    'code' => 1,
                    'msg' => '网站favicon格式错误,仅支持ico'
                ]);
            }
            $path = public_path() . '/static/images/';
            $faviconpath = '/static/images/favicon.ico';
            try {
                $faviconFile->move($path, 'favicon.ico');
            } catch (\Exception $e) {
                return json([
                    'code' => 1,
                    'msg' => '上传失败'
                ]);
            }
        }
        ConfigService::set('website', [
            'webName' => $webName,
            'webLogo' => $iconpath,
            'webFavicon' => $faviconpath
        ], "网站信息");
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function getUserInfo()
    {
        $userid = request()->get('userid');
        if (empty($userid)) {
            return json([
                'code' => 1,
                'msg' => 'userid必须填写'
            ]);
        }
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => "用户不存在"
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => $user
        ]);
    }

    public function updateProductTips()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $data = request()->post();
        $level = 0;
        $content = "";
        if (empty($data['product_id'])) {
            return json([
                'code' => 1,
                'msg' => 'product_id必须填写',
            ]);
        }
        if (empty($data['level'])) {
            return json([
                'code' => 1,
                'msg' => 'level必须填写',
            ]);
        } else {
            $level = intval($data['level']);
        }
        if ($level <= 0 || $level > 3) {
            return json([
                'code' => 1,
                'msg' => 'level非法',
            ]);
        }
        if (empty($data['content'])) {
            return json([
                'code' => 1,
                'msg' => 'content必须填写',
            ]);
        } else {
            $content = trim($data['content']);
        }
        $p = ProductTipsModel::where("product_id", $data['product_id'])->find();
        if (empty($p)) {
            ProductTipsModel::insert(['product_id' => $data['product_id'], 'level' => $level, 'content' => $content, 'update_time' => date('Y-m-d H:i:s')]);
        } else {
            ProductTipsModel::where("product_id", $data['product_id'])->update(['level' => $level, 'content' => $content, 'update_time' => date('Y-m-d H:i:s')]);
        }
        return json([
            'code' => 0,
            'msg' => '设置成功',
        ]);
    }
    public function getProductTipsData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = ProductTipsModel::count();
        $products = ProductTipsModel::limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $products;
        return json($list);
    }
    public function delProductTips()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $data = request()->post();
        if (empty($data['product_id'])) {
            return json([
                'code' => 1,
                'msg' => 'product_id必须填写',
            ]);
        }
        ProductTipsModel::where("product_id", $data['product_id'])->delete();
        return json([
            'code' => 0,
            'msg' => '删除成功',
        ]);
    }
    public function getCardData()
    {

        $list["code"] = 0;
        $list["msg"] = "";
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $cardid = input("get.cardid") ? input("get.cardid") : '';
        $orderid = input("get.orderid") ? input("get.orderid") : '';
        $userid = input("get.userid") ? input("get.userid") : '';
        $where = [];
        if (!empty($cardid)) {
            $where[] = ['id', 'LIKE', '%' . $cardid . '%'];
        }
        if (!empty($orderid)) {
            $where[] = ['order_id', 'LIKE', '%' . $orderid . '%'];
        }
        if (!empty($userid)) {
            $where[] = ['userid', '=', $userid];
        }
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = CardModel::where($where)->count();
        $prdate = CardModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $prdate;
        return json($list);
    }
    public function getUserNoticeData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $userid = input("get.userid") ? input("get.userid") : '';
        $status = input("get.status") ? input("get.status") : '';
        $position = input("get.position") ? input("get.position") : '';
        $where = [];
        if (!empty($userid)) {
            $where[] = ['userid', '=', $userid];
        }
        if (!empty($status)) {
            $where[] = ['status', '=', $status];
        }
        if (!empty($position)) {
            $where[] = ['position', '=', $position];
        }
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = UserNoticeModel::where($where)->count();
        $notices = UserNoticeModel::where($where)->order('update_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $notices;
        return json($list);
    }

    public function auditUserNotice()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $data = request()->post();
        if (empty($data['userid'])) {
            return json([
                'code' => 1,
                'msg' => "userid必须填写"
            ]);
        }
        if (empty($data['position'])) {
            return json([
                'code' => 1,
                'msg' => "position必须填写"
            ]);
        }
        if (empty($data['status'])) {
            return json([
                'code' => 1,
                'msg' => "status必须填写"
            ]);
        }
        UserNoticeModel::where(['userid' => $data['userid'], 'position' => $data['position']])->update(['status' => $data['status']]);
        return json([
            'code' => 0,
            'msg' => ''
        ]);
    }

    public function deleteUser()
    {
        $data = request()->post();
        $userid = 0;
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        if (empty($data['userid'])) {
            return json([
                'code' => 1,
                'msg' => "userid必须填写"
            ]);
        } else {
            $userid = intval($data['userid']);
        }
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => "用户不存在"
            ]);
        }
        if ($user->balance != 0) {
            return json([
                'code' => 1,
                'msg' => "该用户还有余额,不能注销"
            ]);
        }
        if ($user->points != 0) {
            return json([
                'code' => 1,
                'msg' => "该用户还有积分,不能注销"
            ]);
        }
        //开始注销
        AttachModel::where("userid", $userid)->delete();
        BalanceModel::where("user_id", $userid)->delete();
        CardModel::where("userid", $userid)->delete();
        CheckOrderModel::where("userid", $userid)->update(["userid" => 0]);
        EmailModel::where("user_id", $userid)->delete();
        PayRecordModel::where("userid", $userid)->update(['userid' => 0]);
        PointsModel::where("user_id", $userid)->delete();
        SmsModel::where("user_id", $userid)->delete();
        UserCheckModel::where("userid", $userid)->delete();
        UserNoticeModel::where("userid", $userid)->delete();
        UserWebModel::where("userid", $userid)->delete();
        WithdrawModel::where("userid", $userid)->delete();
        UserModel::where("tid", $userid)->update(["tid" => 0]);
        UserModel::where("id", $userid)->update(["name" => "", "email" => "#####", "mobile" => "#####", "status" => 3, "status_time" => date('Y-m-d H:i:s')]);
        return json([
            'code' => 0,
            'msg' => "注销成功"
        ]);
    }

    public function getUserWebid()
    {
        $userid = request()->get("userid");
        $web = UserWebModel::where("userid", $userid)->find();
        if (empty($web)) {
            return json([
                'code' => 1,
                'msg' => "该用户没有设置个性域名"
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => $web
        ]);
    }

    public function getBalanceData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = input("get.userid");
        $business_no = input("get.bid");
        $where = [];
        if (!empty($userid)) {
            $where['user_id'] = $userid;
        }
        if (!empty($business_no)) {
            $where['business_no'] = $business_no;
        }
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = BalanceModel::where($where)->count();
        $users = BalanceModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }

    public function getPointsData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = input("get.userid");
        $business_no = input("get.bid");
        $where = [];
        if (!empty($userid)) {
            $where['user_id'] = $userid;
        }
        if (!empty($business_no)) {
            $where['business_no'] = $business_no;
        }
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = PointsModel::where($where)->count();
        $users = PointsModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }

    public function getPayRecordData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = input("get.userid");
        $payid = input("get.payid");
        $scene = input("get.scene");
        $where = [];
        if (!empty($userid)) {
            $where['userid'] = $userid;
        }
        if (!empty($payid)) {
            $where['id'] = $payid;
        }
        if (!empty($scene)) {
            $where['scene'] = $scene;
        }
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = PayRecordModel::where($where)->count();
        $users = PayRecordModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }
}
