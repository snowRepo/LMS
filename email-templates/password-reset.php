<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset - <?php echo htmlspecialchars($app_name); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); overflow: hidden;">
                    <tr>
                        <td style="background: #3498DB; color: white; padding: 30px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px;">Password Reset Request</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            <p>Hello <?php echo htmlspecialchars($user_name); ?>,</p>
                            
                            <p>We received a request to reset your password for your account at <?php echo htmlspecialchars($app_name); ?>.</p>
                            
                            <p>If you made this request, please click the button below to reset your password:</p>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo htmlspecialchars($reset_url); ?>" 
                                   style="display: inline-block; padding: 12px 25px; background: #3498DB; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                                    Reset Password
                                </a>
                            </div>
                            
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                <p><strong>Note:</strong> This password reset link will expire in 24 hours for security reasons.</p>
                            </div>
                            
                            <p>If you didn't request a password reset, you can safely ignore this email. Your password will not be changed.</p>
                            
                            <p>If the button above doesn't work, you can copy and paste the following link into your browser:</p>
                            <p><?php echo htmlspecialchars($reset_url); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee;">
                            <p style="margin: 0;">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($app_name); ?>. All rights reserved.</p>
                            <p style="margin: 5px 0 0 0;">This is an automated message, please do not reply to this email.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>