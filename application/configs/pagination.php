<?php

declare(strict_types=1);

function build_pagination_links(int $page, int $totalPages): array
{
    $page = max(1, $page);
    $totalPages = max(1, $totalPages);

    $pagination = [];
    $pagination[] = [
        'label' => '<<',
        'page' => max(1, $page - 1),
        'active' => false,
        'disabled' => $page === 1,
    ];

    if ($totalPages <= 3) {
        for ($i = 1; $i <= $totalPages; $i++) {
            $pagination[] = [
                'label' => (string) $i,
                'page' => $i,
                'active' => $i === $page,
                'disabled' => false,
            ];
        }
    } elseif ($page <= 2) {
        for ($i = 1; $i <= 3; $i++) {
            $pagination[] = [
                'label' => (string) $i,
                'page' => $i,
                'active' => $i === $page,
                'disabled' => false,
            ];
        }
        $pagination[] = ['label' => '...', 'page' => null, 'active' => false, 'disabled' => false];
    } elseif ($page >= $totalPages - 1) {
        $pagination[] = ['label' => '...', 'page' => null, 'active' => false, 'disabled' => false];
        for ($i = $totalPages - 2; $i <= $totalPages; $i++) {
            $pagination[] = [
                'label' => (string) $i,
                'page' => $i,
                'active' => $i === $page,
                'disabled' => false,
            ];
        }
    } else {
        $pagination[] = ['label' => '...', 'page' => null, 'active' => false, 'disabled' => false];
        $pagination[] = ['label' => (string) ($page - 1), 'page' => $page - 1, 'active' => false, 'disabled' => false];
        $pagination[] = ['label' => (string) $page, 'page' => $page, 'active' => true, 'disabled' => false];
        $pagination[] = ['label' => (string) ($page + 1), 'page' => $page + 1, 'active' => false, 'disabled' => false];
        $pagination[] = ['label' => '...', 'page' => null, 'active' => false, 'disabled' => false];
    }

    $pagination[] = [
        'label' => '>>',
        'page' => min($totalPages, $page + 1),
        'active' => false,
        'disabled' => $page === $totalPages,
    ];

    return $pagination;
}
