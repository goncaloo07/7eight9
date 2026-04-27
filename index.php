<?php
require "connection.php";
if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

$user_id = isset($_SESSION["id"]) ? $_SESSION["id"] : 0;

$connection = db_connect();
$connection3 = db_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
   $action = $_POST['action'];
   $roupa_id = filter_input(INPUT_POST, 'roupa_id', FILTER_SANITIZE_NUMBER_INT);

   if (!$roupa_id) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'ID do produto inválido.']);
      exit;
   }

   if (!isset($_SESSION["id"])) {
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
         http_response_code(401);
         echo json_encode(['success' => false, 'redirect' => 'login.php']);
         exit;
      } else {
         header("Location: login.php");
         exit;
      }
   }

   if ($action === 'addwish') {
      $sqlWish = "INSERT INTO PAP_WISHLIST (CLIENTE_ID, ROUPA_ID) VALUES (?, ?)";
      $stmtWish = $connection->prepare($sqlWish);
      if ($stmtWish) {
         $stmtWish->bind_param("ii", $user_id, $roupa_id);
         $stmtWish->execute();
         $stmtWish->close();
      }
      echo json_encode(['success' => true, 'action' => 'removewish']);
      exit;

   } elseif ($action === 'removewish') {
      $sqlWish = "DELETE FROM PAP_WISHLIST WHERE CLIENTE_ID = ? AND ROUPA_ID = ?";
      $stmtWish = $connection->prepare($sqlWish);
      if ($stmtWish) {
         $stmtWish->bind_param("ii", $user_id, $roupa_id);
         $stmtWish->execute();
         $stmtWish->close();
      }
      echo json_encode(['success' => true, 'action' => 'addwish']);
      exit;
   }
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
   <!-- Meta tags básicas -->
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <!-- Site metas -->
   <title>7eight9</title>
   <meta name="keywords" content="7eight9">
   <meta name="description" content="Website de roupa chamado 7eight9">
   <meta name="author" content="Gonçalo Pinto">
   <!-- Bootstrap CSS -->
   <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
   <!-- Estilos externos (mantidos para compatibilidade) -->
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
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <!-- Estilos personalizados inseridos diretamente -->
   <style>
      /* GERAL */
      body {
         background: rgb(230, 230, 230);
         font-family: 'Open Sans', sans-serif;
         margin: 0;
         padding: 0;
      }

      /* BANNER */
      .banner_section {
         padding: 0 0;
         background: rgb(230, 230, 230);
      }

      .carouselimg {
         width: 100%;
         object-fit: contain;
         border-radius: 5px;
      }

      /* PRODUTO */
      .product_section {
         padding: 40px 0 20px 0;
      }

      .product_section_2 {
         padding-top: 0px;
      }

      .product_taital {
         font-weight: 600;
         color: #333;
         text-align: center;
         flex: 1;
         position: relative;
      }

      .product_text {
         text-align: center;
         color: #555;
      }

      /* Cartão de Produto */
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
         height: 300px;
      }

      .product_box:hover {
         box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
         transform: translateY(-5px);
      }

      .product_box:hover .image_1 {
         transform: scale(1.05);
      }

      .image_1 {
         width: 100%;
         height: 200px;
         object-fit: contain;
         display: block;
         transition: transform 0.4s ease-in-out;
      }

      .btn_main {
         flex-direction: initial !important;
         display: flex;
         justify-content: space-between;
         align-items: center;
         background-color: #000;
         padding: 10px;
         min-height: 50px;
         height: 40%;
         flex-grow: 1;
         align-items: center;
      }

      .bursh_text {
         color: #fff;
         font-size: 0.9rem;
         margin: 0;
         font-family: 'Poppins', sans-serif;
         text-align: left;
         display: -webkit-box;
         -webkit-line-clamp: 3;
         /* Limita a 3 linhas */
         -webkit-box-orient: vertical;
         overflow: hidden;
         text-overflow: ellipsis;
         min-height: 60px;
         /* Altura aproximada de 3 linhas */
         line-height: 1.2;
         display: flex;
         align-items: center;
         flex: 1;
      }

      .price_text {
         color: #fff;
         font-size: 1rem;
         margin: 0;
         background: none;
         font-family: 'Poppins', sans-serif;
         border-left: 1px solid rgb(69, 69, 69);
         padding-left: 0.4rem;
      }

      /* Ribbon para produto sem stock */
      .ribbon {
         width: 150px;
         height: 150px;
         overflow: hidden;
         position: absolute;
         top: 0;
         right: 0;
         z-index: 1;
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

      .carousel-control-prev-icon,
      .carousel-control-next-icon {
         background-image: none;
         /* remove o SVG padrão */
         height: auto;
      }

      .carousel-control-prev-icon::after {
         content: "\2039";
         /* caractere de seta para a esquerda */
         font-size: 55px;
         color: black;
      }

      .carousel-control-next-icon::after {
         content: "\203A";
         /* caractere de seta para a direita */
         font-size: 55px;
         color: black;
      }

      .carousel-control-prev,
      .carousel-control-next {
         top: 50%;
         transform: translateY(-50%);
      }

      .carousel-indicators ol {
         background-color: #c3c3c3;
      }

      .carousel-indicators .active {
         background-color: #000;
      }

      .heart-container {
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

      .btnwish {
         background: none;
         border: none;
         cursor: pointer;
         outline: none;
      }

      .image-container {
         flex: 1;
         display: flex;
         align-items: center;
         justify-content: center;
         overflow: hidden;
         min-height: 200px;
         max-height: 250px;
      }

      .heart-container .fill1,
      .heart-container .fill2 {
         display: inline-block;
         font-size: 20px;
         transition: transform 0.3s ease;
      }

      .heart-container .fill1 {
         color: white;
         -webkit-text-stroke: 1px black;
      }

      .heart-container .fill2 {
         color: red;
      }

      .heart-container:hover .fill1,
      .heart-container:hover .fill2 {
         transform: scale(1.2);
      }

      .section-divider {
         color: rgba(172, 172, 172, 0.75);
         margin: 10px auto;
         width: 80%;
      }

      /* Responsividade */
      @media (max-width: 767px) {
         .btn_main {
            text-align: center;
         }

         .btn_main h4,
         .btn_main h3 {
            margin: 5px 0;
         }

         .product_taital {
            font-size: 2rem;
         }

         .bursh_text {
            padding-top: 0;
            padding-bottom: 0;
            overflow: hidden !important;
         }
      }

      @media (min-width: 767px) {
         .carousel-inner {
            width: 100%;
            margin: auto;
         }

         .carouselimg {
            height: 400px
         }
      }

      @media (max-width: 400px) {

         .col-6 {
            padding-right: 10px;
            padding-left: 10px;
         }
      }

      @media (max-width: 575px) {
         .banner_img {
            margin-top: 0;
         }
         .bursh_text {
            font-size: 0.8rem;
         }
      }
   </style>
</head>

<body>
   <!-- Header -->
   <?php include "header.php"; ?>
   <?php
   if (isset($_SESSION["nivel"]) && $_SESSION["nivel"] == 2) {
      echo ' <a href="admin.php" class="text-decoration-none">
            <div class="alert alert-success text-center" role="alert">
               <strong>Bem-vindo, Administrador!</strong> Clique aqui para aceder as funcionalidades de administrador.
            </div>
         </a>';
   }
   ?>
   <div class="banner_section">
      <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
         <?php
         $sql_carousel = "SELECT ID, IMG_CAROUSEL, LIGACAO FROM PAP_CAROUSEL ORDER BY ID";
         $result_carousel = $connection->query($sql_carousel);
         $total_imagens = $result_carousel->num_rows;
         ?>
         <ol class="carousel-indicators">
            <?php for ($i = 0; $i < $total_imagens; $i++): ?>
               <li data-target="#carouselExampleIndicators" data-slide-to="<?= $i ?>" <?= $i === 0 ? 'class="active"' : '' ?>>
               </li>
            <?php endfor; ?>
         </ol>
         <div class="carousel-inner">
            <?php
            $active = true;
            while ($row_carousel = $result_carousel->fetch_assoc()):
               $imagem = $row_carousel['IMG_CAROUSEL'];
               $ligacao = $row_carousel['LIGACAO'];
               ?>
               <div class="carousel-item <?= $active ? 'active' : '' ?>">
                  <?php if (!empty($ligacao)): ?>
                     <a href="<?= htmlspecialchars($ligacao) ?>">
                        <img loading="lazy" src="data:image/jpeg;base64,<?= base64_encode($imagem) ?>"
                           class="d-block w-100 carouselimg" alt="Imagem Carousel">
                     </a>
                  <?php else: ?>
                     <img loading="lazy" src="data:image/jpeg;base64,<?= base64_encode($imagem) ?>"
                        class="d-block w-100 carouselimg" alt="Imagem Carousel">
                  <?php endif; ?>
               </div>
               <?php
               $active = false;
            endwhile;
            ?>
         </div>
         <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="sr-only">Anterior</span>
         </a>
         <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="sr-only">Próximo</span>
         </a>
      </div>
   </div>
   <div class="product_section layout_padding_2">
      <div class="container">
         <div class="row">
            <div class="col-sm-12">
               <h1 class="product_taital">Recomendados para si</h1>
               <p class="product_text">Produtos selecionados especialmente para si</p>
            </div>
         </div>
         <div class="product_section_2 layout_padding">
            <div class="row">
               <?php
               $limit = 4;
               $sql = "SELECT ID, NOME, PRECO, FT_1 FROM PAP_ROUPA ORDER BY RAND() LIMIT $limit";
               $roupa = $connection->query($sql);
               while ($row = mysqli_fetch_array($roupa)) {
                  $r_id = $row['ID'];
                  $r_nome = $row['NOME'];
                  $r_preco = $row['PRECO'];
                  $r_ft1 = $row['FT_1'];
                  $sqlStock = "SELECT SUM(QNT) as totalStock FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = " . $r_id;
                  $resultStock = $connection->query($sqlStock);
                  $rowStock = mysqli_fetch_assoc($resultStock);
                  $totalStock = $rowStock['totalStock'];
                  if (isset($_SESSION["id"])) {
                     $ld = "SELECT * FROM PAP_WISHLIST WHERE CLIENTE_ID=" . $_SESSION['id'] . " AND ROUPA_ID=" . $r_id;
                  } else {
                     $ld = "SELECT * FROM PAP_WISHLIST WHERE CLIENTE_ID=0 AND ROUPA_ID=" . $r_id;
                  }
                  $wish = $connection3->query($ld);
                  ?>
                  <div class="col-6 col-md-6 col-lg-3">
                     <a href="roupa2.php?tipo=<?php echo $r_id ?>" class="text-decoration-none">
                        <div class="product_box">
                           <?php
                           $isInWishlist = false;
                           if ($user_id) {
                              $sqlWishCheck = "SELECT 1 FROM PAP_WISHLIST WHERE CLIENTE_ID = ? AND ROUPA_ID = ?";
                              $stmtWishCheck = $connection->prepare($sqlWishCheck);
                              $stmtWishCheck->bind_param("ii", $user_id, $r_id);
                              $stmtWishCheck->execute();
                              $stmtWishCheck->store_result();
                              $isInWishlist = $stmtWishCheck->num_rows > 0;
                              $stmtWishCheck->close();
                           }
                           $wishAction = $isInWishlist ? 'removewish' : 'addwish';
                           $wishClass = $isInWishlist ? 'fill2' : 'fill1';
                           ?>
                           <form method="POST" class="formwish heart-container">
                              <input type="hidden" name="action" value="<?php echo $wishAction; ?>">
                              <input type="hidden" name="roupa_id" value="<?php echo $r_id; ?>">
                              <button type="submit" class="btnwish">
                                 <i class="bi bi-heart-fill <?php echo $wishClass; ?>"></i>
                              </button>
                           </form>
                           <?php if (!$totalStock || $totalStock <= 0): ?>
                              <div class="ribbon">
                                 <span>ESGOTADO</span>
                              </div>
                           <?php endif; ?>
                           <div class="image-container">
                              <img loading="lazy" src="data:image/jpeg;base64,<?php echo base64_encode($r_ft1); ?>"
                                 class="image_1" alt="<?php echo $r_nome ?>">
                           </div>
                           <div class="btn_main">
                              <h4 class="bursh_text"><?php echo truncateText($r_nome) ?></h4>
                              <h3 class="price_text"><?php echo $r_preco ?>€</h3>
                           </div>
                        </div>
                     </a>
                  </div>
                  <?php
               }
               ?>
            </div>
         </div>
      </div>
      <div class="col-sm-12 mb-2 text-center">
         <a href="roupa.php?=0" class="btn btn-secondary"> <i class="bi bi-shop"></i> Ver Todos os Produtos</a>
      </div>
   </div>
   <hr class="section-divider">
   <div class="product_section layout_padding_2" style="padding-top: 10px;">
      <div class="container">
         <div class="row">
            <div class="col-sm-12">
               <h1 class="product_taital">Novidades</h1>
               <p class="product_text">Os nossos produtos mais recentes</p>
            </div>
         </div>
         <div class="product_section_2 layout_padding">
            <div class="row">
               <?php
               $sql_novidades = "SELECT ID, NOME, PRECO, FT_1 FROM PAP_ROUPA ORDER BY DATA_REGISTO DESC, ID DESC LIMIT 4";
               $novidades = $connection->query($sql_novidades);
               $contador_novidades = 0;
               while ($row_nov = mysqli_fetch_array($novidades)) {
                  $r_id = $row_nov['ID'];
                  $r_nome = $row_nov['NOME'];
                  $r_preco = $row_nov['PRECO'];
                  $r_ft1 = $row_nov['FT_1'];
                  $sqlStock = "SELECT SUM(QNT) as totalStock FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = " . $r_id;
                  $resultStock = $connection->query($sqlStock);
                  $rowStock = mysqli_fetch_assoc($resultStock);
                  $totalStock = $rowStock['totalStock'];
                  if (isset($_SESSION["id"])) {
                     $ld = "SELECT * FROM PAP_WISHLIST WHERE CLIENTE_ID=" . $_SESSION['id'] . " AND ROUPA_ID=" . $r_id;
                  } else {
                     $ld = "SELECT * FROM PAP_WISHLIST WHERE CLIENTE_ID=0 AND ROUPA_ID=" . $r_id;
                  }
                  $wish = $connection3->query($ld);
                  ?>
                  <div class="col-6 col-md-6 col-lg-3">
                     <a href="roupa2.php?tipo=<?php echo $r_id ?>" class="text-decoration-none">
                        <div class="product_box">
                           <?php
                           $isInWishlist = false;
                           if ($user_id) {
                              $sqlWishCheck = "SELECT 1 FROM PAP_WISHLIST WHERE CLIENTE_ID = ? AND ROUPA_ID = ?";
                              $stmtWishCheck = $connection->prepare($sqlWishCheck);
                              $stmtWishCheck->bind_param("ii", $user_id, $r_id);
                              $stmtWishCheck->execute();
                              $stmtWishCheck->store_result();
                              $isInWishlist = $stmtWishCheck->num_rows > 0;
                              $stmtWishCheck->close();
                           }
                           $wishAction = $isInWishlist ? 'removewish' : 'addwish';
                           $wishClass = $isInWishlist ? 'fill2' : 'fill1';
                           ?>
                           <form method="POST" class="formwish heart-container">
                              <input type="hidden" name="action" value="<?php echo $wishAction; ?>">
                              <input type="hidden" name="roupa_id" value="<?php echo $r_id; ?>">
                              <button type="submit" class="btnwish">
                                 <i class="bi bi-heart-fill <?php echo $wishClass; ?>"></i>
                              </button>
                           </form>
                           <?php if (!$totalStock || $totalStock <= 0): ?>
                              <div class="ribbon">
                                 <span>ESGOTADO</span>
                              </div>
                           <?php endif; ?>
                           <div class="image-container">
                              <img loading="lazy" src="data:image/jpeg;base64,<?php echo base64_encode($r_ft1); ?>"
                                 class="image_1" alt="<?php echo $r_nome ?>">
                           </div>
                           <div class="btn_main">
                              <h4 class="bursh_text"><?php echo truncateText($r_nome) ?></h4>
                              <h3 class="price_text"><?php echo $r_preco ?>€</h3>
                           </div>
                        </div>
                     </a>
                  </div>
                  <?php
                  $contador_novidades++;
                  if ($contador_novidades % 4 == 0)
                     echo "</div><div class='row'>";
               }
               ?>
            </div>
         </div>
      </div>
      <div class="col-sm-12 mb-2 text-center">
         <a href="roupa.php?tipo=0&ordenacao=mais_novo" class="btn btn-secondary"> <i class="bi bi-shop"></i> Ver
            Produtos Mais Recentes</a>
      </div>
   </div>
   <!-- Footer -->
   <?php include "footer.php"; ?>
   <!-- Javascript Files -->
   <script src="js/jquery.min.js"></script>
   <script src="js/popper.min.js"></script>
   <script src="js/bootstrap.bundle.min.js"></script>
   <script src="js/jquery-3.0.0.min.js"></script>
   <script>
      $(document).ready(function () {
         $('.formwish').on('submit', function (e) {
            e.preventDefault();
            var form = $(this);
            var icon = form.find('.btnwish i');
            var currentAction = form.find('input[name="action"]').val();

            $.ajax({
               url: window.location.href,
               type: 'POST',
               data: form.serialize(),
               dataType: 'json',
               success: function (response) {
                  // Atualiza o input hidden com a nova ação
                  form.find('input[name="action"]').val(response.action);

                  // Atualiza o ícone de coração
                  if (response.action === 'removewish') {
                     icon.removeClass('fill1').addClass('fill2'); // fica vermelho
                  } else {
                     icon.removeClass('fill2').addClass('fill1'); // volta a branco
                  }
               },
               error: function (xhr) {
                  if (xhr.status === 401 && xhr.responseJSON?.redirect) {
                     window.location.href = xhr.responseJSON.redirect;
                  } else {
                     alert("Ocorreu um erro ao processar o pedido.");
                  }
               }
            });
         });
      });
   </script>
   <script src="js/plugin.js"></script>
   <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
   <script src="js/custom.js"></script>
   <script src="js/owl.carousel.js"></script>
</body>

</html>