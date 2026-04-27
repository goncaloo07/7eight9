<?php
session_start();
require "connection.php";

if (!isset($_SESSION['compra_id'])) {
    header('Location: index.php');
    exit();
}

$compra_id = $_SESSION['compra_id'];
unset($_SESSION['compra_id']); // Remove o ID da sessão após usar

// Buscar detalhes da compra no banco de dados
$connection = db_connect();
$sql = "SELECT c.*, p.NUMERO_CARTAO, p.NOME_TITULAR 
        FROM PAP_COMPRAS c
        JOIN PAP_INFO_PAGAMENTO p ON c.ID_PAGAMENTO = p.ID
        WHERE c.ID = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $compra_id);
$stmt->execute();
$result = $stmt->get_result();
$compra = $result->fetch_assoc();

// Buscar itens da compra
$sql_itens = "SELECT cr.*, r.NOME, r.FT_1, tr.TAMANHO 
              FROM PAP_COMPRAS_ROUPA cr
              JOIN PAP_ROUPA r ON cr.ROUPA_ID = r.ID
              JOIN PAP_TAMANHO_ROUPA tr ON cr.TAMANHO_ID = tr.ID
              WHERE cr.COMPRA_ID = ?";
$stmt_itens = $connection->prepare($sql_itens);
$stmt_itens->bind_param("i", $compra_id);
$stmt_itens->execute();
$itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sucesso na Compra</title>
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
        body {
            background-color: #f8f9fa;
        }
        .success-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .success-icon {
            font-size: 5rem;
            color: #28a745;
        }
        .order-summary {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        .item-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    
    <div class="container success-container">
        <div class="text-center mb-4">
            <i class="bi bi-check-circle-fill success-icon"></i>
            <h1 class="mt-3">Compra Concluída com Sucesso!</h1>
            <p class="lead">Obrigado por sua compra. Aqui estão os detalhes do seu pedido.</p>
        </div>
        
        <div class="order-summary">
            <h4 class="mb-4">Resumo do Pedido #<?= $compra_id ?></h4>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5>Informações da Compra</h5>
                    <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($compra['DATA_COMPRA'])) ?></p>
                    <p><strong>Total:</strong> €<?= number_format($compra['TOTAL'], 2, ',', '.') ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Pagamento</h5>
                    <p><strong>Cartão:</strong> **** **** **** <?= substr($compra['NUMERO_CARTAO'], -4) ?></p>
                    <p><strong>Titular:</strong> <?= $compra['NOME_TITULAR'] ?></p>
                </div>
            </div>
            
            <h5>Itens Comprados</h5>
            <div class="list-group">
                <?php foreach ($itens as $item): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <img src="<?= $item['FT_1'] ?>" alt="<?= $item['NOME'] ?>" class="item-img me-3">
                            <div>
                                <h6 class="mb-1"><?= $item['NOME'] ?></h6>
                                <small class="text-muted">Tamanho: <?= $item['TAMANHO'] ?></small>
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="mb-1">€<?= number_format($item['PRECO_UNITARIO'], 2, ',', '.') ?></p>
                            <small class="text-muted">Qtd: <?= $item['QNTD'] ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary">Voltar à Loja</a>
            <a href="minhascompras.php" class="btn btn-outline-secondary ms-2">Minhas Compras</a>
        </div>
    </div>
    
    <?php include "footer.php"; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>