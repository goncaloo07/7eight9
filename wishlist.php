<?php
require "connection.php";
require_once './core.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}
if (!isset($_SESSION['user']) || !isset($_SESSION['id'])) {
   header('Location: login.php');
   exit();
}

$id = $_SESSION['id'];

$connection = db_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'removewish') {
   $roupa_id = filter_input(INPUT_POST, 'roupa_id', FILTER_SANITIZE_NUMBER_INT);
   if (!empty($roupa_id)) {
      $sqlWish = "DELETE FROM PAP_WISHLIST WHERE CLIENTE_ID=? AND ROUPA_ID=?";
      $stmtWish = $connection->prepare($sqlWish);
      if ($stmtWish) {
         $stmtWish->bind_param("ii", $id, $roupa_id);
         $stmtWish->execute();
         $stmtWish->close();
      }
   }
   header('Content-Type: application/json');
   echo json_encode(['success' => true]);
   exit;
}

function truncateText($text, $maxLength = 40)
{
   if (strlen($text) > $maxLength) {
      return substr($text, 0, $maxLength) . '...';
   }
   return $text;
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>Minha Lista de Desejos</title>
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
      html,
      body {
         overflow-x: hidden;
      }

      body {
         background: rgb(230, 230, 230);
         font-family: 'Open Sans', sans-serif;
      }

      .product_section {
         padding: 20px 10px;
      }

      .product_taital {
         font-weight: 600;
         color: #333;
         margin-bottom: 40px;
         text-align: center;
         text-transform: capitalize;
         padding-bottom: 20px;
      }

      .product_taital::after {
         content: '';
         position: absolute;
         bottom: 0;
         left: 50%;
         transform: translateX(-50%);
         width: 150px;
         height: 3px;
         background: rgb(161, 161, 161);
      }

      .product_box {
         background-color: #fff;
         border: none;
         border-radius: 10px;
         overflow: hidden;
         box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
         transition: transform 0.3s ease, box-shadow 0.3s ease;
         position: relative;
         margin-bottom: 30px;
         display: flex;
         flex-direction: column;
         height: 350px;
      }

      .product_box:hover {
         box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
         transform: translateY(-5px);
      }

      .product_box img {
         width: 100%;
         height: 200px;
         object-fit: contain;
         display: block;
         transition: transform 0.4s ease-in-out;
      }

      .product_box img:hover {
         transform: scale(1.05);
      }

      .bursh_text {
         color: #fff;
         font-size: 1rem;
         font-family: 'Poppins', sans-serif;
         text-align: left;
         display: -webkit-box;
         -webkit-box-orient: vertical;
         overflow: hidden;
         text-overflow: ellipsis;
         min-height: 60px;
         line-height: 1.2;
         flex: 1;
      }

      .price_text {
         color: #fff;
         font-size: 1rem;
         border-left: 1px solid rgb(69, 69, 69);
         padding-left: 0.4rem;
         background: none;
      }

      .btn_main {
         flex-direction: initial !important;
         display: flex;
         justify-content: space-between;
         align-items: center;
         background-color: #000;
         padding: 10px;
         height: 150px;
         flex-grow: 1;
      }

      .ribbon {
         width: 150px;
         height: 150px;
         overflow: hidden;
         position: absolute;
         top: 0;
         right: 0;
      }

      .ribbon span {
         position: absolute;
         display: block;
         width: 225px;
         padding: 15px 0;
         background-color: red;
         color: white;
         text-align: center;
         font-weight: bold;
         transform: rotate(45deg);
         top: 30px;
         right: -55px;
      }

      /* Botão de remoção da wishlist - ícone de coração */
      .wishlist-btn {
         position: absolute;
         top: 10px;
         left: 10px;
         background-color: #c3c3c3;
         width: 40px;
         height: 40px;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         z-index: 2;
      }

      .wishlist-btn button {
         background: transparent;
         border: none;
         outline: none;
         cursor: pointer;
      }

      .wishlist-btn i {
         font-size: 20px;
         color: red;
      }

      .navbar {
         padding: 0.5rem 0.1rem;
      }

      .empty-wishlist {
         background: #fff;
         border-radius: 12px;
         padding: 20px;
         box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
      }

      .empty-wishlist i {
         color: #a1a1a1;
      }
   </style>
</head>

<body>
   <?php include "header.php"; ?>

   <div class="container product_section">
      <div class="row">
         <div class="col-sm-12">
            <h1 class="product_taital">Lista de Desejos</h1>
         </div>
      </div>
      <div class="row" id="wishlist-products">
         <?php
         $sql = "SELECT R.ID, R.NOME, R.PRECO, CASE WHEN FT_1 IS NOT NULL THEN CONCAT('data:image/jpeg;base64,', TO_BASE64(FT_1)) ELSE NULL END as FT_1
        FROM PAP_WISHLIST W 
        JOIN PAP_ROUPA R ON W.ROUPA_ID = R.ID 
        WHERE W.CLIENTE_ID = ?";
         $stmt = $connection->prepare($sql);
         if (!$stmt) {
            die("Erro na preparação da consulta: " . $connection->error);
         }
         $stmt->bind_param("i", $id);
         $stmt->execute();
         $result = $stmt->get_result();
         if ($result->num_rows > 0) {
            $cont = 0;
            while ($row = $result->fetch_assoc()) {
               $r_id = $row['ID'];
               $r_nome = $row['NOME'];
               $r_preco = $row['PRECO'];
               $r_ft1 = $row['FT_1'];

               // Consulta para verificar o estoque do produto
               $sqlStock = "SELECT SUM(QNT) AS totalStock FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = ?";
               if ($stmtStock = $connection->prepare($sqlStock)) {
                  $stmtStock->bind_param("i", $r_id);
                  $stmtStock->execute();
                  $resultStock = $stmtStock->get_result();
                  $rowStock = $resultStock->fetch_assoc();
                  $totalStock = $rowStock['totalStock'];
                  $stmtStock->close();
               } else {
                  die("Erro na preparação da consulta de estoque: " . $connection->error);
               }
               $cont++;
               ?>
               <div class="col-6 col-md-6 col-lg-3 product-item" data-product-id="<?php echo $r_id; ?>">
                  <div class="product_box">
                     <div class="wishlist-btn">
                        <form method="POST" class="form-remove-wish">
                           <input type="hidden" name="action" value="removewish">
                           <input type="hidden" name="roupa_id" value="<?php echo $r_id; ?>">
                           <button type="submit"><i class="bi bi-heart-fill"></i></button>
                        </form>
                     </div>
                     <?php if (!$totalStock || $totalStock <= 0): ?>
                        <div class="ribbon">
                           <span>ESGOTADO</span>
                        </div>
                     <?php endif; ?>
                     <a href="roupa2.php?tipo=<?php echo $r_id ?>" class="text-decoration-none">
                        <img src="<?php echo $r_ft1 ?>" alt="<?php echo $r_nome ?>">
                        <div class="btn_main">
                           <h4 class="bursh_text"><?php echo truncateText($r_nome) ?></h4>
                           <h3 class="price_text"><?php echo $r_preco ?>€</h3>
                        </div>
                     </a>
                  </div>
               </div>
               <?php
               if ($cont % 4 == 0)
                  echo "</div><div class='row' id='wishlist-products'>";
            }
         } else {
            echo '
<div class="col-12">
   <div class="empty-wishlist text-center pb-4 pt-3">
      <i class="bi bi-heartbreak-fill text-secondary" style="font-size: 5rem;"></i>
      <h3 class="mt-2">A sua lista de desejos está vazia.</h3>
      <p class="text-muted">Adicione produtos à sua lista para vê-los aqui mais tarde.</p>
      <a href="roupa.php?tipo=0" class="btn btn-dark mt-3 px-4 py-2">
         <i class="bi bi-shop"></i> Explorar Produtos
      </a>
   </div>
</div>';
         }
         ?>
      </div>
   </div>

   <?php include "footer.php"; ?>

   <!-- Scripts -->
   <script src="js/jquery.min.js"></script>
   <script src="js/popper.min.js"></script>
   <script src="js/bootstrap.bundle.min.js"></script>
   <script src="js/plugin.js"></script>
   <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
   <script src="js/custom.js"></script>
   <script src="js/owl.carousel.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
   <script src="https://unpkg.com/gijgo@1.9.13/js/gijgo.min.js" type="text/javascript"></script>
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
   <script>
      // Intercepta o envio do formulário para remoção via AJAX
      $(document).ready(function () {
         $('.form-remove-wish').on('submit', function (e) {
            e.preventDefault();
            var form = $(this);
            var productItem = form.closest('.product-item');
            $.ajax({
               url: window.location.href,
               type: 'POST',
               data: form.serialize(),
               dataType: 'json',
               success: function (response) {
                  if (response.success) {
                     productItem.fadeOut(0, function () {
                        $(this).remove();
                        // Se não houver mais itens, exibe a mensagem
                        if ($('#wishlist-products').find('.product-item').length === 0) {
                           $('#wishlist-products').html("<div class='col-12'><h3 class='text-center text-danger'>Ainda não tem produtos na sua lista de desejos.</h3></div>");
                        }
                     });
                  } else {
                     alert('Erro ao remover o produto.');
                  }
               },
               error: function () {
                  alert('Erro na requisição.');
               }
            });
         });
      });
   </script>
   <?php
   $stmt->close();
   $connection->close();
   ?>
</body>

</html>