<?php
require_once __DIR__ . '/../Execute.php';

class MasterBrandTransaction
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }
    
    public function updateBrand($id, $name, $file, $flag)
    {
        $queries = [];

        $queries[] = [
            'sql' => "UPDATE ms_brand
                      SET mbr_name = :name,
                          mbr_file_name = :file,
                          mbr_flag = :flag,
                          mbr_update_date = NOW()
                      WHERE mbr_id = :id",
            'params' => [
                ':id'   => $id,
                ':name' => $name,
                ':file' => $file,
                ':flag' => $flag,
            ]
        ];

        if (empty($queries)) {
            return ['status' => false, 'message' => 'No changes detected.'];
        }

        return $this->exec->executeTransaction($queries);
    }

    public function insertBrand($name, $file, $flag)
    {
        $queries = [];
        $queries[] = [
            'sql' => "INSERT INTO ms_brand (mbr_name, mbr_file_name, mbr_flag, mbr_create_date, mbr_update_date)
                    VALUES (:name, :file, :flag, NOW(), NOW())",
            'params' => [
                ':name' => $name,
                ':file' => $file,
                ':flag' => $flag,
            ]
        ];
        return $this->exec->executeTransaction($queries);
    }
}
