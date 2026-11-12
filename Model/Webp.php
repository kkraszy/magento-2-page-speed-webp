<?php

declare(strict_types=1);

namespace Kkkonrad\PageSpeed\Model;

/**
 * Rewrites media image URLs in HTML to WebP copies, writing each copy next to its
 * original (`a/b.jpg` -> `a/b.jpg.webp`) the first time a page needs it.
 *
 * The <img> stays an <img> with only its URL changed - no <picture>. Hyvä's product cards
 * and gallery swap `src` from Alpine, and a <source> would silently win over that.
 * Browsers without WebP (Safari < 14, 2020) get broken images; none are in use any more.
 */
class Webp
{
    public const QUALITY = 80;
    /** ponytail: conversion is inline; past this budget a page keeps JPEGs until it is next rendered */
    public const BUDGET_SECONDS = 2.0;

    private float $spent = 0.0;

    /**
     * @param string $mediaUrl e.g. https://shop/media/
     * @param string $mediaDir absolute pub/media path
     */
    public function rewrite(string $html, string $mediaUrl, string $mediaDir): string
    {
        $mediaDir = rtrim($mediaDir, '/') . '/';
        // Plain URLs and their JSON-escaped form (https:\/\/shop\/media\/...) in gallery data
        foreach ([$mediaUrl, str_replace('/', '\/', $mediaUrl)] as $prefix) {
            $html = (string)preg_replace_callback(
                '~' . preg_quote($prefix, '~') . '([^"\'\s<>()?#&]+?\.(?:jpe?g|png))(?![\w.])~i',
                function (array $m) use ($prefix, $mediaDir) {
                    $path = str_replace('\/', '/', $m[1]);
                    return $this->convert($mediaDir . $path) ? $m[0] . '.webp' : $m[0];
                },
                $html
            );
        }
        return $html;
    }

    /** True when a WebP copy of $file exists (made now if needed) and is smaller. */
    public function convert(string $file): bool
    {
        if (str_contains($file, '..') || !is_file($file)) {
            return false;
        }
        $webp = $file . '.webp';
        if (is_file($webp) && filemtime($webp) >= filemtime($file)) {
            return filesize($webp) < filesize($file);
        }
        if ($this->spent > self::BUDGET_SECONDS) {
            return false;
        }

        $start = microtime(true);
        $image = preg_match('/\.png$/i', $file) ? @imagecreatefrompng($file) : @imagecreatefromjpeg($file);
        if (!$image) {
            return false;
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image); // imagewebp() rejects palette PNGs
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        // Written under a temp name and renamed, so a parallel request never serves half a file
        $tmp = $webp . '.' . getmypid() . '.tmp';
        $ok = @imagewebp($image, $tmp, self::QUALITY) && rename($tmp, $webp);
        imagedestroy($image);
        @unlink($tmp);
        $this->spent += microtime(true) - $start;

        // A larger WebP stays on disk as the "already tried" marker but is not used
        return $ok && filesize($webp) < filesize($file);
    }
}
