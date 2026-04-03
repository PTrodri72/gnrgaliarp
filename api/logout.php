<?php
session_start();
session_unset(); // Remove todas as variáveis da sessão
session_destroy(); // Destrói a sessão no servidor
header("Location: index.php?aba=inicio"); // Redireciona para a página inicial (pública)
exit();
?>