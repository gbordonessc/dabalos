<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Declarar la variable $uploadDir al inicio del script para que esté disponible globalmente
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

// Manejar la descarga
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $filePath = $uploadDir . $_GET['download'];
    downloadFile($filePath);
}

// Lógica de análisis y procesamiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_worksheet'])) {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileTmpPath = $_FILES['archivo_worksheet']['tmp_name'];
    $fileName = $_FILES['archivo_worksheet']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedfileExtensions = ['xls', 'xlsx'];
    if (in_array($fileExtension, $allowedfileExtensions)) {
        $dest_path = $uploadDir . $fileName;
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $resultado_analisis = analizarArchivo($dest_path);
            unlink($dest_path);
        } else {
            $resultado_analisis = ['status' => 'error', 'message' => "Error: Hubo un problema al subir tu archivo. Por favor, inténtalo de nuevo."];
        }
    } else {
        $resultado_analisis = ['status' => 'error', 'message' => "Error: Solo se permiten archivos .xls y .xlsx."];
    }
}

// Función principal de análisis
function analizarArchivo($filePath) {
    global $uploadDir; // Acceder a la variable global

    if (!file_exists($filePath)) {
        return ['status' => 'error', 'message' => "El archivo no existe."];
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
        return ['status' => 'error', 'message' => "El archivo de actas está vacío."];
    }

    $headers = $rows[1];
    $col_indices = [];
    foreach ($headers as $key => $header) {
        if ($header === 'Concurso' || $header === 'Tipo' || $header === 'Acta') {
            $col_indices[$header] = $key;
        }
    }

    if (!isset($col_indices['Concurso']) || !isset($col_indices['Tipo']) || !isset($col_indices['Acta'])) {
        return ['status' => 'error', 'message' => "El archivo de actas no contiene las columnas 'Concurso', 'Tipo' o 'Acta' necesarias para el análisis."];
    }

    $sesiones_repetidas = [];
    $vistos = [];
    $filas_unicas = [];

    // Añadir encabezados a las filas únicas
    $filas_unicas[] = $rows[1];

    for ($i = 2; $i <= count($rows); $i++) {
        $row = $rows[$i];
        
        $concurso = $row[$col_indices['Concurso']];
        $tipo = $row[$col_indices['Tipo']];
        $acta = $row[$col_indices['Acta']];
        
        $clave = $concurso . '-' . $tipo . '-' . $acta;
        if (isset($vistos[$clave])) {
            $sesiones_repetidas[] = "Concurso: $concurso, Tipo: $tipo, Acta: $acta";
        } else {
            $vistos[$clave] = true;
            $filas_unicas[] = $row;
        }
    }

    $output = "";
    if (!empty($sesiones_repetidas)) {
        $output .= "<h3>Se encontraron sesiones repetidas:</h3>";
        $output .= "<div class='list-group mt-4 mb-4'>";
        foreach ($sesiones_repetidas as $sesion) {
            $output .= "<li class='list-group-item'>" . htmlspecialchars($sesion) . "</li>";
        }
        $output .= "</div>";
    } else {
        $output .= "<h3>Análisis completado</h3>";
        $output .= "<div class='alert alert-success mt-4'>No se encontraron sesiones repetidas.</div>";
    }

    // Si hay filas únicas para procesar, crear el archivo de descarga
    if (count($filas_unicas) > 1) { // Mayor que 1 porque incluye el encabezado
        $newSpreadsheet = new Spreadsheet();
        $newSpreadsheet->getActiveSheet()->fromArray($filas_unicas, NULL, 'A1');

        $writer = IOFactory::createWriter($newSpreadsheet, $inputFileType === 'Xlsx' ? 'Xlsx' : 'Xls');
        
        // --- INICIO DE LA CORRECCIÓN ---
        // Generar el nombre de archivo con la fecha completa
        $fecha_formato = date('Y-m-d_H-i-s'); // Formato: Año-Mes-Día_Hora-Minuto-Segundo
        $downloadFilename = 'base_sin_duplicados_' . $fecha_formato . '.' . ($inputFileType === 'Xlsx' ? 'xlsx' : 'xls');
        // --- FIN DE LA CORRECCIÓN ---
        
        $downloadPath = $uploadDir . $downloadFilename;
        $writer->save($downloadPath);

        $output .= '<a href="?download=' . urlencode($downloadFilename) . '" class="btn btn-primary mt-4">Descargar base sin duplicados</a>';
    }

    return ['status' => 'success', 'message' => $output];
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
                <h1 class="text-2xl font-bold text-gray-800">Resultado de la Revisión</h1>
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