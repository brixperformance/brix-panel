<?php
require_once __DIR__ . '/../Execute.php';

class InvoiceLogTransaction
{
    private $exec;

    public function __construct($config)
    {
        $this->exec = new Execute($config);
    }

    /**
     * Hitung berapa invoice sudah dibuat hari ini untuk
     * dealer tertentu (atau customer) agar bisa generate seq.
     */
    private function countTodaySequence(string $invoiceType, string $dealerCode): int
    {
        if ($invoiceType === 'dealer' && $dealerCode !== '') {
            $sql    = "SELECT COUNT(*) FROM log_invoice WHERE linv_dealer_code = ? AND DATE(linv_create_date) = CURDATE()";
            $result = $this->exec->executeSelect($sql, [$dealerCode], 'one');
        } else {
            $sql    = "SELECT COUNT(*) FROM log_invoice WHERE linv_type = 'customer' AND DATE(linv_create_date) = CURDATE()";
            $result = $this->exec->executeSelect($sql, [], 'one');
        }
        return (int)($result['data'] ?? 0);
    }

    /**
     * Generate invoice number.
     *
     * Dealer  : {dealer_code_6}{YYMMDD}{seq_3}  => 15 chars
     * Customer: CUST{YYMMDD}{seq_3}              => 13 chars
     */
    public function generateInvoiceNumber(string $invoiceType, string $dealerCode): string
    {
        $datePart = date('ymd');
        $seq      = $this->countTodaySequence($invoiceType, $dealerCode) + 1;
        $seqPart  = str_pad((string)$seq, 3, '0', STR_PAD_LEFT);

        if ($invoiceType === 'dealer' && $dealerCode !== '') {
            $codePart = strtoupper(substr(str_pad($dealerCode, 6, '0', STR_PAD_RIGHT), 0, 6));
            return $codePart . $datePart . $seqPart;
        }

        return 'CUST' . $datePart . $seqPart;
    }

    /**
     * Ambil linv_id berdasarkan invoice number (setelah insertLog).
     */
    public function getLogIdByNumber(string $number): int
    {
        $result = $this->exec->executeSelect(
            'SELECT linv_id FROM log_invoice WHERE linv_number = ? LIMIT 1',
            [$number],
            'one'
        );
        return (int)($result['data'] ?? 0);
    }

    /**
     * Simpan satu record ke log_invoice.
     * $data keys: number, type, dealer_code, bill_to, ship_to,
     *             subtotal, shipping, total, items_json
     */
    public function deleteLog(int $id): array
    {
        try {
            return $this->exec->executeNonQuery(
                "DELETE FROM log_invoice WHERE linv_id = ?",
                [$id],
                'Log deleted'
            );
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateLog(array $data): array
    {
        $sql = "
            UPDATE log_invoice SET
                linv_bill_to         = ?,
                linv_ship_to         = ?,
                linv_subtotal        = ?,
                linv_discount        = ?,
                linv_discount_type   = ?,
                linv_discount_value  = ?,
                linv_discount_max    = ?,
                linv_shipping        = ?,
                linv_additional_fee  = ?,
                linv_additional_fee_label = ?,
                linv_footer_notes    = ?,
                linv_footer_closing_message = ?,
                linv_due_date        = ?,
                linv_total           = ?,
                linv_items_json      = ?,
                linv_update_date     = NOW()
            WHERE linv_id = ?
        ";

        try {
            return $this->exec->executeNonQuery($sql, [
                $data['bill_to'],
                $data['ship_to'],
                $data['subtotal'],
                $data['discount'],
                $data['discount_type']   ?? 'flat',
                $data['discount_value']  ?? 0,
                $data['discount_max']    ?? 0,
                $data['shipping'],
                $data['additional_fee']  ?? 0,
                $data['additional_fee_label'] ?? '',
                $data['footer_notes'] ?? '',
                $data['footer_closing_message'] ?? '',
                $data['due_date']        ?? date('Y-m-d', strtotime('+3 days')),
                $data['total'],
                $data['items_json'],
                $data['id'],
            ], 'Log updated');
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertLog(array $data): array
    {
        $sql = "
            INSERT INTO log_invoice (
                linv_number,
                linv_type,
                linv_dealer_code,
                linv_bill_to,
                linv_ship_to,
                linv_subtotal,
                linv_discount,
                linv_discount_type,
                linv_discount_value,
                linv_discount_max,
                linv_shipping,
                linv_additional_fee,
                linv_additional_fee_label,
                linv_footer_notes,
                linv_footer_closing_message,
                linv_due_date,
                linv_total,
                linv_items_json,
                linv_create_date,
                linv_update_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        try {
            return $this->exec->executeNonQuery($sql, [
                $data['number'],
                $data['type'],
                $data['dealer_code']    ?: null,
                $data['bill_to'],
                $data['ship_to'],
                $data['subtotal'],
                $data['discount']       ?? 0,
                $data['discount_type']  ?? 'flat',
                $data['discount_value'] ?? 0,
                $data['discount_max']   ?? 0,
                $data['shipping'],
                $data['additional_fee'] ?? 0,
                $data['additional_fee_label'] ?? '',
                $data['footer_notes'] ?? '',
                $data['footer_closing_message'] ?? '',
                $data['due_date']       ?? date('Y-m-d', strtotime('+3 days')),
                $data['total'],
                $data['items_json'],
            ], 'Log saved');
        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}
