<?php
// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
namespace App\PhpMailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use App\PhpMailer\SMTPCon;

class EmailTemplate extends SMTPCon { 
    public function __construct($enable_exceptions){
        parent::__construct($enable_exceptions);
        if(!$this->mailer){
            $this->is_connected_to_smtp = false;
        } else {
            $this->is_connected_to_smtp = true;
        }
    }
   public function OTPVerificationTemplate($user_email,$otp_pin){
       if($this->is_connected_to_smtp){
           $contact_subject = "Your OTP Pin is ".$otp_pin;
           $args = [
                "address"=>$user_email,
                "contact_subject"=>"Palihug.co OTP",
                "mail_content"=>"<h2>Your OTP code is </h2><p>{$otp_pin}</p>",
           ];
           return $this->sendHTMLContext($args);
       } else {
           return [
               "status"=>false,
               "message"=>"Unable to connect to SMTP server"
           ];
       }
   }
}