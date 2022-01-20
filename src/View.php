<?php
namespace Sparkle;


use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Jazor\Path;

class View
{
    private static array $view_locations = [];
    private static ?CompilerEngine $engine = null;
    private static ?string $cachePath = null;


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
        if (!is_dir($cachePath)) throw new \Exception('cachePath not exists');
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
        if (!self::$engine) {
            self::$engine = new CompilerEngine(new BladeCompiler(self::$cachePath));
        }

        $viewName = str_replace('.', DIRECTORY_SEPARATOR, $view) . '.blade.php';
        $path = null;

        foreach (self::$view_locations as $dir) {
            $path = Path::combine($dir, $viewName);
            if (is_file($path)) {
                return self::$engine->get($path, $data);
            }
        }
        throw new \Exception('view not exists: ' . $view);
    }
}
