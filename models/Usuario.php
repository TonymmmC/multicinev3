public function cerrarSesion($userId) {
        $this->registrarAcceso($userId, 'LOGOUT');
        session_destroy();
    }
    
    // Obtener datos del perfil del usuario
    public function obtenerPerfil($userId) {
        $query = "SELECT u.id, u.email, u.ultimo_login, u.activo, u.rol_id,
                         r.nombre as rol_nombre,
                         pu.nombres, pu.apellidos, pu.fecha_nacimiento, 
                         pu.celular, pu.direccion, pu.nit_ci,
                         pu.idioma_preferido, pu.modo_oscuro,
                         m.url as imagen_url
                  FROM users u
                  LEFT JOIN perfiles_usuario pu ON u.id = pu.user_id
                  LEFT JOIN roles r ON u.rol_id = r.id
                  LEFT JOIN multimedia m ON pu.imagen_id = m.id
                  WHERE u.id = ? AND u.deleted_at IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    // Actualizar perfil del usuario
    public function actualizarPerfil($userId, $datos) {
        // Sanitizar datos
        $nombres = filter_var($datos['nombres'], FILTER_SANITIZE_STRING);
        $apellidos = filter_var($datos['apellidos'], FILTER_SANITIZE_STRING);
        $celular = filter_var($datos['celular'], FILTER_SANITIZE_STRING);
        $direccion = filter_var($datos['direccion'], FILTER_SANITIZE_STRING);
        $nitCi = filter_var($datos['nit_ci'], FILTER_SANITIZE_STRING);
        $fechaNacimiento = !empty($datos['fecha_nacimiento']) ? $datos['fecha_nacimiento'] : null;
        $idioma = $datos['idioma_preferido'] ?? 'es';
        $modoOscuro = isset($datos['modo_oscuro']) ? 1 : 0;
        
        $this->conn->begin_transaction();
        
        try {
            // Verificar si ya existe un perfil
            $query = "SELECT id FROM perfiles_usuario WHERE user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Actualizar perfil existente
                $query = "UPDATE perfiles_usuario SET 
                          nombres = ?, 
                          apellidos = ?, 
                          fecha_nacimiento = ?, 
                          celular = ?, 
                          direccion = ?, 
                          nit_ci = ?,
                          idioma_preferido = ?,
                          modo_oscuro = ?
                          WHERE user_id = ?";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param(
                    "sssssssii", 
                    $nombres, 
                    $apellidos, 
                    $fechaNacimiento, 
                    $celular, 
                    $direccion, 
                    $nitCi,
                    $idioma,
                    $modoOscuro,
                    $userId
                );
            } else {
                // Crear nuevo perfil
                $query = "INSERT INTO perfiles_usuario 
                          (user_id, nombres, apellidos, fecha_nacimiento, celular, direccion, nit_ci, idioma_preferido, modo_oscuro) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param(
                    "isssssssi", 
                    $userId, 
                    $nombres, 
                    $apellidos, 
                    $fechaNacimiento, 
                    $celular, 
                    $direccion, 
                    $nitCi,
                    $idioma,
                    $modoOscuro
                );
            }
            
            $stmt->execute();
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Perfil actualizado correctamente'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Error al actualizar el perfil: ' . $e->getMessage()];
        }
    }
    
    // Cambiar contraseña
    public function cambiarPassword($userId, $currentPassword, $newPassword) {
        // Verificar contraseña actual
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($currentPassword, $user['password'])) {
                // Contraseña actual correcta, actualizar a la nueva
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("si", $passwordHash, $userId);
                
                if ($stmt->execute()) {
                    return ['success' => true, 'message' => 'Contraseña actualizada correctamente'];
                } else {
                    return ['success' => false, 'message' => 'Error al actualizar la contraseña'];
                }
            } else {
                return ['success' => false, 'message' => 'La contraseña actual es incorrecta'];
            }
        }
        
        return ['success' => false, 'message' => 'Usuario no encontrado'];
    }