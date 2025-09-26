<?php session_start();
if (!empty($_GET['token'])) {
    header('Location: ../sos.php');
}
?>
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
    <script src="../js/main.js"></script>
    <!-- /JS -->


</head>

<section class="h-100 gradient-form" style="background-color: #eee;">
    <div class="container py-5 h-100">
        <div class="row d-flex justify-content-center align-items-center h-100">
            <div class="col-xl-10">
                <div class="card rounded-3 text-black">
                    <div class="row g-0">
                        
                        <div class="col-lg-12">
                            <div class="card-body p-md-5 mx-md-4">

                                <div class="text-center">
                                    <img src="../img/logo MR.webp" style="width: 125px;" class="mb-3" alt="logo">
                                    <h4 class="mt-1 mb-5 pb-1">Reseteo de Password</h4>
                                </div>

                                <form id="login-form" class="was-validated">
                                    <p>Por favor llena los campos con la información solicitada</p>

                                    <div class="form-outline mb-2">
                                        <label class="form-label" for="password">Contraseña:</label>
                                        <input type="password" name="password" id="password" class="form-control" placeholder="*******" required />
                                        <div class="valid-feedback">Valido.</div>
                                        <div class="invalid-feedback">Por favor llena este campo para proceder.</div>
                                    </div>

                                    <div class="form-outline mb-4">
                                        <label class="form-label" for="password">Repetir Contraseña:</label>
                                        <input type="password" name="repassword" id="repassword" class="form-control" placeholder="*******" required />
                                        <div class="valid-feedback">Valido.</div>
                                        <div class="invalid-feedback">Por favor llena este campo para proceder.</div>
                                    </div>


                                    <div class="text-center pt-1 mb-5 pb-1">
                                        <button data-mdb-button-init data-mdb-ripple-init class="btn btn-primary btn-block fa-lg gradient-custom-2 p-3" type="submit">
                                            Cambiar Contraseña
                                        </button>
                                        <a class="btn btn-gray btn-block fa-lg p-1 mt-2">Contactar a Soporte</a>
                                    </div>
                                </form>

                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>