<?php
// dashboard/estagiarios/carregar_dados_estagiario.php
// Endpoint JSON para carregar supervisor, tipo_servico e disponibilidade de um estagiário.
// OBS: Retorna { supervisor, tipo_servico, disponibilidade }.
//      A disponibilidade é normalizada para objeto JSON ({} quando vazio).
//      Usa prepared statements para segurança e códigos HTTP adequados.

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = $GLOBALS['mysqli'];

// --- Valida ID (inteiro > 0) ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'ID inválido']);
  exit;
}

// --- Consulta segura (prepared) ---
$sql = "SELECT supervisor, tipo_servico, disponibilidade FROM estagiarios WHERE id = ? LIMIT 1";
$st = $mysqli->prepare($sql);
if (!$st) {
  http_response_code(500);
  echo json_encode(['error' => 'Falha ao preparar consulta']);
  exit;
}

$st->bind_param('i', $id);
$st->execute();
$res = $st->get_result();
$row = $res->fetch_assoc();
$st->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['error' => 'Estagiário não encontrado']);
  exit;
}

// --- Normaliza disponibilidade ---
// OBS: Se vier vazio, devolvemos {} (stdClass) para manter compatibilidade com clientes.
$disp = null;
if (isset($row['disponibilidade']) && $row['disponibilidade'] !== '') {
  $decoded = json_decode($row['disponibilidade'], true);
  $disp = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['disponibilidade'];
} else {
  $disp = new stdClass(); // {}
}

// --- Resposta OK ---
echo json_encode([
  'supervisor'      => $row['supervisor']   ?? '',
  'tipo_servico'    => $row['tipo_servico'] ?? '',
  'disponibilidade' => $disp
], JSON_UNESCAPED_UNICODE);