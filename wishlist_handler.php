<?php
require "connection.php";
require_once './core.php';

// Verifica se é uma requisição AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso direto não permitido']));
}

// Inicia sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configura o cabeçalho para JSON
header('Content-Type: application/json');

// Verifica se o usuário está logado
if (!isset($_SESSION["id"])) {
    echo json_encode(['success' => false, 'redirect' => 'login.php']);
    exit;
}

// Valida os dados de entrada
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
$roupa_id = filter_input(INPUT_POST, 'roupa_id', FILTER_VALIDATE_INT);

if (!$action || !$roupa_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

$user_id = $_SESSION["id"];
$connection = db_connect();

try {
    if ($action === 'addwish') {
        // Verifica se já está na wishlist
        $check = $connection->prepare("SELECT 1 FROM PAP_WISHLIST WHERE CLIENTE_ID = ? AND ROUPA_ID = ?");
        $check->bind_param("ii", $user_id, $roupa_id);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            $stmt = $connection->prepare("INSERT INTO PAP_WISHLIST (CLIENTE_ID, ROUPA_ID, DATA_ADICAO) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $user_id, $roupa_id);
            if (!$stmt->execute()) {
                throw new Exception("Erro ao adicionar à wishlist");
            }
        }
        
        echo json_encode(['success' => true, 'action' => 'removewish']);
        
    } elseif ($action === 'removewish') {
        $stmt = $connection->prepare("DELETE FROM PAP_WISHLIST WHERE CLIENTE_ID = ? AND ROUPA_ID = ?");
        $stmt->bind_param("ii", $user_id, $roupa_id);
        if (!$stmt->execute()) {
            throw new Exception("Erro ao remover da wishlist");
        }
        
        echo json_encode(['success' => true, 'action' => 'addwish']);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
} finally {
    if (isset($connection)) {
        $connection->close();
    }
}