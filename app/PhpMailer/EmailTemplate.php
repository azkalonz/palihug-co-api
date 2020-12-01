<?php
// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
namespace App\PhpMailer;

use App\Models\OrderDetail;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use App\PhpMailer\SMTPCon;
use Illuminate\Support\Facades\DB;

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
   public function OrderUpdateTemplate($user_email,$contact_subject,$msg_body,$order_detail,$order){
    if($this->is_connected_to_smtp){
        $order_table = '<table border="1" style="border-collapse: collapse">';
        $order_table .= '<tr>';
        $order_table .= '<th></th>';
        $order_table .= '<th>Product Name</th>';
        $order_table .= '<th>Quantity</th>';
        $order_table .= '<th>Price</th>';
        $order_table .= '<th>Subtotal</th>';
        $order_table .= '</tr>';
        foreach($order_detail as $detail){
            $order_table .= "<tr>";
            $product = json_decode($detail['product_meta']);
            $order_table .= "<td style=\"padding: 10px\"><img src=\"{$product->images[0]->src}\" width=\"200\"/></td>";
            $order_table .= "<td style=\"padding: 10px\">{$product->name}</td>";
            $order_table .= "<td style=\"padding: 10px\">{$detail['order_qty']}</td>";
            $order_table .= "<td style=\"padding: 10px\">Php {$product->price}</td>";
            $order_table .= "<td style=\"padding: 10px\">Php {$detail['order_total']}</td>";
            $order_table .= "</tr>";
        }
        $order_table .= '<tr>';
        $order_table .= '<td align="right" colspan="4" style="padding: 10px">Grand Total</td>';
        $order_table .= '<td style="padding: 10px"><b>Php '.$order['total'].'</b></td>';
        $order_table .= '</tr>';
        $order_table .= '</table>';
        if(sizeof($order_detail)){
            $msg_body .= "</br></br><h3>Order Details</h3>".$order_table."</br></br>";
        }
        $msg_body .= '<a href="http://localhost:3000/orders/'.$order['order_id'].'" style="padding: 13px; color: #fff; text-decoration: none; font-weight: bold; border-radius: 4px;background: #d92e45;display: inline-block;margin-top: 16px;">View Order</a>';
        $args = [
             "address"=>$user_email,
             "contact_subject"=>$contact_subject,
             "mail_content"=>$msg_body,
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