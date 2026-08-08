<?php

namespace app\controller;

use app\BaseController;
use app\model\UserModel;
use app\model\EmailModel;
use app\model\SmsModel;
use app\model\BalanceModel;
use app\model\AttachModel;
use app\model\CardModel;
use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\PointsModel;
use app\model\UserCheckModel;
use app\model\UserNoticeModel;
use app\model\UserWebModel;
use app\model\WithdrawModel;
use think\exception\FileException;
use app\service\ConfigService;
use think\facade\Config;
use app\service\PayService;
use app\service\CardService;
use think\facade\Log;

class Console extends BaseController
{
    public function userInfo()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $domain = "";
        $userweb =  UserWebModel::where('userid', $userid)->find();
        if (!empty($userweb)) {
            $domain = $userweb->webid;
        }

        return json([
            'code' => 0,
            'msg' => '登录成功',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->mobile,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'domain' => $domain,
                'pay_type' => $user->pay_type
            ]

        ]);
    }

    public function changeAvatar()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $avatar = $this->request->file('avatar');
        if (empty($avatar)) {
            return json([
                'code' => 1,
                'msg' => '请选择图片'
            ]);
        }

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
        $fileName = '' . $userid . "." . $file_ex;
        $userAvatar = '/static/images/avatar/' . $fileName;
        try {
            $avatar->move($path, $fileName);
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '头像上传失败'
            ]);
        }
        if (!empty($user->avatar)) {
            if (($user->avatar != '/images/avatar/default.png') && ($user->avatar != $userAvatar) && (file_exists(public_path() . $user->avatar))) {
                unlink(public_path() . $user->avatar);
            }
        }
        $user->save(['avatar' => $userAvatar]);
        return json([
            'code' => 0,
            'msg' => '上传成功',
            'data' => [
                'avatar' => $user->avatar
            ]
        ]);
    }

    public function changePassword()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $old_password = $this->request->post('oldpwd');
        if (empty($old_password)) {
            return json([
                'code' => 1,
                'msg' => '请输入旧密码'
            ]);
        }
        if ($user->pass != md5($old_password)) {
            return json([
                'code' => 1,
                'msg' => '旧密码错误'
            ]);
        }
        $new_password = $this->request->post('newpwd');
        if (empty($new_password)) {
            return json([
                'code' => 1,
                'msg' => '请输入新密码'
            ]);
        }
        $user->save(['pass' => md5($new_password)]);
        return json([
            'code' => 0,
            'msg' => '修改成功'
        ]);
    }

    public function changeEmail()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $email = $this->request->post('email');
        if (empty($email)) {
            return json([
                'code' => 1,
                'msg' => '请输入邮箱'
            ]);
        }
        $code = $this->request->post('code');
        $oldCode = $this->request->post('oldcode', '');
        $emailMode = new EmailModel();
        if (!empty($user->email)) {
            if (empty($oldCode)) {
                return json([
                    'code' => 1,
                    'msg' => '更换邮箱请验证旧邮箱'
                ]);
            }

            $ret = $emailMode->verifyCode($user->email, $oldCode);
            if ($ret['code'] != 0) {
                return json([
                    'code' => 1,
                    'msg' => "旧邮箱验证码错误"
                ]);
            }
        }
        $buser = UserModel::where('email', $email)->find();
        if (!empty($buser)) {
            return json([
                'code' => 1,
                'msg' => '邮箱已绑定其他帐号'
            ]);
        }
        $ret = $emailMode->verifyCode($email, $code);
        if ($ret['code'] != 0) {
            return json([
                'code' => 1,
                'msg' => "新邮箱验证码错误"
            ]);
        }
        $user->save(['email' => $email]);
        return json([
            'code' => 0,
            'msg' => '修改成功'
        ]);
    }
    //修改手机号
    public function changeMobile()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $mobile = $this->request->post('mobile');
        if (empty($mobile)) {
            return json([
                'code' => 1,
                'msg' => '请输入手机号'
            ]);
        }
        $code = $this->request->post('code');
        $oldCode = $this->request->post('oldcode', '');
        $mobileMode = new SmsModel();
        if (!empty($user->mobile)) {
            if (empty($oldCode)) {
                return json([
                    'code' => 1,
                    'msg' => '更换手机请验证旧手机'
                ]);
            }
            $ret = $mobileMode->verifyCode($user->mobile, $oldCode);
            if ($ret['code'] != 0) {
                return json([
                    'code' => 1,
                    'msg' => "旧手机验证码错误"
                ]);
            }
        }
        $buser = UserModel::where('mobile', $mobile)->find();
        if (!empty($buser)) {
            return json([
                'code' => 1,
                'msg' => '手机号已绑定其他帐号'
            ]);
        }
        $ret = $mobileMode->verifyCode($mobile, $code);
        if ($ret['code'] != 0) {
            return json([
                'code' => 1,
                'msg' => "新手机验证码错误"
            ]);
        }
        $user->save(['mobile' => $mobile]);
        return json([
            'code' => 0,
            'msg' => '修改成功'
        ]);
    }

    public function getBalance()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
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
                'balance' => $user->balance
            ]
        ]);
    }
    public function getBalanceList()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = $this->request->userid;
        $where = [
            'user_id' => $userid
        ];
        $oid = $this->request->get('oid');
        if (!empty($oid)) {
            $where['business_no'] = $oid;
        }
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = BalanceModel::where($where)->count();
        $data = BalanceModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $data;
        return json($list);
    }

    public function getPoints()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
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
                'points' => $user->points
            ]
        ]);
    }
    public function getPointsList()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = $this->request->userid;
        $where = [
            'user_id' => $userid
        ];
        $oid = $this->request->get('oid');
        if (!empty($oid)) {
            $where['business_no'] = $oid;
        }
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = PointsModel::where($where)->count();
        $data = PointsModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $data;
        return json($list);
    }

    public function attachment_upload()
    {
        $userid = $this->request->userid;
        $attach = AttachModel::where('userid', $userid)->find();
        if (!empty($attach)) {
            if ($attach->file_status == 4) {
                $list['code'] = 1;
                $list['msg'] = '你已经被永久禁止使用该功能';
                return json($list);
            }
        }



        $file = request()->file('file');
        if (empty($file)) {
            $list['code'] = 1;
            $list['msg'] = '文件不能为空';
            return json($list);
        }
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
        $save_name = $userid . '.pdf';
        $path = public_path() . '/static/attach_file';
        try {
            $file->move($path, $save_name);
            $now = date('Y-m-d H:i:s', time());
            if (!empty($attach)) {
                AttachModel::where('userid', $userid)->update(['file_name' => $file_name, 'file_path' => $path . '/' . $save_name, 'file_status' => 1, 'file_time' => $now, 'update_time' => $now]);
            } else {
                $attach = new AttachModel();
                $attach->userid = $userid;
                $attach->file_name = $file_name;
                $attach->file_path = $path . '/' . $save_name;
                $attach->file_status = 1;
                $attach->file_time = $now;
                $attach->update_time = $now;
                $attach->save();
            }
            return json([
                'code' => 0,
                'msg' => '',
                'data' => [
                    'userid' => $userid,
                    'status' => 1,
                    'file_time' => $now,
                    'file_name' => $file_name,
                ]
            ]);
        } catch (FileException $e) {
            $list['code'] = 1;
            $list['msg'] = '保存失败';
            return json($list);
        }
    }

    public function attachment_info()
    {
        $userid = $this->request->userid;
        $attach = AttachModel::where('userid', $userid)->find();
        if (empty($attach)) {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => [
                    'status' => 0,
                    'file_time' => '',
                    'file_name' => ''
                ]
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => [
                'userid' => $userid,
                'status' => $attach->file_status,
                'file_time' => $attach->file_time,
                'file_name' => $attach->file_name
            ]
        ]);
    }

    public function attachment_delete()
    {
        $userid = $this->request->userid;
        $attach = AttachModel::where('userid', $userid)->find();
        if (empty($attach)) {
            return json([
                'code' => 0,
                'msg' => ''
            ]);
        }
        if ($attach->file_status == 4) {
            $list['code'] = 1;
            $list['msg'] = '你已经被永久禁止使用该功能';
            return json($list);
        }
        $file_path =  $attach->file_path;
        if (!empty($file_path)) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        AttachModel::where('userid', $userid)->delete();
        return json([
            'code' => 0,
            'msg' => ''
        ]);
    }

    public function getCheckData()
    {
        $userid = $this->request->userid;
        $list["code"] = 0;
        $list["msg"] = "";
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $pid = input("get.pid") ? input("get.pid") : "";
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $where = [];
        if (!empty($pid)) {
            $where[] = ['id', '=', $pid];
        }
        $count = CheckModel::where($where)->count();
        $products = CheckModel::where($where)->limit($start, $limit)->select();
        $data = [];
        foreach ($products as $product) {
            $up = UserCheckModel::where(['userid' => $userid, 'product_id' => $product->id])->find();
            if (empty($up)) {
                $up = new UserCheckModel();
                $up->userid = $userid;
                $up->product_id = $product->id;
                $up->unit = $product->unit;
                $up->price = max($product->mini_price, $product->low_price, $product->price);
                if ($product->supplier_status == 1 || $product->status == 1) {
                    $up->status = 1;
                } else {
                    $up->status = 2;
                }
                $up->save();
            }
            $item = [
                'id' => $product->id,
                'name' => $product->name,
                'cost' => $product->price,
                'price' => $up->price,
                'reward' => $product->reward,
                'unit' => $product->unit,
                'punit' => $product->unit,
                'mini_price' => $product->mini_price,
                'status' => $up->status,
                'remark' => $product->remark,
            ];
            $data[] = $item;
        }
        $list["count"] = $count;
        $list["data"] = $data;
        return json($list);
    }

    public function updateCheckProduct()
    {
        $userid = $this->request->userid;
        $data = request()->post();
        $product_id = $data['id'];
        $price = 0;
        $unit = 0;
        $status = 0;
        $up = UserCheckModel::where(['userid' => $userid, 'product_id' => $product_id])->find();
        if (empty($up)) {
            $list['code'] = 1;
            $list['msg'] = '产品不存在';
            return json($list);
        }
        $check = CheckModel::where('id', $product_id)->find();
        if (empty($check)) {
            $list['code'] = 1;
            $list['msg'] = '产品不存在';
            return json($list);
        }
        if (empty($data['price'])) {
            return json([
                'code' => 1,
                'msg' => '售价不能为空'
            ]);
        } else {
            $price = intval($data['price']);
        }


        $costPrice = $check->price;
        $minPrice = $check->mini_price;

        if ($price < $costPrice) {
            return json([
                'code' => 1,
                'msg' => '售价不能低于供货价'
            ]);
        }
        if ($price > ($costPrice * 10)) {
            return json([
                'code' => 1,
                'msg' => '售价不能高于供货价10倍'
            ]);
        }
        if ($price < $minPrice) {
            return json([
                'code' => 1,
                'msg' => '售价不能低于最低售价'
            ]);
        }
        if (empty($data['status'])) {
            return json([
                'code' => 1,
                'msg' => '状态不能为空'
            ]);
        } else {
            $status = intval($data['status']);
        }
        if ($status != 1 && $status != 2) {
            return json([
                'code' => 1,
                'msg' => '状态值不正确'
            ]);
        }
        UserCheckModel::where(['userid' => $userid, 'product_id' => $product_id])->update(['price' => $price, 'unit' => $check->unit, 'status' => $status]);
        $list['code'] = 0;
        $list['msg'] = '更新成功';
        return json($list);
    }

    public function setUserName()
    {
        $userid = $this->request->userid;
        $data = request()->post();
        if (empty($data['name'])) {
            return json([
                'code' => 1,
                'msg' => '姓名不能为空'
            ]);
        }
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        if (!empty($user->name)) {
            return json([
                'code' => 1,
                'msg' => '姓名已经存在，请刷新网页'
            ]);
        }
        UserModel::where('id', $userid)->update(['name' => $data['name']]);
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function setUserDomain()
    {
        $userid = $this->request->userid;
        $data = request()->post();
        $domian = "";
        if (empty($data['domain'])) {
            return json([
                'code' => 1,
                'msg' => '域名不能为空'
            ]);
        } else {
            $domain = trim($data['domain']);
        }
        //
        //验证 $domain 只支持小写字母和数字，长度为 4~32
        if (!preg_match('/^[a-z0-9]{4,32}$/', $domain)) {
            return json([
                'code' => 1,
                'msg' => '个性域名只支持小写字母和数字，长度为4~32位'
            ]);
        }
        $isReserved = isReservedDomain($domain);
        if ($isReserved) {
            return json([
                'code' => 1,
                'msg' => '个性域名已被占用，请更换'
            ]);
        }
        $userweb = UserWebModel::where('userid', $userid)->find();
        if (!empty($userweb)) {
            return json([
                'code' => 1,
                'msg' => '个性域名已设置，请刷新网页'
            ]);
        }
        //判断是否被占用
        $yweb = UserWebModel::where('webid', $domain)->find();

        if (!empty($yweb)) {
            return json([
                'code' => 1,
                'msg' => '个性域名已被占用，请更换'
            ]);
        }
        UserWebModel::insert([
            'userid' => $userid,
            'webid' => $domain,
        ]);
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function getCheckOrderData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = $this->request->userid;
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

        $count = CheckOrderModel::where(function ($query) use ($userid) {
            $query->whereOr([[
                ['tid', '=', $userid],
                ['status', '>', 4]
            ], [
                ['userid', '=', $userid]
            ]]);
        })->where($where)->count();
        $products = CheckOrderModel::where(function ($query) use ($userid) {
            $query->whereOr([[
                ['tid', '=', $userid],
                ['status', '>', 4]
            ], [
                ['userid', '=', $userid]
            ]]);
        })->where($where)->withoutField('original,pcost,pprofit,lock,file_key,report_url,payid,lock_time')->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $products;
        return json($list);
    }

    public function homeData()
    {
        $userid = $this->request->userid;
        $user = UserModel::where("id", $userid)->find();
        //今日订单数
        $data = CheckOrderModel::where([
            ['userid', '=', $userid],
            ['status', '>', 3]
        ])->whereDay('create_time')->field('count(id) as mun_count, SUM(total_price) as sales,SUM(profit) as myprofit')->select()
            ->toArray();
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
        $balance = (float)bcdiv($user->balance, 100, 2);
        // 整理返回格式
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                "balance" => $balance,
                "pay_count" => $pay_count,
                "pay_amount" => $pay_amount,
                'profit' => $myprofit
            ]
        ]);
    }
    public function getCustomer()
    {
        $userid = $this->request->userid;
        $userweb = UserWebModel::where('userid', $userid)->find();
        if (empty($userweb)) {
            return json([
                'code' => 1,
                'msg' => '请先设置 检测链接 再来设置客服'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $userweb
        ]);
    }

    public function setCustomer()
    {
        $userid = $this->request->userid;
        $data = request()->post();
        $phone = "";
        $qq = "";
        $wechat = "";
        if (!empty($data['phone'])) {
            $phone = trim($data['phone']);
        }
        if (!empty($data['qq'])) {
            $qq = trim($data['qq']);
        }
        if (!empty($data['wechat'])) {
            $wechat = trim($data['wechat']);
        }
        $userweb = UserWebModel::where('userid', $userid)->find();
        if (empty($userweb)) {
            return json([
                'code' => 1,
                'msg' => '请先设置 检测链接 再来设置客服'
            ]);
        }

        $pattern = '/^[a-zA-Z0-9_\-+]{3,20}$/';
        if (!empty($phone)) {
            if (!(preg_match($pattern, $phone) === 1)) {
                return json([
                    'code' => 1,
                    'msg' => '电话号码格式非法'
                ]);
            }
        }
        if (!empty($wechat)) {
            if (!(preg_match($pattern, $wechat) === 1)) {
                return json([
                    'code' => 1,
                    'msg' => '微信号格式非法'
                ]);
            }
        }
        if (!empty($qq)) {
            if (!(preg_match($pattern, $qq) === 1)) {
                return json([
                    'code' => 1,
                    'msg' => 'qq号格式非法'
                ]);
            }
        }

        UserWebModel::where('userid', $userid)->update(['qq' => $qq, 'wechat' => $wechat, 'phone' => $phone]);
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function getWithdrawList()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = $this->request->userid;
        $where = [
            'userid' => $userid
        ];
        $page = input("get.page") ? input("get.page") : 1;
        $page = intval($page);
        $limit = input("get.limit") ? input("get.limit") : 1;
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = WithdrawModel::where($where)->count();
        $data = WithdrawModel::where($where)->order('create_time', 'desc')->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $data;
        return json($list);
    }

    public function getWithdrawInfo()
    {
        $userid = $this->request->userid;
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        //本月提现次数
        $ycash = WithdrawModel::where('userid', $user->id)->whereMonth('create_time')->select();

        $count = count($ycash);
        return json([
            'code' => 0,
            'msg' => '',
            'data' => [
                'balance' => $user->balance,
                'count' => $count,
                'name' => $user->name,
            ]
        ]);
    }

    public function withdrawSubmit()
    {
        $userid = $this->request->userid;
        $data = request()->post();
        //检查是否有待处理的申请
        $withdraw = WithdrawModel::where(['userid' => $userid, 'status' => 1])->find();
        if (!empty($withdraw)) {
            return json([
                'code' => 1,
                'msg' => '有待处理的提现申请，请等待处理完'
            ]);
        }
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        //检查余额是否充足
        if ($data['amount'] > $user->balance) {
            return json([
                'code' => 1,
                'msg' => '余额不足'
            ]);
        }
        $ycash = WithdrawModel::where('userid', $user->id)->whereMonth('create_time')->select();
        $count = count($ycash);
        $config = ConfigService::get("withdraw");
        if (!empty($config)) {
            if ($data['amount'] < ($config['min'] * 100)) {
                return json([
                    'code' => 1,
                    'msg' => '提现金额不能小于' . $config['min'] . '元'
                ]);
            }
            if ($count >= $config['count']) {
                return json([
                    'code' => 1,
                    'msg' => '每月最多提现' . $config['count'] . "次"
                ]);
            }
        }
        $withdraw = new WithdrawModel();
        $withdraw->id = uniqid('cash', true);;
        $withdraw->userid = $user->id;
        $withdraw->name = $user->name;
        $withdraw->account_type = $data['method'];
        $withdraw->account = $data['account'];
        $withdraw->money = $data['amount'];
        $withdraw->status = 1;
        $withdraw->create_time = date('Y-m-d H:i:s', time());
        $withdraw->save();
        return json([
            'code' => 0,
            'msg' => '提交成功，请等待审核'
        ]);
    }

    public function getInviteData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = $this->request->userid;
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = UserModel::where("tid", $userid)->count();
        $data = UserModel::where("tid", $userid)->field("id,tid,regtime,tmoney,status")->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $data;
        return json($list);
    }

    public function setPayType()
    {
        $userid = $this->request->userid;
        $paystr = request()->post("pay_type");
        if (empty($paystr)) {
            return json([
                'code' => 1,
                'msg' => '必须至少选择一种支付方式'
            ]);
        }
        $pay_type = "";
        $allpay = ['alipay', 'wechat', 'card'];
        $payarr = explode(",", $paystr);
        foreach ($payarr as $key => $value) {
            if (!in_array($value, $allpay)) {
                return json([
                    'code' => 1,
                    'msg' => '不支持的支付方式' . $value
                ]);
            }
            if (empty($pay_type)) {
                $pay_type = $value;
            } else {
                $pay_type = $pay_type . "," . $value;
            }
        }
        UserModel::where("id", $userid)->update(['pay_type' => $pay_type]);
        return json([
            'code' => 0,
            'msg' => '',
            'data' => $pay_type,
        ]);
    }

    public function getPayQRcode()
    {
        $userid = $this->request->userid;
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '测试环境不支持'
            ]);
        }
        $data = $this->request->post();
        if (!isset($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '类型不能为空'
            ]);
        }
        //金额
        if (!isset($data['amount'])) {
            return json([
                'code' => 10005,
                'msg' => '金额不能为空'
            ]);
        }
        if ($data['amount'] <= 0) {
            return json([
                'code' => 10006,
                'msg' => '金额错误'
            ]);
        }
        if (!isset($data['modeid'])) {
            return json([
                'code' => 10009,
                'msg' => '模版id不能为空'
            ]);
        }
        //判断金额是否为数字，紧紧支持两位小数
        if (!is_numeric($data['amount'])) {
            return json([
                'code' => 10007,
                'msg' => '金额错误'
            ]);
        }
        //点的位置
        $pos = strpos($data['amount'], '.');
        if ($pos === false) {
            $pos = strlen($data['amount']);
        }
        //判断小数点后两位
        if (strlen($data['amount']) - $pos - 1 > 2) {
            $len = strlen($data['amount']);
            return json([
                'code' => 10008,
                'msg' => '仅仅支持两位小数' . $len . "-" . $pos,
            ]);
        }
        $amount = floatval($data['amount']);


        if ($data['type'] == 'wechat') {
            $type = 1; //微信支付
        } else if (($data['type'] == 'alipay')) {
            $type = 2; //支付宝支付
        } else {
            return json([
                'code' => 10010,
                'msg' => '不支持的支付方式'
            ]);
        }
        if ($amount <= 0) {
            return json([
                'code' => 10011,
                'msg' => '金额错误'
            ]);
        }

        $subject = "预充值";
        $ret = (new PayService())->getQRcode($data['modeid'], 2, $userid, $type, $amount, '', $subject);
        return json($ret);
    }

    public function getH5Pay()
    {
        $userid = $this->request->userid;
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '测试环境不支持'
            ]);
        }
        $data = $this->request->post();
        if (!isset($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '类型不能为空'
            ]);
        }

        //金额
        if (!isset($data['amount'])) {
            return json([
                'code' => 10005,
                'msg' => '金额不能为空'
            ]);
        }
        if ($data['amount'] <= 0) {
            return json([
                'code' => 10006,
                'msg' => '金额错误'
            ]);
        }
        if (!isset($data['modeid'])) {
            return json([
                'code' => 10009,
                'msg' => '模版id不能为空'
            ]);
        }
        //判断金额是否为数字，紧紧支持两位小数
        if (!is_numeric($data['amount'])) {
            return json([
                'code' => 10007,
                'msg' => '金额错误'
            ]);
        }
        //点的位置
        $pos = strpos($data['amount'], '.');
        if ($pos === false) {
            $pos = strlen($data['amount']);
        }
        //判断小数点后两位
        if (strlen($data['amount']) - $pos - 1 > 2) {
            $len = strlen($data['amount']);
            return json([
                'code' => 10008,
                'msg' => '仅仅支持两位小数' . $len . "-" . $pos,
            ]);
        }
        $amount = floatval($data['amount']);


        if ($data['type'] == 'wechat') {
            $type = 1; //微信支付
        } else if (($data['type'] == 'alipay')) {
            $type = 2; //支付宝支付
        } else {
            return json([
                'code' => 10010,
                'msg' => '不支持的支付方式'
            ]);
        }
        if ($amount <= 0) {
            return json([
                'code' => 10011,
                'msg' => '金额错误'
            ]);
        }

        $subject = "预充值";
        $ret = [];
        $return_url = "";
        if (!empty($data['returnUrl'])) {
            $return_url = $data['returnUrl'];
        }
        if ($type == 1) {
            $ip = $this->request->ip();
            $ret = (new PayService())->wxH5pay('', 2, $userid, $amount, $subject, $data['modeid'], $ip);
        } else if ($type == 2) {
            $ret = (new PayService())->aliH5pay('', 2, $userid, $amount, $subject, $data['modeid'], $return_url);
        }

        return json($ret);
    }

    //微信内部，jsap支付
    public function getMPpay()
    {
        $userid = $this->request->userid;
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '测试环境不支持'
            ]);
        }
        $data = $this->request->post();
        if (!isset($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '类型不能为空'
            ]);
        }
        //金额
        if (!isset($data['amount'])) {
            return json([
                'code' => 10005,
                'msg' => '金额不能为空'
            ]);
        }
        if ($data['amount'] <= 0) {
            return json([
                'code' => 10006,
                'msg' => '金额错误'
            ]);
        }
        if (!isset($data['modeid'])) {
            return json([
                'code' => 10009,
                'msg' => '模版id不能为空'
            ]);
        }
        //判断金额是否为数字，紧紧支持两位小数
        if (!is_numeric($data['amount'])) {
            return json([
                'code' => 10007,
                'msg' => '金额错误'
            ]);
        }
        //点的位置
        $pos = strpos($data['amount'], '.');
        if ($pos === false) {
            $pos = strlen($data['amount']);
        }
        //判断小数点后两位
        if (strlen($data['amount']) - $pos - 1 > 2) {
            $len = strlen($data['amount']);
            return json([
                'code' => 10008,
                'msg' => '仅仅支持两位小数' . $len . "-" . $pos,
            ]);
        }
        $amount = floatval($data['amount']);


        if ($data['type'] == 'wechat') {
            $type = 1; //微信支付
        } else if (($data['type'] == 'alipay')) {
            $type = 2; //支付宝支付
        } else {
            return json([
                'code' => 10010,
                'msg' => '不支持的支付方式'
            ]);
        }
        if ($amount <= 0) {
            return json([
                'code' => 10011,
                'msg' => '金额错误'
            ]);
        }

        if (empty($data['openid'])) {
            return json([
                'code' => 1,
                'msg' => '缺少参数openid'
            ]);
        }
        $subject = "预充值";
        $ret = [];
        $ret = (new PayService())->wxMPpay('', 2, $userid, $amount, $subject, $data['modeid'], $data['openid']);
        return json($ret);
    }

    public function createCheckCard()
    {
        $extensionsEnable = false;
        $funConfig = ConfigService::get("function");
        if (!empty($funConfig)) {
            $extensionsEnable = strtolower($funConfig['extensions']) == 'true';
        }
        if (!$extensionsEnable) {
            return json([
                'code' => 1,
                'msg' => '没有启用扩展功能'
            ]);
        }
        $data = $this->request->post();
        if (empty($data['piece'])) {
            return json([
                'code' => 1,
                'msg' => '件数不能为空'
            ]);
        }
        if (empty($data['product_id'])) {
            return json([
                'code' => 1,
                'msg' => '产品ID不能为空'
            ]);
        }
        $num = 1;
        if (!empty($data['mun'])) {
            $num = intval($data['mun']);
        }
        $remark = "";
        if (!empty($data['remark'])) {
            $remark = $data['remark'];
        }
        $userid = intval($this->request->userid);
        for ($i = 0; $i < $num; $i++) {
            $ret = (new CardService())->createCard($userid, $data['product_id'], $data['piece'], $remark);
            if ($ret['code'] != 0) {
                return json($ret);
            }
        }

        return json([
            'code' => 0,
            'msg' => '创建成功'
        ]);
    }

    public function getCardData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = $this->request->userid;
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $cardid = input("get.cardid") ? input("get.cardid") : '';
        $orderid = input("get.orderid") ? input("get.orderid") : '';
        $status = input("get.status") ? input("get.status") : '';
        $where[] = ['userid', '=', $userid];
        if (!empty($cardid)) {
            $where[] = ['id', 'LIKE', '%' . $cardid . '%'];
        }
        if (!empty($orderid)) {
            $where[] = ['order_id', 'LIKE', '%' . $orderid . '%'];
        }
        if (!empty($status)) {
            $where[] = ['status', '=', $status];
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

    public function disableCard()
    {
        $data = $this->request->post();
        if (empty($data['cardid'])) {
            return json([
                'code' => 1,
                'msg' => '卡号不能为空'
            ]);
        }
        $userid = $this->request->userid;
        $card = CardModel::where(['id' => $data['cardid']])->find();
        if ($card->userid != $userid) {
            return json([
                'code' => 1,
                'msg' => '这个检测卡不是你的，你不能禁用'
            ]);
        }
        if ($card->status != 1) {
            return json([
                'code' => 1,
                'msg' => '这个检测卡已经使用或者已经禁用，无法再禁用'
            ]);
        }
        CardModel::where(['id' => $data['cardid'], 'userid' => $userid])->update([
            'status' => 3
        ]);
        return json([
            'code' => 0,
            'msg' => ''
        ]);
    }

    public function getCardKey()
    {
        $extensionsEnable = false;
        $funConfig = ConfigService::get("function");
        if (!empty($funConfig)) {
            $extensionsEnable = strtolower($funConfig['extensions']) == 'true';
        }
        if (!$extensionsEnable) {
            return json([
                'code' => 1,
                'msg' => '没有启用扩展功能'
            ]);
        }
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $type = $this->request->get('verifyType');
        $code = $this->request->get('code');
        if ($type == 'email') {
            $emailMode = new EmailModel();
            $ret = $emailMode->verifyCode($user->email, $code);
            if ($ret['code'] != 0) {
                return json([
                    'code' => 1,
                    'msg' => "验证码错误"
                ]);
            }
        } else if ($type == 'phone') {
            $mobileMode = new SmsModel();
            $ret = $mobileMode->verifyCode($user->mobile, $code);
            if ($ret['code'] != 0) {
                return json([
                    'code' => 1,
                    'msg' => "验证码错误"
                ]);
            }
        }
        $cardkey = $user->cardkey;
        if (empty($cardkey)) {
            $ret = (new CardService())->resetCardKey($userid);
            if ($ret['code'] != 0) {
                return json($ret);
            } else {
                $cardkey = $ret['data']['card_key'];
            }
        }
        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'cardkey' => $cardkey
            ]
        ]);
    }

    public function resetCardKey()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $ret = (new CardService())->resetCardKey($userid);
        if ($ret['code'] != 0) {
            return json($ret);
        }
        return json([
            'code' => 0,
            'msg' => '重置成功',
        ]);
    }

    public function getOtherSetting()
    {
        $userid = $this->request->userid;
        $userweb = UserWebModel::where('userid', $userid)->find();
        if (empty($userweb)) {
            return json([
                'code' => 1,
                'msg' => '请先去”检测链接“中设置',
                'data' => []
            ]);
        }
        $showJoin = false;
        if ($userweb->show_jc == 1) {
            $showJoin = true;
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => [
                'showJoin' => $showJoin
            ]
        ]);
    }

    public function setOtherSetting()
    {
        $userid = $this->request->userid;
        $data = $this->request->post("config");
        if (!empty($data['showJoin'])) {
            $showJoin = strtolower($data['showJoin']) == 'true';
            if ($showJoin) {
                UserWebModel::where('userid', $userid)->update(['show_jc' => 1]);
            } else {
                UserWebModel::where('userid', $userid)->update(['show_jc' => 0]);
            }
        }
        return json([
            'code' => 0,
            'msg' => "设置成功"
        ]);
    }

    public function updateUserNotice()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $userid = $this->request->userid;
        $data = request()->post();
        $content = "";
        if (empty($data['position'])) {
            return json([
                'code' => 1,
                'msg' => 'position必须填写',
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
        $p = UserNoticeModel::where("position", $data['position'])->find();
        if (empty($p)) {
            UserNoticeModel::insert(['userid' => $userid, 'position' => $data['position'], 'conent' => $content, 'status' => 1, 'update_time' => date('Y-m-d H:i:s')]);
        } else {
            UserNoticeModel::where(['userid' => $userid, 'position' => $data['position']])->update(['status' => 1, 'conent' => $content, 'update_time' => date('Y-m-d H:i:s')]);
        }
        return json([
            'code' => 0,
            'msg' => '设置成功',
        ]);
    }

    public function delUserNotice()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $userid = $this->request->userid;
        $data = request()->post();
        if (empty($data['position'])) {
            return json([
                'code' => 1,
                'msg' => 'position必须填写',
            ]);
        }
        UserNoticeModel::where(['userid' => $userid, 'position' => $data['position']])->delete();
        return json([
            'code' => 0,
            'msg' => '删除成功',
        ]);
    }

    public function getUserNoticeData()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $userid = $this->request->userid;
        $page = input("get.page") ? input("get.page") : 1;
        $limit = input("get.limit") ? input("get.limit") : 1;
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = UserNoticeModel::where("userid", $userid)->count();
        $notices = UserNoticeModel::where("userid", $userid)->limit($start, $limit)->select();
        $list["count"] = $count;
        $list["data"] = $notices;
        return json($list);
    }

    public function getSubscribeMsg()
    {
        $userid = $this->request->userid;
        $user =  UserModel::where("id", $userid)->find();
        $event = "";
        $method = "";
        $threshold = 0;
        if (!empty($user)) {
            if (!empty($user->alarm_method)) {
                if (is_array($user->alarm_method)) {
                    $event = $user->alarm_method['event'];
                    $method = $user->alarm_method['method'];
                } else {
                    $event = $user->alarm_method->event;
                    $method = $user->alarm_method->method;
                }
            }
            if (!empty($user->alarm_threshold)) {
                $threshold = $user->alarm_threshold;
            }
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => [
                'event' => $event,
                'method' => $method,
                'threshold' => $threshold
            ]
        ]);
    }

    public function setSubscribeMsg()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '演示网站不准设置'
            ]);
        }
        $userid = $this->request->userid;
        $event = "";
        $method = "";
        $threshold = 0;
        $data = request()->post();
        if (!empty($data['event'])) {
            $event = $data['event'];
        }
        if (!empty($data['method'])) {
            $method = $data['method'];
        }
        if (!empty($data['threshold'])) {
            $threshold = intval($data['threshold']);
        }
        UserModel::where("id", $userid)->update(['alarm_threshold' => $threshold, 'alarm_method' => ['event' => $event, 'method' => $method]]);
        return json([
            'code' => 0,
            'msg' => '设置成功'
        ]);
    }

    public function getApiInfo()
    {
        $userid = $this->request->userid;
        $user = UserModel::where("id", $userid)->find();
        $notify_url = Config::get('unotify.notify_' . $userid);
        return json([
            'code' => 0,
            'msg' => '',
            'data' => [
                'status' => $user->api_status,
                'notify_url' => $notify_url,
            ]
        ]);
    }

    public function getApiKey()
    {
        $extensionsEnable = false;
        $funConfig = ConfigService::get("function");
        if (!empty($funConfig)) {
            $extensionsEnable = strtolower($funConfig['extensions']) == 'true';
        }
        if (!$extensionsEnable) {
            return json([
                'code' => 1,
                'msg' => '没有启用扩展功能'
            ]);
        }
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $type = $this->request->get('verifyType');
        $code = $this->request->get('code');
        if ($type == 'email') {
            $emailMode = new EmailModel();
            $ret = $emailMode->verifyCode($user->email, $code);
            if ($ret['code'] != 0) {
                return json([
                    'code' => 1,
                    'msg' => "验证码错误"
                ]);
            }
        } else if ($type == 'phone') {
            $mobileMode = new SmsModel();
            $ret = $mobileMode->verifyCode($user->mobile, $code);
            if ($ret['code'] != 0) {
                return json([
                    'code' => 1,
                    'msg' => "验证码错误"
                ]);
            }
        }
        $apikey = $user->apikey;
        if (empty($apikey)) {
            $apikey = getNonceStr(32);
            UserModel::where("id", $userid)->update(['apikey' => $apikey]);
        }
        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'apikey' => $apikey
            ]
        ]);
    }

    public function resetApiKey()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $apikey = getNonceStr(32);
        UserModel::where("id", $userid)->update(['apikey' => $apikey]);

        return json([
            'code' => 0,
            'msg' => '重置成功',
        ]);
    }
}
