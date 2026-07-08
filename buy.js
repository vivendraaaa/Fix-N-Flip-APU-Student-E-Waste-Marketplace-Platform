// Buy Page JavaScript - Product Management and Filtering

// Global variables
let currentProducts = [];
let allProducts = [];

// Convert database products to display format
function formatProductForDisplay(product) {
    return {
        id: product.id,
        name: `${product.brand} ${product.model}`,
        price: `RM ${parseFloat(product.price).toFixed(2)}`,
        imageUrl: product.image_1 || 'https://via.placeholder.com/100?text=No+Image',
        specs: `Storage: ${product.storage || 'N/A'} GB\nRAM: ${product.ram || 'N/A'} GB\nBattery: ${product.battery_health || 'N/A'}%`,
        condition: `Condition: ${product.device_condition}`,
        deviceType: product.device_type
    };
}

// Select device type
function selectDevice(deviceType) {
    // Initialize allProducts from database if empty
    if (allProducts.length === 0 && typeof dbProducts !== 'undefined') {
        allProducts = dbProducts.map(formatProductForDisplay);
    }

    // Update button styles
    document.querySelectorAll('.device-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');

    // Show content
    const contentWrapper = document.getElementById('content');
    contentWrapper.classList.add('active');

    // Set current products based on selection
    if (deviceType === 'all') {
        currentProducts = allProducts;
    } else {
        // Filter by device type
        currentProducts = allProducts.filter(product => {
            const typeMap = {
                'mobile': 'Smartphone',
                'tablet': 'Tablet',
                'laptop': 'Laptop',
                'desktop': 'Desktop Computer',
                'camera': 'Camera',
                'headphones': 'Headphones',
                'other': 'Other'
            };
            return product.deviceType === typeMap[deviceType];
        });
    }

    // Clear search and show products
    document.getElementById('searchInput').value = '';
    generateProducts(currentProducts);
}

// Generate product cards
function generateProducts(products) {
    const container = document.getElementById('products-container');
    container.innerHTML = '';

    if (!products || products.length === 0) {
        container.innerHTML = '<div class="no-products">No products found</div>';
        return;
    }

    products.forEach((product) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.style.cursor = 'pointer';

        // Get condition color
        const conditionColor = getConditionColor(product.condition);

        card.innerHTML = `
                <img src="${product.imageUrl}" alt="${product.name}" class="device-icon">
                <div class="card-content">
                    <h3 class="heading">${product.name}</h3>
                    <div class="condition-badge" style="background-color: ${conditionColor};">${product.condition}</div>
                    <div class="specs">${product.specs.replace(/\n/g, '<br>')}</div>
                    <div class="price">${product.price}</div>
                    <div class="button-container">
                        <button class="btn1" data-action="buy" data-product-id="${product.id}">Buy</button>
                        <button class="btn2" data-action="add-to-cart" data-product-id="${product.id}">Add to Cart</button>
                    </div>
                </div>
        `;

        // Add click event to card
        card.addEventListener('click', function(e) {
            // Don't navigate if button was clicked
            const clickedButton = e.target.closest('button');
            if (!clickedButton) {
                navigateToProduct(product.id);
            }
        });

        // Add click event to Buy button
        const buyButton = card.querySelector('.btn1');
        if (buyButton) {
            buyButton.addEventListener('click', function(e) {
                e.stopPropagation();
                navigateToProduct(product.id);
            });
        }

        // Add click event to Add to Cart button
        const cartButton = card.querySelector('.btn2');
        if (cartButton) {
            cartButton.addEventListener('click', function(e) {
                e.stopPropagation();
                showToast('Add to cart functionality coming soon');
            });
        }

        container.appendChild(card);
    });
}

// Get condition color based on condition text
function getConditionColor(condition) {
    const conditionText = condition.toLowerCase();
    if (conditionText.includes('excellent')) {
        return '#10b981'; // Green
    } else if (conditionText.includes('like new') || conditionText.includes('like-new') || conditionText.includes('likenew') || conditionText.includes('good')) {
        return '#f59e0b'; // Yellow/Orange
    } else if (conditionText.includes('fair') || conditionText.includes('decent')) {
        return '#ef4444'; // Red
    } 
    return '#666666'; // Default gray
}

// Navigate to product detail page
function navigateToProduct(productId) {
    window.location.href = `ProductDetail.php?id=${productId}`;
}

// Filter products based on search
function filterProducts() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    if (!searchTerm) {
        // Show current products if search is empty
        generateProducts(currentProducts);
        return;
    }

    // Filter products based on name or specs from currentProducts (device type specific)
    const filtered = currentProducts.filter(product => {
        const name = product.name.toLowerCase();
        const specs = product.specs.toLowerCase();
        return name.includes(searchTerm) || specs.includes(searchTerm);
    });

    // Display filtered results
    const container = document.getElementById('products-container');
    container.innerHTML = '';

    if (filtered.length === 0) {
        container.innerHTML = '<div class="no-products">No products found matching your search</div>';
        return;
    }

    filtered.forEach((product) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.style.cursor = 'pointer';

        // Get condition color
        const conditionColor = getConditionColor(product.condition);

        card.innerHTML = `
            <img src="${product.imageUrl}" alt="${product.name}" class="device-icon">
            <div class="card-content">
                <h3 class="heading">${product.name}</h3>
                <div class="condition-badge" style="background-color: ${conditionColor};">${product.condition}</div>
                <div class="specs">${product.specs.replace(/\n/g, '<br>')}</div>
                <div class="price">${product.price}</div>
                <div class="button-container">
                    <button class="btn1" data-action="buy" data-product-id="${product.id}">Buy</button>
                    <button class="btn2" data-action="add-to-cart" data-product-id="${product.id}">Add to Cart</button>
                </div>
            </div>
        `;

        // Add click event to card
        card.addEventListener('click', function(e) {
            const clickedButton = e.target.closest('button');
            if (!clickedButton) {
                navigateToProduct(product.id);
            }
        });

        // Add click event to Buy button
        const buyButton = card.querySelector('.btn1');
        if (buyButton) {
            buyButton.addEventListener('click', function(e) {
                e.stopPropagation();
                navigateToProduct(product.id);
            });
        }

        // Add click event to Add to Cart button
        const cartButton = card.querySelector('.btn2');
        if (cartButton) {
            cartButton.addEventListener('click', function(e) {
                e.stopPropagation();
                showToast('Add to cart functionality coming soon');
            });
        }

        container.appendChild(card);
    });
}

// Initialize on page load - automatically load all devices
document.addEventListener('DOMContentLoaded', function() {
    // Populate allProducts from database
    if (typeof dbProducts !== 'undefined' && dbProducts.length > 0) {
        allProducts = dbProducts.map(formatProductForDisplay);
        currentProducts = allProducts;
        console.log('allProducts populated:', allProducts.length);
    } else {
        console.log('No products loaded from database');
        allProducts = [];
        currentProducts = [];
    }

    // Show content by default
    const contentWrapper = document.getElementById('content');
    if (contentWrapper) {
        contentWrapper.classList.add('active');
    }

    // Generate products
    generateProducts(currentProducts);

    // Mark "All Devices" button as active
    const firstDeviceBtn = document.querySelectorAll('.device-btn')[0];
    if (firstDeviceBtn) {
        firstDeviceBtn.classList.add('active');
    }
});
