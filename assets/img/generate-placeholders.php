<?php
/**
 * Script para generar imágenes placeholder estáticas
 * Ejecutar una vez para crear los archivos .jpg
 */

// Verificar que GD está disponible
if (!extension_loaded('gd')) {
    die('GD library no está disponible. Instala php-gd.');
}

// Función para generar poster
function generatePoster($filename) {
    $width = 500;
    $height = 750;
    
    $img = imagecreatetruecolor($width, $height);
    
    // Colores
    $bgColor = imagecolorallocate($img, 20, 20, 20);
    $textColor = imagecolorallocate($img, 255, 255, 255);
    $accentColor = imagecolorallocate($img, 229, 9, 20);
    
    // Fondo
    imagefill($img, 0, 0, $bgColor);
    
    // Gradiente
    for ($i = 0; $i < $height; $i++) {
        $alpha = (int)(($i / $height) * 30);
        $color = imagecolorallocatealpha($img, 229, 9, 20, $alpha);
        imageline($img, 0, $i, $width, $i, $color);
    }
    
    // Texto
    $text = "POSTER";
    $fontSize = 5;
    $x = ($width - imagefontwidth($fontSize) * strlen($text)) / 2;
    $y = ($height - imagefontheight($fontSize)) / 2;
    imagestring($img, $fontSize, $x, $y, $text, $textColor);
    
    // Guardar
    imagejpeg($img, $filename, 80);
    imagedestroy($img);
    
    echo "✓ Generado: $filename\n";
}

// Función para generar backdrop
function generateBackdrop($filename) {
    $width = 1920;
    $height = 1080;
    
    $img = imagecreatetruecolor($width, $height);
    
    // Colores
    $bgColor = imagecolorallocate($img, 20, 20, 20);
    $textColor = imagecolorallocate($img, 255, 255, 255);
    $accentColor = imagecolorallocate($img, 229, 9, 20);
    
    // Fondo
    imagefill($img, 0, 0, $bgColor);
    
    // Gradiente
    for ($i = 0; $i < $width; $i++) {
        $alpha = (int)(($i / $width) * 40);
        $color = imagecolorallocatealpha($img, 229, 9, 20, $alpha);
        imageline($img, $i, 0, $i, $height, $color);
    }
    
    // Texto
    $text = "BACKDROP";
    $fontSize = 5;
    $x = ($width - imagefontwidth($fontSize) * strlen($text)) / 2;
    $y = ($height - imagefontheight($fontSize)) / 2;
    imagestring($img, $fontSize, $x, $y, $text, $textColor);
    
    // Guardar
    imagejpeg($img, $filename, 80);
    imagedestroy($img);
    
    echo "✓ Generado: $filename\n";
}

// Directorio
$dir = __DIR__;

// Generar imágenes
echo "Generando imágenes placeholder...\n\n";

generatePoster($dir . '/default-poster.jpg');
generateBackdrop($dir . '/default-backdrop.jpg');

echo "\n¡Imágenes generadas correctamente!\n";
?>

