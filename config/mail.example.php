<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../PHPMailer/src/Exception.php';
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';

function sendEmail($to, $subject, $message)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;

        // Replace these placeholders with your real SMTP credentials locally.
        $mail->Username = "your-email@example.com";
        $mail->Password = "your-app-password";

        $mail->SMTPSecure = "tls";
        $mail->Port = 587;

        $mail->setFrom("your-email@example.com", "CDF Management System");
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
