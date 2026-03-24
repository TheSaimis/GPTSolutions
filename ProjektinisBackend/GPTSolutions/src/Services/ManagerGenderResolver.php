<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Nustato vadovo lytį pagal managerType (vadovas/vadovė, direktorius/direktorė).
 */
final class ManagerGenderResolver
{
    /**
     * Grąžina "Vyras" arba "Moteris" pagal vadovo tipą.
     */
    public function resolve(string $managerType): string
    {
        $type = mb_strtolower(trim($managerType));
        $female = ['vadovė', 'direktorė'];
        $male   = ['vadovas', 'direktorius'];
        if (in_array($type, $female, true)) {
            return 'Moteris';
        }
        if (in_array($type, $male, true)) {
            return 'Vyras';
        }
        if (str_ends_with($type, 'ė')) {
            return 'Moteris';
        }
        return 'Vyras';
    }
}
