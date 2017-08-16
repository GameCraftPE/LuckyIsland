<?php

namespace GameCraftPE\li;


use pocketmine\event\Listener;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;

use pocketmine\level\Position;
use pocketmine\level\Location;
use pocketmine\math\Vector3;

use pocketmine\Player;
use pocketmine\utils\TextFormat;

class LIlistener implements Listener
{
  /** @var LImain */
  private $pg;

  public function __construct(LImain $plugin)
  {
    $this->pg = $plugin;
  }

  public function onJoin(PlayerJoinEvent $ev){
    if ($ev->getPlayer()->hasPermission("rank.diamond")){
      $ev->getPlayer()->setGamemode("1");
      $pk = new ContainerSetContentPacket();
      $pk->targetEid = $ev->getPlayer()->getId();
      $pk->windowid = ContainerIds::CREATIVE;
      $ev->getPlayer()->dataPacket($pk);
    }
  }

  public function onSignChange(SignChangeEvent $ev)
  {
    if ($ev->getLine(0) != 'li' || $ev->getPlayer()->isOp() == false)
    return;

    //Checks if the arena exists
    $LIname = TextFormat::clean(trim($ev->getLine(1)));
    if (!array_key_exists($LIname, $this->pg->arenas)) {
      $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'This arena doesn\'t exist, try ' . TextFormat::WHITE . '/li create');
      return;
    }

    //Checks if a sign already exists for the arena
    if (in_array($LIname, $this->pg->signs)) {
      $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'A sign for this arena already exist, try ' . TextFormat::WHITE . '/li signdelete');
      return;
    }

    //Checks if the sign is placed inside arenas
    $world = $ev->getPlayer()->getLevel()->getName();
    foreach ($this->pg->arenas as $name => $arena) {
      if ($world == $arena->getWorld()) {
        $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'You can\'t place the join sign inside arenas');
        return;
      }
    }

    //Checks arena spawns
    if (!$this->pg->arenas[$LIname]->checkSpawns()) {
      $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'Not all the spawns are set in this arena, try ' . TextFormat::WHITE . ' /li setspawn');
      return;
    }

    //Saves the sign
    if (!$this->pg->setSign($LIname, ($ev->getBlock()->getX() + 0), ($ev->getBlock()->getY() + 0), ($ev->getBlock()->getZ() + 0), $world))
    $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'An error occured, please contact the developer');
    else
    $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'LI join sign created !');

    //Sets sign format
    $ev->setLine(0, $this->pg->configs['1st_line']);
    $ev->setLine(1, str_replace('{LINAME}', $LIname, $this->pg->configs['2nd_line']));
    $ev->setLine(2, TextFormat::GREEN . '0' . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $this->pg->arenas[$LIname]->getSlot());
    $ev->setLine(3, TextFormat::GREEN . 'Waiting');
    $this->pg->refreshSigns(true);
    unset($LIname, $world);
  }


  public function onInteract(PlayerInteractEvent $ev)
  {
    if ($ev->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK)
    return;

    //In-arena Tap
    foreach ($this->pg->arenas as $a) {
      if ($t = $a->inArena($ev->getPlayer()->getName())) {
        if ($t == 2)
        $ev->setCancelled();
        if ($a->GAME_STATE == 0)
        $ev->setCancelled();
        return;
      }
    }

    //Join sign Tap check
    $key = $ev->getBlock()->x . ':' . $ev->getBlock()->y . ':' . $ev->getBlock()->z . ':' . $ev->getBlock()->getLevel()->getName();
    if (array_key_exists($key, $this->pg->signs))
    $this->pg->arenas[$this->pg->signs[$key]]->join($ev->getPlayer());
    unset($key);
  }


  public function onLevelChange(EntityLevelChangeEvent $ev)
  {
    if ($ev->getEntity() instanceof Player) {
      foreach ($this->pg->arenas as $a) {
        if ($a->inArena($ev->getEntity()->getName())) {
          $ev->setCancelled();
          break;
        }
      }
    }
  }


  public function onTeleport(EntityTeleportEvent $ev)
  {
    if ($ev->getEntity() instanceof Player) {
      foreach ($this->pg->arenas as $a) {
        if ($a->inArena($ev->getEntity()->getName())) {
          //Allow near teleport
          if ($ev->getFrom()->distanceSquared($ev->getTo()) < 10000)
          break;
          $ev->setCancelled();
          break;
        }
      }
    }
  }


  public function onDropItem(PlayerDropItemEvent $ev)
  {
    foreach ($this->pg->arenas as $a) {
      if (($f = $a->inArena($ev->getPlayer()->getName()))) {
        if ($f == 2) {
          $ev->setCancelled();
          break;
        }
        if (!$this->pg->configs['player.drop.item']) {
          $ev->setCancelled();
          break;
        }
        break;
      }
    }
  }


  public function onPickUp(InventoryPickupItemEvent $ev)
  {
    if (($p = $ev->getInventory()->getHolder()) instanceof Player) {
      foreach ($this->pg->arenas as $a) {
        if ($f = $a->inArena($p->getName())) {
          if ($f == 2)
          $ev->setCancelled();
          break;
        }
      }
    }
  }


  public function onItemHeld(PlayerItemHeldEvent $ev)
  {
    foreach ($this->pg->arenas as $a) {
      if ($f = $a->inArena($ev->getPlayer()->getName())) {
        $player = $ev->getPlayer();
        $item = $ev->getItem();
        if($item instanceof Item){
          if($item->getID() === "347"){
            $player->getInventory()->clearAll();
            $player->getInventory()->addItem(Item::get(378,0,1));
            $player->getInventory()->addItem(Item::get(382,0,1));
            $player->getInventory()->sendContents($player);
          }
          if($item->getID() === "378"){
            if(!in_array($player->getName(), $a->daytime[$a])){
              if(in_array($player->getName(), $a->nighttime[$a])){
                unset($a->nighttime[$a][array_search($player->getName(), $a->nighttime[$a])]);
              }
              $a->daytime[$a] = $player->getName();
              $player->getInventory()->clearAll();
              $player->getInventory()->addItem(Item::get(347,0,1));
              $player->getInventory()->sendContents($player);
            }
          }
          if($item->getID() === "382"){
            if(!in_array($player->getName(), $a->nighttime[$a])){
              if(in_array($player->getName(), $a->daytime[$a])){
                unset($a->daytime[$a][array_search($player->getName(), $a->daytime[$a])]);
              }
              $a->nighttime[$a] = $player->getName();
              $player->getInventory()->clearAll();
              $player->getInventory()->addItem(Item::get(347,0,1));
              $player->getInventory()->sendContents($player);
            }
          }
        }
        if ($f == 2) {
          if (($ev->getItem()->getId() . ':' . $ev->getItem()->getDamage()) == $this->pg->configs['spectator.quit.item'])
          $a->closePlayer($ev->getPlayer());
          $ev->setCancelled();
          $ev->getPlayer()->getInventory()->setHeldItemIndex(1);
        }
        break;
      }
    }
  }


  public function onMove(PlayerMoveEvent $ev)
  {
    if ($ev->getPlayer()->getLevel()->getFolderName() === "Lobby"){
      if($ev->getTo()->getFloorY() < 3){
        $ev->getPlayer()->teleport($ev->getPlayer()->getLevel()->getSafeSpawn());
      }
    }
    foreach ($this->pg->arenas as $a) {
      if ($a->inArena($ev->getPlayer()->getName())) {
        if ($a->GAME_STATE == 0) {
          $spawn = $a->getWorld(true, $ev->getPlayer()->getName());
          if ($ev->getPlayer()->getPosition()->distanceSquared(new Position($spawn['x'], $spawn['y'], $spawn['z'])) > 4)
          $ev->setTo(new Location($spawn['x'], $spawn['y'], $spawn['z'] + 0.5 , $spawn['yaw'], $spawn['pitch']));
          break;
        }
        if ($a->void >= $ev->getPlayer()->getFloorY() && $ev->getPlayer()->isAlive()) {
          $event = new EntityDamageEvent($ev->getPlayer(), EntityDamageEvent::CAUSE_VOID, 10);
          $ev->getPlayer()->attack($event->getFinalDamage(), $event);
          unset($event);
        }
        return;
      }
    }
    //Checks if knockBack is enabled
    if ($this->pg->configs['sign.knockBack']) {
      foreach ($this->pg->signs as $key => $val) {
        $ex = explode(':', $key);
        $pl = $ev->getPlayer();
        if ($pl->getLevel()->getName() == $ex[3]) {
          $x = (int)$pl->getFloorX();
          $y = (int)$pl->getFloorY();
          $z = (int)$pl->getFloorZ();
          $radius = (int)$this->pg->configs['knockBack.radius.from.sign'];
          //If is inside the sign radius, knockBack
          if (($x >= ($ex[0] - $radius) && $x <= ($ex[0] + $radius)) && ($z >= ($ex[2] - $radius) && $z <= ($ex[2] + $radius)) && ($y >= ($ex[1] - $radius) && $y <= ($ex[1] + $radius))) {
            //If the block is not a sign, break
            $block = $pl->getLevel()->getBlock(new Vector3($ex[0], $ex[1], $ex[2]));
            if ($block->getId() != 63 && $block->getId() != 68)
            break;
            //Max $i should be 90 to avoid bugs-lag, yes 90 is a magic number :P
            $i = (int)$this->pg->configs['knockBack.intensity'];
            if ($this->pg->configs['knockBack.follow.sign.direction']) {
              //Finds sign yaw
              switch ($block->getId()):
                case 68:
                switch ($block->getDamage()) {
                  case 3:
                  $yaw = 0;
                  break;
                  case 4:
                  $yaw = 0x5a;
                  break;
                  case 2:
                  $yaw = 0xb4;
                  break;
                  case 5:
                  $yaw = 0x10e;
                  break;
                  default:
                  $yaw = 0;
                  break;
                }
                break;
                case 63:
                switch ($block->getDamage()) {
                  case 0:
                  $yaw = 0;
                  break;
                  case 1:
                  $yaw = 22.5;
                  break;
                  case 2:
                  $yaw = 0x2d;
                  break;
                  case 3:
                  $yaw = 67.5;
                  break;
                  case 4:
                  $yaw = 0x5a;
                  break;
                  case 5:
                  $yaw = 112.5;
                  break;
                  case 6:
                  $yaw = 0x87;
                  break;
                  case 7:
                  $yaw = 157.5;
                  break;
                  case 8:
                  $yaw = 0xb4;
                  break;
                  case 9:
                  $yaw = 202.5;
                  break;
                  case 10:
                  $yaw = 0xe1;
                  break;
                  case 11:
                  $yaw = 247.5;
                  break;
                  case 12:
                  $yaw = 0x10e;
                  break;
                  case 13:
                  $yaw = 292.5;
                  break;
                  case 14:
                  $yaw = 0x13b;
                  break;
                  case 15:
                  $yaw = 337.5;
                  break;
                  default:
                  $yaw = 0;
                  break;
                }
                break;
                default:
                $yaw = 0;
              endswitch;
              //knockBack sign direction
              $vector = (new Vector3(-sin(deg2rad($yaw)), 0, cos(deg2rad($yaw))))->normalize();
              $pl->knockBack($pl, 0, $vector->x, $vector->z, ($i / 0xa));
            } else {
              //knockBack sign center
              $pl->knockBack($pl, 0, ($pl->x - ($block->x + 0.5)), ($pl->z - ($block->z + 0.5)), ($i / 0xa));
            }
            break;
          }
          unset($ex, $pl, $x, $y, $z, $radius, $block, $i, $yaw);
        }
      }
    }
  }


  public function onQuit(PlayerQuitEvent $ev)
  {
    foreach ($this->pg->arenas as $a) {
      if ($a->closePlayer($ev->getPlayer(), true))
      break;
    }
  }


  public function onDeath(PlayerDeathEvent $event)
  {
    if ($event->getEntity() instanceof Player) {
      $p = $event->getEntity();
      foreach ($this->pg->arenas as $a) {
        if ($a->closePlayer($p)) {
          $event->setDeathMessage('');
          $cause = $event->getEntity()->getLastDamageCause()->getCause();
          $ev = $event->getEntity()->getLastDamageCause();
          $count = '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']';

          switch ($cause):


            case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
            if ($ev instanceof EntityDamageByEntityEvent) {
              $d = $ev->getDamager();
              if ($d instanceof Player)
              $message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getDisplayName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
              elseif ($d instanceof \pocketmine\entity\Living)
              $message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getNameTag() !== '' ? $d->getNameTag() : $d->getName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
              else
              $message = str_replace('{COUNT}', $count, str_replace('{KILLER}', 'Unknown', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
            }
            break;


            case EntityDamageEvent::CAUSE_PROJECTILE:
            if ($ev instanceof EntityDamageByEntityEvent) {
              $d = $ev->getDamager();
              if ($d instanceof Player)
              $message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getDisplayName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
              elseif ($d instanceof \pocketmine\entity\Living)
              $message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getNameTag() !== '' ? $d->getNameTag() : $d->getName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
              else
              $message = str_replace('{COUNT}', $count, str_replace('{KILLER}', 'Unknown', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
            }
            break;


            case EntityDamageEvent::CAUSE_VOID:
            if($ev instanceof EntityDamageByChildEntityEvent and $ev->getChild() instanceof PrimedTNT) {
              $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.tnt']));
            }else{
              $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.void']));
            }
            break;


            case EntityDamageEvent::CAUSE_LAVA:
            $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.lava']));
            break;


            default:
            $message = str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['game.left']));
            break;


          endswitch;

          foreach ($this->pg->getServer()->getLevelByName($a->getWorld())->getPlayers() as $pl)
          $pl->sendMessage($message);

          if (!$this->pg->configs['drops.on.death'])
          $event->setDrops([]);
          break;
        }
      }
    }
  }


  public function onDamage(EntityDamageEvent $ev)
  {
    if ($ev->getEntity() instanceof Player) {
      $p = $ev->getEntity();
      if ($p->getLevel()->getFolderName() === "Lobby"){
        $ev->setCancelled();
      }
      foreach ($this->pg->arenas as $a) {
        if ($f = $a->inArena($p->getName())) {
          if ($f != 1) {
            $ev->setCancelled();
            break;
          }
          if ($ev instanceof EntityDamageByEntityEvent && ($d = $ev->getDamager()) instanceof Player) {
            if (($f = $a->inArena($d->getName())) == 2 || $f == 0) {
              $ev->setCancelled();
              break;
            }
          }
          if ($a->GAME_STATE == 0 || $a->GAME_STATE == 2) {
            $ev->setCancelled();
            break;
          }

          //SPECTATORS
          $spectate = (bool)$this->pg->configs['death.spectator'];
          if ($spectate && !$ev->isCancelled()) {
            if (($p->getHealth() - $ev->getFinalDamage()) <= 0) {
              $ev->setCancelled();
              //FAKE KILL PLAYER MSG
              $count = '[' . ($a->getSlot(true) - 1) . '/' . $a->getSlot() . ']';
              $cause = $ev->getEntity()->getLastDamageCause()->getCause();
              $message = str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['game.left']));


              switch ($cause):
                case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                if ($ev instanceof EntityDamageByEntityEvent) {
                  $d = $ev->getDamager();
                  $message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getDisplayName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.player'])));
                }
                break;

                case EntityDamageEvent::CAUSE_PROJECTILE:
                if ($ev instanceof EntityDamageByEntityEvent) {
                  $d = $ev->getDamager();
                  $message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $d->getDisplayName(), str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.arrow'])));
                }
                break;

                case EntityDamageEvent::CAUSE_VOID:
                $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.void']));
                break;

                case EntityDamageEvent::CAUSE_LAVA:
                $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.lava']));
                break;

                case EntityDamageEvent::CAUSE_FIRE:
                $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.fire']));
                break;

                case EntityDamageEvent::CAUSE_FIRE_TICK:
                $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.fire']));
                break;

                case EntityDamageEvent::DROWNING:
                $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.drowning']));
                break;

                case EntityDamageEvent::CAUSE_BLOCK_EXPLOSION:
                $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.exploding']));
                break;

                case EntityDamageEvent::CAUSE_FALL:
                $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->getDisplayName(), $this->pg->lang['death.fall']));
                break;
              endswitch;

              foreach ($p->getLevel()->getPlayers() as $pl){
                $pl->sendMessage($message);
              }
              //DROPS
              if ($this->pg->configs['drops.on.death']) {
                foreach ($p->getDrops() as $item) {
                  $p->getLevel()->dropItem($p, $item);
                }
              }
              //CLOSE
              $a->closePlayer($p, false, true);
            }
          }
          break;
        }
      }
    }
  }


  public function onRespawn(PlayerRespawnEvent $ev)
  {
    if ($this->pg->configs['always.spawn.in.defaultLevel'])
    $ev->setRespawnPosition($this->pg->getServer()->getDefaultLevel()->getSpawnLocation());
    //Removes player things
    if ($this->pg->configs['clear.inventory.on.respawn&join'])
    $ev->getPlayer()->getInventory()->clearAll();
    if ($this->pg->configs['clear.effects.on.respawn&join'])
    $ev->getPlayer()->removeAllEffects();
  }


  public function onBreak(BlockBreakEvent $ev)
  {
    if ($ev->getPlayer()->getLevel()->getFolderName() === "Lobby"){
      if (!$ev->getPlayer()->isOP()){
        $ev->setCancelled();
      }
    }
    foreach ($this->pg->arenas as $a) {
      if ($t = $a->inArena($ev->getPlayer()->getName())) {
        if ($t == 2)
        $ev->setCancelled();
        if ($a->GAME_STATE == 0)
        $ev->setCancelled();
        break;

        $block = $ev->getBlock();
        if($block->getID === "19"){
          $this->onLuckyBlockBreak($event)
        }
      }
    }
    if (!$ev->getPlayer()->isOp());
    return;
    $key = (($ev->getBlock()->getX() + 0) . ':' . ($ev->getBlock()->getY() + 0) . ':' . ($ev->getBlock()->getZ() + 0) . ':' . $ev->getPlayer()->getLevel()->getName());
    if (array_key_exists($key, $this->pg->signs)) {
      $this->pg->arenas[$this->pg->signs[$key]]->stop(true);
      $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'Arena reloaded !');
      if ($this->pg->setSign($this->pg->signs[$key], 0, 0, 0, 'world', true, false)) {
        $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::GREEN . 'LI join sign deleted !');
      } else {
        $ev->getPlayer()->sendMessage(TextFormat::AQUA . '>' . TextFormat::RED . 'An error occured, please contact the developer');
      }
    }
    unset($key);
  }

  public function onLuckyBlockBreak($event){
    $player = $event->getPlayer();
    $block = $event->getBlock();
    switch(mt_rand(1,25)){
      case 1:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND,0,1)); //lucky loot:
        $player->getLevel()->dropItem($block, Item::get(Item::GRASS_BLOCK,0,20));
      break;
      case 2:
        $player->getLevel()->dropItem($block, Item::get(Item::COOKED_FISH,0,1));
      break;
      case 3:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND_HELMET,0,1));
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND_BOOTS,0,1));
      break;
      case 4:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND_CHESTPLATE,0,1));
      break;
      case 5:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND_LEGGINGS,0,1));
      break;
      case 6:
        $player->getLevel()->dropItem($block, Item::get(Item::STONE,0,20));
      break;
      case 7:
        $player->getLevel()->dropItem($block, Item::get(Item::COBWEB,0,3));
      break;
      case 8:
        $player->getLevel()->dropItem($block, Item::get(Item::TNT,0,3));
        $player->getLevel()->dropItem($block, Item::get(Item::FLINT_AND_STEEL,0,1));
      break;
      case 9:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND_SWORD,0,1));
      break;
      case 10:
        $player->getLevel()->dropItem($block, Item::get(Item::IRON_PICKAXE,0,1));
        $player->getLevel()->dropItem($block, Item::get(Item::IRON_SHOVEL,0,1));
        $player->getLevel()->dropItem($block, Item::get(Item::IRON_AXE,0,1));
      break;
      case 11:
        $player->getLevel()->dropItem($block, Item::get(Item::STONE_PICKAXE,0,1));
        $player->getLevel()->dropItem($block, Item::get(Item::STONE_SHOVEL,0,1));
        $player->getLevel()->dropItem($block, Item::get(Item::STONE_AXE,0,1));
      break;
      case 12:
        $player->getLevel()->dropItem($block, Item::get(Item::GOLDEN_AXE,0,1));
        $player->getLevel()->dropItem($block, Item::get(Item::GOLDEN_PICKAXE,0,1));
        $player->getLevel()->dropItem($block, Item::get(Item::GOLDEN_SHOVEL,0,1));
      break;
      case 13:
        $player->getLevel()->dropItem($block, Item::get(Item::STONE_SWORD,0,1));
      break;
      case 14:
        $player->getLevel()->dropItem($block, Item::get(Item::GOLDEN_SWORD,0,1));
      break;
      case 15:
        $player->getLevel()->dropItem($block, Item::get(Item::BREAD,0,5));
      break;
      case 16:
        $player->getLevel()->dropItem($block, Item::get(Item::EGG,0,16));
      break;
      case 17:
        $player->getLevel()->dropItem($block, Item::get(Item::COOKED_BEEF,0,3));
      break;
      case 18:
        $player->getLevel()->dropItem($block, Item::get(Item::SPONGE,0,3)); //3 lucky blocks
      break;
      case 19:
        $player->getLevel()->dropItem($block, Item::get(Item::IRON_BLOCK,0,1));
      break;
      case 20:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND_BLOCK,0,1));
      break;
      case 21:
        $player->getLevel()->dropItem($block, Item::get(Item::OBSIDIAN,0,10));
      break;
      case 22:
        $player->getLevel()->dropItem($block, Item::get(Item::BOW,0,1));
        $player->getLevel()->dropItem($block, Item::get(Item::ARROW,0,10));
      break;
      case 23:
        $player->getLevel()->dropItem($block, Item::get(Item::OAK_WOOD,0,20));
      break;
      case 24:
        $player->getLevel()->dropItem($block, Item::get(Item::IRON_INGOT,0,10));
      break;
      case 25:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND,0,10));
      break;
      case 26:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND_AXE,0,1));
      break;
      case 27:
        $player->getLevel()->dropItem($block, Item::get(Item::DIAMOND_PICKAXE,0,1));
      break;

        //unlucky loot:
    }
  }

  public function onPlace(BlockPlaceEvent $ev)
  {
    if ($ev->getPlayer()->getLevel()->getFolderName() === "Lobby"){
      if (!$ev->getPlayer()->isOP()){
        $ev->setCancelled();
      }
    }
    foreach ($this->pg->arenas as $a) {
      if ($t = $a->inArena($ev->getPlayer()->getName())) {$player->getLevel()->dropItem($block, Item::get(Item::STONE_AXE,0,1));
        if ($t == 2)
        $ev->setCancelled();
        if ($a->GAME_STATE == 0)
        $ev->setCancelled();
        break;
      }
    }
  }
}
