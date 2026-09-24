<?php

$host = 'localhost';
$dbname = 'book_it';
$username = 'root';
$password = '[haslo_do_bazy]';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die('Błąd połączenia z bazą danych: ' . $e->getMessage());
}