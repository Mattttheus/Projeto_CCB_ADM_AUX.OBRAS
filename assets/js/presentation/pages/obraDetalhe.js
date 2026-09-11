// Presentation/Pages — página dedicada de UMA obra, endereçável via #obra?id=<id>.
// Equivalente ao page/gerenciar_obra.php (que também usa um parâmetro obra_id):
// cronograma, manutenção recorrente, financeiro, documentos e chamados da obra,
// tudo numa página só, com atalhos de cadastro já pré-selecionando a obra.
import { obraStatusLabel } from '../../domain/obra/ObraStatus.js';
import { displayStatus, ACTIVITY_STATUS_LABELS, WEEKDAY_LABELS } from '../../domain/activity/ActivityStatus.js';
import { categoryLabel } from '../../domain/finance/FinancialCategory.js';
import { layout, panel, badge, money, formatDate, formatDateTime, escapeHtml } from '../ui.js';

function currentObraId() {
    return Number(new URLSearchParams(location.hash.split('?')[1] ?? '').get('id'));
}

/** Só obras que o usuário pode acessar (RLS já filtra no Supabase; obrasFor() filtra no modo demo). */
function currentObra(ctx) {
    return ctx.store.obrasFor(ctx.auth.user()).find(item => item.id === currentObraId());
}

export function render(ctx) {
    const obra = currentObra(ctx);
    if (!obra) {
        return layout('Obra não encontrada', 'Ela pode ter sido excluída, ou você não tem acesso a ela.',
            '<button class="button button-light" data-route-link="obras">← Voltar para Obras</button>');
    }

    const obraId = obra.id;
    const activities = ctx.store.state.activities.filter(item => item.obraId === obraId);
    const cronograma = activities.filter(item => item.type !== 'recorrente')
        .sort((a, b) => (a.date ?? '').localeCompare(b.date ?? ''));
    const manutencoes = activities.filter(item => item.type === 'recorrente')
        .sort((a, b) => (a.dayOfWeek ?? 0) - (b.dayOfWeek ?? 0));
    const transactions = ctx.store.state.transactions.filter(item => item.obraId === obraId)
        .sort((a, b) => b.date.localeCompare(a.date));
    const documents = ctx.store.state.documents.filter(item => item.obraId === obraId)
        .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''));
    const chamados = ctx.store.state.chamados.filter(item => item.obraId === obraId)
        .sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''));

    const spent = transactions.reduce((sum, item) => sum + item.quantity * item.unitCost, 0);
    const progress = ctx.store.obraProgress(obraId);

    return layout(obra.name, escapeHtml(obra.city || 'Detalhes, cronograma e manutenção desta obra.'),
        '<button class="button button-light" data-route-link="obras">← Voltar para Obras</button>')
        + panel('Visão geral', `
            <div class="list-row">
                <div class="row-main">
                    <div class="row-title">${badge(obra.status, obraStatusLabel(obra.status))} Progresso do cronograma: <strong>${progress}%</strong></div>
                    <div class="row-meta">Orçamento: <strong>${money(obra.budget)}</strong> · Gasto: <strong>${money(spent)}</strong> · Saldo: <strong>${money(obra.budget - spent)}</strong></div>
                </div>
            </div>`)
        + panel('Gestão da obra', `
            <h3 class="detail-heading">Cronograma <button class="table-action" data-action="new-activity" data-preset-obra="${obraId}">+ Atividade</button></h3>
            <div class="data-list">${cronograma.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                    <div class="row-meta">Prazo ${formatDate(item.date)}${item.description ? ` · ${escapeHtml(item.description)}` : ''}</div></div>
                    ${badge(displayStatus(item), ACTIVITY_STATUS_LABELS[displayStatus(item)])}
                    <button class="table-action" data-toggle-status="${item.id}" title="Avançar status">Avançar</button>
                    <button class="table-action" data-delete="activity:${item.id}">Excluir</button>
                </div>`).join('') || '<div class="empty-state">Nenhuma atividade cadastrada.</div>'}</div>

            <h3 class="detail-heading">Manutenção recorrente <button class="table-action" data-action="new-maintenance" data-preset-obra="${obraId}">+ Manutenção</button></h3>
            <div class="data-list">${manutencoes.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                    <div class="row-meta">Toda ${WEEKDAY_LABELS[item.dayOfWeek] ?? '—'}${item.description ? ` · ${escapeHtml(item.description)}` : ''}</div></div>
                    ${badge(item.status, ACTIVITY_STATUS_LABELS[item.status])}
                    <button class="table-action" data-toggle-status="${item.id}" title="Avançar status">Avançar</button>
                    <button class="table-action" data-delete="activity:${item.id}">Excluir</button>
                </div>`).join('') || '<div class="empty-state">Nenhuma manutenção recorrente cadastrada.</div>'}</div>

            <h3 class="detail-heading">Financeiro <button class="table-action" data-action="new-transaction" data-preset-obra="${obraId}">+ Lançamento</button></h3>
            <div class="data-list">${transactions.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.description)}</div>
                    <div class="row-meta">${escapeHtml(categoryLabel(item.category))} · ${formatDate(item.date)} · ${item.quantity} × ${money(item.unitCost)}</div></div>
                    <strong>${money(item.quantity * item.unitCost)}</strong>
                    <button class="table-action" data-delete="transaction:${item.id}">Excluir</button>
                </div>`).join('') || '<div class="empty-state">Nenhum lançamento nesta obra.</div>'}</div>

            <h3 class="detail-heading">Documentos <button class="table-action" data-action="new-document" data-preset-obra="${obraId}">+ Arquivo</button></h3>
            <div class="data-list">${documents.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.name)}</div>
                    <div class="row-meta">${escapeHtml(item.type ?? 'Geral')} · Adicionado em ${formatDate(item.date)}</div></div>
                    ${item.path ? `<button class="table-action" data-download="${item.id}">Baixar</button>` : ''}
                    <button class="table-action" data-delete="document:${item.id}">Excluir</button>
                </div>`).join('') || '<div class="empty-state">Nenhum documento nesta obra.</div>'}</div>

            <h3 class="detail-heading">Chamados</h3>
            <div class="data-list">${chamados.map(item => `
                <div class="list-row">
                    <div class="row-main"><div class="row-title">${escapeHtml(item.title)}</div>
                    <div class="row-meta">${badge(item.priority)} · Aberto em ${item.date ? formatDateTime(item.date) : '—'}</div></div>
                    ${badge(item.status)}
                </div>`).join('') || '<div class="empty-state">Nenhum chamado nesta obra.</div>'}</div>`);
}

// Ações de cadastro/exclusão/download são tratadas pela delegação global do app.js
// (new-activity/new-maintenance/new-transaction/new-document, data-delete, data-download,
// data-toggle-status) — funcionam aqui exatamente como nas outras páginas.
export function bind() { }
