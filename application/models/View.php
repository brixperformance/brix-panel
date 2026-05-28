<?php
require_once 'Execute.php';

class AdminView
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
                msa.msa_username = ?
        ";
        return $this->exec->executeSelect($sql, [$username], 'row');
    }
}
