<?php
session_start();

// Cargar las variables de entorno
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Solución para los caracteres extraños
header('Content-Type: text/html; charset=utf-8');

// Definir usuarios y contraseñas válidos desde el .env
$users = [];
foreach ($_ENV as $key => $value) {
    if (strpos($key, 'USER_') === 0) {
        $userIdentifier = substr($key, 5);
        $passKey = 'PASS_' . $userIdentifier;
        if (isset($_ENV[$passKey])) {
            $users[$value] = $_ENV[$passKey];
        }
    }
}

// Obtener los datos del formulario y asegurar que no estén vacíos
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// Verificar si las credenciales son correctas
if (isset($users[$username]) && $users[$username] === $password) {
    // Si son correctas, establece la variable de sesión
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    
    // Redirige al menú principal (menu.php)
    header('Location: menu.php');
    exit;
} else {
    // Si son incorrectas, redirige al login con un mensaje de error
    header('Location: index.php?error=1');
    exit;
}
?>
