<?php
session_start();

// Ativar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conexão com o banco de dados MySQL
$conn = new mysqli('localhost', 'root', '', 'agendamentos');

// Verifica se a conexão foi bem-sucedida
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função de logout
if (isset($_GET['sair'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Função para verificar se a data é inválida (sábados, domingos, feriados ou datas passadas)
function isDataInvalida($data) {
    $diaSemana = date('w', strtotime($data)); // Retorna 0 (domingo) a 6 (sábado)
    $dataAtual = date('Y-m-d'); // Data de hoje

    // Verificar se a data já passou
    if ($data < $dataAtual) {
        return true;
    }

    // Verificar se é sábado (6) ou domingo (0)
    if ($diaSemana == 0 || $diaSemana == 6) {
        return true;
    }

    // Lista de feriados
    $feriados = [
        '2024-01-01', // Ano Novo
        '2024-04-21', // Tiradentes
        '2024-05-01', // Dia do Trabalho
        // Adicione outros feriados conforme necessário
    ];

    // Verificar se é feriado
    if (in_array($data, $feriados)) {
        return true;
    }

    return false;
}

// Processar o agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'agendar') {
    $servico = $_POST['servico'];
    $data = $_POST['data'];
    $horario = $_POST['horario'];
    $user_id = $_SESSION['user_id'];

    // Verificar se a data é válida
    if (isDataInvalida($data)) {
        $_SESSION['message'] = "<span style='color: red;'>Agendamento não permitido para sábados, domingos, feriados ou datas passadas.</span>";
        header("Location: sistema.php?agendar");
        exit();
    }

    // Verificar agendamentos existentes
    if ($servico === 'comercial' || $servico === 'tecnica') {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM agendamentos WHERE servico IN ('comercial', 'tecnica') AND data = ?");
        $stmt->bind_param("s", $data);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM agendamentos WHERE servico = 'saude' AND data = ?");
        $stmt->bind_param("s", $data);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total = $row['total'];
    $stmt->close();

    // Regras de agendamento
    if (($servico === 'comercial' || $servico === 'tecnica') && $total >= 1) {
        $_SESSION['message'] = "<span style='color: red;'>Já existe um agendamento para esse dia.</span>";
        header("Location: sistema.php?agendar");
        exit();
    } elseif ($servico === 'saude' && $total >= 10) {
        $_SESSION['message'] = "<span style='color: red;'>Já existem 10 agendamentos para Saúde neste dia.</span>";
        header("Location: sistema.php?agendar");
        exit();
    }

    // Inserir agendamento no banco de dados
    $stmt = $conn->prepare("INSERT INTO agendamentos (user_id, servico, data, horario) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $servico, $data, $horario);

    if ($stmt->execute()) {
        $_SESSION['message'] = "<span style='color: green;'>Agendamento realizado com sucesso!</span>";
    } else {
        $_SESSION['message'] = "<span style='color: red;'>Erro ao agendar. Tente novamente.</span>";
    }
    $stmt->close();
    header("Location: sistema.php?agendar");
    exit();
}

// Exibir agendamentos do usuário
if (isset($_GET['usuario'])) {
    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT * FROM agendamentos WHERE user_id = $user_id");

    echo "<!DOCTYPE html><html lang='pt-br'><head>";
    echo "<meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Meus Agendamentos</title>";
    echo "<style>
        body { font-family: 'Roboto', sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; color: #333; }
        .container { width: 90%; max-width: 500px; margin: 50px auto; padding: 40px; background-color: white; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); border-radius: 10px; text-align: center; }
        h1 { color: #333; font-size: 28px; font-weight: 500; margin-bottom: 20px; }
        p { font-size: 16px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 12px; text-align: left; }
        th { background-color: #f4f4f4; font-weight: 500; }
        td { color: #333; }
        .btn { display: inline-block; padding: 12px 30px; background-color: #00c853; border: none; color: white; font-size: 16px; cursor: pointer; border-radius: 4px; text-decoration: none; transition: background-color 0.3s ease; }
        .btn:hover { background-color: #008c3a; }
        .btn-sair { background-color: #d32f2f; }
        .btn-sair:hover { background-color: #b71c1c; }
        .btn-voltar { margin-top: 20px; background-color: #0069c0; }
        .btn-voltar:hover { background-color: #004ba0; }
    </style>";
    echo "</head><body>";
    echo "<div class='container'>";
    echo "<h1>Meus Agendamentos</h1>";

    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Serviço</th><th>Data</th><th>Horário</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>" . htmlspecialchars($row['servico']) . "</td><td>" . htmlspecialchars($row['data']) . "</td><td>" . htmlspecialchars($row['horario']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum agendamento encontrado.</p>";
    }

    // Botão Voltar
    echo "<a href='sistema.php?agendar' class='btn btn-voltar'>Voltar</a>";
    echo "<a href='sistema.php?sair' class='btn btn-sair'>Sair</a>";
    echo "</div>";
    echo "</body></html>";
    exit();
}

// Cabeçalho e estilo para a página inicial
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Agendamentos</title>
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; color: #333; }
        .container { width: 90%; max-width: 500px; margin: 50px auto; padding: 40px; background-color: white; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); border-radius: 10px; text-align: center; }
        h1 { color: #333; font-size: 28px; font-weight: 500; margin-bottom: 20px; }
        .form-group { margin-bottom: 25px; text-align: left; }
        .form-group label { display: block; font-size: 14px; color: #555; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; color: #333; }
        .form-group input:focus, .form-group select:focus { border-color: #00c853; outline: none; }
        .form-group select { appearance: none; background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"...'); background-repeat: no-repeat; background-position: right 12px top 50%; }
        button, .btn { display: inline-block; padding: 12px 30px; background-color: #00c853; border: none; color: white; font-size: 16px; cursor: pointer; border-radius: 4px; text-decoration: none; }
        button:hover, .btn:hover { background-color: #008c3a; }
        .btn-sair { background-color: #d32f2f; }
        .btn-sair:hover { background-color: #b71c1c; }
        .message { margin-top: 20px; font-size: 16px; }
    </style>
    <script>
        // Função para verificar se a data é inválida (sábados, domingos, feriados ou datas passadas)
function isDataInvalida($data) {
    $diaSemana = date('w', strtotime($data)); // Retorna 0 (domingo) a 6 (sábado)
    $dataAtual = date('Y-m-d'); // Data de hoje

    // Verificar se a data já passou
    if ($data < $dataAtual) {
        return true;
    }

    // Permitir apenas agendamentos de segunda (1) a sexta (5)
    if ($diaSemana == 0 || $diaSemana == 6) {
        return true;
    }

    // Lista de feriados
    $feriados = [
        '2024-01-01', // Ano Novo
        '2024-04-21', // Tiradentes
        '2024-05-01', // Dia do Trabalho
        // Adicione outros feriados conforme necessário
    ];

    // Verificar se é feriado
    if (in_array($data, $feriados)) {
        return true;
    }

    return false; // Data é válida
}

    </script>
</head>
<body>
    <div class="container">
        <h1>Agendar Serviço</h1>
        <form action="sistema.php" method="POST" class="form-agendar">
            <input type="hidden" name="action" value="agendar">
            <div class="form-group">
                <label for="servico">Escolha o serviço:</label>
                <select name="servico" id="servico" required>
                    <option value="" disabled selected>Selecione um serviço</option>
                    <option value="comercial">Visita Comercial</option>
                    <option value="tecnica">Visita Técnica</option>
                    <option value="saude">Saúde Ocupacional</option>
                </select>
            </div>
            <div class="form-group">
                <label for="data">Data:</label>
                <input type="date" name="data" id="data" required onchange="validarData()">
            </div>
            <div class="form-group">
                <label for="horario">Horário:</label>
                <select name="horario" id="horario" required>
                    <option value="" disabled selected>Selecione um horário</option>
                    <option value="09h-12h">09h às 12h</option>
                    <option value="13h-17h">13h às 17h</option>
                </select>
            </div>
            <button type="submit" class="btn-agendar">Agendar</button>
        </form>
        
        <!-- Exibir mensagem de sucesso ou erro -->
        <?php
        if (isset($_SESSION['message'])) {
            echo "<div class='message'>" . $_SESSION['message'] . "</div>";
            unset($_SESSION['message']); // Limpar a mensagem após exibição
        }
        ?>

        <div class="action-buttons">
            <a href="sistema.php?usuario" class="btn-ver-agendamentos">Meus Agendamentos</a>
            <a href="sistema.php?sair" class="btn-sair">Sair</a>
        </div>
    </div>
</body>
</html>
