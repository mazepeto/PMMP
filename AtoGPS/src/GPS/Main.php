<?php
namespace GPS;

use pocketmine\event\Listener;

use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\level\particle\HeartParticle;

use pocketmine\level\Position;

use pocketmine\item\ItemIds;

use pocketmine\plugin\PluginBase;

use pocketmine\Player;

use pocketmine\form\Form;

use pocketmine\scheduler\Task;

use pocketmine\utils\Config;

use pocketmine\math\Vector3;

use GPS\Command\GpsCommand;

class Main extends PluginBase implements Listener {

  const GPS_REMOVE_MODE = 0;
  const GPS_SELECT_MODE = 1;

  public function onEnable(){
    $this-> getServer()-> getPluginManager()-> registerEvents($this, $this);
    $this->getServer()->getCommandMap()->register('AndaMiro', new GpsCommand($this));
    $this->getScheduler()->scheduleRepeatingTask(new GpsTask($this), 10);
    $this->data = new Config($this->getDataFolder() . "Gps.yml", Config::YAML);
    $this->db = $this->data->getAll();

    if($this->db == []){
      $this->db = ["G" => [], "P" => []];
      $this->save();
    }
  }

  public function onCompas(PlayerInteractEvent $event){
    if($event->getItem()->getId() == ItemIds::COMPASS)
    $this->showList($event->getPlayer());
  }

  public function existGps(string $level, string $name) : bool{
    return isset($this->db["G"][$level][$name]);
  }

  public function addGps(Player $player, string $name){
    if($this->existGps($player->level->getFolderName(), $name)){
      $player->sendMessage("해당 월드의 네비게이션에 " . $name . " (이)라는 이름을 가진 목적지가 이미 존재합니다.");
      return;
    }

    $this->db["G"][$player->level->getFolderName()][$name] = intval($player->x) . ":" . intval($player->y) . ":" . intval($player->z);
    $this->save();
    $player->sendMessage("현재 위치에 " . $name . " (이)라는 목적지를 추가하였습니다.");
  }

  public function removeGps(Player $player, string $level, string $name){
    if(!isset($this->db["G"][$level][$name])){
      $player->sendMessage("해당 월드의 네비게이션에 " . $name . " (이)라는 이름을 가진 목적지는 존재하지 않습니다.");
      return;
    }

    unset($this->db["G"][$level][$name]);
    $this->save();
    $player->sendMessage("해당 월드의 네비게이션에서 " . $name . " (이)라는 목적지를 제거하였습니다.");
  }

  public function selectGps(Player $player, string $name){
    if(!isset($this->db["G"][$player->level->getFolderName()][$name])){
      $player->sendMessage("해당 월드의 네비게이션에 " . $name . " (이)라는 이름을 가진 목적지는 존재하지 않습니다.");
      return;
    }

    $loc = $this->db["G"][$player->level->getFolderName()][$name];
    $this->db["P"][strtolower($player->getName())] = ["LEVEL" => $player->level->getFolderName(), "LOC" => $loc, "NAME" => $name];
    $this->save();

    $player->sendMessage("목적지, " . $name . " (으)로 길 안내를 시작합니다.");
  }

  public function getGps(Player $player) : ?array{
    if(!isset($this->db["P"][strtolower($player->getName())]))
    return null;

    $ex = explode(":", $this->db["P"][strtolower($player->getName())]["LOC"]);
    return ["LOC" => new Vector3($ex[0], $ex[1], $ex[2]), "LEVEL" => $this->db["P"][strtolower($player->getName())]["LEVEL"]];
  }

  public function clearGps(Player $player){
    unset($this->db["P"][strtolower($player->getName())]);
    $this->save();
  }

  public function showList(Player $player, int $mode = 1){
    if(!isset($this->db["G"][$player->level->getFolderName()])){
      $player->sendMessage("해당 월드에는 네비게이션이 등록되어있지 않습니다.");
      return;
    }

    $player->sendForm(new class($this->db, $player, $this, $mode) implements Form{
      private $data;
      private $player;
      private $plugin;
      private $mode;

      public function __construct(array $data, Player $player, $plugin, int $mode){
        $this->data = $data;
        $this->player = $player;
        $this->plugin = $plugin;
        $this->mode = $mode;
      }

      public function jsonSerialize(){
        $arr = [];
        $text = "";
        switch($this->mode){
          case $this->plugin::GPS_REMOVE_MODE :
          $text = "제거를 원하시는 목적지를 선택해주세요.";
          break;

          case $this->plugin::GPS_SELECT_MODE :
          $text = "길안내를 원하시는 목적지를 선택해주세요.";
          break;

          default :
          $text = "길안내를 원하시는 목적지를 선택해주세요.";
          break;
        }

        foreach($this->data["G"][$this->player->level->getFolderName()] as $name => $_)
        array_push($arr, array("text" => $name));

        return [
          "type" => "form",
          "title" => "네비게이션",
          "content" => $text,
          "buttons" => $arr
        ];
      }

      public function handleResponse(Player $player, $data) : void{
        if(is_null($data))
        return;

        switch($this->mode){
          case $this->plugin::GPS_REMOVE_MODE :
          $this->plugin->removeGps($player, $player->level->getFolderName(), array_keys($this->data["G"][$this->player->level->getFolderName()])[$data]);
          break;

          case $this->plugin::GPS_SELECT_MODE :
          $this->plugin->selectGps($player, array_keys($this->data["G"][$this->player->level->getFolderName()])[$data]);
          break;

          default :
          $this->plugin->selectGps($player, array_keys($this->data["G"][$this->player->level->getFolderName()])[$data]);
          break;
        }
      }
    });
  }

  public function save(){
    $this->data->setAll($this->db);
    $this->data->save();
  }
}

class GpsTask extends Task{

  private $plugin;

  public function __construct(Main $plugin){
    $this->plugin = $plugin;
  }

  public function onRun(int $currentTick){
    foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
      if($this->plugin->getGps($player) != null){
        if($this->plugin->getGps($player)["LEVEL"] != $player->level->getFolderName()){
          $this->plugin->clearGps($player);
          $player->sendMessage("다른 월드로 이동하셔서 길안내가 종료됩니다.");
        }
        else{
          $loc = $this->plugin->getGps($player)["LOC"];
          $goal = new Position($loc->x, $player->y, $loc->z, $player->level);
          if($player->distance($goal) <= 10){
            $this->plugin->clearGps($player);
            $player->sendMessage("목적지에 도착 했습니다, 길 안내를 종료합니다.");
          }

          $gps = $player->distance($loc);
          $x =  $player->x + 3 * ($loc->x - $player->x) / $gps;
          $z = $player->z + 3 * ($loc->z - $player->z) / $gps;
          $player->level->addParticle(new HeartParticle(new Position($x, $player->y + 2, $z, $player->level)));
        }
      }
    }
  }
}
