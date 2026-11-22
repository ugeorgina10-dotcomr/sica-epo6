<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Control de Asistencias - SICA EPO6</title>
    <link href="{{ asset('vendor/bootstrap.min.css') }}" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h2 class="mb-4 text-center fw-bold" style="color: #800020;">SICA-EPO6 | Control de Entradas y Salidas</h2>
        
        <div id="alerta"></div>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm p-4 mb-4">
                    <h5 class="fw-bold mb-3">Escáner / Matrícula</h5>
                    <form id="form-registro">
                        <div class="mb-3">
                            <label class="form-label">CURP / Matrícula</label>
                            <input type="text" id="matricula" class="form-control form-control-lg" placeholder="Escanea el QR o escribe..." required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Movimiento</label>
                            <select id="tipo_movimiento" class="form-select form-select-lg">
                                <option value="Entrada">Entrada</option>
                                <option value="Salida">Salida</option>
                            </select>
                        </div>
                        <button type="submit" class="btn text-white w-100 fw-bold py-4" style="background-color: #800020;">Registrar</button>
                    </form>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm p-4">
                    <h5 class="fw-bold mb-3">Historial de Registros Recientes</h5>
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Alumno</th>
                                <th>Estatus</th>
                                <th>Fecha y Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(isset($asistencias) && count($asistencias) > 0): ?>
                                <?php foreach($asistencias as$item): ?>
                                    <tr>
                                        <td><?php echo $item->alumno->matricula ?? 'N/A'; ?></td>
                                        <td><?php echo $item->alumno->nombre ?? 'N/A'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $item->estatus == 'Retardo' ? 'bg-warning text-dark' : ($item->estatus == 'Asistencia' ? 'bg-success' : 'bg-info'); ?>">
                                                <?php echo $item->estatus; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $item->fecha_hora; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No hay registros de asistencia aún.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if(method_exists($asistencias, 'links')): ?>
                        {{ $asistencias->links() }}
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/jquery.min.js') }}"></script>
    <script>
        $('#form-registro').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('asistencias.registrar') }}",
                type: "POST",
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    matricula: $('#matricula').val(),
                    tipo_movimiento: $('#tipo_movimiento').val()
                },
                success: function(res) {
                    $('#alerta').html(`<div class="alert alert-success">${res.message}</div>`);
                    $('#matricula').val('').focus();
                    setTimeout(() => location.reload(), 1000);
                },
                error: function(err) {
                    let msg = err.responseJSON && err.responseJSON.message ? err.responseJSON.message : 'Error al registrar.';
                    $('#alerta').html(`<div class="alert alert-danger">${msg}</div>`);
                    $('#matricula').select();
                }
            });
        });
    </script>
</body>
</html>