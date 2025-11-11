<?php
// Configurações de conexão com o banco de dados
$host = "localhost";
$username = "root";
$password = "123456";
$database = "farmacia";

try {
    // Conectar ao banco de dados
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado ao banco de dados com sucesso.<br>";
    
    // Array de comandos SQL para executar
    $commands = [
        // Verificar se a coluna fabricante_id existe
        "SHOW COLUMNS FROM medicamentos LIKE 'fabricante_id'",
        // Adicionar coluna fabricante_id se não existir
        "ALTER TABLE medicamentos ADD COLUMN fabricante_id INT AFTER fabricante",
        
        // Verificar se a coluna tipo_id existe
        "SHOW COLUMNS FROM medicamentos LIKE 'tipo_id'",
        // Adicionar coluna tipo_id se não existir
        "ALTER TABLE medicamentos ADD COLUMN tipo_id INT AFTER tipo",
        
        // Verificar se a coluna categoria_id existe
        "SHOW COLUMNS FROM medicamentos LIKE 'categoria_id'",
        // Adicionar coluna categoria_id se não existir
        "ALTER TABLE medicamentos ADD COLUMN categoria_id INT AFTER categoria",
        
        // Verificar se a coluna unidade_medida_id existe
        "SHOW COLUMNS FROM medicamentos LIKE 'unidade_medida_id'",
        // Adicionar coluna unidade_medida_id se não existir
        "ALTER TABLE medicamentos ADD COLUMN unidade_medida_id INT AFTER unidade_medida"
    ];
    
    // Executar comandos para adicionar colunas
    for ($i = 0; $i < count($commands); $i += 2) {
        $check = $conn->query($commands[$i]);
        if ($check->rowCount() == 0) {
            // A coluna não existe, adicionar
            try {
                $conn->exec($commands[$i + 1]);
                echo "Coluna adicionada com sucesso: " . explode(" ", $commands[$i + 1])[4] . "<br>";
            } catch (PDOException $e) {
                echo "Erro ao adicionar coluna: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "Coluna já existe: " . explode(" ", $commands[$i + 1])[4] . "<br>";
        }
    }
    
    // Array de comandos para adicionar chaves estrangeiras
    $fkCommands = [
        // Verificar se a chave estrangeira fk_fabricante existe
        "SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'medicamentos' AND CONSTRAINT_NAME = 'fk_fabricante'",
        // Adicionar chave estrangeira fk_fabricante se não existir
        "ALTER TABLE medicamentos ADD CONSTRAINT fk_fabricante FOREIGN KEY (fabricante_id) REFERENCES fabricantes(id) ON DELETE SET NULL",
        
        // Verificar se a chave estrangeira fk_tipo existe
        "SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'medicamentos' AND CONSTRAINT_NAME = 'fk_tipo'",
        // Adicionar chave estrangeira fk_tipo se não existir
        "ALTER TABLE medicamentos ADD CONSTRAINT fk_tipo FOREIGN KEY (tipo_id) REFERENCES tipos_medicamentos(id) ON DELETE SET NULL",
        
        // Verificar se a chave estrangeira fk_categoria existe
        "SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'medicamentos' AND CONSTRAINT_NAME = 'fk_categoria'",
        // Adicionar chave estrangeira fk_categoria se não existir
        "ALTER TABLE medicamentos ADD CONSTRAINT fk_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL",
        
        // Verificar se a chave estrangeira fk_unidade_medida existe
        "SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'medicamentos' AND CONSTRAINT_NAME = 'fk_unidade_medida'",
        // Adicionar chave estrangeira fk_unidade_medida se não existir
        "ALTER TABLE medicamentos ADD CONSTRAINT fk_unidade_medida FOREIGN KEY (unidade_medida_id) REFERENCES unidades_medida(id) ON DELETE SET NULL"
    ];
    
    // Executar comandos para adicionar chaves estrangeiras
    for ($i = 0; $i < count($fkCommands); $i += 2) {
        $check = $conn->query($fkCommands[$i]);
        if ($check->rowCount() == 0) {
            // A chave estrangeira não existe, adicionar
            try {
                $conn->exec($fkCommands[$i + 1]);
                echo "Chave estrangeira adicionada com sucesso: " . explode(" ", $fkCommands[$i + 1])[6] . "<br>";
            } catch (PDOException $e) {
                echo "Erro ao adicionar chave estrangeira: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "Chave estrangeira já existe: " . explode(" ", $fkCommands[$i + 1])[6] . "<br>";
        }
    }
    
    echo "<br>Atualização da estrutura concluída!";
    
} catch(PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage();
}
?>
