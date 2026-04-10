"use client";

import { createContext, useContext, useMemo, useState, ReactNode } from "react";
import { Equipment } from "@/lib/types/equipment/equipment";
import { Worker } from "@/lib/types/Worker";
type EquipmentContextType = {
    equipment: Equipment[];
    setEquipment: (equipment: Equipment[]) => void;
    addEquipment: (item: Equipment) => void;
    removeEquipment: (item: Equipment) => void;
    clearEquipment: () => void;

    workers: Worker[];
    setWorkers: (workers: Worker[]) => void;
    addWorker: (item: Worker) => void;
    removeWorker: (item: Worker) => void;
    clearWorkers: () => void;
};

const EquipmentContext = createContext<EquipmentContextType>({
    equipment: [],
    setEquipment: () => { },
    addEquipment: () => { },
    removeEquipment: () => { },
    clearEquipment: () => { },

    workers: [],
    setWorkers: () => { },
    addWorker: () => { },
    removeWorker: () => { },
    clearWorkers: () => { },
});

export function EquipmentProvider({ children }: { children: ReactNode }) {

    const [equipment, setEquipment] = useState<Equipment[]>([]);
    const [workers, setWorkers] = useState<Worker[]>([]);

    const addEquipment = (item: Equipment) => {
        setEquipment((prev) => [...prev, item]);
    };
    const removeEquipment = (item: Equipment) => {
        setEquipment((prev) => prev.filter((e) => e.id !== item.id));
    };
    const clearEquipment = () => {
        setEquipment([]);
    };

    const addWorker = (item: Worker) => {
        setWorkers((prev) => [...prev, item]);
    };
    const removeWorker = (item: Worker) => {
        setWorkers((prev) => prev.filter((e) => e.id !== item.id));
    };
    const clearWorkers = () => {
        setWorkers([]);
    };

    const contextValue = useMemo(
        () => ({
            equipment,
            setEquipment,
            addEquipment,
            removeEquipment,
            clearEquipment,

            workers,
            setWorkers,
            addWorker,
            removeWorker,
            clearWorkers
        }),
        [equipment, workers]
    );

    return (
        <EquipmentContext.Provider value={contextValue}>
            {children}
        </EquipmentContext.Provider>
    );
}

export function useEquipment() {
    return useContext(EquipmentContext);
}