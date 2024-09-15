<?php

declare(strict_types=1);

namespace Skso;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\item\Armor;
use onebone\economyapi\EconomyAPI;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;

class AnvilUI extends PluginBase implements Listener {

    private Config $settings;
    private EconomyAPI $economyAPI;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->settings = new Config($this->getDataFolder() . "settings.yml", Config::YAML);
        $this->economyAPI = EconomyAPI::getInstance();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if ($block->getTypeId() === VanillaBlocks::ANVIL()->getTypeId()) {
            $this->showAnvilOptions($player);
            $event->cancel();
        }
    }

    private function showAnvilOptions(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $choice) {
            if ($choice !== null) {
                switch ($choice) {
                    case 0:
                        $this->displayRepairForm($player);
                        break;
                    case 1:
                        $this->displayRenameForm($player);
                        break;
                }
            }
        });

        $form->setTitle("§Anvil");
        $form->setContent("Choisissez une option :");

        $repairFee = (int) $this->settings->get("repair", 500);
        $renameFee = (int) $this->settings->get("rename", 250);

        $form->addButton("Réparer l'objet\nCoût : " . $repairFee);
        $form->addButton("Renommer l'objet\nCoût : " . $renameFee);
        $form->addButton("Annuler");

        $player->sendForm($form);
    }

    private function displayRepairForm(Player $player): void {
        $repairFee = (int) $this->settings->get("repair", 500);
        $playerBalance = $this->economyAPI->myMoney($player);

        if ($playerBalance < $repairFee) {
            $player->sendMessage("Solde insuffisant pour réparer l'objet.");
            return;
        }

        $item = $player->getInventory()->getItem($player->getInventory()->getHeldItemIndex());

        if ($item instanceof Tool || $item instanceof Armor) {
            if ($item->getDamage() === 0) {
                $player->sendMessage("L'objet est déjà en parfait état.");
                return;
            }

            $this->economyAPI->reduceMoney($player, $repairFee);

            $item->setDamage(0);
            $player->getInventory()->setItem($player->getInventory()->getHeldItemIndex(), $item);

            $player->sendMessage("L'objet a été réparé pour " . $repairFee . ".");
        } else {
            $player->sendMessage("Cet objet ne peut pas être réparé.");
        }
    }

    private function displayRenameForm(Player $player): void {
        $renameFee = (int) $this->settings->get("rename", 250);
        $playerBalance = $this->economyAPI->myMoney($player);

        if ($playerBalance < $renameFee) {
            $player->sendMessage("Solde insuffisant pour renommer l'objet.");
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $formData) {
            if ($formData !== null && isset($formData[1])) {
                $newName = (string) $formData[1];
                $this->renameItem($player, $newName);
            }
        });

        $form->setTitle("Renommer l'objet");
        $form->addLabel("Votre solde actuel est de " . $playerBalance . ".");
        $form->addInput("Entrez le nouveau nom :");

        $player->sendForm($form);
    }

    private function renameItem(Player $player, string $newName): void {
        $renameFee = (int) $this->settings->get("rename", 250);
        $playerBalance = $this->economyAPI->myMoney($player);

        if ($playerBalance < $renameFee) {
            $player->sendMessage("Solde insuffisant pour renommer l'objet.");
            return;
        }

        $this->economyAPI->reduceMoney($player, $renameFee);

        $item = $player->getInventory()->getItem($player->getInventory()->getHeldItemIndex());
        $item->setCustomName($newName);
        $player->getInventory()->setItem($player->getInventory()->getHeldItemIndex(), $item);

        $player->sendMessage("L'objet a été renommé en " . $newName . " pour " . $renameFee . ".");
    }

    public function onDisable(): void {
    }
}
