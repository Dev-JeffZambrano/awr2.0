<?php

$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$base = rtrim(str_replace('\\', '/', dirname($script)), '/');
$destino = ($base === '' || $base === '.') ? '/login.php' : $base . '/login.php';

header('Location: ' . $destino, true, 302);
exit;
