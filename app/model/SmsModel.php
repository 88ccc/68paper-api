<?php

namespace app\model;

use think\Model;
use app\tool\SMSTool;

class SmsModel extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'pt_sms';
    // 模型数据表主键
    protected $pk = 'id';
    // 设置字段信息
    protected $schema = [
        'id'        => 'string',              //键值
        'user_id'      => 'int',              //用户id
        'type_code'       => 'int',           //业务类型  1发送验证码
        'code'    => 'string',                //验证码
        'phone'  => 'string',                   //手机号码
        'sign_id' => 'string',                //签名id
        'template_id' => 'string',            //模板id
        'params' => 'string',                 //参数
        'sms_id' => 'string',                 //运营方返回的id
        'status' => 'int',                    //状态 0创建 1提交 2发送中 3成功 4提交失败 5发送失败
        'create_time'   => 'datetime',                      //创建时间
        'update_time'   => 'datetime',                      //状态更新时间
    ];

    //验证验证码
    public function verifyCode($mobile, $code)
    {
        //当前推后五分钟
        $expire_time = date('Y-m-d H:i:s', strtotime("-10 minutes"));
        $where = [
            'phone' => $mobile,
            'code' => $code,
            'type_code' => 1
        ];
        $result = $this->where($where)->whereTime('create_time', '>', $expire_time)->find();
        if ($result) {
            return ['code' => 0, 'msg' => '验证码正确'];
        } else {
            return ['code' => 1, 'msg' => '验证码失效或者错误'];
        }
    }

    /**
     * 发送验证码
     */
    public function sendCode($mobile, $userid)
    {
        $codetmp = rand(100000, 999999);
        $code = "" . $codetmp;
        return $this->send($mobile, 1, ['code' => $code], $userid, $code);
    }

    /**
     * 发送短信
     * mobile 手机号码
     * type_code 业务类型 1 发送验证码
     * params 参数 数组
     * userid 用户id
     * code 验证码
     */
    public function send($mobile, $type_code, $params, $userid, $code = "")
    {
        //验证手机号码
        if (!preg_match('/^1[3456789]\d{9}$/', $mobile)) {
            return ['code' => 1, 'msg' => '手机号码格式错误'];
        }
        //验证type_code
        if ($type_code != 1) {
            return ['code' => 1, 'msg' => '业务类型错误'];
        }
        //验证params
        if (!is_array($params)) {
            return ['code' => 1, 'msg' => '参数错误必须是数组'];
        }
        //创建ID,
        $id = uniqid("SMS", true);
        $this->id = $id;
        $this->user_id = $userid;
        $this->code = $code;
        $this->phone = $mobile;
        $this->type_code = $type_code;
        if ($type_code == 1) {
            $this->template_id = "SMS_316305398";
        }
        $this->params = json_encode($params, JSON_UNESCAPED_UNICODE);
        $this->status = 0;
        $this->create_time = date('Y-m-d H:i:s');
        $this->update_time = date('Y-m-d H:i:s');
        //发送
        $sms = new SMSTool();
        $result = $sms->send($mobile, $this->template_id, $params);
        if ($result['success']) {
            $this->status = 1;
            $this->save();
            return ['code' => 0, 'msg' => '发送成功'];
        } else {
            $this->status = 4;
            return ['code' => 1, 'msg' => '发送短信失败'];
        }
    }
}
