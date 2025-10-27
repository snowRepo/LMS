// Tab switching
document.querySelectorAll('.tab-button').forEach(function(button) {
    button.addEventListener('click', function() {
        var tabName = this.dataset.tab;
        
        // Remove active class from all buttons and contents
        document.querySelectorAll('.tab-button').forEach(function(btn) {
            btn.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(function(content) {
            content.classList.remove('active');
        });
        
        // Add active class to clicked button and corresponding content
        this.classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
    });
});

// Return book function
function returnBook(borrowingId, bookTitle) {
    if (confirm('Are you sure you want to mark "' + bookTitle + '" as returned?')) {
        fetch('process_return.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'borrowing_id=' + borrowingId
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Book returned successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('An error occurred while processing the return.');
        });
    }
}

// Renew book function
function renewBook(borrowingId) {
    if (confirm('Are you sure you want to renew this borrowing?')) {
        fetch('process_renew.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'borrowing_id=' + borrowingId
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Borrowing renewed successfully! New due date: ' + data.new_due_date);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('An error occurred while renewing the borrowing.');
        });
    }
}

// Fulfill reservation function
function fulfillReservation(reservationId) {
    if (confirm('Are you sure you want to fulfill this reservation?')) {
        fetch('process_fulfill_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'reservation_id=' + reservationId
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Reservation fulfilled successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('An error occurred while fulfilling the reservation.');
        });
    }
}

// Cancel reservation function
function cancelReservation(reservationId) {
    if (confirm('Are you sure you want to cancel this reservation?')) {
        fetch('process_cancel_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'reservation_id=' + reservationId
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Reservation cancelled successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('An error occurred while cancelling the reservation.');
        });
    }
}

// Issue borrowing button
document.getElementById('issueBorrowingBtn').addEventListener('click', function() {
    alert('Issue Borrowing feature coming soon!');
});

// Quick return button
document.getElementById('quickReturnBtn').addEventListener('click', function() {
    var isbn = prompt('Enter or scan the ISBN of the book to return:');
    if (isbn) {
        fetch('process_quick_return.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'isbn=' + encodeURIComponent(isbn)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Book returned successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('An error occurred while processing the return.');
        });
    }
});
