<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $user_id = $_SESSION['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Get current user data
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $errors = [];

    // Check if username is taken by another user (only if changed)
    if ($username !== $user['username']) {
        $check_username = $mysqli->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_username->bind_param("si", $username, $user_id);
        $check_username->execute();
        if ($check_username->get_result()->num_rows > 0) {
            $errors[] = "Username is already taken by another user";
        }
    }

    // Check if email is taken by another user (only if changed)
    if ($email !== $user['email']) {
        $check_email = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = "Email is already taken by another user";
        }
    }

    // Verify current password if changing password
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "Current password is required to change password";
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters";
        } elseif (password_verify($new_password, $user['password'])) {
            $errors[] = "New password must be different from current password";
        }
    }

    if (empty($errors)) {
        // Update username, email, and phone
        $update = $mysqli->prepare("UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?");
        $update->bind_param("sssi", $username, $email, $phone, $user_id);
        $update->execute();

        // Update password if provided
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_pass->bind_param("si", $hashed_password, $user_id);
            $update_pass->execute();
        }

        // Update session
        $_SESSION['username'] = $username;
        $success = "Profile updated successfully";
    }
}

// Get current user data
$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<div class="content-section">
    <h2>Profile Settings</h2>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?php foreach ($errors as $error): ?>
                <?php echo $error; ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="action" value="update_profile">
        
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="e.g., 012-3456789">
        </div>

        <div class="form-group">
            <label for="current_password">Current Password</label>
            <div class="password-input-wrapper">
                <input type="password" id="current_password" name="current_password">
                <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label for="new_password">New Password</label>
            <div class="password-input-wrapper">
                <input type="password" id="new_password" name="new_password" minlength="6">
                <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <div class="password-input-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" minlength="6">
                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label>Account Information</label>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                <p><strong>Role:</strong> <?php echo ucfirst($user['role']); ?></p>
                <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'] ?? 'now')); ?></p>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Update Profile</button>
    </form>
</div>
