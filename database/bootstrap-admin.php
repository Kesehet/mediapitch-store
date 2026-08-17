<?php

declare(strict_types=1);

use MediaPitch\Core\Database;
use PDO;

require dirname(__DIR__).'/src/bootstrap.php';

$name=trim((string)env('BOOTSTRAP_ADMIN_NAME','MediaPitch Admin')) ?: 'MediaPitch Admin';
$email=strtolower(trim((string)env('BOOTSTRAP_ADMIN_EMAIL','admin@mediapitch.in')));
$password=(string)env('BOOTSTRAP_ADMIN_PASSWORD','');

if($password===''){
    fwrite(STDOUT,"BOOTSTRAP_ADMIN_PASSWORD is not set; administrator credential sync skipped.\n");
    exit(0);
}
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
    fwrite(STDERR,"BOOTSTRAP_ADMIN_EMAIL must be a valid email address.\n");
    exit(1);
}
if(strlen($password)<12){
    fwrite(STDERR,"BOOTSTRAP_ADMIN_PASSWORD must be at least 12 characters.\n");
    exit(1);
}

$db=Database::connection();
$db->beginTransaction();
try{
    // Prefer the explicitly configured email. If this is an older installation
    // that only has the historical bootstrap account, reuse that row so changing
    // BOOTSTRAP_ADMIN_EMAIL changes the login instead of silently creating a
    // second administrator.
    $stmt=$db->prepare('SELECT id,email FROM users WHERE email=:email LIMIT 1');
    $stmt->execute(['email'=>$email]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user){
        $legacy=$db->prepare("SELECT id,email FROM users WHERE email='admin@mediapitch.in' AND name='MediaPitch Admin' AND role='administrator' LIMIT 1");
        $legacy->execute();
        $user=$legacy->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $passwordHash=password_hash($password,PASSWORD_DEFAULT);
    if($user){
        $update=$db->prepare(
            "UPDATE users
             SET name=:name,email=:email,password_hash=:password_hash,role='administrator',active=1,
                 failed_login_count=0,last_failed_login_at=NULL
             WHERE id=:id"
        );
        $update->execute([
            'name'=>$name,
            'email'=>$email,
            'password_hash'=>$passwordHash,
            'id'=>(int)$user['id'],
        ]);
        $id=(int)$user['id'];
        $action='updated';
    }else{
        $insert=$db->prepare(
            "INSERT INTO users (name,email,password_hash,role,active,failed_login_count,last_failed_login_at)
             VALUES (:name,:email,:password_hash,'administrator',1,0,NULL)"
        );
        $insert->execute(['name'=>$name,'email'=>$email,'password_hash'=>$passwordHash]);
        $id=(int)$db->lastInsertId();
        $action='created';
    }

    $db->commit();
    fwrite(STDOUT,"Bootstrap administrator {$action}: {$email} (user #{$id}).\n");
    fwrite(STDOUT,"Credentials were synchronized from BOOTSTRAP_ADMIN_EMAIL / BOOTSTRAP_ADMIN_PASSWORD.\n");
}catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();
    fwrite(STDERR,'Bootstrap administrator sync failed: '.$e->getMessage()."\n");
    exit(1);
}
