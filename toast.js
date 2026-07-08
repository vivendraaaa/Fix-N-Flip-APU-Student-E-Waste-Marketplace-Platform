function showToast(message, type = 'error') {
    // Remove existing toast if any
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    
    // Set background color based on type
    const bgColor = type === 'success' ? '#10b981' : '#EF665B';
    toast.style.background = bgColor;

    // Create toast content
    toast.innerHTML = `
        <svg class="toast__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            ${type === 'success' 
                ? '<path d="M20 6L9 17l-5-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
                : '<path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            }
        </svg>
        <span class="toast__title">${message}</span>
        <svg class="toast__close" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 6L6 18M6 6l12 12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    `;

    // Add styles
    toast.style.cssText += `
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        width: 320px;
        padding: 12px;
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: start;
        border-radius: 8px;
        box-shadow: 0px 0px 5px -3px #111;
        position: fixed;
        top: 20px;
        right: -400px;
        z-index: 10000;
        transition: right 0.3s ease-in-out;
    `;

    // Add to body
    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.style.right = '20px';
    }, 10);

    // Close button functionality
    const closeBtn = toast.querySelector('.toast__close');
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.marginLeft = 'auto';
    closeBtn.addEventListener('click', () => {
        toast.style.right = '-400px';
        setTimeout(() => toast.remove(), 300);
    });

    // Auto dismiss after 2 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.right = '-400px';
            setTimeout(() => toast.remove(), 300);
        }
    }, 2000);
}
