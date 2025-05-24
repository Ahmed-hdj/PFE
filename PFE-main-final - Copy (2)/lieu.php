<?php
include 'config.php';

// Display lieu requests
$sql = "SELECT * FROM lieu WHERE status='pending'";
$result = mysqli_query($conn, $sql);

while($row = mysqli_fetch_assoc($result)) {
    ?>
    <div class="request">
        <p>Employee: <?php echo $row['employee_name']; ?></p>
        <p>Date: <?php echo $row['date']; ?></p>
        
        <form method="POST" action="approve_lieu.php">
            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
            <button type="submit" name="approve">Approve</button>
        </form>
    </div>
    <?php
}
?> 