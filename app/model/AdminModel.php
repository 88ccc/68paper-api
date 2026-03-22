<?php

namespace app\model;

use think\Model;
use Firebase\JWT\JWT;
use think\facade\Log;

class AdminModel extends Model
{
    // 模型数据表
    protected $table = 'pt_admin';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'int',             //用户id 自增益
        'name'    => 'string', //昵称
        'email' => 'string', //邮箱
        'mobile'  => 'string', //手机号
        'avatar'  => 'string', //头像
        'pass'  => 'string', //密码
        'openid' => 'string', //微信openid
        'status' => 'int', //状态 0待激活,1正常,2冻结
        'regtime' => 'datetime', //注册时间
        'logintime' => 'datetime', //登录时间
        'status_time' => 'datetime', //状态时间
        'tips' => 'string',               //备注
    ];

    /**
     * 获取用户授权
     * @param int $userid 用户id
     * @param int $exp 过期时间单位小时
     */
    public static function getAuth($userid, $exp)
    {
        $user = AdminModel::where('id', $userid)->find();
        if (empty($user)) {
            return ['code' => 1, 'msg' => '用户不存在'];
        }
        $payload = [
            'iss' => 'papertools',
            'aud' => $user->mobile,
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 3600 * $exp,
            'data' => [
                'id' => $user->id,
                'name' => $user->name
            ]
        ];
        $jwt = JWT::encode($payload, $user->pass, 'HS256');
        return ['code' => 0, 'msg' => '获取成功', 'jwt' => $jwt];
    }
    /**
     * 获取用户头像
     */
    public  function getAvatar($domian = "")
    {
        if (empty($this->avatar)) {
            return  $domian . '/static/images/avatar/default.png';
        }
        if (strpos($this->avatar, 'http') === 0) {
            return $this->avatar;
        } else {
            return  $domian . $this->avatar;
        }
    }
}
