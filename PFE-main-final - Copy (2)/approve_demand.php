<?php
include 'config.php';

if(isset($_POST['approve'])) {
    $id = $_POST['id'];
    $sql = "UPDATE demands SET status='approved' WHERE id='$id'";
    $result = mysqli_query($conn, $sql);
    
    if($result) {
        header("Location: demands.php?success=1");
    } else {
        header("Location: demands.php?error=1");
    }
}

if(isset($_POST['reject'])) {
    $id = $_POST['id'];
    $sql = "UPDATE demands SET status='rejected' WHERE id='$id'";
    $result = mysqli_query($conn, $sql);
    
    if($result) {
        header("Location: demands.php?success=2");
    } else {
        header("Location: demands.php?error=2");
    }
}
?> 