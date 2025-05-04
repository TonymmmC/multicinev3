<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../includes/functions.php';

class UsuarioController {
    private $usuarioModel;
    
    public function __construct($db) {
        $this->usuarioModel = new Usuario($db);
    }
    
    // Maneja el proceso de login
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            return [
                'success' => false, 
                'message' => 'Por favor ingrese su email y contraseña'
            ];
        }
        
        if ($this->usuarioModel->login($email, $password)) {
            setMensaje('Has iniciado sesión correctamente', 'success');
            return ['success' => true];
        } else {
            return [
                'success' => false, 
                'message' => 'Credenciales incorrectas'
            ];
        }
    }
    
    // Maneja el proceso de registro
    public function registro($email, $password, $passwordConfirm, $nombres, $apellidos) {
        // Validaciones
        $errores = [];
        
        if (empty($email)) {
            $errores[] = 'El email es obligatorio';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no es válido';
        }
        
        if (empty($password)) {
            $errores[] = 'La contraseña es obligatoria';
        } elseif (strlen($password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres';
        }
        
        if ($password !== $passwordConfirm) {
            $errores[] = 'Las contraseñas no coinciden';
        }
        
        if (empty($nombres)) {
            $errores[] = 'Los nombres son obligatorios';
        }
        
        if (empty($apellidos)) {
            $errores[] = 'Los apellidos son obligatorios';
        }
        
        if (!empty($errores)) {
            return [
                'success' => false,
                'message' => 'Por favor corrija los siguientes errores:',
                'errores' => $errores
            ];
        }
        
        // Registrar al usuario
        return $this->usuarioModel->registrar($email, $password, $nombres, $apellidos);
    }
    
    // Cierra la sesión del usuario
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->usuarioModel->cerrarSesion($_SESSION['user_id']);
            setMensaje('Has cerrado sesión correctamente', 'success');
        }
    }
    
    // Obtiene los datos del perfil del usuario
    public function obtenerPerfil($userId) {
        return $this->usuarioModel->obtenerPerfil($userId);
    }
    
    // Actualiza el perfil del usuario
    public function actualizarPerfil($userId, $datos) {
        return $this->usuarioModel->actualizarPerfil($userId, $datos);
    }
    
    // Cambia la contraseña del usuario
    public function cambiarPassword($userId, $currentPassword, $newPassword) {
        return $this->usuarioModel->cambiarPassword($userId, $currentPassword, $newPassword);
    }

    // Enviar email de recuperación de contraseña
    public function enviarRecuperacionPassword($email) {
        return $this->usuarioModel->enviarRecuperacionPassword($email);
    }

    // Restablecer contraseña
    public function restablecerPassword($email, $token, $newPassword) {
        return $this->usuarioModel->restablecerPassword($email, $token, $newPassword);
    }
}
?>