<?php
// KITE Portal — SMTP Bridge
// Uses port 25 (unencrypted) — Railway blocks 465/587

define('SECRET_KEY',    'Kite@1234');
define('SMTP_HOST',     'smtp.rediffmailpro.com');
define('SMTP_USER',     'recognition@khazanajewellery.com');
define('SMTP_PASS',     'Reset@123');
define('SMTP_PORT',     25);
define('SMTP_FROM',     'recognition@khazanajewellery.com');
define('SMTP_FROMNAME', 'KITE Portal - Khazana Jewellery');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

foreach (['to', 'subject', 'body', 'password'] as $f) {
    if (empty($data[$f])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing: ' . $f]);
        exit;
    }
}

if ($data['password'] !== SECRET_KEY) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (!filter_var($data['to'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
    exit;
}

$to        = $data['to'];
$subject   = $data['subject'];
$body      = $data['body'];
$boundary  = md5(uniqid());
$date      = date('r');
$msgId     = '<' . uniqid() . '@khazanajewellery.com>';
$plainText = strip_tags(str_replace(
    ['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>'],
    "\n", $body
));

$rawEmail =
    "Date: $date\r\n" .
    "To: $to\r\n" .
    "From: " . SMTP_FROMNAME . " <" . SMTP_FROM . ">\r\n" .
    "Reply-To: " . SMTP_FROM . "\r\n" .
    "Message-ID: $msgId\r\n" .
    "Subject: $subject\r\n" .
    "MIME-Version: 1.0\r\n" .
    "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n" .
    "\r\n" .
    "--$boundary\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n\r\n" .
    $plainText . "\r\n" .
    "--$boundary\r\n" .
    "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
    $body . "\r\n" .
    "--$boundary--\r\n";

$tmpFile = tempnam(sys_get_temp_dir(), 'kite_');
file_put_contents($tmpFile, $rawEmail);
$fp = fopen($tmpFile, 'r');

$verbose = fopen('php://temp', 'w+');

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'smtp://' . SMTP_HOST . ':' . SMTP_PORT,
    CURLOPT_MAIL_FROM      => '<' . SMTP_FROM . '>',
    CURLOPT_MAIL_RCPT      => ['<' . $to . '>'],
    CURLOPT_USERNAME       => SMTP_USER,
    CURLOPT_PASSWORD       => SMTP_PASS,
    CURLOPT_USE_SSL        => CURLUSESSL_TRY,
    CURLOPT_READDATA       => $fp,
    CURLOPT_UPLOAD         => true,
    CURLOPT_INFILESIZE     => strlen($rawEmail),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_VERBOSE        => true,
    CURLOPT_STDERR         => $verbose,
]);

$result = curl_exec($ch);
$errno  = curl_errno($ch);
$errmsg = curl_error($ch);
curl_close($ch);
fclose($fp);
unlink($tmpFile);

rewind($verbose);
$verboseLog = substr(stream_get_contents($verbose), 0, 800);
fclose($verbose);

if ($errno) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'SMTP error: ' . $errmsg,
        'errno'   => $errno,
        'debug'   => $verboseLog
    ]);
} else {
    echo json_encode(['status' => 'success', 'message' => 'Email sent to ' . $to]);
}
