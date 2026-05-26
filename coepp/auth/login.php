<?php
/** =======================================================================
 *  auth/login.php — Página de login autônoma
 *  [CONTEXTO]
 *    - Responsável por autenticar o usuário e abrir sessão.
 *    - Usa conexão $GLOBALS['mysqli'] e helper url() vindos do init.php.
 *  [REGRA]
 *    - Se já estiver logado, redireciona para dashboard.
 *    - Aceita senha hash (bcrypt) e, como LEGADO, texto puro (ver [WARN]).
 *  [TODO]
 *    - Implementar CSRF token no form (segurança).
 *    - Implementar “lembrar de mim” com cookie seguro (atualmente só UI).
 *    - Rate limiting / bloqueio por tentativas (mitigar brute force).
 * ======================================================================= */

require_once dirname(__DIR__) . '/config/init.php';

// [AÇÃO] Se já há sessão, manda pro dashboard e encerra request
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . url('dashboard/index.php'));
    exit;
}

$error = '';

// [AÇÃO] Tratamento do POST (submissão do formulário)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [AÇÃO] Sanitização básica (trim + defaults)
    $email = trim($_POST['email'] ?? '');
    $senha = (string)($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        // [REGRA] Campos obrigatórios
        $error = 'Informe e-mail e senha.';
    } else {
        // [AÇÃO] Busca usuário pelo e-mail (prepared statement)
        $sql = "SELECT id, nome, email, senha FROM usuarios WHERE email = ? LIMIT 1";
        $st  = $GLOBALS['mysqli']->prepare($sql);
        if (!$st) {
            // [WARN] Falha inesperada na preparação da query
            $error = 'Falha ao preparar consulta.';
        } else {
            $st->bind_param('s', $email);
            $st->execute();
            $res = $st->get_result();
            $user = $res ? $res->fetch_assoc() : null;
            $st->close();

            if (!$user) {
                // [REGRA] Mensagem genérica (não vazar se e-mail existe)
                $error = 'Credenciais inválidas.';
            } else {
                $hash = $user['senha'];

                /** [REGRA]
                 *  - Se começar com $2y$ consideramos bcrypt gerado por password_hash().
                 *  - Fallback LEGADO: compara texto puro.
                 *  [WARN]
                 *  - Guardar senha em texto puro é inseguro.
                 *  [TODO]
                 *  - Ao detectar login bem-sucedido com senha em texto puro, re-hash:
                 *      $novoHash = password_hash($senha, PASSWORD_BCRYPT);
                 *      UPDATE usuarios SET senha=? WHERE id=?
                 */
                $ok = false;
                if (is_string($hash) && str_starts_with($hash, '$2y$')) {
                    $ok = password_verify($senha, $hash);
                } else {
                    $ok = hash_equals((string)$hash, $senha);
                }

                if ($ok) {
                    // [AÇÃO] Abre sessão mínima necessária
                    $_SESSION['user_id']    = (int)$user['id'];
                    $_SESSION['user_name']  = (string)$user['nome'];
                    $_SESSION['user_email'] = (string)$user['email'];

                    // [TODO] Mitigar fixation: regenerar ID de sessão pós-login
                    // session_regenerate_id(true);

                    // [TODO] "Lembrar de mim": setar cookie httpOnly/secure com token opaco

                    header('Location: ' . url('dashboard/index.php'));
                    exit;
                } else {
                    $error = 'Credenciais inválidas.';
                }
            }
        }
    }
}

// [UTIL] Escape seguro de HTML
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login COEPP</title>

  <!-- [CONTEXTO] CSS inline para página isolada de login (sem dependências externas) -->
  <style>
    :root{
      --bg: #f5f6fa; --surface:#fff; --border:#e5e7eb; --ink:#1f2937; --muted:#6b7280;
      --brand:#0a4ea1; --brand-ink:#0d3b7a; --shadow:0 8px 24px rgba(0,0,0,.06);
      --radius:16px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}
    body{display:grid;place-items:center;background:var(--bg);font-family:Arial,Helvetica,sans-serif;color:var(--ink);padding:24px}

    /* [AÇÃO] Layout em 2 colunas (form + info) com responsivo abaixo de 900px */
    .auth-wrapper{width:100%;max-width:1100px;display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:center}

    /* [AÇÃO] Cartões com elevação e bordas */
    .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:28px}

    /* [UI] Branding leve */
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:14px}
    .brand h1{font-size:22px;color:var(--brand-ink);font-weight:800;letter-spacing:.3px}

    /* [UI] Hierarquia de títulos e textos */
    .title{font-size:34px;font-weight:800;margin:6px 0 14px}
    .sub{font-size:14px;color:var(--muted);margin-bottom:16px}

    /* [UI] Campos de formulário */
    .form-group{margin-bottom:12px}
    label{display:block;font-size:14px;color:var(--muted);margin:0 0 6px;font-weight:700}
    .input{width:100%;height:46px;border:1px solid var(--border);border-radius:10px;background:#fff;padding:0 12px;font-size:15px;outline:none}
    .input:focus{border-color:#b4d0ff;box-shadow:0 0 0 3px #ecf3ff}

    /* [UX] Toggle mostrar/ocultar senha */
    .pw-wrap{position:relative}
    .toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:var(--brand);cursor:pointer}

    /* [UX] Linha de opções (lembrar de mim, link ajuda, etc.) */
    .row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:8px 0 16px;flex-wrap:wrap}
    .remember{display:flex;align-items:center;gap:8px;font-size:14px}
    .link{font-size:14px;color:var(--brand);text-decoration:none}
    .link:hover{text-decoration:underline}

    /* [UI] Botão primário */
    .btn{width:100%;height:48px;border:none;border-radius:12px;background:var(--brand);color:#fff;font-size:16px;font-weight:800;cursor:pointer}
    .btn:hover{filter:brightness(1.05)}

    /* [UI] Alertas de erro */
    .alert{background:#fff5f5;border:1px solid #fecaca;color:#b91c1c;padding:10px 12px;border-radius:10px;font-size:14px;margin-bottom:12px}

    /* [UI] Painel informativo (coluna direita) */
    .info{background:linear-gradient(135deg,#ecf3ff 0%,#ffffff 60%);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:28px;display:grid;place-items:center;text-align:center;gap:12px}
    .info h2{font-size:22px;color:var(--brand-ink);font-weight:800}
    .info p{color:var(--muted);font-size:14px;line-height:1.5}

    /* [RESP] Em telas menores, esconde a coluna informativa */
    @media (max-width:900px){.auth-wrapper{grid-template-columns:1fr;max-width:520px}.info{display:none}}
  </style>
</head>
<body>

<div class="auth-wrapper">
  <section class="card">
    <div class="brand">
      <h1>AGENDAMENTO COEPP</h1>
    </div>

    <h2 class="title">Entrar</h2>
    <p class="sub"><b>Acesse o sistema</b> da clínica-escola para gerenciar agendamentos e cadastros.</p>

    <?php if ($error): ?>
      <!-- [UI] Mensagem de erro genérica para falhas de autenticação -->
      <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- [AÇÃO] Formulário de login -->
    <!-- [TODO] Incluir input hidden com CSRF token -->
    <form method="POST" novalidate>
      <div class="form-group">
        <label for="email">E-mail</label>
        <input class="input" type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="form-group pw-wrap">
        <label for="senha">Senha</label>
        <input class="input" type="password" id="senha" name="senha" required>
        <button type="button" class="toggle" onclick="togglePw()">mostrar</button>
      </div>

      <div class="row">
        <!-- [INFO] Check visual; funcionalidade “lembrar de mim” ainda não implementada no backend -->
        <label class="remember"><input type="checkbox" name="remember" value="1"> Lembrar de mim</label>
      </div>

      <button class="btn" type="submit">Entrar</button>
    </form>
  </section>

  <!-- [UX] Coluna informativa (só desktop) -->
  <aside class="info">
    <h2>Bem-vindo(a) ao Sistema de Agendamento COEPP</h2>
    <p>Agende consultas, gerencie pacientes e acompanhe os estágios em um só lugar.</p>
    <p><b>Dica:</b> use seu e-mail institucional para acessar.</p>
  </aside>
</div>

<!-- [AÇÃO] Script pequeno para alternar visibilidade da senha -->
<script>
function togglePw(){
  const input = document.getElementById('senha');
  const btn = document.querySelector('.toggle');
  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';
  btn.textContent = isText ? 'mostrar' : 'ocultar';
}
</script>
</body>
</html>