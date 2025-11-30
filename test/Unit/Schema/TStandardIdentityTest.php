<?php

declare(strict_types=1);

namespace Modular\Persistence\Test\Unit\Schema;

use Modular\Persistence\Schema\TStandardIdentity;
use PHPUnit\Framework\TestCase;

class TStandardIdentityTest extends TestCase
{
    public function testGetId(): void
    {
        $hydrator = new class () {
            use TStandardIdentity;
        };

        $entity = new class () {
            public int $id = 123;
        };

        self::assertSame(123, $hydrator->getId($entity));
    }

    public function testGetIdReturnsNullIfPropertyMissing(): void
    {
        $hydrator = new class () {
            use TStandardIdentity;
        };

        $entity = new class () {
        };

        self::assertNull($hydrator->getId($entity));
    }

    public function testGetIdFieldName(): void
    {
        $hydrator = new class () {
            use TStandardIdentity;
        };

        self::assertSame('id', $hydrator->getIdFieldName());
    }
}
