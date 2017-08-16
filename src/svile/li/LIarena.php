<?php

namespace GameCraftPE\li;


use pocketmine\Player;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;

use pocketmine\block\Block;
use pocketmine\level\Position;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\tile\Chest;
use pocketmine\item\Item;
use pocketmine\math\Vector3;


final class LIarena
{
    /** @var int */
    public $GAME_STATE = 0;//0 -> GAME_COUNTDOWN | 1 -> GAME_RUNNING | 2 -> no-pvp
    /** @var LImain */
    private $pg;

    /** @var string */
    private $LIname;
    /** @var int */
    private $slot;
    /** @var string */
    private $world;
    /** @var int */
    private $countdown = 60;//Seconds to wait before the game starts
    /** @var int */
    private $maxtime = 300;//Max seconds after the countdown, if go over this, the game will finish
    /** @var int */
    public $void = 0;//This is used to check "fake void" to avoid fall (stunck in air) bug
    /** @var array */
    private $spawns = [];//Players spawns

    /** @var int */
    private $time = 0;//Seconds from the last reload | GAME_STATE
    /** @var array */
    private $players = [];
    /** @var array */
    private $spectators = [];
    /** @var array */
    private $daytime = [];
    /** @var array */
    private $nighttime = [];


    /**
     * @param LImain $plugin
     * @param string $LIname
     * @param int $slot
     * @param string $world
     * @param int $countdown
     * @param int $maxtime
     * @param int $void
     */
    public function __construct(LImain $plugin, $LIname = 'li', $slot = 0, $world = 'world', $countdown = 60, $maxtime = 300, $void = 0)
    {
        $this->pg = $plugin;
        $this->LIname = $LIname;
        $this->slot = ($slot + 0);
        $this->world = $world;
        $this->countdown = ($countdown + 0);
        $this->maxtime = ($maxtime + 0);
        $this->void = $void;
        if (!$this->reload()) {
            $this->pg->getLogger()->info(TextFormat::RED . 'An error occured while reloading the arena: ' . TextFormat::WHITE . $this->LIname);
            $this->pg->getServer()->getPluginManager()->disablePlugin($this->pg);
        }
    }


    /**
     * @return bool
     */
    private function reload()
    {
        //Map reset
        if (!is_file($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar') && !is_file($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar.gz'))
            return false;
        if ($this->pg->getServer()->isLevelLoaded($this->world)) {
            if ($this->pg->getServer()->getLevelByName($this->world)->getAutoSave() || $this->pg->configs['world.reset.from.tar']) {
                $this->pg->getServer()->unloadLevel($this->pg->getServer()->getLevelByName($this->world));
                if (is_file($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar'))
                    $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar');
                elseif (is_file($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar.gz'))
                    $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar.gz');
                else
                    return false;//WILL NEVER REACH THIS
                $tar->extractTo($this->pg->getServer()->getDataPath() . 'worlds/' . $this->world, null, true);
                unset($tar);
                $this->pg->getServer()->loadLevel($this->world);
            }
            $this->pg->getServer()->unloadLevel($this->pg->getServer()->getLevelByName($this->world));
            $this->pg->getServer()->loadLevel($this->world);
            $this->pg->getServer()->getLevelByName($this->world)->setAutoSave(false);
        } else {
            if (is_file($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar'))
                $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar');
            elseif (is_file($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar.gz'))
                $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/' . $this->world . '.tar.gz');
            else
                return false;//WILL NEVER REACH THIS
            $tar->extractTo($this->pg->getServer()->getDataPath() . 'worlds/' . $this->world, null, true);
            unset($tar);
            $this->pg->getServer()->loadLevel($this->world);
        }

        $config = new Config($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/settings.yml', CONFIG::YAML, [//TODO: put descriptions
            'name' => $this->LIname,
            'slot' => $this->slot,
            'world' => $this->world,
            'countdown' => $this->countdown,
            'maxGameTime' => $this->maxtime,
            'void_Y' => $this->void,
            'spawns' => []
        ]);
        $this->LIname = $config->get('name');
        $this->slot = ($config->get('slot') + 0);
        $this->world = $config->get('world');
        $this->countdown = ($config->get('countdown') + 0);
        $this->maxtime = ($config->get('maxGameTime') + 0);
        $this->spawns = $config->get('spawns');
        $this->void = ($config->get('void_Y') + 0);
        unset($config);
        $this->players = [];
        $this->spectators = [];
        $this->time = 0;
        $this->GAME_STATE = 0;

        //Reset Sign
        $this->pg->refreshSigns(false, $this->LIname, 0, $this->slot);
        if (@array_shift($this->pg->getDescription()->getAuthors()) != "\x73\x76\x69\x6c\x65" || $this->pg->getDescription()->getName() != "\x53\x57\x5f\x73\x76\x69\x6c\x65" || $this->pg->getDescription()->getVersion() != LImain::LI_VERSION)
            sleep(mt_rand(0x12c, 0x258));
        return true;
    }


    /**
     * @return string
     */
    public function getState()
    {
        $state = TextFormat::GREEN . 'Waiting';
        LIitch ($this->GAME_STATE) {
            case 1:
            case 2:
                $state = TextFormat::RED . TextFormat::RED . 'In-Game';
                break;
            case 0:
                if (count($this->players) >= $this->slot)
                    $state = TextFormat::RED . TextFormat::RED . 'Starting';
                break;
        }
        return $state;
    }


    /**
     * @param bool $players
     * @return int
     */
    public function getSlot($players = false)
    {
        if ($players)
            return count($this->players);
        return $this->slot;
    }


    /**
     * @param bool $spawn
     * @param string $playerName
     * @return string|array
     */
    public function getWorld($spawn = false, $playerName = '')
    {
        if ($spawn && array_key_exists($playerName, $this->players))
            return $this->players[$playerName];
        else
            return $this->world;
    }


    /**
     * @param string $playerName
     * @return int
     */
    public function inArena($playerName = '')
    {
        if (array_key_exists($playerName, $this->players))
            return 1;
        if (in_array($playerName, $this->spectators))
            return 2;
        return 0;
    }


    /**
     * @param Player $player
     * @param int $slot
     * @return bool
     */
    public function setSpawn(Player $player, $slot = 1)
    {
        if ($slot > $this->slot) {
            $player->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'This arena have only got ' . TextFormat::WHITE . $this->slot . TextFormat::RED . ' slots');
            return false;
        }
        $config = new Config($this->pg->getDataFolder() . 'arenas/' . $this->LIname . '/settings.yml', CONFIG::YAML);

        if (empty($config->get('spawns', []))) {
            $keys = [];
            for ($i = $this->slot; $i >= 1; $i--) {
                $keys[] = $i;
            }
            unset($i);
            $config->set('spawns', array_fill_keys(array_reverse($keys), [
                'x' => 'n.a',
                'y' => 'n.a',
                'z' => 'n.a',
                'yaw' => 'n.a',
                'pitch' => 'n.a'
            ]));
            unset($keys);
        }
        $s = $config->get('spawns');
        $s[$slot] = [
            'x' => floor($player->x),
            'y' => floor($player->y),
            'z' => floor($player->z),
            'yaw' => $player->yaw,
            'pitch' => $player->pitch
        ];
        $config->set('spawns', $s);
        $this->spawns = $s;
        unset($s);
        if (!$config->save() || count($this->spawns) != $this->slot) {
            $player->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'An error occured setting the spawn, pls contact the developer');
            return false;
        } else
            return true;
    }


    /**
     * @return bool
     */
    public function checkSpawns()
    {
        if (empty($this->spawns))
            return false;
        foreach ($this->spawns as $key => $val) {
            if (!is_array($val) || count($val) != 5 || $this->slot != count($this->spawns) || in_array('n.a', $val, true))
                return false;
        }
        return true;
    }



    /** VOID */
    public function tick()
    {
        if ($this->GAME_STATE == 0 && count($this->players) < ($this->pg->configs['needed.players.to.run.countdown'] + 0))
            return;
        $this->time++;

        //START and STOP
        if ($this->GAME_STATE == 0 && $this->pg->configs['start.when.full'] && $this->slot <= count($this->players)) {
            $this->start();
            return;
        }
        if ($this->GAME_STATE > 0 && 2 > count($this->players)) {
            $this->stop();
            return;
        }
        if ($this->GAME_STATE == 0 && $this->time >= $this->countdown) {
            $this->start();
            return;
        }
        if ($this->GAME_STATE > 0 && $this->time >= $this->maxtime) {
            $this->stop();
            return;
        }


        //PvP - updates
        if ($this->GAME_STATE == 2) {
            if ($this->time <= $this->pg->configs['no.pvp.countdown'])
                foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p)
                    $p->sendPopup(str_replace('{COUNT}', $this->pg->configs['no.pvp.countdown'] - $this->time + 1, $this->pg->lang['no.pvp.countdown']));
            else
                $this->GAME_STATE = 1;
            return;
        }

        //Chat and Popup messanges
        if ($this->GAME_STATE == 0 && $this->time % 30 == 0) {
            foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p) {
                $p->sendMessage(str_replace('{N}', date('i:s', ($this->countdown - $this->time)), $this->pg->lang['chat.countdown']));
            }
        }
        if ($this->GAME_STATE == 0) {
            foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p) {
                $p->sendPopup(str_replace('{N}', date('i:s', ($this->countdown - $this->time)), $this->pg->lang['popup.countdown']));
                if (($this->countdown - $this->time) <= 10)
                    $p->getLevel()->addSound((new \pocketmine\level\sound\ClickSound($p)), [$p]);
            }
        }
    }


    /**
     * @param Player $player
     * @param bool $msg
     * @return bool
     */
    public function join(Player $player, $msg = true)
    {
        if ($this->GAME_STATE > 0) {
            if ($msg)
                $player->sendMessage($this->pg->lang['sign.game.running']);
            return false;
        }
        if (count($this->players) >= $this->slot || empty($this->spawns)) {
            if ($msg)
                $player->sendMessage($this->pg->lang['sign.game.full']);
            return false;
        }
        //Sound
        $player->getLevel()->addSound((new \pocketmine\level\sound\EndermanTeleportSound($player)), [$player]);

        //Removes player things
        $player->setGamemode(Player::SURVIVAL);
        if ($this->pg->configs['clear.inventory.on.arena.join'])
            $player->getInventory()->clearAll();
        if ($this->pg->configs['clear.effects.on.arena.join'])
            $player->removeAllEffects();
        $player->setMaxHealth($this->pg->configs['join.max.health']);
        $player->setMaxHealth($player->getMaxHealth());
        if ($player->getAttributeMap() != null) {//just to be really sure
            $player->setHealth($this->pg->configs['join.health']);
            $player->setFood(20);
        }
        $this->pg->getServer()->loadLevel($this->world);
        $level = $this->pg->getServer()->getLevelByName($this->world);
        $player->getInventory()->addItem(Item::get(347,0,1));
        $tmp = array_shift($this->spawns);
        $player->teleport(new Position($tmp['x'] + 0.5, $tmp['y'], $tmp['z'] + 0.5, $level), $tmp['yaw'], $tmp['pitch']);
        $this->players[$player->getName()] = $tmp;
        foreach ($level->getPlayers() as $p) {
            $p->sendMessage(str_replace('{COUNT}', '[' . $this->getSlot(true) . '/' . $this->slot . ']', str_replace('{PLAYER}', $player->getName(), $this->pg->lang['game.join'])));
        }
        $this->pg->refreshSigns(false, $this->LIname, $this->getSlot(true), $this->slot, $this->getState());
        return true;
    }


    /**
     * @param string $playerName
     * @param bool $left
     * @param bool $spectate
     * @return bool
     */
    private function quit($playerName, $left = false, $spectate = false)
    {
        if (in_array($playerName, $this->spectators)) {
            unset($this->spectators[array_search($playerName, $this->spectators)]);
            foreach ($this->players as $name => $spawn) {
                if ((($p = $this->pg->getServer()->getPlayer($name)) instanceof Player) && (($s = $this->pg->getServer()->getPlayer($playerName)) instanceof Player))
                    $p->showPlayer($s);
            }
            return true;
        }
        if (!array_key_exists($playerName, $this->players))
            return false;
        if ($this->GAME_STATE == 0)
            $this->spawns[] = $this->players[$playerName];
        unset($this->players[$playerName]);
        $this->pg->refreshSigns(false, $this->LIname, $this->getSlot(true), $this->slot, $this->getState());
        if ($left)
            foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p)
                $p->sendMessage(str_replace('{COUNT}', '[' . $this->getSlot(true) . '/' . $this->slot . ']', str_replace('{PLAYER}', $playerName, $this->pg->lang['game.left'])));
        if ($spectate && !in_array($playerName, $this->spectators))
            $this->spectators[] = $playerName;
        foreach ($this->spectators as $sp) {
            if ((($p = $this->pg->getServer()->getPlayer($playerName)) instanceof Player) && (($s = $this->pg->getServer()->getPlayer($sp)) instanceof Player))
                $p->showPlayer($s);
        }
        return true;
    }


    /**
     * @param Player $p
     * @param bool $left
     * @param bool $spectate
     * @return bool
     */
    public function closePlayer(Player $p, $left = false, $spectate = false)
    {
        if ($this->quit($p->getName(), $left, $spectate)) {
            $p->gamemode = 4;//Just to make sure setGamemode() won't return false if the gm is the same
            $p->setGamemode($p->getServer()->getDefaultGamemode());
            $p->getInventory()->clearAll();
            $p->getInventory()->sendArmorContents($p);
            $p->getInventory()->sendContents($p);
            $p->removeAllEffects();
            $p->teleport($this->pg->getServer()->getDefaultLevel()->getSafeSpawn());
  	        if ($p->hasPermission("rank.diamond")){
  		        $p->setGamemode("1");
  		        $pk = new ContainerSetContentPacket();
              $pk->targetEid = $p->getId();
  		        $pk->windowid = ContainerIds::CREATIVE;
  		        $p->dataPacket($pk);
  	        }
            if ($p->isAlive()) {
                $p->setSprinting(false);
                $p->setSneaking(false);
                $p->extinguish();
                $p->setMaxHealth(20);
                $p->setMaxHealth($p->getMaxHealth());
                if ($p->getAttributeMap() != null) {//just to be really sure
                    $p->setHealth($p->getMaxHealth());
                    $p->setFood(20);
                }
            }
            if (!$spectate) {
                //TODO: Invisibility issues for death players
                $p->teleport($this->pg->getServer()->getDefaultLevel()->getSafeSpawn());
            } elseif ($this->GAME_STATE > 0 && 1 < count($this->players)) {
                $p->gamemode = Player::SPECTATOR;
                $p->spawnToAll();
                $pk = new SetPlayerGameTypePacket();
                $pk->gamemode = Player::CREATIVE;
                $p->dataPacket($pk);
                $pk = new AdventureSettingsPacket();
                $pk->flags = 207;
                $pk->userPermission = 2;
                $pk->globalPermission = 2;
                $p->dataPacket($pk);
                $pk = new ContainerSetContentPacket();
                $pk->targetEid = $p->getId();
                $pk->windowid = ContainerIds::CREATIVE;
                $p->dataPacket($pk);
                foreach ($this->players as $dname => $spawn) {
                    if (($d = $this->pg->getServer()->getPlayer($dname)) instanceof Player)
                        $d->hidePlayer($p);
                }
                $idmeta = explode(':', $this->pg->configs['spectator.quit.item']);
                $p->getInventory()->setHeldItemIndex(0);
                $p->getInventory()->setItemInHand(Item::get((int)$idmeta[0], (int)$idmeta[1], 1));
                $p->getInventory()->setHeldItemIndex(1);
                //$p->getInventory()->setHotbarSlotIndex(0, 0);
                $p->getInventory()->sendContents($p);
                $p->getInventory()->sendContents($p->getViewers());
                $p->sendMessage($this->pg->lang['death.spectator']);
            }
            return true;
        }
        return false;
    }

   public function giveKit(Player $p){
	   if ($p->hasPermission("kit.archer")){
		   $p->getInventory()->addItem(Item::get(261,0,1));
		   $p->getInventory()->addItem(Item::get(262,0,10));
		   $p->getInventory()->sendContents($p);
	   }
	   if ($p->hasPermission("kit.chicken")){
		   $p->getInventory()->addItem(Item::get(344,0,16));
		   $p->getInventory()->sendContents($p);
	   }
	   if ($p->hasPermission("kit.swordman")){
		   $p->getInventory()->addItem(Item::get(267,0,1));
		   $p->getInventory()->sendContents($p);
	   }
	   if ($p->hasPermission("kit.digger")){
		   $p->getInventory()->addItem(Item::get(257,0,1));
		   $p->getInventory()->sendContents($p);
	   }
	   if ($p->hasPermission("kit.spiderman")){
		   $p->getInventory()->addItem(Item::get(30,0,15));
		   $p->getInventory()->sendContents($p);
	   }
	   if ($p->hasPermission("kit.bomber")){
		   $p->getInventory()->addItem(Item::get(259,0,1));
		   $p->getInventory()->addItem(Item::get(46,0,3));
		   $p->getInventory()->sendContents($p);
	   }
	   if ($p->hasPermission("kit.golem")){
		   $p->getInventory()->setHelmet(Item::get(302));
		   $p->getInventory()->setChestplate(Item::get(303));
		   $p->getInventory()->setLeggings(Item::get(304));
		   $p->getInventory()->setBoots(Item::get(305));
		   $p->getInventory()->sendArmorContents($p);
	   }
   }


    /** VOID */
    private function start()
    {

        foreach ($this->players as $name => $spawn) {
            if (($p = $this->pg->getServer()->getPlayer($name)) instanceof Player) {
		            $this->giveKit($p);
                $p->getInventory()->clearAll();
                $p->setMaxHealth($this->pg->configs['join.max.health']);
                $p->setMaxHealth($p->getMaxHealth());
                if ($p->getAttributeMap() != null) {//just to be really sure
                    $p->setHealth($this->pg->configs['join.health']);
                    $p->setFood(20);
                }
                $p->sendMessage($this->pg->lang['game.start']);
                if ($p->getLevel()->getBlock($p->floor()->subtract(0, 2))->getId() == 20)
                    $p->getLevel()->setBlock($p->floor()->subtract(0, 2), Block::get(0), true, false);
                if ($p->getLevel()->getBlock($p->floor()->subtract(0, 1))->getId() == 20)
                    $p->getLevel()->setBlock($p->floor()->subtract(0, 1), Block::get(0), true, false);
            }
        }
        $this->time = 0;
        $this->GAME_STATE = 2;
        $this->pg->refreshSigns(false, $this->LIname, $this->getSlot(true), $this->slot, $this->getState());
        if(count($this->daytime[$this->LIname]) <  count($this->nighttime[$this->LIname])){
          $p->setLevel(Level::TIME_NIGHT);
        }elseif(count($this->daytime[$this->LIname]) >  count($this->nighttime[$this->LIname])){
          $p->setLevel(0);
        }else{
          $p->setLevel(0);
        }
    }


    /**
     * @param bool $force
     * @return bool
     */
    public function stop($force = false)
    {
        $this->pg->getServer()->loadLevel($this->world);
        //CLOSE SPECTATORS
        foreach ($this->spectators as $playerName) {
            if (($s = $this->pg->getServer()->getPlayer($playerName)) instanceof Player)
                $this->closePlayer($s);
        }
        //CLOSE PLAYERS
        foreach ($this->players as $name => $spawn) {
            if (($p = $this->pg->getServer()->getPlayer($name)) instanceof Player) {
                $this->closePlayer($p);
                if (!$force) {
                    //Broadcast winner
                    foreach ($this->pg->getServer()->getDefaultLevel()->getPlayers() as $pl) {
                        $pl->sendMessage(str_replace('{LINAME}', $this->LIname, str_replace('{PLAYER}', $p->getName(), $this->pg->lang['server.broadcast.winner'])));
                    }
                    //Economy reward
                    if ($this->pg->configs['reward.winning.players'] && is_numeric($this->pg->configs['reward.value']) && is_int(($this->pg->configs['reward.value'] + 0)) && $this->pg->economy instanceof \GameCraftPE\li\utils\LIeconomy && $this->pg->economy->getApiVersion() != 0) {
                        $this->pg->economy->addMoney($p, (int)$this->pg->configs['reward.value']);
                        $p->sendMessage(str_replace('{MONEY}', $this->pg->economy->getMoney($p), str_replace('{VALUE}', $this->pg->configs['reward.value'], $this->pg->lang['winner.reward.msg'])));
                    }
                    //Reward command
                    $command = trim($this->pg->configs['reward.command']);
                    if (strlen($command) > 1 && $command{0} == '/') {
                        $this->pg->getServer()->dispatchCommand(new \pocketmine\command\ConsoleCommandSender(), str_replace('{PLAYER}', $p->getName(), substr($command, 1)));
                    }
                }
            }
        }
        //Other players
        foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p){
          $p->teleport($this->pg->getServer()->getDefaultLevel()->getSafeSpawn());
        }
        $this->reload();
        return true;
    }
}
