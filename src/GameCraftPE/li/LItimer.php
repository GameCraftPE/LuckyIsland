<?php


namespace GameCraftPE\li;


use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat as TF;
use pocketmine\event\entity\EntityInventoryChangeEvent;


class LItimer extends PluginTask
{
    /** @var int */
    private $seconds = 0;
    /** @var bool */
    private $tick = false;

    private $plugin;

    public function __construct(LImain $plugin)
    {
        parent::__construct($plugin);
        $this->tick = (bool)$plugin->configs['sign.tick'];
        $this->plugin = $plugin;
    }


    public function onRun(int $tick)
    {
        $pl = $this->plugin->getServer()->getOnlinePlayers();
        foreach($pl as $p){
          if($p->getLevel()->getFolderName() === "Lobby"){
            if(!$p->getInventory()->getItemInHand()->hasEnchantments()){
                $p->sendPopup(TF::GRAY."You are playing on ".TF::BOLD.TF::BLUE."GameCraft PE LuckyIsland".TF::RESET."\n".TF::DARK_GRAY."[".TF::LIGHT_PURPLE.count($this->plugin->getServer()->getOnlinePlayers()).TF::DARK_GRAY."/".TF::LIGHT_PURPLE.$this->plugin->getServer()->getMaxPlayers().TF::DARK_GRAY."] | ".TF::YELLOW."$".$this->plugin->getServer()->getPluginManager()->getPlugin("EconomyAPI")->myMoney($p).TF::DARK_GRAY." | ".TF::BOLD.TF::AQUA."Vote: ".TF::RESET.TF::GREEN."vote.gamecraftpe.tk");
            }
          }
        }
        foreach ($this->getOwner()->arenas as $LIname => $LIarena)
            $LIarena->tick();
        if ($this->tick) {
            if (($this->seconds % 5 == 0))
                $this->getOwner()->refreshSigns();
            $this->seconds++;
        }
    }
}
