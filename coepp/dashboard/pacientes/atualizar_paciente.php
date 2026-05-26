<?php
// dashboard/pacientes/atualizar_paciente.php
// Atualiza paciente existente com validações e unicidade de CPF, E-mail e Nº de prontuário.
// OBS: CPF é normalizado com máscara padrão (xxx.xxx.xxx-xx) antes de salvar.
//      Se não estuda FSA, RA é salvo como NULL.
//      Checa unicidade ignorando o próprio ID.

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
function back_err($msg, $id){
  header('Location: ' . url('dashboard/pacientes/editar_paciente.php?id='.(int)$id.'&erro='.urlencode($msg)));
  exit();
}

try {
  $mysqli = $GLOBALS['mysqli'] ?? null;
  if (!$mysqli) { throw new Exception('Conexão indisponível.'); }

  $id                = (int)($_POST['id'] ?? 0);
  $numero_prontuario = trim($_POST['numero_prontuario'] ?? '');
  $nome              = trim($_POST['nome'] ?? '');
  $telefone          = trim($_POST['telefone'] ?? '');
  $cpf_in            = trim($_POST['cpf'] ?? '');
  $endereco          = trim($_POST['endereco'] ?? '');
  $email             = trim($_POST['email'] ?? '');
  $data_nascimento   = trim($_POST['data_nascimento'] ?? '');
  $preferencial      = isset($_POST['preferencial']) ? 1 : 0;
  $preferencial_obs  = trim($_POST['preferencial_obs'] ?? '');
  $estuda_fsa_in     = $_POST['estuda_fsa'] ?? '0';
  $ra                = trim($_POST['ra'] ?? '');
  $encaminhamento    = trim($_POST['encaminhamento'] ?? '');
  $tipo_servico      = trim($_POST['tipo_servico'] ?? '');

  if ($id <= 0) back_err('ID inválido.', $id);

  if (
    $numero_prontuario === '' ||
    $nome === '' ||
    $telefone === '' ||
    $cpf_in === '' ||
    $endereco === '' ||
    $email === '' ||
    $data_nascimento === '' ||
    $tipo_servico === ''
  ) {
    back_err('Preencha todos os campos obrigatórios.', $id);
  }

  if (!ctype_digit($numero_prontuario) || (int)$numero_prontuario <= 0) {
    back_err('Número de prontuário inválido.', $id);
  }
  $numero_prontuario = (int)$numero_prontuario;

  // CPF
  $cpf_digits = only_digits($cpf_in);
  if (strlen($cpf_digits) !== 11) back_err('CPF inválido.', $id);
  $cpf = format_cpf_digits($cpf_digits);

  // estuda_fsa
  $estuda_fsa = 0;
  if ($estuda_fsa_in === '1' || strtolower($estuda_fsa_in) === 'sim') $estuda_fsa = 1;
  if ($estuda_fsa !== 1) $ra = null;

  // Nº prontuário único (ignora o próprio ID)
  $st = $mysqli->prepare(
    "SELECT id FROM pacientes WHERE numero_prontuario = ? AND id <> ? LIMIT 1"
  );
  $st->bind_param('ii', $numero_prontuario, $id);
  $st->execute();
  if ($st->get_result()->fetch_assoc()) {
    back_err('Já existe outro paciente com este número de prontuário.', $id);
  }
  $st->close();

  // CPF único
  $st = $mysqli->prepare(
    "SELECT id FROM pacientes WHERE cpf = ? AND id <> ? LIMIT 1"
  );
  $st->bind_param('si', $cpf, $id);
  $st->execute();
  if ($st->get_result()->fetch_assoc()) {
    back_err('Já existe outro paciente com este CPF.', $id);
  }
  $st->close();

  // Email único
  $st = $mysqli->prepare(
    "SELECT id FROM pacientes WHERE email = ? AND id <> ? LIMIT 1"
  );
  $st->bind_param('si', $email, $id);
  $st->execute();
  if ($st->get_result()->fetch_assoc()) {
    back_err('Já existe outro paciente com este e-mail.', $id);
  }
  $st->close();

  $sql = "UPDATE pacientes SET
            numero_prontuario=?,
            nome=?, data_nascimento=?, telefone=?, cpf=?, endereco=?,
            preferencial=?, preferencial_obs=?, estuda_fsa=?, ra=?,
            encaminhamento=?, tipo_servico=?, email=?
          WHERE id=?";

  $st = $mysqli->prepare($sql);
  $st->bind_param(
    'issssssisssssi',
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
    $email,
    $id
  );
  $st->execute();
  $st->close();

  header('Location: ' . url('dashboard/pacientes/pacientes.php?ok=1'));
  exit();

} catch (Throwable $e) {
  header('Location: ' . url('dashboard/pacientes/pacientes.php?erro=' . urlencode('Erro ao atualizar: ' . $e->getMessage())));
  exit();
}