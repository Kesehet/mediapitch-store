<?php

declare(strict_types=1);

use MediaPitch\Core\Database;
use PDO;

require dirname(__DIR__).'/src/bootstrap.php';

$password=trim((string)env('BOOTSTRAP_ADMIN_PASSWORD',''));
if($password===''){
    fwrite(STDOUT,"BOOTSTRAP_ADMIN_PASSWORD is not set; bootstrap administrator recovery skipped.\n");
    exit(0);
}
if(strlen($password)<8){
    fwrite(STDERR,"BOOTSTRAP_ADMIN_PASSWORD must be at least 8 characters.\n");
    exit(1);
}

$db=Database::connection();
$stmt=$db->prepare("SELECT id,last_login_at FROM users WHERE email='admin@mediapitch.in' AND name='MediaPitch Admin' AND role='administrator' LIMIT 1");
$stmt->execute();
$user=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$user){
    fwrite(STDOUT,"Bootstrap administrator account was not found; recovery skipped.\n");
    exit(0);
}
if(!empty($user['last_login_at'])){
    fwrite(STDOUT,"Bootstrap administrator has already logged in; deployment will not change its password.\n");
    exit(0);
}

$reset=$db->prepare('UPDATE users SET password_hash=:password_hash,active=1,failed_login_count=0,last_failed_login_at=NULL WHERE id=:id');
$reset->execute(['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>(int)$user['id']]);
fwrite(STDOUT,"Never-used bootstrap administrator password restored from BOOTSTRAP_ADMIN_PASSWORD.\n");
