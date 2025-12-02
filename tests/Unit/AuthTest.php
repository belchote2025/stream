<?php

namespace UrresTv\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOException;

/**
 * Test para la clase de autenticación
 */
class AuthTest extends TestCase
{
    private $db;
    private $auth;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear conexión a base de datos de prueba
        try {
            $this->db = new PDO(
                'mysql:host=127.0.0.1;dbname=streaming_platform_test;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Limpiar tablas de prueba
            $this->db->exec("DELETE FROM users");

        } catch (PDOException $e) {
            $this->markTestSkipped('No se puede conectar a la base de datos de prueba: ' . $e->getMessage());
        }

        // Incluir archivos necesarios
        require_once __DIR__ . '/../../includes/config.php';
        require_once __DIR__ . '/../../includes/auth.php';

        $this->auth = new Auth($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            // Limpiar después de cada test
            $this->db->exec("DELETE FROM users");
        }

        parent::tearDown();
    }

    public function testRegisterWithValidData()
    {
        $result = $this->auth->register('testuser', 'test@example.com', 'password123', 'Test User');

        $this->assertTrue($result);

        // Verificar que el usuario fue creado
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute(['testuser']);
        $user = $stmt->fetch();

        $this->assertNotNull($user);
        $this->assertEquals('testuser', $user['username']);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertEquals('Test User', $user['full_name']);
        $this->assertEquals('user', $user['role']);
    }

    public function testRegisterWithEmptyUsername()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Todos los campos son obligatorios.');

        $this->auth->register('', 'test@example.com', 'password123');
    }

    public function testRegisterWithEmptyEmail()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Todos los campos son obligatorios.');

        $this->auth->register('testuser', '', 'password123');
    }

    public function testRegisterWithEmptyPassword()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Todos los campos son obligatorios.');

        $this->auth->register('testuser', 'test@example.com', '');
    }

    public function testRegisterWithInvalidEmail()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El formato del correo electrónico no es válido.');

        $this->auth->register('testuser', 'invalid-email', 'password123');
    }

    public function testRegisterWithShortPassword()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('La contraseña debe tener al menos 8 caracteres.');

        $this->auth->register('testuser', 'test@example.com', '12345');
    }

    public function testRegisterWithExistingUsername()
    {
        // Crear usuario primero
        $this->auth->register('testuser', 'test@example.com', 'password123');

        // Intentar registrar con mismo username
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El nombre de usuario o correo electrónico ya está en uso.');

        $this->auth->register('testuser', 'different@example.com', 'password123');
    }

    public function testRegisterWithExistingEmail()
    {
        // Crear usuario primero
        $this->auth->register('testuser', 'test@example.com', 'password123');

        // Intentar registrar con mismo email
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El nombre de usuario o correo electrónico ya está en uso.');

        $this->auth->register('differentuser', 'test@example.com', 'password123');
    }

    public function testLoginWithValidCredentials()
    {
        // Registrar usuario primero
        $this->auth->register('testuser', 'test@example.com', 'password123');

        // Iniciar sesión
        $result = $this->auth->login('test@example.com', 'password123');

        $this->assertTrue($result);
        $this->assertTrue($this->auth->isAuthenticated());
    }

    public function testLoginWithInvalidEmail()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Credenciales inválidas. Por favor, verifica tu correo y contraseña.');

        $this->auth->login('nonexistent@example.com', 'password123');
    }

    public function testLoginWithInvalidPassword()
    {
        // Registrar usuario primero
        $this->auth->register('testuser', 'test@example.com', 'password123');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Credenciales inválidas. Por favor, verifica tu correo y contraseña.');

        $this->auth->login('test@example.com', 'wrongpassword');
    }

    public function testLoginWithEmptyCredentials()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El correo electrónico y la contraseña son obligatorios.');

        $this->auth->login('', '');
    }

    public function testIsAuthenticatedBeforeLogin()
    {
        $this->assertFalse($this->auth->isAuthenticated());
    }

    public function testGetCurrentUserBeforeLogin()
    {
        $user = $this->auth->getCurrentUser();
        $this->assertNull($user);
    }

    public function testUpdateProfile()
    {
        // Registrar y hacer login
        $this->auth->register('testuser', 'test@example.com', 'password123', 'Test User');
        $this->auth->login('test@example.com', 'password123');

        // Actualizar perfil
        $result = $this->auth->updateProfile($_SESSION['user_id'], [
            'full_name' => 'Updated Name',
            'email' => 'updated@example.com'
        ]);

        $this->assertTrue($result);

        // Verificar cambios
        $user = $this->auth->getCurrentUser();
        $this->assertEquals('Updated Name', $user['full_name']);
        $this->assertEquals('updated@example.com', $user['email']);
    }

    public function testChangePassword()
    {
        // Registrar y hacer login
        $this->auth->register('testuser', 'test@example.com', 'password123');
        $this->auth->login('test@example.com', 'password123');

        // Cambiar contraseña
        $result = $this->auth->changePassword($_SESSION['user_id'], 'password123', 'newpassword123');

        $this->assertTrue($result);

        // Verificar que puede hacer login con nueva contraseña
        $this->auth->logout();
        $result = $this->auth->login('test@example.com', 'newpassword123');
        $this->assertTrue($result);
    }

    public function testChangePasswordWithWrongCurrentPassword()
    {
        // Registrar y hacer login
        $this->auth->register('testuser', 'test@example.com', 'password123');
        $this->auth->login('test@example.com', 'password123');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('La contraseña actual es incorrecta.');

        $this->auth->changePassword($_SESSION['user_id'], 'wrongpassword', 'newpassword123');
    }

    public function testChangePasswordWithShortNewPassword()
    {
        // Registrar y hacer login
        $this->auth->register('testuser', 'test@example.com', 'password123');
        $this->auth->login('test@example.com', 'password123');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('La nueva contraseña debe tener al menos 8 caracteres.');

        $this->auth->changePassword($_SESSION['user_id'], 'password123', 'short');
    }

    public function testLogout()
    {
        // Registrar y hacer login
        $this->auth->register('testuser', 'test@example.com', 'password123');
        $this->auth->login('test@example.com', 'password123');

        $this->assertTrue($this->auth->isAuthenticated());

        // Cerrar sesión
        $result = $this->auth->logout();
        $this->assertTrue($result);
        $this->assertFalse($this->auth->isAuthenticated());
    }

    public function testPasswordResetToken()
    {
        // Registrar usuario
        $this->auth->register('testuser', 'test@example.com', 'password123');

        // Enviar token de restablecimiento
        $result = $this->auth->sendPasswordResetEmail('test@example.com');
        $this->assertTrue($result);

        // Verificar que el token fue guardado
        $stmt = $this->db->prepare("SELECT reset_token FROM users WHERE email = ?");
        $stmt->execute(['test@example.com']);
        $user = $stmt->fetch();

        $this->assertNotNull($user['reset_token']);
        $this->assertEquals(64, strlen($user['reset_token'])); // 32 bytes en hex
    }

    public function testPasswordResetWithValidToken()
    {
        // Registrar usuario y enviar token
        $this->auth->register('testuser', 'test@example.com', 'password123');
        $this->auth->sendPasswordResetEmail('test@example.com');

        // Obtener token
        $stmt = $this->db->prepare("SELECT reset_token FROM users WHERE email = ?");
        $stmt->execute(['test@example.com']);
        $user = $stmt->fetch();
        $token = $user['reset_token'];

        // Restablecer contraseña
        $result = $this->auth->resetPassword($token, 'newpassword123');
        $this->assertTrue($result);

        // Verificar que puede hacer login con nueva contraseña
        $result = $this->auth->login('test@example.com', 'newpassword123');
        $this->assertTrue($result);
    }

    public function testPasswordResetWithInvalidToken()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El enlace de restablecimiento no es válido o ha expirado.');

        $this->auth->resetPassword('invalid-token', 'newpassword123');
    }
}
