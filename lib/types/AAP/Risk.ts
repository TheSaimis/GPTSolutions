import type { BodyPart } from "./BodyPart";
import type { Worker } from "../Worker";

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