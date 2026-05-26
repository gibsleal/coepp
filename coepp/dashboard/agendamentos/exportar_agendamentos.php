<?php
// =======================================================================
// exportar_agendamentos.php
// [FUNÇÃO] Gera CSV dos agendamentos com filtros simples por estagiário/paciente.
// [ATENÇÃO] Filtros são interpolados direto no SQL (risco de injeção se não forem inteiros).
// =======================================================================

require_once __DIR__ . '/../../config/init.php';

// Filtros via GET
$estagiario_id = $_GET['estagiario_id'] ?? '';
$paciente_id   = $_GET['paciente_id']   ?? '';

// [ATENÇÃO] Sem prepared statement, montar where com MUITO cuidado.
// Aqui presumidamente virão números (IDs). Ideal: validar como int.
$filtro = [];
if ($estagiario_id !== '') $filtro[] = "a.estagiario_id = $estagiario_id";
if ($paciente_id   !== '') $filtro[] = "a.paciente_id = $paciente_id";

$where = count($filtro) ? 'WHERE ' . implode(' AND ', $filtro) : '';

// Buscar agendamentos
$sql = "SELECT 
            a.data, a.hora, a.sala, 
            e.nome AS estagiario, 
            p.nome AS paciente
        FROM agendamentos a
        JOIN estagiarios e ON a.estagiario_id = e.id
        JOIN pacientes p   ON a.paciente_id   = p.id
        $where
        ORDER BY a.data DESC, a.hora";

$result = $conn->query($sql); // $conn é definido em config/db.php

// Cabeçalhos CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="agendamentos.csv"');

$output = fopen('php://output', 'w');
// [DICA] Se quiser compat total com Excel/UTF-8, pode escrever BOM aqui.
// fwrite($output, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($output, ['Data', 'Hora', 'Sala', 'Estagiário', 'Paciente']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        date('d/m/Y', strtotime($row['data'] ?? '')),
        substr((string)$row['hora'], 0, 5),
        $row['sala'],
        $row['estagiario'],
        $row['paciente']
    ]);
}
fclose($output);
exit;
