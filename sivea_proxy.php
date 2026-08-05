<?php
/**
 * Proxy CORS para el Servicio Meteorológico Nacional (SMN / CONAGUA) de México.
 * El SMN no envía cabeceras CORS y siempre responde comprimido en gzip, por eso
 * este archivo se coloca en el mismo servidor que index.html y sirve los datos
 * oficiales con cabeceras CORS y descomprimidos, con caché de 10 minutos.
 *
 * Uso:  sivea_proxy.php?ep=daily    (pronóstico oficial por municipio, 3 días)
 *       sivea_proxy.php?ep=temp     (observaciones de temperatura en vivo)
 *       sivea_proxy.php?ep=viento   (observaciones de viento en vivo)
 *       sivea_proxy.php?ep=precip   (precipitación acumulada 24 h en vivo)
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: max-age=300');

$eps = array(
    'daily'  => 'https://smn.conagua.gob.mx/tools/GUI/webservices/?method=1',
    'temp'   => 'https://smn.conagua.gob.mx/tools/GUI/sivea_v3/php/getTemperatura.php?per=T2&estado=0',
    'viento' => 'https://smn.conagua.gob.mx/tools/GUI/sivea_v3/php/getViento.php?per=T2&estado=0',
    'precip' => 'https://smn.conagua.gob.mx/tools/GUI/sivea_v3/php/getPrecipitacion.php?per=B24&estado=0',
);

$ep = isset($_GET['ep']) ? $_GET['ep'] : 'daily';
if (!isset($eps[$ep])) {
    echo json_encode(array('error' => 'endpoint invalido'));
    exit;
}

$cacheFile = __DIR__ . '/sivea_cache_' . $ep . '.json';
$ttl = 600; // 10 minutos de caché

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
    echo file_get_contents($cacheFile);
    exit;
}

$ch = curl_init($eps[$ep]);
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '', // acepta gzip y lo descomprime automáticamente
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (AgroChiapas; +https://localhost)',
));
$body = curl_exec($ch);
curl_close($ch);

if ($body === false || $body === '') {
    echo json_encode(array('error' => 'no se pudo conectar con el SMN'));
    exit;
}

// Si por alguna razón sigue en gzip, se decodifica manualmente
if (substr($body, 0, 2) === "\x1f\x8b") {
    $body = gzdecode($body);
}

json_decode($body);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(array('error' => 'respuesta del SMN no válida'));
    exit;
}

@file_put_contents($cacheFile, $body);
echo $body;
