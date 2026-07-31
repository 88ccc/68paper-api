<?php

namespace app\tool;

use app\service\ConfigService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use think\facade\Log;

class EmailTool
{
    private $isInit = false;
    private $mail = null;
    private $codeTemplate = "";
    private $codeSubject = "";

    public function __construct()
    {
        $emailconfig = ConfigService::get("email");
        if (!empty($emailconfig)) {
            $this->mail = new PHPMailer(true);
            $this->mail->CharSet = "UTF-8";                     //设定邮件编码
            //Server settings
            //$this->mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            $this->mail->isSMTP();                                            //Send using SMTP
            $this->mail->Host       = $emailconfig['host'];                     //Set the SMTP server to send through
            $this->mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $this->mail->Username   = $emailconfig['user'];                     //SMTP username
            $this->mail->Password   = $emailconfig['password'];                               //SMTP password
            if($emailconfig['secure'] == 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption  
            } else if($emailconfig['secure'] == 'tls') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            //Enable implicit TLS encryption
            } else {
                $this->mail->SMTPSecure = "";            //Enable implicit TLS encryption
            }
            $this->mail->Port       = intval($emailconfig['port']);             //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
            $this->mail->setFrom($emailconfig['user'], $emailconfig['name']);
            $this->mail->addReplyTo($emailconfig['user'], ''); //回信时回信给谁
            $this->codeTemplate = $emailconfig['content'];
            $this->codeSubject = $emailconfig['subject'];
            $this->isInit = true;
        }
    }

    public function getCodeSubject()
    {
        return $this->codeSubject;
    }

    public function getCodeTemplate()
    {
        return $this->codeTemplate;
    }

    public function send(string $toemail,string $toname,string $mailsubject,string $mailbody,?string $attachment, $myname)
    {
        if (!$this->isInit) {
            return [
                'success' => false,
                'message' => '没有配置邮件服务',
                'result' => null
            ];
        }
        try {
            $this->mail->addAddress($toemail, $toname);
            //发送附件
            if (!empty($attachment)) {
                $arr_attach = explode(';', $attachment);
                if (count($arr_attach) > 0) {
                    for ($i = 0; $i < count($arr_attach); $i++) {
                        if (!empty($arr_attach[$i])) {
                            $this->mail->addAttachment($arr_attach[$i]);  // 添加附件

                        }
                    }
                }
            }
            $this->mail->isHTML(true);
            $this->mail->Subject = $mailsubject;
            $this->mail->Body = $mailbody; //正文
            $this->mail->AltBody = $mailbody;
            $this->mail->send();
            return [
                'success' => true,
                'message' => '邮件发送成功',
                'result' => null
            ];
        } catch (Exception $e) {
            Log::error('邮件发送失败: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '邮件发送失败: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }
}
