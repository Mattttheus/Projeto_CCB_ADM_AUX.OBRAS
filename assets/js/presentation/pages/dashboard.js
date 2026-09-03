// Presentation/Pages — equivalente a page/dashboard.php
import { displayStatus, ACTIVITY_STATUS_LABELS } from '../../domain/activity/ActivityStatus.js';
import { layout, panel, stat, badge, money, formatDate, escapeHtml } from '../ui.js';

export function render(ctx) {
    const { store, financialService } = ctx;
    const { obras, activities } = store.state;
    const totalBudget = financialService.totalBudget();
    const spent = financialService.totalSpent();
    const overdue = activities.filter(item => displayStatus(item) === 'atrasada').length;
    const done = activities.filter(item => item.status === 'concluida').length;
    const progress = activities.length ? Math.round((done / activities.length) * 100) : 0;
    const upcoming = [...activities].sort((a, b) => a.date.localeCompare(b.date)).slice(0, 4);

    const obraBars = obras.map(item => `
        <div class="progress-line">
            <div class="progress-label"><strong>${escapeHtml(item.name)}</strong><span>${item.progress}%</span></div>
            <div class="progress-track"><div class="progress-fill ${item.progress < 30 ? 'orange' : ''}" style="width:${item.progress}%"></div></div>
        </div>`).join('');

    return layout('Visão geral', 'Acompanhe o ritmo das suas obras em um só lugar.',
        '<button class="button button-primary" data-action="new-obra">+ Nova obra</button>')
        + `<div class="stats-grid">
            ${stat('Obras ativas', obras.length, 'Projetos no portfólio', 'OB')}
            ${stat('Atividades', activities.length, 'Itens no cronograma', 'AT')}
            ${stat('Em atraso', overdue, 'Precisam de atenção', '!', 'negative')}
            ${stat('Realizado', money(spent), `de ${money(totalBudget)} orçados`, 'R$', 'positive')}
        </div>
        <div class="content-grid">
            ${panel('Progresso das obras', obraBars || '<div class="empty-state">Cadastre sua primeira obra.</div>')}
            ${panel('Próximas atividades', `<div class="data-list">${upcoming.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                    <div class="row-meta">${escapeHtml(store.obraName(item.obraId))} · ${formatDate(item.date)}</div></div>
                    ${badge(displayStatus(item), ACTIVITY_STATUS_LABELS[displayStatus(item)])}
                </div>`).join('') || '<div class="empty-state">Nenhuma atividade cadastrada.</div>'}</div>`)}
            ${panel('Resumo do cronograma', `
                <div class="progress-line"><div class="progress-label"><strong>Conclusão geral</strong><span>${progress}%</span></div>
                <div class="progress-track"><div class="progress-fill lime" style="width:${progress}%"></div></div></div>
                <div class="row-meta">${done} de ${activities.length} atividades concluídas</div>`)}
            ${panel('Atalhos', `<div class="data-list">
                <button class="quick-action button button-light" data-action="new-activity">+ Registrar atividade</button>
                <button class="quick-action button button-light" data-action="new-transaction">+ Lançar despesa</button>
            </div>`)}
        </div>`;
}
