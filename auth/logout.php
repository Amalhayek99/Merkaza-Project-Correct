<?php
require_once '../sessions.php';

session_destroy();
header('location: ../welcome.php');
?>
