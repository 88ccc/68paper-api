<?php

namespace app\controller;

use app\BaseController;
use app\model\AdminModel;
use app\service\ConfigService;
use app\service\WxPublicService;
use think\facade\Log;
use app\model\PayModeModel;
use app\model\PaySetModel;
use app\supplier\Check as CheckSupplier;
use app\model\CheckModel;
use app\model\ShopModel;
use app\model\ShopProductModel;
use think\exception\FileException;
use app\model\CheckOrderModel;
use app\service\PayService;

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
                'avatar' => $user->getAvatar($this->request->domain()),
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
            return json([
                'code' => 0,
                'data' => $config
            ]);
        }
    }

    public function setWxPublicConfig()
    {
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

    public function setPayMode()
    {
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
        $list["msg"] = "暂无数据";

        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = PayModeModel::count();
        $users = PayModeModel::order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }
    public function deletePayMode()
    {
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
                if (($admin->avatar != '/static/images/avatar/default.png') && ($admin->avatar != $userAvatar) && (file_exists(public_path() . $admin->avatar))) {
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
            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }
    public function clearCheckKeyConfig()
    {
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
            $mycheck->unit = $check['unit'];
            $mycheck->mini_price = $check['mini_price'];
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
        $type = input("get.type");
        if (empty($type)) {
            $type = 0;
        }
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

    public function desableCheckProduct()
    {
        $id = $this->request->post("id");
        CheckModel::where('id', $id)->update(['status' => 2]);
        return json([
            'code' => 0,
            'msg' => '',
        ]);
    }

    public function enableCheckProduct()
    {
        $id = $this->request->post("id");
        CheckModel::where('id', $id)->update(['status' => 1]);
        return json([
            'code' => 0,
            'msg' => '',
        ]);
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

    public function addShop()
    {
        $id = $this->request->post("id");
        $name = $this->request->post("name");
        if (empty($id)) {
            return json([
                'code' => 1,
                'msg' => '店铺id必填写'
            ]);
        }
        $id = trim($id);
        $idlen = strlen($id);
        if ($idlen > 64 || $idlen < 1) {
            return json([
                'code' => 1,
                'msg' => 'id的长度不得超过64'
            ]);
        }
        $pattern = '/^[a-z0-9]{1,64}$/';
        if (preg_match($pattern, $id) !== 1) {
            return json([
                'code' => 1,
                'msg' => '店铺ID仅支持小写字母和数字'
            ]);
        }
        if (empty($name)) {
            return json([
                'code' => 1,
                'msg' => '店铺名称不能为空'
            ]);
        }
        $name = trim($name);
        $shop = ShopModel::where('id', $id)->find();
        if (!empty($shop)) {
            return json([
                'code' => 1,
                'msg' => '店铺已经存在'
            ]);
        }
        $shop = new ShopModel();
        $shop->id = $id;
        $shop->name = $name;
        $shop->status = 1;
        $now = date('Y-m-d H:i:s', time());
        $shop->create_time = $now;
        $shop->update_time = $now;
        $shop->save();
        return json([
            'code' => 0,
            'msg' => ''
        ]);
    }

    public function enableShop()
    {
        $id = $this->request->post("id");
        ShopModel::where('id', $id)->update(['status' => 1]);
        return json([
            'code' => 0,
            'msg' => '',
        ]);
    }
    public function desableShop()
    {
        $id = $this->request->post("id");
        ShopModel::where('id', $id)->update(['status' => 2]);
        return json([
            'code' => 0,
            'msg' => '',
        ]);
    }

    public function deleteShop()
    {
        $id = $this->request->post("id");
        ShopModel::where('id', $id)->delete();;
        return json([
            'code' => 0,
            'msg' => '',
        ]);
    }
    public function getShopData()
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
        $count = ShopModel::count();
        $products = ShopModel::limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $products;
        return json($list);
    }

    public function getShopInfo()
    {
        $shopid = $this->request->post("shopid");
        $shop = ShopModel::where('id', $shopid)->find();
        if (empty($shop)) {
            return json([
                'code' => 1,
                'msg' => '没有找到该店铺',
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => $shop,
        ]);
    }

    public function uploadShopFile()
    {
        $shopid = $this->request->post("shopid");
        //查看这个店铺
        $shop = ShopModel::where('id', $shopid)->find();
        if (empty($shop)) {
            return json([
                'code' => 1,
                'msg' => '找不到这个店铺',
            ]);
        }
        //获取文件信息
        $file = $this->request->file('file');
        if (empty($file)) {
            return json([
                'code' => 1,
                'msg' => '未上传文件',
            ]);
        }
        //判断文件类型，只支持pdf
        $file_ex = $file->getOriginalExtension();
        $file_name = $file->getOriginalName();
        $file_size = $file->getSize();
        if (strcmp($file_ex, 'pdf') != 0) {
            $list['code'] = 1;
            $list['msg'] = '仅支持后缀为pdf的文件';
            return json($list);
        }

        if (!(preg_match("/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+\.pdf$/u", $file_name))) {
            $list['code'] = 2;
            $list['msg'] = '文件名只能是中文、大小写字母、数字和下划线，例如 查重秘籍_abcDEF_123.pdf';
            return json($list);
        }
        if ($file_size > 2097152) {
            //2M 2 X 1024 X 1024
            $list['code'] = 1;
            $list['msg'] = '文件大小不得超过2M';
            return json($list);
        }
        $save_name = $shopid . '.pdf';
        //保存路劲
        $path = runtime_path() . '/shop_file/';
        if (!file_exists($path)) {
            $ret = mkdir($path, 0777, true);
            if ($ret === false) {
                Log::error("uploadShopFile 创建文件夹失败 " . $path);
                $list['code'] = 1;
                $list['msg'] = '系统异常，无法创建路径';
                return json($list);
            }
        }
        try {
            $file->move($path, $save_name);
            $now = date('Y-m-d H:i:s', time());
            ShopModel::where('id', $shopid)->update(['file_name' => $file_name, 'file_path' => $path . $save_name]);
            $list['code'] = 0;
            $list['msg'] = '上传成功,';
            $list['data'] = [
                "file_name" => $file_name
            ];
            return json($list);
        } catch (FileException $e) {
            $list['code'] = 1;
            $list['msg'] = '保存失败';
            return json($list);
        }
    }
    public function deleteShopFile()
    {
        $shopid = $this->request->post("shopid");
        $path = runtime_path() . '/shop_file/' . $shopid . ".pdf";
        if (file_exists($path)) {
            unlink($path);
        }
        ShopModel::where('id', $shopid)->update(['file_name' => '', 'file_path' => '']);
        return json([
            'code' => 0,
            'msg' => ''
        ]);
    }

    public function syncShopProduct()
    {
        $shopid = $this->request->post("shopid");
        if (empty($shopid)) {
            return json([
                'code' => 1,
                'msg' => '店铺id不能为空'
            ]);
        }
        $shop = ShopModel::where('id', $shopid)->find();
        if (empty($shop)) {
            return json([
                'code' => 1,
                'msg' => '店铺不存在'
            ]);
        }
        $ids = [];
        $checks = CheckModel::select();
        foreach ($checks as $check) {

            $ids[] = $check['id'];
            $product = ShopProductModel::where(['shopid' => $shopid, 'productid' => $check['id']])->find();
            if (empty($product)) {
                $now = date('Y-m-d H:i:s', time());
                $product = new ShopProductModel();
                $product->shopid = $shopid;
                $product->productid = $check['id'];
                $product->unit = $check['unit'];
                $product->price = $check['mini_price'];
                $product->status = 1;
                $product->create_time = $now;
                $product->update_time = $now;
                $product->save();
            } else {
                $b = 1;
                $checked = false;
                //检查价格
                if ($check['unit'] == 0) {
                    //product unit必须等于0
                    if ($product->unit != 0) {
                        $product->unit = 0;
                        $product->price = $check['mini_price'];
                        $product->save();
                        $checked = true;
                    }
                } else if ($product->unit < $check['unit']) {
                    $product->unit = $check['unit'];
                    $product->price = $check['mini_price'];
                    $product->save();
                    $checked = true;
                } else {
                    $remainder = bcmod($product->unit, $check['unit'], 0);
                    if ($remainder != 0) {
                        $product->unit = $check['unit'];
                        $product->price = $check['mini_price'];
                        $product->save();
                        $checked = true;
                    } else {
                        $b = bcdiv($product->unit, $check['unit'], 0);
                    }
                }

                //比较价格
                if (!$checked) {
                    $cost = bcmul($check['cost'], $b, 0);
                    if ($cost > $product->price) {
                        $product->unit = $check['unit'];
                        $product->price = $check['mini_price'];
                        $product->save();
                    }
                    $min = bcmul($check['mini_price'], $b, 0);
                    if ($min > $product->price) {
                        $product->unit = $check['unit'];
                        $product->price = $check['mini_price'];
                        $product->save();
                    }
                }
            }
        }

        $allProduct = ShopProductModel::where("shopid", $shopid)->field(['productid'])->select();
        foreach ($allProduct as $product) {
            if (!in_array($product['productid'], $ids)) {
                CheckModel::where(['shopid' => $shopid, 'productid' => $product['productid']])->delete();
            }
        }
        return json([
            'code' => 0,
            'msg' => ''
        ]);
    }

    public function getShopProductData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $list["count"] = 0;
        $shopid = input("get.shopid") ? input("get.shopid") : "";
        if (empty($shopid)) {
            return json($list);
        }
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = ShopProductModel::where("shopid", $shopid)->count();
        $users = ShopProductModel::where("shopid", $shopid)->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $users;
        return json($list);
    }

    public function editShopProduct()
    {
        $shopid = $this->request->post('shopid');
        $productid = $this->request->post("productid");
        $status = $this->request->post("status");
        $unit = $this->request->post("unit");
        $price = $this->request->post("price");
        $check = CheckModel::where('id', $productid)->find();
        $tips = $this->request->post("tips");
        if (empty($tips)) {
            $tips = "";
        } else {
            $tips = trim($tips);
        }

        $unit = intval($unit);
        $price = intval($price);
        $status = intval($status);
        if (($status != 1) && ($status != 2)) {
            return json([
                'code' => 1,
                'msg' => '状态不合法',
            ]);
        }

        if (empty($check)) {
            return json([
                'code' => 1,
                'msg' => '该货源不存在',
            ]);
        }
        $product = ShopProductModel::where(['shopid' => $shopid, 'productid' => $productid])->find();
        if (empty($product)) {
            return json([
                'code' => 1,
                'msg' => '该产品不存在',
            ]);
        }
        //检查价格
        if ($check['unit'] == 0) {
            //product unit必须等于0
            if ($unit != 0) {
                return json([
                    'code' => 1,
                    'msg' => '计费单位必须为0',
                ]);
            }
        }
        if (($check['unit'] != 0) && ($unit == 0)) {
            return json([
                'code' => 1,
                'msg' => '计费单位不能为0',
            ]);
        }
        if ($unit < $check['unit']) {
            return json([
                'code' => 1,
                'msg' => '计费单位必须大于' . $check['unit'] . "因为货源的计费单位是" . $check['unit'],
            ]);
        } else {
            $remainder = 0;
            if ($unit != 0) {
                $remainder = bcmod($product->unit, $check['unit'], 0);
            }

            if ($remainder != 0) {
                return json([
                    'code' => 1,
                    'msg' => '计费单位必须是' . $check['unit'] . "的整数倍,因为货源的计费单位是" . $check['unit'],
                ]);
            } else {
                //比较价格
                $cost = $check['cost'];
                if ($unit != 0) {
                    $b = bcdiv($unit, $check['unit'], 0);
                    $cost = bcmul($check['cost'], $b, 0);
                }

                if ($cost > $price) {
                    return json([
                        'code' => 1,
                        'msg' => '这个售价会亏本',
                    ]);
                }
                //比较最低售价
                $min = $check['mini_price'];


                if ($unit != 0) {
                    $b = bcdiv($unit, $check['unit'], 0);
                    $min = bcmul($check['mini_price'], $b, 0);
                }
                $min100 = bcdiv($check['mini_price'], 100, 2);
                $smsg = "" . $min100 . "元/次";
                if ($unit != 0) {
                    $smsg = "" . $min100 . "元/" . $check['unit'] . "字符";
                }
                if ($min > $price) {
                    return json([
                        'code' => 1,
                        'msg' => '必须高于规定的最低售价' . $smsg,
                    ]);
                }
            }
        }
        //合规
        ShopProductModel::where(['shopid' => $shopid, 'productid' => $productid])->update(['status' => $status, 'unit' => $unit, 'price' => $price, 'tips' => $tips, 'update_time' => date('Y-m-d H:i:s', time())]);
        return json([
            'code' => 0,
            'msg' => ''
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
        $shopid = input("get.shopid") ? input("get.shopid") : "";
        $shopid = trim($shopid);
        if (!empty($shopid)) {
            $where[] = ['shopid', '=', $shopid];
        }
        $count = CheckOrderModel::where($where)->count();
        $products = CheckOrderModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $products;
        return json($list);
    }

    public function orderRefund()
    {
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
        $ret  = (new PayService())->refund($order->payid);
        if ($ret['code'] == 0) {
            CheckOrderModel::where(["id" => $order->id])->update(["status" => 9, "update_time" => date('Y-m-d H:i:s')]);
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

    public function homeData()
    {
        //今日订单数
        $sub_count = CheckOrderModel::whereDay('create_time')->count();
        $data = CheckOrderModel::whereDay('create_time')->where([['status', 'IN', [4, 5, 6, 7, 8, 10]]])->field('count(id) as mun_count, SUM(total_price) as sales,SUM(profit) as myprofit')->select()
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
}
