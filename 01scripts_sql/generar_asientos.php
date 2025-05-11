<?php
/**
 * Script para generar los INSERT SQL de asientos desde una matriz de cine
 * Todos los asientos son tipo standard
 */
function generarSQLAsientos($sala_id, $matriz_texto) {
    $sql = "-- Inserción de asientos para sala ID: $sala_id\n";
    $sql .= "INSERT INTO asientos (sala_id, fila, numero, tipo, disponible) VALUES\n";
    
    $lineas = explode("\n", $matriz_texto);
    $asientos = [];
    $asientos_unicos = []; // Registro para prevenir duplicados
    
    foreach ($lineas as $linea) {
        if (strpos($linea, '[') === false) continue;
        
        preg_match('/\[(.*?)\]/', $linea, $matches);
        if (empty($matches[1])) continue;
        
        $posiciones = explode(' - ', $matches[1]);
        
        foreach ($posiciones as $posicion) {
            $posicion = trim($posicion);
            
            if ($posicion == 'NULL') continue;
            
            $fila = substr($posicion, 0, 1);
            $numero = intval(substr($posicion, 1));
            
            // Evitar duplicados
            $clave_unica = "$fila-$numero";
            if (!isset($asientos_unicos[$clave_unica])) {
                $asientos[] = "($sala_id, '$fila', $numero, 'standard', 1)";
                $asientos_unicos[$clave_unica] = true;
            }
        }
    }
    
    $sql .= implode(",\n", $asientos) . ";";
    return $sql;
}

// Ejemplo de uso:
$sala_id = 14; // Cambiar por el ID de sala correspondiente
$matriz_asientos = <<<'EOT'
/* [A1 - A2 - A3 - A4 - NULL - A5 - A6 - A7 - A8] */
/* [B1 - B2 - B3 - B4 - NULL - B5 - B6 - B7 - B8] */
/* [C1 - C2 - C3 - C4 - NULL - C5 - C6 - C7 - C8] */
/* [D1 - D2 - D3 - D4 - NULL - D5 - D6 - D7 - D8] */
/* [E1 - E2 - E3 - E4 - NULL - E5 - E6 - E7 - E8] */
/* [F1 - F2 - F3 - F4 - NULL - F5 - F6 - F7 - F8] */
EOT;

$sql = generarSQLAsientos($sala_id, $matriz_asientos);
echo $sql;
?>