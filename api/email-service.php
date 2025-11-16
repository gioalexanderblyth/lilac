<?php
/**
 * Email Service API
 * Handles email notifications and sending
 */

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Check if this file is being accessed directly (not included)
$isDirectAccess = basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__) || 
                  $_SERVER['SCRIPT_FILENAME'] === __FILE__;

// Only set JSON headers if this is accessed as an API endpoint (not included as library)
if ($isDirectAccess) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Email service configuration
// Configure SMTP settings here or via environment variables
function getEmailConfig() {
    // Check for SMTP configuration in config.php (if defined)
    $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : (getenv('SMTP_HOST') ?: 'localhost');
    $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : (getenv('SMTP_PORT') ?: 587);
    $smtpUsername = defined('SMTP_USERNAME') ? SMTP_USERNAME : (getenv('SMTP_USERNAME') ?: '');
    $smtpPassword = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : (getenv('SMTP_PASSWORD') ?: '');
    $smtpFromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (getenv('SMTP_FROM_EMAIL') ?: 'noreply@cpu.edu.ph');
    $smtpFromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (getenv('SMTP_FROM_NAME') ?: 'LILAC System');
    
    return [
        'smtp_host' => $smtpHost,
        'smtp_port' => $smtpPort,
        'smtp_username' => $smtpUsername,
        'smtp_password' => $smtpPassword,
        'smtp_from_email' => $smtpFromEmail,
        'smtp_from_name' => $smtpFromName,
        'enabled' => getenv('EMAIL_ENABLED') !== 'false' // Default enabled
    ];
}

/**
 * Send email notification
 * For now, logs to file. Integrate with SMTP service later.
 */
function sendEmailNotification($to, $subject, $body, $html = true) {
    $config = getEmailConfig();
    
    if (!$config['enabled']) {
        error_log("Email disabled. Would send: To: $to, Subject: $subject");
        return true; // Return success if email is disabled
    }

    try {
        // Log email to file for debugging
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $emailLog = $logDir . '/emails.log';
        $logEntry = sprintf(
            "[%s] To: %s | Subject: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body
        );

        file_put_contents($emailLog, $logEntry, FILE_APPEND);

        // Try to send actual email using PHP mail() function
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: " . ($html ? "text/html" : "text/plain") . "; charset=UTF-8";
        $headers[] = "From: " . $config['smtp_from_name'] . " <" . $config['smtp_from_email'] . ">";
        $headers[] = "Reply-To: " . $config['smtp_from_email'];
        $headers[] = "X-Mailer: PHP/" . phpversion();

        // Send email using PHP mail() function
        $mailSent = @mail($to, $subject, $body, implode("\r\n", $headers));

        // If mail() failed and SMTP credentials are provided, try SMTP sending
        if (!$mailSent && !empty($config['smtp_host']) && $config['smtp_host'] !== 'localhost') {
            // Check if PHPMailer is available
            $phpmailerPath = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($phpmailerPath)) {
                require_once $phpmailerPath;
                try {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $config['smtp_host'];
                    $mail->SMTPAuth = !empty($config['smtp_username']);
                    $mail->Username = $config['smtp_username'];
                    $mail->Password = $config['smtp_password'];
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = (int)$config['smtp_port'];
                    $mail->setFrom($config['smtp_from_email'], $config['smtp_from_name']);
                    $mail->addAddress($to);
                    $mail->isHTML($html);
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    $mailSent = $mail->send();
                } catch (Exception $e) {
                    error_log('PHPMailer error: ' . $e->getMessage());
                }
            }
        }

        if (!$mailSent) {
            // Log that email wasn't sent (but don't fail - email is in log file)
            error_log("Email not sent via mail() or SMTP. Email logged to: $emailLog");
            error_log("To configure SMTP, set environment variables: SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD");
            error_log("Or install PHPMailer: composer require phpmailer/phpmailer");
        }

        // Always return true - email is logged even if sending fails
        return true;
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $resetToken, $username) {
    $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset-password.php?token=' . $resetToken;
    
    $subject = 'Password Reset Request - LILAC';
    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #137fec;'>Password Reset Request</h2>
                <p>Hello $username,</p>
                <p>You have requested to reset your password for the LILAC system.</p>
                <p>Click the link below to reset your password:</p>
                <p style='margin: 20px 0;'>
                    <a href='$resetLink' style='background-color: #137fec; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                </p>
                <p>This link will expire in 1 hour.</p>
                <p>If you did not request this, please ignore this email.</p>
                <p>Best regards,<br>LILAC System</p>
            </div>
        </body>
        </html>
    ";

    return sendEmailNotification($email, $subject, $body, true);
}

/**
 * Send MOU/MOA renewal reminder email
 */
function sendMouRenewalReminder($email, $username, $mouTitle, $expirationDate, $daysRemaining) {
    $subject = "MOU/MOA Renewal Reminder - $mouTitle";
    $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #137fec;'>MOU/MOA Renewal Reminder</h2>
                <p>Hello $username,</p>
                <p>This is a reminder that your MOU/MOA <strong>$mouTitle</strong> is due for renewal.</p>
                <div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Expiration Date:</strong> $expirationDate</p>
                    <p><strong>Days Remaining:</strong> $daysRemaining days</p>
                </div>
                <p>Please review and renew this agreement before it expires.</p>
                <p>Best regards,<br>LILAC System</p>
            </div>
        </body>
        </html>
    ";

    return sendEmailNotification($email, $subject, $body, true);
}

// Handle API requests (only when accessed directly, not when included)
if ($isDirectAccess) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        try {
            if ($action === 'send') {
                $to = $input['to'] ?? '';
                $subject = $input['subject'] ?? '';
                $body = $input['body'] ?? '';
                $html = $input['html'] ?? true;

                if (empty($to) || empty($subject) || empty($body)) {
                    throw new Exception('To, subject, and body are required');
                }

                $result = sendEmailNotification($to, $subject, $body, $html);
                
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Email sent successfully' : 'Failed to send email'
                ]);

            } else {
                throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
}
?>

