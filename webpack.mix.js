let mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for your application, as well as bundling up your JS files.
 |
 */
const sourcePath = 'web/craftidresources/src';
const distPath = 'web/craftidresources/dist';

mix.js(sourcePath + '/js/main.js', distPath + '/js/')
    .sass(sourcePath + '/sass/main.scss', distPath + '/css/')
    .options({
        processCssUrls: false
    })
    .copy(sourcePath + '/images', distPath + '/images/')
    .sourceMaps();