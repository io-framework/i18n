<?php

namespace Io\I18n\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Io\I18n\Catalog;

final class CatalogTest extends TestCase
{
    public function testLoadSaveRoundTripsSortedWithOrphansLast(): void
    {
        $file = sys_get_temp_dir() . '/io-i18n-' . uniqid() . '/x.json';
        $catalog = new Catalog($file, ['Zebra' => 'z', 'apple' => 'a'], ['Old' => 'o']);
        $catalog->save();

        $this->assertSame("{\n    \"apple\": \"a\",\n    \"Zebra\": \"z\",\n    \"__orphans\": {\n        \"Old\": \"o\"\n    }\n}\n", file_get_contents($file));
        $loaded = Catalog::load($file);
        $this->assertSame(['apple' => 'a', 'Zebra' => 'z'], $loaded->entries);
        $this->assertSame(['Old' => 'o'], $loaded->orphans);
        unlink($file);
        rmdir(dirname($file));
    }

    public function testAMissingFileIsAnEmptyCatalogButInvalidJsonIsAnError(): void
    {
        $this->assertSame([], Catalog::load('/nope/none.json')->entries);

        $file = tempnam(sys_get_temp_dir(), 'i18n');
        file_put_contents($file, '{not json');
        $this->expectException(RuntimeException::class);
        try {
            Catalog::load($file);
        } finally {
            unlink($file);
        }
    }
}
