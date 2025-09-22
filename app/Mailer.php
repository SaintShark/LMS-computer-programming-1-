<?php 
class Mailer{
    /**
     * Sends an email using PHPMailer.
     *
     * @param string $to The recipient's email address.
     * @param string $subject The subject of the email.
     * @param string $message The body of the email.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public static function sendEmail($to, $subject, $message){
        $mailer = new PHPMailer\PHPMailer\PHPMailer();
        $mailer->CharSet = "utf-8"; 
        $mailer->IsSMTP();
        $mailer->SMTPDebug = 0; // Set to 2 for debugging output
        $mailer->SMTPAuth = true;
        $mailer->SMTPSecure = 'tls';
        $mailer->Host = 'smtp.gmail.com';    
        $mailer->Port = 587;    
        $mailer->Username = 'orderflow.dev@gmail.com';
        $mailer->Password = 'orderflow123';        

        $mailer->From = 'orderflow.dev@gmail.com';
        $mailer->FromName = 'OrderFlow';
        $mailer->Subject = $subject;
        $mailer->Body = $message;
        $mailer->AltBody = ""; // Plain text body for non-HTML mail clients
        $mailer->AddAddress($to);
        $mailer->AddReplyTo($to);
        // $mailer->AddCC($to); // Uncomment to add CC
        // $mailer->AddBCC($to); // Uncomment to add BCC
        // $mailer->AddAddress($message); // This line seems incorrect, $message should be the email body, not an address

        if($mailer->Send()){
            return true;
        }else{
            return false;
        }
    }
}
?>