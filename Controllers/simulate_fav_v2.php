<?php
$_GET['action'] = 'toggle_favorite';
$_POST['event_id'] = 1;
session_start();
$_SESSION['auth_user'] = ['id' => 1];
include 'index.php';
