$(document).ready(function () {
  function contieneInyeccion(str) {
    const pattern =
      /<|>|script|onerror|alert|select|insert|delete|update|union|drop|--|;|['"]/gi;
    return pattern.test(str);
  }

  $("#login-form").on("submit", function (e) {
    e.preventDefault(); // Evita el envío por defecto

    const usId = $("#usId").val().trim();
    const usPass = $("#usPass").val().trim();

    // Validación de campos vacíos
    if (!usId || !usPass) {
      Swal.fire({
        icon: "warning",
        title: "Campos vacíos",
        text: "Por favor completa ambos campos.",
      });
      return;
    }

    // Validación contra inyecciones
    if (contieneInyeccion(usId) || contieneInyeccion(usPass)) {
      Swal.fire({
        icon: "error",
        title: "Entrada no válida",
        text: "Tu información contiene caracteres o palabras no permitidas.",
      });
      return;
    }

    $.ajax({
      url: "../php/login.php",
      method: "POST",
      data: {
        usId: usId,
        usPass: usPass,
        csrf_token: window.MRS_CSRF
      },
      dataType: "json", // importante: esperamos JSON
      success: function (response) {
        console.log("login response:", response);

        if (!response || typeof response.success === "undefined") {
          Swal.fire("Error", "Respuesta inesperada del servidor.", "error");
          return;
        }

        // ❌ Login fallido (credenciales mal, usuario inactivo, etc.)
        if (!response.success) {
          Swal.fire(
            "Error",
            response.message || "Usuario o contraseña incorrectos",
            "error"
          );
          return;
        }

        // ✅ Caso especial: forzar cambio de contraseña
        if (response.forceChangePass) {
          // aquí mandamos al usuario a la página de bienvenida / cambio de pass
          window.location.href = "cambiar_password.php";
          return;
        }

        // ✅ Login normal
        window.location.href = "../sos.php";
      },
      error: function (xhr, status, error) {
        console.error("AJAX error:", status, error);
        Swal.fire(
          "Error",
          "No se pudo conectar con el servidor o el usuario fue eliminado",
          "error"
        );
      },
    });
  });

  // RESET PASSWORD
  $("#reset-p-form").on("submit", function (e) {
    e.preventDefault();

    const usId = $("#udId").val().trim();
    const usCorreo = $("#usCorreo").val().trim();

    if (!usId || !usCorreo) {
      Swal.fire({
        icon: "warning",
        title: "Campos vacíos",
        text: "Completa ambos campos.",
      });
      return;
    }

    if (contieneInyeccion(usId) || contieneInyeccion(usCorreo)) {
      Swal.fire({
        icon: "error",
        title: "Entrada no válida",
        text: "Hay caracteres no permitidos.",
      });
      return;
    }

    const $btn = $("#btn-reset-pass")
      .prop("disabled", true)
      .addClass("disabled");

    $.ajax({
      url: "../php/recuperar_password.php",
      method: "POST",
      dataType: "json",
      data: { usId, usCorreo },
      success: function (response) {
        if (response && response.success) {
          Swal.fire({
            icon: "success",
            title: "Correo enviado",
            text: "Revisa tu bandeja para cambiar la contraseña.",
          });
          $("#reset-p-form")[0].reset();
        } else {
          Swal.fire(
            "Error",
            response?.error || "Datos no coinciden o usuario inactivo.",
            "error"
          );
        }
      },
      error: function (xhr, status) {
        console.error("AJAX error:", status);
        Swal.fire("Error", "No se pudo contactar el servidor.", "error");
      },
      complete: function () {
        $btn.prop("disabled", false).removeClass("disabled");
      },
    });
  });
});
