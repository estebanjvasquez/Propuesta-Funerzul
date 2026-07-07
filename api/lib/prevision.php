<?php
/**
 * Helpers compartidos del Módulo de Previsión (planes de previsión funeraria).
 * Lo usan los endpoints api/prevision_*.php. Requiere bootstrap.php cargado.
 */
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

// --- Catálogos (deben coincidir con los ENUM de database/04_prevision.sql) ---
const PREV_MONEDAS             = ['BS', 'USD'];
const PREV_FRECUENCIAS         = ['semanal', 'quincenal', 'mensual', 'trimestral', 'semestral', 'anual'];
const PREV_FORMAS_PAGO_CONTRATO = ['caja', 'domiciliacion', 'transferencia', 'pago_movil', 'cobrador', 'otro'];
const PREV_FORMAS_PAGO_PAGO    = ['efectivo', 'transferencia', 'pago_movil', 'punto', 'zelle', 'divisa', 'otro'];
const PREV_TIPOS_CUENTA        = ['corriente', 'ahorro', 'otra'];
const PREV_ESTATUS_CONTRATO    = ['activo', 'suspendido', 'anulado', 'renuncia', 'finalizado'];
const PREV_ESTATUS_BENEFICIARIO = ['activo', 'suspendido', 'excluido', 'fallecido'];
const PREV_TIPOS_CUOTA         = ['inicial', 'programada', 'especial', 'mora', 'final'];
const PREV_ETAPAS_COMISION     = ['semana1', 'fin_mes1', 'mes2', 'mes13'];
const PREV_ESTADOS_SINIESTRO   = ['abierto', 'liquidado', 'cerrado', 'rechazado'];
const PREV_TIPOS_SIN_DETALLE   = ['servicio', 'pago', 'reintegro', 'otro'];
const PREV_TIPOS_GESTION       = ['llamada', 'visita', 'whatsapp', 'sms', 'email', 'otro'];
const PREV_RESULTADOS_GESTION  = ['contactado', 'no_contactado', 'promesa_pago', 'reclamo', 'otro'];

/** Valor dentro de una lista permitida, o $default. */
function prev_enum($v, array $allowed, ?string $default = null): ?string
{
    $v = is_string($v) ? strtolower(trim($v)) : '';
    return in_array($v, $allowed, true) ? $v : $default;
}

/** Moneda 'BS' | 'USD' (por defecto USD). */
function prev_moneda($v): string
{
    $m = strtoupper(trim((string)$v));
    return in_array($m, PREV_MONEDAS, true) ? $m : 'USD';
}

/** Monto monetario saneado (2 decimales, no negativo). */
function prev_money($v): float
{
    if (is_string($v)) { $v = str_replace([' ', ','], ['', '.'], $v); }
    $n = (float)$v;
    return $n > 0 ? round($n, 2) : 0.0;
}

/** Fecha 'Y-m-d' válida o null. Acepta también d/m/Y y d-m-Y (importaciones). */
function prev_date($v): ?string
{
    $v = trim((string)$v);
    if ($v === '' || $v === '0000-00-00') return null;
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $v);
        if ($d && $d->format($fmt) === $v) return $d->format('Y-m-d');
    }
    return null;
}

/** Cédula/RIF normalizada: solo dígitos y letras, en mayúsculas (sin nacionalidad). */
function prev_cedula($v): string
{
    return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string)$v) ?? '');
}

/** Nacionalidad V/E/J/P (por defecto V). */
function prev_nacionalidad($v): string
{
    $n = strtoupper(substr(trim((string)$v), 0, 1));
    return in_array($n, ['V', 'E', 'J', 'P'], true) ? $n : 'V';
}

/** Avanza una fecha según la frecuencia de pago del contrato. */
function prev_avanzar_fecha(DateTime $d, string $frecuencia): DateTime
{
    $d = clone $d;
    switch ($frecuencia) {
        case 'semanal':    $d->modify('+7 days');   break;
        case 'quincenal':  $d->modify('+15 days');  break;
        case 'trimestral': $d->modify('+3 months'); break;
        case 'semestral':  $d->modify('+6 months'); break;
        case 'anual':      $d->modify('+1 year');   break;
        case 'mensual':
        default:           $d->modify('+1 month');  break;
    }
    return $d;
}

/** Tasa de cambio (Bs/USD) vigente para una fecha: la última registrada <= fecha. */
function prev_tasa_del_dia(?string $fecha = null): float
{
    $fecha = $fecha ?: date('Y-m-d');
    $st = db()->prepare("SELECT tasa FROM prev_tasas WHERE fecha <= ? ORDER BY fecha DESC LIMIT 1");
    $st->execute([$fecha]);
    $t = $st->fetchColumn();
    return $t !== false ? (float)$t : 0.0;
}

/** Edad en años a una fecha dada (o hoy). */
function prev_edad(?string $fechaNac, ?string $al = null): ?int
{
    if (!$fechaNac) return null;
    try {
        $n = new DateTime($fechaNac);
        $a = new DateTime($al ?: 'today');
        return $n <= $a ? $n->diff($a)->y : null;
    } catch (\Throwable $e) { return null; }
}

// ---------------------------------------------------------------------------
// Siniestros: validación de cobertura
// ---------------------------------------------------------------------------

/**
 * Evalúa la cobertura de un siniestro para un beneficiario de un contrato.
 * Devuelve ['cobertura' => cubierto|con_observaciones|sin_cobertura,
 *           'checks' => [[check, ok, detalle], ...]]
 */
function prev_validar_cobertura(array $contrato, array $beneficiario, string $fechaDefuncion): array
{
    $checks = [];
    $fatal = false;   // impide la cobertura
    $obs   = false;   // cobertura con observaciones

    // 1. Estatus del contrato
    $estC = $contrato['estatus'];
    if ($estC === 'activo') {
        $checks[] = ['check' => 'Contrato activo', 'ok' => true, 'detalle' => 'El contrato está activo.'];
    } elseif ($estC === 'suspendido') {
        $obs = true;
        $checks[] = ['check' => 'Contrato activo', 'ok' => false,
                     'detalle' => 'El contrato está SUSPENDIDO; revisar antes de aprobar.'];
    } else {
        $fatal = true;
        $checks[] = ['check' => 'Contrato activo', 'ok' => false,
                     'detalle' => 'El contrato está ' . strtoupper($estC) . '; sin cobertura.'];
    }

    // 2. Estatus del beneficiario
    $estB = $beneficiario['estatus'];
    if ($estB === 'activo') {
        $checks[] = ['check' => 'Beneficiario activo', 'ok' => true, 'detalle' => 'El beneficiario está activo en el contrato.'];
    } elseif ($estB === 'suspendido') {
        $obs = true;
        $checks[] = ['check' => 'Beneficiario activo', 'ok' => false,
                     'detalle' => 'El beneficiario está SUSPENDIDO; revisar antes de aprobar.'];
    } else {
        $fatal = true;
        $checks[] = ['check' => 'Beneficiario activo', 'ok' => false,
                     'detalle' => 'El beneficiario figura como ' . strtoupper($estB) . '; sin cobertura.'];
    }

    // 3. Plazo de espera cumplido (el del beneficiario si lo tiene; si no, el del contrato)
    $vigencia = null;
    if ($beneficiario['plazo_espera_meses'] !== null && $beneficiario['fecha_inclusion']) {
        $vigencia = (new DateTime($beneficiario['fecha_inclusion']))
            ->modify('+' . (int)$beneficiario['plazo_espera_meses'] . ' months')->format('Y-m-d');
    } elseif (!empty($contrato['vigente_desde'])) {
        $vigencia = $contrato['vigente_desde'];
    }
    if ($vigencia === null || $fechaDefuncion >= $vigencia) {
        $checks[] = ['check' => 'Plazo de espera', 'ok' => true,
                     'detalle' => $vigencia ? "Cobertura vigente desde $vigencia." : 'Sin plazo de espera registrado.'];
    } else {
        $fatal = true;
        $checks[] = ['check' => 'Plazo de espera', 'ok' => false,
                     'detalle' => "La cobertura inicia el $vigencia y la defunción fue el $fechaDefuncion; plazo de espera NO cumplido."];
    }

    // 4. Morosidad del contrato
    $st = db()->prepare(
        "SELECT COUNT(*) AS n, COALESCE(SUM(saldo),0) AS monto FROM prev_cuotas
         WHERE contrato_id = ? AND estado IN ('pendiente','parcial') AND fecha_vencimiento < CURDATE()"
    );
    $st->execute([(int)$contrato['id']]);
    $mora = $st->fetch();
    if ((int)$mora['n'] === 0) {
        $checks[] = ['check' => 'Solvencia', 'ok' => true, 'detalle' => 'El contrato no tiene cuotas vencidas.'];
    } else {
        $obs = true;
        $checks[] = ['check' => 'Solvencia', 'ok' => false,
                     'detalle' => "Tiene {$mora['n']} cuota(s) vencida(s) por " .
                                  number_format((float)$mora['monto'], 2, ',', '.') . ' ' . $contrato['moneda'] . '.'];
    }

    $cobertura = $fatal ? 'sin_cobertura' : ($obs ? 'con_observaciones' : 'cubierto');
    return ['cobertura' => $cobertura, 'checks' => $checks];
}

// ---------------------------------------------------------------------------
// Cobranza: auto-lapsado (suspensión automática por cuotas vencidas)
// ---------------------------------------------------------------------------

/**
 * Contratos activos con >= $minCuotas cuotas vencidas. Si $dryRun es false,
 * los suspende (estatus 'suspendido' + motivo) y audita. Devuelve la lista.
 */
function prev_lapsar(int $minCuotas, bool $dryRun = true): array
{
    $minCuotas = max(1, $minCuotas);
    $st = db()->prepare(
        "SELECT c.id, c.numero, c.moneda, CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
                COUNT(q.id) AS cuotas_vencidas, COALESCE(SUM(q.saldo),0) AS saldo_vencido,
                MIN(q.fecha_vencimiento) AS vencida_desde
         FROM prev_contratos c
         JOIN prev_clientes cl ON cl.id = c.cliente_id
         JOIN prev_cuotas q ON q.contrato_id = c.id
              AND q.estado IN ('pendiente','parcial') AND q.fecha_vencimiento < CURDATE()
         WHERE c.estatus = 'activo'
         GROUP BY c.id, c.numero, c.moneda, cliente_nombre
         HAVING COUNT(q.id) >= ?
         ORDER BY cuotas_vencidas DESC, saldo_vencido DESC"
    );
    $st->execute([$minCuotas]);
    $afectados = $st->fetchAll();

    if (!$dryRun && $afectados) {
        $upd = db()->prepare(
            "UPDATE prev_contratos SET estatus = 'suspendido', fecha_estatus = CURDATE(),
                    motivo_estatus = ? WHERE id = ? AND estatus = 'activo'"
        );
        foreach ($afectados as $c) {
            $motivo = "Suspensión automática: {$c['cuotas_vencidas']} cuotas vencidas";
            $upd->execute([$motivo, (int)$c['id']]);
            audit('prev_contrato.auto_lapse', 'prev_contratos', (int)$c['id'],
                  ['numero' => $c['numero'], 'cuotas_vencidas' => (int)$c['cuotas_vencidas'],
                   'saldo_vencido' => (float)$c['saldo_vencido']]);
        }
    }

    return array_map(fn($c) => [
        'contrato_id'     => (int)$c['id'],
        'numero'          => $c['numero'],
        'cliente_nombre'  => $c['cliente_nombre'],
        'moneda'          => $c['moneda'],
        'cuotas_vencidas' => (int)$c['cuotas_vencidas'],
        'saldo_vencido'   => (float)$c['saldo_vencido'],
        'vencida_desde'   => $c['vencida_desde'],
    ], $afectados);
}

// ---------------------------------------------------------------------------
// Formateadores de salida (compartidos entre endpoints)
// ---------------------------------------------------------------------------

function prev_cliente_out(array $r): array
{
    return [
        'id'                  => (int)$r['id'],
        'tipo_persona'        => $r['tipo_persona'],
        'nacionalidad'        => $r['nacionalidad'],
        'cedula'              => $r['cedula'],
        'documento'           => $r['nacionalidad'] . '-' . $r['cedula'],
        'nombres'             => $r['nombres'],
        'apellidos'           => $r['apellidos'],
        'nombre_completo'     => trim($r['nombres'] . ' ' . $r['apellidos']),
        'fecha_nacimiento'    => $r['fecha_nacimiento'],
        'edad'                => prev_edad($r['fecha_nacimiento']),
        'sexo'                => $r['sexo'],
        'estado_civil'        => $r['estado_civil'],
        'telefono_habitacion' => $r['telefono_habitacion'],
        'telefono_celular'    => $r['telefono_celular'],
        'telefono_oficina'    => $r['telefono_oficina'],
        'email'               => $r['email'],
        'direccion'           => $r['direccion'],
        'ciudad'              => $r['ciudad'],
        'estado'              => $r['estado'],
        'municipio'           => $r['municipio'],
        'parroquia'           => $r['parroquia'],
        'empleador'           => $r['empleador'],
        'cargo'               => $r['cargo'],
        'profesion'           => $r['profesion'],
        'origen'              => $r['origen'],
        'info_adicional'      => $r['info_adicional'],
        'contratos'           => isset($r['contratos']) ? (int)$r['contratos'] : null,
        'eliminado'           => !empty($r['deleted_at']),
        'created_at'          => $r['created_at'] ?? null,
        'updated_at'          => $r['updated_at'] ?? null,
    ];
}

function prev_plan_out(array $r): array
{
    return [
        'id'                  => (int)$r['id'],
        'codigo'              => $r['codigo'],
        'nombre'              => $r['nombre'],
        'descripcion'         => $r['descripcion'],
        'moneda'              => $r['moneda'],
        'cuota_mensual'       => (float)$r['cuota_mensual'],
        'cuota_inicial'       => (float)$r['cuota_inicial'],
        'monto_servicio'      => (float)$r['monto_servicio'],
        'max_beneficiarios'   => (int)$r['max_beneficiarios'],
        'solo_nuevo_contrato' => (bool)$r['solo_nuevo_contrato'],
        'es_apoyo'            => (bool)$r['es_apoyo'],
        'bloqueado'           => (bool)$r['bloqueado'],
        'activo'              => (bool)$r['activo'],
        'contratos'           => isset($r['contratos']) ? (int)$r['contratos'] : null,
    ];
}

function prev_vendedor_out(array $r): array
{
    return [
        'id'               => (int)$r['id'],
        'cedula'           => $r['cedula'],
        'nombre'           => $r['nombre'],
        'telefono1'        => $r['telefono1'],
        'telefono2'        => $r['telefono2'],
        'email'            => $r['email'],
        'direccion'        => $r['direccion'],
        'sucursal_id'      => isset($r['sucursal_id']) && $r['sucursal_id'] !== null ? (int)$r['sucursal_id'] : null,
        'sucursal_nombre'  => $r['sucursal_nombre'] ?? null,
        'fecha_ingreso'    => $r['fecha_ingreso'],
        'fecha_retiro'     => $r['fecha_retiro'],
        'comision_semanal' => (float)$r['comision_semanal'],
        'comision_mensual' => (float)$r['comision_mensual'],
        'comision_anual'   => (float)$r['comision_anual'],
        'banco'            => $r['banco'],
        'numero_cuenta'    => $r['numero_cuenta'],
        'titular_cuenta'   => $r['titular_cuenta'],
        'cedula_cuenta'    => $r['cedula_cuenta'],
        'notas'            => $r['notas'],
        'activo'           => (bool)$r['activo'],
        'contratos'        => isset($r['contratos']) ? (int)$r['contratos'] : null,
    ];
}

function prev_contrato_out(array $r): array
{
    return [
        'id'                 => (int)$r['id'],
        'numero'             => $r['numero'],
        'cliente_id'         => (int)$r['cliente_id'],
        'cliente_nombre'     => $r['cliente_nombre'] ?? null,
        'cliente_cedula'     => $r['cliente_cedula'] ?? null,
        'plan_id'            => $r['plan_id'] !== null ? (int)$r['plan_id'] : null,
        'plan_nombre'        => $r['plan_nombre'] ?? null,
        'vendedor_id'        => $r['vendedor_id'] !== null ? (int)$r['vendedor_id'] : null,
        'vendedor_nombre'    => $r['vendedor_nombre'] ?? null,
        'sucursal_id'        => isset($r['sucursal_id']) && $r['sucursal_id'] !== null ? (int)$r['sucursal_id'] : null,
        'sucursal_nombre'    => $r['sucursal_nombre'] ?? null,
        'ruta_id'            => isset($r['ruta_id']) && $r['ruta_id'] !== null ? (int)$r['ruta_id'] : null,
        'ruta_nombre'        => $r['ruta_nombre'] ?? null,
        'origen'             => $r['origen'],
        'fecha_solicitud'    => $r['fecha_solicitud'],
        'fecha_ingreso'      => $r['fecha_ingreso'],
        'vigente_desde'      => $r['vigente_desde'],
        'plazo_espera_meses' => (int)$r['plazo_espera_meses'],
        'frecuencia_pago'    => $r['frecuencia_pago'],
        'forma_pago'         => $r['forma_pago'],
        'moneda'             => $r['moneda'],
        'cuota_inicial'      => (float)$r['cuota_inicial'],
        'monto_cuota'        => (float)$r['monto_cuota'],
        'numero_cuotas'      => (int)$r['numero_cuotas'],
        'comision_venta'     => (float)$r['comision_venta'],
        'edad_ingreso'       => $r['edad_ingreso'] !== null ? (int)$r['edad_ingreso'] : null,
        'banco'              => $r['banco'],
        'numero_cuenta'      => $r['numero_cuenta'],
        'titular_cuenta'     => $r['titular_cuenta'],
        'tipo_cuenta'        => $r['tipo_cuenta'],
        'estatus'            => $r['estatus'],
        'fecha_estatus'      => $r['fecha_estatus'],
        'motivo_estatus'     => $r['motivo_estatus'],
        'comentarios'        => $r['comentarios'],
        'beneficiarios'      => isset($r['beneficiarios']) ? (int)$r['beneficiarios'] : null,
        'cuotas_vencidas'    => isset($r['cuotas_vencidas']) ? (int)$r['cuotas_vencidas'] : null,
        'saldo_vencido'      => isset($r['saldo_vencido']) ? (float)$r['saldo_vencido'] : null,
        'created_at'         => $r['created_at'] ?? null,
        'updated_at'         => $r['updated_at'] ?? null,
    ];
}

function prev_beneficiario_out(array $r): array
{
    return [
        'id'                 => (int)$r['id'],
        'contrato_id'        => (int)$r['contrato_id'],
        'parentesco_id'      => (int)$r['parentesco_id'],
        'parentesco'         => $r['parentesco'] ?? null,
        'nacionalidad'       => $r['nacionalidad'],
        'cedula'             => $r['cedula'],
        'nombres'            => $r['nombres'],
        'apellidos'          => $r['apellidos'],
        'nombre_completo'    => trim($r['nombres'] . ' ' . $r['apellidos']),
        'fecha_nacimiento'   => $r['fecha_nacimiento'],
        'edad'               => prev_edad($r['fecha_nacimiento']),
        'sexo'               => $r['sexo'],
        'cuota_adicional'    => (float)$r['cuota_adicional'],
        'plazo_espera_meses' => $r['plazo_espera_meses'] !== null ? (int)$r['plazo_espera_meses'] : null,
        'estatus'            => $r['estatus'],
        'fecha_inclusion'    => $r['fecha_inclusion'],
        'fecha_exclusion'    => $r['fecha_exclusion'],
        'fecha_defuncion'    => $r['fecha_defuncion'],
        'fecha_suspension'   => $r['fecha_suspension'],
        'comentarios'        => $r['comentarios'],
    ];
}

function prev_cuota_out(array $r): array
{
    return [
        'id'                => (int)$r['id'],
        'contrato_id'       => (int)$r['contrato_id'],
        'contrato_numero'   => $r['contrato_numero'] ?? null,
        'numero'            => (int)$r['numero'],
        'tipo'              => $r['tipo'],
        'fecha_vencimiento' => $r['fecha_vencimiento'],
        'moneda'            => $r['moneda'],
        'monto'             => (float)$r['monto'],
        'saldo'             => (float)$r['saldo'],
        'estado'            => $r['estado'],
        'fecha_cobro'       => $r['fecha_cobro'],
        'generada_el'       => $r['generada_el'],
        'vencida'           => $r['estado'] !== 'cobrada' && $r['estado'] !== 'anulada'
                               && $r['fecha_vencimiento'] < date('Y-m-d'),
    ];
}

function prev_pago_out(array $r): array
{
    return [
        'id'            => (int)$r['id'],
        'contrato_id'   => (int)$r['contrato_id'],
        'contrato_numero' => $r['contrato_numero'] ?? null,
        'cuota_id'      => $r['cuota_id'] !== null ? (int)$r['cuota_id'] : null,
        'cuota_numero'  => isset($r['cuota_numero']) && $r['cuota_numero'] !== null ? (int)$r['cuota_numero'] : null,
        'fecha'         => $r['fecha'],
        'recibo'        => $r['recibo'],
        'moneda'        => $r['moneda'],
        'monto'         => (float)$r['monto'],
        'tasa'          => (float)$r['tasa'],
        'forma_pago'    => $r['forma_pago'],
        'referencia'    => $r['referencia'],
        'banco'         => $r['banco'],
        'observaciones' => $r['observaciones'],
        'created_at'    => $r['created_at'] ?? null,
    ];
}

function prev_siniestro_out(array $r): array
{
    return [
        'id'               => (int)$r['id'],
        'contrato_id'      => (int)$r['contrato_id'],
        'contrato_numero'  => $r['contrato_numero'] ?? null,
        'beneficiario_id'  => (int)$r['beneficiario_id'],
        'cedula_fallecido' => $r['cedula_fallecido'],
        'nombre_fallecido' => $r['nombre_fallecido'],
        'es_titular'       => (bool)$r['es_titular'],
        'parentesco'       => $r['parentesco'] ?? null,
        'fecha_defuncion'  => $r['fecha_defuncion'],
        'fecha_reporte'    => $r['fecha_reporte'],
        'reportado_por'    => $r['reportado_por'],
        'telefono_reporta' => $r['telefono_reporta'],
        'cobertura'        => $r['cobertura'],
        'validacion'       => $r['validacion'] ? json_decode($r['validacion'], true) : [],
        'estado'           => $r['estado'],
        'motivo_rechazo'   => $r['motivo_rechazo'],
        'moneda'           => $r['moneda'],
        'monto_total'      => (float)$r['monto_total'],
        'observaciones'    => $r['observaciones'],
        'cliente_nombre'   => $r['cliente_nombre'] ?? null,
        'plan_nombre'      => $r['plan_nombre'] ?? null,
        'created_at'       => $r['created_at'] ?? null,
    ];
}

function prev_sin_detalle_out(array $r): array
{
    return [
        'id'           => (int)$r['id'],
        'siniestro_id' => (int)$r['siniestro_id'],
        'tipo'         => $r['tipo'],
        'descripcion'  => $r['descripcion'],
        'proveedor'    => $r['proveedor'],
        'moneda'       => $r['moneda'],
        'monto'        => (float)$r['monto'],
        'pagado'       => (bool)$r['pagado'],
        'fecha_pago'   => $r['fecha_pago'],
        'notas'        => $r['notas'],
    ];
}

function prev_gestion_out(array $r): array
{
    return [
        'id'              => (int)$r['id'],
        'contrato_id'     => (int)$r['contrato_id'],
        'contrato_numero' => $r['contrato_numero'] ?? null,
        'cliente_nombre'  => $r['cliente_nombre'] ?? null,
        'fecha'           => $r['fecha'],
        'tipo'            => $r['tipo'],
        'resultado'       => $r['resultado'],
        'promesa_fecha'   => $r['promesa_fecha'],
        'promesa_monto'   => $r['promesa_monto'] !== null ? (float)$r['promesa_monto'] : null,
        'notas'           => $r['notas'],
        'usuario'         => $r['usuario'] ?? null,
    ];
}

function prev_servicio_out(array $r): array
{
    return [
        'id'          => (int)$r['id'],
        'nombre'      => $r['nombre'],
        'descripcion' => $r['descripcion'],
        'moneda'      => $r['moneda'],
        'precio'      => (float)$r['precio'],
        'recurrente'  => (bool)$r['recurrente'],
        'activo'      => (bool)$r['activo'],
    ];
}

function prev_comision_out(array $r): array
{
    return [
        'id'              => (int)$r['id'],
        'contrato_id'     => (int)$r['contrato_id'],
        'contrato_numero' => $r['contrato_numero'] ?? null,
        'vendedor_id'     => (int)$r['vendedor_id'],
        'vendedor_nombre' => $r['vendedor_nombre'] ?? null,
        'etapa'           => $r['etapa'],
        'monto_bs'        => (float)$r['monto_bs'],
        'tasa'            => (float)$r['tasa'],
        'monto_usd'       => (float)$r['monto_usd'],
        'fecha_pago'      => $r['fecha_pago'],
        'comentario'      => $r['comentario'],
        'created_at'      => $r['created_at'] ?? null,
    ];
}
