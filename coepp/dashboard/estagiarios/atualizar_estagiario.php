<?php
// dashboard/estagiarios/atualizar_estagiário.php
// Atualiza cadastro do estagiário (POST). Valida campos e normaliza disponibilidade.
// OBS: 'semestre' é tratado como STRING (compatível com ENUM('4','5','6','7','8') ou VARCHAR).

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . url('dashboard/estagiarios/estagiarios.php'));
  exit;
}

// OBS: Coleta segura com defaults e trims quando aplicável.
$id           = (int)($_POST['id'] ?? 0);
$nome         = trim($_POST['nome'] ?? '');
$matricula    = trim($_POST['matricula'] ?? '');
$telefone     = trim($_POST['telefone'] ?? '');
$email        = trim($_POST['email'] ?? '');
$semestre_in  = $_POST['semestre'] ?? '';            // pega cru do POST
$supervisor   = trim($_POST['supervisor'] ?? '');
$tipo_servico = trim($_POST['tipo_servico'] ?? '');
$disp_raw     = $_POST['disponibilidade'] ?? '';

function redirect_err($id, $msg){
  header('Location: ' . url('dashboard/estagiarios/editar_estagiario.php?id='.$id.'&erro='.urlencode($msg)));
  exit;
}

if ($id <= 0) redirect_err($id, 'ID inválido.');
if ($nome === '' || $matricula === '') redirect_err($id, 'Campos obrigatórios não preenchidos.');

// OBS: Validações coerentes com a UI (tipo_servico conhecido).
$permitidos = ['Triagem','Acompanhamento','Terapia'];
if (!in_array($tipo_servico, $permitidos, true)) {
  redirect_err($id, 'Tipo de serviço inválido.');
}

// OBS: Telefone — aceita 10 ou 11 dígitos.
$tel_digits = preg_replace('/\D+/', '', $telefone);
if (!(strlen($tel_digits) === 10 || strlen($tel_digits) === 11)) {
  redirect_err($id, 'Telefone inválido.');
}

// OBS: E-mail institucional ou comum (usa FILTER_VALIDATE_EMAIL).
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  redirect_err($id, 'E-mail inválido.');
}

// OBS: 'semestre' como STRING — compatível com bases usando ENUM('4'..'8') ou VARCHAR.
$valid_semesters = ['4','5','6','7','8'];
$semestre = trim((string)$semestre_in);
if (!in_array($semestre, $valid_semesters, true)) {
  redirect_err($id, 'Semestre deve ser 4, 5, 6, 7 ou 8.');
}

// ---------- Normalização da disponibilidade ----------
// OBS: Espera formato { dia: ["HH:MM", ...] } com dias em ['segunda','terca','quarta','quinta','sexta','sabado'].
$valid_days = ['segunda','terca','quarta','quinta','sexta','sabado'];
$clean = [];

if (is_array($disp_raw)) {
  foreach ($disp_raw as $day => $hours) {
    $day = strtolower((string)$day);
    if (!in_array($day, $valid_days, true)) continue;
    if (!is_array($hours)) continue;

    $norm = [];
    foreach ($hours as $h) {
      $h = substr(trim((string)$h), 0, 5);
      if (preg_match('/^\d{2}:\d{2}$/', $h)) $norm[] = $h;
    }
    if ($norm) {
      $norm = array_values(array_unique($norm));
      sort($norm);
      $clean[$day] = $norm;
    }
  }
} else {
  // OBS: Aceita string JSON como fallback (clientes alternativos/API).
  $s = trim((string)$disp_raw);
  if ($s !== '') {
    $d = json_decode($s, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($d)) {
      foreach ($d as $day => $hours) {
        $day = strtolower((string)$day);
        if (!in_array($day, $valid_days, true)) continue;
        if (!is_array($hours)) continue;

        $norm = [];
        foreach ($hours as $h) {
          $h = substr(trim((string)$h), 0, 5);
          if (preg_match('/^\d{2}:\d{2}$/', $h)) $norm[] = $h;
        }
        if ($norm) {
          $norm = array_values(array_unique($norm));
          sort($norm);
          $clean[$day] = $norm;
        }
      }
    }
  }
}

// OBS: Pelo menos um horário precisa estar marcado.
$tem_disponibilidade = false;
foreach ($clean as $arr) {
  if (!empty($arr)) { $tem_disponibilidade = true; break; }
}
if (!$tem_disponibilidade) {
  redirect_err($id, 'Selecione pelo menos um horário na disponibilidade.');
}

$disponibilidade_json = json_encode($clean, JSON_UNESCAPED_UNICODE);

// ---------- Persistência ----------
try {
  $st = $mysqli->prepare("
    UPDATE estagiarios
       SET nome=?, matricula=?, telefone=?, email=?, semestre=?, supervisor=?, tipo_servico=?, disponibilidade=?
     WHERE id=?
  ");

  // OBS: 'semestre' é STRING => tipo 's' no bind_param.
  $st->bind_param(
    'ssssssssi',
    $nome,
    $matricula,
    $telefone,
    $email,
    $semestre,               // '4'..'8'
    $supervisor,
    $tipo_servico,
    $disponibilidade_json,
    $id
  );

  $st->execute();
  $st->close();

  header('Location: ' . url('dashboard/estagiarios/estagiarios.php?ok=1'));
  exit;
} catch (Throwable $e) {
  // OBS: Propaga a mensagem para diagnóstico controlado na tela.
  redirect_err($id, $e->getMessage());
}