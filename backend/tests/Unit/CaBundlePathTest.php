<?php

namespace Tests\Unit;

use App\Services\NotificationDispatcher;
use Tests\TestCase;

/** Week 16, defect S2: the gateway CA file may be given relative to backend/ or as an absolute path. */
class CaBundlePathTest extends TestCase
{
    public function test_relative_paths_are_resolved_against_the_backend_folder(): void
    {
        $this->assertSame(base_path('../certs/dev-ca.pem'), NotificationDispatcher::caBundle('../certs/dev-ca.pem'));
    }

    public function test_absolute_paths_are_used_as_given(): void
    {
        foreach (['C:/staging/certs/dev-ca.pem', 'C:\staging\certs\dev-ca.pem', '\\server\share\ca.pem', '/etc/ssl/ca.pem'] as $path) {
            $this->assertSame($path, NotificationDispatcher::caBundle($path), $path);
        }
    }

    public function test_empty_means_the_system_store(): void
    {
        $this->assertTrue(NotificationDispatcher::caBundle(''));
    }
}
