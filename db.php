<?php
try {
    $db = new PDO("mysql:host=localhost;dbname=sql_anonchat_com", "sql_anonchat_com", "NY46ZRTR90wwZZ");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Databaseforbindelse fejlede: " . $e->getMessage());
}
?>
