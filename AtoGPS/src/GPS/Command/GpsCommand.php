<?php

namespace GPS\Command;

use pocketmine\command\{Command, CommandSender};

class GpsCommand extends Command{
  private $plugin;

  public function __construct($plugin){
    $this->plugin = $plugin;
    parent::__construct("네비게이션", "네비게이션 관련 명령어입니다.", '/네비게이션', []);
  }

  public function execute(CommandSender $sender, string $command, array $args){
    if(!$sender->isOp())
    return;

    if(!isset($args[0])){
      $sender->sendMessage("/네비게이션 <추가, 제거>");
      return;
    }

    switch($args[0]){
      case "추가" :
      if(!isset($args[1])){
        $sender->sendMessage("/네비게이션 추가 <목적지 이름>");
        return;
      }
      $this->plugin->addGps($sender, $args[1]);
      break;

      case "제거" :
      $this->plugin->showList($sender, 0);
      break;
    }
  }
}
