<?php
// dashboard/estagiarios/salvar_estagiario.php
// Salva novo estagiário com validações e normalização de disponibilidade.
// OBS: 'semestre' é enviado como STRING ('4'..'8') para compatibilidade com ENUM/VARCHAR.
//      Disponibilidade chega como array (ou JSON) e vira { dia: ["HH:MM", ...] }.
//      Define charset utf8mb4 e retorna via redirect com mensagens na querystring.

require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) {
  header('Location: ' . url('dashboard/estagiarios/estagiarios.php?erro=Sem conexão com o banco'));
  exit;
}

// Opcional em dev: tornar erros visíveis
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . url('dashboard/estagiarios/estagiarios.php'));
  exit;
}

// --- lê campos ---
$nome          = trim($_POST['nome'] ?? '');
$matricula     = trim($_POST['matricula'] ?? '');
$telefone      = trim($_POST['telefone'] ?? '');
$email         = trim($_POST['email'] ?? '');
$semestre_in   = $_POST['semestre'] ?? '';                 // pega cru do POST (STRING)
$supervisor    = trim($_POST['supervisor'] ?? '');
$tipo_servico  = trim($_POST['tipo_servico'] ?? '');
$disp_raw      = $_POST['disponibilidade'] ?? '';

function back($msg){
  header('Location: ' . url('dashboard/estagiarios/cadastrar_estagiario.php?erro=' . urlencode($msg)));
  exit;
}

// --- validações coerentes com a UI ---
if ($nome === '' || $matricula === '' || $telefone === '' || $email === '' || $supervisor === '') {
  back('Campos obrigatórios não preenchidos.');
}

$permitidos = ['Triagem','Acompanhamento','Terapia'];
if (!in_array($tipo_servico, $permitidos, true)) {
  back('Tipo de serviço inválido.');
}

$tel_digits = preg_replace('/\D+/', '', $telefone);
if (!in_array(strlen($tel_digits), [10, 11], true)) {
  back('Telefone inválido.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  back('E-mail inválido.');
}

// 'semestre' como STRING (compatível com ENUM/VARCHAR)
$valid_semesters = ['4','5','6','7','8'];
$semestre = trim((string)$semestre_in);
if (!in_array($semestre, $valid_semesters, true)) {
  back('Semestre deve ser 4, 5, 6, 7 ou 8.');
}

// --- normaliza disponibilidade -> { dia: ["HH:MM", ...] } ---
$valid_days = ['segunda','terca','quarta','quinta','sexta','sabado'];
$clean = [];

if (is_array($disp_raw)) {
  foreach ($disp_raw as $day => $hours) {
    $day = strtolower((string)$day);
    if (!in_array($day, $valid_days, true) || !is_array($hours)) continue;

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
  // OBS: Aceita string JSON vinda de clientes alternativos
  $s = trim((string)$disp_raw);
  if ($s !== '') {
    $d = json_decode($s, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($d)) {
      foreach ($d as $day => $hours) {
        $day = strtolower((string)$day);
        if (!in_array($day, $valid_days, true) || !is_array($hours)) continue;

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

// Exige ao menos um horário marcado em algum dia
$tem_disponibilidade = false;
foreach ($clean as $arr) {
  if (!empty($arr)) { $tem_disponibilidade = true; break; }
}
if (!$tem_disponibilidade) {
  back('Selecione pelo menos um horário na disponibilidade.');
}

$disponibilidade_json = json_encode($clean, JSON_UNESCAPED_UNICODE);

// --- INSERT (semestre STRING) ---
try {
  $sql = "
    INSERT INTO estagiarios
      (nome, matricula, telefone, email, semestre, supervisor, tipo_servico, disponibilidade)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?)
  ";
  $st = $mysqli->prepare($sql);
  // Tipos: s s s s s s s s
  $st->bind_param(
    'ssssssss',
    $nome, $matricula, $telefone, $email,
    $semestre,
    $supervisor, $tipo_servico, $disponibilidade_json
  );
  $st->execute();
  $st->close();

  header('Location: ' . url('dashboard/estagiarios/estagiarios.php?ok=1'));
  exit;
} catch (Throwable $e) {
  back($e->getMessage());
}