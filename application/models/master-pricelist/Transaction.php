<?php
require_once __DIR__ . '/../Execute.php';

class MasterPricelistTransaction
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }
    
    public function updateCatalog($id, $type, $year, $reseller, $retail, $resellerCarbon, $retailCarbon, $status, $status_stock, $current)
    {
        $queries = [];

        if ($current['type'] !== $type || $current['year'] !== $year) {
            $queries[] = [
                'sql' => "UPDATE ms_car 
                        SET mcr_type = :type, mcr_year = :year, mcr_update_date = NOW() 
                        WHERE mcr_id = :id",
                'params' => [ ':id' => $id, ':type' => $type, ':year' => $year ]
            ];
        }

        if (
            (float)$current['reseller'] != (float)$reseller ||
            (float)$current['retail'] != (float)$retail ||
            (float)$current['reseller_carbon'] != (float)$resellerCarbon ||
            (float)$current['retail_carbon'] != (float)$retailCarbon
        ) {
            $queries[] = [
                'sql' => "UPDATE ms_car 
                        SET mcr_price_reseller = :reseller,
                            mcr_price_retail = :retail,
                            mcr_price_reseller_carbon = :reseller_carbon,
                            mcr_price_retail_carbon = :retail_carbon,
                            mcr_update_date = NOW() 
                        WHERE mcr_id = :id",
                'params' => [
                    ':id' => $id,
                    ':reseller' => $reseller,
                    ':retail' => $retail,
                    ':reseller_carbon' => $resellerCarbon,
                    ':retail_carbon' => $retailCarbon
                ]
            ];
        }

        // ✅ NEW: update flags when changed
        if ($current['status'] !== $status) {
            $queries[] = [
                'sql' => "UPDATE ms_car 
                        SET mcr_flag = :status, mcr_update_date = NOW() 
                        WHERE mcr_id = :id",
                'params' => [ ':id' => $id, ':status' => $status ]
            ];
        }
        if ($current['status_stock'] !== $status_stock) {
            $queries[] = [
                'sql' => "UPDATE ms_car 
                        SET mcr_flag_stock = :status_stock, mcr_update_date = NOW() 
                        WHERE mcr_id = :id",
                'params' => [ ':id' => $id, ':status_stock' => $status_stock ]
            ];
        }

        if (empty($queries)) {
            return ['status' => false, 'message' => 'No changes detected.'];
        }

        return $this->exec->executeTransaction($queries);
    }

    public function createCatalog($brandId, $type, $year, $reseller, $retail, $resellerCarbon, $retailCarbon)
    {
        $queries = [
            [
                'sql' => "INSERT INTO ms_car
                        (mcr_mbr_id, mcr_type, mcr_year, mcr_price_reseller, mcr_price_retail, mcr_price_reseller_carbon, mcr_price_retail_carbon, mcr_flag, mcr_create_date)
                        VALUES (:brand, :type, :year, :reseller, :retail, :reseller_carbon, :retail_carbon, 'Y', NOW())",
                'params' => [
                    ':brand' => $brandId,
                    ':type'  => $type,
                    ':year'  => $year,
                    ':reseller' => $reseller,
                    ':retail'   => $retail,
                    ':reseller_carbon' => $resellerCarbon,
                    ':retail_carbon' => $retailCarbon
                ]
            ]
        ];

        return $this->exec->executeTransaction($queries);
    }
}
