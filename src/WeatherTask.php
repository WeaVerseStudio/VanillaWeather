<?php
declare(strict_types=1);

namespace PrograMistV1\Weather;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\SnowLayer;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\format\SubChunk;
use pocketmine\world\World;
use pocketmine\world\WorldManager;
use PrograMistV1\Weather\events\SnowLayerCreateEvent;

class WeatherTask extends Task{

    public function __construct(private readonly WorldManager $worldManager){
    }

    public function onRun() : void{
        foreach($this->worldManager->getWorlds() as $world){
            $worldData = $world->getProvider()->getWorldData();
            if($worldData->getRainTime() === 0){
                if($worldData->getRainLevel() != 0){
                    Weather::changeWeather($world, Weather::CLEAR, rand(6000, 18000));
                }else{
                    $weather = array_rand([Weather::RAIN, Weather::THUNDER]);
                    Weather::changeWeather($world, $weather, rand(6000, 18000));
                }
            }else{
                if($worldData->getRainLevel() === 1.0){
                    foreach($world->getLoadedChunks() as $hash => $chunk){
                        if(rand(1, 100000) === 1){
                            World::getXZ($hash, $x, $z);
                            $entities = $world->getChunkEntities($x, $z);
                            $x = ($x << SubChunk::COORD_BIT_SIZE) + rand(0, 15);
                            $z = ($z << SubChunk::COORD_BIT_SIZE) + rand(0, 15);
                            $y = $chunk->getHighestBlockAt($x, $z) ?? 64;
                            if(BiomeRegistry::getInstance()->getBiome($chunk->getBiomeId($x, $y, $z))->getTemperature() > 0.15){
                                foreach($entities as $entity){
                                    if($entity instanceof Player){
                                        $entityPos = $entity->getPosition();
                                        if((new Vector3($x, $y, $z))->distance($entityPos->add(-3, 0, -3)) < 5){
                                            $x = $entityPos->getX();
                                            $y = $entityPos->getY();
                                            $z = $entityPos->getZ();
                                            $damage = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 5);
                                            $entity->attack($damage);
                                            break;
                                        }
                                    }
                                }
                                Weather::generateThunderBolt($world, $x, $y, $z, true);
                            }
                        }
                    }
                }
                if($worldData->getRainLevel() > 0){
                    foreach($world->getLoadedChunks() as $hash => $chunk){
                        if(rand(1, 100) === 1){
                            World::getXZ($hash, $x, $z);
                            $x = ($x << SubChunk::COORD_BIT_SIZE) + rand(0, 15);
                            $z = ($z << SubChunk::COORD_BIT_SIZE) + rand(0, 15);
                            $y = $chunk->getHighestBlockAt($x, $z);
                            if($y !== null && BiomeRegistry::getInstance()->getBiome($chunk->getBiomeId($x, $y, $z))->getTemperature() <= 0.15){
                                $block = $world->getBlockAt($x, $y, $z);
                                if($block->getTypeId() === BlockTypeIds::SNOW_LAYER){
                                    /** @var SnowLayer $block */
                                    $layers = $block->getLayers();
                                    if($layers >= 2){
                                        continue;
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
                    }
                }
            }
            $worldData->setRainTime($worldData->getRainTime() - 1);
        }
    }
}