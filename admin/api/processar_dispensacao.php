<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();

// Receber dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Verificar se é múltiplos medicamentos (novo formato)
if (isset($data['medicamentos']) && is_array($data['medicamentos'])) {
    // Novo formato: múltiplos medicamentos
    $paciente_id = (int)($data['paciente_id'] ?? 0);
    $medicamentos = $data['medicamentos'];
    $observacoes = trim($data['observacoes'] ?? '');
    $usuario_id = (int)$_SESSION['user_id'];
    
    if (empty($paciente_id)) {
        echo json_encode(['success' => false, 'message' => 'Paciente não informado']);
        exit;
    }
    
    if (empty($medicamentos)) {
        echo json_encode(['success' => false, 'message' => 'Adicione pelo menos um medicamento']);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        foreach ($medicamentos as $med) {
            $medicamento_id = (int)($med['medicamento_id'] ?? 0);
            $lote_id = (int)($med['lote_id'] ?? 0);
            $quantidade = (int)($med['quantidade'] ?? 0);
            
            if (empty($medicamento_id) || empty($lote_id) || empty($quantidade)) {
                throw new Exception('Dados incompletos em um dos medicamentos');
            }
            
            // Verificar estoque do lote
            $stmt = $conn->prepare("SELECT quantidade_atual, data_validade FROM lotes WHERE id = ? FOR UPDATE");
            $stmt->execute([$lote_id]);
            $lote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lote) {
                throw new Exception('Lote não encontrado');
            }
            
            if ($lote['quantidade_atual'] < $quantidade) {
                throw new Exception('Estoque insuficiente. Disponível: ' . $lote['quantidade_atual']);
            }
            
            // Registrar dispensação
            $stmt = $conn->prepare("
                INSERT INTO dispensacoes 
                (paciente_id, medicamento_id, lote_id, quantidade, usuario_id, tipo, observacoes, data_dispensacao)
                VALUES (?, ?, ?, ?, ?, 'avulsa', ?, NOW())
            ");
            $stmt->execute([$paciente_id, $medicamento_id, $lote_id, $quantidade, $usuario_id, $observacoes ?: null]);
            
            // Atualizar estoque do lote
            $stmt = $conn->prepare("UPDATE lotes SET quantidade_atual = quantidade_atual - ? WHERE id = ?");
            $stmt->execute([$quantidade, $lote_id]);
            
            // Atualizar estoque total do medicamento
            $stmt = $conn->prepare("UPDATE medicamentos SET estoque_atual = estoque_atual - ? WHERE id = ?");
            $stmt->execute([$quantidade, $medicamento_id]);
            
            // Registrar movimentação
            $stmt = $conn->prepare("
                INSERT INTO movimentacoes 
                (medicamento_id, lote_id, tipo, quantidade, usuario_id, observacoes, data_movimentacao)
                VALUES (?, ?, 'dispensacao', ?, ?, ?, NOW())
            ");
            $stmt->execute([$medicamento_id, $lote_id, $quantidade, $usuario_id, $observacoes ?: null]);
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Dispensação registrada com sucesso!',
            'total_medicamentos' => count($medicamentos)
        ]);
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Formato antigo: um medicamento por vez (manter compatibilidade)
$medicamento_id = (int)($data['medicamento_id'] ?? 0);
$lote_id = (int)($data['lote_id'] ?? 0);
$paciente_id = (int)($data['paciente_id'] ?? 0);
$quantidade = (int)($data['quantidade'] ?? 0);
$tipo = $data['tipo'] ?? 'avulsa';
$receita_item_id = !empty($data['receita_item_id']) ? (int)$data['receita_item_id'] : null;
$observacoes = trim($data['observacoes'] ?? '');
$usuario_id = (int)$_SESSION['user_id'];

// Validações básicas
if (empty($medicamento_id) || empty($lote_id) || empty($paciente_id) || empty($quantidade)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

if ($quantidade <= 0) {
    echo json_encode(['success' => false, 'message' => 'Quantidade deve ser maior que zero']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // 1. Verificar estoque do lote
    $stmt = $conn->prepare("SELECT quantidade_atual, data_validade FROM lotes WHERE id = ? FOR UPDATE");
    $stmt->execute([$lote_id]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lote) {
        throw new Exception('Lote não encontrado');
    }
    
    if ($lote['quantidade_atual'] < $quantidade) {
        throw new Exception('Estoque insuficiente no lote. Disponível: ' . $lote['quantidade_atual']);
    }
    
    if ($lote['data_validade'] < date('Y-m-d')) {
        throw new Exception('Lote vencido');
    }
    
    // 2. Se for dispensação com receita, validar e atualizar receita
    if ($tipo === 'receita' && $receita_item_id) {
        $stmt = $conn->prepare("
            SELECT ri.*, r.status, r.data_validade
            FROM receitas_itens ri
            INNER JOIN receitas r ON r.id = ri.receita_id
            WHERE ri.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$receita_item_id]);
        $receita_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$receita_item) {
            throw new Exception('Item de receita não encontrado');
        }
        
        if ($receita_item['status'] !== 'ativa') {
            throw new Exception('Receita não está ativa');
        }
        
        if ($receita_item['data_validade'] < date('Y-m-d')) {
            throw new Exception('Receita vencida');
        }
        
        $quantidade_disponivel = $receita_item['quantidade_autorizada'] - $receita_item['quantidade_retirada'];
        
        if ($quantidade > $quantidade_disponivel) {
            throw new Exception("Quantidade excede o autorizado na receita. Disponível: $quantidade_disponivel");
        }
        
        // Verificar intervalo mínimo entre retiradas
        if ($receita_item['ultima_retirada']) {
            $diasDesdeUltima = (strtotime('now') - strtotime($receita_item['ultima_retirada'])) / 86400;
            if ($diasDesdeUltima < $receita_item['intervalo_dias']) {
                $diasRestantes = ceil($receita_item['intervalo_dias'] - $diasDesdeUltima);
                throw new Exception("É necessário aguardar $diasRestantes dia(s) desde a última retirada");
            }
        }
        
        // Atualizar quantidade retirada
        $nova_quantidade_retirada = $receita_item['quantidade_retirada'] + $quantidade;
        $stmt = $conn->prepare("
            UPDATE receitas_itens 
            SET quantidade_retirada = ?, 
                ultima_retirada = CURDATE(),
                atualizado_em = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nova_quantidade_retirada, $receita_item_id]);
        
        // Se completou a receita deste item, verificar se todos os itens foram concluídos
        if ($nova_quantidade_retirada >= $receita_item['quantidade_autorizada']) {
            // Verificar se todos os itens da receita foram concluídos
            $stmt = $conn->prepare("
                SELECT COUNT(*) as pendentes
                FROM receitas_itens
                WHERE receita_id = ?
                AND quantidade_retirada < quantidade_autorizada
            ");
            $stmt->execute([$receita_item['receita_id']]);
            $pendentes = $stmt->fetchColumn();
            
            // Se não há mais itens pendentes, finalizar receita
            if ($pendentes == 0) {
                $stmt = $conn->prepare("UPDATE receitas SET status = 'finalizada', atualizado_em = NOW() WHERE id = ?");
                $stmt->execute([$receita_item['receita_id']]);
            }
        }
    }
    
    // 3. Registrar dispensação
    $stmt = $conn->prepare("
        INSERT INTO dispensacoes 
        (paciente_id, medicamento_id, lote_id, receita_item_id, quantidade, usuario_id, tipo, observacoes, data_dispensacao)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $paciente_id,
        $medicamento_id,
        $lote_id,
        $receita_item_id,
        $quantidade,
        $usuario_id,
        $tipo,
        $observacoes ?: null
    ]);
    
    $dispensacao_id = $conn->lastInsertId();
    
    // 4. Atualizar estoque do lote
    $stmt = $conn->prepare("
        UPDATE lotes 
        SET quantidade_atual = quantidade_atual - ?,
            atualizado_em = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$quantidade, $lote_id]);
    
    // 5. Atualizar estoque total do medicamento
    $stmt = $conn->prepare("
        UPDATE medicamentos 
        SET estoque_atual = estoque_atual - ?,
            atualizado_em = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$quantidade, $medicamento_id]);
    
    // 6. Registrar movimentação no histórico
    $stmt = $conn->prepare("
        INSERT INTO movimentacoes 
        (medicamento_id, lote_id, tipo, quantidade, usuario_id, observacoes, data_movimentacao)
        VALUES (?, ?, 'dispensacao', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $medicamento_id,
        $lote_id,
        $quantidade,
        $usuario_id,
        "Dispensação #$dispensacao_id para paciente ID: $paciente_id" . ($observacoes ? " - $observacoes" : "")
    ]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dispensação realizada com sucesso',
        'dispensacao_id' => $dispensacao_id
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar dispensação: ' . $e->getMessage()
    ]);
}
