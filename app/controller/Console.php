<?php

namespace app\controller;

use app\BaseController;
use app\model\UserModel;
use app\model\EmailModel;
use app\model\SmsModel;
use app\model\BalanceModel;
use app\model\AttachModel;
use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\UserCheckModel;
use app\model\UserWebModel;
use app\model\WithdrawModel;
use think\exception\FileException;
use app\service\ConfigService;

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
                'avatar' => $user->getAvatar($this->request->domain()),
                'domain' => $domain
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
        $fileName = '' . $userid . $file_ex;
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
            if (($user->avatar != '/static/images/avatar/default.png') && ($user->avatar != $userAvatar) && (file_exists(public_path() . $user->avatar))) {
                unlink(public_path() . $user->avatar);
            }
        }
        $user->save(['avatar' => $userAvatar]);
        return json([
            'code' => 0,
            'msg' => '上传成功',
            'data' => [
                'avatar' => $user->getAvatar($this->request->domain())
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

        if (file_exists($file_path)) {
            unlink($file_path);
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
        $page = intval($page);
        $limit = intval($limit);
        $start = $limit * ($page - 1);
        $count = CheckModel::count();
        $products = CheckModel::limit($start, $limit)->select();
        $data = [];
        foreach ($products as $product) {
            $up = UserCheckModel::where(['userid' => $userid, 'product_id' => $product->id])->find();
            if (empty($up)) {
                $up = new UserCheckModel();
                $up->userid = $userid;
                $up->product_id = $product->id;
                $up->unit = $product->unit;
                $up->price = max($product->mini_price, $product->mini_price, $product->price);
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
                'unit' => $up->unit,
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
        if ($price < $check->price) {
            return json([
                'code' => 1,
                'msg' => '售价不能低于供货价'
            ]);
        }
        if ($price < $check->mini_price) {
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
        UserCheckModel::where(['userid' => $userid, 'product_id' => $product_id])->update(['price' => $price, 'status' => $status]);
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
        $where[] = ['userid', '=', $userid];
        $count = CheckOrderModel::where($where)->count();
        $products = CheckOrderModel::where($where)->withoutField('original,pcost,ppiece,pprofit,lock,file_key,report_url,payid,lock_time')->order('create_time', 'desc')->limit($start, $limit)->select();
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
}
