<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Account Removed - <?php echo $app_name; ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f9fa;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: #dc3545; color: white; padding: 30px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 300;">Member Account Removed</h1>
                            <div style="font-size: 24px; font-weight: bold; margin-top: 10px;"><?php echo $app_name; ?></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 30px;">
                            <div style="font-size: 18px; color: #495057; margin-bottom: 20px;">
                                Hello <strong><?php echo htmlspecialchars($first_name); ?></strong>,
                            </div>
                            
                            <p>We're writing to inform you that your member account for <strong><?php echo htmlspecialchars($library_name); ?></strong> has been removed from our Library Management System.</p>
                            
                            <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <h3 style="margin-top: 0; color: #721c24;">Account Access Revoked</h3>
                                <p>Your access to the library management system has been permanently removed. You will no longer be able to log in or access any library services.</p>
                            </div>
                            
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 8px; margin: 15px 0;">
                                <h3 style="margin-top: 0;">Next Steps</h3>
                                <ul style="padding-left: 20px;">
                                    <li>If you believe this was done in error, please contact your library immediately</li>
                                    <li>If you have any borrowed books, please return them as soon as possible</li>
                                    <li>All personal information associated with your account has been retained according to our data retention policy</li>
                                </ul>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <p style="margin-top: 30px; color: #6c757d; font-size: 14px;">
                                    If you have any questions or concerns, please contact your library.<br>
                                    Thank you for being a member of <?php echo htmlspecialchars($library_name); ?>.
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