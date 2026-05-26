<?php
// dashboard/pacientes/api_show.php
// Retorna um card HTML (string) com os dados do paciente para uso em modal.
// OBS: Saída é JSON { ok, html|error }. O HTML já vem sanitizado (htmlspecialchars) e
//      com formatações utilitárias (telefone, CPF, datas). Campo "Preferencial" exibe badge.

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/init.php';
require_once ROOT . '/includes/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');

try {
  /** @var mysqli|null $mysqli */
  $mysqli = $GLOBALS['mysqli'] ?? null;
  if (!$mysqli instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'Conexão indisponível.']);
    exit;
  }

  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) {
    echo json_encode(['ok'=>false, 'error'=>'ID inválido.']);
    exit;
  }

  // Helpers
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $fmtTel = function($t){
    $d = preg_replace('/\D/','',(string)$t);
    if (strlen($d)===11) return preg_replace('/(\d{2})(\d{5})(\d{4})/','($1) $2-$3',$d);
    if (strlen($d)===10) return preg_replace('/(\d{2})(\d{4})(\d{4})/','($1) $2-$3',$d);
    return (string)$t;
  };
  $fmtCPF = function($c){
    $d = preg_replace('/\D/','',(string)$c);
    return strlen($d)===11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/','$1.$2.$3-$4',$d) : (string)$c;
  };
  $fmtDate = function($ymd){
    if (!$ymd) return '';
    $ts = strtotime((string)$ymd);
    return $ts ? date('d/m/Y', $ts) : (string)$ymd;
  };

  // Busca o registro
  $st = $mysqli->prepare("SELECT * FROM pacientes WHERE id=? LIMIT 1");
  $st->bind_param('i', $id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$row) {
    echo json_encode(['ok'=>false, 'error'=>'Paciente não encontrado.']);
    exit;
  }

  // Getter seguro
  $get = function(array $r, string $k, $default = null){
    return array_key_exists($k, $r) ? $r[$k] : $default;
  };

  $np        = trim((string)$get($row, 'numero_prontuario', '—'));
  $nome      = trim((string)$get($row, 'nome', '—'));
  $nasc      = $fmtDate($get($row, 'data_nascimento',''));
  $tel       = $fmtTel($get($row, 'telefone',''));
  $cpf       = $fmtCPF($get($row, 'cpf',''));
  $email     = trim((string)$get($row, 'email', '—'));
  $endereco  = trim((string)$get($row, 'endereco', '—'));
  $encam     = trim((string)$get($row, 'encaminhamento', '—'));
  $tipo      = trim((string)$get($row, 'tipo_servico', '—'));

  // estuda_fsa vem como 0/1 no banco
  $estudaFsaRaw  = $get($row, 'estuda_fsa', 0);
  $estudaFsaBool = ((int)$estudaFsaRaw === 1 || (string)$estudaFsaRaw === '1');
  $estudaFsaTxt  = $estudaFsaBool ? 'Sim' : 'Não';

  $ra        = trim((string)$get($row, 'ra', ''));
  $prefFlag  = (int)$get($row, 'preferencial', 0) === 1 ? 1 : 0;
  $prefObs   = trim((string)$get($row, 'preferencial_obs', ''));

  $createdAt = $get($row, 'created_at', null);
  $updatedAt = $get($row, 'updated_at', null);
  $created   = $createdAt ? date('d/m/Y H:i', strtotime((string)$createdAt)) : '—';
  $updated   = $updatedAt ? date('d/m/Y H:i', strtotime((string)$updatedAt)) : '—';

  $badgePref = $prefFlag
    ? '<span style="display:inline-block;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700;">Preferencial</span>'
    : '';

  // HTML
  $html = '
  <div style="display:grid; gap:10px;">
    <div style="display:flex; align-items:center; gap:10px; justify-content:space-between;">
      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700;">Nº Prontuário</div>
        <div style="font-size:18px; font-weight:800; color:#0f172a;">'.$e($np).'</div>
      </div>
      '.($badgePref ?: '').'
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">Nome</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($nome ?: '—').'</div>
      </div>

      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">Data de Nascimento</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($nasc ?: '—').'</div>
      </div>

      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">CPF</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($cpf ?: '—').'</div>
      </div>

      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">Telefone</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($tel ?: '—').'</div>
      </div>

      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">E-mail</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($email ?: '—').'</div>
      </div>

      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">Endereço</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($endereco ?: '—').'</div>
      </div>

      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">Encaminhamento</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($encam ?: '—').'</div>
      </div>

      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">Tipo de Serviço</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($tipo ?: '—').'</div>
      </div>

      <div>
        <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">Estuda na FSA?</div>
        <div style="font-size:16px; color:#0f172a;">'.$e($estudaFsaTxt).($estudaFsaBool && $ra ? ' — RA: '.$e($ra) : '').'</div>
      </div>

      '.(
        $prefFlag
          ? '<div style="grid-column:1 / -1;">
               <div style="font-size:13px; color:#6b7280; font-weight:700; margin-bottom:6px;">Observação de Prioridade</div>
               <div style="font-size:16px; color:#0f172a;">'.($prefObs !== '' ? $e($prefObs) : '—').'</div>
             </div>'
          : ''
      ).'
    </div>
  </div>';

  echo json_encode(['ok'=>true, 'html'=>$html], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Erro no servidor ao montar os dados.']);
  exit;
}