<?php
session_start();

// Conexão com o banco de dados MySQL
$conn = new mysqli('localhost', 'root', '', 'agendamentos');

// Verifica se a conexão foi bem-sucedida
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Ativar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verifica se o usuário está logado e já está em uma página de destino
if (isset($_SESSION['user_id'])) {
    header("Location: sistema.php?agendar");
    exit();
}

// Ações de login e cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST['action'] === 'login') {
        // Login
        $email = $_POST['email'];
        $senha = $_POST['senha'];

        $stmt = $conn->prepare("SELECT id, nome, senha, nivel FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($senha, $user['senha'])) {
            // Salvar informações do usuário na sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nome'] = $user['nome'];
            $_SESSION['nivel'] = $user['nivel'];

            // Redirecionar o usuário para o formulário de agendamento ao fazer login
            if ($user['nivel'] === 'admin') {
                header("Location: painel.php");
                exit();
            } else {
                header("Location: sistema.php?agendar");
                exit();
            }
        } else {
            echo "Login ou senha incorretos!";
        }
    } elseif ($_POST['action'] === 'cadastro') {
        // Cadastro
        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $telefone_fixo = $_POST['telefone_fixo'];
        $celular = $_POST['celular'];
        $documento = $_POST['documento'];
        $endereco = $_POST['endereco'];

        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, telefone_fixo, celular, documento, endereco, nivel) VALUES (?, ?, ?, ?, ?, ?, ?, 'usuario')");
        $stmt->bind_param("sssssss", $nome, $email, $senha, $telefone_fixo, $celular, $documento, $endereco);

        if ($stmt->execute()) {
            echo "Usuário cadastrado com sucesso!";
        } else {
            echo "Erro ao cadastrar usuário.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login e Cadastro</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Adicionando estilos de exibição */
        .hidden {
            display: none;
        }

        .error-message {
            color: red;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="container" id="login">
    <h2>Login</h2>
    <form action="index.php" method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" name="email" placeholder="E-mail" required>
        </div>
        <div class="form-group">
            <label for="senha">Senha:</label>
            <input type="password" name="senha" placeholder="Senha" required>
        </div>
        <button type="submit">Entrar</button>
    </form>
    <a href="#" onclick="mostrarCadastro()">Não tem uma conta? Cadastre-se</a>
</div>

<div class="container hidden" id="cadastro">
    <h2>Cadastro</h2>
    <form action="index.php" method="POST" onsubmit="return validarFormulario()">
        <input type="hidden" name="action" value="cadastro">
        
        <!-- Nome Completo -->
        <div class="form-group">
            <label for="nome">Nome Completo:</label>
            <input type="text" name="nome" placeholder="Nome Completo" required>
        </div>

        <!-- E-mail -->
        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" name="email" placeholder="E-mail" required>
        </div>

        <!-- Senha -->
        <div class="form-group">
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" placeholder="Senha" required>
        </div>

        <!-- Confirmar Senha -->
        <div class="form-group">
            <label for="confirmar_senha">Confirmar Senha:</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Confirmar Senha" required>
        </div>

        <!-- Telefone Fixo -->
        <div class="form-group">
            <label for="telefone_fixo">Telefone Fixo:</label>
            <input type="text" name="telefone_fixo" placeholder="Telefone Fixo">
        </div>

        <!-- Celular (WhatsApp) -->
        <div class="form-group">
            <label for="celular">Celular (WhatsApp):</label>
            <input type="text" name="celular" placeholder="Celular (WhatsApp)" required>
        </div>

        <!-- CPF ou CNPJ -->
        <div class="form-group">
            <label for="documento">CPF ou CNPJ:</label>
            <input type="text" name="documento" placeholder="CPF ou CNPJ" required>
        </div>

        <!-- Endereço -->
        <div class="form-group">
            <label for="endereco">Endereço:</label>
            <input type="text" name="endereco" placeholder="Endereço" required>
        </div>

        <div id="error-message" class="error-message"></div>

        <button type="submit">Cadastrar</button>
    </form>
    <a href="#" onclick="mostrarLogin()">Já tem uma conta? Faça login</a>
</div>

<script>
    function mostrarCadastro() {
        document.getElementById("login").classList.add("hidden");
        document.getElementById("cadastro").classList.remove("hidden");
    }

    function mostrarLogin() {
        document.getElementById("cadastro").classList.add("hidden");
        document.getElementById("login").classList.remove("hidden");
    }

    function validarFormulario() {
        var senha = document.getElementById("senha").value;
        var confirmarSenha = document.getElementById("confirmar_senha").value;

        if (senha !== confirmarSenha) {
            document.getElementById("error-message").innerText = "As senhas não correspondem.";
            return false;
        }
        return true;
    }
</script>

</body>
</html>
