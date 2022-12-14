<?php

namespace Srdante\AntiBotLinks;

use Illuminate\Support\Facades\Cache;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * AntiBotLinks-Remake Library.
 *
 * This Library is a remake of AntiBotLinks from FBU Script created by MakeJar.
 *
 * @version 2.0.0
 *
 * @author Dante <minidantebtc@gmail.com>
 * @author MakeJar
 */
class AntiBotLinks
{
    use Traits\HasOptions;
    use Traits\HasHelpers;

    /**
     * Fonts file list.
     */
    protected array $fonts = [
        'Exo-Regular.otf',
        'GoodDog.otf',
        'Lobster_1.3.otf',
        'Rubik-Medium.ttf',
        'ShadowsIntoLightTwo-Regular.ttf',
        'SlimJim.ttf',
    ];

    /**
     * Identifier hash for caching.
     */
    protected string $identifierHash;

    /**
     * Scan font directory in order to list available fonts.
     */
    public function __construct(protected string $identifier, protected int $expiresIn)
    {
        $this->identifierHash = md5('antibotlinks.' . $this->identifier);
    }

    /**
     * Make new AntiBotLinks instance.
     */
    public static function make(string $identifier, int $expiresIn = 300): self
    {
        return new static($identifier, $expiresIn);
    }

    /**
     * Generate links randomly with images.
     */
    public function generateLinks(int $amount = 4): array
    {
        return Cache::remember($this->identifierHash, $this->expiresIn, function () use ($amount) {
            // Randomly get set of words and randomize it words order
            $words    = collect($this->wordUniverse)->random();
            $universe = collect($this->getRandomShuffled((array) $words, $amount));

            // 50% of chance to flip solution/answers
            if (random_int(0, 1)) {
                $universe = $universe->flip();
            }

            // Generate images without any repeated
            $options = $universe->map(fn ($after) => [
                'id'    => random_int(1, 99999),
                'image' => $this->generateRandomImage($after),
            ]);

            return [
                'links' => [
                    'phrase' => $this->generateRandomImage($universe->keys()->join(', ')),
                    'options' => $options->shuffle()->all(), // IMPORTANT: Shuffle images order only after generate the phrase!
                ],
                'solution' => $options->pluck('id')->join(''),
            ];
        })['links'];
    }

    /**
     * Flush generated links cache.
     */
    public function flushLinks(): bool
    {
        return Cache::forget($this->identifierHash);
    }

    /**
     * Validate given AntiBotLinks answer.
     */
    public function validateAnswer(string $value): bool
    {
        $solution = Cache::get($this->identifierHash)['solution'] ?? null;

        if (! $solution) {
            return false;
        }

        return $solution === trim($value);
    }

    /**
     * Generate random image from a word.
     */
    protected function generateRandomImage(string $word): array
    {
        $fontFile = $this->asset("fonts/{$this->fonts[array_rand($this->fonts)]}");
        $width     = (strlen($word) + 1) * ((strlen($word) >= 10) ? 12 : 14);
        $height    = 40;

        // Random Settings
        $angle       = ($width > 125) ? random_int(-2, 2) : random_int(-7, 7);
        $textColor   = $this->generateRandomColor(isShadowColor: false);
        $shadowColor = $this->generateRandomColor(isShadowColor: true);

        // Create blank canvas
        $image = Image::canvas($width, $height);

        // Add link background
        if ($this->background) {
            $image->fill($this->asset('backgrounds/bg-' . random_int(1, 3) . '-' . (($this->darkTheme) ? 'l' : 'd') . '.png'));
            // ->brightness(($this->darkTheme) ? mt_rand(5, 15) : mt_rand(55, 75));
        }

        // Add link text
        $image->text($word, (int) ($width / 2), (int) ($height / 2) + 1, function ($font) use ($angle, $fontFile, $shadowColor): void {
            $font->angle($angle);
            $font->file($fontFile);
            $font->size(22);
            $font->align('center');
            $font->valign('middle');
            $font->color($shadowColor);
        })->text($word, (int) ($width / 2), (int) ($height / 2), function ($font) use ($angle, $fontFile, $textColor): void {
            $font->angle($angle);
            $font->file($fontFile);
            $font->size(22);
            $font->align('center');
            $font->valign('middle');
            $font->color($textColor);
        });

        // Add link noise
        if ($this->noise) {
            for ($i = 0; $i < round($width / random_int(16, 22) * 10); ++$i) {
                $x = random_int(1, $width  - 3);
                $y = random_int(1, $height - 3);
                $image->line($x, $y, $x + random_int(1, 2), $y + ((random_int(0, 1)) ? -1 : +1), function ($draw) use ($textColor): void {
                    $draw->color($textColor);
                });
            }
        }

        return [
            'width'  => $width,
            'base64' => $image->encode('data-url')->encoded
        ];
    }

    /**
     * Generate random rgba() color array.
     */
    protected function generateRandomColor(bool $isShadowColor = false): array
    {
        if ($this->darkTheme) {
            return ($isShadowColor)
                ? [random_int(1, 80), random_int(1, 80), random_int(1, 80), random_int(45, 100) / 100]
                : [random_int(214, 254), random_int(214, 254), random_int(214, 254), random_int(75, 100) / 100];
        }

        return ($isShadowColor)
            ? [random_int(174, 254), random_int(174, 254), random_int(174, 254), random_int(75, 100) / 100]
            : [random_int(1, 80), random_int(1, 80), random_int(1, 80), random_int(45, 100) / 100];
    }
}
