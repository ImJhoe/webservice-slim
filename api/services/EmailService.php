<?php
// ===== 1. CLASE PARA MANEJO DE EMAILS =====
// api/services/EmailService.php

namespace App\Services;

class EmailService 
{
    private $fromEmail = 'sistema@clinica.com';
    private $fromName = 'Sistema Citas Médicas';

    /**
     * Generar contraseña temporal
     */
    public function generarContrasenaTemporalSegura($longitud = 8) {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $contrasena = '';
        
        for ($i = 0; $i < $longitud; $i++) {
            $contrasena .= $caracteres[rand(0, strlen($caracteres) - 1)];
        }
        
        return $contrasena;
    }

    /**
     * Generar username único
     */
    public function generarUsername($nombres, $apellidos, $conexion) {
        $baseUsername = strtolower(
            str_replace(' ', '.', trim($nombres)) . '.' . 
            str_replace(' ', '.', trim($apellidos))
        );
        
        // Remover acentos y caracteres especiales
        $baseUsername = $this->removeAccents($baseUsername);
        
        // Verificar si existe
        $contador = 1;
        $username = $baseUsername;
        
        while ($this->usernameExiste($username, $conexion)) {
            $username = $baseUsername . $contador;
            $contador++;
        }
        
        return $username;
    }

    private function removeAccents($string) {
        $unwanted_array = [
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 
            'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 
            'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 
            'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 
            'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 
            'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        ];
        return strtr($string, $unwanted_array);
    }

    private function usernameExiste($username, $conexion) {
        try {
            $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :username");
            $stmt->execute([':username' => $username]);
            return $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Enviar credenciales al médico
     */
    public function enviarCredencialesMedico($datos) {
        $asunto = "Bienvenido Dr/a. {$datos['nombres']} - Credenciales de Acceso";
        
        $mensaje = "
        Estimado Dr/a. {$datos['nombres']} {$datos['apellidos']},

        ¡Bienvenido/a al Sistema de Citas Médicas!

        Su cuenta ha sido creada exitosamente. A continuación, sus credenciales de acceso:

        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        👤 Usuario: {$datos['username']}
        🔐 Contraseña Temporal: {$datos['contrasena_temporal']}
        📧 Correo: {$datos['correo']}
        🏥 Especialidad: {$datos['especialidad']}
        📋 Título: {$datos['titulo_profesional']}
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        ⚠️  IMPORTANTE - PRIMERA CONEXIÓN:
        1. Inicie sesión con estas credenciales
        2. Cambie su contraseña inmediatamente por seguridad
        3. Configure su horario de atención

        📱 Puede acceder desde:
        • Aplicación móvil del personal médico
        • Portal web del médico

        ✅ Sus próximos pasos:
        - Configurar horarios de atención
        - Revisar su perfil profesional
        - Familiarizarse con el sistema de citas

        Para soporte técnico, contacte al administrador del sistema.

        Saludos cordiales,
        Equipo de Sistemas
        Clínica Médica
        ";

        return $this->enviarCorreo($datos['correo'], $asunto, $mensaje);
    }

    /**
     * Enviar credenciales al paciente
     */
    public function enviarCredencialesPaciente($datos) {
        $asunto = "Bienvenido/a {$datos['nombres']} - Credenciales Portal del Paciente";
        
        $mensaje = "
        Estimado/a {$datos['nombres']} {$datos['apellidos']},

        ¡Bienvenido/a al Sistema de Citas Médicas!

        Su cuenta de paciente ha sido creada exitosamente. A continuación, sus credenciales para acceder al portal del paciente:

        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        👤 Usuario: {$datos['username']}
        🔐 Contraseña Temporal: {$datos['contrasena_temporal']}
        📧 Correo: {$datos['correo']}
        🆔 Cédula: {$datos['cedula']}
        📅 Fecha de Nacimiento: {$datos['fecha_nacimiento']}
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        ⚠️  IMPORTANTE - PRIMERA CONEXIÓN:
        1. Inicie sesión con estas credenciales
        2. Cambie su contraseña inmediatamente por seguridad
        3. Complete su perfil médico si es necesario

        📱 Con su cuenta podrá:
        • Ver el historial de sus citas médicas
        • Acceder a sus resultados de laboratorio
        • Solicitar citas (si está habilitado)
        • Actualizar sus datos personales

        📋 RECORDATORIO DE SU CITA:
        Si se registró para una cita médica, recibirá próximamente los detalles 
        de su appointment por correo electrónico separado.

        Para soporte o consultas, contacte a recepción de la clínica.

        Saludos cordiales,
        Equipo de Atención al Paciente
        Clínica Médica
        ";

        return $this->enviarCorreo($datos['correo'], $asunto, $mensaje);
    }

    /**
     * Función base para enviar correos
     */
    private function enviarCorreo($destinatario, $asunto, $mensaje) {
        try {
            // Headers para correo HTML
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "Reply-To: {$this->fromEmail}\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

            // Enviar correo usando función mail() de PHP
            $resultado = mail($destinatario, $asunto, $mensaje, $headers);
            
            if ($resultado) {
                error_log("✅ Correo enviado exitosamente a: {$destinatario}");
                return true;
            } else {
                error_log("❌ Error enviando correo a: {$destinatario}");
                return false;
            }
            
        } catch (\Exception $e) {
            error_log("❌ Excepción enviando correo: " . $e->getMessage());
            return false;
        }
    }
}
