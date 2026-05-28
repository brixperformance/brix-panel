<?php
require_once __DIR__ . '/../Execute.php';

class InvoiceLogView
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    public function countLogData(string $search = '', string $type = '', string $dealerCode = ''): array
    {
        $sql    = "SELECT COUNT(*) FROM log_invoice WHERE 1=1";
        $params = [];

        if ($type !== '') {
            $sql .= " AND linv_type = ?";
            $params[] = $type;
        }

        if ($dealerCode !== '') {
            $sql .= " AND linv_dealer_code = ?";
            $params[] = $dealerCode;
        }

        if ($search !== '') {
            $sql   .= " AND (linv_number LIKE ? OR linv_bill_to LIKE ? OR linv_ship_to LIKE ?)";
            $like   = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return $this->exec->executeSelect($sql, $params, 'one');
    }

    public function getLogData(string $search = '', int $offset = 0, int $limit = 15, string $type = '', string $dealerCode = '', string $sort = 'newest'): array
    {
        $sql = "
            SELECT
                linv_id,
                linv_number,
                linv_type,
                linv_dealer_code,
                linv_bill_to,
                linv_ship_to,
                linv_subtotal,
                linv_discount,
                linv_shipping,
                linv_total,
                FORMAT(linv_total, 0) AS linv_total_fmt,
                DATE_FORMAT(linv_create_date, '%d %b %Y %H:%i') AS linv_date_fmt,
                DATE_FORMAT(COALESCE(linv_update_date, linv_create_date), '%d %b %Y %H:%i') AS linv_last_update_fmt
            FROM log_invoice
            WHERE 1=1
        ";
        $params = [];

        if ($type !== '') {
            $sql .= " AND linv_type = ?";
            $params[] = $type;
        }

        if ($dealerCode !== '') {
            $sql .= " AND linv_dealer_code = ?";
            $params[] = $dealerCode;
        }

        if ($search !== '') {
            $sql   .= " AND (linv_number LIKE ? OR linv_bill_to LIKE ? OR linv_ship_to LIKE ?)";
            $like   = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $orderBy = match ($sort) {
            'oldest' => 'linv_create_date ASC',
            'highest' => 'linv_total DESC, linv_create_date DESC',
            'lowest' => 'linv_total ASC, linv_create_date DESC',
            default => 'linv_create_date DESC',
        };

        $sql .= " ORDER BY " . $orderBy . " LIMIT " . (int)$offset . ", " . (int)$limit;

        return $this->exec->executeSelect($sql, $params, 'all');
    }

    public function getLogById(int $id): array
    {
        $sql = "SELECT * FROM log_invoice WHERE linv_id = ? LIMIT 1";
        return $this->exec->executeSelect($sql, [$id], 'row');
    }
}
