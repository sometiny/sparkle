<?php
namespace Sparkle;


use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\FileEngine;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Jazor\Path;

class View
{
    private static array $view_locations = [];
    private static ?CompilerEngine $engine = null;
    private static ?string $cachePath = null;
    private static ?Factory $factory = null;

    /**
     * @return Factory|null
     */
    public static function getFactory(): ?Factory
    {
        return self::$factory;
    }


    public static function register()
    {
        $resolver = new EngineResolver();
        $resolver->register('file', function () {
            return new FileEngine;
        });
        $resolver->register('php', function () {
            return new PhpEngine();
        });
        $resolver->register('blade', function () {
            return new CompilerEngine(new BladeCompiler(self::$cachePath));
        });

        $finder = new FileViewFinder(self::$view_locations);

        $factory = new Factory($resolver, $finder);

        self::$factory = $factory;
    }
    /**
     * @return array
     */
    public static function getViewLocations(): array
    {
        return self::$view_locations;
    }

    /**
     * @param string $path
     */
    public static function addViewLocation(string $path): void
    {
        if (!is_dir($path)) return;
        self::$view_locations[] = $path;
    }

    /**
     * @return string|null
     */
    public static function getCachePath(): ?string
    {
        return self::$cachePath;
    }

    /**
     * @param string|null $cachePath
     * @throws \Exception
     */
    public static function setCachePath(?string $cachePath): void
    {
        if(!is_dir($cachePath)) mkdir($cachePath, 0777, true);
        self::$cachePath = $cachePath;
    }

    /**
     * @param string $view
     * @param array|null $data
     * @return string
     * @throws \Throwable
     */
    public static function get(string $view, ?array $data)
    {
        return self::getViewInstance($view, $data)->render();
    }

    /**
     * @param string $view
     * @param array|null $data
     * @return \Illuminate\View\View
     * @throws \Throwable
     */
    public static function getViewInstance(string $view, ?array $data)
    {
        return self::$factory->make($view, $data);
    }
}
