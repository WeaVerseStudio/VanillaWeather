<?php
declare(strict_types=1);

namespace PrograMistV1\Weather\events;

use pocketmine\event\CancellableTrait;
use pocketmine\event\world\WorldEvent;

/**
 * Called when the weather changes.
 */
class WeatherChangeEvent extends WorldEvent{
    use CancellableTrait;
}