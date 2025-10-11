<?php
$pageTitle = 'Home';

// Scan videos directory for available videos
$videoDir = 'videos/';
$videos = [];

if (is_dir($videoDir)) {
    $files = scandir($videoDir);
    foreach ($files as $file) {
        // Check for common video file extensions
        if (preg_match('/\.(mp4|webm|ogg|mov)$/i', $file)) {
            $videos[] = $file;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Library Management System</title>
    
    <!-- Navbar CSS -->
    <link rel="stylesheet" href="css/navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        /* Hero Section */
        .hero {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
            text-align: center;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 300;
            color: #495057;
            margin-bottom: 1rem;
            letter-spacing: -1px;
        }

        .hero .subtitle {
            font-size: 1.3rem;
            color: #6c757d;
            margin-bottom: 3rem;
            font-weight: 300;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Features Section */
        .features {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem 2rem;
        }

        .features-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .features-title h2 {
            font-size: 2.5rem;
            color: #495057;
            font-weight: 300;
            margin-bottom: 1rem;
        }

        .features-title p {
            font-size: 1.2rem;
            color: #6c757d;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: #ffffff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
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
            font-size: 1.4rem;
            color: #495057;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .feature-card p {
            color: #6c757d;
            line-height: 1.6;
        }

        /* Action Buttons */
        .actions {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
            text-align: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 1rem 2rem;
            margin: 0 1rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: #ffffff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #ffffff;
            color: #3498DB;
            border: 2px solid #3498DB;
        }

        .btn-secondary:hover {
            background: #3498DB;
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* Subscription Plans */
        .subscription-section {
            background: #ffffff;
            padding: 4rem 0;
            margin-top: 3rem;
        }

        .subscription-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .subscription-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .subscription-title h2 {
            font-size: 2.5rem;
            color: #495057;
            font-weight: 300;
            margin-bottom: 1rem;
        }

        .subscription-title p {
            font-size: 1.2rem;
            color: #6c757d;
        }

        .plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .plan {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .plan:hover {
            border-color: #3498DB;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
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

        .plan-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
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
        }

        .plan-features li {
            padding: 0.5rem 0;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }

        .modal-content {
            background-color: #ffffff;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 80%;
            max-width: 600px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            position: relative;
        }

        .modal-content h2 {
            color: #2980B9;
            margin-bottom: 1.5rem;
        }

        .close {
            color: #aaa;
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .video-placeholder {
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 1rem 0;
        }

        .video-placeholder i {
            font-size: 4rem;
            color: #3498DB;
            margin-bottom: 1rem;
        }

        .video-placeholder p {
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .video-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .video-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .video-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .video-link {
            text-decoration: none;
            color: #495057;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .video-link i {
            font-size: 2rem;
            color: #3498DB;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .hero .subtitle {
                font-size: 1.1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .actions .btn {
                display: block;
                margin: 0.5rem auto;
                text-align: center;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php 
    $currentPage = 'index.php';
    include 'includes/navbar.php'; 
    ?>

    <!-- Hero Section -->
    <section class="hero">
        <h1>Welcome to Your Digital Library</h1>
        <p class="subtitle">Streamline your library operations with our comprehensive management system. From book tracking to member management, we've got you covered.</p>
        
        <div class="actions">
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-rocket"></i>
                Start Free Trial
            </a>
            <a href="#" id="learnMoreBtn" class="btn btn-secondary">
                <i class="fas fa-play-circle"></i>
                Learn More
            </a>
        </div>
    </section>

    <!-- Video Modal -->
    <div id="videoModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Library Management System Tutorials</h2>
            <div id="videoContainer">
                <?php if (!empty($videos)): ?>
                    <div class="video-list">
                        <?php foreach ($videos as $video): ?>
                            <?php
                            $videoName = pathinfo($video, PATHINFO_FILENAME);
                            $videoName = str_replace(['_', '-'], ' ', $videoName);
                            $videoName = ucwords($videoName);
                            ?>
                            <div class="video-item">
                                <a href="#" class="video-link" data-video="<?php echo $videoDir . $video; ?>">
                                    <i class="fas fa-play-circle"></i>
                                    <span><?php echo $videoName; ?></span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="videoPlayer" style="display: none; margin-top: 20px;">
                        <video width="100%" height="300" controls>
                            <source src="" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <button id="backToVideos" class="btn btn-secondary" style="margin-top: 10px;">Back to Videos</button>
                    </div>
                <?php else: ?>
                    <div class="video-placeholder">
                        <i class="fas fa-video"></i>
                        <p>Tutorial videos will be available soon to help you get the most out of our Library Management System.</p>
                        <p>Please check back later for step-by-step guides and demonstrations.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2025 LMS. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('videoModal');
            const btn = document.getElementById('learnMoreBtn');
            const span = document.getElementsByClassName('close')[0];
            
            btn.onclick = function(e) {
                e.preventDefault();
                modal.style.display = 'block';
            }
            
            span.onclick = function() {
                modal.style.display = 'none';
                // Reset modal content when closing
                document.getElementById('videoPlayer').style.display = 'none';
                document.querySelector('.video-list').style.display = 'block';
                document.querySelector('#videoPlayer video source').src = '';
                document.querySelector('#videoPlayer video').load();
            }
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                    // Reset modal content when closing
                    document.getElementById('videoPlayer').style.display = 'none';
                    document.querySelector('.video-list').style.display = 'block';
                    document.querySelector('#videoPlayer video source').src = '';
                    document.querySelector('#videoPlayer video').load();
                }
            }
            
            // Handle video selection
            document.addEventListener('click', function(e) {
                if (e.target.closest('.video-link')) {
                    e.preventDefault();
                    const videoSrc = e.target.closest('.video-link').getAttribute('data-video');
                    const videoPlayer = document.getElementById('videoPlayer');
                    const videoList = document.querySelector('.video-list');
                    const videoSource = videoPlayer.querySelector('video source');
                    
                    videoSource.src = videoSrc;
                    videoPlayer.querySelector('video').load();
                    videoList.style.display = 'none';
                    videoPlayer.style.display = 'block';
                }
            });
            
            // Handle back to videos button
            document.addEventListener('click', function(e) {
                if (e.target.id === 'backToVideos') {
                    e.preventDefault();
                    document.getElementById('videoPlayer').style.display = 'none';
                    document.querySelector('.video-list').style.display = 'block';
                    document.querySelector('#videoPlayer video source').src = '';
                    document.querySelector('#videoPlayer video').load();
                }
            });
        });
    </script>
</body>
</html>