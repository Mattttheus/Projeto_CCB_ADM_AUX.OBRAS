// Presentation/Pages — equivalente a page/financeiro.php
// Inclui orçamento por obra (orcamentos_obras) e gráfico de despesas por categoria.
import { categoryLabel, FINANCIAL_CATEGORY_LABELS } from '../../domain/finance/FinancialCategory.js';
import { layout, panel, stat, money, formatDate, escapeHtml } from '../ui.js';

let chart = null;

export function render(ctx) {
    const { store, financialService } = ctx;
    const obras = store.obrasFor(ctx.auth.user());
    const obraIds = new Set(obras.map(obra => obra.id));
    const transactions = store.state.transactions
        .filter(item => obraIds.has(item.obraId))
        .sort((a, b) => b.date.localeCompare(a.date));
    const spent = transactions.reduce((sum, item) => sum + financialService.entryValue(item), 0);
    const budget = obras.reduce((sum, obra) => sum + obra.budget, 0);

    const budgetRows = obras.map(obra => {
        const realizado = transactions
            .filter(item => item.obraId === obra.id)
            .reduce((sum, item) => sum + financialService.entryValue(item), 0);
        const percentual = obra.budget > 0 ? Math.min(100, Math.round((realizado / obra.budget) * 100)) : 0;
        return `
        <div class="progress-line">
            <div class="progress-label"><strong>${escapeHtml(obra.name)}</strong><span>${money(realizado)} de ${money(obra.budget)}</span></div>
            <div class="progress-track"><div class="progress-fill ${percentual >= 90 ? 'orange' : ''}" style="width:${percentual}%"></div></div>
        </div>`;
    }).join('');

    return layout('Financeiro', 'Controle de despesas por projeto (quantidade × valor unitário).',
        `<div class="header-actions">
            ${ctx.auth.hasFullProjectAccess() ? '<button class="button button-light" data-action="new-budget">Definir orçamento</button>' : ''}
            <button class="button button-primary" data-action="new-transaction">+ Nova despesa</button>
        </div>`)
        + `<div class="stats-grid">
            ${stat('Orçamento total', money(budget), 'Soma dos projetos', 'OR')}
            ${stat('Despesas lançadas', money(spent), 'Total realizado', 'R$', 'negative')}
            ${stat('Saldo disponível', money(budget - spent), 'Estimativa atual', 'SA', 'positive')}
            ${stat('Lançamentos', transactions.length, 'Registros no período', 'LN')}
        </div>`
        + `<div class="chart-grid">
            ${panel('Despesas por categoria', '<div class="chart-box"><canvas id="finance-chart" aria-label="Despesas por categoria"></canvas></div>')}
            ${panel('Orçamento por obra', budgetRows || '<div class="empty-state">Nenhuma obra com orçamento.</div>')}
        </div>`
        + panel('Lançamentos recentes', `
            <div class="table-wrap"><table class="data-table">
                <thead><tr><th>Descrição</th><th>Obra</th><th>Categoria</th><th>Data</th><th>Qtd × Unit.</th><th>Total</th><th></th></tr></thead>
                <tbody>${transactions.map(item => `
                    <tr>
                        <td><strong>${escapeHtml(item.description)}</strong></td>
                        <td>${escapeHtml(store.obraName(item.obraId))}</td>
                        <td>${escapeHtml(categoryLabel(item.category))}</td>
                        <td>${formatDate(item.date)}</td>
                        <td>${item.quantity} × ${money(item.unitCost)}</td>
                        <td><strong>${money(financialService.entryValue(item))}</strong></td>
                        <td><button class="table-action" data-delete="transaction:${item.id}">Excluir</button></td>
                    </tr>`).join('') || '<tr><td colspan="7"><div class="empty-state">Nenhum lançamento.</div></td></tr>'}</tbody>
            </table></div>`);
}

export function bind() { /* ações tratadas pela delegação global do app.js */ }

/** Gráfico de barras por categoria (Chart.js), montado após o HTML entrar no DOM. */
export function mount(ctx) {
    if (typeof Chart === 'undefined') return;
    chart?.destroy();
    chart = null;
    const canvas = document.querySelector('#finance-chart');
    if (!canvas) return;

    const obraIds = new Set(ctx.store.obrasFor(ctx.auth.user()).map(obra => obra.id));
    const totals = Object.keys(FINANCIAL_CATEGORY_LABELS).map(category =>
        ctx.store.state.transactions
            .filter(item => obraIds.has(item.obraId) && item.category === category)
            .reduce((sum, item) => sum + ctx.financialService.entryValue(item), 0));

    chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: Object.values(FINANCIAL_CATEGORY_LABELS),
            datasets: [{ label: 'Total gasto', data: totals, backgroundColor: '#12324a', borderRadius: 6 }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { callback: value => money(value) } } },
        },
    });
}
