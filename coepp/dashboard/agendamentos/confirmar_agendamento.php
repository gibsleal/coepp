<?php
// =======================================================================
// dashboard/agendamento/confirmar_agendamento.php
// [CONTEXTO]
//   - Tela de confirmação após o usuário selecionar um slot no calendário.
//   - Recebe estagiario_id e inicio (datetime ISO) por GET,
//     mostra os dados e permite escolher um paciente para confirmar.
// [WARN]
//   - Usa $conn (compat via init.php).
//   - Includes relativos para header/footer (../includes) — checar estrutura real.
//   - Sem auth_guard aqui; mantido como está.
// =======================================================================

session_start();
require_once __DIR__ . '/../../config/init.php';
include('../includes/header.php');

// [VALIDAÇÃO] Parâmetros obrigatórios
if (!isset($_GET['estagiario_id']) || !isset($_GET['inicio'])) {
    echo "<p>Erro: Dados incompletos.</p>";
    exit;
}

$estagiario_id = intval($_GET['estagiario_id']);
$inicio = $_GET['inicio'];
$data = substr($inicio, 0, 10);   // [REGRA] yyyy-mm-dd
$hora = substr($inicio, 11, 5);   // [REGRA] HH:MM

// [AÇÃO] Buscar nome do estagiário
$sqlEstagiario = "SELECT nome FROM estagiarios WHERE id = $estagiario_id";
$res = $conn->query($sqlEstagiario);
$estagiario = $res->fetch_assoc()['nome'] ?? 'Estagiário não encontrado';

// [AÇÃO] Listar pacientes para o select
$pacientes = $conn->query("SELECT id, nome FROM pacientes ORDER BY nome");
?>

<h2>Confirmação de Agendamento</h2>

<form action="salvar_agendamento.php" method="POST">
    <!-- [REGRA] Encaminha o contexto para o salvamento -->
    <input type="hidden" name="estagiario_id" value="<?= $estagiario_id ?>">
    <input type="hidden" name="inicio" value="<?= $inicio ?>">

    <p><strong>Estagiário:</strong> <?= htmlspecialchars($estagiario) ?></p>
    <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($data)) ?></p>
    <p><strong>Hora:</strong> <?= $hora ?></p>

    <label>Paciente:</label><br>
    <select name="paciente_id" required>
        <option value="">Selecione</option>
        <?php while ($p = $pacientes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>"><?= $p['nome'] ?></option>
        <?php endwhile; ?>
    </select><br><br>

    <input type="submit" value="Confirmar Agendamento">
</form>

<?php include('../includes/footer.php'); ?>