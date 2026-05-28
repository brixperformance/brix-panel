<?php
require_once __DIR__ . '/../Execute.php';

class MasterProvinceTransaction
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    public function insertProvince(string $code, string $islandCode, string $name, string $status): array
    {
        try {
            return $this->exec->executeNonQuery(
                "INSERT INTO ms_province (msp_code, msp_msi_code, msp_name, msp_active_status) VALUES (?, ?, ?, ?)",
                [$code, $islandCode, $name, $status],
                'Province created'
            );
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '1062') !== false || strpos($msg, 'Duplicate entry') !== false) {
                return ['status' => false, 'message' => "Province code '{$code}' already exists."];
            }
            return ['status' => false, 'message' => $msg];
        }
    }

    public function updateProvince(string $code, string $name, string $status): array
    {
        try {
            return $this->exec->executeNonQuery(
                "UPDATE ms_province SET msp_name = ?, msp_active_status = ? WHERE msp_code = ?",
                [$name, $status, $code],
                'Province updated'
            );
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteProvince(string $code): array
    {
        try {
            return $this->exec->executeNonQuery(
                "DELETE FROM ms_province WHERE msp_code = ?",
                [$code],
                'Province deleted'
            );
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
