<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit26956d4b4d7fb6298e05acc86ee7f3c8
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'StaticFactory\\' => 14,
            'Singleton\\' => 10,
            'SimpleFactory\\' => 14,
        ),
        'P' => 
        array (
            'Prototype\\' => 10,
            'Pool\\' => 5,
        ),
        'F' => 
        array (
            'FactoryMethod\\' => 14,
        ),
        'B' => 
        array (
            'Builder\\' => 8,
        ),
        'A' => 
        array (
            'AbstractFactory\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'StaticFactory\\' => 
        array (
            0 => __DIR__ . '/../..' . '/09Static Factory',
        ),
        'Singleton\\' => 
        array (
            0 => __DIR__ . '/../..' . '/08Singleton',
        ),
        'SimpleFactory\\' => 
        array (
            0 => __DIR__ . '/../..' . '/07Simple Factory',
        ),
        'Prototype\\' => 
        array (
            0 => __DIR__ . '/../..' . '/06Prototype',
        ),
        'Pool\\' => 
        array (
            0 => __DIR__ . '/../..' . '/05Object Pool',
        ),
        'FactoryMethod\\' => 
        array (
            0 => __DIR__ . '/../..' . '/03FactoryMethod',
        ),
        'Builder\\' => 
        array (
            0 => __DIR__ . '/../..' . '/02Builder',
        ),
        'AbstractFactory\\' => 
        array (
            0 => __DIR__ . '/../..' . '/01AbstractFactory',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit26956d4b4d7fb6298e05acc86ee7f3c8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit26956d4b4d7fb6298e05acc86ee7f3c8::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
