<?php

// Incluye el autoload de Composer
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Aumentar los límites de tiempo de ejecución y memoria para procesar archivos grandes
ini_set('max_execution_time', 300); // 5 minutos
ini_set('memory_limit', '512M');    // 512 Megabytes

// Función para limpiar y estandarizar los nombres de las columnas
function cleanColumnName($name) {
    // Elimina el BOM (Byte Order Mark) de UTF-8 si está presente
    $name = ltrim($name, "\xEF\xBB\xBF");
    // Limpia espacios en blanco al inicio/final y reemplaza múltiples espacios con uno
    return trim(preg_replace('/\s+/', ' ', $name));
}

// Función para formatear un número sin decimales
function formatNumber($value) {
    return is_numeric($value) ? number_format($value, 0, '', '') : '-';
}

// Verifica si se ha subido un archivo
if (!isset($_FILES['reporte_file']) || $_FILES['reporte_file']['error'] != UPLOAD_ERR_OK) {
    die("Error: No se ha subido ningún archivo o ha ocurrido un error.");
}

$file = $_FILES['reporte_file']['tmp_name'];
$data = [];

// Intenta leer el archivo con PhpSpreadsheet
try {
    $spreadsheet = IOFactory::load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data_raw = $worksheet->toArray(null, true, true, true);
    
    // El primer elemento del array es el encabezado
    $header_raw = array_shift($data_raw);
    $header = array_map('cleanColumnName', $header_raw);
    
    // Mapea los nombres de las columnas a sus índices para una búsqueda fiable
    $col_map = array_flip($header);
    
    // El resto es la data
    $data = $data_raw;

} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    die("Error al cargar el archivo: " . $e->getMessage());
}

// Identifica las columnas por su índice
$col_concurso = $col_map['Concurso'] ?? null;
$col_acta = $col_map['Acta'] ?? null;
$col_tipo = $col_map['Tipo'] ?? null;
$col_profesional_experto = $col_map['Profesional Experto'] ?? null;
$col_rut_principal = $col_map['RUT'] ?? null;
$col_segundo_profesional_experto = $col_map['Segundo Profesional Experto'] ?? null;

// El segundo RUT está después de 'Segundo Profesional Experto' en la posición 23 del array de encabezados
// Dado que toArray() devuelve un array asociativo por defecto si se le pasa el segundo parámetro en true, 
// es mejor buscar el segundo RUT por nombre de columna si existe. Si no, se puede usar un índice fijo.
// Se asume que la columna 23 (W) en el archivo original es el RUT del segundo profesional.
// En un array 1-indexed (como lo devuelve toArray), sería el índice 'W'.
$col_rut_segundo = 'X'; // Asumiendo que está en la columna 24

// Verifica que todas las columnas necesarias existan
$required_cols = ['Concurso', 'Acta', 'Tipo', 'Profesional Experto', 'RUT', 'Segundo Profesional Experto'];
foreach ($required_cols as $col_name) {
    if (!isset($col_map[$col_name])) {
        die("Error: La columna '$col_name' no se encuentra en el archivo. Por favor, asegúrate de que el encabezado sea correcto.");
    }
}


// ====================================================================================
// ANÁLISIS 1: SESIONES REPETIDAS
// ====================================================================================
$duplicate_sessions = [];
$session_groups = [];

foreach ($data as $row) {
    // Si la fila está vacía, la salta
    if (empty(array_filter($row))) {
        continue;
    }
    $tipo = cleanColumnName($row[$col_tipo]);
    if (in_array($tipo, ['ENTREGA DE LINEAMIENTOS', 'PRE-APROBACIÓN'])) {
        $key = $row[$col_concurso] . '|' . $tipo;
        if (!isset($session_groups[$key])) {
            $session_groups[$key] = [];
        }
        $session_groups[$key][] = $row[$col_acta];
    }
}

foreach ($session_groups as $key => $actas) {
    $unique_actas = array_unique($actas);
    if (count($unique_actas) > 1) {
        list($concurso, $tipo) = explode('|', $key);
        $duplicate_sessions[] = [
            'concurso' => $concurso,
            'tipo' => $tipo,
            'actas' => $unique_actas
        ];
    }
}

// ====================================================================================
// ANÁLISIS 2: REPORTE DE PROFESIONALES EXPERTOS
// ====================================================================================
$expert_sessions = [];
$total_prof_experto = 0;
$total_segundo_prof_experto = 0;

foreach ($data as $row) {
    // Si la fila está vacía, la salta
    if (empty(array_filter($row))) {
        continue;
    }

    // Profesional Experto
    $prof_experto_rut = $row[$col_rut_principal];
    $prof_experto_nombre = $row[$col_profesional_experto];
    if (!empty($prof_experto_rut) && !empty($prof_experto_nombre)) {
        if (!isset($expert_sessions[$prof_experto_rut])) {
            $expert_sessions[$prof_experto_rut] = [
                'profesional' => cleanColumnName($prof_experto_nombre),
                'rut' => cleanColumnName($prof_experto_rut),
                'sesiones_prof_experto' => 0,
                'sesiones_segundo_prof_experto' => 0,
                'total_sesiones' => 0
            ];
        }
        $expert_sessions[$prof_experto_rut]['sesiones_prof_experto']++;
    }

    // Segundo Profesional Experto
    $segundo_prof_experto_rut = $row[$col_rut_segundo] ?? null;
    $segundo_prof_experto_nombre = $row[$col_segundo_profesional_experto];
    if (!empty($segundo_prof_experto_rut) && !empty($segundo_prof_experto_nombre)) {
        if (!isset($expert_sessions[$segundo_prof_experto_rut])) {
            $expert_sessions[$segundo_prof_experto_rut] = [
                'profesional' => cleanColumnName($segundo_prof_experto_nombre),
                'rut' => cleanColumnName($segundo_prof_experto_rut),
                'sesiones_prof_experto' => 0,
                'sesiones_segundo_prof_experto' => 0,
                'total_sesiones' => 0
            ];
        }
        $expert_sessions[$segundo_prof_experto_rut]['sesiones_segundo_prof_experto']++;
    }
}

// Calculo de totales y observaciones
$report_table = [];
foreach ($expert_sessions as $rut => $session) {
    $total = $session['sesiones_prof_experto'] + $session['sesiones_segundo_prof_experto'];
    $observacion = ($total > 12) ? 'supera el tope' : '';

    $report_table[] = [
        'profesional' => $session['profesional'],
        'rut' => $session['rut'],
        'sesiones_prof_experto' => $session['sesiones_prof_experto'],
        'sesiones_segundo_prof_experto' => $session['sesiones_segundo_prof_experto'],
        'total_sesiones' => $total,
        'observacion' => $observacion
    ];

    $total_prof_experto += $session['sesiones_prof_experto'];
    $total_segundo_prof_experto += $session['sesiones_segundo_prof_experto'];
}

// Ordenar la tabla por el nombre del profesional
usort($report_table, function($a, $b) {
    return strcmp($a['profesional'], $b['profesional']);
});

// Agregar la fila de totales
$total_sesiones_general = $total_prof_experto + $total_segundo_prof_experto;
$report_table[] = [
    'profesional' => 'Total general',
    'rut' => '-',
    'sesiones_prof_experto' => $total_prof_experto,
    'sesiones_segundo_prof_experto' => $total_segundo_prof_experto,
    'total_sesiones' => $total_sesiones_general,
    'observacion' => ''
];

// Iniciar la salida HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados del Análisis</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .total-row {
            font-weight: bold;
            background-color: #e9e9e9;
        }
        .no-duplicates {
            color: green;
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .back-button {
            display: block;
            width: 200px;
            margin: 30px auto 0;
            text-align: center;
            padding: 10px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Resultados del Análisis</h1>

        <h2>Análisis de Sesiones Repetidas</h2>
        <?php if (empty($duplicate_sessions)): ?>
            <p class="no-duplicates">No se encontraron sesiones repetidas para los tipos 'ENTREGA DE LINEAMIENTOS' y 'PRE-APROBACIÓN'.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Número de Concurso</th>
                        <th>Tipo de Sesión</th>
                        <th>Números de Acta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($duplicate_sessions as $session): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($session['concurso']); ?></td>
                            <td><?php echo htmlspecialchars($session['tipo']); ?></td>
                            <td><?php echo htmlspecialchars(implode(', ', $session['actas'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Reporte de Actas: Profesionales Expertos</h2>
        <table>
            <thead>
                <tr>
                    <th>Profesional</th>
                    <th>RUT</th>
                    <th>Sesiones como Profesional Experto</th>
                    <th>Sesiones como Segundo Profesional Experto</th>
                    <th>Total de Sesiones</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_table as $index => $row): ?>
                    <tr <?php if ($row['profesional'] === 'Total general') echo 'class="total-row"'; ?>>
                        <td><?php echo htmlspecialchars($row['profesional']); ?></td>
                        <td><?php echo htmlspecialchars($row['rut'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(formatNumber($row['sesiones_prof_experto'])); ?></td>
                        <td><?php echo htmlspecialchars(formatNumber($row['sesiones_segundo_prof_experto'])); ?></td>
                        <td><?php echo htmlspecialchars(formatNumber($row['total_sesiones'])); ?></td>
                        <td><?php echo htmlspecialchars($row['observacion']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <a href="index.html" class="back-button">Volver a la página de carga</a>
    </div>

</body>
</html>