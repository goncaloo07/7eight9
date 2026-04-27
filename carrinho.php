<?php
require "connection.php";
require_once './core.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$connection = db_connect();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$id = $_SESSION['id'];

$sql_previous_purchase = "SELECT * FROM PAP_INFO_PAGAMENTO WHERE ID_CLIENTE = ? LIMIT 1";
$stmt_previous = $connection->prepare($sql_previous_purchase);
$stmt_previous->bind_param("i", $id);
$stmt_previous->execute();
$previous_purchase_result = $stmt_previous->get_result();
$has_previous_purchase = $previous_purchase_result->num_rows > 0;
$previous_purchase_data = $has_previous_purchase ? $previous_purchase_result->fetch_assoc() : null;
$stmt_previous->close();

$sql = 'SELECT R.ID, R.NOME, CR.QNT, R.PRECO, 
               CASE WHEN R.FT_1 IS NOT NULL THEN CONCAT("data:image/jpeg;base64,", TO_BASE64(R.FT_1)) ELSE NULL END as FT_1, 
               TR.TAMANHO, TR.ID as size_id 
        FROM PAP_CARRINHO CR
        JOIN PAP_ROUPA R ON CR.ROUPA_ID = R.ID
        JOIN PAP_TAMANHO_ROUPA TR ON CR.TMNH = TR.ID
        WHERE CR.CLIENTE_ID = ?';

$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = $_SESSION['id'] ?? 0;

    if ($user_id == 0) {
        header('Location: login.php');
        exit();
    }

    $roupa_id = filter_input(INPUT_POST, 'roupa_id', FILTER_SANITIZE_NUMBER_INT);
    $new_qty = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);
    $new_size = filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT);

    $stock_sql = "SELECT QNT as available FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = ? AND TAMANHO_ID = ?";
    $stock_stmt = $connection->prepare($stock_sql);
    $stock_stmt->bind_param("ii", $roupa_id, $new_size);
    $stock_stmt->execute();
    $stock_result = $stock_stmt->get_result();
    $stock_row = $stock_result->fetch_assoc();
    $availableStock = $stock_row['available'] ?? 0;
    $stock_stmt->close();

    if ($_POST['action'] == 'update') {
        header('Content-Type: application/json');
        if ($availableStock == 0) {
            $mensagem = "Erro: O artigo está esgotado.";
            $tipoMensagem = "danger";
        } else {
            if ($new_qty > $availableStock) {
                $new_qty = $availableStock;
                $mensagem = "A quantidade foi ajustada automaticamente para o stock disponível ($availableStock).";
                $tipoMensagem = "warning";
            }

            $sqlUpdate = "UPDATE PAP_CARRINHO SET QNT = ?, TMNH = ? WHERE CLIENTE_ID = ? AND ROUPA_ID = ?";
            $stmtUpdate = $connection->prepare($sqlUpdate);
            $stmtUpdate->bind_param("iiii", $new_qty, $new_size, $user_id, $roupa_id);

            if ($stmtUpdate->execute()) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    $sqlItem = 'SELECT R.ID, R.NOME, CR.QNT, R.PRECO, CASE WHEN FT_1 IS NOT NULL THEN CONCAT("data:image/jpeg;base64,", TO_BASE64(FT_1)) ELSE NULL END as FT_1, TR.TAMANHO, TR.ID as size_id 
                                FROM PAP_CARRINHO CR
                                JOIN PAP_ROUPA R ON CR.ROUPA_ID = R.ID
                                JOIN PAP_TAMANHO_ROUPA TR ON CR.TMNH = TR.ID
                                WHERE CR.CLIENTE_ID = ? AND R.ID = ?';

                    $stmtItem = $connection->prepare($sqlItem);
                    $stmtItem->bind_param("ii", $user_id, $roupa_id);
                    $stmtItem->execute();
                    $resultItem = $stmtItem->get_result();
                    $row = $resultItem->fetch_assoc();
                    ob_start(); ?>
                    <div class="cart-item" data-prod-id="<?= $row['ID'] ?>">
                        <div class="cart-item-image">
                            <img src="<?= $row['FT_1'] ?>" alt="<?= $row['NOME'] ?>">
                        </div>
                        <div class="cart-item-details">
                            <div class="item-top">
                                <h4 class="product-name" title="<?php echo $row['NOME']; ?>">
                                    <?php echo strlen($row['NOME']) > 50 ? substr($row['NOME'], 0, 50) . '...' : $row['NOME']; ?>
                                </h4>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-edit" data-prod-id="<?= $row['ID'] ?>" data-editing="false">
                                        <i class="bi bi-pencil-square iedit"></i>
                                    </button>
                                    <form method="POST" class="remove-form">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="roupa_id" value="<?= $row['ID'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger remove-btn">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="item-middle">
                                <form method="POST" class="update-form" id="update-form-<?= $row['ID'] ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="roupa_id" value="<?= $row['ID'] ?>">
                                    <div class="mb-2">
                                        <label for="quantity_<?= $row['ID'] ?>" class="me-2">Qtd:</label>
                                        <div class="quantity-group d-inline-flex">
                                            <button type="button" class="decrement-btn d-none"
                                                data-target="quantity_<?= $row['ID'] ?>">−</button>
                                            <input type="number" name="quantity" id="quantity_<?= $row['ID'] ?>" value="<?= $row['QNT'] ?>"
                                                min="1" max="<?= $availableStock ?>" readonly>
                                            <button type="button" class="increment-btn d-none"
                                                data-target="quantity_<?= $row['ID'] ?>">+</button>
                                        </div>
                                    </div>
                                    <div class="mb-2 size-group">
                                        <label for="size_<?= $row['ID'] ?>">Tamanho:</label>
                                        <select name="size" id="size_<?= $row['ID'] ?>" class="form-control form-control-sm ms-2" disabled>
                                            <?php
                                            $size_sql = "SELECT TR.ID, TR.TAMANHO 
                                                         FROM PAP_TAMANHO_ROUPA TR
                                                         JOIN PAP_ROUPA_HAS_TAMANHO RT ON RT.TAMANHO_ID = TR.ID
                                                         WHERE RT.ROUPA_ID = ? AND RT.QNT > 0";
                                            $size_stmt = $connection->prepare($size_sql);
                                            $size_stmt->bind_param("i", $row['ID']);
                                            $size_stmt->execute();
                                            $size_result = $size_stmt->get_result();
                                            while ($size_row = $size_result->fetch_assoc()):
                                                $selected = ($size_row['ID'] == $row['size_id']) ? "selected" : ""; ?>
                                                <option value="<?= $size_row['ID'] ?>" <?= $selected ?>><?= $size_row['TAMANHO'] ?></option>
                                            <?php endwhile;
                                            $size_stmt->close(); ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="item-bottom">
                                <h5 class="price-text"><?= $row['PRECO'] ?>€</h5>
                                <button type="submit" form="update-form-<?= $row['ID'] ?>" class="btn btn-primary update-btn d-none">
                                    <i class="bi bi-arrow-repeat"></i>
                                    Atualizar Item
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php
                    echo ob_get_clean();
                    exit;
                }
                if (!isset($mensagem)) {
                    $mensagem = "Carrinho atualizado com sucesso.";
                    $tipoMensagem = "success";
                }
            } else {
                $mensagem = "Erro ao atualizar o carrinho: " . $stmtUpdate->error;
                $tipoMensagem = "danger";
            }
            $stmtUpdate->close();
        }
    } elseif ($_POST['action'] == 'remove') {
        $sqlRemove = "DELETE FROM PAP_CARRINHO WHERE CLIENTE_ID = ? AND ROUPA_ID = ?";
        $stmtRemove = $connection->prepare($sqlRemove);
        $stmtRemove->bind_param("ii", $user_id, $roupa_id);

        if ($stmtRemove->execute()) {
            $mensagem = "Item removido do carrinho com sucesso.";
            $tipoMensagem = "success";
        } else {
            $mensagem = "Erro ao remover o item: " . $stmtRemove->error;
            $tipoMensagem = "danger";
        }
        $stmtRemove->close();
    } elseif ($_POST['action'] == 'finalize_purchase') {
        $user_id = $_SESSION['id'];
        $connection->begin_transaction();

        try {
            $required_fields = [
                'cardNumber' => 'Número do cartão',
                'cardName' => 'Nome do titular',
                'expMonth' => 'Mês de expiração',
                'expYear' => 'Ano de expiração',
                'cvv' => 'CVV',
                'address' => 'Endereço',
                'city' => 'Cidade',
                'state' => 'País',
                'zip' => 'Código postal'
            ];

            foreach ($required_fields as $field => $name) {
                if (empty($_POST[$field])) {
                    throw new Exception("O campo $name é obrigatório");
                }
            }

            $cardNumber = preg_replace('/\D/', '', $_POST['cardNumber']); // Remove todos os não-dígitos
            $cardName = htmlspecialchars(trim($_POST['cardName']), ENT_QUOTES, 'UTF-8');
            $expMonth = (int) $_POST['expMonth'];
            $expYear = (int) $_POST['expYear'];
            $cvv = (int) $_POST['cvv'];
            $address = htmlspecialchars(trim($_POST['address']), ENT_QUOTES, 'UTF-8');
            $city = htmlspecialchars(trim($_POST['city']), ENT_QUOTES, 'UTF-8');
            $state = substr(htmlspecialchars(trim($_POST['state']), ENT_QUOTES, 'UTF-8'), 0, 2);
            $zip = preg_replace('/[^0-9-]/', '', $_POST['zip']);

            if (!preg_match('/^[0-9]{13,19}$/', $cardNumber)) { // Valida intervalo padrão de CC
                throw new Exception("Número de cartão inválido");
            }

            $cardu4 = substr($cardNumber, -4);

            if (!preg_match('/^[0-9]{3,4}$/', $_POST['cvv'])) {
                throw new Exception("CVV inválido");
            }

            if ($_POST['expMonth'] < 1 || $_POST['expMonth'] > 12) {
                throw new Exception("Mês de expiração inválido");
            }

            if ($_POST['expYear'] < date('Y')) {
                throw new Exception("Ano de expiração inválido");
            }

            $currentYear = date('Y');
            $currentMonth = date('m');
            if (
                $_POST['expYear'] < $currentYear ||
                ($_POST['expYear'] == $currentYear && $_POST['expMonth'] < $currentMonth)
            ) {
                throw new Exception("Cartão expirado");
            }

            $encryption_key = getenv('goncalo');
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

            $encryptedCard = openssl_encrypt($cardNumber, 'aes-256-cbc', $encryption_key, 0, $iv);
            $encryptedCard = base64_encode($iv . $encryptedCard);

            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
            $encryptedCvv = openssl_encrypt($cvv, 'aes-256-cbc', $encryption_key, 0, $iv);
            $encryptedCvv = base64_encode($iv . $encryptedCvv);

            $payment_sql = "INSERT INTO PAP_INFO_PAGAMENTO (
                ID_CLIENTE, 
                NUMERO_CARTAO, 
                CARTAO_U4,
                NOME_TITULAR, 
                MES_EXPIRACAO, 
                ANO_EXPIRACAO, 
                CVV, 
                RUA_ENDERECO, 
                CIDADE_ENDERECO, 
                PAIS, 
                CODIGO_POSTAL
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $payment_stmt = $connection->prepare($payment_sql);
            $payment_stmt->bind_param(
                "isssiisssss",
                $user_id,
                $encryptedCard,
                $cardu4,
                $cardName,
                $expMonth,
                $expYear,
                $encryptedCvv,
                $address,
                $city,
                $state,
                $zip
            );
            $payment_stmt->execute();
            $payment_id = $connection->insert_id;

            $cart_sql = "SELECT ROUPA_ID, TMNH, QNT FROM PAP_CARRINHO WHERE CLIENTE_ID = ?";
            $stmt = $connection->prepare($cart_sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $cartItems = $result->fetch_all(MYSQLI_ASSOC);

            if (empty($cartItems)) {
                throw new Exception("Carrinho vazio!");
            }

            // Registrar compra
            $insert_sql = "INSERT INTO PAP_COMPRAS (CLIENTE_ID, DATA_COMPRA, TOTAL, ID_PAGAMENTO) 
                      VALUES (?, NOW(), ?, ?)";
            $insert_stmt = $connection->prepare($insert_sql);
            // Calcular total
            $total = 0;
            foreach ($cartItems as $item) {
                $price_sql = "SELECT PRECO FROM PAP_ROUPA WHERE ID = ?";
                $price_stmt = $connection->prepare($price_sql);
                $price_stmt->bind_param("i", $item['ROUPA_ID']);
                $price_stmt->execute();
                $price_result = $price_stmt->get_result();
                $price_row = $price_result->fetch_assoc();
                $total += $price_row['PRECO'] * $item['QNT'];
            }

            $insert_stmt->bind_param("idi", $user_id, $total, $payment_id);
            $insert_stmt->execute();
            $compra_id = $connection->insert_id;

            // Registrar itens da compra
            $item_sql = "INSERT INTO PAP_COMPRAS_ROUPA (COMPRA_ID, ROUPA_ID, TAMANHO_ID, QNTD, PRECO_UNITARIO) 
                         VALUES (?, ?, ?, ?, ?)";
            $item_stmt = $connection->prepare($item_sql);

            foreach ($cartItems as $item) {
                // Obter preço unitário
                $price_sql = "SELECT PRECO FROM PAP_ROUPA WHERE ID = ?";
                $price_stmt = $connection->prepare($price_sql);
                $price_stmt->bind_param("i", $item['ROUPA_ID']);
                $price_stmt->execute();
                $price_result = $price_stmt->get_result();
                $price_row = $price_result->fetch_assoc();

                $item_stmt->bind_param(
                    "iiiid",
                    $compra_id,
                    $item['ROUPA_ID'],
                    $item['TMNH'],
                    $item['QNT'],
                    $price_row['PRECO']
                );
                $item_stmt->execute();
            }

            $stock_sql = "UPDATE PAP_ROUPA_HAS_TAMANHO 
                         SET QNT = QNT - ? 
                         WHERE ROUPA_ID = ? AND TAMANHO_ID = ?";
            $stock_stmt = $connection->prepare($stock_sql);

            foreach ($cartItems as $item) {
                $stock_stmt->bind_param(
                    "iii",
                    $item['QNT'],
                    $item['ROUPA_ID'],
                    $item['TMNH']
                );
                $stock_stmt->execute();
            }

            $delete_sql = "DELETE FROM PAP_CARRINHO WHERE CLIENTE_ID = ?";
            $delete_stmt = $connection->prepare($delete_sql);
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();

            $products = [];
            foreach ($cartItems as $item) {
                if (!isset($products[$item['ROUPA_ID']])) {
                    $sql = "SELECT NOME, 
                       CASE WHEN FT_1 IS NOT NULL THEN CONCAT('data:image/jpeg;base64,', TO_BASE64(FT_1)) ELSE NULL END as FT_1 
                FROM PAP_ROUPA WHERE ID = ?";
                    $stmt = $connection->prepare($sql);
                    $stmt->bind_param("i", $item['ROUPA_ID']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $product = $result->fetch_assoc();

                    $products[$item['ROUPA_ID']] = [
                        'nome' => $product['NOME'],
                        'imagem' => $product['FT_1'] ? $product['FT_1'] : 'img/images.jpg'
                    ];
                }
            }

            $connection->commit();

            echo json_encode([
                'success' => true,
                'idcompra' => $payment_id,
                'produtos' => array_values($products),
                'total' => number_format($total, 2, ",", ".")
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Falha no processamento: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carrinho</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="icon" href="https://alpha.soaresbasto.pt/~a25385/PAP/img/logo.png" type="image/gif" />
    <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
    <link href="https://fonts.googleapis.com/css?family=Great+Vibes|Open+Sans:400,700&display=swap&subset=latin-ext"
        rel="stylesheet">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.css"
        media="screen">
    <link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Layout Geral */
        body {
            background: rgb(230, 230, 230);
            font-family: 'Open Sans', sans-serif;
        }

        .cart-container {
            margin: 0px auto;
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 8px;
            max-width: 1200px;
        }

        .cart-title {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 2.5rem;
            font-weight: 700;
            padding-bottom: 20px;
        }

        .cart-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 3px;
            background: rgb(161, 161, 161);
        }

        .cart-alert {
            margin-bottom: 20px;
        }

        .cart-item {
            padding-left: 1vw;
            display: flex;
            align-items: stretch;
            background: #fff;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
        }

        .cart-items-container.multiple {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .cart-items-container.multiple .cart-item {
            margin-bottom: 0;
        }

        .cart-item-image {
            flex: 0 0 250px;
            position: relative;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .cart-item-details {
            flex: 1;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-top .product-name {
            margin: 0;
            font-size: 1.75rem;
            color: #333;
        }

        .remove-btn {
            background: transparent;
            border: none;
            color: red;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .item-middle {
            margin-top: 10px;
            width: 100%;
        }

        .item-bottom {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
        }


        .price-text {
            font-size: 4vmax;
            margin: 0;
            order: 1;
        }

        .quantity-group {
            display: flex;
            align-items: center;
        }

        .quantity-group button {
            border: 1px solid #ced4da;
            background: #fff;
            color: #495057;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            cursor: pointer;
        }

        .quantity-group input {
            width: 50px;
            text-align: center;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
        }

        .size-group {
            margin-top: 10px;
            display: flex;
            align-items: center;
        }

        .size-group label {
            margin-right: 5px;
            margin-bottom: 0;
        }

        .size-group select {
            border: 2px solid rgb(0, 0, 0);
            border-radius: 4px;
        }

        .form-control {
            width: 5vw;
            max-width: 50px;
            min-width: 40px;
            text-align: center;
        }

        .update-form {
            display: flex;
            flex-direction: column;
        }

        .update-btn {
            margin-top: 0;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #0d6efd;
            background: #0d6efd;
            color: white;
            order: 2;
        }

        .update-btn:hover {
            background: #0b5ed7;
            border-color: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }

        .update-btn:active {
            transform: translateY(0);
            box-shadow: none;
        }

        @media (max-width: 950px) {
            .update-btn {
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
            }
        }

        .btn-edit {
            padding: 5px 10px;
            background-color: transparent;
            font-size: 1.3rem;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-edit:hover {
            transform: scale(1.1);
            color: #0d6efd;
        }

        @media (max-width: 950px) {
            .btn-checkout {
                padding: 15px 30px;
                font-size: 1.5rem;
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                margin-top: 15px;
            }

            .btn-checkout i {
                font-size: 1.5em;
            }

            .cart-summary {
                flex-direction: column;
                gap: 15px;
            }

            .summary-info p {
                display: flex;
                justify-content: space-between;
                width: 100%;
            }
        }

        /* Estilos originais (telas grandes) */
        .cart-summary {
            border-top: 1px solid #ccc;
            padding-top: 20px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }

        .summary-info p {
            margin: 0;
            font-size: 1.25rem;
            text-align: left;
        }

        .total {
            font-size: 2rem !important;
        }

        .btn-checkout {
            font-size: 1.25rem;
            padding: 10px 20px;
        }

        .navbar {
            padding: 0.5rem 1rem;
        }

        .total {
            font-size: 2rem !important;
            padding-top: 1vh;
        }

        .iedit {
            color: blue;
        }

        .payment-form .form-group {
            margin-bottom: 1rem;
        }

        .payment-form label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .payment-form input,
        .payment-form select {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
        }

        #cardNumber {
            letter-spacing: 2px;
        }

        #confirmPayment {
            background-color: #198754;
            padding: 0.5rem 1.5rem;
        }

        .modal-content {
            border-radius: 0.5rem;
        }

        .modal-footer {
            justify-content: flex-start !important;
        }

        .modal-footer .me-auto {
            margin-right: auto !important;
        }

        #paymentModal .modal-footer .total {
            font-size: 1.5rem !important;
            color: #333;
            margin: 0;
            border-radius: 5px;
        }

        #paymentModal .modal-footer {
            align-items: center;
            border-top: 2px solid #dee2e6;
            padding-top: 0 !important;
        }

        #paymentModal ::placeholder {
            opacity: 0.5;
        }


        @media (max-width: 950px) {
            .cart-item {
                flex-direction: column;
            }

            .cart-item-image {
                flex: none;
                width: 100%;
                height: auto;
                padding-top: 1vh;
                padding-right: 1vw;
            }

            .cart-item-details {
                padding: 10px;
            }

            .item-middle {
                flex-direction: column;
            }

            .quantity-group input {
                width: 100%;
            }
        }

        @media (min-width: 950px) {
            .bi-cart-check {
                padding-right: 10px;
            }
        }

        @media (min-width: 768px) {
            .total {
                font-size: 2.5rem !important;
            }
        }

        @media (max-width: 768px) {
            .ps-3 {
                padding-left: 0rem !important;
                margin-top: 0 !important;
            }

            .pe-3 {
                padding-right: 0 !important;
            }
        }

        .precoroupa,
        .addcart {
            width: 45%;
        }

        #paymentModal .form-group {
            width: 100%;
            margin-bottom: 1.5rem;
        }

        #paymentModal .form-control {
            width: 100% !important;
            max-width: none !important;
            display: block;
            box-sizing: border-box;
        }

        #paymentModal .form-select {
            width: 100% !important;
            max-width: none !important;
        }

        #paymentModal .modal-body {
            padding: 2rem;
            padding-bottom: 0 !important;
        }

        #paymentModal .row.g-4 {
            --bs-gutter-x: 1.5rem;
            margin-left: 0;
            margin-right: 0;
        }

        #paymentModal .row.g-4>[class^="col-"] {
            padding-left: 0;
            padding-right: 0;
        }

        #paymentModal .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        #paymentModal .d-flex.gap-3 {
            gap: 1rem !important;
        }

        #paymentModal .flex-grow-1 {
            flex-grow: 1;
        }

        .show {
            padding-right: 0 !important;
        }

        #statusModal .modal-content {
            border: none;
            border-radius: 15px;
        }

        #statusModal .modal-body {
            padding: 2rem;
        }

        #statusModal .spinner-border {
            border-width: 0.25em;
        }

        #statusModal .lead {
            font-size: 1.25rem;
            margin-bottom: 0;
        }

        .purchased-product-img {
            width: 100%;
            object-fit: contain;
            padding: 10px;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .purchased-product-img:hover {
            transform: scale(1.05);
        }

        .col-product {
            flex: 0 0 50%;
            max-width: 100%;
            padding: 0;
        }

        .row.justify-content-center {
            justify-content: center;
        }

        .total-price {
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 0 0 15px 0;
            text-align: center;
        }

        .total-price h4 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 600;
        }

        .total-price span {
            color: #28a745;
            font-weight: 700;
        }

        .cart-container a {
            color: inherit;
            /* Mantém a cor do texto igual ao elemento pai */
            text-decoration: none;
            /* Remove o sublinhado */
        }

        .cart-container a:hover {
            text-decoration: none;
        }

        #confirmPreviousDataModal .modal-content {
            border-radius: 0.5rem;
        }

        #confirmPreviousDataModal .modal-body {
            padding: 1rem;
            text-align: center;
            border-top: rgba(179, 179, 179, 0.5) solid thin;
            border-bottom: rgba(196, 196, 196, 0.5) solid thin;
            margin: 0 1rem;
        }

        #confirmPreviousDataModal .text-muted {
            margin-bottom: 0;
        }

        #confirmPreviousDataModal .btn-close {
            padding: 1rem;
        }

        #confirmPreviousDataModal .modal-footer {
            justify-content: center;
            border-top: none;
            padding-bottom: 1.5rem;
        }

        #confirmPreviousDataModal .btn {
            min-width: 100px;
        }

        #expMonth option[value=""],
        #expYear option[value=""] {
            opacity: 0.5;
            color: #6c757d;
        }

        #expMonth:invalid,
        #expYear:invalid {
            color: #6c757d;
        }

        .empty-cart {
         background: #fff;
         border-radius: 12px;
         padding: 20px;
         box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
      }

      .empty-cart i {
         color: #a1a1a1;
      }
      .empty-cart a {
        color: white
      }
    </style>
</head>

<body>
    <?php include "header.php"; ?>
    <div class="container cart-container">
        <h1 class="cart-title">Carrinho de <?php echo $_SESSION['user'] ?> </h1>
        <?php
        if (!empty($mensagem)) {
            echo "<div class='alert alert-$tipoMensagem cart-alert alert-dismissible fade show' role='alert'>$mensagem
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
        $cartItems = $result->fetch_all(MYSQLI_ASSOC);
        if (count($cartItems) == 0) {
            echo '<div class="col-12">
            <div class="empty-cart text-center pb-4 pt-3">
                <i class="bi bi-cart-x text-secondary" style="font-size: 5rem;"></i>
                <h3 class="mt-2">O seu carrinho está vazio</h3>
                <p class="text-muted">Ainda não adicionou nada ao seu carrinho. Adicione para poder comprar os produtos!</p>
                <a href="roupa.php?tipo=0" class="btn btn-dark mt-3 px-4 py-2">
                    <i class="bi bi-shop"></i> Ver produtos
                </a>
          </div>
            </div>';
        } else {
            ?>
            <div class="cart-items-container <?php echo (count($cartItems) > 1) ? 'multiple' : 'single'; ?>">
                <?php
                foreach ($cartItems as $row) {
                    $prodID = $row['ID'];
                    $size_id = $row['size_id'];

                    $stock_sql = "SELECT QNT as available FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = ? AND TAMANHO_ID = ?";
                    $stock_stmt = $connection->prepare($stock_sql);
                    $stock_stmt->bind_param("ii", $prodID, $size_id);
                    $stock_stmt->execute();
                    $stock_result = $stock_stmt->get_result();
                    $stock_row = $stock_result->fetch_assoc();
                    $availableStock = $stock_row['available'];
                    $stock_stmt->close();
                    ?>
                    <div class="cart-item" data-prod-id="<?php echo $prodID; ?>">
                        <div class="cart-item-image">
                            <a href="https://alpha.soaresbasto.pt/~a25385/PAP/roupa2.php?tipo=<?php echo $prodID ?>">
                                <img src="<?php echo $row['FT_1']; ?>" alt="<?php echo $row['NOME']; ?>">
                            </a>
                        </div>
                        <div class="cart-item-details">
                            <div class="item-top">
                                <h4 class="product-name" title="<?= $row['NOME'] ?>">
                                    <?= strlen($row['NOME']) > 50 ? substr($row['NOME'], 0, 50) . '...' : $row['NOME'] ?>
                                </h4>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-edit" data-prod-id="<?php echo $prodID; ?>"
                                        data-editing="false">
                                        <i class="bi bi-pencil-square iedit"></i>
                                    </button>
                                    <form method="POST" class="remove-form">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="roupa_id" value="<?php echo $prodID; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger remove-btn">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="item-middle">
                                <form method="POST" class="update-form" id="update-form-<?php echo $prodID; ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="roupa_id" id="roupa_id" value="<?php echo $prodID; ?>">
                                    <div class="mb-2">
                                        <label for="quantity_<?php echo $prodID; ?>" class="me-2">Qtd:</label>
                                        <div class="quantity-group d-inline-flex">
                                            <button type="button" class="decrement-btn d-none"
                                                data-target="quantity_<?php echo $prodID; ?>">−</button>
                                            <input type="number" name="quantity" id="quantity_<?php echo $prodID; ?>"
                                                value="<?php echo $row['QNT']; ?>" min="1" max="<?php echo $availableStock; ?>"
                                                readonly>
                                            <button type="button" class="increment-btn d-none"
                                                data-target="quantity_<?php echo $prodID; ?>">+</button>
                                        </div>
                                    </div>
                                    <div class="mb-2 size-group">
                                        <label for="size_<?php echo $prodID; ?>">Tamanho:</label>
                                        <select name="size" id="size_<?php echo $prodID; ?>"
                                            class="form-control form-control-sm ms-2" disabled>
                                            <?php
                                            $size_sql = "SELECT TR.ID, TR.TAMANHO 
                                FROM PAP_TAMANHO_ROUPA TR, PAP_ROUPA_HAS_TAMANHO RT 
                                WHERE RT.ROUPA_ID = ? AND RT.TAMANHO_ID = TR.ID AND RT.QNT > 0";
                                            $size_stmt = $connection->prepare($size_sql);
                                            $size_stmt->bind_param("i", $prodID);
                                            $size_stmt->execute();
                                            $size_result = $size_stmt->get_result();
                                            while ($size_row = $size_result->fetch_assoc()) {
                                                $selected = ($size_row['ID'] == $size_id) ? "selected" : "";
                                                echo "<option value='{$size_row['ID']}' $selected>{$size_row['TAMANHO']}</option>";
                                            }
                                            $size_stmt->close();
                                            ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="item-bottom">
                                <h5 class="price-text"><?php echo $row['PRECO']; ?>€</h5>
                                <button type="submit" form="update-form-<?php echo $prodID; ?>"
                                    class="btn btn-primary update-btn d-none">
                                    <i class="bi bi-arrow-repeat"></i>
                                    Atualizar Item
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                echo '</div>';
                $totalPrice = 0;
                $totalArticles = 0;
                foreach ($cartItems as $item) {
                    $totalPrice += $item['PRECO'] * $item['QNT'];
                    $totalArticles += $item['QNT'];
                }
                ?>
                <div class="cart-summary">
                    <div class="summary-info">
                        <p>Total de Artigos: <?php echo $totalArticles; ?></p>
                        <p class="total">Total: <?php echo number_format($totalPrice, 2, ",", "."); ?>€</p>
                    </div>
                    <button class="btn btn-success btn-checkout" data-bs-toggle="modal" data-bs-target="#paymentModal">
                        <i class="bi bi-cart-check"></i>Finalizar Compra
                    </button>

                </div>
                <?php
        }
        ?>
        </div>
        <div class="modal fade" id="confirmPreviousDataModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Usar dados anteriores?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Detetamos que já fez uma compra antes. Deseja usar os mesmos dados de endereço e titular do
                            cartão?</p>
                        <p class="text-muted small">O número do cartão e CVV não serão preenchidos por segurança.</p>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" id="reuseDataNo">Não</button>
                        <button type="button" class="btn btn-primary" id="reuseDataYes">Sim</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalLabel">Finalizar Compra</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            style="padding-right:25px"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form class="payment-form" id="paymentForm">
                            <div class="row g-4 mx-0">
                                <!-- Coluna Esquerda -->
                                <div class="col-md-6 pe-3">
                                    <!-- Número do Cartão -->
                                    <div class="form-group">
                                        <label class="form-label">Número do Cartão:</label>
                                        <input type="text" id="cardNumber" name="cardNumber"
                                            class="form-control form-control-lg" placeholder="1234 5678 9012 3456"
                                            required>
                                    </div>

                                    <!-- Nome do Titular -->
                                    <div class="form-group">
                                        <label class="form-label">Nome do Titular:</label>
                                        <input type="text" id="cardName" class="form-control form-control-lg"
                                            placeholder="Como está no cartão" required>
                                    </div>

                                    <!-- Validade e CVV -->
                                    <div class="form-group">
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <label class="form-label">Mês:</label>
                                                <select id="expMonth" class="form-select form-select-lg" required>
                                                    <option value="" disabled selected>MM</option>
                                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>">
                                                            <?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Ano:</label>
                                                <select id="expYear" class="form-select form-select-lg" required>
                                                    <option value="" disabled selected>AAAA</option>
                                                    <?php for ($y = date('Y'); $y <= date('Y') + 10; $y++): ?>
                                                        <option value="<?= $y ?>"><?= $y ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CVV -->
                                    <div class="form-group">
                                        <label class="form-label">CVV:</label>
                                        <input type="text" id="cvv" class="form-control form-control-lg"
                                            pattern="\d{3,4}" maxlength="4" placeholder="123" required>
                                    </div>
                                </div>

                                <!-- Coluna Direita -->
                                <div class="col-md-6 ps-3">
                                    <!-- Endereço -->
                                    <div class="form-group">
                                        <label class="form-label">Endereço de Cobrança:</label>
                                        <input type="text" id="address" class="form-control form-control-lg mb-3"
                                            placeholder="Rua" required>
                                        <input type="text" id="city" class="form-control form-control-lg mb-3"
                                            placeholder="Cidade" required>

                                        <label class="form-label">Pais:</label>
                                        <input type="text" id="state" class="form-control form-control-lg mb-3"
                                            placeholder="PT" maxlength="2" required>
                                        <label class="form-label">Codigo-Postal:</label>
                                        <input type="text" id="zip" class="form-control form-control-lg"
                                            placeholder="0000-000" pattern="\d{4}-?\d{3}" required>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <div class="me-auto">
                            <h4 class="total mb-0">Total:
                                <?php echo isset($totalPrice) ? number_format($totalPrice, 2, ",", ".") : '0,00' ?>€
                            </h4>
                        </div>
                        <button type="button" class="btn btn-success btn-lg" id="confirmPayment">Confirmar
                            Pagamento</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0" style="padding-bottom: 0;">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            style="padding: 1rem;"></button>
                    </div>
                    <div class="modal-body text-center py-1 pb-4">
                        <!-- Loading -->
                        <div id="statusLoading">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <h4 class="mt-3">Processando sua compra...</h4>
                        </div>

                        <!-- Sucesso -->
                        <div id="statusSuccess" class="d-none">
                            <div class="mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#28a745"
                                    class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                                    <path
                                        d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z" />
                                </svg>
                            </div>
                            <h4 class="text-success mb-3" style="margin-bottom: 0.4rem!important;">Compra concluída com
                                sucesso!</h4>
                            <p id="idcompra" style="font-weight: lighter;"></p>
                            <div class="container mt-4" style="margin-top: 0!important;">
                                <div class="row justify-content-center" id="purchasedProducts">
                                </div>
                            </div>
                            <div class="total-price mt-3">
                                <h4 class="text-dark">Total Pago: <span
                                        id="totalCompraBottom"><?php echo isset($totalPrice) ? number_format($totalPrice, 2, ",", ".") : '0,00' ?>€</span>
                                </h4>
                            </div>
                            <p id="successMessage" class="lead">Obrigado pela sua compra e confiança,
                                <?php echo $_SESSION['user'] ?>!
                            </p>
                        </div>

                        <!-- Erro -->
                        <div id="statusError" class="d-none">
                            <div class="mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#dc3545"
                                    class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                                    <path
                                        d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z" />
                                </svg>
                            </div>
                            <h4 class="text-danger mb-3">Erro na compra!</h4>
                            <p id="errorMessage" class="lead"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
        <script src="js/jquery.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap.bundle.min.js"></script>
        <script src="js/jquery-3.0.0.min.js"></script>
        <script src="js/plugin.js"></script>
        <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
        <script src="js/custom.js"></script>
        <script src="js/owl.carousel.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
        <script src="https://unpkg.com/gijgo@1.9.13/js/gijgo.min.js" type="text/javascript"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {

                $('.btn-checkout').removeAttr('data-bs-toggle data-bs-target').click(function () {
                    <?php if ($has_previous_purchase): ?>
                        // Mostrar modal de confirmação para reutilizar dados
                        const confirmModal = new bootstrap.Modal(document.getElementById('confirmPreviousDataModal'));
                        confirmModal.show();
                    <?php else: ?>
                        // Se não tiver compra anterior, mostrar diretamente o modal de pagamento
                        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                        paymentModal.show();
                    <?php endif; ?>
                });

                // Quando clicar em "Sim" para reutilizar dados
                $('#reuseDataYes').click(function () {
                    <?php if ($has_previous_purchase && $previous_purchase_data): ?>
                        // Preencher os campos com os dados anteriores (exceto cartão e CVV)
                        $('#cardName').val('<?php echo addslashes($previous_purchase_data["NOME_TITULAR"]); ?>');
                        $('#address').val('<?php echo addslashes($previous_purchase_data["RUA_ENDERECO"]); ?>');
                        $('#city').val('<?php echo addslashes($previous_purchase_data["CIDADE_ENDERECO"]); ?>');
                        $('#state').val('<?php echo addslashes($previous_purchase_data["PAIS"]); ?>');
                        $('#zip').val('<?php echo addslashes($previous_purchase_data["CODIGO_POSTAL"]); ?>');

                        // Preencher data de expiração se disponível
                        const expMonth = '<?php echo str_pad($previous_purchase_data["MES_EXPIRACAO"], 2, "0", STR_PAD_LEFT); ?>';
                        const expYear = '<?php echo $previous_purchase_data["ANO_EXPIRACAO"]; ?>';

                        if (expMonth) $('#expMonth').val(expMonth);
                        if (expYear) $('#expYear').val(expYear);
                    <?php endif; ?>

                    // Fechar o modal de confirmação e abrir o de pagamento
                    $('#confirmPreviousDataModal').modal('hide');
                    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                    paymentModal.show();
                });

                // Quando clicar em "Não" para reutilizar dados
                $('#reuseDataNo').click(function () {
                    $('#paymentForm')[0].reset();
                    $('#confirmPreviousDataModal').modal('hide');
                    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                    paymentModal.show();
                });

                setTimeout(function () {
                    $('.cart-alert').fadeOut('slow', function () {
                        $(this).remove();
                    });
                }, 3000);

                // Delegation para elementos dinâmicos
                $(document).on('click', '.btn-edit, .update-btn', function (e) {
                    e.preventDefault();
                    const button = $(this);
                    const container = button.closest('.cart-item');
                    const form = container.find('.update-form');
                    const formData = form.serialize();

                    // Verificar se é o botão de edição ou atualização
                    const isEditButton = button.hasClass('btn-edit');
                    const isEditing = isEditButton ? button.data('editing') : true;

                    if (isEditButton && !isEditing) {
                        // Ativar modo edição
                        container.find('input[name="quantity"]').prop('readonly', false);
                        container.find('select[name="size"]').prop('disabled', false);
                        container.find('.increment-btn, .decrement-btn').removeClass('d-none');
                        container.find('.update-btn').removeClass('d-none');
                        button.data('editing', true);
                    } else {
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        })
                            .then(response => response.text())
                            .then(html => {
                                const container = form.closest('.cart-item');
                                container.replaceWith(html);
                                updateCartTotals();
                                showAlert('Item atualizado com sucesso', 'success');
                            })
                            .catch(error => {
                                showAlert('Erro ao atualizar item', 'danger');
                                console.error('Error:', error);
                            });
                    }
                });


                // Função para atualizar totais
                function updateCartTotals() {
                    fetch(window.location.href, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newSummary = doc.querySelector('.cart-summary');
                            document.querySelector('.cart-summary').replaceWith(newSummary);
                        });
                }

                // Função para mostrar alertas
                function showAlert(message, type) {
                    const alertHTML = `
        <div class="alert alert-${type} cart-alert alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

                    const existingAlerts = $('.cart-alert');
                    if (existingAlerts.length > 0) {
                        existingAlerts.first().before(alertHTML);
                    } else {
                        $('.cart-title').after(alertHTML);
                    }

                    setTimeout(() => {
                        $(`.alert`).fadeOut('slow', function () {
                            $(this).remove();
                        });
                    }, 3000);
                }

                // Incrementar quantidade
                $('.increment-btn').on('click', function () {
                    const targetId = $(this).data('target');
                    const $input = $('#' + targetId);
                    const currentVal = parseInt($input.val());
                    const maxVal = parseInt($input.attr('max'));
                    if (currentVal < maxVal) {
                        $input.val(currentVal + 1);
                    }
                });

                // Decrementar quantidade
                $('.decrement-btn').on('click', function () {
                    const targetId = $(this).data('target');
                    const $input = $('#' + targetId);
                    const currentVal = parseInt($input.val());
                    if (currentVal > parseInt($input.attr('min'))) {
                        $input.val(currentVal - 1);
                    }
                });
            });


            document.getElementById('cardNumber').addEventListener('input', function (e) {
                let value = this.value.replace(/\D/g, '').substring(0, 16);

                // Adiciona espaços a cada 4 dígitos
                value = value.replace(/(\d{4})(?=\d)/g, '$1 ');

                // Atualiza o valor do campo
                this.value = value;
            });

            document.getElementById('zip').addEventListener('input', function (e) {
                // Remove tudo que não é dígito
                let value = this.value.replace(/\D/g, '');

                // Limita a 7 caracteres (4 antes do traço e 3 depois)
                if (value.length > 7) {
                    value = value.substring(0, 7);
                }

                // Adiciona o traço após os primeiros 4 dígitos
                if (value.length > 4) {
                    value = value.substring(0, 4) + '-' + value.substring(4);
                }

                // Atualiza o valor do campo
                this.value = value;
            });

            document.getElementById('paymentForm').addEventListener('submit', function (e) {
                const zipInput = document.getElementById('zip');
                const zipValue = zipInput.value;
                const cardInput = document.getElementById('cardNumber');
                const cardValue = cardInput.value.replace(/\D/g, '');

                // Verifica se o formato está correto (0000-000)
                if (!/^\d{4}-\d{3}$/.test(zipValue)) {
                    zipInput.classList.add('is-invalid');
                    e.preventDefault();
                    return false;
                }

                if (cardValue.length !== 16) {
                    cardInput.classList.add('is-invalid');
                    e.preventDefault();
                    return false;
                }

                return true;
            });

            // Validação do formulário de pagamento
            document.getElementById('confirmPayment').addEventListener('click', function () {
                const form = document.getElementById('paymentForm');
                const inputs = form.querySelectorAll('input, select');
                let isValid = true;

                // Validação básica
                inputs.forEach(input => {
                    if (!input.checkValidity()) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });

                // Validação adicional
                if (isValid) {
                    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
                    const statusLoading = document.getElementById('statusLoading');
                    const statusSuccess = document.getElementById('statusSuccess');
                    const statusError = document.getElementById('statusError');

                    // Mostrar modal de loading
                    statusLoading.classList.remove('d-none');
                    statusSuccess.classList.add('d-none');
                    statusError.classList.add('d-none');
                    statusModal.show();

                    const formData = new FormData();
                    formData.append('action', 'finalize_purchase');
                    formData.append('cardNumber', document.getElementById('cardNumber').value.replace(/\s/g, ''));
                    formData.append('cardName', document.getElementById('cardName').value);
                    formData.append('expMonth', document.getElementById('expMonth').value);
                    formData.append('expYear', document.getElementById('expYear').value);
                    formData.append('cvv', document.getElementById('cvv').value);
                    formData.append('address', document.getElementById('address').value);
                    formData.append('city', document.getElementById('city').value);
                    formData.append('state', document.getElementById('state').value);
                    formData.append('zip', document.getElementById('zip').value);
                    // Simular processamento
                    $('#paymentModal').modal('hide');

                    const rawCardNumber = document.getElementById('cardNumber').value.replace(/\D/g, '');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: new URLSearchParams(formData),
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                    })
                        .then(response => response.json())
                        .then(data => {
                            // Esconder loading
                            statusLoading.classList.add('d-none');

                            if (data.success) {
                                // Mostrar sucesso
                                statusSuccess.classList.remove('d-none');
                                document.getElementById('idcompra').textContent = 'ID da Compra: ' + data.idcompra;

                                document.getElementById('totalCompraBottom').textContent = data.total + '€';

                                const purchasedProductsContainer = document.getElementById('purchasedProducts');
                                purchasedProductsContainer.innerHTML = ''; // Limpar conteúdo anterior

                                data.produtos.forEach(product => {
                                    const col = document.createElement('div');
                                    col.className = 'col-6 col-sm-4 col-md-3 col-product text-center';

                                    const img = document.createElement('img');
                                    img.src = product.imagem;
                                    img.alt = product.nome;
                                    img.className = 'purchased-product-img';
                                    img.title = product.nome;

                                    col.appendChild(img);
                                    purchasedProductsContainer.appendChild(col);
                                });

                            } else {
                                // Mostrar erro
                                statusError.classList.remove('d-none');
                                document.getElementById('errorMessage').textContent = data.message;

                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            statusLoading.classList.add('d-none');
                            statusError.classList.remove('d-none');
                            document.getElementById('errorMessage').textContent = 'Erro na comunicação com o servidor';
                        });
                }
            });
            // Resetar validação ao fechar modal
            $('#paymentModal').on('hidden.bs.modal', function () {
                $(this).find('.is-invalid').removeClass('is-invalid');
            });
            $('#statusModal').on('hidden.bs.modal', function () {
                window.location.reload();
            });

        </script>
</body>

</html>