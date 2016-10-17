<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2db55f74f6c3aa0fb32fc495a9a9c679
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'League\\HTMLToMarkdown\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'League\\HTMLToMarkdown\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/html-to-markdown/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit2db55f74f6c3aa0fb32fc495a9a9c679::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit2db55f74f6c3aa0fb32fc495a9a9c679::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
