<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        // Determine the driver URL based on environment
        // For Docker: use selenium:4444
        // For local: use localhost:9515
        $driverUrl = $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL');

        if (!$driverUrl) {
            // Auto-detect based on environment
            $driverUrl = env('APP_ENV') === 'testing' && php_sapi_name() === 'cli'
                ? 'http://selenium:4444'  // Assume Docker if testing environment
                : 'http://localhost:9515'; // Local development
        }

        return RemoteWebDriver::create(
            $driverUrl,
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
