<?php

require_once __DIR__ . '/../../models/Execute.php';
require_once __DIR__ . '/../../models/master-pricelist/View.php';

$config = require __DIR__ . '/../../configs/database.php';
$view = new MasterPricelistView($config);

$search = $_GET['search'] ?? '';
$data = $view->getCatalogExportData($search);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="catalog-export.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo "Brand\tType\tYear\tReseller Price (IDR)\tRetail Price (IDR)\n";

foreach ($data as $row) {
    echo "{$row['brand']}\t{$row['type']}\t{$row['year']}\t{$row['reseller_price']}\t{$row['retail_price']}\n";
}

exit;
