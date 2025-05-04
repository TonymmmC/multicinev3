<?php
class Usuario {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($email, $password) {
        // Sanitizar el email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Consulta
        $query = "SELECT id, email, password, rol_id FROM users WHERE email = ? AND deleted_at IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verificar contraseña (la tabla tiene hash de contraseña)
            if (password_verify($password, $user['password'])) {
                // Iniciar sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['rol_id'] = $user['rol_id'];
                
                // Actualizar último login
                $this->updateLastLogin($user['id']);
                
                // Registrar en logs_acceso
                $this->registrarAcceso($user['id'], 'LOGIN');
                
                return true;
            }
        }
        
        return false;
    }
    
    private function updateLastLogin($userId) {
        $query = "UPDATE users SET ultimo_login = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    private function registrarAcceso($userId, $tipo) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        $query = "INSERT INTO logs_acceso (user_id, tipo_acceso, ip_origen, user_agent) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isss", $userId, $tipo, $ip, $userAgent);
        $stmt->execute();
    }
    
    public function registrar($email, $password, $nombres, $apellidos) {
        // Sanitizar datos
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Verificar si el email ya existe
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Este email ya está registrado'];
        }
        
        // Hash de la contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insertar usuario
        $this->conn->begin_transaction();
        
        try {
            // Crear usuario
            $query = "INSERT INTO users (email, password, rol_id) VALUES (?, ?, 3)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ss", $email, $passwordHash);
            $stmt->execute();
            
            $userId = $this->conn->insert_id;
            
            // Crear perfil
            $query = "INSERT INTO perfiles_usuario (user_id, nombres, apellidos) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iss", $userId, $nombres, $apellidos);
            $stmt->execute();
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Usuario registrado correctamente'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Error al registrar: ' . $e->getMessage()];
        }
    }
    
    public function cerrarSesion($userId) {
        $this->registrarAcceso($userId, 'LOGOUT');
        session_destroy();
    }
}