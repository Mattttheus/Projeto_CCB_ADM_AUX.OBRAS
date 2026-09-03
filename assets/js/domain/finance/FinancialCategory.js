// Domain/Finance — espelha app/Domain/Finance/FinancialCategory.php
export const FinancialCategory = Object.freeze({
    MATERIAL: 'material',
    OPERATIONAL: 'operacional',
    SERVICE: 'servico',
    EQUIPMENT: 'equipamento',
    PRODUCT: 'produto',
});

export const FINANCIAL_CATEGORY_LABELS = Object.freeze({
    [FinancialCategory.MATERIAL]: 'Materiais',
    [FinancialCategory.OPERATIONAL]: 'Custos operacionais',
    [FinancialCategory.SERVICE]: 'Serviços',
    [FinancialCategory.EQUIPMENT]: 'Equipamentos',
    [FinancialCategory.PRODUCT]: 'Produtos',
});

export function categoryLabel(key) {
    return FINANCIAL_CATEGORY_LABELS[key] ?? key;
}
