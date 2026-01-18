# VanillaWeather

[![](https://poggit.pmmp.io/shield.api/VanillaWeather)](https://poggit.pmmp.io/p/VanillaWeather) [![](https://poggit.pmmp.io/shield.dl.total/VanillaWeather)](https://poggit.pmmp.io/p/VanillaWeather) [![](https://poggit.pmmp.io/shield.state/VanillaWeather)](https://poggit.pmmp.io/p/VanillaWeather)

A lightweight plugin that brings vanilla-like weather mechanics (clear, rain, thunder) with configurable behavior per
world, lightning control and snow layer creation.

<div style="text-align:center">
  <img src="https://github.com/WeaVerseStudio/VanillaWeather/blob/master/resources/image0.png?raw=true" alt="screenshot0" width="320" style="margin-right:8px;"/>
  <img src="https://github.com/WeaVerseStudio/VanillaWeather/blob/master/resources/image1.png?raw=true" alt="screenshot1" width="320" style="margin-right:8px;"/>
  <img src="https://github.com/WeaVerseStudio/VanillaWeather/blob/master/resources/image2.png?raw=true" alt="screenshot2" width="320"/>
</div>

---

## Features

- Change weather to clear, rain or thunder via command or API.
- Control lightning spawning and its side-effects (fire, damage).
- Create snow layers when snow falls.
- Per-world configuration.
- Events for plugin integration and extension.

## Commands

- `/weather <clear|rain|thunder> [duration]`
  - Change the current world's weather. `duration` is in seconds.
  - Use `0` or a negative number to prevent automatic weather changes in that world.
  - Examples:
    - `/weather rain 120` — rain for 2 minutes
    - `/weather clear` — make the weather clear

## Permissions

- `vanillaweather.weather.command` — Allows use of the `/weather` command.

## Configuration

Settings are stored per world in `plugin_data/VanillaWeather/config.yml`. The `default` section provides fallback
values.

Example `config.yml`:

```yaml
---
default:
  weatherChange: true        # Allow automatic weather changes
  createLightning: true      # Allow lightning to spawn
  lightningFire: false       # Lightning can set blocks on fire
  damageFromLightning: false # Lightning deals damage to players
  createSnowLayers: true     # Create snow layers during snow

world_nether:
  weatherChange: false
```

---

## Contributing

Contributions are welcome — fork the repo, make your changes and open a pull request. For bug reports and feature
requests please use the [repository Issues](https://github.com/WeaVerseStudio/VanillaWeather/issues)

## Contact

If you need help or want to report a bug, open an Issue on the repository or join
our [Discord](https://discord.gg/BBtZ4nh8Rc)
