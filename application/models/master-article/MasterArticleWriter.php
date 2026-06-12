<?php

declare(strict_types=1);

require_once __DIR__ . '/../Execute.php';
require_once dirname(__DIR__, 2) . '/configs/article_media.php';

final class MasterArticleWriter
{
    private Execute $exec;
    private array $mediaConfig;

    public function __construct(array $config)
    {
        $this->exec = new Execute($config);
        $this->mediaConfig = get_article_media_config();
    }

    private function sval(array $data, string $key, string $default = ''): string
    {
        return isset($data[$key]) ? trim((string) $data[$key]) : $default;
    }

    private function extOf(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
        $name = preg_replace('/-+/', '-', $name) ?? $name;
        return strtolower(trim($name, '-._'));
    }

    private function slugify(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = preg_replace('/[^A-Za-z0-9\s-]/', '', $text) ?? '';
        $text = strtolower(trim($text));
        $text = preg_replace('/[\s-]+/', '-', $text) ?? $text;

        return trim($text, '-');
    }

    private function categoryMap(): array
    {
        return [
            'street-series' => 'SS',
            'competition-series' => 'CS',
            'event' => 'EV',
        ];
    }

    private function uploadRootForOrdinal(int $ordinal): string
    {
        return rtrim((string) $this->mediaConfig['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . article_media_directory_name($ordinal);
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Gagal membuat folder upload article: ' . $directory);
        }
    }

    private function storeUploadedFile(string $tmpFile, string $targetPath): bool
    {
        return @move_uploaded_file($tmpFile, $targetPath) || @rename($tmpFile, $targetPath);
    }

    private function saveUploadedImage(string $tmpFile, string $originalName, string $targetDir, string $prefix = 'image'): string
    {
        $extension = $this->extOf($originalName);
        $allowed = $this->mediaConfig['allowed_extensions'] ?? [];

        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Tipe file tidak didukung: ' . $originalName);
        }

        $cleanName = $this->sanitizeFilename($originalName);
        if ($cleanName === '') {
            $cleanName = $prefix . '-' . uniqid('', true) . '.' . $extension;
        }

        $this->ensureDirectory($targetDir);
        $destination = $targetDir . DIRECTORY_SEPARATOR . $cleanName;

        if (!$this->storeUploadedFile($tmpFile, $destination)) {
            throw new RuntimeException('Gagal menyimpan file upload: ' . $originalName);
        }

        return basename($destination);
    }

    private function saveBinaryImage(string $contents, string $originalName, string $targetDir, string $prefix = 'image'): string
    {
        $extension = $this->extOf($originalName);
        $allowed = $this->mediaConfig['allowed_extensions'] ?? [];

        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Tipe file tidak didukung: ' . $originalName);
        }

        $cleanName = $this->sanitizeFilename($originalName);
        if ($cleanName === '') {
            $cleanName = $prefix . '-' . uniqid('', true) . '.' . $extension;
        }

        $this->ensureDirectory($targetDir);
        $destination = $targetDir . DIRECTORY_SEPARATOR . $cleanName;

        if (@file_put_contents($destination, $contents) === false) {
            throw new RuntimeException('Gagal menyimpan file ZIP: ' . $originalName);
        }

        return basename($destination);
    }

    public function nextCodeResponse(string $category): array
    {
        $map = $this->categoryMap();
        if (!isset($map[$category])) {
            return ['ok' => false, 'message' => 'unknown category'];
        }

        $prefix = $map[$category];
        $result = $this->exec->executeSelect(
            'SELECT MAX(CAST(SUBSTRING(msa_code, 3) AS UNSIGNED)) AS mx FROM ms_articles WHERE msa_code LIKE ?',
            [$prefix . '%'],
            'row'
        );

        if (empty($result['status'])) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Failed to generate code')];
        }

        $max = (int) (($result['data']['mx'] ?? 0) ?: 0);
        return ['ok' => true, 'code' => $prefix . ($max + 1)];
    }

    public function createFromPost(array $post, array $files): array
    {
        $old = [
            'msa_code' => '',
            'msa_title' => '',
            'msa_subtitle' => '',
            'msa_slug' => '',
            'msa_category' => '',
            'msa_date' => date('Y-m-d'),
            'msa_meta_description' => '',
            'head_header_alt' => '',
        ];

        $msaCode = $old['msa_code'] = strtoupper($this->sval($post, 'msa_code'));
        $msaTitle = $old['msa_title'] = $this->sval($post, 'msa_title');
        $msaSubtitle = $old['msa_subtitle'] = $this->sval($post, 'msa_subtitle');
        $msaSlug = $old['msa_slug'] = strtolower($this->sval($post, 'msa_slug'));
        $msaCategory = $old['msa_category'] = $this->sval($post, 'msa_category');
        $msaDate = $old['msa_date'] = $this->sval($post, 'msa_date', date('Y-m-d'));
        $msaMetaDescription = $old['msa_meta_description'] = $this->sval($post, 'msa_meta_description');
        $headerAlt = $old['head_header_alt'] = $this->sval($post, 'head_header_alt');
        $blocks = is_array($post['blocks'] ?? null) ? $post['blocks'] : [];

        if ($msaSlug !== '') {
            $msaSlug = $this->slugify($msaSlug);
            $old['msa_slug'] = $msaSlug;
        } elseif ($msaTitle !== '') {
            $msaSlug = $this->slugify($msaTitle);
            $old['msa_slug'] = $msaSlug;
        }

        $map = $this->categoryMap();
        if (isset($map[$msaCategory])) {
            $nextCode = $this->nextCodeResponse($msaCategory);
            if (!empty($nextCode['ok'])) {
                $msaCode = strtoupper((string) ($nextCode['code'] ?? $msaCode));
                $old['msa_code'] = $msaCode;
            }
        }

        $errors = [];
        if ($msaCode === '') {
            $errors[] = 'Article code wajib ada.';
        }
        if ($msaTitle === '') {
            $errors[] = 'Title wajib diisi.';
        }
        if ($msaCategory === '' || !isset($map[$msaCategory])) {
            $errors[] = 'Category wajib dipilih.';
        }
        if ($msaSlug === '') {
            $errors[] = 'Slug wajib diisi.';
        }
        if ($msaMetaDescription === '') {
            $errors[] = 'Meta description wajib diisi.';
        }
        if ($msaSlug !== '' && !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $msaSlug)) {
            $errors[] = 'Slug harus menggunakan format kebab-case.';
        }
        if ($msaDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $msaDate)) {
            $errors[] = 'Tanggal article harus format YYYY-MM-DD.';
        }

        $ordinalResult = $this->exec->executeSelect(
            'SELECT COALESCE(MAX(CAST(msa_ordinal AS UNSIGNED)), 0) AS maxo FROM ms_articles',
            [],
            'row'
        );
        if (empty($ordinalResult['status'])) {
            return [
                'ok' => false,
                'message' => (string) ($ordinalResult['message'] ?? 'Database article belum bisa diakses.'),
                'old' => $old,
            ];
        }

        $msaOrdinal = (int) (($ordinalResult['data']['maxo'] ?? 0) ?: 0) + 1;
        $targetDir = $this->uploadRootForOrdinal($msaOrdinal);

        if ($errors === []) {
            $duplicateCode = $this->exec->executeSelect(
                'SELECT 1 FROM ms_articles WHERE msa_code = ? LIMIT 1',
                [$msaCode],
                'row'
            );
            if (!empty($duplicateCode['data'])) {
                $errors[] = "Article code '{$msaCode}' sudah ada.";
            }

            $duplicateSlug = $this->exec->executeSelect(
                'SELECT 1 FROM ms_articles WHERE msa_slug = ? LIMIT 1',
                [$msaSlug],
                'row'
            );
            if (!empty($duplicateSlug['data'])) {
                $errors[] = "Slug '{$msaSlug}' sudah ada.";
            }
        }

        $headerImage = null;
        if (isset($files['head_header_file']) && is_uploaded_file((string) ($files['head_header_file']['tmp_name'] ?? ''))) {
            try {
                $headerFilename = $this->saveUploadedImage(
                    (string) $files['head_header_file']['tmp_name'],
                    (string) $files['head_header_file']['name'],
                    $targetDir,
                    'header'
                );
                $headerImage = ['filename' => $headerFilename, 'alt' => $headerAlt];
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($headerImage === null) {
            $errors[] = 'Header image wajib diupload.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'message' => implode(' ', $errors), 'old' => $old];
        }

        $perBlockImported = [];
        foreach ($files as $key => $bag) {
            if (!preg_match('/^files_block_([a-zA-Z0-9_-]+)$/', (string) $key, $matches)) {
                continue;
            }

            $tempId = $matches[1];
            if (!is_array($bag['name'] ?? null)) {
                continue;
            }

            $perBlockImported[$tempId] = $perBlockImported[$tempId] ?? [];
            $count = count($bag['name']);

            for ($i = 0; $i < $count; $i++) {
                $errorCode = $bag['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($errorCode === UPLOAD_ERR_NO_FILE || $errorCode !== UPLOAD_ERR_OK) {
                    continue;
                }

                $originalName = (string) ($bag['name'][$i] ?? '');
                $tmpName = (string) ($bag['tmp_name'][$i] ?? '');
                $extension = $this->extOf($originalName);

                try {
                    if ($extension === 'zip' && class_exists('ZipArchive')) {
                        $zip = new ZipArchive();
                        if ($zip->open($tmpName) === true) {
                            for ($z = 0; $z < $zip->numFiles; $z++) {
                                $entry = (string) $zip->getNameIndex($z);
                                if (str_ends_with($entry, '/')) {
                                    continue;
                                }

                                $entryExtension = $this->extOf($entry);
                                if (!in_array($entryExtension, $this->mediaConfig['allowed_extensions'], true)) {
                                    continue;
                                }

                                $contents = $zip->getFromIndex($z);
                                if (!is_string($contents)) {
                                    continue;
                                }

                                $perBlockImported[$tempId][] = $this->saveBinaryImage(
                                    $contents,
                                    basename($entry),
                                    $targetDir,
                                    'body'
                                );
                            }
                            $zip->close();
                        }

                        continue;
                    }

                    $perBlockImported[$tempId][] = $this->saveUploadedImage($tmpName, $originalName, $targetDir, 'body');
                } catch (RuntimeException) {
                    continue;
                }
            }
        }

        $needsHeaderBlock = !empty($headerImage['filename']);
        $queries = [];
        $queries[] = [
            'sql' => 'INSERT INTO ms_articles
                (msa_code, msa_slug, msa_meta_description, msa_title, msa_subtitle, msa_category,
                 msa_date, msa_ordinal, msa_path, msa_views, msa_flag, msa_create_date, msa_update_date,
                 msa_image_header, msa_image_header_alt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW(), NOW(), ?, ?)',
            'params' => [
                $msaCode,
                $msaSlug,
                $msaMetaDescription,
                $msaTitle,
                $msaSubtitle,
                $msaCategory,
                $msaDate,
                (string) $msaOrdinal,
                'articles/' . $msaSlug,
                $headerImage['filename'] ?? null,
                $headerImage['alt'] ?? null,
            ],
        ];

        foreach ($blocks as $tempId => $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $order = (int) ($block['order'] ?? 0);
            if ($type === '' || $order <= 0) {
                continue;
            }

            $finalOrder = $needsHeaderBlock ? $order + 1 : $order;

            if ($type === 'text') {
                $raw = $this->sval($block, 'content');
                $paragraphs = array_values(array_filter(array_map('trim', explode("\n\n", $raw))));
                $queries[] = [
                    'sql' => 'INSERT INTO ms_article_blocks
                        (msab_msa_code, msab_order, msab_type, msab_content, msab_create_date, msab_update_date)
                        VALUES (?, ?, ?, ?, NOW(), NOW())',
                    'params' => [
                        $msaCode,
                        $finalOrder,
                        'text',
                        json_encode($paragraphs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ];
                continue;
            }

            if ($type === 'cta' || $type === 'cta-links') {
                $content = is_array($block['content'] ?? null) ? $block['content'] : [];
                $linksIn = is_array($content['links'] ?? null) ? $content['links'] : [];
                $links = [];
                foreach ($linksIn as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $label = trim((string) ($row['label'] ?? ''));
                    $url = trim((string) ($row['url'] ?? ''));
                    if ($url !== '') {
                        $links[] = ['label' => $label, 'url' => $url];
                    }
                }

                $queries[] = [
                    'sql' => 'INSERT INTO ms_article_blocks
                        (msab_msa_code, msab_order, msab_type, msab_content, msab_create_date, msab_update_date)
                        VALUES (?, ?, ?, ?, NOW(), NOW())',
                    'params' => [
                        $msaCode,
                        $finalOrder,
                        $type,
                        json_encode([
                            'head' => $this->sval($content, 'head'),
                            'body' => $this->sval($content, 'body'),
                            'links' => array_values($links),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ];
                continue;
            }

            if ($type === 'list') {
                $content = is_array($block['content'] ?? null) ? $block['content'] : [];
                $itemsIn = is_array($content['items'] ?? null) ? $content['items'] : [];
                $items = [];
                foreach ($itemsIn as $item) {
                    $value = trim((string) $item);
                    if ($value !== '') {
                        $items[] = $value;
                    }
                }

                $queries[] = [
                    'sql' => 'INSERT INTO ms_article_blocks
                        (msab_msa_code, msab_order, msab_type, msab_content, msab_create_date, msab_update_date)
                        VALUES (?, ?, ?, ?, NOW(), NOW())',
                    'params' => [
                        $msaCode,
                        $finalOrder,
                        'list',
                        json_encode([
                            'head' => $this->sval($content, 'head'),
                            'items' => array_values($items),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ];
                continue;
            }

            if ($type === 'list-grouped') {
                $content = is_array($block['content'] ?? null) ? $block['content'] : [];
                $itemsIn = is_array($content['items'] ?? null) ? $content['items'] : [];
                $items = [];
                foreach ($itemsIn as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $title = trim((string) ($row['title'] ?? ''));
                    $body = trim((string) ($row['body'] ?? ''));
                    if ($title !== '' || $body !== '') {
                        $items[] = ['title' => $title, 'body' => $body];
                    }
                }

                $queries[] = [
                    'sql' => 'INSERT INTO ms_article_blocks
                        (msab_msa_code, msab_order, msab_type, msab_content, msab_create_date, msab_update_date)
                        VALUES (?, ?, ?, ?, NOW(), NOW())',
                    'params' => [
                        $msaCode,
                        $finalOrder,
                        'list-grouped',
                        json_encode([
                            'head' => $this->sval($content, 'head'),
                            'items' => array_values($items),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ];
                continue;
            }

            if ($type === 'image') {
                $imported = $perBlockImported[(string) $tempId] ?? [];
                $altsByIndex = [];

                foreach ($post as $key => $value) {
                    if (!is_string($key)) {
                        continue;
                    }
                    if (preg_match('/^block_' . preg_quote((string) $tempId, '/') . '_img_(\d+)_alt$/', $key, $matches)) {
                        $altsByIndex[(int) $matches[1]] = trim((string) $value);
                    }
                }

                ksort($altsByIndex);
                $images = [];
                foreach (array_values($imported) as $index => $filename) {
                    $images[] = [
                        'filename' => $filename,
                        'alt' => $altsByIndex[$index] ?? '',
                    ];
                }

                $queries[] = [
                    'sql' => 'INSERT INTO ms_article_blocks
                        (msab_msa_code, msab_order, msab_type, msab_images, msab_create_date, msab_update_date)
                        VALUES (?, ?, ?, ?, NOW(), NOW())',
                    'params' => [
                        $msaCode,
                        $finalOrder,
                        'image',
                        json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ];
            }
        }

        if ($needsHeaderBlock) {
            $queries[] = [
                'sql' => 'INSERT INTO ms_article_blocks
                    (msab_msa_code, msab_order, msab_type, msab_images, msab_create_date, msab_update_date)
                    VALUES (?, ?, ?, ?, NOW(), NOW())',
                'params' => [
                    $msaCode,
                    1,
                    'image',
                    json_encode([[
                        'filename' => $headerImage['filename'],
                        'alt' => $headerImage['alt'] ?? '',
                    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ];
        }

        $transaction = $this->exec->executeTransaction($queries);
        if (empty($transaction['status'])) {
            return [
                'ok' => false,
                'message' => (string) ($transaction['message'] ?? 'Gagal menyimpan article.'),
                'old' => $old,
            ];
        }

        return [
            'ok' => true,
            'message' => "Article '{$msaTitle}' berhasil dibuat.",
            'old' => [
                'msa_code' => '',
                'msa_title' => '',
                'msa_subtitle' => '',
                'msa_slug' => '',
                'msa_category' => '',
                'msa_date' => date('Y-m-d'),
                'msa_meta_description' => '',
                'head_header_alt' => '',
            ],
            'code' => $msaCode,
        ];
    }

    public function updateFromPost(string $msaCode, array $post, array $files): array
    {
        $current = $this->getByCode($msaCode);
        if ($current === null) {
            return ['ok' => false, 'errors' => ['Article tidak ditemukan.']];
        }

        $title = $this->sval($post, 'msa_title');
        $subtitle = $this->sval($post, 'msa_subtitle');
        $slug = strtolower($this->sval($post, 'msa_slug'));
        $category = $this->sval($post, 'msa_category');
        $date = $this->sval($post, 'msa_date', date('Y-m-d'));
        $metaDescription = $this->sval($post, 'msa_meta_description');
        $headerAlt = $this->sval($post, 'head_header_alt');

        if ($slug !== '') {
            $slug = $this->slugify($slug);
        }

        $errors = [];
        if ($title === '') {
            $errors[] = 'Title wajib diisi.';
        }
        if ($slug === '') {
            $errors[] = 'Slug wajib diisi.';
        }
        if (!isset($this->categoryMap()[$category])) {
            $errors[] = 'Category wajib dipilih.';
        }
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = 'Tanggal article harus format YYYY-MM-DD.';
        }
        if ($metaDescription === '') {
            $errors[] = 'Meta description wajib diisi.';
        }

        if ($errors === []) {
            $duplicate = $this->exec->executeSelect(
                'SELECT 1 FROM ms_articles WHERE msa_slug = ? AND msa_code <> ? LIMIT 1',
                [$slug, $msaCode],
                'row'
            );
            if (!empty($duplicate['data'])) {
                $errors[] = "Slug '{$slug}' sudah dipakai article lain.";
            }
        }

        $headerFilename = null;
        if (isset($files['head_header_file']) && is_uploaded_file((string) ($files['head_header_file']['tmp_name'] ?? ''))) {
            try {
                $headerFilename = $this->saveUploadedImage(
                    (string) $files['head_header_file']['tmp_name'],
                    (string) $files['head_header_file']['name'],
                    $this->uploadRootForOrdinal((int) ($current['msa_ordinal'] ?? 0)),
                    'header'
                );
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $sql = 'UPDATE ms_articles
            SET msa_slug = ?,
                msa_meta_description = ?,
                msa_title = ?,
                msa_subtitle = ?,
                msa_category = ?,
                msa_date = ?,
                msa_image_header_alt = ?,
                msa_update_date = NOW()';
        $params = [$slug, $metaDescription, $title, $subtitle, $category, $date, $headerAlt];

        if ($headerFilename !== null) {
            $sql .= ', msa_image_header = ?';
            $params[] = $headerFilename;
        }

        $sql .= ' WHERE msa_code = ? LIMIT 1';
        $params[] = $msaCode;

        $queries = [
            ['sql' => $sql, 'params' => $params],
        ];

        $postedBlocks = is_array($post['blocks'] ?? null) ? $post['blocks'] : [];
        $existingBlocks = $this->getBlocksByCode($msaCode);
        $existingById = [];
        foreach ($existingBlocks as $block) {
            $existingById[(string) ($block['msab_id'] ?? '')] = $block;
        }

        $postedIds = [];
        $imageBlocks = [];

        foreach ($postedBlocks as $blockId => $block) {
            $blockId = (string) $blockId;
            $postedIds[$blockId] = true;
            if (!is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $order = (int) ($block['order'] ?? 0);
            if ($type === '' || $order <= 0) {
                continue;
            }

            if ($type === 'image') {
                $imageBlocks[$blockId] = $block;
                continue;
            }

            if ($type === 'text') {
                $contentJson = json_encode(
                    array_values(array_filter(array_map('trim', explode("\n\n", $this->sval($block, 'content'))))),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            } elseif ($type === 'cta' || $type === 'cta-links') {
                $content = is_array($block['content'] ?? null) ? $block['content'] : [];
                $linksIn = is_array($content['links'] ?? null) ? $content['links'] : [];
                $links = [];
                foreach ($linksIn as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $label = trim((string) ($row['label'] ?? ''));
                    $url = trim((string) ($row['url'] ?? ''));
                    if ($url !== '') {
                        $links[] = ['label' => $label, 'url' => $url];
                    }
                }
                $contentJson = json_encode([
                    'head' => $this->sval($content, 'head'),
                    'body' => $this->sval($content, 'body'),
                    'links' => array_values($links),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($type === 'list') {
                $content = is_array($block['content'] ?? null) ? $block['content'] : [];
                $items = [];
                foreach ((array) ($content['items'] ?? []) as $item) {
                    $value = trim((string) $item);
                    if ($value !== '') {
                        $items[] = $value;
                    }
                }
                $contentJson = json_encode([
                    'head' => $this->sval($content, 'head'),
                    'items' => array_values($items),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($type === 'list-grouped') {
                $content = is_array($block['content'] ?? null) ? $block['content'] : [];
                $items = [];
                foreach ((array) ($content['items'] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $itemTitle = trim((string) ($row['title'] ?? ''));
                    $itemBody = trim((string) ($row['body'] ?? ''));
                    if ($itemTitle !== '' || $itemBody !== '') {
                        $items[] = ['title' => $itemTitle, 'body' => $itemBody];
                    }
                }
                $contentJson = json_encode([
                    'head' => $this->sval($content, 'head'),
                    'items' => array_values($items),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                continue;
            }

            if (isset($existingById[$blockId])) {
                $queries[] = [
                    'sql' => 'UPDATE ms_article_blocks
                        SET msab_order = ?, msab_type = ?, msab_content = ?, msab_update_date = NOW()
                        WHERE msab_id = ? AND msab_msa_code = ? LIMIT 1',
                    'params' => [$order, $type, $contentJson, $blockId, $msaCode],
                ];
            } else {
                $queries[] = [
                    'sql' => 'INSERT INTO ms_article_blocks
                        (msab_msa_code, msab_order, msab_type, msab_content, msab_create_date, msab_update_date)
                        VALUES (?, ?, ?, ?, NOW(), NOW())',
                    'params' => [$msaCode, $order, $type, $contentJson],
                ];
            }
        }

        if ($postedBlocks !== []) {
            foreach ($existingById as $blockId => $existingBlock) {
                if (!isset($postedIds[$blockId])) {
                    $queries[] = [
                        'sql' => 'DELETE FROM ms_article_blocks WHERE msab_id = ? AND msab_msa_code = ? LIMIT 1',
                        'params' => [$blockId, $msaCode],
                    ];
                }
            }
        }

        $targetDir = $this->uploadRootForOrdinal((int) ($current['msa_ordinal'] ?? 0));
        foreach ($imageBlocks as $blockId => $block) {
            $order = (int) ($block['order'] ?? 0);
            if ($order <= 0) {
                $order = 1;
            }

            $bag = $files['files_block_' . $blockId] ?? null;
            $indices = [];

            foreach ($post as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                if (preg_match('/^block_' . preg_quote($blockId, '/') . '_img_(\d+)_(alt|existing)$/', $key, $matches)) {
                    $indices[(int) $matches[1]] = true;
                }
            }

            if (is_array($bag['name'] ?? null)) {
                $count = count($bag['name']);
                for ($i = 0; $i < $count; $i++) {
                    $indices[$i] = true;
                }
            }

            ksort($indices);
            $images = [];

            foreach (array_keys($indices) as $index) {
                $alt = trim((string) ($post['block_' . $blockId . '_img_' . $index . '_alt'] ?? ''));
                $existing = trim((string) ($post['block_' . $blockId . '_img_' . $index . '_existing'] ?? ''));
                $filename = '';

                $hasUpload = is_array($bag)
                    && (($bag['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)
                    && !empty($bag['tmp_name'][$index]);

                if ($hasUpload) {
                    try {
                        $filename = $this->saveUploadedImage(
                            (string) $bag['tmp_name'][$index],
                            (string) $bag['name'][$index],
                            $targetDir,
                            'body'
                        );
                    } catch (RuntimeException) {
                        $filename = '';
                    }
                }

                if ($filename === '') {
                    $filename = $existing;
                }

                if ($filename !== '') {
                    $images[] = ['filename' => $filename, 'alt' => $alt];
                }
            }

            if (isset($existingById[$blockId])) {
                $queries[] = [
                    'sql' => 'UPDATE ms_article_blocks
                        SET msab_order = ?, msab_type = ?, msab_images = ?, msab_update_date = NOW()
                        WHERE msab_id = ? AND msab_msa_code = ? LIMIT 1',
                    'params' => [
                        $order,
                        'image',
                        json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $blockId,
                        $msaCode,
                    ],
                ];
            } elseif ($images !== []) {
                $queries[] = [
                    'sql' => 'INSERT INTO ms_article_blocks
                        (msab_msa_code, msab_order, msab_type, msab_images, msab_create_date, msab_update_date)
                        VALUES (?, ?, ?, ?, NOW(), NOW())',
                    'params' => [
                        $msaCode,
                        $order,
                        'image',
                        json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ];
            }
        }

        $transaction = $this->exec->executeTransaction($queries);
        if (empty($transaction['status'])) {
            return ['ok' => false, 'errors' => [(string) ($transaction['message'] ?? 'Gagal update article.')]];
        }

        return ['ok' => true];
    }

    public function getById(int $id): ?array
    {
        $result = $this->exec->executeSelect(
            'SELECT * FROM ms_articles WHERE id = ? LIMIT 1',
            [$id],
            'row'
        );

        return is_array($result['data'] ?? null) ? $result['data'] : null;
    }

    public function getByCode(string $code): ?array
    {
        $result = $this->exec->executeSelect(
            'SELECT * FROM ms_articles WHERE msa_code = ? LIMIT 1',
            [$code],
            'row'
        );

        return is_array($result['data'] ?? null) ? $result['data'] : null;
    }

    public function getBlocksByCode(string $code): array
    {
        $result = $this->exec->executeSelect(
            'SELECT * FROM ms_article_blocks WHERE msab_msa_code = ? ORDER BY CAST(msab_order AS UNSIGNED) ASC, msab_id ASC',
            [$code],
            'all'
        );

        return is_array($result['data'] ?? null) ? $result['data'] : [];
    }

    public function toOldArray(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'msa_code' => (string) ($row['msa_code'] ?? ''),
            'msa_title' => (string) ($row['msa_title'] ?? ''),
            'msa_subtitle' => (string) ($row['msa_subtitle'] ?? ''),
            'msa_slug' => (string) ($row['msa_slug'] ?? ''),
            'msa_category' => (string) ($row['msa_category'] ?? ''),
            'msa_date' => (string) ($row['msa_date'] ?? date('Y-m-d')),
            'msa_meta_description' => (string) ($row['msa_meta_description'] ?? ''),
            'head_header_alt' => (string) ($row['msa_image_header_alt'] ?? ''),
            'head_header_url' => article_media_public_url((int) ($row['msa_ordinal'] ?? 0), (string) ($row['msa_image_header'] ?? '')),
        ];
    }
}

