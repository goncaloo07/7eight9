<?php
require "connection.php";

$roupa_id = filter_input(INPUT_POST, 'roupa_id', FILTER_SANITIZE_NUMBER_INT);
$size = filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT);

$stock_sql = "SELECT QNT as available FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = ? AND TAMANHO_ID = ?";
$stock_stmt = $connection->prepare($stock_sql);
$stock_stmt->bind_param("ii", $roupa_id, $size);
$stock_stmt->execute();
$stock_result = $stock_stmt->get_result();

if ($stock_result->num_rows > 0) {
    $stock_row = $stock_result->fetch_assoc();
    echo json_encode(['available' => $stock_row['available']]);
} else {
    echo json_encode(['available' => 0]);
}

$stock_stmt->close();
$connection->close();
?>