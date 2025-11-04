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

// Check for URL parameters and show toast notifications
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Check for success parameter
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'), 'success');
        // Remove the success parameter from URL
        const url = new URL(window.location);
        url.searchParams.delete('success');
        window.history.replaceState({}, document.title, url);
    }
    
    // Check for error parameter
    if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
        // Remove the error parameter from URL
        const url = new URL(window.location);
        url.searchParams.delete('error');
        window.history.replaceState({}, document.title, url);
    }
});