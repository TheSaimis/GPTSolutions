export type CompanyWorkerEquipmentRow = {
    id: number;
    company: { id: number; companyName?: string | null } | null;
    worker: { id: number; name: string } | null;
    equipment: {
        id: number;
        name: string;
        expirationDate: string;
        unitOfMeasurement?: string;
    } | null;
};
