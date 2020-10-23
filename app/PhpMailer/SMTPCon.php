<?php

namespace App\PhpMailer;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class SMTPCon
{
    public function __construct($enable_exceptions = false)
    {
        try {
            $mail = new PHPMailer($enable_exceptions);
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Host = 'smtp.gmail.com';
            $mail->Username = 'palihug.company@gmail.com';
            $mail->Password = '4244124M@rk';
            $mail->Port = 587;
            $mail->CharSet = "UTF-8";
            $this->mailer = $mail;
        } catch (Exception $e) {
            $this->mailer = null;
        }
    }
    public function sendHTMLContext($args)
    {
        if ($this->mailer) {
            $this->mailer->isHTML(true);
            $this->mailer->setFrom($this->mailer->Username, $args['contact_subject']);
            $this->mailer->addAddress($args['address']);
            $this->mailer->Subject = $args['contact_subject'];
            $this->mailer->Body = $args['mail_content'];
            $this->mailer->AltBody = strip_tags($args['mail_content']);
            if ($this->mailer->send()) {
                return [
                    "status" => true,
                    "message" => "Message sent",
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Unable to send message",
                ];
            }
        }
    }
}
