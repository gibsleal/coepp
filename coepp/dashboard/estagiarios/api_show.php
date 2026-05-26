<?php
// dashboard/estagiarios/api_show.php
// API que retorna um HTML com o “cartão” do estagiário para exibição em modais/popovers.
// OBS: Retorno JSON no formato { ok: bool, html?: string, error?: string }.
//      Mantém compatibilidade com a disponibilidade salva em JSON (chaves com/sem acento).

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli instanceof mysqli) {
  echo json_encode(['ok' => false, 'error' => 'Conexão indisponível.']); exit;
}

// OBS: ID via GET; validação simples (inteiro > 0).
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo json_encode(['ok' => false, 'error' => 'ID inválido.']); exit;
}

/* ---------- helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/**
 * fmt_tel
 * OBS: Formata 10/11 dígitos para (XX) XXXXX-XXXX ou (XX) XXXX-XXXX.
 */
function fmt_tel(string $t): string {
  $d = preg_replace('/\D/', '', $t);
  if (strlen($d) === 11) return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $d);
  if (strlen($d) === 10) return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $d);
  return $t;
}

/**
 * render_disponibilidade
 * OBS: Aceita JSON com chaves “segunda/terça/…” (ou “terca/sabado”) e normaliza horas para HH:MM.
 *      Retorna HTML pronto para o card.
 */
function render_disponibilidade(?string $json): string {
  if (!$json) return '<span style="color:#6b7280;">Não informado</span>';

  $decoded = json_decode($json, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    $decoded = json_decode((string)$json, true);
  }
  if (!is_array($decoded) || !$decoded) return '<span style="color:#6b7280;">Não informado</span>';

  $labelMap = [
    'segunda' => 'Segunda',
    'terça'   => 'Terça',   'terca'  => 'Terça',
    'quarta'  => 'Quarta',
    'quinta'  => 'Quinta',
    'sexta'   => 'Sexta',
    'sábado'  => 'Sábado',  'sabado' => 'Sábado',
    'domingo' => 'Domingo',
  ];

  // OBS: Ordem fixa de exibição.
  $order = ['segunda','terca','terça','quarta','quinta','sexta','sabado','sábado','domingo'];
  $rows  = [];

  foreach ($order as $k) {
    if (!isset($decoded[$k]) || !is_array($decoded[$k]) || !$decoded[$k]) continue;

    // OBS: Normaliza HH:MM e remove duplicados.
    $horas = [];
    foreach ($decoded[$k] as $h) {
      $h = trim((string)$h);
      if ($h === '') continue;
      if (preg_match('/^\d{2}:\d{2}/', $h)) $horas[] = substr($h, 0, 5);
    }
    if (!$horas) continue;

    $label = $labelMap[$k] ?? ucfirst($k);
    $rows[] = '<div><strong>'.h($label).':</strong> '.h(implode(', ', array_unique($horas))).'</div>';
  }

  return $rows ? implode('', $rows) : '<span style="color:#6b7280;">Não informado</span>';
}

/* ---------- busca ---------- */
$sql = "SELECT id, nome, matricula, telefone, email, semestre, supervisor, tipo_servico, disponibilidade
          FROM estagiarios
         WHERE id = ?
         LIMIT 1";
$st  = $mysqli->prepare($sql);
if (!$st) { echo json_encode(['ok'=>false,'error'=>'Falha na preparação da consulta.']); exit; }
$st->bind_param('i', $id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) { echo json_encode(['ok'=>false,'error'=>'Estagiário não encontrado.']); exit; }

/* ---------- campos ---------- */
$nome          = $row['nome'] ?? '';
$matricula     = $row['matricula'] ?? '';
$telefone      = $row['telefone'] ?? '';
$email         = $row['email'] ?? '';
$semestre      = $row['semestre'] ?? '';
$supervisor    = $row['supervisor'] ?? '';
$tipo_servico  = $row['tipo_servico'] ?? '';
$disp_html     = render_disponibilidade($row['disponibilidade'] ?? null);

/* ---------- html ---------- */
// OBS: Layout simples em grid; manter estilos inline facilita uso dentro de modais genéricos.
$html = '
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">

    <div style="grid-column:1 / -1; margin-bottom:4px;">
      <div style="font-size:18px; font-weight:800; color:#0f172a;">'.h($nome).'</div>
      '.($matricula ? '<div style="color:#6b7280; font-size:14px;">Matrícula: '.h($matricula).'</div>' : '').'
    </div>

    <div>
      <div style="font-size:12px; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.3px;">Semestre</div>
      <div style="color:#111827;">'.($semestre !== '' ? h($semestre).'º' : '—').'</div>
    </div>

    <div>
      <div style="font-size:12px; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.3px;">Supervisor</div>
      <div style="color:#111827;">'.h($supervisor ?: '—').'</div>
    </div>

    <div>
      <div style="font-size:12px; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.3px;">Tipo de Serviço</div>
      <div style="color:#111827;">'.h($tipo_servico ?: '—').'</div>
    </div>

    <div>
      <div style="font-size:12px; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.3px;">Telefone</div>
      <div style="color:#111827;">'.h(fmt_tel($telefone)).'</div>
    </div>

    <div style="grid-column:1 / -1;">
      <div style="font-size:12px; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.3px;">E-mail</div>
      <div style="color:#111827;">'.h($email ?: '—').'</div>
    </div>

    <div style="grid-column:1 / -1;">
      <div style="font-size:12px; color:#6b7280; font-weight:700; text-transform:uppercase; letter-spacing:.3px;">Disponibilidade</div>
      <div style="margin-top:8px; color:#334155;">'.$disp_html.'</div>
    </div>

  </div>
';

echo json_encode([
  'ok'   => true,
  'html' => $html
], JSON_UNESCAPED_UNICODE);