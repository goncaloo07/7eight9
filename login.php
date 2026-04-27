<?php
require "connection.php";
require "sqlconnection.php";
require_once "core.php";
if (session_status() === PHP_SESSION_NONE) {
      session_start();
}
if (!isset($_SESSION["user"])) {
      $mensagem = "";
      $tipoMensagem = "";

      if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (!isset($_POST['email'], $_POST['pass'])) {
                  $mensagem = "Erro: Todos os campos são obrigatórios.";
                  $tipoMensagem = "danger";
            } else {
                  $email = trim($_POST['email']);
                  $pass = $_POST['pass'];

                  // Prepara a query para evitar SQL Injection
                  $stmt = $conn->prepare("SELECT * FROM PAP_CLIENTE WHERE EMAIL = ?");
                  $stmt->bind_param("s", $email);
                  $stmt->execute();
                  $result = $stmt->get_result();

                  // Verifica se o usuário existe
                  if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();

                        // Verifica a senha
                        if (password_verify($pass, $row['PASS'])) {
                              session_regenerate_id(true);
                              $_SESSION['id'] = $row['ID'];
                              $_SESSION['user'] = $row['NOME'];
                              $_SESSION['email'] = $email;
                              $_SESSION['nivel'] = $row['NIVEL'];
                              header("Location: index.php");
                        } else {
                              $mensagem = "Palavra-passe incorreta.";
                              $tipoMensagem = "danger";
                        }
                  } else {
                        $mensagem = "Utilizador não encontrado. <a href='login.php' class='text-decoration-none'>Crie conta aqui</a>.";
                        $tipoMensagem = "warning";
                  }
            }
      }
      ?>
      <!DOCTYPE html>
      <html lang="pt">

      <head>
            <!-- basic -->
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <!-- mobile metas -->
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="viewport" content="initial-scale=1, maximum-scale=1">
            <!-- site metas -->
            <title>7eight9</title>
            <meta name="keywords" content="7eight9">
            <meta name="description" content="Website de roupa chamado 7eight9">
            <meta name="author" content="Gonçalo Pinto">
            <!-- bootstrap css -->
            <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
            <!-- style css -->
            <link rel="stylesheet" type="text/css" href="css/register.css">
            <!-- Responsive-->
            <link rel="stylesheet" href="css/responsive.css">
            <!-- fevicon -->
            <link rel="icon" href="https://alpha.soaresbasto.pt/~a25385/PAP/img/logo.png" type="image/gif" />
            <!-- Scrollbar Custom CSS -->
            <link rel="stylesheet" href="css/jquery.mCustomScrollbar.min.css">
            <!-- Tweaks for older IEs-->
            <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css">
            <!-- fonts -->
            <link href="https://fonts.googleapis.com/css?family=Great+Vibes|Open+Sans:400,700&display=swap&subset=latin-ext"
                  rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.css"
                  media="screen">
            <link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css" />
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                  /* Estilização geral */
                  body {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        background: linear-gradient(#C5C5C5, rgb(110, 110, 110), rgb(44, 44, 44));
                        font-family: 'Open Sans', sans-serif;
                  }

                  /* Container do formulário */
                  .form-container {
                        background: #fff;
                        padding: 30px;
                        border-radius: 12px;
                        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
                        width: 400px;
                        text-align: center;
                  }

                  .form-container h2 {
                        margin-bottom: 20px;
                        color: #444;
                        font-weight: 500;
                  }

                  /* Estilo dos inputs */
                  .input-group {
                        position: relative;
                        margin-bottom: 20px;
                  }

                  .input-group input {
                        width: 100%;
                        padding: 12px 12px 12px 40px;
                        border: 1px solid #ccc;
                        border-radius: 8px;
                        outline: none;
                        font-size: 16px;
                        transition: border 0.3s ease, box-shadow 0.3s ease;
                  }

                  .input-group input:focus {
                        border-color: #444;
                        box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
                  }

                  .input-group i {
                        position: absolute;
                        left: 12px;
                        top: 50%;
                        transform: translateY(-50%);
                        color: #888;
                        font-size: 20px;
                  }

                  /* Botão de login */
                  .btn-register {
                        background: #444;
                        color: #fff;
                        border: none;
                        padding: 14px;
                        width: 100%;
                        border-radius: 8px;
                        font-size: 16px;
                        transition: background 0.3s ease, transform 0.3s ease;
                        cursor: pointer;
                  }

                  .btn-register:hover {
                        background: #333;
                        transform: translateY(-2px);
                  }

                  /* Link de registro e navegação */
                  .login-link {
                        margin-top: 15px;
                        font-size: 14px;
                  }

                  .login-link a {
                        color: rgb(36, 36, 36);
                        text-decoration: none;
                        font-weight: bold;
                        transition: color 0.3s ease;
                  }

                  .login-link a:hover {
                        color: #444;
                  }

                  .alert {
                        margin-top: 15px;
                  }

                  .logoimage {
                        max-height: 80px;
                        margin-bottom: 20px;
                  }
            </style>
      </head>

      <body>

            <div class="form-container">
                  <img src="https://alpha.soaresbasto.pt/~a25385/PAP/img/logo.png" class="logoimage" alt="Logo">
                  <h2>Login</h2>

                  <?php
                  if (!empty($mensagem)) {
                        echo "<div class='alert alert-$tipoMensagem'>$mensagem</div>";
                  }
                  ?>

                  <form action="login.php" method="post">
                        <div class="input-group">
                              <i class="bi bi-at"></i>
                              <input type="text" id="email" name="email" placeholder="Email" required>
                        </div>

                        <div class="input-group">
                              <i class="bi bi-lock"></i>
                              <input type="password" id="pass" name="pass" placeholder="Palavra-passe" required>
                        </div>

                        <button type="submit" class="btn-register">Login</button>
                  </form>

                  <p class="login-link">Ainda não tem conta? <a href="register.php">Crie conta aqui</a></p>
                  <p class="login-link"><a href="index.php">Voltar á pagina inicial</a></p>
            </div>

            <!-- Javascript files-->
            <script src="js/jquery.min.js"></script>
            <script src="js/popper.min.js"></script>
            <script src="js/bootstrap.bundle.min.js"></script>
            <script src="js/jquery-3.0.0.min.js"></script>
            <script src="js/plugin.js"></script>
            <!-- sidebar -->
            <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
            <script src="js/custom.js"></script>
            <!-- javascript -->
            <script src="js/owl.carousel.js"></script>
            <script src="https:cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
            <script src="https://unpkg.com/gijgo@1.9.13/js/gijgo.min.js" type="text/javascript"></script>
            <script>
                  function openNav() {
                        document.getElementById("mySidenav").style.width = "100%";
                  }

                  function closeNav() {
                        document.getElementById("mySidenav").style.width = "0";
                  }
            </script>
      </body>

      </html>
      <?php
} else {
      header("Location: index.php");
      exit();
}
$stmt->close();
$conn->close();