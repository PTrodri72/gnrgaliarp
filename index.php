<?php
session_start();
date_default_timezone_set('Europe/Lisbon');
$base_dados = 'dados.php';

// --- MOTOR DE DADOS GNR ---
function salvar_tudo($g, $m, $p, $v, $c, $pa, $dis, $ex, $hist, $comun, $logs, $file) {
    $data = "<?php\n"
          . "\$guardas=" . var_export($g, true) . ";\n"
          . "\$mensagem_comando=" . var_export($m, true) . ";\n"
          . "\$lista_procurados=" . var_export($p, true) . ";\n"
          . "\$veiculos_apreendidos=" . var_export($v, true) . ";\n"
          . "\$candidaturas=" . var_export($c, true) . ";\n"
          . "\$patrulhas=" . var_export($pa, true) . ";\n"
          . "\$disciplinares=" . var_export($dis, true) . ";\n"
          . "\$exames_internos=" . var_export($ex, true) . ";\n"
          . "\$historico_promocoes=" . var_export($hist, true) . ";\n"
          . "\$comunicados=" . var_export($comun, true) . ";\n"
          . "\$logs_comando=" . var_export($logs, true) . ";\n"
          . "?>";
    file_put_contents($file, $data);
}

if (!file_exists($base_dados)) {
    $inicial_g = ['Igor Rodrigues' => ['pass' => 'admin123', 'patente_nome' => 'Comandante Geral', 'patente_id' => 'T-01']];
    salvar_tudo($inicial_g, "GNR em Prontidão.", [], [], [], [], [], [], [], [], [], $base_dados);
}
include($base_dados);

$user_logado = $_SESSION['logado'] ?? null;
$patente_logado = $user_logado ? ($guardas[$user_logado]['patente_nome'] ?? '') : '';
$aba = $_GET['aba'] ?? 'inicio';

// --- PERMISSÕES ---
$alto_comando = ["Comandante Geral", "Tenente General", "Major General", "Brigadeiro General"];
$oficiais = array_merge($alto_comando, ["Coronel", "Tenente Coronel", "Major", "Capitão", "Tenente"]);
$e_alto_comando = in_array($patente_logado, $alto_comando);
$e_comando = in_array($patente_logado, $oficiais);

$postos = ["Comandante Geral", "Tenente General", "Major General", "Brigadeiro General", "Coronel", "Tenente Coronel", "Major", "Capitão", "Tenente", "Alferes", "Aspirante", "Sargento Mor", "Sargento Chefe", "Sargento Ajudante", "1° Sargento", "2° Sargento", "Furriel", "Cabo Mor", "Cabo Chefe", "Cabo", "Guarda Principal", "Guarda", "Cadete GNR"];

// --- LÓGICA CORE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['login'])) {
        if (isset($guardas[$_POST['user']]) && $guardas[$_POST['user']]['pass'] === $_POST['pass']) {
            $_SESSION['logado'] = $_POST['user']; header("Location: index.php"); exit();
        }
    }

    if ($user_logado) {
        $registrar_log = function($msg) use (&$logs_comando, $user_logado) {
            $logs_comando[] = ['d' => date('d/m H:i'), 'u' => $user_logado, 'm' => $msg];
        };

        if (isset($_POST['add_militar']) && $e_alto_comando) {
            $guardas[$_POST['n_nome']] = ['pass' => $_POST['n_pass'], 'patente_nome' => $_POST['n_posto'], 'patente_id' => $_POST['n_id']];
            $registrar_log("Recrutou novo militar: ".$_POST['n_nome']);
        }

        if (isset($_POST['promover_militar']) && $e_comando) {
            $old = $guardas[$_POST['m_alvo']]['patente_nome'];
            $guardas[$_POST['m_alvo']]['patente_nome'] = $_POST['novo_posto'];
            $guardas[$_POST['m_alvo']]['patente_id'] = $_POST['novo_id'];
            $registrar_log("Alterou patente de ".$_POST['m_alvo']." ($old -> ".$_POST['novo_posto'].")");
        }

        if (isset($_POST['demitir_militar']) && $e_comando) {
            $alvo = $_POST['m_alvo'];
            $disciplinares[] = [
                'n' => $alvo, 
                'p' => $guardas[$alvo]['patente_nome'], 
                'm' => $_POST['motivo_baixa'], 
                'd' => date('d/m/Y H:i'),
                'por' => $user_logado
            ];
            unset($guardas[$alvo]);
            $registrar_log("EXPULSOU o militar: $alvo por: ".$_POST['motivo_baixa']);
        }

        if (isset($_POST['ini_ptr'])) {
            $mils = array_filter([$user_logado, $_POST['g2'], $_POST['g3'], $_POST['g4']]);
            $patrulhas[] = ['mils'=>implode(", ", $mils), 'v'=>$_POST['v'], 'st'=>'EM CURSO', 'h_ini'=>time(), 'h_fim'=>null, 'abords'=>0, 'assaltos'=>0, 'detidos'=>0, 'apreendidos'=>0];
        }

        if (isset($_POST['salvar_stats']) || isset($_POST['fim_ptr'])) {
            $id = $_POST['ptr_id'];
            $patrulhas[$id]['abords'] = (int)$_POST['n_ab'];
            $patrulhas[$id]['assaltos'] = (int)$_POST['n_as'];
            $patrulhas[$id]['detidos'] = (int)$_POST['n_de'];
            $patrulhas[$id]['apreendidos'] = (int)$_POST['n_ap'];
            if (isset($_POST['fim_ptr'])) {
                $patrulhas[$id]['st'] = 'FINALIZADA';
                $patrulhas[$id]['h_fim'] = time();
            }
        }

        if (isset($_POST['reabrir_ptr']) && $e_alto_comando) {
            $patrulhas[$_POST['ptr_id']]['st'] = 'EM CURSO';
            $registrar_log("Reabriu a patrulha ID: ".$_POST['ptr_id']);
        }

        if (isset($_POST['add_comunicado']) && $e_comando) {
            $comunicados[] = ['titulo' => $_POST['com_tit'], 'texto' => $_POST['com_txt'], 'autor' => $user_logado, 'data' => date('d/m/Y H:i')];
            $registrar_log("Publicou novo comunicado.");
        }

        if (isset($_POST['lancar_exame']) && $e_comando) {
            $exames_internos[] = ['militar' => $_POST['ex_mil'], 'tipo' => $_POST['ex_tipo'], 'nota' => $_POST['ex_nota'], 'obs' => $_POST['ex_obs'], 'oficial' => $user_logado, 'data'=>date('d/m')];
        }
    }

    if (isset($_POST['btn_enviar_cand'])) { $candidaturas[] = ['nome'=>$_POST['c_nome'], 'disc'=>$_POST['c_disc'], 'exp'=>$_POST['c_exp'], 'data'=>date('d/m/Y')]; }

    salvar_tudo($guardas, $mensagem_comando, $lista_procurados, $veiculos_apreendidos, $candidaturas, $patrulhas, $disciplinares, $exames_internos, $historico_promocoes, $comunicados, $logs_comando, $base_dados);
    header("Location: index.php?aba=$aba"); exit();
}

// DELETE GET
if ($user_logado && isset($_GET['del_id'])) {
    $t = $_GET['tipo']; $id = $_GET['del_id'];
    if($t=='cand' && $e_comando) unset($candidaturas[$id]);
    if($t=='ptr' && $e_alto_comando) unset($patrulhas[$id]);
    if($t=='com' && $e_comando) unset($comunicados[$id]);
    if($t=='disc' && $e_alto_comando) unset($disciplinares[$id]);
    salvar_tudo($guardas, $mensagem_comando, $lista_procurados, $veiculos_apreendidos, $candidaturas, $patrulhas, $disciplinares, $exames_internos, $historico_promocoes, $comunicados, $logs_comando, $base_dados);
    header("Location: index.php?aba=$aba"); exit();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>GNR | PORTAL SOCIAL</title>
    <link rel="icon" href="https://www.gnr.pt/layout/gnr_frontcontent_1.png">
    <style>
        :root { --gnr: #1B4D3E; --gold: #c5a021; --dark: #0f1a14; --red: #a93226; --glass: rgba(255, 255, 255, 0.95); }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #e8ece9; color: #333; }
        header { background: linear-gradient(135deg, var(--gnr), #2c3e50); color: white; text-align: center; padding: 30px; border-bottom: 4px solid var(--gold); }
        nav { background: var(--dark); display: flex; justify-content: center; position: sticky; top:0; z-index:999; box-shadow: 0 2px 10px rgba(0,0,0,0.3); flex-wrap: wrap; }
        nav a { color: white; padding: 16px 22px; text-decoration: none; font-weight: bold; font-size: 11px; text-transform: uppercase; transition: 0.3s; letter-spacing: 1px; }
        nav a:hover { background: var(--gnr); color: var(--gold); }
        .container { max-width: 1300px; margin: 25px auto; padding: 0 15px; }
        .section { background: var(--glass); padding: 25px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card { border: 1px solid #d1d8d1; padding: 18px; margin-bottom: 15px; border-left: 6px solid var(--gnr); border-radius: 6px; background: white; transition: 0.2s; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .btn { background: var(--gnr); color: white; border: none; padding: 10px 18px; cursor: pointer; font-weight: bold; border-radius: 5px; font-size: 10px; text-transform: uppercase; }
        .btn-red { background: var(--red); } .btn-gold { background: var(--gold); color: #000; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
        th, td { padding: 14px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f4f6f4; color: var(--gnr); font-size: 11px; text-transform: uppercase; }
        input, select, textarea { width: 100%; padding: 11px; margin: 6px 0; border: 1px solid #bdc3c7; border-radius: 5px; background: #fdfdfd; }
        .badge { background: var(--gold); padding: 4px 8px; border-radius: 4px; font-weight: bold; color: var(--gnr); font-size: 10px; }
        .badge-red { background: var(--red); color: white; }
    </style>
</head>
<body>

<header>
    <img src="https://www.gnr.pt/layout/gnr_frontcontent_1.png" width="85"><br>
    <span style="font-size: 26px; font-weight: 900; letter-spacing: 3px;">GUARDA NACIONAL REPUBLICANA</span><br>
    <span style="color: var(--gold); font-weight: bold;">PELA LEI E PELA GREI</span>
</header>

<nav>
    <a href="?aba=inicio">🏠 Home</a>
    <a href="?aba=equipa">👥 Efetivos</a>
    <a href="?aba=comunicados">📢 Comunicados</a>
    <?php if(!$user_logado): ?>
        <a href="?aba=candidatura">📝 Recrutamento</a>
        <a href="?aba=login"style="background:var(--gold); color:black;">🔑 Área Militar</a>
    <?php else: ?>
        <a href="?aba=patrulha">🚔 Patrulha</a>
        <a href="?aba=exames">🎓 Exames</a>
        <?php if($e_comando): ?>
            <a href="?aba=gestao_cand" style="color:var(--gold)">📥 Candidaturas</a>
            <a href="?aba=logs" style="color:#3498db">📜 Logs</a>
        <?php endif; ?>
        <a href="logout.php" style="color:var(--red)">🚪 Sair</a>
    <?php endif; ?>
</nav>

<div class="container">
    
<div class="container">

    <?php if($aba == 'inicio'): ?>
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
            
            <div class="section">
                <h2>Escritório Virtual da GNR</h2>
                <p>Bem-vindo, <b><?php echo $user_logado ?: 'Visitante'; ?></b>. Este é o sistema central de coordenação da Guarda Nacional Republicana.</p>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:20px;">
                    <div class="card" style="border-left-color: var(--gold);">
                        <h4>Efetivos em Serviço</h4>
                        <span style="font-size:24px; font-weight:bold;"><?php echo count($guardas); ?></span> Militares
                    </div>
                    <div class="card" style="border-left-color: var(--gnr);">
                        <h4>Patrulhas Ativas</h4>
                        <span style="font-size:24px; font-weight:bold;">
                            <?php 
                            $ativas = 0;
                            foreach($patrulhas ?? [] as $p) if($p['st'] == 'EM CURSO') $ativas++;
                            echo $ativas;
                            ?>
                        </span> Unidades
                    </div>
                </div>

                <div class="card" style="margin-top:20px; background: #f9f9f9;">
                    <h4>📌 Mensagem do Comando Geral</h4>
                    <p><i>"<?php echo $mensagem_comando ?? 'Sem mensagens novas do comando.'; ?>"</i></p>
                </div>
            </div>

            <div class="section">
                <h3>📜 Atividade Recente</h3>
                <div style="font-size:11px; max-height:400px; overflow-y:auto;">
                    <?php if(!empty($logs_comando)): ?>
                        <?php foreach(array_slice(array_reverse($logs_comando), 0, 8) as $l): ?>
                            <div style="padding:8px 0; border-bottom:1px solid #eee;">
                                <span style="color:var(--gnr); font-weight:bold;">[<?php echo $l['d']; ?>]</span><br>
                                <?php echo $l['m']; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Nenhuma atividade registada.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif($aba == 'comunicados'): ?>
        <div class="section">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>📢 Comunicados e Ordens de Serviço</h2>
                <?php if($e_comando): ?>
                    <button onclick="document.getElementById('novo_com').style.display='block'" class="btn">Novo Comunicado</button>
                <?php endif; ?>
            </div>

            <?php if($e_comando): ?>
                <div id="novo_com" class="card" style="display:none; margin-top:15px; border-left-color: var(--gold);">
                    <form method="POST">
                        <input type="text" name="com_tit" placeholder="Título do Comunicado" required>
                        <textarea name="com_txt" rows="5" placeholder="Escreve aqui a ordem ou comunicado..." required></textarea>
                        <div style="display:flex; gap:10px;">
                            <button name="add_comunicado" class="btn">PUBLICAR</button>
                            <button type="button" onclick="document.getElementById('novo_com').style.display='none'" class="btn btn-red">CANCELAR</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div style="margin-top:20px;">
                <?php if(!empty($comunicados)): ?>
                    <?php foreach(array_reverse($comunicados, true) as $id_c => $comun): ?>
                        <div class="card">
                            <div style="display:flex; justify-content:space-between;">
                                <h3 style="margin:0; color:var(--gnr);"><?php echo htmlspecialchars($comun['titulo']); ?></h3>
                                <small><?php echo $comun['data']; ?></small>
                            </div>
                            <hr style="border:0; border-top:1px solid #eee; margin:10px 0;">
                            <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($comun['texto']); ?></p>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                                <span class="badge">Autor: <?php echo htmlspecialchars($comun['autor']); ?></span>
                                <?php if($e_comando): ?>
                                    <a href="?aba=comunicados&tipo=com&del_id=<?php echo $id_c; ?>" style="color:var(--red); font-size:11px; text-decoration:none;">Apagar</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card" style="text-align:center; padding:40px;">
                        <p>Não existem comunicados oficiais no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>
<div class="container">

    <?php if($aba == 'equipa'): ?>
        <div class="section">
            <h2>👥 Efetivos Ativos</h2>
            
            <?php if($e_alto_comando): ?>
                <div class="card" style="background:#fcf3cf;">
                    <h3>📂 Registro de Novo Militar</h3>
                    <form method="POST" style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap:10px;">
                        <input type="text" name="n_nome" placeholder="Nome Completo" required>
                        <input type="text" name="n_pass" placeholder="Senha" required>
                        <input type="text" name="n_id" placeholder="ID (ex: T-01)">
                        <select name="n_posto"><?php foreach($postos as $p) echo "<option value='$p'>$p</option>"; ?></select>
                        <button name="add_militar" class="btn">ADICIONAR</button>
                    </form>
                </div>
            <?php endif; ?>

            <table>
                <tr>
                    <th>Posto</th>
                    <th>Nome do Militar</th>
                    <?php if($e_comando) echo "<th>Ações de Comando</th>"; ?>
                </tr>
                <?php foreach($guardas as $nome => $d): ?>
                <tr>
                    <td><span class="badge"><?php echo htmlspecialchars($d['patente_id'] ?? ''); ?></span> <?php echo htmlspecialchars($d['patente_nome'] ?? ''); ?></td>
                    <td><b><?php echo htmlspecialchars($nome); ?></b></td>
                    <?php if($e_comando && $nome != $user_logado): ?>
                    <td>
                        <form method="POST" style="display:flex; gap:5px; align-items: center;">
                            <input type="hidden" name="m_alvo" value="<?php echo htmlspecialchars($nome); ?>">
                            <select name="novo_posto" style="font-size:9px; height: 30px;"><?php foreach($postos as $p) echo "<option value='$p'>$p</option>"; ?></select>
                            <input type="text" name="novo_id" placeholder="ID" style="width:40px; height: 30px; padding: 2px;">
                            <button name="promover_militar" class="btn" style="padding:5px;">✓</button>
                            
                            <select name="motivo_baixa" style="font-size:9px; height: 30px;">
                                <option value="Processo Disciplinar">Processo Disciplinar</option>
                                <option value="Advertências Maximas">Advertências Maximas</option>
                                <option value="Banido do Servidor">Banido do Servidor</option>
                                <option value="Saiu da GNR">Saiu da GNR</option>
                            </select>
                            <button name="demitir_militar" class="btn btn-red" style="padding:5px;">EXPULSAR</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </table>

            <hr>
            <h2 style="color:var(--red); margin-top:40px;">🚫 Histórico de Baixas (Expulsões)</h2>
            <table>
                <tr><th>Data</th><th>Ex-Militar</th><th>Último Posto</th><th>Motivo</th><th>Responsável</th><?php if($e_alto_comando) echo "<th>Ação</th>"; ?></tr>
                <?php foreach(array_reverse($disciplinares ?? [], true) as $id_d => $disc): ?>
                <tr>
                    <td><?php echo $disc['d']; ?></td>
                    <td><b><?php echo $disc['n']; ?></b></td>
                    <td><?php echo $disc['p']; ?></td>
                    <td><span class="badge-red"><?php echo $disc['m']; ?></span></td>
                    <td><small><?php echo $disc['por'] ?? 'N/A'; ?></small></td>
                    <?php if($e_alto_comando): ?>
                        <td><a href="?aba=equipa&tipo=disc&del_id=<?php echo $id_d; ?>" style="color:red; text-decoration:none;">[Limpar]</a></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($disciplinares)) echo "<tr><td colspan='6' style='text-align:center;'>Nenhum registo de expulsão.</td></tr>"; ?>
            </table>
        </div>

    <?php elseif($aba == 'patrulha' && $user_logado): ?>
        <div class="section">
            <h2>🚔 Gestão de Patrulhas e Horas de Serviço</h2>
            <form method="POST" class="card">
                <h4>Iniciar Nova Guarnição</h4>
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px;">
                    <input type="text" value="<?php echo $user_logado; ?>" readonly>
                    <select name="g2" id="g2" onchange="filterMils()"><option value="">-- Militar 2 --</option><?php foreach($guardas as $n=>$d) if($n!=$user_logado) echo "<option value='$n'>$n</option>"; ?></select>
                    <select name="g3" id="g3" onchange="filterMils()"><option value="">-- Militar 3 --</option><?php foreach($guardas as $n=>$d) if($n!=$user_logado) echo "<option value='$n'>$n</option>"; ?></select>
                    <select name="g4" id="g4" onchange="filterMils()"><option value="">-- Militar 4 --</option><?php foreach($guardas as $n=>$d) if($n!=$user_logado) echo "<option value='$n'>$n</option>"; ?></select>
                </div>
                <select name="v"><option>Viatura Patrulha (Skoda)</option><option>Unidade de Intervenção (Hilux)</option><option>Divisão de Trânsito</option><option>GIOE (Operações Especiais)</option></select>
                <button name="ini_ptr" class="btn" style="width:100%; margin-top:10px; padding:15px;">ABRIR TURNO OPERACIONAL</button>
            </form>

            <?php foreach(array_reverse($patrulhas ?? [], true) as $idx => $p): 
                $f = ($p['st'] == 'FINALIZADA');
                $tempo = $f ? round(($p['h_fim'] - $p['h_ini'])/60) : round((time() - $p['h_ini'])/60); ?>
                <div class="card" style="<?php echo $f ? 'background:#f4f4f4;' : 'border-left-color:#27ae60;'; ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <b>Equipa: <?php echo $p['mils']; ?></b><br>
                            <small>Início: <?php echo date('H:i:s', $p['h_ini']); ?> | <b>Duração: <?php echo $tempo; ?> min</b></small>
                        </div>
                        <div>
                            <span class="badge" style="background:<?php echo $f?'#7f8c8d':'#27ae60'; ?>; color:white;"><?php echo $p['st']; ?></span>
                            <?php if($e_alto_comando): ?><a href="?aba=patrulha&tipo=ptr&del_id=<?php echo $idx; ?>" style="color:red; margin-left:10px;">X</a><?php endif; ?>
                        </div>
                    </div>
                    <form method="POST" style="margin-top:15px;">
                        <input type="hidden" name="ptr_id" value="<?php echo $idx; ?>">
                        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px;">
                            <div>Abord: <input type="number" name="n_ab" value="<?php echo $p['abords']; ?>" <?php echo $f?'disabled':''; ?>></div>
                            <div>Assalt: <input type="number" name="n_as" value="<?php echo $p['assaltos']; ?>" <?php echo $f?'disabled':''; ?>></div>
                            <div>Detidos: <input type="number" name="n_de" value="<?php echo $p['detidos']; ?>" <?php echo $f?'disabled':''; ?>></div>
                            <div>Apreen: <input type="number" name="n_ap" value="<?php echo $p['apreendidos']; ?>" <?php echo $f?'disabled':''; ?>></div>
                        </div>
                        <?php if(!$f): ?>
                            <button name="salvar_stats" class="btn">ATUALIZAR DADOS</button>
                            <button name="fim_ptr" class="btn btn-red">FECHAR TURNO</button>
                        <?php elseif($e_alto_comando): ?>
                            <button name="reabrir_ptr" class="btn btn-gold">REABRIR REGISTO</button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif($aba == 'logs' && $e_comando): ?>
        <div class="section">
            <h2>📜 Auditoria de Comando (Logs de Sistema)</h2>
            <div class="card" style="max-height: 600px; overflow-y: auto; background:#fafafa;">
                <?php foreach(array_reverse($logs_comando ?? []) as $log): ?>
                    <div class="log-line">
                        <span style="color:#7f8c8d;">[<?php echo $log['d']; ?>]</span> 
                        <span style="color:var(--gnr); font-weight:bold;"><?php echo $log['u']; ?>:</span> 
                        <span><?php echo $log['m']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php elseif($aba == 'exames' && $user_logado): ?>
        <div class="section">
            <h2>🎓 Formação e Qualificações</h2>
            <?php if($e_comando): ?>
                <form method="POST" class="card">
                    <h4>Lançar Avaliação de Militar</h4>
                    <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:10px;">
                        <select name="ex_mil"><?php foreach($guardas as $n=>$d) echo "<option value='$n'>$n</option>"; ?></select>
                        <select name="ex_tipo"><option>Tiro e Balística</option><option>Condução de Emergência</option><option>Código Penal</option><option>Protocolos Rádio</option></select>
                        <input type="number" name="ex_nota" placeholder="Nota 0-20" required>
                    </div>
                    <textarea name="ex_obs" placeholder="Observações do instrutor..."></textarea>
                    <button name="lancar_exame" class="btn">GRAVAR NO REGISTO</button>
                </form>
            <?php endif; ?>
            <table>
                <tr><th>Data</th><th>Militar</th><th>Exame</th><th>Nota</th><th>Instrutor</th></tr>
                <?php foreach(array_reverse($exames_internos ?? []) as $e): ?>
                    <tr>
                        <td><?php echo $e['data'] ?? 'N/A'; ?></td>
                        <td><b><?php echo $e['militar']; ?></b></td>
                        <td><?php echo $e['tipo']; ?></td>
                        <td><span class="badge" style="background:<?php echo $e['nota']>=10?'#27ae60':'#c0392b'; ?>; color:white;"><?php echo $e['nota']; ?>/20</span></td>
                        <td><?php echo $e['oficial']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif($aba == 'login'): ?>
        <div style="max-width:400px; margin: 80px auto;">
            <form method="POST" class="section">
                <h2 style="text-align:center; color:var(--gnr);">ACESSO MILITAR</h2>
                <label>Utilizador:</label>
                <input type="text" name="user" required>
                <label>Palavra-Passe:</label>
                <input type="password" name="pass" required>
                <button name="login" class="btn" style="width:100%; padding:15px; margin-top:10px;">AUTENTICAR</button>
            </form>
        </div>

    <?php elseif($aba == 'candidatura'): ?>
        <div class="section">
            <h2>📝 Formulário de Ingresso na GNR</h2>
            <form method="POST" class="card">
                <input type="text" name="c_nome" placeholder="Nome Completo (RP)" required>
                <input type="text" name="c_disc" placeholder="Discord Tag (ex: User#0000)" required>
                <textarea name="c_exp" rows="6" placeholder="Por que deseja entrar na GNR? Liste as suas experiências anteriores em outras cidades ou corporações." required></textarea>
                <button name="btn_enviar_cand" class="btn" style="width:100%; padding:15px;">SUBMETER PARA O ESTADO-MAIOR</button>
            </form>
        </div>

    <?php endif; ?>
</div>

<footer style="text-align:center; padding: 30px; color:#7f8c8d; font-size:11px;">
    &copy; 2026 Guarda Nacional Republicana - Ministério da Administração Interna - PUR</footer>

</body>
</html>