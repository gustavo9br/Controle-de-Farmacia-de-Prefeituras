<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

try {
    $paciente_id = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
    
    error_log("API buscar_dispensacoes_paciente: paciente_id recebido = " . $paciente_id);
    
    if (empty($paciente_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID do paciente não informado'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $db = getConnection();
    
    $sql = "
        SELECT 
            d.id,
            d.data_dispensacao,
            d.quantidade,
            m.nome as medicamento_nome,
            COALESCE(m.descricao, '') as medicamento_descricao
        FROM dispensacoes d
        INNER JOIN medicamentos m ON d.medicamento_id = m.id
        WHERE d.paciente_id = ?
        ORDER BY d.data_dispensacao DESC
        LIMIT 5
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$paciente_id]);
    $dispensacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("API buscar_dispensacoes_paciente: encontradas " . count($dispensacoes) . " dispensações para paciente_id = " . $paciente_id);
    
    echo json_encode([
        'success' => true,
        'dispensacoes' => $dispensacoes,
        'total' => count($dispensacoes)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("API ERROR buscar_dispensacoes_paciente: " . $e->getMessage());
    error_log("API ERROR buscar_dispensacoes_paciente: Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar dispensações: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

