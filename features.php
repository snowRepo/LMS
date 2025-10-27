<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

$pageTitle = 'Features';
$currentPage = 'features.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Navbar CSS -->
    <link rel="stylesheet" href="css/navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 0;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #495057;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem 0;
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: #2980B9;
            margin-bottom: 1rem;
        }

        .page-header p {
            font-size: 1.2rem;
            color: #6c757d;
            max-width: 800px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .feature-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .feature-card i {
            font-size: 3rem;
            color: #3498DB;
            margin-bottom: 1.5rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            color: #2980B9;
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        /* Footer */
        .footer {
            background: #495057;
            color: #adb5bd;
            text-align: center;
            padding: 1rem 0;
            margin-top: 4rem;
        }

        .footer p {
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-info-circle"></i> Features</h1>
            <p>Powerful features designed to make library management effortless and efficient</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-book-open"></i>
                <h3>Book Management</h3>
                <p>Easily add, edit, and organize your entire book collection. Track availability, categories, and detailed information for every title.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-users"></i>
                <h3>Member Portal</h3>
                <p>Give your members access to browse books, check their borrowing history, and view due dates through a clean, user-friendly interface.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-exchange-alt"></i>
                <h3>Borrowing System</h3>
                <p>Streamlined book issuing and return process with automated due date tracking and overdue notifications.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-chart-line"></i>
                <h3>Analytics & Reports</h3>
                <p>Gain insights into your library's performance with detailed reports on popular books, member activity, and circulation statistics.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-user-shield"></i>
                <h3>Role-Based Access</h3>
                <p>Secure multi-level access control for administrators, supervisors, librarians, and members with appropriate permissions.</p>
            </div>

            <div class="feature-card">
                <i class="fas fa-cloud"></i>
                <h3>Cloud-Based</h3>
                <p>Access your library system from anywhere with our secure, cloud-based platform. No software installation required.</p>
            </div>
        </div>
        
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 LMS. All rights reserved.</p>
    </footer>
</body>
</html>