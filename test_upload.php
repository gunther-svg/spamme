<?php
// helpers for cookie management
$cookie_file = __DIR__ . '/cookies_upload_test.txt';
$base_url = 'http://localhost:8080';

// 1. Login to get a session
$ch = curl_init($base_url . '/index.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_POST, true);
// Use an existing user or register one? Let's assume the user from previous tests exists or register new one
$email = 'upload' . time() . '@test.com';
$password = 'password';
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'email' => $email,
    'password' => $password,
    'register' => 1
]);
$output = curl_exec($ch);
// Check if registered
if (strpos($output, 'Registration successful') === false) {
// maybe try login if already exists?
// actually let's just use the hardcoded user from verify_auth.sh if possible, but simpler to just register unique
}

// Login
curl_setopt($ch, CURLOPT_URL, $base_url . '/index.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'email' => $email,
    'password' => $password,
    'login' => 1
]);
$output = curl_exec($ch);

// Verify email manually in DB so we can login
$db = new PDO('mysql:host=127.0.0.1;dbname=spamme', 'root', '');
$stmt = $db->prepare("UPDATE users SET verified = 1 WHERE email = ?");
$stmt->execute([$email]);

// Login again to be sure (since first login might have failed due to verification)
$output = curl_exec($ch);

// 2. Upload File
curl_setopt($ch, CURLOPT_URL, $base_url . '/buy_credits.php');
curl_setopt($ch, CURLOPT_POST, true);
$cfile = new CURLFile(__DIR__ . '/dummy_proof.jpg', 'image/jpeg', 'dummy_proof.jpg');
$data = [
    'amount' => 100,
    'method' => 'Crypto',
    'submit_payment' => 1,
    'proof_file' => $cfile
];
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
$output = curl_exec($ch);

if (strpos($output, 'Payment request submitted successfully') !== false) {
    echo "Upload Test Passed\n";
}
else {
    echo "Upload Test Failed. Output snippet:\n";
    echo substr(strip_tags($output), 0, 500); // Show start of output
}

curl_close($ch);
if (file_exists($cookie_file))
    unlink($cookie_file);
?>