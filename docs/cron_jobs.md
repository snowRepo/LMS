# Cron Jobs for LMS

This document describes the cron jobs that should be set up for the Library Management System to function properly.

## Expired Reservations Check

The system needs to check for expired reservations daily to update the status and increment book availability counts.

### Script Location

```
/Applications/XAMPP/xamppfiles/htdocs/LMS/cron/check_expired_reservations.php
```

### Setting up the Cron Job

To set up the cron job to run daily at 2:00 AM, add the following line to your crontab:

```bash
0 2 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/LMS/cron/check_expired_reservations.php >> /Applications/XAMPP/xamppfiles/htdocs/LMS/logs/expired_reservations.log 2>&1
```

### Manual Execution

You can also run the script manually for testing:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/LMS
php cron/check_expired_reservations.php
```

### What the Script Does

1. Finds all pending reservations where the expiry date is in the past
2. Updates their status to 'expired'
3. Increments the available copies count for the associated books
4. Sends notifications to members about the expired reservations

This ensures that when reservations expire, the book availability is correctly updated.