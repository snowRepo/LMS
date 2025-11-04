<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

$pageTitle = 'Support';
$currentPage = 'support.php';
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

        .support-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .support-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .support-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .support-card i {
            font-size: 3rem;
            color: #3498DB;
            margin-bottom: 1.5rem;
        }

        .support-card h3 {
            font-size: 1.5rem;
            color: #2980B9;
            margin-bottom: 1rem;
        }

        .support-card p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: #ffffff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: #3498DB;
            border: 2px solid #3498DB;
        }

        .btn-outline:hover {
            background: #3498DB;
            color: #ffffff;
        }

        .faq-section {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 3rem;
        }

        .faq-section h2 {
            text-align: center;
            font-size: 2rem;
            color: #2980B9;
            margin-bottom: 2rem;
        }

        .faq-item {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1.5rem;
        }

        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .faq-question {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2980B9;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .faq-answer {
            color: #6c757d;
            line-height: 1.7;
            padding-top: 10px;
            padding-left: 30px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .contact-section {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            color: #ffffff;
            margin-bottom: 3rem;
        }

        .contact-section h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .contact-section p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }


        .contact-form {
            max-width: 600px;
            margin: 0 auto;
            text-align: left;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: 3px solid rgba(255, 255, 255, 0.5);
        }

        .contact-form .btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
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
            
            .support-grid {
                grid-template-columns: 1fr;
            }
            

            
            .faq-answer {
                padding-left: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-headset"></i> Support Center</h1>
            <p>We're here to help you with any questions or issues you may have with our Library Management System</p>
        </div>
        
        <div class="support-grid">
            <div class="support-card">
                <i class="fas fa-hashtag"></i>
                <h3>Social Media</h3>
                <p>Connect with us on our social media platforms</p>
                <div class="btn btn-outline">Coming Soon</div>
            </div>
            
            <div class="support-card">
                <i class="fas fa-envelope"></i>
                <h3>Email Support</h3>
                <p>Contact our support team directly for personalized assistance</p>
                <a href="mailto:info.lms.library@gmail.com" class="btn btn-outline">info.lms.library@gmail.com</a>
            </div>
            
            <div class="support-card">
                <i class="fas fa-phone-alt"></i>
                <h3>Call or WhatsApp</h3>
                <p>Reach us via phone or WhatsApp for immediate assistance</p>
                <a href="tel:+233246960543" class="btn btn-outline">+233 24 696 0543</a>
            </div>
        </div>
        
        <div class="faq-section" id="faq">
            <h2>Frequently Asked Questions</h2>
            
            <div class="faq-item">
                <div class="faq-question">
                    <i class="fas fa-chevron-right"></i>
                    How do I reset my password?
                </div>
                <div class="faq-answer">
                    If you've forgotten your password, you can reset it by clicking the "Forgot Password" link on the login page. 
                    Enter your email address and follow the instructions sent to your inbox.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    <i class="fas fa-chevron-right"></i>
                    How do I enable two-factor authentication?
                </div>
                <div class="faq-answer">
                    Two-factor authentication is automatically enabled for all non-admin accounts. 
                    After logging in with your username and password, you'll receive a 6-digit code via email 
                    that you'll need to enter to complete the login process.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    <i class="fas fa-chevron-right"></i>
                    What should I do if I don't receive the 2FA email?
                </div>
                <div class="faq-answer">
                    If you don't receive the 2FA email within a few minutes:
                    <ol>
                        <li>Check your spam or junk folder</li>
                        <li>Verify that you entered the correct email address during registration</li>
                        <li>Click the "Resend Code" button on the 2FA verification page</li>
                        <li>Contact support if the issue persists</li>
                    </ol>
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    <i class="fas fa-chevron-right"></i>
                    How do I add books to the library?
                </div>
                <div class="faq-answer">
                    Book management is available to librarian roles. 
                    After logging in, navigate to the "Books" section in your dashboard 
                    and use the "Add New Book" button to enter book details.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-question">
                    <i class="fas fa-chevron-right"></i>
                    What are the system requirements?
                </div>
                <div class="faq-answer">
                    Our Library Management System works on any modern web browser including Chrome, Firefox, Safari, and Edge. 
                    No additional software installation is required.
                </div>
            </div>
        </div>
        
        <div class="contact-section">
            <h2>Send Us a Message</h2>
            <p>Have a question or need assistance? Fill out the form below and our support team will get back to you as soon as possible.</p>
            
            <form class="contact-form" id="supportForm">
                <div class="form-group">
                    <input type="text" id="name" name="name" placeholder="Your Name" required>
                </div>
                <div class="form-group">
                    <input type="email" id="email" name="email" placeholder="Your Email" required>
                </div>
                <div class="form-group">
                    <input type="text" id="subject" name="subject" placeholder="Subject" required>
                </div>
                <div class="form-group">
                    <textarea id="message" name="message" placeholder="Your Message" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
            <div id="formMessage" style="margin-top: 15px; padding: 10px; border-radius: 5px; display: none;"></div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 LMS. All rights reserved.</p>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const faqQuestions = document.querySelectorAll('.faq-question');
            
            faqQuestions.forEach(question => {
                question.addEventListener('click', function() {
                    const answer = this.nextElementSibling;
                    const icon = this.querySelector('i');
                    
                    // Toggle the answer visibility
                    if (answer.style.maxHeight) {
                        answer.style.maxHeight = null;
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-right');
                    } else {
                        answer.style.maxHeight = answer.scrollHeight + 'px';
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-down');
                    }
                });
            });
            
            // Handle support form submission
            const supportForm = document.getElementById('supportForm');
            const submitBtn = document.getElementById('submitBtn');
            const formMessage = document.getElementById('formMessage');
            
            supportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Get form data
                const name = document.getElementById('name').value;
                const email = document.getElementById('email').value;
                const subject = document.getElementById('subject').value;
                const message = document.getElementById('message').value;
                
                // Disable submit button and show loading
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                
                // Send data via AJAX
                fetch('ajax_support_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: name,
                        email: email,
                        subject: subject,
                        message: message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        formMessage.style.display = 'block';
                        formMessage.style.backgroundColor = '#d4edda';
                        formMessage.style.color = '#155724';
                        formMessage.style.border = '1px solid #c3e6cb';
                        formMessage.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        supportForm.reset();
                    } else {
                        formMessage.style.display = 'block';
                        formMessage.style.backgroundColor = '#f8d7da';
                        formMessage.style.color = '#721c24';
                        formMessage.style.border = '1px solid #f5c6cb';
                        formMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                    }
                })
                .catch(error => {
                    formMessage.style.display = 'block';
                    formMessage.style.backgroundColor = '#f8d7da';
                    formMessage.style.color = '#721c24';
                    formMessage.style.border = '1px solid #f5c6cb';
                    formMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                })
                .finally(() => {
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Message';
                });
            });
        });
    </script>
</body>
</html>