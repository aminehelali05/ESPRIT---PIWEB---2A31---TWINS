<?php
include 'config.php';
$db = config::getConnexion();
$db->exec("TRUNCATE TABLE favoris");
echo "Favoris table truncated.";
