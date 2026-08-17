<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Core\Database;
use RuntimeException;

final class SpecificationAdminActions
{
    public function archive(int $id): void
    {
        $stmt=Database::connection()->prepare('UPDATE specification_definitions SET active=0,filterable=0,comparable=0 WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        $this->ensureExists($id);
    }

    public function restore(int $id): void
    {
        $stmt=Database::connection()->prepare('UPDATE specification_definitions SET active=1 WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        $this->ensureExists($id);
    }

    private function ensureExists(int $id): void
    {
        $stmt=Database::connection()->prepare('SELECT 1 FROM specification_definitions WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        if(!$stmt->fetchColumn()) throw new RuntimeException('Specification not found.');
    }
}
