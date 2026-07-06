<?php
/**
 * API de Importación del Módulo de Previsión:  api/prevision_import.php?action=...
 *
 *   GET  plantilla  (?tipo=)  -> descarga la plantilla CSV del tipo indicado
 *   GET  lotes                -> historial de importaciones
 *   POST importar   (staff)   -> importa un CSV (multipart, campo "archivo")
 *                                params: tipo, simulacion=1 (solo validar)
 *
 * Tipos soportados: clientes | vendedores | contratos | beneficiarios | pagos
 *   - clientes / vendedores:  upsert por cédula
 *   - contratos:              upsert por número (el cliente debe existir o venir
 *                             en el mismo archivo de clientes importado antes)
 *   - beneficiarios:          se agregan al contrato por número
 *   - pagos:                  se registran como histórico (no tocan cuotas)
 *
 * El CSV puede venir separado por coma (,) o punto y coma (;). La primera fila
 * son los encabezados; se aceptan alias comunes (ver $ALIAS más abajo).
 * Fechas: AAAA-MM-DD o DD/MM/AAAA. Decimales con punto o coma.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'lotes';

const IMPORT_TIPOS = ['clientes', 'vendedores', 'contratos', 'beneficiarios', 'pagos'];
const IMPORT_MAX_FILAS = 5000;

/** Columnas por tipo: [columna => obligatoria]. */
const IMPORT_COLUMNAS = [
    'clientes' => [
        'cedula' => true, 'nombres' => true, 'apellidos' => false, 'nacionalidad' => false,
        'fecha_nacimiento' => false, 'sexo' => false, 'estado_civil' => false,
        'telefono_habitacion' => false, 'telefono_celular' => false, 'telefono_oficina' => false,
        'email' => false, 'direccion' => false, 'ciudad' => false, 'estado' => false,
        'municipio' => false, 'parroquia' => false, 'empleador' => false, 'cargo' => false,
        'profesion' => false, 'origen' => false, 'info_adicional' => false,
    ],
    'vendedores' => [
        'cedula' => false, 'nombre' => true, 'telefono1' => false, 'telefono2' => false,
        'email' => false, 'direccion' => false, 'fecha_ingreso' => false, 'fecha_retiro' => false,
        'comision_semanal' => false, 'comision_mensual' => false, 'comision_anual' => false,
        'banco' => false, 'numero_cuenta' => false, 'titular_cuenta' => false, 'cedula_cuenta' => false,
    ],
    'contratos' => [
        'numero' => true, 'cedula_cliente' => true, 'plan' => false, 'vendedor_cedula' => false,
        'vendedor_nombre' => false, 'fecha_ingreso' => true, 'fecha_solicitud' => false,
        'vigente_desde' => false, 'plazo_espera_meses' => false, 'frecuencia_pago' => false,
        'forma_pago' => false, 'moneda' => false, 'cuota_inicial' => false, 'monto_cuota' => false,
        'numero_cuotas' => false, 'comision_venta' => false, 'estatus' => false,
        'fecha_estatus' => false, 'motivo_estatus' => false, 'comentarios' => false,
        'banco' => false, 'numero_cuenta' => false, 'titular_cuenta' => false, 'origen' => false,
    ],
    'beneficiarios' => [
        'contrato_numero' => true, 'parentesco' => true, 'cedula' => false, 'nombres' => true,
        'apellidos' => false, 'nacionalidad' => false, 'fecha_nacimiento' => false, 'sexo' => false,
        'cuota_adicional' => false, 'estatus' => false, 'fecha_inclusion' => false,
        'fecha_exclusion' => false, 'fecha_defuncion' => false, 'comentarios' => false,
    ],
    'pagos' => [
        'contrato_numero' => true, 'fecha' => true, 'monto' => true, 'moneda' => false,
        'tasa' => false, 'forma_pago' => false, 'referencia' => false, 'banco' => false,
        'recibo' => false, 'observaciones' => false,
    ],
];

/** Alias de encabezados aceptados (ya normalizados) => columna canónica. */
const IMPORT_ALIAS = [
    'ci' => 'cedula', 'rif' => 'cedula', 'documento' => 'cedula', 'cedula_rif' => 'cedula',
    'nombre_completo' => 'nombres', 'primer_nombre' => 'nombres',
    'apellido' => 'apellidos', 'primer_apellido' => 'apellidos',
    'fecha_nac' => 'fecha_nacimiento', 'fechanacimiento' => 'fecha_nacimiento', 'nacimiento' => 'fecha_nacimiento',
    'telefono' => 'telefono_celular', 'celular' => 'telefono_celular', 'movil' => 'telefono_celular',
    'telefono_casa' => 'telefono_habitacion', 'habitacion' => 'telefono_habitacion',
    'correo' => 'email', 'e_mail' => 'email',
    'edocivil' => 'estado_civil', 'edo_civil' => 'estado_civil',
    'numero_contrato' => 'numero', 'contrato' => 'contrato_numero', 'nro_contrato' => 'contrato_numero',
    'cedula_titular' => 'cedula_cliente', 'ci_cliente' => 'cedula_cliente', 'titular' => 'cedula_cliente',
    'vendedor' => 'vendedor_nombre', 'ci_vendedor' => 'vendedor_cedula', 'cedula_vendedor' => 'vendedor_cedula',
    'plan_nombre' => 'plan', 'plan_codigo' => 'plan', 'codigo_plan' => 'plan',
    'fecha_afiliacion' => 'fecha_ingreso', 'fecha_contrato' => 'fecha_ingreso',
    'frecuencia' => 'frecuencia_pago', 'formapago' => 'forma_pago', 'forma_de_pago' => 'forma_pago',
    'cuota' => 'monto_cuota', 'cuota_mensual' => 'monto_cuota', 'monto_cuota_mensual' => 'monto_cuota',
    'cuotas' => 'numero_cuotas', 'comision' => 'comision_venta',
    'estado_contrato' => 'estatus', 'status' => 'estatus',
    'monto_pago' => 'monto', 'importe' => 'monto', 'fecha_pago' => 'fecha',
    'nro_recibo' => 'recibo', 'ref' => 'referencia', 'nro_referencia' => 'referencia',
    'cuenta' => 'numero_cuenta', 'nro_cuenta' => 'numero_cuenta',
    'obs' => 'observaciones', 'observacion' => 'observaciones', 'comentario' => 'comentarios', 'nota' => 'comentarios',
];

/** Normaliza un encabezado: minúsculas, sin acentos, no alfanumérico -> "_". */
function import_norm(string $h): string
{
    $h = mb_strtolower(trim($h), 'UTF-8');
    $h = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $h);
    $h = preg_replace('/[^a-z0-9]+/', '_', $h) ?? '';
    return trim($h, '_');
}

/** Interpreta frecuencia / forma de pago / estatus / moneda con texto libre. */
function import_frecuencia(string $v): string
{
    $v = import_norm($v);
    foreach (['seman' => 'semanal', 'quincen' => 'quincenal', 'trimes' => 'trimestral',
              'semes' => 'semestral', 'anual' => 'anual', 'mensual' => 'mensual', 'mes' => 'mensual'] as $k => $out) {
        if ($v !== '' && str_contains($v, $k)) return $out;
    }
    return 'mensual';
}
function import_forma_pago_contrato(string $v): string
{
    $v = import_norm($v);
    foreach (['domicil' => 'domiciliacion', 'taquilla' => 'caja', 'caja' => 'caja',
              'transfer' => 'transferencia', 'movil' => 'pago_movil', 'cobrador' => 'cobrador',
              'colect' => 'cobrador'] as $k => $out) {
        if ($v !== '' && str_contains($v, $k)) return $out;
    }
    return 'caja';
}
function import_forma_pago_pago(string $v): string
{
    $v = import_norm($v);
    foreach (['transfer' => 'transferencia', 'movil' => 'pago_movil', 'punto' => 'punto',
              'zelle' => 'zelle', 'divisa' => 'divisa', 'dolar' => 'divisa', 'efectivo' => 'efectivo'] as $k => $out) {
        if ($v !== '' && str_contains($v, $k)) return $out;
    }
    return 'efectivo';
}
function import_estatus_contrato(string $v): string
{
    $v = import_norm($v);
    foreach (['anul' => 'anulado', 'suspend' => 'suspendido', 'renunc' => 'renuncia', 'activ' => 'activo'] as $k => $out) {
        if ($v !== '' && str_contains($v, $k)) return $out;
    }
    return 'activo';
}
function import_moneda(string $v): string
{
    $v = import_norm($v);
    if ($v === '' ) return 'USD';
    foreach (['bs' => 'BS', 'boliv' => 'BS', 'ves' => 'BS', 'usd' => 'USD', 'dolar' => 'USD', 'divisa' => 'USD'] as $k => $out) {
        if (str_contains($v, $k)) return $out;
    }
    return 'USD';
}

/** Lee el CSV subido y devuelve [headers_normalizados, filas]. */
function import_leer_csv(string $tmpFile): array
{
    $raw = file_get_contents($tmpFile);
    if ($raw === false || $raw === '') json_out(['ok' => false, 'error' => 'El archivo está vacío.'], 422);
    // BOM y codificación
    if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');

    $lineas = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $primera = '';
    foreach ($lineas as $l) { if (trim($l) !== '') { $primera = $l; break; } }
    if ($primera === '') json_out(['ok' => false, 'error' => 'El archivo no tiene contenido.'], 422);
    // Detecta el delimitador por el encabezado
    $delim = ',';
    foreach ([';', "\t", ','] as $d) {
        if (substr_count($primera, $d) > substr_count($primera, $delim)) $delim = $d;
    }

    $headers = [];
    $rows = [];
    foreach ($lineas as $i => $linea) {
        if (trim($linea) === '') continue;
        $campos = str_getcsv($linea, $delim, '"', '\\');
        if (!$headers) {
            $headers = array_map(fn($h) => IMPORT_ALIAS[import_norm((string)$h)] ?? import_norm((string)$h), $campos);
            continue;
        }
        $fila = [];
        foreach ($headers as $j => $h) {
            if ($h === '') continue;
            $fila[$h] = trim((string)($campos[$j] ?? ''));
        }
        $rows[] = ['linea' => $i + 1, 'datos' => $fila];
        if (count($rows) > IMPORT_MAX_FILAS) {
            json_out(['ok' => false, 'error' => 'Máximo ' . IMPORT_MAX_FILAS . ' filas por importación.'], 422);
        }
    }
    return [$headers, $rows];
}

switch ($action) {

    case 'plantilla': {
        require_method('GET');
        require_role('admin', 'editor');
        $tipo = prev_enum($_GET['tipo'] ?? '', IMPORT_TIPOS);
        if (!$tipo) json_out(['ok' => false, 'error' => 'Tipo no válido.'], 422);
        $cols = array_keys(IMPORT_COLUMNAS[$tipo]);
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=plantilla_$tipo.csv");
        echo "\xEF\xBB\xBF" . implode(';', $cols) . "\r\n";
        exit;
    }

    case 'lotes': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query(
            "SELECT l.*, u.email AS usuario FROM prev_import_lotes l
             LEFT JOIN users u ON u.id = l.usuario_id
             ORDER BY l.id DESC LIMIT 50"
        );
        $items = array_map(fn($r) => [
            'id'           => (int)$r['id'],
            'tipo'         => $r['tipo'],
            'archivo'      => $r['nombre_archivo'],
            'simulacion'   => (bool)$r['simulacion'],
            'total_filas'  => (int)$r['total_filas'],
            'insertados'   => (int)$r['insertados'],
            'actualizados' => (int)$r['actualizados'],
            'rechazados'   => (int)$r['rechazados'],
            'errores'      => $r['errores'] ? json_decode($r['errores'], true) : [],
            'usuario'      => $r['usuario'],
            'fecha'        => $r['created_at'],
        ], $st->fetchAll());
        json_out(['ok' => true, 'items' => $items]);
    }

    case 'importar': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $tipo = prev_enum($_POST['tipo'] ?? '', IMPORT_TIPOS);
        if (!$tipo) json_out(['ok' => false, 'error' => 'Indique el tipo de importación.'], 422);
        $simulacion = (($_POST['simulacion'] ?? '') === '1');

        if (empty($_FILES['archivo']['tmp_name']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
            json_out(['ok' => false, 'error' => 'Adjunte el archivo CSV en el campo "archivo".'], 422);
        }
        $nombreArchivo = clean_str($_FILES['archivo']['name'] ?? '', 255);
        [$headers, $rows] = import_leer_csv($_FILES['archivo']['tmp_name']);

        // Encabezados obligatorios presentes
        foreach (IMPORT_COLUMNAS[$tipo] as $col => $obligatoria) {
            if ($obligatoria && !in_array($col, $headers, true)) {
                json_out(['ok' => false, 'error' => "Falta la columna obligatoria \"$col\". Columnas detectadas: "
                          . implode(', ', array_filter($headers))], 422);
            }
        }

        $pdo = db();
        $ins = 0; $upd = 0; $rech = 0;
        $errores = [];
        $vistos = [];  // claves únicas dentro del mismo archivo

        $pdo->beginTransaction();
        try {
            foreach ($rows as $fila) {
                $n = $fila['linea'];
                $d = $fila['datos'];
                try {
                    switch ($tipo) {

                    case 'clientes': {
                        $cedula = prev_cedula($d['cedula'] ?? '');
                        $nombres = clean_str($d['nombres'] ?? '', 100);
                        if ($cedula === '' || $nombres === '') throw new RuntimeException('cédula y nombres son obligatorios');
                        if (isset($vistos[$cedula])) throw new RuntimeException("cédula $cedula repetida en el archivo");
                        $vistos[$cedula] = true;
                        $sexo = strtoupper(substr(trim($d['sexo'] ?? ''), 0, 1));
                        $vals = [
                            'nacionalidad'        => prev_nacionalidad($d['nacionalidad'] ?? 'V'),
                            'nombres'             => $nombres,
                            'apellidos'           => clean_str($d['apellidos'] ?? '', 100),
                            'fecha_nacimiento'    => prev_date($d['fecha_nacimiento'] ?? ''),
                            'sexo'                => in_array($sexo, ['M', 'F'], true) ? $sexo : null,
                            'estado_civil'        => clean_str($d['estado_civil'] ?? '', 30) ?: null,
                            'telefono_habitacion' => clean_str($d['telefono_habitacion'] ?? '', 20) ?: null,
                            'telefono_celular'    => clean_str($d['telefono_celular'] ?? '', 20) ?: null,
                            'telefono_oficina'    => clean_str($d['telefono_oficina'] ?? '', 20) ?: null,
                            'email'               => clean_str($d['email'] ?? '', 190) ?: null,
                            'direccion'           => clean_str($d['direccion'] ?? '', 255) ?: null,
                            'ciudad'              => clean_str($d['ciudad'] ?? '', 100) ?: null,
                            'estado'              => clean_str($d['estado'] ?? '', 100) ?: null,
                            'municipio'           => clean_str($d['municipio'] ?? '', 100) ?: null,
                            'parroquia'           => clean_str($d['parroquia'] ?? '', 100) ?: null,
                            'empleador'           => clean_str($d['empleador'] ?? '', 200) ?: null,
                            'cargo'               => clean_str($d['cargo'] ?? '', 100) ?: null,
                            'profesion'           => clean_str($d['profesion'] ?? '', 100) ?: null,
                            'origen'              => clean_str($d['origen'] ?? '', 60) ?: null,
                            'info_adicional'      => clean_str($d['info_adicional'] ?? '', 500) ?: null,
                        ];
                        $st = $pdo->prepare("SELECT id FROM prev_clientes WHERE cedula = ?");
                        $st->execute([$cedula]);
                        if ($idExist = $st->fetchColumn()) {
                            $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($vals)));
                            $pdo->prepare("UPDATE prev_clientes SET $sets, updated_by = ? WHERE id = ?")
                                ->execute([...array_values($vals), $u['id'], (int)$idExist]);
                            $upd++;
                        } else {
                            $cols = array_keys($vals);
                            $pdo->prepare(
                                "INSERT INTO prev_clientes (cedula, " . implode(',', $cols) . ", created_by, updated_by)
                                 VALUES (?, " . rtrim(str_repeat('?,', count($cols)), ',') . ", ?, ?)"
                            )->execute([$cedula, ...array_values($vals), $u['id'], $u['id']]);
                            $ins++;
                        }
                        break;
                    }

                    case 'vendedores': {
                        $nombre = clean_str($d['nombre'] ?? '', 100);
                        if ($nombre === '') throw new RuntimeException('nombre es obligatorio');
                        $cedula = prev_cedula($d['cedula'] ?? '') ?: null;
                        if ($cedula && isset($vistos[$cedula])) throw new RuntimeException("cédula $cedula repetida en el archivo");
                        if ($cedula) $vistos[$cedula] = true;
                        $pct = fn($v) => max(0.0, min(100.0, round((float)str_replace(',', '.', (string)$v), 2)));
                        $vals = [
                            'nombre'           => $nombre,
                            'telefono1'        => clean_str($d['telefono1'] ?? '', 20) ?: null,
                            'telefono2'        => clean_str($d['telefono2'] ?? '', 20) ?: null,
                            'email'            => clean_str($d['email'] ?? '', 190) ?: null,
                            'direccion'        => clean_str($d['direccion'] ?? '', 255) ?: null,
                            'fecha_ingreso'    => prev_date($d['fecha_ingreso'] ?? ''),
                            'fecha_retiro'     => prev_date($d['fecha_retiro'] ?? ''),
                            'comision_semanal' => $pct($d['comision_semanal'] ?? 0),
                            'comision_mensual' => $pct($d['comision_mensual'] ?? 0),
                            'comision_anual'   => $pct($d['comision_anual'] ?? 0),
                            'banco'            => clean_str($d['banco'] ?? '', 100) ?: null,
                            'numero_cuenta'    => clean_str($d['numero_cuenta'] ?? '', 24) ?: null,
                            'titular_cuenta'   => clean_str($d['titular_cuenta'] ?? '', 100) ?: null,
                            'cedula_cuenta'    => prev_cedula($d['cedula_cuenta'] ?? '') ?: null,
                        ];
                        $idExist = null;
                        if ($cedula) {
                            $st = $pdo->prepare("SELECT id FROM prev_vendedores WHERE cedula = ?");
                            $st->execute([$cedula]);
                            $idExist = $st->fetchColumn();
                        }
                        if ($idExist) {
                            $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($vals)));
                            $pdo->prepare("UPDATE prev_vendedores SET $sets WHERE id = ?")
                                ->execute([...array_values($vals), (int)$idExist]);
                            $upd++;
                        } else {
                            $cols = array_keys($vals);
                            $pdo->prepare(
                                "INSERT INTO prev_vendedores (cedula, " . implode(',', $cols) . ", activo)
                                 VALUES (?, " . rtrim(str_repeat('?,', count($cols)), ',') . ", ?)"
                            )->execute([$cedula, ...array_values($vals), $vals['fecha_retiro'] ? 0 : 1]);
                            $ins++;
                        }
                        break;
                    }

                    case 'contratos': {
                        $numero = clean_str($d['numero'] ?? '', 20);
                        $cedCliente = prev_cedula($d['cedula_cliente'] ?? '');
                        $fechaIngreso = prev_date($d['fecha_ingreso'] ?? '');
                        if ($numero === '' || $cedCliente === '' || !$fechaIngreso) {
                            throw new RuntimeException('numero, cedula_cliente y fecha_ingreso son obligatorios');
                        }
                        if (isset($vistos[$numero])) throw new RuntimeException("contrato $numero repetido en el archivo");
                        $vistos[$numero] = true;

                        $st = $pdo->prepare("SELECT * FROM prev_clientes WHERE cedula = ?");
                        $st->execute([$cedCliente]);
                        $cliente = $st->fetch();
                        if (!$cliente) throw new RuntimeException("no existe el cliente con cédula $cedCliente (impórtelo primero)");

                        $planId = null;
                        $plan = null;
                        if (($p = trim($d['plan'] ?? '')) !== '') {
                            $st = $pdo->prepare("SELECT * FROM prev_planes WHERE nombre = ? OR codigo = ? LIMIT 1");
                            $st->execute([$p, $p]);
                            $plan = $st->fetch();
                            if (!$plan) throw new RuntimeException("no existe el plan \"$p\"");
                            $planId = (int)$plan['id'];
                        }
                        $vendedorId = null;
                        if (($vc = prev_cedula($d['vendedor_cedula'] ?? '')) !== '') {
                            $st = $pdo->prepare("SELECT id FROM prev_vendedores WHERE cedula = ?");
                            $st->execute([$vc]);
                            $vendedorId = $st->fetchColumn() ?: null;
                            if (!$vendedorId) throw new RuntimeException("no existe el vendedor con cédula $vc");
                        } elseif (($vn = trim($d['vendedor_nombre'] ?? '')) !== '') {
                            $st = $pdo->prepare("SELECT id FROM prev_vendedores WHERE nombre = ? LIMIT 1");
                            $st->execute([$vn]);
                            $vendedorId = $st->fetchColumn() ?: null;
                        }

                        $plazo = max(0, min(60, (int)($d['plazo_espera_meses'] ?? 4)));
                        $moneda = import_moneda($d['moneda'] ?? ($plan['moneda'] ?? 'USD'));
                        $montoCuota = prev_money($d['monto_cuota'] ?? 0);
                        if ($montoCuota <= 0 && $plan) $montoCuota = (float)$plan['cuota_mensual'];
                        $estatus = import_estatus_contrato($d['estatus'] ?? 'activo');
                        $vals = [
                            'cliente_id'         => (int)$cliente['id'],
                            'plan_id'            => $planId,
                            'vendedor_id'        => $vendedorId ? (int)$vendedorId : null,
                            'origen'             => clean_str($d['origen'] ?? '', 60) ?: null,
                            'fecha_solicitud'    => prev_date($d['fecha_solicitud'] ?? ''),
                            'fecha_ingreso'      => $fechaIngreso,
                            'vigente_desde'      => prev_date($d['vigente_desde'] ?? '')
                                                    ?: (new DateTime($fechaIngreso))->modify("+$plazo months")->format('Y-m-d'),
                            'plazo_espera_meses' => $plazo,
                            'frecuencia_pago'    => import_frecuencia($d['frecuencia_pago'] ?? ''),
                            'forma_pago'         => import_forma_pago_contrato($d['forma_pago'] ?? ''),
                            'moneda'             => $moneda,
                            'cuota_inicial'      => prev_money($d['cuota_inicial'] ?? 0),
                            'monto_cuota'        => $montoCuota,
                            'numero_cuotas'      => max(0, (int)($d['numero_cuotas'] ?? 0)),
                            'comision_venta'     => max(0.0, min(100.0, round((float)str_replace(',', '.', (string)($d['comision_venta'] ?? 0)), 2))),
                            'edad_ingreso'       => prev_edad($cliente['fecha_nacimiento'], $fechaIngreso),
                            'banco'              => clean_str($d['banco'] ?? '', 100) ?: null,
                            'numero_cuenta'      => clean_str($d['numero_cuenta'] ?? '', 24) ?: null,
                            'titular_cuenta'     => clean_str($d['titular_cuenta'] ?? '', 100) ?: null,
                            'estatus'            => $estatus,
                            'fecha_estatus'      => prev_date($d['fecha_estatus'] ?? '') ?: $fechaIngreso,
                            'motivo_estatus'     => clean_str($d['motivo_estatus'] ?? '', 255) ?: null,
                            'comentarios'        => clean_str($d['comentarios'] ?? '', 500) ?: null,
                        ];
                        $st = $pdo->prepare("SELECT id FROM prev_contratos WHERE numero = ?");
                        $st->execute([$numero]);
                        if ($idExist = $st->fetchColumn()) {
                            $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($vals)));
                            $pdo->prepare("UPDATE prev_contratos SET $sets, updated_by = ? WHERE id = ?")
                                ->execute([...array_values($vals), $u['id'], (int)$idExist]);
                            $upd++;
                        } else {
                            $cols = array_keys($vals);
                            $pdo->prepare(
                                "INSERT INTO prev_contratos (numero, " . implode(',', $cols) . ", created_by, updated_by)
                                 VALUES (?, " . rtrim(str_repeat('?,', count($cols)), ',') . ", ?, ?)"
                            )->execute([$numero, ...array_values($vals), $u['id'], $u['id']]);
                            $contratoId = (int)$pdo->lastInsertId();
                            // Titular como primer beneficiario
                            $pdo->prepare(
                                "INSERT INTO prev_beneficiarios
                                 (contrato_id, parentesco_id, nacionalidad, cedula, nombres, apellidos,
                                  fecha_nacimiento, sexo, fecha_inclusion)
                                 VALUES (?,1,?,?,?,?,?,?,?)"
                            )->execute([
                                $contratoId, $cliente['nacionalidad'], $cliente['cedula'], $cliente['nombres'],
                                $cliente['apellidos'], $cliente['fecha_nacimiento'], $cliente['sexo'], $fechaIngreso,
                            ]);
                            $ins++;
                        }
                        break;
                    }

                    case 'beneficiarios': {
                        $numContrato = clean_str($d['contrato_numero'] ?? '', 20);
                        $nombres = clean_str($d['nombres'] ?? '', 100);
                        $paTxt = trim($d['parentesco'] ?? '');
                        if ($numContrato === '' || $nombres === '' || $paTxt === '') {
                            throw new RuntimeException('contrato_numero, parentesco y nombres son obligatorios');
                        }
                        $st = $pdo->prepare("SELECT id FROM prev_contratos WHERE numero = ?");
                        $st->execute([$numContrato]);
                        $contratoId = $st->fetchColumn();
                        if (!$contratoId) throw new RuntimeException("no existe el contrato $numContrato");

                        if (ctype_digit($paTxt)) {
                            $st = $pdo->prepare("SELECT id FROM prev_parentescos WHERE id = ?");
                            $st->execute([(int)$paTxt]);
                        } else {
                            $st = $pdo->prepare(
                                "SELECT id FROM prev_parentescos WHERE nombre = ? OR nombre LIKE ?
                                 ORDER BY (nombre = ?) DESC LIMIT 1"
                            );
                            $st->execute([$paTxt, $paTxt . '%', $paTxt]);
                        }
                        $parentescoId = $st->fetchColumn();
                        if (!$parentescoId) throw new RuntimeException("parentesco \"$paTxt\" no reconocido");

                        $cedula = prev_cedula($d['cedula'] ?? '') ?: null;
                        $sexo = strtoupper(substr(trim($d['sexo'] ?? ''), 0, 1));
                        $estatusMap = ['exclu' => 'excluido', 'fallec' => 'fallecido', 'suspend' => 'suspendido'];
                        $estatus = 'activo';
                        foreach ($estatusMap as $k => $v) {
                            if (str_contains(import_norm($d['estatus'] ?? ''), $k)) { $estatus = $v; break; }
                        }
                        // ¿Ya existe? (por cédula o por nombre dentro del contrato)
                        if ($cedula) {
                            $st = $pdo->prepare("SELECT id FROM prev_beneficiarios WHERE contrato_id = ? AND cedula = ?");
                            $st->execute([(int)$contratoId, $cedula]);
                        } else {
                            $st = $pdo->prepare(
                                "SELECT id FROM prev_beneficiarios
                                 WHERE contrato_id = ? AND nombres = ? AND apellidos = ? AND parentesco_id = ?"
                            );
                            $st->execute([(int)$contratoId, $nombres, clean_str($d['apellidos'] ?? '', 100), (int)$parentescoId]);
                        }
                        $vals = [
                            'parentesco_id'    => (int)$parentescoId,
                            'nacionalidad'     => prev_nacionalidad($d['nacionalidad'] ?? 'V'),
                            'cedula'           => $cedula,
                            'nombres'          => $nombres,
                            'apellidos'        => clean_str($d['apellidos'] ?? '', 100),
                            'fecha_nacimiento' => prev_date($d['fecha_nacimiento'] ?? ''),
                            'sexo'             => in_array($sexo, ['M', 'F'], true) ? $sexo : null,
                            'cuota_adicional'  => prev_money($d['cuota_adicional'] ?? 0),
                            'estatus'          => $estatus,
                            'fecha_inclusion'  => prev_date($d['fecha_inclusion'] ?? ''),
                            'fecha_exclusion'  => prev_date($d['fecha_exclusion'] ?? ''),
                            'fecha_defuncion'  => prev_date($d['fecha_defuncion'] ?? ''),
                            'comentarios'      => clean_str($d['comentarios'] ?? '', 500) ?: null,
                        ];
                        if ($idExist = $st->fetchColumn()) {
                            $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($vals)));
                            $pdo->prepare("UPDATE prev_beneficiarios SET $sets WHERE id = ?")
                                ->execute([...array_values($vals), (int)$idExist]);
                            $upd++;
                        } else {
                            $cols = array_keys($vals);
                            $pdo->prepare(
                                "INSERT INTO prev_beneficiarios (contrato_id, " . implode(',', $cols) . ")
                                 VALUES (?, " . rtrim(str_repeat('?,', count($cols)), ',') . ")"
                            )->execute([(int)$contratoId, ...array_values($vals)]);
                            $ins++;
                        }
                        break;
                    }

                    case 'pagos': {
                        $numContrato = clean_str($d['contrato_numero'] ?? '', 20);
                        $fecha = prev_date($d['fecha'] ?? '');
                        $monto = prev_money($d['monto'] ?? 0);
                        if ($numContrato === '' || !$fecha || $monto <= 0) {
                            throw new RuntimeException('contrato_numero, fecha y monto son obligatorios');
                        }
                        $st = $pdo->prepare("SELECT id, moneda FROM prev_contratos WHERE numero = ?");
                        $st->execute([$numContrato]);
                        $c = $st->fetch();
                        if (!$c) throw new RuntimeException("no existe el contrato $numContrato");
                        $pdo->prepare(
                            "INSERT INTO prev_pagos
                             (contrato_id, cuota_id, fecha, recibo, moneda, monto, tasa, forma_pago,
                              referencia, banco, observaciones, registrado_por)
                             VALUES (?,NULL,?,?,?,?,?,?,?,?,?,?)"
                        )->execute([
                            (int)$c['id'], $fecha, clean_str($d['recibo'] ?? '', 20) ?: null,
                            import_moneda($d['moneda'] ?? $c['moneda']), $monto,
                            round((float)str_replace(',', '.', (string)($d['tasa'] ?? 0)), 4),
                            import_forma_pago_pago($d['forma_pago'] ?? ''),
                            clean_str($d['referencia'] ?? '', 60) ?: null,
                            clean_str($d['banco'] ?? '', 100) ?: null,
                            trim('Importado. ' . clean_str($d['observaciones'] ?? '', 200)),
                            $u['id'],
                        ]);
                        $ins++;
                        break;
                    }
                    }
                } catch (\RuntimeException $e) {
                    $rech++;
                    if (count($errores) < 200) $errores[] = ['fila' => $n, 'error' => $e->getMessage()];
                }
            }

            if ($simulacion) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        // Bitácora del lote (fuera de la transacción del contenido)
        db()->prepare(
            "INSERT INTO prev_import_lotes
             (tipo, nombre_archivo, simulacion, total_filas, insertados, actualizados, rechazados, errores, usuario_id)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $tipo, $nombreArchivo ?: null, $simulacion ? 1 : 0, count($rows), $ins, $upd, $rech,
            $errores ? json_encode($errores, JSON_UNESCAPED_UNICODE) : null, $u['id'],
        ]);
        audit('prev_import.' . ($simulacion ? 'simular' : 'importar'), 'prev_import_lotes', null,
              ['tipo' => $tipo, 'archivo' => $nombreArchivo, 'filas' => count($rows),
               'insertados' => $ins, 'actualizados' => $upd, 'rechazados' => $rech]);

        json_out(['ok' => true, 'simulacion' => $simulacion, 'total_filas' => count($rows),
                  'insertados' => $ins, 'actualizados' => $upd, 'rechazados' => $rech, 'errores' => $errores]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
