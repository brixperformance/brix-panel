<?php
require_once __DIR__ . '/../Execute.php';

class MasterBrandView
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    // --- BRAND: count rows (like countCatalogData) ---
    public function countBrandData($search = '')
    {
        $sql = "
            SELECT COUNT(*)
            FROM ms_brand AS mbr
            WHERE 1=1
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (mbr.mbr_name LIKE ? OR mbr.mbr_file_name LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return $this->exec->executeSelect($sql, $params, 'one');
    }

    // --- BRAND: paginated list (mirrors getCatalogData signature/shape) ---
    public function getBrandData($search = '', $offset = 0, $limit = 10)
    {
        $sql = "
            SELECT
                mbr.mbr_id,
                mbr.mbr_name,
                mbr.mbr_file_name,
                mbr.mbr_flag
            FROM ms_brand AS mbr
            WHERE 1=1
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (mbr.mbr_name LIKE ? OR mbr.mbr_file_name LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY mbr.mbr_name ASC LIMIT " . (int)$offset . ", " . (int)$limit;

        return $this->exec->executeSelect($sql, $params, 'all');
    }

    public function getBrandRowById($id)
    {
        $sql = "
            SELECT 
                mbr.mbr_name AS type,
                mbr.mbr_file_name AS year
            FROM 
                ms_brand AS mbr
            WHERE 
                mbr.mbr_id = :id 
        ";
        return $this->exec->executeSelect($sql, [':id' => $id], 'row');
    }
}
