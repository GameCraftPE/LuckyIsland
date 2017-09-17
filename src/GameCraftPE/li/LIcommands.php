<?php

namespace GameCraftPE\li;


use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\Player;

use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\nbt\tag\StringTag as Str;


class LIcommands{
    /** @var LImain */
    private $pg;


    public function __construct(LImain $plugin)
    {
        $this->pg = $plugin;
    }


    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool
    {
        if (!($sender instanceof Player) || !$sender->isOp()) {
            switch (strtolower(array_shift($args))):
                case 'quit':
                    if (!empty($args)) {
                        $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Usage: /li ' . TextFormat::GREEN . 'quit');
                        break;
                    }

                    if ($sender instanceof Player) {
                        foreach ($this->pg->arenas as $a) {
                            if ($a->closePlayer($sender, true))
                                break;
                        }
                    } else {
                        $sender->sendMessage('This command is only avaible in game');
                    }
                    break;


                default:
                    //No option found, usage
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Usage: /li [join|quit]');
                    break;


            endswitch;
            return true;
        }

        //Searchs for a valid option
        switch (strtolower(array_shift($args))):

            case 'create':
                /*
                                          _
                  ___  _ __   ___   __ _ | |_   ___
                 / __|| '__| / _ \ / _` || __| / _ \
                | (__ | |   |  __/| (_| || |_ |  __/
                 \___||_|    \___| \__,_| \__| \___|

                */
                if (!(count($args) > 0b11 && count($args) < 0b101)) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Usage: /li ' . TextFormat::GREEN . 'create [LIname] [slots] [countdown] [maxGameTime]');
                    break;
                }

                $fworld = $sender->getLevel()->getFolderName();
                $world = $sender->getLevel()->getName();

                //Checks if the world is default
                if ($sender->getServer()->getConfigString('level-name', 'world') == $world || $sender->getServer()->getDefaultLevel()->getName() == $world || $sender->getServer()->getDefaultLevel()->getFolderName() == $world) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'You can\'t create an arena in the default world');
                    unset($fworld, $world);
                    break;
                }

                //Checks if there is already an arena in the world
                foreach ($this->pg->arenas as $aname => $arena) {
                    if ($arena->getWorld() == $world) {
                        $sender->sendMessage(TextFormat::RED . '>' . TextFormat::RED . 'You can\'t create 2 arenas in the same world try:');
                        $sender->sendMessage(TextFormat::RED . '>' . TextFormat::WHITE . '/li list' . TextFormat::RED . ' for a list of arenas');
                        $sender->sendMessage(TextFormat::RED . '>' . TextFormat::WHITE . '/li delete' . TextFormat::RED . ' to delete an arena');
                        unset($fworld, $world);
                        break 2;
                    }
                }

                //Checks if there is already a join sign in the world
                foreach ($this->pg->signs as $loc => $name) {
                    if (explode(':', $loc)[3] == $world) {
                        $sender->sendMessage(TextFormat::RED . '>' . TextFormat::RED . 'You can\'t create an arena in the same world of a join sign:');
                        $sender->sendMessage(TextFormat::RED . '>' . TextFormat::WHITE . '/li signdelete' . TextFormat::RED . ' to delete signs');
                        unset($fworld, $world);
                        break 2;
                    }
                }

                //LI NAME
                $LIname = array_shift($args);
                if (!($LIname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $LIname) && strlen($LIname) < 0x10 && strlen($LIname) > 0b10)) {
                    $sender->sendMessage(TextFormat::WHITE . '>' . TextFormat::AQUA . '[LIname]' . TextFormat::RED . ' must consists of a-z 0-9 (min3-max15)');
                    unset($fworld, $world, $LIname);
                    break;
                }

                //Checks if the arena already exists
                if (array_key_exists($LIname, $this->pg->arenas)) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Arena with name: ' . TextFormat::WHITE . $LIname . TextFormat::RED . ' already exist');
                    unset($fworld, $world, $LIname);
                    break;
                }

                //ARENA SLOT
                $slot = array_shift($args);
                if (!($slot && is_numeric($slot) && is_int(($slot + 0)) && $slot < 0x33 && $slot > 1)) {
                    $sender->sendMessage(TextFormat::WHITE . '>' . TextFormat::AQUA . '[slots]' . TextFormat::RED . ' must be an integer >= 50 and >= 2');
                    unset($fworld, $world, $LIname, $slot);
                    break;
                }
                $slot += 0;

                //ARENA COUNTDOWN
                $countdown = array_shift($args);
                if (!($countdown && is_numeric($countdown) && is_int(($countdown + 0)) && $countdown > 0b1001 && $countdown < 0x12d)) {
                    $sender->sendMessage(TextFormat::WHITE . '>' . TextFormat::AQUA . '[countdown]' . TextFormat::RED . ' must be an integer <= 300 seconds (5 minutes) and >= 10');
                    unset($fworld, $world, $LIname, $slot, $countdown);
                    break;
                }
                $countdown += 0;

                //ARENA MAX EXECUTION TIME
                $maxtime = array_shift($args);
                if (!($maxtime && is_numeric($maxtime) && is_int(($maxtime + 0)) && $maxtime > 0x12b && $maxtime < 0x259)) {
                    $sender->sendMessage(TextFormat::WHITE . '>' . TextFormat::AQUA . '[maxGameTime]' . TextFormat::RED . ' must be an integer <= 600 (10 minutes) and >= 300');
                    unset($fworld, $world, $LIname, $slot, $countdown, $maxtime);
                    break;
                }
                $maxtime += 0;

                //ARENA LEVEL NAME
                if ($fworld == $world) {
                    $sender->sendMessage(TextFormat::WHITE . '>' . TextFormat::RED . 'Using the world were you are now: ' . TextFormat::AQUA . $world . TextFormat::RED . ' ,expected lag');
                } else {
                    $sender->sendMessage(TextFormat::WHITE . '>' . TextFormat::RED . 'There is a problem with the world name, try to restart your server');
                    $provider = $sender->getLevel()->getProvider();
                    if ($provider instanceof \pocketmine\level\format\generic\BaseLevelProvider) {
                        $provider->getLevelData()->LevelName = new Str('LevelName', $fworld);
                        $provider->saveLevelData();
                    }
                    unset($fworld, $world, $LIname, $slot, $countdown, $maxtime, $provider);
                    break;
                }

                //Air world generator
                $provider = $sender->getLevel()->getProvider();
                if ($this->pg->configs['world.generator.air'] && $provider instanceof \pocketmine\level\format\generic\BaseLevelProvider) {
                    $provider->getLevelData()->generatorName = new Str('generatorName', 'flat');
                    $provider->getLevelData()->generatorOptions = new Str('generatorOptions', '0;0;0');
                    $provider->saveLevelData();
                }

                $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::LIGHT_PURPLE . 'I\'m creating a backup of the world...teleporting to hub');

                //This is the "fake void"
                $last = 0x80;
                foreach ($sender->getLevel()->getChunks() as $chunk) {
                    for ($x = 0; $x < 0x10; $x++) {
                        for ($z = 0; $z < 0x10; $z++) {
                            for ($y = 0; $y < 0x7f; $y++) {
                                $block = $chunk->getBlockId($x, $y, $z);
                                if ($block !== 0 && $last > $y) {
                                    $last = $y;
                                    break;
                                }
                            }
                        }
                    }
                }
                $void = ($last - 1);

                $sender->teleport($sender->getServer()->getDefaultLevel()->getSpawnLocation());
                foreach ($sender->getServer()->getLevelByName($world)->getPlayers() as $p)
                    $p->close('', 'Please re-join');
                $sender->getServer()->unloadLevel($sender->getServer()->getLevelByName($world));

                //From here @vars are: $LIname , $slot , $world
                // { TAR.GZ
                @mkdir($this->pg->getDataFolder() . 'arenas/' . $LIname, 0755);
                $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $LIname . '/' . $world . '.tar');
                $tar->startBuffering();
                $tar->buildFromDirectory(realpath($sender->getServer()->getDataPath() . 'worlds/' . $world));
                if ($this->pg->configs['world.compress.tar'])
                    $tar->compress(\Phar::GZ);
                $tar->stopBuffering();
                if ($this->pg->configs['world.compress.tar']) {
                    $tar = null;
                    @unlink($this->pg->getDataFolder() . 'arenas/' . $LIname . '/' . $world . '.tar');
                }
                unset($tar);
                $sender->getServer()->loadLevel($world);
                // END TAR.GZ }

                //LIarena object
                $this->pg->arenas[$LIname] = new LIarena($this->pg, $LIname, $slot, $world, $countdown, $maxtime, $void);
                $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'Arena: ' . TextFormat::DARK_GREEN . $LIname . TextFormat::GREEN . ' created successfully!');
                $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'Now set spawns with ' . TextFormat::WHITE . '/li setspawn [slot]');
                $sender->teleport($sender->getServer()->getLevelByName($world)->getSpawnLocation());
                unset($fworld, $world, $LIname, $slot, $countdown, $maxtime, $provider, $void);
                break;


            case 'setspawn':
                /*
                            _    ____
                 ___   ___ | |_ / ___|  _ __   __ _ __      __ _ __
                / __| / _ \| __|\___ \ | '_ \ / _` |\ \ /\ / /| '_ \
                \__ \|  __/| |_  ___) || |_) | (_| | \ /  / / | | | |
                |___/ \___| \__||____/ | .__/ \__,_|  \_/\_/  |_| |_|
                                       |_|

                */
                if (count($args) != 1) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Usage: /li ' . TextFormat::GREEN . 'setspawn [slot]');
                    break;
                }

                $LIname = '';
                foreach ($this->pg->arenas as $name => $arena) {
                    if ($arena->getWorld() == $sender->getLevel()->getName()) {
                        $LIname = $name;
                        break;
                    }
                }
                if (!($LIname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $LIname) && strlen($LIname) < 0x10 && strlen($LIname) > 0b10 && array_key_exists($LIname, $this->pg->arenas))) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Arena not found here, try ' . TextFormat::WHITE . '/li create');
                    unset($LIname);
                    break;
                }

                $slot = array_shift($args);
                if (!($slot && is_numeric($slot) && is_int(($slot + 0)) && $slot < 0x33 && $slot > 0)) {
                    $sender->sendMessage(TextFormat::WHITE . '>' . TextFormat::AQUA . '[slot]' . TextFormat::RED . ' must be an integer <= than 50 and >= 1');
                    unset($LIname, $slot);
                    break;
                }
                $slot += 0;

                if ($sender->getLevel()->getName() == $this->pg->arenas[$LIname]->getWorld()) {
                    if ($this->pg->arenas[$LIname]->setSpawn($sender, $slot)) {
                        $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'New spawn: ' . TextFormat::WHITE . $slot . TextFormat::GREEN . ' In arena: ' . TextFormat::WHITE . $LIname);
                        if ($this->pg->arenas[$LIname]->checkSpawns())
                            $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'I found all the spawns for Arena: ' . TextFormat::WHITE . $LIname . TextFormat::GREEN . ', now you can create a join sign!');
                    }
                }
                break;


            case 'list':
                /*
                  _   _         _
                 | | (_)  ___  | |_
                 | | | | / __| | __|
                 | | | | \__ \ | |_
                 |_| |_| |___/  \__|

                */
                if (count($this->pg->arenas) > 0) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'Loaded arenas:');
                    foreach ($this->pg->arenas as $key => $val) {
                        $sender->sendMessage(TextFormat::BLACK . '> ' . TextFormat::YELLOW . $key . TextFormat::AQUA . ' [' . $val->getSlot(true) . '/' . $val->getSlot() . ']' . TextFormat::DARK_GRAY . ' => ' . TextFormat::GREEN . $val->getWorld());
                    }
                } else {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'There aren\'t loaded arenas, create one with ' . TextFormat::WHITE . '/li create');
                }
                break;


            case 'delete':
                /*
                     _        _        _
                  __| |  ___ | |  ___ | |_   ___
                 / _` | / _ \| | / _ \| __| / _ \
                | (_| ||  __/| ||  __/| |_ |  __/
                 \__,_| \___||_| \___| \__| \___|

                */
                if (count($args) != 1) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Usage: /li ' . TextFormat::GREEN . 'delete [LIname]');
                    break;
                }

                $LIname = array_shift($args);
                if (!($LIname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $LIname) && strlen($LIname) < 0x10 && strlen($LIname) > 0b10 && array_key_exists($LIname, $this->pg->arenas))) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Arena: ' . TextFormat::WHITE . $LIname . TextFormat::RED . ' doesn\'t exist');
                    unset($LIname);
                    break;
                }

                if (!(is_dir($this->pg->getDataFolder() . 'arenas/' . $LIname) && is_file($this->pg->getDataFolder() . 'arenas/' . $LIname . '/settings.yml'))) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Arena files doesn\'t exists');
                    unset($LIname);
                    break;
                }

                $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'Please wait, this can take a bit');
                $this->pg->arenas[$LIname]->stop(true);
                foreach ($this->pg->signs as $loc => $name) {
                    if ($LIname == $name) {
                        $ex = explode(':', $loc);
                        if ($sender->getServer()->loadLevel($ex[0b11])) {
                            $block = $sender->getServer()->getLevelByName($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
                            if ($block->getId() == 0x3f || $block->getId() == 0x44)
                                $sender->getServer()->getLevelByName($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), Block::get(0));
                        }
                    }
                }
                $this->pg->setSign($LIname, 0, 0, 0, 'world', true, false);
                unset($this->pg->arenas[$LIname]);

                foreach (scandir($this->pg->getDataFolder() . 'arenas/' . $LIname) as $file) {
                    if ($file != '.' && $file != '..' && is_file($this->pg->getDataFolder() . 'arenas/' . $LIname . '/' . $file)) {
                        @unlink($this->pg->getDataFolder() . 'arenas/' . $LIname . '/' . $file);
                    }
                }
                @rmdir($this->pg->getDataFolder() . 'arenas/' . $LIname);
                $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'Arena: ' . TextFormat::DARK_GREEN . $LIname . TextFormat::GREEN . ' Deleted !');
                unset($LIname, $loc, $name, $ex, $block);
                break;


            case 'signdelete':
                /*
                      _                ____         _        _
                 ___ (_)  __ _  _ __  |  _ \   ___ | |  ___ | |_   ___
                / __|| | / _` || '_ \ | | | | / _ \| | / _ \| __| / _ \
                \__ \| || (_| || | | || |_| ||  __/| ||  __/| |_ |  __/
                |___/|_| \__, ||_| |_||____/  \___||_| \___| \__| \___|
                         |___/

                */
                if (count($args) != 1) {
                    $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Usage: /li ' . TextFormat::GREEN . 'signdelete [LIname|all]');
                    break;
                }

                $LIname = array_shift($args);
                if (!array_key_exists($LIname, $this->pg->arenas)) {
                    if ($LIname == 'all') {
                        //Deleting LI signs blocks
                        foreach ($this->pg->signs as $loc => $name) {
                            $ex = explode(':', $loc);
                            if ($sender->getServer()->loadLevel($ex[0b11])) {
                                $block = $sender->getServer()->getLevelByName($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
                                if ($block->getId() == 0x3f || $block->getId() == 0x44)
                                    $sender->getServer()->getLevelByName($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), Block::get(0));
                            }
                        }
                        //Deleting signs from db & array
                        $this->pg->setSign($LIname, 0, 0, 0, 'world', true);
                        $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'Deleted all LI signs !');
                        unset($LIname, $loc, $name, $ex, $block);
                    } else {
                        $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Arena: ' . TextFormat::WHITE . $LIname . TextFormat::RED . ' doesn\'t exist');
                        unset($LIname);
                    }
                    break;
                }
                $this->pg->arenas[$LIname]->stop(true);
                foreach ($this->pg->signs as $loc => $name) {
                    if ($LIname == $name) {
                        $ex = explode(':', $loc);
                        if ($sender->getServer()->loadLevel($ex[0b11])) {
                            $block = $sender->getServer()->getLevelByName($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
                            if ($block->getId() == 0x3f || $block->getId() == 0x44)
                                $sender->getServer()->getLevelByName($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), Block::get(0));
                        }
                    }
                }
                $this->pg->setSign($LIname, 0, 0, 0, 'world', true, false);
                $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'Deleted signs for arena: ' . TextFormat::DARK_GREEN . $LIname);
                unset($LIname, $loc, $name, $ex, $block);
                break;


            default:
                //No option found, usage
                $sender->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Usage: /li [create|setspawn|list|delete|signdelete]');
                break;


        endswitch;
        return true;
    }
}
