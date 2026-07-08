<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (support both session formats)
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] || isset($_SESSION['user_id']);
if (!$is_logged_in) {
    header('Location: Login.php');
    exit;
}

// Check if user is admin or clerk
$allowed_roles = ['admin', 'clerk'];
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';
if (!in_array($user_role, $allowed_roles)) {
    header('Location: Index.php');
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle order actions - return JSON response for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        $order_id = intval($_POST['order_id']);
        
        if ($_POST['action'] === 'ready_for_pickup') {
            $pickup_location = isset($_POST['pickup_location']) ? trim($_POST['pickup_location']) : '';
            
            // Validate pickup location is not empty
            if (empty($pickup_location)) {
                http_response_code(400);
                echo json_encode(['error' => 'Pickup location is required']);
                exit;
            }
            
            // Handle file upload - store binary data directly in database
            $proof_pictures = NULL;
            if (isset($_FILES['proof_pictures']) && isset($_FILES['proof_pictures']['tmp_name'][0]) && $_FILES['proof_pictures']['tmp_name'][0] !== '') {
                $files = $_FILES['proof_pictures'];
                $file_count = count($files['name']);
                
                // For now, we'll store the first image as binary data
                // If you need multiple images, you could create a separate table
                if ($files['error'][0] === UPLOAD_ERR_OK) {
                    $proof_pictures = file_get_contents($files['tmp_name'][0]);
                }
            }
            
            // Build query based on whether proof picture was uploaded
            if ($proof_pictures !== NULL) {
                $update_query = "UPDATE orders SET status = 'ready_for_pickup', pickup_location = ?, proof_pictures = ? WHERE id = ?";
                $stmt = $mysqli->prepare($update_query);
                
                if (!$stmt) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Database error: ' . $mysqli->error]);
                    exit;
                }
                
                $stmt->bind_param('sbi', $pickup_location, $proof_pictures, $order_id);
                $stmt->send_long_data(1, $proof_pictures);
            } else {
                $update_query = "UPDATE orders SET status = 'ready_for_pickup', pickup_location = ? WHERE id = ?";
                $stmt = $mysqli->prepare($update_query);
                
                if (!$stmt) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Database error: ' . $mysqli->error]);
                    exit;
                }
                
                $stmt->bind_param('si', $pickup_location, $order_id);
            }
            
            if (!$stmt->execute()) {
                http_response_code(500);
                echo json_encode(['error' => 'Update failed: ' . $stmt->error]);
                $stmt->close();
                exit;
            }
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected_rows === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Order not found or already updated']);
                exit;
            }
            
            $result = $mysqli->query("SELECT user_id FROM orders WHERE id = $order_id");
            if ($result && $row = $result->fetch_assoc()) {
                $user_id = intval($row['user_id']);
                $status_message = "Your order #$order_id is ready for pickup at: $pickup_location";
                $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'order', NOW())";
                $stmt = $mysqli->prepare($notification_query);
                $stmt->bind_param('is', $user_id, $status_message);
                $stmt->execute();
                $stmt->close();
            }
            
            http_response_code(200);
            echo json_encode(['success' => 'Order marked as ready for pickup']);
            exit;
        } elseif ($_POST['action'] === 'mark_completed') {
            // First check current status
            $current_status_result = $mysqli->query("SELECT status FROM orders WHERE id = $order_id");
            if ($current_status_result && $row = $current_status_result->fetch_assoc()) {
                $current_status = $row['status'];
                error_log("Current status for order $order_id: " . $current_status);
            }
            
            // Check if completed_at column exists
            $has_completed_at = $mysqli->query("SHOW COLUMNS FROM orders LIKE 'completed_at'");
            
            if ($has_completed_at && $has_completed_at->num_rows > 0) {
                $update_query = "UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = ?";
            } else {
                $update_query = "UPDATE orders SET status = 'completed' WHERE id = ?";
            }
            
            $stmt = $mysqli->prepare($update_query);
            
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error: ' . $mysqli->error]);
                exit;
            }
            
            $stmt->bind_param('i', $order_id);
            
            if (!$stmt->execute()) {
                http_response_code(500);
                echo json_encode(['error' => 'Update failed: ' . $stmt->error]);
                $stmt->close();
                exit;
            }
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected_rows === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Order not found or already updated']);
                exit;
            }
            
            // Verify the update
            $check_result = $mysqli->query("SELECT status FROM orders WHERE id = $order_id");
            if ($check_result && $row = $check_result->fetch_assoc()) {
                $actual_status = $row['status'];
                error_log("Status after update for order $order_id: " . $actual_status);
                if ($actual_status !== 'completed') {
                    http_response_code(500);
                    echo json_encode(['error' => 'Status verification failed. Expected: completed, Actual: ' . $actual_status]);
                    exit;
                }
            }
            
            $result = $mysqli->query("SELECT user_id FROM orders WHERE id = $order_id");
            if ($result && $row = $result->fetch_assoc()) {
                $user_id = intval($row['user_id']);
                $status_message = "Your order #$order_id has been completed and picked up.";
                $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'order', NOW())";
                $stmt = $mysqli->prepare($notification_query);
                $stmt->bind_param('is', $user_id, $status_message);
                $stmt->execute();
                $stmt->close();
            }
            
            http_response_code(200);
            echo json_encode(['success' => 'Order marked as completed']);
            exit;
        }
    }
}

// Get filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "SELECT o.*, o.total_amount AS total, u.username FROM orders o JOIN users u ON o.user_id = u.id";
if ($status_filter) {
    $query .= " WHERE o.status = '$status_filter'";
}

// Check if order_date column exists
$has_order_date = $mysqli->query("SHOW COLUMNS FROM orders LIKE 'order_date'");
if ($has_order_date && $has_order_date->num_rows > 0) {
    $query .= " ORDER BY o.order_date DESC";
} else {
    $query .= " ORDER BY o.id DESC";
}

$orders = $mysqli->query($query);
?>

<div class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Order Management</h2>
        <div>
            <select onchange="window.location.href='admin.php?section=orders&status='+this.value" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="packing" <?php echo $status_filter === 'packing' ? 'selected' : ''; ?>>Packing</option>
                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                <option value="ready_for_pickup" <?php echo $status_filter === 'ready_for_pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($order = $orders->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                    <td><?php echo number_format($order['total'], 2); ?></td>
                    <td>
                        <?php 
                        $status = $order['status'];
                        $status_colors = [
                            'pending' => '#ffc107',
                            'confirmed' => '#17a2b8',
                            'packing' => '#6f42c1',
                            'processing' => '#fd7e14',
                            'ready_for_pickup' => '#10b981',
                            'shipped' => '#007bff',
                            'delivered' => '#20c997',
                            'completed' => '#10b981',
                            'cancelled' => '#dc3545'
                        ];
                        $color = $status_colors[$status] ?? '#6c757d';
                        ?>
                        <span style="padding: 6px 12px; border-radius: 4px; background-color: <?php echo $color; ?>; color: white; font-weight: 500;"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                    </td>
                    <td><?php echo isset($order['created_at']) ? date('M d, Y H:i', strtotime($order['created_at'])) : 'N/A'; ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="viewOrder(<?php echo $order['id']; ?>)">View</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteOrder(<?php echo $order['id']; ?>)">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- View Order Modal -->
<div id="viewOrderModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3>Order Details</h3>
            <button class="close-modal" onclick="closeModal('viewOrderModal')">&times;</button>
        </div>
        <div id="orderDetails"></div>
    </div>
</div>

<!-- Ready for Pickup Modal -->
<div id="readyForPickupModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Mark Order Ready for Pickup</h3>
            <button class="close-modal" onclick="closeModal('readyForPickupModal')">&times;</button>
        </div>
        <form id="readyForPickupForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="action" value="ready_for_pickup">
            <input type="hidden" name="order_id" id="pickupOrderId">
            
            <div class="form-group">
                <label for="pickupLocation"><strong>Pickup Location:</strong></label>
                <input type="text" id="pickupLocation" name="pickup_location" placeholder="Enter pickup location (e.g., Store A, Counter 2)" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div class="form-group">
                <label for="proofPictures"><strong>Proof Pictures (Optional):</strong></label>
                <input type="file" id="proofPictures" name="proof_pictures[]" multiple accept="image/*" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                <small style="display: block; margin-top: 5px; color: #666;">You can upload multiple images as proof of the order preparation.</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('readyForPickupModal')" style="padding: 8px 16px; border-radius: 4px; border: none; cursor: pointer; font-weight: 500;">Cancel</button>
                <button type="submit" class="btn btn-success" style="background-color: #10b981; padding: 8px 16px; border-radius: 6px; border: none; color: white; cursor: pointer; font-weight: 500; transition: background-color 0.3s ease;">Mark Ready for Pickup</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewOrder(id) {
    fetch('get_order_details.php?id=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                console.error('API Error:', data.error);
                return;
            }
            
            // Status color mapping
            const statusColors = {
                'pending': '#ffc107',
                'confirmed': '#17a2b8',
                'packing': '#6f42c1',
                'processing': '#fd7e14',
                'ready_for_pickup': '#10b981',
                'shipped': '#007bff',
                'delivered': '#20c997',
                'completed': '#10b981',
                'cancelled': '#dc3545'
            };
            
            const statusColor = statusColors[data.status] || '#6c757d';
            
            let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
            html += '<div><strong>Order ID:</strong> #' + data.id + '</div>';
            html += '<div><strong>Customer:</strong> ' + data.username + '</div>';
            html += '<div><strong>Total:</strong> RM' + parseFloat(data.total).toFixed(2) + '</div>';
            html += '<div><strong>Status:</strong> <span style="padding: 6px 12px; border-radius: 4px; background-color: ' + statusColor + '; color: white; font-weight: 500;">' + data.status.replace(/_/g, ' ').toUpperCase() + '</span></div>';
            
            if (data.pickup_location) {
                html += '<div><strong>Pickup Location:</strong> ' + data.pickup_location + '</div>';
            }
            
            if (data.created_at) {
                html += '<div><strong>Date:</strong> ' + new Date(data.created_at).toLocaleString() + '</div>';
            }
            
            // Display proof picture if exists
            if (data.proof_pictures) {
                html += '<div style="margin-top: 20px;"><strong>Proof Picture:</strong></div>';
                html += '<img src="get_proof_picture.php?order_id=' + data.id + '" style="max-width: 100%; max-height: 300px; margin-top: 10px; border: 1px solid #ddd; border-radius: 4px;">';
            }
            
            html += '</div>';
            
            html += '<h4 style="margin-top: 20px;">Order Items</h4>';
            html += '<table style="margin-top: 10px; width: 100%; border-collapse: collapse;">';
            html += '<thead><tr style="background-color: #f5f5f5;"><th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Product</th><th style="padding: 10px; text-align: center; border-bottom: 1px solid #ddd;">Quantity</th><th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">Price</th></tr></thead>';
            html += '<tbody>';
            
            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    html += '<tr style="border-bottom: 1px solid #ddd;">';
                    html += '<td style="padding: 10px;">' + (item.product_name || 'N/A') + '</td>';
                    html += '<td style="padding: 10px; text-align: center;">' + (item.quantity || 0) + '</td>';
                    html += '<td style="padding: 10px; text-align: right;">RM' + parseFloat(item.price || 0).toFixed(2) + '</td>';
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="3" style="padding: 10px; text-align: center;">No items found</td></tr>';
            }
            html += '</tbody></table>';
            
            // Add action buttons
            html += '<div style="margin-top: 20px; display: flex; gap: 10px;">';
            if (data.status !== 'ready_for_pickup' && data.status !== 'completed') {
                html += '<button class="btn btn-success" style="background-color: #10b981; padding: 8px 16px; border-radius: 6px; border: none; color: white; cursor: pointer; font-weight: 500; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor=\'#059669\';" onmouseout="this.style.backgroundColor=\'#10b981\';" onclick="openReadyForPickupForm(' + data.id + ')">Mark Ready for Pickup</button>';
            }
            if (data.status === 'ready_for_pickup') {
                html += '<button class="btn btn-primary" style="background-color: #10b981; padding: 8px 16px; border-radius: 6px; border: none; color: white; cursor: pointer; font-weight: 500; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor=\'#059669\';" onmouseout="this.style.backgroundColor=\'#10b981\';" onclick="markOrderCompleted(' + data.id + ')">Mark as Completed</button>';
            }
            html += '</div>';
            
            document.getElementById('orderDetails').innerHTML = html;
            openModal('viewOrderModal');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading order details: ' + error.message);
        });
}

function openReadyForPickupForm(orderId) {
    document.getElementById('pickupOrderId').value = orderId;
    document.getElementById('pickupLocation').value = '';
    document.getElementById('proofPictures').value = '';
    closeModal('viewOrderModal');
    openModal('readyForPickupModal');
}

function markOrderCompleted(orderId) {
    if (confirm('Mark this order as completed?')) {
        const formData = new FormData();
        formData.append('action', 'mark_completed');
        formData.append('order_id', orderId);
        
        console.log('Sending mark_completed request for order:', orderId);
        
        fetch('admin.php?section=orders', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text().then(text => {
                console.log('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.error) {
                alert('Error: ' + data.error);
            } else if (data.success) {
                alert(data.success);
                closeModal('viewOrderModal');
                setTimeout(() => location.reload(), 100);
            } else {
                alert('Order marked as completed!');
                closeModal('viewOrderModal');
                setTimeout(() => location.reload(), 100);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred: ' + error.message);
        });
    }
}

function deleteOrder(id) {
    document.getElementById('deleteOrderId').value = id;
    openModal('deleteOrderModal');
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'flex';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const readyForPickupForm = document.getElementById('readyForPickupForm');
    if (readyForPickupForm) {
        readyForPickupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('admin.php?section=orders', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.log('Response text:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else if (data.success) {
                    alert(data.success);
                    closeModal('readyForPickupModal');
                    location.reload();
                } else {
                    alert('Order marked as ready for pickup!');
                    closeModal('readyForPickupModal');
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again. Check console for details.');
            });
        });
    }
});
</script>
</script>

<!-- Delete Order Modal -->
<div id="deleteOrderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Order</h3>
            <button class="close-modal" onclick="closeModal('deleteOrderModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete_order">
            <input type="hidden" name="order_id" id="deleteOrderId">
            <p>Are you sure you want to delete this order? This action cannot be undone.</p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteOrderModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Order</button>
            </div>
        </form>
    </div>
</div>
