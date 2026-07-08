<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $mysqli->real_escape_string($_POST['username']);
    $email = $mysqli->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    $query = "INSERT INTO users (username, email, password, role, created_at) VALUES ('$username', '$email', '$password', '$role', NOW())";
    
    if ($mysqli->query($query)) {
        header('Location: admin.php?section=users');
        exit;
    } else {
        echo "Error: " . $mysqli->error;
    }
}
