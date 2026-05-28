<?php
require_once __DIR__ . '/../Execute.php';

class MasterPricelistView
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    public function getAdminByUsername($username)
    {
        $sql = "
            SELECT 
                * 
            FROM 
                ms_admins AS msa 
            WHERE 
                msa.msa_type = 'A'
                AND msa.msa_username = ?
        ";
        return $this->exec->executeSelect($sql, [$username], 'row');
    }

    public function getCatalogData($search = '', $offset = 0, $limit = 10)
    {
        $sql = "
            SELECT 
                msc.mcr_id AS id,
                mbr.mbr_name AS brand,
                mbr.mbr_file_name AS file_name,
                msc.mcr_type AS type,
                msc.mcr_year AS year,
                msc.mcr_price_reseller AS reseller_raw,
                msc.mcr_price_retail AS retail_raw,
                msc.mcr_price_reseller_carbon AS reseller_carbon_raw,
                msc.mcr_price_retail_carbon AS retail_carbon_raw,
                FORMAT(msc.mcr_price_reseller, 0) AS reseller_price,
                FORMAT(msc.mcr_price_retail, 0) AS retail_price,
                FORMAT(msc.mcr_price_reseller_carbon, 0) AS reseller_price_carbon,
                FORMAT(msc.mcr_price_retail_carbon, 0) AS retail_price_carbon,
                msc.mcr_flag AS status,
                msc.mcr_flag_stock AS status_stock
            FROM 
                ms_car AS msc
            JOIN 
                ms_brand AS mbr ON msc.mcr_mbr_id = mbr.mbr_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($search)) {
            $sql .= " AND (
                mbr.mbr_name LIKE ? OR
                msc.mcr_type LIKE ? OR
                msc.mcr_year LIKE ?
            )";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $sql .= " ORDER BY mbr.mbr_name ASC, msc.mcr_type ASC LIMIT " . (int)$offset . ", " . (int)$limit;

        return $this->exec->executeSelect($sql, $params, 'all');
    }

    public function getCatalogRowById($id)
    {
        $sql = "
            SELECT 
                msc.mcr_type AS type,
                msc.mcr_year AS year,
                msc.mcr_price_reseller AS reseller,
                msc.mcr_price_retail AS retail,
                msc.mcr_price_reseller_carbon AS reseller_carbon,
                msc.mcr_price_retail_carbon AS retail_carbon,
                msc.mcr_flag AS status,
                msc.mcr_flag_stock AS status_stock 
            FROM 
                ms_car AS msc
            WHERE 
                msc.mcr_id = :id 
        ";
        return $this->exec->executeSelect($sql, [':id' => $id], 'row');
    }

    public function countCatalogData($search = '')
    {
        $sql = "
            SELECT 
                COUNT(*) 
            FROM 
                ms_car AS msc
            JOIN 
                ms_brand AS mbr ON msc.mcr_mbr_id = mbr.mbr_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($search)) {
            $sql .= " AND (
                mbr.mbr_name LIKE ? OR
                msc.mcr_type LIKE ? OR
                msc.mcr_year LIKE ?
            )";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        return $this->exec->executeSelect($sql, $params, 'one');
    }

    public function getCatalogExportData($search = '')
    {
        $sql = "
            SELECT 
                mbr.mbr_name AS brand,
                msc.mcr_type AS type,
                msc.mcr_year AS year,
                FORMAT(msc.mcr_price_reseller, 0) AS reseller_price,
                FORMAT(msc.mcr_price_retail, 0) AS retail_price,
                FORMAT(msc.mcr_price_reseller_carbon, 0) AS reseller_price_carbon,
                FORMAT(msc.mcr_price_retail_carbon, 0) AS retail_price_carbon
            FROM 
                ms_car AS msc
            JOIN 
                ms_brand AS mbr ON msc.mcr_mbr_id = mbr.mbr_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($search)) {
            $sql .= " AND (
                mbr.mbr_name LIKE ? OR
                msc.mcr_type LIKE ? OR
                msc.mcr_year LIKE ?
            )";
            $keyword = '%' . $search . '%';
            $params = [$keyword, $keyword, $keyword];
        }

        $sql .= " ORDER BY mbr.mbr_name ASC, msc.mcr_type ASC";

        $result = $this->exec->executeSelect($sql, $params, 'all');

        return $result['status'] ? $result['data'] : [];
    }

    public function getAllBrands()
    {
        $sql = "
            SELECT 
                mbr_id, 
                mbr_name 
            FROM 
                ms_brand 
            WHERE 
                mbr_flag = 'Y' 
            ORDER BY 
                mbr_name ASC";
        return $this->exec->executeSelect($sql, [], 'all')['data'] ?? [];
    }

    public function getInvoiceItemOptions()
    {
        $sql = "
            SELECT
                msc.mcr_id AS id,
                mbr.mbr_name AS brand,
                msc.mcr_type AS type,
                msc.mcr_year AS year,
                msc.mcr_price_reseller AS reseller_price,
                msc.mcr_price_retail AS retail_price,
                msc.mcr_price_reseller_carbon AS reseller_price_carbon,
                msc.mcr_price_retail_carbon AS retail_price_carbon
            FROM
                ms_car AS msc
            JOIN
                ms_brand AS mbr ON msc.mcr_mbr_id = mbr.mbr_id
            WHERE
                msc.mcr_flag = 'Y'
                AND msc.mcr_flag_stock = 'Y'
            ORDER BY
                mbr.mbr_name ASC,
                msc.mcr_type ASC,
                msc.mcr_year ASC
        ";

        return $this->exec->executeSelect($sql, [], 'all');
    }

    public function catalogExistsById($brandId, $type, $year)
    {
        $sql = "
            SELECT 
                COUNT(*)
            FROM 
                ms_car
            WHERE 
                mcr_mbr_id = ?
                AND mcr_type = ?
                AND mcr_year = ?
        ";
        $res = $this->exec->executeSelect($sql, [$brandId, $type, $year], 'one');
        return (int)($res['data'] ?? 0) > 0;
    }

    // MASTER BRAND

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
}
