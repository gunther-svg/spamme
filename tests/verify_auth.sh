#!/bin/bash

BASE_URL="http://localhost:8080"
COOKIE_JAR="cookies_auth.txt"
rm -f $COOKIE_JAR
EMAIL="auth$(date +%s)@test.com"
PASSWORD="password123"

echo "Testing Auth Flows with Email: $EMAIL"

# Reset DB to ensure clean state
php tests/reset_db.php > /dev/null

# 1. Register
echo "1. Registering..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL&password=$PASSWORD&register=1" "$BASE_URL/index.php" | grep "Registration successful" > /dev/null
if [ $? -eq 0 ]; then echo "Registration OK"; else echo "Registration Failed"; exit 1; fi

# 2. Try Login (Should Fail - Unverified)
echo "2. Login before verification..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL&password=$PASSWORD&login=1" "$BASE_URL/index.php" | grep "Please verify your email" > /dev/null
if [ $? -eq 0 ]; then echo "Login Blocked OK"; else echo "Login Blocked Failed"; exit 1; fi

# 3. Get Verification Token from DB
echo "3. Fetching Verification Token..."
TOKEN=$(php -r "
\$db = new PDO('mysql:host=127.0.0.1;dbname=spamme', 'root', '');
\$stmt = \$db->prepare('SELECT verification_token FROM users WHERE email = ?');
\$stmt->execute(['$EMAIL']);
echo \$stmt->fetchColumn();
")

if [ -z "$TOKEN" ]; then echo "Token Fetch Failed"; exit 1; fi
echo "Token: $TOKEN"

# 4. Verify Email
echo "4. Verifying Email..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR "$BASE_URL/verify.php?token=$TOKEN" | grep "Email verified successfully" > /dev/null
if [ $? -eq 0 ]; then echo "Verification OK"; else echo "Verification Failed"; exit 1; fi

# 5. Login (Should Success)
echo "5. Login after verification..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL&password=$PASSWORD&login=1" "$BASE_URL/index.php" -L | grep "Dashboard" > /dev/null
if [ $? -eq 0 ]; then echo "Login OK"; else echo "Login Failed"; exit 1; fi

# 6. Forgot Password Request
echo "6. Requesting Password Reset..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL" "$BASE_URL/forgot_password.php" | grep "reset link has been sent" > /dev/null
if [ $? -eq 0 ]; then echo "Reset Request OK"; else echo "Reset Request Failed"; exit 1; fi

# 7. Get Reset Token
echo "7. Fetching Reset Token..."
RESET_TOKEN=$(php -r "
\$db = new PDO('mysql:host=127.0.0.1;dbname=spamme', 'root', '');
\$stmt = \$db->prepare('SELECT reset_token FROM users WHERE email = ?');
\$stmt->execute(['$EMAIL']);
echo \$stmt->fetchColumn();
")

if [ -z "$RESET_TOKEN" ]; then echo "Reset Token Fetch Failed"; exit 1; fi
echo "Reset Token: $RESET_TOKEN"

# 8. Reset Password
echo "8. Resetting Password..."
NEW_PASSWORD="newpassword456"
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "token=$RESET_TOKEN&password=$NEW_PASSWORD" "$BASE_URL/reset_password.php?token=$RESET_TOKEN" | grep "Password has been reset" > /dev/null
if [ $? -eq 0 ]; then echo "Password Reset OK"; else echo "Password Reset Failed"; exit 1; fi

# 9. Login with New Password
echo "9. Login with New Password..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL&password=$NEW_PASSWORD&login=1" "$BASE_URL/index.php" -L | grep "Dashboard" > /dev/null
if [ $? -eq 0 ]; then echo "New Password Login OK"; else echo "New Password Login Failed"; exit 1; fi

echo "Auth Verification Complete!"
rm $COOKIE_JAR
