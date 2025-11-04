<!DOCTYPE html>
<html>
<head>
    <title>Toast Test</title>
    <link rel="stylesheet" href="css/toast.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Toast Test</h1>
    <button id="showToast">Show Success Toast</button>
    
    <script>
        // Toast Notification Functions
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Set icon based on type
            let iconClass = 'fa-info-circle';
            if (type === 'success') iconClass = 'fa-check-circle';
            else if (type === 'error') iconClass = 'fa-exclamation-circle';
            else if (type === 'warning') iconClass = 'fa-exclamation-triangle';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <div class="toast-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="toast-message">${message}</div>
                    <button class="toast-close">&times;</button>
                </div>
            `;
            
            // Add toast to container
            toastContainer.appendChild(toast);
            
            // Show toast with animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Add close button event listener
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            // Auto hide toast after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.remove('show');
                    toast.classList.add('hide');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }
            }, 5000);
        }
        
        $(document).ready(function() {
            $('#showToast').click(function() {
                showToast('Test success message', 'success');
            });
            
            // Check for success parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1') {
                showToast('Book returned successfully', 'success');
                // Remove the success parameter from URL
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, document.title, url);
            }
        });
    </script>
</body>
</html>