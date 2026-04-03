<?php
session_start();
date_default_timezone_set('Europe/Lisbon');

// --- CONEXÃO SUPABASE (POSTGRESQL) ---
$host = 'fjizcgagpqiufuvsaffo.supabase.co'; // Host exato do teu print
$db   = 'postgres';
$user = 'postgres';
$pass = 'galiarpgaliar.'; // A senha que definiste ao criar o projeto
$port = '5432';

try {
    // Nota: Para PostgreSQL no Supabase, usamos o driver pgsql
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro Crítico de Ligação: " . $e->getMessage());
}

// --- CARREGAR DADOS GLOBAIS ---
$user_logado = $_SESSION['logado'] ?? null;
$aba = $_GET['aba'] ?? 'inicio';

// Buscar dados do utilizador logado
$militar_auth = null;
if ($user_logado) {
    $stmt = $pdo->prepare("SELECT * FROM guardas WHERE nome = ?");
    $stmt->execute([$user_logado]);
    $militar_auth = $stmt->fetch(PDO::FETCH_ASSOC);
}

$patente_logado = $militar_auth['patente_nome'] ?? '';
$alto_comando = ["Comandante Geral", "Tenente General", "Major General", "Brigadeiro General"];
$oficiais = array_merge($alto_comando, ["Coronel", "Tenente Coronel", "Major", "Capitão", "Tenente"]);
$e_alto_comando = in_array($patente_logado, $alto_comando);
$e_comando = in_array($patente_logado, $oficiais);

$postos = ["Comandante Geral", "Tenente General", "Major General", "Brigadeiro General", "Coronel", "Tenente Coronel", "Major", "Capitão", "Tenente", "Alferes", "Aspirante", "Sargento Mor", "Sargento Chefe", "Sargento Ajudante", "1° Sargento", "2° Sargento", "Furriel", "Cabo Mor", "Cabo Chefe", "Cabo", "Guarda Principal", "Guarda", "Cadete GNR"];

// --- LÓGICA DE PROCESSAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Login
    if (isset($_POST['login'])) {
        $stmt = $pdo->prepare("SELECT * FROM guardas WHERE nome = ? AND pass = ?");
        $stmt->execute([$_POST['user'], $_POST['pass']]);
        if ($stmt->fetch()) {
            $_SESSION['logado'] = $_POST['user'];
            header("Location: index.php"); exit();
        }
    }

    if ($user_logado) {
        // Recrutar
        if (isset($_POST['add_militar']) && $e_alto_comando) {
            $sql = "INSERT INTO guardas (nome, pass, patente_nome, patente_id) VALUES (?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$_POST['n_nome'], $_POST['n_pass'], $_POST['n_posto'], $_POST['n_id']]);
        }

        // Expulsar
        if (isset($_POST['demitir_militar']) && $e_comando) {
            $pdo->prepare("DELETE FROM guardas WHERE nome = ?")->execute([$_POST['m_alvo']]);
        }

        // Iniciar Patrulha
        if (isset($_POST['ini_ptr'])) {
            $mils = implode(", ", array_filter([$user_logado, $_POST['g2'], $_POST['g3']]));
            $sql = "INSERT INTO patrulhas (mils, viatura) VALUES (?, ?)";
            $pdo->prepare($sql)->execute([$mils, $_POST['v']]);
        }

        // Atualizar/Fechar Patrulha
        if (isset($_POST['salvar_stats']) || isset($_POST['fim_ptr'])) {
            $status = isset($_POST['fim_ptr']) ? 'FINALIZADA' : 'EM CURSO';
            $h_fim = isset($_POST['fim_ptr']) ? date('Y-m-d H:i:s') : null;
            $sql = "UPDATE patrulhas SET abords = ?, detidos = ?, status = ?, h_fim = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$_POST['n_ab'], $_POST['n_de'], $status, $h_fim, $_POST['ptr_id']]);
        }
    }
    header("Location: index.php?aba=$aba"); exit();
}

// Logout
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit(); }
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>GNR | SISTEMA OPERACIONAL 2026</title>
    <style>
        :root { --gnr: #1B4D3E; --gold: #c5a021; --dark: #0f1a14; --red: #a93226; }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #e8ece9; color: #333; }
        header { background: linear-gradient(135deg, var(--gnr), #2c3e50); color: white; text-align: center; padding: 20px; border-bottom: 4px solid var(--gold); }
        nav { background: var(--dark); display: flex; justify-content: center; position: sticky; top:0; z-index:99; }
        nav a { color: white; padding: 15px 20px; text-decoration: none; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        nav a:hover { background: var(--gnr); color: var(--gold); }
        .container { max-width: 1100px; margin: 20px auto; padding: 0 15px; }
        .section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .card { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-left: 5px solid var(--gnr); border-radius: 4px; }
        .btn { background: var(--gnr); color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .btn-red { background: var(--red); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8f9fa; color: var(--gnr); }
        input, select { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ccc; border-radius: 4px; }
        .badge { background: var(--gold); padding: 3px 7px; border-radius: 3px; font-size: 10px; font-weight: bold; }
    </style>
</head>
<body>

<header>
    <img src="https://www.gnr.pt/layout/gnr_frontcontent_1.png" width="60"><br>
    <span style="font-size: 20px; font-weight: 900; letter-spacing: 2px;">GUARDA NACIONAL REPUBLICANA</span>
</header>

<nav>
    <a href="?aba=inicio">🏠 Home</a>
    <a href="?aba=equipa">👥 Efetivos</a>
    <?php if(!$user_logado): ?>
        <a href="?aba=login">🔑 Área Militar</a>
    <?php else: ?>
        <a href="?aba=patrulha">🚔 Patrulha</a>
        <a href="?logout=1" style="color:var(--red)">🚪 Sair</a>
    <?php endif; ?>
</nav>

<div class="container">

    <?php if($aba == 'inicio'): ?>
        <div class="section">
            <h2>Bem-vindo, Militar.</h2>
            <p>Sistema Central de Gestão da GNR. Selecione uma opção no menu acima.</p>
            <div class="card" style="border-left-color: var(--gold);">
                <h4>Estado do Contingente</h4>
                <?php 
                $count = $pdo->query("SELECT count(*) FROM guardas")->fetchColumn();
                echo "Atualmente existem <b>$count</b> militares registados no sistema.";
                ?>
            </div>
        </div>

    <?php elseif($aba == 'equipa'): ?>
        <div class="section">
            <h2>👥 Efetivos Ativos</h2>
            <?php if($e_alto_comando): ?>
                <div class="card" style="background:#fffbe6;">
                    <h4>Recrutar Militar</h4>
                    <form method="POST" style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:10px;">
                        <input type="text" name="n_nome" placeholder="Nome" required>
                        <input type="text" name="n_pass" placeholder="Senha" required>
                        <input type="text" name="n_id" placeholder="ID (ex: T-01)">
                        <select name="n_posto"><?php foreach($postos as $p) echo "<option value='$p'>$p</option>"; ?></select>
                        <button name="add_militar" class="btn">ADICIONAR</button>
                    </form>
                </div>
            <?php endif; ?>

            <table>
                <tr><th>Posto</th><th>Nome</th><?php if($e_comando) echo "<th>Ações</th>"; ?></tr>
                <?php 
                $res = $pdo->query("SELECT * FROM guardas ORDER BY patente_id ASC");
                while($row = $res->fetch()): ?>
                <tr>
                    <td><span class="badge"><?= $row['patente_id'] ?></span> <?= $row['patente_nome'] ?></td>
                    <td><b><?= $row['nome'] ?></b></td>
                    <?php if($e_comando && $row['nome'] != $user_logado): ?>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="m_alvo" value="<?= $row['nome'] ?>">
                            <button name="demitir_militar" class="btn btn-red" onclick="return confirm('Expulsar militar?')">EXPULSAR</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

    <?php elseif($aba == 'patrulha' && $user_logado): ?>
        <div class="section">
            <h2>🚔 Gestão de Patrulhas</h2>
            <form method="POST" class="card">
                <h4>Iniciar Turno</h4>
                <div style="display:flex; gap:10px;">
                    <select name="g2"><option value="">-- Militar 2 --</option><?php 
                        $res = $pdo->query("SELECT nome FROM guardas");
                        while($r = $res->fetch()) if($r['nome'] != $user_logado) echo "<option value='".$r['nome']."'>".$r['nome']."</option>";
                    ?></select>
                    <select name="v"><option>Viatura Patrulha</option><option>Unidade Intervenção</option></select>
                    <button name="ini_ptr" class="btn">ABRIR PATRULHA</button>
                </div>
            </form>

            <?php 
            $res = $pdo->query("SELECT * FROM patrulhas ORDER BY id DESC LIMIT 5");
            while($p = $res->fetch()): 
                $final = ($p['status'] == 'FINALIZADA');
            ?>
                <div class="card" style="<?= $final ? 'opacity:0.6' : 'border-left-color:#27ae60' ?>">
                    <b>Equipa: <?= $p['mils'] ?></b> | Status: <?= $p['status'] ?>
                    <form method="POST" style="margin-top:10px; display:flex; gap:10px;">
                        <input type="hidden" name="ptr_id" value="<?= $p['id'] ?>">
                        Abordagens: <input type="number" name="n_ab" value="<?= $p['abords'] ?>" style="width:60px;">
                        Detidos: <input type="number" name="n_de" value="<?= $p['detidos'] ?>" style="width:60px;">
                        <button name="salvar_stats" class="btn">Salvar</button>
                        <?php if(!$final): ?><button name="fim_ptr" class="btn btn-red">Fechar Turno</button><?php endif; ?>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>

    <?php elseif($aba == 'login'): ?>
        <div style="max-width:350px; margin: 60px auto;">
            <form method="POST" class="section">
                <h3 style="text-align:center;">LOGIN MILITAR</h3>
                <input type="text" name="user" placeholder="Nome" required>
                <input type="password" name="pass" placeholder="Senha" required>
                <button name="login" class="btn" style="width:100%; padding:12px; margin-top:10px;">ENTRAR</button>
            </form>
        </div>

    <?php endif; ?>
</div>

</body>
</html>
