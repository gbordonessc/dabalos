<?php
// Incluimos el autoloader de Composer para cargar la librería PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Muestra un mensaje de error estilizado con Bootstrap.
 * @param string $mensaje El mensaje a mostrar.
 */
function mostrarError($mensaje) {
    echo "<!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Error</title>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            </head>
            <body>
            <div class='container mt-5'>
                <div class='alert alert-danger' role='alert'>$mensaje</div>
                <div class='text-center mt-4'><a href='index.html' class='btn btn-secondary'>Volver</a></div>
            </div>
            </body>
            </html>";
}

/**
 * Procesa un archivo Excel/CSV y retorna los datos en un array.
 * @param string $rutaTemporal La ruta temporal del archivo.
 * @param bool $skipFirstRow Si se deben saltar las 2 primeras filas.
 * @return array Retorna un array con los datos o un string con el error.
 */
function procesarArchivo($rutaTemporal, $skipFirstRows = 0) {
    try {
        $reader = IOFactory::createReaderForFile($rutaTemporal);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($rutaTemporal);
        $hojaActual = $spreadsheet->getActiveSheet();
        $filaMasAlta = $hojaActual->getHighestRow();
        $columnaMasAlta = $hojaActual->getHighestColumn();
        
        $datos = [];
        $encabezados = [];
        $startRow = 1 + $skipFirstRows;

        // Leer encabezados
        for ($col = 'A'; $col <= $columnaMasAlta; $col++) {
            $encabezados[] = $hojaActual->getCell($col . $startRow)->getValue();
        }
        $datos[] = $encabezados;

        // Leer datos
        for ($fila = $startRow + 1; $fila <= $filaMasAlta; $fila++) {
            $filaDatos = [];
            for ($col = 'A'; $col <= $columnaMasAlta; $col++) {
                $filaDatos[] = $hojaActual->getCell($col . $fila)->getValue();
            }
            $datos[] = $filaDatos;
        }
        
        return $datos;
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        return "Error al leer el archivo: " . $e->getMessage();
    }
}

/**
 * Muestra los resultados en tablas estilizadas con Bootstrap.
 * @param array $resultados Array de arrays con los datos a mostrar.
 */
function mostrarResultadosActas($resultados) {
    echo "<!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Reporte Mensual de Actas</title>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
                <style>
                    body { background-color: #f8f9fa; }
                    .container { max-width: 1100px; margin-top: 50px; padding: 30px; background-color: #ffffff; border-radius: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                    .table-resultados th { background-color: #0d6efd; color: white; }
                    .card-title { font-weight: bold; }
                </style>
            </head>
            <body>
            <div class='container'>
                <h1 class='text-center mb-4'>Reporte Mensual de Actas</h1>
                <p>Se ha generado el reporte mensual con los archivos proporcionados.</p>
                <hr>";

    foreach ($resultados as $nombre => $datos) {
        if (is_string($datos)) {
            echo "<div class='alert alert-danger' role='alert'>Error en el archivo '$nombre': $datos</div>";
            continue;
        }

        echo "<div class='card mb-4'>
                <div class='card-header bg-primary text-white card-title'>
                    Archivo: " . htmlspecialchars($nombre) . "
                </div>
                <div class='card-body'>
                    <div class='table-responsive'>
                        <table class='table table-bordered table-striped table-sm'>
                            <thead>
                                <tr>";
        foreach ($datos[0] as $header) {
            echo "<th scope='col'>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr>
                            </thead>
                            <tbody>";

        for ($i = 1; $i < count($datos); $i++) {
            echo "<tr>";
            foreach ($datos[$i] as $celda) {
                echo "<td>" . htmlspecialchars($celda) . "</td>";
            }
            echo "</tr>";
        }

        echo "</tbody></table>
                    </div>
                </div>
              </div>";
    }

    echo "<div class='text-center mt-4'>
            <a href='index.html' class='btn btn-primary'>Volver</a>
          </div>
        </div>
        <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
        </body>
        </html>";
}

// Lógica principal para procesar los archivos
if (isset($_FILES['archivo_worksheet']) && isset($_FILES['archivo_nulas'])) {
    $fileWorksheet = $_FILES['archivo_worksheet'];
    $fileNulas = $_FILES['archivo_nulas'];

    $resultados = [];

    // Procesar archivo de Worksheet
    if ($fileWorksheet['error'] === UPLOAD_ERR_OK) {
        $resultados[$fileWorksheet['name']] = procesarArchivo($fileWorksheet['tmp_name'], 1);
    } else {
        $resultados[$fileWorksheet['name']] = "Error al subir el archivo: " . $fileWorksheet['error'];
    }

    // Procesar archivo de Actas Nulas
    if ($fileNulas['error'] === UPLOAD_ERR_OK) {
        $resultados[$fileNulas['name']] = procesarArchivo($fileNulas['tmp_name'], 0);
    } else {
        $resultados[$fileNulas['name']] = "Error al subir el archivo: " . $fileNulas['error'];
    }

    mostrarResultadosActas($resultados);

} else {
    mostrarError("No se ha subido uno o ambos archivos.");
}
?>
