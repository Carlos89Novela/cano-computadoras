<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    public static function prepare(): void
    {
        // Se omite startChromeDriver porque ya se ejecuta de forma externa y estable en el puerto 9515.
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--window-size=1920,1080',
            '--disable-gpu',
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
        ]);

        // Si Chrome está instalado en la ruta por defecto de Windows
        $chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
        if (! file_exists($chromePath)) {
            $chromePath = 'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe';
        }

        if (file_exists($chromePath)) {
            $options->setBinary($chromePath);
        }

        return RemoteWebDriver::create(
            'http://127.0.0.1:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            ),
            5000,
            30000
        );
    }
}