<?php
if (!defined('ABSPATH')) {
    exit;
}

function tmw_a11y_social_platform_label($href) {
    if (!is_string($href) || $href === '') {
        return '';
    }

    $normalized = strtolower($href);

    if (strpos($normalized, 'facebook.com') !== false) {
        return 'Facebook';
    }

    if (strpos($normalized, 'instagram.com') !== false) {
        return 'Instagram';
    }

    if (strpos($normalized, 'reddit.com') !== false) {
        return 'Reddit';
    }

    if (strpos($normalized, 'twitter.com') !== false || strpos($normalized, 'x.com') !== false) {
        return 'Twitter';
    }

    return '';
}

function tmw_a11y_inject_top_bar_social_labels() {
    ?>
    <script>
        (function () {
            const links = document.querySelectorAll('.top-bar .social-share a[target="_blank"]');
            if (!links.length) {
                return;
            }

            const platformFromHref = (href) => {
                const normalized = String(href || '').toLowerCase();
                if (normalized.includes('facebook.com')) {
                    return 'Facebook';
                }
                if (normalized.includes('instagram.com')) {
                    return 'Instagram';
                }
                if (normalized.includes('reddit.com')) {
                    return 'Reddit';
                }
                if (normalized.includes('twitter.com') || normalized.includes('x.com')) {
                    return 'Twitter';
                }
                return '';
            };

            links.forEach((link) => {
                const platform = platformFromHref(link.getAttribute('href'));
                if (platform && !link.getAttribute('aria-label')) {
                    link.setAttribute('aria-label', `${platform} (opens in a new tab)`);
                }

                const relTokens = String(link.getAttribute('rel') || '')
                    .split(/\s+/)
                    .filter(Boolean);
                let relChanged = false;
                ['noopener', 'noreferrer'].forEach((token) => {
                    if (!relTokens.includes(token)) {
                        relTokens.push(token);
                        relChanged = true;
                    }
                });

                if (relChanged) {
                    link.setAttribute('rel', relTokens.join(' '));
                }
            });
        })();
    </script>
    <?php
}
add_action('wp_footer', 'tmw_a11y_inject_top_bar_social_labels', 100);

function tmw_a11y_fix_more_videos_links($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped);

    if (!$loaded) {
        libxml_use_internal_errors($previous);
        return $html;
    }

    $xpath = new DOMXPath($dom);
    $links = $xpath->query("//a[contains(concat(' ', normalize-space(@class), ' '), ' more-videos ') and contains(concat(' ', normalize-space(@class), ' '), ' label ')]");

    if ($links instanceof DOMNodeList) {
        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }

            $href = html_entity_decode($link->getAttribute('href'), ENT_QUOTES, 'UTF-8');
            $label = 'More videos';

            if ($href !== '') {
                $parts = wp_parse_url($href);
                $query = $parts['query'] ?? '';
                parse_str($query, $q);
                $filter = $q['filter'] ?? '';

                if ($filter === 'latest') {
                    $label = 'More latest videos';
                } elseif ($filter === 'random') {
                    $label = 'More random videos';
                }
            }

            if (!$link->hasAttribute('aria-label')) {
                $link->setAttribute('aria-label', $label);
            }

            $text = trim($link->textContent);
            if ($text === '') {
                $span = $dom->createElement('span', $label);
                $span->setAttribute('class', 'screen-reader-text');
                $link->appendChild($span);
            }
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        libxml_use_internal_errors($previous);
        return $html;
    }

    $updated = '';
    foreach ($body->childNodes as $child) {
        $updated .= $dom->saveHTML($child);
    }

    libxml_use_internal_errors($previous);

    return $updated !== '' ? $updated : $html;
}
