<?php
require "connection.php";
ob_start();

$connection = db_connect();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}
if ($_SESSION['nivel'] != 2) {
    header('Location: index.php');
    exit();
}

$filtros = [];
if (isset($_GET['filtros'])) {
    $jsonFiltros = json_decode($_GET['filtros'], true);
    if (is_array($jsonFiltros)) {
        $filtros = $jsonFiltros;
    }
}

// Captura orderBy e orderDir do GET para ordenação
$orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : null;
$orderDir = isset($_GET['orderDir']) ? strtoupper($_GET['orderDir']) : 'ASC';
if ($orderDir !== 'ASC' && $orderDir !== 'DESC') {
    $orderDir = 'ASC';
}

$tabela = ""; // inicialização

if (isset($_GET['get_image'])) {
    $id = (int) $_GET['id'];
    $field = $_GET['field'];

    $table = isset($_GET['table']) ? $_GET['table'] : 'PAP_ROUPA';
    $query = "SELECT `$field` FROM `$table` WHERE ID = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($imageData);
    $stmt->fetch();

    if ($imageData) {
        $finfo = new finfo(FILEINFO_MIME);
        $mime = $finfo->buffer($imageData);
        header("Content-Type: $mime");
        echo $imageData;
    } else {
        // Imagem padrão caso não exista
        header("Content-Type: image/jpg");
        readfile('img/images.jpg');
    }
    exit;
}
function getForeignKeys($connection, $table)
{
    $allowedTables = [
        'PAP_CATEGORIA_ROUPA',
        'PAP_CORES',
        'PAP_MARCA',
        'PAP_MATERIAIS_ROUPA',
        'PAP_TAMANHO_ROUPA',
        'PAP_ROUPA',
        'PAP_CAROUSEL',
        'PAP_CLIENTE',
        'PAP_COMPRAS'
    ];

    $foreignKeys = [];

    if (!in_array($table, $allowedTables)) {
        return $foreignKeys;
    }

    try {
        $query = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = ? 
                 AND REFERENCED_TABLE_NAME IS NOT NULL";

        $stmt = $connection->prepare($query);
        if (!$stmt) {
            throw new Exception("Erro ao preparar consulta: " . $connection->error);
        }

        $stmt->bind_param('s', $table);
        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar consulta: " . $stmt->error);
        }

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $foreignKeys[$row['COLUMN_NAME']] = [
                'table' => $row['REFERENCED_TABLE_NAME'],
                'column' => $row['REFERENCED_COLUMN_NAME']
            ];
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Erro em getForeignKeys: " . $e->getMessage());
    }

    return $foreignKeys;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_image') {
    header('Content-Type: application/json');

    $id = (int) $_POST['id'];
    $field = $_POST['field'];
    $tipo = $_POST['tipo'] ?? '';

    $tabelasValidasImagens = [
        'produtos' => 'PAP_ROUPA',
        'carousel' => 'PAP_CAROUSEL'
    ];

    if (!isset($tabelasValidasImagens[$tipo])) {
        echo json_encode(['success' => false, 'message' => 'Tipo inválido para atualização de imagem']);
        exit;
    }

    $tabela = $tabelasValidasImagens[$tipo];

    // Verificar se o campo é válido para a tabela
    $allowedFields = [
        'PAP_ROUPA' => ['FT_1'],
        'PAP_CAROUSEL' => ['IMG_CAROUSEL']
    ];

    if (!isset($allowedFields[$tabela]) || !in_array($field, $allowedFields[$tabela])) {
        echo json_encode(['success' => false, 'message' => 'Campo de imagem inválido para esta tabela']);
        exit;
    }

    try {
        // Verificar se o arquivo foi enviado corretamente
        if (!isset($_FILES['image'])) {
            throw new Exception('Nenhuma imagem foi enviada');
        }

        $file = $_FILES['image'];

        // Verificar erros de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload: ' . $file['error']);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        // Verificação mais robusta do tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedTypes)) {
            throw new Exception('Tipo de arquivo não permitido. Use JPEG, PNG ou GIF. Tipo recebido: ' . $mime);
        }

        // Verificar extensão do arquivo
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            throw new Exception('Extensão de arquivo não permitida.');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: 2MB.');
        }

        // Verificar se o arquivo é uma imagem válida
        if (!getimagesize($file['tmp_name'])) {
            throw new Exception('O arquivo não é uma imagem válida.');
        }

        // Ler o conteúdo do arquivo como binário
        $imageData = file_get_contents($file['tmp_name']);
        if ($imageData === false) {
            throw new Exception('Não foi possível ler o conteúdo da imagem.');
        }

        // Preparar a query com tratamento de erro melhorado
        $query = "UPDATE `$tabela` SET `$field` = ? WHERE ID = ?";
        $stmt = $connection->prepare($query);
        if (!$stmt) {
            throw new Exception("Erro ao preparar atualização: " . $connection->error);
        }

        // Bind dos parâmetros
        $null = null;
        if (!$stmt->bind_param('bi', $null, $id)) {
            throw new Exception("Erro ao vincular parâmetros: " . $stmt->error);
        }

        // Enviar dados longos
        $stmt->send_long_data(0, $imageData);

        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar atualização: " . $stmt->error);
        }

        // Verificar se a imagem foi realmente atualizada
        if ($stmt->affected_rows === 0) {
            throw new Exception("Nenhum registo foi atualizado. Verifique se o ID existe.");
        }

        echo json_encode([
            'success' => true,
            'message' => 'Imagem atualizada com sucesso',
            'newUrl' => 'admin.php?get_image=1&id=' . $id . '&field=' . $field . '&table=' . $tabela . '&t=' . time() // Cache buster
        ]);

    } catch (Exception $e) {
        error_log('Erro ao atualizar imagem: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao atualizar a imagem: ' . $e->getMessage(),
            'debug' => [
                'file' => isset($file) ? $file : null,
                'tabela' => $tabela,
                'field' => $field,
                'id' => $id
            ]
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');

    if (!isset($_POST['ids']) || empty($_POST['ids'])) {
        echo json_encode(['success' => false, 'message' => 'Nenhum ID selecionado para eliminar']);
        exit();
    }

    $tabela = $_POST['tabela'];
    $ids = $_POST['ids'];

    // Validar tabela
    $tabelasPermitidas = [
        'PAP_CATEGORIA_ROUPA',
        'PAP_CORES',
        'PAP_MARCA',
        'PAP_MATERIAIS_ROUPA',
        'PAP_TAMANHO_ROUPA',
        'PAP_ROUPA',
        'PAP_CAROUSEL',
        'PAP_CLIENTE',
        'PAP_COMPRAS'
    ];

    if (!in_array($tabela, $tabelasPermitidas)) {
        echo json_encode(['success' => false, 'message' => 'Tabela não permitida']);
        exit();
    }

    try {
        // Converter IDs para array se não for
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        // Criar placeholders para a query
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        // Preparar e executar a query
        $stmt = $connection->prepare("DELETE FROM `$tabela` WHERE ID IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);
        $result = $stmt->execute();

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Eliminado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao eliminar registos: ' . $connection->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['tipo'])) {
    if (isset($_GET['updateField'], $_GET['id'], $_GET['field'], $_GET['value'])) {
        header('Content-Type: application/json');

        $id = (int) $_GET['id'];
        $field = $_GET['field'];
        $value = $_GET['value'];
        $tipo = $_GET['tipo'] ?? '';

        // Bloquear edição de IDs
        if ($field === 'ID') {
            echo json_encode(['success' => false, 'message' => 'Não é permitido editar o ID']);
            exit;
        }
        if ($field === 'PRECO') {
            $value = formatarPreco($value);
        }


        $tabelasValidas = [
            'categorias' => 'PAP_CATEGORIA_ROUPA',
            'cores' => 'PAP_CORES',
            'marcas' => 'PAP_MARCA',
            'materiais' => 'PAP_MATERIAIS_ROUPA',
            'tamanhos' => 'PAP_TAMANHO_ROUPA',
            'produtos' => 'PAP_ROUPA',
            'carousel' => 'PAP_CAROUSEL',
            'usuarios' => 'PAP_CLIENTE',
            'compras' => 'PAP_COMPRAS'
        ];

        if (!isset($tabelasValidas[$tipo])) {
            echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
            exit;
        }

        $tabela = $tabelasValidas[$tipo];

        if ($tabela === 'PAP_COMPRAS') {
            echo json_encode([
                'success' => false,
                'message' => 'Edição desativada: Registos de compras não podem ser modificados para manter a integridade dos dados financeiros'
            ]);
            exit;
        }

        $stmt = $connection->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
        $stmt->bind_param('s', $field);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Campo não existe na tabela']);
            exit;
        }

        try {
            $stmt = $connection->prepare("UPDATE `$tabela` SET `$field` = ? WHERE ID = ?");
            $stmt->bind_param('si', $value, $id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Campo atualizado com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Nenhuma alteração foi feita']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()]);
        }
        exit;
    }
    if (isset($_GET['getDetails'], $_GET['id'])) {
        header('Content-Type: application/json');

        $id = (int) $_GET['id'];
        $tipo = $_GET['tipo'] ?? '';
        $tabelasValidas = [
            'categorias' => 'PAP_CATEGORIA_ROUPA',
            'cores' => 'PAP_CORES',
            'marcas' => 'PAP_MARCA',
            'materiais' => 'PAP_MATERIAIS_ROUPA',
            'tamanhos' => 'PAP_TAMANHO_ROUPA',
            'produtos' => 'PAP_ROUPA',
            'carousel' => 'PAP_CAROUSEL',
            'usuarios' => 'PAP_CLIENTE',
            'compras' => 'PAP_COMPRAS'
        ];

        if (!isset($tabelasValidas[$tipo])) {
            echo json_encode(['error' => 'Tipo inválido']);
            exit;
        }

        $tabela = $tabelasValidas[$tipo];
        $response = ['error' => 'Registo não encontrado'];

        try {
            switch ($tabela) {
                case 'PAP_ROUPA':
                    $query = "
                SELECT R.*, C.CATEGORIA, CO.COR, M.NOME AS MATERIAIS, MA.MARCA
                FROM PAP_ROUPA R
                JOIN PAP_CATEGORIA_ROUPA C ON C.ID = R.CATEGORIA
                JOIN PAP_CORES CO ON CO.ID = R.COR
                JOIN PAP_MATERIAIS_ROUPA M ON M.ID = R.MATERIAIS
                JOIN PAP_MARCA MA ON MA.ID = R.MARCA
                WHERE R.ID = ?
            ";
                    break;
                case 'PAP_COMPRAS':
                    $query = "
                SELECT C.*, CL.NOME AS NOME_CLIENTE, 
           CONCAT('**** **** **** ', RIGHT(P.CARTAO_U4, 4)) AS NUMERO_CARTAO, 
           P.NOME_TITULAR AS NOME_TITULAR
    FROM PAP_COMPRAS C
    JOIN PAP_CLIENTE CL ON CL.ID = C.CLIENTE_ID
    JOIN PAP_INFO_PAGAMENTO P ON P.ID = C.ID_PAGAMENTO
    WHERE C.ID = ?
            ";
                    break;
                case 'PAP_CLIENTE':
                    $query = "SELECT ID, NOME, EMAIL, MORADA, NUMTELE, NIVEL FROM PAP_CLIENTE WHERE ID = ?";
                    break;
                case 'PAP_CAROUSEL':
                    $query = "SELECT ID, LIGACAO, IMG_CAROUSEL FROM PAP_CAROUSEL WHERE ID = ?";
                    break;
                default:
                    $query = "SELECT * FROM `$tabela` WHERE ID = ?";
            }

            $stmt = $connection->prepare($query);
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $response = $result->fetch_assoc();
                    if (isset($response['IMG_CAROUSEL']) && !empty($response['IMG_CAROUSEL'])) {
                        $response['IMG_CAROUSEL_url'] = 'admin.php?get_image=1&id=' . $id . '&field=IMG_CAROUSEL&table=PAP_CAROUSEL';
                        $response['IMG_CAROUSEL'] = '';
                    }

                    foreach ($response as $key => $value) {
                        if (strpos($key, 'FT_') === 0 && !empty($value)) {
                            $response[$key . '_url'] = 'admin.php?get_image=1&id=' . $id . '&field=' . $key;
                            $response[$key] = '';
                        }
                        if ($response[$key] === null) {
                            $response[$key] = 'N/A';
                        } else {
                            $response[$key] = (string) $response[$key];
                        }
                    }
                }
                echo json_encode($response);
                $stmt->close();
                exit;
            }
        } catch (Exception $e) {
            $response = ['error' => 'Erro ao buscar detalhes: ' . $e->getMessage()];
        }

        echo json_encode($response);
        exit;
    }
    $tipo = $_GET['tipo'];
    $tabelasValidas = [
        'categorias' => 'PAP_CATEGORIA_ROUPA',
        'cores' => 'PAP_CORES',
        'marcas' => 'PAP_MARCA',
        'materiais' => 'PAP_MATERIAIS_ROUPA',
        'tamanhos' => 'PAP_TAMANHO_ROUPA',
        'produtos' => 'PAP_ROUPA',
        'carousel' => 'PAP_CAROUSEL',
        'usuarios' => 'PAP_CLIENTE',
        'compras' => 'PAP_COMPRAS',
    ];

    if (!array_key_exists($tipo, $tabelasValidas)) {
        echo "Tipo inválido.";
        exit();
    }

    $tabela = $tabelasValidas[$tipo];

    if (isset($_GET['getCols'])) {
        $foreignKeys = getForeignKeys($connection, $tabela);
        $resultTemp = $connection->query("SHOW COLUMNS FROM `$tabela`");

        if (!$resultTemp) {
            echo json_encode([]);
            exit();
        }

        $cols = [];
        while ($row = $resultTemp->fetch_assoc()) {
            if ($row['Field'] !== 'ID') {
                $colInfo = [
                    'name' => $row['Field'],
                    'required' => ($row['Null'] === 'NO' && $row['Default'] === null && $row['Extra'] !== 'auto_increment'),
                    'type' => preg_replace('/\(\d+\)/', '', $row['Type']) // Remove tamanho do tipo (ex: varchar(255))
                ];

                if (isset($foreignKeys[$row['Field']])) {
                    $colInfo['foreignKey'] = $foreignKeys[$row['Field']];
                }

                $cols[] = $colInfo;
            }
        }
        foreach ($cols as &$col) {
            if ($col['name'] === 'CATEGORIA') {
                // Busca as categorias disponíveis
                $resultCategorias = $connection->query("SELECT ID, CATEGORIA FROM PAP_CATEGORIA_ROUPA");
                $opcoes = [];
                while ($row = $resultCategorias->fetch_assoc()) {
                    $opcoes[] = [
                        'value' => $row['ID'],
                        'label' => $row['CATEGORIA']
                    ];
                }
                $col['optionscat'] = $opcoes;
                $col['type'] = 'select'; // Força o tipo para select
            }
            if ($col['name'] === 'COR') {
                // Busca as cores disponíveis
                $resultCores = $connection->query("SELECT ID, COR FROM PAP_CORES");
                $opcoes = [];
                while ($row = $resultCores->fetch_assoc()) {
                    $opcoes[] = [
                        'value' => $row['ID'],
                        'label' => $row['COR']
                    ];
                }
                $col['optionscor'] = $opcoes;
                $col['type'] = 'select'; // Força o tipo para select
            }
            if ($col['name'] === 'MATERIAIS') {
                // Busca os materiais disponíveis
                $resultMateriais = $connection->query("SELECT ID, NOME FROM PAP_MATERIAIS_ROUPA");
                $opcoes = [];
                while ($row = $resultMateriais->fetch_assoc()) {
                    $opcoes[] = [
                        'value' => $row['ID'],
                        'label' => $row['NOME']
                    ];
                }
                $col['optionsmat'] = $opcoes;
                $col['type'] = 'select'; // Força o tipo para select
            }
            if ($col['name'] === 'MARCA') {
                // Busca as marcas disponíveis
                $resultMarcas = $connection->query("SELECT ID, MARCA FROM PAP_MARCA");
                $opcoes = [];
                while ($row = $resultMarcas->fetch_assoc()) {
                    $opcoes[] = [
                        'value' => $row['ID'],
                        'label' => $row['MARCA']
                    ];
                }
                $col['optionsmarca'] = $opcoes;
                $col['type'] = 'select'; // Força o tipo para select
            }
        }
        unset($col);
        echo json_encode($cols);
        exit();
    }

    if ($tabela === 'PAP_CLIENTE') {
        $queryBase = "SELECT ID, NOME, EMAIL, MORADA, NUMTELE AS TELEMOVEL, NIVEL FROM PAP_CLIENTE";
    } else if ($tabela === 'PAP_COMPRAS') {
        $queryBase = "SELECT C.ID, C.CLIENTE_ID AS ID_DO_CLIENTE, CL.NOME AS NOME_DO_CLIENTE, 
                  C.TOTAL, C.DATA_COMPRA, 
                  CONCAT('**** **** **** ', RIGHT(P.CARTAO_U4, 4)) AS NUMERO_CARTAO
                  FROM PAP_COMPRAS C 
                  JOIN PAP_CLIENTE CL ON CL.ID = C.CLIENTE_ID
                  JOIN PAP_INFO_PAGAMENTO P ON P.ID = C.ID_PAGAMENTO";
    } else if ($tabela === 'PAP_ROUPA') {
        $queryBase = "SELECT R.ID, R.NOME, R.PRECO, C.CATEGORIA, CO.COR, R.FT_1, M.NOME AS MATERIAL, MA.MARCA 
        FROM PAP_ROUPA R 
        JOIN PAP_CATEGORIA_ROUPA C ON C.ID = R.CATEGORIA 
        JOIN PAP_CORES CO ON CO.ID = R.COR 
        JOIN PAP_MATERIAIS_ROUPA M ON M.ID = R.MATERIAIS 
        JOIN PAP_MARCA MA ON MA.ID = R.MARCA";
    } else if ($tabela === 'PAP_CAROUSEL') {
        $queryBase = "SELECT ID, LIGACAO, IMG_CAROUSEL FROM PAP_CAROUSEL";
    } else {
        $queryBase = "SELECT * FROM `$tabela`";
    }

    $resultTemp = $connection->query($queryBase . " LIMIT 1");
    if (!$resultTemp) {
        echo "Erro ao executar consulta base.";
        exit;
    }
    $fields = $resultTemp->fetch_fields();

    // Lista de colunas válidas
    $validColumns = array_map(function ($f) {
        return $f->name;
    }, $fields);

    // Monta cláusulas WHERE de filtro
    $whereClauses = [];
    foreach ($filtros as $col => $val) {
        if (in_array($col, $validColumns)) {
            $valEscaped = $connection->real_escape_string($val);
            $whereClauses[] = "`$col` LIKE '%$valEscaped%'";
        }
    }

    if (!empty($whereClauses)) {
        $queryBase .= " WHERE " . implode(" AND ", $whereClauses);
    }

    // Ordenação segura: só aceita colunas válidas
    if ($orderBy && in_array($orderBy, $validColumns)) {
        $queryBase .= " ORDER BY `$orderBy` $orderDir";
    } else {
        // Ordem padrão por ID desc se coluna existir
        if (in_array('ID', $validColumns)) {
            $queryBase .= " ORDER BY ID DESC";
        }
    }

    // Executa a consulta final
    $resultado = $connection->query($queryBase);
    if (!$resultado) {
        echo "Erro na consulta: {$connection->error}";
        exit;
    }

    if ($tabela !== 'PAP_COMPRAS') {
        echo "<div class='d-flex justify-content-between mb-3'>";
        echo "<button id='btnDeleteRegistos' class='btn btn-danger'>Eliminar Selecionados</button>";
        echo "<button id='btnAddRegisto' class='btn btn-success'>Adicionar Registo</button>";
        echo "</div>";
    }

    echo "<div class='table-responsivo'><table class='table table-striped table-bordered'><thead><tr>";

    $fields = $resultado->fetch_fields();


    echo "<th><input type='checkbox' id='selectAllRows'></th>";

    foreach ($fields as $field) {
        $colName = $field->name;
        switch ($colName) {
            case 'FT_1':
                echo "<th class='sortable' data-coluna='{$colName}' style='cursor:pointer; padding: 0 30px;'>{$colName} <i class='bi bi-arrow-down-up'></i></th>";
                break;
            default:
                echo "<th class='sortable' data-coluna='{$colName}' style='cursor:pointer;'>{$colName} <i class='bi bi-arrow-down-up'></i></th>";
                break;

        }
    }
    echo "</tr><tr>";
    echo "<th></th>"; // coluna vazia para alinhamento dos filtros com os checkboxes

    foreach ($fields as $field) {
        echo "<th><input type='text' class='form-control form-control-sm filtro-coluna' data-coluna='{$field->name}' placeholder='Pesquisar...'></th>";
    }
    echo "</tr></thead><tbody>";

    if ($resultado->num_rows > 0) {
        if ($tabela === 'PAP_ROUPA') {
            $allStocks = [];
            $stockQuery = "SELECT ROUPA_ID, SUM(QNT) as total FROM PAP_ROUPA_HAS_TAMANHO GROUP BY ROUPA_ID";
            $result = $connection->query($stockQuery);
            while ($row = $result->fetch_assoc()) {
                $allStocks[$row['ROUPA_ID']] = (int) $row['total'];
            }
            while ($row = $resultado->fetch_assoc()) {
                echo "<tr>";
                $rowId = htmlspecialchars($row['ID']);
                echo "<td>";
                echo "<input type='checkbox' class='rowCheckbox' value='{$rowId}'>";

                $totalStock = $allStocks[$row['ID']] ?? 0;

                if ($totalStock <= 0) {
                    echo "<span class='badge bg-danger ms-2' title='Produto sem stock'><i class='bi bi-exclamation-triangle'></i> Sem stock</span>";
                }

                echo "</td>";

                foreach ($row as $key => $valor) {
                    if (in_array($key, ['FT_1'])) {
                        echo "<td>";
                        if (!empty($valor)) {
                            echo "<img src='admin.php?get_image=1&id=" . $row['ID'] . "&field=$key' style='max-height: 50px;'>";
                        } else {
                            echo "Sem imagem";
                        }
                        echo "</td>";
                    } else {
                        // Para outros campos, manter o comportamento original
                        if (strlen($valor) > 50) {
                            $valor = substr($valor, 0, 47) . "...";
                        }
                        if ($key === 'NUMTELE' || $key === 'TELEMOVEL') {
                            $valor = preg_replace('/(\d{3})(?=\d)/', '$1 ', $valor);
                        }
                        echo "<td>$valor</td>";
                    }
                }
                echo "</tr>";
            }
        } else {
            while ($row = $resultado->fetch_assoc()) {
                echo "<tr>";
                // adiciona o checkbox da linha
                $rowId = htmlspecialchars($row['ID']);
                echo "<td><input type='checkbox' class='rowCheckbox' value='{$rowId}'></td>";

                foreach ($row as $key => $valor) {
                    if (in_array($key, ['FT_1'])) {
                        // Para campos de imagem, exibir a miniatura
                        echo "<td>";
                        if (!empty($valor)) {
                            echo "<img src='admin.php?get_image=1&id=" . $row['ID'] . "&field=$key' style='max-height: 50px;'>";
                        } else {
                            echo "Sem imagem";
                        }
                        echo "</td>";
                    } else if (in_array($key, ['IMG_CAROUSEL'])) {
                        // Para campos de imagem, exibir a miniatura
                        echo "<td>";
                        if (!empty($valor)) {
                            echo "<img src='admin.php?get_image=1&id=" . $row['ID'] . "&field=$key&table=PAP_CAROUSEL' style='max-height: 50px;'>";
                        } else {
                            echo "Sem imagem";
                        }
                        echo "</td>";
                    } else {
                        // Para outros campos, manter o comportamento original
                        if (strlen($valor) > 50) {
                            $valor = substr($valor, 0, 47) . "...";
                        }
                        if ($key === 'NUMTELE' || $key === 'TELEMOVEL') {
                            $valor = preg_replace('/(\d{3})(?=\d)/', '$1 ', $valor);
                        }
                        echo "<td>$valor</td>";
                    }
                }
                echo "</tr>";
            }
        }
    } else {
        echo "<tr><td colspan='" . count($fields) . "' class='text-center text-muted'>Nenhum dado encontrado.</td></tr>";
    }

    echo "</tbody></table></div>";
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_field') {
    header('Content-Type: application/json');

    try {
        if (!isset($_POST['id'], $_POST['field'], $_POST['value'], $_POST['tipo'])) {
            throw new Exception('Parâmetros incompletos');
        }

        $id = (int) $_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];
        $tipo = $_POST['tipo'];

        $tabelasValidas = [
            'categorias' => 'PAP_CATEGORIA_ROUPA',
            'cores' => 'PAP_CORES',
            'marcas' => 'PAP_MARCA',
            'materiais' => 'PAP_MATERIAIS_ROUPA',
            'tamanhos' => 'PAP_TAMANHO_ROUPA',
            'produtos' => 'PAP_ROUPA',
            'carousel' => 'PAP_CAROUSEL',
            'usuarios' => 'PAP_CLIENTE',
            'compras' => 'PAP_COMPRAS'
        ];

        if (!isset($tabelasValidas[$tipo])) {
            throw new Exception('Tipo de tabela inválido');
        }

        $tabela = $tabelasValidas[$tipo];

        $result = $connection->query("SHOW COLUMNS FROM `{$tabela}`");
        if (!$result) {
            throw new Exception("Erro ao verificar colunas da tabela: " . $connection->error);
        }

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        if (!in_array($field, $columns)) {
            throw new Exception("O campo '{$field}' não existe na tabela '{$tabela}'");
        }

        if ($tabela === 'PAP_ROUPA') {
            if (in_array($field, ['COR', 'CATEGORIA', 'MARCA', 'MATERIAIS'])) {
                $value = (int) $value;
                if ($value <= 0) {
                    throw new Exception("Valor inválido para o campo '{$field}'");
                }

                $tabela_relacionada = [
                    'COR' => 'PAP_CORES',
                    'CATEGORIA' => 'PAP_CATEGORIA_ROUPA',
                    'MARCA' => 'PAP_MARCA',
                    'MATERIAIS' => 'PAP_MATERIAIS_ROUPA'
                ][$field];

                $check = $connection->query("SELECT 1 FROM `{$tabela_relacionada}` WHERE ID = {$value}");
                if (!$check || $check->num_rows === 0) {
                    throw new Exception("O valor {$value} não existe na tabela {$tabela_relacionada}");
                }
            }

        }

        $query = "UPDATE `{$tabela}` SET `{$field}` = ? WHERE `ID` = ?";
        $stmt = $connection->prepare($query);

        if (!$stmt) {
            throw new Exception("Erro ao preparar atualização: " . $connection->error);
        }

        $stmt->bind_param('si', $value, $id);
        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar atualização: " . $stmt->error);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Campo atualizado com sucesso',
            'affected_rows' => $stmt->affected_rows
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'debug' => [
                'tabela' => $tabela ?? null,
                'campo' => $field ?? null,
                'valor' => $value ?? null,
                'id' => $id ?? null,
                'post_data' => $_POST
            ]
        ]);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tabela'])) {
    header('Content-Type: application/json');

    try {
        $tabela = $_POST['tabela'];
        if ($tabela === 'PAP_COMPRAS') {
            echo json_encode([
                'success' => false,
                'message' => 'Não é permitido adicionar registos manualmente na tabela de compras'
            ]);
            exit;
        }

        $imageFields = [];
        if ($tabela === 'PAP_ROUPA') {
            foreach ($_POST as $key => $value) {
                if ($key === 'PRECO') {
                    $_POST[$key] = formatarPreco($value);
                }
            }
            $imageFields = ['FT_1'];
            foreach ($imageFields as $field) {
                if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$field];

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($mime, $allowedTypes)) {
                        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido para ' . $field]);
                        exit;
                    }

                    if ($file['size'] > 2 * 1024 * 1024) {
                        echo json_encode(['success' => false, 'message' => 'Arquivo muito grande para ' . $field . ' (máximo 2MB)']);
                        exit;
                    }

                    $imageData = file_get_contents($file['tmp_name']);
                    $_POST[$field] = $imageData;
                } else if ($field === 'FT_1') {
                    echo json_encode(['success' => false, 'message' => 'A imagem principal (FT_1) é obrigatória']);
                    exit;
                }
            }
        }
        if ($tabela === 'PAP_CAROUSEL') {
            $imageFields = ['IMG_CAROUSEL'];
            foreach ($imageFields as $field) {
                if (isset($_FILES[$field])) {
                    $file = $_FILES[$field];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        echo json_encode(['success' => false, 'message' => 'Erro no upload da imagem']);
                        exit;
                    }

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($mime, $allowedTypes)) {
                        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Apenas JPEG, PNG ou GIF.']);
                        exit;
                    }

                    if ($file['size'] > 2 * 1024 * 1024) {
                        echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Tamanho máximo: 2MB']);
                        exit;
                    }

                    $imageData = file_get_contents($file['tmp_name']);
                    $_POST[$field] = $imageData;
                } else {
                    echo json_encode(['success' => false, 'message' => 'A imagem do carrossel é obrigatória']);
                    exit;
                }
            }
        }

        unset($_POST['tabela']);
        unset($_POST['action']);

        $colunas = array_keys($_POST);
        $valores = array_values($_POST);

        $tabelasPermitidas = [
            'PAP_CATEGORIA_ROUPA',
            'PAP_CORES',
            'PAP_MARCA',
            'PAP_MATERIAIS_ROUPA',
            'PAP_TAMANHO_ROUPA',
            'PAP_ROUPA',
            'PAP_CAROUSEL',
            'PAP_CLIENTE',
            'PAP_COMPRAS'
        ];

        if (!in_array($tabela, $tabelasPermitidas)) {
            echo json_encode(['success' => false, 'message' => 'Tabela não permitida']);
            exit();
        }

        $placeholders = implode(',', array_fill(0, count($colunas), '?'));
        $query = "INSERT INTO `$tabela` (" . implode(', ', $colunas) . ") VALUES ($placeholders)";
        $stmt = $connection->prepare($query);

        if (!$stmt) {
            throw new Exception("Erro ao preparar query: " . $connection->error);
        }

        $types = '';
        $bindParams = [];
        foreach ($colunas as $col) {
            if (in_array($col, $imageFields)) {
                $types .= 'b';
                $bindParams[] = null;
            } else {
                $types .= 's';
                $bindParams[] = $_POST[$col] ?? '';
            }
        }

        $bindArgs = array_merge([$types], $bindParams);
        $bindRefs = [];
        foreach ($bindArgs as $key => $value) {
            $bindRefs[$key] = &$bindArgs[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $bindRefs);

        foreach ($colunas as $i => $col) {
            if (in_array($col, $imageFields) && isset($_POST[$col])) {
                $stmt->send_long_data($i, $_POST[$col]);
            }
        }

        $result = $stmt->execute();

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Registo adicionado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao adicionar registo: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}
function formatarPreco($preco)
{
    // Remove qualquer formatação existente (como vírgulas, símbolos de moeda, etc.)
    $preco = preg_replace('/[^0-9.]/', '', $preco);

    // Converte para float e formata com 2 casas decimais
    $precoFormatado = number_format((float) $preco, 2, '.', '');

    return $precoFormatado;
}

?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ferramentas de administrador</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="icon" href="https://alpha.soaresbasto.pt/~a25385/PAP/img/logo.png" type="image/gif" />
    <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
    <link href="https://fonts.googleapis.com/css?family=Great+Vibes|Open+Sans:400,700&display=swap&subset=latin-ext"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.css"
        media="screen">
    <link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: rgb(230, 230, 230);
            font-family: 'Open Sans', sans-serif;
            margin: 0;
            padding: 0;
        }

        .highlight-wrapper {
            position: relative;
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            gap: 1rem;
        }

        .slider-bar {
            position: absolute;
            bottom: -7px;
            height: 5px;
            background-color: rgb(39, 39, 39);
            width: calc(50% - 0.5rem);
            border-radius: 0 0 10px 10px;
            transition: transform 0.4s ease;
            z-index: 1;
            left: 0;
        }

        .btn-admin {
            z-index: 2;
            position: relative;
            font-size: 1.2rem;
            padding: 1rem;
            transition: background 0.4s ease, color 0.4s ease;
            border: none;
            border-radius: 10px;
            width: 100%;
            background-color: rgb(213, 213, 213);
        }

        .btn-admin:hover {
            background-color: rgb(124, 124, 124);
            color: white;
        }

        .btn-admin.active {
            background-color: rgb(39, 39, 39) !important;
            font-weight: bold;
            color: white;
        }

        .btn-admin::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            height: 3px;
            width: 0;
            transition: all 0.3s ease;
        }

        .btn-admin.active::after {
            left: 0;
            width: 100%;
        }

        .btn-admin i {
            margin-right: 8px;
        }

        .admin-section {
            background: white;
            padding: 2rem;
            margin-top: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            display: none;
            animation: fadeIn 0.4s ease-in-out;
        }

        #msginicial {
            background: #ffffff;
            padding: 2rem;
            margin-top: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(77, 77, 77, 0.06);
        }

        .coladmin {
            padding-left: 0;
            padding-right: 0;
            flex: 1;
            min-width: 0;
        }

        .table-responsivo {
            overflow-x: auto;
            width: 100%;
        }

        .container {
            max-width: 95%;
        }

        .btn-dado.active {
            background-color: rgb(39, 39, 39);
            color: white;
            font-weight: bold;
            border-color: rgb(39, 39, 39);
        }

        .modal-body {
            padding-bottom: 0;
        }

        .modal-body .container-fluid {
            padding: 0;
        }

        .modal-body .row {
            margin: 0;
        }

        .modal-body .col-md-6 {
            padding: 0 15px;
        }

        .modal-body .mb-3 {
            margin-bottom: 1rem !important;
        }

        .modal-body .form-control {
            border: 1px solid #ced4da;
            /* Borda mais espessa */
            border-radius: 5px;
            /* Cantos arredondados */
            padding: 10px;
            /* Mais espaço interno */
            transition: border-color 0.3s ease;
            /* Transição suave */
        }

        .modal-body .form-control:focus {
            border-color: #86b7fe;
            /* Cor quando em foco */
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            /* Sombra suave */
        }

        .modal-body .form-label {
            font-weight: 500;
            /* Texto mais destacado */
            margin-bottom: 8px;
            /* Espaçamento melhorado */
            color: #495057;
            /* Cor mais escura */
        }

        .modal-body .mb-3 {
            margin-bottom: 1.5rem !important;
            /* Mais espaço entre campos */
        }

        #btnDeleteRegistos {
            display: none;
            margin-left: 10px;
        }

        .delete-confirm-modal .modal-body {
            padding: 20px;
        }

        .delete-confirm-modal .modal-footer {
            justify-content: space-between;
        }

        .table tbody tr {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .table tbody tr:hover {
            background-color: #f5f5f5 !important;
        }

        /* Evitar que checkboxes e inputs dentro da linha disparem o evento de clique */
        .table tbody tr td:first-child,
        .table tbody tr td:first-child * {
            cursor: default;
            pointer-events: auto;
        }

        .detail-modal-field {
            margin-bottom: 1rem;
        }

        .detail-modal-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.25rem;
        }

        .detail-modal-value {
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid #dee2e6;
            word-break: break-word;
        }

        .product-detail-img {
            background-color: #f8f9fa;
            border-radius: 0.25rem 0.25rem 0 0;
            object-position: center;
        }

        .detail-modal-field {
            margin-bottom: 1rem;
        }

        .detail-modal-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.25rem;
        }

        .detail-modal-value {
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid #dee2e6;
            word-break: break-word;
        }

        .modal-xl .modal-lg {
            max-width: 1200px;
        }

        .form-control-plaintext.border-bottom {
            min-height: 38px;
            padding-left: 0;
            padding-right: 0;
            word-wrap: break-word;
        }

        #detailModalContent {
            padding: 20px;
        }

        /* Efeito hover para imagens */
        .product-detail-img:hover {
            transform: scale(1.02);
            transition: transform 0.3s ease;
        }

        .product-main-img {
            max-height: 300px;
            max-width: 400px;
            margin: auto;
            object-fit: contain;
            background-color: #f8f9fa;
            border-radius: 0.25rem 0.25rem 0 0;
        }

        .product-secondary-img {
            max-height: 200px;
            object-fit: contain;
            background-color: #f8f9fa;
            border-radius: 0.25rem 0.25rem 0 0;
        }

        .card-placeholder {
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
        }

        /* Efeitos de hover apenas quando há imagem */
        .card:hover .product-main-img,
        .card:hover .product-secondary-img {
            transform: scale(1.02);
            transition: transform 0.3s ease;
        }

        /* Espaçamento consistente */
        #detailModal .modal-body {
            padding: 1.5rem;
        }

        .btn-close {
            padding: 1rem;
        }

        .detail-field-container {
            position: relative;
            padding: 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
        }

        .detail-field-container:hover {
            background-color: #f8f9fa;
        }

        .btn-edit-field {
            opacity: 0;
            transition: opacity 0.2s;
        }

        .detail-field-container:hover .btn-edit-field {
            opacity: 1;
        }

        .field-edit-container {
            animation: fadeIn 0.3s ease-out;
        }

        .img-thumbnail {
            max-width: 100%;
            height: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.25rem;
            background-color: #fff;
        }

        .field-edit-container .form-control[type="file"] {
            padding: 0.375rem 0.75rem;
        }

        .detail-field-container[data-field^="FT_"] .field-value {
            min-height: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .read-only-field .form-control-plaintext {
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.25rem;
        }

        /* Para a tabela de compras */
        .compras-field {
            cursor: default;
        }

        .read-only-modal .detail-field-container {
            cursor: default;
        }

        .read-only-modal .btn-edit-field {
            display: none !important;
        }

        .read-only-field .form-control-plaintext {
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.25rem;
            cursor: default;
        }

        .read-only-modal .detail-field-container {
            cursor: default;
        }

        .read-only-modal .btn-edit-field {
            display: none !important;
        }

        .read-only-modal .form-control-plaintext {
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.25rem;
            cursor: default;
        }

        .detail-field-container[data-field^="FT_"] .field-value {
            min-height: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .field-edit-container .form-control[type="file"] {
            padding: 0.375rem 0.75rem;
        }

        .img-thumbnail {
            max-width: 100%;
            height: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.25rem;
            background-color: #fff;
            transition: transform 0.3s ease;
        }

        .img-thumbnail:hover {
            transform: scale(1.05);
        }

        .alert-dismissible {
            animation: fadeIn 0.3s ease-out;
            margin-bottom: 1rem;
        }

        .alert-dismissible .btn-close {
            padding: 0.5rem;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-field-message {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            border-radius: 0.25rem;
        }

        #stockModalContent table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
        }

        #stockModalContent table th {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }

        #stockModalContent table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #dee2e6;
        }

        #stockModalContent .stock-quantity {
            max-width: 100px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .modal-body .col-md-6 {
                width: 100%;
                padding: 0;
            }

            #colunaDireita {
                margin-top: 1rem;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 767px) {
            #btnProdutos {
                margin-bottom: 1rem;
            }
        }

        .badge.ms-2 {
            margin-left: 0.5rem;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>

<body>
    <?php include "header.php"; ?>
    <h1 class="text-center pt-4">Ferramentas de Administrador</h1>
    <div class="container mt-2 mb-4">
        <div class="highlight-wrapper">
            <div class="col-md-6 coladmin">
                <button id="btnProdutos" class="btn btn-admin">
                    <i class="bi bi-box-seam"></i> Produtos
                </button>
            </div>
            <div class="col-md-6 coladmin">
                <button id="btnUsuarios" class="btn btn-admin">
                    <i class="bi bi-people"></i> Usuários
                </button>
            </div>
            <div class="slider-bar" id="sliderBar"></div>
        </div>


        <div id="msginicial" class="text-center">
            <h2>Bem-vindo às Ferramentas de Administração</h2>
            <p>Escolha uma opção acima para começar a gerir.</p>
        </div>

        <div id="produtosContent" class="admin-section">
            <h3><i class="bi bi-box-seam"></i> Gestão de Produtos</h3>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="categorias">
                    <i class="bi bi-tags"></i> Categorias
                </button>
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="cores">
                    <i class="bi bi-palette"></i> Cores
                </button>
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="marcas">
                    <i class="bi bi-bookmark-star"></i> Marcas
                </button>
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="materiais">
                    <i class="bi bi-boxes"></i> Materiais
                </button>
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="tamanhos">
                    <i class="bi bi-aspect-ratio"></i> Tamanhos
                </button>
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="produtos">
                    <i class="bi bi-box"></i> Produtos
                </button>
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="carousel">
                    <i class="bi bi-images"></i> Carrossel de Imagens
                </button>
            </div>
            <div id="dadosTabelaProdutos" class="mt-4"></div>
        </div>
        <div id="usuariosContent" class="admin-section">
            <h3><i class="bi bi-people"></i> Gestão de Usuários</h3>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="usuarios">
                    <i class="bi bi-person-lines-fill"></i> Usuários
                </button>
                <button class="btn btn-outline-dark m-1 btn-dado" data-tipo="compras">
                    <i class="bi bi-bag-check"></i> Compras
                </button>
            </div>
            <div id="dadosTabelaUsuarios" class="mt-4"></div>
        </div>
    </div>
    <div class="modal fade" id="modalAddRegisto" tabindex="-1" aria-labelledby="modalAddRegistoLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="formAddRegisto" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalAddRegistoLabel">Adicionar Novo Registo</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"
                            style="padding: 1rem;"></button>
                    </div>
                    <div class="modal-body">
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-md-6" id="colunaEsquerda">

                                </div>
                                <div class="col-md-6" id="colunaDireita">

                                </div>
                            </div>
                        </div>
                        <div id="formMensagem" class="mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnModal">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade delete-confirm-modal" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminação</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Tem a certeza que deseja eliminar os registos selecionados?</p>
                    <p class="text-danger"><strong>Esta ação não pode ser desfeita!</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="successDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-success text-white">
                <div class="modal-body text-center py-4">
                    <i class="bi bi-check-circle-fill" style="font-size:2rem;"></i>
                    <div class="mt-2">Eliminado com sucesso!</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel">Detalhes do Registo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="detailModalContent">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="stockModal" tabindex="-1" aria-labelledby="stockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockModalLabel">Ajustar Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="stockModalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="saveStock">Guardar</button>
                </div>
            </div>
        </div>
    </div>
    <?php include "footer.php"; ?>
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentOrderBy = null;
        let currentOrderDir = 'ASC';

        function autoDismissAlert(alertElement) {
            setTimeout(() => {
                alertElement.fadeOut(400, () => alertElement.remove());
            }, 2000);
        }

        $(document).ready(function () {
            const tabelasValidas = {
                'categorias': 'PAP_CATEGORIA_ROUPA',
                'cores': 'PAP_CORES',
                'marcas': 'PAP_MARCA',
                'materiais': 'PAP_MATERIAIS_ROUPA',
                'tamanhos': 'PAP_TAMANHO_ROUPA',
                'produtos': 'PAP_ROUPA',
                'carousel': 'PAP_CAROUSEL',
                'usuarios': 'PAP_CLIENTE',
                'compras': 'PAP_COMPRAS'
            };

            const slider = $('#sliderBar');
            const btnProdutos = $('#btnProdutos');
            const btnUsuarios = $('#btnUsuarios');

            function moveSlider(toLeft) {
                slider.css('transform', toLeft ? 'translateX(0%)' : 'translateX(100%)');
                slider.css('margin-left', toLeft ? '0' : '1rem');
            }

            function updateSliderVisibility() {
                slider.toggle($('.btn-admin.active').length > 0);
            }

            function activateButton(activeId) {
                $('.btn-admin').removeClass('active');
                $('#' + activeId).addClass('active');
                updateSliderVisibility();
            }

            btnProdutos.click(function () {
                $('#usuariosContent').hide();
                $('#produtosContent').fadeIn();
                $('#msginicial').hide();
                activateButton('btnProdutos');
                moveSlider(true);
            });

            btnUsuarios.click(function () {
                $('#produtosContent').hide();
                $('#usuariosContent').fadeIn();
                $('#msginicial').hide();
                activateButton('btnUsuarios');
                moveSlider(false);
            });

            $('#produtosContent, #usuariosContent').hide();
            $('#msginicial').show();
            $('.btn-admin').removeClass('active');
            slider.hide();

            $('.btn-dado').click(function () {
                $('.btn-dado').removeClass('active');
                $(this).addClass('active');

                const tipo = $(this).data('tipo');
                const $targetDiv = $(this).closest('.admin-section').find('div[id^="dadosTabela"]');

                $targetDiv.html('<p>Carregando dados de <strong>' + tipo + '</strong>...</p>');

                $.ajax({
                    url: window.location.href,
                    type: 'GET',
                    data: { ajax: 1, tipo: tipo },
                    success: function (resposta) {
                        $targetDiv.html(resposta);
                    },
                    error: function () {
                        $targetDiv.html('<p class="text-danger">Erro ao carregar os dados.</p>');
                    }
                });
            });

            $(document).on('click', '.btn-dado[data-tipo="compras"]', function () {
                // Desativa todos os botões de edição na tabela
                $('.btn-edit-field').prop('disabled', true).addClass('disabled')
                    .attr('title', 'Edição desativada para compras');
            });
            $(document).on('click', '.btn-dado:not([data-tipo="compras"])', function () {
                $('.btn-edit-field').prop('disabled', false).removeClass('disabled')
                    .attr('title', 'Editar este campo');
            });

            $(document).on('change', '.rowCheckbox, #selectAllRows', function () {
                const anyChecked = $('.rowCheckbox:checked').length > 0;
                $('#btnDeleteRegistos').toggle(anyChecked);
            });

            // Selecionar todos/deselecionar todos
            $(document).on('change', '#selectAllRows', function () {
                $('.rowCheckbox').prop('checked', $(this).prop('checked'));
                $('#btnDeleteRegistos').toggle($(this).prop('checked'));
            });

            $(document).on('click', 'tbody tr', function (e) {
                if ($(e.target).is('input[type="checkbox"], .btn, a, button') ||
                    $(e.target).closest('input[type="checkbox"], .btn, a, button').length) {
                    return;
                }

                const tipo = $('.btn-dado.active').data('tipo');
                const id = $(this).find('.rowCheckbox').val();

                if (!id || !tipo) return;

                $('#detailModalContent').html('<div class="text-center py-4"><div class="spinner-border" role="status"><span class="visually-hidden">Carregando...</span></div></div>');
                $('#detailModal').modal('show');

                $.ajax({
                    url: window.location.href,
                    type: 'GET',
                    data: {
                        ajax: 1,
                        tipo: tipo,
                        id: id,
                        getDetails: 1
                    },
                    success: function (response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            if (data.error) {
                                $('#detailModalContent').html('<div class="alert alert-danger">' + data.error + '</div>');
                                return;
                            }

                            const tipo = $('.btn-dado.active').data('tipo');
                            let html = '<div class="container-fluid">';

                            html += '<div class="row">';
                            let leftColumn = '<div class="col-md-6">';
                            let rightColumn = '<div class="col-md-6">';

                            const keys = Object.keys(data).sort((a, b) => {
                                if (a === 'ID') return -1;
                                if (b === 'ID') return 1;
                                if (a === 'NOME') return -1;
                                if (b === 'NOME') return 1;
                                return a.localeCompare(b);
                            });

                            let isLeft = true;
                            for (const key of keys) {
                                const value = data[key];
                                const formattedKey = key.replace(/_/g, ' ')
                                    .replace(/\b\w/g, l => l.toUpperCase());
                                let displayValue = value || '<span class="text-muted">N/A</span>';

                                if (key === 'NUMTELE' || key === 'TELEMOVEL') {
                                    displayValue = formatarTelemovel(value);
                                }

                                const fieldHtml = renderField(key, data[key], formattedKey, displayValue, tipo, data);

                                if (isLeft) {
                                    leftColumn += fieldHtml;
                                } else {
                                    rightColumn += fieldHtml;
                                }
                                isLeft = !isLeft;
                            }

                            leftColumn += '</div>';
                            rightColumn += '</div>';
                            html += leftColumn + rightColumn + '</div></div>';

                            $('#detailModalContent').html(html);
                            $('#detailModal').data('current-id', id);

                            if (tipo === 'compras') {
                                $('#detailModal').addClass('read-only-modal');
                                $('#detailModalLabel').html('Detalhes da Compra (somente leitura)');
                            } else {
                                $('#detailModal').removeClass('read-only-modal');
                                $('#detailModalLabel').html('Detalhes do Registo');
                            }
                        } catch (e) {
                            $('#detailModalContent').html('<div class="alert alert-danger">Erro ao processar os detalhes: ' + e.message + '</div>');
                        }
                    }, error: function (xhr, status, error) {
                        $('#detailModalContent').html('<div class="alert alert-danger">Erro ao carregar os detalhes: ' + error + '</div>');
                    }

                });
            });

            $(document).on('click', '#btnDeleteRegistos', function () {
                const selectedIds = [];
                $('.rowCheckbox:checked').each(function () {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    alert('Nenhum registo selecionado!');
                    return;
                }

                $('#deleteConfirmModal').data('ids', selectedIds);
                $('#deleteConfirmModal').modal('show');
            });

            $(document).on('click', '#confirmDelete', function () {
                const modal = $('#deleteConfirmModal');
                const selectedIds = modal.data('ids');
                const tipo = $('.btn-dado.active').data('tipo');
                const tabelasValidas = {
                    'categorias': 'PAP_CATEGORIA_ROUPA',
                    'cores': 'PAP_CORES',
                    'marcas': 'PAP_MARCA',
                    'materiais': 'PAP_MATERIAIS_ROUPA',
                    'tamanhos': 'PAP_TAMANHO_ROUPA',
                    'produtos': 'PAP_ROUPA',
                    'carousel': 'PAP_CAROUSEL',
                    'usuarios': 'PAP_CLIENTE',
                    'compras': 'PAP_COMPRAS'
                };
                const nomeTabela = tabelasValidas[tipo];

                if (!nomeTabela) {
                    alert('Tabela inválida!');
                    return;
                }

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        action: 'delete',
                        tabela: nomeTabela,
                        ids: selectedIds
                    },
                    dataType: 'json',
                    success: function (response) {
                        modal.modal('hide');
                        if (response.success) {
                            $('#successDeleteModal').modal('show');
                            setTimeout(function () {
                                $('#successDeleteModal').modal('hide');
                                $('.btn-dado.active').click();
                            }, 1000);
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        modal.modal('hide');
                        alert('Erro na comunicação com o servidor: ' + error);
                    }
                });
            });

            $(document).on('input', '#input_PRECO', function () {
                let value = $(this).val().replace(/[^\d,.]/g, '');
                value = value.replace(',', '.'); // Substitui vírgula por ponto

                // Garante que há no máximo 2 casas decimais
                if (value.indexOf('.') !== -1) {
                    const parts = value.split('.');
                    if (parts[1].length > 2) {
                        value = parts[0] + '.' + parts[1].substring(0, 2);
                    }
                }

                $(this).val(value);
            });

            $(document).on('click', '#btnAjustarStock', function () {
                const produtoId = $(this).data('id');
                $('#stockModal').data('produto-id', produtoId);

                $.ajax({
                    url: 'stock_admin.php',
                    type: 'GET',
                    data: { action: 'get_stock', produto_id: produtoId },
                    success: function (response) {
                        try {
                            const stockData = typeof response === 'string' ? JSON.parse(response) : response;

                            if (stockData.success === false) {
                                $('#stockModalContent').html(`
                        <div class="alert alert-danger">
                            ${stockData.message || 'Erro ao carregar dados de stock'}
                        </div>
                    `);
                                return;
                            }

                            let html = `
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Tamanho</th>
                                    <th>Quantidade</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                            stockData.forEach(item => {
                                html += `
                        <tr>
                            <td>${item.tamanho || 'N/A'}</td>
                            <td>
                                <input type="number" class="form-control stock-quantity" 
                                    data-tamanho-id="${item.tamanho_id}" 
                                    value="${item.quantidade || 0}" 
                                    min="0">
                            </td>
                        </tr>
                    `;
                            });

                            html += `
                            </tbody>
                        </table>
                    </div>
                `;

                            $('#stockModalContent').html(html);
                        } catch (e) {
                            $('#stockModalContent').html(`
                    <div class="alert alert-danger">
                        Erro ao processar os dados de stock: ${e.message}
                    </div>
                `);
                        }
                    },
                    error: function (xhr, status, error) {
                        $('#stockModalContent').html(`
                <div class="alert alert-danger">
                    Erro na comunicação com o servidor: ${error}
                </div>
            `);
                    }
                });

                $('#stockModal').modal('show');
            });

            $(document).on('click', '#saveStock', function () {
                const produtoId = $('#stockModal').data('produto-id');
                const stockUpdates = [];

                $('.stock-quantity').each(function () {
                    const tamanhoId = $(this).data('tamanho-id');
                    const quantidade = $(this).val();

                    stockUpdates.push({
                        tamanho_id: tamanhoId,
                        quantidade: quantidade
                    });
                });

                const saveButton = $(this);
                saveButton.prop('disabled', true).html(`
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        Salvando...
    `);

                $.ajax({
                    url: 'stock_admin.php',
                    type: 'POST',
                    data: {
                        action: 'update_stock',
                        produto_id: produtoId,
                        stock: JSON.stringify(stockUpdates)
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#stockModalContent').prepend(`
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        ${response.message || 'Stock atualizado com sucesso!'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `);
                            autoDismissAlert($('#stockModalContent .alert'));

                            setTimeout(() => {
                                $('#stockModal').modal('hide');
                                saveButton.prop('disabled', false).html('Guardar');
                            }, 1000);
                        } else {
                            $('#stockModalContent').prepend(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ${response.message || 'Erro ao atualizar stock'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `);
                            autoDismissAlert($('#stockModalContent .alert'));
                            saveButton.prop('disabled', false).html('Guardar');
                        }
                    },
                    error: function (xhr, status, error) {
                        $('#stockModalContent').prepend(`
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Erro na comunicação com o servidor: ${error}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
                        saveButton.prop('disabled', false).html('Guardar');
                    }
                });
            });
            autoDismissAlert($('#stockModalContent .alert'));

            $(document).on('click', '.btn-edit-field', function () {
                const container = $(this).closest('.detail-field-container');
                container.find('.field-value').hide();
                container.find('.field-edit-container').show();
                container.find('.field-edit-input').focus();
            });

            $(document).on('click', '.btn-cancel-edit', function () {
                const container = $(this).closest('.detail-field-container');
                container.find('.field-edit-container').hide();
                container.find('.field-value').show();
            });

            $(document).on('click', '.btn-save-field', function () {
                const fieldName = $(this).data('field');
                const container = $(this).closest('.detail-field-container');
                const isImageField = container.find('.btn-edit-field').data('isimage');
                const tipo = $('.btn-dado.active').data('tipo');
                const id = $('#detailModal').data('current-id');

                if (!id || !tipo) {
                    console.error('ID ou tipo não definido');
                    return;
                }

                // Mostrar loading
                const saveButton = $(this);
                saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> A Guardar...');

                if (isImageField) {
                    // Processar upload de imagem
                    const fileInput = container.find('.field-edit-input')[0];
                    if (fileInput.files.length === 0) {
                        showEditFieldError(container, fieldName, '', 'Selecione uma imagem para upload');
                        saveButton.prop('disabled', false).html('<i class="bi bi-check"></i> Salvar');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'update_image');
                    formData.append('tipo', tipo);
                    formData.append('id', id);
                    formData.append('field', fieldName);
                    formData.append('image', fileInput.files[0]);

                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                $('#detailModalContent').prepend(
                                    `<div class="alert alert-success alert-dismissible fade show" role="alert">
            Imagem atualizada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`
                                );
                                autoDismissAlert($('#detailModalContent .alert-success'));

                                container.find('.field-value').html(`<img src="${response.newUrl}" class="img-thumbnail" style="max-height: 100px;">`);
                                container.find('.field-edit-container').hide();

                                const $activeBtn = $('.btn-dado.active');
                                if ($activeBtn.length) {
                                    $activeBtn.click();
                                }
                            } else {
                                showEditFieldError(container, fieldName, '', response.message);
                            }
                            saveButton.prop('disabled', false).html('<i class="bi bi-check"></i> Salvar');
                        },
                        error: function () {
                            showEditFieldError(container, fieldName, '', 'Erro ao atualizar a imagem.');
                            saveButton.prop('disabled', false).html('<i class="bi bi-check"></i> Salvar');
                        }
                    });
                } else {
                    let newValue;
                    const inputElement = container.find('.field-edit-input');

                    if (inputElement.is('select')) {
                        newValue = inputElement.val();
                    } else {
                        newValue = inputElement.val();

                        if (fieldName === 'PRECO') {
                            const numValue = parseFloat(newValue);
                            if (!isNaN(numValue)) {
                                newValue = numValue.toFixed(2);
                            } else {
                                newValue = '0.00';
                            }
                        }
                    }

                    if (!newValue && newValue !== '0') {
                        showEditFieldError(container, fieldName, newValue, 'O valor não pode estar vazio');
                        saveButton.prop('disabled', false).html('<i class="bi bi-check"></i> Salvar');
                        return;
                    }

                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'update_field',
                            ajax: 1,
                            tipo: tipo,
                            id: id,
                            field: fieldName,
                            value: newValue
                        },
                        success: function (response) {
                            if (response && response.success) {
                                let displayValue;
                                if (inputElement.is('select')) {
                                    displayValue = inputElement.find('option:selected').text();
                                } else {
                                    displayValue = formatFieldValue(fieldName, newValue);
                                }

                                $('#detailModalContent').prepend(
                                    `<div class="alert alert-success alert-dismissible fade show" role="alert">
                            Campo atualizado com sucesso!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>`
                                ); ~
                                    autoDismissAlert($('#detailModalContent .alert'));

                                container.find('.field-value').html(displayValue);
                                container.find('.field-edit-container').hide();
                                container.find('.field-value').show();

                                const $activeBtn = $('.btn-dado.active');
                                if ($activeBtn.length) {
                                    $activeBtn.click();
                                }
                            } else {
                                const errorMsg = response && response.message ? response.message : 'Resposta inválida do servidor';
                                showEditFieldError(container, fieldName, newValue, errorMsg);
                            }
                        },
                        error: function (xhr, status, error) {
                            let errorMsg = 'Erro na comunicação com o servidor';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            } else if (xhr.responseText) {
                                try {
                                    const resp = JSON.parse(xhr.responseText);
                                    errorMsg = resp.message || errorMsg;
                                } catch (e) {
                                    errorMsg = xhr.responseText || errorMsg;
                                }
                            }
                            showEditFieldError(container, fieldName, newValue, errorMsg);
                        },
                        complete: function () {
                            saveButton.prop('disabled', false).html('<i class="bi bi-check"></i> Salvar');
                        }
                    });
                }
            });

            // Funções auxiliares
            function showFieldMessage(container, message, type) {
                // Remove mensagens anteriores
                container.find(`.alert-field-message.alert-${type}`).remove();

                const alert = $(`<div class="alert-field-message alert-${type}">${message}</div>`);
                container.append(alert);

                // Remove a mensagem após 3 segundos
                setTimeout(() => alert.fadeOut(400, () => alert.remove()), 3000);
            }

            function showEditFieldError(container, fieldName, value, errorMessage) {
                const isImageField = container.find('.btn-edit-field').data('isimage');
                const isSelectField = container.find('.field-edit-input').is('select');

                container.find('.alert-field-message').remove();

                if (isImageField) {
                    container.find('.field-edit-container').html(`
            <div class="alert alert-danger">${errorMessage}</div>
            <div class="alert alert-info mb-2">Selecione uma nova imagem para substituir</div>
            <div class="input-group">
                <input type="file" class="form-control field-edit-input" accept="image/*">
                <div class="input-group-append">
                    <button class="btn btn-success btn-save-field" data-field="${fieldName}">
                        <i class="bi bi-check"></i> Salvar
                    </button>
                    <button class="btn btn-secondary btn-cancel-edit">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
            </div>
        `);
                } else if (isSelectField) {
                    container.find('.field-edit-container').html(`
            <div class="alert alert-danger">${errorMessage}</div>
            <select class="form-select field-edit-input">
                ${container.find('.field-edit-input').html()}
            </select>
            <div class="d-flex justify-content-end mt-2">
                <button class="btn btn-success btn-save-field me-2" data-field="${fieldName}">
                    <i class="bi bi-check"></i> Salvar
                </button>
                <button class="btn btn-secondary btn-cancel-edit">
                    <i class="bi bi-x"></i> Cancelar
                </button>
            </div>
        `);
                } else {
                    container.find('.field-edit-container').html(`
            <div class="alert alert-danger">${errorMessage}</div>
            <input type="text" class="form-control field-edit-input" value="${value || ''}">
            <div class="d-flex justify-content-end mt-2">
                <button class="btn btn-success btn-save-field me-2" data-field="${fieldName}">
                    <i class="bi bi-check"></i> Salvar
                </button>
                <button class="btn btn-secondary btn-cancel-edit">
                    <i class="bi bi-x"></i> Cancelar
                </button>
            </div>
        `);
                }
            }

            function formatFieldValue(fieldName, value) {
                if (!value && value !== 0) return '<span class="text-muted">N/A</span>';

                // Formatação especial para campo PRECO
                if (fieldName === 'PRECO') {
                    const numValue = parseFloat(value);
                    return !isNaN(numValue) ? numValue.toFixed(2) : '0.00';
                }

                if (fieldName === 'NUMTELE' || fieldName === 'TELEMOVEL') {
                    return value.replace(/(\d{3})(?=\d)/g, '$1 ');
                }

                return value;
            }

            // Atualizar a tabela quando o modal de confirmação é fechado
            $('#deleteConfirmModal').on('hidden.bs.modal', function () {
                $('.rowCheckbox').prop('checked', false);
                $('#selectAllRows').prop('checked', false);
                $('#btnDeleteRegistos').hide();
            });


            // Filtros
            $(document).on('input', '.filtro-coluna', function () {
                const $row = $(this).closest('tr');
                const filtros = {};
                const tipo = $('.btn-dado.active').data('tipo');
                const $targetDiv = $(this).closest('.admin-section').find('div[id^="dadosTabela"]');
                const colunaFocada = $(this).data('coluna');

                $row.find('.filtro-coluna').each(function () {
                    const coluna = $(this).data('coluna');
                    const valor = $(this).val();
                    if (valor) filtros[coluna] = valor;
                });

                $.ajax({
                    url: window.location.href,
                    type: 'GET',
                    data: {
                        ajax: 1,
                        tipo: tipo,
                        filtros: JSON.stringify(filtros),
                        orderBy: currentOrderBy,
                        orderDir: currentOrderDir
                    },
                    success: function (resposta) {
                        $targetDiv.html(resposta);
                        $targetDiv.find('.filtro-coluna').each(function () {
                            const coluna = $(this).data('coluna');
                            if (filtros[coluna]) $(this).val(filtros[coluna]);
                            if (coluna === colunaFocada) $(this).focus();
                        });
                    },
                    error: function () {
                        $targetDiv.html('<p class="text-danger">Erro ao carregar os dados.</p>');
                    }
                });
            });

            $(document).on('click', '#btnAddRegisto', function () {
                const tipo = $('.btn-dado.active').data('tipo');
                if (!tipo) return alert('Selecione uma tabela primeiro.');

                $.ajax({
                    url: window.location.href,
                    type: 'GET',
                    data: { ajax: 1, tipo: tipo, getCols: 1 },
                    success: function (resposta) {
                        try {
                            const cols = JSON.parse(resposta);
                            let htmlEsquerda = '';
                            let htmlDireita = '';

                            const metade = Math.ceil(cols.length / 2);

                            cols.forEach((col, index) => {
                                let campoHtml = '';
                                if (tipo === 'carousel') {
                                    if (col.name === 'IMG_CAROUSEL') {
                                        campoHtml = `
                                <div class="mb-3">
                                    <label for="input_${col.name}" class="form-label">
                                        ${col.name}
                                        <span class="text-danger" title="Campo obrigatório">*</span>
                                    </label>
                                    <input type="file" class="form-control" id="input_${col.name}" name="${col.name}" 
                                        accept="image/jpeg, image/png, image/gif" required>
                                    <small class="text-muted">Apenas imagens (JPEG, PNG, GIF)</small>
                                </div>
                            `;
                                    } else if (col.name === 'LIGACAO') {
                                        campoHtml = `
                                <div class="mb-3">
                                    <label for="input_${col.name}" class="form-label">
                                        ${col.name}
                                    </label>
                                    <input type="url" class="form-control" id="input_${col.name}" name="${col.name}" 
                                        placeholder="https://exemplo.com">
                                    <small class="text-muted">URL para onde o carrossel deve redirecionar (opcional)</small>
                                </div>
                            `;
                                    }
                                } else if (tipo === 'produtos') {
                                    if (col.optionscat) {
                                        campoHtml = `
            <div class="mb-3">
                <label for="input_${col.name}" class="form-label">
                    ${col.name}
                    ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                </label>
                <select class="form-select" id="input_${col.name}" name="${col.name}" 
                    ${col.required ? 'required' : ''}>
                    <option value="">Selecione...</option>
                    ${col.optionscat.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('')}
                </select>
                ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
            </div>
        `;
                                    } else if (col.optionscor) {
                                        campoHtml = `
            <div class="mb-3">
                <label for="input_${col.name}" class="form-label">
                    ${col.name}
                    ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                </label>
                <select class="form-select" id="input_${col.name}" name="${col.name}" 
                    ${col.required ? 'required' : ''}>
                    <option value="">Selecione...</option>
                    ${col.optionscor.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('')}
                </select>
                ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
            </div>
        `;
                                    } else if (col.optionsmat) {
                                        campoHtml = `<div class="mb-3">
                <label for="input_${col.name}" class="form-label">
                    ${col.name}
                    ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                </label>
                <select class="form-select" id="input_${col.name}" name="${col.name}" 
                    ${col.required ? 'required' : ''}>
                    <option value="">Selecione...</option>
                    ${col.optionsmat.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('')}
                </select>
                ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
            </div>
        `;
                                    } else if (col.optionsmarca) {
                                        campoHtml = `<div class="mb-3">
                <label for="input_${col.name}" class="form-label">
                    ${col.name}
                    ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                </label>
                <select class="form-select" id="input_${col.name}" name="${col.name}" 
                    ${col.required ? 'required' : ''}>
                    <option value="">Selecione...</option>
                    ${col.optionsmarca.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('')}
                </select>
                ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
            </div>
        `;
                                    } else if (col.name === 'FT_1') {
                                        campoHtml = `
        <div class="mb-3">
            <label for="input_${col.name}" class="form-label">
                ${col.name}
                ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
            </label>
            <input type="file" class="form-control ft-field" id="input_${col.name}" name="${col.name}" 
                data-field="1" accept="image/*" ${col.required ? 'required' : ''}>
            ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
        </div>
    `;
                                    } else if (col.name === 'PRECO') {
                                        campoHtml = `<div class="mb-3">
        <label for="input_${col.name}" class="form-label">
            ${col.name}
            ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
        </label>
        <input type="text" class="form-control" id="input_${col.name}" name="${col.name}" 
             title="Insira um valor numérico com até 2 casas decimais"
            ${col.required ? 'required' : ''}>
        ${col.required ? '<small class="text-muted">Será automaticamente formatado para 2 casas decimais</small>' : ''}
    </div>`;
                                    } else {
                                        campoHtml = `
            <div class="mb-3">
                <label for="input_${col.name}" class="form-label">
                    ${col.name}
                    ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                </label>
                <input type="text" class="form-control" id="input_${col.name}" name="${col.name}" 
                    ${col.required ? 'required' : ''}>
                ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
            </div>
        `;
                                    }

                                } else if (tipo === 'usuarios') {
                                    if (col.name === 'EMAIL') {
                                        campoHtml = `
                                <div class="mb-3">
                                    <label for="input_${col.name}" class="form-label">
                                        ${col.name}
                                        ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                                    </label>
                                    <input type="email" class="form-control" id="input_${col.name}" name="${col.name}" 
                                        ${col.required ? 'required' : ''}>
                                    ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
                                </div>
                            `;
                                    } else if (col.name === 'PASS') {
                                        campoHtml = `
                                <div class="mb-3">
                                    <label for="input_${col.name}" class="form-label">
                                        ${col.name}
                                        ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                                    </label>
                                    <input type="password" class="form-control" id="input_${col.name}" name="${col.name}" 
                                        ${col.required ? 'required' : ''}>
                                    ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
                                </div>
                            `;
                                    } else if (col.name === 'NIVEL') {
                                        campoHtml = `
                                <div class="mb-3">
                                    <label for="input_${col.name}" class="form-label">
                                        ${col.name}
                                        ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                                    </label>
                                    <select class="form-select" id="input_${col.name}" name="${col.name}" 
                                        ${col.required ? 'required' : ''}>
                                        <option value="1">Normal</option>
                                        <option value="2">Admin</option>
                                    </select>
                                    ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
                                </div>
                            `;
                                    } else if (col.name === 'NUMTELE') {
                                        campoHtml = `
        <div class="mb-3">
            <label for="input_${col.name}" class="form-label">
                ${col.name}
                ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
            </label>
            <input type="tel" class="form-control" id="input_${col.name}" name="${col.name}" 
                maxlength="9" pattern="[0-9\s]{9}" ${col.required ? 'required' : ''}
                placeholder="123 456 789">
            ${col.required ? '<small class="text-muted">9 dígitos</small>' : ''}
        </div>
                            `;
                                    } else {
                                        campoHtml = `
            <div class="mb-3">
                <label for="input_${col.name}" class="form-label">
                    ${col.name}
                    ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                </label>
                <input type="text" class="form-control" id="input_${col.name}" name="${col.name}" 
                    ${col.required ? 'required' : ''}>
                ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
            </div>
        `;
                                    }
                                } else {
                                    // Campo do tipo texto normal
                                    campoHtml = `
            <div class="mb-3">
                <label for="input_${col.name}" class="form-label">
                    ${col.name}
                    ${col.required ? '<span class="text-danger" title="Campo obrigatório">*</span>' : ''}
                </label>
                <input type="text" class="form-control" id="input_${col.name}" name="${col.name}" 
                    ${col.required ? 'required' : ''}>
                ${col.required ? '<small class="text-muted">Obrigatório</small>' : ''}
            </div>
        `;
                                }


                                if (index < metade) {
                                    htmlEsquerda += campoHtml;
                                } else {
                                    htmlDireita += campoHtml;
                                }
                            });

                            $('#colunaEsquerda').html(htmlEsquerda);
                            $('#colunaDireita').html(htmlDireita);
                            $('#formMensagem').html('');
                            $('#modalAddRegisto').modal('show');
                        } catch (e) {
                            alert('Erro ao montar formulário: ' + e.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('Erro ao obter campos: ' + error);
                    }
                });
            });
            $('#formAddRegisto').on('submit', function (e) {
                e.preventDefault();

                if ($('#input_PRECO').length) {
                    const precoValue = $('#input_PRECO').val();
                    const numValue = parseFloat(precoValue);
                    if (!isNaN(numValue)) {
                        $('#input_PRECO').val(numValue.toFixed(2));
                    } else {
                        $('#input_PRECO').val('0.00');
                    }
                }

                const tipoSelecionado = $('.btn-dado.active').data('tipo');
                const nomeTabela = tabelasValidas[tipoSelecionado];

                if (!nomeTabela) {
                    $('#formMensagem').html('<div class="alert alert-danger">Tabela inválida ou não selecionada.</div>');
                    return;
                }

                const formData = new FormData(this);
                formData.append('tabela', nomeTabela);
                formData.append('action', 'add_registo');

                if (nomeTabela === 'PAP_CAROUSEL') {
                    const fileInput = $('#input_IMG_CAROUSEL')[0];
                    if (fileInput.files.length === 0) {
                        $('#formMensagem').html('<div class="alert alert-danger">É obrigatório selecionar uma imagem para o carrossel</div>');
                        return;
                    }
                }

                if ($('#input_NUMTELE').length) {
                    let numTele = $('#input_NUMTELE').val();
                    numTele = numTele.replace(/\s/g, ''); // Remove todos os espaços
                    $('#input_NUMTELE').val(numTele);
                }

                if ($('#input_PRECO').length) {
                    let preco = $('#input_PRECO').val();
                    preco = parseFloat(preco).toFixed(2);
                    $('#input_PRECO').val(preco);
                }


                $('#formMensagem').html('<div class="alert alert-info">Enviando dados...</div>');

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#formMensagem').html('<div class="alert alert-success">' + response.message + '</div>');
                            setTimeout(() => {
                                $('.btn-dado.active').click();
                                $('#modalAddRegisto').modal('hide');
                            }, 1000);
                        } else {
                            $('#formMensagem').html('<div class="alert alert-danger">' + response.message + '</div>');
                        }
                    },
                    error: function (xhr, status, error) {
                        $('#formMensagem').html('<div class="alert alert-danger">Erro na comunicação com o servidor: ' + error + '</div>');
                    }
                });
            });

            // Limpar formulário quando o modal é fechado
            $('#modalAddRegisto').on('hidden.bs.modal', function () {
                $('#formAddRegisto')[0].reset();
                $('#formMensagem').html('');
            });
        });
        function formatarTelemovel(numero) {
            if (!numero) return 'N/A';
            const apenasNumeros = numero.toString().replace(/\D/g, '');
            return apenasNumeros.replace(/(\d{3})(?=\d)/g, '$1 ');
        }

        function renderField(key, value, formattedKey, displayValue, tipo, data) {

            if (key.match(/(ft_?\d*_url)/i)) {
                return '';
            }
            if (key.endsWith('_url')) {
                return '';
            }
            if (key === 'ID' && tipo === 'produtos') {
                return `
            <div class="mb-3">
                <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                    ${formattedKey}
                </label>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="form-control-plaintext border-bottom pb-2">
                        ${displayValue}
                    </div>
                    <button class="btn btn-sm btn-outline-info ms-2" id="btnAjustarStock" 
                            title="Ajustar stock por tamanho" data-id="${value}">
                        <i class="bi bi-box-seam"></i> Ajustar Stock
                    </button>
                </div>
            </div>
        `;
            }

            if (tipo === 'compras' || key === 'ID') {
                return `
        <div class="mb-3">
            <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                ${formattedKey}
            </label>
            <div class="form-control-plaintext border-bottom pb-2">
                ${displayValue}
            </div>
        </div>
        `;
            }

            if (key.startsWith('FT_') || key === 'IMG_CAROUSEL') {
                const imageUrl = data && data[key + '_url'] ? data[key + '_url'] : '';

                return `
<div class="mb-3 detail-field-container" data-field="${key}">
    <div class="d-flex justify-content-between align-items-center">
        <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
            ${formattedKey}
        </label>
        <button class="btn btn-sm btn-outline-primary btn-edit-field" 
                data-field="${key}" 
                data-isimage="true"
                title="Editar esta imagem">
            <i class="bi bi-pencil"></i>
        </button>
    </div>
    <div class="field-value">
        ${imageUrl ? `<img src="${imageUrl}" class="img-thumbnail" style="max-height: 100px;">` : 'Nenhuma imagem'}
    </div>
    <div class="field-edit-container" style="display: none;">
        <div class="alert alert-info mb-2">Selecione uma nova imagem para substituir</div>
        <div class="input-group">
            <input type="file" class="form-control field-edit-input" accept="image/*">
            <div class="input-group-append">
                <button class="btn btn-success btn-save-field" data-field="${key}">
                    <i class="bi bi-check"></i> Salvar
                </button>
                <button class="btn btn-secondary btn-cancel-edit">
                    <i class="bi bi-x"></i> Cancelar
                </button>
            </div>
        </div>
    </div>
</div>
`;
            }

            let fieldInfo = {};
            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: { ajax: 1, tipo: tipo, getCols: 1 },
                async: false,
                success: function (resposta) {
                    try {
                        const cols = JSON.parse(resposta);
                        fieldInfo = cols.find(col => col.name === key) || {};
                    } catch (e) {
                        console.error('Erro ao processar informações do campo:', e);
                    }
                }
            });

            // Campos de seleção (select)
            if ((key === 'COR' && fieldInfo.optionscor) ||
                (key === 'CATEGORIA' && fieldInfo.optionscat && tipo === 'produtos') ||
                (key === 'MATERIAIS' && fieldInfo.optionsmat) ||
                (key === 'MARCA' && fieldInfo.optionsmarca)) {

                let options;
                if (key === 'COR' && fieldInfo.optionscor) {
                    options = fieldInfo.optionscor;
                } else if (key === 'CATEGORIA' && fieldInfo.optionscat && tipo === 'produtos') {
                    options = fieldInfo.optionscat;
                } else if (key === 'MATERIAIS' && fieldInfo.optionsmat) {
                    options = fieldInfo.optionsmat;
                } else if (key === 'MARCA' && fieldInfo.optionsmarca) {
                    options = fieldInfo.optionsmarca;
                }

                const optionsHtml = options.map(opt =>
                    `<option value="${opt.value}" ${opt.value == value ? 'selected' : ''}>${opt.label}</option>`
                ).join('');

                return `
        <div class="mb-3 detail-field-container" data-field="${key}">
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                    ${formattedKey}
                </label>
                <button class="btn btn-sm btn-outline-primary btn-edit-field" data-field="${key}" title="Editar este campo">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <div class="form-control-plaintext border-bottom pb-2 field-value">
                ${displayValue}
            </div>
            <div class="field-edit-container" style="display: none;">
                <select class="form-select field-edit-input">
                    <option value="">Selecione...</option>
                    ${optionsHtml}
                </select>
                <div class="d-flex justify-content-end mt-2">
                    <button class="btn btn-success btn-save-field me-2" data-field="${key}">
                        <i class="bi bi-check"></i> Salvar
                    </button>
                    <button class="btn btn-secondary btn-cancel-edit">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
        `;

            }


            if (key === 'PRECO') {

                return `
<div class="mb-3 detail-field-container" data-field="${key}">
    <div class="d-flex justify-content-between align-items-center">
        <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
            ${formattedKey}
        </label>
        <button class="btn btn-sm btn-outline-primary btn-edit-field" data-field="${key}" title="Editar este campo">
            <i class="bi bi-pencil"></i>
        </button>
    </div>
    <div class="form-control-plaintext border-bottom pb-2 field-value">
        ${value ? parseFloat(value).toFixed(2) : 'N/A'}
    </div>
    <div class="field-edit-container" style="display: none;">
        <input type="text" class="form-control field-edit-input" 
               value="${value ? parseFloat(value).toFixed(2) : ''}">
        <div class="d-flex justify-content-end mt-2">
            <button class="btn btn-success btn-save-field me-2" data-field="${key}">
                <i class="bi bi-check"></i> Salvar
            </button>
            <button class="btn btn-secondary btn-cancel-edit">
                <i class="bi bi-x"></i> Cancelar
            </button>
        </div>
    </div>
</div>
`;
            }

            if (key === 'NUMTELE' || key === 'TELEMOVEL') {
                return `
        <div class="mb-3 detail-field-container" data-field="${key}">
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                    ${formattedKey}
                </label>
                <button class="btn btn-sm btn-outline-primary btn-edit-field" data-field="${key}" title="Editar este campo">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <div class="form-control-plaintext border-bottom pb-2 field-value">
                ${formatarTelemovel(value)}
            </div>
            <div class="field-edit-container" style="display: none;">
                <input type="tel" class="form-control field-edit-input" value="${value || ''}" 
                       maxlength="9" pattern="[0-9\s]{9}">
                <div class="d-flex justify-content-end mt-2">
                    <button class="btn btn-success btn-save-field me-2" data-field="${key}">
                        <i class="bi bi-check"></i> Salvar
                    </button>
                    <button class="btn btn-secondary btn-cancel-edit">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
        `;
            }

            if (key === 'NIVEL') {
                return `
        <div class="mb-3 detail-field-container" data-field="${key}">
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                    ${formattedKey}
                </label>
                <button class="btn btn-sm btn-outline-primary btn-edit-field" data-field="${key}" title="Editar este campo">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <div class="form-control-plaintext border-bottom pb-2 field-value">
                ${value == 2 ? 'Admin' : 'Normal'}
            </div>
            <div class="field-edit-container" style="display: none;">
                <select class="form-select field-edit-input">
                    <option value="1" ${value == 1 ? 'selected' : ''}>Normal</option>
                    <option value="2" ${value == 2 ? 'selected' : ''}>Admin</option>
                </select>
                <div class="d-flex justify-content-end mt-2">
                    <button class="btn btn-success btn-save-field me-2" data-field="${key}">
                        <i class="bi bi-check"></i> Salvar
                    </button>
                    <button class="btn btn-secondary btn-cancel-edit">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
        `;
            }

            if (tipo === 'carousel') {
                if (key === 'IMG_CAROUSEL') {
                    const imageUrl = data && data[key + '_url'] ? data[key + '_url'] : '';

                    return `
        <div class="mb-3 detail-field-container" data-field="${key}">
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                    ${formattedKey}
                </label>
                <button class="btn btn-sm btn-outline-primary btn-edit-field" 
                        data-field="${key}" 
                        data-isimage="true"
                        title="Editar esta imagem">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>
            <div class="field-value">
                ${imageUrl ? `<img src="${imageUrl}" class="img-thumbnail" style="max-height: 100px;">` : 'Nenhuma imagem'}
            </div>
            <div class="field-edit-container" style="display: none;">
                <div class="alert alert-info mb-2">Selecione uma nova imagem para substituir</div>
                <div class="input-group">
                    <input type="file" class="form-control field-edit-input" accept="image/jpeg, image/png, image/gif">
                    <div class="input-group-append">
                        <button class="btn btn-success btn-save-field" data-field="${key}">
                            <i class="bi bi-check"></i> Salvar
                        </button>
                        <button class="btn btn-secondary btn-cancel-edit">
                            <i class="bi bi-x"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
                } else if (key === 'LIGACAO') {
                    return `
            <div class="mb-3 detail-field-container" data-field="${key}">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                        ${formattedKey}
                    </label>
                    <button class="btn btn-sm btn-outline-primary btn-edit-field" data-field="${key}" title="Editar este campo">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
                <div class="form-control-plaintext border-bottom pb-2 field-value">
                    ${displayValue}
                </div>
                <div class="field-edit-container" style="display: none;">
                    <input type="url" class="form-control field-edit-input" value="${value || ''}" 
                           placeholder="https://exemplo.com">
                    <div class="d-flex justify-content-end mt-2">
                        <button class="btn btn-success btn-save-field me-2" data-field="${key}">
                            <i class="bi bi-check"></i> Salvar
                        </button>
                        <button class="btn btn-secondary btn-cancel-edit">
                            <i class="bi bi-x"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        `;
                }
            }

            return `
    <div class="mb-3 detail-field-container" data-field="${key}">
        <div class="d-flex justify-content-between align-items-center">
            <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                ${formattedKey}
            </label>
            <button class="btn btn-sm btn-outline-primary btn-edit-field" data-field="${key}" title="Editar este campo">
                <i class="bi bi-pencil"></i>
            </button>
        </div>
        <div class="form-control-plaintext border-bottom pb-2 field-value">
            ${displayValue}
        </div>
        <div class="field-edit-container" style="display: none;">
            <input type="text" class="form-control field-edit-input" value="${value || ''}">
            <div class="d-flex justify-content-end mt-2">
                <button class="btn btn-success btn-save-field me-2" data-field="${key}">
                    <i class="bi bi-check"></i> Salvar
                </button>
                <button class="btn btn-secondary btn-cancel-edit">
                    <i class="bi bi-x"></i> Cancelar
                </button>
            </div>
        </div>
    </div>
    `;
        }

    </script>
</body>

</html>