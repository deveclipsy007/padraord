<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class HostingerDeploymentTest extends TestCase
{
    public function test_it_keeps_the_production_public_surface_limited_to_public_assets(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $this->assertTrue(file_exists($projectRoot.'/public/index.php'));
        $this->assertFalse(file_exists($projectRoot.'/public/index.html'));
        $this->assertTrue(file_exists($projectRoot.'/scripts/deploy/build-hostinger-release.sh'));
        $this->assertTrue(file_exists($projectRoot.'/scripts/deploy/deploy-hostinger-sftp.sh'));

        $releaseRoot = $projectRoot.'/dist/hostinger';
        if (file_exists($releaseRoot.'/release.json')) {
            $this->assertFileExists($releaseRoot.'/public_html/index.php');
            $this->assertFileDoesNotExist($releaseRoot.'/public_html/index.html');
            $this->assertFileDoesNotExist($releaseRoot.'/app_core/.env');
            $this->assertDirectoryDoesNotExist($releaseRoot.'/app_core/resources/js');
            $this->assertDirectoryDoesNotExist($releaseRoot.'/app_core/resources/images');
            $this->assertFileExists($releaseRoot.'/app_core/resources/views/app.blade.php');
            $this->assertFileExists($releaseRoot.'/public_html/build/manifest.json');
        }
    }
}
