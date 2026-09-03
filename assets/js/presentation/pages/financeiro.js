// Presentation/Pages — equivalente a page/financeiro.php
import { categoryLabel } from '../../domain/finance/FinancialCategory.js';
import { layout, panel, stat, money, formatDate, escapeHtml } from '../ui.js';

export function render(ctx) {
    const { store, financialService } = ctx;
    const spent = financialService.totalSpent();
    const budget = financialService.totalBudget();
    const transactions = [...store.state.transactions].sort((a, b) => b.date.localeCompare(a.date));

    return layout('Financeiro', 'Controle de despesas por projeto (quantidade × valor unitário).',
        '<button class="button button-primary" data-action="new-transaction">+ Nova despesa</button>')
        + `<div class="stats-grid">
            ${stat('Orçamento total', money(budget), 'Soma dos projetos', 'OR')}
            ${stat('Despesas lançadas', money(spent), 'Total realizado', 'R$', 'negative')}
            ${stat('Saldo disponível', money(budget - spent), 'Estimativa atual', 'SA', 'positive')}
            ${stat('Lançamentos', transactions.length, 'Registros no período', 'LN')}
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
