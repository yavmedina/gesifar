<?php
// ============================================
// GESIFAR - Configuración de Base de Datos
// ============================================
// Este archivo es para la base de datos de pruebas
// cuando no se usa, renombrar a database-testing.php

define('DB_HOST', 'localhost'); // localhost si el motor de bbdd está en el mismo server, sino la IP del server
define('DB_USER', 'admin');     // usuario con permisos para escribir en la bd
define('DB_PASS', 'linux14');   // contraseña del usuario
define('DB_NAME', 'farmhos');   // gesifar para produccion, farmhos para testing

class Database {
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                //array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")   //funciona pero daba un error con los emojis
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //esto arregla lo anterior, si hay un error, dice SQL EXECUTION ERRROR #
                //la linea de arriba, en Debian y derivados, ya está incluída en el PHP.ini, si se repetí no pasa nada
                      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                )
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Error de conexión: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}
?>