<?php
require "connection.php";
if (session_status() === PHP_SESSION_NONE) {
   session_start();
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
   <title>Sobre Nós</title>
   <meta name="keywords" content="">
   <meta name="description" content="">
   <meta name="author" content="">
   <!-- Bootstrap CSS -->
   <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
   <!-- Outras referências (mantidas para compatibilidade) -->
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
   <!-- Estilos customizados inseridos diretamente -->
   <style>
      /* Layout Geral */
      body {
         background: rgb(230, 230, 230);
         margin: 0;
         padding: 0;
         font-family: 'Open Sans', sans-serif;
      }

      .about_section_main {
         max-width: 1200px;
         margin: auto;
         display: flex;
         align-items: center;
         justify-content: space-between;
         flex-wrap: wrap;
      }

      .about_section{
         padding-bottom: 1rem;
      }

      .about_taital_main {
         padding: 20px;
      }

      .about_taital {
         font-size: 36px;
         color: #333;
         margin-bottom: 20px;
      }

      .about_text {
         font-size: 16px;
         line-height: 1.8;
         color: #555;
      }

      .imgdiv {
         text-align: center;
         padding: 20px;
      }

      .imgdiv img {
         width: 100%;
         max-width: 500px;
         border-radius: 10px;
         object-fit: contain;
         height: auto;
      }

      /* Responsividade */
      @media (max-width: 767px) {
         .about_section_main {
            flex-direction: column;
            text-align: center;
         }

         .about_taital_main,
         .imgdiv {
            padding: 10px;
         }
         .about_section{
            padding-bottom: 3rem
         }
      }
   </style>
</head>

<body>
   <!-- header section start -->
   <?php include('header.php'); ?>
   <!-- header section end -->

   <!-- about section start -->
   <div class="about_section layout_padding_3">
      <div class="container">
         <div class="about_section_main">
            <div class="col-md-6">
               <div class="about_taital_main">
                  <h1 class="about_taital">Sobre Nós</h1>
                  <p class="about_text">
                     A 7eight9 é uma loja de roupa virtual que foi feita com o objetivo de oferecer um local onde as pessoas pudessem descobrir roupa que nunca tinham visto antes, sem levarem com os produtos recomendados que todas as lojas tem que são sempre ou os produtos mais caros ou os mais basicos/vendidos. A nossa loja quer com que as pessoas possam encontrar coisas novas, quer sejam muito vendidas ou pouco. Os produtos aparecem sempre numa ordem aleatória, a menos que o utilizador utilize filtros para não aparecer assim, para que as pessoas possam descobrir coisas novas e diferentes. Nós orgulamo-nos em ter uma estrutura simples e fácil de entender, para que ninguem fique sobrecarregada com a quantidade de informação que em algumas lojas aparece. Se tiver alguma sugestão pode sempre contactar-nos com as informações que aparecem no fim da página, pois estamos sempre a tentar melhorar a sua experiência.
                  </p>
               </div>
            </div>
            <div class="col-md-6 imgdiv">
               <img src="https://alpha.soaresbasto.pt/~a25385/PAP/img/logo.png" class="image_3" alt="Sobre Nós">
            </div>
         </div>
      </div>
   </div>
   <!-- about section end -->

   <?php include('footer.php'); ?>

   <!-- Javascript files -->
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