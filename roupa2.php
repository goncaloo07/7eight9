<?php
require "connection.php";
require_once './core.php';

if (session_status() === PHP_SESSION_NONE) {
   session_destroy();
   session_start();
}

$connection = db_connect();
$connection2 = db_connect();
$connection3 = db_connect();

/* Processamento de formulários */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
   $user_id = isset($_SESSION['id']) ? $_SESSION['id'] : 0;

   // Verificação de login para todas ações
   if ($user_id == 0) {
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         echo json_encode(['redirect' => 'login.php']);
         exit;
      } else {
         header("Location: login.php");
         exit();
      }
   }

   if ($_POST['action'] == 'addcart') {
      $roupa_id = filter_input(INPUT_POST, 'roupa_id', FILTER_SANITIZE_NUMBER_INT);
      $tamanho = filter_input(INPUT_POST, 'tamanho', FILTER_SANITIZE_NUMBER_INT);

      if (!empty($roupa_id) && !empty($tamanho)) {
         try {
            // Verificar estoque primeiro
            $stockSql = "SELECT QNT FROM PAP_ROUPA_HAS_TAMANHO 
                        WHERE ROUPA_ID = ? AND TAMANHO_ID = ?";
            $stockStmt = $connection->prepare($stockSql);
            $stockStmt->bind_param("ii", $roupa_id, $tamanho);
            $stockStmt->execute();
            $stockResult = $stockStmt->get_result();

            if ($stockResult->num_rows == 0) {
               throw new Exception("Tamanho selecionado não disponível");
            }

            $stockRow = $stockResult->fetch_assoc();
            $availableStock = $stockRow['QNT'];

            // Verificar se já está no carrinho
            $checkSql = "SELECT QNT FROM PAP_CARRINHO 
                        WHERE CLIENTE_ID = ? AND ROUPA_ID = ? AND TMNH = ?";
            $checkStmt = $connection->prepare($checkSql);
            $checkStmt->bind_param("iii", $user_id, $roupa_id, $tamanho);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
               // Item existe - verificar estoque
               $currentQnt = $checkResult->fetch_assoc()['QNT'];
               if (($currentQnt + 1) > $availableStock) {
                  throw new Exception("Quantidade indisponível em estoque");
               }

               // Atualizar quantidade
               $updateSql = "UPDATE PAP_CARRINHO SET QNT = QNT + 1 
                             WHERE CLIENTE_ID = ? AND ROUPA_ID = ? AND TMNH = ?";
               $updateStmt = $connection->prepare($updateSql);
               $updateStmt->bind_param("iii", $user_id, $roupa_id, $tamanho);

               if (!$updateStmt->execute()) {
                  throw new Exception("Erro ao atualizar quantidade no carrinho");
               }

               $message = "Quantidade atualizada no carrinho";
            } else {
               // Novo item - verificar estoque
               if (1 > $availableStock) {
                  throw new Exception("Quantidade indisponível em estoque");
               }

               // Inserir novo item
               $insertSql = "INSERT INTO PAP_CARRINHO (CLIENTE_ID, ROUPA_ID, TMNH, QNT) 
                             VALUES (?, ?, ?, 1)";
               $insertStmt = $connection->prepare($insertSql);
               $insertStmt->bind_param("iii", $user_id, $roupa_id, $tamanho);

               if (!$insertStmt->execute()) {
                  if ($connection->errno == 1062) { // Código de erro para entrada duplicada
                     throw new Exception("Item já existe no carrinho");
                  } else {
                     throw new Exception("Erro ao adicionar ao carrinho");
                  }
               }

               $message = "Produto adicionado ao carrinho";
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit;

         } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
               'success' => false,
               'message' => $e->getMessage()
            ]);
            exit;
         }
      } else {
         header('Content-Type: application/json');
         echo json_encode(['success' => false, 'message' => "Dados incompletos"]);
         exit;
      }
   }


   if ($_POST['action'] == 'addwish') {
      $roupa_id = filter_input(INPUT_POST, 'roupa_id', FILTER_SANITIZE_NUMBER_INT);
      if (!empty($roupa_id)) {
         $sqlWish = "INSERT INTO PAP_WISHLIST (CLIENTE_ID, ROUPA_ID) VALUES (?, ?)";
         $stmtWish = $connection->prepare($sqlWish);
         if (!$stmtWish) {
            $wish_msg = "Erro ao adicionar produto à lista de desejos: " . $connection->error;
         } else {
            $stmtWish->bind_param("ii", $user_id, $roupa_id);
            $stmtWish->execute();
            $stmtWish->close();
         }
      }
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         echo json_encode(['success' => true, 'action' => 'removewish']);
         exit;
      }
   }

   if ($_POST["action"] == "removewish") {
      $roupa_id = filter_input(INPUT_POST, 'roupa_id', FILTER_SANITIZE_NUMBER_INT);
      if (!empty($roupa_id)) {
         $sqlWish = "DELETE FROM PAP_WISHLIST WHERE CLIENTE_ID=? AND ROUPA_ID=?";
         $stmtWish = $connection->prepare($sqlWish);
         if (!$stmtWish) {
            $wish_msg = "Erro ao remover produto da lista de desejos: " . $connection->error;
         } else {
            $stmtWish->bind_param("ii", $user_id, $roupa_id);
            $stmtWish->execute();
            $stmtWish->close();
         }
      }
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         echo json_encode(['success' => true, 'action' => 'addwish']);
         exit;
      }
   }
}

$tipo = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_NUMBER_INT);
if (!is_numeric($tipo)) {
   $ligacao = connectDB($db);
   $r = "SELECT * FROM PAP_ROUPA R, PAP_TAMANHO_ROUPA TR, PAP_ROUPA_HAS_TAMANHO RT WHERE R.ID=1";
   $t = "SELECT * FROM PAP_TAMANHO_ROUPA TR, PAP_ROUPA_HAS_TAMANHO RT 
          WHERE RT.ROUPA_ID=1 AND RT.TAMANHO_ID=TR.ID AND RT.QNT>0 ORDER BY TR.ID ASC";
   $d = "SELECT M.MARCA, MR.NOME AS MATERIAL, C.COR 
          FROM PAP_ROUPA R, PAP_MARCA M, PAP_MATERIAIS_ROUPA MR, PAP_CORES C 
          WHERE R.MARCA=M.ID AND R.MATERIAIS=MR.ID AND R.COR=C.ID AND R.ID=1";
   if (isset($_SESSION["id"])) {
      $ld = "SELECT * FROM PAP_WISHLIST WHERE CLIENTE_ID=" . $_SESSION['id'] . " AND ROUPA_ID=1";
   } else {
      $ld = "SELECT * FROM PAP_WISHLIST WHERE CLIENTE_ID=0 AND ROUPA_ID=1";
   }
} else {
   $ligacao = connectDB($db);
   $r = "SELECT * FROM PAP_ROUPA WHERE ID=" . $_GET['tipo'];
   $t = "SELECT * FROM PAP_TAMANHO_ROUPA TR, PAP_ROUPA_HAS_TAMANHO RT 
          WHERE RT.ROUPA_ID=" . $_GET['tipo'] . " AND RT.TAMANHO_ID=TR.ID AND RT.QNT>0 ORDER BY TR.ID ASC";
   $d = "SELECT M.MARCA, MR.NOME AS MATERIAL, C.COR 
          FROM PAP_ROUPA R, PAP_MARCA M, PAP_MATERIAIS_ROUPA MR, PAP_CORES C 
          WHERE R.MARCA=M.ID AND R.MATERIAIS=MR.ID AND R.COR=C.ID AND R.ID=" . $_GET['tipo'];
   if (isset($_SESSION["id"])) {
      $ld = "SELECT * FROM PAP_WISHLIST WHERE CLIENTE_ID=" . $_SESSION['id'] . " AND ROUPA_ID=" . $_GET['tipo'];
   } else {
      $ld = "SELECT * FROM PAP_WISHLIST WHERE CLIENTE_ID=0 AND ROUPA_ID=" . $_GET['tipo'];
   }
}
$roupa1 = $connection->query($r);
$roupa = queryDB($ligacao, $r);
$tmnh = $connection2->query($t);
$wish = $connection3->query($ld);
$desc = queryDB($ligacao, $d);

function blobToBase64($blob)
{
   if ($blob !== null) {
      return 'data:image/jpeg;base64,' . base64_encode($blob);
   }
   return null;
}

// Converter imagens BLOB para base64
if (isset($roupa[0]['FT_1'])) {
   $roupa[0]['FT_1'] = blobToBase64($roupa[0]['FT_1']);
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
   <!-- Meta tags -->
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <title><?php echo $roupa[0]['NOME']; ?></title>
   <meta name="keywords" content="">
   <meta name="description" content="">
   <meta name="author" content="">
   <!-- Bootstrap e outras bibliotecas -->
   <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
   <link rel="stylesheet" type="text/css" href="css/style.css">
   <link rel="stylesheet" href="css/responsive.css">
   <link rel="icon" href="https://alpha.soaresbasto.pt/~a25385/PAP/img/logo.png" type="image/gif" />
   <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
   <link href="https://fonts.googleapis.com/css?family=Great+Vibes|Open+Sans:400,700&display=swap&subset=latin-ext"
      rel="stylesheet">
   <link rel="stylesheet" href="css/owl.carousel.min.css">
   <link rel="stylesheet" href="css/owl.theme.default.min.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.css"
      media="screen">
   <link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css" />
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <!-- Estilos personalizados -->
   <style>
      body {
         background: rgb(230, 230, 230);
         min-height: 100vh;
         font-family: 'Open Sans', sans-serif;
      }

      .main-container {
         padding: 20px;
      }

      .product-image {
         height: auto;
         object-fit: scale-down;
         border-radius: 15px;
         transition: all 0.3s ease;
      }

      .product-image:hover {
         transform: scale(1.05);
      }

      .product-info {
         padding: 20px;
         background: #f8f9fa;
         border-radius: 15px;
         height: 100%;
      }

      .product-title {
         font-size: 1.8rem;
         font-weight: 700;
         margin-bottom: 1rem;
      }

      .product-price {
         font-size: 2rem;
         color: #00b27d;
         margin-bottom: 1rem;
      }

      .size-buttons {
         gap: 10px;
         margin-bottom: 2rem;
      }

      .size-btn {
         min-width: 80px;
         transition: all 0.2s ease;
      }

      .size-btn.active {
         background: #6c757d !important;
         color: white !important;
      }

      .action-buttons {
         gap: 15px;
         margin-bottom: 2rem;
      }

      .wishlist-btn {
         width: 20%;
      }

      .wishlist-btn button {
         width: 100%;
         padding: 12px;
         background: #f8f9fa;
      }

      .cart-btn {
         width: 80%;
      }

      .cart-btn button {
         width: 100%;
         padding: 12px;
         background: #00b27d;
         color: white;
         border: none;
         transition: all 0.3s ease;
      }

      .cart-btn button:hover {
         background: #008f65;
      }

      .product-description {
         font-size: 1.1rem;
         margin-bottom: 0.5rem;
         opacity: 0;
         max-height: 0;
         overflow: hidden;
         transition: all 0.8s ease-in-out;
      }

      .product-description.show {
         opacity: 1;
         max-height: 1000px;
         overflow: visible;
      }

      .bi-heart-wish {
         font-size: 2rem !important;
         transition: color 0.3s ease, transform 0.3s ease;
      }

      .pulse {
         animation: pulse-animation 0.3s ease-in-out;
      }

      .btn-cart-disabled {
         background-color: #cccccc !important;
         color: #6c757d !important;
         cursor: not-allowed;
         border: none;
         height: 100%;
         text-wrap: wrap;
      }

      .btn-cart-enabled {
         background-color: #00b27d !important;
         color: white !important;
         cursor: pointer;
         border: none;
         height: 100%;
         text-wrap: wrap;
      }

      .btn-cart-enabled:hover {
         background-color: #008f65 !important;
      }

      .formwish {
         height: 100%;
      }

      p {
         margin-left: 0;
         margin-right: 0;
      }

      .ver-mais-container {
         text-align: center;
         margin-bottom: 0.5rem;
      }

      /* Estilos do carrossel */
      .carousel-container {
         position: relative;
         width: 100%;
         max-width: 600px;
         margin: 0 auto;
      }

      .carousel {
         display: flex;
         overflow: hidden;
         border-radius: 15px;
         position: relative;
      }

      .carousel-inner {
         display: flex;
         transition: transform 0.5s ease;
      }

      .carousel-item {
         min-width: 100%;
         transition: opacity 0.5s ease;
      }

      .carousel-item img {
         width: 100%;
         height: auto;
         object-fit: contain;
         border-radius: 15px;
      }

      .carousel-control {
         position: absolute;
         top: 50%;
         transform: translateY(-50%);
         background-color: rgba(0, 0, 0, 0.5);
         color: white;
         border: none;
         padding: 10px;
         cursor: pointer;
         z-index: 10;
         border-radius: 50%;
         width: 40px;
         height: 40px;
         display: flex;
         align-items: center;
         justify-content: center;
         transition: all 0.3s ease;
      }

      .carousel-control:hover {
         background-color: rgba(0, 0, 0, 0.8);
      }

      .carousel-control.prev {
         left: 10px;
      }

      .carousel-control.next {
         right: 10px;
      }

      .carousel-indicators {
         position: absolute;
         bottom: 10px;
         left: 0;
         right: 0;
         display: flex;
         justify-content: center;
         gap: 5px;
         z-index: 10;
      }

      .carousel-indicator {
         width: 10px;
         height: 10px;
         border-radius: 50%;
         background-color: rgba(255, 255, 255, 0.5);
         cursor: pointer;
         transition: all 0.3s ease;
      }

      .carousel-indicator.active {
         background-color: white;
         transform: scale(1.2);
      }

      @keyframes pulse-animation {
         0% {
            transform: scale(1);
         }

         50% {
            transform: scale(1.3);
         }

         100% {
            transform: scale(1);
         }
      }

      @media (max-width: 768px) {
         .product-image {
            height: auto;
            margin-bottom: 10px;
         }

         .product-title {
            font-size: 2rem;
         }

         .product-price {
            font-size: 1.75rem;
         }
      }

      @media (max-width: 339px) {
         .bi-heart-wish {
            font-size: 1.5rem !important;
         }
      }

      #cartModal .modal-content {
         border-radius: 15px;
         border: none;
         box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
      }

      #cartModal .modal-header {
         position: absolute;
         top: 0;
         right: 0;
         border: none;
         z-index: 1;
      }

      #cartModal .btn-close {
         font-size: 1.2rem;
         opacity: 0.7;
         transition: opacity 0.2s;
         padding: 0.5rem !important;
      }

      #cartModal .btn-close:hover {
         opacity: 1;
      }

      #cartModal .modal-body {
         padding: 2rem;
         padding-top: 3rem;
         /* Espaço extra para o header */
      }

      #cartModal .bi-cart-check {
         animation: bounce 0.5s;
      }

      .divfoto{
         height: 100%;
         background-color: #f7f7f7;
    border-radius: 50px;
      }

      .divfoto:hover{
         background-color: inherit;
      }

      @keyframes bounce {

         0%,
         100% {
            transform: translateY(0);
         }

         50% {
            transform: translateY(-10px);
         }
      }
   </style>
</head>

<body>
   <?php include "header.php"; ?>
   <div class="main-container">
      <div class="container">
         <div class="row g-4 align-items-start">
            <!-- Coluna da Imagem -->
            <div class="col-12 col-lg-7 d-flex align-items-start divfoto">
               <img src="<?php echo $roupa[0]['FT_1']; ?>" class="product-image mx-auto"
                  alt="<?php echo $roupa[0]['NOME']; ?>">
            </div>
            <!-- Coluna de Informações -->
            <div class="col-12 col-lg-5">
               <div class="product-info">
                  <h1 class="product-title"><?php echo $roupa[0]['NOME']; ?></h1>
                  <div class="product-price"><?php echo $roupa[0]['PRECO']; ?>€</div>
                  <!-- Seleção de Tamanho -->
                  <div class="d-flex flex-wrap size-buttons">
                     <?php foreach ($tmnh as $t): ?>
                        <?php if ($t['QNT'] > 0): ?>
                           <button type="button" class="btn btn-outline-secondary size-btn"
                              data-value="<?php echo $t['ID']; ?>">
                              <?php echo $t['TAMANHO']; ?>
                           </button>
                        <?php endif; ?>
                     <?php endforeach; ?>
                  </div>
                  <!-- Botões de Ação -->
                  <div class="d-flex action-buttons">
                     <!-- Wishlist -->
                     <div class="wishlist-btn">
                        <form method="POST" class="formwish">
                           <input type="hidden" name="action"
                              value="<?php echo $wish->num_rows > 0 ? 'removewish' : 'addwish'; ?>">
                           <input type="hidden" name="roupa_id" value="<?php echo $roupa[0]['ID']; ?>">
                           <button type="submit" class="btn rounded-3" style="padding: 0; height: 100%;">
                              <i
                                 class="bi <?php echo $wish->num_rows > 0 ? 'bi-heart-fill bi-heart-wish text-danger' : 'bi-heart bi-heart-wish text-dark'; ?> fs-3"></i>
                           </button>
                        </form>
                     </div>
                     <!-- Carrinho -->
                     <div class="cart-btn">
                        <form method="POST" style="height: 100%;" id="addToCartForm">
                           <input type="hidden" name="action" value="addcart">
                           <input type="hidden" name="roupa_id" value="<?php echo $roupa[0]['ID']; ?>">
                           <input type="hidden" name="tamanho" id="selectedTamanho">
                           <?php if ($tmnh->num_rows > 0): ?>
                              <button type="submit" class="btn rounded-3 btn-cart-disabled" id="cartButton" disabled>
                                 Adicionar ao Carrinho
                              </button>
                           <?php else: ?>
                              <button type="button" class="btn btn-secondary rounded-3 w-100" disabled>
                                 Esgotado
                              </button>
                           <?php endif; ?>
                        </form>
                     </div>
                  </div>
                  <!-- Botão "Ver Mais" -->
                  <div class="ver-mais-container">
                     <button id="verMaisBtn" class="btn btn-link p-0">
                        Ver Mais <i class="bi bi-arrow-down-circle"></i>
                     </button>
                  </div>
                  <!-- Descrição do produto -->
                  <div id="productDescription" class="product-description">
                     <p><strong>Marca:</strong> <?php echo $desc[0]['MARCA']; ?></p>
                     <p><strong>Material:</strong> <?php echo $desc[0]['MATERIAL']; ?></p>
                     <p><strong>Cor:</strong> <?php echo $desc[0]['COR']; ?></p>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
   <div class="modal fade" id="cartModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered">
         <div class="modal-content">
            <div class="modal-header border-0 pb-0">
               <button type="button" class="btn-close p-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-0 pb-4">
               <div class="mb-3">
                  <i class="bi bi-cart-check text-success" style="font-size: 3rem;"></i>
               </div>
               <h4 class="mb-3">Artigo adicionado ao carrinho!</h4>
            </div>
         </div>
      </div>
   </div>
   <?php include "footer.php"; ?>
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
   <script>
      document.addEventListener("DOMContentLoaded", function () {
         const sizeButtons = document.querySelectorAll('.size-btn');
         const sizeInput = document.getElementById('selectedTamanho');
         const cartButton = document.getElementById('cartButton');
         const verMaisBtn = document.getElementById("verMaisBtn");
         const productDescription = document.getElementById("productDescription");
         const arrowIcon = verMaisBtn.querySelector('i');

         // Botão "Ver Mais": alterna exibição com animação de slide down/up
         verMaisBtn.addEventListener('click', function () {
            if (productDescription.classList.contains('show')) {
               productDescription.classList.remove('show');
               arrowIcon.classList.remove('bi-arrow-up-circle');
               arrowIcon.classList.add('bi-arrow-down-circle');
            } else {
               productDescription.classList.add('show');
               arrowIcon.classList.remove('bi-arrow-down-circle');
               arrowIcon.classList.add('bi-arrow-up-circle');
            }
         });

         if (cartButton && cartButton.disabled) {
            cartButton.classList.add('btn-cart-disabled');
         }

         sizeButtons.forEach(button => {
            button.addEventListener('click', function () {
               sizeButtons.forEach(btn => btn.classList.remove('active'));
               this.classList.add('active');
               sizeInput.value = this.dataset.value;

               // Habilitar o botão de adicionar ao carrinho quando um tamanho for selecionado
               if (cartButton) {
                  cartButton.disabled = false;
                  cartButton.classList.remove('btn-cart-disabled');
                  cartButton.classList.add('btn-cart-enabled');
               }
            });
         });

         // AJAX Wishlist
         document.querySelectorAll('.formwish').forEach(form => {
            form.addEventListener('submit', function (e) {
               e.preventDefault();

               const formData = new FormData(this);
               const parent = this.parentElement;

               const params = new URLSearchParams();
               formData.forEach((value, key) => {
                  params.append(key, value);
               });

               fetch(window.location.href, {
                  method: 'POST',
                  headers: {
                     'Content-Type': 'application/x-www-form-urlencoded',
                     'X-Requested-With': 'XMLHttpRequest'
                  },
                  body: params
               })
                  .then(response => response.json())
                  .then(data => {
                     if (data && data.redirect) {
                        window.location.href = data.redirect;
                     } else if (data) {
                        const icon = parent.querySelector('i');
                        const actionInput = form.querySelector('input[name="action"]');

                        icon.classList.add('pulse');

                        if (data.action === 'removewish') {
                           icon.classList.remove('bi-heart');
                           icon.classList.add('bi-heart-fill', 'text-danger');
                           icon.classList.remove('text-dark');
                           actionInput.value = 'removewish';
                        } else {
                           icon.classList.remove('bi-heart-fill', 'text-danger');
                           icon.classList.add('bi-heart', 'text-dark');
                           actionInput.value = 'addwish';
                        }

                        setTimeout(() => icon.classList.remove('pulse'), 300);
                     }
                  })
                  .catch(error => console.error('Erro na wishlist:', error));
            });
         });
      });

      document.getElementById('addToCartForm').addEventListener('submit', function (e) {
         e.preventDefault();

         // Verificar se um tamanho foi selecionado
         const selectedSize = document.getElementById('selectedTamanho').value;
         if (!selectedSize) {
            alert('Por favor, selecione um tamanho antes de adicionar ao carrinho.');
            return;
         }

         const formData = new FormData(this);
         const cartButton = this.querySelector('button[type="submit"]');

         // Mostrar loading no botão
         const originalText = cartButton.innerHTML;
         cartButton.disabled = true;
         cartButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adicionando...';

         fetch(window.location.href, {
            method: 'POST',
            headers: {
               'Content-Type': 'application/x-www-form-urlencoded',
               'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(formData)
         })
            .then(response => {
               if (!response.ok) {
                  throw new Error('Network response was not ok');
               }
               return response.json();
            })
            .then(data => {
               // Restaurar botão
               cartButton.disabled = false;
               cartButton.innerHTML = originalText;

               if (data.redirect) {
                  window.location.href = data.redirect;
               } else if (data.success) {
                  // Mostrar modal de confirmação
                  const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
                  cartModal.show();

                  // Fechar automaticamente após 5 segundos
                  setTimeout(() => {
                     cartModal.hide();
                  }, 5000);
               } else {
                  alert(data.message || 'Erro ao adicionar ao carrinho');
               }
            })
            .catch(error => {
               console.error('Error:', error);
               cartButton.disabled = false;
               cartButton.innerHTML = originalText;
               alert('Ocorreu um erro ao processar sua solicitação');
            });
      });

      // Funções do carrossel
      let currentSlide = 0;
      const slides = document.querySelectorAll('.carousel-item');
      const indicators = document.querySelectorAll('.carousel-indicator');

      function updateCarousel() {
         const carouselInner = document.getElementById('carouselInner');
         carouselInner.style.transform = `translateX(-${currentSlide * 100}%)`;

         // Atualizar indicadores
         indicators.forEach((indicator, index) => {
            if (index === currentSlide) {
               indicator.classList.add('active');
            } else {
               indicator.classList.remove('active');
            }
         });
      }

      function moveSlide(direction) {
         currentSlide += direction;

         // Verificar limites
         if (currentSlide < 0) {
            currentSlide = slides.length - 1;
         } else if (currentSlide >= slides.length) {
            currentSlide = 0;
         }

         updateCarousel();
      }

      function goToSlide(index) {
         currentSlide = index;
         updateCarousel();
      }

      // Auto-avanço do carrossel (opcional)
      <?php if ($hasAdditionalImages): ?>
         setInterval(() => moveSlide(1), 5000);
      <?php endif; ?>
   </script>
</body>

</html>