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
 * Muestra un mensaje de error estilizado con Bootstrap.
 * @param string $mensaje El mensaje a mostrar.
 */
function mostrarError($mensaje) {
    return "<div class='alert alert-danger mt-3' role='alert'>$mensaje</div>";
}

/**
 * Muestra el resultado del análisis en una tabla estilizada con Bootstrap.
 * @param string $nombreArchivo Nombre del archivo analizado.
 * @param array $resultados Un array asociativo con los resultados de los conteos.
 * @param string|null $concurso El número de concurso para mostrar.
 */
function mostrarResultados($nombreArchivo, $resultados, $concurso = null) {
    $titulo = $concurso ? "Reporte de Pago $concurso" : "Reporte de Pago";
    $html = "<h3 class='text-xl font-bold text-center mt-4 mb-2'>$titulo</h3>
             <p>Se ha analizado el archivo: <strong>" . htmlspecialchars($nombreArchivo) . "</strong></p>
             <hr>
             <h4 class='text-center mb-4'>Resultados de los Conteos</h4>
             <div class='table-responsive'>
                 <table class='table table-bordered table-striped'>
                     <thead>
                         <tr>
                             <th scope='col'>Tipo de Conteo</th>
                             <th scope='col'>Cantidad de RUNs</th>
                         </tr>
                     </thead>
                     <tbody>
                         <tr>
                             <td>RUNs Homologados</td>
                             <td>" . htmlspecialchars($resultados['homologados']) . "</td>
                         </tr>
                         <tr>
                             <td>Conteo P1</td>
                             <td>" . htmlspecialchars($resultados['P1']) . "</td>
                         </tr>
                         <tr>
                             <td>Conteo P2</td>
                             <td>" . htmlspecialchars($resultados['P2']) . "</td>
                         </tr>
                         <tr>
                             <td>Conteo P3</td>
                             <td>" . htmlspecialchars($resultados['P3']) . "</td>
                         </tr>
                     </tbody>
                 </table>
             </div>
             <div class='text-center mt-4'><button class='btn btn-primary' onclick='window.location.reload()'>Volver</button></div>";
    
    return $html;
}

// Lógica principal para procesar los archivos
if (isset($_FILES['archivo_xls']) && $_FILES['archivo_xls']['error'] === UPLOAD_ERR_OK) {
    
    $nombreArchivo = $_FILES['archivo_xls']['name'];
    $concurso = obtenerConcursoDelNombre($nombreArchivo);
    $rutaTemporal = $_FILES['archivo_xls']['tmp_name'];
    $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
    
    // Validamos el tipo de archivo
    if ($extension != 'xls' && $extension != 'xlsx' && $extension != 'csv') {
        echo mostrarError("El archivo debe ser un formato .xls, .xlsx o .csv.", $concurso);
        exit;
    }

    try {
        // Cargamos el archivo Excel o CSV
        $spreadsheet = IOFactory::load($rutaTemporal);
        $hojaActual = $spreadsheet->getActiveSheet();
        $filaMasAlta = $hojaActual->getHighestRow();
        
        // Inicializamos los contadores y sets para guardar los RUNs únicos
        $runsHomologados = [];
        $runsP1 = [];
        $runsP2 = [];
        $runsP3 = [];

        // Extraemos los encabezados de la primera fila para encontrar las columnas
        $encabezados = [];
        for ($col = 'A'; $col <= $hojaActual->getHighestColumn(); $col++) {
            $encabezados[$hojaActual->getCell($col . '1')->getValue()] = $col;
        }

        // Verificamos que todas las columnas necesarias existan
        $columnasRequeridas = [
            'Run', 'Homologado SI/NO', 'Admisibilidad', 'Nota P1',
            'Fase de Evaluacion', 'Nota p2', 'Estado de Postulacion', 'Calce'
        ];
        foreach ($columnasRequeridas as $col) {
            if (!isset($encabezados[$col])) {
                echo mostrarError("Error: El archivo Excel no contiene la columna '$col' necesaria para el análisis.");
                exit;
            }
        }

        // Recorremos las filas del archivo, comenzando desde la segunda fila (después de los encabezados)
        for ($fila = 2; $fila <= $filaMasAlta; $fila++) {
            $run = $hojaActual->getCell($encabezados['Run'] . $fila)->getValue();
            if (empty($run)) continue;

            $run = trim(strtoupper($run)); // Normalizamos el RUN para evitar duplicados por formato
            
            $homologado = trim(strtoupper($hojaActual->getCell($encabezados['Homologado SI/NO'] . $fila)->getValue()));
            $admisibilidad = trim(strtoupper($hojaActual->getCell($encabezados['Admisibilidad'] . $fila)->getValue()));
            $notaP1 = $hojaActual->getCell($encabezados['Nota P1'] . $fila)->getValue();
            $faseEvaluacion = trim(strtoupper($hojaActual->getCell($encabezados['Fase de Evaluacion'] . $fila)->getValue()));
            $notaP2 = $hojaActual->getCell($encabezados['Nota p2'] . $fila)->getValue();
            $estadoPostulacion = trim(strtoupper($hojaActual->getCell($encabezados['Estado de Postulacion'] . $fila)->getValue()));
            $calce = $hojaActual->getCell($encabezados['Calce'] . $fila)->getValue();

            // 1. Conteo de Run homologados
            if ($homologado === 'SI') {
                $runsHomologados[$run] = true;
            }

            // 2. Conteo P1
            if ($admisibilidad === 'CUMPLE' && is_numeric($notaP1)) {
                $runsP1[$run] = true;
            }

            // 3. Conteo P2
            $calce_clean = str_replace('%', '', $calce);
            $calce_is_numeric = is_numeric($calce_clean);
            $calce_value = $calce_is_numeric ? (float)$calce_clean : null;

            $condicionA = ($faseEvaluacion === 'ACEPTADO P2' || $faseEvaluacion === 'DESISTE') && is_numeric($notaP2) && (float)$notaP2 !== 0.0;
            $condicionB = ($faseEvaluacion === 'ACEPTADO P3') && ($estadoPostulacion === 'DESISTE' || $estadoPostulacion === 'NO ASISTE' || $estadoPostulacion === 'NO UBICABLE') && $calce_is_numeric && $calce_value === 0.0;
            
            if ($condicionA || $condicionB) {
                $runsP2[$run] = true;
            }

            // 4. Conteo P3
            $condicion1P3 = ($faseEvaluacion === 'ACEPTADO P3' || $faseEvaluacion === 'ENTREVISTA FINAL' || $faseEvaluacion === 'DESISTE' || $faseEvaluacion === 'NO ASISTE');
            $condicion2P3 = $calce_is_numeric && $calce_value !== 0.0;

            if ($condicion1P3 && $condicion2P3) {
                $runsP3[$run] = true;
            }
        }
        
        // Calculamos los conteos finales
        $conteoHomologados = count($runsHomologados);
        $conteoP1 = count($runsP1);
        $conteoP2 = count($runsP2) + $conteoHomologados;
        $runsP3SinHomologados = array_diff(array_keys($runsP3), array_keys($runsHomologados));
        $conteoP3 = count($runsP3SinHomologados);

        // Mostramos los resultados
        $resultados = [
            'homologados' => $conteoHomologados,
            'P1' => $conteoP1,
            'P2' => $conteoP2,
            'P3' => $conteoP3
        ];
        echo mostrarResultados($nombreArchivo, $resultados, $concurso);
        
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        echo mostrarError("Error al leer el archivo Excel: " . $e->getMessage());
    }
} else {
    echo mostrarError("No se ha subido ningún archivo o ha ocurrido un error.");
}
?>
