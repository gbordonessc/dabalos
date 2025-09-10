<?php
// Incluye la librería de PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: text/html; charset=utf-8');

// Verifica si se ha subido un archivo
if (!isset($_FILES['archivo_ri']) || $_FILES['archivo_ri']['error'] !== UPLOAD_ERR_OK) {
    echo '<div class="alert alert-danger mt-3" role="alert">Error: No se ha seleccionado un archivo o ha ocurrido un error en la subida.</div>';
    exit;
}

$file_name = $_FILES['archivo_ri']['name'];
$file_tmp_path = $_FILES['archivo_ri']['tmp_name'];
$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);

// Extraer el número de concurso del nombre del archivo
$concurso_numero = 'N/A';
if (preg_match('/ADP-(\d+)/', $file_name, $matches)) {
    $concurso_numero = $matches[1];
}

// Carga el archivo Excel
try {
    $spreadsheet = IOFactory::load($file_tmp_path);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    $data = $sheet->toArray(null, true, true, false);
} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    echo '<div class="alert alert-danger mt-3" role="alert">Error al leer el archivo Excel: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Variables para los conteos
$aprobados_p1 = [];
$total_en_nomina = [];
$mujeres_en_nomina = [];

// Búsqueda de la columna de género de forma robusta
$metodo_genero = 'inferido';
$genero_col_index = -1;
$encabezados = array_map('trim', $data[0]);
$encabezados_lower = array_map('strtolower', $encabezados);

$genero_col_index = array_search('sexo registral', $encabezados_lower);

if ($genero_col_index !== false) {
    $metodo_genero = 'columna';
}

// Inicializa el arreglo para el cálculo de género inferido
$genero_inferido_cache = [];
function inferirGenero($nombre) {
    global $genero_inferido_cache;
    if (isset($genero_inferido_cache[$nombre])) {
        return $genero_inferido_cache[$nombre];
    }
    
    $nombre_limpio = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚ\s]/u', '', $nombre);
    $partes_nombre = explode(' ', trim($nombre_limpio));
    $primer_nombre = strtolower($partes_nombre[0]);

    $femeninos = ['ana', 'maría', 'rosa', 'lorena', 'paula', 'karina', 'susy', 'andrea', 'tamara', 'daniel'];
    if (in_array($primer_nombre, $femeninos)) {
        return 'Femenino';
    }
    
    return 'Masculino';
}

// Muestra el nombre del concurso
echo '<h3 class="mt-4">Resultados de Concurso: ' . htmlspecialchars($concurso_numero) . '</h3>';

// Recorremos los datos desde la segunda fila (sin encabezados)
for ($i = 1; $i < $highestRow; $i++) {
    $fila = $data[$i];
    $tipo_postulacion = trim($fila[2]); // Columna 'Tipo'
    if (strtolower($tipo_postulacion) !== 'rec') {
        continue;
    }
    
    $run = trim($fila[3]); // Columna 'Run'
    if (empty($run)) continue;

    // Conteo de Aprobados P1
    $admisibilidad = trim($fila[6]); // Columna 'Admisibilidad'
    $notaP1 = $fila[7]; // Columna 'Nota P1'
    if (strtolower($admisibilidad) === 'cumple' && is_numeric($notaP1)) {
        $aprobados_p1[$run] = true;
    }

    // Conteo en Nómina y Mujeres en Nómina
    $posicion_nomina = $fila[15]; // Columna 'Posicion en nomina'
    if (is_numeric($posicion_nomina) && $posicion_nomina > 0) {
        $total_en_nomina[$run] = true;

        if ($metodo_genero === 'columna') {
            $genero = trim($fila[$genero_col_index]);
            if (strtolower($genero) === 'femenino' || strtolower($genero) === 'f') {
                $mujeres_en_nomina[$run] = true;
            }
        } else {
            $nombre_completo = trim($fila[4]); // Columna 'Nombre Completo'
            if (inferirGenero($nombre_completo) === 'Femenino') {
                $mujeres_en_nomina[$run] = true;
            }
        }
    }
}

// Métrica: Aprobados P1
echo '<h4 class="mt-4">Aprobados P1</h4>';
echo '<p><strong>Cálculo:</strong> Conteo de Run únicos.</p>';
echo '<p><strong>Filtros:</strong> Tipo = "REC", Admisibilidad = "Cumple" y Nota P1 con valor numérico.</p>';
echo '<div class="alert alert-info">Total de postulantes aprobados: <strong>' . count($aprobados_p1) . '</strong></div>';

// Métrica: Total en Nómina
echo '<h4 class="mt-4">Total en Nómina</h4>';
echo '<p><strong>Cálculo:</strong> Conteo de Run únicos.</p>';
echo '<p><strong>Filtros:</strong> Tipo = "REC" y Posición en nómina > 0.</p>';
echo '<div class="alert alert-info">Total de postulantes en nómina: <strong>' . count($total_en_nomina) . '</strong></div>';

// Métrica: Mujeres en Nómina
echo '<h4 class="mt-4">Mujeres en Nómina</h4>';
echo '<p><strong>Cálculo:</strong> Conteo de Run únicos (del grupo "Total en Nómina").</p>';
if ($metodo_genero === 'columna') {
    echo '<p><strong>Filtros:</strong> Género femenino (basado en la columna "Sexo registral").</p>';
    echo '<div class="alert alert-success"><strong>Éxito:</strong> Se encontró y se usó la columna "Sexo registral" para este cálculo.</div>';
    echo '<div class="alert alert-info">Total de mujeres en nómina: <strong>' . count($mujeres_en_nomina) . '</strong></div>';
} else {
    echo '<p><strong>Filtros:</strong> Género femenino (basado en el nombre).</p>';
    echo '<div class="alert alert-warning"><strong>Advertencia:</strong> La columna "Sexo registral" no se encontró. El cálculo se realizó infiriendo el género del nombre. Este método no es 100% preciso.</div>';
    echo '<div class="alert alert-info">Total de mujeres en nómina: <strong>' . count($mujeres_en_nomina) . '</strong></div>';
}
?>
