<?php
require_once __DIR__ . '/../admin/config.php';


if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: access-denied.php");
    exit;
}
?>
