<?php

declare(strict_types=1);

require_once __DIR__ . '/string_utils.php';

function get_invoice_footer_limits(): array
{
    return [
        'notes_header' => [
            'max_chars' => 60,
        ],
        'notes' => [
            'max_chars' => 450,
            'max_paragraphs' => 3,
            'max_chars_per_paragraph' => 150,
        ],
        'closing_header' => [
            'max_chars' => 100,
        ],
        'closing_message' => [
            'max_chars' => 150,
            'max_paragraphs' => 1,
            'max_chars_per_paragraph' => 150,
        ],
    ];
}

function get_invoice_footer_defaults(): array
{
    return [
        'notes_header' => 'Notes',
        'notes' => implode("\n\n", [
            'Brill HEPA Filter eliminates 99.97% bacteria, virus, and harmful airborne particles with 1 Cycle of Clean Air every 1 Minute.',
            'Removing fine particles, bad odor and refreshes the air around an enclosed space.',
            'We recommend checking the HEPA filter every 6 months. Replacing it every 9-12 months or 10,000-15,000 km.',
        ]),
        'closing_header' => "Thank you for choosing\nBrill HEPA Filter.",
        'closing_message' => 'We appreciate your trust and are committed to providing the best product possible.',
    ];
}

function split_invoice_footer_paragraphs(string $text): array
{
    $normalized = preg_replace("/\r\n?/", "\n", trim($text));
    if (!is_string($normalized) || $normalized === '') {
        return [];
    }

    $parts = preg_split("/\n{2,}/", $normalized) ?: [];
    $paragraphs = [];
    foreach ($parts as $part) {
        $clean = trim($part);
        if ($clean !== '') {
            $paragraphs[] = $clean;
        }
    }

    return $paragraphs;
}

function validate_invoice_footer_content(string $notes, string $closingMessage): array
{
    $limits = get_invoice_footer_limits();
    $errors = [];

    $fields = [
        'notes' => [
            'label' => 'Notes',
            'value' => trim($notes),
        ],
        'closing_message' => [
            'label' => 'Closing message',
            'value' => trim($closingMessage),
        ],
    ];

    foreach ($fields as $fieldKey => $field) {
        $value = $field['value'];
        if ($value === '') {
            continue;
        }

        $fieldLimits = $limits[$fieldKey];
        if (app_string_length($value) > $fieldLimits['max_chars']) {
            $errors[] = sprintf(
                '%s maksimal %d karakter.',
                $field['label'],
                $fieldLimits['max_chars']
            );
        }

        $paragraphs = split_invoice_footer_paragraphs($value);
        if (count($paragraphs) > $fieldLimits['max_paragraphs']) {
            $errors[] = sprintf(
                '%s maksimal %d paragraf.',
                $field['label'],
                $fieldLimits['max_paragraphs']
            );
        }

        foreach ($paragraphs as $paragraph) {
            if (app_string_length($paragraph) > $fieldLimits['max_chars_per_paragraph']) {
                $errors[] = sprintf(
                    '%s maksimal %d karakter per paragraf.',
                    $field['label'],
                    $fieldLimits['max_chars_per_paragraph']
                );
                break;
            }
        }
    }

    return $errors;
}
