<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lietuviškų vardų ir pavardžių linksniavimas (kilmininkas, naudininkas, galininkas, įnagininkas,
 * vietininkas, šauksmininkas) ir pareigų žodžių (vadovas, vadovė, direktorius, direktorė) formos.
 *
 * Pavyzdžiai:
 *   Tomas → Tomo (gen), Tomui (dat), Tomą (acc), Tomu (ins), Tome (loc), Tomai (voc)
 *   Jonaitis → Jonaičio (gen), Jonaičiui (dat), …
 *   vadovas → vadovo (gen), vadovui (dat), …
 */
final class Namer
{
    public function __construct(
        private readonly ManagerGenderResolver $genderResolver,
    ) {}

    /**
     * Vardo kilmininkas: Tomas → Tomo, Ona → Onos
     */
    public function vardo(string $vardas, string $lytis): string
    {
        return $this->genitive($vardas, $lytis);
    }

    /**
     * Pavardės kilmininkas: Jonaitis → Jonaičio, Jonaitienė → Jonaitienės
     */
    public function pavardes(string $pavarde, string $lytis): string
    {
        return $this->genitive($pavarde, $lytis);
    }

    /**
     * Vardo šauksmininkas: Tomas → Tomai, Ona → Ona
     */
    public function vardoSauksmininkas(string $vardas, string $lytis): string
    {
        return $this->vocative($vardas, $lytis);
    }

    /**
     * Pavardės šauksmininkas: Jonaitis → Jonaiti, Jonaitienė → Jonaitienė
     */
    public function pavardesSauksmininkas(string $pavarde, string $lytis): string
    {
        return $this->vocative($pavarde, $lytis);
    }

    /**
     * Vadovo titulo kilmininkas: vadovas → vadovo, vadovė → vadovės
     */
    public function vadovo(string $managerType): string
    {
        return $this->declineManagerTitle($managerType)['genitive'];
    }

    /**
     * Pareigų žodžio visos formos (vardininkas … šauksmininkas).
     *
     * @return array{nominative: string, genitive: string, dative: string, accusative: string, instrumental: string, locative: string, vocative: string}
     */
    public function declineManagerTitle(string $managerType): array
    {
        $type = mb_strtolower(trim($managerType));
        if ($type === '') {
            return $this->emptyTitleDeclension();
        }

        $known = match ($type) {
            'vadovas' => [
                'nominative' => 'vadovas',
                'genitive' => 'vadovo',
                'dative' => 'vadovui',
                'accusative' => 'vadovą',
                'instrumental' => 'vadovu',
                'locative' => 'vadove',
                'vocative' => 'vadovai',
            ],
            'vadovė' => [
                'nominative' => 'vadovė',
                'genitive' => 'vadovės',
                'dative' => 'vadovei',
                'accusative' => 'vadovę',
                'instrumental' => 'vadove',
                'locative' => 'vadovėje',
                'vocative' => 'vadove',
            ],
            'direktorius' => [
                'nominative' => 'direktorius',
                'genitive' => 'direktoriaus',
                'dative' => 'direktoriui',
                'accusative' => 'direktorių',
                'instrumental' => 'direktoriumi',
                'locative' => 'direktoriuje',
                'vocative' => 'direktoriau',
            ],
            'direktorė' => [
                'nominative' => 'direktorė',
                'genitive' => 'direktorės',
                'dative' => 'direktorei',
                'accusative' => 'direktorę',
                'instrumental' => 'direktore',
                'locative' => 'direktorėje',
                'vocative' => 'direktore',
            ],
            default => null,
        };

        if ($known !== null) {
            return $known;
        }

        if (str_ends_with($type, 'ė')) {
            $stem = mb_substr($type, 0, -1);

            return [
                'nominative' => $type,
                'genitive' => $stem . 'ės',
                'dative' => $stem . 'ei',
                'accusative' => $stem . 'ę',
                'instrumental' => $stem . 'e',
                'locative' => $stem . 'ėje',
                'vocative' => $stem . 'e',
            ];
        }

        if (str_ends_with($type, 'as')) {
            $stem = mb_substr($type, 0, -2);

            return [
                'nominative' => $type,
                'genitive' => $stem . 'o',
                'dative' => $stem . 'ui',
                'accusative' => $stem . 'ą',
                'instrumental' => $stem . 'u',
                'locative' => $stem . 'e',
                'vocative' => $stem . 'ai',
            ];
        }

        if (str_ends_with($type, 'ius')) {
            $stem = mb_substr($type, 0, -3);

            return [
                'nominative' => $type,
                'genitive' => $stem . 'iaus',
                'dative' => $stem . 'iui',
                'accusative' => $stem . 'ių',
                'instrumental' => $stem . 'iumi',
                'locative' => $stem . 'iuje',
                'vocative' => $stem . 'iau',
            ];
        }

        return [
            'nominative' => $managerType,
            'genitive' => $managerType,
            'dative' => $managerType,
            'accusative' => $managerType,
            'instrumental' => $managerType,
            'locative' => $managerType,
            'vocative' => $managerType,
        ];
    }

    /**
     * @return array{nominative: string, genitive: string, dative: string, accusative: string, instrumental: string, locative: string, vocative: string}
     */
    private function emptyTitleDeclension(): array
    {
        return [
            'nominative' => '',
            'genitive' => '',
            'dative' => '',
            'accusative' => '',
            'instrumental' => '',
            'locative' => '',
            'vocative' => '',
        ];
    }

    /**
     * Naudininkas (vardui, pavardui).
     */
    public function dative(string $name, string $lytis): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $isFemale = mb_strtolower($lytis) === 'moteris';

        return $isFemale ? $this->dativeFemale($name) : $this->dativeMale($name);
    }

    /**
     * Galininkas (vardą, pavardą).
     */
    public function accusative(string $name, string $lytis): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $isFemale = mb_strtolower($lytis) === 'moteris';

        return $isFemale ? $this->accusativeFemale($name) : $this->accusativeMale($name);
    }

    /**
     * Įnagininkas (vardu, pavardu).
     */
    public function instrumental(string $name, string $lytis): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $isFemale = mb_strtolower($lytis) === 'moteris';

        return $isFemale ? $this->instrumentalFemale($name) : $this->instrumentalMale($name);
    }

    /**
     * Vietininkas (vardviet, pavardviet).
     */
    public function locative(string $name, string $lytis): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $isFemale = mb_strtolower($lytis) === 'moteris';

        return $isFemale ? $this->locativeFemale($name) : $this->locativeMale($name);
    }

    /**
     * Bendras kilmininkas pagal lytį.
     */
    public function genitive(string $name, string $lytis): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $isFemale = mb_strtolower($lytis) === 'moteris';
        return $isFemale ? $this->genitiveFemale($name) : $this->genitiveMale($name);
    }

    /**
     * Bendras šauksmininkas pagal lytį.
     */
    public function vocative(string $name, string $lytis): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $isFemale = mb_strtolower($lytis) === 'moteris';
        return $isFemale ? $this->vocativeFemale($name) : $this->vocativeMale($name);
    }

    private function genitiveMale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'tis' => mb_substr($name, 0, -3) . 'čio',
            $last3 === 'dis' => mb_substr($name, 0, -3) . 'džio',
            $last2 === 'is' => mb_substr($name, 0, -2) . 'io',
            $last2 === 'as' => mb_substr($name, 0, -2) . 'o',
            $last2 === 'ys' => mb_substr($name, 0, -2) . 'io',
            $last2 === 'us' => mb_substr($name, 0, -2) . 'aus',
            $last3 === 'ius' => mb_substr($name, 0, -3) . 'iaus',
            default => $name,
        };
    }

    private function genitiveFemale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last = mb_substr($name, -1);
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'ienė' => mb_substr($name, 0, -1) . 'ės',
            $last2 === 'tė' => mb_substr($name, 0, -1) . 'ės',
            $last2 === 'ė' => mb_substr($name, 0, -1) . 'ės',
            $last === 'a' => mb_substr($name, 0, -1) . 'os',
            $last === 'ė' => mb_substr($name, 0, -1) . 'ės',
            default => $name,
        };
    }

    private function vocativeMale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'tis' => mb_substr($name, 0, -3) . 'ti',
            $last3 === 'dis' => mb_substr($name, 0, -3) . 'di',
            $last2 === 'is' => mb_substr($name, 0, -2) . 'i',
            $last2 === 'as' => mb_substr($name, 0, -2) . 'ai',
            $last2 === 'ys' => mb_substr($name, 0, -2) . 'y',
            $last2 === 'us' => mb_substr($name, 0, -2) . 'au',
            $last3 === 'ius' => mb_substr($name, 0, -3) . 'iau',
            default => $name,
        };
    }

    private function vocativeFemale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last = mb_substr($name, -1);

        return match (true) {
            $last === 'ė' => mb_substr($name, 0, -1) . 'e',
            $last === 'a' => $name,
            default => $name,
        };
    }

    private function dativeMale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'tis' => mb_substr($name, 0, -3) . 'čiui',
            $last3 === 'dis' => mb_substr($name, 0, -3) . 'džiui',
            $last3 === 'ius' => mb_substr($name, 0, -3) . 'iui',
            $last2 === 'is' => mb_substr($name, 0, -2) . 'iui',
            $last2 === 'as' => mb_substr($name, 0, -2) . 'ui',
            $last2 === 'ys' => mb_substr($name, 0, -2) . 'iui',
            $last2 === 'us' => mb_substr($name, 0, -2) . 'ui',
            default => $name,
        };
    }

    private function dativeFemale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last = mb_substr($name, -1);
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'ienė' => mb_substr($name, 0, -1) . 'ei',
            $last2 === 'tė' => mb_substr($name, 0, -1) . 'ei',
            $last2 === 'ė' => mb_substr($name, 0, -1) . 'ei',
            $last === 'a' => mb_substr($name, 0, -1) . 'ai',
            default => $name,
        };
    }

    private function accusativeMale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'tis' => mb_substr($name, 0, -3) . 'į',
            $last3 === 'dis' => mb_substr($name, 0, -3) . 'į',
            $last3 === 'ius' => mb_substr($name, 0, -3) . 'ių',
            $last2 === 'is' => mb_substr($name, 0, -2) . 'į',
            $last2 === 'as' => mb_substr($name, 0, -2) . 'ą',
            $last2 === 'ys' => mb_substr($name, 0, -2) . 'į',
            $last2 === 'us' => mb_substr($name, 0, -2) . 'ą',
            default => $name,
        };
    }

    private function accusativeFemale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last = mb_substr($name, -1);
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'ienė' => mb_substr($name, 0, -1) . 'ę',
            $last2 === 'tė' => mb_substr($name, 0, -1) . 'ę',
            $last2 === 'ė' => mb_substr($name, 0, -1) . 'ę',
            $last === 'a' => mb_substr($name, 0, -1) . 'ą',
            default => $name,
        };
    }

    private function instrumentalMale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'tis' => mb_substr($name, 0, -3) . 'čiu',
            $last3 === 'dis' => mb_substr($name, 0, -3) . 'džiu',
            $last3 === 'ius' => mb_substr($name, 0, -3) . 'iumi',
            $last2 === 'is' => mb_substr($name, 0, -2) . 'iu',
            $last2 === 'as' => mb_substr($name, 0, -2) . 'u',
            $last2 === 'ys' => mb_substr($name, 0, -2) . 'iu',
            $last2 === 'us' => mb_substr($name, 0, -2) . 'u',
            default => $name,
        };
    }

    private function instrumentalFemale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last = mb_substr($name, -1);
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'ienė' => mb_substr($name, 0, -1) . 'e',
            $last2 === 'tė' => mb_substr($name, 0, -1) . 'e',
            $last2 === 'ė' => mb_substr($name, 0, -1) . 'e',
            $last === 'a' => $name,
            default => $name,
        };
    }

    private function locativeMale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'tis' => mb_substr($name, 0, -3) . 'čiuje',
            $last3 === 'dis' => mb_substr($name, 0, -3) . 'džiuje',
            $last3 === 'ius' => mb_substr($name, 0, -3) . 'iuje',
            $last2 === 'is' => mb_substr($name, 0, -2) . 'yje',
            $last2 === 'as' => mb_substr($name, 0, -2) . 'e',
            $last2 === 'ys' => mb_substr($name, 0, -2) . 'yje',
            $last2 === 'us' => mb_substr($name, 0, -2) . 'uje',
            default => $name,
        };
    }

    private function locativeFemale(string $name): string
    {
        if (mb_strlen($name) < 2) {
            return $name;
        }
        $last = mb_substr($name, -1);
        $last2 = mb_substr($name, -2);
        $last3 = mb_substr($name, -3);

        return match (true) {
            $last3 === 'ienė' => mb_substr($name, 0, -1) . 'ėje',
            $last2 === 'tė' => mb_substr($name, 0, -1) . 'ėje',
            $last2 === 'ė' => mb_substr($name, 0, -1) . 'ėje',
            $last === 'a' => mb_substr($name, 0, -1) . 'oje',
            default => $name,
        };
    }
}
