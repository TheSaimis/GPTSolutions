"use client";

import {
    createContext,
    useContext,
    useMemo,
    useState,
    ReactNode,
    useCallback,
    useEffect,
} from "react";

import type { BodyPart, BodyPartCategory } from "@/lib/types/AAP/BodyPart";
import type {
    RiskCategory,
    RiskGroup,
    RiskList,
    RiskSubcategory,
} from "@/lib/types/AAP/Risk";
import type { Worker } from "@/lib/types/Worker";
import { bodyPartApi } from "@/lib/api/AAP/bodyPart";
import { RiskApi } from "@/lib/api/AAP/risk";

type AAPTableContextType = {
    bodyPartCategories: BodyPartCategory[];
    setBodyPartCategories: React.Dispatch<React.SetStateAction<BodyPartCategory[]>>;

    bodyParts: BodyPart[];
    setBodyParts: React.Dispatch<React.SetStateAction<BodyPart[]>>;

    riskCategories: RiskCategory[];
    setRiskCategories: React.Dispatch<React.SetStateAction<RiskCategory[]>>;

    riskSubCategories: RiskSubcategory[];
    setRiskSubCategories: React.Dispatch<React.SetStateAction<RiskSubcategory[]>>;

    riskGroups: RiskGroup[];
    setRiskGroups: React.Dispatch<React.SetStateAction<RiskGroup[]>>;

    risks: RiskList[];
    setRisks: React.Dispatch<React.SetStateAction<RiskList[]>>;

    workers: Worker[];
    setWorkers: React.Dispatch<React.SetStateAction<Worker[]>>;

    selectedWorkerId: number | null;
    setSelectedWorkerId: React.Dispatch<React.SetStateAction<number | null>>;

    loading: boolean;
    setLoading: React.Dispatch<React.SetStateAction<boolean>>;

    reset: () => void;
};

const AAPTableContext = createContext<AAPTableContextType | null>(null);

type AAPTableProviderProps = {
    children: ReactNode;
};

export function AAPTableProvider({ children }: AAPTableProviderProps) {
    const [bodyPartCategories, setBodyPartCategories] = useState<BodyPartCategory[]>([]);
    const [bodyParts, setBodyParts] = useState<BodyPart[]>([]);
    const [riskCategories, setRiskCategories] = useState<RiskCategory[]>([]);
    const [riskSubCategories, setRiskSubCategories] = useState<RiskSubcategory[]>([]);
    const [riskGroups, setRiskGroups] = useState<RiskGroup[]>([]);
    const [risks, setRisks] = useState<RiskList[]>([]);
    const [workers, setWorkers] = useState<Worker[]>([]);
    const [selectedWorkerId, setSelectedWorkerId] = useState<number | null>(null);
    const [loading, setLoading] = useState<boolean>(false);

    const reset = useCallback(() => {
        setBodyPartCategories([]);
        setBodyParts([]);
        setRiskCategories([]);
        setRiskSubCategories([]);
        setRiskGroups([]);
        setRisks([]);
        setWorkers([]);
        setSelectedWorkerId(null);
        setLoading(false);
    }, []);

    useEffect(() => {
        async function fetchAll() {
            setLoading(true);

            const [
                bodyPartCategories,
                bodyParts,
                riskCategories,
                riskSubCategories,
                riskGroups,
                risks,
            ] = await Promise.all([
                bodyPartApi.getAllCategories(),
                bodyPartApi.getAllParts(),
                RiskApi.getRiskCategories(),
                RiskApi.getRiskSubcategories(),
                RiskApi.getRiskGroups(),
                RiskApi.getRiskLists(),
            ]);
            setBodyPartCategories(bodyPartCategories);
            setBodyParts(bodyParts);
            setRiskCategories(riskCategories);
            setRiskSubCategories(riskSubCategories);
            setRiskGroups(riskGroups);
            setRisks(risks);
            setLoading(false);
        }
        fetchAll();
    }, []);

    const value = useMemo<AAPTableContextType>(
        () => ({
            bodyPartCategories,
            setBodyPartCategories,

            bodyParts,
            setBodyParts,

            riskCategories,
            setRiskCategories,

            riskSubCategories,
            setRiskSubCategories,

            riskGroups,
            setRiskGroups,

            risks,
            setRisks,

            workers,
            setWorkers,

            selectedWorkerId,
            setSelectedWorkerId,

            loading,
            setLoading,

            reset,
        }),
        [
            bodyPartCategories,
            bodyParts,
            riskCategories,
            riskSubCategories,
            riskGroups,
            risks,
            workers,
            selectedWorkerId,
            loading,
            reset,
        ]
    );

    return (
        <AAPTableContext.Provider value={value}>
            {children}
        </AAPTableContext.Provider>
    );
}

export function useAAPTable() {
    const context = useContext(AAPTableContext);

    if (!context) {
        throw new Error("useAAPTable must be used inside AAPTableProvider");
    }

    return context;
}