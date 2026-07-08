<?php
session_start();

$mysqli = new mysqli("localhost", "root", "", "fyp");
if ($mysqli->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'error' => 'Please log in first']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_session') {
    $request = $mysqli->query(
        "SELECT lcr.*, u.username AS clerk_name FROM live_chat_requests lcr LEFT JOIN users u ON lcr.clerk_id = u.id WHERE lcr.user_id = $user_id AND lcr.status = 'accepted' ORDER BY lcr.updated_at DESC LIMIT 1"
    );

    if (!$request || $request->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No active chat session']);
        exit;
    }

    $request_data = $request->fetch_assoc();
    $clerk_id = intval($request_data['clerk_id']);

    $messages_query = $mysqli->query(
        "SELECT id, sender_id, receiver_id, message, created_at FROM chat_messages WHERE 
            (sender_id = $user_id AND receiver_id = $clerk_id) OR 
            (sender_id = $clerk_id AND receiver_id = $user_id)
         ORDER BY created_at ASC"
    );

    $messages = [];
    while ($msg = $messages_query->fetch_assoc()) {
        $messages[] = [
            'id' => intval($msg['id']),
            'sender_id' => intval($msg['sender_id']),
            'receiver_id' => intval($msg['receiver_id']),
            'message' => $msg['message'],
            'time' => date('H:i', strtotime($msg['created_at']))
        ];
    }

    $mysqli->query("UPDATE chat_messages SET is_read = TRUE WHERE receiver_id = $user_id AND sender_id = $clerk_id");

    echo json_encode([
        'success' => true,
        'current_user_id' => $user_id,
        'clerk_id' => $clerk_id,
        'messages' => $messages
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit;
    }

    $request = $mysqli->query(
        "SELECT clerk_id FROM live_chat_requests WHERE user_id = $user_id AND status = 'accepted' ORDER BY updated_at DESC LIMIT 1"
    );

    if (!$request || $request->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No active chat session']);
        exit;
    }

    $request_data = $request->fetch_assoc();
    $clerk_id = intval($request_data['clerk_id']);

    $stmt = $mysqli->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $clerk_id, $message);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
