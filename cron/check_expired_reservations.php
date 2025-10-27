#!/usr/bin/env php
<?php
/**
 * Cron job script to check and update expired reservations
 * Usage: php check_expired_reservations.php
 */

// Define path to LMS root
define('LMS_ROOT', __DIR__ . '/..');

// Load the script that checks expired reservations
require_once LMS_ROOT . '/scripts/check_expired_reservations.php';