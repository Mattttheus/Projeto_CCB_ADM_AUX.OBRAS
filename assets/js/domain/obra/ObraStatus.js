// Domain/Obra — espelha o CHECK de status da tabela `obras` (PostgreSQL/MySQL).
export const ObraStatus = Object.freeze({
    IN_PROGRESS: 'em_andamento',
    COMPLETED: 'concluida',
    PAUSED: 'pausada',
});

export const OBRA_STATUS_LABELS = Object.freeze({
    [ObraStatus.IN_PROGRESS]: 'Em andamento',
    [ObraStatus.COMPLETED]: 'Concluída',
    [ObraStatus.PAUSED]: 'Pausada',
});

export function obraStatusLabel(status) {
    return OBRA_STATUS_LABELS[status] ?? status;
}

/** Normaliza rótulos legados do modo demonstração para os enums do banco. */
export function normalizeObraStatus(status) {
    const map = {
        'Em andamento': ObraStatus.IN_PROGRESS,
        'Planejamento': ObraStatus.PAUSED,
        'Concluída': ObraStatus.COMPLETED,
        'Pausada': ObraStatus.PAUSED,
    };
    return OBRA_STATUS_LABELS[status] ? status : (map[status] ?? ObraStatus.IN_PROGRESS);
}
