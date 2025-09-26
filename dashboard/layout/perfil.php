<?php include("headers.php"); ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Mi Perfil</h2>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <!-- Imagen de perfil editable -->
            <div class="text-center mb-4 position-relative">
                <img src="../../img/Usuario/chilaquil.jpg" alt="Perfil" class="rounded-circle" width="180" height="180" id="imagenPerfil" style="object-fit: cover; cursor: pointer; border: 3px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                <input type="file" id="fileImagen" accept="image/*" style="display: none;">
                <button class="btn btn-sm btn-secondary position-absolute bottom-0 end-0" onclick="document.getElementById('fileImagen').click();">
                    <i class="bi bi-pencil"></i>
                </button>
            </div>

            <!-- Información editable -->
            <div id="infoPerfil">
                <?php
                // Simulación: Puedes reemplazar con datos reales de la BD
                $usuario = [
                    'usNombre' => $_SESSION['usNombre'],
                    'usAPaterno' => $_SESSION['usAPaterno'],
                    'usAMaterno' => $_SESSION['usAMaterno'],
                    'usTelefono' => $_SESSION['usTelefono'],
                    'usUsername' => $_SESSION['usUsername']
                ];
                foreach ($usuario as $campo => $valor) {
                    echo "
                    <div class='mb-3 d-flex justify-content-between align-items-center'>
                        <span><b>" . ucfirst($campo) . ":</b> <span class='campo-texto' data-campo='$campo'>$valor</span></span>
                        <button class='btn btn-sm btn-outline-primary' onclick='editarCampo(this, \"$campo\")'><i class='bi bi-pencil'></i></button>
                    </div>";
                }
                ?>
            </div>

            <!-- Cambio de contraseña -->
            <hr>
            <!-- Botón para abrir el offcanvas -->

            <button class="btn btn-secondary" data-bs-toggle="offcanvas" data-bs-target="#offcanvasPassword" aria-controls="offcanvasPassword">
                Cambiar Contraseña
            </button>

            <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasPassword" aria-labelledby="offcanvasPasswordLabel">
                <div class="offcanvas-header bg-primary text-white">
                    <h5 class="offcanvas-title" id="offcanvasPasswordLabel">Actualizar Contraseña</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
                </div>
                <div class="offcanvas-body">
                    <p class="text-muted mb-3">Tu nueva contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula y un caracter especial.</p>
                    <form id="formOffcanvasPassword" class="needs-validation" novalidate>
                        <div class="mb-3 position-relative">
                            <label for="nuevaPassword" class="form-label">Nueva Contraseña</label>
                            <input type="password" id="nuevaPassword" name="nuevaPassword" class="form-control"
                                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\W).{8,}$"
                                title="Debe contener al menos 8 caracteres, una mayúscula, una minúscula y un caracter especial" required>
                            <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-50 end-0 translate-middle-y me-2" onclick="togglePassword('nuevaPassword')">
                                <i class="bi bi-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                La contraseña no cumple los requisitos.
                            </div>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="confirmarPassword" class="form-label">Confirmar Contraseña</label>
                            <input type="password" id="confirmarPassword" name="confirmarPassword" class="form-control" required>
                            <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-50 end-0 translate-middle-y me-2" onclick="togglePassword('confirmarPassword')">
                                <i class="bi bi-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                Debes confirmar la contraseña.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Actualizar Contraseña</button>
                    </form>
                </div>
            </div>



        </div>
    </div>
</div>
<!-- Toast Éxito -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">¡Operación exitosa!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Toast Error -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="toastError" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">¡Ocurrió un error!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<!-- Toasts (al final del body o layout) -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
    <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">Campo actualizado correctamente.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    <div id="toastError" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">Error al actualizar campo.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>


<script>
    $("#formOffcanvasPassword").submit(function(e) {
        e.preventDefault();

        const form = this;
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');
            return;
        }

        const nuevaPassword = $("#nuevaPassword").val().trim();
        const confirmarPassword = $("#confirmarPassword").val().trim();

        if (nuevaPassword !== confirmarPassword) {
            mostrarToast('error', 'Las contraseñas no coinciden.');
            return;
        }

        $.ajax({
            url: '../../php/cambiar_contrasena.php',
            method: 'POST',
            data: {
                nuevaPassword
            },
            dataType: 'json', // 🔥 Esto asegura que sea interpretado como JSON
            success: function(response) {
                console.log(response); // 👈 Verifica en consola qué devuelve
                if (response.success) {
                    mostrarToast('success', 'Contraseña actualizada correctamente. Redirigiendo...');
                    setTimeout(() => {
                        window.location.href = '../../login/login.php'; // ✅ Cambia según ruta real
                    }, 2000);
                } else {
                    mostrarToast('error', response.error || 'Error al actualizar contraseña.');
                }
            },
            error: function(xhr, status, error) {
                mostrarToast('error', 'Error en el servidor.');
                console.error("Error: ", xhr.responseText);
            }
        });
    });
    // Mostrar toast
    function mostrarToast(tipo, mensaje) {
        const toastId = tipo === 'success' ? '#toastSuccess' : '#toastError';
        $(`${toastId} .toast-body`).text(mensaje);
        const toast = new bootstrap.Toast(document.querySelector(toastId));
        toast.show();
    }

    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        if (input.type === "password") {
            input.type = "text";
        } else {
            input.type = "password";
        }
    }
</script>
<?php include("footer.php"); ?>