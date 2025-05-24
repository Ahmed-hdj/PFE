<!-- Display lieu requests -->
<div class="lieu-requests">
    <?php
    $sql = "SELECT * FROM lieu_requests WHERE status = 'pending'";
    $result = $conn->query($sql);
    
    while($row = $result->fetch_assoc()) {
        ?>
        <div class="request-item">
            <p>Employee: <?php echo $row['employee_name']; ?></p>
            <p>Date: <?php echo $row['request_date']; ?></p>
            <p>Reason: <?php echo $row['reason']; ?></p>
            
            <form method="POST" action="approve_lieu.php">
                <input type="hidden" name="lieu_id" value="<?php echo $row['id']; ?>">
                <button type="submit" name="approve_lieu">Approve</button>
                <button type="submit" name="reject_lieu">Reject</button>
            </form>
        </div>
        <?php
    }
    ?>
</div> 