<?php
session_start();
include("conexion.php"); // Asegúrate de que este archivo existe y conecta a la BD
include("funciones.php"); // Si tienes funciones comunes, inclúyelas

// Verificar sesión activa
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Manejo de subida de archivos
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $directorio = "uploads/cc_multiconcurso_rec/";
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    $archivoTmp = $_FILES['archivo']['tmp_name'];
    $nombreArchivo = basename($_FILES['archivo']['name']);
    $rutaDestino = $directorio . $nombreArchivo;

    if (move_uploaded_file($archivoTmp, $rutaDestino)) {
        $mensaje = "Archivo subido exitosamente: " . htmlspecialchars($nombreArchivo);

        // Si necesitas procesar el archivo como en analizar_multiconcurso.php, puedes agregar aquí el código
        // Ejemplo: guardar en BD
        $sql = "INSERT INTO documentos_multiconcurso_rec (nombre, ruta, usuario, fecha) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$nombreArchivo, $rutaDestino, $_SESSION['usuario']]);
    } else {
        $mensaje = "Error al subir el archivo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CC Multiconcurso REC</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <h1>CC Multiconcurso REC</h1>

    <?php if ($mensaje): ?>
        <p><?php echo $mensaje; ?></p>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <label for="archivo">Seleccionar archivo:</label>
        <input type="file" name="archivo" id="archivo" required>
        <button type="submit">Subir</button>
    </form>

    <hr>

    <h2>Archivos Subidos</h2>
    <table border="1" cellpadding="5">
        <tr>
            <th>Nombre</th>
            <th>Ruta</th>
            <th>Usuario</th>
            <th>Fecha</th>
        </tr>
        <?php
        $stmt = $conexion->query("SELECT * FROM documentos_multiconcurso_rec ORDER BY fecha DESC");
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>
                    <td>" . htmlspecialchars($fila['nombre']) . "</td>
                    <td><a href='" . htmlspecialchars($fila['ruta']) . "' target='_blank'>Ver</a></td>
                    <td>" . htmlspecialchars($fila['usuario']) . "</td>
                    <td>" . htmlspecialchars($fila['fecha']) . "</td>
                  </tr>";
        }
        ?>
    </table>
</body>
</html>
