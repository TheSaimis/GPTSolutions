"use client";

import { createContext, useContext, useMemo, useState, ReactNode } from "react";
import { Equipment } from "@/lib/types/equipment/equipment";
import { Worker } from "@/lib/types/Worker";
import { WorkerItem } from "@/lib/types/entities";

type EquipmentContextType = {
    equipment: Equipment[];
    setEquipment: (equipment: Equipment[]) => void;
    addEquipment: (item: Equipment) => void;
    removeEquipment: (item: Equipment) => void;
    clearEquipment: () => void;

    workerItems: WorkerItem[];
    setWorkerItems: (workerItems: WorkerItem[]) => void;
    addWorkerItem: (item: WorkerItem) => void;
    removeWorkerItem: (item: WorkerItem) => void;
    clearWorkerItems: () => void;

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

    workerItems: [],
    setWorkerItems: () => { },
    addWorkerItem: () => { },
    removeWorkerItem: () => { },
    clearWorkerItems: () => { },

    workers: [],
    setWorkers: () => { },
    addWorker: () => { },
    removeWorker: () => { },
    clearWorkers: () => { },
});

export function EquipmentProvider({ children }: { children: ReactNode }) {

    const [equipment, setEquipment] = useState<Equipment[]>([]);
    const [workerItems, setWorkerItems] = useState<WorkerItem[]>([]);
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

    const addWorkerItem = (item: WorkerItem) => {
        setWorkerItems((prev) => [...prev, item]);
    };
    const removeWorkerItem = (item: WorkerItem) => {
        setWorkerItems((prev) => prev.filter((e) => e.id !== item.id));
    };
    const clearWorkerItems = () => {
        setWorkerItems([]);
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

            workerItems,
            setWorkerItems,
            addWorkerItem,
            removeWorkerItem,
            clearWorkerItems,

            workers,
            setWorkers,
            addWorker,
            removeWorker,
            clearWorkers
        }),
        [equipment, workerItems, workers]
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