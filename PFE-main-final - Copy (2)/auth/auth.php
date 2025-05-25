<?php
session_start();
require_once '../config/database.php';

function register($username, $email, $password, $profile_picture = null, $bio = null)
{
    global $pdo;

    try {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, profile_picture, bio) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashed_password, $profile_picture, $bio]);

        return ['success' => true, 'message' => 'Registration successful'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

function login($email, $password)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();

            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Return success with redirect to index.php for all users
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'redirect' => 'index.php'
                ];
            }
        }

        return ['success' => false, 'message' => 'Invalid email or password'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
    }
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function logout()
{
    session_destroy();
    return ['success' => true, 'message' => 'Logged out successfully'];
}
?>