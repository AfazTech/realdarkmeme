<?php
namespace Tests\Unit;
use App\Services\VideoPipeline;
use PHPUnit\Framework\TestCase;

class PersianOcrTest extends TestCase
{
    public function test_it_removes_latin_ocr_noise(): void
    {
        $text=VideoPipeline::onlyPersian("This is a test\nسلام دنیا 123 hello\nمتن فارسی دیگر\nEnglish!");
        $this->assertStringContainsString('سلام دنیا', $text);
        $this->assertStringContainsString('متن فارسی دیگر', $text);
        $this->assertStringNotContainsString('hello', $text);
        $this->assertStringNotContainsString('English', $text);
    }
}
