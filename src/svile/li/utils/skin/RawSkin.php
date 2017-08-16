<?php


namespace GameCraftPE\li\utils\skin;

class RawSkin extends Skin
{
    /**
     * RawSkin constructor.
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
        if ($this->getType() != 2)
            return false;
        $bytes = @zlib_decode(@file_get_contents($this->getPath()));
        if ($this->setBytes($bytes))
            return true;
        return false;
    }


    final public function save()
    {
        if (!$this->ok || strtolower(pathinfo($this->getPath(false), PATHINFO_EXTENSION)) != 'skin' || !is_dir(pathinfo($this->getPath(false), PATHINFO_DIRNAME)))
            return false;
        if (is_file($this->getPath(false)))
            @unlink($this->getPath(false));
        @file_put_contents($this->getPath(false), @zlib_encode($this->getBytes(), ZLIB_ENCODING_DEFLATE, 9));
        if ($this->getType() == 2)
            return true;
        @unlink($this->getPath(false));
        return false;
    }
}
