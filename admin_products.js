// Store selected files for each modal
const addProductFiles = [];
const editProductFiles = [];

function handleImageSelect(input, listId) {
    const list = document.getElementById(listId);
    const filesArray = listId === 'addProductImageList' ? addProductFiles : editProductFiles;

    // Add new files to the array with preview URLs
    for (let i = 0; i < input.files.length; i++) {
        const file = input.files[i];
        const reader = new FileReader();
        reader.onload = function(e) {
            filesArray.push({
                file: file,
                name: file.name,
                existing: false,
                url: e.target.result
            });
            // Update the file input with all files
            const dt = new DataTransfer();
            filesArray.forEach(f => {
                if (f.file) {
                    dt.items.add(f);
                }
            });
            input.files = dt.files;
            // Update the display
            updateImageList(listId, filesArray, input);
        };
        reader.readAsDataURL(file);
    }
}

function updateImageList(listId, filesArray, input) {
    const list = document.getElementById(listId);
    list.innerHTML = '';

    for (let i = 0; i < filesArray.length; i++) {
        const file = filesArray[i];
        const item = document.createElement('div');
        item.className = 'image-item';
        const imageUrl = file.url || (file.file ? URL.createObjectURL(file.file) : '');
        item.innerHTML = `
            <button type="button" class="remove-image" onclick="removeImage('${listId}', ${i})">×</button>
            <img src="${imageUrl}" alt="${file.name}" class="image-preview">
            <span class="image-name">${file.name}</span>
        `;
        list.appendChild(item);
    }
}

function removeImage(listId, index) {
    const input = document.getElementById(listId === 'addProductImageList' ? 'addProductImages' : 'editProductImages');
    const filesArray = listId === 'addProductImageList' ? addProductFiles : editProductFiles;

    const file = filesArray[index];

    // If it's an existing image, delete it from database
    if (file.existing) {
        fetch('delete_product_image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'image_id=' + file.id
        });
    }

    // Remove the file from the array
    filesArray.splice(index, 1);

    // Update the file input with remaining files (only new files)
    const dt = new DataTransfer();
    filesArray.forEach(f => {
        if (!f.existing) {
            dt.items.add(f);
        }
    });
    input.files = dt.files;

    // Update the display
    updateImageList(listId, filesArray, input);
}

// Clear files when modal is closed
function clearImageFiles(listId) {
    const filesArray = listId === 'addProductImageList' ? addProductFiles : editProductFiles;
    filesArray.length = 0;
    const list = document.getElementById(listId);
    list.innerHTML = '';
    const input = document.getElementById(listId === 'addProductImageList' ? 'addProductImages' : 'editProductImages');
    input.value = '';
}

// Override closeModal to clear image files
const originalCloseModal = closeModal;
function closeModal(modalId) {
    if (modalId === 'addProductModal') {
        clearImageFiles('addProductImageList');
    } else if (modalId === 'editProductModal') {
        clearImageFiles('editProductImageList');
    }
    originalCloseModal(modalId);
}

function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete_product"><input type="hidden" name="product_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function filterByCategory(category) {
    const url = new URL(window.location.href);
    if (category) {
        url.searchParams.set('category', category);
    } else {
        url.searchParams.delete('category');
    }
    window.location.href = url.toString();
}
