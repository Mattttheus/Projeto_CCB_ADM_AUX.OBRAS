// Presentation/Pages — equivalente a page/relatorios.php
import { categoryLabel } from '../../domain/finance/FinancialCategory.js';
import { layout, panel, money } from '../ui.js';

export function render(ctx) {
    const { store, financialService } = ctx;
    return layout('Relatórios', 'Exporte um resumo dos dados registrados neste navegador.',
        '<button class="button button-primary" data-action="export-report">Baixar CSV</button>')
        + `<div class="content-grid">
            ${panel('Resumo operacional', `<div class="data-list">
                <div class="list-row"><strong>Obras cadastradas</strong><span>${store.state.obras.length}</span></div>
                <div class="list-row"><strong>Atividades registradas</strong><span>${store.state.activities.length}</span></div>
                <div class="list-row"><strong>Despesas no período</strong><span>${money(financialService.totalSpent())}</span></div>
            </div>`)}
            ${panel('Exportação', '<div class="row-meta">O arquivo CSV é gerado localmente e não é enviado para nenhum servidor.</div>')}
        </div>`;
}

export function bind(ctx) {
    document.querySelector('[data-action="export-report"]').addEventListener('click', () => {
        const { store, financialService } = ctx;
        const rows = [
            ['Tipo', 'Descrição', 'Obra', 'Categoria', 'Quantidade', 'Valor unitário', 'Total', 'Data'],
            ...store.state.transactions.map(item => [
                'Despesa', item.description, store.obraName(item.obraId),
                categoryLabel(item.category), item.quantity, item.unitCost,
                financialService.entryValue(item), item.date,
            ]),
        ];
        const csv = rows.map(row => row.map(value => `"${String(value).replaceAll('"', '""')}"`).join(';')).join('\n');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8' }));
        link.download = 'relatorio-auxiliar-obras.csv';
        link.click();
        URL.revokeObjectURL(link.href);
        ctx.store.log('Relatório CSV exportado.');
        ctx.notify('Relatório exportado.');
    });
}
