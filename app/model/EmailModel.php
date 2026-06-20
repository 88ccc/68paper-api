<?php

namespace app\model;

use app\tool\EmailTool;
use think\Model;


class EmailModel extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'pt_email';
    // 模型数据表主键
    protected $pk = 'id';
    // 设置字段信息
    protected $schema = [
        'id'           => 'string',          //键值
        'user_id'      => 'int',             //用户id
        'type_code'    => 'int',             //业务类型 1发送验证码 2 余额告警
        'email'        => 'string', //接收方
        'code'    => 'string',                //验证码
        'subject'      => 'string',          //邮件主题
        'body'         => 'string',          //邮件内容
        'attachment'   => 'string',          //附件   可以是多个 用';'隔开
        'status'       => 'int',              //状态 0已创建未提交  1成功  2失败
        'create_time'  => 'datetime',         //创建时间
        'update_time'  => 'datetime',         //更新时间
    ];




    //验证验证码
    public function verifyCode($email, $code)
    {
        //当前推后五分钟
        $expire_time = date('Y-m-d H:i:s', strtotime("-10 minutes"));
        $where = [
            'email' => $email,
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
    public function sendCode($email, $userid, $isReg = true)
    {
        $expire_time = date('Y-m-d H:i:s', strtotime("-1 minutes"));
        $result = $this->where(['email'=>$email,'type_code'=>1])->whereTime('create_time', '>', $expire_time)->find();
        if ($result) {
            return ['code' => 1, 'msg' => '发邮件太频繁了'];
        }
        $codetmp = rand(100000, 999999);
        $code = "" . $codetmp;
        $emailtool = new EmailTool();
        $content = "您的邮箱验证码为：" . $code . "，请勿告诉任何人。";
        if ($isReg) {
            $codeTemplate = $emailtool->getCodeTemplate();
            $content = str_replace("{code}", $code, $codeTemplate);
        }

        $mid = uniqid("EMAIL", true);
        $subject = "验证码";
        if ($isReg) {
            $subject = $emailtool->getCodeSubject();
        }

        $model = new EmailModel();
        $model->id = $mid;
        $model->user_id = $userid;
        $model->type_code = 1;
        $model->email = $email;
        $model->code = $code;
        $model->subject = $subject;
        $model->body = $content;
        $model->status = 0;
        $model->create_time = date('Y-m-d H:i:s');
        $model->update_time = date('Y-m-d H:i:s');
        $model->save();
        $result = $emailtool->send($email, '', $subject, $content, null, '');
        if ($result['success']) {
            
            EmailModel::update(['status' => 1, 'id' =>  $mid, 'update_time' => date('Y-m-d H:i:s')]);
            return ['code' => 0, 'msg' => '发送成功'];
        } else {
            $model->status = 2;
            EmailModel::update(['status' => 2, 'id' =>  $mid, 'update_time' => date('Y-m-d H:i:s')]);
            return ['code' => 1, 'msg' => '发送失败'];
        }
    }

    /**
     * 发送余额告警
     * balance 单位元
     */
    public function sendBalanceAlarm(string $email, int $userid, string $balance)
    {
        $expire_time = date('Y-m-d H:i:s', strtotime("-2 hours"));
        $result = $this->where(['email'=>$email,'type_code'=>2])->whereTime('create_time', '>', $expire_time)->find();
        if ($result) {
            return ['code' => 1, 'msg' => '发邮件太频繁了'];
        }
        $emailtool = new EmailTool();
        $content = "您的余额仅剩 " . $balance . " 元，请及时充值，以免影响使用。如果你不想再收到此类消息，可以登录88学子开放平台，取消余额预警功能。";
        
        $mid = uniqid("EMAIL", true);
        $subject = "余额告警";
        $model = new EmailModel();
        $model->id = $mid;
        $model->user_id = $userid;
        $model->type_code = 2;
        $model->email = $email;
        $model->code = "";
        $model->subject = $subject;
        $model->body = $content;
        $model->status = 0;
        $model->create_time = date('Y-m-d H:i:s');
        $model->update_time = date('Y-m-d H:i:s');
        $model->save();
        $result = $emailtool->send($email, '', $subject, $content, null, '');
        if ($result['success']) {
            
            EmailModel::update(['status' => 1, 'id' =>  $mid, 'update_time' => date('Y-m-d H:i:s')]);
            return ['code' => 0, 'msg' => '发送成功'];
        } else {
            $model->status = 2;
            EmailModel::update(['status' => 2, 'id' =>  $mid, 'update_time' => date('Y-m-d H:i:s')]);
            return ['code' => 1, 'msg' => '发送失败'];
        }
    }
}
