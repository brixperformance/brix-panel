<?php
require_once 'Execute.php';

class BaseTransaction
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }
}
