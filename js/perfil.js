$(document).ready(function () {
    // Cargar perfil al inicio
    cargarPerfil();

    // Subir nueva imagen
    $("#fileImagen").change(function () {
        const formData = new FormData();
        formData.append("imagen", this.files[0]);

        $.ajax({
            url: '../../php/actualizar_imagen.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: res => {
                if (res.success) {
                    $("#imagenPerfil").attr("src", res.nuevaRuta);
                    alert("Imagen actualizada.");
                } else {
                    alert(res.error || "Error al actualizar imagen.");
                }
            }
        });
    });

    // Guardar perfil
    $("#formPerfil").submit(function (e) {
        e.preventDefault();
        const data = $(this).serialize();

        $.post('../../php/actualizar_perfil.php', data, res => {
            if (res.success) {
                alert("Perfil actualizado.");
            } else {
                alert(res.error || "Error al guardar.");
            }
        });
    });


});

function cargarPerfil() {
    $.getJSON('../../php/obtener_perfil.php', res => {
        if (res.success) {
            $("#nombre").val(res.datos.nombre);
            $("#apellidoPaterno").val(res.datos.apellidoPaterno);
            $("#apellidoMaterno").val(res.datos.apellidoMaterno);
            $("#telefono").val(res.datos.telefono);
            $("#username").val(res.datos.username);
            $("#imagenPerfil").attr("src", res.datos.imagen);
        } else {
            alert(res.error || "Error al cargar perfil.");
        }
    });
}
// Cambiar imagen al seleccionar archivo
    document.getElementById('fileImagen').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('imagenPerfil').src = e.target.result;
                // Opcional: enviar imagen a la BD usando AJAX
                const formData = new FormData();
                formData.append('imagenPerfil', file);
                $.ajax({
                    url: '../../php/actualizar_imagen_perfil.php',
                    method: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(res) {
                        if (res.success) {
                            console.log('Imagen actualizada.');
                        } else {
                            alert('Error: ' + res.error);
                        }
                    }
                });
            };
            reader.readAsDataURL(file);
        }
    });

    // Función para editar campo
    function editarCampo(button, campo) {
        const span = button.parentNode.querySelector(".campo-texto");
        const valorActual = span.textContent;

        const input = document.createElement("input");
        input.type = "text";
        input.value = valorActual;
        input.className = "form-control form-control-sm";
        input.style.width = "200px";

        input.addEventListener("blur", function() {
            guardarNuevoValor(input, span, campo);
        });
        input.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                input.blur();
            }
        });

        span.replaceWith(input);
        input.focus();
    }

    function guardarNuevoValor(input, span, campo) {
        const nuevoValor = input.value.trim() || "Sin información";
        const nuevoSpan = document.createElement("span");
        nuevoSpan.className = "campo-texto";
        nuevoSpan.dataset.campo = campo;
        nuevoSpan.textContent = nuevoValor;

        input.replaceWith(nuevoSpan);

        // Enviar a BD
        $.ajax({
            url: '../../php/actualizar_perfil.php',
            method: 'POST',
            data: {
                campo,
                valor: nuevoValor
            },
            success: function(response) {
                if (response.success) {
                    mostrarToast('success', `Valor actualizado correctamente.`);
                    console.log(`Campo ${campo} actualizado a: ${nuevoValor}`);
                } else {
                    mostrarToast('error', response.error || 'Error al actualizar campo.');
                    
                }
            },
            error: function() {
                mostrarToast('error', 'Error en el servidor.');
            }
        });
    }
    