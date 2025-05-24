<?php
include 'config.php'; // Make sure this points to your database connection file

if(isset($_POST['approve'])) {
    $id = $_POST['id'];
    $sql = "UPDATE lieu SET status='approved' WHERE id='$id'";
    $result = mysqli_query($conn, $sql);
    
    if($result) {
        header("Location: lieu.php?success=1");
    } else {
        header("Location: lieu.php?error=1");
    }
}
?> 