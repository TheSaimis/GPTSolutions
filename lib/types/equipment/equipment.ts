export type Equipment = {
    id: number;
    name: string;
    expirationDate: string;
    /** Matavimo vienetas dokumente: „vnt“ arba „poros“ */
    unitOfMeasurement?: string;
    nameEn?: string | null;
    nameRu?: string | null;
    expirationDateEn?: string | null;
    expirationDateRu?: string | null;
};
