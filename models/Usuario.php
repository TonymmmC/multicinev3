<?php
class Usuario {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($email, $password) {
        // Buscar usuario por email
        $query = "SELECT u.id, u.email, u.password, u.rol_id, 
                        pu.nombres, pu.apellidos 
                FROM users u
                LEFT JOIN perfiles_usuario pu ON u.id = pu.user_id
                WHERE u.email = ? AND u.activo = 1 AND u.deleted_at IS NULL";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Verificar si el usuario existe
        if ($result->num_rows === 0) {
            return false;
        }
        
        $user = $result->fetch_assoc();
        
        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            return false;
        }
        
        // Actualizar último login
        $query = "UPDATE users SET ultimo_login = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        
        // Registrar acceso
        $this->registrarAcceso($user['id'], 'LOGIN');
        
        // Establecer datos de sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['rol_id'] = $user['rol_id'];
        $_SESSION['nombres'] = $user['nombres'] ?? '';
        $_SESSION['apellidos'] = $user['apellidos'] ?? '';
        
        return true;
    }

    // Agregar método para registrar intentos fallidos
    public function registrarIntentoFallido($email) {
        $query = "INSERT INTO login_attempts (email, ip_address, attempted_at) 
                VALUES (?, ?, NOW())";
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $email, $ip);
        $stmt->execute();
    }
    
    public function registrar($email, $password, $nombres, $apellidos) {
        // Verificar si el email ya existe
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return [
                'success' => false,
                'message' => 'El email ya está registrado'
            ];
        }
        
        // Encriptar la contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Iniciar transacción
        $this->conn->begin_transaction();
        
        try {
            // Insertar el usuario
            $query = "INSERT INTO users (email, password, rol_id) VALUES (?, ?, 3)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ss", $email, $passwordHash);
            $stmt->execute();
            
            // Obtener el ID del usuario recién creado
            $userId = $this->conn->insert_id;
            
            // Crear el perfil del usuario
            $query = "INSERT INTO perfiles_usuario (user_id, nombres, apellidos) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iss", $userId, $nombres, $apellidos);
            $stmt->execute();
            
            // Confirmar la transacción
            $this->conn->commit();
            
            // Registrar el acceso
            $this->registrarAcceso($userId, 'LOGIN');
            
            return [
                'success' => true,
                'message' => 'Usuario registrado correctamente'
            ];
        } catch (Exception $e) {
            // Revertir la transacción en caso de error
            $this->conn->rollback();
            
            return [
                'success' => false,
                'message' => 'Error al registrar el usuario: ' . $e->getMessage()
            ];
        }
    }
    
    // Método para cerrar sesión
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
    
    // Método auxiliar para registrar accesos (necesario para cerrarSesion)
    private function registrarAcceso($userId, $tipoAcceso) {
        $query = "INSERT INTO logs_acceso (user_id, tipo_acceso, ip_origen, dispositivo, user_agent) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $dispositivo = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("issss", $userId, $tipoAcceso, $ip, $dispositivo, $userAgent);
        $stmt->execute();
    }

    // Método para enviar email de recuperación de contraseña
    public function enviarRecuperacionPassword($email) {
        // Verificar si el email existe
        $query = "SELECT id FROM users WHERE email = ? AND activo = 1 AND deleted_at IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'No se encontró una cuenta con ese email'
            ];
        }
        
        // Generar token único
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token válido por 1 hora
        
        // Guardar token en la base de datos
        $query = "DELETE FROM password_resets WHERE email = ?"; // Eliminar tokens anteriores
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        $query = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $email, $token, $expires);
        
        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Error al generar el token de recuperación'
            ];
        }
        
        // En un entorno real, aquí enviarías un email
        // Para fines de prueba, mostraremos el enlace directamente
        $resetUrl = "http://localhost/multicinev3/auth/reset-password.php?email=" . urlencode($email) . "&token=" . $token;
        
        // Simular envío de email
        return [
            'success' => true,
            'message' => 'Se han enviado instrucciones a tu email. Para fines de desarrollo, puedes usar este enlace: ' . 
                        '<a href="' . $resetUrl . '">Restablecer contraseña</a>'
        ];
    }

    // Método para restablecer la contraseña
    public function restablecerPassword($email, $token, $newPassword) {
        // Verificar si el token es válido y no ha expirado
        $query = "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'El enlace de recuperación es inválido o ha expirado'
            ];
        }
        
        // Actualizar la contraseña
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = ? WHERE email = ? AND activo = 1 AND deleted_at IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $passwordHash, $email);
        
        if (!$stmt->execute()) {
            return [
                'success' => false,
                'message' => 'Error al actualizar la contraseña'
            ];
        }
        
        // Eliminar el token usado
        $query = "DELETE FROM password_resets WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Tu contraseña ha sido actualizada correctamente. Ahora puedes iniciar sesión con tu nueva contraseña.'
        ];
    }
    // Método para detectar el tipo de dispositivo
    private function detectarDispositivo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/(android|iphone|ipad|ipod|blackberry|windows phone)/i', $userAgent)) {
            return 'Móvil';
        } elseif (preg_match('/(tablet|ipad)/i', $userAgent)) {
            return 'Tablet';
        } else {
            return 'Computadora';
        }
    }

    // Añade aquí cualquier otro método que necesites...
}
?>