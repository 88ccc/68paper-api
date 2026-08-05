<?php

namespace app\model;

use think\Model;
use Firebase\JWT\JWT;
use think\facade\Log;
use think\facade\Queue;
use app\tool\QueueJob;
use think\facade\Config;

class UserModel extends Model
{
    // 模型数据表
    protected string $table = 'pt_user';
    // 模型数据表主键
    protected string $pk = 'id';
    // 设置json类型字段
    protected $json = ['alarm_method'];
    protected array $schema = [
        'id'          => 'int',             //用户id 自增益
        'tid' => 'int', //推荐人id
        'name'    => 'string', //真实姓名
        'email' => 'string', //邮箱
        'mobile'  => 'string', //手机号
        'avatar'  => 'string', //头像
        'pass'  => 'string', //密码
        'pay_type' => "string", //支付方式 alipay,wechat
        'wxpay' => 'int', //微信支付模板,默认用平台模板
        'alipay' => 'int', //支付宝支付模板,默认用平台模板
        'apikey' => 'string', //apikey
        'cardkey' => 'string', //检测卡key
        'api_status' => 'int', //api状态 0禁用 1正常
        'ip_list' => 'string', //ip白名单 多个ip用;隔开
        'openid' => 'string', //微信openid
        'alarm_threshold' => 'int', //告警阀值 单位分
        'alarm_method' => 'json', //告警方式用,json
        'status' => 'int', //状态 0待激活,1正常,2冻结,3已经注销
        'regtime' => 'datetime', //注册时间
        'logintime' => 'datetime', //登录时间
        'balance' => 'int', //账户余额
        'money' => 'int', //分销的总收入
        'tmoney' => 'int', //为邀请人贡献
        'account_type' => 'string', //默认收款账户类型
        'account' => 'string', //默认收款账户
        'points' => 'int', //积分
        'status_time' => 'datetime', //状态时间
        'tips' => 'string',               //备注
    ];

    /*
    * 新增用户
    */
    public static function  add(?string $mobile, ?string  $email, string $password, int $utid, ?string $openid): array
    {
        Log::write('add user', $mobile);
        if (empty($mobile) && empty($email)) {
            return ['code' => 1, 'msg' => '手机号或邮箱不能为空'];
        }

        if (empty($password)) {
            return ['code' => 1, 'msg' => '密码不能为空'];
        }
        $user = UserModel::where('mobile', $mobile)->find();
        if ((!empty($user)) && (!empty($mobile))) {

            return ['code' => 1, 'msg' => '手机号已存在'];
        }
        $user = UserModel::where('email', $email)->find();
        if ((!empty($user)) && (!empty($email))) {

            return ['code' => 1, 'msg' => '邮箱已存在'];
        }

        $now = date("Y-m-d H:i:s", time());
        $fp = fopen(app()->getRootPath() . 'public/lock/user_lock.txt', 'r');
        $tid = -1;
        $user = null;
        if ($fp !== false && flock($fp, LOCK_EX)) {
            $user = UserModel::where('mobile', "#####")->find();
            if (!empty($user)) {
                $tid = $user->id;
                UserModel::destroy($tid);
            }
            $user = new UserModel;
            if ($tid != -1) {
                $user->id = $tid;
            }
            flock($fp, LOCK_UN);
        } else {
            $user = new UserModel;
        }
        fclose($fp);
        //判断tid是否存在
        $tuser = UserModel::where('id', $utid)->find();
        if (empty($tuser)) {
            $utid = 0;
        }
        $user->tid = $utid;
        $user->openid = $openid;
        $user->mobile = $mobile;
        $user->email = $email;
        $user->pass = md5($password);
        $user->regtime = $now;
        $user->logintime = $now;
        $user->balance = 0;
        $user->status = 1;
        $user->status_time = $now;
        $user->api_status = 1;
        $user->money = 0;
        $user->tmoney = 0;
        $user->account_type = '';
        $user->account = '';
        $user->points = 0;
        //获取apikey
        $user->apikey = md5(uniqid());
        try {
            $ret = $user->save();
        } catch (\Exception $e) {
            return ['code' => 1, 'msg' => '注册失败，请重试'];
        }
        if (!$ret) {
            return ['code' => 2, 'msg' => '注册失败，请重试'];
        }

        $user = UserModel::where(['mobile' => $mobile, 'email' => $email])->find();
        if (empty($user)) {
            return ['code' => 3, 'msg' => '注册失败，请重试'];
        }

        return ['code' => 0, 'msg' => '注册成功', 'userid' => $user->id];
    }

    /**
     * 获取用户授权
     * @param int $userid 用户id
     * @param int $exp 过期时间单位小时
     */
    public static function getAuth(int $userid, int $exp): array
    {
        $user = UserModel::where('id', $userid)->find();
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
        $now = date("Y-m-d H:i:s", time());
        $user->logintime = $now;
        $user->save();
        return ['code' => 0, 'msg' => '获取成功', 'jwt' => $jwt];
    }
    /**
     * 获取用户头像
     */
    public  function getAvatarAttr(string|null $value): string
    {
        if (empty($value)) {
            return  Config::get('website.api_domain') . '/images/avatar/default.png';
        }
        if (strpos($value, 'http') === 0) {
            return $value;
        } else {
            return  Config::get('website.api_domain') . $value;
        }
    }

    public function getPayTypeAttr(string|null $value): string
    {
        if (empty($value)) {
            return "wechat,alipay";
        } else {
            return $value;
        }
    }


    /**
     * 增加用户余额（充值、退款、转账收入等）
     * @param int $amount 增加金额（单位：分，必须>0）
     * @param int $changeType 变更类型（1=收入，2=消费，3=退款，4=赠送，5=提现，6=其他）
     * @param string $businessNo 关联业务单号（可选）
     * @param int $operatorId 操作人ID（0=系统）
     * @param int $operatorType 操作人类型（1=系统，2=管理员，3=用户自身）
     * @param string $remark 备注（可选）
     * @return bool
     * @throws \Exception
     */
    public function increaseBalance(
        int $amount,
        int $changeType,
        string $businessNo = '',
        string $remark = '',
        int $operatorId = 0,
        int $operatorType = 1
    ): bool {
        // 1. 前置参数校验
        if ($amount <= 0) {
            Log::error('增加金额必须大于0（单位：分）');
            return false;
        }

        $allowTypes = [1, 3, 4, 6];
        if (!in_array($changeType, $allowTypes)) {
            Log::error('不支持的余额增加类型：' . $changeType);
            return false;
        }
        if (!in_array($operatorType, [1, 2, 3])) {
            Log::error('非法操作人类型');
            return false;
        }

        // 2. 开启数据库事务
        $this->startTrans();
        try {
            // 3. 原子更新余额（仅正常状态用户可操作）
            $updateRows = $this->where([
                'id'     => $this->id,
            ])->inc('balance', $amount)->update();

            // 校验更新结果（0行更新=用户不存在/状态异常）
            if ($updateRows === 0) {
                throw new \RuntimeException('用户不存在或状态异常，余额增加失败');
            }

            // 4. 获取更新后的余额（事务内查询，保证一致性）
            $updatedUser = $this->where('id', $this->id)->field('balance')->findOrFail();
            $afterBalance = $updatedUser->balance;
            $beforeBalance = $afterBalance - $amount;

            // 5. 记录余额变更日志
            $log = new BalanceModel();
            $logResult = $log->save([
                'id'              => uniqid('bal_', true), // 生成唯一日志ID（或用UUID）
                'user_id'         => $this->id,
                'before_balance'  => $beforeBalance,
                'change_amount'   => $amount, // 增加记正数
                'after_balance'   => $afterBalance,
                'change_type'     => $changeType,
                'business_no'     => $businessNo,
                'operator_id'     => $operatorId,
                'operator_type'   => $operatorType,
                'remark'          => $remark,
                'create_time'     => date('Y-m-d H:i:s'),
            ]);

            if (!$logResult) {
                throw new \RuntimeException('余额变更日志记录失败');
            }

            // 6. 提交事务
            $this->commit();
            return true;
        } catch (\Exception $e) {
            // 7. 异常回滚 + 日志记录
            $this->rollback();
            Log::error(sprintf(
                '用户余额增加失败：用户ID=%d，金额=%d，错误：%s',
                $this->id,
                $amount,
                $e->getMessage()
            ));
            return false;
        }
    }


    /**
     * 减少用户余额（消费、提现、转账支出等）
     * @param int $amount 减少金额（单位：分，必须>0）
     * @param int $changeType 变更类型（1=充值，2=消费，3=退款，4=赠送，5=提现，6=其他）
     * @param string $businessNo 关联业务单号（可选）
     * @param int $operatorId 操作人ID（0=系统）
     * @param int $operatorType 操作人类型（1=系统，2=管理员，3=用户自身）
     * @param string $remark 备注（可选）
     * @return bool
     * @throws \Exception
     */
    public function decreaseBalance(
        int $amount,
        int $changeType,
        string $businessNo = '',
        string $remark = '',
        int $operatorId = 0,
        int $operatorType = 1
    ): bool {
        // 1. 前置参数校验
        if ($amount <= 0) {
            Log::error('减少金额必须大于0（单位：分）');
            return false;
        }
        $allowTypes = [2, 3, 5, 6];
        if (!in_array($changeType, $allowTypes)) {
            Log::error('不支持的余额减少类型：' . $changeType);
            return false;
        }
        if (!in_array($operatorType, [1, 2, 3])) {
            Log::error('非法操作人类型');
            return false;
        }

        // 2. 开启数据库事务
        $this->startTrans();
        try {
            // 3. 原子更新余额（校验：用户正常 + 余额充足）
            $updateRows = $this->where([
                'id'      => $this->id,
            ])->dec('balance', $amount)->update();

            if ($updateRows === 0) {
                throw new \RuntimeException('用户不存在、状态异常或余额不足，余额减少失败');
            }

            // 4. 获取更新后的余额
            $updatedUser = $this->where('id', $this->id)->field('balance')->find();
            $afterBalance = $updatedUser->balance;
            $beforeBalance = $afterBalance + $amount;

            // 5. 记录余额变更日志（减少记负数，便于统计）
            $log = new BalanceModel();
            $logResult = $log->save([
                'id'              => uniqid('bal_', true),
                'user_id'         => $this->id,
                'before_balance'  => $beforeBalance,
                'change_amount'   => -$amount, // 减少记负数
                'after_balance'   => $afterBalance,
                'change_type'     => $changeType,
                'business_no'     => $businessNo,
                'operator_id'     => $operatorId,
                'operator_type'   => $operatorType,
                'remark'          => $remark,
                'create_time'     => date('Y-m-d H:i:s'),
            ]);

            if (!$logResult) {
                throw new \RuntimeException('余额变更日志记录失败');
            }
            // 6. 提交事务
            $this->commit();
            
            return true;
        } catch (\Exception $e) {
            // 7. 异常回滚 + 日志记录
            $this->rollback();
            Log::error(sprintf(
                '用户余额减少失败：用户ID=%d，金额=%d，错误：%s',
                $this->id,
                $amount,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * 增加用户积分（充值、退款、转账收入等）
     * @param int $amount 增加金额（单位：分，必须>0）
     * @param int $changeType 变更类型（1=充值，2=消费，3=退款，4=赠送，5=提现，6=其他）
     * @param string $businessNo 关联业务单号（可选）
     * @param int $operatorId 操作人ID（0=系统）
     * @param int $operatorType 操作人类型（1=系统，2=管理员，3=用户自身）
     * @param string $remark 备注（可选）
     * @return bool
     * @throws \Exception
     */
    public function increasePoints(
        int $amount,
        int $changeType,
        string $businessNo = '',
        string $remark = '',
        int $operatorId = 0,
        int $operatorType = 1
    ): bool {
        // 1. 前置参数校验
        if ($amount <= 0) {
            Log::error('增加金额必须大于0（单位：分）');
            return false;
        }

        $allowTypes = [1, 3, 4, 6];
        if (!in_array($changeType, $allowTypes)) {
            Log::error('不支持的余额增加类型：' . $changeType);
            return false;
        }
        if (!in_array($operatorType, [1, 2, 3])) {
            Log::error('非法操作人类型');
            return false;
        }

        // 2. 开启数据库事务
        $this->startTrans();
        try {
            // 3. 原子更新余额（仅正常状态用户可操作）
            $updateRows = $this->where([
                'id'     => $this->id,
            ])->inc('points', $amount)->update();

            // 校验更新结果（0行更新=用户不存在/状态异常）
            if ($updateRows === 0) {
                throw new \RuntimeException('用户不存在或状态异常，余额增加失败');
            }

            // 4. 获取更新后的余额（事务内查询，保证一致性）
            $updatedUser = $this->where('id', $this->id)->field('points')->findOrFail();
            $afterBalance = $updatedUser->points;
            $beforeBalance = $afterBalance - $amount;

            // 5. 记录余额变更日志
            $log = new PointsModel();
            $logResult = $log->save([
                'id'              => uniqid('bal_', true), // 生成唯一日志ID（或用UUID）
                'user_id'         => $this->id,
                'before_balance'  => $beforeBalance,
                'change_amount'   => $amount, // 增加记正数
                'after_balance'   => $afterBalance,
                'change_type'     => $changeType,
                'business_no'     => $businessNo,
                'operator_id'     => $operatorId,
                'operator_type'   => $operatorType,
                'remark'          => $remark,
                'create_time'     => date('Y-m-d H:i:s'),
            ]);

            if (!$logResult) {
                throw new \RuntimeException('余额变更日志记录失败');
            }

            // 6. 提交事务
            $this->commit();
            return true;
        } catch (\Exception $e) {
            // 7. 异常回滚 + 日志记录
            $this->rollback();
            Log::error(sprintf(
                '用户积分增加失败：用户ID=%d，金额=%d，错误：%s',
                $this->id,
                $amount,
                $e->getMessage()
            ));
            return false;
        }
    }


    /**
     * 减少用户积分（消费、提现、转账支出等）
     * @param int $amount 减少金额（单位：分，必须>0）
     * @param int $changeType 变更类型（1=充值，2=消费，3=退款，4=赠送，5=提现，6=其他）
     * @param string $businessNo 关联业务单号（可选）
     * @param int $operatorId 操作人ID（0=系统）
     * @param int $operatorType 操作人类型（1=系统，2=管理员，3=用户自身）
     * @param string $remark 备注（可选）
     * @return bool
     * @throws \Exception
     */
    public function decreasePoints(
        int $amount,
        int $changeType,
        string $businessNo = '',
        string $remark = '',
        int $operatorId = 0,
        int $operatorType = 1
    ): bool {
        // 1. 前置参数校验
        if ($amount <= 0) {
            Log::error('减少金额必须大于0（单位：分）');
            return false;
        }
        $allowTypes = [2, 3, 5, 6];
        if (!in_array($changeType, $allowTypes)) {
            Log::error('不支持的余额减少类型：' . $changeType);
            return false;
        }
        if (!in_array($operatorType, [1, 2, 3])) {
            Log::error('非法操作人类型');
            return false;
        }

        // 2. 开启数据库事务
        $this->startTrans();
        try {
            // 3. 原子更新余额（校验：用户正常 + 余额充足）
            $updateRows = $this->where([
                'id'      => $this->id,
            ])->dec('points', $amount)->update();

            if ($updateRows === 0) {
                throw new \RuntimeException('用户不存在、状态异常或余额不足，余额减少失败');
            }

            // 4. 获取更新后的余额
            $updatedUser = $this->where('id', $this->id)->field('points')->find();
            $afterBalance = $updatedUser->points;
            $beforeBalance = $afterBalance + $amount;

            // 5. 记录余额变更日志（减少记负数，便于统计）
            $log = new PointsModel();
            $logResult = $log->save([
                'id'              => uniqid('bal_', true),
                'user_id'         => $this->id,
                'before_balance'  => $beforeBalance,
                'change_amount'   => -$amount, // 减少记负数
                'after_balance'   => $afterBalance,
                'change_type'     => $changeType,
                'business_no'     => $businessNo,
                'operator_id'     => $operatorId,
                'operator_type'   => $operatorType,
                'remark'          => $remark,
                'create_time'     => date('Y-m-d H:i:s'),
            ]);

            if (!$logResult) {
                throw new \RuntimeException('积分变更日志记录失败');
            }
            // 6. 提交事务
            $this->commit();
            
            if ($this->alarm_threshold > 0 && (!empty($this->alarm_method))) {
                if (($beforeBalance > $this->alarm_threshold) && ($afterBalance <= $this->alarm_threshold)) {
                    //余额告警
                    $data = [
                        'job' => 'send_submsg',
                        'userid' => $this->id,
                        'event' => "points"
                    ];
                    Queue::push(QueueJob::class,  $data,  'default');
                }
            }
            return true;
        } catch (\Exception $e) {
            // 7. 异常回滚 + 日志记录
            $this->rollback();
            Log::error(sprintf(
                '用户积分减少失败：用户ID=%d，金额=%d，错误：%s',
                $this->id,
                $amount,
                $e->getMessage()
            ));
            return false;
        }
    }
}
