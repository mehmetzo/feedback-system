<?php
require_once __DIR__ . '/../config/db.php';
sessionBaslat();
session_destroy();
header('Location: /admin/index.php');
exit;
