<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use PDO;
use RuntimeException;

final class UserRepository
{
    private const ROLES = ['administrator', 'editor', 'writer', 'seo_manager'];

    public function all(): array
    {
        return Database::connection()->query(
            'SELECT id,name,email,role,active,created_at,updated_at FROM users ORDER BY active DESC,name,email'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(?int $id): ?array
    {
        if (!$id) return null;
        $stmt = Database::connection()->prepare(
            'SELECT id,name,email,role,active,created_at,updated_at FROM users WHERE id=:id LIMIT 1'
        );
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, ?int $id, int $currentUserId): int
    {
        $db=Database::connection();
        $name=trim((string)($data['name']??''));
        $email=strtolower(trim((string)($data['email']??'')));
        $role=(string)($data['role']??'writer');
        $active=!empty($data['active'])?1:0;
        $password=(string)($data['password']??'');

        if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)){
            throw new InvalidArgumentException('A name and valid email address are required.');
        }
        if(!in_array($role,self::ROLES,true)){
            throw new InvalidArgumentException('Invalid user role.');
        }
        if($id === $currentUserId && !$active){
            throw new RuntimeException('You cannot deactivate your own account.');
        }

        if($id){
            $existing=$this->find($id);
            if(!$existing) throw new RuntimeException('User not found.');
            if($existing['role']==='administrator' && ($role!=='administrator' || !$active)){
                $stmt=$db->prepare("SELECT COUNT(*) FROM users WHERE role='administrator' AND active=1 AND id<>:id");
                $stmt->execute(['id'=>$id]);
                if((int)$stmt->fetchColumn()===0){
                    throw new RuntimeException('At least one active administrator must remain.');
                }
            }

            $params=['id'=>$id,'name'=>$name,'email'=>$email,'role'=>$role,'active'=>$active];
            $sql='UPDATE users SET name=:name,email=:email,role=:role,active=:active';
            if($password!==''){
                if(strlen($password)<8) throw new InvalidArgumentException('Passwords must be at least 8 characters.');
                $sql.=',password_hash=:password_hash';
                $params['password_hash']=password_hash($password,PASSWORD_DEFAULT);
            }
            $sql.=' WHERE id=:id';
            $db->prepare($sql)->execute($params);
            return $id;
        }

        if(strlen($password)<8){
            throw new InvalidArgumentException('A password of at least 8 characters is required for new users.');
        }
        $stmt=$db->prepare(
            'INSERT INTO users (name,email,password_hash,role,active) VALUES (:name,:email,:password_hash,:role,:active)'
        );
        $stmt->execute([
            'name'=>$name,
            'email'=>$email,
            'password_hash'=>password_hash($password,PASSWORD_DEFAULT),
            'role'=>$role,
            'active'=>$active,
        ]);
        return (int)$db->lastInsertId();
    }
}
