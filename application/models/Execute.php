<?php

require_once __DIR__ . '/../configs/error_logging.php';

/**
 * Class Execute
 *
 * A lightweight database utility class for executing select, insert/update,
 * and transactional queries using PDO.
 */
class Execute
{
    private $pdo;
    private $connectionError = '';

    public function __construct($config)
    {
        $host = isset($config['host']) ? $config['host'] : '';
        $dbname = isset($config['dbname']) ? $config['dbname'] : '';
        $user = isset($config['username']) ? $config['username'] : '';
        $pass = isset($config['password']) ? $config['password'] : '';
        $port = isset($config['port']) ? $config['port'] : '3306';
        $driver = isset($config['driver']) ? $config['driver'] : 'mysql';
        $charset = isset($config['charset']) ? $config['charset'] : 'utf8mb4';

        if ($host === '' || $dbname === '' || $user === '') {
            $this->pdo = null;
            $this->connectionError = 'Database is not configured. Add DB credentials in .env to enable live data.';
            return;
        }

        $dsn = "$driver:host=$host;dbname=$dbname;port=$port;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            if ($driver === 'mysql') {
                $this->pdo->exec("SET time_zone = '+07:00'");
            }
        } catch (PDOException $e) {
            $this->pdo = null;
            $this->connectionError = 'Connection failed: ' . $e->getMessage();
            app_log_exception($e, 'database_connect');
        }
    }

    private function unavailableData($mode)
    {
        switch ($mode) {
            case 'all':
                return [];
            case 'one':
                return null;
            case 'row':
            default:
                return null;
        }
    }

    public function executeSelect($sql, $params = [], $mode = 'all')
    {
        $result = ['status' => true, 'message' => '', 'data' => null];

        if (!$this->pdo instanceof PDO) {
            $result['status'] = false;
            $result['message'] = $this->connectionError !== '' ? $this->connectionError : 'Database is unavailable.';
            $result['data'] = $this->unavailableData($mode);
            return $result;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            switch ($mode) {
                case 'all':
                    $result['data'] = $stmt->fetchAll();
                    break;
                case 'row':
                    $result['data'] = $stmt->fetch();
                    break;
                case 'one':
                    $result['data'] = $stmt->fetchColumn();
                    break;
                default:
                    $result['status'] = false;
                    $result['message'] = "Invalid fetch mode: $mode";
            }
        } catch (PDOException $e) {
            $result['status'] = false;
            $result['message'] = $e->getMessage();
            app_log_exception($e, 'execute_select');
        }

        return $result;
    }

    public function executeNonQuery($sql, $params = [], $successMessage = 'Query executed successfully')
    {
        $result = ['status' => true, 'message' => $successMessage];

        if (!$this->pdo instanceof PDO) {
            throw new Exception($this->connectionError !== '' ? $this->connectionError : 'Database is unavailable.');
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            app_log_exception($e, 'execute_non_query');
            throw $e;
        }

        return $result;
    }

    public function executeTransaction($queries)
    {
        $result = ['status' => true, 'message' => 'All queries executed successfully'];

        if (!$this->pdo instanceof PDO) {
            $result['status'] = false;
            $result['message'] = $this->connectionError !== '' ? $this->connectionError : 'Database is unavailable.';
            return $result;
        }

        try {
            $this->pdo->beginTransaction();

            foreach ($queries as $q) {
                $sql = isset($q['sql']) ? $q['sql'] : '';
                $params = isset($q['params']) ? $q['params'] : [];

                if (trim($sql) === '') {
                    throw new Exception('Missing SQL in transaction query.');
                }

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $result['status'] = false;
            $result['message'] = 'Transaction failed: ' . $e->getMessage();
            app_log_exception($e, 'execute_transaction');
        }

        return $result;
    }

    public function close()
    {
        $this->pdo = null;
    }
}
