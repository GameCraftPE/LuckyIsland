<?php


namespace GameCraftPE\li\utils\skin;

class PngSkin extends Skin
{
    /**
     * PngSkin constructor.
     * @param string $path
     * @param string $bytes
     */
    public function __construct($path, $bytes)
    {
        parent::__construct($path, $bytes);
    }


    /**
     * @return bool
     */
    final public function load()
    {
        if (!extension_loaded('gd') || $this->getType() != 1)
            return false;
        $img = @imagecreatefrompng($this->getPath());
        if (!$img)
            return false;
        $bytes = '';
        $l = (int)@getimagesize($this->getPath())[1];
        for ($y = 0; $y < $l; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $rgba = @imagecolorat($img, $x, $y);
                //This will never be 255
                $a = ((~((int)($rgba >> 24))) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        @imagedestroy($img);
        if ($this->setBytes($bytes))
            return true;
        return false;
    }


    final public function save()
    {
        if (!extension_loaded('gd') || !$this->ok || strtolower(pathinfo($this->getPath(false), PATHINFO_EXTENSION)) != 'png' || !is_dir(pathinfo($this->getPath(false), PATHINFO_DIRNAME)))
            return false;
        if (is_file($this->getPath(false)))
            @unlink($this->getPath(false));
        strlen($this->getBytes()) == 8192 ? $l = 32 : $l = 64;
        $img = @imagecreatetruecolor(64, $l);
        @imagealphablending($img, false);
        @imagesavealpha($img, true);
        $bytes = $this->getBytes();
        $i = 0;
        for ($y = 0; $y < $l; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $rgb = substr($bytes, $i, 4);
                $i += 4;
                $color = @imagecolorallocatealpha($img, ord($rgb{0}), ord($rgb{1}), ord($rgb{2}), (((~((int)ord($rgb{3}))) & 0xff) >> 1));
                @imagesetpixel($img, $x, $y, $color);
            }
        }
        if (@imagepng($img, $this->getPath(false)) && $this->getType() == 1) {
            @imagedestroy($img);
            return true;
        }
        @unlink($this->getPath(false));
        @imagedestroy($img);
        return false;
    }
}
