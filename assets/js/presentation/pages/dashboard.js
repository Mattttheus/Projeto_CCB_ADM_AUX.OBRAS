// Presentation/Pages — equivalente a page/dashboard.php
// Inclui os gráficos Chart.js do painel executivo (status e orçado × realizado).
import { displayStatus, ACTIVITY_STATUS_LABELS } from '../../domain/activity/ActivityStatus.js';
import { layout, panel, stat, badge, money, formatDate, escapeHtml } from '../ui.js';

let charts = [];

export function render(ctx) {
    const { store, financialService } = ctx;
    const obras = store.obrasFor(ctx.auth.user());
    const obraIds = new Set(obras.map(obra => obra.id));
    const activities = store.state.activities.filter(item => obraIds.has(item.obraId));
    const totalBudget = obras.reduce((sum, obra) => sum + obra.budget, 0);
    const spent = store.state.transactions
        .filter(item => obraIds.has(item.obraId))
        .reduce((sum, item) => sum + financialService.entryValue(item), 0);
    const overdue = activities.filter(item => displayStatus(item) === 'atrasada').length;
    const done = activities.filter(item => item.status === 'concluida').length;
    const progress = activities.length ? Math.round((done / activities.length) * 100) : 0;
    // Manutenção recorrente (type='recorrente') não tem prazo fixo — fica fora do "próximas atividades".
    const upcoming = activities.filter(item => item.date)
        .sort((a, b) => a.date.localeCompare(b.date)).slice(0, 4);

    const obraBars = obras.map(item => {
        const obraProgress = store.obraProgress(item.id);
        return `
        <div class="progress-line">
            <div class="progress-label"><strong>${escapeHtml(item.name)}</strong><span>${obraProgress}%</span></div>
            <div class="progress-track"><div class="progress-fill ${obraProgress < 30 ? 'orange' : ''}" style="width:${obraProgress}%"></div></div>
        </div>`;
    }).join('');

    return layout('Visão geral', 'Acompanhe o ritmo das suas obras em um só lugar.',
        ctx.auth.hasFullProjectAccess() ? '<button class="button button-primary" data-action="new-obra">+ Nova obra</button>' : '')
        + `<div class="stats-grid">
            ${stat('Obras ativas', obras.length, 'Projetos no portfólio', 'OB')}
            ${stat('Atividades', activities.length, 'Itens no cronograma', 'AT')}
            ${stat('Em atraso', overdue, 'Precisam de atenção', '!', 'negative')}
            ${stat('Realizado', money(spent), `de ${money(totalBudget)} orçados`, 'R$', 'positive')}
        </div>
        <div class="chart-grid">
            ${panel('Status das atividades', '<div class="chart-box"><canvas id="chart-status" aria-label="Distribuição de status das atividades"></canvas></div>')}
            ${panel('Orçado × realizado por obra', '<div class="chart-box"><canvas id="chart-budget" aria-label="Orçado versus realizado por obra"></canvas></div>')}
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
                ${ctx.auth.isAdmin() ? '<button class="quick-action button button-light" data-route-link="usuarios">◎ Controle de acessos</button>' : ''}
            </div>`)}
        </div>`;
}

/** Instancia os gráficos após o HTML entrar no DOM (hook chamado pelo roteador). */
export function mount(ctx) {
    if (typeof Chart === 'undefined') return;
    charts.forEach(chart => chart.destroy());
    charts = [];

    const { store, financialService } = ctx;
    const obras = store.obrasFor(ctx.auth.user());
    const obraIds = new Set(obras.map(obra => obra.id));
    const activities = store.state.activities.filter(item => obraIds.has(item.obraId));

    const statusCanvas = document.querySelector('#chart-status');
    if (statusCanvas) {
        const groups = { pendente: 0, em_andamento: 0, atrasada: 0, concluida: 0 };
        activities.forEach(item => { groups[displayStatus(item)] += 1; });
        charts.push(new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Pendente', 'Em andamento', 'Atrasada', 'Concluída'],
                datasets: [{
                    data: [groups.pendente, groups.em_andamento, groups.atrasada, groups.concluida],
                    backgroundColor: ['#94a3b8', '#d97706', '#dc2626', '#059669'],
                    borderWidth: 0,
                }],
            },
            options: { plugins: { legend: { position: 'bottom' } }, cutout: '62%', maintainAspectRatio: false },
        }));
    }

    const budgetCanvas = document.querySelector('#chart-budget');
    if (budgetCanvas) {
        charts.push(new Chart(budgetCanvas, {
            type: 'bar',
            data: {
                labels: obras.map(obra => obra.name),
                datasets: [
                    { label: 'Orçado', data: obras.map(obra => obra.budget), backgroundColor: '#12324a', borderRadius: 6 },
                    {
                        label: 'Realizado',
                        data: obras.map(obra => store.state.transactions
                            .filter(item => item.obraId === obra.id)
                            .reduce((sum, item) => sum + financialService.entryValue(item), 0)),
                        backgroundColor: '#0f766e',
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { ticks: { callback: value => money(value) } } },
            },
        }));
    }
}
