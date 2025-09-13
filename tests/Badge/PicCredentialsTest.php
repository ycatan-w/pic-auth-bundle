<?php

declare(strict_types=1);

namespace Tests\Crayon\PicAuthBundle\Badge;

use Crayon\PicAuthBundle\Badge\PicCredentials;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class PicCredentialsTest extends TestCase
{
    public function testPicCredentials(): void
    {
        $file = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();
        $credentials = new PicCredentials($file);
        $this->assertSame($file, $credentials->getImage());
        $this->assertFalse($credentials->isResolved());
        $credentials->markResolved();
        $this->assertTrue($credentials->isResolved());
    }
}
