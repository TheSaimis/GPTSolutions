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
     * Sulygina dažnas ASCII / klaidingas pareigų formas su lietuviškomis (linksniavimui).
     */
    public function normalizeManagerTitleType(string $managerType): string
    {
        $trimmed = trim($managerType);
        if ($trimmed === '') {
            return '';
        }
        $t = mb_strtolower($trimmed);
        $map = [
            'direktore'              => 'direktorė',
            'vadove'                 => 'vadovė',
            'generaline direktore'   => 'generalinė direktorė',
            'pirmininke'             => 'pirmininkė',
            'administratore'         => 'administratorė',
            'prezidente'             => 'prezidentė',
        ];

        return $map[$t] ?? $trimmed;
    }

    /**
     * Pareigų žodžio visos formos (vardininkas … šauksmininkas).
     *
     * @return array{nominative: string, genitive: string, dative: string, accusative: string, instrumental: string, locative: string, vocative: string}
     */
    public function declineManagerTitle(string $managerType): array
    {
        $managerType = $this->normalizeManagerTitleType($managerType);
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
        if ($isFemale) {
            $name = $this->normalizeAsciiFeminineSurnameEnding($name);
        }

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
        if ($isFemale) {
            $name = $this->normalizeAsciiFeminineSurnameEnding($name);
        }

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
        if ($isFemale) {
            $name = $this->normalizeAsciiFeminineSurnameEnding($name);
        }

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
        if ($isFemale) {
            $name = $this->normalizeAsciiFeminineSurnameEnding($name);
        }

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
        if ($isFemale) {
            $name = $this->normalizeAsciiFeminineSurnameEnding($name);
        }

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
        if ($isFemale) {
            $name = $this->normalizeAsciiFeminineSurnameEnding($name);
        }

        return $isFemale ? $this->vocativeFemale($name) : $this->vocativeMale($name);
    }

    /**
     * Moteriškos pavardės dažnai įvedamos be lietuviškų diakritikų: -iene, -aite, -yte, -iute, -ute.
     * Sulygina į -ienė, -aitė, -ytė, -iūtė, -ūtė, kad veiktų bendros -tė / -ienė taisyklės.
     */
    private function normalizeAsciiFeminineSurnameEnding(string $name): string
    {
        $n = trim($name);
        if ($n === '') {
            return $name;
        }
        if (preg_match('/ė$/u', $n) === 1) {
            return $n;
        }
        // Ilgesnės galūnės pirmiau (kad „kazlausk**iute**“ sugautų prieš bendrą „ute“).
        $rules = [
            '/iene$/iu' => 'ienė',
            '/aite$/iu' => 'aitė',
            '/iute$/iu' => 'iūtė',
            '/yte$/iu'  => 'ytė',
        ];
        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $n) === 1) {
                return (string) preg_replace($pattern, $replacement, $n);
            }
        }
        // -utė / -ūtė abu ASCII dažnai „...ute“ — be žodyno neatskiriama; įveskite Unicode arba naudokite -iūtė per „iute“.

        return $n;
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
        $last3 = mb_substr($name, -3);

        if ($last3 === 'ienė') {
            return mb_substr($name, 0, -1) . 'ės';
        }
        if (preg_match('/utė$/u', $name) === 1) {
            return (string) preg_replace('/utė$/u', 'učės', $name);
        }
        if (preg_match('/ytė$/u', $name) === 1) {
            return (string) preg_replace('/ytė$/u', 'yčės', $name);
        }
        if (str_ends_with($name, 'tė')) {
            return mb_substr($name, 0, -1) . 'ės';
        }
        if (str_ends_with($name, 'ė')) {
            return mb_substr($name, 0, -1) . 'ės';
        }
        if ($last === 'a') {
            return mb_substr($name, 0, -1) . 'os';
        }

        return $name;
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

        if (preg_match('/utė$/u', $name) === 1) {
            return (string) preg_replace('/utė$/u', 'učiai', $name);
        }
        if (preg_match('/ytė$/u', $name) === 1) {
            return (string) preg_replace('/ytė$/u', 'yčiai', $name);
        }
        if (str_ends_with($name, 'tė')) {
            return mb_substr($name, 0, -1) . 'e';
        }
        if ($last === 'ė') {
            return mb_substr($name, 0, -1) . 'e';
        }
        if ($last === 'a') {
            return $name;
        }

        return $name;
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
        $last3 = mb_substr($name, -3);

        if ($last3 === 'ienė') {
            return mb_substr($name, 0, -1) . 'ei';
        }
        if (preg_match('/utė$/u', $name) === 1) {
            return (string) preg_replace('/utė$/u', 'učiai', $name);
        }
        if (preg_match('/ytė$/u', $name) === 1) {
            return (string) preg_replace('/ytė$/u', 'yčiai', $name);
        }
        if (str_ends_with($name, 'tė')) {
            return mb_substr($name, 0, -1) . 'ei';
        }
        if (str_ends_with($name, 'ė')) {
            return mb_substr($name, 0, -1) . 'ei';
        }
        if ($last === 'a') {
            return mb_substr($name, 0, -1) . 'ai';
        }

        return $name;
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
        $last3 = mb_substr($name, -3);

        if ($last3 === 'ienė') {
            return mb_substr($name, 0, -1) . 'ę';
        }
        if (preg_match('/utė$/u', $name) === 1) {
            return (string) preg_replace('/utė$/u', 'učią', $name);
        }
        if (preg_match('/ytė$/u', $name) === 1) {
            return (string) preg_replace('/ytė$/u', 'yčią', $name);
        }
        if (str_ends_with($name, 'tė')) {
            return mb_substr($name, 0, -1) . 'ę';
        }
        if (str_ends_with($name, 'ė')) {
            return mb_substr($name, 0, -1) . 'ę';
        }
        if ($last === 'a') {
            return mb_substr($name, 0, -1) . 'ą';
        }

        return $name;
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
        $last3 = mb_substr($name, -3);

        if ($last3 === 'ienė') {
            return mb_substr($name, 0, -1) . 'e';
        }
        if (preg_match('/utė$/u', $name) === 1) {
            return (string) preg_replace('/utė$/u', 'učia', $name);
        }
        if (preg_match('/ytė$/u', $name) === 1) {
            return (string) preg_replace('/ytė$/u', 'yčia', $name);
        }
        if (str_ends_with($name, 'tė')) {
            return mb_substr($name, 0, -1) . 'e';
        }
        if (str_ends_with($name, 'ė')) {
            return mb_substr($name, 0, -1) . 'e';
        }
        if ($last === 'a') {
            return $name;
        }

        return $name;
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
        $last3 = mb_substr($name, -3);

        if ($last3 === 'ienė') {
            return mb_substr($name, 0, -1) . 'ėje';
        }
        if (preg_match('/utė$/u', $name) === 1) {
            return (string) preg_replace('/utė$/u', 'učioje', $name);
        }
        if (preg_match('/ytė$/u', $name) === 1) {
            return (string) preg_replace('/ytė$/u', 'yčioje', $name);
        }
        if (str_ends_with($name, 'tė')) {
            return mb_substr($name, 0, -1) . 'ėje';
        }
        if (str_ends_with($name, 'ė')) {
            return mb_substr($name, 0, -1) . 'ėje';
        }
        if ($last === 'a') {
            return mb_substr($name, 0, -1) . 'oje';
        }

        return $name;
    }
}