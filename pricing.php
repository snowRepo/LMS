<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

$pageTitle = 'Pricing';
$currentPage = 'pricing.php';
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

        .plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .plan {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
            position: relative;
            border: 2px solid #e9ecef;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .plan:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .plan.featured {
            border-color: #2980B9;
            background: #ffffff;
        }

        .plan.featured::before {
            content: "Most Popular";
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: #2980B9;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .plan h3 {
            font-size: 1.5rem;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .plan-period {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .plan-features {
            list-style: none;
            margin-bottom: 2rem;
            text-align: left;
        }

        .plan-features li {
            padding: 0.5rem 0;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .plan-features li i {
            color: #28a745;
            font-size: 0.9rem;
        }

        .plan-btn {
            width: 100%;
            padding: 1rem;
            background: #3498DB;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .plan-btn:hover {
            background: #2980B9;
        }

        .plan.featured .plan-btn {
            background: #2980B9;
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
            
            .plans {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-tag"></i> Pricing</h1>
            <p>Select the perfect subscription based on your library's size and needs</p>
        </div>
        
        <div class="plans">
            <div class="plan">
                <h3>Basic</h3>
                <div class="plan-price">GHS 1,200</div>
                <div class="plan-period">per year</div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Up to 500 books</li>
                    <li><i class="fas fa-check"></i> Up to 100 members</li>
                    <li><i class="fas fa-check"></i> Basic reporting</li>
                    <li><i class="fas fa-check"></i> Email support</li>
                    <li><i class="fas fa-check"></i> Mobile responsive</li>
                </ul>
                <button class="plan-btn">Choose Basic</button>
            </div>

            <div class="plan featured">
                <h3>Standard</h3>
                <div class="plan-price">GHS 1,800</div>
                <div class="plan-period">per year</div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Up to 2,000 books</li>
                    <li><i class="fas fa-check"></i> Up to 500 members</li>
                    <li><i class="fas fa-check"></i> Advanced reporting</li>
                    <li><i class="fas fa-check"></i> Priority support</li>
                    <li><i class="fas fa-check"></i> Custom categories</li>
                    <li><i class="fas fa-check"></i> Data backup</li>
                </ul>
                <button class="plan-btn">Choose Standard</button>
            </div>

            <div class="plan">
                <h3>Premium</h3>
                <div class="plan-price">GHS 2,400</div>
                <div class="plan-period">per year</div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Unlimited books</li>
                    <li><i class="fas fa-check"></i> Unlimited members</li>
                    <li><i class="fas fa-check"></i> Full analytics suite</li>
                    <li><i class="fas fa-check"></i> 24/7 phone support</li>
                    <li><i class="fas fa-check"></i> Multi-branch support</li>
                    <li><i class="fas fa-check"></i> API access</li>
                    <li><i class="fas fa-check"></i> Custom branding</li>
                </ul>
                <button class="plan-btn">Choose Premium</button>
            </div>
        </div>
        
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 LMS. All rights reserved.</p>
    </footer>
</body>
</html>