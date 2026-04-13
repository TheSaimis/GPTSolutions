<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\Metadata\CustomVariableScanner;
use PHPUnit\Framework\TestCase;

final class CustomVariableScannerTest extends TestCase
{
    public function testExtractUnknownVariableNamesSkipsCompanyAndExtraIgnores(): void
    {
        $scanner = new CustomVariableScanner();
        $text    = 'X ${kompanija} Y ${pareigybe} Z ${manoLaukas} ${manoLaukas}';

        $names = $scanner->extractUnknownVariableNames($text, ['pareigybe']);

        self::assertSame(['manoLaukas'], $names);
    }

    public function testParseIgnoreListFromRequest(): void
    {
        self::assertSame([], CustomVariableScanner::parseIgnoreListFromRequest(null));
        self::assertSame(['a', 'b'], CustomVariableScanner::parseIgnoreListFromRequest(['a', 'b']));
        self::assertSame(['x'], CustomVariableScanner::parseIgnoreListFromRequest('["x"]'));
    }
}
