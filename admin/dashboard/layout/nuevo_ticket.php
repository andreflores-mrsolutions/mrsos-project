<?php include("headers.php"); ?>
<div class="container">
    <div class="row">
        <main class="col-md-9 ms-sm-auto col-lg-12 px-md-4">

            <div class="container mt-4">
                <h2 class="text-center">Selecciona un equipo para crear un nuevo ticket</h2>
                <div id="wizard-container">

                    <!-- PASO 1: Equipos -->
                    <div id="paso1" class="wizard-step">
                        <h3>Selecciona el equipo</h3>
                        <div id="contenedorEquipos" class="row"></div>
                    </div>

                    <!-- PASO 2: Severidad -->
                    <div id="paso2" class="wizard-step" style="display:none;">
                        <h3 class="text-center">Selecciona la Severidad</h3>
                        <div class="row g-3">

                            <!-- Card Nivel 1 -->
                            <div class="col-md-4">
                                <div class="card severidad-card h-100 text-center shadow-sm" data-severidad="1">
                                    <div class="card-body d-flex flex-column justify-content-between">
                                        <h5 class="mb-3" style="color: red;">Nivel 1</h5>
                                        <p style="flex-grow: 1;">Significa la caída total del producto, equipo o sus subsistemas que interrumpe un Servicio crítico de El Cliente, y los procedimientos de recuperación no funcionaron ante la caída y se han aplicado todas las acciones al alcance de El Cliente, sin lograr recuperar la operabilidad del producto o equipo.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Nivel 2 -->
                            <div class="col-md-4">
                                <div class="card severidad-card h-100 text-center shadow-sm" data-severidad="2">
                                    <div class="card-body d-flex flex-column justify-content-between">
                                        <h5 class="mb-3" style="color: orange;">Nivel 2</h5>
                                        <p style="flex-grow: 1;">El servicio o Equipo para un usuario individual del sistema no está disponible o está seriamente afectado, o el servicio a muchos usuarios está afectado. No existe alternativa disponible para efectuar el trabajo. La pérdida del servicio puede resultar en pérdida de productividad o puede poner en peligro beneficios o ingresos monetarios.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Nivel 3 -->
                            <div class="col-md-4">
                                <div class="card severidad-card h-100 text-center shadow-sm" data-severidad="3">
                                    <div class="card-body d-flex flex-column justify-content-between">
                                        <h5 class="mb-3" style="color: green;">Nivel 3</h5>
                                        <p style="flex-grow: 1;">El servicio o Equipo para un usuario individual está afectado, causando dificultad para efectuar su trabajo normal. Existen alternativas disponibles para efectuar el trabajo, pero otras actividades pueden ser afectadas mientras se espera la resolución del problema. La pérdida del servicio puede resultar en reducciones de la productividad, pero no afecta beneficios o ingresos monetarios.</p>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <button type="button" class="btn btn-secondary mt-3" style="margin-bottom: 100px;" id="btnVolverPaso1"><i class="bi bi-arrow-left-short"></i> Volver a Equipos</button>
                    </div>


                    <!-- PASO 3: Formulario -->
                    <div id="paso3" class="wizard-step" style="display:none;">
                        <h3>Detalles del problema</h3>
                        <form id="formTicket">
                            <input type="hidden" name="eqId" id="selectedEqId">
                            <input type="hidden" name="severidad" id="selectedSeveridad">

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción del problema</label>
                                <textarea id="descripcion" name="descripcion" class="form-control" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="logs" class="form-label">Logs (opcional)</label>
                                <input type="file" id="logs" name="logs" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="contacto" class="form-label">Nombre del contacto</label>
                                <input type="text" id="contacto" name="contacto" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="telefono" class="form-label">Número de contacto</label>
                                <input type="tel" id="telefono" name="telefono" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo electrónico</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Crear Ticket</button>
                        </form>
                        <button type="button" class="btn btn-secondary mt-3" id="btnVolverPaso2" style="margin-bottom: 100px;"><i class="bi bi-arrow-left-short"></i> Volver a Severidad</button>
                    </div>

                </div>

            </div>
            <!-- Toast container -->
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055">
                <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ✅ Ticket creado exitosamente.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
                <div id="toastError" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ❌ Error al crear el ticket.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </div>

            <script src="../../js/nuevoticket.js"></script>



        </main>
    </div>
</div>
<?php include("footer.php"); ?>