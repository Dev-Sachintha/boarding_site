<?php
// Add error display for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// The send_json_response function should be defined in db.php
require_once '../config/db.php';

// PHPMailer - Path to autoloader from api/contact/send_message.php is ../../vendor/autoload.php
// Assuming 'vendor' is in the project root 'boarding_site'
require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(["success" => false, "message" => "Invalid request method."], 405);
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->name) || empty($data->email) || empty($data->subject) || empty($data->message)) {
    send_json_response(["success" => false, "message" => "All fields are required."], 400);
}

$name = strip_tags(trim($data->name));
$email_from_user = filter_var(trim($data->email), FILTER_SANITIZE_EMAIL); // User's email
$subject = strip_tags(trim($data->subject));
// Preserve line breaks from textarea for HTML email and sanitize for security
$message_body = nl2br(htmlspecialchars(trim($data->message), ENT_QUOTES, 'UTF-8'));

if (!filter_var($email_from_user, FILTER_VALIDATE_EMAIL)) {
    send_json_response(["success" => false, "message" => "Invalid email format for sender."], 400);
}

$mail = new PHPMailer(true); // Passing `true` enables exceptions

try {
    //Server settings
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output for troubleshooting (VERY HELPFUL!)
    // Comment out for production

    $mail->isSMTP();                                      // Send using SMTP
    $mail->Host       = 'smtp.gmail.com';                 // Set the SMTP server to send through
    $mail->SMTPAuth   = true;                             // Enable SMTP authentication
    $mail->Username   = 'chamikara24sachintha@gmail.com';   // SMTP username (your Gmail address) <<< --- REPLACE THIS
    $mail->Password   = 'sbvl pqpq sxri yhnw';        // SMTP password (your Gmail App Password) <<< --- REPLACE THIS
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;    // Enable implicit TLS encryption (preferred)
    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // Alternative for SSL on port 465
    $mail->Port       = 587;                              // TCP port to connect to for STARTTLS
    // Use 465 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_SMTPS`

    //Recipients
    $mail->setFrom('your_actual_sending_gmail_address@gmail.com', 'UniBoard Contact Form'); // This is the "From" address the recipient sees
    // Best to use the same as your Username
    $mail->addAddress('chamikara24sachintha@gmail.com', 'Admin UniBoard');     // Add a recipient (your admin email) <<< --- VERIFY OR REPLACE THIS
    $mail->addReplyTo($email_from_user, $name); // So when admin replies, it goes to the user

    // Content
    $mail->isHTML(true); // Set email format to HTML to allow for nl2br effect
    $mail->Subject = "New UniBoard Contact: " . htmlspecialchars($subject); // Sanitize subject too

    $html_email_content = "<html><body>";
    $html_email_content .= "<h2>New Message from UniBoard Contact Form</h2>";
    $html_email_content .= "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>";
    $html_email_content .= "<p><strong>Email:</strong> " . htmlspecialchars($email_from_user) . "</p>";
    $html_email_content .= "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>";
    $html_email_content .= "<h3>Message:</h3>";
    $html_email_content .= "<p>" . $message_body . "</p>"; // message_body is already nl2br(htmlspecialchars())
    $html_email_content .= "</body></html>";
    $mail->Body    = $html_email_content;

    // Plain text version for non-HTML mail clients
    $plain_text_content = "You have received a new message from UniBoard:\n\n";
    $plain_text_content .= "Name: " . $name . "\n";
    $plain_text_content .= "Email: " . $email_from_user . "\n";
    $plain_text_content .= "Subject: " . $subject . "\n\n";
    $plain_text_content .= "Message:\n" . strip_tags(str_replace(["<br />", "<br>", "<br/>"], "\n", $message_body)) . "\n"; // Convert <br /> back to newlines for plain text
    $mail->AltBody = $plain_text_content;

    $mail->send();
    send_json_response(["success" => true, "message" => 'Thank you! Your message has been sent.']);
} catch (Exception $e) {
    // Log the detailed PHPMailer error and the general exception message
    error_log("PHPMailer Message could not be sent. Mailer Error: {$mail->ErrorInfo} | Exception: {$e->getMessage()}");
    // Send a more generic message to the client, but include MailerInfo for easier debugging if you choose
    send_json_response(["success" => false, "message" => "Message could not be sent. Please try again later. (Details: {$mail->ErrorInfo})"], 500);
}
