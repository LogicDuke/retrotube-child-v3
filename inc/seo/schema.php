<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_schema_itemlist')) {
    /**
     * Output lightweight ItemList schema for flipbox archives/grids.
     *
     * @param array<int, array<string, string>> $items
     * @param string $item_type Optional item type (default 'Thing', use 'Person' for models)
     */
    function tmw_schema_itemlist(array $items, string $item_type = 'Person'): void {
        if (empty($items)) {
            return;
        }

        $elements = [];
        $position = 1;

        foreach ($items as $item) {
            $url  = isset($item['url']) ? trim((string) $item['url']) : '';
            $name = isset($item['name']) ? trim((string) $item['name']) : '';

            if ($url === '' || $name === '') {
                continue;
            }

            $list_item = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'url'      => $url,
                'name'     => $name,
            ];

            // Add typed item for richer schema
            if ($item_type !== 'Thing') {
                $list_item['item'] = [
                    '@type' => $item_type,
                    'name'  => $name,
                    'url'   => $url,
                ];
            }

            $elements[] = $list_item;
        }

        if (empty($elements)) {
            return;
        }

        $data = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'numberOfItems'   => count($elements),
            'itemListElement' => $elements,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}
