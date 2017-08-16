<?php

namespace GameCraftPE\li\utils;


use pocketmine\block\Glass as PmGlass;


class Glass extends PmGlass
{
    public function canPassThrough()
    {
        return true;
    }
}
