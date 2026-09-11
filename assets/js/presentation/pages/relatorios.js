// Presentation/Pages — equivalente a page/relatorios.php
// Exporta CSV local com obras, atividades e despesas das obras visíveis.
import { categoryLabel } from '../../domain/finance/FinancialCategory.js';
import { displayStatus, ACTIVITY_STATUS_LABELS } from '../../domain/activity/ActivityStatus.js';
import { obraStatusLabel } from '../../domain/obra/ObraStatus.js';
import { layout, panel, money } from '../ui.js';

export function render(ctx) {
    const { store, financialService } = ctx;
    const obras = store.obrasFor(ctx.auth.user());
    const obraIds = new Set(obras.map(obra => obra.id));
    const activities = store.state.activities.filter(item => obraIds.has(item.obraId));
    const spent = store.state.transactions
        .filter(item => obraIds.has(item.obraId))
        .reduce((sum, item) => sum + financialService.entryValue(item), 0);

    return layout('Relatórios', 'Exporte um resumo dos dados das suas obras.',
        '<button class="button button-primary" data-action="export-report">Baixar CSV</button>')
        + `<div class="content-grid">
            ${panel('Resumo operacional', `<div class="data-list">
                <div class="list-row"><strong>Obras cadastradas</strong><span>${obras.length}</span></div>
                <div class="list-row"><strong>Atividades registradas</strong><span>${activities.length}</span></div>
                <div class="list-row"><strong>Despesas no período</strong><span>${money(spent)}</span></div>
            </div>`)}
            ${panel('Exportação', '<div class="row-meta">O arquivo CSV é gerado no navegador e não é enviado para nenhum servidor. O envio por e-mail e a geração de PDF seguem disponíveis na versão PHP (page/relatorios.php).</div>')}
        </div>`;
}

export function bind(ctx) {
    document.querySelector('[data-action="export-report"]').addEventListener('click', () => {
        const { store, financialService } = ctx;
        const obras = store.obrasFor(ctx.auth.user());
        const obraIds = new Set(obras.map(obra => obra.id));
        const rows = [
            ['Tipo', 'Descrição', 'Obra', 'Categoria/Status', 'Quantidade', 'Valor unitário', 'Total', 'Data'],
            ...obras.map(obra => [
                'Obra', obra.name, obra.city ?? '', obraStatusLabel(obra.status),
                '', '', money(obra.budget), '',
            ]),
            ...store.state.activities.filter(item => obraIds.has(item.obraId)).map(item => [
                'Atividade', item.title, store.obraName(item.obraId),
                ACTIVITY_STATUS_LABELS[displayStatus(item)], '', '', '', item.date ?? '',
            ]),
            ...store.state.transactions.filter(item => obraIds.has(item.obraId)).map(item => [
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
