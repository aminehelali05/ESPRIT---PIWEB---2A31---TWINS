<?php
if (isset($_GET['id'])) {
	header('Location: user_edit.php?id=' . urlencode((string) $_GET['id']));
} else {
	header('Location: user_list.php');
}
exit;
