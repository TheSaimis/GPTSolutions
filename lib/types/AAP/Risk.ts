import type { BodyPart } from "./bodyPart";
import type { Worker } from "./worker";

export interface RiskGroup {
  id: number;
  name: string;
  lineNumber: number;
  categories?: RiskCategory[];
  directSubcategories?: RiskSubcategory[];
}

export interface RiskCategory {
  id: number;
  name: string;
  lineNumber: number;
  group: RiskGroup | null;
  subcategories?: RiskSubcategory[];
}

export interface RiskSubcategory {
  id: number;
  name: string;
  lineNumber: number;
  category: RiskCategory | null;
  group: RiskGroup | null;
  effectiveGroup?: RiskGroup | null;
  riskLists?: RiskList[];
}

export interface RiskList {
  id: number;
  bodyPart: BodyPart | null;
  riskSubcategory: RiskSubcategory | null;
  worker: Worker | null;
}