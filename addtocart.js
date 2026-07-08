// Add to Cart Page JavaScript

// Cart data (in real app, this would come from backend/session)
let cartItems = [
    {
        id: 1,
        name: 'iPhone 12',
        price: 1200,
        quantity: 1,
        image: 'https://via.placeholder.com/80?text=iPhone+12'
    },
    {
        id: 2,
        name: 'Samsung S21',
        price: 1400,
        quantity: 1,
        image: 'https://via.placeholder.com/80?text=Samsung+S21'
    }
];

// Initialize cart page
document.addEventListener('DOMContentLoaded', function() {
    renderCart();
    updateCartSummary();
    setupEventListeners();
});

// Render cart items
function renderCart() {
    const cartContainer = document.querySelector('.cart-items');
    
    if (cartItems.length === 0) {
        cartContainer.innerHTML = `
            <div class="empty-cart">
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="Buy.php" class="button2">Continue Shopping</a>
            </div>
        `;
        return;
    }

    cartContainer.innerHTML = cartItems.map(item => `
        <div class="cart-item" data-id="${item.id}">
            <img src="${item.image}" alt="${item.name}">
            <div class="item-details">
                <h3>${item.name}</h3>
                <p>Condition: Excellent</p>
                <p class="item-price">RM ${item.price}</p>
            </div>
            <div class="quantity">
                <button class="decrease-qty" data-id="${item.id}">-</button>
                <input type="number" value="${item.quantity}" min="1" class="qty-input" data-id="${item.id}">
                <button class="increase-qty" data-id="${item.id}">+</button>
            </div>
            <button class="remove-btn" data-id="${item.id}">Remove</button>
        </div>
    `).join('');
}

// Update cart summary
function updateCartSummary() {
    const subtotal = cartItems.reduce((total, item) => total + (item.price * item.quantity), 0);
    const tax = subtotal * 0.06; // 6% tax
    const shipping = subtotal > 100 ? 0 : 10; // Free shipping over RM100
    const total = subtotal + tax + shipping;

    const summaryContainer = document.querySelector('.cart-summary');
    if (summaryContainer) {
        summaryContainer.innerHTML = `
            <h3>Order Summary</h3>
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>RM ${subtotal.toFixed(2)}</span>
            </div>
            <div class="summary-row">
                <span>Tax (6%):</span>
                <span>RM ${tax.toFixed(2)}</span>
            </div>
            <div class="summary-row">
                <span>Shipping:</span>
                <span>${shipping === 0 ? 'FREE' : `RM ${shipping.toFixed(2)}`}</span>
            </div>
            <div class="summary-row total">
                <span>Total:</span>
                <span>RM ${total.toFixed(2)}</span>
            </div>
        `;
    }
}

// Setup event listeners
function setupEventListeners() {
    // Quantity increase/decrease
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('increase-qty')) {
            const id = parseInt(e.target.dataset.id);
            updateQuantity(id, 1);
        } else if (e.target.classList.contains('decrease-qty')) {
            const id = parseInt(e.target.dataset.id);
            updateQuantity(id, -1);
        } else if (e.target.classList.contains('remove-btn')) {
            const id = parseInt(e.target.dataset.id);
            removeItem(id);
        }
    });

    // Quantity input change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('qty-input')) {
            const id = parseInt(e.target.dataset.id);
            const newQuantity = parseInt(e.target.value);
            if (newQuantity > 0) {
                setQuantity(id, newQuantity);
            }
        }
    });
}

// Update quantity
function updateQuantity(id, change) {
    const item = cartItems.find(item => item.id === id);
    if (item) {
        const newQuantity = item.quantity + change;
        if (newQuantity > 0) {
            item.quantity = newQuantity;
            renderCart();
            updateCartSummary();
        }
    }
}

// Set quantity
function setQuantity(id, quantity) {
    const item = cartItems.find(item => item.id === id);
    if (item && quantity > 0) {
        item.quantity = quantity;
        renderCart();
        updateCartSummary();
    }
}

// Remove item
function removeItem(id) {
    cartItems = cartItems.filter(item => item.id !== id);
    renderCart();
    updateCartSummary();
}

// Checkout function
function checkout() {
    if (cartItems.length === 0) {
        showToast('Your cart is empty!');
        return;
    }

    // In real app, this would send data to backend
    const total = cartItems.reduce((total, item) => total + (item.price * item.quantity), 0);
    const tax = total * 0.06;
    const shipping = total > 100 ? 0 : 10;
    const finalTotal = total + tax + shipping;

    if (confirm(`Proceed to checkout? Total: RM ${finalTotal.toFixed(2)}`)) {
        // Redirect to checkout page or show checkout form
        showToast('Proceeding to checkout...', 'success');
        // window.location.href = 'checkout.php';
    }
}

// Apply discount code
function applyDiscountCode() {
    const codeInput = document.getElementById('discount-code');
    const code = codeInput.value.trim().toUpperCase();

    // Simple discount logic (in real app, this would be validated on backend)
    let discount = 0;
    switch(code) {
        case 'SAVE10':
            discount = 0.1;
            break;
        case 'SAVE20':
            discount = 0.2;
            break;
        case 'WELCOME':
            discount = 0.15;
            break;
        default:
            showToast('Invalid discount code');
            return;
    }

    const subtotal = cartItems.reduce((total, item) => total + (item.price * item.quantity), 0);
    const discountAmount = subtotal * discount;

    showToast(`Discount applied: RM ${discountAmount.toFixed(2)}`, 'success');
    updateCartSummary();
}

// Clear cart
function clearCart() {
    if (confirm('Are you sure you want to clear your cart?')) {
        cartItems = [];
        renderCart();
        updateCartSummary();
    }
}
