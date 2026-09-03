// Presentation/Pages — equivalente a page/obras_calendario.php e page/calendario.php
import { displayStatus, ACTIVITY_STATUS_LABELS } from '../../domain/activity/ActivityStatus.js';
import { layout, panel, badge, formatDate, escapeHtml } from '../ui.js';

export function render(ctx) {
    const { store } = ctx;
    const byDay = {};
    store.state.activities.forEach(item => {
        (byDay[item.date] ||= []).push(item);
    });
    const days = Object.keys(byDay).sort();

    return layout('Calendário', 'Uma leitura rápida dos próximos compromissos.')
        + panel('Agenda de atividades', `<div class="data-list">${days.map(day => {
            const items = byDay[day];
            const worst = items.some(item => displayStatus(item) === 'atrasada') ? 'atrasada' : displayStatus(items[0]);
            return `
            <div class="list-row">
                <div class="row-main"><div class="row-title">${formatDate(day)}</div>
                ${items.map(item => `<div class="row-meta">${escapeHtml(item.title)} · ${escapeHtml(store.obraName(item.obraId))}</div>`).join('')}</div>
                ${badge(worst, ACTIVITY_STATUS_LABELS[worst])}
            </div>`;
        }).join('') || '<div class="empty-state">O calendário está livre.</div>'}</div>`);
}
