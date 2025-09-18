@echo off
REM === set project dir ===
cd /d "C:\xampp\htdocs\car-service-tracker"

REM --- BulkSMSBD credentials ---
set BULKSMSBD_API_KEY=4coS9g4aVf3ZVe4aCBny
set BULKSMSBD_SENDER_ID=8809617629068

REM === run the reminder job and append to a log ===
"C:\xampp\php\php.exe" app\cron\send_reminders.php >> "C:\xampp\htdocs\car-service-tracker\logs\reminders.log" 2>&1
