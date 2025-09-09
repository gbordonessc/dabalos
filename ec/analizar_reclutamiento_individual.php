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
    echo '<div class="alert alert-danger mt-3" role="alert">Error al leer el archivo: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Variables para almacenar las métricas
$aprobados_p1 = [];
$total_en_nomina = [];
$mujeres_en_nomina = [];
$metodo_genero = ''; // Variable para indicar el método de detección de género

// Obtener los índices de las columnas y hacer la búsqueda de manera robusta
$headers = array_map('trim', $data[0]);
$headers_lower = array_map('strtolower', $headers);

$run_col_index = array_search('run', $headers_lower);
$tipo_col_index = array_search('tipo', $headers_lower);
$pasa_a_p2p3_col_index = array_search('pasa a p2p3', $headers_lower);
$posicion_nomina_col_index = array_search('posicion en nomina', $headers_lower);
$nombre_completo_col_index = array_search('nombre completo', $headers_lower);
$sexo_registral_col_index = array_search('sexo registral', $headers_lower);

if ($run_col_index === false || $tipo_col_index === false || $pasa_a_p2p3_col_index === false || $posicion_nomina_col_index === false || $nombre_completo_col_index === false) {
    echo '<div class="alert alert-danger mt-3" role="alert">Error: El archivo no contiene todas las columnas necesarias (Run, Tipo, Pasa a P2P3, Posicion en nomina, Nombre Completo).</div>';
    exit;
}

// Recorrer las filas del archivo Excel (empezando desde la segunda fila para ignorar los encabezados)
for ($row = 1; $row < $highestRow; $row++) {
    $row_data = $data[$row];

    $run = trim($row_data[$run_col_index]);
    $tipo = trim($row_data[$tipo_col_index]);
    $pasa_a_p2p3 = trim($row_data[$pasa_a_p2p3_col_index]);
    $posicion_nomina = trim($row_data[$posicion_nomina_col_index]);
    $nombre_completo = trim($row_data[$nombre_completo_col_index]);

    // Aplicar filtros para "Aprobados P1"
    if ($tipo === 'REC' && $pasa_a_p2p3 === 'Sigue') {
        if (!in_array($run, $aprobados_p1)) {
            $aprobados_p1[] = $run;
        }
    }

    // Aplicar filtros para "Total en Nómina"
    if ($tipo === 'REC' && is_numeric($posicion_nomina) && $posicion_nomina > 0) {
        if (!in_array($run, $total_en_nomina)) {
            $total_en_nomina[] = $run;
        }
    }

    // Aplicar filtros para "Mujeres en Nómina" (subgrupo de Total en Nómina)
    if (in_array($run, $total_en_nomina)) {
        $es_femenino = false;
        
        // Priorizar la columna "Sexo registral" si existe
        if ($sexo_registral_col_index !== false) {
            $sexo_registral = trim($row_data[$sexo_registral_col_index]);
            if (strtolower($sexo_registral) === 'femenino') {
                $es_femenino = true;
                $metodo_genero = 'columna';
            }
        } else {
            // Si la columna "Sexo registral" no existe, inferir del nombre
            $es_femenino = detectarGeneroFemenino($nombre_completo);
            if ($es_femenino) {
                $metodo_genero = 'nombre';
            }
        }

        if ($es_femenino) {
            if (!in_array($run, $mujeres_en_nomina)) {
                $mujeres_en_nomina[] = $run;
            }
        }
    }
}

/**
 * Función simple para detectar género femenino basado en el nombre.
 * Se puede mejorar con una lógica más avanzada o una base de datos de nombres.
 */
function detectarGeneroFemenino($nombre) {
    $nombre_parts = explode(' ', strtolower(trim($nombre)));
    $primer_nombre = $nombre_parts[0];
    
    // Lista más amplia de nombres comunes femeninos en Chile
    $nombres_femeninos = [
        'ana', 'maria', 'luisa', 'carla', 'paula', 'lorena', 'javiera', 'catalina', 'camila', 'francisca',
        'isidora', 'valentina', 'sofia', 'antonia', 'constanza', 'alejandra', 'daniela', 'monica', 'veronica',
        'natalia', 'fernanda', 'andrea', 'macarena', 'cecilia', 'tamara', 'loreto', 'elena', 'victoria'
    ];
    
    foreach ($nombres_femeninos as $n_femenino) {
        if (strpos($primer_nombre, $n_femenino) !== false) {
            return true;
        }
    }
    return false;
}

// Presentar los resultados en la página
echo '<div class="container mt-4">';
echo '<h3>Resultados del Análisis de Reclutamiento Individual - N° Concurso: ' . htmlspecialchars($concurso_numero) . '</h3>';
echo '<hr>';

// Métrica: Aprobados P1
echo '<h4 class="mt-4">Aprobados P1</h4>';
echo '<p><strong>Cálculo:</strong> Conteo de Run únicos.</p>';
echo '<p><strong>Filtros:</strong> Tipo = REC y Pasa a P2P3 = Sigue.</p>';
echo '<div class="alert alert-info">Total de postulantes aprobados: <strong>' . count($aprobados_p1) . '</strong></div>';

// Métrica: Total en Nómina
echo '<h4 class="mt-4">Total en Nómina</h4>';
echo '<p><strong>Cálculo:</strong> Conteo de Run únicos.</p>';
echo '<p><strong>Filtros:</strong> Tipo = REC y Posición en nómina > 0.</p>';
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

echo '</div>';
?>