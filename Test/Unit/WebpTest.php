<?php

declare(strict_types=1);

namespace Kkkonrad\PageSpeed\Test\Unit;

use Kkkonrad\PageSpeed\Model\Webp;
use PHPUnit\Framework\TestCase;

class WebpTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kkwebp' . uniqid() . '/';
        mkdir($this->dir . 'a', 0777, true);
        $img = imagecreatetruecolor(400, 300);
        for ($i = 0; $i < 300; $i += 3) {
            imageline($img, 0, $i, 399, 299 - $i, imagecolorallocate($img, $i % 255, 80, 200));
        }
        imagejpeg($img, $this->dir . 'a/x.jpg', 95);
        $pal = imagecreate(10, 10);
        imagecolorallocate($pal, 255, 0, 0);
        imagepng($pal, $this->dir . 'a/p.png'); // palette PNG
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . 'a/*'));
        rmdir($this->dir . 'a');
        rmdir($this->dir);
    }

    public function testRewritesPlainAndEscapedUrlsOnly(): void
    {
        $html = '<img src="https://s/media/a/x.jpg">'
            . '<script>{"img":"https:\/\/s\/media\/a\/x.jpg"}</script>'
            . '<img src="https://s/media/a/missing.jpg">'
            . '<img src="https://s/media/a/x.jpg.webp">'
            . '<img src="https://s/media/../a/x.jpg">'
            . '<img src="https://other/media/a/x.jpg">';

        $out = (new Webp())->rewrite($html, 'https://s/media/', $this->dir);

        $this->assertSame(
            '<img src="https://s/media/a/x.jpg.webp">'
            . '<script>{"img":"https:\/\/s\/media\/a\/x.jpg.webp"}</script>'
            . '<img src="https://s/media/a/missing.jpg">'
            . '<img src="https://s/media/a/x.jpg.webp">'
            . '<img src="https://s/media/../a/x.jpg">'
            . '<img src="https://other/media/a/x.jpg">',
            $out
        );
        $this->assertSame('RIFF', substr(file_get_contents($this->dir . 'a/x.jpg.webp'), 0, 4));
    }

    public function testPalettePngConverts(): void
    {
        (new Webp())->convert($this->dir . 'a/p.png');
        $this->assertFileExists($this->dir . 'a/p.png.webp');
    }
}
