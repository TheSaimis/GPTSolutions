export type AapEquipmentGroupRow = {
    id: number;
    companyId: number | null;
    name: string;
    sortOrder: number;
    workers: Array<{
        id: number;
        worker: { id: number; name: string };
    }>;
    equipment: Array<{
        id: number;
        equipment: {
            id: number;
            name: string;
            expirationDate: string;
            unitOfMeasurement?: string;
        };
    }>;
};
