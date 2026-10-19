<?php

namespace Tests\Feature;

use App\Support\SafeReturnUrl;
use Tests\TestCase;

class SafeReturnUrlTest extends TestCase
{
    public function test_safe_return_url_accepts_valid_studio_paths(): void
    {
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('/studio/galleries'));
        $this->assertEquals('/studio/galleries/01a0c4eb-2170-7273-b1f3-dbfdd3a78254', SafeReturnUrl::sanitize('/studio/galleries/01a0c4eb-2170-7273-b1f3-dbfdd3a78254'));
        $this->assertEquals('/studio/galleries/123?resume=1&step=upload', SafeReturnUrl::sanitize('/studio/galleries/123?resume=1&step=upload'));
    }

    public function test_safe_return_url_rejects_external_scheme(): void
    {
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('https://evil.com/studio/galleries'));
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('http://attacker.com/studio/galleries'));
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('javascript:alert(1)'));
    }

    public function test_safe_return_url_rejects_protocol_relative_url(): void
    {
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('//evil.com/studio/galleries'));
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('///evil.com/studio'));
    }

    public function test_safe_return_url_rejects_non_studio_paths(): void
    {
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('/admin/dashboard'));
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('/login'));
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize('/api/v1/users'));
    }

    public function test_safe_return_url_rejects_backslashes_and_control_chars(): void
    {
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize("/studio/galleries\\evil"));
        $this->assertEquals('/studio/galleries', SafeReturnUrl::sanitize("/studio/galleries\n"));
    }
}
