<?php
require_once __DIR__ . '/../Execute.php';

class MasterIslandTransaction
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    public function insertIsland(string $code, string $name, string $status): array
    {
        try {
            return $this->exec->executeNonQuery(
                "INSERT INTO ms_island (msi_code, msi_name, msi_active_status) VALUES (?, ?, ?)",
                [$code, $name, $status],
                'Island created'
            );
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateIsland(string $code, string $name, string $status): array
    {
        try {
            return $this->exec->executeNonQuery(
                "UPDATE ms_island SET msi_name = ?, msi_active_status = ? WHERE msi_code = ?",
                [$name, $status, $code],
                'Island updated'
            );
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteIsland(string $code): array
    {
        try {
            return $this->exec->executeNonQuery(
                "DELETE FROM ms_island WHERE msi_code = ?",
                [$code],
                'Island deleted'
            );
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '1451') !== false || strpos($msg, 'foreign key') !== false) {
                return ['status' => false, 'message' => 'Cannot delete: island still has province data linked to it.'];
            }
            return ['status' => false, 'message' => $msg];
        }
    }
}
