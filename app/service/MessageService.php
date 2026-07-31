<?php
// 自定义工具类 ConfigService.php
namespace app\service;


use app\model\UserModel;
use app\model\EmailModel;
use think\facade\Log;

class MessageService
{
    private array $event = []; //txsucc,txfail,points
    private array $method = []; //email,sms,wechat
    private int $userid = 0;
    private bool $enable = false;
    private string $email = "";
    private string $name = "用户";
    private string $mobile = "";
    private string $openid = "";
    public  function __construct(int $userid)
    {

        if ($userid > 0) {
            $this->userid = $userid;
            $user =  UserModel::where("id", $userid)->find();
            $this->email = $user->email;
            if (!empty($user->name)) {
                $this->name = $user->name;
            }
            $this->mobile = $user->mobile;
            $this->openid = $user->openid;
            if (!empty($user)) {
                if (!empty($user->alarm_method)) {
                    $event = "";
                    $method = "";
                    if (is_array($user->alarm_method)) {
                        $event = $user->alarm_method['event'];
                        $method = $user->alarm_method['method'];
                    } else {
                        $event = $user->alarm_method->event;
                        $method = $user->alarm_method->method;
                    }
                    $this->event = explode(",", $event);
                    $this->method = explode(",", $method);
                }
            }
        }
        $config = ConfigService::get("function");
        if (!empty($config)) {
            $this->enable = strtolower($config['msgsub']) == 'true';
        }
    }

    public function setMethod(string $event, string $method)
    {
        if ($this->userid > 0) {
            UserModel::where("id", $this->userid)->update([
                'alarm_method' => [
                    'event' => $event,
                    'method' => $method,
                ]
            ]);
        }
    }

    public function withdrawFail()
    {
        if ($this->enable == false) {
            return;
        }
        if (!in_array("txfail", $this->event)) {
            return;
        }
        if (in_array("email", $this->method)) {
            if (!empty($this->email)) {
                $this->withdrawFailtoEmail();
            }
        }
        if (in_array('wechat', $this->method)) {
            if (!empty($this->openid)) {
                $this->withdrawFailtoWechat();
            }
        }
    }

    public function withdrawSucc()
    {
        if ($this->enable == false) {
            return;
        }
        if (!in_array("txsucc", $this->event)) {
            return;
        }
        if (in_array("email", $this->method)) {
            if (!empty($this->email)) {
                $this->withdrawSucctoEmail();
            }
        }
        if (in_array('wechat', $this->method)) {
            if (!empty($this->openid)) {
                $this->withdrawSucctoWechat();
            }
        }
    }

    public function pointsAlarm(float $value)
    {
        if ($this->enable == false) {
            return;
        }
        if (!in_array("points", $this->event)) {
            return;
        }
        if (in_array("email", $this->method)) {
            if (!empty($this->email)) {
                $this->pointsAlarmtoEmail($value);
            }
        }
        if (in_array('wechat', $this->method)) {
            if (!empty($this->openid)) {
                $this->pointsAlarmtoWechat($value);
            }
        }
    }



    public function withdrawFailtoEmail()
    {
        (new EmailModel())->sendWithdrawNotice($this->email, $this->userid, false);
    }
    public function withdrawFailtoWechat() {}
    public function withdrawSucctoEmail()
    {
        (new EmailModel())->sendWithdrawNotice($this->email, $this->userid, true);
    }
    public function withdrawSucctoWechat() {}
    public function pointsAlarmtoEmail(float $value)
    {
        (new EmailModel())->sendBalanceAlarm($this->email, $this->userid, strval($value));
    }
    public function pointsAlarmtoWechat(float $value) {}
}
