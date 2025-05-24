<?php
session_start();
require_once 'config/database.php';

// Initialize user variable
$user = null;

// Get user information if logged in
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch(PDOException $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
    }
}

// Check if user is admin
if (!isset($user['role']) || $user['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Fetch pending demands
try {
    $stmt = $pdo->prepare("
        SELECT l.*, u.username as employee_name
        FROM lieu l
        JOIN users u ON l.user_id = u.user_id
        WHERE l.status = 'pending'
        ORDER BY l.created_at DESC
    ");
    $stmt->execute();
    $pending_lieu = $stmt->fetchAll();
} catch(PDOException $e) {
    $pending_lieu = [];
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Pending Demands</title>
</head>
<body>
    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-8">
            <h1 class="text-5xl font-extrabold text-center text-blue-600 drop-shadow-md">
                <i class="fas fa-list mr-3"></i>
                Pending Demands
            </h1>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($pending_lieu)): ?>
                <div class="col-span-full text-center py-8">
                    <p class="text-gray-600 text-lg">No pending demands at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_lieu as $lieu): ?>
                    <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300" data-lieu-id="<?php echo $lieu['id']; ?>">
                        <div class="p-6">
                            <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($lieu['title']); ?></h3>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($lieu['description']); ?></p>
                            
                            <div class="mt-4 text-gray-600">
                                <p><strong>Employee:</strong> <?php echo htmlspecialchars($lieu['employee_name']); ?></p>
                                <p><strong>Date:</strong> <?php echo htmlspecialchars($lieu['date']); ?></p>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <button onclick="approveLieu(<?php echo $lieu['id']; ?>)"
                                    class="bg-green-500 text-white p-2 rounded-full shadow-lg hover:bg-green-600 transition-colors">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button onclick="rejectLieu(<?php echo $lieu['id']; ?>)"
                                    class="bg-red-500 text-white p-2 rounded-full shadow-lg hover:bg-red-600 transition-colors">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function approveDemand(demandId) {
            if (confirm('Are you sure you want to approve this demand?')) {
                fetch('update_demand_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lieu_id: lieuId,
                        status: 'approved'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const card = document.querySelector(`[data-lieu-id="${lieuId}"]`);
                        card.remove();
                        alert('Demand approved successfully');
                    } else {
                        alert(data.message || 'Error approving demand');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error approving demand. Please try again.');
                });
            }
        }

        function rejectDemand(demandId) {
            if (confirm('Are you sure you want to reject this demand?')) {
                fetch('update_demand_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lieu_id: lieuId,
                        status: 'rejected'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const card = document.querySelector(`[data-lieu-id="${lieuId}"]`);
                        card.remove();
                        alert('Demand rejected successfully');
                    } else {
                        alert(data.message || 'Error rejecting demand');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error rejecting demand. Please try again.');
                });
            }
        }
    </script>
</body>
</html> 
