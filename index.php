<?php
// Inicia la sesión PHP
session_start();

// Verifica si el usuario ya ha iniciado sesión
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Si la sesión está activa, redirige al usuario a la página de menú
    header('Location: menu.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Iniciar Sesión</h1>
        <?php
            // Muestra un mensaje de error si existe en la URL
            if (isset($_GET['error']) && $_GET['error'] == '1') {
                echo '<div class="error-message">Usuario o contraseña incorrectos.</div>';
            }
        ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-button">Acceder</button>
        </form>
    </div>
</body>
</html>