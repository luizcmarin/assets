<?php

declare(strict_types=1);

namespace Yiisoft\Assets\Tests;

use RuntimeException;
use Yiisoft\Assets\AssetBundle;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Assets\Exception\InvalidConfigException;
use Yiisoft\Assets\Tests\stubs\BaseAsset;
use Yiisoft\Assets\Tests\stubs\BaseWrongAsset;
use Yiisoft\Assets\Tests\stubs\CircleAsset;
use Yiisoft\Assets\Tests\stubs\CircleDependsAsset;
use Yiisoft\Assets\Tests\stubs\FileOptionsAsset;
use Yiisoft\Assets\Tests\stubs\JqueryAsset;
use Yiisoft\Assets\Tests\stubs\RootAsset;
use Yiisoft\Assets\Tests\stubs\SimpleAsset;

final class AssetBundleTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeAssets('@asset');
    }

    public function testBasePath(): void
    {
        $this->assertEmpty($this->getRegisteredBundles($this->manager));

        $this->manager->register([BaseAsset::class]);

        $this->assertStringContainsString(
            '/baseUrl/css/basePath.css',
            $this->manager->getCssFiles()['/baseUrl/css/basePath.css']['url'],
        );
        $this->assertEquals(
            [
                'integrity' => 'integrity-hash',
                'crossorigin' => 'anonymous',
            ],
            $this->manager->getCssFiles()['/baseUrl/css/basePath.css']['attributes'],
        );

        $this->assertStringContainsString(
            '/baseUrl/js/basePath.js',
            $this->manager->getJsFiles()['/baseUrl/js/basePath.js']['url'],
        );
        $this->assertEquals(
            [
                'integrity' => 'integrity-hash',
                'crossorigin' => 'anonymous',
                'position' => 3,
            ],
            $this->manager->getJsFiles()['/baseUrl/js/basePath.js']['attributes'],
        );
    }

    public function testBasePathEmptyException(): void
    {
        $manager = new AssetManager($this->aliases, $this->loader, [], [
            BaseAsset::class => [
                'basePath' => null,
            ],
        ]);

        $this->assertEmpty($this->getRegisteredBundles($manager));

        $message = 'basePath must be set in AssetLoader->setBasePath($path)'
            . ' or AssetBundle property public ?string $basePath = $path';

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($message);

        $manager->register([BaseAsset::class]);
    }

    public function testBaseUrlEmptyString(): void
    {
        $manager = new AssetManager($this->aliases, $this->loader, [], [
            BaseAsset::class => [
                'baseUrl' => '',
            ],
        ]);

        $this->assertEmpty($this->getRegisteredBundles($manager));

        $manager->register([RootAsset::class]);
    }

    public function testBaseUrlIsNotSetException(): void
    {
        $manager = new AssetManager($this->aliases, $this->loader, [], [
            BaseAsset::class => [
                'basePath' => null,
                'baseUrl' => null,
            ],
        ]);

        $this->manager->getLoader()->setBasePath('@asset');

        $this->assertEmpty($this->getRegisteredBundles($manager));

        $message = 'baseUrl must be set in AssetLoader->setBaseUrl($path)'
            . ' or AssetBundle property public ?string $baseUrl = $path';

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($message);

        $manager->register([BaseAsset::class]);
    }

    public function testBasePathEmptyWithAssetManagerSetBasePath(): void
    {
        $this->manager->getLoader()->setBasePath('@asset');

        $this->assertEmpty($this->getRegisteredBundles($this->manager));
        $this->assertIsObject($this->manager->getBundle(BaseAsset::class));
        $this->assertInstanceOf(BaseAsset::class, $this->manager->getBundle(BaseAsset::class));
    }

    public function testBasePathEmptyBaseUrlEmptyWithAssetManagerSetBasePathSetBaseUrl(): void
    {
        $this->manager->getLoader()->setBasePath('@asset');
        $this->manager->getLoader()->setBaseUrl('@assetUrl');

        $this->assertEmpty($this->getRegisteredBundles($this->manager));
        $this->assertIsObject($this->manager->getBundle(BaseAsset::class));
        $this->assertInstanceOf(BaseAsset::class, $this->manager->getBundle(BaseAsset::class));
    }

    public function testBasePathWrongException(): void
    {
        $bundle = new BaseWrongAsset();

        $this->assertEmpty($this->getRegisteredBundles($this->manager));

        $file = $bundle->js[0];
        $message = "Asset files not found: \"{$bundle->basePath}/{$file}\".";

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($message);

        $this->manager->register([BaseWrongAsset::class]);
    }

    public function testCircularDependency(): void
    {
        $depends = (new CircleDependsAsset())->depends;

        $message = "A circular dependency is detected for bundle \"$depends[0]\".";

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->manager->register([CircleAsset::class]);
    }

    public function testDuplicateAssetFile(): void
    {
        $this->assertEmpty($this->getRegisteredBundles($this->manager));

        $this->manager->register([JqueryAsset::class, SimpleAsset::class]);

        $this->assertCount(3, $this->getRegisteredBundles($this->manager));
        $this->assertArrayHasKey(SimpleAsset::class, $this->getRegisteredBundles($this->manager));
        $this->assertInstanceOf(AssetBundle::class, $this->getRegisteredBundles($this->manager)[SimpleAsset::class]);

        $this->assertStringContainsString(
            '/js/jquery.js',
            $this->manager->getJsFiles()['/js/jquery.js']['url'],
        );
        $this->assertEquals(
            [
                'position' => 3,
            ],
            $this->manager->getJsFiles()['/js/jquery.js']['attributes'],
        );
    }

    public function testJsString(): void
    {
        $this->manager->register([BaseAsset::class]);

        $this->assertEquals(
            'app1.start();',
            $this->manager->getJsStrings()['uniqueName']['string'],
        );
        $this->assertEquals(
            'app2.start();',
            $this->manager->getJsStrings()['app2.start();']['string'],
        );
        $this->assertEquals(
            1,
            $this->manager->getJsStrings()['uniqueName2']['attributes']['position'],
        );
    }

    public function testJsVars(): void
    {
        $this->manager->register([BaseAsset::class]);

        $this->assertEquals(
            [
                'option1' => 'value1',
            ],
            $this->manager->getJsVar()['var1']['variables'],
        );
        $this->assertEquals(
            [
                'option2' => 'value2',
                'option3' => 'value3',
            ],
            $this->manager->getJsVar()['var2']['variables'],
        );
        $this->assertEquals(
            3,
            $this->manager->getJsVar()['var3']['attributes']['position'],
        );
    }

    public function testFileOptionsAsset(): void
    {
        $this->assertEmpty($this->getRegisteredBundles($this->manager));

        $this->manager->register([FileOptionsAsset::class]);

        $this->assertStringContainsString(
            '/baseUrl/css/default_options.css',
            $this->manager->getCssFiles()['/baseUrl/css/default_options.css']['url'],
        );
        $this->assertEquals(
            [
                'media' => 'screen',
                'hreflang' => 'en',
            ],
            $this->manager->getCssFiles()['/baseUrl/css/default_options.css']['attributes'],
        );

        $this->assertStringContainsString(
            '/baseUrl/css/tv.css',
            $this->manager->getCssFiles()['/baseUrl/css/tv.css']['url'],
        );
        $this->assertEquals(
            [
                'media' => 'tv',
                'hreflang' => 'en',
            ],
            $this->manager->getCssFiles()['/baseUrl/css/tv.css']['attributes'],
        );

        $this->assertStringContainsString(
            '/baseUrl/css/screen_and_print.css',
            $this->manager->getCssFiles()['/baseUrl/css/screen_and_print.css']['url'],
        );
        $this->assertEquals(
            [
                'media' => 'screen, print',
                'hreflang' => 'en',
            ],
            $this->manager->getCssFiles()['/baseUrl/css/screen_and_print.css']['attributes'],
        );

        $this->assertStringContainsString(
            '/baseUrl/js/normal.js',
            $this->manager->getJsFiles()['/baseUrl/js/normal.js']['url'],
        );
        $this->assertEquals(
            [
                'charset' => 'utf-8',
                'position' => 3,
            ],
            $this->manager->getJsFiles()['/baseUrl/js/normal.js']['attributes'],
        );

        $this->assertStringContainsString(
            '/baseUrl/js/defered.js',
            $this->manager->getJsFiles()['/baseUrl/js/defered.js']['url'],
        );
        $this->assertEquals(
            [
                'charset' => 'utf-8',
                'defer' => true,
                'position' => 3,
            ],
            $this->manager->getJsFiles()['/baseUrl/js/defered.js']['attributes'],
        );
    }
}
