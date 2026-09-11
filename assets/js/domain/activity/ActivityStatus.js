// Domain/Activity — espelha app/Domain/Activity/ActivityStatus.php
export const ActivityStatus = Object.freeze({
    PENDING: 'pendente',
    IN_PROGRESS: 'em_andamento',
    COMPLETED: 'concluida',
});

export const ACTIVITY_STATUS_LABELS = Object.freeze({
    [ActivityStatus.PENDING]: 'Pendente',
    [ActivityStatus.IN_PROGRESS]: 'Em andamento',
    [ActivityStatus.COMPLETED]: 'Concluída',
    atrasada: 'Atrasada',
});

export function allStatuses() {
    return Object.values(ActivityStatus);
}

export function isValidStatus(status) {
    return allStatuses().includes(status);
}

/** Atraso é derivado: prazo vencido e atividade não concluída (equivalente ao cron PHP). */
export function isOverdue(activity, today = new Date().toISOString().slice(0, 10)) {
    return activity.status !== ActivityStatus.COMPLETED && activity.date < today;
}

export function displayStatus(activity, today) {
    return isOverdue(activity, today) ? 'atrasada' : activity.status;
}

export function nextStatus(current) {
    const order = allStatuses();
    return order[(order.indexOf(current) + 1) % order.length];
}

/** Manutenção recorrente (tipo='recorrente' no banco): dia_semana segue Date.getDay() (0=domingo). */
export const WEEKDAY_LABELS = Object.freeze([
    'Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado',
]);

export function isMaintenance(activity) {
    return activity.type === 'recorrente';
}
