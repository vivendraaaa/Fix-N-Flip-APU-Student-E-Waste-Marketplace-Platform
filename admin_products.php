<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_product') {
            $name = $mysqli->real_escape_string($_POST['name']);
            $brand = $mysqli->real_escape_string($_POST['brand']);
            $specs = isset($_POST['specs']) ? $mysqli->real_escape_string($_POST['specs']) : '';
            $condition = $mysqli->real_escape_string($_POST['condition']);
            $accessories = isset($_POST['accessories']) ? $mysqli->real_escape_string($_POST['accessories']) : '';
            $price = floatval($_POST['price']);
            $category = $mysqli->real_escape_string($_POST['category']);

            // Check if specs column exists
            $check_specs = $mysqli->query("SHOW COLUMNS FROM products LIKE 'specs'");
            $has_specs = $check_specs && $check_specs->num_rows > 0;

            // Check if condition column exists
            $check_condition = $mysqli->query("SHOW COLUMNS FROM products LIKE 'condition'");
            $has_condition = $check_condition && $check_condition->num_rows > 0;

            // Check if accessories column exists
            $check_accessories = $mysqli->query("SHOW COLUMNS FROM products LIKE 'accessories'");
            $has_accessories = $check_accessories && $check_accessories->num_rows > 0;

            if ($has_specs && $has_condition && $has_accessories) {
                $mysqli->query("INSERT INTO products (name, brand, specs, `condition`, accessories, price, category, created_at) VALUES ('$name', '$brand', '$specs', '$condition', '$accessories', $price, '$category', NOW())");
            } elseif ($has_specs && $has_condition) {
                $mysqli->query("INSERT INTO products (name, brand, specs, `condition`, price, category, created_at) VALUES ('$name', '$brand', '$specs', '$condition', $price, '$category', NOW())");
            } elseif ($has_specs) {
                $mysqli->query("INSERT INTO products (name, brand, specs, price, category, created_at) VALUES ('$name', '$brand', '$specs', $price, '$category', NOW())");
            } else {
                $mysqli->query("INSERT INTO products (name, brand, price, category, created_at) VALUES ('$name', '$brand', $price, '$category', NOW())");
            }

            $product_id = $mysqli->insert_id;

            // Handle multiple image uploads
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] == 0) {
                        $image_data = file_get_contents($tmp_name);
                        $image = $mysqli->real_escape_string($image_data);
                        $mysqli->query("INSERT INTO product_images (product_id, image) VALUES ($product_id, '$image')");
                    }
                }
            }

            // Use JavaScript redirect since this file is included
            echo "<script>window.location.href='admin.php?section=products';</script>";
            exit();
        } elseif ($_POST['action'] === 'update_product') {
            $id = intval($_POST['product_id']);
            $name = $mysqli->real_escape_string($_POST['name']);
            $brand = $mysqli->real_escape_string($_POST['brand']);
            $specs = isset($_POST['specs']) ? $mysqli->real_escape_string($_POST['specs']) : '';
            $condition = $mysqli->real_escape_string($_POST['condition']);
            $accessories = isset($_POST['accessories']) ? $mysqli->real_escape_string($_POST['accessories']) : '';
            $price = floatval($_POST['price']);
            $category = $mysqli->real_escape_string($_POST['category']);

            // Check if specs column exists
            $check_specs = $mysqli->query("SHOW COLUMNS FROM products LIKE 'specs'");
            $has_specs = $check_specs && $check_specs->num_rows > 0;

            // Check if condition column exists
            $check_condition = $mysqli->query("SHOW COLUMNS FROM products LIKE 'condition'");
            $has_condition = $check_condition && $check_condition->num_rows > 0;

            // Check if accessories column exists
            $check_accessories = $mysqli->query("SHOW COLUMNS FROM products LIKE 'accessories'");
            $has_accessories = $check_accessories && $check_accessories->num_rows > 0;

            if ($has_specs && $has_condition && $has_accessories) {
                $query = "UPDATE products SET name='$name', brand='$brand', specs='$specs', `condition`='$condition', accessories='$accessories', price=$price, category='$category'";
            } elseif ($has_specs && $has_condition) {
                $query = "UPDATE products SET name='$name', brand='$brand', specs='$specs', `condition`='$condition', price=$price, category='$category'";
            } elseif ($has_specs) {
                $query = "UPDATE products SET name='$name', brand='$brand', specs='$specs', price=$price, category='$category'";
            } else {
                $query = "UPDATE products SET name='$name', brand='$brand', price=$price, category='$category'";
            }

            $query .= " WHERE id=$id";
            $mysqli->query($query);

            // Handle multiple image uploads - only add new images (existing ones are already managed via delete API)
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] == 0) {
                        $image_data = file_get_contents($tmp_name);
                        $image = $mysqli->real_escape_string($image_data);
                        $mysqli->query("INSERT INTO product_images (product_id, image) VALUES ($id, '$image')");
                    }
                }
            }

            // Use JavaScript redirect since this file is included
            echo "<script>window.location.href='admin.php?section=products';</script>";
            exit();
        } elseif ($_POST['action'] === 'delete_product') {
            $id = intval($_POST['product_id']);
            $mysqli->query("DELETE FROM products WHERE id = $id");
        }
    }
}

// Get category filter
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build query with filter
$query = "SELECT * FROM products";
if ($category_filter) {
    $query .= " WHERE category = '$category_filter'";
}
$query .= " ORDER BY created_at DESC";
$products = $mysqli->query($query);

// Fetch images for each product
$products_with_images = [];
if ($products) {
    while ($product = $products->fetch_assoc()) {
        $product_id = $product['id'];
        $images_result = $mysqli->query("SELECT id FROM product_images WHERE product_id = $product_id ORDER BY id ASC LIMIT 1");
        $first_image_id = null;
        if ($images_result && $images_result->num_rows > 0) {
            $img_row = $images_result->fetch_assoc();
            $first_image_id = $img_row['id'];
        }
        $product['first_image_id'] = $first_image_id;
        $products_with_images[] = $product;
    }
}
$products = $products_with_images;
?>

<div class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Product Management</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <ul class="role-menu">
                <li class="role-item">
                    <div class="role-link">
                        <span><?php echo $category_filter ? $category_filter : 'All Categories'; ?></span>
                        <svg class="role-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <ul class="role-submenu">
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByCategory('')">All Categories</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByCategory('Smartphone')">Smartphone</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByCategory('Tablet')">Tablet</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByCategory('Laptop')">Laptop</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByCategory('Smartwatch')">Smartwatch</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByCategory('Accessories')">Accessories</div>
                        </li>
                    </ul>
                </li>
            </ul>
            <button class="btn btn-primary" onclick="openModal('addProductModal')">Add Product</button>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Brand</th>
                <th>Condition</th>
                <th>Category</th>
                <th>Price</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['id']; ?></td>
                    <td>
                        <?php if (isset($product['first_image_id']) && $product['first_image_id']): ?>
                            <img src="get_product_image.php?id=<?php echo $product['first_image_id']; ?>" class="image-preview" style="width: 50px; height: 50px;">
                        <?php else: ?>
                            <span style="color: #999;">No image</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo isset($product['name']) ? htmlspecialchars($product['name']) : 'N/A'; ?></td>
                    <td><?php echo isset($product['brand']) ? htmlspecialchars($product['brand']) : 'N/A'; ?></td>
                    <td><?php echo isset($product['condition']) ? htmlspecialchars($product['condition']) : 'N/A'; ?></td>
                    <td><?php echo isset($product['category']) ? htmlspecialchars($product['category']) : 'N/A'; ?></td>
                    <td>RM<?php echo number_format($product['price'], 2); ?></td>
                    <td><?php echo isset($product['created_at']) ? date('M d, Y', strtotime($product['created_at'])) : 'N/A'; ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="editProduct(<?php echo $product['id']; ?>)">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteProduct(<?php echo $product['id']; ?>)">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Product Modal -->
<div id="addProductModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Product</h3>
            <button class="close-modal" onclick="closeModal('addProductModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_product">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Brand *</label>
                <input type="text" name="brand" required>
            </div>
            <div class="form-group">
                <label>Specs (separate by comma)</label>
                <input type="text" name="specs" placeholder="e.g., Mediatek CPU, 256GB ROM, 12GB RAM, Black Colour">
            </div>
            <div class="form-group">
                <label>Condition *</label>
                <select name="condition" required>
                    <option value="Excellent">Excellent</option>
                    <option value="Like New">Like New</option>
                    <option value="Good" selected>Good</option>
                    <option value="Fair">Fair</option>
                </select>
            </div>
            <div class="form-group">
                <label>Accessories (separate by comma)</label>
                <input type="text" name="accessories" placeholder="e.g., Charger, Case, Earphones">
            </div>
            <div class="form-group">
                <label>Price (RM) *</label>
                <input type="number" step="0.01" name="price" required>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category" required>
                    <option value="Smartphone">Smartphone</option>
                    <option value="Tablet">Tablet</option>
                    <option value="Laptop">Laptop</option>
                    <option value="Smartwatch">Smartwatch</option>
                    <option value="Accessories">Accessories</option>
                </select>
            </div>
            <div class="form-group">
                <label>Product Images (multiple)</label>
                <input type="file" name="images[]" accept="image/*" multiple id="addProductImages" onchange="handleImageSelect(this, 'addProductImageList')">
                <div id="addProductImageList" class="image-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Product</h3>
            <button class="close-modal" onclick="closeModal('editProductModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" id="editProductId">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" id="editProductName" required>
            </div>
            <div class="form-group">
                <label>Brand *</label>
                <input type="text" name="brand" id="editProductBrand" required>
            </div>
            <div class="form-group">
                <label>Specs (separate by comma)</label>
                <input type="text" name="specs" id="editProductSpecs" placeholder="e.g., Mediatek CPU, 256GB ROM, 12GB RAM, Black Colour">
            </div>
            <div class="form-group">
                <label>Condition *</label>
                <select name="condition" id="editProductCondition" required>
                    <option value="Excellent">Excellent</option>
                    <option value="Like New">Like New</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                </select>
            </div>
            <div class="form-group">
                <label>Accessories (separate by comma)</label>
                <input type="text" name="accessories" id="editProductAccessories" placeholder="e.g., Charger, Case, Earphones">
            </div>
            <div class="form-group">
                <label>Price (RM) *</label>
                <input type="number" step="0.01" name="price" id="editProductPrice" required>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category" id="editProductCategory" required>
                    <option value="Smartphone">Smartphone</option>
                    <option value="Tablet">Tablet</option>
                    <option value="Laptop">Laptop</option>
                    <option value="Smartwatch">Smartwatch</option>
                    <option value="Accessories">Accessories</option>
                </select>
            </div>
            <div class="form-group">
                <label>Product Images (multiple)</label>
                <input type="file" name="images[]" accept="image/*" multiple id="editProductImages" onchange="handleImageSelect(this, 'editProductImageList')">
                <div id="editProductImageList" class="image-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function editProduct(id) {
    // Clear existing files
    editProductFiles.length = 0;
    document.getElementById('editProductImageList').innerHTML = '';
    document.getElementById('editProductImages').value = '';

    fetch('get_product_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('editProductId').value = data.id;
            document.getElementById('editProductName').value = data.name;
            document.getElementById('editProductBrand').value = data.brand || '';
            document.getElementById('editProductSpecs').value = data.specs || '';
            document.getElementById('editProductCondition').value = data.condition || 'Good';
            document.getElementById('editProductAccessories').value = data.accessories || '';
            document.getElementById('editProductPrice').value = data.price;
            document.getElementById('editProductCategory').value = data.category;

            // Fetch existing images
            fetch(`get_product_images.php?product_id=${id}`)
                .then(response => response.json())
                .then(images => {
                    images.forEach(img => {
                        editProductFiles.push({
                            id: img.id,
                            name: 'Image ' + img.id,
                            existing: true,
                            url: 'get_product_image.php?id=' + img.id
                        });
                    });
                    updateImageList('editProductImageList', editProductFiles, document.getElementById('editProductImages'));
                });

            openModal('editProductModal');
        });
}
</script>

<link rel="stylesheet" href="admin_products.css">
<script src="admin_products.js"></script>
