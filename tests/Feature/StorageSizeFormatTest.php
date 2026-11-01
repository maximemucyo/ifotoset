<?php

namespace Tests\Feature;

use App\Support\Bytes;
use Tests\TestCase;

class StorageSizeFormatTest extends TestCase
{
    public function test_plan_sizes_read_the_same_as_they_are_sold(): void
    {
        $this->assertSame('1.5 TB', Bytes::format(1500 * 1024 ** 3));
        $this->assertSame('1 TB', Bytes::format(1000 * 1024 ** 3));
        $this->assertSame('3 TB', Bytes::format(3000 * 1024 ** 3));
        $this->assertSame('50 GB', Bytes::format(50 * 1024 ** 3));
        $this->assertSame('5 GB', Bytes::format(5 * 1024 ** 3));
    }

    public function test_usage_keeps_useful_precision(): void
    {
        $this->assertSame('2.08 GB', Bytes::format(2.08 * 1024 ** 3));
        $this->assertSame('72.8 MB', Bytes::format(72.8 * 1024 ** 2));
        $this->assertSame('0 B', Bytes::format(0));
    }
}
