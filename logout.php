<?php
// Incluir arquivo de autenticação
require_once 'includes/auth.php';

// Fazer logout do usuário
logoutUser();

// Redirecionar para a página de login
header("Location: login.php");
exit;
?>
