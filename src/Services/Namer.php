<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lietuviškų vardų ir pavardžių linksniavimas – kilmininkas (genitive) ir šauksmininkas (vocative).
 *
 * Pavyzdžiai:
 *   Tomas → Tomo (gen), Tomai (voc)
 *   Jonaitis → Jonaičio (gen), Jonaiti (voc)
 *   Jonaitienė → Jonaitienės (gen)
 *   vadovas → vadovo (gen)
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
        $type = mb_strtolower(trim($managerType));
        return match ($type) {
            'vadovas' => 'vadovo',
            'vadovė' => 'vadovės',
            'direktorius' => 'direktoriaus',
            'direktorė' => 'direktorės',
            default => str_ends_with($type, 'ė')
                ? mb_substr($type, 0, -1) . 'ės'
                : (str_ends_with($type, 'as') ? mb_substr($type, 0, -2) . 'o' : $type),
        };
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
}
