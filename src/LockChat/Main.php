<?php

declare(strict_types=1);

namespace LockChat;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\scheduler\Task;

class Main extends PluginBase implements Listener {

    protected bool $chatLocked = false;

    protected function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onChat(PlayerChatEvent $event): void {
        if ($this->chatLocked && !$event->getPlayer()->hasPermission("operator")) {
            $event->cancel();
            $event->getPlayer()->sendMessage(TextFormat::RED . "Le chat est actuellement verrouillé.");
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "lockchat" && $sender->hasPermission("lockchat.command.lock")) {
            if (isset($args[0])) {
                $duration = $args[0];
                $this->lockChat($duration);
                $sender->sendMessage(TextFormat::GREEN . "Le chat est verrouillé pour " . $duration);
            } else {
                $this->chatLocked = true;
                $sender->sendMessage(TextFormat::GREEN . "Le chat est verrouillé de manière permanente.");
            }
            return true;
        }

        if ($command->getName() === "unlockchat" && $sender->hasPermission("lockchat.command.unlock")) {
            $this->chatLocked = false;
            $sender->sendMessage(TextFormat::GREEN . "Le chat a été débloqué.");
            return true;
        }

        return false;
    }

    private function lockChat(string $duration): void {
        $this->chatLocked = true;

        // Convertir la durée en secondes
        $seconds = $this->parseDuration($duration);
        if ($seconds > 0) {
            // Planifier le déblocage du chat après la durée spécifiée
            $this->getScheduler()->scheduleDelayedTask(new class($this) extends Task {
                private Main $plugin;

                public function __construct(Main $plugin) {
                    $this->plugin = $plugin;
                }

                public function onRun(): void {
                    $this->plugin->setChatLocked(false);
                    $this->plugin->getServer()->broadcastMessage(TextFormat::GREEN . "Le chat a été débloqué.");
                }
            }, $seconds * 20); // 20 ticks = 1 seconde
        }
    }

    public function setChatLocked(bool $locked): void {
        $this->chatLocked = $locked;
    }

    private function parseDuration(string $duration): int {
        $seconds = 0;
        preg_match_all('/(\d+)([dhms])/', $duration, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $value = (int)$match[1];
            $unit = $match[2];
            switch ($unit) {
                case 'd':
                    $seconds += $value * 86400; // 24h * 60m * 60s
                    break;
                case 'h':
                    $seconds += $value * 3600; // 60m * 60s
                    break;
                case 'm':
                    $seconds += $value * 60; // 60s
                    break;
                case 's':
                    $seconds += $value; // secondes
                    break;
            }
        }
        return $seconds;
    }
}
