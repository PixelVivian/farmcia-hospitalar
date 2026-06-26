<?php
/**
 * config/db.php
 * Conexão única com o banco MySQL usando PDO.
 * Qualquer controller ou view que precisar do banco deve usar:
 *      require_once __DIR__ . '/../config/db.php';
 * e então acessar a variável $pdo.
 */

$host   = 'localhost';
$dbname = 'farmacia_hospitalar';
$user   = 'root';      // troque pelo usuário real do seu MySQL/XAMPP
$senha  = '';           // troque pela senha real (no XAMPP padrão geralmente é vazia)
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$opcoes = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lança exceção em erro de SQL
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // retorna arrays associativos
    PDO::ATTR_EMULATE_PREPARES   => false,                    // usa prepared statements reais
];

try {
    $pdo = new PDO($dsn, $user, $senha, $opcoes);
} catch (PDOException $e) {
    // Em produção, nunca exiba $e->getMessage() diretamente ao usuário final.
    die('Erro na conexão com o banco de dados: ' . $e->getMessage());
}
