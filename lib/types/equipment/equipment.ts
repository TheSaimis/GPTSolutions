export type Equipment = {
    id: number;
    name: string;
    expirationDate: string;
    /** Matavimo vienetas dokumente: „vnt“ arba „poros“ */
    unitOfMeasurement?: string;
};
