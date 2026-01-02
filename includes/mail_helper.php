<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require_once __DIR__ . '/../vendor/autoload.php';
/**
 * Sends an email using PHPMailer
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body HTML body content
 * @return bool|string Returns true on success, or error message on failure
 */
function sendCustomMail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // Server settings - ideally these should be constants from your config.php
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'webmaker040@gmail.com'; 
        $mail->Password   = 'rjwrakwognunkjjv'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Gadget Store');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // Plain text version

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mail Error: {$mail->ErrorInfo}";
    }
}