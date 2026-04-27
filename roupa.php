<?php
require "connection.php";
require_once './core.php';

if (session_status() === PHP_SESSION_NONE) {
   session_start();
}

$user_id = isset($_SESSION["id"]) ? $_SESSION["id"] : 0;
$connection = db_connect();
$tipo = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_NUMBER_INT);
$tipo = ($tipo === null || $tipo === false) ? 0 : (int) $tipo;

$cores = filter_input(INPUT_GET, 'cores', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
$marcas = filter_input(INPUT_GET, 'marcas', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
$materiais = filter_input(INPUT_GET, 'materiais', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);

$ordenacao = filter_input(INPUT_GET, 'ordenacao', FILTER_SANITIZE_STRING);
$preco_min = filter_input(INPUT_GET, 'preco_min', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$preco_max = filter_input(INPUT_GET, 'preco_max', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

// Adicionando parâmetros de paginação
$pagina_atual = filter_input(INPUT_GET, 'pagina', FILTER_SANITIZE_NUMBER_INT);
$pagina_atual = ($pagina_atual === null || $pagina_atual === false) ? 1 : (int) $pagina_atual;
$produtos_por_pagina = 12;

if ($pagina_atual > $total_paginas && $total_paginas > 0) {
   header("Location: " . getPaginationUrl($total_paginas));
   exit;
}

$com_stock = filter_input(INPUT_GET, 'com_stock', FILTER_SANITIZE_NUMBER_INT);
$com_stock = ($com_stock === null || $com_stock === false) ? 0 : (int) $com_stock;

function prepareAndBind($connection, $sql, $params, $paramTypes)
{
   $stmt = $connection->prepare($sql);
   if (!$stmt) {
      return false;
   }

   if (!empty($params)) {
      $bindParams = [$stmt, $paramTypes];
      foreach ($params as &$param) {
         $bindParams[] = &$param;
      }

      call_user_func_array('mysqli_stmt_bind_param', $bindParams);
   }

   return $stmt;
}
function getPaginationUrl($pagina)
{
   $params = $_GET;
   $params['pagina'] = $pagina;
   return 'roupa.php?' . http_build_query($params);
}

if (!in_array($ordenacao, ['recomendados', 'preco_asc', 'preco_desc', 'mais_novo', 'mais_antigo'])) {
   $ordenacao = 'recomendados';
}
$preco_min = $preco_min ? floatval($preco_min) : null;
$preco_max = $preco_max ? floatval($preco_max) : null;

$sqlCatCount = "SELECT COUNT(*) AS total FROM PAP_CATEGORIA_ROUPA";
$totalCategorias = 0;
if ($stmtCatCount = $connection->prepare($sqlCatCount)) {
   $stmtCatCount->execute();
   $stmtCatCount->bind_result($totalCategorias);
   $stmtCatCount->fetch();
   $stmtCatCount->close();
}

if ($tipo > 0) {
   // Verificar se a categoria existe
   $sqlCheckCat = "SELECT ID FROM PAP_CATEGORIA_ROUPA WHERE ID = ? LIMIT 1";
   $stmtCheckCat = $connection->prepare($sqlCheckCat);

   if ($stmtCheckCat) {
      $stmtCheckCat->bind_param("i", $tipo);
      $stmtCheckCat->execute();
      $stmtCheckCat->store_result();

      if ($stmtCheckCat->num_rows === 0) {
         $tipo = 0; // Categoria não existe, voltar para "todas"
      }
      $stmtCheckCat->close();
   } else {
      // Log de erro ou tratamento adequado
      error_log("Erro ao verificar categoria: " . $connection->error);
      $tipo = 0;
   }
}

if (is_numeric($tipo) && ($tipo > $totalCategorias || $tipo < 0)) {
   $tipo = 0;
}

$cores = isset($_GET['cores']) && is_array($_GET['cores']) ?
   array_filter($_GET['cores'], 'is_numeric') : [];
$marcas = isset($_GET['marcas']) && is_array($_GET['marcas']) ?
   array_filter($_GET['marcas'], 'is_numeric') : [];
$materiais = isset($_GET['materiais']) && is_array($_GET['materiais']) ?
   array_filter($_GET['materiais'], 'is_numeric') : [];

$params = [];
$paramTypes = "";

// Base da query SQL
$sql = "SELECT DISTINCT R.*, C.CATEGORIA AS NOME_CATEGORIA 
        FROM PAP_ROUPA R FORCE INDEX (idx_categoria, idx_preco, idx_data_registo)
        JOIN PAP_CATEGORIA_ROUPA C ON R.CATEGORIA = C.ID
        LEFT JOIN PAP_ROUPA_HAS_TAMANHO RT ON R.ID = RT.ROUPA_ID
        WHERE 1=1";

$params = [];
$paramTypes = "";

if ($tipo > 0) {
   $sql .= " AND R.CATEGORIA = ?";
   $params[] = $tipo;
   $paramTypes .= 'i';
}

if ($preco_min !== null) {
   $sql .= " AND R.PRECO >= ?";
   $params[] = $preco_min;
   $paramTypes .= 'd';
}

if ($preco_max !== null) {
   $sql .= " AND R.PRECO <= ?";
   $params[] = $preco_max;
   $paramTypes .= 'd';
}

if (!empty($cores) && is_array($cores)) {
   $placeholders = implode(',', array_fill(0, count($cores), '?'));
   $sql .= " AND R.COR IN ($placeholders)";
   $params = array_merge($params, $cores);
   $paramTypes .= str_repeat('i', count($cores));
}

if (!empty($marcas) && is_array($marcas)) {
   $placeholders = implode(',', array_fill(0, count($marcas), '?'));
   $sql .= " AND R.MARCA IN ($placeholders)";
   $params = array_merge($params, $marcas);
   $paramTypes .= str_repeat('i', count($marcas));
}

if (!empty($materiais) && is_array($materiais)) {
   $placeholders = implode(',', array_fill(0, count($materiais), '?'));
   $sql .= " AND R.MATERIAIS IN ($placeholders)";
   $params = array_merge($params, $materiais);
   $paramTypes .= str_repeat('i', count($materiais));
}

if (!empty($searchTerm)) {
   $sql .= " AND R.NOME LIKE ?";
   $params[] = "%$searchTerm%";
   $paramTypes .= "s";
}

if ($com_stock) {
   $sql .= " AND RT.QNT > 0";
}

switch ($ordenacao) {
   case 'preco_asc':
      $sql .= " ORDER BY R.PRECO ASC";
      break;
   case 'preco_desc':
      $sql .= " ORDER BY R.PRECO DESC";
      break;
   case 'mais_novo':
      $sql .= " ORDER BY R.DATA_REGISTO DESC, R.ID DESC";
      break;
   case 'mais_antigo':
      $sql .= " ORDER BY R.DATA_REGISTO ASC, R.ID ASC";
      break;
   case 'recomendados':
   default:
      $sql .= " ORDER BY RAND()";
      break;
}

$offset = ($pagina_atual - 1) * $produtos_por_pagina;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $produtos_por_pagina;
$paramTypes .= 'ii';



if ($stmt = $connection->prepare($sql)) {
   if (!empty($params)) {
      $bindParams = [$paramTypes];
      foreach ($params as &$param) {
         $bindParams[] = &$param;
      }

      call_user_func_array([$stmt, 'bind_param'], $bindParams);
   }

   $stmt = prepareAndBind($connection, $sql, $params, $paramTypes);
   if ($stmt) {
      $stmt->execute();
      $result = $stmt->get_result();

      if (!$result) {
         error_log("Erro ao obter resultados: " . $stmt->error);
         // Tratamento de erro adequado
         die("Ocorreu um erro ao processar sua solicitação. Por favor, tente novamente mais tarde.");
      }
   } else {
      die("Erro ao preparar a consulta SQL");
   }

   $stmt->close();
} else {
   die("Erro na preparação da consulta: " . $connection->error);
}

$sqlCount = "SELECT COUNT(DISTINCT R.ID) AS total 
             FROM PAP_ROUPA R FORCE INDEX (idx_categoria, idx_preco, idx_data_registo)
             JOIN PAP_CATEGORIA_ROUPA C ON R.CATEGORIA = C.ID
             LEFT JOIN PAP_ROUPA_HAS_TAMANHO RT ON R.ID = RT.ROUPA_ID
             WHERE 1=1";

if ($com_stock) {
   $sqlCount .= " AND EXISTS (SELECT 1 FROM PAP_ROUPA_HAS_TAMANHO WHERE ROUPA_ID = R.ID AND QNT > 0)";
}

$paramsCount = [];
$paramTypesCount = "";

if ($tipo > 0) {
   $sqlCount .= " AND R.CATEGORIA = ?";
   $paramsCount[] = $tipo;
   $paramTypesCount .= 'i';
}

if ($preco_min !== null) {
   $sqlCount .= " AND R.PRECO >= ?";
   $paramsCount[] = $preco_min;
   $paramTypesCount .= 'd';
}

if ($preco_max !== null) {
   $sqlCount .= " AND R.PRECO <= ?";
   $paramsCount[] = $preco_max;
   $paramTypesCount .= 'd';
}

if (!empty($cores) && is_array($cores)) {
   $placeholders = implode(',', array_fill(0, count($cores), '?'));
   $sql .= " AND R.COR IN ($placeholders)";
   $params = array_merge($params, $cores);
   $paramTypes .= str_repeat('i', count($cores));
}

if (!empty($marcas) && is_array($marcas)) {
   $placeholders = implode(',', array_fill(0, count($marcas), '?'));
   $sql .= " AND R.MARCA IN ($placeholders)";
   $params = array_merge($params, $marcas);
   $paramTypes .= str_repeat('i', count($marcas));
}

if (!empty($materiais) && is_array($materiais)) {
   $placeholders = implode(',', array_fill(0, count($materiais), '?'));
   $sql .= " AND R.MATERIAIS IN ($placeholders)";
   $params = array_merge($params, $materiais);
   $paramTypes .= str_repeat('i', count($materiais));
}

if (!empty($searchTerm)) {
   $sqlCount .= " AND R.NOME LIKE ?";
   $paramsCount[] = "%$searchTerm%";
   $paramTypesCount .= "s";
}

$total_produtos = 0;
$stmtCount = prepareAndBind($connection, $sqlCount, $paramsCount, $paramTypesCount);
if ($stmtCount) {
   $stmtCount->execute();
   $stmtCount->bind_result($total_produtos);
   $stmtCount->fetch();
   $stmtCount->close();
}

$total_paginas = ceil($total_produtos / $produtos_por_pagina);

$categoriaNome = "";
if (is_numeric($tipo)) {
   $categoriaQuery = "SELECT CATEGORIA FROM PAP_CATEGORIA_ROUPA WHERE ID = ?";
   $categoriaStmt = $connection->prepare($categoriaQuery);
   $categoriaStmt->bind_param("i", $tipo);
   $categoriaStmt->execute();
   $categoriaStmt->bind_result($categoriaNome);
   $categoriaStmt->fetch();
   $categoriaStmt->close();
}

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

   $success = false;
   $newAction = '';

   if ($action === 'addwish') {
      // Verificar se já não está na wishlist
      $checkSql = "SELECT 1 FROM PAP_WISHLIST WHERE CLIENTE_ID = ? AND ROUPA_ID = ?";
      $checkStmt = $connection->prepare($checkSql);
      $checkStmt->bind_param("ii", $user_id, $roupa_id);
      $checkStmt->execute();
      $alreadyExists = $checkStmt->get_result()->num_rows > 0;
      $checkStmt->close();

      if (!$alreadyExists) {
         $sqlWish = "INSERT INTO PAP_WISHLIST (CLIENTE_ID, ROUPA_ID) VALUES (?, ?)";
         $stmtWish = $connection->prepare($sqlWish);
         if ($stmtWish) {
            $stmtWish->bind_param("ii", $user_id, $roupa_id);
            $success = $stmtWish->execute();
            $stmtWish->close();
         }
      } else {
         $success = true; // Já está na wishlist
      }
      $newAction = 'removewish';

   } elseif ($action === 'removewish') {
      $sqlWish = "DELETE FROM PAP_WISHLIST WHERE CLIENTE_ID = ? AND ROUPA_ID = ?";
      $stmtWish = $connection->prepare($sqlWish);
      if ($stmtWish) {
         $stmtWish->bind_param("ii", $user_id, $roupa_id);
         $success = $stmtWish->execute();
         $stmtWish->close();
      }
      $newAction = 'addwish';
   }

   if ($success) {
      echo json_encode([
         'success' => true,
         'action' => $newAction,
         'newIconClass' => $newAction === 'removewish' ? 'bi-heart-fill fill2' : 'bi-heart-fill fill1'
      ]);
   } else {
      echo json_encode(['success' => false, 'message' => 'Operação falhou']);
   }
   exit;
}
function displayBlobImage($blobData, $altText = '')
{
   $style = 'height:200px;width:auto;object-fit:cover;';
   if ($blobData) {
      if (filter_var($blobData, FILTER_VALIDATE_URL)) {
         return '<img loading="lazy" 
                    src="' . htmlspecialchars($blobData) . '" 
                    alt="' . htmlspecialchars($altText) . '" 
                    class="image_1" 
                    style="' . $style . '">';
      } else {
         return '<img loading="lazy" 
                    src="data:image/jpeg;base64,' . base64_encode($blobData) . '" 
                    alt="' . htmlspecialchars($altText) . '" 
                    class="image_1" 
                    style="' . $style . '"
                    decoding="async">';
      }
   }

   return '<img loading="lazy" 
            src="img/images.jpg" 
            alt="' . htmlspecialchars($altText) . '" 
            class="image_1" 
            style="' . $style . '">';
}
function truncateText($text, $maxLength = 50)
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
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <title><?php echo $categoriaNome ? $categoriaNome : "Produtos"; ?></title>
   <meta name="keywords" content="">
   <meta name="description" content="">
   <meta name="author" content="">
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
   <link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css" />
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
   <style>
      body {
         background: rgb(230, 230, 230);
         font-family: 'Open Sans', sans-serif;
      }

      .product_section {
         padding: 20px 0;
      }

      .product_taital {
         font-weight: 600;
         color: #333;
         margin-bottom: 40px;
         text-align: center;
         flex: 1;
         padding-bottom: 20px;
         position: relative;
         max-width: calc(100% - 60px);
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

      .product_section_2 {
         padding-top: 15px;
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
         height: 380px;
      }

      .product_box:hover {
         transform: translateY(-5px);
         box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
      }

      .product_box img {
         width: 100%;
         height: 200px;
         object-fit: cover;
      }

      .btn_main {
         flex-direction: initial !important;
         display: flex;
         justify-content: space-between;
         align-items: center;
         background-color: #000;
         padding: 10px;
         min-height: 60px;
         height: 40%;
      }

      .bursh_text {
         color: #fff;
         font-size: 1rem;
         margin: 0;
         font-family: 'Poppins', sans-serif;
         text-align: left;
         display: -webkit-box;
         -webkit-box-orient: vertical;
         overflow: hidden;
         text-overflow: ellipsis;
         min-height: 60px;
         /* Altura aproximada de 3 linhas */
         line-height: 1.2;
      }

      .price_text {
         color: #fff;
         font-size: 1rem;
         margin: 0;
         background-color: inherit;
         font-family: 'Poppins', sans-serif;
         border-left: 1px solid rgb(69, 69, 69);
         padding-left: 0.4rem;
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

      .image_1 {
         object-fit: contain !important;
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

      .heart-container .fill1,
      .heart-container .fill2 {
         display: inline-block;
         font-size: 20px;
         transition: transform 0.3s ease;
      }

      .heart-container .fill1 {
         color: white;
         -webkit-text-stroke: 1.3px black;
      }

      .heart-container .fill2 {
         color: red;
      }

      .heart-container:hover .fill1,
      .heart-container:hover .fill2 {
         transform: scale(1.2);
      }

      .bi-filter {
         font-size: 1.5rem;
         cursor: pointer;
         background: #f0f0f0;
         padding: 8px;
         border-radius: 50%;
         transition: all 0.3s ease;
      }

      .bi-filter:hover {
         background: #e0e0e0;
      }


      .divfilter {
         flex: 0 0 auto;
         padding-left: 10px;
      }

      .filterside {
         height: 100%;
         width: 0;
         position: fixed;
         z-index: 1050;
         top: 0;
         right: 0;
         background-color: #f8f9fa;
         overflow-x: hidden;
         transition: width 0.4s ease;
         box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
      }


      .filter-header {
         padding: 20px 25px;
         background-color: #343a40;
         margin-bottom: 15px;
      }

      .filter-title {
         color: #fff;
         font-size: 1.8rem;
         font-weight: 600;
         margin-bottom: 5px;
      }

      .filter-subtitle {
         color: #adb5bd;
         font-size: 0.9rem;
      }

      .filter-section {
         padding: 15px 25px;
         border-bottom: 1px solid #e9ecef;
      }

      .filter-section .form-select,
      .filter-section .form-control {
         border-radius: 6px;
         padding: 10px 15px;
         border: 1px solid #dee2e6;
         transition: all 0.3s ease;
      }

      .filter-section .form-select:focus,
      .filter-section .form-control:focus {
         border-color: #007bff;
         box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
      }

      .filter-section .form-label {
         color: #495057;
         font-weight: 500;
         margin-bottom: 8px;
         display: block;
      }

      .filter-section:last-child {
         border-bottom: none;
      }

      .filter-section-title {
         color: #495057;
         font-size: 1.1rem;
         font-weight: 600;
         margin-bottom: 15px;
         display: flex;
         align-items: center;
         cursor: pointer;
         user-select: none;
         position: relative;
         padding-right: 30px;
      }

      .filter-section-title .toggle-arrow {
         position: absolute;
         right: 0;
         transition: transform 0.3s ease;
      }

      .filter-section-title.collapsed .toggle-arrow {
         transform: rotate(-90deg);
      }

      .filter-section-content {
         overflow: hidden;
         transition: max-height 0.3s ease, opacity 0.3s ease;
         max-height: 1000px;
         opacity: 1;
      }

      .filter-section-content.collapsed {
         max-height: 0;
         opacity: 0;
      }

      .filter-section-title i {
         margin-right: 10px;
         font-size: 1.2rem;
         color: #6c757d;
      }

      .filter-options {
         display: flex;
         flex-direction: column;
         gap: 10px;
      }

      .filter-option {
         color: #495057;
         padding: 8px 12px;
         border-radius: 6px;
         text-decoration: none;
         transition: all 0.3s ease;
         display: flex;
         align-items: center;
         background-color: #fff;
         border: 1px solid #dee2e6;
      }

      .filter-option:hover {
         background-color: #e9ecef;
         color: #212529;
         transform: translateX(5px);
         border-color: #ced4da;
      }

      .filter-option.active {
         background-color: #007bff;
         color: white;
         border-color: #007bff;
      }

      .filter-option i {
         margin-right: 8px;
         transition: all 0.3s ease;
         color: #6c757d;
      }

      .filter-option.active i {
         color: white;
      }

      .filter-option:hover i {
         transform: rotate(90deg);
      }

      .filter-checkbox {
         padding: 1rem 12px;
         border-radius: 6px;
         transition: all 0.3s ease;
         background-color: #fff;
         border: 1px solid #dee2e6;
         width: 100%;
      }

      .filter-checkbox:hover {
         background-color: #e9ecef;
      }

      .form-check-input {
         margin-left: -0.5rem;
      }

      .filter-checkbox .form-check-label {
         color: #495057;
         cursor: pointer;
         margin-left: 15px;
         width: 100%;
      }

      .filter-checkbox .form-check-input {
         cursor: pointer;
      }

      .filter-checkbox .form-check-input:checked {
         background-color: #007bff;
         border-color: #007bff;
      }

      .filter-colors {
         display: flex;
         flex-wrap: wrap;
         gap: 15px;
      }

      .color-container {
         display: flex;
         flex-direction: column;
         align-items: center;
         gap: 5px;
         width: 60px;
      }

      .color-option {
         width: 40px;
         height: 40px;
         border-radius: 50%;
         cursor: pointer;
         transition: all 0.3s ease;
         position: relative;
         display: flex;
         align-items: center;
         justify-content: center;
      }

      .color-circle {
         width: 34px;
         height: 34px;
         border-radius: 50%;
         border: 2px solid #fff;
         box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
      }

      .color-option.selected .color-circle {
         border: 3px solid #3498db;
      }

      .color-name {
         color: #495057;
         font-size: 0.8rem;
         text-align: center;
         word-break: break-word;
         width: 100%;
      }

      .filter-actions {
         display: flex;
         justify-content: space-between;
         padding: 20px;
         background-color: #f8f9fa;
         position: sticky;
         bottom: 0;
         border-top: 1px solid #e9ecef;
         gap: 15px;
      }

      .btn-clear,
      .btn-apply {
         padding: 10px 15px;
         border-radius: 6px;
         font-weight: 500;
         transition: all 0.3s ease;
         display: flex;
         align-items: center;
         flex: 1;
         justify-content: center;
      }

      .btn-clear {
         background-color: #6c757d;
         color: white;
         border: none;
      }

      .btn-clear:hover {
         background-color: #5a6268;
      }

      .btn-apply {
         background-color: #007bff;
         color: white;
         border: none;
      }

      .btn-apply:hover {
         background-color: #0069d9;
      }

      .btn-clear i,
      .btn-apply i {
         margin-right: 8px;
      }

      .closefilter {
         position: absolute;
         top: 20px;
         right: 25px;
         font-size: 36px;
         color: #495057;
         font-weight: bold;
         cursor: pointer;
         transition: 0.3s;
         z-index: 2;
      }

      .closefilter:hover {
         color: #dc3545;
         transform: rotate(90deg);
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

      .btn_cart_disabled {
         background-color: #cccccc !important;
         color: #6c757d !important;
         cursor: not-allowed;
         border: none;
         height: 100%;
      }

      .btn_cart_enabled {
         background-color: #00b27d !important;
         color: white !important;
         cursor: pointer;
         border: none;
         height: 100%;
      }

      .btn_cart_enabled:hover {
         background-color: #008f65 !important;
      }

      .ver-mais-container {
         text-align: center;
         margin-bottom: 0.5rem;
      }

      .color-option.selected {
         transform: scale(1.1);
         box-shadow: 0 0 0 2px #3498db;
      }

      .color-option.selected .color-circle {
         border: 3px solid white !important;
      }

      .mb-3 {
         margin-bottom: 0 !important;
      }

      .search-container {
         margin-bottom: 20px;
      }

      .search-input {
         border-radius: 20px 0 0 20px !important;
         border-right: none;
         padding: 10px 20px;
         height: 45px;
      }

      .search-btn {
         border-radius: 0 20px 20px 0 !important;
         background-color: #000;
         color: white;
         border-left: none;
         height: 45px;
      }

      .search-btn:hover {
         background-color: #333;
         color: white;
      }

      .image-container {
         flex: 1;
         display: flex;
         align-items: center;
         justify-content: center;
         overflow: hidden;
         height: 200px !important;
      }

      /* Estilos para a paginação */
      .pagination {
         display: flex;
         justify-content: center;
         margin-top: 30px;
         margin-bottom: 30px;
      }

      .pagination .page-item {
         margin: 0 5px;
      }

      .pagination .page-link {
         color: #495057;
         border: 1px solid #dee2e6;
         padding: 8px 16px;
         border-radius: 4px;
         transition: all 0.3s;
      }

      .pagination .page-link:hover {
         background-color: #e9ecef;
         color: #007bff;
         border-color: #dee2e6;
      }

      .pagination .page-item.active .page-link {
         background-color: #007bff;
         color: white;
         border-color: #007bff;
      }

      .pagination .page-item.disabled .page-link {
         color: #6c757d;
         pointer-events: none;
         background-color: #fff;
         border-color: #dee2e6;
      }

      .filter-checkbox#com_stock {
         background-color: #f8f9fa;
         border-left: 4px solid #28a745;
      }

      .filter-checkbox#com_stock:hover {
         background-color: #e9ecef;
      }

      .filter-checkbox#com_stock .form-check-label {
         font-weight: 500;
         color: #28a745;
      }

      @media (max-width: 576px) {
         .search-container {
            padding: 0 15px;
         }
      }

      @media (max-width: 500px) {
         .product_taital {
            white-space: normal !important;
         }
      }

      @media (max-width: 400px) {

         .btn_main h4,
         .btn_main h3 {
            font-size: 0.9rem;
         }

         .btn_main h4 {
            padding-left: 0;
         }
      }

      @media screen and (max-width: 767px) {
         .color-container {
            width: 50px;
         }

         .color-option {
            width: 35px;
            height: 35px;
         }

         .color-circle {
            width: 30px;
            height: 30px;
         }

         .color-name {
            font-size: 0.69rem;
         }

         .filter-actions {
            flex-direction: column;
            gap: 10px;
         }

         .btn-clear,
         .btn-apply {
            width: 100%;
            justify-content: center;
         }

         .product_image {
            height: auto;
            margin-bottom: 10px;
         }

         .product_taital {
            font-size: 2rem;
         }

         .product-price {
            font-size: 1.75rem;
         }

         .btn_main {
            text-align: center;
         }

         .btn_main h4,
         .btn_main h3 {
            margin: 5px 0;
         }
      }

      @media (max-width: 339px) {

         .bi-heart,
         .bi-heart-fill {
            font-size: 1.5rem !important;
         }
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
   </style>
</head>

<body>
   <?php include "header.php"; ?>

   <div class="product_section layout_padding_2">
      <div class="container">
         <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-end">
               <div style="flex: 1;"></div>
               <h1 class="product_taital mb-0 text-center" style="flex: 1; white-space: nowrap;">
                  <?php echo $categoriaNome ? $categoriaNome : "Os nossos produtos"; ?>
               </h1>
               <div class="divfilter" style="flex: 1; text-align: right;">
                  <i class="bi bi-filter" onclick="openFilter()"></i>
               </div>
            </div>
         </div>
         <div class="row mt-4">
            <div class="col-md-6 mx-auto">
               <form method="get" action="roupa.php" class="d-flex">
                  <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                  <div class="input-group">
                     <input type="text" name="search" class="form-control search-input"
                        placeholder="Pesquisar artigos..."
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                     <button class="btn search-btn" type="submit">
                        <i class="bi bi-search"></i>
                     </button>
                     <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <a href="roupa.php?tipo=<?php echo $tipo; ?>" class="btn btn-outline-danger">
                           <i class="bi bi-x"></i>
                        </a>
                     <?php endif; ?>
                  </div>
               </form>
            </div>
         </div>

         <div class="product_section_2 layout_padding">
            <div class="row">
               <?php
               if ($result->num_rows > 0) {
                  $cont = 0;
                  while ($row = $result->fetch_assoc()) {
                     $r_id = $row['ID'];
                     $r_nome = $row['NOME'];
                     $r_preco = $row['PRECO'];
                     $r_ft1 = $row['FT_1'];

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
                                    <i
                                       class="bi <?php echo $isInWishlist ? 'bi-heart-fill fill2' : 'bi-heart-fill fill1'; ?>"></i>
                                 </button>
                              </form>
                              <?php if (!$totalStock || $totalStock <= 0): ?>
                                 <div class="ribbon">
                                    <span>ESGOTADO</span>
                                 </div>
                              <?php endif; ?>
                              <div class="image-container">
                                 <?php echo displayBlobImage($r_ft1, $r_nome); ?>
                              </div>
                              <div class="btn_main">
                                 <h4 class="bursh_text"><?php echo truncateText($r_nome) ?></h4>
                                 <h3 class="price_text"><?php echo $r_preco ?>€</h3>
                              </div>
                           </div>
                        </a>
                     </div>
                     <?php
                     if ($cont % 4 == 0)
                        echo "</div><div class='row'>";
                  }
               } else {
                  $message = ($tipo != 0) ? "Desculpe, mas de momento não tem roupa disponível nesta categoria.<br>Tente Novamente mais tarde." : "Desculpe, mas de momento não tem roupa disponível.<br>Tente Novamente mais tarde.";
                  echo "<div class='col-12'><h3 class='text-center text-danger'>$message</h3></div>";
               }
               ?>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
               <div class="row">
                  <div class="col-12">
                     <nav aria-label="Page navigation">
                        <ul class="pagination">
                           <?php if ($pagina_atual > 1): ?>
                              <li class="page-item">
                                 <a class="page-link" href="<?php echo getPaginationUrl($pagina_atual - 1); ?>"
                                    aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                 </a>
                              </li>
                           <?php else: ?>
                              <li class="page-item disabled">
                                 <a class="page-link" href="#" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                 </a>
                              </li>
                           <?php endif; ?>

                           <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                              <li class="page-item <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>">
                                 <a class="page-link" href="<?php echo getPaginationUrl($i); ?>"><?php echo $i; ?></a>
                              </li>
                           <?php endfor; ?>

                           <?php if ($pagina_atual < $total_paginas): ?>
                              <li class="page-item">
                                 <a class="page-link" href="<?php echo getPaginationUrl($pagina_atual + 1); ?>"
                                    aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                 </a>
                              </li>
                           <?php else: ?>
                              <li class="page-item disabled">
                                 <a class="page-link" href="#" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                 </a>
                              </li>
                           <?php endif; ?>
                        </ul>
                     </nav>
                  </div>
               </div>
            <?php endif; ?>
         </div>
      </div>
   </div>

   <div id="filtrosidenav" class="filterside">
      <a href="javascript:void(0)" class="closefilter" onclick="closeFilter()">&times;</a>

      <div class="filter-header">
         <h1 class="filter-title">Filtrar Produtos</h1>
         <div class="filter-subtitle">Ajuste os filtros para encontrar o que procura</div>
      </div>

      <div class="filter-section">
         <h5 class="filter-section-title">
            <i class="bi bi-tags"></i> Categorias
            <i class="bi bi-chevron-down toggle-arrow"></i>
         </h5>
         <div class="filter-section-content">
            <div class="filter-options">
               <a href="roupa.php?tipo=0<?php
               echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : '';
               echo isset($_GET['ordenacao']) ? '&ordenacao=' . htmlspecialchars($_GET['ordenacao']) : '';
               ?>" class="filter-option <?php echo $tipo == 0 ? 'active' : '' ?>">
                  <i class="bi bi-chevron-right"></i> Tudo
               </a>
               <?php
               $sqlCategories = "SELECT ID, CATEGORIA FROM PAP_CATEGORIA_ROUPA";
               $resultCategories = $connection->query($sqlCategories);
               if ($resultCategories->num_rows > 0) {
                  while ($category = $resultCategories->fetch_assoc()) {
                     $url = 'roupa.php?tipo=' . $category['ID'];

                     // Manter outros parâmetros existentes
                     $params = $_GET;
                     unset($params['tipo']); // Removemos o tipo atual pois será substituído
               
                     foreach ($params as $key => $value) {
                        if (is_array($value)) {
                           foreach ($value as $val) {
                              $url .= '&' . urlencode($key . '[]') . '=' . urlencode($val);
                           }
                        } else {
                           $url .= '&' . urlencode($key) . '=' . urlencode($value);
                        }
                     }

                     echo '<a href="' . htmlspecialchars($url) . '" class="filter-option ' . ($tipo == $category['ID'] ? 'active' : '') . '">';
                     echo '<i class="bi bi-chevron-right"></i> ' . htmlspecialchars($category['CATEGORIA']);
                     echo '</a>';
                  }
               }
               ?>
            </div>
         </div>
      </div>

      <div class="filter-section">
         <h5 class="filter-section-title">
            <i class="bi bi-shop"></i> Marcas
            <i class="bi bi-chevron-down toggle-arrow"></i>
         </h5>
         <div class="filter-section-content">
            <div class="filter-options">
               <?php
               $sqlBrands = "SELECT ID, MARCA FROM PAP_MARCA";
               $resultBrands = $connection->query($sqlBrands);
               if ($resultBrands->num_rows > 0) {
                  while ($brand = $resultBrands->fetch_assoc()) {
                     echo '<div class="form-check filter-checkbox">';
                     echo '<input class="form-check-input" type="checkbox" id="brand-' . $brand['ID'] . '" name="brand" value="' . $brand['ID'] . '">';
                     echo '<label class="form-check-label" for="brand-' . $brand['ID'] . '">' . $brand['MARCA'] . '</label>';
                     echo '</div>';
                  }
               }
               ?>
            </div>
         </div>
      </div>

      <div class="filter-section">
         <h5 class="filter-section-title">
            <i class="bi bi-grid"></i> Materiais
            <i class="bi bi-chevron-down toggle-arrow"></i>
         </h5>
         <div class="filter-section-content">
            <div class="filter-options">
               <?php
               $sqlMaterials = "SELECT ID, NOME FROM PAP_MATERIAIS_ROUPA";
               $resultMaterials = $connection->query($sqlMaterials);
               if ($resultMaterials->num_rows > 0) {
                  while ($material = $resultMaterials->fetch_assoc()) {
                     echo '<div class="form-check filter-checkbox">';
                     echo '<input class="form-check-input" type="checkbox" id="material-' . $material['ID'] . '" name="material" value="' . $material['ID'] . '">';
                     echo '<label class="form-check-label" for="material-' . $material['ID'] . '">' . $material['NOME'] . '</label>';
                     echo '</div>';
                  }
               }
               ?>
            </div>
         </div>
      </div>

      <div class="filter-section">
         <h5 class="filter-section-title">
            <i class="bi bi-palette"></i> Cores
            <i class="bi bi-chevron-down toggle-arrow"></i>
         </h5>
         <div class="filter-section-content">
            <div class="filter-colors">
               <?php
               $sqlColors = "SELECT ID, COR, COR_CSS FROM PAP_CORES";
               $resultColors = $connection->query($sqlColors);
               if ($resultColors->num_rows > 0) {
                  while ($color = $resultColors->fetch_assoc()) {
                     echo '<div class="color-container">';
                     echo '<div class="color-option" data-color="' . $color['ID'] . '">';
                     if ($color['COR'] == 'Outra') {
                        echo '<div class="color-circle" style="background: ' . $color['COR_CSS'] . '; border: 2px solid #ccc;"></div>';
                     } else {
                        echo '<div class="color-circle" style="background-color: ' . strtolower($color['COR_CSS']) . '"></div>';
                     }
                     echo '</div>';
                     echo '<div class="color-name">' . $color['COR'] . '</div>';
                     echo '</div>';
                  }
               }
               ?>
            </div>
         </div>
         <div class="filter-section">
            <h5 class="filter-section-title">
               <i class="bi bi-currency-euro"></i> Preço
               <i class="bi bi-chevron-down toggle-arrow"></i>
            </h5>
            <div class="filter-section-content">
               <div class="mb-3">
                  <label class="form-label">Ordenar por:</label>
                  <select class="form-select" name="ordenacao" id="ordenacao">
                     <option value="recomendados" <?php echo $ordenacao === 'recomendados' ? 'selected' : ''; ?>>
                        Recomendados</option>
                     <option value="preco_asc" <?php echo $ordenacao === 'preco_asc' ? 'selected' : ''; ?>>Preço: menor
                        para maior</option>
                     <option value="preco_desc" <?php echo $ordenacao === 'preco_desc' ? 'selected' : ''; ?>>Preço: maior
                        para menor</option>
                     <option value="mais_novo" <?php echo $ordenacao === 'mais_novo' ? 'selected' : ''; ?>>Mais novos
                     </option>
                     <option value="mais_antigo" <?php echo $ordenacao === 'mais_antigo' ? 'selected' : ''; ?>>Mais
                        antigos</option>
                  </select>
               </div>
               <div class="mb-3">
                  <label class="form-label">Preço mínimo (€)</label>
                  <input type="number" class="form-control" name="preco_min" id="preco_min" min="0" step="0.01"
                     value="<?php echo $preco_min !== null ? htmlspecialchars($preco_min) : ''; ?>">
               </div>
               <div class="mb-3">
                  <label class="form-label">Preço máximo (€)</label>
                  <input type="number" class="form-control" name="preco_max" id="preco_max" min="0" step="0.01"
                     value="<?php echo $preco_max !== null ? htmlspecialchars($preco_max) : ''; ?>">
               </div>
            </div>
         </div>
         <div class="filter-section">
            <h5 class="filter-section-title">
               <i class="bi bi-box-seam"></i> Disponibilidade
               <i class="bi bi-chevron-down toggle-arrow"></i>
            </h5>
            <div class="filter-section-content">
               <div class="form-check filter-checkbox">
                  <input class="form-check-input" type="checkbox" id="com_stock" name="com_stock" value="1" <?php echo $com_stock ? 'checked' : '' ?>>
                  <label class="form-check-label" for="com_stock">Mostrar apenas artigos com stock</label>
               </div>
            </div>
         </div>
      </div>

      <div class="filter-actions">
         <button class="btn btn-clear" onclick="clearFilters()">
            <i class="bi bi-x-circle"></i> Limpar Filtros
         </button>
         <button class="btn btn-apply" onclick="applyFilters()">
            <i class="bi bi-check-circle"></i> Aplicar Filtros
         </button>
      </div>
   </div>

   <?php include "footer.php"; ?>

   <script src="js/jquery.min.js"></script>
   <script src="js/popper.min.js"></script>
   <script src="js/bootstrap.bundle.min.js"></script>
   <script src="js/jquery-3.0.0.min.js"></script>
   <script>
      $(document).ready(function () {
         document.querySelectorAll('.formwish').forEach(form => {
            form.addEventListener('submit', function (e) {
               e.preventDefault();
               const formData = new FormData(this);
               const button = this.querySelector('button');
               const icon = this.querySelector('i');

               fetch(window.location.href, {
                  method: 'POST',
                  body: formData
               })
                  .then(response => response.json())
                  .then(data => {
                     if (data.redirect) {
                        window.location.href = data.redirect;
                     } else if (data.success) {
                        // Atualiza o ícone
                        icon.className = 'bi ' + data.newIconClass;

                        // Atualiza a ação do formulário
                        this.querySelector('input[name="action"]').value = data.action;

                        // Efeito visual
                        icon.classList.add('pulse');
                        setTimeout(() => icon.classList.remove('pulse'), 300);
                     }
                  })
                  .catch(error => console.error('Erro:', error));
            });
         });
      });
      document.addEventListener("DOMContentLoaded", function () {
         const sizeButtons = document.querySelectorAll('.size-btn');
         const sizeInput = document.getElementById('selectedTamanho');
         const cartButton = document.getElementById('cartButton');
         const verMaisBtn = document.getElementById("verMaisBtn");
         const productDescription = document.getElementById("productDescription");
         const arrowIcon = verMaisBtn.querySelector('i');
         document.getElementById("filtrosidenav").style.width = "0";

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
               if (cartButton) {
                  cartButton.disabled = false;
                  cartButton.classList.remove('btn-cart-disabled');
                  cartButton.classList.add('btn-cart-enabled');
               }
            });
         });
         clearFilters();
      });

      function getPaginationUrl(page) {
         const urlParams = new URLSearchParams(window.location.search);
         urlParams.set('pagina', page);
         return 'roupa.php?' + urlParams.toString();
      }

      function clearFilters() {
         // Limpar seleções na interface
         document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
         });

         document.querySelectorAll('.color-option').forEach(color => {
            color.classList.remove('selected');
            const container = color.closest('.color-container');
            const nameElement = container.querySelector('.color-name');
            nameElement.style.fontWeight = 'normal';
            nameElement.style.color = '#495057';
         });

         // Limpar filtros de preço
         document.getElementById('ordenacao').value = 'recomendados';
         document.getElementById('preco_min').value = '';
         document.getElementById('preco_max').value = '';

         // Obter parâmetros atuais da URL
         const urlParams = new URLSearchParams(window.location.search);
         const currentTipo = urlParams.get('tipo') || '0';
         const currentSearch = urlParams.get('search') || '';

         // Construir a URL base mantendo apenas tipo e search
         let url = 'roupa.php?tipo=0';

         window.location.href = url;
      }


      document.querySelectorAll('.color-option').forEach(colorOption => {
         colorOption.addEventListener('click', function () {
            // Alterna a classe 'selected' no elemento clicado
            this.classList.toggle('selected');

            // Atualiza o estilo do nome da cor
            const container = this.closest('.color-container');
            const nameElement = container.querySelector('.color-name');

            if (this.classList.contains('selected')) {
               nameElement.style.fontWeight = 'bold';
               nameElement.style.color = '#3498db';
            } else {
               nameElement.style.fontWeight = 'normal';
               nameElement.style.color = '#495057';
            }
         });
      });

      function applyFilters() {
         const urlParams = new URLSearchParams();

         // Manter o tipo de categoria
         const currentTipo = new URLSearchParams(window.location.search).get('tipo') || '0';
         urlParams.append('tipo', currentTipo);

         // Manter a busca se existir
         const currentSearch = document.querySelector('input[name="search"]').value;
         if (currentSearch) {
            urlParams.append('search', currentSearch);
         }

         // Cores selecionadas
         document.querySelectorAll('.color-option.selected').forEach(color => {
            urlParams.append('cores[]', color.dataset.color);
         });

         // Marcas selecionadas
         document.querySelectorAll('input[name="brand"]:checked').forEach(brand => {
            urlParams.append('marcas[]', brand.value);
         });

         // Materiais selecionados
         document.querySelectorAll('input[name="material"]:checked').forEach(material => {
            urlParams.append('materiais[]', material.value);
         });

         // Ordenação
         urlParams.append('ordenacao', document.getElementById('ordenacao').value);

         // Preços
         const precoMin = document.getElementById('preco_min').value;
         const precoMax = document.getElementById('preco_max').value;

         if (precoMin) urlParams.append('preco_min', precoMin);
         if (precoMax) urlParams.append('preco_max', precoMax);

         const comStock = document.getElementById('com_stock').checked;
         if (comStock) {
            urlParams.append('com_stock', '1');
         }

         // Redirecionar com todos os parâmetros
         window.location.href = 'roupa.php?' + urlParams.toString();
      }

      document.addEventListener('DOMContentLoaded', function () {
         const urlParams = new URLSearchParams(window.location.search);

         // Manter cores selecionadas
         const cores = urlParams.getAll('cores[]');
         cores.forEach(corId => {
            const colorOption = document.querySelector(`.color-option[data-color="${corId}"]`);
            if (colorOption) {
               colorOption.classList.add('selected');
               const container = colorOption.closest('.color-container');
               const nameElement = container.querySelector('.color-name');
               nameElement.style.fontWeight = 'bold';
               nameElement.style.color = '#3498db';
            }
         });

         // Manter marcas selecionadas
         const marcas = urlParams.getAll('marcas[]');
         marcas.forEach(marcaId => {
            const checkbox = document.getElementById(`brand-${marcaId}`);
            if (checkbox) checkbox.checked = true;
         });

         // Manter materiais selecionados
         const materiais = urlParams.getAll('materiais[]');
         materiais.forEach(materialId => {
            const checkbox = document.getElementById(`material-${materialId}`);
            if (checkbox) checkbox.checked = true;
         });
      });

      function openFilter() {
         document.getElementById("filtrosidenav").style.width = window.innerWidth <= 767 ? "70%" : "350px";
      }

      function closeFilter() {
         document.getElementById("filtrosidenav").style.width = "0";
      }

      document.querySelector('.bi-filter').addEventListener('click', openFilter);
      document.querySelector('.closefilter').addEventListener('click', closeFilter);

      document.addEventListener('click', function (event) {
         const filterSide = document.getElementById("filtrosidenav");
         const filterBtn = document.querySelector(".bi-filter");

         if (!filterSide.contains(event.target) && event.target !== filterBtn) {
            closeFilter();
         }
      });

      window.addEventListener('resize', function () {
         const filterSide = document.getElementById("filtrosidenav");
         if (window.innerWidth <= 767 && parseInt(filterSide.style.width) > 0) {
            filterSide.style.width = "70%";
         } else if (window.innerWidth > 767 && parseInt(filterSide.style.width) > 0) {
            filterSide.style.width = "350px";
         }
      });
      document.querySelectorAll('.filter-section-title').forEach(title => {
         title.addEventListener('click', function () {
            const content = this.nextElementSibling;
            this.classList.toggle('collapsed');
            content.classList.toggle('collapsed');

            // Atualiza o ícone da seta
            const arrow = this.querySelector('.toggle-arrow');
            if (this.classList.contains('collapsed')) {
               arrow.classList.remove('bi-chevron-down');
               arrow.classList.add('bi-chevron-right');
            } else {
               arrow.classList.remove('bi-chevron-right');
               arrow.classList.add('bi-chevron-down');
            }
         });
      });

      // Opcional: Adicione localStorage para lembrar o estado dos filtros
      document.addEventListener('DOMContentLoaded', function () {
         document.querySelectorAll('.filter-section').forEach((section, index) => {
            const title = section.querySelector('.filter-section-title');
            const content = section.querySelector('.filter-section-content');
            const arrow = title.querySelector('.toggle-arrow');

            // Verifica o estado salvo (opcional)
            const isCollapsed = localStorage.getItem(`filter-section-${index}`) === 'true';

            if (isCollapsed) {
               title.classList.add('collapsed');
               content.classList.add('collapsed');
               arrow.classList.remove('bi-chevron-down');
               arrow.classList.add('bi-chevron-right');
            }

            // Atualiza o evento de clique para salvar o estado
            title.addEventListener('click', function () {
               const isNowCollapsed = this.classList.contains('collapsed');
               localStorage.setItem(`filter-section-${index}`, isNowCollapsed);
            });
         });
      });
   </script>
   <script src="js/plugin.js"></script>
   <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
   <script src="js/custom.js"></script>
   <script src="js/owl.carousel.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
   <script src="https://unpkg.com/gijgo@1.9.13/js/gijgo.min.js" type="text/javascript"></script>
</body>

</html>