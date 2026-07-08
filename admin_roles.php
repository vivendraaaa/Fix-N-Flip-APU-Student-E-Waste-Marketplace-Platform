<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle role actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_role') {
            $role_name = $mysqli->real_escape_string($_POST['role_name']);
            $permissions = json_encode($_POST['permissions'] ?? []);
            $mysqli->query("INSERT INTO roles (name, permissions) VALUES ('$role_name', '$permissions')");
        } elseif ($_POST['action'] === 'update_role') {
            $role_id = intval($_POST['role_id']);
            $role_name = $mysqli->real_escape_string($_POST['role_name']);
            $permissions = json_encode($_POST['permissions'] ?? []);
            $mysqli->query("UPDATE roles SET name='$role_name', permissions='$permissions' WHERE id=$role_id");
        } elseif ($_POST['action'] === 'delete_role') {
            $role_id = intval($_POST['role_id']);
            $mysqli->query("DELETE FROM roles WHERE id = $role_id");
        }
    }
}

// Get all roles
$roles = $mysqli->query("SELECT * FROM roles ORDER BY id ASC");

// Get users by role (case-insensitive)
$users_by_role = [];
$users = $mysqli->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $users->fetch_assoc()) {
    $users_by_role[strtolower($row['role'])] = $row['count'];
}
?>

<div class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Role Management</h2>
        <button class="btn btn-primary" onclick="openModal('addRoleModal')">Add Role</button>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Role Name</th>
                <th>Users Count</th>
                <th>Permissions</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($role = $roles->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $role['id']; ?></td>
                    <td><?php echo htmlspecialchars($role['name']); ?></td>
                    <td><?php echo isset($users_by_role[strtolower($role['name'])]) ? $users_by_role[strtolower($role['name'])] : 0; ?></td>
                    <td>
                        <?php
                        $permissions = json_decode($role['permissions'], true);
                        if ($permissions && is_array($permissions)) {
                            echo implode(', ', $permissions);
                        } else {
                            echo 'All permissions';
                        }
                        ?>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="editRole(<?php echo $role['id']; ?>)">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteRole(<?php echo $role['id']; ?>)">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add Role Modal -->
<div id="addRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Role</h3>
            <button class="close-modal" onclick="closeModal('addRoleModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_role">
            <div class="form-group">
                <label>Role Name *</label>
                <input type="text" name="role_name" required>
            </div>
            <div class="form-group">
                <label>Permissions</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <label><input type="checkbox" name="permissions[]" value="manage_users"> Manage Users</label>
                    <label><input type="checkbox" name="permissions[]" value="manage_products"> Manage Products</label>
                    <label><input type="checkbox" name="permissions[]" value="manage_orders"> Manage Orders</label>
                    <label><input type="checkbox" name="permissions[]" value="manage_submissions"> Manage Submissions</label>
                    <label><input type="checkbox" name="permissions[]" value="manage_roles"> Manage Roles</label>
                    <label><input type="checkbox" name="permissions[]" value="view_reports"> View Reports</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addRoleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="editRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Role</h3>
            <button class="close-modal" onclick="closeModal('editRoleModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="role_id" id="editRoleId">
            <div class="form-group">
                <label>Role Name *</label>
                <input type="text" name="name" id="editRoleName" required>
            </div>
            <div class="form-group">
                <label>Permissions</label>
                <div class="permissions-grid">
                    <label><input type="checkbox" name="permissions[]" value="manage_users"> Manage Users</label>
                    <label><input type="checkbox" name="permissions[]" value="manage_products"> Manage Products</label>
                    <label><input type="checkbox" name="permissions[]" value="manage_orders"> Manage Orders</label>
                    <label><input type="checkbox" name="permissions[]" value="manage_roles"> Manage Roles</label>
                    <label><input type="checkbox" name="permissions[]" value="view_reports"> View Reports</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editRoleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Role Modal -->
<div id="deleteRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Role</h3>
            <button class="close-modal" onclick="closeModal('deleteRoleModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" id="deleteRoleId">
            <p>Are you sure you want to delete this role? This action cannot be undone.</p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteRoleModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Role</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'flex';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
}

function editRole(id) {
    fetch('get_role_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('editRoleId').value = data.id;
            document.getElementById('editRoleName').value = data.name;
            
            // Uncheck all checkboxes first
            document.querySelectorAll('#editPermissions input[type="checkbox"]').forEach(cb => cb.checked = false);
            
            // Check permissions
            const permissions = JSON.parse(data.permissions);
            if (permissions && Array.isArray(permissions)) {
                permissions.forEach(perm => {
                    const checkbox = document.querySelector('#editPermissions input[value="' + perm + '"]');
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            openModal('editRoleModal');
        });
}

function deleteRole(id) {
    document.getElementById('deleteRoleId').value = id;
    openModal('deleteRoleModal');
}
</script>
