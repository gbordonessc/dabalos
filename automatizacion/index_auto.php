<?php
session_start();

// Si el usuario no ha iniciado sesión, lo redirige a la página de login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automatización (prototipo)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
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
        /* Ocultar el contenido principal por defecto */
        #main-content {
            display: none;
        }
        .nav-tabs .nav-link {
            background-color: #e2e8f0;
            color: #4a5568;
        }
        .nav-tabs .nav-link.active {
            background-color: #ffffff !important;
            color: #1a426f !important;
            font-weight: bold;
            border-color: #dee2e6 #dee2e6 #fff !important;
            border-bottom: 4px solid #fff !important;
        }
        .nav-tabs .nav-link:not(.active):hover {
            background-color: #cbd5e0;
        }
        .max-w-7xl {
            max-width: 80%;
        }
    </style>
</head>
<body class="h-full bg-gray-100 flex flex-col items-center justify-center">

    <div id="main-content" class="bg-serviciocivil h-full w-full flex items-center justify-center p-4">
        <div class="bg-white p-8 md:p-10 rounded-xl shadow-lg w-full max-w-7xl">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Automatización (prototipo)</h1>
                <a href="../index.php" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Volver al Menú
                </a>
            </div>
            
            <ul class="nav nav-tabs mb-3" id="topLevelTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="automatizacion-tab" data-bs-toggle="tab" data-bs-target="#automatizacion" type="button" role="tab" aria-controls="automatizacion" aria-selected="true">Herramientas</button>
                </li>
            </ul>

            <div class="tab-content" id="topLevelTabContent">
                <div class="tab-pane fade show active" id="automatizacion" role="tabpanel" aria-labelledby="automatizacion-tab">
                    <ul class="nav nav-tabs mb-3" id="subTabAutomatizacion" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="herramienta-1-tab" data-bs-toggle="tab" data-bs-target="#herramienta-1" type="button" role="tab" aria-controls="herramienta-1" aria-selected="true">Herramienta Prototipo 1</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="subTabAutomatizacionContent">
                        <div class="tab-pane fade show active" id="herramienta-1" role="tabpanel" aria-labelledby="herramienta-1-tab">
                            <h3 class="text-xl font-bold text-center mt-4 mb-2">Descripción de la Herramienta 1</h3>
                            <p class="text-center text-red-500 font-bold text-sm mb-4">Sube un archivo para comenzar el análisis.</p>
                            <form id="form-herramienta-1" action="procesar_herramienta_1.php" method="post" enctype="multipart/form-data" class="mt-4">
                                <div class="mb-4">
                                    <label for="archivo_auto" class="form-label block text-sm font-medium text-gray-700 mb-2">Selecciona un archivo Excel (.xls o .xlsx)</label>
                                    <input class="form-control block w-full px-3 py-2 text-gray-900 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" type="file" id="archivo_auto" name="archivo_auto" accept=".xls, .xlsx" required>
                                </div>
                                <div>
                                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        Analizar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function removeFileInput(button) {
            var fileInputGroup = button.parentNode;
            if (fileInputGroup && fileInputGroup.parentNode.children.length > 1) {
                fileInputGroup.remove();
            }
        }
    
        // Función para inicializar las pestañas
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
            var mainContent = document.getElementById('main-content');
            
            function showMainContent() {
                mainContent.style.display = 'flex';
                
                initTabs('topLevelTab', 'activeTopLevelTab_auto');
                initTabs('subTabAutomatizacion', 'activeSubTabAutomatizacion');
                
                setupFormListeners();
            }

            function setupFormListeners() {
                document.getElementById('form-herramienta-1').addEventListener('submit', function(event) {
                    handleFormSubmit(event, 'herramienta-1', 'procesar_herramienta_1.php');
                });
            }

            showMainContent();
            
            function handleFormSubmit(event, targetId, actionUrl) {
                event.preventDefault();
                var form = event.target;
                var formData = new FormData(form);
                var fileInput = document.getElementById('archivo_auto');
                
                if (fileInput.files.length === 0) {
                    alert('Error: Por favor, selecciona un archivo para subir.');
                    return;
                }

                fetch(actionUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    document.getElementById(targetId).innerHTML = html;
                    initTabs('subTabAutomatizacion', 'activeSubTabAutomatizacion');
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById(targetId).innerHTML = '<div class="alert alert-danger mt-3">Ocurrió un error al procesar la solicitud.</div>';
                });
            }
        });
    </script>
</body>
</html>