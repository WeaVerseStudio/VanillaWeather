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
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\WorldData;
use pocketmine\world\format\SubChunk;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use PrograMistV1\Weather\events\SnowLayerCreateEvent;

class WeatherTask extends Task{

    private const MAX_SNOW_LAYERS = 2;
    private const LIGHTNING_DAMAGE = 5;
    private const LIGHTNING_CHANCE = 100000;
    private const SNOW_CHANCE = 100;
    private const SNOW_TEMPERATURE_THRESHOLD = 0.15;

    private const MIN_RAIN_TIME = 6000;
    private const MAX_RAIN_TIME = 18000;


    public function __construct(private readonly WorldManager $worldManager){
    }

    /**
     * @throws Exception
     */
    public function onRun(): void {
        foreach ($this->worldManager->getWorlds() as $world) {
            $this->processWeather($world);
        }
    }

    /**
     * @throws Exception
     */
    private function processWeather(World $world): void {
        $worldData = $world->getProvider()->getWorldData();
        $rainTime = $worldData->getRainTime();

        if($rainTime === 0 && Weather::getInstance()->getWorldSetting($world->getFolderName(), Weather::CHANGE_WEATHER)){
            if($worldData->getRainLevel() != 0){
                Weather::changeWeather($world, Weather::CLEAR, rand(self::MIN_RAIN_TIME, self::MAX_RAIN_TIME));
            }else{
                $weather = array_rand([Weather::RAIN, Weather::THUNDER]);
                Weather::changeWeather($world, $weather, rand(self::MIN_RAIN_TIME, self::MAX_RAIN_TIME));
            }
        }

        $this->handleWeatherEvents($world, $worldData);

        if($rainTime > 0){
            $worldData->setRainTime($rainTime - 1);
        }
    }

    /**
     * @throws Exception
     */
    private function handleWeatherEvents(World $world, WorldData $worldData): void{
        foreach($world->getLoadedChunks() as $hash => $chunk){
            World::getXZ($hash, $x, $z);
            $x = ($x << SubChunk::COORD_BIT_SIZE) + rand(0, 15);
            $z = ($z << SubChunk::COORD_BIT_SIZE) + rand(0, 15);
            $y = $chunk->getHighestBlockAt($x, $z);


            /**
             * Snow and lightning cannot spawn in air block
             */
            if($y === null){
                continue;
            }

            $temperature = BiomeRegistry::getInstance()->getBiome($chunk->getBiomeId($x, $y, $z))->getTemperature();
            $rainLevel = $worldData->getRainLevel();

            $createLightning = Weather::getInstance()->getWorldSetting($world->getFolderName(), Weather::CREATE_LIGHTNING);
            if($createLightning && $temperature > self::SNOW_TEMPERATURE_THRESHOLD && $rainLevel === 1.0 && rand(1, self::LIGHTNING_CHANCE) === 1){
                $this->handleThunder($world, $chunk, $x, $y, $z);
            }

            $createSnow = Weather::getInstance()->getWorldSetting($world->getFolderName(), Weather::CREATE_SNOW_LAYERS);
            if($createSnow && $temperature <= self::SNOW_TEMPERATURE_THRESHOLD && $rainLevel > 0 && rand(1, self::SNOW_CHANCE) === 1){
                $this->handleSnow($world, $x, $y, $z);
            }
        }
    }

    /**
     * @throws Exception
     */
    private function handleThunder(World $world, Chunk $chunk, int $x, int $y, int $z): void {
        $entities = $world->getChunkEntities($x, $z);
        if(BiomeRegistry::getInstance()->getBiome($chunk->getBiomeId($x, $y, $z))->getTemperature() > 0.15){
            $firstPos = null;
            if(Weather::getInstance()->getWorldSetting($world->getFolderName(), Weather::DAMAGE_FROM_LIGHTNING)){
                foreach($entities as $entity){
                    if($entity instanceof Player){
                        $entityPos = $entity->getPosition();
                        if(($firstPos ??= new Vector3($x, $y, $z))->distance($entityPos) < 2){
                            $x = $entityPos->getX();
                            $y = $entityPos->getY();
                            $z = $entityPos->getZ();
                            $damage = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, self::LIGHTNING_DAMAGE);
                            $entity->attack($damage);
                        }
                    }

                }
            }
            Weather::generateThunderBolt($world, $firstPos->x ?? $x, $firstPos->y ?? $y, $firstPos->z ?? $z, Weather::getInstance()->getWorldSetting($world->getFolderName(), Weather::LIGHTNING_FIRE));
        }
    }

    private function handleSnow(World $world, int $x, int $y, int $z): void {
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
                $block->setLayers(++$layers);
                $world->setBlockAt($x, $y, $z, $block);
            }

        }elseif($block->isFullCube() && $block->getLightLevel() < 9){
            $ev = new SnowLayerCreateEvent($world, $x, $y, $z);
            $ev->call();
            if(!$ev->isCancelled()){
                $world->setBlockAt($x, $y + 1, $z, VanillaBlocks::SNOW_LAYER());
            }
        }
    }
}