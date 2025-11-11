<?php
// Iniciar sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir arquivos necessários
require_once 'includes/auth.php';
require_once 'config/config.php';

// Obter conexão com o banco de dados
$conn = getConnection();

// Verificar se o usuário já está logado
if (isLoggedIn()) {
    redirectAfterLogin();
}

// Variáveis para o formulário
$email = $senha = '';
$error = '';

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar o token CSRF
    if (!isset($_POST['csrf_token']) || !verificarCSRFToken($_POST['csrf_token'])) {
        $error = 'Erro de segurança. Por favor, tente novamente.';
    } else {
        // Obter os dados do formulário
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        
        // Validar os dados
        if (empty($email) || empty($senha)) {
            $error = 'Por favor, preencha todos os campos.';
        } else {
            try {
                // Buscar o usuário pelo email
                $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = :email AND status = 'ativo'");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Verificar a senha
                    if (password_verify($senha, $user['senha'])) {
                        // Login bem-sucedido
                        loginUser($user);
                        
                        // Redirecionar para a página apropriada
                        redirectAfterLogin();
                    } else {
                        $error = 'Senha incorreta.';
                    }
                } else {
                    $error = 'Usuário não encontrado ou inativo.';
                }
            } catch (PDOException $e) {
                $error = 'Erro ao fazer login: ' . $e->getMessage();
            }
        }
    }
}

// Obter mensagem de erro da sessão
$sessionError = getErrorMessage();
if (!empty($sessionError)) {
    $error = $sessionError;
}

// Gerar token CSRF
$csrf_token = gerarCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestão de farmácia - Controle de medicamentos, lotes, pacientes e receitas">
    <meta name="keywords" content="farmácia, medicamentos, gestão, controle de estoque, receitas">
    <meta name="author" content="Sistema Farmácia">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph -->
    <meta property="og:title" content="Login - <?php echo SYSTEM_NAME; ?>">
    <meta property="og:description" content="Sistema de gestão de farmácia da Prefeitura de Laje.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://farmacia.laje.app/login.php">
    <meta property="og:image" content="https://farmacia.laje.app/images/logo.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:site_name" content="Farmácia de Laje">
    <meta property="og:locale" content="pt_BR">
    
    <!-- Twitter / WhatsApp -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Login - <?php echo SYSTEM_NAME; ?>">
    <meta name="twitter:description" content="Sistema de gestão de farmácia da Prefeitura de Laje.">
    <meta name="twitter:image" content="https://farmacia.laje.app/images/logo.png">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="images/logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="images/logo.svg">
    <link rel="apple-touch-icon" href="images/logo.svg">
    
    <title>Login - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/custom.css">
    <style>
        body {
            font-family: var(--font-family);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-color: #f8f9fa;
            position: relative;
            overflow-x: hidden;
        }
        
        #bg-image-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            z-index: 1;
            background-color: transparent;
            pointer-events: none; /* Permite clicar através do container para elementos abaixo */
        }
        
        .login-card {
            width: 100%;
            max-width: 450px;
            pointer-events: auto; /* Garantir que os elementos dentro do container sejam clicáveis */
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.25);
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .login-title {
            text-align: center;
            font-weight: 700;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Container de imagem de fundo -->
    <div id="bg-image-container">
        <img src="images/background_farmacia.jpg" style="width: 100%; height: 100%; object-fit: cover;" alt="">
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="card shadow-lg">
                <div class="card-body p-4 p-md-5">
                    <div class="login-logo">
                        <img src="images/logo.svg" alt="Logo Farmácia" style="width: 80px; height: 80px;">
                    </div>
                    
                    <h1 class="login-title">Farmácia de Laje</h1>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <!-- Token CSRF para proteção -->
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-4">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="senha" class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="senha" name="senha" required>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i> Entrar
                            </button>
                        </div>
                        

                    </form>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <img src="images/logo_laje.png" alt="Prefeitura de Laje" style="max-width: 120px; opacity: 0.8;">
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
