<?php
require_once __DIR__ . '/../config/session_init.php';
session_unset();
session_destroy();
echo json_encode(['success' => true]); 