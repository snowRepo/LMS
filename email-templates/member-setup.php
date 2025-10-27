<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Account Setup - <?php echo $app_name; ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f9fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: #3498DB; color: white; padding: 30px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 300;">Member Account Setup</h1>
                            <div style="font-size: 24px; font-weight: bold; margin-top: 10px;"><?php echo $app_name; ?></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 30px;">
                            <div style="font-size: 18px; color: #495057; margin-bottom: 20px;">
                                Hello <strong><?php echo htmlspecialchars($first_name); ?></strong>,
                            </div>
                            
                            <p>You've been added as a member to <strong><?php echo htmlspecialchars($library_name); ?></strong> in our Library Management System. To complete your account setup, please verify your email and set your password.</p>
                            
                            <div style="background: #f8f9fa; border-left: 4px solid #3498DB; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <h3 style="margin-top: 0;">What you need to do:</h3>
                                <ol>
                                    <li>Click the "Setup Account" button below</li>
                                    <li>Confirm your account details</li>
                                    <li>Create a secure password for your account</li>
                                    <li>Login to start using our library services</li>
                                </ol>
                            </div>
                            
                            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px; margin: 15px 0;">
                                <h3 style="margin-top: 0;">Important</h3>
                                <p>This link will expire in 24 hours. After that, you'll need to contact your library to resend the setup email.</p>
                                <p>If you did not expect this email, please ignore it or contact your library.</p>
                            </div>
                            
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo $verification_link; ?>" 
                                   style="display: inline-block; padding: 12px 30px; background: #3498DB; color: white; text-decoration: none; border-radius: 8px; font-weight: 500;">
                                    Setup Your Account
                                </a>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <p style="margin-top: 30px; color: #6c757d; font-size: 14px;">
                                    If you have any questions or need assistance, please contact your library.<br>
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