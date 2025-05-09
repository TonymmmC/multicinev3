<?php
// update_multimedia_urls.php
// Script para actualizar URLs de multimedia a rutas locales

// Inicializar conexión a la base de datos
require_once 'config/database.php';
$conn = require 'config/database.php';

if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Definir tipos de medios y sus rutas correspondientes
$mediaTypes = [
    'trailer' => [
        'local_paths' => [
            'assets/videos/trailers/{pelicula_id}.mp4',
            'videos/trailers/{pelicula_id}.mp4'
        ],
        'regex' => '/^https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/'
    ],
    'banner' => [
        'local_paths' => [
            'assets/img/banners/{pelicula_id}.jpg',
            'img/banners/{pelicula_id}.jpg'
        ]
    ],
    'poster' => [
        'local_paths' => [
            'assets/img/posters/{pelicula_id}.jpg',
            'img/posters/{pelicula_id}.jpg'
        ]
    ],
    'galeria' => [
        'local_paths' => [
            'assets/img/gallery/{pelicula_id}/{orden}.jpg',
            'img/gallery/{pelicula_id}/{orden}.jpg'
        ]
    ]
];

// 1. Actualizar URLs de trailers en tabla peliculas_detalle
$trailerSql = "SELECT pd.pelicula_id, pd.url_trailer 
               FROM peliculas_detalle pd 
               WHERE pd.url_trailer LIKE 'http%'";
$trailerResult = $conn->query($trailerSql);

$updatedTrailers = 0;
if ($trailerResult && $trailerResult->num_rows > 0) {
    echo "<h2>Actualizando trailers</h2>";
    
    while ($row = $trailerResult->fetch_assoc()) {
        $peliculaId = $row['pelicula_id'];
        $url = $row['url_trailer'];
        
        // Solo actualizamos si es una URL de YouTube
        if (preg_match($mediaTypes['trailer']['regex'], $url, $matches)) {
            // Crear directorios para cada ruta posible
            foreach ($mediaTypes['trailer']['local_paths'] as $pathTemplate) {
                $localPath = str_replace('{pelicula_id}', $peliculaId, $pathTemplate);
                $dir = dirname($localPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
            
            // Actualizar ruta en la base de datos
            $updateSql = "UPDATE peliculas_detalle SET url_trailer = '' WHERE pelicula_id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("i", $peliculaId);
            if ($stmt->execute()) {
                $updatedTrailers++;
                echo "Trailer para película ID $peliculaId actualizado. Debes subir manualmente el video a las rutas posibles:<br>";
                foreach ($mediaTypes['trailer']['local_paths'] as $pathTemplate) {
                    $localPath = str_replace('{pelicula_id}', $peliculaId, $pathTemplate);
                    echo "- $localPath<br>";
                }
            } else {
                echo "Error al actualizar trailer para película ID $peliculaId: " . $stmt->error . "<br>";
            }
        }
    }
    
    echo "<p>Total de trailers actualizados: $updatedTrailers</p>";
}

// 2. Actualizar URLs en tabla multimedia
$mediaSql = "SELECT m.id, m.tipo, m.url, mp.pelicula_id, mp.proposito, mp.orden 
             FROM multimedia m 
             JOIN multimedia_pelicula mp ON m.id = mp.multimedia_id 
             WHERE m.url LIKE 'http%'";
$mediaResult = $conn->query($mediaSql);

$updatedMedia = 0;
if ($mediaResult && $mediaResult->num_rows > 0) {
    echo "<h2>Actualizando multimedia</h2>";
    
    while ($row = $mediaResult->fetch_assoc()) {
        $mediaId = $row['id'];
        $mediaType = $row['tipo'];
        $url = $row['url'];
        $peliculaId = $row['pelicula_id'];
        $proposito = $row['proposito'];
        $orden = $row['orden'] ?? 1;
        
        $localPaths = [];
        
        // Determinar la ruta local según el tipo y propósito
        if ($proposito == 'banner' && isset($mediaTypes['banner'])) {
            foreach ($mediaTypes['banner']['local_paths'] as $pathTemplate) {
                $localPaths[] = str_replace('{pelicula_id}', $peliculaId, $pathTemplate);
            }
        } elseif ($proposito == 'poster' && isset($mediaTypes['poster'])) {
            foreach ($mediaTypes['poster']['local_paths'] as $pathTemplate) {
                $localPaths[] = str_replace('{pelicula_id}', $peliculaId, $pathTemplate);
            }
        } elseif ($proposito == 'galeria' && isset($mediaTypes['galeria'])) {
            foreach ($mediaTypes['galeria']['local_paths'] as $pathTemplate) {
                $localPaths[] = str_replace(['{pelicula_id}', '{orden}'], [$peliculaId, $orden], $pathTemplate);
            }
        }
        
        if (!empty($localPaths)) {
            // Crear directorios para cada ruta posible
            foreach ($localPaths as $path) {
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
            
            // Utilizamos la primera ruta para la actualización de la BD
            $localPath = $localPaths[0];
            
            // Actualizar ruta en la base de datos
            $updateSql = "UPDATE multimedia SET url = ? WHERE id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("si", $localPath, $mediaId);
            if ($stmt->execute()) {
                $updatedMedia++;
                echo "Multimedia ID $mediaId ($proposito) para película ID $peliculaId actualizado.<br>";
                echo "Debes subir manualmente el archivo a una de estas rutas:<br>";
                foreach ($localPaths as $path) {
                    echo "- $path<br>";
                }
            } else {
                echo "Error al actualizar multimedia ID $mediaId: " . $stmt->error . "<br>";
            }
        }
    }
    
    echo "<p>Total de multimedia actualizada: $updatedMedia</p>";
}

// Resumen final
echo "<h2>Resumen</h2>";
echo "<p>Total de trailers actualizados: $updatedTrailers</p>";
echo "<p>Total de multimedia actualizada: $updatedMedia</p>";
echo "<p>Total general: " . ($updatedTrailers + $updatedMedia) . "</p>";
echo "<p>Nota importante: Las rutas se han actualizado en la base de datos, pero debes subir manualmente los archivos de multimedia a las rutas correspondientes.</p>";
?>