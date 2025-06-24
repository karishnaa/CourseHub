<?php

$host = 'localhost';
$dbname = 'coursehubdatabase';
$username = 'coursehubusername';
$password = 'coursehubpassword';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}



