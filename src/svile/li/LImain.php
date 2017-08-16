<?php

namespace GameCraftPE\li;


use pocketmine\plugin\PluginBase;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag as Compound;
use pocketmine\nbt\tag\StringTag as Str;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\tile\Sign;
use pocketmine\math\Vector3;


class LImain extends PluginBase
{
    /** Plugin Version */
    const LI_VERSION = '0.6dev';

    /** @var LIcommands */
    private $commands;
    /** @var LIarena[] */
    public $arenas = [];
    /** @var array */
    public $signs = [];
    /** @var array */
    public $configs;
    /** @var array */
    public $lang;
    /** @var \SQLite3 */
    private $db;
    /** @var \GameCraftPE\li\utils\LIeconomy */
    public $economy;

    public function onLoad()
    {
        //Sometimes the silence operator " @ " doesn't works and the server crash, this is better.Don't ask me why, i just know that.
        if (!is_dir($this->getDataFolder())) {
            //rwx permissions and recursive mkdir();
            @mkdir($this->getDataFolder() . "\x61\x72\x65\x6e\x61\x73", 0755, true);
        }

        //This changes worlds NBT name with folders ones to avoid problems
        try {
            foreach (scandir($this->getServer()->getDataPath() . "\x77\x6f\x72\x6c\x64\x73") as $worldDir) {
                if (is_dir($this->getServer()->getDataPath() . "\x77\x6f\x72\x6c\x64\x73\x2f" . $worldDir) && is_file($this->getServer()->getDataPath() . "\x77\x6f\x72\x6c\x64\x73\x2f" . $worldDir . "\x2f\x6c\x65\x76\x65\x6c\x2e\x64\x61\x74")) {
                    $nbt = new NBT(NBT::BIG_ENDIAN);
                    $nbt->readCompressed(file_get_contents($this->getServer()->getDataPath() . "\x77\x6f\x72\x6c\x64\x73\x2f" . $worldDir . "\x2f\x6c\x65\x76\x65\x6c\x2e\x64\x61\x74"));
                    $levelData = $nbt->getData();
                    if (array_key_exists("\x44\x61\x74\x61", $levelData) && $levelData["\x44\x61\x74\x61"] instanceof Compound) {
                        $levelData = $levelData["\x44\x61\x74\x61"];
                        if (array_key_exists("\x4c\x65\x76\x65\x6c\x4e\x61\x6d\x65", $levelData) && $levelData["\x4c\x65\x76\x65\x6c\x4e\x61\x6d\x65"] != $worldDir) {
                            $levelData["\x4c\x65\x76\x65\x6c\x4e\x61\x6d\x65"] = new Str("\x4c\x65\x76\x65\x6c\x4e\x61\x6d\x65", $worldDir);
                            $nbt->setData(new Compound('', ["\x44\x61\x74\x61" => $levelData]));
                            file_put_contents($this->getServer()->getDataPath() . "\x77\x6f\x72\x6c\x64\x73\x2f" . $worldDir . "\x2f\x6c\x65\x76\x65\x6c\x2e\x64\x61\x74", $nbt->writeCompressed());
                        }
                        unset($worldDir, $levelData, $nbt);
                    } else {
                        $this->getLogger()->critical('There is a problem with the "level.dat" of the world: §f' . $worldDir);
                        unset($worldDir, $levelData, $nbt);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->critical($e->getMessage() . ' in §b' . $e->getFile() . '§c on line §b' . $e->getLine());
        }
    }


    public function onEnable()
    {
        if ($this->getDescription()->getVersion() != self::LI_VERSION)
            $this->getLogger()->critical(@gzinflate(@base64_decode('C8lILUpVyCxWSFQoKMpPyknNVSjPLMlQKMlIVSjIKU3PzFMoSy0qzszPAwA=')));

        //Creates the database that is needed to store signs info
        try {
            if (!is_file($this->getDataFolder() . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62")) {
                $this->db = new \SQLite3($this->getDataFolder() . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            } else {
                $this->db = new \SQLite3($this->getDataFolder() . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62", SQLITE3_OPEN_READWRITE);
            }
            $this->db->exec("CREATE TABLE IF NOT EXISTS signs (arena TEXT PRIMARY KEY COLLATE NOCASE, x INTEGER , y INTEGER , z INTEGER, world TEXT);");
        } catch (\Exception $e) {
            $this->getLogger()->critical($e->getMessage() . ' in §b' . $e->getFile() . '§c on line §b' . $e->getLine());
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }

        //Config file...
        $v = ((new Config($this->getDataFolder() . 'LI_configs.yml', CONFIG::YAML))->get('CONFIG_VERSION', '1st'));
        if ($v != '1st' && $v != self::LI_VERSION) {
            $this->getLogger()->notice('You are using old configs, deleting them.Make sure to delete old arenas if aren\'t working');
            @unlink($this->getDataFolder() . 'LI_configs.yml');
            @unlink($this->getDataFolder() . 'LI_lang.yml');
            $this->saveResource('LI_configs.yml', true);
        } elseif ($v == '1st') {
            $this->saveResource('LI_configs.yml', true);
        }
        unset($v);

        //Config files: /LI_configs.yml /LI_lang.yml & for arenas: /arenas/LIname/settings.yml

        /*
                                       __  _                                   _
                   ___   ___   _ __   / _|(_)  __ _  ___     _   _  _ __ ___  | |
                  / __| / _ \ | '_ \ | |_ | | / _` |/ __|   | | | || '_ ` _ \ | |
                 | (__ | (_) || | | ||  _|| || (_| |\__ \ _ | |_| || | | | | || |
                  \___| \___/ |_| |_||_|  |_| \__, ||___/(_) \__, ||_| |_| |_||_|
                                              |___/          |___/
        */
        $this->configs = new Config($this->getDataFolder() . 'LI_configs.yml', CONFIG::YAML, [
            'CONFIG_VERSION' => self::LI_VERSION,
            'banned.commands.while.in.game' => array('/hub', '/lobby', '/spawn', '/tpa', '/tp', '/tpaccept', '/back', '/home', '/f', '/kill'),
            'start.when.full' => true,
            'needed.players.to.run.countdown' => 1,
            'join.max.health' => 20,
            'join.health' => 20,
            'damage.cancelled.causes' => [0, 3, 4, 8, 12, 15],
            'drops.on.death' => false,
            'player.drop.item' => true,
            'chest.refill' => true,
            'chest.refill.rate' => 0xf0,
            'no.pvp.countdown' => 20,
            'death.spectator' => true,
            'spectator.quit.item' => '120:0',
            'reward.winning.players' => false,
            'reward.value' => 100,
            'reward.command' => '/',
            '1st_line' => '§l§c[§bLI§c]',
            '2nd_line' => '§l§e{LINAME}',
            'sign.tick' => false,
            'sign.knockBack' => true,
            'knockBack.radius.from.sign' => 1,
            'knockBack.intensity' => 0b10,
            'knockBack.follow.sign.direction' => false,
            'always.spawn.in.defaultLevel' => true,
            'clear.inventory.on.respawn&join' => false,//many people don't know on respawn means also on join
            'clear.inventory.on.arena.join' => true,
            'clear.effects.on.respawn&join' => false,//many people don't know on respawn means also on join
            'clear.effects.on.arena.join' => true,
            'world.generator.air' => true,
            'world.compress.tar' => false,
            'world.reset.from.tar' => true
        ]);
        $this->configs = $this->configs->getAll();

        /*
                  _                                                   _
                 | |   __ _   _ __     __ _       _   _   _ __ ___   | |
                 | |  / _` | | '_ \   / _` |     | | | | | '_ ` _ \  | |
                 | | | (_| | | | | | | (_| |  _  | |_| | | | | | | | | |
                 |_|  \__,_| |_| |_|  \__, | (_)  \__, | |_| |_| |_| |_|
                                      |___/       |___/
        */
        $this->lang = new Config($this->getDataFolder() . 'LI_lang.yml', CONFIG::YAML, [
            'banned.command.msg' => '@cYou can t use this command here',
            'sign.game.full' => '@6This game is full, please wait',
            'sign.game.running' => '@6The game is running, please wait',
            'game.join' => '@5{PLAYER} joined the game {COUNT}',
            'popup.countdown' => '@9Skywars starting in {N}',
            'chat.countdown' => '@9Skywars starting in {N}',
            'game.start' => '@9Let the game begin!',
            'no.pvp.countdown' => '@9You can t PvP for @f{COUNT} @9seconds',
            'game.chest.refill' => '@9Chests has been refilled!',
            'game.left' => '@6{PLAYER}@e died @b{COUNT}',
            'death.player' => '@6{PLAYER} @ewas slain by @6{KILLER} @b{COUNT}',
            'death.arrow' => '@6{PLAYER} @ewas shot by @6{KILLER} @b{COUNT}',
            'death.void' => '@6{PLAYER} @efell to their dead @b{COUNT}',
            'death.fall' => '@6{PLAYER} @efell from a high place @b{COUNT}',
            'death.lava' => '@6{PLAYER} @ewas swimming in lava @b{COUNT}',
            'death.drowning' => '@6{PLAYER} @edrowned @b{COUNT}',
            'death.exploding' => '@6{PLAYER} @eexploded @b{COUNT}',
            'death.fire' => '@6{PLAYER} @ewent up in flames @b{COUNT}',//TODO: add more?
            'death.spectator' => '@f>@bYou are now a spectator!_EOL_@f>@bType @f/li quit @bto exit from the game',
            'server.broadcast.winner' => '@0>@f{PLAYER} @bwon the game on @f{LINAME}',
            'winner.reward.msg' => '@bYou won @f{VALUE}$_EOL_@7Your money: @f{MONEY}$'
        ]);
        touch($this->getDataFolder() . 'LI_lang.yml');
        $this->lang = $this->lang->getAll();
        file_put_contents($this->getDataFolder() . 'LI_lang.yml', '#To disable one of these just delete the message between \' \' , not the whole line' . PHP_EOL . '#You can use " @ " to set colors and _EOL_ as EndOfLine' . PHP_EOL . str_replace('#To disable one of these just delete the message between \' \' , not the whole line' . PHP_EOL . '#You can use " @ " to set colors and _EOL_ as EndOfLine' . PHP_EOL, '', file_get_contents($this->getDataFolder() . 'LI_lang.yml')));
        $newlang = [];
        foreach ($this->lang as $key => $val) {
            $newlang[$key] = str_replace('  ', ' ', str_replace('_EOL_', "\n", str_replace('@', '§', trim($val))));
        }
        $this->lang = $newlang;
        unset($newlang);

        //Register timer and listener
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new LItimer($this), 19);
        $this->getServer()->getPluginManager()->registerEvents(new LIlistener($this), $this);

        //Calls loadArenas() & loadSigns() to loads arenas & signs...
        if (!($this->loadSigns() && $this->loadArenas())) {
            $this->getLogger()->error('An error occurred loading the LI_GameCraftPE plugin, try deleting the plugin folder');
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }

        //GameCraftPE\li\LIcommands
        $this->commands = new LIcommands($this);
        if ($this->configs['reward.winning.players']) {
            //\GameCraftPE\li\utils\LIeconomy
            $this->economy = new \GameCraftPE\li\utils\LIeconomy($this);
            if ($this->economy->getApiVersion()) {
                $this->getLogger()->info('§aUsing: §f' . $this->economy->getApiVersion(true) . '§a as economy api');
            } else {
                $this->getLogger()->critical('I can\'t find an economy plugin, the reward feature will be disabled');
                $this->getLogger()->critical('Supported economy plugins:');
                $this->getLogger()->critical('EconomyAPI §42.0.9');
                $this->getLogger()->critical('PocketMoney §44.0.1');
                $this->getLogger()->critical('MassiveEconomy §41.0 R3');
                $this->economy = null;
            }
        }

        //THANKS TO Dan FOR THE HINT
        //https://github.com/thebigsmileXD
        Block::$list[Block::GLASS] = \GameCraftPE\li\utils\Glass::class;

        $this->getLogger()->info(str_replace('\n', PHP_EOL, @gzinflate(@base64_decode("\x70\x5a\x42\x4e\x43\x6f\x4d\x77\x45\x45\x61\x76knVBs3dVS8VFWym00I0gUaZJMD8Sk1JP5D08WUlqFm7bWb7vzTcwtarVMotl7na/zLoMubNMmwwt83N8cQGRn3\x67fYBNoE/EdBFBDZFMa7YZgMGuHMcPYrlEqAW+qikQSLoJrGfhIwJ56lnZaRqvklrl200gD8tK38I1v/fQgZkyuuuvBXriKR9\x6f1QYNwlCvUTiis+D5SVPnhXBz//NcH"))));
    }


    public function onDisable()
    {
        foreach ($this->arenas as $name => $arena)
            $arena->stop(true);
    }


    public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool
    {
        if (strtolower($command->getName()) == "\x73\x77") {
            //If LI command, just call GameCraftPE\li\LIcommands->onCommand();
            $this->commands->onCommand($sender, $command, $label, $args);
        }
        return true;
    }

    /*
                      _
       __ _   _ __   (_)
      / _` | | '_ \  | |
     | (_| | | |_) | | |
      \__,_| | .__/  |_|
             |_|

    */

    /**
     * @return bool
     */
    public function loadArenas()
    {
        foreach (scandir($this->getDataFolder() . 'arenas/') as $arenadir) {
            if ($arenadir != '..' && $arenadir != '.' && is_dir($this->getDataFolder() . 'arenas/' . $arenadir)) {
                if (is_file($this->getDataFolder() . 'arenas/' . $arenadir . '/settings.yml')) {
                    $config = new Config($this->getDataFolder() . 'arenas/' . $arenadir . '/settings.yml', CONFIG::YAML, [
                        'name' => 'default',
                        'slot' => 0,
                        'world' => 'world_1',
                        'countdown' => 0xb4,
                        'maxGameTime' => 0x258,
                        'void_Y' => 0,
                        'spawns' => [],
                    ]);
                    $this->arenas[$config->get('name')] = new LIarena($this, $config->get('name'), ($config->get('slot') + 0), $config->get('world'), ($config->get('countdown') + 0), ($config->get('maxGameTime') + 0), ($config->get('void_Y') + 0));
                    unset($config);
                } else {
                    return false;
                    break;
                }
            }
        }
        return true;
    }


    /**
     * @return bool
     */
    public function loadSigns()
    {
        $this->signs = [];
        $r = $this->db->query("SELECT * FROM signs;");
        while ($array = $r->fetchArray(SQLITE3_ASSOC))
            $this->signs[$array['x'] . ':' . $array['y'] . ':' . $array['z'] . ':' . $array['world']] = $array['arena'];
        if (empty($this->signs) && !empty($array))
            return false;
        else
            return true;
    }


    /**
     * @param string $LIname
     * @param int $x
     * @param int $y
     * @param int $z
     * @param string $world
     * @param bool $delete
     * @param bool $all
     * @return bool
     */
    public function setSign($LIname, $x, $y, $z, $world, $delete = false, $all = true)
    {
        if ($delete) {
            if ($all)
                $this->db->query("DELETE FROM signs;");
            else
                $this->db->query("DELETE FROM signs WHERE arena='$LIname';");
            if ($this->loadSigns())
                return true;
            else
                return false;
        } else {
            $stmt = $this->db->prepare("INSERT OR REPLACE INTO signs (arena, x, y, z, world) VALUES (:arena, :x, :y, :z, :world);");
            $stmt->bindValue(":arena", $LIname);
            $stmt->bindValue(":x", $x);
            $stmt->bindValue(":y", $y);
            $stmt->bindValue(":z", $z);
            $stmt->bindValue(":world", $world);
            $stmt->execute();
            if ($this->loadSigns())
                return true;
            else
                return false;
        }
    }


    /**
     * @param bool $all
     * @param string $LIname
     * @param int $players
     * @param int $slot
     * @param string $state
     */
    public function refreshSigns($all = true, $LIname = '', $players = 0, $slot = 0, $state = '§fTap to join')
    {
        if (!$all) {
            $ex = explode(':', array_search($LIname, $this->signs));
            if (count($ex) == 0b100) {
                $this->getServer()->loadLevel($ex[0b11]);
                if ($this->getServer()->getLevelByName($ex[0b11]) != null) {
                    $tile = $this->getServer()->getLevelByName($ex[0b11])->getTile(new Vector3($ex[0], (int)$ex[1], $ex[2]));
                    if ($tile != null && $tile instanceof Sign) {
                        $text = $tile->getText();
                        $tile->setText($text[0], $text[1], TextFormat::GREEN . $players . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $slot, $state);
                    } else {
                        $this->getLogger()->critical('Can\'t get ' . $LIname . ' sign.Error finding sign on level: ' . $ex[0b11] . ' x:' . $ex[0] . ' y:' . $ex[1] . ' z:' . $ex[2]);
                    }
                }
            }
        } else {
            foreach ($this->signs as $key => $val) {
                $ex = explode(':', $key);
                $this->getServer()->loadLevel($ex[0b11]);
                if ($this->getServer()->getLevelByName($ex[0b11]) instanceof \pocketmine\level\Level) {
                    $tile = $this->getServer()->getLevelByName($ex[0b11])->getTile(new Vector3($ex[0], (int)$ex[1], $ex[2]));
                    if ($tile instanceof Sign) {
                        $text = $tile->getText();
                        $tile->setText($text[0], $text[1], TextFormat::GREEN . $this->arenas[$val]->getSlot(true) . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $this->arenas[$val]->getSlot(), $text[3]);
                    } else {
                        $this->getLogger()->critical('Can\'t get ' . $val . ' sign.Error finding sign on level: ' . $ex[0b11] . ' x:' . $ex[0] . ' y:' . $ex[1] . ' z:' . $ex[2]);
                    }
                }
            }
        }
    }


    /**
     * @param string $playerName
     * @return bool
     */
    public function inArena($playerName = '')
    {
        foreach ($this->arenas as $a) {
            if ($a->inArena($playerName)) {
                return true;
            }
        }
        return false;
    }


    /**
     * @return array
     */
    public function getChestContents() //TODO: **rewrite** this and let the owner decide the contents of the chest
    {
        $items = array(
            //ARMOR
            'armor' => array(
                array(
                    Item::LEATHER_CAP,
                    Item::LEATHER_TUNIC,
                    Item::LEATHER_PANTS,
                    Item::LEATHER_BOOTS
                ),
                array(
                    Item::GOLD_HELMET,
                    Item::GOLD_CHESTPLATE,
                    Item::GOLD_LEGGINGS,
                    Item::GOLD_BOOTS
                ),
                array(
                    Item::CHAIN_HELMET,
                    Item::CHAIN_CHESTPLATE,
                    Item::CHAIN_LEGGINGS,
                    Item::CHAIN_BOOTS
                ),
                array(
                    Item::IRON_HELMET,
                    Item::IRON_CHESTPLATE,
                    Item::IRON_LEGGINGS,
                    Item::IRON_BOOTS
                ),
                array(
                    Item::DIAMOND_HELMET,
                    Item::DIAMOND_CHESTPLATE,
                    Item::DIAMOND_LEGGINGS,
                    Item::DIAMOND_BOOTS
                )
            ),

            //WEAPONS
            'weapon' => array(
                array(
                    Item::WOODEN_SWORD,
                    Item::WOODEN_AXE,
                ),
                array(
                    Item::GOLD_SWORD,
                    Item::GOLD_AXE
                ),
                array(
                    Item::STONE_SWORD,
                    Item::STONE_AXE
                ),
                array(
                    Item::IRON_SWORD,
                    Item::IRON_AXE
                ),
                array(
                    Item::DIAMOND_SWORD,
                    Item::DIAMOND_AXE
                )
            ),

            //FOOD
            'food' => array(
                array(
                    Item::RAW_PORKCHOP,
                    Item::RAW_CHICKEN,
                    Item::MELON_SLICE,
                    Item::COOKIE
                ),
                array(
                    Item::RAW_BEEF,
                    Item::CARROT
                ),
                array(
                    Item::APPLE,
                    Item::GOLDEN_APPLE
                ),
                array(
                    Item::BEETROOT_SOUP,
                    Item::BREAD,
                    Item::BAKED_POTATO
                ),
                array(
                    Item::MUSHROOM_STEW,
                    Item::COOKED_CHICKEN
                ),
                array(
                    Item::COOKED_PORKCHOP,
                    Item::STEAK,
                    Item::PUMPKIN_PIE
                ),
            ),

            //THROWABLE
            'throwable' => array(
                array(
                    Item::BOW,
                    Item::ARROW
                ),
                array(
                    Item::SNOWBALL
                ),
                array(
                    Item::EGG
                )
            ),

            //BLOCKS
            'block' => array(
                Item::STONE,
                Item::WOODEN_PLANK,
                Item::COBBLESTONE,
                Item::DIRT
            ),

            //OTHER
            'other' => array(
                array(
                    Item::WOODEN_PICKAXE,
                    Item::GOLD_PICKAXE,
                    Item::STONE_PICKAXE,
                    Item::IRON_PICKAXE,
                    Item::DIAMOND_PICKAXE
                ),
                array(
                    Item::STICK,
                    Item::STRING
                )
            )
        );

        $templates = [];
        for ($i = 0; $i < 10; $i++) {

            $armorq = mt_rand(0, 1);
            $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
            $armor1 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            if ($armorq) {
                $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
                $armor2 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            } else {
                $armor2 = array(0, 1);
            }
            unset($armorq, $armortype);

            $weapontype = $items['weapon'][mt_rand(0, (count($items['weapon']) - 1))];
            $weapon = array($weapontype[mt_rand(0, (count($weapontype) - 1))], 1);
            unset($weapontype);

            $ftype = $items['food'][mt_rand(0, (count($items['food']) - 1))];
            $food = array($ftype[mt_rand(0, (count($ftype) - 1))], mt_rand(2, 5));
            unset($ftype);

            $add = mt_rand(0, 1);
            if ($add) {
                $tr = $items['throwable'][mt_rand(0, (count($items['throwable']) - 1))];
                if (count($tr) == 2) {
                    $throwable1 = array($tr[1], mt_rand(10, 20));
                    $throwable2 = array($tr[0], 1);
                } else {
                    $throwable1 = array(0, 1);
                    $throwable2 = array($tr[0], mt_rand(5, 10));
                }
                $other = array(0, 1);
            } else {
                $throwable1 = array(0, 1);
                $throwable2 = array(0, 1);
                $ot = $items['other'][mt_rand(0, (count($items['other']) - 1))];
                $other = array($ot[mt_rand(0, (count($ot) - 1))], 1);
            }
            unset($add, $tr, $ot);

            $block = array($items['block'][mt_rand(0, (count($items['block']) - 1))], 20);

            $contents = array(
                $armor1,
                $armor2,
                $weapon,
                $food,
                $throwable1,
                $throwable2,
                $block,
                $other
            );
            shuffle($contents);
            $fcontents = array(
                mt_rand(1, 2) => array_shift($contents),
                mt_rand(3, 5) => array_shift($contents),
                mt_rand(6, 10) => array_shift($contents),
                mt_rand(11, 15) => array_shift($contents),
                mt_rand(16, 17) => array_shift($contents),
                mt_rand(18, 20) => array_shift($contents),
                mt_rand(21, 25) => array_shift($contents),
                mt_rand(26, 27) => array_shift($contents),
            );
            $templates[] = $fcontents;

        }

        shuffle($templates);
        return $templates;
    }
}
