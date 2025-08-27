<?php
// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Vary: Origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Lê CPF (GET, POST form ou JSON)
$cpf = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cpf = $_GET['cpf'] ?? null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $in = json_decode(file_get_contents('php://input'), true);
        $cpf = $in['cpf'] ?? null;
    } else {
        $cpf = $_POST['cpf'] ?? null;
    }
}

// Validação
if (!$cpf) { echo json_encode(['ok'=>false,'error'=>'CPF não fornecido']); exit; }
$cpf = preg_replace('/\D/', '', $cpf);
if (strlen($cpf) !== 11) { echo json_encode(['ok'=>false,'error'=>'CPF inválido, deve conter 11 dígitos']); exit; }

// Endpoint
$token = '4jinxv7me8lhx5nbney6bu';
$endpoint = 'https://bk.elaitech.pro/consultar-filtrada/cpf';
$url = $endpoint.'?cpf='.rawurlencode($cpf).'&token='.rawurlencode($token);

// Chamada externa
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_FOLLOWLOCATION => true,
]);
$response = curl_exec($ch);
$err = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo json_encode(['ok'=>false,'error'=>'Falha na consulta externa: '.$err]); exit;
}

// Tenta decodificar JSON
$prov = json_decode($response, true);
if (!is_array($prov)) {
    echo json_encode(['ok'=>false,'error'=>'Resposta inválida do provedor','body'=>substr($response,0,300)]); exit;
}

// Regra de sucesso: veio CPF com 11 dígitos (e opcionalmente nome)
$cpfResp = isset($prov['cpf']) ? preg_replace('/\D/', '', (string)$prov['cpf']) : '';
if ($status >= 200 && $status < 300 && $cpfResp === $cpf) {
    echo json_encode([
        'ok'   => true,
        'cpf'  => $cpfResp,
        'nome' => $prov['nome'] ?? null,
        'data' => $prov
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

// Erro da upstream (mantém mensagem se houver)
$msg = $prov['error'] ?? $prov['message'] ?? 'CPF não encontrado ou erro na API externa';
echo json_encode(['ok'=>false,'error'=>$msg,'status'=>$status]);
