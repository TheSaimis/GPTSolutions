<?php

declare(strict_types=1);

namespace App\Services\Metadata;

/**
 * Papildomi ${...} žymų vardai (be riestinių), kuriuos užpildo specializuoti generatoriai —
 * jie neturi patekti į „naudotojo papildomų laukų“ sąrašą kartu su įmonės rekvizitais.
 */
final class FlowMacroIgnores
{
    /**
     * AAP įrangos Word šablonai (sąrašas / kortelės).
     *
     * @return list<string>
     */
    public static function aapEquipmentWord(): array
    {
        return [
            'pareiga',
            'pareigybe',
            'pareigybė',
            'pareigybes',
            'priemones',
            'terminas',
            'eilNr',
            'sarasas_turinys',
            'sarasas_duomenys',
            'aap_sarasas',
            'kiekis',
            'vnt',
            'korteles_turinys',
            'aap_korteles',
        ];
    }

    /**
     * AAP rizikų vertinimo Excel šablonas.
     *
     * @return list<string>
     */
    public static function riskAssessmentExcel(): array
    {
        return [
            'pareigybe',
            'pareigybė',
        ];
    }

    /**
     * Darbo vietų kenksmingų veiksnių pažyma (health certificate Word).
     *
     * @return list<string>
     */
    public static function healthCertificate(): array
    {
        return [
            'eilNr',
            'pareigybe',
            'veiksniai',
            'sifrai',
            'veiksniaiSuSifrais',
            'periodiskumas',
            'workerType',
            'riskFactors',
            'riskCodes',
            'riskFactorsWithCodes',
            'checkPeriod',
            'sveikatosRizikosVeiksniai',
            'sveikatosRizikosSifrai',
            'sveikatosRizikosLentele',
            'sveikatosTerminas',
            'rizikosVeiksniai',
            'rizikosSifrai',
            'rizikosLentele',
            'terminas',
            'healthRiskFactors',
            'healthRiskCodes',
            'healthRiskTable',
            'healthCheckTerm',
            'workerRiskRows',
            'workerRiskRowsText',
            'workerRiskFactorsByType',
            'workerRiskCiphersByType',
            'workerCheckPeriodsByType',
        ];
    }
}
