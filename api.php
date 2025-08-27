<?php
// Habilitar CORS para permitir requisições do frontend
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json");

// Verificar se o CPF foi fornecido
if (!isset($_GET['cpf']) || empty($_GET['cpf'])) {
    http_response_code(400);
    echo json_encode(['error' => 'CPF não fornecido']);
    exit;
}

// Obter o CPF da requisição
$cpf = $_GET['cpf'];

// Limpar o CPF (remover caracteres não numéricos)
$cpf = preg_replace('/[^0-9]/', '', $cpf);

// Verificar se o CPF tem 11 dígitos
if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['error' => 'CPF inválido, deve conter 11 dígitos']);
    exit;
}

// Token da API
$token = "1090";

// URL da API
$url = "https://searchapi.dnnl.live/consulta?token_api={$token}&cpf={$cpf}";

// Inicializar cURL
$ch = curl_init();

// Configurar a requisição cURL
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

// Executar a requisição
$response = curl_exec($ch);

// Verificar se houve algum erro
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar a API: ' . curl_error($ch)]);
    exit;
}

// Obter o código de status HTTP
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Fechar a conexão cURL
curl_close($ch);

// Verificar o código de status
if ($statusCode !== 200) {
    http_response_code($statusCode);
    echo json_encode(['error' => 'Erro na API externa', 'status' => $statusCode]);
    exit;
}

// Decodificar a resposta
$data = json_encode(json_decode($response), JSON_PRETTY_PRINT);

// Retornar a resposta
echo $data;
?> 
