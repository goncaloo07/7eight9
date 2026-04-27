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
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compras</title>
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
            background: rgb(230, 230, 230);
            font-family: 'Open Sans', sans-serif;
        }

        .compras-container {
            margin: 0 auto 30px auto;
            padding: 30px;
            width: 90%;
        }

        .compra-card {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .compra-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .compra-header {
            background: #343a40;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .compra-body {
            padding: 30px;
        }

        .produtos-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            height: 100%;
            justify-content: flex-end;
        }

        .produto-img-container {
            flex: 0 0 calc(25% - 15px);
        }

        .produto-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 5px;
            border: 1px solid #eee;
        }

        .compra-info {
            margin-bottom: 5px;
        }

        .compra-info p {
            margin-bottom: 5px;
        }

        .badge-compra {
            font-size: 1rem;
            background: #17a2b8;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .valor-total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }

        .page-title {
            color: #343a40;
            margin-bottom: 30px;
            font-weight: 700;
            text-align: center;
            position: relative;
            padding-bottom: 20px;
        }

        .page-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 3px;
            background: rgb(161, 161, 161);
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }

        .numero-compra {
            font-weight: bold;
            color: #343a40;
            font-size: 1.2rem;
        }

        .compra-row {
            display: flex;
            align-items: center;
        }

        .btn-close {
            padding: 1rem !important;
        }

        .modal-content {
            background-color: #f8f9fa;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem 1.5rem 0.5rem 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #212529;
            width: 100%;
            text-align: center;
        }

        .modal-body {
            padding: 0 1.5rem 1.5rem 1.5rem;
        }

        .compra-info-section,
        .pagamento-section {
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .compra-info-section h6 {
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 500;
            color: #495057;
        }

        .info-value {
            font-weight: 500;
            color: #212529;
        }

        .produto-card {
            transition: all 0.2s ease;
            background: #ffffff;
            border: 1px solid #e9ecef;
        }

        .produto-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-color: #dee2e6;
        }

        .produto-img-modal {
            height: 120px;
            object-fit: contain;
            padding: 0.5rem;
        }

        .produto-details {
            padding: 0.75rem;
            text-align: center;
        }

        .produto-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
            color: #212529;
        }

        .produto-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .pagamento-section h6 {
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        @media (max-width: 767.98px) {
            .produto-img-container {
                flex: 0 0 calc(50% - 15px) !important;
                max-width: calc(50% - 15px) !important;
            }

            .produto-img {
                height: auto;
            }

            .produtos-container {
                justify-content: flex-start;
                margin-top: 15px;
            }
        }

        @media (max-width: 900px) {
            .produto-img-container {
                flex: 0 0 calc(33.33% - 15px);
                max-width: calc(33.33% - 15px);
            }
        }
    </style>
</head>

<body>
    <?php include "header.php"; ?>

    <div class="compras-container">
        <?php
        $sql = "SELECT * FROM PAP_COMPRAS PC WHERE PC.CLIENTE_ID = ? ORDER BY PC.DATA_COMPRA DESC";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $compras = $result->fetch_all(MYSQLI_ASSOC);
            $totalCompras = count($compras);
        } else {
            $compras = [];
        }
        $stmt->close();
        if (empty($compras)): ?>
            <div class="empty-state">
                <i class="bi bi-cart-x"></i>
                <h3 class="text-secondary">Nenhuma compra realizada ainda</h3>
                <p class="text-muted">Quando você fizer uma compra, ela aparecerá aqui.</p>
                <a href="produtos.php" class="btn btn-primary mt-3">Ver produtos</a>
            </div>
        <?php else: ?>
            <h1 class="page-title">As suas Compras</h1>
            <?php foreach ($compras as $compra):
                $dataCompra = date('d/m/Y', strtotime($compra['DATA_COMPRA']));
                $totalCompra = number_format($compra['TOTAL'], 2, ',', '.');
                $numeroCompra = $totalCompras - array_search($compra, $compras);
                ?>
                <div class="compra-card" data-bs-toggle="modal" data-bs-target="#modal-<?php echo $compra['ID']; ?>">
                    <div class="compra-body">
                        <div class="row">
                            <div class="col-md-3 compra-row">
                                <div class="compra-info">
                                    <p class="numero-compra">A sua <?php echo $numeroCompra; ?>ª Compra</p>
                                    <p><strong>Data:</strong> <?php echo $dataCompra; ?></p>
                                    <p class="valor-total">Total: <?php echo $totalCompra; ?>€</p>
                                </div>
                            </div>

                            <div class="col-md-9">
                                <div class="produtos-container">
                                    <?php
                                    $roupacompra = "SELECT *, CASE WHEN FT_1 IS NOT NULL THEN CONCAT('data:image/jpeg;base64,', TO_BASE64(FT_1)) ELSE NULL END as FT_1 
                                    FROM PAP_ROUPA PR, PAP_COMPRAS_ROUPA PCR WHERE PR.ID = PCR.ROUPA_ID AND PCR.COMPRA_ID = ?";
                                    $stmt = $connection->prepare($roupacompra);
                                    $stmt->bind_param("i", $compra['ID']);
                                    $stmt->execute();
                                    $roupas = $stmt->get_result();

                                    if ($roupas->num_rows > 0):
                                        while ($roupa1 = $roupas->fetch_assoc()): ?>
                                            <div class="produto-img-container">
                                                <img src="<?php echo $roupa1['FT_1']; ?>" alt="<?php echo $roupa1['NOME']; ?>"
                                                    class="produto-img">
                                            </div>
                                        <?php endwhile;
                                    endif;
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                $roupacompra = "SELECT PR.*, PCR.*, TR.TAMANHO, CASE WHEN FT_1 IS NOT NULL THEN CONCAT('data:image/jpeg;base64,', TO_BASE64(FT_1)) ELSE NULL END as FT_1 
                FROM PAP_ROUPA PR, PAP_COMPRAS_ROUPA PCR, PAP_TAMANHO_ROUPA TR WHERE PR.ID = PCR.ROUPA_ID AND PCR.COMPRA_ID = ? AND TR.ID= PCR.TAMANHO_ID";
                $stmt = $connection->prepare($roupacompra);
                $stmt->bind_param("i", $compra['ID']);
                $stmt->execute();
                $roupas = $stmt->get_result();
                ?>
                <div class="modal fade" id="modal-<?php echo $compra['ID']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Detalhes da <?php echo $numeroCompra; ?>ª Compra</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="compra-info-section">
                                    <h6>Informações da Compra</h6>
                                    <div class="info-item">
                                        <span class="info-label">ID da compra:</span>
                                        <span class="info-value"><?php echo $compra['ID']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Data:</span>
                                        <span class="info-value"><?php echo $dataCompra; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Total:</span>
                                        <span class="info-value text-success fw-bold"><?php echo $totalCompra; ?>€</span>
                                    </div>
                                </div>

                                <div class="compra-info-section">
                                    <h6>Produtos Comprados</h6>
                                    <div class="row g-3" style="display: ruby;">
                                        <?php while ($roupa2 = $roupas->fetch_assoc()): ?>
                                            <a
                                                href="https://alpha.soaresbasto.pt/~a25385/PAP/roupa2.php?tipo=<?php echo $roupa2['ID']; ?>">
                                                <div class="col-6 col-sm-4 col-md-3">
                                                    <div class="produto-card rounded-3 h-100">
                                                        <img src="<?php echo $roupa2['FT_1']; ?>"
                                                            alt="<?php echo $roupa2['NOME']; ?>" class="produto-img-modal w-100">
                                                        <div class="produto-details">
                                                            <div class="produto-name"><?php echo $roupa2['NOME']; ?></div>
                                                            <div class="produto-meta">
                                                                <div>Qtd: <?php echo $roupa2['QNTD']; ?></div>
                                                                <div>Tam: <?php echo $roupa2['TAMANHO']; ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endwhile; ?>
                                    </div>
                                </div>

                                <?php
                                $pagamento = "SELECT * FROM PAP_COMPRAS PC, PAP_INFO_PAGAMENTO PIP WHERE PC.ID = ? AND PC.ID_PAGAMENTO = PIP.ID";
                                $stmt = $connection->prepare($pagamento);
                                $stmt->bind_param("i", $compra['ID']);
                                $stmt->execute();
                                $pagamentoInfo = $stmt->get_result()->fetch_assoc();
                                $u4cartao = $pagamentoInfo['CARTAO_U4'];
                                $nomeTitular = $pagamentoInfo['NOME_TITULAR'];
                                $endereco = $pagamentoInfo['RUA_ENDERECO'] . ', ' . $pagamentoInfo['CIDADE_ENDERECO'] . ', ' . $pagamentoInfo['CODIGO_POSTAL'];
                                ?>

                                <div class="pagamento-section mt-3">
                                    <h6>Informações de Pagamento</h6>
                                    <div class="info-item">
                                        <span class="info-label">Titular do cartão:</span>
                                        <span class="info-value"><?php echo $nomeTitular ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Número do cartão:</span>
                                        <span class="info-value">**** **** **** <?php echo $u4cartao ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Endereço de entrega:</span>
                                        <span class="info-value"><?php echo $endereco ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php $stmt->close(); ?>
            <?php endforeach; ?>
        <?php endif; ?>
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
</body>

</html>