<?php
namespace TrackPet\utils;

class Sanitizer
{
    private string $string;

    private const CONTROL_CHARS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';

    private const INVISIBLE_CHARS = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u';

    private array $stripTags = [
        '@<script[^>]*?>.*?</script>@si',
        '@<style[^>]*?>.*?</style>@siU',
        '@<[\/\!]*?[^<>]*?>@si',
        '@<![\s\S]*?--[ \t\n\r]*>@',
    ];

    private bool $stripHtml;
    private bool $normalizeWhitespace;

    public function __construct(
        string $string,
        bool $stripHtml = true,
        bool $normalizeWhitespace = true,
    ) {
        $this->string              = $string;
        $this->stripHtml           = $stripHtml;
        $this->normalizeWhitespace = $normalizeWhitespace;
    }

    public function cleanString(): string
    {
        $this->normalizeEncoding();
        $this->removeControlChars();
        $this->removeInvisibleChars();

        if ($this->stripHtml) {
            $this->tagClean();
        }

        if ($this->normalizeWhitespace) {
            $this->whitespaceClean();
        }

        $this->trim();

        return $this->string;
    }

    /** Ensure valid UTF-8; drop or substitute malformed bytes. */
    private function normalizeEncoding(): void
    {
        if (mb_check_encoding($this->string, 'UTF-8')) {
            return;
        }
        $this->string = mb_convert_encoding($this->string, 'UTF-8', 'UTF-8');
    }

    private function removeControlChars(): void
    {
        $this->string = preg_replace(self::CONTROL_CHARS, '', $this->string) ?? '';
    }

    private function removeInvisibleChars(): void
    {
        $this->string = preg_replace(self::INVISIBLE_CHARS, '', $this->string) ?? '';
    }

    private function tagClean(): void
    {
        $this->string = preg_replace($this->stripTags, '', $this->string) ?? '';
    }

    private function whitespaceClean(): void
    {
        $this->string = preg_replace('/[\p{Z}\s]+/u', ' ', $this->string) ?? '';
    }

    private function trim(): void
    {
        $this->string = trim($this->string);
    }

}