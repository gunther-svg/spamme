#!/bin/bash

BASE_URL="http://localhost:8080"
COOKIE_JAR="cookies.txt"
rm -f $COOKIE_JAR
EMAIL="user$(date +%s)@test.com"
PASSWORD="password123"

echo "Testing with Email: $EMAIL"

# Reset DB
php tests/reset_db.php

# 1. Register
echo "1. Registering..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL&password=$PASSWORD&register=1" "$BASE_URL/index.php" | grep "Registration successful" > /dev/null
if [ $? -eq 0 ]; then echo "Registration OK"; else echo "Registration Failed"; exit 1; fi

# 2. Login
echo "2. Logging in..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "email=$EMAIL&password=$PASSWORD&login=1" "$BASE_URL/index.php" -L | grep "Dashboard" > /dev/null
if [ $? -eq 0 ]; then echo "Login OK"; else echo "Login Failed"; exit 1; fi

# 3. Add SMTP Config
echo "3. Adding SMTP Config..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -d "host=smtp.mailtrap.io&port=2525&username=testuser&password=testpass&add_config=1" "$BASE_URL/smtp_configs.php" | grep "Config added" > /dev/null
if [ $? -eq 0 ]; then echo "SMTP Config OK"; else echo "SMTP Config Failed"; exit 1; fi

# 4. Request Payment
# Need to upload a dummy file
echo "dummy file" > dummy_proof.txt
echo "4. Requesting Payment..."
curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST -F "amount=10" -F "method=Crypto" -F "proof_file=@dummy_proof.txt" -F "submit_payment=1" "$BASE_URL/buy_credits.php" | grep "Payment request submitted" > /dev/null
if [ $? -eq 0 ]; then echo "Payment Request OK"; else echo "Payment Request Failed"; exit 1; fi

# 5. Admin Approve (CLI)
echo "5. Approving Payment (CLI)..."
# Simulate 'y' input to approve_payment.php
echo "y" | php src/approve_payment.php

# 6. Check Credits on Dashboard
echo "6. Checking Credits..."
PAGE_CONTENT=$(curl -s -c $COOKIE_JAR -b $COOKIE_JAR "$BASE_URL/dashboard.php")
# Extract credits using grep more robustly
CREDITS=$(echo "$PAGE_CONTENT" | grep -o "Credits: [0-9]*" | cut -d' ' -f2)

if [ -z "$CREDITS" ]; then
    echo "Could not extract credits. Page content excerpt:"
    echo "$PAGE_CONTENT" | grep "Credits"
    exit 1
fi

echo "Detected Credits: $CREDITS"

if [ "$CREDITS" -ge 1000 ]; then echo "Credits Updated OK"; else echo "Credits Check Failed"; exit 1; fi

# 7. Create Campaign
# We need to get the SMTP ID first. For simplicity, assume ID=1 (or last inserted)
echo "7. Creating Campaign..."
SMTP_ID=$(curl -s -c $COOKIE_JAR -b $COOKIE_JAR "$BASE_URL/create_campaign.php" | grep "value=" | head -n 1 | sed -E 's/.*value="([0-9]+)".*/\1/')
echo "Using SMTP ID: $SMTP_ID"

curl -s -c $COOKIE_JAR -b $COOKIE_JAR -X POST \
  -F "name=Test Campaign" \
  -F "smtp_config_id=$SMTP_ID" \
  -F "subject=Test Subject" \
  -F "body=<h1>Test Body</h1>" \
  -F "recipient_list=@recipients.csv" \
  -F "start_time=$(date +%Y-%m-%dT%H:%M)" \
  -F "send_rate=100" \
  "$BASE_URL/create_campaign.php" | grep "Campaign created" > /dev/null

if [ $? -eq 0 ]; then echo "Campaign Created OK"; else echo "Campaign Creation Failed"; exit 1; fi

# 8. Process Queue (CLI)
echo "8. Processing Queue..."
php src/process_queue.php

echo "Verification Complete!"
rm $COOKIE_JAR dummy_proof.txt
