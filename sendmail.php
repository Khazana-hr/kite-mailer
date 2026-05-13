<?php
// ═══════════════════════════════════════════════════════════════════════════
// KITE Portal — SMTP Bridge (sendmail.php)
// Deployed on Railway.app (free tier) as a PHP service.
//
// Folder structure (on Railway / GitHub repo):
//   kite-mailer/
//   ├── sendmail.php     ← this file
//   └── composer.json    ← tells Railway to install PHPMailer automatically
//
// PHPMailer is installed automatically by Railway via Composer.
// No manual downloading needed.
// ═══════════════════════════════════════════════════════════════════════════

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composer autoloader (used on Railway / any hosted environment with composer install)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
// Manual src/ fallback (used on shared hosting without Composer)
} else {
    require __DIR__ . '/src/PHPMailer.php';
    require __DIR__ . '/src/SMTP.php';
    require __DIR__ . '/src/Exception.php';
}

// ── CONFIG — fill these in ──────────────────────────────────────────────────
define('SECRET_KEY',    'Kite@1234');                      // ← change this to any strong password (must match Code.gs)
define('SMTP_HOST',     'smtp.rediffmailpro.com');              // Rediffmail Pro SMTP server
define('SMTP_USER',     'recognition@khazanajewellery.com');    // SMTP login / sender address
define('SMTP_PASS',     'Reset@123');       // ← replace with your actual Rediffmail Pro password
define('SMTP_PORT',     587);                                   // 587 = STARTTLS
define('SMTP_SECURE',   'tls');                                 // 'tls' for port 587 (STARTTLS)
define('SMTP_FROM',     'recognition@khazanajewellery.com');    // from address
define('SMTP_FROMNAME', 'KITE Portal — Khazana Jewellery');
// ───────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Read and decode JSON body
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body']);
    exit;
}

// Validate required fields
$required = ['to', 'subject', 'body', 'password'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required field: ' . $field]);
        exit;
    }
}

// Validate secret key
if ($data['password'] !== SECRET_KEY) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Validate email address
if (!filter_var($data['to'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid recipient email address']);
    exit;
}

// ── Send via PHPMailer ──────────────────────────────────────────────────────
try {
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // Sender & recipient
    $mail->setFrom(SMTP_FROM, SMTP_FROMNAME);
    $mail->addAddress($data['to']);

    // Reply-To (optional — same as sender by default)
    $mail->addReplyTo(SMTP_FROM, SMTP_FROMNAME);

    // Content — body is treated as HTML; plain text auto-generated
    $mail->isHTML(true);
    $mail->Subject = $data['subject'];
    $mail->Body    = $data['body'];
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>'], "\n", $data['body']));

    // Optional file attachment from a public URL
    if (!empty($data['attachmentUrl']) && !empty($data['attachmentName'])) {
        $fileContent = @file_get_contents($data['attachmentUrl']);
        if ($fileContent !== false) {
            $mail->addStringAttachment($fileContent, $data['attachmentName']);
        }
    }

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'Email sent to ' . $data['to']]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Mailer error: ' . $mail->ErrorInfo
    ]);
}
