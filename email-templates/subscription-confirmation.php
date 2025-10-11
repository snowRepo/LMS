<?php
/**
 * Subscription Confirmation Email Template
 * Variables available:
 * - $user_name: User's full name
 * - $library_name: Library name
 * - $plan_name: Subscription plan name
 * - $amount: Payment amount
 * - $reference: Payment reference
 * - $expires_date: Subscription expiration date
 * - $app_name: Application name
 * - $app_url: Application URL
 */
// Ensure $app_name has a default value
if (!isset($app_name)) {
    $app_name = defined('APP_NAME') ? APP_NAME : 'LMS - Library Management System';
}
if (!isset($app_url)) {
    $app_url = defined('APP_URL') ? APP_URL : 'http://localhost/LMS';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Subscription Confirmation - <?php echo htmlspecialchars($app_name); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: #3498DB; color: white; padding: 30px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 28px;">Subscription Confirmation</h1>
                            <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">Your payment has been successfully processed</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 20px;">
                            <p style="font-size: 16px;">Hello <?php echo htmlspecialchars($user_name); ?>,</p>
                            
                            <p style="font-size: 16px;">Thank you for subscribing to <strong><?php echo htmlspecialchars($app_name); ?></strong>! Your payment has been successfully processed and your subscription is now active.</p>

                            <!-- Subscription Details -->
                            <div style="background: white; border-radius: 8px; padding: 20px; margin: 25px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #eee;">
                                <h2 style="color: #2c3e50; margin-top: 0; border-bottom: 2px solid #3498DB; padding-bottom: 10px;">Subscription Details</h2>
                                
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: bold; width: 35%;">Library:</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($library_name); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: bold;">Plan:</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($plan_name); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: bold;">Amount Paid:</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($amount); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee; font-weight: bold;">Payment Reference:</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee; font-family: monospace;"><?php echo htmlspecialchars($reference); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; font-weight: bold;">Expires On:</td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($expires_date); ?></td>
                                    </tr>
                                </table>
                            </div>

                            <p style="font-size: 16px;">Your subscription is now active and you can continue using all features of our library management system.</p>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo htmlspecialchars($app_url); ?>" 
                                   style="display: inline-block; padding: 12px 30px; background: #3498DB; 
                                          color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
                                    Go to Dashboard
                                </a>
                            </div>

                            <p style="font-size: 16px;">If you have any questions about your subscription, please don't hesitate to contact our support team.</p>
                            
                            <p style="font-size: 16px;">Best regards,<br><strong><?php echo htmlspecialchars($app_name); ?> Team</strong></p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background: #2c3e50; color: #ecf0f1; padding: 20px; text-align: center; font-size: 12px;">
                            <p style="margin: 0;">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($app_name); ?>. All rights reserved.</p>
                            <p style="margin: 10px 0 0 0;">
                                <a href="<?php echo htmlspecialchars($app_url); ?>" style="color: #3498DB; text-decoration: none;">Visit Website</a> | 
                                <a href="<?php echo htmlspecialchars($app_url); ?>/support" style="color: #3498DB; text-decoration: none;">Support</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>