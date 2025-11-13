<?php
/**
 * Genera una imagen placeholder para backdrop por defecto
 */

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=31536000');

// Dimensiones (16:9)
$width = 1920;
$height = 1080;

// Crear imagen
$img = imagecreatetruecolor($width, $height);

// Colores estilo Netflix
$bgColor = imagecolorallocate($img, 20, 20, 20); // #141414
$textColor = imagecolorallocate($img, 255, 255, 255);
$accentColor = imagecolorallocate($img, 229, 9, 20); // #e50914

// Fondo
imagefill($img, 0, 0, $bgColor);

// Gradiente diagonal
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

// Generar JPEG
imagejpeg($img, null, 80);
imagedestroy($img);
?>

