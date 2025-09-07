@echo off
REM === set project dir ===
cd /d "C:\xampp\htdocs\car-service-tracker"

REM === run the reminder job and append to a log ===
"C:\xampp\php\php.exe" app\cron\send_reminders.php >> "C:\xampp\htdocs\car-service-tracker\logs\reminders.log" 2>&1
