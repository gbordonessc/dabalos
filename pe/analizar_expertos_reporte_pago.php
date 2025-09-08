<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Declarar la variable $uploadDir al inicio del script
$uploadDir = __DIR__ . '/uploads/';

// Función para servir el archivo para descarga y luego eliminarlo
function downloadFile($filePath) {
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        ob_clean();
        flush();
        readfile($filePath);
        unlink($filePath);
        exit;
    }
}

// Lógica de manejo de descarga
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $filePath = $uploadDir . $_GET['download'];
    downloadFile($filePath);
}

// Lógica de subida de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_reporte_pago'])) {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileTmpPath = $_FILES['archivo_reporte_pago']['tmp_name'];
    $fileName = $_FILES['archivo_reporte_pago']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedfileExtensions = ['xls', 'xlsx'];
    if (in_array($fileExtension, $allowedfileExtensions)) {
        $dest_path = $uploadDir . $fileName;
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $resultado_analisis = generarReporte($dest_path);
            unlink($dest_path);
        } else {
            $resultado_analisis = ['status' => 'error', 'message' => "Error: Hubo un problema al subir tu archivo. Por favor, inténtalo de nuevo."];
        }
    } else {
        $resultado_analisis = ['status' => 'error', 'message' => "Error: Solo se permiten archivos .xls y .xlsx."];
    }
}

// Función para generar la salida HTML y el archivo de descarga
function generarReporte($filePath) {
    global $uploadDir;
    
    if (!file_exists($filePath)) {
        return ['status' => 'error', 'message' => "Error: El archivo no existe."];
    }

    try {
        $inputFileType = IOFactory::identify($filePath);
        $reader = IOFactory::createReader($inputFileType);
        $spreadsheet = $reader->load($filePath);
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        return ['status' => 'error', 'message' => "Error al cargar el archivo de Excel: " . $e->getMessage()];
    }

    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray(null, true, true, true);

    if (empty($rows)) {
        return ['status' => 'error', 'message' => "Error: El archivo de reporte de pago está vacío."];
    }

    // Mapear los encabezados de las columnas a sus índices para evitar errores
    $headers = $rows[1];
    $col_indices = [];
    foreach ($headers as $key => $header) {
        $trimmed_header = trim($header);
        if ($trimmed_header === 'Profesional Experto') {
            $col_indices['Profesional Experto'] = $key;
        }
    }

    // Usar índices de columna directos para J y W
    $col_indices['Primer Profesional Experto'] = 'J';
    $col_indices['Segundo Profesional Experto'] = 'W';

    if (!isset($col_indices['Profesional Experto'])) {
        return ['status' => 'error', 'message' => "Error: El archivo no contiene la columna 'Profesional Experto' necesaria."];
    }

    // Estructura para agrupar los datos
    $resumen_profesionales = [];

    // Ignorar la fila de encabezados
    for ($i = 2; $i <= count($rows); $i++) {
        $row = $rows[$i];
        
        $profesional = $row[$col_indices['Profesional Experto']];
        $primer_experto_valor = isset($row[$col_indices['Primer Profesional Experto']]) ? $row[$col_indices['Primer Profesional Experto']] : '';
        $segundo_experto_valor = isset($row[$col_indices['Segundo Profesional Experto']]) ? $row[$col_indices['Segundo Profesional Experto']] : '';

        if (!isset($resumen_profesionales[$profesional])) {
            $resumen_profesionales[$profesional] = [
                'primer_experto_sesiones' => 0,
                'segundo_experto_sesiones' => 0,
            ];
        }

        // Si la columna 'J' tiene un valor, se cuenta la sesión
        if (!empty(trim($primer_experto_valor))) {
            $resumen_profesionales[$profesional]['primer_experto_sesiones']++;
        }

        // Si la columna 'W' tiene un valor, se cuenta la sesión
        if (!empty(trim($segundo_experto_valor))) {
            $resumen_profesionales[$profesional]['segundo_experto_sesiones']++;
        }
    }
    
    // Generar la tabla HTML
    $table_html = "<div class='table-responsive'><table class='table table-striped table-hover mt-4'>";
    $table_html .= "<thead><tr><th>Profesional Experto</th><th>RUN</th><th>Primer Profesional Experto</th><th>Segundo Profesional Experto</th><th>Total sesiones</th><th>Observación</th></tr></thead><tbody>";

    // Preparar datos para el nuevo archivo Excel
    $newSpreadsheet = new Spreadsheet();
    $sheet = $newSpreadsheet->getActiveSheet();
    
    // Encabezados para el archivo de Excel
    $excel_headers = [
        'Profesional Experto',
        'RUN',
        'Primer Profesional Experto',
        'Segundo Profesional Experto',
        'Total sesiones',
        'Observación'
    ];
    $sheet->fromArray($excel_headers, NULL, 'A1');

    $row_count = 2;
    foreach ($resumen_profesionales as $profesional => $data) {
        $total_sesiones = $data['primer_experto_sesiones'] + $data['segundo_experto_sesiones'];
        $observacion = ($total_sesiones > 12) ? 'supera el limite' : '';
        
        // Añadir fila a la tabla HTML
        $table_html .= "<tr>";
        $table_html .= "<td>" . htmlspecialchars($profesional) . "</td>";
        $table_html .= "<td></td>"; // RUN, se deja vacío
        $table_html .= "<td>" . $data['primer_experto_sesiones'] . "</td>";
        $table_html .= "<td>" . $data['segundo_experto_sesiones'] . "</td>";
        $table_html .= "<td>" . $total_sesiones . "</td>";
        $table_html .= "<td>" . htmlspecialchars($observacion) . "</td>";
        $table_html .= "</tr>";

        // Añadir fila a los datos para el archivo Excel
        $excel_data = [
            $profesional,
            '', // RUN, se deja vacío
            $data['primer_experto_sesiones'],
            $data['segundo_experto_sesiones'],
            $total_sesiones,
            $observacion
        ];
        $sheet->fromArray($excel_data, NULL, 'A' . $row_count);

        // Aplicar estilo si supera el límite
        if ($total_sesiones > 12) {
            $sheet->getStyle('A' . $row_count . ':F' . $row_count)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFE0E0'); // Rojo claro
        }

        $row_count++;
    }

    $table_html .= "</tbody></table></div>";

    // Ajustar el ancho de las columnas en el Excel
    foreach(range('A','F') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    $writer = IOFactory::createWriter($newSpreadsheet, 'Xlsx');

    // Generar el nombre de archivo con la fecha completa
    $fecha_formato = date('Y-m-d_H-i-s');
    $downloadFilename = 'Reporte Mensual de Actas_' . $fecha_formato . '.xlsx';
    
    $downloadPath = $uploadDir . $downloadFilename;
    $writer->save($downloadPath);
    
    // Devolver el HTML y el enlace de descarga
    $output_html = "<h3>Reporte Generado:</h3>";
    $output_html .= $table_html;
    $output_html .= '<a href="?download=' . urlencode($downloadFilename) . '" class="btn btn-success mt-4">Descargar Reporte en Excel</a>';
    
    return ['status' => 'success', 'message' => $output_html];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado del Análisis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        .bg-serviciocivil {
            background-color: #1a426f;
            background-image: linear-gradient(180deg, #1a426f 0%, #2b5c90 100%);
        }
    </style>
</head>
<body class="h-full bg-gray-100 flex flex-col items-center justify-center">

    <div class="bg-serviciocivil h-full w-full flex items-center justify-center p-4">
        <div class="bg-white p-8 md:p-10 rounded-xl shadow-lg w-full max-w-7xl">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Reporte de Pago</h1>
                <a href="javascript:history.back()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    &larr; Volver
                </a>
            </div>
            <div class="mt-4">
                <?php
                    if (isset($resultado_analisis['status']) && $resultado_analisis['status'] === 'success') {
                        echo $resultado_analisis['message'];
                    } elseif (isset($resultado_analisis['status']) && $resultado_analisis['status'] === 'error') {
                        echo '<div class="alert alert-danger">' . $resultado_analisis['message'] . '</div>';
                    }
                ?>
            </div>
        </div>
    </div>
</body>
</html>