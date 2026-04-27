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
      // Recupera os valores já preenchidos
      $new_username = htmlspecialchars(trim($_POST['new_username']));
      $new_email = htmlspecialchars(trim($_POST['new_email']));
      $new_tele = preg_replace('/\D/', '', $_POST['new_tele']);
      $new_morada = htmlspecialchars(trim($_POST['new_morada']));

      // Verificar se todos os campos foram preenchidos
      if (empty($new_username) || empty($_POST['new_password']) || empty($_POST['new_password2']) || empty($new_email) || empty($new_tele) || empty($new_morada)) {
         $mensagem = "Erro: Todos os campos são obrigatórios.";
         $tipoMensagem = "danger";
      } elseif ($_POST['new_password'] !== $_POST['new_password2']) {
         $mensagem = "As palavras-passe não coincidem.";
         $tipoMensagem = "danger";
      } elseif (strlen($_POST['new_password']) < 8) {
         $mensagem = "A palavra-passe deve ter pelo menos 8 caracteres.";
         $tipoMensagem = "warning";
      } elseif (strlen($new_tele) !== 9) {
         $mensagem = "O número de telemóvel deve conter exatamente 9 dígitos.";
         $tipoMensagem = "warning";
      } else {
         // Hash da senha
         $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

         // Verificar se o email já existe
         $sql1 = "SELECT * FROM PAP_CLIENTE WHERE EMAIL=?";
         $stmt1 = $conn->prepare($sql1);
         $stmt1->bind_param("s", $new_email);
         $stmt1->execute();
         $result = $stmt1->get_result();
         $stmt1->close();

         if ($result->num_rows > 0) {
            $mensagem = "O email já está em uso. <a href='login.php' class='text-decoration-none'>Faça login aqui</a>.";
            $tipoMensagem = "warning";
         } else {
            // Inserir novo usuário
            $sql = "INSERT INTO PAP_CLIENTE (NOME, EMAIL, PASS, MORADA, NUMTELE) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
               $stmt->bind_param("sssss", $new_username, $new_email, $new_password, $new_morada, $new_tele);
               if ($stmt->execute()) {
                  $_SESSION['id'] = $conn->insert_id;
                  $_SESSION['user'] = $new_username;
                  $_SESSION['nivel'] = 1;
                  $_SESSION['email'] = $new_email;
                  header("Location: index.php");
                  exit();
               } else {
                  $mensagem = "Erro ao registrar: " . htmlspecialchars($stmt->error);
                  $tipoMensagem = "danger";
               }
               $stmt->close();
            }
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
      <title>Criar Conta</title>
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
            font-family: 'Open Sans', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(#C5C5C5, rgb(110, 110, 110), rgb(44, 44, 44));
            margin: 0;
         }

         /* Container do formulário */
         .form-container {
            background: #fff;
            padding: 20px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            max-width: 500px;
            width: 60%;
            text-align: center;
         }

         .form-container h2 {
            margin-bottom: 20px;
            color: #333;
            font-weight: 600;
            letter-spacing: 0.5px;
         }

         /* Container para agrupar os inputs */
         .form-fields {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
         }

         /* Cada input-group ficará com 48% de largura (horizontal) */
         .form-fields .input-group {
            width: calc(50% - 10px);
            margin-bottom: 20px;
            position: relative;
         }

         /* Estilo dos inputs */
         .input-group input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
            font-size: 16px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
         }

         .input-group input:focus {
            border-color: #666;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
         }

         .input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 20px;
         }

         /* Botão de registo */
         .btn-register {
            background: rgb(66, 66, 66);
            color: #fff;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 8px;
            font-size: 16px;
            transition: background-color 0.3s ease, transform 0.3s ease;
            cursor: pointer;
            margin-bottom: 20px;
         }

         .btn-register:hover {
            background: rgb(92, 92, 92);
            transform: translateY(-2px);
         }

         /* Link de login */
         .login-link {
            font-size: 14px;
         }

         .login-link a {
            color: rgb(36, 36, 36);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
         }

         .login-link a:hover {
            color: rgb(66, 66, 66);
         }

         .alert {
            margin-top: 15px;
         }

         .logoimage {
            max-height: 80px;
            margin-bottom: 20px;
         }

         /* Para ecrãs pequenos, os inputs ocupam 100% */
         @media screen and (max-width: 768px) {
            .form-fields .input-group {
               width: 100%;
            }

            .form-container {
               padding: 10px 20px;
               margin: 5vh 0;
               width: auto;
            }
         }

         @media screen and (max-width: 520px) {
            .form-container {
               width: 90%;
            }
         }
      </style>

   </head>

   <body>
      <div class="form-container">
         <img src="https://alpha.soaresbasto.pt/~a25385/PAP/img/logo.png" class="logoimage" alt="Logo">
         <h2>Criar Conta</h2>

         <?php
         if (!empty($mensagem)) {
            echo "<div class='alert alert-$tipoMensagem'>$mensagem</div>";
         }
         ?>

         <form method="POST">
            <div class="form-fields">
               <div class="input-group">
                  <i class="bi bi-person"></i>
                  <input type="text" id="new_username" name="new_username" placeholder="Nome de Utilizador" required>
               </div>

               <div class="input-group">
                  <i class="bi bi-at"></i>
                  <input type="email" id="new_email" name="new_email" placeholder="Email" required>
               </div>

               <div class="input-group">
                  <i class="bi bi-telephone"></i>
                  <input type="tel" id="new_tele" name="new_tele" placeholder="Número de telemóvel" required>
               </div>

               <div class="input-group">
                  <i class="bi bi-house"></i>
                  <input type="text" id="new_morada" name="new_morada" placeholder="Morada" required>
               </div>

               <div class="input-group">
                  <i class="bi bi-lock"></i>
                  <input type="password" id="new_password" name="new_password" placeholder="Palavra-passe" required>
               </div>

               <div class="input-group">
                  <i class="bi bi-lock"></i>
                  <input type="password" id="new_password2" name="new_password2" placeholder="Repita a palavra-passe"
                     required>
               </div>
            </div>

            <button type="submit" class="btn-register">Criar Conta</button>
         </form>

         <p class="login-link">Já tem uma conta? <a href="login.php">Faça login aqui</a></p>
         <p class="login-link"><a href="index.php">Voltar à página inicial</a></p>
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
         document.querySelector("form").addEventListener("submit", function (e) {
            const teleField = document.getElementById("new_tele");
            const digitsOnly = teleField.value.replace(/\D/g, "");

            if (digitsOnly.length !== 9) {
               e.preventDefault(); // Impede o envio do formulário
               alert("O número de telemóvel deve conter exatamente 9 dígitos.");
               teleField.focus();
            }
         });

         // Formatação ao digitar
         document.getElementById('new_tele').addEventListener('input', function (e) {
            let value = this.value.replace(/\D/g, '').substring(0, 9);
            value = value.replace(/(\d{3})(?=\d)/g, '$1 ');
            this.value = value;
         });
      </script>
   </body>

   </html>
   <?php
} else {
   header("Location: index.php");
   exit();
}
$conn->close();
?>