<?php
require "connection.php";
require "sqlconnection.php";
require_once './core.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    $mensagem = "";
    $tipoMensagem = "";
    $mensagempass = "";
    $tipoMensagempass = "";

    $old_email = $_SESSION['email'];

    // Obter dados do cliente
    $sql = "SELECT * FROM PAP_CLIENTE WHERE EMAIL = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit'])) {
        if (empty($_POST['edit_name']) || empty($_POST['edit_email']) || empty($_POST['edit_phone']) || empty($_POST['edit_address'])) {
            $mensagem = "Preencha todos os campos!";
            $tipoMensagem = "danger";
        } else {
            $nome = $_POST['edit_name'];
            $new_email = $_POST['edit_email'];
            $tele = $_POST['edit_phone'];
            $morada = $_POST['edit_address'];

            $sql = "UPDATE PAP_CLIENTE SET NOME = ?, EMAIL = ?, NUMTELE = ?, MORADA = ? WHERE EMAIL = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiss", $nome, $new_email, $tele, $morada, $old_email);

            if ($stmt->execute()) {
                $mensagem = "Conta atualizada com sucesso";
                $tipoMensagem = "success";
                $_SESSION['email'] = $new_email;
                $_SESSION['user'] = $nome;
                $sql = "SELECT * FROM PAP_CLIENTE WHERE EMAIL = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $new_email);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
            } else {
                $mensagem = "Erro ao atualizar a conta: " . $stmt->error;
                $tipoMensagem = "danger";
            }
        }
    } else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pass'])) {
        $response = array();
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $email = $_SESSION['email'];

        if ($new_password != $confirm_password) {
            $response['status'] = 'error';
            $response['message'] = 'As palavras-passe não coincidem.';
        } else {
            $sql = "SELECT PASS FROM PAP_CLIENTE WHERE EMAIL = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $rowDB = $result->fetch_assoc();

            if (password_verify($current_password, $rowDB['PASS'])) {
                if (strlen($new_password) < 8) {
                    $response['status'] = 'error';
                    $response['message'] = 'A password tem que ter no mínimo 8 caracteres.';
                } else {
                    $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE PAP_CLIENTE SET PASS = ? WHERE EMAIL = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ss", $new_password_hashed, $email);
                    if ($stmt->execute()) {
                        $response['status'] = 'success';
                        $response['message'] = 'Palavra-passe alterada com sucesso.';
                    } else {
                        $response['status'] = 'error';
                        $response['message'] = 'Erro ao alterar a palavra-passe: ' . $stmt->error;
                    }
                }
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Palavra-passe atual incorreta.';
            }
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        $mensagempass = $response['message'];
        $tipoMensagempass = ($response['status'] == 'success') ? 'success' : 'danger';
    } else if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['deleteacc'])) {
        $email = $_SESSION['email'];
        $sql = "DELETE FROM PAP_CLIENTE WHERE EMAIL = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            session_destroy();
            header("Location: index.php");
            exit();
        } else {
            $mensagem = "Erro ao eliminar a conta: " . $stmt->error;
            $tipoMensagem = "danger";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt">

    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <!-- Viewport para responsividade -->
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Editar conta</title>
        <!-- CSS -->
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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            body {
                background: rgb(230, 230, 230);
                font-family: 'Open Sans', sans-serif;
            }

            .account-container {
                background: #fff;
                padding: 25px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                width: 90%;
                max-width: 600px;
                margin: 5vh auto;
            }

            .alert {
                margin-bottom: 20px;
            }

            .info-group {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #ddd;
            }

            .info-group:last-child {
                border-bottom: none;
            }

            .info-group label {
                font-weight: bold;
                width: 120px;
                color: #555;
                margin-bottom: 0;
            }

            .info-group span {
                flex: 1;
                text-align: right;
                color: #333;
                word-break: break-all;
            }

            .info-group input {
                display: none;
                flex: 1;
                padding: 5px;
                font-size: 16px;
                border: 1px solid #ccc;
                border-radius: 5px;
                margin-left: 2vw;
                transition: border-color 0.3s ease;
            }

            .info-group input:focus {
                border-color: #0d6efd;
                box-shadow: 0 0 5px rgba(13, 110, 253, 0.5);
                outline: none;
            }

            .edit-icon {
                cursor: pointer;
                color: #007bff;
                font-size: 1.2rem;
                margin-left: 10px;
            }

            .edit-icon:hover {
                color: #0056b3;
            }

            .btn-save,
            #cancel-main-btn {
                display: none;
            }

            .btn-pass {
                background-color: #C5C5C5;
                font-weight: bold;
                width: 100%;
                word-wrap: break-word;
                white-space: normal;
            }

            .btn-pass:hover {
                background-color: #A9A9A9;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            }

            /* Área de alteração de palavra-passe inline */
            #edit-password-container {
                display: none;
                background: #f9f9f9;
                padding: 15px;
                margin-top: 15px;
                border-radius: 5px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .buttonsgc {
                gap: 20px;
            }

            .modal-content {
                border-radius: 12px;
                overflow: hidden;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }

            .modal-content:hover {
                transform: scale(1.02);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            }

            .modal-header {
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            }

            @media (max-width: 576px) {
                .info-group label {
                    width: 100%;
                    margin-bottom: 5px;
                    text-align: left;
                }

                .info-group span,
                .info-group input {
                    width: 100%;
                    margin-left: 0;
                    text-align: left;
                }

                .edit-icon {
                    margin-top: 5px;
                }

                .buttonsgc {
                    flex-direction: column;
                    gap: 10px;
                }
            }

            .navbar {
                padding: 0.5rem 1rem;
            }
        </style>
    </head>

    <body>
        <?php include('header.php'); ?>
        <div class="account-container">
            <h2>Minha Conta</h2>
            <?php
            if (!empty($mensagem)) {
                echo "<div class='alert alert-$tipoMensagem'>$mensagem</div>";
            }
            ?>
            <form id="account-form" method="POST">
                <input type="hidden" name="edit" value="edit">
                <div class="info-group">
                    <label>Nome:</label>
                    <span id="view-name"><?= htmlspecialchars($row["NOME"]) ?></span>
                    <input type="text" id="edit-name" name="edit_name" value="<?= htmlspecialchars($row["NOME"]) ?>">
                    <i class="bi bi-pencil edit-icon" onclick="toggleEdit('name')"></i>
                </div>
                <div class="info-group">
                    <label>Email:</label>
                    <span id="view-email"><?= htmlspecialchars($row["EMAIL"]) ?></span>
                    <input type="email" id="edit-email" name="edit_email" value="<?= htmlspecialchars($row["EMAIL"]) ?>">
                    <i class="bi bi-pencil edit-icon" onclick="toggleEdit('email')"></i>
                </div>
                <div class="info-group">
                    <label>Telemóvel:</label>
                    <span id="view-phone"><?= htmlspecialchars($row["NUMTELE"]) ?></span>
                    <input type="tel" id="edit-phone" name="edit_phone" pattern="[0-9]{9}"
                        value="<?= htmlspecialchars($row["NUMTELE"]) ?>">
                    <i class="bi bi-pencil edit-icon" onclick="toggleEdit('phone')"></i>
                </div>
                <div class="info-group">
                    <label>Morada:</label>
                    <span id="view-address"><?= htmlspecialchars($row["MORADA"]) ?></span>
                    <input type="text" id="edit-address" name="edit_address"
                        value="<?= htmlspecialchars($row["MORADA"]) ?>">
                    <i class="bi bi-pencil edit-icon" onclick="toggleEdit('address')"></i>
                </div>
                <div class="d-flex justify-content-center mt-3 buttonsgc">
                    <button type="submit" class="btn btn-primary btn-save" id="save-button">Guardar Alterações</button>
                    <button type="button" class="btn btn-danger" id="cancel-main-btn">Cancelar alterações</button>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i>
                    Voltar</a>
            </div>
            <div class="text-center mt-4">
                <button type="button" class="btn btn-secondary btn-pass" id="edit-pass-btn">Editar palavra-passe</button>
            </div>
            <div id="edit-password-container">
                <h4>Alterar Palavra-passe</h4>
                <div id="pass-message"></div>
                <form method="POST" id="pass-form">
                    <input type="hidden" name="pass" value="pass">
                    <div class="mb-3">
                        <label class="form-label">Palavra-passe atual:</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nova Palavra-passe:</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar Nova Palavra-passe:</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <div class="text-center">
                        <button type="button" class="btn btn-secondary" id="cancel-pass-btn">Cancelar</button>
                        <button type="submit" class="btn btn-success">Alterar</button>
                    </div>
                </form>
            </div>
            <div class="text-center mt-4">
                <button type="button" class="btn btn-danger fw-bold px-4 py-2" id="open-delete-modal">
                    <i class="bi bi-trash"></i> Eliminar Conta
                </button>
            </div>
        </div>
        <div class="modal fade" id="modalDeleteAccount" tabindex="-1" aria-labelledby="modalDeleteLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg rounded-3">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="modalDeleteLabel">Confirmação de Exclusão</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p class="fw-bold">Tem certeza que deseja eliminar a sua conta?</p>
                        <p class="text-muted">Esta ação é irreversível e todos os seus dados serão perdidos.</p>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <form method="POST">
                            <input type="hidden" name="deleteacc" value="deleteacc">
                            <button type="submit" name="delete_account" class="btn btn-danger">Confirmar</button>
                        </form>
                        <button type="button" class="btn btn-secondary w-50" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
        <script src="js/jquery.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap.bundle.min.js"></script>
        <script src="js/plugin.js"></script>
        <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
        <script src="js/custom.js"></script>
        <script src="js/owl.carousel.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.5/jquery.fancybox.min.js"></script>
        <script src="https://unpkg.com/gijgo@1.9.13/js/gijgo.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Função para alternar entre visualização e edição
            function toggleEdit(field) {
                $("#view-" + field).hide();
                $("#edit-" + field).show().focus();
                $("#save-button, #cancel-main-btn").show();
            }

            document.getElementById("open-delete-modal").addEventListener("click", function () {
                let modal = new bootstrap.Modal(document.getElementById("modalDeleteAccount"));
                modal.show();
            });

            $(document).ready(function () {
                // Cancelar alterações
                $("#cancel-main-btn").click(function () {
                    $("#edit-name").val($("#view-name").text());
                    $("#edit-email").val($("#view-email").text());
                    $("#edit-phone").val($("#view-phone").text());
                    $("#edit-address").val($("#view-address").text());

                    $("#edit-name, #edit-email, #edit-phone, #edit-address").hide();
                    $("#view-name, #view-email, #view-phone, #view-address").show();
                    $("#save-button, #cancel-main-btn").hide();
                });

                // Exibir alteração de palavra-passe
                $("#edit-pass-btn").click(function () {
                    $("#edit-password-container").slideDown();
                    $(this).hide();
                });

                // Cancelar alteração de palavra-passe
                $("#cancel-pass-btn").click(function () {
                    $("#edit-password-container").slideUp(function () {
                        $("#pass-form")[0].reset();
                        $("#pass-message").html("");
                    });
                    $("#edit-pass-btn").show();
                });

                // Submissão via AJAX para alteração de palavra-passe
                $("#pass-form").submit(function (e) {
                    e.preventDefault();
                    $("#pass-message").html("");
                    $.ajax({
                        type: "POST",
                        url: "",
                        data: $(this).serialize(),
                        dataType: "json",
                        success: function (response) {
                            var alertType = response.status === "success" ? "success" : "danger";
                            $("#pass-message").html("<div class='alert alert-" + alertType + "'>" + response.message + "</div>");
                            if (response.status === "success") {
                                $("#pass-form")[0].reset();
                                setTimeout(function () {
                                    $("#edit-password-container").slideUp(function () {
                                        $("#pass-message").html("");
                                    });
                                    $("#edit-pass-btn").show();
                                }, 2000);
                            }
                        },
                        error: function () {
                            $("#pass-message").html("<div class='alert alert-danger'>Ocorreu um erro ao atualizar a palavra-passe.</div>");
                        }
                    });
                });
            });
        </script>
    </body>

    </html>
    <?php
} else {
    header("Location: login.php");
    exit();
}
?>