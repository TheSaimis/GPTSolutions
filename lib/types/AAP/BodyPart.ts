export type BodyPartCategory = {
    map(arg0: (bp: { id: any; }) => import("react/jsx-runtime").JSX.Element): import("react").ReactNode;
    name: string;
    lineNumber: number;
    id: number;
};

export type BodyPart = {
    name: string;
    lineNumber: number;
    id: number;
    category: number;
}