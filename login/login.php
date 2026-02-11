<?php 
require_once __DIR__ . '/../php/csrf.php';
$csrf = csrf_token();

if (isset($_SESSION['usEstatus'])) {
    if ($_SESSION['usEstatus'] === 'NewPass') {
        header('Location: cambiar_password.php');
    }
}
if (!empty($_SESSION['usId']) ) {
    header('Location: ../sos.php');
}
?>
<script>
  window.MRS_CSRF = <?= json_encode($csrf) ?>;
</script>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>MRSolutions</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- SweetAlert -->
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <!-- /SweetAlert -->

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- /Bootstrap -->

    <!-- Fontawesome -->
    <script src="https://kit.fontawesome.com/04af9e068b.js" crossorigin="anonymous"></script>
    <!-- /Fontawesome -->

    <!-- CSS3 -->
    <link href="../css/style.css" rel="stylesheet">
    <!-- /CSS3 -->

    <!-- Ajax - JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- /Ajax - JQuery -->

    <!-- JS -->
    <script src="../js/login.js"></script>

    <!-- /JS -->


</head>

<section class="h-100 gradient-form" style="background-color: #eee;">
    <div class="container py-5 h-100">
        <div class="row d-flex justify-content-center align-items-center h-100">
            <div class="col-xl-10">
                <div class="card rounded-3 text-black">
                    <div class="row g-0">
                        <div class="col-lg-6">
                            <div class="card-body p-md-5 mx-md-4">

                                <div class="text-center">
                                    <img src="../img/logo MR.webp" style="width: 125px;" class="mb-3" alt="logo">
                                    <h4 class="mt-1 mb-5 pb-1">Inicia sesión para poder acceder</h4>

                                </div>

                                <form id="login-form">
                                    <p>Por favor llena los campos con la información solicitada</p>

                                    <div class="form-outline mb-4">
                                        <label class="form-label" for="usId">Número de Usuario:</label>
                                        <input type="number" name="usId" id="usId" class="form-control" placeholder="0000" required />
                                    </div>

                                    <div class="form-outline mb-4">
                                        <label class="form-label" for="usPass">Contraseña:</label>
                                        <input type="password" name="usPass" id="usPass" class="form-control" placeholder="*******" required />
                                    </div>



                                    <div class="text-center pt-1 mb-5 pb-1">
                                        <button data-mdb-button-init data-mdb-ripple-init class="btn btn-primary btn-block fa-lg gradient-custom-2 p-3" type="submit">
                                            Iniciar Sesión
                                        </button>
                                        <a class="btn btn-gray btn-block fa-lg p-1 mt-2 collpased" data-bs-toggle="collapse" href="#collapseTwo">¿Olvidaste tu contraseña?</a>
                                    </div>
                                </form>
                                <div id="collapseTwo" class="collapse" data-bs-parent="#accordion">
                                    <div class="card-body">
                                        <form id="reset-p-form">
                                            <p>Reseteo de password, ingrese el número de Usuario:</p>
                                            <div class="form-outline mb-4">
                                                <label class="form-label" for="usId">Número de Usuario:</label>
                                                <input type="number" name="usId" id="udId" class="form-control" placeholder="0000" required />
                                            </div>
                                            <div class="form-outline mb-4">
                                                <label class="form-label" for="usCorreo">Email:</label>
                                                <input type="email" name="usCorreo" id="usCorreo" class="form-control" placeholder="example@mail.com" required />
                                            </div>

                                            <div class="text-center pt-1 mb-5 pb-1">
                                                <button class="btn btn-primary btn-block fa-lg gradient-custom-2 p-3" type="submit" id="btn-reset-pass">
                                                    Resetear Contraseña
                                                </button>
                                            </div>
                                        </form>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 d-flex align-items-center gradient-custom-2">
                            <div class="text-white px-3 py-4 p-md-5 mx-md-4">
                                <h4 class="mb-4">25 años de MRSolutions</h4>
                                <p class="small mb-0">En MRSOLUTIONS, llevamos 25 años comprometidos con la innovación y
                                    la excelencia en el ámbito de la tecnología de la información. Como empresa mexicana
                                    líder en servicios de TI, entendemos los constantes cambios en la industria y
                                    trabajamos mano a mano con nuestros clientes para resolver los desafíos más complejos en
                                    Procesamiento, Virtualización, Almacenamiento y Respaldo de Información.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>