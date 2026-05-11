<?php
include_once('config.php');
include_once('Controllers/ResourceController.php');
include_once('Models/Resource.php');

$rc = new ResourceController();
$res = new ResourceItem(1, 'planning', 'Test Resource for Event 4', 'This is a test resource description.', 'active', 4);
if ($rc->addResource($res)) {
    echo "Resource added successfully to event 4.\n";
} else {
    echo "Failed to add resource.\n";
}
