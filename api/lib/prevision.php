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
const PREV_ESTATUS_CONTRATO    = ['activo', 'suspendido', 'anulado', 'renuncia'];
const PREV_ESTATUS_BENEFICIARIO = ['activo', 'suspendido', 'excluido', 'fallecido'];
const PREV_TIPOS_CUOTA         = ['inicial', 'programada', 'especial', 'mora', 'final'];
const PREV_ETAPAS_COMISION     = ['semana1', 'fin_mes1', 'mes2', 'mes13'];

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
