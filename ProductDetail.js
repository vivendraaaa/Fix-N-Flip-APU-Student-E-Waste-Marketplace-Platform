// Product Detail Page JavaScript

// Change main product image when thumbnail is clicked
function changeImage(imageId) {
    const mainImage = document.getElementById('mainProductImage');
    if (mainImage) {
        mainImage.style.opacity = '0';
        setTimeout(() => {
            mainImage.src = 'get_product_image.php?id=' + imageId;
            mainImage.style.opacity = '1';
        }, 200);
    }

    // Update active thumbnail
    const thumbnails = document.querySelectorAll('.thumbnail');
    thumbnails.forEach(thumb => {
        thumb.classList.remove('active');
        if (thumb.querySelector('img') && thumb.querySelector('img').src.includes('id=' + imageId)) {
            thumb.classList.add('active');
        }
    });
}

// Play video in modal
function playVideo(videoSrc) {
    const modal = document.getElementById('videoModal');
    const video = document.getElementById('productVideo');
    
    if (modal && video) {
        video.src = videoSrc;
        modal.style.display = 'flex';
        video.play();
    }
}

// Close video modal
function closeVideoModal() {
    const modal = document.getElementById('videoModal');
    const video = document.getElementById('productVideo');
    
    if (modal && video) {
        video.pause();
        video.src = '';
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('videoModal');
    if (event.target === modal) {
        closeVideoModal();
    }
}

// Toggle inspection form
function toggleInspectionForm() {
    const form = document.getElementById('inspectionForm');
    if (form) {
        if (form.style.display === 'none') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }
}

// Set minimum date for inspection request (today)
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('requested_date');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
    }
});

// Add smooth transitions to main image
document.addEventListener('DOMContentLoaded', function() {
    const mainImage = document.getElementById('mainProductImage');
    if (mainImage) {
        mainImage.style.transition = 'opacity 0.3s ease';
    }
});

// Thumbnail hover effect
document.addEventListener('DOMContentLoaded', function() {
    const thumbnails = document.querySelectorAll('.thumbnail');
    thumbnails.forEach(thumb => {
        thumb.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
        });
        thumb.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
});
