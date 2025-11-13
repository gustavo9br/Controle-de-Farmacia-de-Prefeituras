<?php
/**
 * Script de migração: Remover fabricantes e reorganizar estrutura
 * 
 * Nova estrutura:
 * - Medicamento (sem fabricante, sem código de barras direto)
 * - Códigos de Barras (tabela separada, ligada ao medicamento)
 * - Lotes (ligados ao código de barras, não mais diretamente ao medicamento)
 */

require_once __DIR__ . '/database.php';

$conn = getConnection();

try {
    $conn->beginTransaction();
    
    echo "<h2>Iniciando migração do banco de dados...</h2>";
    
    // 1. Criar tabela de códigos de barras
    echo "<p>1. Criando tabela codigos_barras...</p>";
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `codigos_barras` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `medicamento_id` INT NOT NULL,
            `codigo` VARCHAR(50) NOT NULL,
            `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`medicamento_id`) REFERENCES `medicamentos`(`id`) ON DELETE CASCADE,
            INDEX `idx_codigo_barras_medicamento` (`medicamento_id`),
            INDEX `idx_codigo_barras_codigo` (`codigo`),
            UNIQUE KEY `unique_medicamento_codigo` (`medicamento_id`, `codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "<p style='color: green;'>✓ Tabela codigos_barras criada.</p>";
    
    // 2. Migrar códigos de barras existentes de medicamentos para codigos_barras
    echo "<p>2. Migrando códigos de barras existentes...</p>";
    $stmt = $conn->query("SELECT id, codigo_barras FROM medicamentos WHERE codigo_barras IS NOT NULL AND codigo_barras != ''");
    $medicamentos_com_codigo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $migrados = 0;
    foreach ($medicamentos_com_codigo as $med) {
        try {
            $stmt = $conn->prepare("INSERT INTO codigos_barras (medicamento_id, codigo) VALUES (?, ?)");
            $stmt->execute([$med['id'], $med['codigo_barras']]);
            $migrados++;
        } catch (PDOException $e) {
            // Ignorar se já existe (duplicado)
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "<p style='color: orange;'>⚠ Erro ao migrar código de barras do medicamento ID {$med['id']}: " . $e->getMessage() . "</p>";
            }
        }
    }
    echo "<p style='color: green;'>✓ {$migrados} códigos de barras migrados.</p>";
    
    // 3. Criar coluna temporária codigo_barras_id na tabela lotes
    echo "<p>3. Adicionando coluna codigo_barras_id na tabela lotes...</p>";
    try {
        $conn->exec("ALTER TABLE lotes ADD COLUMN codigo_barras_id INT NULL AFTER medicamento_id");
        echo "<p style='color: green;'>✓ Coluna codigo_barras_id adicionada.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color: orange;'>⚠ Coluna codigo_barras_id já existe.</p>";
        } else {
            throw $e;
        }
    }
    
    // 4. Migrar lotes: associar cada lote ao código de barras do seu medicamento
    echo "<p>4. Associando lotes aos códigos de barras...</p>";
    $stmt = $conn->query("
        SELECT l.id as lote_id, l.medicamento_id, cb.id as codigo_barras_id
        FROM lotes l
        LEFT JOIN codigos_barras cb ON cb.medicamento_id = l.medicamento_id
        WHERE l.codigo_barras_id IS NULL
    ");
    $lotes_para_migrar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $lotes_migrados = 0;
    foreach ($lotes_para_migrar as $lote) {
        if ($lote['codigo_barras_id']) {
            $stmt = $conn->prepare("UPDATE lotes SET codigo_barras_id = ? WHERE id = ?");
            $stmt->execute([$lote['codigo_barras_id'], $lote['lote_id']]);
            $lotes_migrados++;
        } else {
            // Se não houver código de barras, criar um genérico ou deixar NULL
            // Por enquanto, vamos deixar NULL e o usuário terá que associar depois
            echo "<p style='color: orange;'>⚠ Lote ID {$lote['lote_id']} não tem código de barras associado. Será necessário associar manualmente.</p>";
        }
    }
    echo "<p style='color: green;'>✓ {$lotes_migrados} lotes associados a códigos de barras.</p>";
    
    // 5. Adicionar chave estrangeira de lotes para codigos_barras
    echo "<p>5. Adicionando chave estrangeira...</p>";
    try {
        $conn->exec("ALTER TABLE lotes ADD CONSTRAINT fk_lote_codigo_barras FOREIGN KEY (codigo_barras_id) REFERENCES codigos_barras(id) ON DELETE SET NULL");
        echo "<p style='color: green;'>✓ Chave estrangeira adicionada.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p style='color: orange;'>⚠ Chave estrangeira já existe.</p>";
        } else {
            throw $e;
        }
    }
    
    // 6. Remover coluna codigo_barras de medicamentos (manter por enquanto para segurança)
    // echo "<p>6. Removendo coluna codigo_barras de medicamentos...</p>";
    // $conn->exec("ALTER TABLE medicamentos DROP COLUMN codigo_barras");
    // echo "<p style='color: green;'>✓ Coluna codigo_barras removida.</p>";
    
    // 7. Remover referências a fabricantes (manter por enquanto para segurança)
    // echo "<p>7. Removendo referências a fabricantes...</p>";
    // try {
    //     $conn->exec("ALTER TABLE medicamentos DROP FOREIGN KEY IF EXISTS fk_fabricante");
    //     $conn->exec("ALTER TABLE medicamentos DROP COLUMN fabricante_id");
    //     $conn->exec("ALTER TABLE medicamentos DROP COLUMN fabricante");
    //     echo "<p style='color: green;'>✓ Referências a fabricantes removidas.</p>";
    // } catch (PDOException $e) {
    //     echo "<p style='color: orange;'>⚠ Erro ao remover fabricantes: " . $e->getMessage() . "</p>";
    // }
    
    $conn->commit();
    
    echo "<h2 style='color: green;'>✓ Migração concluída com sucesso!</h2>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>Verifique se os lotes foram associados corretamente aos códigos de barras</li>";
    echo "<li>Teste o cadastro de novos lotes com a nova estrutura</li>";
    echo "<li>Após confirmar que tudo está funcionando, execute a segunda parte da migração para remover as colunas antigas</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "<h2 style='color: red;'>✗ Erro na migração:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p>Rollback executado. Nenhuma alteração foi aplicada.</p>";
}
?>

