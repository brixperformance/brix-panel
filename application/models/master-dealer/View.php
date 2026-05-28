<?php
require_once __DIR__ . '/../Execute.php';

class MasterDealerView
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    // --- DEALER: count rows (like countCatalogData) ---
    public function countDealerData($search = '')
    {
        $sql = "
            SELECT COUNT(*)
            FROM ms_dealer AS msd
            WHERE 1=1
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (msd.msd_name LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
        }

        return $this->exec->executeSelect($sql, $params, 'one');
    }

    // --- DEALER: paginated list (mirrors getCatalogData signature/shape) ---
    public function getDealerData($search = '', $offset = 0, $limit = 10)
    {
        $sql = "
            SELECT
                msd.msd_code AS dealer_code,
                msd.msd_name AS dealer_name,
                CASE 
                    WHEN msd.msd_type = 'R' THEN 'Regular'
                    WHEN msd.msd_type = 'O' THEN 'Online'
                    ELSE '-'
                END AS dealer_type,
                msd.msd_contact AS dealer_contact,
                msd.msd_address AS dealer_address,
                DATE_FORMAT(msd.msd_join_date, '%d %b %Y') AS dealer_join_date,
                msd.msd_status AS dealer_status
            FROM ms_dealer AS msd
            WHERE 1=1
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (msd.msd_name LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
        }

        $sql .= " ORDER BY msd.msd_name ASC LIMIT " . (int)$offset . ", " . (int)$limit;

        return $this->exec->executeSelect($sql, $params, 'all');
    }

    public function getDealerRowById($id)
    {
        $sql = "
            SELECT 
                msd.msd_code      AS dealer_code,
                msd.msd_name      AS dealer_name,
                msd.msd_contact   AS dealer_contact,
                msd.msd_address   AS dealer_address,
                msd.msd_type      AS dealer_type,
                msd.msd_map_embed AS dealer_map,
                DATE_FORMAT(msd.msd_join_date, '%Y-%m-%d') AS dealer_join_date,
                msd.msd_status    AS dealer_status
            FROM 
                ms_dealer msd
            WHERE 
                msd.msd_code = :id
            LIMIT 1
        ";
        return $this->exec->executeSelect($sql, [':id' => $id], 'row');
    }

    public function getActiveDealerOptions()
    {
        $sql = "
            SELECT
                msd.msd_code AS dealer_code,
                msd.msd_name AS dealer_name,
                msd.msd_contact AS dealer_contact,
                msd.msd_address AS dealer_address
            FROM ms_dealer AS msd
            WHERE msd.msd_status = 'Y'
            ORDER BY msd.msd_name ASC
        ";

        return $this->exec->executeSelect($sql, [], 'all');
    }

    public function getAllIslands()
    {
        $sql = "
            SELECT 
                msi_code, 
                msi_name 
            FROM 
                ms_island 
            WHERE 
                msi_active_status = 'Y' 
            ORDER BY 
                msi_name ASC";
        return $this->exec->executeSelect($sql, [], 'all')['data'] ?? [];
    }

    public function getProvincesByIsland($islandCode)
    {
        $sql = "
            SELECT msp_code, msp_name
            FROM ms_province
            WHERE msp_msi_code = ?
            ORDER BY msp_name ASC
        ";
        return $this->exec->executeSelect($sql, [$islandCode], 'all')['data'] ?? [];
    }
}
