<?php
declare(strict_types=1);

namespace PrograMistV1\Weather;

use Exception;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\SnowLayer;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\SubChunk;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use PrograMistV1\Weather\events\SnowLayerCreateEvent;

class WeatherTask extends Task{

    private const MAX_SNOW_LAYERS = 2;
    private const LIGHTNING_DAMAGE = 5;
    private const LIGHTNING_CHANCE = 100000;
    private const LIGHTNING_DAMAGE_RADIUS = 2;
    private const SNOW_CHANCE = 100;
    private const SNOW_TEMPERATURE_THRESHOLD = 0.15;
    private const SNOW_PLACEMENT_MAX_LIGHT_LEVEL = 9;

    private const MIN_RAIN_TIME = 6000;
    private const MAX_RAIN_TIME = 18000;

    private readonly Weather $weather;

    /**
     * [folderName => [SETTING_KEY => value, ...]]
     * @var array<string, array<string, mixed>>
     */
    private array $worldSettings = [];

    public function __construct(private readonly WorldManager $worldManager){
        $this->weather = Weather::getInstance();
    }

    /**
     * @return array<string, mixed>
     */
    private function getWorldSettings(string $folderName) : array{
        if(isset($this->worldSettings[$folderName])){
            return $this->worldSettings[$folderName];
        }

        $this->worldSettings[$folderName] = [
            Weather::CHANGE_WEATHER => $this->weather->getWorldSetting($folderName, Weather::CHANGE_WEATHER),
            Weather::CREATE_LIGHTNING => $this->weather->getWorldSetting($folderName, Weather::CREATE_LIGHTNING),
            Weather::CREATE_SNOW_LAYERS => $this->weather->getWorldSetting($folderName, Weather::CREATE_SNOW_LAYERS),
            Weather::DAMAGE_FROM_LIGHTNING => $this->weather->getWorldSetting($folderName, Weather::DAMAGE_FROM_LIGHTNING),
            Weather::LIGHTNING_FIRE => $this->weather->getWorldSetting($folderName, Weather::LIGHTNING_FIRE),
        ];

        return $this->worldSettings[$folderName];
    }

    /**
     * @throws Exception
     */
    public function onRun() : void{
        foreach($this->worldManager->getWorlds() as $world){
            $this->processWeather($world);
        }
    }

    /**
     * @throws Exception
     */
    private function processWeather(World $world) : void{
        $worldData = $world->getProvider()->getWorldData();
        $rainTime = $worldData->getRainTime();

        $folderName = $world->getFolderName();
        $settings = $this->getWorldSettings($folderName);

        if($rainTime === 0 && ($settings[Weather::CHANGE_WEATHER] ?? false)){
            if($worldData->getRainLevel() !== 0.0){
                Weather::changeWeather($world, Weather::CLEAR, rand(self::MIN_RAIN_TIME, self::MAX_RAIN_TIME));
            }else{
                $weather = [Weather::RAIN, Weather::THUNDER];
                Weather::changeWeather($world, $weather[array_rand($weather)], rand(self::MIN_RAIN_TIME, self::MAX_RAIN_TIME));
            }
        }

        $this->handleWeatherEvents($world, $worldData);

        if($rainTime > 0){
            $worldData->setRainTime(max($rainTime - $this->getHandler()->getPeriod(), 0));
        }
    }

    /**
     * @throws Exception
     */
    private function handleWeatherEvents(World $world, WorldData $worldData) : void{
        $folderName = $world->getFolderName();
        $settings = $this->getWorldSettings($folderName);

        $createLightning = $settings[Weather::CREATE_LIGHTNING] ?? false;
        $createSnow = $settings[Weather::CREATE_SNOW_LAYERS] ?? false;
        $damageFromLightning = $settings[Weather::DAMAGE_FROM_LIGHTNING] ?? false;
        $lightningFire = $settings[Weather::LIGHTNING_FIRE] ?? false;
        $localMask = (1 << SubChunk::COORD_BIT_SIZE) - 1;

        foreach($world->getLoadedChunks() as $hash => $chunk){
            World::getXZ($hash, $chunkX, $chunkZ);

            // local coordinates within the chunk
            $localX = rand(0, $localMask);
            $localZ = rand(0, $localMask);

            // world coordinates
            $blockX = ($chunkX << SubChunk::COORD_BIT_SIZE) + $localX;
            $blockZ = ($chunkZ << SubChunk::COORD_BIT_SIZE) + $localZ;

            $blockY = $chunk->getHighestBlockAt($localX, $localZ);

            /**
             * Snow and lightning cannot spawn in air block
             */
            if($blockY === null){
                continue;
            }

            $temperature = BiomeRegistry::getInstance()->getBiome($chunk->getBiomeId($localX, $blockY, $localZ))->getTemperature();
            $rainLevel = $worldData->getRainLevel();

            if($createLightning && $temperature > self::SNOW_TEMPERATURE_THRESHOLD && $rainLevel === 1.0 && rand(1, self::LIGHTNING_CHANCE) === 1){
                $this->handleThunder($world, $blockX, $blockY, $blockZ, $damageFromLightning, $lightningFire);
            }

            if($createSnow && $temperature <= self::SNOW_TEMPERATURE_THRESHOLD && $rainLevel > 0 && rand(1, self::SNOW_CHANCE) === 1){
                $this->handleSnow($world, $blockX, $blockY, $blockZ);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function handleThunder(World $world, int $x, int $y, int $z, bool $damageFromLightning, bool $lightningFire) : void{
        $chunkX = (int) floor($x / (1 << SubChunk::COORD_BIT_SIZE));
        $chunkZ = (int) floor($z / (1 << SubChunk::COORD_BIT_SIZE));

        $entities = $world->getChunkEntities($chunkX, $chunkZ);

        $lightningPos = new Vector3($x, $y, $z);

        $closest = null;
        $closestDist = INF;
        if($damageFromLightning){
            foreach($entities as $entity){
                if($entity instanceof Player){
                    $entityPos = $entity->getPosition();
                    $dist = $lightningPos->distance($entityPos);
                    if($dist <= self::LIGHTNING_DAMAGE_RADIUS){
                        $damage = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, self::LIGHTNING_DAMAGE);
                        $entity->attack($damage);
                    }

                    if($dist < $closestDist){
                        $closestDist = $dist;
                        $closest = $entityPos;
                    }
                }
            }
        }

        if($closest !== null){
            $lightningPos = $closest;
        }

        Weather::generateThunderBolt($world, $lightningPos->x, $lightningPos->y, $lightningPos->z, $lightningFire);
    }

    private function handleSnow(World $world, int $x, int $y, int $z) : void{
        $block = $world->getBlockAt($x, $y, $z);
        if($block->getTypeId() === BlockTypeIds::SNOW_LAYER){
            /** @var SnowLayer $block */
            $layers = $block->getLayers();
            if($layers >= self::MAX_SNOW_LAYERS){
                return;
            }

            $ev = new SnowLayerCreateEvent($world, $x, $y, $z);
            $ev->call();
            if(!$ev->isCancelled()){
                $layers++;
                $block->setLayers($layers);
                $world->setBlockAt($x, $y, $z, $block);
            }

        }elseif($block->isFullCube() && $block->getLightLevel() < self::SNOW_PLACEMENT_MAX_LIGHT_LEVEL){
            $ev = new SnowLayerCreateEvent($world, $x, $y, $z);
            $ev->call();
            if(!$ev->isCancelled()){
                $world->setBlockAt($x, $y + 1, $z, VanillaBlocks::SNOW_LAYER());
            }
        }
    }
}