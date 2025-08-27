<?php
// CORS / headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

// 1) Captura CPF (POST form, GET ou JSON)
$cpfRaw = $_POST['cpf'] ?? ($_GET['cpf'] ?? null);
if (!$cpfRaw) {
  $body = file_get_contents('php://input');
  if ($body) {
    $json = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($json['cpf'])) $cpfRaw = $json['cpf'];
  }
}
if (!$cpfRaw) { echo json_encode(['status'=>400,'message'=>'CPF não fornecido']); exit; }

// 2) Normaliza e corrige zeros à esquerda
$cpf = preg_replace('/\D/', '', (string)$cpfRaw);
if (strlen($cpf) > 11) { echo json_encode(['status'=>400,'message'=>'CPF inválido (mais de 11 dígitos)']); exit; }
if (strlen($cpf) < 11) { $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT); }

// 3) Validação dos dígitos
if (!validaCPF($cpf)) { echo json_encode(['status'=>400,'message'=>'CPF inválido']); exit; }

// 4) Chama API externa
$token = "1090";
$url = "https://searchapi.dnnl.live/consulta?token_api={$token}&cpf={$cpf}";
$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 15,
  CURLOPT_HTTPHEADER => ['Accept: application/json']
]);
$response = curl_exec($ch);
if ($response === false) {
  $err = curl_error($ch);
  curl_close($ch);
  echo json_encode(['status'=>502,'message'=>'Erro ao consultar a API','details'=>$err], JSON_UNESCAPED_UNICODE);
  exit;
}
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 5) Uniformiza a resposta pro teu front (sempre HTTP 200)
$decoded = json_decode($response, true);
if ($status === 200 && json_last_error() === JSON_ERROR_NONE) {
  echo json_encode(['status'=>200,'data'=>$decoded], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} else {
  $msg = $decoded['message'] ?? 'Erro na API externa';
  echo json_encode(['status'=>$status ?: 500,'message'=>$msg,'raw'=>$response], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

// --- função de validação ---
function validaCPF($cpf){
  if (strlen($cpf)!=11 || preg_match('/^(\d)\1{10}$/',$cpf)) return false;
  for ($t=9; $t<11; $t++){
    $sum=0;
    for ($i=0,$w=$t+1;$i<$t;$i++,$w--) $sum += intval($cpf[$i])*$w;
    $d=$sum%11; $d=($d<2)?0:11-$d;
    if (intval($cpf[$t])!==$d) return false;
  }
  return true;
}
