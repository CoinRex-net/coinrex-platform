<?php
require_once dirname(__DIR__,2).'/includes/config.php';
header('Content-Type: application/json; charset=utf-8');
try{
 if($_SERVER['REQUEST_METHOD']!=='POST'||!isLoggedIn())throw new RuntimeException('Authentication required.');
 if(!validateAppCsrfToken((string)($_POST['csrf_token']??''))){http_response_code(403);throw new RuntimeException('Invalid CSRF token.');}
 engagementDismissAnnouncement((int)$_SESSION['user_id'],(int)($_POST['announcement_id']??0),!empty($_POST['forever']));
 echo json_encode(['success'=>true]);
}catch(Throwable $e){if(http_response_code()<400)http_response_code(422);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
