<?php
// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to get user data
function get_user_data($user_id) {
    global $conn;
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to check if a place is saved by user
function is_place_saved($place_id, $user_id) {
    global $conn;
    $query = "SELECT 1 FROM saved_places WHERE lieu_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $place_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Function to get average rating for a place
function get_place_rating($place_id) {
    global $conn;
    $query = "SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as total_ratings 
              FROM ratings WHERE lieu_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $place_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to format date
function format_date($date) {
    return date('F j, Y', strtotime($date));
}

// Function to truncate text
function truncate_text($text, $length = 150) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}
?> 