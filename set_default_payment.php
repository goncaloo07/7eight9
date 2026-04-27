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
    // Primeiro remove o padrão de todos os métodos
    $sql_reset = "UPDATE PAP_INFO_PAGAMENTO SET METODO_PADRAO = 0 WHERE ID_CLIENTE = ?";
    $stmt_reset = $connection->prepare($sql_reset);
    $stmt_reset->bind_param("i", $user_id);
    $stmt_reset->execute();
    
    // Define o novo método como padrão
    $sql_set = "UPDATE PAP_INFO_PAGAMENTO SET METODO_PADRAO = 1 WHERE ID = ? AND ID_CLIENTE = ?";
    $stmt_set = $connection->prepare($sql_set);
    $stmt_set->bind_param("ii", $_POST['id'], $user_id);
    $stmt_set->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>