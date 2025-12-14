<?php
session_start();
session_destroy();
// Send user back to the public homepage
header('Location: /Ecommerce/index.php');
exit;
