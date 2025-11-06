#!/usr/bin/env php
<?php
/**
 * Cron job script to check for books due in 24 hours and send notifications
 * Usage: php check_due_books.php
 */

// Define path to LMS root
define('LMS_ROOT', __DIR__ . '/..');

// Load the script that checks due books
require_once LMS_ROOT . '/scripts/check_due_books.php';