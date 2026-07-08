function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Approve submission
function approveSubmission(submissionId) {
    if (confirm('Are you sure you want to approve this submission?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'clerk_submissions.php';
        form.innerHTML = '<input type="hidden" name="action" value="approve"><input type="hidden" name="submission_id" value="' + submissionId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}


// Approve in-person review
function approveInPerson(reviewId) {
    openModal('approveReviewModal');
    document.getElementById('approveReviewId').value = reviewId;
}

// Request time change
function requestTimeChange(reviewId) {
    openModal('timeChangeModal');
    document.getElementById('timeChangeReviewId').value = reviewId;
}

// Mark clerk arrived
function markClerkArrived(reviewId) {
    fetch('clerk_in_person.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=clerk_arrived&review_id=' + reviewId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Arrival marked successfully!');
            location.reload();
        } else {
            alert(data.error || 'Error marking arrival');
        }
    });
}

// Mark user arrived
function markUserArrived(reviewId) {
    fetch('clerk_in_person.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=user_arrived&review_id=' + reviewId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('User arrival marked successfully!');
            location.reload();
        } else {
            alert(data.error || 'Error marking arrival');
        }
    });
}

// End consultation
function endConsultation(reviewId) {
    const reason = prompt('Please enter the reason for ending the consultation:');
    if (reason) {
        fetch('clerk_in_person.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=end_consultation&review_id=' + reviewId + '&end_reason=' + encodeURIComponent(reason)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Consultation ended successfully!');
                location.reload();
            } else {
                alert(data.error || 'Error ending consultation');
            }
        });
    }
}

// Approve technician listing
function approveListing(listingId) {
    if (confirm('Are you sure you want to approve this listing?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'clerk_listings.php';
        form.innerHTML = '<input type="hidden" name="action" value="approve"><input type="hidden" name="listing_id" value="' + listingId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Reject technician listing
function rejectListing(listingId) {
    const reason = prompt('Please enter the reason for rejection:');
    if (reason) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'clerk_listings.php';
        form.innerHTML = '<input type="hidden" name="action" value="reject"><input type="hidden" name="listing_id" value="' + listingId + '"><input type="hidden" name="rejection_reason" value="' + encodeURIComponent(reason) + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Request revision for listing
function requestRevision(listingId) {
    const notes = prompt('Please enter the revision notes:');
    if (notes) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'clerk_listings.php';
        form.innerHTML = '<input type="hidden" name="action" value="request_revision"><input type="hidden" name="listing_id" value="' + listingId + '"><input type="hidden" name="clerk_notes" value="' + encodeURIComponent(notes) + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Update product location
function updateLocation(productId, productType) {
    openModal('updateLocationModal');
    document.getElementById('updateLocationProductId').value = productId;
    document.getElementById('updateLocationProductType').value = productType;
}

// Ping technician
function pingTechnician(repairId) {
    const message = prompt('Enter message to send to technician:');
    if (message) {
        fetch('clerk_repairs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=ping_technician&repair_id=' + repairId + '&message=' + encodeURIComponent(message)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Message sent to technician!');
            } else {
                alert(data.error || 'Error sending message');
            }
        });
    }
}

// Chat functions
function selectChatUser(userId) {
    document.querySelectorAll('.chat-user').forEach(el => el.classList.remove('active'));
    document.querySelector('.chat-user[data-user-id="' + userId + '"]').classList.add('active');
    
    fetch('clerk_chat.php?action=get_messages&user_id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.messages) {
                displayMessages(data.messages, data.current_user_id);
            }
        });
}

function displayMessages(messages, currentUserId) {
    const container = document.getElementById('chatMessages');
    container.innerHTML = '';

    messages.forEach(msg => {
        const div = document.createElement('div');
        div.className = 'chat-message ' + (msg.sender_id === currentUserId ? 'sent' : 'received');
        div.innerHTML = `
            <div class="chat-message-content">${msg.message}</div>
            <div class="chat-message-time">${msg.time}</div>
        `;
        container.appendChild(div);
    });

    container.scrollTop = container.scrollHeight;
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    const activeUser = document.querySelector('.chat-user.active');
    
    if (message && activeUser) {
        const userId = activeUser.dataset.userId;
        
        fetch('clerk_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=send_message&receiver_id=' + userId + '&message=' + encodeURIComponent(message)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                selectChatUser(userId);
            }
        });
    }
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const svg = button.querySelector('svg');

    if (input.type === 'password') {
        input.type = 'text';
        svg.innerHTML = `
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
        `;
    } else {
        input.type = 'password';
        svg.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        `;
    }
}
