<?php
function db_connect()
{
    try {
        require "config.php";
        $connection = new mysqli($servername, $username, $password, $dbname);
 
        return $connection;
    } catch (PDOException $e) {
        die($e->getMessage());
    }
}
$db = [
    'host' => '',
    'user' => '',
    'pwd' => '',
    'dbname' => '',
    'port' => 3306,
    'charset' => 'utf8'
];
 