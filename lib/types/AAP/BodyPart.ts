export type BodyPartCategory = {
  id: number;
  name: string;
  lineNumber: number;
};

export type BodyPart = {
  id: number;
  name: string;
  lineNumber: number;
  category: BodyPartCategory | null;
};