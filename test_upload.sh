#!/bin/bash
BASE_URL="http://localhost:8080"
COOKIE_JAR="cookies_upload.txt"
rm -f $COOKIE_JAR
EMAIL="upload$(date +%s)@test.com"
PASSWORD="password"

# 1. Register
echo "Registering $EMAIL..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL&password=$PASSWORD&register=1" "$BASE_URL/index.php" > /dev/null

# 2. Verify Email (manually via DB)
php -r "
\$db = new PDO('mysql:host=127.0.0.1;dbname=spamme', 'root', '');
\$stmt = \$db->prepare('UPDATE users SET verified = 1 WHERE email = ?');
\$stmt->execute(['$EMAIL']);
"

# 3. Login
echo "Logging in..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL&password=$PASSWORD&login=1" "$BASE_URL/index.php" > /dev/null

# 4. Upload File
echo "Uploading file..."
echo "dummy content" > dummy.jpg

# Print cookies for debug
echo "Cookie Jar Content:"
cat $COOKIE_JAR

OUTPUT=$(curl -v -c $COOKIE_JAR -b $COOKIE_JAR -F "amount=100" -F "method=Crypto" -F "submit_payment=1" -F "proof_file=@dummy.jpg" "$BASE_URL/buy_credits.php" 2>&1)

echo "--- CURL OUTPUT START ---"
echo "$OUTPUT"
echo "--- CURL OUTPUT END ---"

rm $COOKIE_JAR dummy.jpg
