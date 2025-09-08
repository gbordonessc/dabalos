<?php
// Incluimos el autoloader de Composer para cargar la librería PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Extrae el número de concurso del nombre de archivo.
 * @param string $nombreArchivo El nombre completo del archivo.
 * @return string|null El número del concurso o null si no se encuentra.
 */
function obtenerConcursoDelNombre($nombreArchivo) {
    if (preg_match('/ADP-(\d+)/', $nombreArchivo, $matches)) {
        return 'ADP-' . $matches[1];
    }
    return null;
}

/**
 * Realiza el análisis de múltiples archivos Excel según las reglas del multiconcurso.
 * @param array $archivos Array de archivos subidos ($_FILES['archivos_xls_multi']).
 * @return array Retorna un array con los resultados y mensajes de error.
 */
function analizarMulticoncurso($archivos) {
    $resultados = [];
    $runsYaContadosP1 = [];
    $runsYaContadosP2 = [];
    $runsYaContadosP3 = [];

    foreach ($archivos['tmp_name'] as $clave => $rutaTemporal) {
        $nombreArchivo = $archivos['name'][$clave];
        $concursoActual = obtenerConcursoDelNombre($nombreArchivo) ?? "No identificado";
        $resultados[$nombreArchivo] = ['concurso' => $concursoActual];

        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        if ($extension != 'xls' && $extension != 'xlsx') {
            $resultados[$nombreArchivo]['error'] = "El archivo debe ser un formato .xls o .xlsx.";
            continue;
        }

        try {
            $spreadsheet = IOFactory::load($rutaTemporal);
            $hojaActual = $spreadsheet->getActiveSheet();
            $filaMasAlta = $hojaActual->getHighestRow();

            $currentRunsP1 = [];
            $currentRunsP2 = [];
            $currentRunsP3 = [];

            $encabezados = [];
            for ($col = 'A'; $col <= $hojaActual->getHighestColumn(); $col++) {
                $encabezados[$hojaActual->getCell($col . '1')->getValue()] = $col;
            }

            $columnasRequeridas = [
                'Run', 'Homologado SI/NO', 'Admisibilidad', 'Nota P1',
                'Fase de Evaluacion', 'Nota p2', 'Estado de Postulacion', 'Calce'
            ];
            foreach ($columnasRequeridas as $col) {
                if (!isset($encabezados[$col])) {
                    $resultados[$nombreArchivo]['error'] = "Error: El archivo Excel no contiene la columna '$col'.";
                    continue 2; // Salir del bucle interno y pasar al siguiente archivo
                }
            }

            for ($fila = 2; $fila <= $filaMasAlta; $fila++) {
                $run = $hojaActual->getCell($encabezados['Run'] . $fila)->getValue();
                if (empty($run)) continue;

                $run = trim(strtoupper($run));
                $homologado = trim(strtoupper($hojaActual->getCell($encabezados['Homologado SI/NO'] . $fila)->getValue()));
                $admisibilidad = trim(strtoupper($hojaActual->getCell($encabezados['Admisibilidad'] . $fila)->getValue()));
                $notaP1 = $hojaActual->getCell($encabezados['Nota P1'] . $fila)->getValue();
                $faseEvaluacion = trim(strtoupper($hojaActual->getCell($encabezados['Fase de Evaluacion'] . $fila)->getValue()));
                $notaP2 = $hojaActual->getCell($encabezados['Nota p2'] . $fila)->getValue();
                $estadoPostulacion = trim(strtoupper($hojaActual->getCell($encabezados['Estado de Postulacion'] . $fila)->getValue()));
                $calce = $hojaActual->getCell($encabezados['Calce'] . $fila)->getValue();

                // 1. Conteo P1
                if ($admisibilidad === 'CUMPLE' && is_numeric($notaP1)) {
                    $currentRunsP1[$run] = true;
                }

                // 2. Conteo P2
                $calce_clean = str_replace('%', '', $calce);
                $calce_is_numeric = is_numeric($calce_clean);
                $calce_value = $calce_is_numeric ? (float)$calce_clean : null;
                
                $condicionA = ($faseEvaluacion === 'ACEPTADO P2' || $faseEvaluacion === 'DESISTE') && is_numeric($notaP2) && (float)$notaP2 !== 0.0;
                $condicionB = ($faseEvaluacion === 'ACEPTADO P3') && ($estadoPostulacion === 'DESISTE' || $estadoPostulacion === 'NO ASISTE' || $estadoPostulacion === 'NO UBICABLE') && $calce_is_numeric && $calce_value === 0.0;
                if ($condicionA || $condicionB) {
                    $currentRunsP2[$run] = true;
                }

                // 3. Conteo P3
                $condicion1P3 = ($faseEvaluacion === 'ACEPTADO P3');
                $condicion2P3 = $calce_is_numeric && $calce_value !== 0.0;
                if ($condicion1P3 && $condicion2P3) {
                    $currentRunsP3[$run] = true;
                }
            }

            // Aplicar lógica de descuentos
            $runsP1Final = array_keys($currentRunsP1);
            $runsP2Final = array_keys($currentRunsP2);
            $runsP3Final = array_keys($currentRunsP3);
            
            if ($clave > 0) { // Si no es el primer archivo, aplicamos los descuentos
                $runsP1Final = array_diff($runsP1Final, array_keys($runsYaContadosP1));
                
                $runsP2Final = array_diff($runsP2Final, array_keys($runsYaContadosP2));
                $runsP2Final = array_diff($runsP2Final, array_keys($runsYaContadosP3));
                
                $runsP3Final = array_diff($runsP3Final, array_keys($runsYaContadosP3));
            }

            // Almacenar los RUNs para ser descontados en la siguiente iteración
            $runsYaContadosP1 = array_merge($runsYaContadosP1, array_fill_keys($runsP1Final, true));
            $runsYaContadosP2 = array_merge($runsYaContadosP2, array_fill_keys($runsP2Final, true));
            $runsYaContadosP3 = array_merge($runsYaContadosP3, array_fill_keys($runsP3Final, true));

            $resultados[$nombreArchivo]['P1'] = count($runsP1Final);
            $resultados[$nombreArchivo]['P2'] = count($runsP2Final);
            $resultados[$nombreArchivo]['P3'] = count($runsP3Final);
            $resultados[$nombreArchivo]['homologados'] = 0; // El prompt no menciona homologados para multiconcurso
            
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            $resultados[$nombreArchivo]['error'] = "Error al leer el archivo Excel: " . $e->getMessage();
        }
    }
    return $resultados;
}

/**
 * Muestra los resultados de todos los archivos analizados.
 * @param array $resultados Un array de arrays, donde cada elemento es el resultado de un archivo.
 */
function mostrarResultadosMultiples($resultados) {
    echo "<!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Resultados de Análisis</title>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
                <style>
                    body { background-color: #f8f9fa; }
                    .container { max-width: 900px; margin-top: 50px; padding: 30px; background-color: #ffffff; border-radius: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                    .table-resultados th { background-color: #0d6efd; color: white; }
                    .table-resultados td:first-child { font-weight: bold; }
                    .card-concurso { margin-bottom: 20px; }
                </style>
            </head>
            <body>
            <div class='container'>
                <h1 class='text-center mb-5'>Reporte de Pago Multiconcurso</h1>
                
                <div class='row'>";
    
    foreach ($resultados as $nombreArchivo => $resultado) {
        $concurso = htmlspecialchars($resultado['concurso']);
        echo "<div class='col-md-6 mb-4'>
                <div class='card card-concurso'>
                    <div class='card-header bg-primary text-white'>
                        Concurso: <strong>$concurso</strong>
                    </div>
                    <div class='card-body'>
                        <p>Archivo: " . htmlspecialchars($nombreArchivo) . "</p>
                        <ul class='list-group list-group-flush'>";
        if (isset($resultado['error'])) {
            // Es un error
            echo "<li class='list-group-item text-danger'>" . htmlspecialchars($resultado['error']) . "</li>";
        } else {
            // Son resultados válidos
            echo "<li class='list-group-item d-flex justify-content-between align-items-center'>
                    Conteo P1
                    <span class='badge bg-primary rounded-pill'>" . htmlspecialchars($resultado['P1']) . "</span>
                  </li>
                  <li class='list-group-item d-flex justify-content-between align-items-center'>
                    Conteo P2
                    <span class='badge bg-primary rounded-pill'>" . htmlspecialchars($resultado['P2']) . "</span>
                  </li>
                  <li class='list-group-item d-flex justify-content-between align-items-center'>
                    Conteo P3
                    <span class='badge bg-primary rounded-pill'>" . htmlspecialchars($resultado['P3']) . "</span>
                  </li>";
        }
        echo "</ul>
                    </div>
                </div>
              </div>";
    }

    echo "</div>
            <div class='text-center mt-4'>
                <a href='index.html' class='btn btn-primary'>Volver</a>
            </div>
            </div>
            <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
            </body>
            </html>";
}

// Lógica principal para procesar múltiples archivos
if (isset($_FILES['archivos_xls_multi']) && !empty($_FILES['archivos_xls_multi']['tmp_name'][0])) {
    $archivos = $_FILES['archivos_xls_multi'];
    $resultados = analizarMulticoncurso($archivos);
    mostrarResultadosMultiples($resultados);
} else {
    mostrarError("No se ha subido ningún archivo o ha ocurrido un error.");
}
?>
