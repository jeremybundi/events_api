<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit95f8c0b903eade3643eac9aa9e97329c
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInit95f8c0b903eade3643eac9aa9e97329c', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInit95f8c0b903eade3643eac9aa9e97329c', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInit95f8c0b903eade3643eac9aa9e97329c::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
