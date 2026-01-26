<?php
if (!defined('ABSPATH')) {
    exit;
}

$parent_template = trailingslashit(get_template_directory()) . 'template-parts/content-share-buttons.php';
if (!file_exists($parent_template)) {
    return;
}

ob_start();
include $parent_template;
$share_html = ob_get_clean();

if (!is_string($share_html) || $share_html === '') {
    echo $share_html;
    return;
}

$previous = libxml_use_internal_errors(true);

$dom = new DOMDocument('1.0', 'UTF-8');
$wrapped = '<!DOCTYPE html><html><body>' . $share_html . '</body></html>';
$loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped);

if (!$loaded) {
    libxml_use_internal_errors($previous);
    echo $share_html;
    return;
}

$xpath = new DOMXPath($dom);
$links = $xpath->query("//div[@id='video-share']//a");

if ($links instanceof DOMNodeList) {
    foreach ($links as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }

        $href = strtolower($link->getAttribute('href'));
        $label = '';

        if (strpos($href, 'facebook.com') !== false) {
            $label = 'Share this page on Facebook';
        } elseif (strpos($href, 'twitter.com') !== false || strpos($href, 'x.com') !== false) {
            $label = 'Share this page on Twitter';
        } elseif (strpos($href, 'linkedin.com') !== false) {
            $label = 'Share this page on LinkedIn';
        } elseif (strpos($href, 'reddit.com') !== false) {
            $label = 'Share this page on Reddit';
        } elseif (strpos($href, 'mailto:') === 0) {
            $label = 'Share this page via email';
        }

        if ($label !== '') {
            $link->setAttribute('aria-label', $label);
            $link->setAttribute('title', $label);
        }
    }
}

$body = $dom->getElementsByTagName('body')->item(0);
if (!$body) {
    libxml_use_internal_errors($previous);
    echo $share_html;
    return;
}

$updated = '';
foreach ($body->childNodes as $child) {
    $updated .= $dom->saveHTML($child);
}

libxml_use_internal_errors($previous);

echo $updated !== '' ? $updated : $share_html;
