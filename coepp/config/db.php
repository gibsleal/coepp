<?php
// config/db.php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'coepp');
if ($mysqli->connect_errno) {
  die('Falha na conexão: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

$GLOBALS['mysqli'] = $mysqli; // compat
$GLOBALS['conn']   = $mysqli;