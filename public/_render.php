<?php
$_SESSION = [];
session_id("m02mruhrafju5ksk2nr5p115d7");
session_start();
$_SESSION["user_id"] = 17;
$_SESSION["username"] = "erfankhan";
$_SESSION["email"] = "realerfankhan11@gmail.com";
$_SESSION["role"] = "user";
$_SESSION["level"] = "pro";
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/functions.php";
$_GET["project_id"] = 2;
$_SERVER["REQUEST_METHOD"] = "GET";
$_SESSION["user_id"] = 17;
require __DIR__ . "/submit-review.php";