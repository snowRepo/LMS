<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?php echo $app_name; ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f9fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: #3498DB; color: white; padding: 30px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 300;">Welcome to Your Digital Library!</h1>
                            <div style="font-size: 24px; font-weight: bold; margin-top: 10px;">📚 <?php echo $app_name; ?></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 30px;">
                            <div style="font-size: 18px; color: #495057; margin-bottom: 20px;">
                                Hello <strong><?php echo htmlspecialchars($user_name); ?></strong>,
                            </div>
                            
                            <p>We're excited to welcome you to <?php echo $app_name; ?>! Your account has been successfully created and you now have access to our comprehensive library management system.</p>
                            
                            <?php if (isset($temporary_password) && !empty($temporary_password)): ?>
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 8px; margin: 15px 0;">
                                <h3 style="margin-top: 0;">🔐 Your Login Credentials</h3>
                                <p><strong>Email:</strong> Your registered email address</p>
                                <p><strong>Temporary Password:</strong> <code><?php echo htmlspecialchars($temporary_password); ?></code></p>
                                <p><em>⚠️ Please change your password after your first login for security.</em></p>
                            </div>
                            <?php endif; ?>
                            
                            <div style="background: #f8f9fa; border-left: 4px solid #3498DB; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <h3 style="margin-top: 0;">🎉 What you can do now:</h3>
                                <ul>
                                    <li>Browse our extensive book collection</li>
                                    <li>View your borrowing history and current loans</li>
                                    <li>Check due dates and renewal options</li>
                                    <li>Manage your profile and preferences</li>
                                    <li>Receive notifications about new books and overdue items</li>
                                </ul>
                            </div>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo $login_url; ?>" 
                                   style="display: inline-block; padding: 12px 30px; background: #3498DB; color: white; text-decoration: none; border-radius: 8px; font-weight: 500;">
                                    🚀 Login to Your Account
                                </a>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <p style="margin-top: 30px; color: #6c757d; font-size: 14px;">
                                    If you have any questions or need assistance, please don't hesitate to contact our support team.<br>
                                    We're here to help make your library experience as smooth as possible!
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="background: #495057; color: #adb5bd; padding: 20px; text-align: center; font-size: 14px;">
                            <p style="margin: 0;">&copy; <?php echo date('Y'); ?> <?php echo $app_name; ?>. All rights reserved.</p>
                            <p style="margin-top: 10px;">
                                <a href="<?php echo $app_url; ?>" style="color: #adb5bd; text-decoration: none;">Visit Website</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>