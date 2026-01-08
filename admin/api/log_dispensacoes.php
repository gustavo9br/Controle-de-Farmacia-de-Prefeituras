<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // Validar limite para evitar SQL injection
    if ($limit < 1 || $limit > 100) {
        $limit = 10;
    }
    
    $db = getConnection();
    
    // Log para debug
    error_log("LOG API: Buscando $limit dispensações");
    
    // Usar LIMIT diretamente pois é inteiro validado
    $sql = "
        SELECT 
            d.id,
            d.data_dispensacao,
            d.quantidade,
            p.nome as paciente_nome,
            m.nome as medicamento_nome,
            l.numero_lote as lote_numero,
            COALESCE(f.nome, u.nome, 'Sistema') as responsavel_nome
        FROM dispensacoes d
        INNER JOIN pacientes p ON d.paciente_id = p.id
        INNER JOIN medicamentos m ON d.medicamento_id = m.id
        INNER JOIN lotes l ON d.lote_id = l.id
        INNER JOIN usuarios u ON d.usuario_id = u.id
        LEFT JOIN funcionarios f ON d.funcionario_id = f.id
        ORDER BY d.data_dispensacao DESC
        LIMIT $limit
    ";
    
    $stmt = $db->query($sql);
    $dispensacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("LOG API: Encontradas " . count($dispensacoes) . " dispensações");
    
    echo json_encode([
        'success' => true,
        'dispensacoes' => $dispensacoes,
        'total' => count($dispensacoes)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("LOG API ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar log: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
