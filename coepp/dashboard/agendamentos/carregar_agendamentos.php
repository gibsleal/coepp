<?php
// =======================================================================
// dashboard/agendamento/carregar_agendamentos.php
// [CONTEXTO]
//   - Endpoint que retorna eventos no formato esperado pelo FullCalendar.
// [REGRA]
//   - Duração fixa de 1h para cada evento (calculada em PHP).
// [WARN]
//   - Usa $conn (compat via init.php).
// =======================================================================

require_once __DIR__ . '/../../config/init.php';

// [AÇÃO] Consulta base (id, data, hora, sala, nome estagiário)
$sql = "SELECT 
            a.id,
            a.data,
            a.hora,
            a.sala,
            e.nome AS estagiario
        FROM agendamentos a
        JOIN estagiarios e ON a.estagiario_id = e.id";

$result = $conn->query($sql);
$eventos = [];

// [AÇÃO] Constrói o array de eventos com start/end ISO e título
while ($row = $result->fetch_assoc()) {
    $start = $row['data'] . 'T' . $row['hora']; // ex.: 2025-09-10T09:00:00
    $end = date('Y-m-d\TH:i:s', strtotime($start . ' +1 hour')); // [REGRA] +1h

    $eventos[] = [
        'id' => $row['id'],
        'title' => "Sala {$row['sala']} - {$row['estagiario']}",
        'start' => $start,
        'end' => $end,
        'backgroundColor' => '#007bff',
        'borderColor' => '#0056b3',
        'textColor' => '#fff'
    ];
}

// [SAÍDA] JSON para o FullCalendar
header('Content-Type: application/json');
echo json_encode($eventos);