<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $_SERVER['APP_ENV'] = 'testing';
        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');

        $_SERVER['APP_KEY'] = 'base64:w/M3o1q4LhE2g9R2P8T1v8v0x1z3A5B5C5D5E5F5G5H=';
        $_ENV['APP_KEY'] = 'base64:w/M3o1q4LhE2g9R2P8T1v8v0x1z3A5B5C5D5E5F5G5H=';
        putenv('APP_KEY=base64:w/M3o1q4LhE2g9R2P8T1v8v0x1z3A5B5C5D5E5F5G5H=');

        $_SERVER['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_CONNECTION'] = 'mysql';
        putenv('DB_CONNECTION=mysql');

        $_SERVER['DB_DATABASE'] = 'ifotoset_testing';
        $_ENV['DB_DATABASE'] = 'ifotoset_testing';
        putenv('DB_DATABASE=ifotoset_testing');

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.b2.bucket' => 'ifotoset-test-bucket',
            'filesystems.disks.b2.key'    => 'test-key',
            'filesystems.disks.b2.secret' => 'test-secret',
            'filesystems.disks.b2.region' => 'us-east-005',
            'filesystems.disks.s3.bucket' => 'ifotoset-test-bucket',
            'filesystems.disks.s3.key'    => 'test-key',
            'filesystems.disks.s3.secret' => 'test-secret',
            'filesystems.disks.s3.region' => 'us-east-1',
        ]);
    }
}
