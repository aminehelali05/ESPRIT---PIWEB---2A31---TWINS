<?php
require 'config.php';
$db = config::getConnexion();
$q = $db->query('SELECT id, title FROM brainstormings LIMIT 5');
print_r($q->fetchAll());
$q = $db->query('SELECT id, first_name FROM users LIMIT 5');
print_r($q->fetchAll());
$q = $db->query('SHOW CREATE TABLE ideas');
print_r($q->fetchAll()[0]);
