<?php
require_once __DIR__ . '/../Execute.php';

class MasterDealerTransaction
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    /**
     * Create a dealer row with minimal required fields + optional details.
     * If your table auto-generates msd_code, this will work as-is.
     * Otherwise, add code generation logic here.
     */
    // public function createDealer($islandCode, $provinceCode, $name = '', $type = '', $contact = '', $address = '', $mapIframe = '', $joinDate = '')
    // {
    //     $params = [
    //         ':msi'   => $islandCode,
    //         ':msp'   => $provinceCode,
    //         ':name'  => $name,
    //         ':type'  => $type,
    //         ':contact' => $contact,
    //         ':address' => $address,
    //         ':map'     => $mapIframe,
    //         ':join'    => ($joinDate !== '' ? $joinDate : null),
    //     ];

    //     $queries = [[
    //         'sql' => "
    //             INSERT INTO ms_dealer
    //                 (msd_msi_code, msd_msp_code, msd_name, msd_type, msd_contact, msd_address, msd_map_iframe, msd_join_date, msd_status, msd_create_date)
    //             VALUES
    //                 (:msi, :msp, :name, :type, :contact, :address, :map, :join, 'Y', NOW())
    //         ",
    //         'params' => $params
    //     ]];

    //     return $this->exec->executeTransaction($queries);
    // }

    public function createDealer($islandCode, $provinceCode, $name = '', $type = '', $contact = '', $address = '', $mapIframe = '', $joinDate = '')
    {
        // Prefix from province (first 4 chars)
        $prefix = substr($provinceCode, 0, 4);

        // Find max 2-digit suffix for this prefix
        $row = $this->exec->executeSelect(
            "
            SELECT MAX(CAST(SUBSTRING(msd_code, 5, 2) AS UNSIGNED)) AS max_suffix
            FROM ms_dealer
            WHERE LEFT(msd_code, 4) = :prefix
            ",
            [':prefix' => $prefix],
            'row'
        );

        $max  = isset($row['data']['max_suffix']) ? (int)$row['data']['max_suffix'] : 0;
        $next = $max + 1;
        if ($next > 99) {
            return ['status' => false, 'message' => 'Dealer code limit reached for prefix ' . $prefix];
        }
        $suffix  = str_pad((string)$next, 2, '0', STR_PAD_LEFT);
        $newCode = $prefix . $suffix; // e.g., JVBT03

        // ⚠️ params: match placeholders exactly (no :msi)
        $queries = [[
            'sql' => "
                INSERT INTO ms_dealer
                    (msd_code, msd_msp_code, msd_name, msd_type, msd_contact, msd_address, msd_map_embed, msd_join_date, msd_status, msd_create_date)
                VALUES
                    (:code, :msp, :name, :type, :contact, :address, :map, :join, 'Y', NOW())
            ",
            'params' => [
                ':code'    => $newCode,
                ':msp'     => $provinceCode,
                ':name'    => $name,
                ':type'    => $type,
                ':contact' => $contact,
                ':address' => $address,
                ':map'     => $mapIframe,
                ':join'    => ($joinDate !== '' ? $joinDate : null),
            ]
        ]];

        return $this->exec->executeTransaction($queries);
    }

    public function updateDealer($code, $name, $type, $contact, $address, $map, $joinDate, $status)
    {
        $queries = [[
            'sql' => "
                UPDATE ms_dealer
                SET 
                    msd_name       = :name,
                    msd_type       = :type,
                    msd_contact    = :contact,
                    msd_address    = :address,
                    msd_map_embed  = :map,
                    msd_join_date  = :join,
                    msd_status     = :status,
                    msd_update_date= NOW()
                WHERE msd_code = :code
            ",
            'params' => [
                ':code'    => $code,
                ':name'    => $name,
                ':type'    => $type,
                ':contact' => $contact,
                ':address' => $address,
                ':map'     => ($map !== '' ? $map : null),
                ':join'    => ($joinDate !== '' ? $joinDate : null),
                ':status'  => ($status === 'Y' ? 'Y' : 'N'),
            ]
        ]];

        return $this->exec->executeTransaction($queries);
    }
}
