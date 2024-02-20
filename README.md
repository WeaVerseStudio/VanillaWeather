# VanillaWeather by WeaVerseStudio
### Description:
> A plugin that adds weather mechanics like in vanilla.
> 
> <img src="https://github.com/WeaVerseStudio/VanillaWeather/blob/master/resources/image0.png?raw=true" alt="image not found">
> <img src="https://github.com/WeaVerseStudio/VanillaWeather/blob/master/resources/image1.png?raw=true" alt="image not found">
> <img src="https://github.com/WeaVerseStudio/VanillaWeather/blob/master/resources/image2.png?raw=true" alt="image not found">

### Authors:
> PrograMistV1 - made major contributions to the writing of the plugin.

### Commands:
> `/weather <clear|rain|thunder> [duration: int]` - Players who have permission can change the current weather if they wish, by entering one of the following commands: /weather rain [*duration*] or /weather thunder [*duration*], and /weather clear [*duration*] to clear the inclement weather. The **time** parameter is the duration of the weather in seconds.
If you want to prevent weather changes in the world, then set the time value to 0 or less.
> 
> Permission: `vanillaweather.weather.command`

### For developers:
> Track events using these events:
> `PrograMistV1\Weather\events\SnowLayerCreateEvent`
> `PrograMistV1\Weather\events\ThunderBoltSpawnEvent`
> `PrograMistV1\Weather\events\WeatherChangeEvent`
> 
> You can change the weather using:
> `PrograMistV1\Weather\Weather::changeWeather(World $world, int $weather, int $time = 6000) : void;`
> 
### Config
> You can customize the weather behavior for a specific world. To do this, edit config.yml in plugin_data. The default values for all worlds are set to `default`.
> 
> Filling example:
> ```yaml
> ---
> default:
>   weatherChange: true
>   createLightning: true
>   lightningFire: false
>   damageFromLightning: false
>   createSnowLayers: true
> 
> firstWorld:
>   weatherChange: false
> 
> secondWorld:
>   createLightning: false
>   createSnowLayers: false
> ...
> ```