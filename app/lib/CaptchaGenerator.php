<?php
declare(strict_types=1);

session_name('MCCSID');
session_start();
if (!isset($_SESSION['started'])) {
    session_regenerate_id();
    $_SESSION['started'] = true;
}

/**
 *
 */
class CaptchaGenerator
{
    protected string $bgColor = 'C3C3C3';

    /**
     * @param string $bgColor
     */
    public function __construct(string $bgColor = '')
    {
        if ($bgColor !== '') {
            $this->bgColor = $bgColor;
        }
        $this->renderCaptchaImage();
    }

    /**
     * @return void
     */
    private function renderCaptchaImage(): void
    {
        $bg_hex    = $this->parseBgColor();
        $distort  = rand(80, 120) / 100;
        $distort2 = rand(80, 120) / 100;
        $f_x      = (int)round(75 * $distort);
        $f_y      = (int)round(25 * $distort);
        $s_x      = (int)round(175 * $distort2);
        $s_y      = (int)round(70 * $distort2);
        $first    = imagecreatetruecolor($f_x, $f_y);
        $second   = imagecreatetruecolor($s_x, $s_y);
        $white    = imagecolorallocate($first, $bg_hex[0], $bg_hex[1], $bg_hex[2]);
        $black    = imagecolorallocate($first, 0, 0, 0);
        $red         = imagecolorallocate($first, 255, 0, 0);
        imagefill($first, 0, 0, $white);
        $points = [
            [10, $f_x - 10],
            [5, $f_y - 5],
            [10, $f_x - 10],
            [5, $f_y - 5],
            [10, $f_x - 10],
            [5, $f_y - 5],
            [10, $f_x - 10],
            [5, $f_y - 5],
            [10, $f_x - 10],
            [5, $f_y - 5],
        ];
        for ($i = 0; $i <= 2; $i++) {
            imagefilledpolygon($first, $points, $red);
        }
        imagestring($first, 4, rand(0, (int)($f_x / 3)), rand(0, (int)($f_y / 2.5)), $_SESSION['captcha'], $black);
        imagecopyresized($second, $first, 0, 0, 0, 0, $s_x, $s_y, $f_x, $f_y);
        imagedestroy($first);
        $red           = imagecolorallocate($second, 255, 0, 0);
        $green         = imagecolorallocate($second, 0, 128, 0);
        $blue          = imagecolorallocate($second, 0, 0, 255);
        $random_pixels = ceil($s_x * $s_y / 100);
        for ($i = 0; $i < $random_pixels; $i++) {
            $locx = rand(0, $s_x - 1);
            $locy = rand(0, $s_y - 1);
            imagesetpixel($second, $locx, $locy, $red);
        }
        for ($i = 0; $i < $random_pixels; $i++) {
            $locx = rand(0, $s_x - 1);
            $locy = rand(0, $s_y - 1);
            imagesetpixel($second, $locx, $locy, $green);
        }
        for ($i = 0; $i < $random_pixels; $i++) {
            $locx = rand(0, $s_x - 1);
            $locy = rand(0, $s_y - 1);
            imagesetpixel($second, $locx, $locy, $blue);
        }
        $randcolor = imagecolorallocate($second, rand(100, 255), rand(100, 255), rand(100, 255));
        for ($i = 0; $i < 5; $i++) {
            imageline($second, rand(0, $s_x), rand(0, $s_y), rand(0, $s_x), rand(0, $s_y), $randcolor);
            $randcolor = imagecolorallocate($second, rand(100, 255), rand(100, 255), rand(100, 255));
        }
        @header('Content-Type: image/png');
        $finished = imagerotate($second, rand(0, 15) - 7.5, $bg_hex[2] * 65536 + $bg_hex[1] * 256 + $bg_hex[0]);
        imagedestroy($second);
        imagepng($finished);
        imagedestroy($finished);
    }

    /**
     * @return array|int[]
     */
    private function parseBgColor(): array
    {
        $hexdec = '0-9abcdef';
        if (!preg_match('/^([' . $hexdec . ']{3}|[' . $hexdec . ']{6})$/ims', $this->bgColor)) {
            return [0, 0, 0];
        }
        if (strlen($this->bgColor) == 6) {
            $p1 = $this->bgColor[0] . $this->bgColor[1];
            $p2 = $this->bgColor[2] . $this->bgColor[3];
            $p3 = $this->bgColor[4] . $this->bgColor[5];
        } elseif (strlen($this->bgColor) == 3) {
            $p1 = $this->bgColor[0] . $this->bgColor[0];
            $p2 = $this->bgColor[1] . $this->bgColor[1];
            $p3 = $this->bgColor[2] . $this->bgColor[2];
        } else {
            return [0, 0, 0];
        }
        return [hexdec($p1), hexdec($p2), hexdec($p3)];
    }
}

$captcha = new CaptchaGenerator();
