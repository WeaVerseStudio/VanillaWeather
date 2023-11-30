<?php
declare(strict_types=1);

namespace PrograMistV1\Weather\events;

use pocketmine\event\CancellableTrait;
use pocketmine\event\world\WorldEvent;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * Called when a snow layer is created
 */
class SnowLayerCreateEvent extends WorldEvent{
    use CancellableTrait;

    private Vector3 $position;

    public function __construct(
        World $world,
        int $x,
        int $y,
        int $z){
        parent::__construct($world);
        $this->position = new Vector3($x, $y, $z);
    }

    public function getPosition() : Vector3{
        return $this->position;
    }
}