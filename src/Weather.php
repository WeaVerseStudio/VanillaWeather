<?php

declare(strict_types=1);

namespace PrograMistV1\Weather;

use InvalidArgumentException;
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
    public const MAX_RAIN_INTENSITY = 65535;
    public const COMMAND_WEATHER = "vanillaweather.weather.command";

    public const CHANGE_WEATHER = "weatherChange";
    public const CREATE_LIGHTNING = "createLightning";
    public const LIGHTNING_FIRE = "lightningFire";
    public const DAMAGE_FROM_LIGHTNING = "damageFromLightning";
    public const CREATE_SNOW_LAYERS = "createSnowLayers";

    private Config $config;

    private static array $packets = [];

    protected function onEnable() : void{
        self::setInstance($this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new WeatherTask(
            $this->getServer()->getWorldManager()
        ), 20);
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

        self::$packets[self::RAIN] = [LevelEventPacket::create(LevelEvent::START_RAIN, self::MAX_RAIN_INTENSITY, null)];
        self::$packets[self::THUNDER] = [LevelEventPacket::create(LevelEvent::START_THUNDER, self::MAX_RAIN_INTENSITY, null)];
        self::$packets[self::CLEAR] = [
            LevelEventPacket::create(LevelEvent::STOP_RAIN, 0, null),
            LevelEventPacket::create(LevelEvent::STOP_THUNDER, 0, null)
        ];
    }

    /** @noinspection PhpUnused */
    public function onPlayerJoin(PlayerJoinEvent $event) : void{
        self::changeWeatherForPlayer($event->getPlayer());
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function changeWeather(World $world, int $weather, int $time = 6000) : void{
        if(!array_key_exists($weather, self::$packets)){
            throw new InvalidArgumentException("Invalid weather type: $weather");
        }

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
        NetworkBroadcastUtils::broadcastPackets($world->getPlayers(), self::$packets[$weather]);
    }

    public static function changeWeatherForPlayer(Player $player, ?World $world = null) : void{
        $world ??= $player->getWorld();
        $level = $world->getProvider()->getWorldData()->getRainLevel();
        $weather = match ($level) {
            0.5 => self::RAIN,
            1.0 => self::THUNDER,
            default => self::CLEAR,
        };
        foreach(self::$packets[$weather] as $packet){
            $player->getNetworkSession()->sendDataPacket($packet);
        }
    }

    public static function generateThunderBolt(World $world, int $x, int $y, int $z, bool $doFire = false) : void{
        $event = new ThunderBoltSpawnEvent($world, $x, $y, $z, $doFire);
        $event->call();

        if($event->isCancelled()){
            return;
        }

        $entityId = Entity::nextRuntimeId();
        $position = new Vector3($x, $y, $z);

        $packets = [
            AddActorPacket::create(
                $entityId,
                $entityId,
                'minecraft:lightning_bolt',
                $position,
                null,
                0, 0, 0,
                0,
                [],
                [],
                new PropertySyncData([], []),
                []
            ),
            PlaySoundPacket::create(
                'ambient.weather.thunder',
                $x,
                $y,
                $z,
                10,
                1
            )
        ];

        NetworkBroadcastUtils::broadcastPackets($world->getPlayers(), $packets);

        if($event->isDoFire()){
            self::tryIgniteBlock($world, $x, $y + 1, $z);
        }
    }

    private static function tryIgniteBlock(World $world, int $x, int $y, int $z) : void{
        if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR){
            $world->setBlockAt($x, $y, $z, VanillaBlocks::FIRE());
        }
    }

    /** @noinspection PhpUnused */
    public function onPlayerTeleport(EntityTeleportEvent $event) : void{
        $player = $event->getEntity();
        if(!($player instanceof Player)){
            return;
        }

        self::changeWeatherForPlayer($player, $event->getTo()->getWorld());
    }

    /** @noinspection PhpUnused */
    public function onWorldInit(WorldInitEvent $event) : void{
        $world = $event->getWorld();
        self::changeWeather($world, self::CLEAR, 18000);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getWorldSetting(string $worldName, string $setting) : bool{
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
            throw new InvalidArgumentException("Unknown setting: $setting");
        }
    }
}