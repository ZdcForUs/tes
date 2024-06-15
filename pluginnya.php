<?php

namespace XeonCh\ChasePass;

use Closure;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\inventory\{Inventory, BaseInventory};
use pockstmine\Inventory\transaction\InventoryTransaction;
use pocketmine\event\player\PlayerInteractEvent;
use pockstmine\item\Item;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\item\ItemFactory;
use pocketmine\utils\Config;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\command\{Command, CommandSender};
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\event\Listener;
use pocketmine\scheduler\ClosureTask;
use pocketmine\event\player\{PlayerJoinEvent, PlayerChatEvent, PlayerQuitEvent};

use pocketmine\item\enchantment\{Enchantment, VanillaEnchantments, EnchantmentInstance};
use pocketmine\data\bedrock\EnchantmentIdMap;

use pocketmine\world\sound\PopSound;
use pocketmine\world\sound\AnvilFallSound;

use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use onebone\economyapi\EconomyApi;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use muqsit\invmenu\{InvMenu, InvMenuEventHandler, InvMenuHandler};
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;

class Main extends PluginBase implements Listener{
    
    public $playerList = [];
    private static $instance;
    public $prefix = "§7[§b Chase§fPass §7] §r";
    
    public function onEnable():void{
        if(!InvMenuHandler::isRegistered()){
    	InvMenuHandler::register($this);
        }
        $this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        self::$instance = $this;
        $this->cfg = $this->getConfig();
        $this->saveResource("harga.txt");
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        switch($command->getName()){
            case "chasepass":
                if($sender instanceof Player){
                    if (isset($args[0]) && $sender->hasPermission("chasepass.admin.cmd") || isset($args[0]) && $sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
                        if(isset($args[0]) == "admin"){
                            self::playSound($sender, "random.pop");
                            $this->adminMenu($sender);
                            return true;
                        }
                    }
                    $this->chasePass1($sender);
                    self::playSound($sender, "random.chestopen");
                    return true;
                }
                return false;
        }
        return true;
    }
    
    public function getItemFactory(int $id, int $meta = 0, int $amount = 1)
    {
        return \pocketmine\item\LegacyStringToItemParser::getInstance()->parse("{$id}:{$meta}")->setCount($amount);
    }
    
    public function getFormat(Player $player)
    {
        $format = "";
        if($player->hasPermission("cpass.premium.bypass")){
            $format = "";
        } else {
            $format = "";
        }
        return $format;
    }
    
    public function adminMenu(Player $player)
    {
        $list = [];
        foreach($this->getServer()->getOnlinePlayers() as $p){
            $list[] = $p->getName();
        }
        $this->playerList[$player->getName()] = $list;
	    $form = new \jojoe77777\FormAPI\CustomForm(function($player, $data){
	        if($data === null){
	            self::playSound($player, "mob.villager.no");
	            return true;
	        }
	        if($data[2] == true){
	            $name = $this->playerList[$player->getName()][$data[1]];
	            $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), "setuperm $name cpass.premium.bypass");
	            self::playSound($player, "random.level.up");
	            $player->sendMessage($this->prefix . "You have succeeded in giving $name premium bypass");
	        } else if($data[2] == false){
	            $name = $this->playerList[$player->getName()][$data[1]];
	            $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), "unsetuperm $name cpass.premium.bypass");
	            self::playSound($player, "random.level.up");
	            $player->sendMessage($this->prefix . "You have removed {$name}'s premium chasepass bypass");
	        }
	    });
	    $form->setTitle("§aAdmin Menu");
	    $form->addLabel("§l§6»»§r §fAdd and remove bypass menu, §aDAVA NOOB");
	    $form->addDropdown("Choose Player", $this->playerList[$player->getName()]);
	    $form->addToggle("§aRemove§7/§aAdd", true);
	    $form->sendToPlayer($player);
    }
    
    public function harga(Player $player)
    {
        $form = new \jojoe77777\FormAPI\SimpleForm(function(Player $player, $data = null){
            $result = $data;
            if($result == null){
                self::playSound($player, "mob.villager.yes");
                return true;
            }
        });
        $form->setTitle("§bHarga");
        $form->setContent(file_get_contents($this->getDataFolder() . "harga.txt"));
        $form->addButton("§aConfirm", 0, "textures/ui/confirm");
        $form->sendToPlayer($player);
    }
    
    
    /*
    
  [00] [01] [02] [03] [04] [05] [06] [07] [08]
  [09] [10] (11) [12] (13) [14] (15) [16] [17]
  [18] [19] [20] [21] [22] [23] [24] [25] [26]
  
  */
    
    public function chasePass1(Player $player){
        $inv = $this->menu->getInventory();
        $inv->clearAll();
        $this->menu->setListener(InvMenu::readonly());
        $this->menu->setListener(\Closure::fromCallable([$this, "passListener"]));
        $this->menu->setName("§e» §bChase§fPass §7( §a1 §8|§a 3§8 )");
        $vines = array(0, 9, 18, 27, 36, 8, 17, 26, 35, 44);
        $putih = array(3, 4, 5, 20, 21, 22, 23, 24, 39, 40, 41);
        $hitam = array(1, 2, 6, 7, 19, 25, 37, 38, 42, 43);
        $slotfree = array(10, 11, 12, 13, 14, 15, 16);
        $slotprem = array(28, 29, 30, 31, 32, 33, 34);
        $dauns = array(45, 47, 51, 53);
        $iv = //;
        $enc = EnchantmentIdMap::getInstance()->fromId(17);
        $minecart = $this->getItemFactory(154, 0, 1);
        $glass = $this->getItemFactory(160, 0, 1);
        $glass1 = $this->getItemFactory(160, 7, 1);
        $lily = $this->getItemFactory(111, 0, 1);
        $book = $this->getItemFactory(340, 0, 1);
        $v = $this->getItemFactory(106, 0, 1);
        $barrier = $this->getItemFactory(-161, 0, 1);
        $chest = $this->getItemFactory(54, 0, 1);
        $next = $this->getItemFactory(35, 5, 1);
        $noobv = $this->getItemFactory(35, 1, 1);
        $noob = $this->getItemFactory(154, 0, 1);
        $pro = $this->getItemFactory(54, 0, 1);
        $dava = $this->getItemFactory(328, 1, 1);
        $dava->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $pro->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $noob->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $chest->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $book->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $barrier->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $next->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $noobv->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        foreach($vines as $vine){
            foreach ($putih as $white){
                foreach ($hitam as $black){
                    foreach ($dauns as $daun){
                        $inv->setItem($vine, $v->setCustomName("§8-"));
                        $inv->setItem($white, $glass->setCustomName("§8-"));
                        $inv->setItem($black, $glass1->setCustomName("§8-"));
                        $inv->setItem($daun, $lily->setCustomName("§8-"));
                    }
                }
            }
        }
        // FREE
        if($player->hasPermission("cpass.free-1.perms")){
            $inv->setItem(10, $noob->setCustomName("§7FreePass-#1\n§7Tap To Claim"));
        } else {
            $inv->setItem(10, $dava->setCustomName("§7FreePass-#1\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-2.perms")){
            $inv->setItem(11, $noob->setCustomName("§7FreePass-#2\n§7Tap To Claim"));
        } else {
            $inv->setItem(11, $dava->setCustomName("§7FreePass-#2\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-3.perms")){
            $inv->setItem(12, $noob->setCustomName("§7FreePass-#3\n§7Tap To Claim"));
        } else {
            $inv->setItem(12, $dava->setCustomName("§7FreePass-#3\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-4.perms")){
            $inv->setItem(13, $noob->setCustomName("§7FreePass-#4\n§7Tap To Claim"));
        } else {
            $inv->setItem(13, $dava->setCustomName("§7FreePass-#4\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-5.perms")){
            $inv->setItem(14, $noob->setCustomName("§7FreePass-#5\n§7Tap To Claim"));
        } else {
            $inv->setItem(14, $dava->setCustomName("§7FreePass-#5\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-6.perms")){
            $inv->setItem(15, $noob->setCustomName("§7FreePass-#6\n§7Tap To Claim"));
        } else {
            $inv->setItem(15, $dava->setCustomName("§7FreePass-#6\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-7.perms")){
            $inv->setItem(16, $noob->setCustomName("§7FreePass-#7\n§7Tap To Claim"));
        } else {
            $inv->setItem(16, $dava->setCustomName("§7FreePass-#7\n§7Locked"));
        }
        
        //Premium
        if($player->hasPermission("cpass.prem-1.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(28, $pro->setCustomName("§ePre§gmium§6Pass§7-#1\n§7Tap To Claim"));
        } else {
            $inv->setItem(28, $dava->setCustomName("§7§ePre§gmium§6Pass§7-#1\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-2.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(29, $pro->setCustomName("§ePre§gmium§6Pass§7-#2\n§7Tap To Claim"));
        } else {
            $inv->setItem(29, $dava->setCustomName("§ePre§gmium§6Pass§7-#2\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-3.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(30, $pro->setCustomName("§ePre§gmium§6Pass§7-#3\n§7Tap To Claim"));
        } else {
            $inv->setItem(30, $dava->setCustomName("§ePre§gmium§6Pass§7-#3\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-4.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(31, $pro->setCustomName("§ePre§gmium§6Pass§7-#4\n§7Tap To Claim"));
        } else {
            $inv->setItem(31, $dava->setCustomName("§ePre§gmium§6Pass§7-#4\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-5.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(32, $pro->setCustomName("§ePre§gmium§6Pass§7-#5\n§7Tap To Claim"));
        } else {
            $inv->setItem(32, $dava->setCustomName("§ePre§gmium§6Pass§7-#5\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-6.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(33, $pro->setCustomName("§ePre§gmium§6Pass§7-#6\n§7Tap To Claim"));
        } else {
            $inv->setItem(33, $dava->setCustomName("§ePre§gmium§6Pass§7-#6\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-7.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(34, $pro->setCustomName("§ePre§gmium§6Pass§7-#7\n§7Tap To Claim"));
        } else {
            $inv->setItem(34, $dava->setCustomName("§ePre§gmium§6Pass§7-#2\n§7Locked"));
        }
        $inv->setItem(46, $noobv->setCustomName("§6Previous"));
        $inv->setItem(48, $book->setCustomName("§eHarga §bChase§fPass"));
        $inv->setItem(49, $barrier->setCustomName("§cExit"));
        $inv->setItem(50, $chest->setCustomName("§bAdmin Menu"));
        $inv->setItem(52, $next->setCustomName("§aPage 2"));
        $this->menu->send($player);
    }
    
    public function chasePass2(Player $player){
        $inv = $this->menu->getInventory();
        $inv->clearAll();
        $this->menu->setListener(InvMenu::readonly());
        $this->menu->setListener(\Closure::fromCallable([$this, "passListener"]));
        $this->menu->setName("§e» §bChase§fPass §7( §a2 §8|§a 3§8 )");
        $vines = array(0, 9, 18, 27, 36, 8, 17, 26, 35, 44);
        $putih = array(3, 4, 5, 20, 21, 22, 23, 24, 39, 40, 41);
        $hitam = array(1, 2, 6, 7, 19, 25, 37, 38, 42, 43);
        $slotfree = array(10, 11, 12, 13, 14, 15, 16);
        $slotprem = array(28, 29, 30, 31, 32, 33, 34);
        $dauns = array(45, 47, 51, 53);
        $iv = //;
        $enc = EnchantmentIdMap::getInstance()->fromId(17);
        $minecart = $this->getItemFactory(154, 0, 1);
        $glass = $this->getItemFactory(160, 0, 1);
        $glass1 = $this->getItemFactory(160, 7, 1);
        $lily = $this->getItemFactory(111, 0, 1);
        $book = $this->getItemFactory(340, 0, 1);
        $v = $this->getItemFactory(106, 0, 1);
        $barrier = $this->getItemFactory(-161, 0, 1);
        $chest = $this->getItemFactory(54, 0, 1);
        $next = $this->getItemFactory(35, 5, 1);
        $noobv = $this->getItemFactory(35, 1, 1);
        $noob = $this->getItemFactory(154, 0, 1);
        $pro = $this->getItemFactory(54, 0, 1);
        $dava = $this->getItemFactory(328, 1, 1);
        $dava->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $pro->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $noob->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $chest->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $book->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $barrier->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $next->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $noobv->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        foreach($vines as $vine){
            foreach ($putih as $white){
                foreach ($hitam as $black){
                    foreach ($dauns as $daun){
                        $inv->setItem($vine, $v->setCustomName("§8-"));
                        $inv->setItem($white, $glass->setCustomName("§8-"));
                        $inv->setItem($black, $glass1->setCustomName("§8-"));
                        $inv->setItem($daun, $lily->setCustomName("§8-"));
                    }
                }
            }
        }
        // FREE
        if($player->hasPermission("cpass.free-8.perms")){
            $inv->setItem(10, $noob->setCustomName("§7FreePass-#8\n§7Tap To Claim"));
        } else {
            $inv->setItem(10, $dava->setCustomName("§7FreePass-#8\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-9.perms")){
            $inv->setItem(11, $noob->setCustomName("§7FreePass-#9\n§7Tap To Claim"));
        } else {
            $inv->setItem(11, $dava->setCustomName("§7FreePass-#9\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-10.perms")){
            $inv->setItem(12, $noob->setCustomName("§7FreePass-#10\n§7Tap To Claim"));
        } else {
            $inv->setItem(12, $dava->setCustomName("§7FreePass-#10\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-11.perms")){
            $inv->setItem(13, $noob->setCustomName("§7FreePass-#11\n§7Tap To Claim"));
        } else {
            $inv->setItem(13, $dava->setCustomName("§7FreePass-#11\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-12.perms")){
            $inv->setItem(14, $noob->setCustomName("§7FreePass-#12\n§7Tap To Claim"));
        } else {
            $inv->setItem(14, $dava->setCustomName("§7FreePass-#12\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-13.perms")){
            $inv->setItem(15, $noob->setCustomName("§7FreePass-#13\n§7Tap To Claim"));
        } else {
            $inv->setItem(15, $dava->setCustomName("§7FreePass-#13\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-14.perms")){
            $inv->setItem(16, $noob->setCustomName("§7FreePass-#14\n§7Tap To Claim"));
        } else {
            $inv->setItem(16, $dava->setCustomName("§7FreePass-#14\n§7Locked"));
        }
        
        //Premium
        if($player->hasPermission("cpass.prem-8.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(28, $pro->setCustomName("§ePre§gmium§6Pass§7-#8\n§7Tap To Claim"));
        } else {
            $inv->setItem(28, $dava->setCustomName("§7§ePre§gmium§6Pass§7-#8\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-9.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(29, $pro->setCustomName("§ePre§gmium§6Pass§7-#9\n§7Tap To Claim"));
        } else {
            $inv->setItem(29, $dava->setCustomName("§ePre§gmium§6Pass§7-#9\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-10.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(30, $pro->setCustomName("§ePre§gmium§6Pass§7-#10\n§7Tap To Claim"));
        } else {
            $inv->setItem(30, $dava->setCustomName("§ePre§gmium§6Pass§7-#10\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-11.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(31, $pro->setCustomName("§ePre§gmium§6Pass§7-#11\n§7Tap To Claim"));
        } else {
            $inv->setItem(31, $dava->setCustomName("§ePre§gmium§6Pass§7-#11\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-12.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(32, $pro->setCustomName("§ePre§gmium§6Pass§7-#12\n§7Tap To Claim"));
        } else {
            $inv->setItem(32, $dava->setCustomName("§ePre§gmium§6Pass§7-#12\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-13.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(33, $pro->setCustomName("§ePre§gmium§6Pass§13-#13\n§7Tap To Claim"));
        } else {
            $inv->setItem(33, $dava->setCustomName("§ePre§gmium§6Pass§7-#13\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-14.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(34, $pro->setCustomName("§ePre§gmium§6Pass§7-#14\n§7Tap To Claim"));
        } else {
            $inv->setItem(34, $dava->setCustomName("§ePre§gmium§6Pass§7-#14\n§7Locked"));
        }
        $inv->setItem(46, $noobv->setCustomName("§6Page 1"));
        $inv->setItem(48, $book->setCustomName("§eHarga §bChase§fPass"));
        $inv->setItem(49, $barrier->setCustomName("§cExit"));
        $inv->setItem(50, $chest->setCustomName("§bAdmin Menu"));
        $inv->setItem(52, $next->setCustomName("§aPage 3"));
        $this->menu->send($player);
    }
    
    public function chasePass3(Player $player){
        $inv = $this->menu->getInventory();
        $inv->clearAll();
        $this->menu->setListener(InvMenu::readonly());
        $this->menu->setListener(\Closure::fromCallable([$this, "passListener"]));
        $this->menu->setName("§e» §bChase§fPass §7( §a3 §8|§a 3§8 )");
        $vines = array(0, 9, 18, 27, 36, 8, 17, 26, 35, 44);
        $putih = array(3, 4, 5, 20, 21, 22, 23, 24, 39, 40, 41);
   
        
        $hitam = array(1, 2, 6, 7, 19, 25, 37, 38, 42, 43);
        $slotfree = array(10, 11, 12, 13, 14, 15, 16);
        $slotprem = array(28, 29, 30, 31, 32, 33, 34);
        $dauns = array(45, 47, 51, 53);
        $iv = //;
        $enc = EnchantmentIdMap::getInstance()->fromId(17);
        $minecart = $this->getItemFactory(154, 0, 1);
        $glass = $this->getItemFactory(160, 0, 1);
        $glass1 = $this->getItemFactory(160, 7, 1);
        $lily = $this->getItemFactory(111, 0, 1);
        $book = $this->getItemFactory(340, 0, 1);
        $v = $this->getItemFactory(106, 0, 1);
        $barrier = $this->getItemFactory(-161, 0, 1);
        $chest = $this->getItemFactory(54, 0, 1);
        $next = $this->getItemFactory(35, 5, 1);
        $noobv = $this->getItemFactory(35, 1, 1);
        $noob = $this->getItemFactory(154, 0, 1);
        $pro = $this->getItemFactory(54, 0, 1);
        $dava = $this->getItemFactory(328, 1, 1);
        $max = $this->getItemFactory(407, 0, 1);
        $dava->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $max->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $pro->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $noob->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $chest->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $book->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $barrier->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $next->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        $noobv->addEnchantment(new EnchantmentInstance($enc, (int) 1));
        foreach($vines as $vine){
            foreach ($putih as $white){
                foreach ($hitam as $black){
                    foreach ($dauns as $daun){
                        $inv->setItem($vine, $v->setCustomName("§8-"));
                        $inv->setItem($white, $glass->setCustomName("§8-"));
                        $inv->setItem($black, $glass1->setCustomName("§8-"));
                        $inv->setItem($daun, $lily->setCustomName("§8-"));
                    }
                }
            }
        }
        // FREE
        if($player->hasPermission("cpass.free-15.perms")){
            $inv->setItem(10, $noob->setCustomName("§7FreePass-#15\n§7Tap To Claim"));
        } else {
            $inv->setItem(10, $dava->setCustomName("§7FreePass-#15\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-16.perms")){
            $inv->setItem(11, $noob->setCustomName("§7FreePass-#16\n§7Tap To Claim"));
        } else {
            $inv->setItem(11, $dava->setCustomName("§7FreePass-#16\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-17.perms")){
            $inv->setItem(12, $noob->setCustomName("§7FreePass-#17\n§7Tap To Claim"));
        } else {
            $inv->setItem(12, $dava->setCustomName("§7FreePass-#17\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-18.perms")){
            $inv->setItem(13, $noob->setCustomName("§7FreePass-#18\n§7Tap To Claim"));
        } else {
            $inv->setItem(13, $dava->setCustomName("§7FreePass-#18\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-19.perms")){
            $inv->setItem(14, $noob->setCustomName("§7FreePass-#19\n§7Tap To Claim"));
        } else {
            $inv->setItem(14, $dava->setCustomName("§7FreePass-#19\n§7Locked"));
        }
        if($player->hasPermission("cpass.free-20.perms")){
            $inv->setItem(15, $noob->setCustomName("§7FreePass-#20\n§7Tap To Claim"));
        } else {
            $inv->setItem(15, $dava->setCustomName("§7FreePass-#20\n§7Locked"));
        }
        
        //Premium
        if($player->hasPermission("cpass.prem-15.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(28, $pro->setCustomName("§ePre§gmium§6Pass§7-#15\n§7Tap To Claim"));
        } else {
            $inv->setItem(28, $dava->setCustomName("§7§ePre§gmium§6Pass§7-#15\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-16.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(29, $pro->setCustomName("§ePre§gmium§6Pass§7-#16\n§7Tap To Claim"));
        } else {
            $inv->setItem(29, $dava->setCustomName("§ePre§gmium§6Pass§7-#16\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-17.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(30, $pro->setCustomName("§ePre§gmium§6Pass§7-#17\n§7Tap To Claim"));
        } else {
            $inv->setItem(30, $dava->setCustomName("§ePre§gmium§6Pass§7-#17\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-18.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(31, $pro->setCustomName("§ePre§gmium§6Pass§7-#18\n§7Tap To Claim"));
        } else {
            $inv->setItem(31, $dava->setCustomName("§ePre§gmium§6Pass§7-#18\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-19.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(32, $pro->setCustomName("§ePre§gmium§6Pass§7-#19\n§7Tap To Claim"));
        } else {
            $inv->setItem(32, $dava->setCustomName("§ePre§gmium§6Pass§7-#19\n§7Locked"));
        }
        if($player->hasPermission("cpass.prem-20.perms") && $player->hasPermission("cpass.premium.bypass")){
            $inv->setItem(33, $pro->setCustomName("§ePre§gmium§6Pass§13-#20\n§7Tap To Claim"));
        } else {
            $inv->setItem(33, $dava->setCustomName("§ePre§gmium§6Pass§7-#20\n§7Locked"));
        }
        
        $inv->setItem(34, $max->setCustomName("§cComing soon"));
        $inv->setItem(16, $max->setCustomName("§cComing soon"));
        $inv->setItem(46, $noobv->setCustomName("§6Page 2"));
        $inv->setItem(48, $book->setCustomName("§eHarga §bChase§fPass"));
        $inv->setItem(49, $barrier->setCustomName("§cExit"));
        $inv->setItem(50, $chest->setCustomName("§bAdmin Menu"));
        $inv->setItem(52, $next->setCustomName("§aFull Page"));
        $this->menu->send($player);
    }
    
    public function passListener(InvMenuTransaction $transaction) : InvMenuTransactionResult{
        $player = $transaction->getPlayer();
        $action = $transaction->getAction();
        $inv = $transaction->getAction()->getInventory();
        $item = $transaction->getItemClicked();
        $eco = EconomyAPI::getInstance();
        $iv = //;
        $id = str_replace("§7FreePass-#", "", $item->getName());
        $i = explode("\n", $id);
        $ids = $i[0];
        if($item->getCustomName() == "§7FreePass-#{$ids}\n§7Tap To Claim"){
            $data = $this->cfg->getAll()["reward"]["cpass-free"][$ids];
            $msg = str_replace("{player}", $player->getName(), $data["message"]);
            $cmd = str_replace("{player}", $player->getName(), $data["command"]);
            $player->sendMessage($this->prefix . $msg);
            foreach($cmd as $cmds){
                $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), $cmds);
            }
            self::playSound($player, "random.levelup");
            $player->removeCurrentWindow();
        } else if($item->getCustomName() == "§7FreePass-#{$ids}\n§7Locked"){
            self::playSound($player, "random.chestclosed");
            $player->removeCurrentWindow();
            $player->sendMessage($this->prefix . "§cYou can't claim this free chasepass");
        }
        $idp = str_replace("§ePre§gmium§6Pass§7-#", "", $item->getName());
        $ip = explode("\n", $idp);
        $idsp = $ip[0];
        if($item->getCustomName() == "§ePre§gmium§6Pass§7-#{$idsp}\n§7Tap To Claim"){
            $data = $this->cfg->getAll()["reward"]["cpass-premium"][$idsp];
            $msg = str_replace("{player}", $player->getName(), $data["message"]);
            $cmd = str_replace("{player}", $player->getName(), $data["command"]);
            $player->sendMessage($this->prefix . $msg);
            foreach($cmd as $cmds){
                $this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), $cmds);
            }
            self::playSound($player, "random.levelup");
            $player->removeCurrentWindow();
        } else if($item->getCustomName() == "§ePre§gmium§6Pass§7-#{$idsp}\n§7Locked"){
            $player->sendMessage($this->prefix . "§cYou can't claim this premium chasepass");
            self::playSound($player, "random.chestclosed");
            $player->removeCurrentWindow();
        }
        if ($item->getId() == 54) {
            if ($player->hasPermission("chasepass.admin.cmd")) {
                self::playSound($player, "random.chestclosed");
                $player->removeCurrentWindow();
                $this->adminMenu($player);
            } else {
                self::playSound($player, "mob.villager.no");
                $player->removeCurrentWindow();
            }
        } elseif ($item->getId() == 340) {
            self::playSound($player, "random.chestclosed");
            $player->removeCurrentWindow();
            $this->harga($player);
        } else {
            $customName = $item->getCustomName();
            if ($customName !== null) {
                switch ($customName) {
                    case "§aPage 2":
                    case "§6Page 2":
                        self::playSound($player, "random.pop");
                        $this->chasePass2($player);
                        break;
                    case "§aPage 3":
                        self::playSound($player, "random.pop");
                        $this->chasePass3($player);
                        break;
                    case "§6Page 1":
                        self::playSound($player, "random.pop");
                        $this->chasePass1($player);
                        break;
                    case "§cExit":
                        self::playSound($player, "random.chestclosed");
                        $player->removeCurrentWindow();
                        break;
                    // Tambahkan case untuk customName lain jika diperlukan
                    default:
                        break;
                }
            }
        }
        
        return $transaction->discard();
        
        // Fungsi playSound tetap sama
        
        public static function getInstance() {
            return self::$instance;
        }
        
