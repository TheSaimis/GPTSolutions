"use client";

import { EquipmentApi } from "@/lib/api/equipment";
import { useEquipment } from "../../equipmentContext";
import { useEffect } from "react";

export default function EquipmentController() {

    const { setEquipment } = useEquipment();
    useEffect(() => {
        EquipmentApi.getAll().then((res) => {
            setEquipment(res);
        });
    }, []);

    return (
        <div>
            <h1>I am equipment</h1>
        </div>
    );
}