<?php
require "connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

$connection = db_connect();
$user_id = $_SESSION['id'];

try {
    $sql = "DELETE FROM PAP_INFO_PAGAMENTO WHERE ID = ? AND ID_CLIENTE = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $_POST['id'], $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>