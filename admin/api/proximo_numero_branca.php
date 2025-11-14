<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();

try {
    // Buscar o último número de receita branca
    $stmt = $conn->prepare("SELECT MAX(CAST(numero_receita AS UNSIGNED)) as ultimo_numero FROM receitas WHERE tipo_receita = 'branca' AND numero_receita REGEXP '^[0-9]+$'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $ultimoNumero = $result['ultimo_numero'] ?? 0;
    $proximoNumero = (string)($ultimoNumero + 1);
    
    echo json_encode([
        'success' => true,
        'proximo_numero' => $proximoNumero
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar próximo número: ' . $e->getMessage(),
        'proximo_numero' => '1'
    ]);
}

