<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_user') {
            $user_id = intval($_POST['user_id']);
            $mysqli->query("DELETE FROM users WHERE id = $user_id");
        } elseif ($_POST['action'] === 'update_role') {
            $user_id = intval($_POST['user_id']);
            $role = $_POST['role'];
            $mysqli->query("UPDATE users SET role = '$role' WHERE id = $user_id");
        }
    }
}

// Get role filter and search term
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filter and search
$query = "SELECT id, username, email, role, created_at FROM users WHERE 1=1";

if ($role_filter) {
    $query .= " AND role = '$role_filter'";
}

if ($search_term) {
    $search_escaped = $mysqli->real_escape_string($search_term);
    $query .= " AND (username LIKE '%$search_escaped%' OR email LIKE '%$search_escaped%')";
}

$query .= " ORDER BY created_at DESC";
$users = $mysqli->query($query);
?>

<div class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>User Management</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" id="searchInput" placeholder="Search users..." value="<?php echo htmlspecialchars($search_term); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; width: 200px;" onkeyup="handleSearch(event)">
            <ul class="role-menu">
                <li class="role-item">
                    <div class="role-link">
                        <span><?php echo $role_filter ? ucfirst($role_filter) : 'All Roles'; ?></span>
                        <svg class="role-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <ul class="role-submenu">
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByRole('')">All Roles</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByRole('user')">User</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByRole('admin')">Admin</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByRole('clerk')">Clerk</div>
                        </li>
                        <li class="role-submenu-item">
                            <div class="role-submenu-link" onclick="filterByRole('technician')">Technician</div>
                        </li>
                    </ul>
                </li>
            </ul>
            <button class="btn btn-primary" onclick="openModal('addUserModal')">Add User</button>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($user = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <ul class="role-menu" data-user-id="<?php echo $user['id']; ?>">
                            <li class="role-item">
                                <div class="role-link">
                                    <span><?php echo ucfirst($user['role']); ?></span>
                                    <svg class="role-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <ul class="role-submenu">
                                    <li class="role-submenu-item">
                                        <div class="role-submenu-link" data-value="user">User</div>
                                    </li>
                                    <li class="role-submenu-item">
                                        <div class="role-submenu-link" data-value="admin">Admin</div>
                                    </li>
                                    <li class="role-submenu-item">
                                        <div class="role-submenu-link" data-value="clerk">Clerk</div>
                                    </li>
                                    <li class="role-submenu-item">
                                        <div class="role-submenu-link" data-value="technician">Technician</div>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New User</h3>
            <button class="close-modal" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="admin_add_user.php">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Role *</label>
                <select name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                    <option value="clerk">Clerk</option>
                    <option value="technician">Technician</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<style>
.role-menu {
    font-size: 14px;
    line-height: 1.6;
    color: #333;
    width: fit-content;
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
}

.role-link {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.48s cubic-bezier(0.23, 1, 0.32, 1);
    background-color: #f3f4f6;
    cursor: pointer;
}

.role-link::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: #10b981;
    z-index: -1;
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.48s cubic-bezier(0.23, 1, 0.32, 1);
}

.role-link span {
    text-transform: capitalize;
}

.role-arrow {
    fill: #333;
    transition: all 0.48s cubic-bezier(0.23, 1, 0.32, 1);
}

.role-item {
    position: relative;
}

.role-submenu {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: absolute;
    top: 100%;
    border-radius: 0 0 8px 8px;
    left: 0;
    width: 100%;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-12px);
    transition: all 0.48s cubic-bezier(0.23, 1, 0.32, 1);
    z-index: 1;
    pointer-events: none;
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: white;
}

.role-item:hover .role-submenu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
    border-top: transparent;
    border-color: #10b981;
}

.role-item:hover .role-link {
    color: #ffffff;
    border-radius: 8px 8px 0 0;
}

.role-item:hover .role-link::after {
    transform: scaleX(1);
    transform-origin: right;
}

.role-item:hover .role-link .role-arrow {
    fill: #ffffff;
    transform: rotate(-180deg);
}

.role-submenu-item {
    width: 100%;
    transition: all 0.48s cubic-bezier(0.23, 1, 0.32, 1);
}

.role-submenu-link {
    display: block;
    padding: 8px 16px;
    width: 100%;
    position: relative;
    text-align: center;
    transition: all 0.48s cubic-bezier(0.23, 1, 0.32, 1);
    cursor: pointer;
    text-transform: capitalize;
}

.role-submenu-item:last-child .role-submenu-link {
    border-bottom: none;
}

.role-submenu-link::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    transform: scaleX(0);
    width: 100%;
    height: 100%;
    background-color: #10b981;
    z-index: -1;
    transform-origin: left;
    transition: transform 0.48s cubic-bezier(0.23, 1, 0.32, 1);
}

.role-submenu-link:hover::before {
    transform: scaleX(1);
    transform-origin: right;
}

.role-submenu-link:hover {
    color: #ffffff;
}
</style>

<script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'flex';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
}

function deleteUser(userId) {
    document.getElementById('deleteUserId').value = userId;
    openModal('deleteUserModal');
}

function updateUserRole(userId, role) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="update_role"><input type="hidden" name="user_id" value="' + userId + '"><input type="hidden" name="role" value="' + role + '">';
    document.body.appendChild(form);
    form.submit();
}

function filterByRole(role) {
    const url = new URL(window.location.href);
    if (role) {
        url.searchParams.set('role', role);
    } else {
        url.searchParams.delete('role');
    }
    window.location.href = url.toString();
}

function handleSearch(event) {
    if (event.key === 'Enter') {
        const searchTerm = document.getElementById('searchInput').value;
        const url = new URL(window.location.href);
        if (searchTerm.trim()) {
            url.searchParams.set('search', searchTerm.trim());
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }
}

// Handle custom dropdown clicks for user role changes
document.querySelectorAll('.role-menu[data-user-id] .role-submenu-link').forEach(option => {
    option.addEventListener('click', function(e) {
        e.stopPropagation();
        const roleMenu = this.closest('.role-menu');
        const userId = roleMenu.dataset.userId;
        const newRole = this.dataset.value;
        const roleName = this.textContent;

        // Update the selected role display
        roleMenu.querySelector('.role-link span').textContent = roleName;

        // Submit the form
        updateUserRole(userId, newRole);
    });
});
</script>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete User</h3>
            <button class="close-modal" onclick="closeModal('deleteUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" id="deleteUserId">
            <p>Are you sure you want to delete this user? This action cannot be undone.</p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteUserModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete User</button>
            </div>
        </form>
    </div>
</div>
