<?php
session_start();

// Verifica se o usuário é administrador
if (!isset($_SESSION['user_id']) || $_SESSION['nivel'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Ativar exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_WARNING); // Remover exibição de warnings

// Conexão com o banco de dados MySQL
$conn = new mysqli('localhost', 'root', '', 'agendamentos');

// Verifica se a conexão foi bem-sucedida
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Inicializa as variáveis de filtro para evitar warnings
$filtroData = isset($_GET['filtro_data']) ? $_GET['filtro_data'] : '';
$pesquisaNome = isset($_GET['pesquisa_nome']) ? $_GET['pesquisa_nome'] : '';
$filtroServico = isset($_GET['filtro_servico']) ? $_GET['filtro_servico'] : '';

// Função de logout
if (isset($_GET['sair'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Função para deletar agendamentos em massa
if (isset($_POST['action']) && $_POST['action'] === 'delete_selected') {
    if (isset($_POST['selected_agendamentos'])) {
        $agendamentosSelecionados = $_POST['selected_agendamentos'];
        foreach ($agendamentosSelecionados as $agendamento_id) {
            $stmt = $conn->prepare("DELETE FROM agendamentos WHERE id = ?");
            $stmt->bind_param("i", $agendamento_id);
            $stmt->execute();
        }
        echo "Agendamentos excluídos com sucesso!";
    }
}

// Função para consultar agendamentos com filtros
function consultar_agendamentos($conn, $servico_filtro, $filtroData, $pesquisaNome) {
    $sql = "SELECT agendamentos.id AS agendamento_id, usuarios.nome AS usuario_nome, usuarios.email AS usuario_email, agendamentos.servico, agendamentos.data, agendamentos.horario 
            FROM agendamentos 
            JOIN usuarios ON agendamentos.usuario_id = usuarios.id 
            WHERE agendamentos.servico = ?";

    if ($filtroData) {
        $sql .= " AND agendamentos.data = ?";
    }

    if ($pesquisaNome) {
        $sql .= " AND usuarios.nome LIKE ?";
    }

    $stmt = $conn->prepare($sql);
    if ($filtroData && $pesquisaNome) {
        $pesquisaNome = "%$pesquisaNome%";
        $stmt->bind_param("sss", $servico_filtro, $filtroData, $pesquisaNome);
    } elseif ($filtroData) {
        $stmt->bind_param("ss", $servico_filtro, $filtroData);
    } elseif ($pesquisaNome) {
        $pesquisaNome = "%$pesquisaNome%";
        $stmt->bind_param("ss", $servico_filtro, $pesquisaNome);
    } else {
        $stmt->bind_param("s", $servico_filtro);
    }

    $stmt->execute();
    return $stmt->get_result();
}

// Consultar total de agendamentos por serviço
$agendamentosPorServico = [];
$servicos = ["comercial", "tecnica", "saude"];
foreach ($servicos as $servico) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM agendamentos WHERE servico = ?");
    $stmt->bind_param("s", $servico);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $agendamentosPorServico[$servico] = $row['total'];
}

// Consultar total de usuários
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM usuarios");
$stmt->execute();
$result = $stmt->get_result();
$totalUsuarios = $result->fetch_assoc()['total'];

// Consultar todos os usuários para exibir na aba "Usuários"
$usuarios = $conn->query("SELECT id, nome, email FROM usuarios");

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos para as abas e sub-abas */
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }

        .tab-button {
            padding: 10px 20px;
            background-color: #f4f4f4;
            border: 1px solid #ddd;
            cursor: pointer;
        }

        .tab-button.active {
            background-color: #00c853;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .tab-content table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tab-content th, .tab-content td {
            border: 1px solid #ddd;
            padding: 10px;
        }

        .tab-content th {
            background-color: #f4f4f4;
        }

        /* Estilos para o dashboard */
        .dashboard-box {
            background-color: #fff;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        /* Estilos de seleção múltipla */
        .bulk-actions {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="admin-container">
    <h1>Painel Administrativo</h1>
    <a class="logout" href="painel.php?sair">Sair</a>

    <!-- Abas -->
    <div class="tabs">
        <div class="tab-button" data-tab="dashboard">Dashboard</div>
        <div class="tab-button" data-tab="agendamentos">Agendamentos</div>
        <div class="tab-button" data-tab="usuarios">Usuários</div>
    </div>

    <!-- Conteúdo do Dashboard -->
    <div class="tab-content" id="tab-dashboard">
        <h2>Dashboard</h2>
        <div class="dashboard-box">
            <h3>Total de Agendamentos por Serviço</h3>
            <p>Comercial: <?php echo $agendamentosPorServico['comercial']; ?></p>
            <p>Técnica: <?php echo $agendamentosPorServico['tecnica']; ?></p>
            <p>Saúde Ocupacional: <?php echo $agendamentosPorServico['saude']; ?></p>
        </div>
        <div class="dashboard-box">
            <h3>Total de Usuários: <?php echo $totalUsuarios; ?></h3>
        </div>
    </div>

    <!-- Conteúdo da Aba Agendamentos -->
    <div class="tab-content" id="tab-agendamentos">
        <h2>Agendamentos</h2>
        <form method="POST" action="painel.php">
            <input type="hidden" name="action" value="delete_selected">
            <div class="bulk-actions">
                <button type="submit">Excluir Selecionados</button>
            </div>
            <table>
                <tr>
                    <th><input type="checkbox" id="select-all"></th> <!-- Checkbox para selecionar todos -->
                    <th>Usuário</th>
                    <th>Email</th>
                    <th>Serviço</th>
                    <th>Data</th>
                    <th>Horário</th>
                    <th>Ações</th>
                </tr>
                <?php foreach ($servicos as $servico): ?>
                    <?php
                    $agendamentos = consultar_agendamentos($conn, $servico, $filtroData, $pesquisaNome);
                    if ($agendamentos->num_rows > 0):
                        while ($agendamento = $agendamentos->fetch_assoc()): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_agendamentos[]" value="<?php echo $agendamento['agendamento_id']; ?>"></td>
                                <td><?php echo $agendamento['usuario_nome']; ?></td>
                                <td><?php echo $agendamento['usuario_email']; ?></td>
                                <td><?php echo $agendamento['servico']; ?></td>
                                <td><?php echo $agendamento['data']; ?></td>
                                <td><?php echo $agendamento['horario']; ?></td>
                                <td>
                                    <form method="POST" action="painel.php" style="display: inline-block;">
                                        <input type="hidden" name="agendamento_id" value="<?php echo $agendamento['agendamento_id']; ?>">
                                        <input type="hidden" name="action" value="delete_agendamento">
                                        <button type="submit">Excluir</button>
                                    </form>
                                    <form method="POST" action="painel.php" style="display: inline-block;">
                                        <input type="hidden" name="agendamento_id" value="<?php echo $agendamento['agendamento_id']; ?>">
                                        <input type="text" name="servico" value="<?php echo $agendamento['servico']; ?>" required>
                                        <input type="date" name="data" value="<?php echo $agendamento['data']; ?>" required>
                                        <input type="text" name="horario" value="<?php echo $agendamento['horario']; ?>" required>
                                        <input type="hidden" name="action" value="edit_agendamento">
                                        <button type="submit">Salvar Alterações</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile;
                    endif; ?>
                <?php endforeach; ?>
            </table>
        </form>
    </div>

    <!-- Conteúdo da Aba Usuários -->
    <div class="tab-content" id="tab-usuarios">
        <h2>Usuários</h2>
        <table>
            <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Ações</th>
            </tr>
            <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $usuario['nome']; ?></td>
                    <td><?php echo $usuario['email']; ?></td>
                    <td>
                        <form method="POST" action="painel.php" style="display: inline-block;">
                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                            <input type="text" name="nome" value="<?php echo $usuario['nome']; ?>" required>
                            <input type="email" name="email" value="<?php echo $usuario['email']; ?>" required>
                            <input type="password" name="senha" placeholder="Nova senha (deixe em branco para não alterar)">
                            <input type="hidden" name="action" value="edit_user">
                            <button type="submit">Salvar Alterações</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

</div>

<script>
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    const selectAllCheckbox = document.getElementById('select-all');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById('tab-' + button.getAttribute('data-tab')).classList.add('active');
        });
    });

    // Selecionar ou desmarcar todos os checkboxes
    selectAllCheckbox.addEventListener('click', function () {
        const checkboxes = document.querySelectorAll('input[name="selected_agendamentos[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });

    // Definir a primeira aba como ativa por padrão
    tabButtons[0].classList.add('active');
    document.getElementById('tab-dashboard').classList.add('active');
</script>

</body>
</html>
