<?php
declare(strict_types=1);

namespace PrograMistV1\Weather\events;

use pocketmine\event\CancellableTrait;
use pocketmine\event\world\WorldEvent;
use pocketmine\math\Vector3;
use pocketmine\world\World;

/**
 * Called when lightning spawns, it allows you to cancel the creation of fire.
 */
class ThunderBoltSpawnEvent extends WorldEvent{
    use CancellableTrait;

    private Vector3 $position;

    public function __construct(
        World $world,
        int $x,
        int $y,
        int $z,
        private bool $doFire){
        parent::__construct($world);
        $this->position = new Vector3($x, $y, $z);
    }

    public function getPosition() : Vector3{
        return $this->position;
    }

    public function isDoFire() : bool{
        return $this->doFire;
    }

    public function setDoFire(bool $doFire) : void{
        $this->doFire = $doFire;
    }
}