<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$clerk_id = $_SESSION['user_id'];

// Handle GET actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'get_messages') {
        $user_id = intval($_GET['user_id']);
        
        $messages = $mysqli->query("
            SELECT cm.*, 
                   CASE WHEN cm.sender_id = $clerk_id THEN u2.username ELSE u1.username END as sender_name
            FROM chat_messages cm
            LEFT JOIN users u1 ON cm.sender_id = u1.id
            LEFT JOIN users u2 ON cm.receiver_id = u2.id
            WHERE (cm.sender_id = $clerk_id AND cm.receiver_id = $user_id)
               OR (cm.sender_id = $user_id AND cm.receiver_id = $clerk_id)
            ORDER BY cm.created_at ASC
        ");
        
        $message_list = [];
        while ($msg = $messages->fetch_assoc()) {
            $message_list[] = [
                'id' => $msg['id'],
                'sender_id' => $msg['sender_id'],
                'message' => $msg['message'],
                'time' => date('H:i', strtotime($msg['created_at'])),
                'is_read' => $msg['is_read']
            ];
        }
        
        // Mark messages as read
        $mysqli->query("UPDATE chat_messages SET is_read = TRUE WHERE receiver_id = $clerk_id AND sender_id = $user_id");
        
        echo json_encode(['messages' => $message_list, 'current_user_id' => $clerk_id]);
        exit;
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'send_message') {
        $receiver_id = intval($_POST['receiver_id']);
        $message = trim($_POST['message']);
        
        if ($message) {
            $insert = $mysqli->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $insert->bind_param("iis", $clerk_id, $receiver_id, $message);
            $insert->execute();
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        }
        exit;
    }

    if ($action === 'accept_request') {
        $request_id = intval($_POST['request_id']);

        $request = $mysqli->query("SELECT * FROM live_chat_requests WHERE id = $request_id AND status = 'pending'")->fetch_assoc();
        if ($request) {
            $user_id = intval($request['user_id']);
            $question = $request['question'];

            $mysqli->query("UPDATE live_chat_requests SET clerk_id = $clerk_id, status = 'accepted', updated_at = NOW() WHERE id = $request_id");

            $insert = $mysqli->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $insert->bind_param("iis", $user_id, $clerk_id, $question);
            $insert->execute();

            header('Location: clerk.php?section=chat');
            exit;
        }
    }
}

// Get all users who have conversations with clerk
$users = $mysqli->query("
    SELECT DISTINCT 
        CASE WHEN cm.sender_id = $clerk_id THEN cm.receiver_id ELSE cm.sender_id END as user_id,
        u.username,
        u.role,
        (SELECT message FROM chat_messages WHERE (sender_id = $clerk_id AND receiver_id = user_id) OR (sender_id = user_id AND receiver_id = $clerk_id) ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM chat_messages WHERE (sender_id = $clerk_id AND receiver_id = user_id) OR (sender_id = user_id AND receiver_id = $clerk_id) ORDER BY created_at DESC LIMIT 1) as last_time,
        (SELECT COUNT(*) FROM chat_messages WHERE receiver_id = $clerk_id AND sender_id = user_id AND is_read = FALSE) as unread_count
    FROM chat_messages cm
    JOIN users u ON CASE WHEN cm.sender_id = $clerk_id THEN cm.receiver_id ELSE cm.sender_id END = u.id
    WHERE cm.sender_id = $clerk_id OR cm.receiver_id = $clerk_id
    ORDER BY last_time DESC
");

$pending_requests = $mysqli->query("
    SELECT lcr.*, u.username, u.role
    FROM live_chat_requests lcr
    JOIN users u ON lcr.user_id = u.id
    WHERE lcr.status = 'pending'
    ORDER BY lcr.created_at ASC
");
?>

<div class="content-section">
    <h2>Live Chat Support</h2>

    <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
        <div class="content-section" style="margin-bottom: 20px; background: #fff7ed; border: 1px solid #fdba74; border-radius: 12px; padding: 15px;">
            <h3 style="margin-top: 0;">Pending Live Chat Requests</h3>
            <?php while ($request = $pending_requests->fetch_assoc()): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #fed7aa;">
                    <div>
                        <strong><?php echo htmlspecialchars($request['username']); ?></strong>
                        <div style="color: #666; font-size: 14px;"><?php echo htmlspecialchars($request['question']); ?></div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="accept_request">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <button class="btn btn-primary" type="submit">Accept</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
    
    <div class="chat-container">
        <div class="chat-sidebar">
            <?php if ($users && $users->num_rows > 0): ?>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <div class="chat-user <?php echo $user['unread_count'] > 0 ? 'unread' : ''; ?>" data-user-id="<?php echo $user['user_id']; ?>" onclick="selectChatUser(<?php echo $user['user_id']; ?>)">
                        <div class="chat-user-name">
                            <?php echo htmlspecialchars($user['username']); ?>
                            <span style="font-size: 11px; color: #666;">(<?php echo ucfirst($user['role']); ?>)</span>
                            <?php if ($user['unread_count'] > 0): ?>
                                <span class="badge"><?php echo $user['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="chat-user-preview">
                            <?php echo htmlspecialchars(substr($user['last_message'] ?? '', 0, 30)); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="padding: 20px; text-align: center; color: #666;">No conversations yet.</p>
            <?php endif; ?>
        </div>
        
        <div class="chat-main">
            <div class="chat-messages" id="chatMessages">
                <p style="text-align: center; color: #666; margin-top: 50px;">Select a user to start chatting</p>
            </div>
            <div class="chat-input">
                <input type="text" id="chatInput" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') sendMessage()">
                <button class="btn btn-primary" onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>
</div>
