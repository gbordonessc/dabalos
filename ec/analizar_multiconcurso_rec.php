<?php

/**
 * Muestra un mensaje de error con formato de alerta de Bootstrap.
 * @param string $message El mensaje de error a mostrar.
 */
function showError($message) {
    echo '<div class="alert alert-danger mt-3"><strong>Error:</strong> ' . htmlspecialchars($message) . '</div>';
}

/**
 * Lee un archivo CSV y lo convierte en un array de arrays asociativos.
 * @param string $filePath La ruta al archivo CSV.
 * @return array|null Un array de datos o null si hay un error.
 */
function readCsvFile($filePath) {
    $data = [];
    if (($handle = fopen($filePath, 'r')) === FALSE) {
        return null;
    }

    // Leer la fila de encabezados
    $headers = fgetcsv($handle);
    if ($headers === FALSE) {
        fclose($handle);
        return null;
    }
    // Limpiar los encabezados de espacios en blanco
    $headers = array_map('trim', $headers);

    // Leer el resto de las filas
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($headers) == count($row)) {
            $data[] = array_combine($headers, $row);
        }
    }
    fclose($handle);
    return $data;
}

/**
 * Calcula las métricas para un conjunto de datos dado, según las reglas del prompt.
 * @param array $data El conjunto de datos (filtrado por Tipo='REC').
 * @return array Un array con las métricas calculadas.
 */
function calculateMetrics($data) {
    $metrics = [
        'aprobados_p1' => 0,
        'total_nomina' => 0,
        'mujeres_nomina' => 0,
    ];

    [cite_start]// Métrica: Aprobados P1 [cite: 14]
    $aprobados_data = array_filter($data, fn($row) => isset($row['Pasa a P2P3']) && $row['Pasa a P2P3'] === 'Sigue');
    $metrics['aprobados_p1'] = count(array_unique(array_column($aprobados_data, 'Run')));

    [cite_start]// Métrica: Total en Nómina [cite: 15]
    $nomina_data = array_filter($data, fn($row) => isset($row['Posicion en nomina']) && is_numeric($row['Posicion en nomina']) && $row['Posicion en nomina'] > 0);
    $metrics['total_nomina'] = count(array_unique(array_column($nomina_data, 'Run')));

    [cite_start]// Métrica: Mujeres en Nómina [cite: 16]
    // Asumimos que el valor para identificar a las mujeres es 'Femenino'.
    $mujeres_data = array_filter($nomina_data, fn($row) => isset($row['Nombre Sexo registral']) && $row['Nombre Sexo registral'] === 'Femenino');
    $metrics['mujeres_nomina'] = count(array_unique(array_column($mujeres_data, 'Run')));
    
    return $metrics;
}


// --- LÓGICA PRINCIPAL DEL SCRIPT ---

// 1. Validaciones iniciales
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError("Acceso no válido.");
    exit;
}

if (!isset($_FILES['archivos_xls_multi_rec']) || !is_array($_FILES['archivos_xls_multi_rec'])) {
    showError("No se recibieron archivos.");
    exit;
}

if (count($_FILES['archivos_xls_multi_rec']['name']) != 2) {
    showError("Se deben subir exactamente dos archivos para la comparación.");
    exit;
}

// 2. Procesamiento de archivos
$file1_tmp = $_FILES['archivos_xls_multi_rec']['tmp_name'][0];
$file2_tmp = $_FILES['archivos_xls_multi_rec']['tmp_name'][1];
$file1_name = $_FILES['archivos_xls_multi_rec']['name'][0];
$file2_name = $_FILES['archivos_xls_multi_rec']['name'][1];

$data1 = readCsvFile($file1_tmp);
$data2 = readCsvFile($file2_tmp);

if ($data1 === null || $data2 === null) {
    showError("No se pudo procesar uno o ambos archivos. Asegúrese de que son archivos CSV válidos.");
    exit;
}

[cite_start]// 3. Aplicar Regla General de Filtrado (Tipo = 'REC') [cite: 5, 6, 7]
$data1_rec = array_filter($data1, fn($row) => isset($row['Tipo']) && $row['Tipo'] === 'REC');
$data2_rec = array_filter($data2, fn($row) => isset($row['Tipo']) && $row['Tipo'] === 'REC');

[cite_start]// 4. Paso 1: Identificar RUNs Duplicados [cite: 8]
$runs1 = array_unique(array_column($data1_rec, 'Run')); [cite_start]// [cite: 9]
$runs2 = array_unique(array_column($data2_rec, 'Run')); [cite_start]// [cite: 10]
$duplicate_runs = array_intersect($runs1, $runs2); [cite_start]// [cite: 11]

[cite_start]// 5. Paso 2: Calcular Métricas para el primer concurso [cite: 12]
$metrics1 = calculateMetrics($data1_rec); [cite_start]// [cite: 13]

[cite_start]// 6. Paso 3: Calcular Métricas para el segundo concurso (excluyendo duplicados) [cite: 17]
$data2_rec_filtered = array_filter($data2_rec, fn($row) => !in_array($row['Run'], $duplicate_runs)); [cite_start]// [cite: 18]
$metrics2 = calculateMetrics($data2_rec_filtered); [cite_start]// [cite: 19]

[cite_start]// 7. Paso 4: Entregar Reporte Final [cite: 20]
?>

<div class="mt-4">
    [cite_start]<h3 class="text-xl font-bold text-gray-800 mb-3">Reporte de Pago Consolidado y Análisis de Duplicados [cite: 1]</h3>
    
    <h4 class="text-lg font-semibold text-gray-700 mt-4 mb-2">1. [cite_start]Tabla Comparativa de Métricas [cite: 21]</h4>
    <p class="text-sm text-gray-600 mb-3">Nota: Los cálculos para el segundo archivo se realizaron después de excluir los postulantes duplicados (Tipo 'REC') encontrados en el primer archivo.</p>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Métrica</th>
                    <th><?php echo htmlspecialchars($file1_name); ?></th>
                    <th><?php echo htmlspecialchars($file2_name); ?> (sin duplicados)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    [cite_start]<td><strong>Aprobados P1</strong> (Pasa a P2P3 = 'Sigue') [cite: 14]</td>
                    <td class="text-center"><?php echo $metrics1['aprobados_p1']; ?></td>
                    <td class="text-center"><?php echo $metrics2['aprobados_p1']; ?></td>
                </tr>
                <tr>
                    [cite_start]<td><strong>Total en Nómina</strong> (Posicion en nomina > 0) [cite: 15]</td>
                    <td class="text-center"><?php echo $metrics1['total_nomina']; ?></td>
                    <td class="text-center"><?php echo $metrics2['total_nomina']; ?></td>
                </tr>
                <tr>
                    [cite_start]<td><strong>Mujeres en Nómina</strong> [cite: 16]</td>
                    <td class="text-center"><?php echo $metrics1['mujeres_nomina']; ?></td>
                    <td class="text-center"><?php echo $metrics2['mujeres_nomina']; ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h4 class="text-lg font-semibold text-gray-700 mt-5 mb-2">2. [cite_start]Listado de RUNs Duplicados (Tipo 'REC') [cite: 22]</h4>
    <?php if (empty($duplicate_runs)): ?>
        <p class="text-gray-600">No se encontraron RUNs duplicados entre los dos archivos para el tipo 'REC'.</p>
    <?php else: ?>
        <p class="text-gray-600">Se encontraron <?php echo count($duplicate_runs); ?> RUNs duplicados:</p>
        <div class="bg-gray-100 p-3 rounded-md mt-2" style="max-height: 200px; overflow-y: auto;">
            <ul class="list-disc list-inside">
                <?php foreach ($duplicate_runs as $run): ?>
                    <li><?php echo htmlspecialchars($run); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>