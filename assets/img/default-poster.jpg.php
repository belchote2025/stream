<?php
/**
 * Genera una imagen placeholder para poster por defecto
 */

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=31536000');

// Dimensiones
$width = 500;
$height = 750;

// Crear imagen
$img = imagecreatetruecolor($width, $height);

// Colores estilo Netflix
$bgColor = imagecolorallocate($img, 20, 20, 20); // #141414
$textColor = imagecolorallocate($img, 255, 255, 255);
$accentColor = imagecolorallocate($img, 229, 9, 20); // #e50914

// Fondo
imagefill($img, 0, 0, $bgColor);

// Gradiente simple
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

// Generar JPEG
imagejpeg($img, null, 80);
imagedestroy($img);
?>

