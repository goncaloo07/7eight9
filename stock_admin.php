<?php
require "connection.php";

$connection = db_connect();
header('Content-Type: application/json');

// Verificação de sessão e permissões
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}
if ($_SESSION['nivel'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

// Tratamento de erros
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        if ($_GET['action'] === 'get_stock' && isset($_GET['produto_id'])) {
            $produtoId = (int) $_GET['produto_id'];

            if ($produtoId <= 0) {
                throw new Exception('ID do produto inválido');
            }

            $query = "SELECT ID, TAMANHO FROM PAP_TAMANHO_ROUPA ORDER BY TAMANHO";
            $result = $connection->query($query);

            if (!$result) {
                throw new Exception('Erro ao obter tamanhos disponíveis');
            }

            $tamanhos = [];
            while ($row = $result->fetch_assoc()) {
                $tamanhos[$row['ID']] = $row['TAMANHO'];
            }

            $query = "SELECT TAMANHO_ID, QNT FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = ?";
            $stmt = $connection->prepare($query);

            if (!$stmt) {
                throw new Exception('Erro ao preparar consulta de stock');
            }

            $stmt->bind_param('i', $produtoId);
            if (!$stmt->execute()) {
                throw new Exception('Erro ao executar consulta de stock');
            }

            $result = $stmt->get_result();
            $stockAtual = [];

            while ($row = $result->fetch_assoc()) {
                $stockAtual[$row['TAMANHO_ID']] = (int) $row['QNT'];
            }

            // Preparar resposta
            $response = [];
            foreach ($tamanhos as $id => $tamanho) {
                $response[] = [
                    'tamanho_id' => (int) $id,
                    'tamanho' => $tamanho,
                    'quantidade' => $stockAtual[$id] ?? 0
                ];
            }

            echo json_encode($response);
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'update_stock' && isset($_POST['produto_id'], $_POST['stock'])) {
            $produtoId = (int) $_POST['produto_id'];

            if ($produtoId <= 0) {
                throw new Exception('ID do produto inválido');
            }

            $stockData = json_decode($_POST['stock'], true);

            if (!is_array($stockData)) {
                throw new Exception('Dados de stock inválidos');
            }

            $connection->begin_transaction();

            try {
                $query = "DELETE FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = ?";
                $stmt = $connection->prepare($query);

                if (!$stmt) {
                    throw new Exception('Erro ao preparar exclusão de stock');
                }

                $stmt->bind_param('i', $produtoId);
                if (!$stmt->execute()) {
                    throw new Exception('Erro ao excluir stock existente');
                }

                $query = "INSERT INTO PAP_ROUPA_HAS_TAMANHO (ROUPA_ID, TAMANHO_ID, QNT) VALUES (?, ?, ?)";
                $stmt = $connection->prepare($query);

                if (!$stmt) {
                    throw new Exception('Erro ao preparar inserção de stock');
                }

                foreach ($stockData as $item) {
                    $tamanhoId = (int) ($item['tamanho_id'] ?? 0);
                    $quantidade = (int) ($item['quantidade'] ?? 0);

                    if ($tamanhoId <= 0) {
                        continue;
                    }

                    if ($quantidade >= 0) {
                        $stmt->bind_param('iii', $produtoId, $tamanhoId, $quantidade);
                        if (!$stmt->execute()) {
                            throw new Exception('Erro ao atualizar stock para tamanho ID ' . $tamanhoId);
                        }
                    }
                }

                $connection->commit();
                echo json_encode(['success' => true, 'message' => 'Stock atualizado com sucesso']);

            } catch (Exception $e) {
                $connection->rollback();
                throw $e; 
            }

            exit();
        }
    }

    // Se nenhuma ação válida foi encontrada
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);

} catch (Exception $e) {
    // Log do erro (opcional)
    error_log('Erro no stock_handler: ' . $e->getMessage());

    // Resposta de erro
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreu um erro: ' . $e->getMessage()
    ]);
}