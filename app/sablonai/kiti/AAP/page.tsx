"use client";

import { bodyPartApi } from "@/lib/api/AAP/bodyPart";
import { RiskApi } from "@/lib/api/AAP/risk";
import type { BodyPart, BodyPartCategory } from "@/lib/types/AAP/BodyPart";
import type { RiskCategory, RiskGroup, RiskList, RiskSubcategory } from "@/lib/types/AAP/Risk";
import { AAPTableProvider } from "./AAPTableContext";
import { useEffect, useState } from "react";
import AAPTable from "./table/table";

export default function Page() {
    const [bodyPartCategories, setBodyPartCategories] = useState<BodyPartCategory[]>([]);
    const [bodyParts, setBodyParts] = useState<BodyPart[]>([]);
    const [riskCategories, setRiskCategories] = useState<RiskCategory[]>([]);
    const [riskSubCategories, setRiskSubCategories] = useState<RiskSubcategory[]>([]);
    const [riskGroups, setRiskGroups] = useState<RiskGroup[]>([]);
    const [risks, setRisks] = useState<RiskList[]>([]);

    async function getAllItems() {
        const bodyPartCategories = await bodyPartApi.getAllCategories();
        const bodyParts = await bodyPartApi.getAllParts();
        const riskCategories = await RiskApi.getRiskCategories();
        const riskSubCategories = await RiskApi.getRiskSubcategories();
        const riskGroups = await RiskApi.getRiskGroups();
        const risks = await RiskApi.getRiskLists();

        setBodyPartCategories(bodyPartCategories);
        setBodyParts(bodyParts);
        setRiskCategories(riskCategories);
        setRiskSubCategories(riskSubCategories);
        setRiskGroups(riskGroups);
        setRisks(risks);
    }

    useEffect(() => {
        getAllItems();
    }, []);

    return (
        <div>
            <AAPTableProvider>
                <AAPTable />
            </AAPTableProvider>
        </div>
    )
}