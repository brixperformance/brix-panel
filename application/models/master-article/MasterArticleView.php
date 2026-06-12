<?php

declare(strict_types=1);

require_once __DIR__ . '/../Execute.php';

final class MasterArticleView
{
    private Execute $exec;

    public function __construct(array $config)
    {
        $this->exec = new Execute($config);
    }

    public function countArticles(string $search = ''): array
    {
        $sql = 'SELECT COUNT(*) FROM ms_articles WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (msa_title LIKE ? OR msa_code LIKE ? OR msa_slug LIKE ?)';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }

        return $this->exec->executeSelect($sql, $params, 'one');
    }

    public function getArticles(string $search = '', int $offset = 0, int $limit = 10): array
    {
        $sql = '
            SELECT
                msa_code,
                msa_title,
                msa_subtitle,
                msa_slug,
                msa_category,
                msa_date,
                COALESCE(msa_views, 0) AS msa_views,
                msa_flag,
                msa_meta_description
            FROM ms_articles
            WHERE 1=1
        ';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (msa_title LIKE ? OR msa_code LIKE ? OR msa_slug LIKE ?)';
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }

        $sql .= ' ORDER BY msa_date DESC, msa_code DESC LIMIT ' . max(0, $offset) . ', ' . max(1, $limit);

        return $this->exec->executeSelect($sql, $params, 'all');
    }
}

