<?php
session_start();

// Si el usuario no ha iniciado sesión, lo redirige a la página de login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página de Inicio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Fondo con degradado que imita el estilo del Servicio Civil */
        .bg-serviciocivil {
            background-color: #1a426f;
            background-image: linear-gradient(180deg, #1a426f 0%, #2b5c90 100%);
        }
        .btn-analizar {
            background-color: #0b784a;
            color: white;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        .btn-analizar:hover {
            background-color: #085f39;
        }
        .form-label {
            font-weight: 500;
        }
        .file-input-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        /* Estilos para pestañas de nivel superior activas */
        .nav-tabs .nav-link {
            background-color: #e2e8f0; /* Fondo gris para pestañas inactivas */
            color: #4a5568; /* Color de texto gris oscuro */
        }
        .nav-tabs .nav-link.active {
            background-color: #ffffff !important; /* Fondo blanco para pestaña activa */
            color: #1a426f !important; /* Color de texto azul oscuro para pestaña activa */
            font-weight: bold;
            border-color: #dee2e6 #dee2e6 #fff !important;
            border-bottom: 4px solid #fff !important;
        }
        .nav-tabs .nav-link:not(.active):hover {
            background-color: #cbd5e0; /* Fondo gris más claro en hover */
        }
        /* Ajuste de ancho del contenedor principal */
        .max-w-7xl {
            max-width: 80%; /* 80% del ancho de la pantalla */
        }
    </style>
</head>
<body class="h-full bg-serviciocivil flex flex-col items-center justify-center">

    <div id="main-content" class="bg-white p-8 md:p-10 rounded-xl shadow-lg w-full max-w-7xl">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Menú Principal</h1>
            <a href="logout.php" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                Cerrar Sesión
            </a>
        </div>
        
        <p class="text-gray-600 mb-6">Selecciona el sitio al que deseas acceder.</p>
        
        <div class="d-flex flex-wrap justify-content-center gap-4">
            
            <div class="card shadow-sm" style="width: 18rem;">
                <div class="card-body text-center">
                    <h5 class="card-title fw-bold">Revisión de Planillas EC</h5>
                    <p class="card-text text-muted">Accede a la herramienta de revisión de planillas de pago CC.</p>
                    <a href="ec/index_ec.php" class="btn btn-primary bg-blue-600 hover:bg-blue-700 w-100">Ir al Sitio</a>
                </div>
            </div>

            <div class="card shadow-sm" style="width: 18rem;">
                <div class="card-body text-center">
                    <h5 class="card-title fw-bold">Análisis de Reporte de Actas PE</h5>
                    <p class="card-text text-muted">Accede a las herramientas de análisis de actas.</p>
                    <a href="pe/index_pe.php" class="btn btn-primary bg-blue-600 hover:bg-blue-700 w-100">Ir al Sitio</a>
                </div>
            </div>

            <div class="card shadow-sm" style="width: 18rem;">
                <div class="card-body text-center">
                    <h5 class="card-title fw-bold">Automatización (prototipo)</h5>
                    <p class="card-text text-muted">Accede a las herramientas de automatización y prototipos.</p>
                    <a href="automatizacion/index_auto.php" class="btn btn-primary bg-blue-600 hover:bg-blue-700 w-100">Ir al Sitio</a>
                </div>
            </div>
            
        </div>

    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Funcionalidad de tabs, si fuera necesaria
        function initTabs(containerId, localStorageKey) {
            var tabContainer = document.getElementById(containerId);
            if (!tabContainer) return;

            var activeTabId = localStorage.getItem(localStorageKey);
            var activeTab = activeTabId ? document.querySelector('#' + activeTabId) : null;

            if (activeTab) {
                new bootstrap.Tab(activeTab).show();
            } else {
                new bootstrap.Tab(tabContainer.querySelector('.nav-link')).show();
            }

            tabContainer.addEventListener('shown.bs.tab', function (event) {
                localStorage.setItem(localStorageKey, event.target.id);
            });
        }
    
        document.addEventListener('DOMContentLoaded', function() {
            initTabs('topLevelTab', 'activeTopLevelTab');
            initTabs('subTabEmpresas', 'activeSubTabEmpresas');
        });
    </script>
</body>
</html>