<?php
$connectionheader = db_connect();
$sqlheader = "SELECT * FROM PAP_CATEGORIA_ROUPA;";
$categoria_roupa = $connectionheader->query($sqlheader);
?>
<div class="header_section">
  <div class="container-fluid">
    <nav class="navbar">
      <!-- Sidenav Overlay -->
      <div id="mySidenav" class="sidenav">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <a href="index.php" class="sidenav-link">Página Inicial</a>
        <a href="javascript:void(0)" onclick="toggleCategories()" class="sidenav-link">Produtos</a>
        <div id="categoryList" class="category-links">
          <a href="roupa.php?tipo=0" class="category-link">Tudo</a>
          <?php
          while ($rowhead = mysqli_fetch_array($categoria_roupa)) {
            $cr_id = $rowhead['ID'];
            $cr_cat = $rowhead['CATEGORIA'];
            ?>
            <a href="roupa.php?tipo=<?php echo $cr_id ?>" class="category-link"><?php echo $cr_cat ?></a>
          <?php } ?>
        </div>
        <a href="about.php" class="sidenav-link">Sobre Nós</a>
      </div>

      <!-- Header Inner: Menu, Logo e Login -->
      <div class="header_inner">
        <!-- Menu à Esquerda -->
        <div class="menu_icon">
          <span class="toggle_icon" onclick="openNav()">
            <i class="bi bi-list"></i>
          </span>
        </div>
        <!-- Logo no Centro -->
        <div class="logo_wrapper">
          <a class="logo" href="index.php">
            <img src="https://alpha.soaresbasto.pt/~a25385/PAP/img/logo.png" class="logoimage" alt="Logo">
          </a>
        </div>
        <div class="login_icons">
          <?php if (isset($_SESSION["user"])): ?>
            <div class="account-dropdown">
              <i class="bi bi-person-fill perfilimg" onclick="toggleAccountDropdown()"></i>
              <div id="accountDropdown" class="dropdown-content">
                <?php
                if ($_SESSION["nivel"] == 2) {
                  echo '<a href="admin.php" class="ferramenta-admin">Ferramentas de Admin</a><hr class="hr-admin"/>';
                }
                ?>
                <a href="editaccount.php">Minha Conta</a>
                <a href="wishlist.php">Minha Lista de Desejos</a>
                <a href="carrinho.php">Meu Carrinho</a>
                <a href="compras.php">Minhas Compras</a>
                <a href="about.php">Sobre Nós</a>
                <a href="logout.php">Sair</a>
              </div>
            </div>
            <a href="carrinho.php">
              <i class="bi bi-cart perfilimg"></i>
            </a>
            <a href="wishlist.php">
              <i class="bi bi-heart perfilimg wishbtn"></i>
            </a>
          <?php else: ?>
            <a href="login.php">
              <i class="bi bi-person-fill perfilimg"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </nav>
  </div>
</div>

<style>
  /* Evita overflow horizontal */
  html,
  body {
    overflow-x: hidden;
  }

  /* Header Section */
  .header_section {
    border-bottom: 1px solid rgba(161, 161, 161, 0.5);
    padding: 0 10px;
    margin-bottom: 0;
  }

  /* Header Inner com layout em 3 colunas */
  .header_inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: nowrap;
  }

  /* Alinhamento dos itens */
  .menu_icon {
    text-align: left;
    width: 30%;
  }

  .logo_wrapper {
    text-align: center;
    width: 40%;
  }

  .login_icons {
    text-align: right;
    width: 30%;
    position: relative;
  }

  /* Sidenav Overlay */
  .sidenav {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    height: 100vh;
    width: 0;
    position: fixed;
    top: 0;
    left: 0;
    background-color: rgba(0, 0, 0, 0.9);
    overflow-x: hidden;
    transition: width 0.5s ease;
    z-index: 9999;
    padding-top: 60px;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.5);
  }

  .sidenav a:hover {
    color: #c3c3c3;
  }

  .sidenav-link {
    font-size: clamp(1.2rem, 3vw, 1.8rem);
    color: #fff;
    text-decoration: none;
    padding: 12px 20px;
    width: 100%;
    transition: background 0.3s ease;
  }

  .sidenav-link:hover {
    background-color: #444;
  }

  .closebtn {
    font-size: 2.5rem;
    position: absolute;
    top: 20px;
    right: 30px;
    color: #fff;
    text-decoration: none;
  }

  /* Ícone do Menu */
  .toggle_icon {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
  }

  .toggle_icon i {
    font-size: 3rem;
    transition: transform 0.3s ease;
  }

  .toggle_icon:hover i {
    transform: scale(1.05);
  }

  /* Ícones de Login */
  .perfilimg {
    font-size: 2.5rem;
    color: black;
    margin-left: 15px;
    cursor: pointer;
  }

  .wishbtn {
    font-size: 2.4rem;
  }

  /* Logo */
  .logoimage {
    max-width: 100%;
    height: auto;
    object-fit: contain;
    height: 18vh;
    min-height: 50px;
    max-height: 500px;
  }

  .navbar {
    display: block;
    padding: 0 1rem;
  }

  .container-fluid {
    padding: 5px 15px;
  }

  .logoimage:hover {
    transform: scale(1.05);
  }

  /* Menu de Categorias */
  .category-links {
    width: 90%;
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    display: flex;
    flex-direction: column;
    transition: max-height 0.5s ease-out, opacity 0.5s ease-out, margin 0.5s ease-out;
    border-top: 1px solid #444;
    border-bottom: 1px solid #444;
  }

  .category-links.show {
    max-height: 500px;
    opacity: 1;
    margin: 10px 0;
  }

  .category-link {
    font-size: 1.4rem;
    color: #ccc;
    text-decoration: none;
    padding: 8px 0;
    transition: color 0.3s ease-in-out;
  }

  .category-link:hover {
    color: #444;
  }

  /* Dropdown de Conta */
  .account-dropdown {
    position: relative;
    display: inline-block;
  }

  .dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    background-color: #fff;
    min-width: 150px;
    box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
    z-index: 1000;
  }

  .dropdown-content a {
    color: #333;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
  }

  .dropdown-content a:hover {
    background-color: #f1f1f1;
  }

  .hr-admin {
    margin: 0 10px;
    border-top: 1px solid rgba(161, 161, 161, 0.8);
  }

  /* Responsividade para telas pequenas */
  @media screen and (max-width: 768px) {
    .sidenav-link {
      font-size: 1.5rem;
      padding: 10px 15px;
    }

    .header_inner {
      flex-wrap: nowrap;
    }

    .menu_icon,
    .logo_wrapper,
    .login_icons {
      margin: 5px 0;
    }

    .perfilimg {
      font-size: 2rem;
      margin-left: 10px;
    }

    .wishbtn {
      font-size: 1.9rem;
    }

    .logoimage {
      max-width: 150px;
      width: 80%;
    }

    .container-fluid {
      padding: 0 5px;
    }

    .toggle_icon i {
      font-size: 2.5rem;
    }
  }

  @media screen and (max-width: 500px) {
    .login_icons {
      display: flex;
      flex-direction: column;
      width: 10%;
    }

    .menu_icon {
      width: 10%;
    }

    .logo_wrapper {
      width: 80%;
    }
  }

  @media (max-width: 480px) {
    .sidenav-link {
      font-size: 1.2rem;
    }

    .closebtn {
      right: 20px;
      top: 15px;
    }
  }
</style>

<!-- JavaScript para Controle do Sidenav e do Dropdown da Conta -->
<script>
  function openNav() {
    document.getElementById("mySidenav").style.width = "100%";
  }
  function closeNav() {
    document.getElementById("mySidenav").style.width = "0";
  }
  function toggleCategories() {
    var categoryList = document.getElementById("categoryList");
    categoryList.classList.toggle("show");
  }
  function toggleAccountDropdown() {
    var dropdown = document.getElementById("accountDropdown");
    // Alterna entre exibir e esconder o dropdown
    if (dropdown.style.display === "block") {
      dropdown.style.display = "none";
    } else {
      dropdown.style.display = "block";
    }
  }
  // Opcional: fechar o dropdown ao clicar fora
  window.onclick = function (event) {
    if (!event.target.matches('.perfilimg')) {
      var dropdown = document.getElementById("accountDropdown");
      if (dropdown && dropdown.style.display === "block") {
        dropdown.style.display = "none";
      }
    }
  }
</script>