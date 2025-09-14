<?php
header('Content-Type: image/jpeg');

// --- Ambil parameter gambar asli
$src = isset($_GET['src']) ? $_GET['src'] : '';
if (!$src) {
    http_response_code(400);
    die("No source image");
}

// --- Download gambar asli via cURL
function getImageFromUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code != 200 || !$data) return false;
    return $data;
}

$imgData = getImageFromUrl($src);
if (!$imgData) {
    http_response_code(404);
    die("Failed to download image");
}

$original = @imagecreatefromstring($imgData);
if (!$original) {
    http_response_code(415);
    die("Unsupported image format");
}

// --- Target ukuran
$targetW = 1280;
$targetH = 720;
$origW = imagesx($original);
$origH = imagesy($original);
$targetRatio = $targetW / $targetH;
$origRatio = $origW / $origH;

// --- Crop sesuai aspect ratio
if ($origRatio > $targetRatio) {
    $newH = $origH;
    $newW = (int)($origH * $targetRatio);
    $srcX = (int)(($origW - $newW) / 2);
    $srcY = 0;
} else {
    $newW = $origW;
    $newH = (int)($origW / $targetRatio);
    $srcX = 0;
    $srcY = (int)(($origH - $newH) / 2);
}

// --- Resize gambar
$resized = imagecreatetruecolor($targetW, $targetH);
imagecopyresampled(
    $resized, $original,
    0, 0, $srcX, $srcY,
    $targetW, $targetH,
    $newW, $newH
);
imagedestroy($original);

// --- Load frame PNG
$framePath = __DIR__ . "/frame.png";
$frame = @imagecreatefrompng($framePath);
if (!$frame) {
    http_response_code(500);
    die("Frame not found");
}

// --- Resize frame ke ukuran target
$frameResized = imagecreatetruecolor($targetW, $targetH);
imagesavealpha($frameResized, true);
$trans_colour = imagecolorallocatealpha($frameResized, 0, 0, 0, 127);
imagefill($frameResized, 0, 0, $trans_colour);
imagecopyresampled(
    $frameResized, $frame,
    0, 0, 0, 0,
    $targetW, $targetH,
    imagesx($frame), imagesy($frame)
);
imagedestroy($frame);

// --- Blend frame PNG ke JPG
// Buat alpha channel manual
for ($x = 0; $x < $targetW; $x++) {
    for ($y = 0; $y < $targetH; $y++) {
        $frameColor = imagecolorat($frameResized, $x, $y);
        $alpha = ($frameColor >> 24) & 0x7F;
        if ($alpha < 127) {
            $rF = ($frameColor >> 16) & 0xFF;
            $gF = ($frameColor >> 8) & 0xFF;
            $bF = $frameColor & 0xFF;

            $bgColor = imagecolorat($resized, $x, $y);
            $rB = ($bgColor >> 16) & 0xFF;
            $gB = ($bgColor >> 8) & 0xFF;
            $bB = $bgColor & 0xFF;

            // Blend alpha
            $a = 1 - ($alpha / 127);
            $r = (int)($rF * $a + $rB * (1 - $a));
            $g = (int)($gF * $a + $gB * (1 - $a));
            $b = (int)($bF * $a + $bB * (1 - $a));

            $color = imagecolorallocate($resized, $r, $g, $b);
            imagesetpixel($resized, $x, $y, $color);
        }
    }
}
imagedestroy($frameResized);

// --- Output JPG
imagejpeg($resized, null, 90);
imagedestroy($resized);
?>
