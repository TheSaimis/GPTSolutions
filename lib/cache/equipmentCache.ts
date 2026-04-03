import { Equipment } from "../types/equipment/equipment";

export const equipmentCache: Equipment[] = [];

export function setEquipmentCache(equipment: Equipment[]) {
    equipmentCache.splice(0, equipmentCache.length);
    equipmentCache.push(...equipment);
}

export function getEquipmentCache() {
    return equipmentCache;
}

export function addEquipmentToCache(equipment: Equipment) {
    equipmentCache.push(equipment);
}

export function getEquipmentFromCache(id: number) {
    return equipmentCache.find(equipment => equipment.id === id);
}

export function removeEquipmentFromCache(id: number) {
    equipmentCache.splice(equipmentCache.findIndex(equipment => equipment.id === id), 1);
}

export function clearEquipmentCache() {
    equipmentCache.splice(0, equipmentCache.length);
}