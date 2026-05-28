<?php

declare(strict_types=1);

$tableId = isset($tableId) && is_string($tableId) && $tableId !== '' ? $tableId : 'data-hub-table';
$tableTitle = isset($tableTitle) && is_string($tableTitle) ? $tableTitle : 'Data Table';
$tableDescription = isset($tableDescription) && is_string($tableDescription) ? $tableDescription : '';
$tableColumns = isset($tableColumns) && is_array($tableColumns) ? $tableColumns : [];
$tableRows = isset($tableRows) && is_array($tableRows) ? $tableRows : [];
$tableToolbarHtml = isset($tableToolbarHtml) && is_string($tableToolbarHtml) ? $tableToolbarHtml : '';
$tableFiltersHtml = isset($tableFiltersHtml) && is_string($tableFiltersHtml) ? $tableFiltersHtml : '';
$tableFooterHtml = isset($tableFooterHtml) && is_string($tableFooterHtml) ? $tableFooterHtml : '';
$tableCardClass = isset($tableCardClass) && is_string($tableCardClass) ? trim($tableCardClass) : '';
$tableClassName = isset($tableClassName) && is_string($tableClassName) ? trim($tableClassName) : '';
$tableEmptyMessage = isset($tableEmptyMessage) && is_string($tableEmptyMessage) ? $tableEmptyMessage : 'No data available.';
$tableShowCheckbox = !empty($tableShowCheckbox);
$tableStriped = !empty($tableStriped);
$tableMobile = !empty($tableMobile);
$tableNowrap = array_key_exists('tableNowrap', get_defined_vars()) ? (bool) $tableNowrap : true;
$tableHeaderClass = isset($tableHeaderClass) && is_string($tableHeaderClass) ? trim($tableHeaderClass) : '';

$tableClasses = ['table', 'table-vcenter'];
if ($tableShowCheckbox) {
	$tableClasses[] = 'table-selectable';
}
if ($tableStriped) {
	$tableClasses[] = 'table-striped';
}
if ($tableMobile) {
	$tableClasses[] = 'table-mobile-md';
}
if ($tableNowrap) {
	$tableClasses[] = 'text-nowrap';
}
if ($tableClassName !== '') {
	$tableClasses[] = $tableClassName;
}

$colspan = count($tableColumns) + ($tableShowCheckbox ? 1 : 0);
?>
<div class="card <?= htmlspecialchars($tableCardClass, ENT_QUOTES) ?>">
	<div class="card-table">
		<div class="card-header <?= htmlspecialchars($tableHeaderClass, ENT_QUOTES) ?>">
			<div class="row align-items-center w-100 g-3">
				<div class="col">
					<h3 class="card-title mb-0"><?= htmlspecialchars($tableTitle, ENT_QUOTES) ?></h3>
					<?php if ($tableDescription !== ''): ?>
						<p class="text-secondary m-0"><?= htmlspecialchars($tableDescription, ENT_QUOTES) ?></p>
					<?php endif; ?>
				</div>
				<?php if ($tableToolbarHtml !== ''): ?>
					<div class="col-md-auto col-sm-12">
						<div class="ms-auto d-flex flex-wrap btn-list">
							<?= $tableToolbarHtml ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($tableFiltersHtml !== ''): ?>
			<div class="card-body border-bottom py-3">
				<?= $tableFiltersHtml ?>
			</div>
		<?php endif; ?>

		<div class="table-responsive">
			<table id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>" class="<?= htmlspecialchars(implode(' ', $tableClasses), ENT_QUOTES) ?>">
				<thead>
					<tr>
						<?php if ($tableShowCheckbox): ?>
							<th class="w-1">
								<input class="form-check-input m-0 align-middle" type="checkbox" aria-label="Select all rows">
							</th>
						<?php endif; ?>

						<?php foreach ($tableColumns as $column): ?>
							<?php
							$label = isset($column['label']) ? (string) $column['label'] : '';
							$class = isset($column['class']) ? (string) $column['class'] : '';
							?>
							<th class="<?= htmlspecialchars($class, ENT_QUOTES) ?>">
								<?= htmlspecialchars($label, ENT_QUOTES) ?>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php if ($tableRows === []): ?>
						<tr>
							<td colspan="<?= $colspan ?>" class="text-center text-secondary py-5">
								<?= htmlspecialchars($tableEmptyMessage, ENT_QUOTES) ?>
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($tableRows as $row): ?>
							<?php
							$rowClass = isset($row['class']) ? (string) $row['class'] : '';
							$cells = isset($row['cells']) && is_array($row['cells']) ? $row['cells'] : [];
							?>
							<tr class="<?= htmlspecialchars($rowClass, ENT_QUOTES) ?>">
								<?php if ($tableShowCheckbox): ?>
									<td>
										<input class="form-check-input m-0 align-middle table-selectable-check" type="checkbox" aria-label="Select row">
									</td>
								<?php endif; ?>

								<?php foreach ($cells as $index => $cell): ?>
									<?php
									$content = isset($cell['html']) ? (string) $cell['html'] : htmlspecialchars((string) ($cell['text'] ?? ''), ENT_QUOTES);
									$cellClass = isset($cell['class']) ? (string) $cell['class'] : '';
									$cellTag = isset($cell['tag']) && in_array($cell['tag'], ['th', 'td'], true) ? $cell['tag'] : 'td';
									$dataLabel = isset($tableColumns[$index]['label']) ? (string) $tableColumns[$index]['label'] : '';
									?>
									<<?= $cellTag ?>
										<?= $tableMobile && $dataLabel !== '' ? 'data-label="' . htmlspecialchars($dataLabel, ENT_QUOTES) . '"' : '' ?>
										class="<?= htmlspecialchars($cellClass, ENT_QUOTES) ?>"
									><?= $content ?></<?= $cellTag ?>>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ($tableFooterHtml !== ''): ?>
			<div class="card-footer">
				<?= $tableFooterHtml ?>
			</div>
		<?php endif; ?>
	</div>
</div>
