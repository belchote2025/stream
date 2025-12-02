<?php

namespace UrresTv\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test para funciones de configuración
 */
class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar variables de entorno de pruebas anteriores
        putenv('APP_ENV');
        putenv('DB_HOST');
        putenv('DB_USER');
        putenv('DB_PASS');
        putenv('DB_NAME');
        putenv('SITE_URL');
    }

    protected function tearDown(): void
    {
        // Limpiar después de cada test
        putenv('APP_ENV');
        putenv('DB_HOST');
        putenv('DB_USER');
        putenv('DB_PASS');
        putenv('DB_NAME');
        putenv('SITE_URL');

        parent::tearDown();
    }

    public function testConstantsAreDefined()
    {
        // Incluir el archivo de configuración
        require_once __DIR__ . '/../../includes/config.php';

        $this->assertTrue(defined('DB_HOST'));
        $this->assertTrue(defined('DB_USER'));
        $this->assertTrue(defined('DB_PASS'));
        $this->assertTrue(defined('DB_NAME'));
        $this->assertTrue(defined('APP_ENV'));
        $this->assertTrue(defined('SITE_URL'));
    }

    public function testDefaultDatabaseConfiguration()
    {
        // Test configuración por defecto
        putenv('APP_ENV=local');

        require_once __DIR__ . '/../../includes/config.php';

        $this->assertEquals('127.0.0.1', DB_HOST);
        $this->assertEquals('root', DB_USER);
        $this->assertEquals('', DB_PASS);
        $this->assertEquals('streaming_platform', DB_NAME);
    }

    public function testEnvironmentOverrides()
    {
        // Test que las variables de entorno tienen prioridad
        putenv('APP_ENV=local');
        putenv('DB_HOST=testhost');
        putenv('DB_USER=testuser');
        putenv('DB_PASS=testpass');
        putenv('DB_NAME=testdb');

        require_once __DIR__ . '/../../includes/config.php';

        $this->assertEquals('testhost', DB_HOST);
        $this->assertEquals('testuser', DB_USER);
        $this->assertEquals('testpass', DB_PASS);
        $this->assertEquals('testdb', DB_NAME);
    }

    public function testSiteUrlConstruction()
    {
        // Simular servidor web
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/streaming-platform/';

        putenv('APP_ENV=local');

        require_once __DIR__ . '/../../includes/config.php';

        $this->assertStringContains('localhost', SITE_URL);
        $this->assertStringContains('streaming-platform', SITE_URL);
    }

    public function testSanitizeInput()
    {
        require_once __DIR__ . '/../../includes/config.php';

        $input = "  <script>alert('test')</script>  ";
        $expected = "<script>alert(&#039;test&#039;)</script>";

        $this->assertEquals($expected, sanitizeInput($input));
    }

    public function testCsrfTokenGeneration()
    {
        require_once __DIR__ . '/../../includes/config.php';

        // Limpiar sesión
        $_SESSION = [];

        $token1 = generateCsrfToken();
        $token2 = generateCsrfToken();

        $this->assertEquals($token1, $token2); // Debe ser el mismo token
        $this->assertIsString($token1);
        $this->assertEquals(64, strlen($token1)); // 32 bytes en hex = 64 caracteres
    }

    public function testCsrfTokenVerification()
    {
        require_once __DIR__ . '/../../includes/config.php';

        // Limpiar sesión
        $_SESSION = [];

        $token = generateCsrfToken();

        $this->assertTrue(verifyCsrfToken($token));
        $this->assertFalse(verifyCsrfToken('invalid-token'));
    }

    public function testHashAlgoConstant()
    {
        require_once __DIR__ . '/../../includes/config.php';

        $this->assertEquals(PASSWORD_BCRYPT, HASH_ALGO);
        $this->assertEquals(['cost' => 12], HASH_OPTIONS);
    }
}
