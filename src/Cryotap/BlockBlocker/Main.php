<?php

namespace Cryotap\BlockBlocker;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\player\Player;
use Vecnavium\FormsUI\SimpleForm;
use Vecnavium\FormsUI\CustomForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class Main extends PluginBase implements Listener {

    public function onEnable(): Void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onBlockPlace(BlockPlaceEvent $event) {
        $block = $event->getItem();
        $world = $event->getPlayer()->getWorld()->getFolderName();
		$this->reloadConfig();
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		if (!$event->getPlayer()->hasPermission("blockblocker.disable.bypass")) {
		$blocksDisabled = $config->get("blocksDisabled", []);
        $blockDisabledWorlds = $config->get("blockDisabledWorlds", []);

        // Check if the block ID is listed in the disabled blocks array
        $isBlockDisabled = in_array($block->getName(), $blocksDisabled);

        // Check if the block's world is listed in the disabled worlds array
        $isWorldDisabled = in_array($world, $blockDisabledWorlds);
		
        if ($config->get("disableAll") === true && $config->get("disableBlocker") === false && $isWorldDisabled) {
            $event->cancel();
            return;
        }

        if ($isBlockDisabled && $isWorldDisabled && $config->get("disableBlocker") === false) {
            $event->cancel();
        }
		}
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "bldisable") {
            if ($sender instanceof Player) {
                if ($sender->hasPermission("blockblocker.disable.editor")) {
                    $this->openMainForm($sender);
                } else {
                    $sender->sendMessage("You don't have permission to use this command.");
                }
            } else {
                $sender->sendMessage("Please run this command in-game.");
            }
            return true;
        }
        return false;
    }

    public function openMainForm(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data !== null) {
                switch ($data) {
                    case 0: // Toggle disableBlocker
                        $this->toggleDisableBlocker($player);
                        break;
                    case 1: // Toggle disableAll
                        $this->toggleDisableAll($player);
                        break;
                    case 2: // Open form to add a world to the blocked world list
                        $this->openAddBlockedWorldForm($player);
                        break;
                    case 3: // Open form to add a block to the disabled block list
                        $this->openAddDisabledBlockForm($player);
                        break;
                    case 4: // Open form to list blocked worlds
                        $this->openBlockedWorldsListForm($player);
                        break;
                    case 5: // Open form to list blocked blocks
                        $this->openBlockedBlocksListForm($player);
                        break;
                }
            }
        });

        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $disableBlocker = $config->get("disableBlocker", false) ? "Enabled" : "Disabled";
        $disableAll = $config->get("disableAll", false) ? "Enabled" : "Disabled";

        $form->setTitle("Block Disable Settings");
        $form->setContent("Current settings:\nDisable Blocker: $disableBlocker\nDisable All: $disableAll");

        $form->addButton("Toggle Disable Blocker");
        $form->addButton("Toggle Disable All");
        $form->addButton("Add Blocked World");
        $form->addButton("Add Disabled Block");
        $form->addButton("List Blocked Worlds");
        $form->addButton("List Blocked Blocks");

        $player->sendForm($form);
    }

    public function toggleDisableBlocker(Player $player): void {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $disableBlocker = !$config->get("disableBlocker", false);
        $config->set("disableBlocker", $disableBlocker);
        $config->save();

        $player->sendMessage("Blocker disable status set to: " . ($disableBlocker ? "Enabled" : "Disabled"));
    }

    public function toggleDisableAll(Player $player): void {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $disableAll = !$config->get("disableAll", false);
        $config->set("disableAll", $disableAll);
        $config->save();

        $player->sendMessage("Disable all status set to: " . ($disableAll ? "Enabled" : "Disabled"));
    }

    public function openAddBlockedWorldForm(Player $player): void {
        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data !== null) {
                $worldName = $data[0];
                if (!empty($worldName)) {
                    $this->addBlockedWorld($player, $worldName);
                }
            }
        });

        $form->setTitle("Add Blocked World");
        $form->addInput("Enter the name of the world to block:", "World Name");

        $player->sendForm($form);
    }

    public function addBlockedWorld(Player $player, string $worldName): void {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $blockedWorlds = $config->get("blockDisabledWorlds", []);
        $worldName = trim($worldName);

        if (!in_array($worldName, $blockedWorlds)) {
            $blockedWorlds[] = $worldName;
            $config->set("blockDisabledWorlds", $blockedWorlds);
            $config->save();
            $player->sendMessage("Added world '$worldName' to the blocked world list.");
        } else {
            $player->sendMessage("World '$worldName' is already in the blocked world list.");
        }
    }

    public function openAddDisabledBlockForm(Player $player): void {
        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data !== null) {
                $blockName = $data[0];
                    $this->addDisabledBlock($player, $blockName);
            }
        });

        $form->setTitle("Add Disabled Block");
        $form->addInput("Enter the name of the block to disable:", "Block Name");

        $player->sendForm($form);
    }

    public function addDisabledBlock(Player $player, $blockName): void {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $disabledBlocks = $config->get("blocksDisabled", []);

        if (!in_array($blockName, $disabledBlocks)) {
            $disabledBlocks[] = $blockName;
            $config->set("blocksDisabled", $disabledBlocks);
            $config->save();
            $player->sendMessage("Added block with the name '$blockName' to the disabled block list.");
        } else {
            $player->sendMessage("Block with name '$blockName' is already in the disabled block list.");
        }
    }

    public function openBlockedWorldsListForm(Player $player): void {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $blockedWorlds = $config->get("blockDisabledWorlds", []);

        $form = new SimpleForm(function (Player $player, ?int $data) use ($blockedWorlds) {
            if ($data !== null) {
                $worldName = $blockedWorlds[$data] ?? null;
                if ($worldName !== null) {
                    $this->removeBlockedWorld($player, $worldName);
                }
            }
        });

        $form->setTitle("Blocked Worlds");
        foreach ($blockedWorlds as $worldName) {
            $form->addButton($worldName);
        }

        $player->sendForm($form);
    }

    public function removeBlockedWorld(Player $player, string $worldName): void {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $blockedWorlds = $config->get("blockDisabledWorlds", []);

        if (($key = array_search($worldName, $blockedWorlds)) !== false) {
            unset($blockedWorlds[$key]);
            $config->set("blockDisabledWorlds", array_values($blockedWorlds));
            $config->save();
            $player->sendMessage("Removed world '$worldName' from the blocked world list.");
        } else {
            $player->sendMessage("World '$worldName' was not found in the blocked world list.");
        }
    }

    public function openBlockedBlocksListForm(Player $player): void {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $disabledBlocks = $config->get("blocksDisabled", []);

        $form = new SimpleForm(function (Player $player, ?int $data) use ($disabledBlocks) {
            if ($data !== null) {
                $blockName = $disabledBlocks[$data] ?? null;
                if ($blockName !== null) {
                    $this->removeDisabledBlock($player, $blockName);
                }
            }
        });

        $form->setTitle("Blocked Blocks");
        foreach ($disabledBlocks as $blockName) {
            $form->addButton("$blockName");
        }

        $player->sendForm($form);
    }

    public function removeDisabledBlock(Player $player, $blockName): void {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $disabledBlocks = $config->get("blocksDisabled", []);

        if (($key = array_search($blockName, $disabledBlocks)) !== false) {
            unset($disabledBlocks[$key]);
            $config->set("blocksDisabled", array_values($disabledBlocks));
            $config->save();
            $player->sendMessage("Removed block with name '$blockName' from the disabled block list.");
        } else {
            $player->sendMessage("Block with name '$blockName' was not found in the disabled block list.");
        }
    }
}
