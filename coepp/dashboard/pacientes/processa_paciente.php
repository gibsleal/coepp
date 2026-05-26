<?php
// dashboard/pacientes/processa_paciente.php
// Processa o cadastro de paciente (POST) com validações de unicidade de CPF, e-mail e Nº de prontuário.
// OBS: Número de prontuário é informado manualmente pelo administrador.
// OBS: `estuda_fsa` aceita 0/1 ou “Sim”/“Não”; se não estuda, RA é salvo como NULL.
// OBS: Em caso de falha de validação, retornamos via `back_with()` alert + history.back().

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function only_digits($s){ return preg_replace('/\D+/', '', (string)$s); }
function format_cpf_digits($d){
  $d = only_digits($d);
  if (strlen($d) !== 11) return $d;
  return substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2);
}
function back_with($msg){
  echo "<script>alert(".json_encode($msg)."); window.history.back();</script>";
  exit();
}

try {
  /** @var mysqli|null $mysqli */
  $mysqli = $GLOBALS['mysqli'] ?? null;
  if (!$mysqli) { throw new Exception('Conexão indisponível.'); }

  // Campos do formulário
  $numero_prontuario = trim($_POST['numero_prontuario'] ?? '');
  $nome            = trim($_POST['nome'] ?? '');
  $telefone        = trim($_POST['telefone'] ?? '');
  $cpf_in          = trim($_POST['cpf'] ?? '');
  $endereco        = trim($_POST['endereco'] ?? '');
  $email           = trim($_POST['email'] ?? '');
  $data_nascimento = trim($_POST['data_nascimento'] ?? '');
  $preferencial    = isset($_POST['preferencial']) ? 1 : 0;
  $preferencial_obs= trim($_POST['preferencial_obs'] ?? '');
  $estuda_fsa_in   = $_POST['estuda_fsa'] ?? '';
  $ra              = trim($_POST['ra'] ?? '');
  $encaminhamento  = trim($_POST['encaminhamento'] ?? '');
  $tipo_servico    = trim($_POST['tipo_servico'] ?? '');

  // Validações mínimas
  if (
    $numero_prontuario === '' ||
    $nome==='' ||
    $telefone==='' ||
    $cpf_in==='' ||
    $endereco==='' ||
    $email==='' ||
    $data_nascimento==='' ||
    $estuda_fsa_in==='' ||
    $tipo_servico===''
  ) {
    back_with('Preencha todos os campos obrigatórios.');
  }

  if (!ctype_digit($numero_prontuario) || (int)$numero_prontuario <= 0) {
    back_with('Número de prontuário inválido.');
  }
  $numero_prontuario = (int)$numero_prontuario;

  // CPF
  $cpf_digits = only_digits($cpf_in);
  if (strlen($cpf_digits) !== 11) back_with('CPF inválido (precisa ter 11 dígitos).');
  $cpf = format_cpf_digits($cpf_digits);

  // normaliza estuda_fsa para 0/1
  $estuda_fsa = 0;
  if ($estuda_fsa_in === '1' || strtolower($estuda_fsa_in) === 'sim') $estuda_fsa = 1;

  if ($estuda_fsa !== 1) $ra = null;

  // Unicidade Nº prontuário
  $st = $mysqli->prepare("SELECT id FROM pacientes WHERE numero_prontuario = ? LIMIT 1");
  $st->bind_param('i', $numero_prontuario);
  $st->execute();
  if ($st->get_result()->fetch_assoc()) back_with('Já existe um paciente com este número de prontuário.');
  $st->close();

  // Unicidade CPF
  $st = $mysqli->prepare("SELECT id FROM pacientes WHERE cpf = ? LIMIT 1");
  $st->bind_param('s', $cpf);
  $st->execute();
  if ($st->get_result()->fetch_assoc()) back_with('Já existe um paciente com este CPF.');
  $st->close();

  // Unicidade Email
  $st = $mysqli->prepare("SELECT id FROM pacientes WHERE email = ? LIMIT 1");
  $st->bind_param('s', $email);
  $st->execute();
  if ($st->get_result()->fetch_assoc()) back_with('Já existe um paciente com este e-mail.');
  $st->close();

  // INSERT
  $sql = "INSERT INTO pacientes
            (numero_prontuario, nome, data_nascimento, telefone, cpf, endereco,
             preferencial, preferencial_obs, estuda_fsa, ra, encaminhamento, tipo_servico, email)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";

  $st = $mysqli->prepare($sql);
  $st->bind_param(
    'issssssisssss',
    $numero_prontuario,
    $nome,
    $data_nascimento,
    $telefone,
    $cpf,
    $endereco,
    $preferencial,
    $preferencial_obs,
    $estuda_fsa,
    $ra,
    $encaminhamento,
    $tipo_servico,
    $email
  );
  $st->execute();
  $st->close();

  header('Location: ' . url('dashboard/pacientes/pacientes.php?ok=1'));
  exit();

} catch (Throwable $e) {
  header('Location: ' . url('dashboard/pacientes/pacientes.php?erro=' . urlencode('Erro ao cadastrar: ' . $e->getMessage())));
  exit();
}