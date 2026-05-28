<?php
require_once __DIR__ . '/../Execute.php';

class MasterIslandView
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    public function countIslandData(string $search = ''): array
    {
        $sql    = "SELECT COUNT(*) FROM ms_island WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql   .= " AND (msi_code LIKE ? OR msi_name LIKE ?)";
            $like   = '%' . $search . '%';
            $params = [$like, $like];
        }

        return $this->exec->executeSelect($sql, $params, 'one');
    }

    public function getIslandData(string $search = '', int $offset = 0, int $limit = 15): array
    {
        $sql    = "SELECT msi_code, msi_name, msi_active_status FROM ms_island WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql   .= " AND (msi_code LIKE ? OR msi_name LIKE ?)";
            $like   = '%' . $search . '%';
            $params = [$like, $like];
        }

        $sql .= " ORDER BY msi_code ASC LIMIT " . (int)$offset . ", " . (int)$limit;

        return $this->exec->executeSelect($sql, $params, 'all');
    }

    public function getAllIslands(): array
    {
        return $this->exec->executeSelect(
            "SELECT msi_code, msi_name FROM ms_island WHERE msi_active_status = 'Y' ORDER BY msi_name ASC",
            [],
            'all'
        );
    }
}
