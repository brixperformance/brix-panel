<?php
require_once __DIR__ . '/../Execute.php';

class MasterProvinceView
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    public function countProvinceData(string $search = ''): array
    {
        $sql    = "SELECT COUNT(*) FROM ms_province WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql   .= " AND (msp_code LIKE ? OR msp_name LIKE ? OR msp_msi_code LIKE ?)";
            $like   = '%' . $search . '%';
            $params = [$like, $like, $like];
        }

        return $this->exec->executeSelect($sql, $params, 'one');
    }

    public function getProvinceData(string $search = '', int $offset = 0, int $limit = 15): array
    {
        $sql = "
            SELECT
                p.msp_code,
                p.msp_msi_code,
                p.msp_name,
                p.msp_active_status,
                i.msi_name AS msi_name
            FROM ms_province p
            LEFT JOIN ms_island i ON i.msi_code = p.msp_msi_code
            WHERE 1=1
        ";
        $params = [];

        if ($search !== '') {
            $sql   .= " AND (p.msp_code LIKE ? OR p.msp_name LIKE ? OR p.msp_msi_code LIKE ?)";
            $like   = '%' . $search . '%';
            $params = [$like, $like, $like];
        }

        $sql .= " ORDER BY p.msp_msi_code ASC, p.msp_code ASC LIMIT " . (int)$offset . ", " . (int)$limit;

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
