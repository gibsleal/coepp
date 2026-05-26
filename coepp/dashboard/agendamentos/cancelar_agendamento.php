<?php
// =======================================================================
// dashboard/agendamento/cancelar_agendamento.php
// [CONTEXTO]
//   - Exclui um agendamento por ID e volta para o calendário.
// [WARN]
//   - Usa $conn (compat via init.php), query com interpolação (risco de SQL injection
//     se no futuro trocar GET sanitizado por string). Mantido como está.
//   - Não aplica auth_guard (página pública se não houver proteção por roteamento).
// =======================================================================

require_once __DIR__ . '/../../config/init.php';

// [AÇÃO] Checa parâmetro e executa exclusão simples
if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // [REGRA] força inteiro
    $sql = "DELETE FROM agendamentos WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        // [AÇÃO] Sucesso → volta para o calendário
        header("Location: calendario.php");
    } else {
        // [AÇÃO] Erro bruto (mantido)
        echo "Erro ao excluir agendamento: " . $conn->error;
    }
}