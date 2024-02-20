<?php

declare(strict_types=1);

namespace PrograMistV1\Weather;

use Exception;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\world\WorldInitEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use PrograMistV1\Weather\commands\WeatherCommand;
use PrograMistV1\Weather\events\ThunderBoltSpawnEvent;
use PrograMistV1\Weather\events\WeatherChangeEvent;
use Symfony\Component\Filesystem\Path;

class Weather extends PluginBase implements Listener{
    use SingletonTrait;

    public const CLEAR = 0;
    public const RAIN = 1;
    public const THUNDER = 2;
    public const COMMAND_WEATHER = "vanillaweather.weather.command";

    public const CHANGE_WEATHER = "weatherChange";
    public const CREATE_LIGHTNING = "createLightning";
    public const LIGHTNING_FIRE = "lightningFire";
    public const DAMAGE_FROM_LIGHTNING = "damageFromLightning";
    public const CREATE_SNOW_LAYERS = "createSnowLayers";

    private Config $config;

    protected function onEnable() : void{
        self::setInstance($this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new WeatherTask(
            $this->getServer()->getWorldManager()
        ), 1);
        $this->getServer()->getCommandMap()->register("vanillaweather", new WeatherCommand($this));

        $this->config = new Config(Path::join($this->getDataFolder(), "config.yml"), Config::YAML, [
            "default" => [
                self::CHANGE_WEATHER => true,
                self::CREATE_LIGHTNING => true,
                self::LIGHTNING_FIRE => false,
                self::DAMAGE_FROM_LIGHTNING => false,
                self::CREATE_SNOW_LAYERS => true
            ]
        ]);
    }

    public function onPlayerJoin(PlayerJoinEvent $event) : void{
        self::changeWeatherForPlayer($event->getPlayer());
    }

    public static function changeWeather(World $world, int $weather, int $time = 6000) : void{
        $ev = new WeatherChangeEvent($world);
        $ev->call();
        if($ev->isCancelled()){
            return;
        }
        $worldData = $world->getProvider()->getWorldData();
        $worldData->setRainTime($time);
        $worldData->setRainLevel(match ($weather) {
            self::RAIN => 0.5,
            self::THUNDER => 1,
            default => 0
        });
        if($weather === self::RAIN){
            $packets = [LevelEventPacket::create(LevelEvent::START_RAIN, 65535, null)];
        }elseif($weather === self::THUNDER){
            $packets = [LevelEventPacket::create(LevelEvent::START_THUNDER, 65535, null)];
        }else{
            $packets = [
                LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, null),
                LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null)
            ];
        }
        foreach($world->getPlayers() as $player){
            foreach($packets as $packet){
                $player->getNetworkSession()->sendDataPacket($packet);
            }
        }
    }

    public static function changeWeatherForPlayer(Player $player, ?World $world = null) : void{
        $world ?? $world = $player->getWorld();
        $level = $world->getProvider()->getWorldData()->getRainLevel();
        if($level == 0.5){
            $packets = [LevelEventPacket::create(LevelEvent::START_RAIN, 65535, null)];
        }elseif($level == 1){
            $packets = [LevelEventPacket::create(LevelEvent::START_THUNDER, 65535, null)];
        }else{
            $packets = [
                LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, null),
                LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null)
            ];
        }
        NetworkBroadcastUtils::broadcastPackets([$player], $packets);
    }

    public static function generateThunderBolt(World $world, int $x, int $y, int $z, bool $doFire = false) : void{
        $ev = new ThunderBoltSpawnEvent($world, $x, $y, $z, $doFire);
        $ev->call();
        if($ev->isCancelled()){
            return;
        }
        $id = Entity::nextRuntimeId();
        $packets[] = AddActorPacket::create(
            $id,
            $id,
            "minecraft:lightning_bolt",
            new Vector3($x, $y, $z),
            null,
            0,
            0,
            0,
            0,
            [],
            [],
            new PropertySyncData([], []),
            []
        );
        $packets[] = PlaySoundPacket::create(
            "ambient.weather.thunder",
            $x,
            $y,
            $z,
            10,
            1
        );
        NetworkBroadcastUtils::broadcastPackets($world->getPlayers(), $packets);
        if($ev->isDoFire()){
            $block = $world->getBlockAt($x, $y + 1, $z);
            if($block->getTypeId() === BlockTypeIds::AIR){
                $world->setBlockAt($x, $y + 1, $z, VanillaBlocks::FIRE());
            }
        }
    }

    public function onPlayerTeleport(EntityTeleportEvent $event) : void{
        if(!($player = $event->getEntity()) instanceof Player){
            return;
        }
        self::changeWeatherForPlayer($player, $event->getTo()->getWorld());
    }

    public function onWorldInit(WorldInitEvent $event) : void{
        $world = $event->getWorld();
        self::changeWeather($world, self::CLEAR, 18000);
    }

    /**
     * @throws Exception
     */
    public function getWorldSetting(string $worldName, string $setting): bool{
        $defaultSettings = $this->config->get("default", null);
        $settings = $this->config->get($worldName, null);

        if($settings !== null){
            if(isset($settings[$setting])){
                return boolval($settings[$setting]);
            }
        }

        if(isset($defaultSettings[$setting])){
            return boolval($defaultSettings[$setting]);
        }else{
            throw new Exception("Unknown setting: $setting");
        }
    }
}